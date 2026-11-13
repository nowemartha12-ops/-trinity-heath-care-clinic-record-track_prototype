<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['clinician']);

// Choose the period: daily, weekly (last 7 days) or monthly (this month)
$period = $_GET['period'] ?? 'daily';
if ($period === 'weekly') {
    $start = date('Y-m-d', strtotime('-6 days'));
    $label = 'Weekly report (last 7 days)';
} elseif ($period === 'monthly') {
    $start = date('Y-m-01');
    $label = 'Monthly report (this month)';
} else {
    $period = 'daily';
    $start = date('Y-m-d');
    $label = 'Daily report (today)';
}

function one($pdo, $sql, $start) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start]);
    return $stmt->fetchColumn();
}

$visits   = one($pdo, "SELECT COUNT(*) FROM visit WHERE DATE(visit_date) >= ?", $start);
$seen     = one($pdo, "SELECT COUNT(DISTINCT patient_id) FROM visit WHERE DATE(visit_date) >= ?", $start);
$newPat   = one($pdo, "SELECT COUNT(*) FROM patient WHERE DATE(created_at) >= ?", $start);
$labTotal = one($pdo, "SELECT COUNT(*) FROM lab_request l JOIN visit v ON v.visit_id = l.visit_id WHERE DATE(v.visit_date) >= ?", $start);
$labDone  = one($pdo, "SELECT COUNT(*) FROM lab_request l JOIN visit v ON v.visit_id = l.visit_id WHERE l.status = 'Done' AND DATE(v.visit_date) >= ?", $start);
$income   = one($pdo, "SELECT COALESCE(SUM(amount),0) FROM payment WHERE DATE(payment_date) >= ?", $start);

$stmt = $pdo->prepare("SELECT s.service_name, COUNT(DISTINCT v.visit_id) AS visits, COALESCE(SUM(p.amount),0) AS income
                       FROM visit v
                       JOIN service s ON s.service_id = v.service_id
                       LEFT JOIN payment p ON p.visit_id = v.visit_id AND DATE(p.payment_date) >= ?
                       WHERE DATE(v.visit_date) >= ?
                       GROUP BY s.service_id ORDER BY income DESC");
$stmt->execute([$start, $start]);
$perService = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT d.drug_name, SUM(pr.quantity) AS qty
                       FROM prescription pr JOIN drug d ON d.drug_id = pr.drug_id
                       WHERE pr.status = 'Dispensed' AND DATE(pr.dispensed_date) >= ?
                       GROUP BY d.drug_id ORDER BY qty DESC");
$stmt->execute([$start]);
$drugsUsed = $stmt->fetchAll();

$lowDrugs = $pdo->query("SELECT drug_name, quantity_in_stock, reorder_level FROM drug
                         WHERE quantity_in_stock <= reorder_level ORDER BY drug_name")->fetchAll();

$title = 'Reports';
include __DIR__ . '/includes/header.php';
?>
<h1>Reports</h1>
<p class="noprint">
  <a class="btn" href="reports.php?period=daily">Daily</a>
  <a class="btn" href="reports.php?period=weekly">Weekly</a>
  <a class="btn" href="reports.php?period=monthly">Monthly</a>
  <button onclick="window.print()">Print</button>
</p>

<h2><?= e($label) ?></h2>
<div class="cards">
  <div class="card"><div class="num"><?= e($visits) ?></div>Visits</div>
  <div class="card"><div class="num"><?= e($seen) ?></div>Different patients seen</div>
  <div class="card"><div class="num"><?= e($newPat) ?></div>New patients</div>
  <div class="card"><div class="num"><?= e($labDone) ?> / <?= e($labTotal) ?></div>Lab tests done / requested</div>
  <div class="card"><div class="num"><?= number_format($income) ?></div>Income (UGX)</div>
</div>

<h2>Services</h2>
<table>
  <tr><th>Service</th><th>Visits</th><th>Income (UGX)</th></tr>
  <?php foreach ($perService as $r): ?>
    <tr><td><?= e($r['service_name']) ?></td><td><?= e($r['visits']) ?></td><td><?= number_format($r['income']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$perService): ?><tr><td colspan="3">No visits in this period.</td></tr><?php endif; ?>
</table>

<h2>Medicines dispensed</h2>
<table>
  <tr><th>Medicine</th><th>Quantity</th></tr>
  <?php foreach ($drugsUsed as $r): ?>
    <tr><td><?= e($r['drug_name']) ?></td><td><?= e($r['qty']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$drugsUsed): ?><tr><td colspan="2">No medicine dispensed in this period.</td></tr><?php endif; ?>
</table>

<h2>Drugs to reorder</h2>
<table>
  <tr><th>Drug</th><th>In stock</th><th>Reorder level</th></tr>
  <?php foreach ($lowDrugs as $d): ?>
    <tr class="low"><td><?= e($d['drug_name']) ?></td><td><?= e($d['quantity_in_stock']) ?></td><td><?= e($d['reorder_level']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$lowDrugs): ?><tr><td colspan="3">All drugs are above the reorder level.</td></tr><?php endif; ?>
</table>
<?php include __DIR__ . '/includes/footer.php'; ?>