<?php
require 'auth_check.php';
require_approved();
require 'db.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

  if ($_POST['action'] === 'add') {
    $name    = trim($_POST['name']);
    $qty     = (int)$_POST['quantity'];
    $buy     = (float)$_POST['buy_price'];
    $sell    = (float)$_POST['sell_price'];
    $is_temp = isset($_POST['is_temp']) ? 1 : 0;
    if ($name && $qty > 0 && $buy > 0 && $sell > 0) {
      try {
        $now = date('Y-m-d H:i:s');
        $pdo->prepare('INSERT INTO items (name,quantity,buy_price,sell_price,image_id,is_temp,created_at,updated_at) VALUES (?,?,?,?,1,?,?,?)')->execute([$name,$qty,$buy,$sell,$is_temp,$now,$now]);
        $id = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO restocks (item_id,quantity,buy_price,sell_price,restocked_at) VALUES (?,?,?,?,?)')->execute([$id,$qty,$buy,$sell,$now]);
        $pdo->prepare('INSERT INTO item_price_history (item_id,buy_price,sell_price,changed_at,changed_by) VALUES (?,?,?,?,?)')->execute([$id,$buy,$sell,$now,$frozen_user['id']]);
        log_activity($pdo, 'add_item', "$name qty=$qty buy=$buy sell=$sell is_temp=$is_temp");
        $msg = '✅ Item added.';
      } catch (Exception $e) {
        $msg = '❌ Item name already exists.';
      }
    } else {
      $msg = '❌ All fields required and must be valid.';
    }

  } elseif ($_POST['action'] === 'update') {
    $id       = (int)$_POST['item_id'];
    $new_qty  = (int)$_POST['quantity'];
    $new_buy  = (float)$_POST['buy_price'];
    $new_sell = (float)$_POST['sell_price'];

    if ($id && $new_qty >= 0 && $new_buy > 0 && $new_sell > 0) {
      $stmt = $pdo->prepare('SELECT * FROM items WHERE id=?');
      $stmt->execute([$id]);
      $item = $stmt->fetch(PDO::FETCH_ASSOC);

      $changes = [];
      if ($new_qty  != $item['quantity'])   $changes[] = "qty={$item['quantity']}→$new_qty";
      if ($new_buy  != $item['buy_price'])  $changes[] = "buy={$item['buy_price']}→$new_buy";
      if ($new_sell != $item['sell_price']) $changes[] = "sell={$item['sell_price']}→$new_sell";

      if ($changes) {
        $now = date('Y-m-d H:i:s');
        $pdo->prepare('UPDATE items SET quantity=?,buy_price=?,sell_price=?,updated_at=? WHERE id=?')->execute([$new_qty,$new_buy,$new_sell,$now,$id]);
        if ($new_buy != $item['buy_price'] || $new_sell != $item['sell_price']) {
          $pdo->prepare('INSERT INTO item_price_history (item_id,buy_price,sell_price,changed_at,changed_by) VALUES (?,?,?,?,?)')->execute([$id,$new_buy,$new_sell,$now,$frozen_user['id']]);
        }
        log_activity($pdo, 'update_item', "{$item['name']} " . implode(', ', $changes));
        $msg = '✅ Updated.';
      } else {
        header('Location: items.php');
        exit;
      }
    }

  } elseif ($_POST['action'] === 'merge') {
    $temp_id   = (int)$_POST['temp_id'];
    $target_id = (int)$_POST['target_id'];
    if ($temp_id && $target_id) {
      $temp   = $pdo->prepare('SELECT * FROM items WHERE id=? AND is_temp=1'); $temp->execute([$temp_id]);   $temp   = $temp->fetch(PDO::FETCH_ASSOC);
      $target = $pdo->prepare('SELECT * FROM items WHERE id=? AND is_temp=0'); $target->execute([$target_id]); $target = $target->fetch(PDO::FETCH_ASSOC);
      if ($temp && $target && $target['quantity'] == 0) {
        $now = date('Y-m-d H:i:s');
        $pdo->prepare('UPDATE items SET quantity=quantity+?,buy_price=?,sell_price=?,updated_at=? WHERE id=?')
            ->execute([$temp['quantity'],$temp['buy_price'],$temp['sell_price'],$now,$target_id]);
        $pdo->prepare('INSERT INTO restocks (item_id,quantity,buy_price,sell_price,restocked_at) VALUES (?,?,?,?,?)')
            ->execute([$target_id,$temp['quantity'],$temp['buy_price'],$temp['sell_price'],$now]);
        $pdo->prepare('INSERT INTO item_price_history (item_id,buy_price,sell_price,changed_at,changed_by) VALUES (?,?,?,?,?)')
            ->execute([$target_id,$temp['buy_price'],$temp['sell_price'],$now,$frozen_user['id']]);
        $pdo->prepare('DELETE FROM items WHERE id=?')->execute([$temp_id]);
        log_activity($pdo, 'merge_item', "Merged {$temp['name']} into {$target['name']} qty={$temp['quantity']} buy={$temp['buy_price']} sell={$temp['sell_price']}");
        $msg = '✅ Merged ' . htmlspecialchars($temp['name']) . ' into ' . htmlspecialchars($target['name']) . '.';
      } else {
        $msg = '❌ Invalid merge. Target item must have 0 stock.';
      }
    }
  }
}

