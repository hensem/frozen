<?php
require 'auth_check.php';
require_approved();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['user_id'])) {
  $uid = (int)$_POST['user_id'];
  if ($uid !== $frozen_user['id']) {
    $target = $pdo->prepare('SELECT email FROM users WHERE id=?');
    $target->execute([$uid]);
    $target_email = $target->fetchColumn();
    $protected = ($target_email === 'hensem@gmail.com' && in_array($_POST['action'], ['ban','unapprove']));
    if (!$protected) {
      switch ($_POST['action']) {
        case 'approve':
          $pdo->prepare('UPDATE users SET approved=1 WHERE id=?')->execute([$uid]);
          log_activity($pdo, 'approve_user', "user_id=$uid");
          $msg = '✅ User approved.';
          break;
        case 'unapprove':
          $pdo->prepare('UPDATE users SET approved=0 WHERE id=?')->execute([$uid]);
          log_activity($pdo, 'unapprove_user', "user_id=$uid");
          $msg = '✅ User unapproved.';
          break;
        case 'ban':
          $pdo->prepare('UPDATE users SET banned=1,approved=0 WHERE id=?')->execute([$uid]);
          log_activity($pdo, 'ban_user', "user_id=$uid");
          $msg = '✅ User banned.';
          break;
        case 'unban':
          $pdo->prepare('UPDATE users SET banned=0,approved=1 WHERE id=?')->execute([$uid]);
          log_activity($pdo, 'unban_user', "user_id=$uid");
          $msg = '✅ User unbanned and approved.';
          break;
      }
    }
  }
}

$users = $pdo->query('SELECT * FROM users WHERE deleted=0 ORDER BY approved ASC, created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
$pending = array_filter($users, fn($u) => !$u['approved'] && !$u['banned']);
$all_users = $pdo->query('SELECT id, name FROM users WHERE deleted=0 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

$per_page  = 20;
$page      = max(1, (int)($_GET['page'] ?? 1));
$f_user    = (int)($_GET['f_user'] ?? 0);
$f_from    = $_GET['f_from'] ?? '';
$f_to      = $_GET['f_to'] ?? '';

$where = [];
$params = [];
if ($f_user) { $where[] = 'a.user_id=?'; $params[] = $f_user; }
if ($f_from) { $where[] = 'DATE(a.created_at)>=?'; $params[] = $f_from; }
if ($f_to)   { $where[] = 'DATE(a.created_at)<=?'; $params[] = $f_to; }
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM activity_log a $where_sql");
$total->execute($params);
$total_rows = $total->fetchColumn();
$total_pages = max(1, ceil($total_rows / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$log_stmt = $pdo->prepare("
  SELECT a.*, u.name FROM activity_log a
  JOIN users u ON u.id = a.user_id
  $where_sql
  ORDER BY a.created_at DESC
  LIMIT $per_page OFFSET $offset
");
$log_stmt->execute($params);
$logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);

function log_query_string($overrides = []) {
  $params = array_merge(['f_user' => $_GET['f_user'] ?? '', 'f_from' => $_GET['f_from'] ?? '', 'f_to' => $_GET['f_to'] ?? '', 'page' => $_GET['page'] ?? 1], $overrides);
  return '?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== 0 && $v !== '0'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Users</title>
<link rel="stylesheet" href="/frozen/style.css">
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Users</h1>
  <?php if ($msg): ?><div class="alert"><?= $msg ?></div><?php endif; ?>

  <?php if (count($pending)): ?>
  <div class="alert alert-warn">
    ⚠️ <?= count($pending) ?> user<?= count($pending) != 1 ? 's' : '' ?> waiting for approval:
    <?= implode(', ', array_map(fn($u) => htmlspecialchars($u['name']), $pending)) ?>
  </div>
  <?php endif; ?>

  <div class="table-wrap">
  <table>
    <thead>
      <tr><th>User</th><th>Email</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td>
          <?php if ($u['avatar']): ?><img src="<?= htmlspecialchars($u['avatar']) ?>" class="avatar"><?php endif; ?>
          <?= htmlspecialchars($u['name']) ?>
          <?php if ($u['id'] === $frozen_user['id']): ?><span class="badge">you</span><?php endif; ?>
        </td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><?= $u['created_at'] ?></td>
        <td>
          <?php if ($u['banned']): ?>
            <span class="badge badge-red">Banned</span>
          <?php elseif ($u['approved']): ?>
            <span class="badge badge-green">Approved</span>
          <?php else: ?>
            <span class="badge badge-yellow">Pending</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($u['id'] !== $frozen_user['id']): ?>
          <div class="inline-form">
            <?php if ($u['banned']): ?>
              <form method="post"><input type="hidden" name="action" value="unban"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit">Unban</button></form>
            <?php elseif ($u['approved']): ?>
              <?php if ($u['email'] !== 'hensem@gmail.com'): ?>
              <form method="post"><input type="hidden" name="action" value="unapprove"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit" class="btn-warn">Unapprove</button></form>
              <form method="post"><input type="hidden" name="action" value="ban"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit" class="btn-danger">Ban</button></form>
              <?php endif; ?>
            <?php else: ?>
              <form method="post"><input type="hidden" name="action" value="approve"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit">Approve</button></form>
              <form method="post"><input type="hidden" name="action" value="ban"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><button type="submit" class="btn-danger">Ban</button></form>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <h2>Activity Log</h2>

  <form method="get" class="form-grid" style="margin-bottom:16px;">
    <label>User
      <select name="f_user">
        <option value="">All users</option>
        <?php foreach ($all_users as $u): ?>
        <option value="<?= $u['id'] ?>" <?= $f_user == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>From<input type="date" name="f_from" value="<?= htmlspecialchars($f_from) ?>"></label>
    <label>To<input type="date" name="f_to" value="<?= htmlspecialchars($f_to) ?>"></label>
    <button type="submit">Filter</button>
    <?php if ($f_user || $f_from || $f_to): ?>
    <a href="users.php" class="btn-link" style="align-self:center;">Clear</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap">
  <table>
    <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Detail</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td><?= $l['created_at'] ?></td>
        <td><?= htmlspecialchars($l['name']) ?></td>
        <td><?= htmlspecialchars($l['action']) ?></td>
        <td><?= htmlspecialchars($l['detail'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($logs)): ?>
      <tr><td colspan="4" class="muted" style="text-align:center;padding:20px;">No records found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>

  <?php if ($total_pages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?>
      <a href="<?= log_query_string(['page' => $page - 1]) ?>">&laquo; Prev</a>
    <?php endif; ?>
    <span>Page <?= $page ?> of <?= $total_pages ?> &nbsp;(<?= $total_rows ?> records)</span>
    <?php if ($page < $total_pages): ?>
      <a href="<?= log_query_string(['page' => $page + 1]) ?>">Next &raquo;</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
