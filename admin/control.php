<?php
$page_title = '現場控制台';
require_once __DIR__ . '/../includes/admin_layout.php';

if (!$meeting || $meeting['status'] !== 'active') {
    echo '<div class="alert alert-warning mb-4">會議尚未開始。</div>
          <a href="'.BASE_URL.'/admin/setup.php" class="btn btn-primary">前往設定 →</a>
          </div></body></html>';
    exit;
}

$mid = $meeting['id'];
?>

<!-- To fix the transform animation of .btn in DaisyUI -->
<style>
  .btn { transform: none !important; transition: background-color 0.15s, color 0.15s !important; }
  .btn:active { transform: scale(0.98) !important; }
</style>

<!-- 頂部會議資訊 -->
<div class="flex items-center justify-between mb-4 flex-wrap gap-3">
  <div>
    <h1 class="text-2xl font-bold">🛑 現場控制台</h1>
    <span id="elapsed-timer" class="badge badge-lg badge-ghost font-mono text-lg">00:00:00</span>
    <p class="text-gray-500 text-sm"><?= h($meeting['title']) ?> &nbsp;|&nbsp; <?= h($meeting['location']) ?></p>
  </div>
  <div class="flex gap-2">
    <a href="<?= BASE_URL ?>/screen/index.php?pin=<?= SCREEN_PIN ?>" target="_blank"
       class="btn btn-outline btn-sm">📺 開啟大螢幕</a>
    <a href="<?= BASE_URL ?>/api/export_txt.php?meeting_id=<?= $mid ?>"
       class="btn btn-outline btn-sm">📄 匯出紀錄</a>
  </div>
</div>

<!-- 待審臨時動議通知（動態） -->
<div id="motion-alert" class="hidden alert alert-warning mb-4">
  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
  </svg>
  <span><span id="motion-count">0</span> 件臨時動議待審核</span>
  <button onclick="document.getElementById('motions-panel').scrollIntoView({behavior:'smooth'})" class="btn btn-xs btn-warning">查看</button>
</div>

<div class="grid grid-cols-1 xl:grid-cols-4 gap-4">

<!-- ─── 左欄：階段切換 & 議程導航 ─────────────────────────────── -->
<div class="xl:col-span-1 space-y-4">

  <!-- 目前階段 -->
  <div class="card bg-base-100 shadow">
    <div class="card-body p-4">
      <h3 class="font-bold mb-2">⏱️ 目前階段</h3>
      <div id="current-phase" class="badge badge-lg badge-primary text-sm mb-3">載入中...</div>
      <div id="current-item-title" class="text-sm text-gray-600 font-medium"></div>

      <!-- 快速切換 -->
      <div class="divider my-2">快速切換</div>
      <button onclick="setPhase('standby')" class="btn btn-outline btn-sm w-full mb-1">⏳ 待機／簽到</button>
      <button onclick="setPhase('temp_motion')" class="btn btn-outline btn-sm w-full mb-1">📝 臨時動議</button>
      <button onclick="setPhase('ended')" class="btn btn-error btn-outline btn-sm w-full"
              onclick="return confirm('確定結束會議？')">⏹ 結束會議</button>
    </div>
  </div>

  <!-- 議程列表 -->
  <div class="card bg-base-100 shadow">
    <div class="card-body p-4">
      <h3 class="font-bold mb-3">📋 議程快速切換</h3>
      <div id="agenda-nav" class="space-y-1 max-h-80 overflow-y-auto">
        <div class="text-gray-400 text-sm text-center py-2">載入中...</div>
      </div>
    </div>
  </div>

</div>