$items       = $pdo->query('SELECT * FROM items ORDER BY is_temp ASC, CASE WHEN quantity=0 THEN 1 ELSE 0 END, name')->fetchAll(PDO::FETCH_ASSOC);
$zero_items  = array_filter($items, fn($i) => $i['quantity'] == 0 && !$i['is_temp']);
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
    <label style="flex-direction:row;align-items:center;gap:8px;"><input type="checkbox" name="is_temp" value="1"> Temporary item</label>
    <button type="submit">Add Item</button>
  </form>

  <h2>Current Items</h2>

  <div style="margin-bottom:12px;display:flex;gap:8px">
    <a href="/frozen/pdf.php?lang=my" style="text-decoration:none;">⬇️ Senarai Harga (BM)</a>
    <a href="/frozen/pdf.php?lang=en" style="text-decoration:none;">⬇️ Stock List (EN)</a>
  </div>

  <div class="table-wrap">
  <table>
    <thead>
      <tr><th>Item</th><th>Stock</th><th>Buy (RM)</th><th>Sell (RM)</th><th></th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): ?>
      <tr class="<?= $item['quantity'] == 0 ? 'row-warn' : '' ?>">
        <form method="post">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
        <td>
          <?= htmlspecialchars($item['name']) ?>
          <?php if ($item['is_temp']): ?><span class="badge badge-yellow" style="font-size:.72rem;margin-left:4px;">Temp</span><?php endif; ?>
        </td>
        <td><input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="0" required style="width:70px"></td>
        <td><input type="number" name="buy_price" value="<?= $item['buy_price'] ?>" step="0.01" min="0.01" required style="width:80px"></td>
        <td><input type="number" name="sell_price" value="<?= $item['sell_price'] ?>" step="0.01" min="0.01" required style="width:80px"></td>
        <td><button type="submit">Update</button></td>
        </form>
        <td style="display:flex;gap:6px;align-items:center;">
          <a href="/frozen/price_history.php?id=<?= $item['id'] ?>" style="text-decoration:none;font-size:.85rem;padding:5px 10px;background:#f1f5f9;color:#1e293b;border:1px solid #cbd5e1;border-radius:5px;display:inline-block;">Price History</a>
          <?php if ($item['is_temp'] && !empty($zero_items)): ?>
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="merge">
            <input type="hidden" name="temp_id" value="<?= $item['id'] ?>">
            <select name="target_id" required style="padding:4px 6px;border:1px solid #cbd5e1;border-radius:5px;font-size:.85rem;">
              <option value="">— Merge into —</option>
              <?php foreach ($zero_items as $zi): ?>
              <option value="<?= $zi['id'] ?>"><?= htmlspecialchars($zi['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="submit" onclick="return confirm('Merge <?= htmlspecialchars(addslashes($item['name'])) ?> into selected item?')">Merge</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
</body>
</html>
