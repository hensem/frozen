<?php
require_once __DIR__ . '/../../config/config_frozen.php';

use League\OAuth2\Client\Provider\Google;

require_once __DIR__ . '/../../library/vendor/autoload.php';

session_set_cookie_params([
  'lifetime' => 86400 * 30,
  'httponly' => true,
  'secure'   => true,
  'samesite' => 'Lax',
]);
session_start();

if ($_SERVER['HTTP_HOST'] !== FROZEN_ALLOWED_HOST) {
  http_response_code(403);
  exit('Access denied.');
}

$google_provider = new Google([
  'clientId'     => FROZEN_GOOGLE_CLIENT_ID,
  'clientSecret' => FROZEN_GOOGLE_CLIENT_SECRET,
  'redirectUri'  => FROZEN_GOOGLE_REDIRECT_URI,
  'scopes'       => ['openid', 'email', 'profile'],
]);

// Turnstile gate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cf-turnstile-response'])) {
  $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
      'secret'   => FROZEN_CF_SECRET_KEY,
      'response' => $_POST['cf-turnstile-response'],
      'remoteip' => $_SERVER['REMOTE_ADDR'],
    ]),
  ]);
  $result = json_decode(curl_exec($ch), true);
  curl_close($ch);
  if ($result['success'] ?? false) {
    $_SESSION['frozen_human'] = time();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
  }
  $turnstile_error = true;
}

if (empty($_SESSION['frozen_human']) || $_SESSION['frozen_human'] < time() - 3600) {
  // Skip gate for authenticated AJAX requests
  if (!($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !empty($_SESSION['frozen_user_id']))) {
  ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Frozen</title>
<link rel="stylesheet" href="/frozen/style.css">
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<script>function onVerified(){ document.getElementById('gate-form').submit(); }</script>
</head>
<body>
<div class="gate-wrap">
  <?php if (!empty($turnstile_error)): ?>
    <p class="gate-error">Verification failed. Please try again.</p>
  <?php endif; ?>
  <form method="POST" id="gate-form">
    <div class="cf-turnstile" data-sitekey="<?= FROZEN_CF_SITE_KEY ?>" data-theme="light" data-callback="onVerified"></div>
  </form>
</div>
</body>
</html><?php
  exit;
  } // end AJAX exemption
} // end Turnstile gate

// Google login check
$frozen_user = null;
if (!empty($_SESSION['frozen_user_id'])) {
  require_once __DIR__ . '/db.php';
  $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? AND deleted=0');
  $stmt->execute([$_SESSION['frozen_user_id']]);
  $frozen_user = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$frozen_user || $frozen_user['banned']) {
    session_destroy();
    header('Location: /frozen/home.php');
    exit;
  }
}

if (!$frozen_user) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'session_expired']);
    exit;
  }
  header('Location: /frozen/home.php');
  exit;
}

function log_activity($pdo, $action, $detail = null) {
  global $frozen_user;
  $pdo->prepare('INSERT INTO activity_log (user_id,action,detail,created_at) VALUES (?,?,?,?)')
      ->execute([$frozen_user['id'], $action, $detail, date('Y-m-d H:i:s')]);
}

function require_approved() {
  global $frozen_user;
  if (!$frozen_user['approved']) {
    include __DIR__ . '/pending.php';
    exit;
  }
}
