<?php
require 'auth_check.php';
require_approved();
require 'db.php';
require __DIR__ . '/../../library/vendor/autoload.php';

$lang  = $_GET['lang'] ?? 'my';
$items = $pdo->query('SELECT items.name, items.sell_price, items.quantity, items.image_id, images.data, images.mime_type FROM items LEFT JOIN images ON images.id = items.image_id WHERE items.quantity > 0 AND items.is_temp = 0 ORDER BY items.name')->fetchAll(PDO::FETCH_ASSOC);

$title = $lang === 'my' ? 'Senarai Harga' : 'Stock List';
$col2  = $lang === 'my' ? 'Harga' : 'Stock';

// Page content area: A4 210x297mm, margins 15mm each = 180x267mm
$contentH = 267; // mm
$headH    = 18;  // approx h2 + thead height in mm
$count    = count($items);
$rowH     = $count > 0 ? floor(($contentH - $headH) / $count) : 20;
$rowH     = max(8, min($rowH, 40)); // clamp between 8mm and 40mm
$imgSize  = $rowH - 2; // slight padding, in mm

// For stock list, calculate explicit column widths based on longest text
$col1w = $col2w = '';
if ($lang === 'en') {
  $maxName = max(array_map(fn($i) => mb_strlen($i['name']), $items));
  $maxQty  = max(array_map(fn($i) => mb_strlen((string)$i['quantity']), $items));
  $col1w = 'width="' . ($maxName * 7 + 20) . '"';
  $col2w = 'width="' . ($maxQty  * 7 + 20) . '"';
}

// Font size: scale down slightly if rows are small
$fontSize = $rowH >= 14 ? 12 : ($rowH >= 10 ? 10 : 8);

$rows = '';
foreach ($items as $i) {
  $col2val = $lang === 'my' ? 'RM ' . number_format($i['sell_price'], 2) : $i['quantity'];
  if ($lang === 'en') {
    $rows .= '<tr style="height:' . $rowH . 'mm"><td>' . htmlspecialchars($i['name']) . '</td><td>' . $col2val . '</td><td></td></tr>';
  } else {
    $imgTag  = '';
    if ($i['data']) {
      $b64    = base64_encode($i['data']);
      $imgTag = '<img src="data:' . $i['mime_type'] . ';base64,' . $b64 . '" style="width:' . $imgSize . 'mm;height:' . $imgSize . 'mm;object-fit:cover;vertical-align:middle;margin-right:24px;">';
    }
    $rows .= '<tr style="height:' . $rowH . 'mm"><td style="vertical-align:middle;">' . $imgTag . htmlspecialchars($i['name']) . '</td><td>' . $col2val . '</td></tr>';
  }
}

if ($lang === 'en') {
  $thead = "<tr><th $col1w>Item</th><th $col2w>$col2</th><th></th></tr>";
} else {
  $thead = '<tr><th>Item</th><th>' . $col2 . '</th></tr>';
}

$html = "
<h2>$title</h2>
<table>
  <thead>$thead</thead>
  <tbody>$rows</tbody>
</table>
";

$mpdf = new \Mpdf\Mpdf(['margin_top' => 15, 'margin_bottom' => 15, 'margin_left' => 15, 'margin_right' => 15]);
$mpdf->SetTitle($title);
$mpdf->WriteHTML("
<style>
  body { font-family: Arial, sans-serif; font-size: {$fontSize}px; }
  h2 { margin: 0 0 6px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #333; padding: 4px 8px; text-align: left; vertical-align: middle; }
  th { background: #f0f0f0; }
</style>
$html
");
if ($lang === 'my') {
  $mpdf->AddPage();
  $mpdf->Image(__DIR__ . '/images/QR.png', 60, 78.1, 90, 90);
}

$mpdf->Output("$title.pdf", 'D');
