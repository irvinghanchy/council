<?php
require_once __DIR__ . '/../db/connect.php';

// ─── Auth Helpers ──────────────────────────────────────────────

function current_role(): string {
    return $_SESSION['role'] ?? 'guest';
}

function require_host(): void {
    if (!in_array(current_role(), ['admin', 'host'])) {
        header('Location: ' . BASE_URL . '/index.php?err=forbidden');
        exit;
    }
}

function require_member(): void {
    if (!in_array(current_role(), ['admin', 'host', 'member', 'observer'])) {
        header('Location: ' . BASE_URL . '/index.php?err=forbidden');
        exit;
    }
}

function is_host(): bool {
    return in_array(current_role(), ['admin', 'host']);
}

// ─── Meeting ───────────────────────────────────────────────────

function active_meeting(): ?array {
    $row = db()->query(
        "SELECT * FROM meeting WHERE status IN ('preparing','active') ORDER BY id DESC LIMIT 1"
    )->fetch();
    return $row ?: null;
}

function get_phase(int $meeting_id): array {
    $row = db()->prepare(
        "SELECT * FROM phase_control WHERE meeting_id = ?"
    );
    $row->execute([$meeting_id]);
    $r = $row->fetch();
    if (!$r) {
        // auto-create standby row
        db()->prepare(
            "INSERT IGNORE INTO phase_control (meeting_id, phase_type, version)
             VALUES (?, 'standby', 1)"
        )->execute([$meeting_id]);
        return ['phase_type' => 'standby', 'agenda_item_id' => null, 'version' => 1];
    }
    return $r;
}

function set_phase(int $meeting_id, string $type, ?int $item_id = null): void {
    db()->prepare(
        "INSERT INTO phase_control (meeting_id, phase_type, agenda_item_id, version)
         VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE
           phase_type = VALUES(phase_type),
           agenda_item_id = VALUES(agenda_item_id),
           version = version + 1"
    )->execute([$meeting_id, $type, $item_id]);
}

// ─── Log ───────────────────────────────────────────────────────

