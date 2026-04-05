<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_member();

$mid       = $_SESSION['meeting_id'];
$member_id = $_SESSION['member_id'];
$role      = current_role();   // 'member' or 'observer'
$name      = $_SESSION['user_name'];

$meeting = db()->prepare("SELECT * FROM meeting WHERE id=?")->execute([$mid])
           ? db()->query("SELECT * FROM meeting WHERE id=$mid")->fetch() : null;
?>
<!DOCTYPE html>
<html lang="zh-TW" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>會議介面 | 議事系統</title>
<link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.10/dist/full.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>

<!-- To fix the transform animation of .btn in DaisyUI -->
<!-- <style>
  .btn { transform: none !important; transition: background-color 0.15s, color 0.15s !important; }
  .btn:active { transform: scale(0.98) !important; }
</style> -->

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/custom.css">
</head>
<body class="bg-base-200 min-h-screen">

<!-- Top Bar -->
<div class="navbar bg-blue-950 text-white px-4 sticky top-0 z-50">
  <div class="flex-1 items-center">
    <span class="font-bold text-lg flex items-center">
      <div class="avatar h-8 my-auto mx-2">
        <div class="rounded-full">
          <img src="<?= BASE_URL ?>/assets/ASHSSP Logo.png" />
        </div>
      </div>
      <span class="my-auto">
        議事系統
      </span>
    </span>
    <span class="text-blue-300 text-sm ml-3">
      <?= h($name) ?>
      <?= $role === 'observer' ? '<span class="badge badge-sm badge-secondary ml-1">列席</span>' : '' ?>
    </span>
  </div>
  <div>
    <div class="badge badge-success badge-lg">✅ 已簽到</div>
    <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-ghost btn-sm ml-2">登出</a>
  </div>
</div>

