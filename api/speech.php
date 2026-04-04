<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';

$action     = $_REQUEST['action'] ?? '';
$meeting_id = (int)($_REQUEST['meeting_id'] ?? 0);

if (!$meeting_id) json_err('Missing meeting_id');

$pdo = db();

// ── 申請發言（議員） ──────────────────────────────────────────
if ($action === 'request') {
    require_member();
    $member_id      = (int)($_POST['member_id'] ?? 0);
    $agenda_item_id = $_POST['agenda_item_id'] ? (int)$_POST['agenda_item_id'] : null;

    // 確認議員屬於此會議
    $m = $pdo->prepare("SELECT id FROM members WHERE id=? AND meeting_id=?");
    $m->execute([$member_id, $meeting_id]);
    if (!$m->fetch()) json_err('議員不在名單中');

    // 確認沒有 waiting/speaking 的申請
    $existing = $pdo->prepare(
        "SELECT id FROM speech_queue WHERE meeting_id=? AND member_id=? AND status IN ('waiting','speaking')"
    );
    $existing->execute([$meeting_id, $member_id]);
    if ($existing->fetch()) json_err('您已在發言佇列中');

    $pdo->prepare(
        "INSERT INTO speech_queue (meeting_id, agenda_item_id, member_id, status) VALUES (?,?,?,'waiting')"
    )->execute([$meeting_id, $agenda_item_id, $member_id]);

    log_event($meeting_id, 'speech_requested', ['member_id' => $member_id]);
    json_ok();
}

// ── 取消申請（議員本人） ──────────────────────────────────────
if ($action === 'cancel') {
    require_member();
    $id        = (int)($_POST['id'] ?? 0);
    $member_id = (int)($_POST['member_id'] ?? $_SESSION['member_id'] ?? 0);

    $sq = $pdo->prepare("SELECT * FROM speech_queue WHERE id=? AND meeting_id=?");
    $sq->execute([$id, $meeting_id]);
    $sq = $sq->fetch();

    if (!$sq) json_err('申請不存在');
    if ($sq['member_id'] != $member_id && !is_host()) json_err('無權取消他人申請');
    if ($sq['status'] === 'speaking') json_err('正在發言中，不可取消');

    $pdo->prepare("UPDATE speech_queue SET status='cancelled' WHERE id=?")->execute([$id]);
    json_ok();
}

// ── 主辦人更新狀態 ────────────────────────────────────────────
if ($action === 'update') {
    require_host();
    $id     = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!in_array($status, ['speaking','done','removed'])) json_err('Invalid status');

    // 若設為 speaking，把其他人的 speaking 改回 waiting（同時只能一人說話）
    if ($status === 'speaking') {
        $pdo->prepare(
            "UPDATE speech_queue SET status='waiting' WHERE meeting_id=? AND status='speaking' AND id != ?"
        )->execute([$meeting_id, $id]);
    }

    $pdo->prepare("UPDATE speech_queue SET status=? WHERE id=? AND meeting_id=?")
        ->execute([$status, $id, $meeting_id]);

    if ($status === 'done' || $status === 'removed') {
        // 取得下一位 waiting，自動設為 speaking
        $next = $pdo->prepare(
            "SELECT id FROM speech_queue WHERE meeting_id=? AND status='waiting' ORDER BY requested_at LIMIT 1"
        );
        $next->execute([$meeting_id]);
        // 不自動叫下一位，由主辦人手動操作
    }

    json_ok();
}

// ── 取得佇列（唯讀，所有人可查） ─────────────────────────────
if ($action === 'list') {
    $sq = $pdo->prepare(
        "SELECT sq.*, m.name, m.position
         FROM speech_queue sq JOIN members m ON m.id=sq.member_id
         WHERE sq.meeting_id=? AND sq.status IN ('waiting','speaking')
         ORDER BY sq.requested_at"
    );
    $sq->execute([$meeting_id]);
    json_ok($sq->fetchAll());
}

json_err('Unknown action');
