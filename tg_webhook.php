
<?php
require_once __DIR__ . '/../../config/config_frozen.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/telegram_notify.php';

/**
 * Normalize phone number for matching.
 */
function normalize_phone(string $phone): string {
    return preg_replace('/[\s\-+]/', '', trim($phone));
}

/**
 * Send Telegram response.
 */
function tg_reply(int $chat_id, string $text): void {
    tg_send($chat_id, $text);
}

/**
 * Register Telegram chat against an existing website subscriber.
 */
function register_subscriber(PDO $pdo, int $chat_id, string $phone): bool {
    $phone = normalize_phone($phone);

    if (!preg_match('/^[0-9]{7,15}$/', $phone)) {
        return false;
    }

    // Match phone numbers regardless of +, spaces or hyphens.
    $stmt = $pdo->prepare("
        SELECT id
        FROM location_subscribers
        WHERE REPLACE(
            REPLACE(
                REPLACE(phone, '+', ''),
                ' ', ''
            ),
            '-', ''
        ) = ?
        LIMIT 1
    ");

    $stmt->execute([$phone]);
    $subscriber_id = $stmt->fetchColumn();

    if (!$subscriber_id) {
        return false;
    }

    // Link Telegram chat to subscriber.
    $update = $pdo->prepare("
        UPDATE location_subscribers
        SET chat_id = ?
        WHERE id = ?
    ");

    $update->execute([$chat_id, $subscriber_id]);

    return true;
}

// Read Telegram update.
$body = file_get_contents('php://input');
$upd  = json_decode($body, true);

$msg = $upd['message'] ?? null;

if (!$msg) {
    exit;
}

$chat_id = $msg['chat']['id'] ?? null;
$text    = trim($msg['text'] ?? '');
$chat_type = $msg['chat']['type'] ?? '';

if (!$chat_id || !$text) {
    exit;
}

// Only accept private conversations.
if ($chat_type !== 'private') {
    exit;
}

// Handle /start with optional deep-link parameter.
// Example: /start 60123456789
if (preg_match('/^\/start(?:@\w+)?(?:\s+([A-Za-z0-9_-]+))?$/i', $text, $m)) {

    $payload = $m[1] ?? '';

    // No parameter: normal Start.
    if ($payload === '') {
        tg_reply(
            (int)$chat_id,
            "👋 Selamat datang!\n\n"
            . "Untuk menerima notifikasi lokasi jualan kami, "
            . "sila daftar nombor telefon melalui website terlebih dahulu.\n\n"
            . "Jika anda sudah mendaftar, gunakan link Telegram "
            . "yang diberikan selepas pendaftaran."
        );
        exit;
    }

    // Deep-link payload contains the phone number.
    if (register_subscriber($pdo, (int)$chat_id, $payload)) {
        tg_reply(
            (int)$chat_id,
            "✅ Pendaftaran berjaya!\n\n"
            . "Anda kini melanggan notifikasi lokasi jualan kami.\n\n"
            . "📍 Kami akan menghantar mesej apabila lokasi jualan dikemaskini.\n\n"
            . "Gunakan arahan berikut:\n"
            . "/lokasi - Semak lokasi terkini\n"
            . "/berhenti - Berhenti menerima notifikasi"
        );
    } else {
        tg_reply(
            (int)$chat_id,
            "❌ Nombor telefon tidak dijumpai.\n\n"
            . "Sila daftar nombor telefon di website kami terlebih dahulu."
        );
    }

    exit;
}

// Retain support for the old /daftar command.
if (preg_match('/^\/daftar(?:@\w+)?\s+(.+)$/i', $text, $m)) {
    $phone = $m[1];

    if (register_subscriber($pdo, (int)$chat_id, $phone)) {
        tg_reply(
            (int)$chat_id,
            "✅ Berjaya! Anda akan menerima notifikasi lokasi."
        );
    } else {
        tg_reply(
            (int)$chat_id,
            "❌ Nombor telefon tidak dijumpai. Sila daftar di laman web dahulu."
        );
    }

    exit;
}

// Handle unsubscribe command.
if (preg_match('/^\/berhenti(?:@\w+)?$/i', $text)) {

    $stmt = $pdo->prepare("
        UPDATE location_subscribers
        SET chat_id = NULL
        WHERE chat_id = ?
    ");

    $stmt->execute([$chat_id]);

    tg_reply(
        (int)$chat_id,
        "Anda telah berhenti menerima notifikasi lokasi.\n\n"
        . "Terima kasih kerana mengikuti kami."
    );

    exit;
}

// Handle current location request.
if (preg_match('/^\/lokasi(?:@\w+)?$/i', $text)) {

    $stmt = $pdo->prepare("
        SELECT chat_id
        FROM location_subscribers
        WHERE chat_id = ?
        LIMIT 1
    ");

    $stmt->execute([$chat_id]);

    if (!$stmt->fetchColumn()) {
        tg_reply(
            (int)$chat_id,
            "Anda belum melanggan notifikasi lokasi.\n\n"
            . "Sila daftar melalui website kami terlebih dahulu."
        );
        exit;
    }

    // Get latest location from settings.
    $stmt = $pdo->prepare("
        SELECT value
        FROM settings
        WHERE key = 'current_location'
        LIMIT 1
    ");

    $stmt->execute();
    $location = $stmt->fetchColumn();

    if ($location) {
        tg_reply(
            (int)$chat_id,
            "📍 Lokasi terkini:\n\n" . $location
        );
    } else {
        tg_reply(
            (int)$chat_id,
            "Maaf, lokasi jualan belum dikemaskini."
        );
    }

    exit;
}
