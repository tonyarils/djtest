<?php
/**
 * Excel 数据库批量执行工具 - 优化版
 * 
 * 新增功能:
 * - 超时控制
 * - 执行进度跟踪
 * - 安全检查
 * - 完善的资源管理
 * - 日志记录
 */

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// ==================== 配置常量 ====================
define('MAX_RESULT_ROWS', 100);        // 每个SQL最大返回行数
define('MAX_EXECUTION_TIME', 30);      // 单次SQL执行超时(秒)
define('MAX_TOTAL_TIME', 300);         // 总执行时间上限(秒)
define('MAX_ROWS', 1000);              // 最大处理行数
define('ENABLE_LOG', true);            // 是否启用日志
define('LOG_FILE', __DIR__ . '/logs/process.log');

// 运行时可覆盖的配置（来自表单，字段以 cfg_ 前缀）
$CFG_MAX_RESULT_ROWS = isset($_POST['cfg_max_result_rows']) ? intval($_POST['cfg_max_result_rows']) : MAX_RESULT_ROWS;
$CFG_MAX_EXECUTION_TIME = isset($_POST['cfg_max_execution_time']) ? intval($_POST['cfg_max_execution_time']) : MAX_EXECUTION_TIME;
$CFG_MAX_TOTAL_TIME = isset($_POST['cfg_max_total_time']) ? intval($_POST['cfg_max_total_time']) : MAX_TOTAL_TIME;
$CFG_MAX_ROWS = isset($_POST['cfg_max_rows']) ? intval($_POST['cfg_max_rows']) : MAX_ROWS;
$CFG_ENABLE_LOG = isset($_POST['cfg_enable_log']) ? true : ENABLE_LOG;

// ==================== 工具函数 ====================

/**
 * 重定向并显示错误
 */
function redirect_err(string $msg): void {
    header('Location: index.php?err=' . urlencode($msg));
    exit;
}
/**
 * 记录日志
 */
function log_message(string $level, string $message, array $context = []): void {
    global $CFG_ENABLE_LOG;
    $enableLog = isset($CFG_ENABLE_LOG) ? $CFG_ENABLE_LOG : ENABLE_LOG;
    if (!$enableLog) return;
    
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $line = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
    
    @file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/**
 * 安全检查SQL语句
 * @return array ['safe' => bool, 'warnings' => array]
 */
function check_sql_safety(string $sql): array {
    $warnings = [];
    $sqlUpper = strtoupper(trim($sql));
    
    // 危险操作检测
    $dangerousPatterns = [
        'DROP' => 'DROP 操作会删除数据表',
        'TRUNCATE' => 'TRUNCATE 操作会清空数据表',
        'DELETE' => 'DELETE 操作可能删除大量数据',
        'UPDATE' => 'UPDATE 操作可能修改大量数据',
        'ALTER' => 'ALTER 操作会修改表结构',
        'GRANT' => 'GRANT 操作会修改权限',
        'REVOKE' => 'REVOKE 操作会修改权限',
    ];
    
    foreach ($dangerousPatterns as $keyword => $warning) {
        if (preg_match('/\b' . $keyword . '\b/i', $sqlUpper)) {
            $warnings[] = $warning;
        }
    }
    
    return [
        'safe' => empty($warnings),
        'warnings' => $warnings
    ];
}

/**
 * 解析Excel文件并返回数据
 */
function parse_excel_file(string $filepath): array {
    $errors = [];
    
    if (!file_exists($filepath)) {
        return ['error' => '文件不存在'];
    }
    
    try {
        $spreadsheet = IOFactory::load($filepath);
    } catch (Exception $e) {
        return ['error' => '无法读取 Excel: ' . $e->getMessage()];
    }
    
    $sheet = $spreadsheet->getActiveSheet();
    $data = $sheet->toArray(null, true, true, true);
    
    if (count($data) < 1) {
        return ['error' => 'Excel 内容为空'];
    }
    
    // 解析表头
    $headerRow = $data[array_keys($data)[0]];
    $headerMap = [];
    foreach ($headerRow as $col => $val) {
        $name = strtolower(trim((string)$val));
        if ($name !== '') {
            $headerMap[$name] = $col;
        }
    }
    
    // 检查必要列
    $requiredColumns = ['host', 'servicename', 'port'];
    foreach ($requiredColumns as $need) {
        if (!isset($headerMap[$need])) {
            return ['error' => 'Excel 缺少必要列: ' . $need];
        }
    }
    
    return [
        'success' => true,
        'data' => $data,
        'headerMap' => $headerMap,
        'headerRow' => $headerRow
    ];
}

/**
 * 创建Oracle数据库连接
 */
function create_oracle_connection(string $host, string $port, string $serv, string $user, string $password) {
    $dsn = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST={$host})(PORT={$port}))(CONNECT_DATA=(SERVICE_NAME={$serv})))";
    // 检查 OCI8 函数是否存在，避免因扩展未安装导致致命错误
    if (!function_exists('oci_connect')) {
        return [
            'success' => false,
            'error' => 'OCI8 扩展不可用或未安装（oci_connect 未定义）',
            'error_code' => 'OCI_MISSING',
            'connection' => null
        ];
    }

    $conn = @oci_connect($user, $password, $dsn);

    if (!$conn) {
        $err = null;
        if (function_exists('oci_error')) {
            $err = @oci_error();
        }
        return [
            'success' => false,
            'error' => $err && isset($err['message']) ? $err['message'] : '连接失败',
            'error_code' => 'OCI_CONN_FAIL',
            'connection' => null
        ];
    }

    return [
        'success' => true,
        'error' => null,
        'error_code' => null,
        'connection' => $conn
    ];
}