function log_event(int $meeting_id, string $type, array $data = []): void {
    db()->prepare(
        "INSERT INTO meeting_log (meeting_id, event_type, event_data) VALUES (?, ?, ?)"
    )->execute([$meeting_id, $type, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

// ─── Status JSON (used by polling) ─────────────────────────────

function build_status(int $meeting_id): array {
    $pdo = db();

    // meeting
    $meeting = $pdo->prepare("SELECT * FROM meeting WHERE id = ?");
    $meeting->execute([$meeting_id]);
    $meeting = $meeting->fetch();

    // phase
    $phase = get_phase($meeting_id);

    // current agenda item
    $item = null;
    if ($phase['agenda_item_id']) {
        $s = $pdo->prepare("SELECT * FROM agenda_items WHERE id = ?");
        $s->execute([$phase['agenda_item_id']]);
        $item = $s->fetch();
    }

    // election / resolution detail
    $election   = null;
    $candidates = [];
    $vote_stats = null;

    if ($item && $item['type'] === 'election') {
        $e = $pdo->prepare("SELECT * FROM elections WHERE agenda_item_id = ?");
        $e->execute([$item['id']]);
        $election = $e->fetch();
        if ($election) {
            $c = $pdo->prepare(
                "SELECT c.*, COALESCE(cnt.votes,0) AS vote_count
                 FROM candidates c
                 LEFT JOIN (
                     SELECT candidate_id, COUNT(*) AS votes
                     FROM election_votes WHERE election_id = ?
                     GROUP BY candidate_id
                 ) cnt ON cnt.candidate_id = c.id
                 WHERE c.election_id = ?
                 ORDER BY vote_count DESC, c.id"
            );
            $c->execute([$election['id'], $election['id']]);
            $candidates = $c->fetchAll();
        }
    }

    if ($item && $item['type'] === 'resolution') {
        $y = $pdo->prepare(
            "SELECT
               SUM(vote='yes') AS yes_count,
               SUM(vote='no') AS no_count,
               SUM(vote='abstain') AS abstain_count,
               COUNT(*) AS total_voted
             FROM votes WHERE agenda_item_id = ?"
        );
        $y->execute([$item['id']]);
        $vote_stats = $y->fetch();
    }

    // members with attendance + vote status
    $members_q = $pdo->prepare(
        "SELECT m.*,
                IF(a.id IS NOT NULL, 1, 0) AS signed_in,
                v.vote
         FROM members m
         LEFT JOIN attendance a ON a.member_id = m.id AND a.meeting_id = m.meeting_id
         LEFT JOIN votes v ON v.member_id = m.id AND v.agenda_item_id = ?
         WHERE m.meeting_id = ?
         ORDER BY m.type, m.member_no, m.id"
    );
    $members_q->execute([$phase['agenda_item_id'], $meeting_id]);
    $members = $members_q->fetchAll();

    // speech queue (waiting + speaking)
    $sq = $pdo->prepare(
        "SELECT sq.*, m.name, m.position
         FROM speech_queue sq
         JOIN members m ON m.id = sq.member_id
         WHERE sq.meeting_id = ? AND sq.status IN ('waiting','speaking')
         ORDER BY sq.requested_at"
    );
    $sq->execute([$meeting_id]);
    $speech_queue = $sq->fetchAll();

    // pending temp motions count (for host badge)
    $mc = $pdo->prepare(
        "SELECT COUNT(*) FROM temp_motions WHERE meeting_id = ? AND status = 'pending'"
    );
    $mc->execute([$meeting_id]);
    $pending_motions = (int)$mc->fetchColumn();

    // agenda list (for navigation)
    $ag = $pdo->prepare(
        "SELECT id, type, title, order_no, status FROM agenda_items
         WHERE meeting_id = ? ORDER BY order_no, id"
    );
    $ag->execute([$meeting_id]);
    $agenda_list = $ag->fetchAll();

    // Timer
    $timer = [
        'end_at' => $phase['timer_end_at'] ?? null,
        'end_ts' => $phase['timer_end_at'] ? strtotime($phase['timer_end_at']) : null,
        'label'  => $phase['timer_label'] ?? null,
    ];

    return compact(
        'meeting', 'phase', 'item', 'election', 'candidates','timer',
        'vote_stats', 'members', 'speech_queue', 'pending_motions', 'agenda_list'
    );
}

// ─── Export TXT ────────────────────────────────────────────────

function export_txt(int $meeting_id): string {
    $pdo = db();
    $m = $pdo->prepare("SELECT * FROM meeting WHERE id = ?");
    $m->execute([$meeting_id]);
    $mtg = $m->fetch();

    $members = $pdo->prepare(
        "SELECT m.*, IF(a.id IS NOT NULL,1,0) AS signed_in
         FROM members m
         LEFT JOIN attendance a ON a.member_id=m.id AND a.meeting_id=m.meeting_id
         WHERE m.meeting_id=? ORDER BY m.type, m.member_no"
    );
    $members->execute([$meeting_id]);
    $members = $members->fetchAll();

    $items = $pdo->prepare(
        "SELECT * FROM agenda_items WHERE meeting_id=? ORDER BY order_no, id"
    );
    $items->execute([$meeting_id]);
    $items = $items->fetchAll();

    $line = str_repeat('=', 54);
    $out  = $line . "\n";
    $out .= $mtg['title'] . "\n";
    $out .= "會議時間：" . $mtg['start_time'] . "\n";
    // 計算開會時長
    $duration_str = '';
    if ($mtg['actual_start_at']) {
        $start = new DateTime($mtg['actual_start_at']);
        $end   = $mtg['actual_end_at'] ? new DateTime($mtg['actual_end_at']) : new DateTime();
        $diff  = $start->diff($end);
        $duration_str = sprintf("會議時長：%d 小時 %02d 分 %02d 秒\n", $diff->h + $diff->days*24, $diff->i, $diff->s);
    }
    // 在散會時間前插入
    $out .= "會議地點：" . $mtg['location'] . "\n";
    $out .= "開會事由：" . $mtg['reason'] . "\n";
    $out .= $line . "\n\n";

    // attendance
    $attendees = array_filter($members, fn($r) => $r['type'] === 'attendee');
    $present   = array_filter($attendees, fn($r) => $r['signed_in']);
    $absent    = array_filter($attendees, fn($r) => !$r['signed_in']);
    $observers = array_filter($members,  fn($r) => $r['type'] === 'observer');

    $out .= "【出席情形】\n";
    $out .= sprintf("應出席：%d 人  實際出席：%d 人  缺席：%d 人\n",
        count($attendees), count($present), count($absent));
    if ($present)
        $out .= "出席：" . implode('、', array_map(fn($r)=>$r['name'].'（'.$r['position'].'）', $present)) . "\n";
    if ($absent)
        $out .= "缺席：" . implode('、', array_map(fn($r)=>$r['name'].'（'.$r['position'].'）', $absent)) . "\n";
    if ($observers)
        $out .= "列席：" . implode('、', array_map(fn($r)=>$r['name'].'（'.$r['position'].'）', $observers)) . "\n";
    $out .= "\n";

    // items
    $type_labels = [
        'report'     => '報告事項',
        'resolution' => '討論事項',
        'election'   => '選舉事項',
        'temp'       => '臨時動議',
    ];
    $current_section = '';
    $section_no = [];

    foreach ($items as $it) {
        $section = $type_labels[$it['type']] ?? '其他';
        if ($section !== $current_section) {
            $out .= "▌" . $section . "\n";
            $current_section = $section;
        }
        $no = ($section_no[$section] = ($section_no[$section] ?? 0) + 1);
        $out .= "  " . $no . "、" . $it['title'] . "\n";
        if ($it['description']) $out .= "     說明：" . $it['description'] . "\n";

        if ($it['type'] === 'resolution') {
            // fetch vote results
            $vs = $pdo->prepare(
                "SELECT SUM(vote='yes') y, SUM(vote='no') n, SUM(vote='abstain') a FROM votes WHERE agenda_item_id=?"
            );
            $vs->execute([$it['id']]);
            $vs = $vs->fetch();
            $out .= "     表決結果：同意 {$vs['y']} 票 / 反對 {$vs['n']} 票 / 棄權 {$vs['a']} 票\n";
            // individual
            $iv = $pdo->prepare(
                "SELECT m.name, v.vote FROM votes v JOIN members m ON m.id=v.member_id WHERE v.agenda_item_id=?"
            );
            $iv->execute([$it['id']]);
            $rows = $iv->fetchAll();
            $yes_names = implode('、', array_map(fn($r)=>$r['name'], array_filter($rows, fn($r)=>$r['vote']==='yes')));
            $no_names  = implode('、', array_map(fn($r)=>$r['name'], array_filter($rows, fn($r)=>$r['vote']==='no')));
            if ($yes_names) $out .= "     同意：$yes_names\n";
            if ($no_names)  $out .= "     反對：$no_names\n";
        }

        if ($it['type'] === 'election') {
            $el = $pdo->prepare("SELECT * FROM elections WHERE agenda_item_id=?");
            $el->execute([$it['id']]);
            $el = $el->fetch();
            if ($el) {
                $out .= "     應選人數：" . $el['seats'] . " 人\n";
                $cs = $pdo->prepare(
                    "SELECT c.name, c.is_elected, COUNT(ev.id) AS votes
                     FROM candidates c LEFT JOIN election_votes ev ON ev.candidate_id=c.id
                     WHERE c.election_id=? GROUP BY c.id ORDER BY votes DESC"
                );
                $cs->execute([$el['id']]);
                foreach ($cs->fetchAll() as $c) {
                    $mark = $c['is_elected'] ? '【當選】' : '';
                    $out .= "     {$c['name']}：{$c['votes']} 票 {$mark}\n";
                }
            }
        }
        $out .= "\n";
    }

    // temp motions by members
    $tms = $pdo->prepare(
        "SELECT tm.*, m.name AS proposer FROM temp_motions tm
         LEFT JOIN members m ON m.id=tm.member_id
         WHERE tm.meeting_id=? AND tm.member_id IS NOT NULL AND tm.status='accepted'
         ORDER BY tm.submitted_at"
    );
    $tms->execute([$meeting_id]);
    foreach ($tms->fetchAll() as $tm) {
        $out .= "  （臨時動議，提案人：{$tm['proposer']}）{$tm['content']}\n";
    }

    $out .= "\n" . $line . "\n";
    $out .= $duration_str;
    $out .= "散會時間：" . ($mtg['actual_end_at'] ?? date('Y-m-d H:i:s')) . "\n";
    $out .= $line . "\n";

    return $out;
}

// ─── Misc ──────────────────────────────────────────────────────

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function json_ok(mixed $data = null): never {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_err(string $msg, int $code = 400): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function email_to_student_id(string $email): string {
    return explode('@', $email)[0];
}
