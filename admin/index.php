<?php
$page_title = '後台總覽';
require_once __DIR__ . '/../includes/admin_layout.php';

$pdo = db();
$mtg = $meeting; // from layout

$stats = [];
if ($mtg) {
    $stats['total']    = (int)$pdo->prepare("SELECT COUNT(*) FROM members WHERE meeting_id=? AND type='attendee'")->execute([$mtg['id']]) ? $pdo->query("SELECT COUNT(*) FROM members WHERE meeting_id={$mtg['id']} AND type='attendee'")->fetchColumn() : 0;
    $stats['present']  = (int)$pdo->query("SELECT COUNT(*) FROM attendance a JOIN members m ON m.id=a.member_id WHERE a.meeting_id={$mtg['id']} AND m.type='attendee'")->fetchColumn();
    $stats['observer'] = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE meeting_id={$mtg['id']} AND type='observer'")->fetchColumn();
    $stats['agenda']   = (int)$pdo->query("SELECT COUNT(*) FROM agenda_items WHERE meeting_id={$mtg['id']}")->fetchColumn();
    $stats['pending_motions'] = (int)$pdo->query("SELECT COUNT(*) FROM temp_motions WHERE meeting_id={$mtg['id']} AND status='pending'")->fetchColumn();

    $phase = get_phase($mtg['id']);
    $phase_labels = [
        'standby'     => '⏳ 待機中（簽到）',
        'agenda'      => '📣 議程進行中',
        'resolution'  => '🪧 表決中',
        'election'    => '🏆 選舉中',
        'temp_motion' => '📝 臨時動議',
        'ended'       => '✅ 會議結束',
    ];
}
?>

<h1 class="text-3xl font-bold mb-6"><span class="font-emoji">🏠</span> 後台總覽</h1>

<?php if (!$mtg): ?>
<div class="alert alert-warning mb-6 font-emoji">
  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
  尚未建立會議。請先到「🔧會議設定」建立會議。
</div>
<a href="<?= BASE_URL ?>/admin/setup.php" class="btn btn-primary">前往建立會議 →</a>

<?php else: ?>

<!-- 會議資訊 -->
<div class="card bg-base-100 shadow mb-6">
  <div class="card-body">
    <div class="flex justify-between items-start flex-wrap gap-4">
      <div>
        <h2 class="card-title text-2xl"><?= h($mtg['title']) ?></h2>
        <p class="text-gray-500">📍 <?= h($mtg['location']) ?> &nbsp;|&nbsp; 🕐 <?= h($mtg['start_time']) ?></p>
        <p class="text-gray-500 mt-1">事由：<?= h($mtg['reason']) ?></p>
      </div>
      <div class="flex gap-2 flex-wrap">
        <?php if ($mtg['status'] === 'preparing'): ?>
        <form method="POST" action="<?= BASE_URL ?>/api/phase.php">
          <input type="hidden" name="meeting_id" value="<?= $mtg['id'] ?>">
          <input type="hidden" name="type" value="standby">
          <button class="btn btn-success">▶ 開始會議（進入待機）</button>
        </form>
        <?php elseif ($mtg['status'] === 'active'): ?>
        <a href="<?= BASE_URL ?>/admin/control.php" class="btn btn-primary">🛑 進入現場控制台</a>
        <a href="<?= BASE_URL ?>/screen/index.php?pin=<?= SCREEN_PIN ?>" target="_blank" class="btn btn-outline">📺 大螢幕</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/api/export_txt.php?meeting_id=<?= $mtg['id'] ?>" class="btn btn-outline btn-sm">📄 匯出紀錄</a>
      </div>
    </div>
  </div>
</div>

<!-- 統計卡片 -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
  <div class="stat bg-base-100 shadow rounded-box">
    <div class="stat-title">應出席</div>
    <div class="stat-value text-primary"><?= $stats['total'] ?></div>
    <div class="stat-desc">人</div>
  </div>
  <div class="stat bg-base-100 shadow rounded-box">
    <div class="stat-title">已簽到</div>
    <div class="stat-value text-success"><?= $stats['present'] ?></div>
    <div class="stat-desc">出席議員</div>
  </div>
  <div class="stat bg-base-100 shadow rounded-box">
    <div class="stat-title">缺席</div>
    <div class="stat-value text-error"><?= $stats['total'] - $stats['present'] ?></div>
    <div class="stat-desc">人</div>
  </div>
  <div class="stat bg-base-100 shadow rounded-box">
    <div class="stat-title">列席</div>
    <div class="stat-value text-secondary"><?= $stats['observer'] ?></div>
    <div class="stat-desc">人</div>
  </div>
  <div class="stat bg-base-100 shadow rounded-box <?= $stats['pending_motions'] > 0 ? 'bg-warning/20' : '' ?>">
    <div class="stat-title">待審臨時動議</div>
    <div class="stat-value <?= $stats['pending_motions'] > 0 ? 'text-warning' : '' ?>"><?= $stats['pending_motions'] ?></div>
    <div class="stat-desc"><?= $stats['pending_motions'] > 0 ? '⚠️ 需要審核' : '無待審' ?></div>
  </div>
</div>

<!-- 目前階段 -->
<?php if ($mtg['status'] === 'active'): ?>
<div class="alert alert-info mb-6">
  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
  <strong>目前階段：</strong><?= $phase_labels[$phase['phase_type']] ?? $phase['phase_type'] ?>
</div>
<?php endif; ?>

<!-- 快速連結 -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
  <a href="<?= BASE_URL ?>/admin/members.php" class="card bg-base-100 shadow hover:shadow-lg transition-shadow">
    <div class="card-body items-center text-center">
      <div class="text-4xl">👥</div>
      <h3 class="card-title">成員管理</h3>
      <p class="text-gray-500 text-sm">新增/編輯出席人與列席人名單</p>
    </div>
  </a>
  <a href="<?= BASE_URL ?>/admin/agenda.php" class="card bg-base-100 shadow hover:shadow-lg transition-shadow">
    <div class="card-body items-center text-center">
      <div class="text-4xl">📋</div>
      <h3 class="card-title">議程管理</h3>
      <p class="text-gray-500 text-sm">設定報告事項、案由、選舉</p>
    </div>
  </a>
  <a href="<?= BASE_URL ?>/admin/control.php" class="card bg-base-100 shadow hover:shadow-lg transition-shadow">
    <div class="card-body items-center text-center">
      <div class="text-4xl">🛑</div>
      <h3 class="card-title">現場控制台</h3>
      <p class="text-gray-500 text-sm">切換階段、管理表決、發言佇列</p>
    </div>
  </a>
</div>

<?php endif; ?>

</div></body></html>
