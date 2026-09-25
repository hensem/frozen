<?php
require_once __DIR__ . '/../../config/config_frozen.php';
if ($_SERVER['HTTP_HOST'] !== FROZEN_ALLOWED_HOST) { http_response_code(403); exit('Access denied.'); }

$pdo = new PDO('sqlite:' . __DIR__ . '/../../config/frozen.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Ensure orders table exists
$pdo->exec('CREATE TABLE IF NOT EXISTS orders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  phone TEXT NOT NULL,
  address TEXT NOT NULL,
  email TEXT,
  items TEXT NOT NULL,
  total REAL NOT NULL,
  ordered_at TEXT NOT NULL
)');

$items = $pdo->query('SELECT id, name, sell_price, quantity, image_id FROM items WHERE quantity > 0 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

function frozen_mailer($to, $name, $subject, $body) {
  $clientId     = FROZEN_MAIL_CLIENT_ID;
  $clientSecret = FROZEN_MAIL_CLIENT_SECRET;
  $tenantId     = FROZEN_MAIL_TENANT_ID;
  $fromEmail    = FROZEN_MAIL_FROM;
  $rtFile       = __DIR__ . '/../../config/refresh_token_frozen.txt';

  $data = http_build_query([
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'grant_type'    => 'refresh_token',
    'refresh_token' => trim(file_get_contents($rtFile)),
    'scope'         => 'https://graph.microsoft.com/.default offline_access',
  ]);
  $ch = curl_init("https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token");
  curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$data, CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
  $token = json_decode(curl_exec($ch), true);
  curl_close($ch);
  if (!isset($token['access_token'])) return ['success'=>false,'error'=>'Token refresh failed'];
  if (isset($token['refresh_token'])) file_put_contents($rtFile, $token['refresh_token']);

  $payload = ['message'=>['subject'=>$subject,'body'=>['contentType'=>'HTML','content'=>$body],'from'=>['emailAddress'=>['address'=>$fromEmail,'name'=>'Frozen Food']],'toRecipients'=>[['emailAddress'=>['address'=>$to,'name'=>$name]]]],'saveToSentItems'=>true];
  $ch = curl_init("https://graph.microsoft.com/v1.0/users/$fromEmail/sendMail");
  curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token['access_token'],'Content-Type: application/json']]);
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return $code === 202 ? ['success'=>true] : ['success'=>false,'error'=>"HTTP $code: $resp"];
}