<!-- ─── 中欄：表決/選舉控制 & 發言佇列 ──────────────────────── -->
<div class="xl:col-span-2 space-y-4">

  <!-- 表決控制 -->
  <div id="vote-panel" class="card bg-base-100 shadow hidden">
    <div class="card-body p-4">
      <h3 class="font-bold text-lg mb-3">🪧 表決控制</h3>
      <div id="vote-stats" class="grid grid-cols-3 gap-3 mb-4">
        <div class="stat bg-green-50 rounded p-3 text-center">
          <div class="text-xs text-gray-500">同意</div>
          <div class="text-2xl font-bold text-green-600" id="yes-count">0</div>
        </div>
        <div class="stat bg-red-50 rounded p-3 text-center">
          <div class="text-xs text-gray-500">反對</div>
          <div class="text-2xl font-bold text-red-600" id="no-count">0</div>
        </div>
        <div class="stat bg-yellow-50 rounded p-3 text-center">
          <div class="text-xs text-gray-500">棄權</div>
          <div class="text-2xl font-bold text-yellow-600" id="abstain-count">0</div>
        </div>
      </div>
      <div class="text-sm text-gray-500 mb-3">
        已投票：<span id="voted-count">0</span> / <span id="total-count">0</span> 人
        <span id="vote-progress" class="ml-2"></span>
      </div>
      <div id="vote-controls">
        <button id="close-vote-btn" onclick="closeVote()"
                class="btn btn-error w-full">
          ⏹ 截止表決（未投票者自動棄權）
        </button>
      </div>
      <div id="vote-closed-msg" class="hidden alert alert-success mt-2">
        表決已截止
      </div>
    </div>
  </div>

  <!-- 選舉控制 -->
  <div id="election-panel" class="card bg-base-100 shadow hidden">
    <div class="card-body p-4">
      <h3 class="font-bold text-lg mb-3">🏆 選舉控制</h3>
      <div id="election-seats" class="text-sm text-gray-600 mb-3"></div>
      <div id="candidates-list" class="space-y-2 mb-4"></div>
      <button onclick="closeElection()" class="btn btn-error w-full">
        ⏹ 截止選舉（未投票者自動棄權）
      </button>
    </div>
  </div>

  <!-- 成員狀態格 -->
  <div class="card bg-base-100 shadow">
    <div class="card-body p-4">
      <h3 class="font-bold mb-3">👥 與會人員狀態</h3>
      <div id="members-grid" class="grid grid-cols-3 sm:grid-cols-4 gap-2">
        <div class="text-gray-400 text-sm text-center col-span-4 py-4">載入中...</div>
      </div>
    </div>
  </div>

  <!-- 臨時動議管理 -->
  <div id="motions-panel" class="card bg-base-100 shadow">
    <div class="card-body p-4">
      <h3 class="font-bold mb-3">📝 臨時動議</h3>

      <!-- 主辦人直接新增 -->
      <form id="host-motion-form" class="mb-4 bg-base-200 p-3 rounded-lg">
        <div class="font-semibold text-sm mb-2">主辦人直接新增議程</div>
        <div class="flex gap-2 flex-wrap">
          <select id="motion-type" class="select select-bordered select-xs">
            <option value="temp">臨時動議（討論）</option>
            <option value="resolution">臨時動議（表決）</option>
            <option value="election">選舉</option>
          </select>
          <input id="motion-title" type="text" class="input input-bordered input-xs flex-1"
                 placeholder="動議標題">
          <button type="button" onclick="addHostMotion()" class="btn btn-xs btn-primary">新增</button>
        </div>
      </form>

      <!-- 議員送出的待審動議 -->
      <div class="font-semibold text-sm mb-2">議員提出（待審核）</div>
      <div id="pending-motions-list" class="space-y-2 max-h-60 overflow-y-auto">
        <div class="text-gray-400 text-sm text-center py-2">無待審動議</div>
      </div>
    </div>
  </div>

</div>

