<?php
$page_title = '後台總覽';
require_once __DIR__ . '/../includes/admin_layout.php';

$pdo = db();
$mtg = $meeting;

$stats = [];
if ($mtg) {
    $stats['total']           = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE meeting_id={$mtg['id']} AND type='attendee'")->fetchColumn();
    $stats['present']         = (int)$pdo->query("SELECT COUNT(*) FROM attendance a JOIN members m ON m.id=a.member_id WHERE a.meeting_id={$mtg['id']} AND m.type='attendee'")->fetchColumn();
    $stats['observer']        = (int)$pdo->query("SELECT COUNT(*) FROM members WHERE meeting_id={$mtg['id']} AND type='observer'")->fetchColumn();
    $stats['agenda']          = (int)$pdo->query("SELECT COUNT(*) FROM agenda_items WHERE meeting_id={$mtg['id']}")->fetchColumn();
    $stats['pending_motions'] = (int)$pdo->query("SELECT COUNT(*) FROM temp_motions WHERE meeting_id={$mtg['id']} AND status='pending'")->fetchColumn();
    $phase = get_phase($mtg['id']);
    $phase_labels = [
        'standby'     => '待機中（開放簽到）',
        'agenda'      => '議程進行中',
        'resolution'  => '表決進行中',
        'election'    => '選舉進行中',
        'temp_motion' => '臨時動議',
        'ended'       => '會議結束',
    ];
    $phase_icons = [
        'standby' => '⏳', 'agenda' => '📣', 'resolution' => '🪧',
        'election' => '🏆', 'temp_motion' => '📝', 'ended' => '✔',
    ];
}
?>

<div class="page-title animate-float delay-0">🏠 後台總覽</div>

<?php if (!$mtg): ?>
<!-- No meeting state -->
<div style="max-width:480px;">
  <div class="bento-card animate-spring delay-1" style="padding:40px;text-align:center;">
    <div style="font-size:3rem;margin-bottom:16px;">📋</div>
    <div style="font-family:'Chiron GoRound TC',sans-serif;font-size:1.1rem;font-weight:600;color:#111827;margin-bottom:8px;">尚未建立會議</div>
    <div style="color:#9CA3AF;font-size:0.875rem;margin-bottom:24px;">請先到「會議設定」建立本次會議。</div>
    <a href="<?= BASE_URL ?>/admin/setup.php" class="btn btn-primary">前往建立會議 →</a>
  </div>
</div>

<?php else: ?>