/**
 * 创建数据库连接（Oracle/DM/Kingbase/OceanBase）
 */
function create_db_connection(string $dbType, string $host, string $port, string $serv, string $user, string $password) {
    global $CFG_MAX_EXECUTION_TIME;
    $dbTypeNorm = strtolower(trim($dbType));

    if ($dbTypeNorm === '' || in_array($dbTypeNorm, ['oracle', 'oci'], true)) {
        return create_oracle_connection($host, $port, $serv, $user, $password);
    }

    if (!extension_loaded('pdo')) {
        return [
            'success' => false,
            'error' => 'PDO 扩展未加载，无法连接 ' . $dbType,
            'error_code' => 'PDO_MISSING',
            'connection' => null
        ];
    }

    try {
        if (in_array($dbTypeNorm, ['dm', 'dameng'], true)) {
            $dsn = "dm:host={$host};port={$port};dbname={$serv}";
        } elseif (in_array($dbTypeNorm, ['kingbase', '人大金仓'], true)) {
            $dsn = "kdb:host={$host};port={$port};dbname={$serv}";
        } elseif ($dbTypeNorm === 'oceanbase') {
            // OceanBase MySQL协议优先
            $dsn = "mysql:host={$host};port={$port};dbname={$serv};charset=utf8";
        } else {
            return [
                'success' => false,
                'error' => '不支持的 db_type: ' . $dbType,
                'error_code' => 'UNSUPPORTED_DBTYPE',
                'connection' => null
            ];
        }

        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => max(1, intval($CFG_MAX_EXECUTION_TIME))
        ]);

        return [
            'success' => true,
            'error' => null,
            'error_code' => null,
            'connection' => $pdo
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'error_code' => 'PDO_CONN_FAIL',
            'connection' => null
        ];
    }
}

/**
 * 执行SQL并返回结果
 */
