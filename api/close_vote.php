<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_host();

$meeting_id     = (int)($_POST['meeting_id'] ?? 0);
$agenda_item_id = (int)($_POST['agenda_item_id'] ?? 0);
$type           = $_POST['type'] ?? 'resolution'; // 'resolution' | 'election'

if (!$meeting_id || !$agenda_item_id) json_err('Missing params');

$pdo = db();

// 取得議程項目
$item = $pdo->prepare("SELECT * FROM agenda_items WHERE id=? AND meeting_id=?");
$item->execute([$agenda_item_id, $meeting_id]);
$item = $item->fetch();
if (!$item) json_err('Agenda item not found');
if ($item['status'] === 'closed') json_err('已截止');

// 取得所有出席且已簽到的 attendee（有資格投票者）
$attendees = $pdo->prepare(
    "SELECT m.id FROM members m
     JOIN attendance a ON a.member_id=m.id AND a.meeting_id=m.meeting_id
     WHERE m.meeting_id=? AND m.type='attendee'"
);
$attendees->execute([$meeting_id]);
$attendees = array_column($attendees->fetchAll(), 'id');

$auto_abstained = 0;

if ($item['type'] === 'resolution') {
    // 找出尚未投票的出席者，自動寫入棄權
    $already = $pdo->prepare("SELECT member_id FROM votes WHERE agenda_item_id=?");
    $already->execute([$agenda_item_id]);
    $voted_ids = array_column($already->fetchAll(), 'member_id');

    $not_voted = array_diff($attendees, $voted_ids);
    $stmt = $pdo->prepare("INSERT IGNORE INTO votes (agenda_item_id,member_id,vote) VALUES (?,?,'abstain')");
    foreach ($not_voted as $mid) {
        $stmt->execute([$agenda_item_id, $mid]);
        $auto_abstained++;
    }

    // 統計結果
    $stats = $pdo->prepare(
        "SELECT SUM(vote='yes') y, SUM(vote='no') n, SUM(vote='abstain') a FROM votes WHERE agenda_item_id=?"
    );
    $stats->execute([$agenda_item_id]);
    $stats = $stats->fetch();

    log_event($meeting_id, 'resolution_closed', [
        'agenda_item_id' => $agenda_item_id,
        'title'          => $item['title'],
        'yes'            => (int)$stats['y'],
        'no'             => (int)$stats['n'],
        'abstain'        => (int)$stats['a'],
        'auto_abstained' => $auto_abstained,
    ]);

} elseif ($item['type'] === 'election') {
    $election = $pdo->prepare("SELECT * FROM elections WHERE agenda_item_id=?");
    $election->execute([$agenda_item_id]);
    $election = $election->fetch();

    if ($election) {
        // 已投票的議員
        $already = $pdo->prepare(
            "SELECT DISTINCT member_id FROM election_votes WHERE election_id=?"
        );
        $already->execute([$election['id']]);
        $voted_ids = array_column($already->fetchAll(), 'member_id');
        $auto_abstained = count(array_diff($attendees, $voted_ids));
        // election 棄權不需寫入，直接記錄人數即可

        // 統計候選人得票
        $cands = $pdo->prepare(
            "SELECT c.name, COUNT(ev.id) AS votes
             FROM candidates c LEFT JOIN election_votes ev ON ev.candidate_id=c.id
             WHERE c.election_id=? GROUP BY c.id ORDER BY votes DESC"
        );
        $cands->execute([$election['id']]);
        log_event($meeting_id, 'election_closed', [
            'agenda_item_id' => $agenda_item_id,
            'title'          => $item['title'],
            'seats'          => $election['seats'],
            'results'        => $cands->fetchAll(),
            'auto_abstained' => $auto_abstained,
        ]);
    }
}

// 標記 agenda_item 為 closed
$pdo->prepare("UPDATE agenda_items SET status='closed' WHERE id=?")->execute([$agenda_item_id]);

json_ok(['auto_abstained' => $auto_abstained]);
