
<?php
require_once __DIR__ . '/../../config/config_frozen.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram_notify.php';

$msg = '';
$msg_type = 'ok';

// Build hour options 0-23.
function hour_label(int $h): string {
    $suffix = $h < 12 ? 'AM' : 'PM';
    $disp   = $h % 12 ?: 12;

    return $disp . $suffix;
}

// Normalize phone number.
function normalize_phone(string $phone): string {
    return preg_replace('/[\s\-]/', '', trim($phone));
}

// Telegram bot username.
$telegram_bot = 'FrozenFoodAlertBot';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    // Subscribe / update notification preferences.
    if ($action === 'subscribe') {

        $phone = normalize_phone($_POST['phone'] ?? '');

        $all_day = ($_POST['hour_start'] ?? '') === 'all' ? 1 : 0;

        $h_start = $all_day ? 0 : (int)($_POST['hour_start'] ?? 0);
        $h_end   = $all_day ? 23 : (int)($_POST['hour_end'] ?? 23);

        if (!preg_match('/^\+?[\d]{7,15}$/', $phone)) {

            $msg = '❌ Nombor telefon tidak sah.';
            $msg_type = 'err';

        } elseif (!$all_day && ($h_start < 0 || $h_start > 23 || $h_end < 0 || $h_end > 23 || $h_start > $h_end)) {

            $msg = '❌ Julat masa tidak sah.';
            $msg_type = 'err';

        } else {

            try {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM location_subscribers
                    WHERE phone = ?
                ");

                $stmt->execute([$phone]);

                $subscriber_id = $stmt->fetchColumn();

                if ($subscriber_id) {

                    // Update existing subscriber preferences.
                    $pdo->prepare("
                        UPDATE location_subscribers
                        SET all_day = ?,
                            hour_start = ?,
                            hour_end = ?
                        WHERE id = ?
                    ")->execute([
                        $all_day,
                        $h_start,
                        $h_end,
                        $subscriber_id
                    ]);

                    // Check if Telegram is already linked.
                    $stmt = $pdo->prepare("
                        SELECT chat_id
                        FROM location_subscribers
                        WHERE id = ?
                    ");

                    $stmt->execute([$subscriber_id]);

                    $chat_id = $stmt->fetchColumn();

                    if ($chat_id) {

                        $msg = '✅ Tetapan notifikasi anda telah dikemaskini.';

                    } else {

                        // Remove + from phone for Telegram deep-link payload.
                        $payload = preg_replace('/[^0-9]/', '', $phone);

                        $telegram_link = 'https://t.me/'
                            . $telegram_bot
                            . '?start='
                            . $payload;

                        $msg = '✅ Tetapan anda telah dikemaskini!'
                            . '<br><br>'
                            . 'Untuk mengaktifkan notifikasi Telegram:'
                            . '<br><br>'
                            . '<a class="btn" href="'
                            . htmlspecialchars($telegram_link, ENT_QUOTES, 'UTF-8')
                            . '" target="_blank" rel="noopener">'
                            . 'Aktifkan Telegram'
                            . '</a>'
                            . '<br><br>'
                            . 'Tekan Start dalam Telegram untuk melengkapkan pendaftaran.';
                    }

                } else {

                    // Insert new subscriber.
                    $pdo->prepare("
                        INSERT INTO location_subscribers
                        (phone, all_day, hour_start, hour_end, created_at)
                        VALUES (?, ?, ?, ?, ?)
                    ")->execute([
                        $phone,
                        $all_day,
                        $h_start,
                        $h_end,
                        date('Y-m-d H:i:s')
                    ]);

                    // Generate Telegram deep link.
                    $payload = preg_replace('/[^0-9]/', '', $phone);

                    $telegram_link = 'https://t.me/'
                        . $telegram_bot
                        . '?start='
                        . $payload;

                    $msg = '✅ Berjaya didaftarkan!'
                        . '<br><br>'
                        . 'Langkah terakhir untuk menerima notifikasi:'
                        . '<br><br>'
                        . '<a class="btn" href="'
                        . htmlspecialchars($telegram_link, ENT_QUOTES, 'UTF-8')
                        . '" target="_blank" rel="noopener">'
                        . 'Aktifkan Telegram'
                        . '</a>'
                        . '<br><br>'
                        . '1. Klik butang di atas.'
                        . '<br>'
                        . '2. Tekan Start dalam Telegram.'
                        . '<br>'
                        . '3. Anda akan menerima notifikasi apabila lokasi jualan dikemaskini.';
                }

            } catch (Exception $e) {

                error_log('Location subscription error: ' . $e->getMessage());

                $msg = '❌ Ralat. Sila cuba lagi.';
                $msg_type = 'err';
            }
        }

    // Unsubscribe.
    } elseif ($action === 'unsubscribe') {

        $phone = normalize_phone($_POST['unsub_phone'] ?? '');

        if ($phone) {

            $pdo->prepare("
                DELETE FROM location_subscribers
                WHERE phone = ?
            ")->execute([$phone]);
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

<div style="padding:12px 16px;"><a href="/frozen/home.php" style="color:#64748b;font-size:.85rem;text-decoration:none;">&larr; Kembali</a></div>

<div class="container" style="max-width:520px;">

    <h1>Notifikasi Lokasi</h1>

    <p style="color:#475569;margin-bottom:20px;">
        Daftar untuk menerima notifikasi Telegram apabila lokasi kami dikemaskini.
    </p>

    <?php if ($msg): ?>
        <div class="alert <?= $msg_type === 'err' ? 'alert-warn' : '' ?>">
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <h2>Daftar / Kemaskini</h2>

    <form method="post">

        <input type="hidden" name="action" value="subscribe">

        <div class="form-grid">

            <label>Nombor Telefon
                <input
                    type="tel"
                    name="phone"
                    placeholder="cth: 0123456789"
                    required
                >
            </label>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;grid-column:1/-1;">

                <label>Masa untuk mula terima notifikasi

                    <select name="hour_start" id="hour_start" onchange="toggleEnd()">

                        <option value="all">Sepanjang Hari</option>

                        <?php for ($h = 0; $h < 24; $h++): ?>

                            <option value="<?= $h ?>">
                                <?= hour_label($h) ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </label>

                <label id="hour_end_wrap">Masa untuk tamat terima notifikasi

                    <select name="hour_end" id="hour_end">

                        <?php for ($h = 0; $h < 24; $h++): ?>

                            <option
                                value="<?= $h ?>"
                                <?= $h === 23 ? 'selected' : '' ?>
                            >
                                <?= hour_label($h) ?>
                            </option>

                        <?php endfor; ?>

                    </select>

                </label>

            </div>

            <button type="submit" style="grid-column:1/-1;">
                Daftar
            </button>

        </div>

    </form>

    <hr style="margin:28px 0;border:none;border-top:1px solid #e2e8f0;">

    <h2>Berhenti Langganan</h2>

    <form method="post">

        <input type="hidden" name="action" value="unsubscribe">

        <div class="form-grid">

            <label>Nombor Telefon

                <input
                    type="tel"
                    name="unsub_phone"
                    placeholder="cth: 0123456789"
                    required
                >

            </label>

            <button type="submit" class="btn-danger">
                Berhenti Langganan
            </button>

        </div>

    </form>

</div>

<script>
function toggleEnd() {
    const isAll = document.getElementById('hour_start').value === 'all';
    const wrap  = document.getElementById('hour_end_wrap');
    const sel   = document.getElementById('hour_end');
    wrap.style.display  = isAll ? 'none' : '';
    sel.disabled        = isAll;
}
toggleEnd();
</script>

</body>
</html>
