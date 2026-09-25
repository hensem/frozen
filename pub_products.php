<?php
require_once __DIR__ . '/../../config/config_frozen.php';
if ($_SERVER['HTTP_HOST'] !== FROZEN_ALLOWED_HOST) { http_response_code(403); exit('Access denied.'); }
$pdo = new PDO('sqlite:' . __DIR__ . '/../../config/frozen.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$products = $pdo->query('SELECT name, pcs, retail_price FROM products ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Produk — Frozen</title>
<link rel="stylesheet" href="/frozen/style.css">
</head>
<body>
<div style="position:sticky;top:0;background:#f4f6f8;z-index:10;padding:12px 16px;border-bottom:1px solid #e2e8f0;"><a href="/frozen/home.php" style="color:#64748b;font-size:.85rem;text-decoration:none;">← Balik</a></div>
<div class="container" style="max-width:600px;">
  <input type="text" id="search" placeholder="Cari produk..." oninput="filter()"
    style="width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:.95rem;margin-bottom:16px;">
  <div class="table-wrap">
  <table id="tbl">
    <thead><tr><th>Nama</th><th>Pcs</th><th>Harga (RM)</th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
      <tr data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>">
        <td><?= htmlspecialchars($p['name']) ?></td>
        <td><?= $p['pcs'] ?></td>
        <td><?= $p['retail_price'] !== null ? number_format($p['retail_price'], 2) : '<span class="muted">—</span>' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<button id="top-btn" onclick="window.scrollTo({top:0,behavior:'smooth'})" style="display:none;position:fixed;bottom:24px;right:24px;width:44px;height:44px;border-radius:50%;background:#1e293b;color:#fff;font-size:1.2rem;border:none;cursor:pointer;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.3);">&#8679;</button>
<script>
function filter() {
  const q = document.getElementById('search').value.toLowerCase();
  document.querySelectorAll('#tbl tbody tr').forEach(r => r.style.display = r.dataset.name.includes(q) ? '' : 'none');
}
const btn = document.getElementById('top-btn');
window.addEventListener('scroll', () => btn.style.display = window.scrollY > 200 ? 'flex' : 'none');
</script>
</body>
</html>