<!-- ─── 右欄：發言佇列 ─────────────────────────────────────────── -->
<div class="xl:col-span-1 space-y-4">

  <!-- 計時器控制 -->
  <div class="card bg-base-100 shadow">
    <div class="card-body p-4">
      <h3 class="font-bold mb-3">⏳ 計時器</h3>

      <!-- 快速選項 -->
      <div class="flex flex-wrap gap-2 mb-3">
        <?php foreach ([['1分','60'],['3分','180'],['5分','300'],['10分','600'],['15分','900']] as [$l,$s]): ?>
        <button onclick="startTimer(<?= $s ?>, '<?= $l ?>計時')"
                class="btn btn-sm btn-outline"><?= $l ?></button>
        <?php endforeach; ?>
      </div>

      <!-- 自訂時間 -->
      <div class="flex gap-2 items-center mb-3">
        <input id="timer-min" type="number" min="0" max="99" value="3"
              class="input input-bordered input-sm w-16" placeholder="分">
        <span class="text-sm">分</span>
        <input id="timer-sec" type="number" min="0" max="59" value="0"
              class="input input-bordered input-sm w-16" placeholder="秒">
        <span class="text-sm">秒</span>
        <button onclick="startTimerCustom()" class="btn btn-sm btn-primary">開始</button>
      </div>

      <!-- 計時器顯示 -->
      <div id="timer-display" class="hidden text-center">
        <div id="timer-countdown" class="text-5xl font-mono font-bold py-4"></div>
        <div id="timer-label-txt" class="text-sm text-gray-500 mb-3"></div>
        <button onclick="stopTimer()" class="btn btn-error w-full">⏹ 停止計時</button>
      </div>
    </div>
  </div>

  <div class="card bg-base-100 shadow">
    <div class="card-body p-4">
      <h3 class="font-bold mb-3">🎤 發言佇列</h3>
      <div id="speech-queue" class="space-y-2 max-h-[500px] overflow-y-auto">
        <div class="text-gray-400 text-sm text-center py-4">無發言申請</div>
      </div>
    </div>
  </div>

  <!-- 出席統計 -->
  <div class="card bg-base-100 shadow">
    <div class="card-body p-4">
      <h3 class="font-bold mb-3">📊 出席統計</h3>
      <div id="attendance-stats" class="text-sm space-y-1">
        <div>應出席：<span id="total-attendees">—</span> 人</div>
        <div>已簽到：<span id="signed-in" class="font-bold text-green-600">—</span> 人</div>
        <div>缺　席：<span id="absent" class="font-bold text-red-500">—</span> 人</div>
      </div>
    </div>
  </div>

</div>
</div>

<script>
const MEETING_ID = <?= $mid ?>;
const BASE_URL = '<?= BASE_URL ?>';
let lastVersion = 0;
let currentPhase = null;
let currentItemId = null;

// ── Timer ──────────────────────────────────────────────────────
let timerInterval = null;

function startTimer(seconds, label) {
    fetch(`${BASE_URL}/api/timer.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `meeting_id=${MEETING_ID}&action=start&seconds=${seconds}&label=${encodeURIComponent(label)}`
    });
}

function startTimerCustom() {
    const m = parseInt(document.getElementById('timer-min').value) || 0;
    const s = parseInt(document.getElementById('timer-sec').value) || 0;
    const total = m * 60 + s;
    if (total <= 0) return;
    startTimer(total, `${m}分${s}秒計時`);
}

function stopTimer() {
    fetch(`${BASE_URL}/api/timer.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `meeting_id=${MEETING_ID}&action=stop`
    });
}

function renderTimer(timerData) {
    const display  = document.getElementById('timer-display');
    const countdown = document.getElementById('timer-countdown');
    const labelTxt = document.getElementById('timer-label-txt');
    if (!timerData?.end_at) {
        display.classList.add('hidden');
        return;
    }
    display.classList.remove('hidden');
    labelTxt.textContent = timerData.label || '';
    const endAt  = new Date(timerData.end_ts * 1000);
    const remain = Math.floor((endAt - new Date()) / 1000);
    const abs    = Math.abs(remain);
    const h = String(Math.floor(abs / 3600)).padStart(2,'0');
    const m = String(Math.floor((abs % 3600) / 60)).padStart(2,'0');
    const s = String(abs % 60).padStart(2,'0');
    const txt = abs >= 3600 ? `${h}:${m}:${s}` : `${m}:${s}`;
    if (remain < 0) {
        countdown.textContent = '+' + txt;
        countdown.className = 'text-5xl font-mono font-bold py-4 text-red-500';
    } else {
        countdown.textContent = txt;
        countdown.className = 'text-5xl font-mono font-bold py-4 text-base-content';
    }
}

