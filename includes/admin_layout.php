<?php
// includes/admin_layout.php
// 使用前先設定 $page_title
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_host();
$meeting = active_meeting();
?>
<!DOCTYPE html>
<html lang="zh-TW" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($page_title ?? '主辦人後台') ?> | 議事系統</title>
<link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/custom.css">
</head>
<body class="bg-base-200">

<!-- Navbar -->
<div class="navbar bg-blue-950 text-white shadow px-4 sticky top-0 z-50">
  <div class="navbar-start">
    <div class="dropdown">
      <label tabindex="0" class="btn btn-ghost lg:hidden">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"/>
        </svg>
      </label>
      <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 text-base-content rounded-box w-52">
        <li><a href="<?= BASE_URL ?>/admin/index.php">🏠 總覽</a></li>
        <li><a href="<?= BASE_URL ?>/admin/setup.php">🔧 會議設定</a></li>
        <li><a href="<?= BASE_URL ?>/admin/members.php">👥 成員管理</a></li>
        <li><a href="<?= BASE_URL ?>/admin/agenda.php">📋 議程管理</a></li>
        <li><a href="<?= BASE_URL ?>/admin/control.php">🛑 現場控制</a></li>
        <?php if (current_role() === 'admin'): ?>
        <li><a href="<?= BASE_URL ?>/admin/hosts.php">🔑 主辦人帳號</a></li>
        <?php endif; ?>
      </ul>
    </div>
    <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-ghost text-xl font-bold">🏛️ 議事系統</a>
  </div>
  <div class="navbar-center hidden lg:flex">
    <ul class="menu menu-horizontal px-1 gap-1">
      <li><a href="<?= BASE_URL ?>/admin/index.php" class="hover:bg-blue-800 rounded-lg">🏠 總覽</a></li>
      <li><a href="<?= BASE_URL ?>/admin/setup.php" class="hover:bg-blue-800 rounded-lg">🔧 會議設定</a></li>
      <li><a href="<?= BASE_URL ?>/admin/members.php" class="hover:bg-blue-800 rounded-lg">👥 成員</a></li>
      <li><a href="<?= BASE_URL ?>/admin/agenda.php" class="hover:bg-blue-800 rounded-lg">📋 議程</a></li>
      <li>
        <a href="<?= BASE_URL ?>/admin/control.php"
           class="hover:bg-blue-800 rounded-lg <?= str_contains($_SERVER['PHP_SELF'],'control') ? 'bg-blue-700' : '' ?>">
          🛑 現場控制
        </a>
      </li>
      <?php if (current_role() === 'admin'): ?>
      <li><a href="<?= BASE_URL ?>/admin/hosts.php" class="hover:bg-blue-800 rounded-lg">🔑 主辦人</a></li>
      <?php endif; ?>
    </ul>
  </div>
  <div class="navbar-end gap-2">
    <?php if ($meeting): ?>
    <div id="nav-status-badge"  class="badge badge-<?= $meeting['status']==='active' ? 'success' : 'warning' ?> badge-lg">
      <?= $meeting['status']==='active' ? '會議進行中' : '準備中' ?>
    </div>
    <?php endif; ?>
    <div class="dropdown dropdown-end">
      <label tabindex="0" class="btn btn-ghost btn-circle avatar">
        <div class="w-8 rounded-full bg-blue-700 flex items-center justify-center text-sm font-bold">
          <?= mb_substr($_SESSION['user_name'] ?? 'A', 0, 1) ?>
        </div>
      </label>
      <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 text-base-content rounded-box w-40">
        <li class="menu-title text-xs"><?= h($_SESSION['user_name'] ?? '') ?></li>
        <li><a href="<?= BASE_URL ?>/auth/logout.php" class="text-error">登出</a></li>
      </ul>
    </div>
  </div>
</div>

<div class="container mx-auto px-4 py-6 max-w-7xl">


<script>
// 動態更新 navbar 狀態 badge
async function refreshNavBadge() {
    try {
        const r = await fetch('<?= BASE_URL ?>/api/status.php?meeting_id=<?= $meeting ? $meeting['id'] : 0 ?>');
        const d = await r.json();
        if (!d.ok) return;
        const badge = document.getElementById('nav-status-badge');
        if (!badge) return;
        const s = d.data.meeting?.status;
        badge.textContent = s === 'active' ? '會議進行中' : s === 'preparing' ? '準備中' : '已結束';
        badge.className = `badge badge-lg ${s === 'active' ? 'badge-success' : s === 'preparing' ? 'badge-warning' : 'badge-ghost'}`;
    } catch(e) {}
    setTimeout(refreshNavBadge, 5000);
}
<?php if ($meeting): ?>refreshNavBadge();<?php endif; ?>
</script>