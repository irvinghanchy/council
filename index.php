<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db/connect.php';
require_once __DIR__ . '/includes/functions.php';

// 螢幕 PIN 直接轉跳大螢幕
if (isset($_GET['pin']) && $_GET['pin'] === SCREEN_PIN) {
    header('Location: ' . BASE_URL . '/screen/index.php');
    exit;
}

// 已登入者導向各自頁面
$role = current_role();
if ($role === 'admin' || $role === 'host') {
    header('Location: ' . BASE_URL . '/admin/index.php'); exit;
}
if ($role === 'member' || $role === 'observer') {
    header('Location: ' . BASE_URL . '/member/index.php'); exit;
}

$err_map = [
    'forbidden'    => '您沒有存取權限。',
    'oauth_denied' => '已取消 Google 登入。',
    'oauth_fail'   => 'Google 登入失敗，請稍後再試。',
    'wrong_domain' => '請使用學校（@' . ALLOWED_DOMAIN . '）信箱登入。',
    'not_in_list'  => '您的帳號不在本次會議名單中，請聯絡會議主辦人。',
    'no_meeting'   => '目前沒有進行中的會議。',
];
$err = $err_map[$_GET['err'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>高師大附中學生議會線上議事系統</title>
<link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/custom.css">
</head>
<body class="bg-gradient-to-br from-blue-950 to-slate-900 min-h-screen flex items-center justify-center p-4">
<div class="text-center">
  <!-- <div class="text-6xl mb-4 font-emoji">🏛️</div> -->
  <div class="avatar mb-4">
    <div class="w-52 rounded-full">
      <img src="<?= BASE_URL ?>/assets/ASHSSP Logo.png" />
    </div>
  </div>
  <h1 class="text-4xl font-bold text-white mb-2">線上議事系統</h1>
  <p class="text-blue-300 mb-8 text-lg">高師大附中學生議會議事輔助平台</p>

  <?php if ($err): ?>
  <div class="alert alert-error mb-6 max-w-sm mx-auto">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <?= htmlspecialchars($err) ?>
  </div>
  <?php endif; ?>

  <div class="flex flex-col gap-4 max-w-xs mx-auto">
    <!-- 議員 Google 登入 -->
    <a href="<?= BASE_URL ?>/auth/google_login.php?as=member"
       class="btn btn-lg bg-white text-gray-700 hover:bg-gray-100 border-0 shadow-lg gap-3">
      <svg class="w-6 h-6" viewBox="0 0 24 24">
        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
      </svg>
      Google 登入（簽到）
    </a>

    <!-- 主辦人登入 -->
    <a href="<?= BASE_URL ?>/auth/admin_login.php"
       class="btn btn-lg btn-outline text-blue-200 border-blue-400 hover:bg-blue-900 gap-3">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
      </svg>
      主辦人登入
    </a>

    <!-- 大螢幕（提示） -->
    <!-- <div class="text-blue-400 text-sm mt-4">
      大螢幕請在網址後加上 <code class="bg-blue-900 px-2 py-0.5 rounded">?pin=XXXX</code>
    </div> -->
  </div>
</div>
</body>
</html>