<!-- Main Content -->
<div class="max-w-lg mx-auto p-4 space-y-4">

  <!-- 會議資訊 -->
  <div class="card bg-base-100 shadow-sm">
    <div class="card-body py-3 px-4">
      <div class="font-bold text-lg"><?= h($meeting['title'] ?? '') ?></div>
      <div class="text-sm text-gray-500">
        📍 <?= h($meeting['location'] ?? '') ?> &nbsp;|&nbsp; 🕐 <?= h($meeting['start_time'] ?? '') ?>
      </div>
    </div>
  </div>

  <!-- 目前狀態面板 -->
  <div id="phase-panel">

    <!-- 待機 -->
    <div id="panel-standby" class="hidden card bg-base-100 shadow">
      <div class="card-body items-center text-center py-10">
        <div class="text-5xl mb-3">⏳</div>
        <h2 class="text-xl font-bold text-gray-600">會議待機中</h2>
        <p class="text-gray-400">請等候主辦人開始程序</p>
        <div class="badge badge-success badge-lg mt-4">✅ 簽到成功</div>
      </div>
    </div>

    <!-- 議程（報告/討論） -->
    <div id="panel-agenda" class="hidden card bg-base-100 shadow">
      <div class="card-body">
        <div class="text-sm text-blue-600 font-semibold mb-1">📣 目前議程</div>
        <h2 id="agenda-title" class="text-xl font-bold mb-4"></h2>
        <p id="agenda-desc" class="text-gray-600 text-sm mb-4"></p>

        <!-- 發言申請按鈕 -->
        <div id="speech-area">
          <button id="speech-btn" onclick="requestSpeech()"
                  class="btn btn-primary w-full btn-lg">
            🎤 請求發言地位
          </button>
          <button id="cancel-speech-btn" onclick="cancelSpeech()"
                  class="btn btn-outline w-full hidden mt-2">
            ✕ 取消發言申請
          </button>
          <div id="speaking-badge" class="hidden alert alert-success mt-2">
            🎤 您目前正在發言
          </div>
          <button id="end-speech-btn" onclick="endSpeech()"
                  class="btn btn-outline w-full hidden mt-2">
            ✅ 結束發言
          </button>
        </div>
      </div>
    </div>

    <!-- 表決 -->
    <div id="panel-resolution" class="hidden card bg-base-100 shadow">
      <div class="card-body">
        <div class="text-sm text-orange-600 font-semibold mb-1">🪧 表決進行中</div>
        <h2 id="resolution-title" class="text-xl font-bold mb-2"></h2>
        <p id="resolution-desc" class="text-gray-600 text-sm mb-6"></p>

        <?php if ($role === 'member'): ?>
        <div id="vote-buttons" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
          <button onclick="submitVote('yes')"
                  class="btn btn-success btn-lg h-24 flex-col gap-1 text-success-content">
            <span class="text-3xl">✅</span>
            <span class="font-bold">同意</span>
          </button>
          <button onclick="submitVote('no')"
                  class="btn btn-error btn-lg h-24 flex-col gap-1 text-error-content">
            <span class="text-3xl">❌</span>
            <span class="font-bold">反對</span>
          </button>
          <button onclick="submitVote('abstain')"
                  class="btn btn-warning btn-lg h-24 flex-col gap-1 text-warning-content">
            <span class="text-3xl">⚪</span>
            <span class="font-bold">棄權</span>
          </button>
        </div>
        <div id="vote-done" class="hidden alert text-center text-lg py-6">
          <span id="vote-done-text"></span>
        </div>
        <?php else: ?>
        <div class="alert alert-info">列席人無表決權</div>
        <?php endif; ?>

        <!-- 即時票數（給列席人和已投票者看） -->
        <div id="live-vote-stats" class="grid grid-cols-3 gap-2 text-center mt-2">
          <div class="bg-green-50 rounded p-2">
            <div class="text-xs text-gray-500">同意</div>
            <div class="text-xl font-bold text-green-600" id="m-yes">—</div>
          </div>
          <div class="bg-red-50 rounded p-2">
            <div class="text-xs text-gray-500">反對</div>
            <div class="text-xl font-bold text-red-600" id="m-no">—</div>
          </div>
          <div class="bg-yellow-50 rounded p-2">
            <div class="text-xs text-gray-500">棄權</div>
            <div class="text-xl font-bold text-yellow-600" id="m-abstain">—</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 選舉 -->
    <div id="panel-election" class="hidden card bg-base-100 shadow">
      <div class="card-body">
        <div class="text-sm text-purple-600 font-semibold mb-1">🏆 選舉進行中</div>
        <h2 id="election-title" class="text-xl font-bold mb-1"></h2>
        <div id="election-seats-info" class="badge badge-secondary mb-4"></div>

        <?php if ($role === 'member'): ?>
        <div id="election-vote-area">
          <p class="text-sm text-gray-500 mb-3">請勾選您支持的候選人（不超過票數上限）：</p>
          <div id="candidates-list" class="space-y-2 mb-4"></div>
          <button id="submit-election-btn" onclick="submitElectionVote()"
                  class="btn btn-primary w-full btn-lg">確認投票</button>
        </div>
        <div id="election-done" class="hidden alert alert-success text-center py-6">
          ✅ 您已完成投票
        </div>
        <?php else: ?>
        <div class="alert alert-info">列席人無投票權</div>
        <?php endif; ?>

        <div id="election-candidates-result" class="space-y-1 mt-4 hidden">
          <div class="font-semibold text-sm mb-2">目前票數：</div>
        </div>
      </div>
    </div>

    <!-- 臨時動議 -->
    <div id="panel-temp" class="hidden card bg-base-100 shadow">
      <div class="card-body">
        <div class="text-sm text-gray-600 font-semibold mb-1">📝 臨時動議</div>
        <h2 class="text-xl font-bold mb-4">提出臨時動議</h2>
        <div id="motion-form">
          <textarea id="motion-content" class="textarea textarea-bordered w-full mb-3" rows="4"
                    placeholder="請輸入您的臨時動議案由..."></textarea>
          <button onclick="submitMotion()" class="btn btn-primary w-full">📤 送出（待主辦人審核）</button>
        </div>
        <div id="motion-sent" class="hidden alert alert-success">
          ✅ 動議已送出，等待主辦人審核。
        </div>
      </div>
    </div>

    <!-- 結束 -->
    <div id="panel-ended" class="hidden card bg-base-100 shadow">
      <div class="card-body items-center text-center py-10">
        <div class="text-5xl mb-3">🎉</div>
        <h2 class="text-xl font-bold">會議已結束</h2>
        <p class="text-gray-500 mt-2">感謝您的參與！<br>也別忘了登出您的帳號</p>
      </div>
    </div>

  </div>

  <!-- 發言佇列顯示 -->
  <div id="speech-queue-card" class="card bg-base-100 shadow hidden">
    <div class="card-body p-4">
      <h3 class="font-bold mb-2">🎤 發言佇列</h3>
      <div id="speech-queue-list" class="space-y-1 text-sm"></div>
    </div>
  </div>

