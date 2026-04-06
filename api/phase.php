<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_host();

$meeting_id    = (int)($_POST['meeting_id'] ?? 0);
$type          = $_POST['type'] ?? '';
$agenda_item_id = !empty($_POST['agenda_item_id']) ? (int)$_POST['agenda_item_id'] : null;
$allowed_types = ['standby','agenda','resolution','election','temp_motion','ended'];
if (!$meeting_id || !in_array($type, $allowed_types)) json_err('Invalid params');

$pdo = db();

// 若切換到 resolution/election/agenda，把 agenda_item 狀態設為 open（若尚未 closed）
if ($agenda_item_id && in_array($type, ['agenda','resolution','election'])) {
    $pdo->prepare(
        "UPDATE agenda_items SET status='open' WHERE id=? AND status='pending'"
    )->execute([$agenda_item_id]);
}

// 若切換離開某個 open 的議程（換到別項），不自動關閉，讓截止按鈕控制

if ($type === 'standby') {
    $pdo->prepare(
        "UPDATE meeting SET status='active', actual_start_at=NOW() WHERE id=? AND status='preparing'"
    )->execute([$meeting_id]);
}

// 若結束會議，同步更新 meeting status
if ($type === 'ended') {
    $pdo->prepare(
        "UPDATE meeting SET status='ended', actual_end_at=NOW() WHERE id=?"
    )->execute([$meeting_id]);
}

set_phase($meeting_id, $type, $agenda_item_id);

// 取得即將切換到的 item 標題供 log 用
$item_title = '';
if ($agenda_item_id) {
    $r = $pdo->prepare("SELECT title FROM agenda_items WHERE id=?");
    $r->execute([$agenda_item_id]);
    $item_title = $r->fetchColumn() ?: '';
}

log_event($meeting_id, 'phase_changed', [
    'to'            => $type,
    'agenda_item_id'=> $agenda_item_id,
    'item_title'    => $item_title,
]);

json_ok(['phase' => $type, 'agenda_item_id' => $agenda_item_id]);
