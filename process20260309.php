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
    if (!ENABLE_LOG) return;
    
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
    
    $conn = @oci_connect($user, $password, $dsn);
    
    if (!$conn) {
        $err = oci_error();
        return [
            'success' => false,
            'error' => $err ? $err['message'] : '连接失败',
            'connection' => null
        ];
    }
    
    return [
        'success' => true,
        'error' => null,
        'connection' => $conn
    ];
}

/**
 * 执行SQL并返回结果
 */
function execute_sql($conn, string $sql, int $timeout = MAX_EXECUTION_TIME): array {
    $result = [
        'success' => false,
        'data' => [],
        'affected' => 0,
        'error' => null,
        'columns' => []
    ];
    
    // 设置执行超时 (Oracle层面)
    // 注意: 实际超时控制需要数据库端配合
    
    $stid = @oci_parse($conn, $sql);
    if (!$stid) {
        $err = oci_error($conn);
        $result['error'] = $err ? $err['message'] : '解析 SQL 失败';
        return $result;
    }
    
    // 检查语句类型
    $stmt_type = oci_statement_type($stid); // 返回类似 'SELECT','INSERT' 的字符串
    
    $exec = @oci_execute($stid, OCI_COMMIT_ON_SUCCESS);
    if (!$exec) {
        $err = oci_error($stid);
        $result['error'] = $err ? $err['message'] : '执行失败';
        oci_free_statement($stid);
        return $result;
    }
    
    if (strcasecmp($stmt_type, 'SELECT') === 0) {
        // SELECT 语句，无论是否有行都需要设置列名
        $cols = [];
        $numCols = oci_num_fields($stid);
        for ($ci = 1; $ci <= $numCols; $ci++) {
            $cols[] = oci_field_name($stid, $ci);
        }
        $result['columns'] = $cols;
        
        $rowCount = 0;
        while ($r = oci_fetch_assoc($stid)) {
            $result['data'][] = $r;
            $rowCount++;
            if ($rowCount >= MAX_RESULT_ROWS) {
                log_message('INFO', '结果已达到最大行数限制', ['limit' => MAX_RESULT_ROWS]);
                break;
            }
        }
        $result['success'] = true;
    } else {
        // 非SELECT 语句，创建执行结果列
        $result['affected'] = oci_num_rows($stid);
        $result['columns'] = ['execution_status', 'affected_rows'];
        $result['success'] = true;
    }
    
    oci_free_statement($stid);
    return $result;
}

/**
 * 处理单行数据
 */
function process_row(array $row, array $headerMap, string $sql, string $form_user, string $form_password): array {
    $host = (string)($row[$headerMap['host']] ?? '');
    $serv = (string)($row[$headerMap['servicename']] ?? '');
    $port = (string)($row[$headerMap['port']] ?? '');
    
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
            'columns' => []
        ];
    }
    
    // 创建连接
    $connResult = create_oracle_connection($host, $port, $serv, $user, $password);
    
    if (!$connResult['success']) {
        return [
            'orig' => $row,
            'result' => null,
            'status' => 'fail',
            'message' => $connResult['error'],
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
                'columns' => $sqlResult['columns']
            ];
        }
        
        // DML/DDL 语句
        return [
            'orig' => $row,
            'result' => ['execution_status' => 'success', 'affected_rows' => $sqlResult['affected']],
            'status' => 'ok',
            'message' => "执行成功，影响行数: {$sqlResult['affected']}",
            'columns' => $sqlResult['columns']
        ];
        
    } finally {
        oci_close($conn);
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
        
        // 状态列
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
if (count($rows) - 1 > MAX_ROWS) {
    @unlink($tmpfname);
    redirect_err('数据行数超过限制 (最大 ' . MAX_ROWS . ' 行)');
}

$outputRows = [];
$resultColumns = [];
$startTime = microtime(true);
$processedCount = 0;
$errorCount = 0;

// 进度跟踪数组
$progress = [
    'total' => count($rows) - 1,
    'processed' => 0,
    'success' => 0,
    'failed' => 0
];

for ($i = 1; $i < count($rows); $i++) {
    // 检查总超时
    if ((microtime(true) - $startTime) > MAX_TOTAL_TIME) {
        log_message('WARN', '执行超时，已中断', ['processed' => $processedCount]);
        $outputRows[] = [
            'orig' => $rows[$i],
            'result' => null,
            'status' => 'timeout',
            'message' => '执行超时，已中断',
            'columns' => []
        ];
        $errorCount++;
        break;
    }
    
    $row = $rows[$i];
    
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
            foreach ($output['columns'] as $cname) {
                if (!in_array($cname, $resultColumns, true)) {
                    $resultColumns[] = $cname;
                }
            }
        }
        $progress['success']++;
    } else {
        $outputRows[] = $result;
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

readfile($outFile);

// 清理临时文件
@unlink($outFile);
@unlink($tmpfname);

log_message('INFO', '结果已发送');
exit;