function execute_sql($conn, string $sql, ?int $timeout = null): array {
    global $CFG_MAX_RESULT_ROWS, $CFG_MAX_EXECUTION_TIME;

    $result = [
        'success' => false,
        'data' => [],
        'affected' => 0,
        'error' => null,
        'error_code' => null,
        'columns' => []
    ];
    
    // 检查 OCI8 必要函数
    if (!function_exists('oci_parse') || !function_exists('oci_execute')) {
        return [
            'success' => false,
            'data' => [],
            'affected' => 0,
            'error' => 'OCI8 扩展不可用或缺少必要函数',
            'error_code' => 'OCI_MISSING_FUNCS',
            'columns' => []
        ];
    }

    // 设置执行超时 (Oracle层面)
    // 注意: 实际超时控制需要数据库端配合

    if (is_resource($conn) || (is_object($conn) && get_class($conn) === 'OCI8')) {
        // 之前的 OCI8 逻辑（不变）
        $stid = @oci_parse($conn, $sql);
        if (!$stid) {
            $err = null;
            if (function_exists('oci_error')) {
                $err = @oci_error($conn);
            }
            $result['error'] = $err && isset($err['message']) ? $err['message'] : '解析 SQL 失败';
            $result['error_code'] = 'SQL_PARSE_ERROR';
            return $result;
        }
        
        // 检查语句类型
        $stmt_type = oci_statement_type($stid); // 返回类似 'SELECT','INSERT' 的字符串
        
        // 如果调用方没有传入 $timeout，则使用配置值
        if ($timeout === null) {
            $timeout = isset($CFG_MAX_EXECUTION_TIME) ? intval($CFG_MAX_EXECUTION_TIME) : MAX_EXECUTION_TIME;
        }

        // 如果 OCI8 支持，可以为单次调用设置超时（毫秒）
        $reset_call_timeout = false;
        if (function_exists('oci_set_call_timeout')) {
            try {
                $ms = max(1, intval($timeout * 1000));
                @oci_set_call_timeout($conn, $ms);
                $reset_call_timeout = true;
            } catch (Throwable $e) {
                // 忽略设置失败，继续执行
                log_message('DEBUG', 'oci_set_call_timeout 设置失败', ['msg' => $e->getMessage()]);
            }
        } else {
            log_message('DEBUG', 'oci_set_call_timeout 不可用，无法为单条 SQL 强制超时');
        }

        $exec = @oci_execute($stid, defined('OCI_COMMIT_ON_SUCCESS') ? OCI_COMMIT_ON_SUCCESS : null);
        if (!$exec) {
            $err = null;
            if (function_exists('oci_error')) {
                $err = @oci_error($stid);
            }
            $result['error'] = $err && isset($err['message']) ? $err['message'] : '执行失败';
            $result['error_code'] = 'SQL_EXEC_ERROR';
            if (function_exists('oci_free_statement')) {
                @oci_free_statement($stid);
            }
            // 重置 call timeout
            if ($reset_call_timeout && function_exists('oci_set_call_timeout')) {
                @oci_set_call_timeout($conn, 0);
            }
            return $result;
        }
        
        if (strcasecmp($stmt_type, 'SELECT') === 0) {
            // SELECT 语句，无论是否有行都需要设置列名
            $cols = [];
            if (function_exists('oci_num_fields') && function_exists('oci_field_name')) {
                $numCols = @oci_num_fields($stid);
                for ($ci = 1; $ci <= $numCols; $ci++) {
                    $cols[] = @oci_field_name($stid, $ci);
                }
            }
            $result['columns'] = $cols;
            
            $rowCount = 0;
            if (function_exists('oci_fetch_assoc')) {
                while ($r = @oci_fetch_assoc($stid)) {
                    $result['data'][] = $r;
                    $rowCount++;
                    if ($rowCount >= $CFG_MAX_RESULT_ROWS) {
                        log_message('INFO', '结果已达到最大行数限制', ['limit' => $CFG_MAX_RESULT_ROWS]);
                        break;
                    }
                }
            }
            $result['success'] = true;
            $result['error_code'] = null;
        } else {
            // 非SELECT 语句，创建执行结果列
            if (function_exists('oci_num_rows')) {
                $result['affected'] = @oci_num_rows($stid);
            } else {
                $result['affected'] = 0;
            }
            $result['columns'] = ['execution_status', 'affected_rows'];
            $result['success'] = true;
            $result['error_code'] = null;
        }

        if (function_exists('oci_free_statement')) {
            @oci_free_statement($stid);
        }
        // 重置 call timeout（如果之前设置过）
        if ($reset_call_timeout && function_exists('oci_set_call_timeout')) {
            @oci_set_call_timeout($conn, 0);
        }
        return $result;
    }

    // PDO 逻辑
    if ($conn instanceof PDO) {
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $stmtType = strtolower(substr(trim($sql), 0, 6));

            if (in_array($stmtType, ['select'], true)) {
                $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $rowCount = 0;
                $cols = [];
                foreach ($stmt->columnCount() ? range(0, $stmt->columnCount() - 1) : [] as $ci) {
                    $meta = $stmt->getColumnMeta($ci);
                    $cols[] = $meta['name'] ?? 'col' . ($ci + 1);
                }
                $result['columns'] = $cols;
                foreach ($all as $row) {
                    if ($rowCount >= $CFG_MAX_RESULT_ROWS) break;
                    $result['data'][] = $row;
                    $rowCount++;
                }
                $result['success'] = true;
                $result['error_code'] = null;
            } else {
                $aff = $stmt->rowCount();
                $result['affected'] = $aff;
                $result['columns'] = ['execution_status', 'affected_rows'];
                $result['success'] = true;
                $result['error_code'] = null;
            }
        } catch (PDOException $e) {
            $result['error'] = $e->getMessage();
            $result['error_code'] = 'PDO_SQL_ERROR';
            return $result;
        }
        return $result;
    }

    // 不支持的连接类型
    return [
        'success' => false,
        'data' => [],
        'affected' => 0,
        'error' => '未知数据库连接类型',
        'error_code' => 'UNKNOWN_CONN_TYPE',
        'columns' => []
    ];
}

