<?php
require 'auth_check.php';
require_approved();
require 'db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: /frozen/items.php'); exit; }

$item = $pdo->prepare('SELECT id, name FROM items WHERE id=?');
$item->execute([$id]);
$item = $item->fetch(PDO::FETCH_ASSOC);
if (!$item) { header('Location: /frozen/items.php'); exit; }

$history = $pdo->prepare('SELECT ph.buy_price, ph.sell_price, ph.changed_at, u.name AS changed_by FROM item_price_history ph LEFT JOIN users u ON u.id = ph.changed_by WHERE ph.item_id=? ORDER BY ph.changed_at DESC');
$history->execute([$id]);
$history = $history->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Price History — <?= htmlspecialchars($item['name']) ?></title>
<link rel="stylesheet" href="/frozen/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <p><a href="/frozen/items.php">&larr; Back to Items</a></p>
  <h1>Price History</h1>
  <h2 style="margin-top:0;color:#475569;"><?= htmlspecialchars($item['name']) ?></h2>

  <?php if (empty($history)): ?>
    <p class="muted">No price history recorded yet.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead>
      <tr><th>Date &amp; Time</th><th>Buy Price (RM)</th><th>Sell Price (RM)</th><th>Changed By</th></tr>
    </thead>
    <tbody>
    <?php foreach ($history as $r): ?>
      <tr>
        <td><?= $r['changed_at'] ?></td>
        <td><?= number_format($r['buy_price'], 2) ?></td>
        <td><?= number_format($r['sell_price'], 2) ?></td>
        <td><?= $r['changed_by'] ? htmlspecialchars($r['changed_by']) : '<span class="muted">—</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
