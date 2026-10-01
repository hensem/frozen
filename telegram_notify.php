<?php
// Send a Telegram message to a single chat_id. Returns true on success.
function tg_send(int $chat_id, string $text): bool {
  $url  = 'https://api.telegram.org/bot' . FROZEN_TELEGRAM_TOKEN . '/sendMessage';
  $ctx  = stream_context_create(['http' => [
    'method'  => 'POST',
    'header'  => 'Content-Type: application/x-www-form-urlencoded',
    'content' => http_build_query(['chat_id' => $chat_id, 'text' => $text]),
    'timeout' => 5,
  ]]);
  $res = @file_get_contents($url, false, $ctx);
  return $res !== false;
}

// Notify all eligible subscribers about a location change.
function tg_notify_location(PDO $pdo, string $location): void {
  $hour = (int)date('G'); // 0-23
  $subs = $pdo->query('SELECT chat_id FROM location_subscribers WHERE chat_id IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);
  foreach ($subs as $s) {
    // Re-fetch per subscriber to check time window
    $sub = $pdo->prepare('SELECT all_day, hour_start, hour_end FROM location_subscribers WHERE chat_id=?');
    $sub->execute([$s['chat_id']]);
    $sub = $sub->fetch(PDO::FETCH_ASSOC);
    if (!$sub) continue;
    if (!$sub['all_day']) {
      $start = (int)$sub['hour_start'];
      $end   = (int)$sub['hour_end'];
      // end hour means up to end:59, so check hour >= start && hour <= end
      if ($hour < $start || $hour > $end) continue;
    }
    tg_send((int)$s['chat_id'], "📍 Lokasi terkini: $location");
  }
}
