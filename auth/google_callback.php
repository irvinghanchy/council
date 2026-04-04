<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/connect.php';
require_once __DIR__ . '/../includes/functions.php';

// ── 1. CSRF 驗證 ──────────────────────────────────────────────
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
    die('安全驗證失敗，請重新登入。');
}
unset($_SESSION['oauth_state']);

if (isset($_GET['error'])) {
    header('Location: ' . BASE_URL . '/index.php?err=oauth_denied');
    exit;
}

// ── 2. 換 access token ───────────────────────────────────────
$resp = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query([
            'code'          => $_GET['code'],
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code',
        ]),
    ],
]));

$token = json_decode($resp, true);
if (empty($token['id_token'])) {
    header('Location: ' . BASE_URL . '/index.php?err=oauth_fail');
    exit;
}

// ── 3. 解析 id_token（JWT payload，不需驗簽即可取 email） ──
$parts   = explode('.', $token['id_token']);
$payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4 ? strlen($parts[1]) + 4 - strlen($parts[1]) % 4 : 0, '=')), true);

$email = strtolower($payload['email'] ?? '');
$name  = $payload['name']  ?? '未知姓名';

// ── 4. 驗證網域 ──────────────────────────────────────────────
$domain = substr($email, strpos($email, '@') + 1);
if ($domain !== ALLOWED_DOMAIN) {
    header('Location: ' . BASE_URL . '/index.php?err=wrong_domain');
    exit;
}

// ── 5. 判斷角色 ──────────────────────────────────────────────
$pdo = db();

// 是否為主辦人？
$host = $pdo->prepare("SELECT * FROM hosts WHERE email = ?");
$host->execute([$email]);
$host = $host->fetch();

if ($host) {
    $_SESSION['role']      = 'host';
    $_SESSION['host_id']   = $host['id'];
    $_SESSION['user_name'] = $host['name'];
    $_SESSION['user_email']= $email;
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

// 是否為目前會議的議員/列席人？
$meeting = active_meeting();
if (!$meeting) {
    header('Location: ' . BASE_URL . '/index.php?err=no_meeting');
    exit;
}

$member = $pdo->prepare("SELECT * FROM members WHERE email = ? AND meeting_id = ?");
$member->execute([$email, $meeting['id']]);
$member = $member->fetch();

if (!$member) {
    header('Location: ' . BASE_URL . '/index.php?err=not_in_list');
    exit;
}

// 自動簽到
$pdo->prepare(
    "INSERT IGNORE INTO attendance (member_id, meeting_id) VALUES (?, ?)"
)->execute([$member['id'], $meeting['id']]);

// 寫 log（只在第一次簽到時）
$check = $pdo->prepare("SELECT signed_in_at FROM attendance WHERE member_id=? AND meeting_id=?");
$check->execute([$member['id'], $meeting['id']]);
$att = $check->fetch();

log_event($meeting['id'], 'sign_in', [
    'member_id' => $member['id'],
    'name'      => $member['name'],
]);

$_SESSION['role']       = $member['type'] === 'observer' ? 'observer' : 'member';
$_SESSION['member_id']  = $member['id'];
$_SESSION['meeting_id'] = $meeting['id'];
$_SESSION['user_name']  = $member['name'];
$_SESSION['user_email'] = $email;

header('Location: ' . BASE_URL . '/member/index.php');
exit;
