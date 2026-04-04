<?php
$page_title = '成員管理';
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

    if ($action === 'add') {
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $name     = trim($_POST['name'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $no       = trim($_POST['member_no'] ?? '');
        $type     = $_POST['type'] ?? 'attendee';

        // 允許輸入學號，自動補全
        if (!str_contains($email, '@')) {
            $email = $email . '@' . ALLOWED_DOMAIN;
        }

        if (!$email || !$name) { $msg = '❌ 信箱和姓名為必填。'; }
        else {
            try {
                $pdo->prepare(
                    "INSERT INTO members (meeting_id,email,name,position,member_no,type)
                     VALUES (?,?,?,?,?,?)"
                )->execute([$mid, $email, $name, $position, $no, $type]);
                $msg = "✅ 已新增：{$name}";
            } catch (PDOException $e) {
                $msg = '❌ 該信箱已在名單中。';
            }
        }
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM members WHERE id=? AND meeting_id=?")->execute([$_POST['id'], $mid]);
        $msg = '✅ 已刪除。';
    }

    if ($action === 'batch') {
        // 批次匯入：每行「學號 名字 職位 類型」(tab 或空格分隔，類型可選)
        $lines = explode("\n", $_POST['batch_data'] ?? '');
        $ok = 0; $fail = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) continue;
            $parts = preg_split('/[\t,]+/', $line);
            if (count($parts) < 2) { $fail++; continue; }
            $id_or_email = trim($parts[0]);
            $name        = trim($parts[1]);
            $position    = trim($parts[2] ?? '');
            $type        = strtolower(trim($parts[3] ?? 'attendee'));
            if (!in_array($type, ['attendee','observer'])) $type = 'attendee';
            if (!str_contains($id_or_email, '@'))
                $id_or_email = $id_or_email . '@' . ALLOWED_DOMAIN;
            try {
                $pdo->prepare(
                    "INSERT IGNORE INTO members (meeting_id,email,name,position,member_no,type) VALUES (?,?,?,?,?,?)"
                )->execute([$mid, $id_or_email, $name, $position,
                    !str_contains($id_or_email, '@') ? $parts[0] : '', $type]);
                $ok++;
            } catch (Exception $e) { $fail++; }
        }
        $msg = "✅ 批次匯入完成：成功 {$ok} 筆，失敗 {$fail} 筆。";
    }
}

// 取得成員列表
$attendees = $pdo->prepare(
    "SELECT m.*, IF(a.id IS NOT NULL,1,0) AS signed_in
     FROM members m LEFT JOIN attendance a ON a.member_id=m.id AND a.meeting_id=m.meeting_id
     WHERE m.meeting_id=? AND m.type='attendee' ORDER BY m.member_no, m.id"
);
$attendees->execute([$mid]);
$attendees = $attendees->fetchAll();

$observers = $pdo->prepare(
    "SELECT m.*, IF(a.id IS NOT NULL,1,0) AS signed_in
     FROM members m LEFT JOIN attendance a ON a.member_id=m.id AND a.meeting_id=m.meeting_id
     WHERE m.meeting_id=? AND m.type='observer' ORDER BY m.id"
);
$observers->execute([$mid]);
$observers = $observers->fetchAll();
?>

<h1 class="text-3xl font-bold mb-2">👥 成員管理</h1>
<p class="text-gray-500 mb-6">
  出席人（議員）<?= count($attendees) ?> 位 &nbsp;|&nbsp; 列席人 <?= count($observers) ?> 位
</p>

<?php if ($msg): ?>
<div class="alert <?= str_starts_with($msg,'✅') ? 'alert-success' : 'alert-error' ?> mb-4"><?= h($msg) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