/**
 * 处理单行数据
 */
function process_row(array $row, array $headerMap, string $sql, string $form_user, string $form_password): array {
    $host = (string)($row[$headerMap['host']] ?? '');
    $serv = (string)($row[$headerMap['servicename']] ?? '');
    $port = (string)($row[$headerMap['port']] ?? '');
    $dbType = 'oracle';
    if (isset($headerMap['dbtype']) && trim((string)($row[$headerMap['dbtype']] ?? '')) !== '') {
        $dbType = strtolower(trim((string)$row[$headerMap['dbtype']]));
    }

    // 优先使用行内凭据
    $user = $form_user;
    $password = $form_password;
    
    if (isset($headerMap['username']) && trim((string)($row[$headerMap['username']] ?? '')) !== '') {
        $user = (string)$row[$headerMap['username']];
    }
    if (isset($headerMap['password']) && trim((string)($row[$headerMap['password']] ?? '')) !== '') {
        $password = (string)$row[$headerMap['password']];
    }
    
    // 验证凭据
    if ($user === '' || $password === '') {
        return [
            'orig' => $row,
            'result' => null,
            'status' => 'fail',
            'message' => '缺少用户名或密码',
            'error_code' => 'MISSING_CREDENTIALS',
            'columns' => []
        ];
    }
    
    $connResult = create_db_connection($dbType, $host, $port, $serv, $user, $password);
    
    if (!$connResult['success']) {
        return [
            'orig' => $row,
            'result' => null,
            'status' => 'fail',
            'message' => $connResult['error'],
            'error_code' => isset($connResult['error_code']) ? $connResult['error_code'] : 'CONN_FAIL',
            'columns' => []
        ];
    }
    
    $conn = $connResult['connection'];
    
    try {
        $sqlResult = execute_sql($conn, $sql);
        
        if (!$sqlResult['success']) {
            return [
                'orig' => $row,
                'result' => null,
                'status' => 'fail',
                'message' => $sqlResult['error'],
                'error_code' => isset($sqlResult['error_code']) ? $sqlResult['error_code'] : 'SQL_EXEC_ERROR',
                'columns' => []
            ];
        }
        
        // 有返回数据
        if (!empty($sqlResult['data'])) {
            $outputs = [];
            foreach ($sqlResult['data'] as $dataRow) {
                $outputs[] = [
                    'orig' => $row,
                    'result' => $dataRow,
                    'status' => 'ok',
                    'message' => '',
                    'error_code' => null,
                    'columns' => $sqlResult['columns']
                ];
            }
            return ['multiple' => true, 'outputs' => $outputs];
        }
        
        // 检查是否为SELECT语句（通过columns判断）
        if (!in_array('execution_status', $sqlResult['columns'])) {
            // SELECT 语句但无数据
            return [
                'orig' => $row,
                'result' => null,
                'status' => 'ok',
                'message' => '查询成功，无数据',
                'error_code' => null,
                'columns' => $sqlResult['columns']
            ];
        }
        
        // DML/DDL 语句
        return [
            'orig' => $row,
            'result' => ['execution_status' => 'success', 'affected_rows' => $sqlResult['affected']],
            'status' => 'ok',
            'message' => "执行成功，影响行数: {$sqlResult['affected']}",
            'error_code' => null,
            'columns' => $sqlResult['columns']
        ];
        
    } finally {
        if (isset($conn) && $conn && function_exists('oci_close')) {
            @oci_close($conn);
        }
    }
}

