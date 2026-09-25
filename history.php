<?php
require 'auth_check.php';
require_approved();
require 'db.php';

$view  = $_GET['view'] ?? 'report';
$from  = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to    = $_GET['to']   ?? date('Y-m-d');
$from_dt = $from . ' 00:00:00';
$to_dt   = $to   . ' 23:59:59';

// ── shared queries ────────────────────────────────────────────────────────────

if ($view === 'report') {

  // Summary
  $sum = $pdo->prepare('
    SELECT COUNT(*) AS txn,
      SUM(quantity_sold) AS units,
      SUM(quantity_sold * sell_price) AS sales,
      SUM(quantity_sold * buy_price)  AS cost
    FROM sales WHERE sold_at BETWEEN ? AND ?');
  $sum->execute([$from_dt, $to_dt]);
  $s = $sum->fetch(PDO::FETCH_ASSOC);
  $s['profit'] = $s['sales'] - $s['cost'];
  $s['margin'] = $s['sales'] > 0 ? $s['profit'] / $s['sales'] * 100 : 0;
  $s['avg_txn'] = $s['txn'] > 0 ? $s['sales'] / $s['txn'] : 0;

  // Product performance
  $prod = $pdo->prepare('
    SELECT i.id, i.name, i.quantity AS stock_now,
      COALESCE(SUM(s.quantity_sold),0) AS qty_sold,
      COALESCE(SUM(s.quantity_sold * s.sell_price),0) AS sales,
      COALESCE(SUM(s.quantity_sold * s.buy_price),0)  AS cost
    FROM items i
    LEFT JOIN sales s ON s.item_id = i.id AND s.sold_at BETWEEN ? AND ?
    GROUP BY i.id ORDER BY i.name');
  $prod->execute([$from_dt, $to_dt]);
  $products = $prod->fetchAll(PDO::FETCH_ASSOC);

  // Opening stock per item (qty restocked before period end + qty sold before period start)
  foreach ($products as &$p) {
    $r = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM restocks WHERE item_id=? AND restocked_at <= ?');
    $r->execute([$p['id'], $to_dt]);
    $total_restocked = (int)$r->fetchColumn();

    $sold_before = $pdo->prepare('SELECT COALESCE(SUM(quantity_sold),0) FROM sales WHERE item_id=? AND sold_at < ?');
    $sold_before->execute([$p['id'], $from_dt]);
    $opening = max(0, $total_restocked - (int)$sold_before->fetchColumn());

    $available = $opening + 0; // opening stock at start of period
    $p['opening'] = $opening;
    $p['profit']  = $p['sales'] - $p['cost'];
    $p['margin']  = $p['sales'] > 0 ? $p['profit'] / $p['sales'] * 100 : 0;
    $p['sellthrough'] = $opening > 0 ? min(100, $p['qty_sold'] / $opening * 100) : ($p['qty_sold'] > 0 ? 100 : 0);
  }
  unset($p);

  // By day
  $days_q = $pdo->prepare('
    SELECT DATE(sold_at) AS day,
      SUM(quantity_sold) AS units,
      SUM(quantity_sold * sell_price) AS sales,
      SUM(quantity_sold * buy_price)  AS cost,
      COUNT(*) AS txn
    FROM sales WHERE sold_at BETWEEN ? AND ?
    GROUP BY DATE(sold_at) ORDER BY day DESC');
  $days_q->execute([$from_dt, $to_dt]);
  $days = $days_q->fetchAll(PDO::FETCH_ASSOC);

  // By hour
  $hours_q = $pdo->prepare('
    SELECT CAST(strftime(\'%H\', sold_at) AS INTEGER) AS hr,
      SUM(quantity_sold * sell_price) AS sales,
      SUM(quantity_sold) AS units,
      COUNT(*) AS txn,
      SUM(quantity_sold * (sell_price - buy_price)) AS profit
    FROM sales WHERE sold_at BETWEEN ? AND ?
    GROUP BY hr ORDER BY hr');
  $hours_q->execute([$from_dt, $to_dt]);
  $hours = $hours_q->fetchAll(PDO::FETCH_ASSOC);

  // Transactions
  $txns_q = $pdo->prepare('
    SELECT s.sold_at, i.name, s.quantity_sold,
      s.quantity_sold * s.sell_price AS total,
      s.quantity_sold * (s.sell_price - s.buy_price) AS profit
    FROM sales s JOIN items i ON i.id = s.item_id
    WHERE s.sold_at BETWEEN ? AND ?
    ORDER BY s.sold_at DESC');
  $txns_q->execute([$from_dt, $to_dt]);
  $txns = $txns_q->fetchAll(PDO::FETCH_ASSOC);

} elseif ($view === 'daily') {
  $rows = $pdo->prepare('
    SELECT DATE(sold_at) AS day,
      SUM(quantity_sold) AS units,
      SUM(quantity_sold * sell_price) AS revenue,
      SUM(quantity_sold * (sell_price - buy_price)) AS profit
    FROM sales WHERE sold_at BETWEEN ? AND ?
    GROUP BY DATE(sold_at) ORDER BY day DESC');
  $rows->execute([$from_dt, $to_dt]);
  $rows = $rows->fetchAll(PDO::FETCH_ASSOC);
} else {
  $rows = $pdo->prepare('
    SELECT s.id, i.name, s.quantity_sold, s.buy_price, s.sell_price,
      (s.quantity_sold * (s.sell_price - s.buy_price)) AS profit,
      s.sold_at
    FROM sales s JOIN items i ON i.id = s.item_id
    WHERE s.sold_at BETWEEN ? AND ?
    ORDER BY s.sold_at DESC');
  $rows->execute([$from_dt, $to_dt]);
  $rows = $rows->fetchAll(PDO::FETCH_ASSOC);
}

function rm($v) { return 'RM ' . number_format($v, 2); }
function pct($v) { return number_format($v, 1) . '%'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>History — Frozen</title>
<link rel="stylesheet" href="/frozen/style.css">
<style>
.filter-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;margin-bottom:16px}
.filter-bar label{font-size:.85rem;display:flex;flex-direction:column;gap:3px}
.filter-bar input{padding:5px 8px;border:1px solid #ccc;border-radius:4px}
.summary-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;margin-bottom:24px}
.s-card{background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;padding:12px 14px}
.s-card .val{font-size:1.3rem;font-weight:700;margin-top:4px}
.s-card .lbl{font-size:.78rem;color:#666}
.section{margin-bottom:32px}
.section h2{font-size:1.05rem;margin-bottom:10px;border-bottom:2px solid #dee2e6;padding-bottom:4px}
table.report{width:100%;border-collapse:collapse;font-size:.88rem}
table.report th,table.report td{border:1px solid #dee2e6;padding:6px 10px}
table.report thead th{background:#1e293b;color:#fff;cursor:pointer;white-space:nowrap;user-select:none}
table.report thead th:hover{background:#e2e6ea}
table.report tbody tr:hover{background:#f8f9fa}
.tag-restock{background:#d4edda;color:#155724;padding:2px 6px;border-radius:4px;font-size:.78rem}
.tag-monitor{background:#d1ecf1;color:#0c5460;padding:2px 6px;border-radius:4px;font-size:.78rem}
.tag-reduce{background:#f8d7da;color:#721c24;padding:2px 6px;border-radius:4px;font-size:.78rem}
.num{text-align:right}
</style>
</head>
<body>
<?php include 'nav.php'; ?>
<div class="container">
  <h1>Sales History</h1>

  <form method="get" class="filter-bar">
    <input type="hidden" name="view" value="<?= htmlspecialchars($view) ?>">
    <label>From <input type="date" name="from" value="<?= $from ?>"></label>
    <label>To <input type="date" name="to" value="<?= $to ?>"></label>
    <button type="submit">Filter</button>
    <a href="?view=<?= $view ?>" style="align-self:flex-end;padding:6px 10px;font-size:.85rem">Reset</a>
  </form>

  <div class="view-toggle">
    <a href="?view=report&from=<?= $from ?>&to=<?= $to ?>" class="<?= $view==='report'?'active':'' ?>">Report</a>
    <a href="?view=daily&from=<?= $from ?>&to=<?= $to ?>"  class="<?= $view==='daily'?'active':'' ?>">By Day</a>
    <a href="?view=transactions&from=<?= $from ?>&to=<?= $to ?>" class="<?= $view==='transactions'?'active':'' ?>">By Transaction</a>
  </div>

<?php if ($view === 'report'): ?>

  <!-- 1. Summary -->
  <div class="section">
    <h2>Summary</h2>
    <div class="summary-grid">
      <div class="s-card"><div class="lbl">Total Sales</div><div class="val"><?= rm($s['sales']) ?></div></div>
      <div class="s-card"><div class="lbl">Total Cost</div><div class="val"><?= rm($s['cost']) ?></div></div>
      <div class="s-card"><div class="lbl">Gross Profit</div><div class="val"><?= rm($s['profit']) ?></div></div>
      <div class="s-card"><div class="lbl">Profit Margin</div><div class="val"><?= pct($s['margin']) ?></div></div>
      <div class="s-card"><div class="lbl">Items Sold</div><div class="val"><?= number_format($s['units']) ?></div></div>
      <div class="s-card"><div class="lbl">Transactions</div><div class="val"><?= number_format($s['txn']) ?></div></div>
      <div class="s-card"><div class="lbl">Avg Sale / Txn</div><div class="val"><?= rm($s['avg_txn']) ?></div></div>
    </div>
  </div>

  <!-- 2. Product Performance -->
  <div class="section">
    <h2>Product Performance</h2>
    <div class="table-wrap">
    <table class="report" id="tbl-prod">
      <thead><tr>
        <th>Product</th>
        <th class="num">Qty Sold</th>
        <th class="num">Sales</th>
        <th class="num">Cost</th>
        <th class="num">Profit</th>
        <th class="num">Margin %</th>
        <th class="num">Stock Left</th>
        <th class="num">Sell-through %</th>
      </tr></thead>
      <tbody>
      <?php foreach ($products as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td class="num"><?= $p['qty_sold'] ?></td>
          <td class="num"><?= rm($p['sales']) ?></td>
          <td class="num"><?= rm($p['cost']) ?></td>
          <td class="num"><?= rm($p['profit']) ?></td>
          <td class="num"><?= pct($p['margin']) ?></td>
          <td class="num"><?= $p['stock_now'] ?></td>
          <td class="num"><?= pct($p['sellthrough']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <?php
  $sold_products = array_filter($products, fn($p) => $p['qty_sold'] > 0);
  $by_qty    = $sold_products; usort($by_qty,    fn($a,$b) => $b['qty_sold'] <=> $a['qty_sold']);
  $by_profit = $sold_products; usort($by_profit, fn($a,$b) => $b['profit']   <=> $a['profit']);

  // Slow-moving: fetch all-time sales history per item for action decision
  $alltime_q = $pdo->prepare('SELECT item_id, COUNT(DISTINCT DATE(sold_at)) AS days_sold, SUM(quantity_sold) AS total_sold FROM sales GROUP BY item_id');
  $alltime_q->execute();
  $alltime = [];
  foreach ($alltime_q->fetchAll(PDO::FETCH_ASSOC) as $row) $alltime[$row['item_id']] = $row;

  $slow = array_filter($products, fn($p) => $p['sellthrough'] < 50 || $p['qty_sold'] == 0);
  usort($slow, fn($a,$b) => $a['sellthrough'] <=> $b['sellthrough']);
  ?>

  <!-- 3. Best Sellers -->
  <div class="section">
    <h2>Best Sellers</h2>
    <div class="table-wrap">
    <table class="report">
      <thead><tr><th class="num">Rank</th><th>Product</th><th class="num">Qty Sold</th><th class="num">Sales</th><th class="num">Profit</th><th class="num">Sell-Through %</th></tr></thead>
      <tbody>
      <?php foreach (array_values($by_qty) as $i => $p): ?>
        <tr>
          <td class="num"><?= $i+1 ?></td>
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td class="num"><?= $p['qty_sold'] ?></td>
          <td class="num"><?= rm($p['sales']) ?></td>
          <td class="num"><?= rm($p['profit']) ?></td>
          <td class="num"><?= pct($p['sellthrough']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- 4. Highest-Profit Products -->
  <div class="section">
    <h2>Highest-Profit Products</h2>
    <div class="table-wrap">
    <table class="report">
      <thead><tr><th class="num">Rank</th><th>Product</th><th class="num">Qty Sold</th><th class="num">Total Profit</th><th class="num">Margin %</th></tr></thead>
      <tbody>
      <?php foreach (array_values($by_profit) as $i => $p): ?>
        <tr>
          <td class="num"><?= $i+1 ?></td>
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td class="num"><?= $p['qty_sold'] ?></td>
          <td class="num"><?= rm($p['profit']) ?></td>
          <td class="num"><?= pct($p['margin']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <!-- 5. Slow-Moving Products -->
  <div class="section">
    <h2>Slow-Moving Products <small style="font-weight:normal;color:#888">(sell-through &lt; 50% or zero sales)</small></h2>
    <?php if (empty($slow)): ?>
      <p class="muted">No slow-moving products in this period.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="report">
      <thead><tr><th>Product</th><th class="num">Opening Stock</th><th class="num">Qty Sold</th><th class="num">Stock Left</th><th class="num">Sell-through %</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($slow as $p):
        $at = $alltime[$p['id']] ?? ['days_sold'=>0,'total_sold'=>0];
        if ($p['stock_now'] == 0) {
          $tag = '<span class="tag-restock">Restock</span>';
        } elseif ($at['days_sold'] >= 5 && $p['sellthrough'] < 25 && $p['stock_now'] > 0) {
          $tag = '<span class="tag-reduce">Reduce / Stop</span>';
        } else {
          $tag = '<span class="tag-monitor">Monitor</span>';
        }
      ?>
        <tr>
          <td><?= htmlspecialchars($p['name']) ?></td>
          <td class="num"><?= $p['opening'] ?></td>
          <td class="num"><?= $p['qty_sold'] ?></td>
          <td class="num"><?= $p['stock_now'] ?></td>
          <td class="num"><?= pct($p['sellthrough']) ?></td>
          <td><?= $tag ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- 6. Sales by Day -->
  <div class="section">
    <h2>Sales by Day</h2>
    <?php if (empty($days)): ?><p class="muted">No data.</p><?php else: ?>
    <div class="table-wrap">
    <table class="report">
      <thead><tr><th>Date</th><th class="num">Sales</th><th class="num">Cost</th><th class="num">Gross Profit</th><th class="num">Items Sold</th><th class="num">Transactions</th><th class="num">Avg Sale</th></tr></thead>
      <tbody>
      <?php foreach ($days as $d): $gp = $d['sales'] - $d['cost']; ?>
        <tr>
          <td><?= $d['day'] ?></td>
          <td class="num"><?= rm($d['sales']) ?></td>
          <td class="num"><?= rm($d['cost']) ?></td>
          <td class="num"><?= rm($gp) ?></td>
          <td class="num"><?= $d['units'] ?></td>
          <td class="num"><?= $d['txn'] ?></td>
          <td class="num"><?= rm($d['txn'] > 0 ? $d['sales'] / $d['txn'] : 0) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- 7. Sales by Hour -->
  <div class="section">
    <h2>Sales by Hour</h2>
    <?php if (empty($hours)): ?><p class="muted">No data.</p><?php else: ?>
    <div class="table-wrap">
    <table class="report">
      <thead><tr><th>Hour</th><th class="num">Sales</th><th class="num">Items Sold</th><th class="num">Transactions</th><th class="num">Gross Profit</th></tr></thead>
      <tbody>
      <?php foreach ($hours as $h): ?>
        <tr>
          <td><?= str_pad($h['hr'],2,'0',STR_PAD_LEFT) ?>:00 – <?= str_pad($h['hr'],2,'0',STR_PAD_LEFT) ?>:59</td>
          <td class="num"><?= rm($h['sales']) ?></td>
          <td class="num"><?= $h['units'] ?></td>
          <td class="num"><?= $h['txn'] ?></td>
          <td class="num"><?= rm($h['profit']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- 8. Transaction History -->
  <div class="section">
    <h2>Transaction History</h2>
    <?php if (empty($txns)): ?><p class="muted">No transactions.</p><?php else: ?>
    <div class="table-wrap">
    <table class="report">
      <thead><tr><th>Date / Time</th><th>Item</th><th class="num">Quantity</th><th class="num">Total</th><th class="num">Profit</th></tr></thead>
      <tbody>
      <?php foreach ($txns as $t): ?>
        <tr>
          <td><?= $t['sold_at'] ?></td>
          <td><?= htmlspecialchars($t['name']) ?></td>
          <td class="num"><?= $t['quantity_sold'] ?></td>
          <td class="num"><?= rm($t['total']) ?></td>
          <td class="num"><?= rm($t['profit']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </div>

  <script>
  document.querySelectorAll('table.report').forEach(tbl => {
    tbl.querySelectorAll('thead th').forEach((th, ci) => {
      th.addEventListener('click', () => {
        const tbody = tbl.querySelector('tbody');
        const rows  = Array.from(tbody.querySelectorAll('tr'));
        const asc   = th.dataset.sort !== 'asc';
        th.dataset.sort = asc ? 'asc' : 'desc';
        rows.sort((a, b) => {
          const av = a.cells[ci]?.innerText.replace(/[^0-9.\-]/g,'') || '';
          const bv = b.cells[ci]?.innerText.replace(/[^0-9.\-]/g,'') || '';
          const an = parseFloat(av), bn = parseFloat(bv);
          if (!isNaN(an) && !isNaN(bn)) return asc ? an-bn : bn-an;
          return asc ? av.localeCompare(bv) : bv.localeCompare(av);
        });
        rows.forEach(r => tbody.appendChild(r));
      });
    });
  });
  </script>

<?php elseif ($view === 'daily'): ?>
  <?php if (empty($rows)): ?>
    <p class="muted">No sales in this period.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Units Sold</th><th>Revenue (RM)</th><th>Profit (RM)</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= $r['day'] ?></td><td><?= $r['units'] ?></td><td><?= number_format($r['revenue'],2) ?></td><td><?= number_format($r['profit'],2) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>

<?php else: ?>
  <?php if (empty($rows)): ?>
    <p class="muted">No sales in this period.</p>
  <?php else: ?>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Date &amp; Time</th><th>Item</th><th>Qty</th><th>Buy (RM)</th><th>Sell (RM)</th><th>Profit (RM)</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= $r['sold_at'] ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= $r['quantity_sold'] ?></td>
        <td><?= number_format($r['buy_price'],2) ?></td>
        <td><?= number_format($r['sell_price'],2) ?></td>
        <td><?= number_format($r['profit'],2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
<?php endif; ?>

</div>
</body>
</html>