</div>

<script>
const MEETING_ID  = <?= $mid ?>;
const MEMBER_ID   = <?= $member_id ?>;
const IS_OBSERVER = <?= $role === 'observer' ? 'true' : 'false' ?>;
const BASE_URL    = '<?= BASE_URL ?>';

let myVote       = null;
let myElecVotedItems = {};   // { agenda_item_id: true }
let lastElectionItemId = null;
let mySpeechId   = null;
let mySpeechStatus = null;
let selectedCandidates = new Set();
let currentElectionSeats = 1;
let currentItemId = null;

async function poll() {
    try {
        const res = await fetch(`${BASE_URL}/api/status.php?meeting_id=${MEETING_ID}`);
        const d = await res.json();
        if (d.ok) updateMemberUI(d.data);
    } catch (e) {}
    setTimeout(poll, <?= POLL_MS ?>);
}

function showPanel(name) {
    ['standby','agenda','resolution','election','temp','ended'].forEach(p => {
        document.getElementById(`panel-${p}`).classList.add('hidden');
    });
    document.getElementById(`panel-${name}`)?.classList.remove('hidden');
}

function updateMemberUI(data) {
    const { phase, item, vote_stats, members, speech_queue, election, candidates } = data;
    const pt = phase.phase_type;
    currentItemId = phase.agenda_item_id;

    // find my member data
    const me = members.find(m => m.id == MEMBER_ID);
    myVote = me?.vote || null;

    // panels
    if (pt === 'standby')     showPanel('standby');
    else if (pt === 'agenda') {
        showPanel('agenda');
        document.getElementById('agenda-title').textContent = item?.title || '';
        document.getElementById('agenda-desc').textContent  = item?.description || '';
    }
    else if (pt === 'resolution') {
        showPanel('resolution');
        document.getElementById('resolution-title').textContent = item?.title || '';
        document.getElementById('resolution-desc').textContent  = item?.description || '';

        const closed = item?.status === 'closed';
        const voted  = !!myVote;
        document.getElementById('vote-buttons')?.classList.toggle('hidden', voted || closed || IS_OBSERVER);
        document.getElementById('vote-done')?.classList.toggle('hidden', !voted && !closed);
        if (voted || closed) {
            const labels = {yes:'✅ 已投：同意', no:'❌ 已投：反對', abstain:'⚪ 已投：棄權'};
            document.getElementById('vote-done-text').textContent =
                myVote ? (labels[myVote] || '') : (closed ? '⚪ 表決已截止（自動棄權）' : '');
        }

        // stats (show after voted or closed)
        const statsEl = document.getElementById('live-vote-stats');
        if (vote_stats && (voted || closed || IS_OBSERVER)) {
            statsEl.classList.remove('hidden');
            document.getElementById('m-yes').textContent     = vote_stats.yes_count || 0;
            document.getElementById('m-no').textContent      = vote_stats.no_count || 0;
            document.getElementById('m-abstain').textContent = vote_stats.abstain_count || 0;
        }
    }
    else if (pt === 'election') {
        // 切換到新選舉時，重置選擇
        if (currentItemId !== lastElectionItemId) {
            lastElectionItemId = currentItemId;
            selectedCandidates = new Set();
        }
        // 從伺服器取得是否已投票（解決跨選舉殘留問題）
        if (data.my_election_voted !== undefined) {
            myElecVotedItems[currentItemId] = data.my_election_voted;
        }
        showPanel('election');
        document.getElementById('election-title').textContent = item?.title || '';
        if (election) {
            currentElectionSeats = election.seats;
            document.getElementById('election-seats-info').textContent =
                `每人可投 ${election.seats} 票`;
        }
        if (!IS_OBSERVER) {
                updateCandidatesUI(candidates, item?.status === 'closed', !!myElecVotedItems[currentItemId]);
            }
    }
    else if (pt === 'temp_motion') showPanel('temp');
    else if (pt === 'ended')       showPanel('ended');

    // speech queue
    updateSpeechUI(speech_queue, pt === 'agenda');

    // reset motion form on new item
    if (pt === 'temp_motion') {
        document.getElementById('motion-form').classList.remove('hidden');
        document.getElementById('motion-sent').classList.add('hidden');
    }
}

