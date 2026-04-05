<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';

$action     = $_REQUEST['action'] ?? '';
$meeting_id = (int)($_REQUEST['meeting_id'] ?? 0);
if (!$meeting_id) json_err('Missing meeting_id');

$pdo = db();

// ── 議員提出臨時動議 ─────────────────────────────────────────
if ($action === 'submit') {
    require_member();
    $member_id = (int)($_POST['member_id'] ?? 0);
    $content   = trim($_POST['content'] ?? '');
    if (!$content) json_err('內容不可為空');

    $pdo->prepare(
        "INSERT INTO temp_motions (meeting_id, member_id, content, status) VALUES (?,?,?,'pending')"
    )->execute([$meeting_id, $member_id, $content]);

    log_event($meeting_id, 'motion_submitted', ['member_id' => $member_id, 'content' => $content]);
    json_ok();
}

// ── 主辦人審核動議 ───────────────────────────────────────────
if ($action === 'review') {
    require_host();
    $id     = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (!in_array($status, ['accepted','rejected'])) json_err('Invalid status');

    $motion = $pdo->prepare("SELECT * FROM temp_motions WHERE id=? AND meeting_id=?");
    $motion->execute([$id, $meeting_id]);
    $motion = $motion->fetch();
    if (!$motion) json_err('動議不存在');

    $agenda_item_id = null;

    if ($status === 'accepted') {
        // 自動建立 agenda_item（type=temp）
        $max_order = $pdo->prepare("SELECT COALESCE(MAX(order_no),0)+1 FROM agenda_items WHERE meeting_id=?");
        $max_order->execute([$meeting_id]);
        $order = (int)$max_order->fetchColumn();

        $pdo->prepare(
            "INSERT INTO agenda_items (meeting_id,type,title,description,order_no,source)
             VALUES (?,'temp',?,?,?,'motion')"
        )->execute([$meeting_id, '臨時動議：' . mb_substr($motion['content'], 0, 50), $motion['content'], $order]);        $agenda_item_id = (int)$pdo->lastInsertId();

        $pdo->prepare(
            "INSERT INTO agenda_items (meeting_id,type,title,description,order_no,source)
             VALUES (?,?,?,?,?,'motion')"
        )->execute([$meeting_id, $motion_type,
            '臨時動議：' . mb_substr($motion['content'], 0, 50),
            $motion['content'], $order]);
        $agenda_item_id = (int)$pdo->lastInsertId();

        // 若是表決，補建 resolution 記錄
        if ($motion_type === 'resolution') {
            $pdo->prepare("INSERT INTO resolutions (agenda_item_id) VALUES (?)")
                ->execute([$agenda_item_id]);
        }

    }

    $pdo->prepare(
        "UPDATE temp_motions SET status=?, agenda_item_id=? WHERE id=?"
    )->execute([$status, $agenda_item_id, $id]);

    log_event($meeting_id, 'motion_reviewed', [
        'motion_id'      => $id,
        'status'         => $status,
        'agenda_item_id' => $agenda_item_id,
    ]);

    json_ok(['agenda_item_id' => $agenda_item_id]);
}

// ── 主辦人直接新增議程（臨時動議快速通道） ─────────────────
if ($action === 'host_add') {
    require_host();
    $title = trim($_POST['title'] ?? '');
    $type  = $_POST['type'] ?? 'temp';
    if (!in_array($type, ['temp','resolution','election'])) $type = 'temp';
    if (!$title) json_err('標題不可為空');

    $max_order = $pdo->prepare("SELECT COALESCE(MAX(order_no),0)+1 FROM agenda_items WHERE meeting_id=?");
    $max_order->execute([$meeting_id]);
    $order = (int)$max_order->fetchColumn();

    $pdo->prepare(
        "INSERT INTO agenda_items (meeting_id,type,title,order_no,source) VALUES (?,?,?,?,'host_added')"
    )->execute([$meeting_id, $type, $title, $order]);
    $new_id = (int)$pdo->lastInsertId();

    // 若是案由，補建 resolution 記錄
    if ($type === 'resolution') {
        $pdo->prepare("INSERT INTO resolutions (agenda_item_id) VALUES (?)")->execute([$new_id]);
    }
    // 若是選舉，補建 election 記錄（預設應選 1 人）
    if ($type === 'election') {
        $pdo->prepare("INSERT INTO elections (agenda_item_id,seats) VALUES (?,1)")->execute([$new_id]);
    }

    log_event($meeting_id, 'host_added_item', [
        'type'  => $type,
        'title' => $title,
        'id'    => $new_id,
    ]);

    json_ok(['agenda_item_id' => $new_id]);
}

// ── 列出待審動議 ─────────────────────────────────────────────
if ($action === 'list') {
    require_host();
    $status = $_GET['status'] ?? 'pending';
    $rows = $pdo->prepare(
        "SELECT tm.*, m.name AS proposer
         FROM temp_motions tm LEFT JOIN members m ON m.id=tm.member_id
         WHERE tm.meeting_id=? AND tm.status=?
         ORDER BY tm.submitted_at"
    );
    $rows->execute([$meeting_id, $status]);
    json_ok($rows->fetchAll());
}

json_err('Unknown action');
