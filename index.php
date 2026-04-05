<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db/connect.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_GET['pin']) && $_GET['pin'] === SCREEN_PIN) {
    header('Location: ' . BASE_URL . '/screen/index.php');
    exit;
}

$role = current_role();
if ($role === 'admin' || $role === 'host') { header('Location: ' . BASE_URL . '/admin/index.php'); exit; }
if ($role === 'member' || $role === 'observer') { header('Location: ' . BASE_URL . '/member/index.php'); exit; }

$err_map = [
    'forbidden'    => '您沒有存取權限。',
    'oauth_denied' => '已取消 Google 登入。',
    'oauth_fail'   => 'Google 登入失敗，請稍後再試。',
    'wrong_domain' => '請使用學校（@' . ALLOWED_DOMAIN . '）信箱登入。',
    'not_in_list'  => '您的帳號不在本次會議名單中，請聯絡會議聯絡人。',
    'no_meeting'   => '目前沒有進行中的會議。',
];
$err = $err_map[$_GET['err'] ?? ''] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-TW" data-theme="wireframe">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>線上議事系統 | 高師大附中學生議會</title>
<link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/custom.css">
<style>
  /* Login page specific */
  body {
    background-color: #F4F5F7;
    min-height: 100svh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    overflow: hidden;
  }

  /* Subtle grid texture */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
      linear-gradient(rgba(0,85,255,0.03) 1px, transparent 1px),
      linear-gradient(90deg, rgba(0,85,255,0.03) 1px, transparent 1px);
    background-size: 32px 32px;
    pointer-events: none;
    z-index: 0;
  }

  /* Floating brand orb */
  body::after {
    content: '';
    position: fixed;
    width: 600px;
    height: 600px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(0,85,255,0.06) 0%, transparent 70%);
    top: -100px;
    right: -100px;
    pointer-events: none;
    z-index: 0;
  }

  .login-wrapper {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 400px;
  }

  /* Identity block */
  .identity-block {
    text-align: center;
    margin-bottom: 32px;
  }

  .logo-ring {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid #E4E7EB;
    padding: 8px;
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
  }

  .login-title {
    font-family: 'Chiron GoRound TC', 'Noto Sans TC', sans-serif;
    font-size: 1.75rem;
    font-weight: 700;
    color: #111827;
    letter-spacing: -0.02em;
    line-height: 1.2;
  }

  .login-subtitle {
    font-size: 0.875rem;
    color: #9CA3AF;
    margin-top: 6px;
  }

  /* Main card */
  .login-card {
    background: #fff;
    border: 1px solid #E4E7EB;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.05);
  }

  /* Google button */
  .btn-google {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 14px 24px;
    background: #fff;
    border: 1px solid #E4E7EB;
    border-radius: 10px;
    font-family: 'Chiron GoRound TC', 'Noto Sans TC', sans-serif;
    font-size: 0.95rem;
    font-weight: 500;
    color: #111827;
    text-decoration: none;
    transition: border-color 0.15s, background 0.15s, transform 0.2s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.15s;
    cursor: pointer;
  }
  .btn-google:hover {
    border-color: #C9CDD4;
    background: #FAFAFA;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  }
  .btn-google:active { transform: scale(0.97); }

  /* Host button */
  .btn-host {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 24px;
    background: transparent;
    border: 1px solid #E4E7EB;
    border-radius: 10px;
    font-family: 'Chiron GoRound TC', 'Noto Sans TC', sans-serif;
    font-size: 0.875rem;
    font-weight: 500;
    color: #6B7280;
    text-decoration: none;
    transition: all 0.15s ease;
  }
  .btn-host:hover {
    background: #F4F5F7;
    border-color: #C9CDD4;
    color: #374151;
  }

  /* Section label */
  .section-label {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #9CA3AF;
    margin-bottom: 12px;
    display: block;
    font-family: 'Chiron GoRound TC', sans-serif;
  }

  /* Divider */
  .login-divider {
    display: flex;
    align-items: center;
    gap: 16px;
    margin: 20px 0;
    color: #D1D5DB;
    font-size: 0.75rem;
  }
  .login-divider::before,
  .login-divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: #E4E7EB;
  }

  /* Error */
  .login-error {
    background: #FEF2F2;
    border: 1px solid #FECACA;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 0.875rem;
    color: #DC2626;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
  }

  /* Screen pin hint */
  .screen-hint {
    text-align: center;
    margin-top: 24px;
    font-size: 0.75rem;
    color: #9CA3AF;
  }
  .screen-hint code {
    background: #ECEEF2;
    border-radius: 4px;
    padding: 2px 6px;
    font-family: 'JetBrains Mono', monospace;
    color: #4B5563;
  }
</style>
</head>
<body>

<div class="login-wrapper">

  <!-- Identity -->
  <div class="identity-block animate-spring delay-0">
    <div class="logo-ring">
      <img src="<?= BASE_URL ?>/assets/ASHSSP Logo.png" alt="Logo" style="width:48px;height:48px;object-fit:contain;">
    </div>
    <div class="login-title">線上議事系統</div>
    <div class="login-subtitle">高師大附中學生議會議事輔助平台</div>
  </div>

  <!-- Main login card -->
  <div class="login-card animate-spring delay-2">

    <?php if ($err): ?>
    <div class="login-error">
      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <?= htmlspecialchars($err) ?>
    </div>
    <?php endif; ?>

    <!-- Google login -->
    <span class="section-label">與會者登入</span>
    <a href="<?= BASE_URL ?>/auth/google_login.php?as=member" class="btn-google">
      <svg width="20" height="20" viewBox="0 0 24 24">
        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
      </svg>
      以 Google 帳號登入（自動完成簽到）
    </a>

    <div class="login-divider">主辦人</div>

    <a href="<?= BASE_URL ?>/auth/admin_login.php" class="btn-host">
      <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
      </svg>
      主辦人 / 管理員登入
    </a>

  </div>

  <!-- Screen hint -->
  <!-- <div class="screen-hint animate-float delay-4">
    大螢幕請在網址後加上 <code>?pin=XXXX</code>
  </div> -->

</div>

</body>
</html>