/**
 * 生成Mock数据（测试用）
 */
function generate_mock_results(array $row, int $rowIndex): array {
    $mockData = [
        ['MOCK_COL1' => 'val1_' . $rowIndex, 'MOCK_COL2' => 'val2_' . $rowIndex],
        ['MOCK_COL1' => 'val1b_' . $rowIndex, 'MOCK_COL2' => 'val2b_' . $rowIndex],
    ];
    
    $outputs = [];
    foreach ($mockData as $data) {
        $outputs[] = [
            'orig' => $row,
            'result' => $data,
            'status' => 'ok',
            'message' => 'mock',
            'error_code' => null,
            'columns' => array_keys($data)
        ];
    }
    
    return ['multiple' => true, 'outputs' => $outputs];
}

/**
 * 导出结果到Excel
 */
function export_results(array $outputRows, array $resultColumns, array $origHeader): string {
    $outSpreadsheet = new Spreadsheet();
    $outSheet = $outSpreadsheet->getActiveSheet();
    
    // 构建表头
    $outHeader = $origHeader;
    foreach ($resultColumns as $cname) {
        $outHeader[] = $cname;
    }
    // 标准化额外输出列
    $outHeader[] = 'error_code';
    $outHeader[] = 'run_status';
    $outHeader[] = 'run_result';
    
    // 写入表头
    foreach ($outHeader as $c => $h) {
        $outSheet->setCellValueByColumnAndRow($c + 1, 1, $h);
    }
    
    // 写入数据行
    foreach ($outputRows as $i => $out) {
        $rIdx = $i + 2;
        $colIdx = 1;
        
        // 原始列
        foreach ($out['orig'] as $cell) {
            $outSheet->setCellValueByColumnAndRow($colIdx++, $rIdx, $cell);
        }
        
        // 结果列
        foreach ($resultColumns as $cname) {
            $val = '';
            if (!empty($out['result']) && array_key_exists($cname, $out['result'])) {
                $val = $out['result'][$cname];
            }
            $outSheet->setCellValueByColumnAndRow($colIdx++, $rIdx, $val);
        }
        
        // 错误码与状态列
        $errCode = isset($out['error_code']) ? $out['error_code'] : '';
        $outSheet->setCellValueByColumnAndRow($colIdx++, $rIdx, $errCode);
        $outSheet->setCellValueByColumnAndRow($colIdx++, $rIdx, $out['status']);
        $outSheet->setCellValueByColumnAndRow($colIdx++, $rIdx, $out['message']);
    }
    
    // 保存到临时文件
    $outFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'results_' . time() . '.xlsx';
    $writer = new Xlsx($outSpreadsheet);
    $writer->save($outFile);
    
    return $outFile;
}

// ==================== 主程序 ====================

log_message('INFO', '开始处理请求');

