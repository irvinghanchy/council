<?php
require_once __DIR__ . '/../config.php';

// 產生隨機 state 防 CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
// 記住要回到哪裡
$_SESSION['oauth_intent'] = $_GET['as'] ?? 'member'; // 'host' or 'member'

$params = http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'hd'            => ALLOWED_DOMAIN,   // 限制學校網域（提示用，仍需後端驗證）
    'prompt'        => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
