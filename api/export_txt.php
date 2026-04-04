<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_host();

$meeting_id = (int)($_GET['meeting_id'] ?? 0);
if (!$meeting_id) json_err('Missing meeting_id');

$txt = export_txt($meeting_id);

// 取得會議名稱當檔名
$name = db()->prepare("SELECT title FROM meeting WHERE id=?");
$name->execute([$meeting_id]);
$name = $name->fetchColumn() ?: 'meeting';
$filename = preg_replace('/[^\w\x{4e00}-\x{9fff}]/u', '_', $name) . '_紀錄.txt';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $txt;