function updateCandidatesUI(candidates, closed, alreadyVoted) {
    const el = document.getElementById('candidates-list');
    const btn = document.getElementById('submit-election-btn');
    const done = document.getElementById('election-done');

    if (alreadyVoted || closed) {
        el.classList.add('hidden');
        btn?.classList.add('hidden');
        done.classList.remove('hidden');
        return;
    }

    el.innerHTML = candidates.map(c =>
        `<label class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-base-200">
           <input type="checkbox" class="checkbox checkbox-primary"
                  value="${c.id}" onchange="toggleCandidate(${c.id}, this.checked)"
                  ${selectedCandidates.has(c.id) ? 'checked' : ''}>
           <span class="font-medium">${escHtml(c.name)}</span>
         </label>`
    ).join('');

    document.getElementById('submit-election-btn').textContent =
        `確認投票（已選 ${selectedCandidates.size} / ${currentElectionSeats}）`;
}

function toggleCandidate(id, checked) {
    if (checked && selectedCandidates.size >= currentElectionSeats) {
        event.target.checked = false;
        alert(`最多只能選 ${currentElectionSeats} 位候選人！`);
        return;
    }
    if (checked) selectedCandidates.add(id);
    else selectedCandidates.delete(id);
    document.getElementById('submit-election-btn').textContent =
        `確認投票（已選 ${selectedCandidates.size} / ${currentElectionSeats}）`;
}

function updateSpeechUI(queue, inAgenda) {
    const card = document.getElementById('speech-queue-card');
    card.classList.toggle('hidden', !inAgenda || !queue.length);
    if (!inAgenda) return;

    // find my speech
    const mine = queue.find(s => s.member_id == MEMBER_ID);
    mySpeechId = mine?.id || null;
    mySpeechStatus = mine?.status || null;

    const btn    = document.getElementById('speech-btn');
    const cancel = document.getElementById('cancel-speech-btn');
    const badge  = document.getElementById('speaking-badge');
    const endBtn = document.getElementById('end-speech-btn');
    endBtn.classList.toggle('hidden', mine?.status !== 'speaking');

    if (!mine) {
        btn.classList.remove('hidden');
        cancel.classList.add('hidden');
        badge.classList.add('hidden');
    } else if (mine.status === 'waiting') {
        btn.classList.add('hidden');
        cancel.classList.remove('hidden');
        badge.classList.add('hidden');
    } else if (mine.status === 'speaking') {
        btn.classList.add('hidden');
        cancel.classList.add('hidden');
        badge.classList.remove('hidden');
    }

    const list = document.getElementById('speech-queue-list');
    list.innerHTML = queue.map((s, i) =>
        `<div class="flex items-center gap-2 ${s.member_id == MEMBER_ID ? 'font-bold' : ''}">
           <span class="text-gray-400 text-xs w-4">${i+1}</span>
           <span class="${s.status==='speaking' ? 'text-green-600' : ''}">
             ${s.status==='speaking' ? '🎤 ' : ''}${escHtml(s.name)}
           </span>
         </div>`
    ).join('');
}

