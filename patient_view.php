<?php
require_once __DIR__ . '/includes/auth.php';
require_role(['clinician']);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM patient WHERE patient_id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) {
    die('Patient not found. <a href="patients.php">Back</a>');
}

// Start a new visit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("INSERT INTO visit (patient_id, staff_id, service_id, symptoms) VALUES (?,?,?,?)");
    $stmt->execute([$id, $_SESSION['staff_id'], (int)$_POST['service_id'], trim($_POST['symptoms'] ?? '')]);
    $visitId = $pdo->lastInsertId();
    log_action($pdo, "Started visit #$visitId for patient #$id");
    header("Location: visit.php?id=$visitId");
    exit;
}

$services = $pdo->query("SELECT * FROM service ORDER BY service_name")->fetchAll();

$stmt = $pdo->prepare("SELECT v.*, s.service_name, st.full_name AS doctor
                       FROM visit v
                       JOIN service s ON s.service_id = v.service_id
                       JOIN staff st ON st.staff_id = v.staff_id
                       WHERE v.patient_id = ? ORDER BY v.visit_date DESC");
$stmt->execute([$id]);
$visits = $stmt->fetchAll();

$age = '';
if ($patient['date_of_birth']) {
    $age = date_diff(date_create($patient['date_of_birth']), date_create('today'))->y . ' years';
}

$title = 'Patient ' . $patient['full_name'];
include __DIR__ . '/includes/header.php';
?>
<h1><?= e($patient['full_name']) ?> <span class="small">(Patient no. <?= e($patient['patient_id']) ?>)</span></h1>
<div class="box">
  Gender: <b><?= e($patient['gender']) ?></b> &nbsp;|&nbsp;
  Age: <b><?= e($age ?: 'not recorded') ?></b> &nbsp;|&nbsp;
  Phone: <b><?= e($patient['phone']) ?></b> &nbsp;|&nbsp;
  Address: <b><?= e($patient['address']) ?></b>
</div>

<h2>Start a new visit</h2>
<form method="post" class="box">
  <label>Service</label>
  <select name="service_id">
    <?php foreach ($services as $s): ?>
      <option value="<?= e($s['service_id']) ?>"><?= e($s['service_name']) ?> (<?= money($s['fee']) ?>)</option>
    <?php endforeach; ?>
  </select>
  <label>Symptoms</label>
  <textarea name="symptoms" rows="2"></textarea>
  <p><button type="submit">Start visit</button></p>
</form>

<h2>History</h2>
<?php foreach ($visits as $v): ?>
  <div class="box">
    <b><?= e(date('d M Y H:i', strtotime($v['visit_date']))) ?></b> - <?= e($v['service_name']) ?>
    <span class="small">(seen by <?= e($v['doctor']) ?>)</span>
    <a href="visit.php?id=<?= e($v['visit_id']) ?>">Open visit</a><br>
    Symptoms: <?= e($v['symptoms']) ?><br>
    Diagnosis: <?= e($v['diagnosis']) ?><br>
    <?php if ($v['next_visit_date']): ?>Next visit: <?= e($v['next_visit_date']) ?><br><?php endif; ?>
    <?php
      $stmt = $pdo->prepare("SELECT test_name, result, status FROM lab_request WHERE visit_id = ?");
      $stmt->execute([$v['visit_id']]);
      foreach ($stmt->fetchAll() as $l) {
          echo 'Test: ' . e($l['test_name']) . ' - ' . e($l['status'] === 'Done' ? $l['result'] : 'waiting for result') . '<br>';
      }
      $stmt = $pdo->prepare("SELECT d.drug_name, pr.dosage, pr.route, pr.quantity, pr.status
                             FROM prescription pr JOIN drug d ON d.drug_id = pr.drug_id WHERE pr.visit_id = ?");
      $stmt->execute([$v['visit_id']]);
      foreach ($stmt->fetchAll() as $r) {
          echo 'Medicine: ' . e($r['drug_name']) . ', ' . e($r['dosage']) . ' (' . e($r['route']) . '), qty ' . e($r['quantity']) . ' - ' . e($r['status']) . '<br>';
      }
    ?>
  </div>
<?php endforeach; ?>
<?php if (!$visits): ?><p>No visits yet.</p><?php endif; ?>
<p><a href="patients.php">&laquo; Back to patients</a></p>
<?php include __DIR__ . '/includes/footer.php'; ?>