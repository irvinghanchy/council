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

$data = build_status($meeting_id);

// 若是議員，回傳其在目前選舉的投票狀態
if (isset($_SESSION['member_id']) && $data['phase']['phase_type'] === 'election' && !empty($data['election'])) {
    $chk = db()->prepare(
        "SELECT COUNT(*) FROM election_votes WHERE election_id=? AND member_id=?"
    );
    $chk->execute([$data['election']['id'], (int)$_SESSION['member_id']]);
    $data['my_election_voted'] = (int)$chk->fetchColumn() > 0;
} else {
    $data['my_election_voted'] = false;
}

json_ok($data);

json_ok(build_status($meeting_id));
