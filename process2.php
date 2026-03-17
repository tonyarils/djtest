<?php
require __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function redirect_err($msg) {
    header('Location: index.php?err=' . urlencode($msg));
    exit;
}

if (empty($_FILES['excel']) || $_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
    redirect_err('请上传 Excel 文件');
}

$sql = isset($_POST['sql']) ? trim($_POST['sql']) : '';
if ($sql === '') {
    redirect_err('请提供要执行的 SQL 脚本');
}

$form_user = isset($_POST['username']) ? trim($_POST['username']) : '';
$form_password = isset($_POST['password']) ? trim($_POST['password']) : '';

$tmpfname = sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($_FILES['excel']['tmp_name']) . '_' . time();
if (!move_uploaded_file($_FILES['excel']['tmp_name'], $tmpfname)) {
    // Some PHP setups don't allow move_uploaded_file for certain streams; try copy
    if (!copy($_FILES['excel']['tmp_name'], $tmpfname)) {
        redirect_err('无法保存上传的文件');
    }
}

try {
    $spreadsheet = IOFactory::load($tmpfname);
} catch (Exception $e) {
    @unlink($tmpfname);
    redirect_err('无法读取 Excel: ' . $e->getMessage());
}

$sheet = $spreadsheet->getActiveSheet();
$data = $sheet->toArray(null, true, true, true);
if (count($data) < 1) {
    @unlink($tmpfname);
    redirect_err('Excel 内容为空');
}

// header row is first row
$headerRow = $data[array_keys($data)[0]]; // e.g. ['A' => 'host', 'B' => 'servicename', ...]
// normalize header: map lowercased name => column letter
$headerMap = [];
foreach ($headerRow as $col => $val) {
    $name = strtolower(trim((string)$val));
    if ($name !== '') $headerMap[$name] = $col;
}

foreach (['host', 'servicename', 'port'] as $need) {
    if (!isset($headerMap[$need])) {
        @unlink($tmpfname);
        redirect_err('Excel 缺少必要列: ' . $need);
    }
}

$results = [];

// iterate data rows (skip header)
$rows = array_values($data);

// We'll build output rows: each input row may produce multiple output rows (one per fetched SQL row)
$outputRows = [];
$resultColumns = [];