// ── Polling ────────────────────────────────────────────────────
async function poll() {
    try {
        const res = await fetch(`${BASE_URL}/api/status.php?meeting_id=${MEETING_ID}`);
        const d = await res.json();
        if (d.ok) updateUI(d.data);
    } catch (e) {}
    setTimeout(poll, <?= POLL_MS ?>);
}

function updateUI(data) {
    meetingStartAt = data.meeting?.actual_start_at || null;
    meetingEndAt   = data.meeting?.actual_end_at   || null;
    const { phase, item, members, vote_stats, speech_queue,
            agenda_list, pending_motions, election, candidates } = data;

    currentPhase  = phase.phase_type;
    currentItemId = phase.agenda_item_id;

    // Phase label
    const phaseNames = {
        standby:'⏳ 待機／簽到', agenda:'📣 議程',
        resolution:'🪧 表決', election:'🏆 選舉',
        temp_motion:'📝 臨時動議', ended:'✅ 已結束'
    };
    document.getElementById('current-phase').textContent = phaseNames[phase.phase_type] || phase.phase_type;
    document.getElementById('current-item-title').textContent = item ? item.title : '';

    // Timer
    renderTimer(data.timer);

    // Agenda nav
    const nav = document.getElementById('agenda-nav');
    nav.innerHTML = agenda_list.map(ag => {
        const active = ag.id == phase.agenda_item_id;
        const icons = {report:'📣', resolution:'🪧', election:'🏆', temp:'📝'};
        return `<button onclick="switchToItem(${ag.id}, '${ag.type}')"
                  class="btn btn-xs w-full justify-start text-left line-clamp-1 ${active ? 'btn-primary' : 'btn-ghost'}">
                  ${icons[ag.type]||'•'} ${escHtml(ag.title)}
                </button>`;
    }).join('') || '<div class="text-gray-400 text-xs text-center">無議程</div>';

    // Vote panel
    const votePanel = document.getElementById('vote-panel');
    const elecPanel = document.getElementById('election-panel');
    votePanel.classList.toggle('hidden', phase.phase_type !== 'resolution');
    elecPanel.classList.toggle('hidden', phase.phase_type !== 'election');

    if (phase.phase_type === 'resolution' && vote_stats) {
        document.getElementById('yes-count').textContent     = vote_stats.yes_count || 0;
        document.getElementById('no-count').textContent      = vote_stats.no_count || 0;
        document.getElementById('abstain-count').textContent = vote_stats.abstain_count || 0;
        document.getElementById('voted-count').textContent   = vote_stats.total_voted || 0;
        const total = members.filter(m => m.type === 'attendee').length;
        document.getElementById('total-count').textContent   = total;
        const closed = item && item.status === 'closed';
        document.getElementById('vote-controls').classList.toggle('hidden', closed);
        document.getElementById('vote-closed-msg').classList.toggle('hidden', !closed);
    }

    // Election panel
    if (phase.phase_type === 'election' && election) {
        document.getElementById('election-seats').textContent = `應選 ${election.seats} 人，每位議員持 ${election.seats} 張票`;
        document.getElementById('candidates-list').innerHTML = candidates.map(c =>
            `<div class="flex items-center gap-2">
               <span class="${c.is_elected ? 'font-bold text-success' : ''}">${escHtml(c.name)}</span>
               <span class="badge badge-sm ml-auto">${c.vote_count || 0} 票</span>
             </div>`
        ).join('');
    }

    // Members grid
    const grid = document.getElementById('members-grid');
    const attendees = members.filter(m => m.type === 'attendee');
    const observers = members.filter(m => m.type === 'observer');

    const colorMap = { yes:'bg-green-500 text-white', no:'bg-red-500 text-white',
                       abstain:'bg-yellow-400 text-black' };
    grid.innerHTML = attendees.map(m => {
        let cls = m.signed_in ? 'bg-gray-100 text-gray-800' : 'bg-gray-300 text-gray-500';
        if (phase.phase_type === 'resolution' && m.vote) cls = colorMap[m.vote] || cls;
        return `<div class="rounded p-2 text-center text-xs font-medium ${cls} transition-colors">
                  <div>${escHtml(m.name)}</div>
                  <div class="text-xs opacity-70">${escHtml(m.position||'')}</div>
                </div>`;
    }).join('') +
    (observers.length ? `<div class="col-span-full border-t pt-2 mt-1 text-xs text-gray-400">列席</div>` +
    observers.map(m =>
        `<div class="rounded p-2 text-center text-xs border border-dashed border-gray-300 ${m.signed_in?'':'opacity-40'}">
           ${escHtml(m.name)}
           <div class="text-xs opacity-70 line-clamp-1">${escHtml(m.position||'')}</div>
         </div>`
    ).join('') : '');

    // Attendance stats
    const singedIn = attendees.filter(m => m.signed_in).length;
    document.getElementById('total-attendees').textContent = attendees.length;
    document.getElementById('signed-in').textContent       = singedIn;
    document.getElementById('absent').textContent          = attendees.length - singedIn;

    // Speech queue
    const sq = document.getElementById('speech-queue');
    sq.innerHTML = speech_queue.length ? speech_queue.map(s =>
        `<div class="flex items-center gap-2 p-2 rounded ${s.status==='speaking' ? 'bg-green-100 border-l-4 border-green-500' : 'bg-base-200'}">
           <div class="flex-1">
             <div class="font-semibold text-sm">${escHtml(s.name)}</div>
             <div class="text-xs text-gray-500">${escHtml(s.position||'')}</div>
           </div>
           <div class="flex gap-1">
             ${s.status === 'waiting' ?
               `<button onclick="speechAction(${s.id},'speaking')" class="btn btn-xs btn-success">▶</button>` :
               `<button onclick="speechAction(${s.id},'done')" class="btn btn-xs btn-ghost">✓</button>`}
             <button onclick="speechAction(${s.id},'removed')" class="btn btn-xs btn-error btn-outline">✕</button>
           </div>
         </div>`
    ).join('') : '<div class="text-gray-400 text-sm text-center py-4">無發言申請</div>';

    // Pending motions
    const ma = document.getElementById('motion-alert');
    const mc = document.getElementById('motion-count');
    mc.textContent = pending_motions;
    ma.classList.toggle('hidden', pending_motions === 0);
    // 只在數量有變化時才重新拉取清單（避免每 2 秒覆蓋）
    if (pending_motions !== prevCount) {
        updateMotionsList();
    }


    // updateMotionsList();
    // const ma = document.getElementById('motion-alert');
    // document.getElementById('motion-count').textContent = pending_motions;
    // ma.classList.toggle('hidden', pending_motions === 0);

}

