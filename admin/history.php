<?php
$page_title = '歷次會議紀錄';
require_once __DIR__ . '/../includes/admin_layout.php';
$pdo = db();

$meetings = $pdo->query(
    "SELECT m.*,
        (SELECT COUNT(*) FROM members WHERE meeting_id=m.id AND type='attendee') AS total_attendees,
        (SELECT COUNT(*) FROM attendance a JOIN members mb ON mb.id=a.member_id WHERE a.meeting_id=m.id AND mb.type='attendee') AS present_count
     FROM meeting m ORDER BY m.id DESC"
)->fetchAll();
?>

<h1 class="text-3xl font-bold mb-6">📚 歷次會議紀錄</h1>

<div class="card bg-base-100 shadow">
  <div class="card-body p-0">
    <div class="overflow-x-auto">
      <table class="table table-zebra">
        <thead>
          <tr class="bg-base-200">
            <th>#</th><th>會議名稱</th><th>地點</th><th>開始時間</th>
            <th>出席</th><th>狀態</th><th>操作</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($meetings as $m): ?>
        <tr>
          <td class="text-gray-400 text-sm"><?= $m['id'] ?></td>
          <td class="font-semibold max-w-xs">
            <div><?= h($m['title']) ?></div>
            <div class="text-xs text-gray-400"><?= h($m['reason'] ?? '') ?></div>
          </td>
          <td class="text-sm text-gray-500"><?= h($m['location'] ?? '—') ?></td>
          <td class="text-sm">
            <?= $m['actual_start_at']
                ? date('Y/m/d H:i', strtotime($m['actual_start_at']))
                : ($m['start_time'] ? date('Y/m/d H:i', strtotime($m['start_time'])) : '—') ?>
          </td>
          <td>
            <?php if ($m['total_attendees']): ?>
            <span class="text-green-600 font-bold"><?= $m['present_count'] ?></span>
            <span class="text-gray-400"> / <?= $m['total_attendees'] ?></span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td>
            <div class="badge <?= $m['status']==='active' ? 'badge-success' : ($m['status']==='ended' ? 'badge-ghost' : 'badge-warning') ?> badge-sm">
              <?= ['preparing'=>'準備中','active'=>'進行中','ended'=>'已結束'][$m['status']] ?>
            </div>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/api/export_txt.php?meeting_id=<?= $m['id'] ?>"
               class="btn btn-xs btn-outline">📤 匯出</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$meetings): ?>
        <tr><td colspan="7" class="text-center text-gray-400 py-8">尚無會議紀錄</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</div></body></html>