for ($i = 1; $i < count($rows); $i++) {
    $row = $rows[$i];
    $host = (string)($row[$headerMap['host']] ?? '');
    $serv = (string)($row[$headerMap['servicename']] ?? '');
    $port = (string)($row[$headerMap['port']] ?? '');

    $user = $form_user;
    $password = $form_password;
    if (isset($headerMap['username']) && trim((string)$row[$headerMap['username']]) !== '') {
        $user = (string)$row[$headerMap['username']];
    }
    if (isset($headerMap['password']) && trim((string)$row[$headerMap['password']]) !== '') {
        $password = (string)$row[$headerMap['password']];
    }

    if ($user === '' || $password === '') {
        $outputRows[] = ['orig' => $row, 'result' => null, 'status' => 'fail', 'message' => '缺少用户名或密码（可在表中增加 username/password 列，或在表单填写）'];
        continue;
    }

    // If mock mode is requested, generate fake results for testing
    if (isset($_POST['mock']) && $_POST['mock'] == '1') {
        // produce two fake rows as example
        $rowsFetched = [
            ['MOCK_COL1' => 'val1_' . ($i), 'MOCK_COL2' => 'val2_' . ($i)],
            ['MOCK_COL1' => 'val1b_' . ($i), 'MOCK_COL2' => 'val2b_' . ($i)],
        ];
        foreach ($rowsFetched as $rf) {
            $outputRows[] = ['orig' => $row, 'result' => $rf, 'status' => 'ok', 'message' => 'mock'];
            foreach (array_keys($rf) as $cname) {
                if (!in_array($cname, $resultColumns, true)) $resultColumns[] = $cname;
            }
        }
        continue;
    }

    // Build DSN for oci_connect
    $dsn = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST={$host})(PORT={$port}))(CONNECT_DATA=(SERVICE_NAME={$serv})))";

    $conn = @oci_connect($user, $password, $dsn);
    if (!$conn) {
        $err = oci_error();
        $outputRows[] = ['orig' => $row, 'result' => null, 'status' => 'fail', 'message' => ($err ? $err['message'] : '连接失败')];
        continue;
    }

    try {
        $stid = @oci_parse($conn, $sql);
        if (!$stid) {
            $err = oci_error($conn);
            $outputRows[] = ['orig' => $row, 'result' => null, 'status' => 'fail', 'message' => ($err ? $err['message'] : '解析 SQL 失败')];
            oci_close($conn);
            continue;
        }

        $exec = @oci_execute($stid, OCI_COMMIT_ON_SUCCESS);
        if (!$exec) {
            $err = oci_error($stid);
            $outputRows[] = ['orig' => $row, 'result' => null, 'status' => 'fail', 'message' => ($err ? $err['message'] : '执行失败')];
            oci_free_statement($stid);
            oci_close($conn);
            continue;
        }

        // Attempt to fetch first row to detect SELECT
        $first = oci_fetch_assoc($stid);
        if ($first !== false) {
            $rowsFetched = [];
            $rowsFetched[] = $first;
            while ($r = oci_fetch_assoc($stid)) {
                $rowsFetched[] = $r;
                if (count($rowsFetched) >= 100) break;
            }
            // for each fetched row, create an output row and record columns
            foreach ($rowsFetched as $rf) {
                $outputRows[] = ['orig' => $row, 'result' => $rf, 'status' => 'ok', 'message' => ''];
                foreach (array_keys($rf) as $cname) {
                    if (!in_array($cname, $resultColumns, true)) $resultColumns[] = $cname;
                }
            }
        } else {
            $affected = oci_num_rows($stid);
            $outputRows[] = ['orig' => $row, 'result' => null, 'status' => 'ok', 'message' => "Statement executed, affected rows={$affected}"];
        }

        oci_free_statement($stid);
    } catch (Exception $e) {
        $outputRows[] = ['orig' => $row, 'result' => null, 'status' => 'fail', 'message' => $e->getMessage()];
    }

    oci_close($conn);
}

// Build result spreadsheet: include original header, result columns, then run_status/run_result
$outSpreadsheet = new Spreadsheet();
$outSheet = $outSpreadsheet->getActiveSheet();

// prepare header (original header values)
$origHeader = array_values($rows[0]);
$outHeader = $origHeader;
// append result columns discovered
foreach ($resultColumns as $cname) {
    $outHeader[] = $cname;
}
$outHeader[] = 'run_status';
$outHeader[] = 'run_result';

// write header
foreach ($outHeader as $c => $h) {
    $outSheet->setCellValueByColumnAndRow($c+1, 1, $h);
}

// write output rows (expanded)
for ($i = 0; $i < count($outputRows); $i++) {
    $out = $outputRows[$i];
    $row = $out['orig'];
    $rIdx = $i + 2; // data starts at row 2
    // write original columns in same order
    $colIdx = 1;
    foreach ($row as $cell) {
        $outSheet->setCellValueByColumnAndRow($colIdx, $rIdx, $cell);
        $colIdx++;
    }
    // write result columns
    if (!empty($resultColumns)) {
        foreach ($resultColumns as $cname) {
            $val = '';
            if (!empty($out['result']) && array_key_exists($cname, $out['result'])) {
                $val = $out['result'][$cname];
            }
            $outSheet->setCellValueByColumnAndRow($colIdx, $rIdx, $val);
            $colIdx++;
        }
    }
    // run_status and run_result
    $outSheet->setCellValueByColumnAndRow($colIdx++, $rIdx, $out['status']);
    $outSheet->setCellValueByColumnAndRow($colIdx++, $rIdx, $out['message']);
}

$outFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'results_' . time() . '.xlsx';
$writer = new Xlsx($outSpreadsheet);
$writer->save($outFile);

// send file
header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="results.xlsx"');
header('Content-Length: ' . filesize($outFile));
readfile($outFile);

@unlink($outFile);
@unlink($tmpfname);
exit;
