<?php
$page_title = '議程管理';
require_once __DIR__ . '/../includes/admin_layout.php';

if (!$meeting) {
    echo '<div class="alert alert-warning">請先建立會議。</div></div></body></html>';
    exit;
}

$pdo = db();
$mid = $meeting['id'];
$msg = '';

// ── Actions ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $type  = $_POST['item_type'] ?? 'report';
        $title = trim($_POST['title'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $order = (int)($_POST['order_no'] ?? 0);
        if (!$title) { $msg = '❌ 請填寫標題。'; }
        else {
            $pdo->prepare(
                "INSERT INTO agenda_items (meeting_id,type,title,description,order_no,source)
                 VALUES (?,?,?,?,?,'preset')"
            )->execute([$mid, $type, $title, $desc, $order]);
            $new_id = (int)$pdo->lastInsertId();

            if ($type === 'resolution') {
                $pdo->prepare("INSERT INTO resolutions (agenda_item_id) VALUES (?)")->execute([$new_id]);
            }
            if ($type === 'election') {
                $seats = max(1, (int)($_POST['seats'] ?? 1));
                $pdo->prepare("INSERT INTO elections (agenda_item_id,seats) VALUES (?,?)")
                    ->execute([$new_id, $seats]);
            }
            $msg = "✔ 已新增議程：{$title}";
        }
    }

    if ($action === 'delete_item') {
        $pdo->prepare("DELETE FROM agenda_items WHERE id=? AND meeting_id=?")->execute([$_POST['id'], $mid]);
        $msg = '✔ 已刪除。';
    }

    if ($action === 'reorder') {
        $ids = explode(',', $_POST['order'] ?? '');
        foreach ($ids as $i => $id) {
            $pdo->prepare("UPDATE agenda_items SET order_no=? WHERE id=? AND meeting_id=?")
                ->execute([$i + 1, (int)$id, $mid]);
        }
        json_ok(); // AJAX call
    }

    // 候選人管理
    if ($action === 'add_candidate') {
        $election_id = (int)$_POST['election_id'];
        $raw_names   = trim($_POST['cand_name'] ?? '');
        $member_id   = null; // 快速加入現在只填名字，不帶 member_id
        if ($raw_names) {
            $names = array_filter(array_map('trim', explode(',', $raw_names)));
            foreach ($names as $name) {
                $pdo->prepare("INSERT INTO candidates (election_id,name,member_id) VALUES (?,?,?)")
                    ->execute([$election_id, $name, $member_id]);
            }
            $msg = "✔ 已新增候選人：" . implode('、', $names);
        }
    }

    if ($action === 'delete_candidate') {
        $pdo->prepare("DELETE FROM candidates WHERE id=?")->execute([$_POST['candidate_id']]);
        $msg = '✔ 已刪除候選人。';
    }

    if ($action === 'set_elected') {
        $val = (int)$_POST['elected'];
        $pdo->prepare("UPDATE candidates SET is_elected=? WHERE id=?")->execute([$val, (int)$_POST['candidate_id']]);
        json_ok();
    }

    if ($action === 'batch_agenda') {
        $lines = explode("\n", $_POST['batch_agenda_data'] ?? '');
        $ok = 0; $fail = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) continue;
            $parts = array_map('trim', explode(',', $line, 5));
            $type  = strtolower($parts[0] ?? '');
            $title = $parts[1] ?? '';
            $desc  = $parts[2] ?? '';
            $order = (int)($parts[3] ?? 0);
            $seats = max(1, (int)($parts[4] ?? 1));
            if (!in_array($type, ['report','resolution','election','temp']) || !$title) {
                $fail++; continue;
            }
            $pdo->prepare(
                "INSERT INTO agenda_items (meeting_id,type,title,description,order_no,source)
                VALUES (?,?,?,?,?,'preset')"
            )->execute([$mid, $type, $title, $desc, $order]);
            $new_id = (int)$pdo->lastInsertId();
            if ($type === 'resolution') {
                $pdo->prepare("INSERT INTO resolutions (agenda_item_id) VALUES (?)")->execute([$new_id]);
            }
            if ($type === 'election') {
                $pdo->prepare("INSERT INTO elections (agenda_item_id,seats) VALUES (?,?)")->execute([$new_id, $seats]);
            }
            $ok++;
        }
        $msg = "✔ 批次匯入：成功 {$ok} 筆，失敗 {$fail} 筆。";
    }
}

// 取議程列表
$items = $pdo->prepare(
    "SELECT a.*, e.seats FROM agenda_items a
     LEFT JOIN elections e ON e.agenda_item_id = a.id
     WHERE a.meeting_id=? ORDER BY a.order_no, a.id"
);
$items->execute([$mid]);
$items = $items->fetchAll();

