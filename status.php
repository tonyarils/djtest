<?php
// status.php — 安全返回 process 状态文件内容（供前端轮询）
// 使用: status.php?file=process_status_<uniq>.json

// 简单速率/输入保护
if (empty($_GET['file'])) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'missing file']);
    exit;
}

$basename = basename($_GET['file']);
// 限制只允许 process_status_ 前缀和 .json 后缀，防止目录遍历
if (!preg_match('/^process_status_[A-Za-z0-9._-]+\.json$/', $basename)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'invalid file']);
    exit;
}

$tempDir = sys_get_temp_dir();
$path = $tempDir . DIRECTORY_SEPARATOR . $basename;

if (!file_exists($path)) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'not found']);
    exit;
}

// 只读并直接返回文件内容（文件本身为 JSON）
$content = @file_get_contents($path);
if ($content === false) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'read failed']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
// 可选: 设置较短的过期，避免浏览器缓存
header('Expires: 0');

echo $content;
