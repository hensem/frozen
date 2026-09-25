<?php
// included by auth_check.php, $frozen_user is available
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Frozen — Pending Approval</title>
<link rel="stylesheet" href="/frozen/style.css">
</head>
<body>
<nav class="topnav">
  <span class="topnav-brand">Frozen</span>
  <div class="topnav-user">
    <?php if (!empty($frozen_user['avatar'])): ?>
      <img src="<?= htmlspecialchars($frozen_user['avatar']) ?>" class="avatar">
    <?php endif; ?>
    <span><?= htmlspecialchars($frozen_user['name']) ?></span>
    <a href="/frozen/logout.php">Logout</a>
  </div>
</nav>
<div class="container" style="text-align:center;padding-top:60px;">
  <div class="card" style="max-width:400px;margin:0 auto;">
    <div style="font-size:2.5rem;margin-bottom:12px;">⏳</div>
    <h2 style="margin-bottom:8px;">Pending Approval</h2>
    <p class="muted">Your account is waiting for approval. Please check back later.</p>
    <a href="/frozen/logout.php" style="display:inline-block;margin-top:20px;" class="btn-link">Logout</a>
  </div>
</div>
</body>
</html>