// 取所有議員供快速加入候選人用
$all_members = $pdo->prepare(
    "SELECT * FROM members WHERE meeting_id=? ORDER BY member_no, name"
);
$all_members->execute([$mid]);
$all_members = $all_members->fetchAll();

$type_labels = [
    'report'     => ['label'=>'報告', 'badge'=>'badge-info'],
    'resolution' => ['label'=>'案由', 'badge'=>'badge-warning'],
    'election'   => ['label'=>'選舉', 'badge'=>'badge-error'],
    'temp'       => ['label'=>'臨時動議', 'badge'=>'badge-ghost'],
];
?>

<h1 class="text-3xl font-bold mb-6">📋 議程管理</h1>

<?php if ($msg): ?>
<div class="alert <?= str_starts_with($msg,'✔') ? 'alert-success' : 'alert-error' ?> mb-4"><?= h($msg) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

<!-- 新增議程 -->
<div class="xl:col-span-1">
  <div class="card bg-base-100 shadow">
    <div class="card-body">
      <h2 class="card-title">➕ 新增議程項目</h2>
      <form method="POST" class="space-y-3" id="add-form">
        <input type="hidden" name="action" value="add_item">
        <label class="form-control">
          <div class="label"><span class="label-text font-semibold">類型</span></div>
          <select name="item_type" class="select select-bordered select-sm" id="type-select"
                  onchange="toggleElectionField(this.value)">
            <option value="report">📣 報告事項</option>
            <option value="resolution">🪧 案由（表決）</option>
            <option value="election">🏆 選舉</option>
            <option value="temp">📝 臨時動議</option>
          </select>
        </label>
        <label class="form-control">
          <div class="label"><span class="label-text font-semibold">標題 *</span></div>
          <input name="title" type="text" class="input input-bordered input-sm"
                 placeholder="例：議長報告／修改章程案" required>
        </label>
        <label class="form-control">
          <div class="label"><span class="label-text font-semibold">說明／內文</span></div>
          <textarea name="description" class="textarea textarea-bordered text-sm" rows="3"
                    placeholder="案由內容、補充說明..."></textarea>
        </label>
        <label class="form-control">
          <div class="label"><span class="label-text font-semibold">排序編號</span></div>
          <input name="order_no" type="number" class="input input-bordered input-sm"
                 value="<?= count($items) + 1 ?>" min="1">
        </label>
        <div id="election-field" class="hidden">
          <label class="form-control">
            <div class="label"><span class="label-text font-semibold">應選人數（席次）</span></div>
            <input name="seats" type="number" class="input input-bordered input-sm" value="1" min="1">
            <div class="label"><span class="label-text-alt">每位議員持有此張數的票（單記不可讓渡制）</span></div>
          </label>
        </div>
        <button class="btn btn-primary btn-sm w-full">新增</button>
      </form>
    </div>
  </div>
  <!-- 大量新增議程 -->
  <div class="card bg-base-100 shadow mt-4">
    <div class="card-body">
      <h2 class="card-title">📥 大量新增議程</h2>
      <p class="text-xs text-gray-500 mb-2">
        <b>每行格式：</b><code>type, 標題, 說明(可空), 排序, 席次(選舉用)</code><br>
        <b><span class="inline-block w-[4em] text-right">type值</span>：</b>
        <code>report</code> / <code>resolution</code> / <code>election</code> / <code>temp</code>
      </p>
      <form method="POST">
        <input type="hidden" name="action" value="batch_agenda">
        <textarea name="batch_agenda_data" class="textarea textarea-bordered w-full text-xs" rows="7"
                  placeholder="report, 議長報告, 本學期施政方針, 1
resolution, 修改組織章程案, 修改第三條規定, 2
election, 選舉議長, , 3, 1
election, 選舉常務代表, , 4, 3"></textarea>
        <button class="btn btn-secondary btn-sm w-full mt-2">批次匯入</button>
      </form>
    </div>
  </div>

</div>


