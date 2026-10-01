<?php
require 'auth_check.php';
require_approved();
require 'db.php';
require_once __DIR__ . '/telegram_notify.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if ($_POST['action'] === 'set_location') {
    $loc = trim($_POST['location']);
    if ($loc) {
      $pdo->prepare('INSERT OR REPLACE INTO settings (key,value) VALUES ("location",?)')->execute([$loc]);
      $pdo->prepare('UPDATE locations SET last_used=? WHERE name=?')->execute([date('Y-m-d H:i:s'), $loc]);
      log_activity($pdo, 'update_location', $loc);
      tg_notify_location($pdo, $loc);
      $msg = '✅ Location updated.';
    }
  } elseif ($_POST['action'] === 'add_location') {
    $loc     = trim($_POST['new_location'] ?? '');
    $map_url = trim($_POST['map_url'] ?? '');
    if ($loc) {
      try {
        $pdo->prepare('INSERT INTO locations (name,map_url) VALUES (?,?)')->execute([$loc, $map_url ?: null]);
        $msg = '✅ Location added.';
      } catch (Exception $e) {
        $msg = '❌ Location already exists.';
      }
    }
  } elseif ($_POST['action'] === 'edit_location') {
    $id      = (int)($_POST['loc_id'] ?? 0);
    $name    = trim($_POST['name'] ?? '');
    $map_url = trim($_POST['map_url'] ?? '');
    if ($id && $name) {
      try {
        $old = $pdo->prepare('SELECT name FROM locations WHERE id=?');
        $old->execute([$id]);
        $old_name = $old->fetchColumn();
        $pdo->prepare('UPDATE locations SET name=?,map_url=? WHERE id=?')->execute([$name, $map_url ?: null, $id]);
        if ($old_name === ($pdo->query('SELECT value FROM settings WHERE key="location"')->fetchColumn())) {
          $pdo->prepare('INSERT OR REPLACE INTO settings (key,value) VALUES ("location",?)')->execute([$name]);
        }
        log_activity($pdo, 'edit_location', "id=$id $old_name→$name");
        $msg = '✅ Location updated.';
      } catch (Exception $e) {
        $msg = '❌ Name already exists.';
      }
    }
  } elseif ($_POST['action'] === 'delete_location') {
    $id = (int)($_POST['loc_id'] ?? 0);
    if ($id) {
      $loc_name = $pdo->prepare('SELECT name FROM locations WHERE id=?');
      $loc_name->execute([$id]);
      $loc_name = $loc_name->fetchColumn();
      $active   = $pdo->query('SELECT value FROM settings WHERE key="location"')->fetchColumn();
      if ($loc_name === $active) {
        $msg = '❌ Cannot delete the current active location.';
      } else {
        $pdo->prepare('DELETE FROM locations WHERE id=?')->execute([$id]);
        log_activity($pdo, 'delete_location', "id=$id $loc_name");
        $msg = '✅ Location deleted.';
      }
    }
  }
}

$current_location = $pdo->query('SELECT value FROM settings WHERE key="location"')->fetchColumn() ?: 'Tak meniaga sekarang';
$locations = $pdo->query('SELECT id, name, map_url FROM locations ORDER BY last_used DESC NULLS LAST, name')->fetchAll(PDO::FETCH_ASSOC);

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

  <div style="margin-bottom:12px;">
    <label style="font-weight:600;display:block;margin-bottom:6px;">Edit / Delete Location</label>
    <div style="position:relative;max-width:320px;margin-bottom:10px;">
      <input type="text" id="loc-search" placeholder="Search location..." autocomplete="off"
        style="width:100%;box-sizing:border-box;padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:.95rem;"
        oninput="filterLoc()" onfocus="filterLoc()" onblur="setTimeout(()=>document.getElementById('loc-drop').style.display='none',150)">
      <div id="loc-drop" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #cbd5e1;border-radius:6px;max-height:200px;overflow-y:auto;z-index:50;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
    </div>
    <div id="loc-edit" style="display:none;">
      <form method="post" class="form-grid" style="margin-bottom:6px;">
        <input type="hidden" name="action" value="edit_location">
        <input type="hidden" name="loc_id" id="loc-edit-id">
        <label>Name<input type="text" name="name" id="loc-edit-name" required></label>
        <label>Map URL<input type="url" name="map_url" id="loc-edit-map" placeholder="https://maps.app.goo.gl/..."></label>
        <button type="submit">Save</button>
      </form>
      <form method="post" style="margin-top:4px;">
        <input type="hidden" name="action" value="delete_location">
        <input type="hidden" name="loc_id" id="loc-del-id">
        <button type="submit" class="btn-danger" id="loc-del-btn" onclick="return confirm('Delete this location?')">Delete</button>
      </form>
    </div>
  </div>

  <form method="post" class="form-grid" style="margin-bottom:8px;">
    <input type="hidden" name="action" value="add_location">
    <label>Add New Location<input type="text" name="new_location" placeholder="e.g. Pasar Malam Taman X"></label>
    <label>Google Map URL<input type="url" name="map_url" placeholder="https://maps.app.goo.gl/..."></label>
    <button type="submit">Add</button>
  </form>

  <script>
  const activeLoc = <?= json_encode($current_location) ?>;
  const locsData  = <?= json_encode(array_values($locations)) ?>;

  function filterLoc() {
    const q    = document.getElementById('loc-search').value.toLowerCase();
    const drop = document.getElementById('loc-drop');
    const matches = locsData.filter(l => l.name.toLowerCase().includes(q));
    if (!matches.length) { drop.style.display='none'; return; }
    drop.innerHTML = matches.map(l =>
      `<div style="padding:7px 10px;cursor:pointer;font-size:.95rem;" onmousedown="selectLoc(${l.id},'${escJ(l.name)}','${escJ(l.map_url||'')}')">${escH(l.name)}</div>`
    ).join('');
    drop.style.display = 'block';
  }

  function selectLoc(id, name, map) {
    document.getElementById('loc-search').value    = name;
    document.getElementById('loc-drop').style.display = 'none';
    document.getElementById('loc-edit-id').value   = id;
    document.getElementById('loc-del-id').value    = id;
    document.getElementById('loc-edit-name').value = name;
    document.getElementById('loc-edit-map').value  = map;
    const delBtn = document.getElementById('loc-del-btn');
    delBtn.disabled = name === activeLoc;
    delBtn.title    = name === activeLoc ? 'Cannot delete active location' : '';
    document.getElementById('loc-edit').style.display = 'block';
  }

  function escH(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  function escJ(s) { return s.replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }
  </script>

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
