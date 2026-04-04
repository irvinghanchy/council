<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_host();

$action = $_POST['action'] ?? '';
$pdo    = db();

// ── 標記當選 ─────────────────────────────────────────────────
if ($action === 'set_elected') {
    $candidate_id = (int)($_POST['candidate_id'] ?? 0);
    $elected      = (int)($_POST['elected'] ?? 0);
    $pdo->prepare("UPDATE candidates SET is_elected=? WHERE id=?")->execute([$elected, $candidate_id]);

    // 取得 meeting_id 以便 log
    $mid = $pdo->prepare(
        "SELECT a.meeting_id FROM candidates c
         JOIN elections e ON e.id=c.election_id
         JOIN agenda_items a ON a.id=e.agenda_item_id
         WHERE c.id=?"
    );
    $mid->execute([$candidate_id]);
    $mid = (int)($mid->fetchColumn() ?: 0);
    if ($mid) {
        log_event($mid, 'candidate_elected', ['candidate_id' => $candidate_id, 'elected' => $elected]);
    }
    json_ok();
}

// ── 更新席次 ─────────────────────────────────────────────────
if ($action === 'update_seats') {
    $election_id = (int)($_POST['election_id'] ?? 0);
    $seats       = max(1, (int)($_POST['seats'] ?? 1));
    $pdo->prepare("UPDATE elections SET seats=? WHERE id=?")->execute([$seats, $election_id]);
    json_ok();
}

// ── 更新 order ────────────────────────────────────────────────
if ($action === 'reorder') {
    $meeting_id = (int)($_POST['meeting_id'] ?? 0);
    $ids        = explode(',', $_POST['order'] ?? '');
    foreach ($ids as $i => $id) {
        $pdo->prepare("UPDATE agenda_items SET order_no=? WHERE id=? AND meeting_id=?")
            ->execute([$i + 1, (int)$id, $meeting_id]);
    }
    json_ok();
}

json_err('Unknown action');
