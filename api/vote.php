<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';

// 議員或主辦人（測試用）才能投票
if (!in_array(current_role(), ['member','admin','host'])) json_err('Unauthorized', 401);

$meeting_id    = (int)($_POST['meeting_id'] ?? 0);
$agenda_item_id = (int)($_POST['agenda_item_id'] ?? 0);
$member_id     = (int)($_POST['member_id'] ?? 0);
$vote          = $_POST['vote'] ?? '';

if (!in_array($vote, ['yes','no','abstain'])) json_err('Invalid vote value');
if (!$meeting_id || !$agenda_item_id || !$member_id) json_err('Missing params');

$pdo = db();

// 確認此案由狀態為 open
$item = $pdo->prepare("SELECT status, type FROM agenda_items WHERE id=? AND meeting_id=?");
$item->execute([$agenda_item_id, $meeting_id]);
$item = $item->fetch();
if (!$item || $item['type'] !== 'resolution') json_err('Invalid agenda item');
if ($item['status'] === 'closed') json_err('表決已截止');

// 確認議員屬於此會議且為 attendee
$member = $pdo->prepare("SELECT id, type FROM members WHERE id=? AND meeting_id=?");
$member->execute([$member_id, $meeting_id]);
$member = $member->fetch();
if (!$member) json_err('議員不在名單中');
if ($member['type'] !== 'attendee') json_err('列席人員無表決權');

// 確認尚未投票
$existing = $pdo->prepare("SELECT id FROM votes WHERE agenda_item_id=? AND member_id=?");
$existing->execute([$agenda_item_id, $member_id]);
if ($existing->fetch()) json_err('您已投過票，不可更改');

// 寫入
$pdo->prepare(
    "INSERT INTO votes (agenda_item_id, member_id, vote) VALUES (?,?,?)"
)->execute([$agenda_item_id, $member_id, $vote]);

log_event($meeting_id, 'vote_cast', [
    'member_id'      => $member_id,
    'agenda_item_id' => $agenda_item_id,
    'vote'           => $vote,
]);

json_ok(['vote' => $vote]);
