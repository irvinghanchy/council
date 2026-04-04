<?php
/**
 * install.php — 首次安裝輔助頁面
 * 完成後請立即刪除此檔案！
 */

// 簡單的 IP 限制，只允許本機存取
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('僅限本機存取。');
}

$msg = '';
$step = (int)($_GET['step'] ?? 1);

// Step 2: 產生密碼 hash
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $pw   = $_POST['password'];
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $msg  = $hash;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<title>安裝輔助</title>
<link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-base-200 p-8 max-w-2xl mx-auto">
<h1 class="text-3xl font-bold mb-6">🔧 線上議事系統 — 安裝輔助</h1>

<div class="alert alert-error mb-6">
  ⚠️ 安裝完成後請立即刪除 <code>install.php</code>！
</div>

<div class="steps steps-vertical gap-6">

  <div class="card bg-base-100 shadow mb-4">
    <div class="card-body">
      <h2 class="card-title">Step 1：建立資料庫</h2>
      <ol class="list-decimal list-inside space-y-1 text-sm">
        <li>開啟 phpMyAdmin（<code>http://localhost/phpmyadmin</code>）</li>
        <li>匯入 <code>db/schema.sql</code></li>
        <li>或在 SQL 頁面執行 schema.sql 內容</li>
      </ol>
    </div>
  </div>

  <div class="card bg-base-100 shadow mb-4">
    <div class="card-body">
      <h2 class="card-title">Step 2：設定 config.php</h2>
      <p class="text-sm mb-3">填寫 DB 連線、Google OAuth 憑證、BASE_URL 和 SCREEN_PIN。</p>
      <div class="bg-base-200 p-3 rounded text-xs font-mono">
        GOOGLE_REDIRECT_URI = http://你的網域/council/auth/google_callback.php
      </div>
    </div>
  </div>

  <div class="card bg-base-100 shadow mb-4">
    <div class="card-body">
      <h2 class="card-title">Step 3：產生管理員密碼 Hash</h2>
      <form method="POST" class="flex gap-3 mb-3">
        <input name="password" type="password" class="input input-bordered flex-1"
               placeholder="請輸入管理員密碼" required>
        <button class="btn btn-primary">產生 Hash</button>
      </form>
      <?php if ($msg): ?>
      <div class="bg-base-200 p-3 rounded text-xs break-all font-mono"><?= htmlspecialchars($msg) ?></div>
      <p class="text-sm mt-2">複製上方 hash，執行以下 SQL 更新管理員密碼：</p>
      <div class="bg-base-200 p-3 rounded text-xs font-mono mt-1">
        UPDATE hosts SET password_hash='<?= htmlspecialchars($msg) ?>' WHERE is_admin=1;
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card bg-base-100 shadow mb-4">
    <div class="card-body">
      <h2 class="card-title">Step 4：Google OAuth 設定</h2>
      <ol class="list-decimal list-inside space-y-1 text-sm">
        <li>前往 <a class="link" href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a></li>
        <li>建立專案 → API 和服務 → 憑證 → 建立 OAuth 2.0 用戶端 ID</li>
        <li>應用程式類型選「網頁應用程式」</li>
        <li>已授權的重新導向 URI：填入 <code>GOOGLE_REDIRECT_URI</code> 的值</li>
        <li>複製 Client ID 和 Client Secret 填入 <code>config.php</code></li>
      </ol>
    </div>
  </div>

  <div class="card bg-base-100 shadow border-2 border-error">
    <div class="card-body">
      <h2 class="card-title text-error">Step 5：刪除此檔案！</h2>
      <p class="text-sm">安裝完成後，立即刪除 <code>install.php</code>。</p>
      <code class="text-xs bg-base-200 p-2 rounded block mt-2">rm /path/to/council/install.php</code>
    </div>
  </div>

</div>
</body>
</html>
