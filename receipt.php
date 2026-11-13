<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['finance']);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT pay.*, p.full_name, s.service_name, st.full_name AS cashier
                       FROM payment pay
                       JOIN visit v ON v.visit_id = pay.visit_id
                       JOIN patient p ON p.patient_id = v.patient_id
                       JOIN service s ON s.service_id = v.service_id
                       JOIN staff st ON st.staff_id = pay.received_by
                       WHERE pay.payment_id = ?");
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) {
    die('Receipt not found.');
}
$money = visit_money($pdo, $r['visit_id']);

$title = 'Receipt #' . $id;
include __DIR__ . '/includes/header.php';
?>
<div class="receipt">
  <h2 style="text-align:center;border:0">Trinity Health Care Clinic</h2>
  <p style="text-align:center">PAYMENT RECEIPT</p>
  <p>Receipt no: <b>#<?= e($r['payment_id']) ?></b><br>
     Date: <?= e(date('d M Y H:i', strtotime($r['payment_date']))) ?><br>
     Patient: <?= e($r['full_name']) ?><br>
     Visit: #<?= e($r['visit_id']) ?> (<?= e($r['service_name']) ?>)</p>
  <hr>
  <p>Amount paid: <b><?= money($r['amount']) ?></b> (<?= e($r['method']) ?>)<br>
     Total bill: <?= money($money['bill']) ?><br>
     Balance remaining: <?= money($money['balance']) ?></p>
  <hr>
  <p class="small">Received by <?= e($r['cashier']) ?></p>
  <p class="noprint"><button onclick="window.print()">Print</button> <a class="btn" href="payments.php">Back</a></p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>