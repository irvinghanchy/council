<?php
$page_title = '會議設定';
require_once __DIR__ . '/../includes/admin_layout.php';

$pdo = db();
$msg = '';

// 處理表單
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $title  = trim($_POST['title'] ?? '');
        $loc    = trim($_POST['location'] ?? '');
        $time   = trim($_POST['start_time'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        if (!$title) { $msg = '❌ 請填寫會議名稱。'; }
        else {
            if ($meeting && $action === 'update') {
                $pdo->prepare("UPDATE meeting SET title=?,location=?,start_time=?,reason=? WHERE id=?")
                    ->execute([$title, $loc, $time, $reason, $meeting['id']]);
                $msg = '✅ 會議資料已更新。';
            } else {
                // 只能有一場 preparing/active 會議
                $pdo->prepare("UPDATE meeting SET status='ended' WHERE status IN ('preparing','active')")->execute();
                $pdo->prepare("INSERT INTO meeting (title,location,start_time,reason,status) VALUES (?,?,?,?,'preparing')")
                    ->execute([$title, $loc, $time, $reason]);
                $new_id = (int)$pdo->lastInsertId();
                // 初始化 phase
                $pdo->prepare("INSERT INTO phase_control (meeting_id,phase_type,version) VALUES (?,'standby',1)")
                    ->execute([$new_id]);
                log_event($new_id, 'meeting_created', ['title' => $title]);
                $msg = '✅ 會議建立成功！';
            }
            $meeting = active_meeting();
        }
    }

    if ($action === 'end_meeting' && $meeting) {
        $pdo->prepare("UPDATE meeting SET status='ended' WHERE id=?")->execute([$meeting['id']]);
        set_phase($meeting['id'], 'ended');
        log_event($meeting['id'], 'meeting_ended', []);
        $msg = '✅ 會議已結束。';
        $meeting = null;
    }

if ($action === 'activate' && $meeting) {
        $pdo->prepare(
            "UPDATE meeting SET status='active', actual_start_at=NOW() WHERE id=?"
        )->execute([$meeting['id']]);
        set_phase($meeting['id'], 'standby');
        log_event($meeting['id'], 'meeting_started', []);
        $msg = '✅ 會議已開始！';
        $meeting = active_meeting();
    }
}
?>

<h1 class="text-3xl font-bold mb-6">⚙️ 會議設定</h1>

<?php if ($msg): ?>
<div class="alert <?= str_starts_with($msg,'✅') ? 'alert-success' : 'alert-error' ?> mb-4"><?= h($msg) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

<!-- 建立 / 編輯會議 -->
<div class="card bg-base-100 shadow">
  <div class="card-body">
    <h2 class="card-title"><?= $meeting ? '✏️ 編輯會議資料' : '➕ 建立新會議' ?></h2>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="<?= $meeting ? 'update' : 'create' ?>">

      <label class="form-control">
        <div class="label"><span class="label-text font-semibold">會議名稱 *</span></div>
        <input name="title" type="text" class="input input-bordered"
               value="<?= h($meeting['title'] ?? '') ?>"
               placeholder="例：第12屆學生代表大會第1次定期大會" required>
      </label>

      <label class="form-control">
        <div class="label"><span class="label-text font-semibold">會議地點</span></div>
        <input name="location" type="text" class="input input-bordered"
               value="<?= h($meeting['location'] ?? '') ?>"
               placeholder="例：圖書館三樓多功能會議室">
      </label>

      <label class="form-control">
        <div class="label"><span class="label-text font-semibold">開始時間</span></div>
        <input name="start_time" type="datetime-local" class="input input-bordered"
               value="<?= h($meeting ? date('Y-m-d\TH:i', strtotime($meeting['start_time'])) : '') ?>">
      </label>

      <label class="form-control">
        <div class="label"><span class="label-text font-semibold">開會事由</span></div>
        <textarea name="reason" class="textarea textarea-bordered" rows="3"
                  placeholder="簡述本次會議事由..."><?= h($meeting['reason'] ?? '') ?></textarea>
      </label>

      <button class="btn btn-primary w-full"><?= $meeting ? '更新' : '建立會議' ?></button>
    </form>
  </div>
</div>

<!-- 會議狀態控制 -->
<div class="space-y-4">
  <?php if ($meeting): ?>
  <div class="card bg-base-100 shadow">
    <div class="card-body">
      <h2 class="card-title">🎚️ 會議狀態</h2>
      <div class="flex items-center gap-3 mb-4">
        <div class="badge badge-lg <?= $meeting['status']==='active' ? 'badge-success' : ($meeting['status']==='preparing' ? 'badge-warning' : 'badge-ghost') ?>">
          <?= ['preparing'=>'準備中','active'=>'進行中','ended'=>'已結束'][$meeting['status']] ?? $meeting['status'] ?>
        </div>
        <span class="text-sm text-gray-500">會議 ID #<?= $meeting['id'] ?></span>
      </div>

      <?php if ($meeting['status'] === 'preparing'): ?>
      <form method="POST">
        <input type="hidden" name="action" value="activate">
        <button class="btn btn-success w-full">▶ 開始會議（進入待機/簽到）</button>
      </form>
      <?php elseif ($meeting['status'] === 'active'): ?>
      <a href="<?= BASE_URL ?>/admin/control.php" class="btn btn-primary w-full mb-2">🎛️ 前往現場控制台</a>
      <form method="POST" onsubmit="return confirm('確定要結束本次會議？結束後無法再切換階段。')">
        <input type="hidden" name="action" value="end_meeting">
        <button class="btn btn-error btn-outline w-full">⏹ 結束會議</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="card bg-base-100 shadow">
    <div class="card-body">
      <h2 class="card-title">🔗 快速連結</h2>
      <div class="space-y-2">
        <div class="flex items-center gap-2">
          <span class="text-sm font-semibold w-20">大螢幕</span>
          <code class="text-xs bg-base-200 px-2 py-1 rounded flex-1 overflow-auto">
            <?= BASE_URL ?>/index.php?pin=<?= SCREEN_PIN ?>
          </code>
          <button onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/index.php?pin=<?= SCREEN_PIN ?>')"
                  class="btn btn-xs btn-outline">複製</button>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-sm font-semibold w-20">議員登入</span>
          <code class="text-xs bg-base-200 px-2 py-1 rounded flex-1 overflow-auto">
            <?= BASE_URL ?>/index.php
          </code>
          <button onclick="navigator.clipboard.writeText('<?= BASE_URL ?>/index.php')"
                  class="btn btn-xs btn-outline">複製</button>
        </div>
      </div>
    </div>
  </div>

  <div class="card bg-base-100 shadow">
    <div class="card-body">
      <h2 class="card-title">📄 匯出</h2>
      <a href="<?= BASE_URL ?>/api/export_txt.php?meeting_id=<?= $meeting['id'] ?>"
         class="btn btn-outline w-full">
        📄 匯出會議紀錄 TXT
      </a>
    </div>
  </div>
  <?php else: ?>
  <div class="alert">尚未建立會議，請先建立。</div>
  <?php endif; ?>
</div>

</div>

</div></body></html>
