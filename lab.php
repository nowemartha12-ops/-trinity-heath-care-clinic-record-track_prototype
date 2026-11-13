<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['lab']);

// Record a result
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = trim($_POST['result'] ?? '');
    $requestId = (int)$_POST['request_id'];
    if ($result === '') {
        flash('Please type the result.');
    } else {
        $stmt = $pdo->prepare("UPDATE lab_request SET result = ?, status = 'Done', result_date = NOW()
                               WHERE request_id = ? AND status = 'Pending'");
        $stmt->execute([$result, $requestId]);
        log_action($pdo, "Recorded result for lab request #$requestId");
        flash('Result saved.');
    }
    header('Location: lab.php');
    exit;
}

$pending = $pdo->query("SELECT l.request_id, l.test_name, v.visit_date, p.full_name, p.patient_id, st.full_name AS requested_by
                        FROM lab_request l
                        JOIN visit v ON v.visit_id = l.visit_id
                        JOIN patient p ON p.patient_id = v.patient_id
                        JOIN staff st ON st.staff_id = l.requested_by
                        WHERE l.status = 'Pending' ORDER BY v.visit_date")->fetchAll();

$done = $pdo->query("SELECT l.test_name, l.result, l.result_date, p.full_name
                     FROM lab_request l
                     JOIN visit v ON v.visit_id = l.visit_id
                     JOIN patient p ON p.patient_id = v.patient_id
                     WHERE l.status = 'Done' ORDER BY l.result_date DESC LIMIT 15")->fetchAll();

$title = 'Laboratory';
include __DIR__ . '/includes/header.php';
?>
<h1>Laboratory and scans</h1>

<h2>Waiting for results</h2>
<table>
  <tr><th>Patient</th><th>Test</th><th>Requested by</th><th>Enter result</th></tr>
  <?php foreach ($pending as $r): ?>
    <tr>
      <td><?= e($r['full_name']) ?> <span class="small">(no. <?= e($r['patient_id']) ?>)</span></td>
      <td><?= e($r['test_name']) ?></td>
      <td><?= e($r['requested_by']) ?></td>
      <td>
        <form method="post" class="inline">
          <input type="hidden" name="request_id" value="<?= e($r['request_id']) ?>">
          <input type="text" name="result" placeholder="Result" required>
          <button type="submit">Save</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$pending): ?><tr><td colspan="4">No pending requests.</td></tr><?php endif; ?>
</table>

<h2>Recently completed</h2>
<table>
  <tr><th>Patient</th><th>Test</th><th>Result</th><th>Date</th></tr>
  <?php foreach ($done as $r): ?>
    <tr><td><?= e($r['full_name']) ?></td><td><?= e($r['test_name']) ?></td><td><?= e($r['result']) ?></td><td><?= e($r['result_date']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$done): ?><tr><td colspan="4">Nothing yet.</td></tr><?php endif; ?>
</table>
<?php include __DIR__ . '/includes/footer.php'; ?>