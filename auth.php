<?php
require_once __DIR__ . '/../../config/config_frozen.php';
use League\OAuth2\Client\Provider\Google;
require_once __DIR__ . '/../../library/vendor/autoload.php';

session_set_cookie_params(['lifetime'=>86400*30,'httponly'=>true,'secure'=>true,'samesite'=>'Lax']);
session_start();

if ($_SERVER['HTTP_HOST'] !== FROZEN_ALLOWED_HOST) { http_response_code(403); exit('Access denied.'); }

if (!isset($_GET['code'], $_GET['state'], $_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
  header('Location: /frozen/home.php');
  exit;
}
unset($_SESSION['oauth_state']);

$google_provider = new Google([
  'clientId'     => FROZEN_GOOGLE_CLIENT_ID,
  'clientSecret' => FROZEN_GOOGLE_CLIENT_SECRET,
  'redirectUri'  => FROZEN_GOOGLE_REDIRECT_URI,
  'scopes'       => ['openid','email','profile'],
]);

try {
  $token = $google_provider->getAccessToken('authorization_code', ['code' => $_GET['code']]);
  $guser = $google_provider->getResourceOwner($token);
} catch (Exception $e) {
  header('Location: /frozen/home.php');
  exit;
}

$google_id = $guser->getId();
$name      = trim($guser->getFirstName() . ' ' . $guser->getLastName());
$email     = $guser->getEmail();
$avatar    = $guser->getAvatar() ?? '';

require_once __DIR__ . '/db.php';

$stmt = $pdo->prepare('SELECT * FROM users WHERE google_id=?');
$stmt->execute([$google_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  $pdo->prepare('INSERT INTO users (google_id,name,email,avatar,created_at) VALUES (?,?,?,?,?)')
      ->execute([$google_id, $name, $email, $avatar, date('Y-m-d H:i:s')]);
  $user_id = $pdo->lastInsertId();
  $pdo->prepare('INSERT INTO activity_log (user_id,action,detail,created_at) VALUES (?,?,?,?)')
      ->execute([$user_id, 'register', 'First login', date('Y-m-d H:i:s')]);
} elseif ($user['banned'] || $user['deleted']) {
  session_destroy();
  header('Location: /frozen/home.php');
  exit;
} else {
  $user_id = $user['id'];
  $pdo->prepare('UPDATE users SET name=?,email=?,avatar=? WHERE id=?')
      ->execute([$name, $email, $avatar, $user_id]);
  $pdo->prepare('INSERT INTO activity_log (user_id,action,detail,created_at) VALUES (?,?,?,?)')
      ->execute([$user_id, 'login', null, date('Y-m-d H:i:s')]);
}

session_regenerate_id(true);
$_SESSION['frozen_user_id'] = $user_id;
header('Location: /frozen/');
exit;
