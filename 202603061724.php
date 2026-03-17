/**
 * 执行SQL并返回结果
 */
function execute_sql($conn, string $sql, int $timeout = MAX_EXECUTION_TIME): array
{
    $result = [
        'success' => false,
        'data' => [],
        'affected' => 0,
        'error' => null,
        'columns' => []
    ];

    // 解析 SQL
    $stid = @oci_parse($conn, $sql);
    if (!$stid) {
        $err = oci_error($conn);
        $result['error'] = $err ? $err['message'] : '解析 SQL 失败';
        return $result;
    }

    // 获取语句类型
    $stmt_type = oci_statement_type($stid);

    // 执行 SQL
    $exec = @oci_execute($stid, OCI_COMMIT_ON_SUCCESS);
    if (!$exec) {
        $err = oci_error($stid);
        $result['error'] = $err ? $err['message'] : '执行失败';
        @oci_free_statement($stid);
        return $result;
    }

    // 【关键修复点】使用正确的常量 OCI_STATEMENT_SELECT
    if ($stmt_type === OCI_STATEMENT_SELECT) {
        // SELECT 语句处理
        $first = oci_fetch_assoc($stid);
        if ($first !== false) {
            $result['data'][] = $first;
            $result['columns'] = array_keys($first);
            $rowCount = 1;
            while ($r = oci_fetch_assoc($stid)) {
                $result['data'][] = $r;
                $rowCount++;
                if ($rowCount >= MAX_RESULT_ROWS) {
                    log_message('INFO', '结果已达到最大行数限制', ['limit' => MAX_RESULT_ROWS]);
                    break;
                }
            }
        }
        $result['success'] = true;
    } else {
        // 非 SELECT 语句 (INSERT, UPDATE, DELETE 等)
        $result['affected'] = oci_num_rows($stid);
        $result['columns'] = ['execution_status', 'affected_rows'];
        $result['success'] = true;
    }

    @oci_free_statement($stid);
    return $result;
}