<!-- 議程列表 -->
<div class="xl:col-span-2">
  <div class="card bg-base-100 shadow">
    <div class="card-body p-4">
      <h2 class="card-title mb-4">📋 議程清單（<?= count($items) ?> 項）</h2>

      <?php if (!$items): ?>
      <div class="text-center text-gray-400 py-8">尚未新增任何議程項目</div>
      <?php endif; ?>

      <div class="space-y-3" id="agenda-list">
      <?php foreach ($items as $it): ?>
      <div class="collapse collapse-arrow border border-base-300 bg-base-100 rounded-box" id="item-<?= $it['id'] ?>">
        <input type="checkbox">
        <div class="collapse-title font-medium flex items-center gap-2">
          <span class="text-gray-400 text-sm w-6"><?= $it['order_no'] ?>.</span>
          <div class="badge <?= $type_labels[$it['type']]['badge'] ?? 'badge-ghost' ?> badge-sm text-nowrap">
            <?= $type_labels[$it['type']]['label'] ?? $it['type'] ?>
          </div>
          <span><?= h($it['title']) ?></span>
          <div class="badge badge-<?= $it['status']==='open' ? 'success' : ($it['status']==='closed' ? 'ghost' : 'warning') ?> badge-sm ml-auto text-nowrap">
            <?= ['pending'=>'待開始','open'=>'進行中','closed'=>'已結束'][$it['status']] ?>
          </div>
        </div>
        <div class="collapse-content">
          <?php if ($it['description']): ?>
          <p class="text-sm text-gray-600 mb-3"><?= h($it['description']) ?></p>
          <?php endif; ?>

          <?php if ($it['type'] === 'election'): ?>
          <div class="bg-base-200 p-3 rounded-lg mb-3">
            <div class="text-sm font-semibold mb-2">🏆 應選 <?= $it['seats'] ?> 人 — 候選人管理</div>

            <?php
            $cands = $pdo->prepare(
                "SELECT c.*, COALESCE(cnt.votes,0) AS vote_count
                 FROM candidates c
                 JOIN elections e ON e.id=c.election_id
                 LEFT JOIN (SELECT candidate_id, COUNT(*) votes FROM election_votes GROUP BY candidate_id) cnt
                   ON cnt.candidate_id=c.id
                 WHERE e.agenda_item_id=?
                 ORDER BY c.id"
            );
            $cands->execute([$it['id']]);
            $cands = $cands->fetchAll();

            $election_row = $pdo->prepare("SELECT id FROM elections WHERE agenda_item_id=?");
            $election_row->execute([$it['id']]);
            $eid = $election_row->fetchColumn();
            ?>

            <div class="space-y-1 mb-3">
            <?php foreach ($cands as $c): ?>
            <div class="flex items-center gap-2 text-sm">
              <span class="<?= $c['is_elected'] ? 'font-bold text-success' : '' ?>"><?= h($c['name']) ?></span>
              <?php if ($it['status']==='closed'): ?>
              <span class="badge badge-sm"><?= $c['vote_count'] ?> 票</span>
              <button onclick="setElected(<?= $c['id'] ?>, <?= $c['is_elected'] ? 0 : 1 ?>)"
                      class="btn btn-xs <?= $c['is_elected'] ? 'btn-success' : 'btn-outline' ?>">
                <?= $c['is_elected'] ? '✔ 當選' : '標記當選' ?>
              </button>
              <?php endif; ?>
              <form method="POST" class="ml-auto">
                <input type="hidden" name="action" value="delete_candidate">
                <input type="hidden" name="candidate_id" value="<?= $c['id'] ?>">
                <button class="btn btn-xs btn-error btn-outline">✕</button>
              </form>
            </div>
            <?php endforeach; ?>
            </div>

            <!-- 大量匯入 -->
            <form method="POST" class="mb-2">
              <input type="hidden" name="action" value="add_candidate">
              <input type="hidden" name="election_id" value="<?= $eid ?>">
              <div class="flex gap-2 mb-2">
                <input id="cand-input-<?= $eid ?>" name="cand_name" type="text"
                      class="input input-bordered input-xs flex-1"
                      placeholder="候選人（可逗號分隔多人，例：王小明, 李小華）">
                <button class="btn btn-xs btn-secondary">新增</button>
              </div>
              <!-- 快速從名單加入：點選後填入欄位 -->
              <select class="select select-bordered select-xs w-full"
                      onchange="fillCandName(this, <?= $eid ?>)">
                <option value="">— 從名單快速填入 —</option>
                <?php foreach ($all_members as $mb): ?>
                <option value="<?= h($mb['name']) ?>"><?= h($mb['name'].'（'.$mb['position'].'）') ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
          <?php endif; ?>

          <form method="POST" onsubmit="return confirm('確定刪除此議程項目？')">
            <input type="hidden" name="action" value="delete_item">
            <input type="hidden" name="id" value="<?= $it['id'] ?>">
            <button class="btn btn-xs btn-error btn-outline">🗑 刪除此項目</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
</div>

<script>
function toggleElectionField(val) {
    document.getElementById('election-field').classList.toggle('hidden', val !== 'election');
}

function setElected(candidateId, val) {
    fetch('<?= BASE_URL ?>/api/agenda_crud.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=set_elected&candidate_id=${candidateId}&elected=${val}`
    }).then(() => location.reload());
}

function fillCandName(sel, eid) {
    if (!sel.value) return;
    document.getElementById('cand-input-' + eid).value = sel.value;
    sel.selectedIndex = 0;
}
</script>

</div></body></html>
