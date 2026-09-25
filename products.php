<?php
require 'auth_check.php';
require_approved();
require 'db.php';

$msg = '';
$msg_type = 'alert';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_csv') {
  $file = $_FILES['csv'] ?? null;
  if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $msg = '❌ Upload failed.';
  } else {
    $handle = fopen($file['tmp_name'], 'r');
    $header = fgetcsv($handle); // skip header row

    $added = 0; $updated = 0; $ignored = 0;
    $now = date('Y-m-d H:i:s');
    $user_id = $frozen_user['id'];

    $find = $pdo->prepare('SELECT * FROM products WHERE name=?');
    $insert = $pdo->prepare('INSERT INTO products (name,pcs,wholesale_price,retail_price,created_at,created_by) VALUES (?,?,?,?,?,?)');
    $update = $pdo->prepare('UPDATE products SET pcs=?,wholesale_price=?,retail_price=?,updated_at=?,updated_by=? WHERE name=?');

    while (($row = fgetcsv($handle)) !== false) {
      if (count($row) < 3) continue;
      $name  = trim($row[0]);
      $pcs   = (int)trim($row[1]);
      $buy   = round((float)trim($row[2]), 2);
      $sell  = isset($row[3]) && trim($row[3]) !== '' ? round((float)trim($row[3]), 2) : null;

      if (!$name) continue;

      $find->execute([$name]);
      $existing = $find->fetch(PDO::FETCH_ASSOC);

      if (!$existing) {
        $insert->execute([$name, $pcs, $buy, $sell, $now, $user_id]);
        $added++;
      } else {
        if ($existing['pcs'] != $pcs || $existing['wholesale_price'] != $buy || $existing['retail_price'] != $sell) {
          $update->execute([$pcs, $buy, $sell, $now, $user_id, $name]);
          $updated++;
        } else {
          $ignored++;
        }
      }
    }
    fclose($handle);
    log_activity($pdo, 'upload_products_csv', "added=$added updated=$updated ignored=$ignored");
    $msg = "✅ Done — Added: $added, Updated: $updated, Ignored (no change): $ignored";
  }
}

$products = $pdo->query('SELECT * FROM products ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Products — Frozen</title>
<link rel="stylesheet" href="/frozen/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Products</h1>
  <?php if ($msg): ?><div class="alert"><?= $msg ?></div><?php endif; ?>

  <div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;">
    <label style="font-size:.85rem;font-weight:500;color:#475569;">
      Search
      <input type="text" id="prod-search" placeholder="Type to filter..." oninput="filterProducts()"
        style="display:block;margin-top:4px;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:.95rem;width:260px;">
    </label>
    <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
      <input type="hidden" name="action" value="upload_csv">
      <label style="font-size:.85rem;font-weight:500;color:#475569;">
        Update via CSV
        <input type="file" name="csv" accept=".csv" required style="display:block;margin-top:4px;">
      </label>
      <button type="submit">Upload</button>
    </form>
  </div>

  <p class="muted" style="margin-bottom:8px;">
    CSV format: <code>name,pcs,wholesale_price,retail_price</code> — first row is header, retail_price can be empty.
  </p>

  <div class="table-wrap">
  <table id="prod-table">
    <thead>
      <tr>
        <th>#</th>
        <th>Name</th>
        <th>Pcs</th>
        <th>Wholesale (RM)</th>
        <th>Retail (RM)</th>
        <th>Created</th>
        <th>Updated</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($products as $i => $p): ?>
      <tr data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>">
        <td><?= $i + 1 ?></td>
        <td><?= htmlspecialchars($p['name']) ?></td>
        <td><?= $p['pcs'] ?></td>
        <td><?= number_format($p['wholesale_price'], 2) ?></td>
        <td><?= $p['retail_price'] !== null ? number_format($p['retail_price'], 2) : '<span class="muted">—</span>' ?></td>
        <td><?= $p['created_at'] ?? '—' ?></td>
        <td><?= $p['updated_at'] ?? '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<script>
function filterProducts() {
  const q = document.getElementById('prod-search').value.toLowerCase();
  document.querySelectorAll('#prod-table tbody tr').forEach(row => {
    row.style.display = row.dataset.name.includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>
