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
function h($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}
?>
<!DOCTYPE html>
<html lang="zh-TW" data-theme="wireframe">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>主辦人登入 | 議事系統</title>
<link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/custom.css">
<style>
  body {
    background-color: #F4F5F7;
    min-height: 100svh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
  }
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image: linear-gradient(rgba(0,85,255,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(0,85,255,0.03) 1px, transparent 1px);
    background-size: 32px 32px;
    pointer-events: none;
    z-index: 0;
  }
  .login-card {
    position: relative; z-index: 1;
    background: #fff;
    border: 1px solid #E4E7EB;
    border-radius: 20px;
    padding: 40px 36px;
    width: 100%; max-width: 380px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.05);
  }
  .field-label { font-size: 0.8rem; font-weight: 600; color: #6B7280; margin-bottom: 6px; display: block; font-family: 'Chiron GoRound TC', sans-serif; }
  .field-input {
    width: 100%; padding: 10px 14px;
    border: 1px solid #E4E7EB; border-radius: 10px;
    background: #FAFAFA; font-size: 0.95rem; color: #111827;
    transition: border-color 0.15s, box-shadow 0.15s;
    font-family: 'Lato', sans-serif;
    outline: none;
  }
  .field-input:focus { border-color: #0055FF; box-shadow: 0 0 0 3px rgba(0,85,255,0.1); background: #fff; }
</style>
</head>
<body>

<div class="login-card animate-spring">
  <!-- Header -->
  <div style="text-align:center;margin-bottom:28px;">
    <div style="width:48px;height:48px;border-radius:50%;background:#EEF3FF;border:1px solid #DBEAFE;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
      <svg width="22" height="22" fill="none" stroke="#0055FF" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
      </svg>
    </div>
    <div style="font-family:'Chiron GoRound TC',sans-serif;font-size:1.25rem;font-weight:700;color:#111827;">主辦人登入</div>
    <div style="font-size:0.8rem;color:#9CA3AF;margin-top:4px;">議事系統後台</div>
  </div>

  <?php if ($err): ?>
  <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:10px 14px;font-size:0.85rem;color:#DC2626;margin-bottom:20px;display:flex;align-items:center;gap:8px;">
    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <?= h($err) ?>
  </div>
  <?php endif; ?>

  <form method="POST" style="display:flex;flex-direction:column;gap:16px;">
    <div>
      <label class="field-label">帳號</label>
      <input name="username" type="text" class="field-input" placeholder="管理員帳號" required autocomplete="username">
    </div>
    <div>
      <label class="field-label">密碼</label>
      <input name="password" type="password" class="field-input" placeholder="••••••••" required autocomplete="current-password">
    </div>
    <button type="submit" style="margin-top:8px;width:100%;padding:12px;background:#0055FF;color:#fff;border:none;border-radius:10px;font-family:'Chiron GoRound TC',sans-serif;font-size:0.95rem;font-weight:600;cursor:pointer;transition:background 0.15s,transform 0.15s;">
      登入
    </button>
  </form>

  <div style="margin:20px 0;display:flex;align-items:center;gap:12px;color:#D1D5DB;font-size:0.75rem;">
    <div style="flex:1;height:1px;background:#E4E7EB;"></div>或<div style="flex:1;height:1px;background:#E4E7EB;"></div>
  </div>

  <a href="<?= BASE_URL ?>/auth/google_login.php?as=host"
     style="width:100%;display:flex;align-items:center;justify-content:center;gap:10px;padding:11px 16px;border:1px solid #E4E7EB;border-radius:10px;font-family:'Chiron GoRound TC',sans-serif;font-size:0.875rem;color:#6B7280;text-decoration:none;transition:all 0.15s;">
    <svg width="16" height="16" viewBox="0 0 24 24">
      <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
      <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
      <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
      <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
    </svg>
    Google 帳號登入（主辦人）
  </a>

  <div style="text-align:center;margin-top:20px;">
    <a href="<?= BASE_URL ?>/index.php" style="font-size:0.8rem;color:#9CA3AF;text-decoration:none;">← 返回首頁</a>
  </div>
</div>

</body>
</html>
