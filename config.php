<?php
date_default_timezone_set('Asia/Taipei');
// ═══════════════════════════════════════════════════════════════
//  系統設定檔  config.php
//  ⚠️  安裝後請逐一修改以下設定值
// ═══════════════════════════════════════════════════════════════

// ─── 資料庫 ────────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'council_vote');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ─── Google OAuth 2.0 ──────────────────────────────────────────
// 請至 https://console.cloud.google.com 建立 OAuth 2.0 用戶端憑證
// 已授權的重新導向 URI 請填入 GOOGLE_REDIRECT_URI 的值
define('GOOGLE_CLIENT_ID',     '146003004231-l8gvavu5k0l95spfa202p9skbd5di3dq.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI',  'http://localhost/council/auth/google_callback.php');

// ─── 學校信箱網域 ──────────────────────────────────────────────
// 登入時會驗證信箱網域，可設多個（用 | 分隔）
define('ALLOWED_DOMAIN', 'stu.nknush.kh.edu.tw');

// ─── 系統根 URL（無結尾斜線）────────────────────────────────
define('BASE_URL', 'http://localhost/council');

// ─── 輪詢間隔（毫秒）─────────────────────────────────────────
define('POLL_MS', 2000);

// ─── 大螢幕存取碼（直接在 URL 加 ?pin=XXXX）────────────────
define('SCREEN_PIN', 'screen2025');

// ─── Session ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name('council_sess');
    session_start();
}