<!-- 新增單筆 -->
<div class="xl:col-span-1 space-y-4">
  <div class="card bg-base-100 shadow">
    <div class="card-body">
      <h2 class="card-title text-lg">➕ 新增成員</h2>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="add">
        <label class="form-control">
          <div class="label"><span class="label-text">學號 / 信箱 *</span></div>
          <input name="email" type="text" class="input input-bordered input-sm"
                 placeholder="C112000001 或完整信箱" required>
          <div class="label"><span class="label-text-alt text-gray-400">輸入學號自動補 @stu.nknush...</span></div>
        </label>
        <label class="form-control">
          <div class="label"><span class="label-text">姓名 *</span></div>
          <input name="name" type="text" class="input input-bordered input-sm" placeholder="王小明" required>
        </label>
        <label class="form-control">
          <div class="label"><span class="label-text">職位</span></div>
          <input name="position" type="text" class="input input-bordered input-sm" placeholder="第一選區第一席">
        </label>
        <label class="form-control">
          <div class="label"><span class="label-text">議員編號</span></div>
          <input name="member_no" type="text" class="input input-bordered input-sm" placeholder="A01">
        </label>
        <label class="form-control">
          <div class="label"><span class="label-text">類型</span></div>
          <select name="type" class="select select-bordered select-sm">
            <option value="attendee">出席（議員，有表決權）</option>
            <option value="observer">列席（無表決權）</option>
          </select>
        </label>
        <button class="btn btn-primary btn-sm w-full">新增</button>
      </form>
    </div>
  </div>

  <!-- 批次匯入 -->
  <div class="card bg-base-100 shadow">
    <div class="card-body">
      <h2 class="card-title text-lg">📥 批次匯入</h2>
      <p class="text-xs text-gray-500 mb-2">每行格式：<br>
        <code>學號, 姓名, 職位, 類型</code><br>
        類型可填 <code>attendee</code> 或 <code>observer</code>（省略預設 <code>attendee</code>）
      </p>
      <form method="POST">
        <input type="hidden" name="action" value="batch">
        <textarea name="batch_data" class="textarea textarea-bordered w-full text-xs" rows="8"
                  placeholder="C112000001, 王小明, 第一選區第一席&#10;C112000002, 李小華, 第二選區第一席&#10;C112000003, 張老師, 指導老師, observer"></textarea>
        <button class="btn btn-secondary btn-sm w-full mt-2">匯入</button>
      </form>
    </div>
  </div>
</div>

<!-- 出席人列表 -->
<div class="xl:col-span-2 space-y-6">
  <div class="card bg-base-100 shadow">
    <div class="card-body p-4">
      <h2 class="card-title text-lg mb-3">🤝 出席人—— <?= count($attendees) ?> 人</h2>
      <div class="overflow-x-auto">
        <table class="table table-sm">
          <thead>
            <tr class="bg-base-200">
              <th>編號</th><th>姓名</th><th>職位</th><th>信箱/學號</th><th>簽到</th><th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($attendees as $m): ?>
          <tr class="hover">
            <td><?= h($m['member_no']) ?></td>
            <td class="font-semibold"><?= h($m['name']) ?></td>
            <td class="text-sm text-gray-500"><?= h($m['position']) ?></td>
            <td class="text-xs text-gray-400"><?= h($m['email']) ?></td>
            <td>
              <?php if ($m['signed_in']): ?>
              <div class="badge badge-success badge-sm">已簽到</div>
              <?php else: ?>
              <div class="badge badge-ghost badge-sm">未到</div>
              <?php endif; ?>
            </td>
            <td>
              <form method="POST" onsubmit="return confirm('確定刪除 <?= h($m['name']) ?>？')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button class="btn btn-xs btn-error btn-outline">刪除</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$attendees): ?>
          <tr><td colspan="6" class="text-center text-gray-400 py-4">尚未新增出席人</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card bg-base-100 shadow">
    <div class="card-body p-4">
      <h2 class="card-title text-lg mb-3">👂 列席人 —— <?= count($observers) ?> 人</h2>
      <div class="overflow-x-auto">
        <table class="table table-sm">
          <thead>
            <tr class="bg-base-200">
              <th>姓名</th><th>職位</th><th>信箱</th><th>簽到</th><th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($observers as $m): ?>
          <tr class="hover">
            <td class="font-semibold"><?= h($m['name']) ?></td>
            <td class="text-sm text-gray-500"><?= h($m['position']) ?></td>
            <td class="text-xs text-gray-400"><?= h($m['email']) ?></td>
            <td>
              <?php if ($m['signed_in']): ?>
              <div class="badge badge-success badge-sm">已簽到</div>
              <?php else: ?>
              <div class="badge badge-ghost badge-sm">未到</div>
              <?php endif; ?>
            </td>
            <td>
              <form method="POST" onsubmit="return confirm('確定刪除？')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $m['id'] ?>">
                <button class="btn btn-xs btn-error btn-outline">刪除</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$observers): ?>
          <tr><td colspan="5" class="text-center text-gray-400 py-4">尚未新增列席人</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</div>

</div></body></html>
