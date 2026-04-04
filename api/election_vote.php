<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (!in_array(current_role(), ['member','admin','host'])) json_err('Unauthorized', 401);

$meeting_id     = (int)($_POST['meeting_id'] ?? 0);
$agenda_item_id = (int)($_POST['agenda_item_id'] ?? 0);
$member_id      = (int)($_POST['member_id'] ?? 0);
$candidate_ids  = array_filter(array_map('intval', explode(',', $_POST['candidate_ids'] ?? '')));

if (!$meeting_id || !$agenda_item_id || !$member_id || empty($candidate_ids)) {
    json_err('Missing params');
}

$pdo = db();

// 確認 agenda_item
$item = $pdo->prepare("SELECT status, type FROM agenda_items WHERE id=? AND meeting_id=?");
$item->execute([$agenda_item_id, $meeting_id]);
$item = $item->fetch();
if (!$item || $item['type'] !== 'election') json_err('Invalid agenda item');
if ($item['status'] === 'closed') json_err('選舉已截止');

// 取得 election 與席次
$election = $pdo->prepare("SELECT * FROM elections WHERE agenda_item_id=?");
$election->execute([$agenda_item_id]);
$election = $election->fetch();
if (!$election) json_err('選舉設定不存在');

// 驗證票數不超過席次（單記不可讓渡制）
if (count($candidate_ids) > $election['seats']) {
    json_err("超過票數上限（應選 {$election['seats']} 席，您選了 " . count($candidate_ids) . ' 人）');
}

// 確認議員合法
$member = $pdo->prepare("SELECT id, type FROM members WHERE id=? AND meeting_id=?");
$member->execute([$member_id, $meeting_id]);
$member = $member->fetch();
if (!$member || $member['type'] !== 'attendee') json_err('無投票資格');

// 確認尚未在此選舉投票
$already = $pdo->prepare("SELECT COUNT(*) FROM election_votes WHERE election_id=? AND member_id=?");
$already->execute([$election['id'], $member_id]);
if ((int)$already->fetchColumn() > 0) json_err('您已投過票，不可更改');

// 確認所有 candidate_id 屬於此選舉
$placeholders = implode(',', array_fill(0, count($candidate_ids), '?'));
$valid = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE election_id=? AND id IN ($placeholders)");
$valid->execute(array_merge([$election['id']], $candidate_ids));
if ((int)$valid->fetchColumn() !== count($candidate_ids)) json_err('候選人 ID 無效');

// 批次寫入
$stmt = $pdo->prepare(
    "INSERT INTO election_votes (election_id, member_id, candidate_id) VALUES (?,?,?)"
);
foreach ($candidate_ids as $cid) {
    $stmt->execute([$election['id'], $member_id, $cid]);
}

log_event($meeting_id, 'election_vote_cast', [
    'member_id'      => $member_id,
    'election_id'    => $election['id'],
    'candidate_ids'  => $candidate_ids,
]);

json_ok(['votes_cast' => count($candidate_ids)]);
