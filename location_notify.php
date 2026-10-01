<?php
require_once __DIR__ . '/../../config/config_frozen.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram_notify.php';

$msg = '';
$msg_type = 'ok';

// Build hour options 0-23
function hour_label(int $h): string {
  $suffix = $h < 12 ? 'AM' : 'PM';
  $disp   = $h % 12 ?: 12;
  return $disp . $suffix;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'subscribe') {
    $phone   = preg_replace('/[\s\-]/', '', trim($_POST['phone'] ?? ''));
    $all_day = ($_POST['hour_start'] ?? '') === 'all' ? 1 : 0;
    $h_start = $all_day ? 0 : (int)($_POST['hour_start'] ?? 0);
    $h_end   = $all_day ? 23 : (int)($_POST['hour_end'] ?? 23);

    if (!preg_match('/^\+?[\d]{7,15}$/', $phone)) {
      $msg = '❌ Nombor telefon tidak sah.';
      $msg_type = 'err';
    } else {
      try {
        $stmt = $pdo->prepare('SELECT id FROM location_subscribers WHERE phone=?');
        $stmt->execute([$phone]);
        if ($stmt->fetchColumn()) {
          // Update existing
          $pdo->prepare('UPDATE location_subscribers SET all_day=?,hour_start=?,hour_end=? WHERE phone=?')
              ->execute([$all_day, $h_start, $h_end, $phone]);
          $msg = '✅ Tetapan notifikasi anda telah dikemaskini.';
        } else {
          $pdo->prepare('INSERT INTO location_subscribers (phone,all_day,hour_start,hour_end,created_at) VALUES (?,?,?,?,?)')
              ->execute([$phone, $all_day, $h_start, $h_end, date('Y-m-d H:i:s')]);
          $msg = '✅ Berjaya didaftarkan!<br><br>Untuk mula menerima notifikasi, ikut langkah berikut:<br><br>1. Buka Telegram dan cari bot <strong><a href="https://t.me/FrozenFoodAlertBot" target="_blank" style="color:#16a34a;">@FrozenFoodAlertBot</a></strong><br>2. Tekan <strong>Start</strong><br>3. Hantar mesej: <strong>/daftar ' . htmlspecialchars($phone) . '</strong><br><br>Selepas itu, anda akan menerima notifikasi apabila lokasi dikemaskini.';
        }
      } catch (Exception $e) {
        $msg = '❌ Ralat. Sila cuba lagi.';
        $msg_type = 'err';
      }
    }

  } elseif ($action === 'unsubscribe') {
    $phone = preg_replace('/[\s\-]/', '', trim($_POST['unsub_phone'] ?? ''));
    if ($phone) {
      $pdo->prepare('DELETE FROM location_subscribers WHERE phone=?')->execute([$phone]);
    }
    $msg = 'Anda tidak akan menerima notifikasi lagi.';
  }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notifikasi Lokasi</title>
<link rel="stylesheet" href="/frozen/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container" style="max-width:520px;">
  <h1>Notifikasi Lokasi</h1>
  <p style="color:#475569;margin-bottom:20px;">Daftar untuk menerima notifikasi Telegram apabila lokasi kami dikemaskini.</p>

  <?php if ($msg): ?>
  <div class="alert <?= $msg_type === 'err' ? 'alert-warn' : '' ?>"><?= $msg ?></div>
  <?php endif; ?>

  <h2>Daftar / Kemaskini</h2>
  <form method="post">
    <input type="hidden" name="action" value="subscribe">
    <div class="form-grid">
      <label>Nombor Telefon
        <input type="tel" name="phone" placeholder="cth: 0123456789" required>
      </label>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;grid-column:1/-1;">
        <label>Masa untuk mula terima notifikasi
          <select name="hour_start" id="hour_start" onchange="toggleEnd()">
            <option value="all">Sepanjang Hari</option>
            <?php for ($h = 0; $h < 24; $h++): ?>
            <option value="<?= $h ?>"><?= hour_label($h) ?></option>
            <?php endfor; ?>
          </select>
        </label>
        <label>Masa untuk tamat terima notifikasi
          <select name="hour_end" id="hour_end" disabled>
            <?php for ($h = 0; $h < 24; $h++): ?>
            <option value="<?= $h ?>" <?= $h === 23 ? 'selected' : '' ?>><?= hour_label($h) ?></option>
            <?php endfor; ?>
          </select>
        </label>
      </div>

      <button type="submit" style="grid-column:1/-1;">Daftar</button>
    </div>
  </form>

  <hr style="margin:28px 0;border:none;border-top:1px solid #e2e8f0;">

  <h2>Berhenti Langganan</h2>
  <form method="post">
    <input type="hidden" name="action" value="unsubscribe">
    <div class="form-grid">
      <label>Nombor Telefon
        <input type="tel" name="unsub_phone" placeholder="cth: 0123456789" required>
      </label>
      <button type="submit" class="btn-danger">Berhenti Langganan</button>
    </div>
  </form>
</div>

<script>
function toggleEnd() {
  const isAll = document.getElementById('hour_start').value === 'all';
  document.getElementById('hour_end').disabled = isAll;
}
</script>
</body>
</html>