$order = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name    = trim($_POST['name'] ?? '');
  $phone   = trim($_POST['phone'] ?? '');
  $address = trim($_POST['address'] ?? '');
  $email   = trim($_POST['email'] ?? '');
  $sel_ids = $_POST['item_id'] ?? [];
  $sel_qty = $_POST['qty'] ?? [];

  if (!$name || !$phone || !$address) {
    $error = 'Sila isi nama, nombor telefon dan alamat.';
  } elseif (empty($sel_ids)) {
    $error = 'Sila pilih sekurang-kurangnya satu produk.';
  } else {
    // Build order lines
    $lines = [];
    $total = 0;
    $item_map = array_column($items, null, 'id');
    foreach ($sel_ids as $iid) {
      $iid = (int)$iid;
      $qty = max(1, (int)($sel_qty[$iid] ?? 1));
      if (!isset($item_map[$iid])) continue;
      $it = $item_map[$iid];
      $qty = min($qty, $it['quantity']);
      $subtotal = $qty * $it['sell_price'];
      $total += $subtotal;
      $lines[] = ['name'=>$it['name'], 'qty'=>$qty, 'price'=>$it['sell_price'], 'subtotal'=>$subtotal];
    }

    if (empty($lines)) {
      $error = 'Tiada produk yang dipilih.';
    } else {
      $now = date('Y-m-d H:i:s');
      $items_json = json_encode($lines, JSON_UNESCAPED_UNICODE);
      $pdo->prepare('INSERT INTO orders (name,phone,address,email,items,total,ordered_at) VALUES (?,?,?,?,?,?,?)')
          ->execute([$name, $phone, $address, $email ?: null, $items_json, $total, $now]);
      $order_id = $pdo->lastInsertId();

      // Build email table rows
      $rows_html = '';
      foreach ($lines as $l) {
        $rows_html .= "<tr><td style='padding:6px 10px;border:1px solid #ddd;'>" . htmlspecialchars($l['name']) . "</td>"
          . "<td style='padding:6px 10px;border:1px solid #ddd;text-align:center;'>{$l['qty']}</td>"
          . "<td style='padding:6px 10px;border:1px solid #ddd;text-align:right;'>RM " . number_format($l['price'],2) . "</td>"
          . "<td style='padding:6px 10px;border:1px solid #ddd;text-align:right;'>RM " . number_format($l['subtotal'],2) . "</td></tr>";
      }

      // Admin email
      $admin_body = "<h2>Pesanan Baru #$order_id</h2>"
        . "<p><b>Nama:</b> " . htmlspecialchars($name) . "<br>"
        . "<b>Telefon:</b> " . htmlspecialchars($phone) . "<br>"
        . "<b>Alamat:</b><br>" . nl2br(htmlspecialchars($address)) . "<br>"
        . "<b>Email:</b> " . ($email ? htmlspecialchars($email) : '—') . "<br>"
        . "<b>Tarikh:</b> $now</p>"
        . "<table style='border-collapse:collapse;width:100%;'>"
        . "<thead><tr><th style='padding:6px 10px;border:1px solid #ddd;background:#f0f0f0;'>Produk</th><th style='padding:6px 10px;border:1px solid #ddd;background:#f0f0f0;'>Qty</th><th style='padding:6px 10px;border:1px solid #ddd;background:#f0f0f0;'>Harga</th><th style='padding:6px 10px;border:1px solid #ddd;background:#f0f0f0;'>Jumlah</th></tr></thead>"
        . "<tbody>$rows_html</tbody></table>"
        . "<p><b>Jumlah Keseluruhan: RM " . number_format($total,2) . "</b></p>";
      frozen_mailer('frozen@kuceng.my', 'Frozen', "Pesanan Baru #$order_id — " . $name, $admin_body);

      // Customer email
      if ($email) {
        $cust_body = "<p>Assalamualaikum / Salam sejahtera " . htmlspecialchars($name) . ",</p>"
          . "<p>Terima kasih kerana membuat pesanan dengan kami. Berikut adalah ringkasan pesanan anda:</p>"
          . "<p><b>Tarikh:</b> $now</p>"
          . "<table style='border-collapse:collapse;width:100%;'>"
          . "<thead><tr><th style='padding:6px 10px;border:1px solid #ddd;background:#f0f0f0;'>Produk</th><th style='padding:6px 10px;border:1px solid #ddd;background:#f0f0f0;'>Qty</th><th style='padding:6px 10px;border:1px solid #ddd;background:#f0f0f0;'>Harga</th><th style='padding:6px 10px;border:1px solid #ddd;background:#f0f0f0;'>Jumlah</th></tr></thead>"
          . "<tbody>$rows_html</tbody></table>"
          . "<p><b>Jumlah Keseluruhan: RM " . number_format($total,2) . "</b></p>"
          . "<hr style='margin:20px 0;border:none;border-top:1px solid #e2e8f0;'>"
          . "<h3 style='margin-bottom:8px;'>Maklumat Penghantaran</h3>"
          . "<ul style='padding-left:20px;line-height:1.8;font-size:.9rem;'>"
          . "<li>Bagi rumah landed, penghantaran akan dibuat ke alamat yang diberikan.</li>"
          . "<li>Bagi rumah flat atau pangsapuri, pesanan perlu diambil di bawah/lobi, atau sila gunakan perkhidmatan Lalamove.</li>"
          . "<li>Bagi kawasan berpagar atau berpengawal (guarded/security), pesanan mungkin akan diserahkan kepada pengawal keselamatan sekiranya proses kemasukan atau menunggu mengambil masa yang lama.</li>"
          . "<li>Sila pastikan nombor telefon boleh dihubungi pada hari penghantaran.</li>"
          . "</ul>"
          . "<h3 style='margin-bottom:8px;'>Self Collect</h3>"
          . "<p style='font-size:.9rem;line-height:1.8;'>Self collect juga disediakan di Seksyen 18 Shah Alam. Sila hubungi kami terlebih dahulu sebelum datang mengambil pesanan bagi memastikan pesanan telah siap dan sedia untuk diambil. Sekiranya pesanan tidak diambil pada hari yang dipersetujui, pesanan boleh diambil dalam tempoh <b>7 hari</b>.</p>"
          . "<h3 style='margin-bottom:8px;'>Penghantaran Melalui Lalamove</h3>"
          . "<p style='font-size:.9rem;line-height:1.8;'>Bagi penghantaran menggunakan Lalamove, tanggungjawab kami adalah sehingga pesanan diserahkan kepada penghantar dan bukti penyerahan diberikan. Sebarang kelewatan, kerosakan, kehilangan atau kegagalan penghantaran selepas pesanan diserahkan kepada pihak Lalamove adalah di luar kawalan kami dan tertakluk kepada terma serta polisi pihak Lalamove.</p>"
          . "<h3 style='margin-bottom:8px;'>Pembayaran</h3>"
          . "<p style='font-size:.9rem;line-height:1.8;'>Kami akan menghubungi anda untuk maklumat pembayaran melalui WhatsApp. Selepas pembayaran dibuat, sila hantar bukti pembayaran untuk pengesahan.<br><b><i>Tempahan hanya disahkan selepas pembayaran penuh diterima.</i></b></p>"
          . "<p>Terima kasih.</p>";
        frozen_mailer($email, $name, "Pesanan Anda — Frozen", $cust_body);
      }

      $order = compact('order_id','name','phone','address','email','lines','total','now');
    }
  }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Online — Frozen</title>
