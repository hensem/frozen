<?php
require_once __DIR__ . '/../../config/config_frozen.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(404); exit; }

$pdo = new PDO('sqlite:' . __DIR__ . '/../../config/frozen.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->prepare('SELECT data, mime_type FROM images WHERE id=?');
$stmt->execute([$id]);
$img = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$img) { http_response_code(404); exit; }

header('Content-Type: ' . $img['mime_type']);
header('Cache-Control: max-age=86400');
echo $img['data'];
