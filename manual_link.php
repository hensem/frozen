<?php
require 'auth_check.php';
require_approved();
require 'db.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  if ($_POST['action'] === 'link') {
    $item_id     = (int)$_POST['item_id'];
    $template_id = $_POST['template_id'] === '' ? null : (int)$_POST['template_id'];
    if ($item_id) {
      $pdo->prepare('UPDATE items SET template_id=? WHERE id=?')->execute([$template_id, $item_id]);
      log_activity($pdo, 'link_template', "item_id=$item_id template_id=".($template_id ?? 'null'));
      $msg = '✅ Template updated.';
    }
  }
}

$templates = $pdo->query('SELECT id, name FROM templates ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$items     = $pdo->query('
  SELECT i.id, i.name, i.template_id, t.name AS template_name
  FROM items i
  LEFT JOIN templates t ON t.id = i.template_id
  ORDER BY CASE WHEN i.template_id IS NULL THEN 0 ELSE 1 END, i.name
')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manual Link — Frozen</title>
<link rel="stylesheet" href="/frozen/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Link Items to Manual Template</h1>
  <?php if ($msg): ?><div class="alert"><?= $msg ?></div><?php endif; ?>

  <div class="table-wrap">
  <table>
    <thead>
      <tr><th>Item</th><th>Current Template</th><th>Change To</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($items as $item): ?>
      <tr>
        <td><?= htmlspecialchars($item['name']) ?></td>
        <td>
          <?php if ($item['template_name']): ?>
            <?= htmlspecialchars($item['template_name']) ?>
          <?php else: ?>
            <span class="muted">No template</span>
          <?php endif; ?>
        </td>
        <td>
          <form method="post" class="inline-form">
            <input type="hidden" name="action" value="link">
            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
            <select name="template_id" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:5px;font-size:.88rem;">
              <option value="">— No template —</option>
              <?php foreach ($templates as $t): ?>
              <option value="<?= $t['id'] ?>" <?= $t['id'] == $item['template_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <button type="submit">Save</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
</body>
</html>