// ── Phase Control ──────────────────────────────────────────────
async function setPhase(type, itemId = null) {
    if (type === 'ended' && !confirm('確定結束會議？結束後將自動下載會議紀錄。')) return;
    await fetch(`${BASE_URL}/api/phase.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `meeting_id=${MEETING_ID}&type=${type}&agenda_item_id=${itemId||''}`
    });
    if (type === 'ended') {
        // 觸發下載（在背景開新分頁，不影響跳轉）
        const a = document.createElement('a');
        a.href = `${BASE_URL}/api/export_txt.php?meeting_id=${MEETING_ID}`;
        a.download = '';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        // 稍等一下再跳轉，確保下載請求已送出
        setTimeout(() => {
            window.location.href = `${BASE_URL}/admin/index.php`;
        }, 800);
        return;
    }
}

async function switchToItem(itemId, type) {
    const phaseMap = {report:'agenda', resolution:'resolution', election:'election', temp:'temp_motion'};
    await setPhase(phaseMap[type] || 'agenda', itemId);
}

async function closeVote() {
    if (!confirm('確定截止表決？未投票者將自動標記為棄權。')) return;
    await fetch(`${BASE_URL}/api/close_vote.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `meeting_id=${MEETING_ID}&agenda_item_id=${currentItemId}`
    });
}