// 验证文件上传
if (empty($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
    log_message('ERROR', '文件上传失败');
    redirect_err('请上传 Excel 文件');
}

// 获取参数
$sql = isset($_POST['sql']) ? trim($_POST['sql']) : '';
if ($sql === '') {
    redirect_err('请提供要执行的 SQL 脚本');
}

$form_user = isset($_POST['username']) ? trim($_POST['username']) : '';
$form_password = isset($_POST['password']) ? trim($_POST['password']) : '';
$isMock = isset($_POST['mock']) && $_POST['mock'] == '1';

// 安全检查
$safetyCheck = check_sql_safety($sql);
if (!$safetyCheck['safe']) {
    log_message('WARN', 'SQL包含危险操作', ['warnings' => $safetyCheck['warnings']]);
    // 可以选择: 显示警告让用户确认，或直接拒绝
    // 这里我们记录警告但继续执行（实际生产环境建议更严格的控制）
}

// 保存上传文件
$tmpfname = sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($_FILES['excel']['tmp_name']) . '_' . time();
if (!move_uploaded_file($_FILES['excel']['tmp_name'], $tmpfname)) {
    if (!copy($_FILES['excel']['tmp_name'], $tmpfname)) {
        redirect_err('无法保存上传的文件');
    }
}

log_message('INFO', '文件已保存', ['path' => $tmpfname]);

// 解析Excel
$parsed = parse_excel_file($tmpfname);
if (isset($parsed['error'])) {
    @unlink($tmpfname);
    redirect_err($parsed['error']);
}

$data = $parsed['data'];
$headerMap = $parsed['headerMap'];
$headerRow = $parsed['headerRow'];

log_message('INFO', 'Excel解析完成', ['rows' => count($data) - 1]);

// 处理数据行
$rows = array_values($data);
if (count($rows) - 1 > $CFG_MAX_ROWS) {
    @unlink($tmpfname);
    redirect_err('数据行数超过限制 (最大 ' . $CFG_MAX_ROWS . ' 行)');
}

$outputRows = [];
$resultColumns = [];
$startTime = microtime(true);
$processedCount = 0;
$errorCount = 0;
// 按错误码统计
$errorCounts = [];
// 当前正在连接的数据库（host:port/serv）
$currentDb = '';
// 可轮询的状态文件（basename 将返回给客户端用于轮询）
// 支持客户端指定 status_token，使前端可在提交后立即知道轮询的文件名
$statusToken = '';
if (!empty($_POST['status_token'])) {
    $statusToken = preg_replace('/[^A-Za-z0-9._-]/', '', $_POST['status_token']);
}

if ($statusToken !== '') {
    $statusBasename = 'process_status_' . $statusToken . '.json';
} else {
    $statusBasename = 'process_status_' . preg_replace('/[^a-z0-9._-]/i', '', uniqid('', true)) . '.json';
}
$statusFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $statusBasename;
// 写入初始状态
$initialStatus = [
    'processed' => 0,
    'total' => count($rows) - 1,
    'success' => 0,
    'failed' => 0,
    'current_db' => '',
    'error_counts' => new stdClass(),
    'done' => false,
    'timestamp' => time()
];
@file_put_contents($statusFile, json_encode($initialStatus, JSON_UNESCAPED_UNICODE));

// 进度跟踪数组
$progress = [
    'total' => count($rows) - 1,
    'processed' => 0,
    'success' => 0,
    'failed' => 0
];

for ($i = 1; $i < count($rows); $i++) {
    // 检查总超时：如果超过总时限，则跳过当前行并继续（记录错误码），以保证遍历所有行后程序结束
        if ((microtime(true) - $startTime) > $CFG_MAX_TOTAL_TIME) {
        log_message('WARN', '总执行时间已超过限制，跳过当前行', ['processed' => $processedCount]);
        $outputRows[] = [
            'orig' => $rows[$i],
            'result' => null,
            'status' => 'timeout',
            'message' => '总执行时间已超过限制，跳过该库',
            'error_code' => 'TIMEOUT_TOTAL',
            'columns' => []
        ];
        $errorCount++;
        // 统计错误码
        if (!isset($errorCounts['TIMEOUT_TOTAL'])) $errorCounts['TIMEOUT_TOTAL'] = 0;
        $errorCounts['TIMEOUT_TOTAL']++;
        // 标记为失败并推进进度
        $progress['failed']++;
        $processedCount++;
        $progress['processed'] = $processedCount;

        // 更新状态文件以便前端轮询
        if (isset($statusFile)) {
            $statusData = [
                'processed' => $progress['processed'],
                'total' => $progress['total'],
                'success' => $progress['success'],
                'failed' => $progress['failed'],
                'current_db' => $currentDb,
                'error_counts' => $errorCounts,
                'done' => false,
                'timestamp' => time()
            ];
            @file_put_contents($statusFile, json_encode($statusData, JSON_UNESCAPED_UNICODE));
        }

        // 继续处理下一行，而不是中断整个脚本
        continue;
    }
    
    $row = $rows[$i];
    // 更新当前正在处理的数据库信息（用于返回给客户端显示）
    $hostDisp = (string)($row[$headerMap['host']] ?? '');
    $servDisp = (string)($row[$headerMap['servicename']] ?? '');
    $portDisp = (string)($row[$headerMap['port']] ?? '');
    $currentDb = trim($hostDisp) !== '' ? ($hostDisp . ':' . $portDisp . '/' . $servDisp) : '';
    
    // Mock模式
    if ($isMock) {
        $result = generate_mock_results($row, $i);
    } else {
        $result = process_row($row, $headerMap, $sql, $form_user, $form_password);
    }
    
    // 处理结果
    if (isset($result['multiple']) && $result['multiple']) {
        foreach ($result['outputs'] as $output) {
            $outputRows[] = $output;
            // 统计错误码
            $ecode = isset($output['error_code']) ? $output['error_code'] : null;
            if ($ecode) {
                if (!isset($errorCounts[$ecode])) $errorCounts[$ecode] = 0;
                $errorCounts[$ecode]++;
            }
            foreach ($output['columns'] as $cname) {
                if (!in_array($cname, $resultColumns, true)) {
                    $resultColumns[] = $cname;
                }
            }
        }
        $progress['success']++;
    } else {
        $outputRows[] = $result;
        // 统计错误码（单条）
        $ecode = isset($result['error_code']) ? $result['error_code'] : null;
        if ($ecode) {
            if (!isset($errorCounts[$ecode])) $errorCounts[$ecode] = 0;
            $errorCounts[$ecode]++;
        }
        foreach ($result['columns'] as $cname) {
            if (!in_array($cname, $resultColumns, true)) {
                $resultColumns[] = $cname;
            }
        }
        
        if ($result['status'] === 'ok') {
            $progress['success']++;
        } else {
            $progress['failed']++;
            $errorCount++;
        }
    }
    
    $processedCount++;
    $progress['processed'] = $processedCount;
    // 更新状态文件，便于前端轮询
    $statusData = [
        'processed' => $progress['processed'],
        'total' => $progress['total'],
        'success' => $progress['success'],
        'failed' => $progress['failed'],
        'current_db' => $currentDb,
        'error_counts' => $errorCounts,
        'done' => false,
        'timestamp' => time()
    ];
    @file_put_contents($statusFile, json_encode($statusData, JSON_UNESCAPED_UNICODE));
}

log_message('INFO', '处理完成', [
    'processed' => $progress['processed'],
    'success' => $progress['success'],
    'failed' => $progress['failed'],
    'duration' => round(microtime(true) - $startTime, 2) . 's'
]);

// 导出结果
$outFile = export_results($outputRows, $resultColumns, array_values($headerRow));

// 发送文件
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="results.xlsx"');
header('Content-Length: ' . filesize($outFile));
header('X-Process-Stats: ' . json_encode($progress));
// 返回每种错误码的汇总（error_code => count）
header('X-Process-Error-Codes: ' . json_encode($errorCounts));
// 返回最终进度（分数形式）和当前/最后一个连接的数据库
header('X-Process-Progress: ' . $progress['processed'] . '/' . $progress['total']);
header('X-Process-Current-DB: ' . $currentDb);
// 将状态文件名返回给客户端，客户端可通过轮询该文件读取进度
header('X-Process-Status-File: ' . basename($statusFile));

// 写入最终状态（done）到状态文件
$finalStatus = [
    'processed' => $progress['processed'],
    'total' => $progress['total'],
    'success' => $progress['success'],
    'failed' => $progress['failed'],
    'current_db' => $currentDb,
    'error_counts' => $errorCounts,
    'done' => true,
    'timestamp' => time()
];
@file_put_contents($statusFile, json_encode($finalStatus, JSON_UNESCAPED_UNICODE));

readfile($outFile);

// 清理临时文件
@unlink($outFile);
@unlink($tmpfname);

log_message('INFO', '结果已发送');
exit;