<link rel="stylesheet" href="/frozen/style.css">
<style>
.order-wrap{max-width:560px;margin:0 auto;padding:24px 16px}
.item-row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f1f5f9}
.item-row:last-child{border-bottom:none}
.item-row label{flex:1;font-size:.95rem;cursor:pointer}
.item-row select{padding:4px 6px;border:1px solid #cbd5e1;border-radius:4px;font-size:.9rem}
.field{display:flex;flex-direction:column;gap:5px;margin-bottom:14px}
.field label{font-size:.85rem;font-weight:500;color:#475569}
.field input,.field textarea{padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:.95rem}
.field textarea{resize:vertical;min-height:70px}
.notice{background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 14px;font-size:.88rem;color:#166534;margin-bottom:20px;line-height:1.6}
.confirm-box{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:20px;margin-top:16px}
.confirm-box table{width:100%;border-collapse:collapse;font-size:.9rem;margin:12px 0}
.confirm-box th,.confirm-box td{border:1px solid #dee2e6;padding:6px 10px}
.confirm-box th{background:#1e293b;color:#fff;}
.back-link{display:inline-block;margin-bottom:16px;color:#64748b;font-size:.85rem;text-decoration:none}
</style>
</head>
<body>
<div style="position:sticky;top:0;background:#f4f6f8;z-index:10;padding:12px 16px;border-bottom:1px solid #e2e8f0;">
<?php if (!$order): ?>
  <a href="/frozen/home.php" style="color:#64748b;font-size:.85rem;text-decoration:none;">← Balik</a>
<?php endif; ?>
</div>

<div class="order-wrap">

<?php if ($order): ?>
  <h1 style="margin-bottom:16px;">✅ Pesanan Diterima</h1>
  <div class="notice" style="background:#fffbeb;border-color:#fcd34d;color:#92400e;">
    Bayaran diperlukan untuk pengesahan tempahan. Maklumat pembayaran akan diberikan melalui WhatsApp.
  </div>
  <div class="confirm-box">
    <p><strong>Nama:</strong> <?= htmlspecialchars($order['name']) ?></p>
    <p><strong>Telefon:</strong> <?= htmlspecialchars($order['phone']) ?></p>
    <p><strong>Alamat:</strong><br><?= nl2br(htmlspecialchars($order['address'])) ?></p>
    <?php if ($order['email']): ?>
    <p><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></p>
    <?php endif; ?>
    <table>
      <thead><tr><th>Produk</th><th>Qty</th><th>Harga</th><th>Jumlah</th></tr></thead>
      <tbody>
      <?php foreach ($order['lines'] as $l): ?>
        <tr>
          <td><?= htmlspecialchars($l['name']) ?></td>
          <td style="text-align:center"><?= $l['qty'] ?></td>
          <td style="text-align:right">RM <?= number_format($l['price'],2) ?></td>
          <td style="text-align:right">RM <?= number_format($l['subtotal'],2) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p><strong>Jumlah Keseluruhan: RM <?= number_format($order['total'],2) ?></strong></p>
    <?php if ($order['email']): ?>
    <p style="font-size:.85rem;color:#64748b;">Pengesahan pesanan telah dihantar ke <?= htmlspecialchars($order['email']) ?>.</p>
    <?php endif; ?>
  </div>
  <p style="margin-top:16px;"><a href="/frozen/home.php" style="color:#0ea5e9;">← Kembali ke halaman utama</a></p>

<?php else: ?>
  <h1 style="margin-bottom:16px;">Order Online</h1>

  <?php if ($error): ?>
    <div class="alert" style="margin-bottom:16px;">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="notice">
    Kami akan maklumkan bila order akan dihantar. Penghantaran percuma hanya di sekitar Seksyen 18, 19 dan 20 Shah Alam. Diluar kawasan, penghantaran akan dibuat menggunakan Lalamove. Kos penghantaran perlu dibayar secara tunai terus kepada penghantar Lalamove semasa pesanan diterima.
  </div>

  <?php if (empty($items)): ?>
    <p class="muted">Tiada produk yang tersedia pada masa ini.</p>
  <?php else: ?>
  <form method="post">
    <p style="font-weight:600;margin-bottom:10px;">Pilih Produk</p>
    <div style="background:#fff;border-radius:8px;padding:12px 16px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:20px;">
    <?php foreach ($items as $it): ?>
      <div class="item-row">
        <input type="checkbox" name="item_id[]" value="<?= $it['id'] ?>" id="item_<?= $it['id'] ?>"
          onchange="toggleQty(<?= $it['id'] ?>)">
        <label for="item_<?= $it['id'] ?>" style="display:flex;align-items:center;gap:8px;">
          <?php if ($it['image_id'] == 1): ?>
            <img src="/frozen/img.php?id=1" style="width:40px;height:40px;object-fit:cover;border-radius:4px;flex-shrink:0;">
          <?php else: ?>
            <img src="/frozen/img.php?id=<?= $it['image_id'] ?>" style="width:40px;height:40px;object-fit:cover;border-radius:4px;flex-shrink:0;cursor:pointer;" onclick="event.preventDefault();openModal('/frozen/img.php?id=<?= $it['image_id'] ?>')">
          <?php endif; ?>
          <span><?= htmlspecialchars($it['name']) ?>
          <span style="color:#64748b;font-size:.85rem;"> — RM <?= number_format($it['sell_price'],2) ?></span></span>
        </label>
        <select name="qty[<?= $it['id'] ?>]" id="qty_<?= $it['id'] ?>" disabled style="opacity:.4">
          <?php for ($q=1; $q<=$it['quantity']; $q++): ?>
            <option value="<?= $q ?>"><?= $q ?></option>
          <?php endfor; ?>
        </select>
      </div>
    <?php endforeach; ?>
    </div>

    <p style="font-weight:600;margin-bottom:10px;">Maklumat Penghantaran</p>
    <div class="field">
      <label>Nama <span style="color:#dc2626">*</span></label>
      <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
    </div>
    <div class="field">
      <label>Nombor Telefon (WhatsApp) <span style="color:#dc2626">*</span></label>
      <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
    </div>
    <div class="field">
      <label>Alamat Penghantaran <span style="color:#dc2626">*</span></label>
      <textarea name="address"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
    </div>
    <div class="field">
      <label>Email <span style="color:#94a3b8;font-weight:400;">(tidak wajib — untuk terima pengesahan)</span></label>
      <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>

    <button type="submit" style="width:100%;padding:12px;font-size:1rem;margin-top:4px;">Hantar Pesanan</button>
  </form>

  <div style="background:#fff;border-radius:8px;padding:16px 20px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-top:20px;font-size:.88rem;line-height:1.8;color:#334155;">
    <p style="font-weight:700;margin:0 0 6px;">MAKLUMAT PENGHANTARAN</p>
    <ul style="margin:0 0 14px;padding-left:18px;">
      <li>Bagi rumah landed, penghantaran akan dibuat ke alamat yang diberikan.</li>
      <li>Bagi rumah flat atau pangsapuri, pesanan perlu diambil di bawah/lobi, atau sila gunakan perkhidmatan Lalamove.</li>
      <li>Bagi kawasan berpagar atau berpengawal (guarded/security), pesanan mungkin akan diserahkan kepada pengawal keselamatan sekiranya proses kemasukan atau menunggu mengambil masa yang lama.</li>
      <li>Sila pastikan nombor telefon boleh dihubungi pada hari penghantaran.</li>
    </ul>
    <p style="font-weight:700;margin:0 0 6px;">SELF COLLECT</p>
    <p style="margin:0 0 14px;">Self collect juga disediakan di Seksyen 18 Shah Alam. Sila hubungi kami terlebih dahulu sebelum datang mengambil pesanan bagi memastikan pesanan telah siap dan sedia untuk diambil. Sekiranya pesanan tidak diambil pada hari yang dipersetujui, pesanan boleh diambil dalam tempoh <strong>7 hari</strong>.</p>
    <p style="font-weight:700;margin:0 0 6px;">PENGHANTARAN MELALUI LALAMOVE</p>
    <p style="margin:0 0 14px;">Bagi penghantaran menggunakan Lalamove, tanggungjawab kami adalah sehingga pesanan diserahkan kepada penghantar dan bukti penyerahan diberikan. Sebarang kelewatan, kerosakan, kehilangan atau kegagalan penghantaran selepas pesanan diserahkan kepada pihak Lalamove adalah di luar kawalan kami dan tertakluk kepada terma serta polisi pihak Lalamove.</p>
    <p style="font-weight:700;margin:0 0 6px;">PEMBAYARAN</p>
    <p style="margin:0;">Kami akan menghubungi anda untuk maklumat pembayaran melalui WhatsApp. Selepas pembayaran dibuat, sila hantar bukti pembayaran untuk pengesahan.<br><em><strong>Tempahan hanya disahkan selepas pembayaran penuh diterima.</strong></em></p>
  </div>
  <?php endif; ?>
<?php endif; ?>

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
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
function toggleQty(id) {
  const cb  = document.getElementById('item_' + id);
  const sel = document.getElementById('qty_' + id);
  sel.disabled = !cb.checked;
  sel.style.opacity = cb.checked ? '1' : '.4';
}
</script>
</body>
</html>