async function closeElection() {
    if (!confirm('確定截止選舉？')) return;
    await fetch(`${BASE_URL}/api/close_vote.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `meeting_id=${MEETING_ID}&agenda_item_id=${currentItemId}&type=election`
    });
}

// ── Speech ─────────────────────────────────────────────────────
async function speechAction(id, status) {
    await fetch(`${BASE_URL}/api/speech.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=update&id=${id}&status=${status}&meeting_id=${MEETING_ID}`
    });
}

// ── Motions ────────────────────────────────────────────────────
async function updateMotionsList() {
    const res = await fetch(`${BASE_URL}/api/motion.php?action=list&meeting_id=${MEETING_ID}&status=pending`);
    const d = await res.json();
    const el = document.getElementById('pending-motions-list');
    if (!d.ok || !d.data.length) {
        el.innerHTML = '<div class="text-gray-400 text-sm text-center py-2">無待審動議</div>';
        return;
    }
    el.innerHTML = d.data.map(m =>
        `<div id="motion-${m.id}" class="bg-base-200 rounded p-2 text-sm">
          <div class="font-semibold">${escHtml(m.content)}</div>
          <div class="text-xs text-gray-500 mb-2">提案：${escHtml(m.proposer||'—')}</div>
          <div class="flex gap-1 flex-wrap">
            <button onclick="reviewMotion(${m.id},'accepted','temp')" class="btn btn-xs btn-success">✅ 受理（討論）</button>
            <button onclick="reviewMotion(${m.id},'accepted','resolution')" class="btn btn-xs btn-warning">🗳️ 受理（表決）</button>
            <button onclick="reviewMotion(${m.id},'rejected','')" class="btn btn-xs btn-error btn-outline">❌ 不受理</button>
          </div>
         </div>`
    ).join('');
}

async function reviewMotion(id, status, motionType) {
    // 先從畫面移除，不等 server（樂觀更新）
    const el = document.getElementById(`motion-${id}`);
    if (el) el.remove();

    const res = await fetch(`${BASE_URL}/api/motion.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=review&id=${id}&status=${status}&meeting_id=${MEETING_ID}&motion_type=${encodeURIComponent(motionType)}`
    });
    const d = await res.json();

    // 若受理，自動切換到新建立的議程
    if (status === 'accepted' && d.data?.agenda_item_id) {
        const phaseMap = { resolution: 'resolution', temp: 'agenda', report: 'agenda' };
        await setPhase(phaseMap[motionType] || 'agenda', d.data.agenda_item_id);
    }

    // 更新剩餘待審名單
    updateMotionsList();
}

async function addHostMotion() {
    const title = document.getElementById('motion-title').value.trim();
    const type  = document.getElementById('motion-type').value;
    if (!title) return;
    await fetch(`${BASE_URL}/api/motion.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=host_add&meeting_id=${MEETING_ID}&title=${encodeURIComponent(title)}&type=${type}`
    });
    document.getElementById('motion-title').value = '';
    updateMotionsList();
}

function escHtml(s) {
    if (!s) return '';
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

poll();
updateMotionsList();

let meetingStartAt = null;
let meetingEndAt   = null;

function updateElapsedTimer() {
    if (!meetingStartAt) return;
    const start = new Date(meetingStartAt);
    const now   = meetingEndAt ? new Date(meetingEndAt) : new Date();
    const secs  = Math.floor((now - start) / 1000);
    const h = String(Math.floor(secs / 3600)).padStart(2, '0');
    const m = String(Math.floor((secs % 3600) / 60)).padStart(2, '0');
    const s = String(secs % 60).padStart(2, '0');
    const txt = `${h}:${m}:${s}`;
    // admin/control.php 用：
    const el = document.getElementById('elapsed-timer');
    if (el) el.textContent = txt;
    // screen/index.php 用：
    const se = document.getElementById('screen-elapsed');
    if (se) se.textContent = '⏱ ' + txt;
}

// 在 updateUI / updateScreen 裡加入（從 data.meeting 取值）：
// meetingStartAt = data.meeting?.actual_start_at || null;
// meetingEndAt   = data.meeting?.actual_end_at   || null;

setInterval(updateElapsedTimer, 1000);
</script>

</div></body></html>
