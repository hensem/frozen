<?php
// $frozen_user and $pdo are available from auth_check.php
$pending_count = $pdo->query('SELECT COUNT(*) FROM users WHERE approved=0 AND banned=0 AND deleted=0')->fetchColumn();
$pending_orders = $pdo->query('SELECT COUNT(*) FROM orders WHERE status="pending"')->fetchColumn();
$current = basename($_SERVER['PHP_SELF']);
?>
<nav class="topnav">
  <div class="topnav-links">
    <a href="/frozen/" class="<?= $current==='index.php'?'active':'' ?>">Dashboard</a>
    <a href="/frozen/items.php" class="<?= $current==='items.php'?'active':'' ?>">Items</a>
    <a href="/frozen/images.php" class="<?= $current==='images.php'?'active':'' ?>">Images</a>
    <a href="/frozen/products.php" class="<?= $current==='products.php'?'active':'' ?>">Products</a>
    <a href="/frozen/sell.php" class="<?= $current==='sell.php'?'active':'' ?>">Sell</a>
    <a href="/frozen/history.php" class="<?= $current==='history.php'?'active':'' ?>">History</a>
    <a href="/frozen/manual.php" class="<?= $current==='manual.php'?'active':'' ?>">Manual</a>
    <a href="/frozen/orders.php" class="<?= $current==='orders.php'?'active':'' ?>">
      Orders<?php if ($pending_orders): ?> <span class="badge badge-red"><?= $pending_orders ?></span><?php endif; ?>
    </a>
    <a href="/frozen/users.php" class="<?= $current==='users.php'?'active':'' ?>">
      Users<?php if ($pending_count): ?> <span class="badge badge-red"><?= $pending_count ?></span><?php endif; ?>
    </a>
  </div>
  <div class="topnav-user">
    <?php if (!empty($frozen_user['avatar'])): ?>
      <img src="<?= htmlspecialchars($frozen_user['avatar']) ?>" class="avatar">
    <?php endif; ?>
    <span class="topnav-name"><?= htmlspecialchars($frozen_user['name']) ?></span>
    <a href="/frozen/logout.php" class="topnav-logout">Logout</a>
  </div>
</nav>
