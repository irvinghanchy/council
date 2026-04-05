<?php
$page_title = '主辦人帳號管理';
require_once __DIR__ . '/../includes/admin_layout.php';

if (current_role() !== 'admin') {
    echo '<div class="alert alert-error">僅系統管理員可存取。</div></div></body></html>';
    exit;
}

$pdo = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!$name || !$email) { $msg = '❌ 姓名和信箱為必填。'; }
        else {
            try {
                $pdo->prepare("INSERT INTO hosts (name,email) VALUES (?,?)")->execute([$name, $email]);
                $msg = "✅ 已新增主辦人帳號：{$name}（{$email}）";
            } catch (PDOException $e) {
                $msg = '❌ 該信箱已存在。';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $pdo->prepare("DELETE FROM hosts WHERE id=? AND is_admin=0")->execute([$id]);
        $msg = '✅ 已刪除主辦人帳號。';
    }

    if ($action === 'change_password') {
        $new_pw = $_POST['new_password'] ?? '';
        if (strlen($new_pw) < 6) { $msg = '❌ 密碼至少 6 個字元。'; }
        else {
            $hash = password_hash($new_pw, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE hosts SET password_hash=? WHERE is_admin=1")->execute([$hash]);
            $msg = '✅ 管理員密碼已更新。';
        }
    }
}

$hosts = $pdo->query("SELECT * FROM hosts ORDER BY is_admin DESC, id")->fetchAll();
?>

<h1 class="text-3xl font-bold mb-6">🔑 主辦人帳號管理</h1>

<?php if ($msg): ?>
<div class="alert <?= str_starts_with($msg,'✅') ? 'alert-success' : 'alert-error' ?> mb-4"><?= h($msg) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

<div class="lg:col-span-2">
  <div class="card bg-base-100 shadow">
    <div class="card-body">
      <h2 class="card-title">帳號列表</h2>
      <table class="table table-sm">
        <thead><tr><th>名稱</th><th>信箱</th><th>角色</th><th>登入方式</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($hosts as $h): ?>
        <tr>
          <td class="font-semibold"><?= h($h['name']) ?></td>
          <td class="text-sm"><?= h($h['email'] ?? '—') ?></td>
          <td><?= $h['is_admin'] ? '<div class="badge badge-primary">Admin</div>' : '<div class="badge badge-secondary">主辦人</div>' ?></td>
          <td class="text-xs text-gray-500"><?= $h['is_admin'] ? '帳號密碼' : 'Google OAuth' ?></td>
          <td>
            <?php if (!$h['is_admin']): ?>
            <form method="POST" onsubmit="return confirm('確定刪除？')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $h['id'] ?>">
              <button class="btn btn-xs btn-error btn-outline">刪除</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="space-y-4">
  <!-- 新增主辦人 -->
  <div class="card bg-base-100 shadow">
    <div class="card-body">
      <h2 class="card-title text-lg">➕ 新增主辦人</h2>
      <p class="text-xs text-gray-500 mb-3">主辦人以 Google 帳號登入，請確認信箱為學校帳號。</p>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="add">
        <label class="form-control">
          <div class="label"><span class="label-text">姓名</span></div>
          <input name="name" type="text" class="input input-bordered input-sm" placeholder="秘書長" required>
        </label>
        <label class="form-control">
          <div class="label"><span class="label-text">Google 信箱（學號@stu…）</span></div>
          <input name="email" type="email" class="input input-bordered input-sm"
                 placeholder="211001@stu.nknush.kh.edu.tw" required>
        </label>
        <button class="btn btn-primary btn-sm w-full">新增</button>
      </form>
    </div>
  </div>

  <!-- 更改 Admin 密碼 -->
  <div class="card bg-base-100 shadow border-2 border-warning">
    <div class="card-body">
      <h2 class="card-title text-lg text-warning">🔐 更改Admin管理員密碼</h2>
      <form method="POST" class="space-y-3">
        <input type="hidden" name="action" value="change_password">
        <label class="form-control">
          <div class="label"><span class="label-text">新密碼（至少 6 字元）</span></div>
          <input name="new_password" type="password" class="input input-bordered input-sm" required>
        </label>
        <button class="btn btn-warning btn-sm w-full">更新密碼</button>
      </form>
    </div>
  </div>
</div>

</div>

</div></body></html>
