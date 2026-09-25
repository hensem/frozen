<?php
require 'auth_check.php';
require_approved();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
  $allowed = ['pending', 'completed', 'cancelled'];
  if (in_array($_POST['status'], $allowed)) {
    $oid = (int)$_POST['order_id'];
    $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$_POST['status'], $oid]);
    $pdo->prepare('INSERT INTO activity_log (user_id,action,detail,created_at) VALUES (?,?,?,?)')
        ->execute([$frozen_user['id'], 'order_'.$_POST['status'], 'Order #'.$oid, date('Y-m-d H:i:s')]);
  }
  header('Location: /frozen/orders.php');
  exit;
}

$filter = $_GET['status'] ?? 'pending';
$allowed_filters = ['pending', 'completed', 'cancelled', 'all'];
if (!in_array($filter, $allowed_filters)) $filter = 'pending';

$where = $filter === 'all' ? '' : 'WHERE status=?';
$params = $filter === 'all' ? [] : [$filter];
$orders = $pdo->prepare("SELECT * FROM orders $where ORDER BY ordered_at DESC");
$orders->execute($params);
$orders = $orders->fetchAll(PDO::FETCH_ASSOC);

$counts = $pdo->query('SELECT status, COUNT(*) as c FROM orders GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Orders — Frozen</title>
<link rel="stylesheet" href="/frozen/style.css">
<style>
.status-pending   { background:#fef9c3;color:#854d0e;padding:2px 8px;border-radius:12px;font-size:.8rem;font-weight:600; }
.status-completed { background:#dcfce7;color:#166534;padding:2px 8px;border-radius:12px;font-size:.8rem;font-weight:600; }
.status-cancelled { background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:12px;font-size:.8rem;font-weight:600; }
.order-card { background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:16px; }
.order-card h3 { margin:0 0 8px;font-size:1rem;display:flex;align-items:center;gap:8px; }
.order-meta { font-size:.85rem;color:#475569;margin-bottom:10px;line-height:1.7; }
.order-items { width:100%;border-collapse:collapse;font-size:.88rem;margin-bottom:10px; }
.order-items th { background:#1e293b;color:#fff;padding:5px 10px;text-align:left; }
.order-items td { border:1px solid #e2e8f0;padding:5px 10px; }
.order-actions { display:flex;gap:8px;flex-wrap:wrap; }
.filter-tabs { display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap; }
.filter-tabs a { padding:6px 14px;border-radius:20px;font-size:.85rem;text-decoration:none;background:#f1f5f9;color:#475569; }
.filter-tabs a.active { background:#1e293b;color:#fff; }
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Orders</h1>

  <div class="filter-tabs">
    <a href="?status=pending" class="<?= $filter==='pending'?'active':'' ?>">
      Pending <?php if (!empty($counts['pending'])): ?><span class="badge badge-red"><?= $counts['pending'] ?></span><?php endif; ?>
    </a>
    <a href="?status=completed" class="<?= $filter==='completed'?'active':'' ?>">Completed</a>
    <a href="?status=cancelled" class="<?= $filter==='cancelled'?'active':'' ?>">Cancelled</a>
    <a href="?status=all" class="<?= $filter==='all'?'active':'' ?>">All</a>
  </div>

  <?php if (empty($orders)): ?>
    <p class="muted">No <?= $filter === 'all' ? '' : $filter ?> orders.</p>
  <?php endif; ?>

  <?php foreach ($orders as $o):
    $lines = json_decode($o['items'], true);
  ?>
  <div class="order-card">
    <h3>
      <?= htmlspecialchars($o['name']) ?>
      <span class="status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span>
    </h3>
    <div class="order-meta">
      📞 <?= htmlspecialchars($o['phone']) ?><br>
      📍 <?= nl2br(htmlspecialchars($o['address'])) ?><br>
      <?php if ($o['email']): ?>✉️ <?= htmlspecialchars($o['email']) ?><br><?php endif; ?>
      🕐 <?= $o['ordered_at'] ?>
    </div>
    <table class="order-items">
      <thead><tr><th>Produk</th><th>Qty</th><th>Harga</th><th>Jumlah</th></tr></thead>
      <tbody>
      <?php foreach ($lines as $l): ?>
        <tr>
          <td><?= htmlspecialchars($l['name']) ?></td>
          <td style="text-align:center"><?= $l['qty'] ?></td>
          <td style="text-align:right">RM <?= number_format($l['price'],2) ?></td>
          <td style="text-align:right">RM <?= number_format($l['subtotal'],2) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p style="font-weight:600;margin-bottom:10px;">Jumlah: RM <?= number_format($o['total'],2) ?></p>

    <?php if ($o['status'] === 'pending'): ?>
    <div class="order-actions">
      <form method="post">
        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
        <input type="hidden" name="status" value="completed">
        <button type="submit" style="background:#16a34a;color:#fff;">✓ Completed</button>
      </form>
      <form method="post">
        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
        <input type="hidden" name="status" value="cancelled">
        <button type="submit" style="background:#dc2626;color:#fff;">✕ Cancel</button>
      </form>
    </div>
    <?php elseif ($o['status'] === 'completed' || $o['status'] === 'cancelled'): ?>
    <div class="order-actions">
      <form method="post">
        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
        <input type="hidden" name="status" value="pending">
        <button type="submit">↩ Set Pending</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
</body>
</html>
