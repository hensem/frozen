<?php
require 'auth_check.php';
require_approved();
require 'db.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

  if ($_POST['action'] === 'add') {
    $name = trim($_POST['name']);
    $qty  = (int)$_POST['quantity'];
    $buy  = (float)$_POST['buy_price'];
    $sell = (float)$_POST['sell_price'];
    if ($name && $qty > 0 && $buy > 0 && $sell > 0) {
      try {
        $pdo->prepare('INSERT INTO items (name,quantity,buy_price,sell_price,image_id) VALUES (?,?,?,?,1)')->execute([$name,$qty,$buy,$sell]);
        $id = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO restocks (item_id,quantity,buy_price,sell_price,restocked_at) VALUES (?,?,?,?,?)')->execute([$id,$qty,$buy,$sell,date('Y-m-d H:i:s')]);
        log_activity($pdo, 'add_item', "$name qty=$qty buy=$buy sell=$sell");
        $msg = '✅ Item added.';
      } catch (Exception $e) {
        $msg = '❌ Item name already exists.';
      }
    } else {
      $msg = '❌ All fields required and must be valid.';
    }

  } elseif ($_POST['action'] === 'update') {
    $id      = (int)$_POST['item_id'];
    $new_qty  = (int)$_POST['quantity'];
    $new_buy  = (float)$_POST['buy_price'];
    $new_sell = (float)$_POST['sell_price'];

    if ($id && $new_qty >= 0 && $new_buy > 0 && $new_sell > 0) {
      $stmt = $pdo->prepare('SELECT * FROM items WHERE id=?');
      $stmt->execute([$id]);
      $item = $stmt->fetch(PDO::FETCH_ASSOC);

      $changes = [];
      if ($new_qty  != $item['quantity'])  $changes[] = "qty={$item['quantity']}→$new_qty";
      if ($new_buy  != $item['buy_price']) $changes[] = "buy={$item['buy_price']}→$new_buy";
      if ($new_sell != $item['sell_price']) $changes[] = "sell={$item['sell_price']}→$new_sell";

      if ($changes) {
        $pdo->prepare('UPDATE items SET quantity=?,buy_price=?,sell_price=? WHERE id=?')->execute([$new_qty,$new_buy,$new_sell,$id]);
        log_activity($pdo, 'update_item', "{$item['name']} " . implode(', ', $changes));
        $msg = '✅ Updated.';
      } else {
        header('Location: items.php');
        exit;
      }
    }
  }
}

$items = $pdo->query('SELECT * FROM items ORDER BY CASE WHEN quantity=0 THEN 1 ELSE 0 END, name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Items — Frozen</title>
<link rel="stylesheet" href="/frozen/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Items</h1>
  <?php if ($msg): ?><div class="alert"><?= $msg ?></div><?php endif; ?>

  <h2>Add New Item</h2>
  <form method="post" class="form-grid">
    <input type="hidden" name="action" value="add">
    <label>Name<input type="text" name="name" required></label>
    <label>Quantity<input type="number" name="quantity" min="1" required></label>
    <label>Buy Price (RM)<input type="number" name="buy_price" step="0.01" min="0.01" required></label>
    <label>Sell Price (RM)<input type="number" name="sell_price" step="0.01" min="0.01" required></label>
    <button type="submit">Add Item</button>
  </form>

  <h2>Current Items</h2>

  <div style="margin-bottom:12px;display:flex;gap:8px">
    <a href="/frozen/pdf.php?lang=my" class="btn-secondary" style="text-decoration:none;">⬇️ Senarai Harga (BM)</a>
    <a href="/frozen/pdf.php?lang=en" class="btn-secondary" style="text-decoration:none;">⬇️ Stock List (EN)</a>
  </div>

  <div class="table-wrap">
  <table>
    <thead>
      <tr><th>Item</th><th>Stock</th><th>Buy (RM)</th><th>Sell (RM)</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): ?>
      <tr class="<?= $item['quantity'] == 0 ? 'row-warn' : '' ?>">
        <form method="post">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
        <td><?= htmlspecialchars($item['name']) ?></td>
        <td><input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="0" required style="width:70px"></td>
        <td><input type="number" name="buy_price" value="<?= $item['buy_price'] ?>" step="0.01" min="0.01" required style="width:80px"></td>
        <td><input type="number" name="sell_price" value="<?= $item['sell_price'] ?>" step="0.01" min="0.01" required style="width:80px"></td>
        <td><button type="submit">Update</button></td>
        </form>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
</body>
</html>
