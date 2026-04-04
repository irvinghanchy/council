<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// 大螢幕 PIN 或已登入者均可取得狀態
$pin_ok = (isset($_GET['pin']) && $_GET['pin'] === SCREEN_PIN);
$logged_in = in_array(current_role(), ['admin','host','member','observer']);
if (!$pin_ok && !$logged_in) {
    json_err('Unauthorized', 401);
}

$meeting_id = (int)($_GET['meeting_id'] ?? 0);
if (!$meeting_id) {
    $m = active_meeting();
    $meeting_id = $m ? $m['id'] : 0;
}
if (!$meeting_id) json_err('No active meeting');

json_ok(build_status($meeting_id));
