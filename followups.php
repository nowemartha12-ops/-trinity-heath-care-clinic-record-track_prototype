<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['clinician']);

// Patients whose follow-up date has passed and who have not come back since
$overdue = $pdo->query("SELECT p.patient_id, p.full_name, p.phone, v.next_visit_date
                        FROM visit v
                        JOIN patient p ON p.patient_id = v.patient_id
                        WHERE v.next_visit_date IS NOT NULL
                          AND v.next_visit_date < CURDATE()
                          AND NOT EXISTS (SELECT 1 FROM visit v2
                                          WHERE v2.patient_id = v.patient_id AND v2.visit_date > v.visit_date)
                        ORDER BY v.next_visit_date")->fetchAll();

// Patients expected in the coming days
$upcoming = $pdo->query("SELECT p.patient_id, p.full_name, p.phone, v.next_visit_date
                         FROM visit v
                         JOIN patient p ON p.patient_id = v.patient_id
                         WHERE v.next_visit_date >= CURDATE()
                         ORDER BY v.next_visit_date")->fetchAll();

$title = 'Follow-ups';
include __DIR__ . '/includes/header.php';
?>
<h1>Patient follow-ups</h1>

<h2>Missed follow-up (call these patients)</h2>
<table>
  <tr><th>Patient</th><th>Phone</th><th>Was due on</th><th></th></tr>
  <?php foreach ($overdue as $r): ?>
    <tr class="low">
      <td><?= e($r['full_name']) ?></td>
      <td><?= e($r['phone']) ?></td>
      <td><?= e($r['next_visit_date']) ?></td>
      <td><a href="patient_view.php?id=<?= e($r['patient_id']) ?>">Open record</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$overdue): ?><tr><td colspan="4">No missed follow-ups.</td></tr><?php endif; ?>
</table>

<h2>Coming up</h2>
<table>
  <tr><th>Patient</th><th>Phone</th><th>Expected on</th><th></th></tr>
  <?php foreach ($upcoming as $r): ?>
    <tr>
      <td><?= e($r['full_name']) ?></td>
      <td><?= e($r['phone']) ?></td>
      <td><?= e($r['next_visit_date']) ?></td>
      <td><a href="patient_view.php?id=<?= e($r['patient_id']) ?>">Open record</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$upcoming): ?><tr><td colspan="4">No follow-ups planned.</td></tr><?php endif; ?>
</table>
<?php include __DIR__ . '/includes/footer.php'; ?>