async function submitVote(vote) {
    if (!confirm(`確定投「${{yes:'同意',no:'反對',abstain:'棄權'}[vote]}」？投票後不可更改。`)) return;
    const res = await fetch(`${BASE_URL}/api/vote.php`, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `meeting_id=${MEETING_ID}&agenda_item_id=${currentItemId}&member_id=${MEMBER_ID}&vote=${vote}`
    });
    const d = await res.json();
    if (d.ok) {
        myVote = vote;
        document.getElementById('vote-buttons')?.classList.add('hidden');
        document.getElementById('vote-done')?.classList.remove('hidden');
        const labels = {yes:'✅ 已投：同意', no:'❌ 已投：反對', abstain:'⚪ 已投：棄權'};
        document.getElementById('vote-done-text').textContent = labels[vote];
    } else {
        alert('投票失敗：' + d.error);
    }
}

async function submitElectionVote() {
    if (!selectedCandidates.size) { alert('請至少選擇 1 位候選人。'); return; }
    if (!confirm(`確定投票？共選 ${selectedCandidates.size} 人，投票後不可更改。`)) return;
    const ids = Array.from(selectedCandidates).join(',');
    const res = await fetch(`${BASE_URL}/api/election_vote.php`, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `meeting_id=${MEETING_ID}&agenda_item_id=${currentItemId}&member_id=${MEMBER_ID}&candidate_ids=${ids}`
    });
    const d = await res.json();
    if (d.ok) {
        // myElecVoted = true;
        myElecVotedItems[currentItemId] = true;
        document.getElementById('election-vote-area')?.classList.add('hidden');
        document.getElementById('election-done').classList.remove('hidden');
    } else alert('投票失敗：' + d.error);
}

async function requestSpeech() {
    await fetch(`${BASE_URL}/api/speech.php`, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=request&meeting_id=${MEETING_ID}&member_id=${MEMBER_ID}&agenda_item_id=${currentItemId}`
    });
}

async function cancelSpeech() {
    if (!mySpeechId) return;
    await fetch(`${BASE_URL}/api/speech.php`, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=cancel&id=${mySpeechId}&meeting_id=${MEETING_ID}`
    });
}

async function endSpeech() {
    if (!mySpeechId) return;
    await fetch(`${BASE_URL}/api/speech.php`, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=update&id=${mySpeechId}&status=done&meeting_id=${MEETING_ID}&member_id=${MEMBER_ID}`
    });
}

async function submitMotion() {
    const content = document.getElementById('motion-content').value.trim();
    if (!content) { alert('請填寫動議內容。'); return; }
    const res = await fetch(`${BASE_URL}/api/motion.php`, {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=submit&meeting_id=${MEETING_ID}&member_id=${MEMBER_ID}&content=${encodeURIComponent(content)}`
    });
    const d = await res.json();
    if (d.ok) {
        document.getElementById('motion-content').value = '';
        document.getElementById('motion-sent').classList.remove('hidden');
        setTimeout(() => {
            document.getElementById('motion-sent').classList.add('hidden');
        }, 5000);
    } else alert('送出失敗。');
}

function escHtml(s) {
    if (!s) return '';
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

poll();
</script>

</body>
</html>
