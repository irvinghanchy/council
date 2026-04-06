<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_GET['pin']) || $_GET['pin'] !== SCREEN_PIN) {
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
<title>大螢幕 | 議事系統</title>
<link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/custom.css">
<style>
/* ═══════════════════════════════════════════════════
   大螢幕設計系統
   目標：投影機可讀性、12–18 出席人、至多 22 列席人
   基準字級 18px（約 1.3× 放大）
   ═══════════════════════════════════════════════════ */
:root {
  /* 背景層次 */
  --bg-0: #08090D;   /* 最底層 */
  --bg-1: #0F1118;   /* 卡片底 */
  --bg-2: #161923;   /* 凸起區塊 */
  --bg-3: #1E2230;   /* hover / active */

  /* 邊框 */
  --b-dim:  rgba(255,255,255,0.07);
  --b-mid:  rgba(255,255,255,0.13);
  --b-hi:   rgba(255,255,255,0.22);

  /* 文字 — 全部提升至投影機可讀 */
  --t-1: #ECEEF8;   /* 主要 */
  --t-2: #A8B0CC;   /* 次要（原本太暗） */
  --t-3: #7A84A0;   /* 輔助（仍明顯） */

  /* 品牌 */
  --brand:     #0055FF;
  --brand-glo: rgba(0, 85, 255, 0.22);

  /* 狀態色：高對比，投影機友好 */
  --c-absent-bg:   #1A1D28;  /* 明顯的深藍灰 */
  --c-absent-tx:   #6B7592;  /* 較亮的灰藍 */
  --c-absent-bd:   rgba(255,255,255,0.10);

  --c-present-bg:  #3c4050;  /* 深藍 */
  --c-present-tx:  #FFFFFF;  /* 純白，最高對比 */
  /* --c-present-bd:  rgba(0,85,255,0.40); */
  --c-present-bd:  #454a5e;

  --c-yes-bg:      #0B2918;
  --c-yes-tx:      #4ADE80;
  --c-yes-bd:      rgba(74,222,128,0.35);

  --c-no-bg:       #2D0A0A;
  --c-no-tx:       #FF6B6B;
  --c-no-bd:       rgba(255,107,107,0.35);

  --c-abstain-bg:  #2A1C04;
  --c-abstain-tx:  #FCD34D;
  --c-abstain-bd:  rgba(252,211,77,0.35);

  --c-voting-bg:   #0A1E38;
  --c-voting-tx:   #60A5FA;
  --c-voting-bd:   rgba(96,165,250,0.35);

  --c-obs-on-bg:   #141824;
  --c-obs-on-tx:   #8B9BB8;
  --c-obs-on-bd:   rgba(255,255,255,0.10);

  --c-obs-off-bg:  #0C0D12;
  --c-obs-off-tx:  rgb(117, 125, 156);
  --c-obs-off-bd:  rgba(255,255,255,0.04);

  /* 字型 */
  --f-ui:   'Chiron GoRound TC', 'Noto Emoji', 'Noto Sans TC', sans-serif;
  --f-body: 'Lato', 'Noto Emoji', 'ChironHeiHKText', 'Noto Sans TC', sans-serif;
  --f-mono: 'JetBrains Mono', 'Noto Emoji', monospace;

  /* 基準字級 */
  --base: 18px;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { font-size: var(--base); }

html, body {
  width: 100%; height: 100%;
  background: var(--bg-0);
  color: var(--t-1);
  font-family: var(--f-body);
  -webkit-font-smoothing: antialiased;
  overflow: hidden;
}

/* ── Shell: 4-row grid ───────────────────────────── */
.shell {
  display: grid;
  grid-template-rows: auto auto 1fr auto;
  height: 100svh;
}

/* ════════════════════════════════════════════════
   ① TOP BAR
   ════════════════════════════════════════════════ */
.topbar {
  display: flex;
  align-items: center;
  padding: 0 2rem;
  height: 4rem;        /* 72px @ 18px base */
  background: var(--bg-1);
  border-bottom: 1px solid var(--b-dim);
  gap: 1.5rem;
  flex-shrink: 0;
}

.topbar-title {
  font-family: var(--f-ui);
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--t-1);      /* 白，不再是藍灰 */
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.topbar-meta {
  display: flex;
  gap: 1.25rem;
  font-size: 0.78rem;
  color: var(--t-2);      /* 提升：A8B0CC */
  flex-shrink: 0;
}

.topbar-meta span { display: flex; align-items: center; gap: 0.3rem; }

/* 時鐘區 */
.clock-wrap {
  display: flex;
  align-items: baseline;
  gap: 0.75rem;
  flex-shrink: 0;
}

.clock-time {
  font-family: var(--f-mono);
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--t-1);      /* 純白 */
  letter-spacing: 0.05em;
}

/* 開會歷時 — 品牌強調色 */
.clock-elapsed {
  font-family: var(--f-mono);
  font-size: 0.82rem;
  font-weight: 600;
  /* color: var(--brand);  */    /* #0055FF 強調 */
  color: var(--c-voting-tx);
  letter-spacing: 0.04em;
}

.fs-btn {
  display: flex; align-items: center; justify-content: center;
  width: 2.2rem; height: 2.2rem;
  border-radius: 0.5rem;
  border: 1px solid var(--b-mid);
  background: transparent;
  color: var(--t-2);       /* 提升 */
  cursor: pointer;
  transition: background 0.15s, color 0.15s;
  flex-shrink: 0;
}
.fs-btn:hover { background: var(--bg-3); color: var(--t-1); }

/* ════════════════════════════════════════════════
   ② INFO STRIP
   ════════════════════════════════════════════════ */
.info-strip {
  background: var(--bg-1);
  border-bottom: 1px solid var(--b-dim);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

/* Phase row */
.phase-row {
  display: flex;
  align-items: center;
  padding: 0.85rem 2rem;
  gap: 1rem;
  border-bottom: 1px solid var(--b-dim);
}

.phase-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.3rem 0.9rem;
  border-radius: 999px;
  font-family: var(--f-ui);
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  white-space: nowrap;
  border: 1px solid transparent;
  flex-shrink: 0;
}

.pp-standby    { background: rgba(255,255,255,0.07); color: var(--t-2);  border-color: var(--b-mid); }
.pp-agenda     { background: rgba(0,85,255,0.18);    color: #93C5FD;     border-color: rgba(0,85,255,0.38); }
.pp-resolution { background: rgba(217,119,6,0.18);   color: #FCD34D;     border-color: rgba(217,119,6,0.40); }
.pp-election   { background: rgba(124,58,237,0.18);  color: #C4B5FD;     border-color: rgba(124,58,237,0.38); }
.pp-temp       { background: rgba(255,255,255,0.06); color: var(--t-2);  border-color: var(--b-mid); }
.pp-ended      { background: rgba(22,163,74,0.18);   color: #4ADE80;     border-color: rgba(22,163,74,0.38); }

.phase-item-wrap { flex: 1; min-width: 0; }

.phase-item-title {
  font-family: var(--f-ui);
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--t-1);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.phase-item-desc {
  font-size: 0.78rem;
  color: var(--t-2);       /* 提升 */
  margin-top: 0.15rem;
}

/* Vote row */
.vote-row {
  display: flex;
  align-items: center;
  padding: 0.75rem 2rem;
  gap: 2.5rem;
  border-bottom: 1px solid var(--b-dim);
}

.tally {
  display: flex;
  align-items: baseline;
  gap: 0.6rem;
}

.tally-lbl {
  font-family: var(--f-ui);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.05em;
}

.tally-num {
  font-family: var(--f-mono);
  font-size: 2.6rem;
  font-weight: 700;
  line-height: 1;
}

.t-yes     { color: #4ADE80; }
.t-no      { color: #FF6B6B; }
.t-abstain { color: #FCD34D; }

.vote-right {
  margin-left: auto;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.3rem;
}

.vote-prog-lbl {
  font-family: var(--f-mono);
  font-size: 0.75rem;
  color: var(--t-2);       /* 提升 */
}

.vote-prog-bar {
  width: 10rem;
  height: 5px;
  background: var(--bg-3);
  border-radius: 3px;
  overflow: hidden;
}

.vote-prog-fill {
  height: 100%;
  background: var(--brand);
  border-radius: 3px;
  transition: width 0.6s ease;
}

/* Election row */
.election-row {
  display: flex;
  align-items: center;
  padding: 0.65rem 2rem;
  gap: 0.6rem;
  flex-wrap: wrap;
  border-bottom: 1px solid var(--b-dim);
}

.elec-lbl {
  font-family: var(--f-ui);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  color: var(--t-3);
  white-space: nowrap;
}

.cand-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 0.85rem;
  background: var(--bg-2);
  border: 1px solid var(--b-mid);
  border-radius: 0.5rem;
  font-size: 0.85rem;
}
.cand-chip.elected {
  border-color: rgba(253,224,71,0.45);
  background: rgba(253,224,71,0.09);
}
.cand-name          { font-family: var(--f-ui); font-weight: 600; color: var(--t-1); }
.cand-name.elected  { color: #FDE047; }
.cand-votes         { font-family: var(--f-mono); font-size: 0.78rem; color: var(--t-2); }

/* Timer row */
.timer-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  padding: 0.8rem 2rem;
  border-bottom: 1px solid var(--b-dim);
}

.timer-lbl {
  font-family: var(--f-ui);
  font-size: 0.82rem;
  color: var(--t-2);
}

.timer-num {
  font-family: var(--f-mono);
  font-size: 3.2rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: var(--t-1);
  line-height: 1;
}
.timer-num.ot { color: #FF6B6B; }

/* Speech row */
.speech-row {
  display: flex;
  align-items: center;
  padding: 0.55rem 2rem;
  gap: 0.75rem;
  flex-wrap: wrap;
  border-bottom: 1px solid var(--b-dim);
}

.speech-lbl {
  font-family: var(--f-ui);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  color: var(--t-3);
  white-space: nowrap;
}

.spkr {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.25rem 0.75rem;
  border-radius: 999px;
  font-size: 0.85rem;
  font-family: var(--f-ui);
  border: 1px solid transparent;
}
.spkr.on  { background: rgba(74,222,128,0.14); border-color: rgba(74,222,128,0.35); color: #4ADE80; font-weight: 700; }
.spkr.off { background: var(--bg-2); border-color: var(--b-mid); color: var(--t-2); }

/* ════════════════════════════════════════════════
   ③ MEMBERS AREA
   ════════════════════════════════════════════════ */
.members-area {
  overflow: hidden;
  padding: 1rem 1.2rem 0.6rem;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

/*
  出席人格：
  最大 18 人 → 6 col × 3 row，平均 12 人 → 4 col × 3 row
  minmax(170px, 1fr) 在 1280px 視窗可得約 6 欄
*/
.attendees-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 0.5rem;
  align-content: start;
  flex: 1;
  overflow: hidden;
}

.mc {
  border-radius: 0.7rem;
  padding: 0.7rem 0.8rem;
  min-height: 5rem;      /* 90px @ 18px base */
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  border: 1px solid transparent;
  transition: background-color 0.5s, color 0.5s, border-color 0.5s;
}

.mc-name {
  font-family: var(--f-ui);
  font-size: 0.95rem;
  font-weight: 700;
  line-height: 1.25;
}

.mc-pos {
  font-size: 0.68rem;
  margin-top: 0.2rem;
  opacity: 0.6;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
}

/* 狀態 */
.s-absent   { background: var(--c-absent-bg);  color: var(--c-absent-tx);  border-color: var(--c-absent-bd); }
.s-present  { background: var(--c-present-bg); color: var(--c-present-tx); border-color: var(--c-present-bd); }
.s-yes      { background: var(--c-yes-bg);     color: var(--c-yes-tx);     border-color: var(--c-yes-bd); }
.s-no       { background: var(--c-no-bg);      color: var(--c-no-tx);      border-color: var(--c-no-bd); }
.s-abstain  { background: var(--c-abstain-bg); color: var(--c-abstain-tx); border-color: var(--c-abstain-bd); }
.s-voting   { background: var(--c-voting-bg);  color: var(--c-voting-tx);  border-color: var(--c-voting-bd); }

/* 列席人格：較小，不搶焦點 */
.obs-sep {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font-family: var(--f-ui);
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--t-3);
}
.obs-sep::before, .obs-sep::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--b-dim);
}

/*
  列席人格：最多 22 人
  minmax(120px, 1fr) → 1280px 可得約 9 欄
*/
.observers-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 0.35rem;
}

.oc {
  border-radius: 0.5rem;
  padding: 0.45rem 0.6rem;
  min-height: 3.2rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  border: 1px solid transparent;
  transition: background-color 0.4s, color 0.4s;
}

.oc-name { font-family: var(--f-ui); font-size: 0.75rem; font-weight: 600; color: inherit; }
.oc-pos  { font-size: 0.6rem; opacity: 0.5; margin-top: 0.1rem; }

.s-obs-on  { background: var(--c-obs-on-bg);  color: var(--c-obs-on-tx);  border-color: var(--c-obs-on-bd); }
.s-obs-off { background: var(--c-obs-off-bg); color: var(--c-obs-off-tx); border-color: var(--c-obs-off-bd); }

/* ════════════════════════════════════════════════
   ④ BOTTOM BAR
   ════════════════════════════════════════════════ */
.bottombar {
  height: 2rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 2rem;
  background: var(--bg-1);
  border-top: 1px solid var(--b-dim);
  flex-shrink: 0;
}

.bot-brand {
  font-family: var(--f-ui);
  font-size: 0.67rem;
  color: var(--t-3);
}

.bot-stats {
  display: flex;
  gap: 1.2rem;
  font-family: var(--f-mono);
  font-size: 0.7rem;
  color: var(--t-2);      /* 提升 */
}

.c-ok  { color: #4ADE80; }
.c-err { color: #FF6B6B; }

/* Blink */
@keyframes scr-blink { 0%,100%{opacity:1} 50%{opacity:0.18} }
.blink { animation: scr-blink 1.4s ease-in-out infinite; }
</style>
</head>
<body>
<div class="shell">

  <!-- ① Top bar -->
  <div class="topbar">
    <div class="topbar-title" id="screen-meeting-title">載入中…</div>
    <div class="topbar-meta">
      <span id="screen-location">📍 —</span>
      <span id="screen-time">🕐 —</span>
    </div>
    <div class="clock-wrap">
      <div class="clock-time"    id="screen-clock">--:--:--</div>
      <div class="clock-elapsed" id="screen-elapsed"></div>
    </div>
    <button class="fs-btn" onclick="toggleFullscreen()" title="全螢幕">
      <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M8 3H5a2 2 0 00-2 2v3m18 0V5a2 2 0 00-2-2h-3m0 18h3a2 2 0 002-2v-3M3 16v3a2 2 0 002 2h3"/>
      </svg>
    </button>
  </div>

  <!-- ② Info strip -->
  <div class="info-strip">

    <!-- Phase -->
    <div class="phase-row">
      <div class="phase-pill pp-standby" id="phase-pill">⏳ 待機</div>
      <div class="phase-item-wrap">
        <div class="phase-item-title" id="phase-item-title"></div>
        <div class="phase-item-desc"  id="phase-item-desc"></div>
      </div>
    </div>

    <!-- Vote stats -->
    <div class="vote-row" id="vote-bar" style="display:none;">
      <div class="tally">
        <span class="tally-lbl t-yes">同意</span>
        <span class="tally-num t-yes" id="b-yes">0</span>
      </div>
      <div class="tally">
        <span class="tally-lbl t-no">反對</span>
        <span class="tally-num t-no" id="b-no">0</span>
      </div>
      <div class="tally">
        <span class="tally-lbl t-abstain">棄權</span>
        <span class="tally-num t-abstain" id="b-abstain">0</span>
      </div>
      <div class="vote-right">
        <div class="vote-prog-lbl">已投 <span id="b-voted">0</span> / <span id="b-total">0</span></div>
        <div class="vote-prog-bar">
          <div class="vote-prog-fill" id="b-fill" style="width:0%"></div>
        </div>
      </div>
    </div>

    <!-- Election -->
    <div class="election-row" id="election-bar" style="display:none;">
      <span class="elec-lbl">候選人</span>
      <div style="display:flex;flex-wrap:wrap;gap:0.5rem;" id="election-cand-bar"></div>
    </div>

    <!-- Timer -->
    <div class="timer-row" id="timer-bar" style="display:none;">
      <span class="timer-lbl" id="screen-timer-label"></span>
      <span class="timer-num" id="screen-timer-countdown"></span>
    </div>

    <!-- Speech -->
    <div class="speech-row" id="speech-bar" style="display:none;">
      <span class="speech-lbl">🎤 發言</span>
      <div id="speech-queue-text" style="display:flex;flex-wrap:wrap;gap:0.4rem;"></div>
    </div>

  </div>

  <!-- ③ Members -->
  <div class="members-area">
    <div class="attendees-grid" id="members-grid"></div>
    <div id="observers-section" style="display:none;">
      <div class="obs-sep">列席人員</div>
      <div class="observers-grid" id="observers-grid"></div>
    </div>
  </div>

  <!-- ④ Bottom bar -->
  <div class="bottombar">
    <div class="bot-brand">高師大附中學生議會 · 線上議事系統</div>
    <div class="bot-stats">
      <span>應出席 <span id="stat-total">—</span></span>
      <span>出席 <span class="c-ok" id="stat-present">—</span></span>
      <span>缺席 <span class="c-err" id="stat-absent">—</span></span>
    </div>
  </div>

</div>

<script>
window.addEventListener('unhandledrejection', e => {
    if (e.reason?.message?.includes('Receiving end does not exist')) e.preventDefault();
});

const MEETING_ID = <?= $meeting ? $meeting['id'] : 'null' ?>;
const BASE_URL   = '<?= BASE_URL ?>';
let meetingStartAt = null, meetingEndAt = null;

// Clock
setInterval(() => {
    document.getElementById('screen-clock').textContent =
        new Date().toLocaleTimeString('zh-TW', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
}, 1000);

// Elapsed
function updateElapsed() {
    if (!meetingStartAt) return;
    const s = Math.floor(((meetingEndAt ? new Date(meetingEndAt) : new Date()) - new Date(meetingStartAt)) / 1000);
    const h = String(Math.floor(s/3600)).padStart(2,'0');
    const m = String(Math.floor((s%3600)/60)).padStart(2,'0');
    const sec = String(s%60).padStart(2,'0');
    const el = document.getElementById('screen-elapsed');
    if (el) el.textContent = `⏱ ${h}:${m}:${sec}`;
}
setInterval(updateElapsed, 1000);

// Poll
async function poll() {
    if (!MEETING_ID) { setTimeout(poll, 3000); return; }
    try {
        const r = await fetch(`${BASE_URL}/api/status.php?meeting_id=${MEETING_ID}`);
        const d = await r.json();
        if (d.ok) updateScreen(d.data);
    } catch(e) {}
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

    // Phase pill
    const PM = {
        standby:     { l:'⏳ 待機 / 簽到', c:'pp-standby' },
        agenda:      { l:'📣 議程',         c:'pp-agenda' },
        resolution:  { l:'🪧 表決',         c:'pp-resolution' },
        election:    { l:'🏆 選舉',         c:'pp-election' },
        temp_motion: { l:'📝 臨時動議',      c:'pp-temp' },
        ended:       { l:'✅ 會議結束',      c:'pp-ended' },
    };
    const pi = PM[phase.phase_type] || { l: phase.phase_type, c:'pp-standby' };
    const pill = document.getElementById('phase-pill');
    pill.textContent = pi.l;
    pill.className   = `phase-pill ${pi.c}`;

    document.getElementById('phase-item-title').textContent = item?.title || '';
    const descEl = document.getElementById('phase-item-desc');
    if (phase.phase_type === 'temp_motion' && data.motion_info?.proposer_name) {
        descEl.textContent = `提案人：${data.motion_info.proposer_name}（${data.motion_info.proposer_position || ''}）`;
    } else {
        descEl.textContent = item?.description || '';
    }

    // Vote bar
    const vBar = document.getElementById('vote-bar');
    vBar.style.display = phase.phase_type === 'resolution' ? 'flex' : 'none';
    if (phase.phase_type === 'resolution' && vote_stats) {
        const yes = +vote_stats.yes_count||0, no = +vote_stats.no_count||0;
        const abs = +vote_stats.abstain_count||0, tot = +vote_stats.total_voted||0;
        const total = members.filter(m=>m.type==='attendee').length;
        document.getElementById('b-yes').textContent     = yes;
        document.getElementById('b-no').textContent      = no;
        document.getElementById('b-abstain').textContent = abs;
        document.getElementById('b-voted').textContent   = tot;
        document.getElementById('b-total').textContent   = total;
        document.getElementById('b-fill').style.width = (total>0?Math.round(tot/total*100):0)+'%';
    }

    // Election bar
    const eBar = document.getElementById('election-bar');
    eBar.style.display = (phase.phase_type==='election' && candidates?.length) ? 'flex' : 'none';
    if (phase.phase_type==='election' && candidates?.length) {
        document.getElementById('election-cand-bar').innerHTML = candidates.map(c =>
            `<div class="cand-chip ${c.is_elected?'elected':''}">
               <span class="cand-name ${c.is_elected?'elected':''}">${esc(c.name)}</span>
               <span class="cand-votes">${c.vote_count||0} 票</span>
               ${c.is_elected?'<span>👑</span>':''}
             </div>`
        ).join('');
    }

    // Timer
    const tBar = document.getElementById('timer-bar');
    if (data.timer?.end_ts) {
        tBar.style.display = 'flex';
        const remain = Math.floor((new Date(data.timer.end_ts*1000) - new Date()) / 1000);
        const a = Math.abs(remain);
        const mm = String(Math.floor(a/60)).padStart(2,'0');
        const ss = String(a%60).padStart(2,'0');
        document.getElementById('screen-timer-label').textContent = data.timer.label || '';
        const tc = document.getElementById('screen-timer-countdown');
        tc.textContent = (remain<0?'+':'')+`${mm}:${ss}`;
        tc.className   = 'timer-num' + (remain<0?' ot':'');
    } else {
        tBar.style.display = 'none';
    }

    // Speech bar
    const sBar = document.getElementById('speech-bar');
    sBar.style.display = (phase.phase_type==='agenda' && speech_queue.length) ? 'flex' : 'none';
    if (speech_queue.length) {
        document.getElementById('speech-queue-text').innerHTML = speech_queue.map((s,i) => {
            const on = s.status === 'speaking';
            return `<span class="spkr ${on?'on blink':'off'}">${on?'🎤 ':(i+1)+'. '}${esc(s.name)}</span>`;
        }).join('');
    }

    // Members
    const attendees = members.filter(m=>m.type==='attendee');
    const observers  = members.filter(m=>m.type==='observer');

    document.getElementById('members-grid').innerHTML = attendees.map(m => {
        let cls = m.signed_in ? 's-present' : 's-absent';
        let blink = '';
        if (phase.phase_type === 'resolution') {
            if      (m.vote==='yes')     cls = 's-yes';
            else if (m.vote==='no')      cls = 's-no';
            else if (m.vote==='abstain') cls = 's-abstain';
            else if (m.signed_in)      { cls = 's-voting'; blink = ' blink'; }
        }
        return `<div class="mc ${cls}${blink}">
                  <div class="mc-name">${esc(m.name)}</div>
                  <div class="mc-pos">${esc(m.position||m.member_no||'')}</div>
                </div>`;
    }).join('');

    const obsSection = document.getElementById('observers-section');
    obsSection.style.display = observers.length ? 'block' : 'none';
    document.getElementById('observers-grid').innerHTML = observers.map(m => {
        const cls = m.signed_in ? 's-obs-on' : 's-obs-off';
        return `<div class="oc ${cls}">
                  <div class="oc-name">${esc(m.name)}</div>
                  <div class="oc-pos">${esc(m.position||'')}</div>
                </div>`;
    }).join('');

    const present = attendees.filter(m=>m.signed_in).length;
    document.getElementById('stat-total').textContent   = attendees.length;
    document.getElementById('stat-present').textContent = present;
    document.getElementById('stat-absent').textContent  = attendees.length - present;
}

function toggleFullscreen() {
    if (!document.fullscreenElement) document.documentElement.requestFullscreen();
    else document.exitFullscreen();
}

function esc(s) {
    if (!s) return '';
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

poll();
</script>
</body>
</html>
