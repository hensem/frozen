<?php
require 'auth_check.php';
require_approved();
require 'db.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $item_id = (int)$_POST['item_id'];
  $qty     = (int)$_POST['quantity'];

  if ($item_id && $qty > 0) {
    $stmt = $pdo->prepare('SELECT * FROM items WHERE id=?');
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
      $msg = '❌ Item not found.';
    } elseif ($item['quantity'] < $qty) {
      $msg = '❌ Not enough stock. Available: ' . $item['quantity'];
    } else {
      $now = date('Y-m-d H:i:s');
      $pdo->prepare('INSERT INTO sales (item_id,quantity_sold,buy_price,sell_price,sold_at) VALUES (?,?,?,?,?)')
          ->execute([$item_id, $qty, $item['buy_price'], $item['sell_price'], $now]);
      $pdo->prepare('UPDATE items SET quantity = quantity - ? WHERE id=?')->execute([$qty, $item_id]);
      $profit = $qty * ($item['sell_price'] - $item['buy_price']);
      log_activity($pdo, 'sell', "{$item['name']} qty=$qty profit=" . number_format($profit,2));
      $msg = '✅ Sold ' . $qty . 'x ' . htmlspecialchars($item['name']) . ' — Profit: RM ' . number_format($profit,2);
    }
  } else {
    $msg = '❌ Please select an item and enter a valid quantity.';
  }
}

$items = $pdo->query('SELECT * FROM items WHERE quantity > 0 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sell — Frozen</title>
<link rel="stylesheet" href="/frozen/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Record Sale</h1>
  <?php if ($msg): ?><div class="alert"><?= $msg ?></div><?php endif; ?>

  <?php if (empty($items)): ?>
    <p class="muted">No items in stock. <a href="/frozen/items.php">Restock here</a>.</p>
  <?php else: ?>
  <form method="post" class="form-grid">
    <label>Item
      <select name="item_id" id="item_sel" required>
        <option value="">— select —</option>
        <?php foreach ($items as $item): ?>
        <option value="<?= $item['id'] ?>" data-stock="<?= $item['quantity'] ?>" data-buy="<?= $item['buy_price'] ?>" data-sell="<?= $item['sell_price'] ?>">
          <?= htmlspecialchars($item['name']) ?> (stock: <?= $item['quantity'] ?>)
        </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Quantity<input type="number" name="quantity" id="qty" min="1" required></label>
    <div id="preview" class="preview hidden"></div>
    <button type="submit">Confirm Sale</button>
  </form>
  <?php endif; ?>
</div>
<script>
const sel = document.getElementById('item_sel');
const qty = document.getElementById('qty');
const preview = document.getElementById('preview');
function updatePreview() {
  const opt = sel.options[sel.selectedIndex];
  const q = parseInt(qty.value) || 0;
  if (!opt.value || !q) { preview.classList.add('hidden'); return; }
  const buy = parseFloat(opt.dataset.buy), sell = parseFloat(opt.dataset.sell);
  preview.classList.remove('hidden');
  preview.innerHTML = `Sell @ RM ${sell.toFixed(2)} &nbsp;|&nbsp; Profit: <strong>RM ${((sell-buy)*q).toFixed(2)}</strong>`;
}
sel.addEventListener('change', updatePreview);
qty.addEventListener('input', updatePreview);
</script>
</body>
</html>
