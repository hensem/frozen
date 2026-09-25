<?php
require 'auth_check.php';
require_approved();
require 'db.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if ($_POST['action'] === 'set_location') {
    $loc = trim($_POST['location']);
    if ($loc) {
      $pdo->prepare('INSERT OR REPLACE INTO settings (key,value) VALUES ("location",?)')->execute([$loc]);
      $pdo->prepare('UPDATE locations SET last_used=? WHERE name=?')->execute([date('Y-m-d H:i:s'), $loc]);
      log_activity($pdo, 'update_location', $loc);
      $msg = '✅ Location updated.';
    }
  } elseif ($_POST['action'] === 'add_location') {
    $loc = trim($_POST['new_location']);
    $map_url = trim($_POST['map_url'] ?? '');
    if ($loc) {
      try {
        $pdo->prepare('INSERT INTO locations (name,map_url) VALUES (?,?)')->execute([$loc, $map_url ?: null]);
        $msg = '✅ Location added.';
      } catch (Exception $e) {
        $msg = '❌ Location already exists.';
      }
    }
  }
}

$current_location = $pdo->query('SELECT value FROM settings WHERE key="location"')->fetchColumn() ?: 'Tak meniaga sekarang';
$locations = $pdo->query('SELECT name, map_url FROM locations ORDER BY CASE WHEN name="Tak meniaga sekarang" THEN 0 ELSE 1 END DESC, last_used DESC, name')->fetchAll(PDO::FETCH_ASSOC);

$summary = $pdo->query('
  SELECT i.name,
    i.quantity,
    i.buy_price,
    i.sell_price,
    COALESCE(SUM(s.quantity_sold),0) AS total_sold,
    COALESCE(SUM(s.quantity_sold * (s.sell_price - s.buy_price)),0) AS profit,
    COALESCE(SUM(s.quantity_sold * s.sell_price),0) AS revenue
  FROM items i
  LEFT JOIN sales s ON s.item_id = i.id
  GROUP BY i.id
  ORDER BY i.name
')->fetchAll(PDO::FETCH_ASSOC);

$totals = $pdo->query('
  SELECT COALESCE(SUM(quantity_sold * sell_price),0) AS revenue,
         COALESCE(SUM(quantity_sold * (sell_price - buy_price)),0) AS profit
  FROM sales
')->fetch(PDO::FETCH_ASSOC);

$out_of_stock = array_filter($summary, fn($r) => $r['quantity'] == 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — Frozen</title>
<link rel="stylesheet" href="/frozen/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Dashboard</h1>
  <?php if ($msg): ?><div class="alert"><?= $msg ?></div><?php endif; ?>

  <h2>Location</h2>
  <div class="form-grid" style="margin-bottom:8px;">
    <form method="post" style="display:contents;">
      <input type="hidden" name="action" value="set_location">
      <label>Current Location
        <select name="location">
          <?php foreach ($locations as $loc): ?>
          <option value="<?= htmlspecialchars($loc['name']) ?>" <?= $loc['name'] === $current_location ? 'selected' : '' ?>><?= htmlspecialchars($loc['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit">Update</button>
    </form>
  </div>
  <form method="post" class="form-grid" style="margin-bottom:8px;">
    <input type="hidden" name="action" value="add_location">
    <label>Add New Location<input type="text" name="new_location" placeholder="e.g. Pasar Malam Taman X"></label>
    <label>Google Map URL<input type="url" name="map_url" placeholder="https://maps.app.goo.gl/..."></label>
    <button type="submit">Add</button>
  </form>

  <div class="cards">
    <div class="card">
      <div class="card-label">Total Revenue</div>
      <div class="card-value">RM <?= number_format($totals['revenue'],2) ?></div>
    </div>
    <div class="card">
      <div class="card-label">Total Profit</div>
      <div class="card-value">RM <?= number_format($totals['profit'],2) ?></div>
    </div>
    <div class="card <?= count($out_of_stock) ? 'card-warn' : '' ?>">
      <div class="card-label">Out of Stock</div>
      <div class="card-value"><?= count($out_of_stock) ?> item<?= count($out_of_stock) != 1 ? 's' : '' ?></div>
    </div>
  </div>

  <?php if ($out_of_stock): ?>
  <div class="alert">
    ⚠️ Out of stock: <?= implode(', ', array_column($out_of_stock, 'name')) ?>
  </div>
  <?php endif; ?>

  <h2>Profit per Item</h2>
  <div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Item</th><th>In Stock</th><th>Buy (RM)</th><th>Sell (RM)</th>
        <th>Units Sold</th><th>Revenue (RM)</th><th>Profit (RM)</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($summary as $r): ?>
      <tr class="<?= $r['quantity'] == 0 ? 'row-warn' : '' ?>">
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= $r['quantity'] ?></td>
        <td><?= number_format($r['buy_price'],2) ?></td>
        <td><?= number_format($r['sell_price'],2) ?></td>
        <td><?= $r['total_sold'] ?></td>
        <td><?= number_format($r['revenue'],2) ?></td>
        <td><?= number_format($r['profit'],2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
</body>
</html>
