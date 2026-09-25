<?php
require 'auth_check.php';
require_approved();
require 'db.php';
require __DIR__ . '/../../library/vendor/autoload.php';

$lang  = $_GET['lang'] ?? 'my';
$items = $pdo->query('SELECT name, sell_price, quantity FROM items WHERE quantity > 0 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

$title = $lang === 'my' ? 'Senarai Harga' : 'Stock List';
$col2  = $lang === 'my' ? 'Harga' : 'Stock';

// For stock list, calculate explicit column widths based on longest text
$col1w = $col2w = '';
if ($lang === 'en') {
  $maxName = max(array_map(fn($i) => mb_strlen($i['name']), $items));
  $maxQty  = max(array_map(fn($i) => mb_strlen((string)$i['quantity']), $items));
  // ~2.2px per char at 12px Arial, add 20px padding
  $col1w = 'width="' . ($maxName * 7 + 20) . '"';
  $col2w = 'width="' . ($maxQty  * 7 + 20) . '"';
}

$rows = '';
foreach ($items as $i) {
  $col2val = $lang === 'my' ? 'RM ' . number_format($i['sell_price'], 2) : $i['quantity'];
  if ($lang === 'en') {
    $rows .= '<tr><td>' . htmlspecialchars($i['name']) . '</td><td>' . $col2val . '</td><td></td></tr>';
  } else {
    $rows .= '<tr><td>' . htmlspecialchars($i['name']) . '</td><td>' . $col2val . '</td></tr>';
  }
}

$thead = $lang === 'en'
  ? "<tr><th $col1w>Item</th><th $col2w>$col2</th><th></th></tr>"
  : '<tr><th>Item</th><th>' . $col2 . '</th></tr>';

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
  body { font-family: Arial, sans-serif; font-size: 12px; }
  h2 { margin: 0 0 12px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #333; padding: 6px 10px; text-align: left; }
  th { background: #f0f0f0; }
</style>
$html
");
$mpdf->Output("$title.pdf", 'D');
