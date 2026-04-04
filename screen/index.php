<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';

// PIN 驗證
if (!isset($_GET['pin']) || $_GET['pin'] !== SCREEN_PIN) {
    // 若已登入主辦人也可存取
    if (!is_host()) {
        header('Location: ' . BASE_URL . '/index.php?err=forbidden');
        exit;
    }
}

$meeting = active_meeting();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>大螢幕顯示 | 議事系統</title>
<link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { background: #000; color: #fff; font-family: 'Arial', sans-serif; }
  .member-card { transition: background-color 0.4s, color 0.4s; }
  .blink { animation: blink-anim 1.2s infinite; }
  @keyframes blink-anim { 0%,100%{opacity:1} 50%{opacity:.3} }

  /* 顏色狀態 */
  .status-absent  { background:#374151; color:#9CA3AF; }
  .status-present { background:#1F2937; color:#E5E7EB; }
  .status-yes     { background:#166534; color:#BBF7D0; }
  .status-no      { background:#991B1B; color:#FECACA; }
  .status-abstain { background:#854D0E; color:#FEF08A; }
  .status-voting  { background:#1E3A5F; color:#BAE6FD; }
  .status-observer{ background:#1C1917; color:#A8A29E; border:1px dashed #57534E; }
</style>
<!-- To fix the transform animation of .btn in DaisyUI -->
<!-- <style>
  .btn { transform: none !important; transition: background-color 0.15s, color 0.15s !important; }
  .btn:active { transform: scale(0.98) !important; }
</style> -->
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/custom.css">
</head>
<body class="min-h-screen flex flex-col">

<!-- Top Bar -->
<div id="top-bar" class="flex items-center justify-between px-6 py-3 border-b border-gray-800">
  <div>
    <div id="screen-meeting-title" class="text-xl font-bold text-blue-300"></div>
    <div class="flex gap-4 text-sm text-gray-400 mt-1">
      <span id="screen-location">📍 —</span>
      <span id="screen-time">🕐 —</span>
    </div>
  </div>
  <div class="flex items-center gap-4">
    <div id="screen-clock" class="text-3xl font-mono text-gray-300"></div>
    <div id="screen-elapsed" class="text-lg font-mono text-blue-400"></div>
    <button onclick="toggleFullscreen()"
            class="btn btn-sm btn-outline text-gray-400 border-gray-600 hover:bg-gray-800">
      ⛶ 全螢幕
    </button>
  </div>
</div>

<!-- Phase Banner -->
<div id="phase-banner" class="px-6 py-4 border-b border-gray-800">
  <div class="flex items-center gap-4">
    <div id="phase-badge" class="badge badge-lg text-lg px-4 py-4"></div>
    <div>
      <div id="phase-item-title" class="text-2xl font-bold"></div>
      <div id="phase-item-desc" class="text-gray-400 text-sm mt-1"></div>
    </div>
  </div>
</div>

<!-- Vote Stats Bar -->
<div id="vote-bar" class="hidden px-6 py-3 border-b border-gray-800 bg-gray-950">
  <div class="flex items-center gap-8">
    <div class="flex items-center gap-3">
      <span class="text-green-400 font-semibold">同意</span>
      <span id="b-yes" class="text-4xl font-bold text-green-400">0</span>
    </div>
    <div class="flex items-center gap-3">
      <span class="text-red-400 font-semibold">反對</span>
      <span id="b-no" class="text-4xl font-bold text-red-400">0</span>
    </div>
    <div class="flex items-center gap-3">
      <span class="text-yellow-400 font-semibold">棄權</span>
      <span id="b-abstain" class="text-4xl font-bold text-yellow-400">0</span>
    </div>
    <div class="ml-auto text-gray-500 text-sm">
      已投 <span id="b-voted">0</span> / <span id="b-total">0</span>
    </div>
  </div>
</div>

<!-- Election results bar -->
<div id="election-bar" class="hidden px-6 py-3 border-b border-gray-800 bg-gray-950">
  <div class="flex items-center gap-2 flex-wrap" id="election-cand-bar"></div>
</div>

<!-- Timer -->
<div id="timer-bar" class="hidden px-6 py-4 border-b border-gray-800 bg-black text-center">
  <div id="screen-timer-label" class="text-gray-500 text-sm mb-1"></div>
  <div id="screen-timer-countdown" class="text-7xl font-mono font-bold"></div>
</div>

<!-- Speech Queue (agenda phase) -->
<div id="speech-bar" class="hidden px-6 py-2 border-b border-gray-800 bg-gray-900 text-sm">
  <span class="text-gray-500 mr-3">🎤 發言佇列：</span>
  <span id="speech-queue-text" class="text-white"></span>
</div>

<!-- Members Grid -->
<div class="flex-1 p-4 overflow-hidden">
  <!-- Attendees -->
  <div id="members-grid" class="grid gap-2 mb-4"
       style="grid-template-columns: repeat(auto-fill, minmax(120px, 1fr))"></div>

  <!-- Separator + Observers -->
  <div id="observers-section" class="hidden">
    <div class="text-gray-600 text-xs border-t border-gray-800 pt-2 mb-2">列席人員</div>
    <div id="observers-grid" class="grid gap-2"
         style="grid-template-columns: repeat(auto-fill, minmax(100px, 1fr))"></div>
  </div>
</div>

<!-- Bottom Status -->
<div class="px-6 py-2 border-t border-gray-800 flex justify-between text-xs text-gray-600">
  <span>線上議事系統</span>
  <div class="flex gap-4">
    <span>應出席：<span id="stat-total" class="text-gray-400">—</span></span>
    <span>出席：<span id="stat-present" class="text-green-500">—</span></span>
    <span>缺席：<span id="stat-absent" class="text-red-500">—</span></span>
  </div>
</div>

<script>
const MEETING_ID = <?= $meeting ? $meeting['id'] : 'null' ?>;
const BASE_URL   = '<?= BASE_URL ?>';

// Clock
setInterval(() => {
    document.getElementById('screen-clock').textContent =
        new Date().toLocaleTimeString('zh-TW', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
}, 1000);

async function poll() {
    if (!MEETING_ID) { setTimeout(poll, 3000); return; }
    try {
        const res = await fetch(`${BASE_URL}/api/status.php?meeting_id=${MEETING_ID}`);
        const d = await res.json();
        if (d.ok) updateScreen(d.data);
    } catch (e) {}
    setTimeout(poll, <?= POLL_MS ?>);
}

function updateScreen(data) {
    meetingStartAt = data.meeting?.actual_start_at || null;
    meetingEndAt   = data.meeting?.actual_end_at   || null;

    const { meeting, phase, item, vote_stats, members, speech_queue, election, candidates } = data;

    // Top bar
    document.getElementById('screen-meeting-title').textContent = meeting?.title || '';
    document.getElementById('screen-location').textContent = '📍 ' + (meeting?.location || '—');
    document.getElementById('screen-time').textContent     = '🕐 ' + (meeting?.start_time || '—');

    // Timer
    const tBar = document.getElementById('timer-bar');
    if (data.timer?.end_at) {
        tBar.classList.remove('hidden');
        const endAt  = new Date(data.timer.end_ts * 1000);
        const remain = Math.floor((endAt - new Date()) / 1000);
        const abs    = Math.abs(remain);
        const m = String(Math.floor(abs / 60)).padStart(2,'0');
        const s = String(abs % 60).padStart(2,'0');
        const txt = remain < 0 ? `+${m}:${s}` : `${m}:${s}`;
        document.getElementById('screen-timer-label').textContent     = data.timer.label || '';
        const tc = document.getElementById('screen-timer-countdown');
        tc.textContent = txt;
        tc.className   = remain < 0
            ? 'text-7xl font-mono font-bold text-red-500'
            : 'text-7xl font-mono font-bold text-white';
    } else {
        tBar.classList.add('hidden');
    }

    // Phase badge
    const phaseInfo = {
        standby:     {label:'⏳ 待機/簽到',   cls:'badge-ghost'},
        agenda:      {label:'📣 議程',         cls:'badge-info'},
        resolution:  {label:'🪧 表決',         cls:'badge-warning'},
        election:    {label:'🏆 選舉',         cls:'badge-secondary'},
        temp_motion: {label:'📝 臨時動議',      cls:'badge-ghost'},
        ended:       {label:'✅ 會議結束',      cls:'badge-success'},
    };
    const pi = phaseInfo[phase.phase_type] || {label:phase.phase_type, cls:'badge-ghost'};
    const badge = document.getElementById('phase-badge');
    badge.textContent = pi.label;
    badge.className   = `badge badge-lg text-lg px-4 py-4 ${pi.cls}`;

    document.getElementById('phase-item-title').textContent = item?.title || '';
    document.getElementById('phase-item-desc').textContent  = item?.description || '';

    // Vote bar
    const vBar = document.getElementById('vote-bar');
    const eBar = document.getElementById('election-bar');
    vBar.classList.toggle('hidden', phase.phase_type !== 'resolution');
    eBar.classList.toggle('hidden', phase.phase_type !== 'election');

    if (phase.phase_type === 'resolution' && vote_stats) {
        document.getElementById('b-yes').textContent    = vote_stats.yes_count    || 0;
        document.getElementById('b-no').textContent     = vote_stats.no_count     || 0;
        document.getElementById('b-abstain').textContent= vote_stats.abstain_count|| 0;
        document.getElementById('b-voted').textContent  = vote_stats.total_voted  || 0;
        document.getElementById('b-total').textContent  =
            members.filter(m => m.type==='attendee').length;
    }

    if (phase.phase_type === 'election' && candidates?.length) {
        document.getElementById('election-cand-bar').innerHTML =
            candidates.map(c =>
                `<div class="flex items-center gap-2 bg-gray-800 rounded px-3 py-1">
                   <span class="${c.is_elected ? 'text-yellow-300 font-bold' : 'text-white'}">${esc(c.name)}</span>
                   <span class="badge badge-sm">${c.vote_count || 0} 票</span>
                   ${c.is_elected ? '<span class="text-yellow-400 text-xs">✅ 當選</span>' : ''}
                 </div>`
            ).join('');
    }

    // Speech queue bar
    const sBar = document.getElementById('speech-bar');
    sBar.classList.toggle('hidden', phase.phase_type !== 'agenda' || !speech_queue.length);
    if (speech_queue.length) {
        document.getElementById('speech-queue-text').innerHTML = speech_queue.map((s, i) => {
            const speaking = s.status === 'speaking';
            return `<span class="${speaking ? 'text-green-400 font-bold blink' : 'text-gray-300'} mr-4">
                      ${speaking ? '🎤 ' : (i+1)+'. '}${esc(s.name)}
                    </span>`;
        }).join('');
    }

    // Members grid
    const attendees = members.filter(m => m.type === 'attendee');
    const observers = members.filter(m => m.type === 'observer');

    document.getElementById('members-grid').innerHTML = attendees.map(m => {
        let cls = m.signed_in ? 'status-present' : 'status-absent';
        if (phase.phase_type === 'resolution') {
            if      (m.vote === 'yes')     cls = 'status-yes';
            else if (m.vote === 'no')      cls = 'status-no';
            else if (m.vote === 'abstain') cls = 'status-abstain';
            else if (m.signed_in)          cls = 'status-voting blink';
        }
        return `<div class="member-card ${cls} rounded-lg p-3 text-center min-h-[70px] flex flex-col justify-center">
                  <div class="font-semibold text-sm leading-tight">${esc(m.name)}</div>
                  <div class="text-xs opacity-60 leading-tight mt-1">${esc(m.position||m.member_no||'')}</div>
                </div>`;
    }).join('');

    // Observers
    const obsSection = document.getElementById('observers-section');
    obsSection.classList.toggle('hidden', !observers.length);
    document.getElementById('observers-grid').innerHTML = observers.map(m =>
        `<div class="member-card status-observer rounded-lg p-2 text-center">
           <div class="text-xs">${esc(m.name)}★</div>
         </div>`
    ).join('');

    // Stats
    const present = attendees.filter(m => m.signed_in).length;
    document.getElementById('stat-total').textContent   = attendees.length;
    document.getElementById('stat-present').textContent = present;
    document.getElementById('stat-absent').textContent  = attendees.length - present;
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

function esc(s) {
    if (!s) return '';
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

poll();

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
</body>
</html>
