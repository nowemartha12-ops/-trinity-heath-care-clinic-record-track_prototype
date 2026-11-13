<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['finance']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitId = (int)$_POST['visit_id'];
    $amount = (int)$_POST['amount'];
    $method = in_array($_POST['method'], ['Cash', 'Mobile Money', 'Other']) ? $_POST['method'] : 'Cash';
    $balance = visit_money($pdo, $visitId)['balance'];

    if ($amount <= 0 || $amount > $balance) {
        flash('The amount must be more than 0 and not more than the balance.');
        header('Location: payments.php');
        exit;
    }
    $pdo->prepare("INSERT INTO payment (visit_id, received_by, amount, method) VALUES (?,?,?,?)")
        ->execute([$visitId, $_SESSION['staff_id'], $amount, $method]);
    $paymentId = $pdo->lastInsertId();
    log_action($pdo, "Recorded payment #$paymentId of $amount for visit #$visitId");
    header("Location: receipt.php?id=$paymentId");
    exit;
}

// Recent visits; we show only those with a balance
$visits = $pdo->query("SELECT v.visit_id, v.visit_date, p.full_name, s.service_name
                       FROM visit v
                       JOIN patient p ON p.patient_id = v.patient_id
                       JOIN service s ON s.service_id = v.service_id
                       ORDER BY v.visit_date DESC LIMIT 100")->fetchAll();
$unpaid = [];
foreach ($visits as $v) {
    $m = visit_money($pdo, $v['visit_id']);
    if ($m['balance'] > 0) {
        $v['bill'] = $m['bill'];
        $v['paid'] = $m['paid'];
        $v['balance'] = $m['balance'];
        $unpaid[] = $v;
    }
}

$today = $pdo->query("SELECT pay.payment_id, pay.amount, pay.method, pay.payment_date, p.full_name
                      FROM payment pay
                      JOIN visit v ON v.visit_id = pay.visit_id
                      JOIN patient p ON p.patient_id = v.patient_id
                      WHERE DATE(pay.payment_date) = CURDATE() ORDER BY pay.payment_id DESC")->fetchAll();

$title = 'Payments';
include __DIR__ . '/includes/header.php';
?>
<h1>Payments</h1>

<h2>Unpaid bills</h2>
<table>
  <tr><th>Visit</th><th>Patient</th><th>Service</th><th>Bill</th><th>Paid</th><th>Balance</th><th>Receive payment</th></tr>
  <?php foreach ($unpaid as $v): ?>
    <tr>
      <td>#<?= e($v['visit_id']) ?><br><span class="small"><?= e(date('d M', strtotime($v['visit_date']))) ?></span></td>
      <td><?= e($v['full_name']) ?></td>
      <td><?= e($v['service_name']) ?></td>
      <td><?= number_format($v['bill']) ?></td>
      <td><?= number_format($v['paid']) ?></td>
      <td class="warn"><?= number_format($v['balance']) ?></td>
      <td>
        <form method="post" class="inline">
          <input type="hidden" name="visit_id" value="<?= e($v['visit_id']) ?>">
          <input type="number" name="amount" value="<?= e($v['balance']) ?>" min="1" max="<?= e($v['balance']) ?>" style="width:100px">
          <select name="method"><option>Cash</option><option>Mobile Money</option><option>Other</option></select>
          <button type="submit">Pay</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$unpaid): ?><tr><td colspan="7">No unpaid bills.</td></tr><?php endif; ?>
</table>

<h2>Payments received today</h2>
<table>
  <tr><th>Receipt</th><th>Patient</th><th>Amount</th><th>Method</th><th>Time</th></tr>
  <?php $sum = 0; foreach ($today as $p): $sum += $p['amount']; ?>
    <tr>
      <td><a href="receipt.php?id=<?= e($p['payment_id']) ?>">#<?= e($p['payment_id']) ?></a></td>
      <td><?= e($p['full_name']) ?></td>
      <td><?= number_format($p['amount']) ?></td>
      <td><?= e($p['method']) ?></td>
      <td><?= e(date('H:i', strtotime($p['payment_date']))) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$today): ?><tr><td colspan="5">No payments today.</td></tr><?php endif; ?>
</table>
<p><b>Total today: <?= money($sum) ?></b></p>
<?php include __DIR__ . '/includes/footer.php'; ?>