<?php
require 'auth_check.php';
require_approved();
require 'db.php';

define('MAX_UPLOAD_BYTES', 100 * 1024); // 100KB

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

  if ($_POST['action'] === 'upload') {
    $name = trim($_POST['name']);
    $file = $_FILES['image'] ?? null;
    if (!$name) {
      $msg = '❌ Name is required.';
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
      $msg = '❌ Upload failed.';
    } elseif ($file['size'] > MAX_UPLOAD_BYTES) {
      $msg = '❌ File too large. Maximum is 100KB.';
    } else {
      $mime = mime_content_type($file['tmp_name']);
      $data = file_get_contents($file['tmp_name']);
      $pdo->prepare('INSERT INTO images (name,data,mime_type,created_at) VALUES (?,?,?,?)')
          ->execute([$name, $data, $mime, date('Y-m-d H:i:s')]);
      log_activity($pdo, 'upload_image', $name);
      $msg = '✅ Image uploaded.';
    }

  } elseif ($_POST['action'] === 'rename') {
    $id   = (int)$_POST['image_id'];
    $name = trim($_POST['name']);
    if ($id && $name) {
      $pdo->prepare('UPDATE images SET name=? WHERE id=?')->execute([$name, $id]);
      log_activity($pdo, 'rename_image', "id=$id name=$name");
      $msg = '✅ Image renamed.';
    }

  } elseif ($_POST['action'] === 'delete') {
    $id = (int)$_POST['image_id'];
    if ($id === 1) {
      $msg = '❌ Cannot delete the default image.';
    } elseif ($id) {
      $pdo->prepare('UPDATE items SET image_id=1 WHERE image_id=?')->execute([$id]);
      $pdo->prepare('DELETE FROM images WHERE id=?')->execute([$id]);
      log_activity($pdo, 'delete_image', "id=$id");
      $msg = '✅ Image deleted.';
    }

  } elseif ($_POST['action'] === 'link') {
    $image_id = (int)$_POST['image_id'];
    $item_id  = (int)$_POST['item_id'];
    if ($image_id && $item_id) {
      $pdo->prepare('UPDATE items SET image_id=? WHERE id=?')->execute([$image_id, $item_id]);
      log_activity($pdo, 'link_image', "image_id=$image_id item_id=$item_id");
      $msg = '✅ Item linked.';
    }
  }
}

$images = $pdo->query('SELECT id, name, mime_type, created_at FROM images ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
$items  = $pdo->query('SELECT id, name, image_id FROM items ORDER BY CASE WHEN image_id=1 THEN 0 ELSE 1 END, name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Images — Frozen</title>
<link rel="stylesheet" href="/frozen/style.css">
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Images</h1>
  <?php if ($msg): ?><div class="alert"><?= $msg ?></div><?php endif; ?>

  <h2>Upload New Image</h2>
  <form method="post" enctype="multipart/form-data" class="form-grid">
    <input type="hidden" name="action" value="upload">
    <label>Name<input type="text" name="name" required></label>
    <label>File<input type="file" name="image" accept="image/*" required></label>
    <button type="submit">Upload</button>
  </form>

  <h2>Manage Images</h2>
  <input type="text" id="img-search" placeholder="Search by name..." oninput="filterImages()" style="margin-bottom:10px;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:.95rem;width:100%;max-width:300px;">
  <div class="table-wrap" style="max-height:400px;overflow-y:auto;">
  <table id="img-table">
    <thead>
      <tr><th>Preview</th><th>Name</th><th>Type</th><th>Uploaded</th><th>Linked Items</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($images as $img): ?>
      <?php $linked = array_filter($items, fn($i) => $i['image_id'] == $img['id']); ?>
      <tr data-name="<?= strtolower(htmlspecialchars($img['name'])) ?>">
        <td><img src="/frozen/img.php?id=<?= $img['id'] ?>" style="height:48px;width:48px;object-fit:cover;border-radius:4px;cursor:pointer;" onclick="openModal('/frozen/img.php?id=<?= $img['id'] ?>')" ></td>
        <td>
          <form method="post" class="inline-form">
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
            <input type="text" name="name" value="<?= htmlspecialchars($img['name']) ?>" required style="width:140px">
            <button type="submit">Save</button>
          </form>
        </td>
        <td><?= htmlspecialchars($img['mime_type']) ?></td>
        <td><?= $img['created_at'] ?></td>
        <td>
          <?php if ($linked): ?>
            <?= implode(', ', array_map(fn($i) => htmlspecialchars($i['name']), $linked)) ?>
          <?php else: ?>
            <span class="muted">None</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($img['id'] !== 1): ?>
          <form method="post" class="inline-form">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
            <button type="submit" class="btn-danger" onclick="return confirm('Delete this image? Linked items will revert to default.')">Delete</button>
          </form>
          <?php else: ?>
            <span class="muted">Default</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <h2>Link Items to Image</h2>
  <div class="table-wrap" style="max-height:400px;overflow-y:auto;">
  <table>
    <thead><tr><th>Item</th><th>Current Image</th><th>Change To</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $item): ?>
      <?php $cur = array_values(array_filter($images, fn($img) => $img['id'] == $item['image_id']))[0] ?? null; ?>
      <tr>
        <td><?= htmlspecialchars($item['name']) ?></td>
        <td><?= $cur ? htmlspecialchars($cur['name']) : '<span class="muted">None</span>' ?></td>
        <td>
          <form method="post" class="inline-form">
            <input type="hidden" name="action" value="link">
            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
            <select name="image_id" class="selectpicker" data-live-search="true" data-width="200px">
              <?php foreach ($images as $img): ?>
              <option value="<?= $img['id'] ?>" <?= $img['id'] == $item['image_id'] ? 'selected' : '' ?>
                <?= $img['id'] == 1 ? 'data-tokens="' . implode(' ', array_column($images, 'name')) . '"' : '' ?>>
                <?= htmlspecialchars($img['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <button type="submit">Link</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
</div>

<div id="img-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:999;align-items:center;justify-content:center;" onclick="closeModal()">
  <img id="img-modal-src" src="" style="max-width:90vw;max-height:90vh;border-radius:8px;">
</div>
<script>
$(document).ready(function() {
  $('.selectpicker').selectpicker();
});
function filterImages() {
  const q = document.getElementById('img-search').value.toLowerCase();
  document.querySelectorAll('#img-table tr[data-name]').forEach(row => {
    row.style.display = row.dataset.name.includes(q) ? '' : 'none';
  });
}
function openModal(src) {
  document.getElementById('img-modal-src').src = src;
  document.getElementById('img-modal').style.display = 'flex';
}
function closeModal() {
  document.getElementById('img-modal').style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
</body>
</html>
