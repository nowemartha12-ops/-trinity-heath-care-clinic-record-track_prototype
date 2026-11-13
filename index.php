<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$patients_total = $pdo->query("SELECT COUNT(*) FROM patient")->fetchColumn();
$visits_today   = $pdo->query("SELECT COUNT(*) FROM visit WHERE DATE(visit_date) = CURDATE()")->fetchColumn();
$pending_lab    = $pdo->query("SELECT COUNT(*) FROM lab_request WHERE status = 'Pending'")->fetchColumn();
$income_today   = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payment WHERE DATE(payment_date) = CURDATE()")->fetchColumn();
$income_month   = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payment WHERE payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();

// Stock alerts (out, low, expiring soon, expired)
$drugs = $pdo->query("SELECT * FROM drug ORDER BY drug_name")->fetchAll();
$alerts = [];
$count = ['Out' => 0, 'Low' => 0, 'Expiring' => 0, 'Expired' => 0];
foreach ($drugs as $d) {
    $st = drug_status($d);
    if ($st[0] === 'OK') {
        continue;
    }
    $d['status'] = $st;
    $alerts[] = $d;
    if ($st[0] === 'Out') $count['Out']++;
    elseif ($st[0] === 'Low') $count['Low']++;
    elseif ($st[0] === 'Expired') $count['Expired']++;
    else $count['Expiring']++;
}

$title = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>
<h1>Dashboard</h1>

<div class="cards">
  <div class="card"><div class="num"><?= e($visits_today) ?></div><div class="lbl">Visits today</div></div>
  <div class="card"><div class="num"><?= e($patients_total) ?></div><div class="lbl">Registered patients</div></div>
  <div class="card"><div class="num"><?= e($pending_lab) ?></div><div class="lbl">Pending lab requests</div></div>
  <div class="card"><div class="num"><?= number_format($income_today) ?></div><div class="lbl">Income today (UGX)</div></div>
  <div class="card"><div class="num"><?= number_format($income_month) ?></div><div class="lbl">Income this month (UGX)</div></div>
</div>

<?php if (can(['dispensary', 'clinician'])): ?>
<h2>Stock alerts</h2>
<div class="cards">
  <div class="card red"><div class="num"><?= $count['Out'] ?></div><div class="lbl">Out of stock</div></div>
  <div class="card amber"><div class="num"><?= $count['Low'] ?></div><div class="lbl">Low stock</div></div>
  <div class="card amber"><div class="num"><?= $count['Expiring'] ?></div><div class="lbl">Expiring within 30 days</div></div>
  <div class="card red"><div class="num"><?= $count['Expired'] ?></div><div class="lbl">Expired</div></div>
</div>
<div class="panel">
  <table>
    <tr><th>Medicine</th><th>In stock</th><th>Reorder level</th><th>Expiry date</th><th>Status</th></tr>
    <?php foreach ($alerts as $d): ?>
      <tr>
        <td><?= e($d['drug_name']) ?></td>
        <td><?= e($d['quantity_in_stock']) ?></td>
        <td><?= e($d['reorder_level']) ?></td>
        <td><?= e($d['expiry_date']) ?></td>
        <td><?= badge($d['status'][0], $d['status'][1]) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$alerts): ?><tr><td colspan="5">No stock problems. All medicines are fine.</td></tr><?php endif; ?>
  </table>
</div>

<h2>Recent stock activity</h2>
<div class="panel">
<?php
$recent = $pdo->query("SELECT m.movement_type, m.quantity, m.created_at, d.drug_name
                       FROM stock_movement m JOIN drug d ON d.drug_id = m.drug_id
                       ORDER BY m.movement_id DESC LIMIT 8")->fetchAll();
foreach ($recent as $r):
    $in = $r['quantity'] > 0; ?>
  <div class="act">
    <span class="ico <?= $in ? 'in' : 'out' ?>"><?= $in ? '+' : '-' ?></span>
    <span><b><?= e($r['drug_name']) ?></b> - <?= e(strtolower($r['movement_type'])) ?> <?= $in ? '+' : '' ?><?= e($r['quantity']) ?></span>
    <span class="small" style="margin-left:auto"><?= e(date('d M H:i', strtotime($r['created_at']))) ?></span>
  </div>
<?php endforeach; ?>
<?php if (!$recent): ?>No stock activity yet.<?php endif; ?>
</div>
<?php endif; ?>

<?php if (can(['clinician'])):
    // Management view: visits in the last 7 days
    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $days[date('Y-m-d', strtotime("-$i days"))] = 0;
    }
    $rows = $pdo->query("SELECT DATE(visit_date) AS d, COUNT(*) AS c FROM visit
                         WHERE visit_date >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(visit_date)")->fetchAll();
    foreach ($rows as $r) {
        if (isset($days[$r['d']])) {
            $days[$r['d']] = (int)$r['c'];
        }
    }
    $maxDay = max($days);

    $perService = $pdo->query("SELECT s.service_name, SUM(p.amount) AS total
                               FROM payment p
                               JOIN visit v ON v.visit_id = p.visit_id
                               JOIN service s ON s.service_id = v.service_id
                               WHERE p.payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
                               GROUP BY s.service_id ORDER BY total DESC")->fetchAll();
    $maxService = $perService ? $perService[0]['total'] : 0;
?>
<h2>Management view (trends)</h2>
<div class="grid2">
  <div class="panel">
    <b>Visits in the last 7 days</b>
    <table>
      <?php foreach ($days as $day => $c): ?>
        <tr><td style="width:110px"><?= e(date('D d M', strtotime($day))) ?></td><td><?= bar($c, $maxDay) ?></td><td style="width:30px"><?= e($c) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="panel">
    <b>Income per service this month</b>
    <table>
      <?php foreach ($perService as $r): ?>
        <tr><td><?= e($r['service_name']) ?></td><td><?= bar($r['total'], $maxService) ?></td><td style="width:90px"><?= number_format($r['total']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$perService): ?><tr><td>No payments yet this month.</td></tr><?php endif; ?>
    </table>
  </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>