<!-- Bento Grid -->
<div style="display:grid;grid-template-columns:repeat(12,1fr);gap:16px;">

  <!-- ① 會議資訊 (8 cols) -->
  <div class="bento-card animate-float delay-0 accent-top" style="grid-column:span 8;padding:28px 32px;">
    <div class="bento-label">目前會議</div>
    <div style="font-family:'Chiron GoRound TC',sans-serif;font-size:1.35rem;font-weight:700;color:#111827;margin-bottom:8px;line-height:1.3;">
      <?= h($mtg['title']) ?>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:16px;color:#6B7280;font-size:0.875rem;margin-bottom:20px;">
      <span>📍 <?= h($mtg['location'] ?: '—') ?></span>
      <span>🕐 <?= h($mtg['start_time'] ?: '—') ?></span>
      <?php if ($mtg['reason']): ?>
      <span>📌 <?= h($mtg['reason']) ?></span>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <?php if ($mtg['status'] === 'preparing'): ?>
        <form method="POST" action="<?= BASE_URL ?>/api/phase.php">
          <input type="hidden" name="meeting_id" value="<?= $mtg['id'] ?>">
          <input type="hidden" name="type" value="standby">
          <button class="btn btn-success btn-sm">▶ 開始會議</button>
        </form>
      <?php elseif ($mtg['status'] === 'active'): ?>
        <a href="<?= BASE_URL ?>/admin/control.php" class="btn btn-primary btn-sm">🛑 現場控制台</a>
        <a href="<?= BASE_URL ?>/screen/index.php?pin=<?= SCREEN_PIN ?>" target="_blank" class="btn btn-outline btn-sm">📺 大螢幕</a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/api/export_txt.php?meeting_id=<?= $mtg['id'] ?>" class="btn btn-outline btn-sm">📄 匯出紀錄</a>
    </div>
  </div>

  <!-- ② 目前階段 (4 cols) -->
  <div class="bento-card animate-float delay-1" style="grid-column:span 4;padding:28px 24px;display:flex;flex-direction:column;justify-content:center;">
    <div class="bento-label">目前階段</div>
    <?php if ($mtg['status'] === 'active'): ?>
    <div style="font-size:2.5rem;margin-bottom:10px;"><?= $phase_icons[$phase['phase_type']] ?? '•' ?></div>
    <div style="font-family:'Noto Emoji', 'Chiron GoRound TC',sans-serif;font-size:1rem;font-weight:600;color:#111827;">
      <?= $phase_labels[$phase['phase_type']] ?? $phase['phase_type'] ?>
    </div>
    <?php else: ?>
    <div style="color:#9CA3AF;font-size:0.875rem;">
      <?= $mtg['status'] === 'preparing' ? '尚未開始' : '已結束' ?>
    </div>
    <?php endif; ?>
    <?php if ($stats['pending_motions'] > 0): ?>
    <div style="margin-top:16px;padding:10px 14px;background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;font-size:0.8rem;color:#D97706;font-weight:600;">
      ⚠ <?= $stats['pending_motions'] ?> 件待審動議
    </div>
    <?php endif; ?>
  </div>

  <!-- ③ 統計列 (5 × 12/5 cols) -->
  <?php
  $stat_items = [
    ['label'=>'應出席', 'value'=>$stats['total'],                           'color'=>'#0055FF', 'bg'=>'#EEF3FF'],
    ['label'=>'已簽到', 'value'=>$stats['present'],                         'color'=>'#16A34A', 'bg'=>'#F0FDF4'],
    ['label'=>'缺　席', 'value'=>$stats['total'] - $stats['present'],       'color'=>'#DC2626', 'bg'=>'#FEF2F2'],
    ['label'=>'列　席', 'value'=>$stats['observer'],                        'color'=>'#6B7280', 'bg'=>'#F4F5F7'],
    ['label'=>'議程項目','value'=>$stats['agenda'],                         'color'=>'#7C3AED', 'bg'=>'#F5F3FF'],
  ];
  foreach ($stat_items as $i => $s):
  ?>
  <div class="bento-card animate-float delay-<?= $i + 2 ?>" style="grid-column:span <?= ($i < 2) ? '3' : (($i == 2) ? '2' : '2') ?>;padding:20px 24px;border-left:3px solid <?= $s['color'] ?>;">
    <div class="bento-label"><?= $s['label'] ?></div>
    <div style="font-family:'JetBrains Mono',monospace;font-size:2rem;font-weight:700;color:<?= $s['color'] ?>;line-height:1;"><?= $s['value'] ?></div>
  </div>
  <?php endforeach; ?>

  <!-- ④ 快速連結 (4 cols each) -->
  <?php
  $quick_links = [
    ['href'=>'members.php',  'icon'=>'👥', 'title'=>'與會成員', 'desc'=>'新增、編輯出席人與列席人'],
    ['href'=>'agenda.php',   'icon'=>'📋', 'title'=>'議程管理', 'desc'=>'報告事項、案由、選舉'],
    ['href'=>'control.php',  'icon'=>'🛑', 'title'=>'現場控制台','desc'=>'切換階段、管理表決'],
  ];
  foreach ($quick_links as $i => $lk):
  ?>
  <a href="<?= BASE_URL ?>/admin/<?= $lk['href'] ?>" class="bento-card animate-float delay-<?= $i + 4 ?>"
     style="grid-column:span 4;padding:24px 28px;display:flex;align-items:center;gap:20px;text-decoration:none;">
    <div style="font-size:2rem;flex-shrink:0;"><?= $lk['icon'] ?></div>
    <div>
      <div style="font-family:'Chiron GoRound TC',sans-serif;font-size:1rem;font-weight:700;color:#111827;"><?= $lk['title'] ?></div>
      <div style="font-size:0.8rem;color:#9CA3AF;margin-top:3px;"><?= $lk['desc'] ?></div>
    </div>
    <svg style="margin-left:auto;color:#D1D5DB;" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
  </a>
  <?php endforeach; ?>

</div>
<?php endif; ?>

<style>
@media (max-width: 768px) {
  [style*="grid-template-columns:repeat(12"] { grid-template-columns: 1fr 1fr !important; }
  [style*="grid-column:span 8"],
  [style*="grid-column:span 4"],
  [style*="grid-column:span 3"],
  [style*="grid-column:span 2"] { grid-column: span 2 !important; }
}
</style>

</div></body></html>
