<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare("SELECT * FROM hosts WHERE is_admin = 1 AND email IS NULL LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();

    if ($admin && $username === 'admin' && password_verify($password, $admin['password_hash'])) {
        $_SESSION['role']      = 'admin';
        $_SESSION['host_id']   = $admin['id'];
        $_SESSION['user_name'] = $admin['name'];
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    }
    $err = '帳號或密碼錯誤。';
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>主辦人登入</title>
<link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/custom.css">
</head>
<body class="bg-base-200 min-h-screen flex items-center justify-center">
<div class="card w-96 bg-base-100 shadow-xl">
  <div class="card-body">
    <h2 class="card-title text-2xl justify-center mb-4"><span class="font-emoji">📺</span> 系統管理員登入</h2>
    <?php if ($err): ?>
    <div class="alert alert-error text-sm"><?= h($err) ?></div>
    <?php endif; ?>
    <form method="POST">
      <label class="form-control mb-3">
        <div class="label"><span class="label-text">帳號</span></div>
        <input name="username" type="text" class="input input-bordered" placeholder="帳號" required>
      </label>
      <label class="form-control mb-4">
        <div class="label"><span class="label-text">密碼</span></div>
        <input name="password" type="password" class="input input-bordered" placeholder="密碼" required>
      </label>
      <button class="btn btn-primary w-full">登入</button>
    </form>
    <div class="divider">或</div>
    <a href="<?= BASE_URL ?>/auth/google_login.php?as=host" class="btn btn-outline btn-error w-full">
      <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="currentColor" d="M21.35 11.1h-9.17v2.73h6.51c-.33 3.81-3.5 5.44-6.5 5.44C8.36 19.27 5 16.25 5 12c0-4.1 3.2-7.27 7.2-7.27 3.09 0 4.9 1.97 4.9 1.97L19 4.72S16.56 2 12.1 2C6.42 2 2.03 6.8 2.03 12c0 5.05 4.13 10 10.22 10 5.35 0 9.25-3.67 9.25-9.09 0-1.15-.15-1.81-.15-1.81Z"/></svg>
      以 Google 帳號登入（主辦人）
    </a>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-ghost btn-sm mt-2">← 返回首頁</a>
  </div>
</div>
</body>
</html>
<?php function h($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}?>
