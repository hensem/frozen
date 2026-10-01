<?php
require_once __DIR__ . '/../../config/config_frozen.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram_notify.php';

$body = file_get_contents('php://input');
$upd  = json_decode($body, true);

$msg     = $upd['message'] ?? null;
$chat_id = $msg['chat']['id'] ?? null;
$text    = trim($msg['text'] ?? '');

if (!$chat_id || !$text) exit;

if (preg_match('/^\/daftar\s+(\+?[\d\s\-]+)$/i', $text, $m)) {
  $phone = preg_replace('/[\s\-]/', '', $m[1]);
  $stmt  = $pdo->prepare('SELECT id FROM location_subscribers WHERE phone=?');
  $stmt->execute([$phone]);
  if ($stmt->fetchColumn()) {
    $pdo->prepare('UPDATE location_subscribers SET chat_id=? WHERE phone=?')->execute([$chat_id, $phone]);
    tg_send($chat_id, "✅ Berjaya! Anda akan menerima notifikasi lokasi.");
  } else {
    tg_send($chat_id, "❌ Nombor telefon tidak dijumpai. Sila daftar di laman web dahulu.");
  }
}
