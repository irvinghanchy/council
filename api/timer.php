<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_host();

$action     = $_POST['action'] ?? '';
$meeting_id = (int)($_POST['meeting_id'] ?? 0);
if (!$meeting_id) json_err('Missing meeting_id');

$pdo = db();

if ($action === 'start') {
    $seconds = max(1, (int)($_POST['seconds'] ?? 60));
    $label   = trim($_POST['label'] ?? $seconds . '秒');
    $end_at  = date('Y-m-d H:i:s', time() + $seconds);
    $pdo->prepare(
        "UPDATE phase_control SET timer_end_at=?, timer_label=? WHERE meeting_id=?"
    )->execute([$end_at, $label, $meeting_id]);
    json_ok(['end_at' => $end_at]);
}

if ($action === 'stop') {
    $pdo->prepare(
        "UPDATE phase_control SET timer_end_at=NULL, timer_label=NULL WHERE meeting_id=?"
    )->execute([$meeting_id]);
    json_ok();
}

json_err('Unknown action');