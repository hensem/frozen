<?php
require_once __DIR__ . '/../../config/config_frozen.php';
use League\OAuth2\Client\Provider\Google;
require_once __DIR__ . '/../../library/vendor/autoload.php';

session_set_cookie_params(['lifetime'=>86400*30,'httponly'=>true,'secure'=>true,'samesite'=>'Lax']);
session_start();

if ($_SERVER['HTTP_HOST'] !== FROZEN_ALLOWED_HOST) { http_response_code(403); exit('Access denied.'); }
if (!empty($_SESSION['frozen_user_id'])) { header('Location: /frozen/'); exit; }

$google_provider = new Google([
  'clientId'     => FROZEN_GOOGLE_CLIENT_ID,
  'clientSecret' => FROZEN_GOOGLE_CLIENT_SECRET,
  'redirectUri'  => FROZEN_GOOGLE_REDIRECT_URI,
  'scopes'       => ['openid','email','profile'],
]);

$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$authUrl = $google_provider->getAuthorizationUrl(['state' => $state]);

$pdo = new PDO('sqlite:' . __DIR__ . '/../../config/frozen.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$items = $pdo->query('SELECT i.name, i.sell_price, i.quantity, i.image_id, i.id, i.template_id FROM items i ORDER BY CASE WHEN i.name="Ketupat Sotong 2 Ekor" THEN 2 WHEN i.quantity=0 THEN 1 ELSE 0 END, i.name')->fetchAll(PDO::FETCH_ASSOC);
$location = $pdo->query('SELECT value FROM settings WHERE key="location"')->fetchColumn() ?: 'Tak meniaga sekarang';
$loc_row = $pdo->prepare('SELECT map_url FROM locations WHERE name=?');
$loc_row->execute([$location]);
$map_url = $loc_row->fetchColumn();

// Build template content per item
$item_manuals = [];
foreach ($items as $item) {
  if (!$item['template_id']) continue;
  $tpl = $pdo->prepare('SELECT content FROM templates WHERE id=?');
  $tpl->execute([$item['template_id']]);
  $content = $tpl->fetchColumn();
  if (!$content) continue;
  $vals = $pdo->prepare('SELECT name, value FROM template_variables WHERE template_id=?');
  $vals->execute([$item['template_id']]);
  foreach ($vals->fetchAll(PDO::FETCH_ASSOC) as $v) {
    $content = str_replace('{{'.$v['name'].'}}', $v['value'], $content);
  }
  // Escape and convert newlines first, then substitute image tokens
  $content = nl2br(htmlspecialchars($content, ENT_QUOTES));
  $timg = $pdo->prepare('SELECT ti.name, ti.image_id FROM template_images ti WHERE ti.template_id=?');
  $timg->execute([$item['template_id']]);
  foreach ($timg->fetchAll(PDO::FETCH_ASSOC) as $img) {
    $content = str_replace('[['.htmlspecialchars($img['name'], ENT_QUOTES).']]', '<img src="/frozen/img.php?id='.$img['image_id'].'" style="max-width:100%;border-radius:6px;margin:6px 0;display:block;">', $content);
  }
  $item_manuals[$item['id']] = $content;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Frozen</title>
<link rel="stylesheet" href="/frozen/style.css">
</head>
<body>
<div style="text-align:right;padding:12px 16px;"><a href="<?= htmlspecialchars($authUrl) ?>" style="color:#64748b;font-size:.85rem;text-decoration:none;">Staff's Login</a></div>

<div class="container" style="max-width:480px;">

  <?php if ($items): ?>
  <h1 style="margin-bottom:32px;text-align:center;">Senarai Harga</h1>
  <div class="table-wrap" style="margin-bottom:48px;">
  <table>
    <thead>
      <tr><th>Item</th><th>Harga (RM)</th></tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): ?>
      <?php if ($item['quantity'] > 0): ?>
      <?php if ($item['name'] === 'Ketupat Sotong 2 Ekor'): ?>
      <tr><td colspan="2" style="padding:16px 0 0;"></td></tr>
      <?php endif; ?>
      <tr>
        <td>
          <?php if ($item['image_id'] == 1): ?>
            <img src="/frozen/img.php?id=1" style="width:40px;height:40px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:8px;" title="Tiada gambar">
          <?php else: ?>
            <img src="/frozen/img.php?id=<?= $item['image_id'] ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-right:8px;cursor:pointer;" onclick="openModal('/frozen/img.php?id=<?= $item['image_id'] ?>')">
          <?php endif; ?>
          <?php if (isset($item_manuals[$item['id']])): ?><span class="manual-link" onclick="openManual(this)" data-content="<?= htmlspecialchars($item_manuals[$item['id']], ENT_QUOTES) ?>"><?= htmlspecialchars($item['name']) ?></span><?php else: ?><?= htmlspecialchars($item['name']) ?><?php endif; ?><?php if ($item['name'] === 'Ketupat Sotong 2 Ekor'): ?><br><br><span style="font-size:.85rem;color:#475569;">Tak nak Ketupat Sotong frozen? Nak yang baru masak? WhatsApp <a href="https://wa.me/60165235523?text=Saya%20nak%20tempah%20Ketupat%20Sotong" target="_blank" style="color:#16a34a;font-weight:600;">016-523 5523</a> untuk tempahan.</span><?php endif; ?>
        </td>
        <td><?= number_format($item['sell_price'], 2) ?></td>
      </tr>
      <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <p style="margin-top:16px;font-size:.95rem;color:#1e293b;">📍 Lokasi Sekarang: <strong><?php if ($map_url): ?><a href="<?= htmlspecialchars($map_url) ?>" target="_blank" style="color:#16a34a;text-decoration:underline;"><?= htmlspecialchars($location) ?></a><?php else: ?><?= htmlspecialchars($location) ?><?php endif; ?></strong></p>
  <p style="margin-top:10px;text-align:center;"><br><br><a href="/frozen/order.php" style="color:#16a34a;font-size:.95rem;font-weight:600;">Order Online</a></p>
  <p style="margin-top:10px;text-align:center;"><a href="/frozen/pub_products.php" style="color:#0ea5e9;font-size:.95rem;">Produk</a></p>
  <?php endif; ?>
</div>

<style>
.manual-link { color:#16a34a; text-decoration:underline; cursor:pointer; }
#manual-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:1000; align-items:flex-end; justify-content:center; }
#manual-modal.open { display:flex; }
#manual-box { position:relative; background:#fff; border-radius:16px 16px 0 0; width:100%; max-width:520px; max-height:80vh; overflow-y:auto; padding:24px 20px 32px; box-sizing:border-box; }
#manual-box h2 { margin:0 0 16px; font-size:1.1rem; color:#1e293b; padding-right:32px; }
#manual-body { font-size:.95rem; line-height:1.8; color:#334155; }
#manual-close { position:absolute; top:12px; right:14px; font-size:1.4rem; color:#1e293b; cursor:pointer; line-height:1; background:none; border:none; padding:4px; }
</style>

<div id="manual-modal" onclick="closeManual()">
  <div id="manual-box" onclick="event.stopPropagation()">
    <button id="manual-close" onclick="closeManual()">&#x2715;</button>
    <h2 id="manual-title"></h2>
    <div id="manual-body"></div>
  </div>
</div>

<div id="img-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:999;align-items:center;justify-content:center;" onclick="closeModal()">
  <span style="position:fixed;top:16px;right:20px;color:#fff;font-size:2rem;cursor:pointer;line-height:1;" onclick="closeModal()">&#x2715;</span>
  <img id="img-modal-src" src="" style="max-width:90vw;max-height:90vh;border-radius:8px;" onclick="event.stopPropagation()">
</div>
<script>
function openModal(src) {
  document.getElementById('img-modal-src').src = src;
  document.getElementById('img-modal').style.display = 'flex';
}
function closeModal() {
  document.getElementById('img-modal').style.display = 'none';
}
function openManual(el) {
  document.getElementById('manual-title').textContent = el.textContent;
  document.getElementById('manual-body').innerHTML = el.dataset.content;
  document.getElementById('manual-modal').classList.add('open');
}
function closeManual() {
  document.getElementById('manual-modal').classList.remove('open');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal(); closeManual(); } });
</script>
</body>
</html>
