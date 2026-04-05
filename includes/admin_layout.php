<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_host();
$meeting = active_meeting();

// Determine active page for nav highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');
function nav_active($page) {
    global $current_page;
    return str_contains($current_page, $page) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="zh-TW" data-theme="wireframe">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($page_title ?? '主辦人後台') ?> | 議事系統</title>
<link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/custom.css">
<style>
  body { background: #F4F5F7; }

  /* ── Admin Navbar ─────────────────────────────── */
  .admin-nav {
    height: 60px;
    background: #fff;
    border-bottom: 1px solid #E4E7EB;
    display: flex;
    align-items: center;
    padding: 0 24px;
    position: sticky;
    top: 0;
    z-index: 50;
    gap: 8px;
  }

  .nav-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: 'Chiron GoRound TC', 'Noto Sans TC', sans-serif;
    font-weight: 700;
    font-size: 0.95rem;
    color: #111827;
    text-decoration: none;
    margin-right: 8px;
    flex-shrink: 0;
  }

  .nav-links {
    display: flex;
    align-items: center;
    gap: 2px;
    flex: 1;
    overflow-x: auto;
    scrollbar-width: none;
  }
  .nav-links::-webkit-scrollbar { display: none; }

  .nav-link {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-family: 'Noto Emoji', 'Chiron GoRound TC', 'Noto Sans TC', sans-serif;
    font-size: 0.85rem;
    font-weight: 500;
    color: #6B7280;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.12s, color 0.12s;
    flex-shrink: 0;
  }
  .nav-link:hover { background: #F4F5F7; color: #111827; }
  .nav-link.active { background: #EEF3FF; color: #0055FF; }

  .nav-end {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-left: auto;
    flex-shrink: 0;
  }

  /* Mobile menu */
  .mobile-menu-btn {
    display: none;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    border: 1px solid #E4E7EB;
    background: transparent;
    cursor: pointer;
    color: #6B7280;
    flex-shrink: 0;
  }

  .mobile-drawer {
    display: none;
    position: fixed;
    top: 60px;
    left: 0;
    right: 0;
    background: #fff;
    border-bottom: 1px solid #E4E7EB;
    padding: 12px 16px;
    z-index: 49;
    flex-direction: column;
    gap: 4px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
  }
  .mobile-drawer.open { display: flex; }
  .mobile-drawer .nav-link { padding: 10px 14px; }

  /* Status pill */
  .status-pill {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    font-family: 'Noto Emoji', 'Chiron GoRound TC', sans-serif;
    border: 1px solid transparent;
  }
  .status-pill.active   { background: #F0FDF4; color: #16A34A; border-color: #BBF7D0; }
  .status-pill.active::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #16A34A; animation: pulse-glow 2s infinite; }
  .status-pill.preparing { background: #FFFBEB; color: #D97706; border-color: #FDE68A; }
  .status-pill.ended     { background: #F4F5F7; color: #9CA3AF; border-color: #E4E7EB; }

  /* Avatar button */
  .avatar-btn {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: #EEF3FF;
    color: #0055FF;
    font-family: 'Noto Emoji', 'Chiron GoRound TC', sans-serif;
    font-weight: 700;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border: 1px solid #DBEAFE;
    transition: background 0.15s;
  }
  .avatar-btn:hover { background: #DBEAFE; }

  /* Page container */
  .page-container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 32px 24px;
    min-height: calc(100svh - 60px);
  }

  /* Page title */
  .page-title {
    font-family: 'Noto Emoji', 'Chiron GoRound TC', 'Noto Sans TC', sans-serif;
    font-size: 1.5rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 24px;
  }

  @media (max-width: 768px) {
    .nav-links { display: none; }
    .mobile-menu-btn { display: flex; }
    .page-container { padding: 20px 16px; }
  }
</style>
</head>
<body>

<!-- Navbar -->
<nav class="admin-nav">
  <button class="mobile-menu-btn" onclick="toggleMobileMenu()" aria-label="選單">
    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
    </svg>
  </button>

  <a href="<?= BASE_URL ?>/admin/index.php" class="nav-logo">
    <img src="<?= BASE_URL ?>/assets/ASHSSP Logo.png" alt="Logo" style="width:28px;height:28px;object-fit:contain;border-radius:6px;">
    議事系統
  </a>

  <div class="nav-links">
    <a href="<?= BASE_URL ?>/admin/index.php"    class="nav-link <?= nav_active('index') ?>">🏠 總覽</a>
    <a href="<?= BASE_URL ?>/admin/setup.php"    class="nav-link <?= nav_active('setup') ?>">🔧 會議設定</a>
    <a href="<?= BASE_URL ?>/admin/members.php"  class="nav-link <?= nav_active('members') ?>">👥 與會成員</a>
    <a href="<?= BASE_URL ?>/admin/agenda.php"   class="nav-link <?= nav_active('agenda') ?>">📋 議程</a>
    <a href="<?= BASE_URL ?>/admin/control.php"  class="nav-link <?= nav_active('control') ?>">🛑 現場控制</a>
    <a href="<?= BASE_URL ?>/admin/history.php"  class="nav-link <?= nav_active('history') ?>">📚 歷次會議</a>
    <?php if (current_role() === 'admin'): ?>
    <a href="<?= BASE_URL ?>/admin/hosts.php"    class="nav-link <?= nav_active('hosts') ?>">🔑 主辦人</a>
    <?php endif; ?>
  </div>

  <div class="nav-end">
    <?php if ($meeting): ?>
    <div id="nav-status-pill" class="status-pill <?= $meeting['status'] === 'active' ? 'active' : ($meeting['status'] === 'preparing' ? 'preparing' : 'ended') ?>">
      <?= $meeting['status'] === 'active' ? '進行中' : ($meeting['status'] === 'preparing' ? '準備中' : '已結束') ?>
    </div>
    <?php endif; ?>

    <div class="dropdown dropdown-end">
      <div class="avatar-btn" tabindex="0" role="button">
        <?= mb_substr($_SESSION['user_name'] ?? 'A', 0, 1) ?>
      </div>
      <ul tabindex="0" class="dropdown-content menu p-2 w-44 z-50 mt-2">
        <li class="px-3 py-1.5 text-xs text-gray-400 font-medium"><?= h($_SESSION['user_name'] ?? '') ?></li>
        <li class="border-t border-gray-100 mt-1 pt-1">
          <a href="<?= BASE_URL ?>/auth/logout.php" class="text-red-500 hover:bg-red-50">登出</a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Mobile drawer -->
<div class="mobile-drawer" id="mobile-drawer">
  <a href="<?= BASE_URL ?>/admin/index.php"   class="nav-link <?= nav_active('index') ?>">🏠 總覽</a>
  <a href="<?= BASE_URL ?>/admin/setup.php"   class="nav-link <?= nav_active('setup') ?>">🔧 會議設定</a>
  <a href="<?= BASE_URL ?>/admin/members.php" class="nav-link <?= nav_active('members') ?>">👥 與會成員</a>
  <a href="<?= BASE_URL ?>/admin/agenda.php"  class="nav-link <?= nav_active('agenda') ?>">📋 議程</a>
  <a href="<?= BASE_URL ?>/admin/control.php" class="nav-link <?= nav_active('control') ?>">🛑 現場控制</a>
  <a href="<?= BASE_URL ?>/admin/history.php" class="nav-link <?= nav_active('history') ?>">📚 歷次會議</a>
  <?php if (current_role() === 'admin'): ?>
  <a href="<?= BASE_URL ?>/admin/hosts.php"   class="nav-link <?= nav_active('hosts') ?>">🔑 主辦人</a>
  <?php endif; ?>
</div>

<div class="page-container">

<script>
function toggleMobileMenu() {
    document.getElementById('mobile-drawer').classList.toggle('open');
}

async function refreshNavBadge() {
    try {
        const r = await fetch('<?= BASE_URL ?>/api/status.php?meeting_id=<?= $meeting ? $meeting['id'] : 0 ?>');
        const d = await r.json();
        if (!d.ok) return;
        const pill = document.getElementById('nav-status-pill');
        if (!pill) return;
        const s = d.data.meeting?.status;
        pill.textContent = s === 'active' ? '進行中' : s === 'preparing' ? '準備中' : '已結束';
        pill.className = `status-pill ${s === 'active' ? 'active' : s === 'preparing' ? 'preparing' : 'ended'}`;
        if (s === 'active') pill.insertAdjacentHTML('afterbegin', '');
    } catch(e) {}
    setTimeout(refreshNavBadge, 5000);
}
<?php if ($meeting): ?>refreshNavBadge();<?php endif; ?>
</script>
