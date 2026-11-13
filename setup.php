<?php
// Run this page ONCE after importing database.sql. It creates the demo users and some sample visits.
// Delete this file afterwards.
require_once __DIR__ . '/config.php';

$count = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
if ($count > 0) {
    die('Setup was already done. Delete setup.php. <a href="login.php">Go to login</a>');
}

// 1. Demo users (all use the password: password123)
$users = [
    ['Clinic Manager',    NULL, 'management', 'admin'],
    ['Medical Officer',   1,    'clinician',  'clinician'],
    ['Lab Technician',    2,    'lab',        'lab'],
    ['Pharmacy Officer', 4,   'dispensary', 'pharmacy'],
    ['Finance Officer',   5,    'finance',    'finance'],
];
$stmt = $pdo->prepare("INSERT INTO staff (department_id, full_name, role, username, password_hash) VALUES (?,?,?,?,?)");
foreach ($users as $u) {
    $stmt->execute([$u[1], $u[0], $u[2], $u[3], password_hash('password123', PASSWORD_DEFAULT)]);
}
$doctor = $pdo->query("SELECT staff_id FROM staff WHERE username='clinician'")->fetchColumn();
$finance = $pdo->query("SELECT staff_id FROM staff WHERE username='finance'")->fetchColumn();

// 2. Sample visits so the dashboard and reports are not empty
function add_visit($pdo, $doctor, $patient, $service, $daysAgo, $symptoms, $diagnosis, $nextInDays) {
    $date = date('Y-m-d', strtotime("-$daysAgo days")) . ' 09:30:00';
    $next = ($nextInDays === null) ? null : date('Y-m-d', strtotime(sprintf('%+d days', $nextInDays)));
    $pdo->prepare("INSERT INTO visit (patient_id, staff_id, service_id, visit_date, symptoms, diagnosis, next_visit_date) VALUES (?,?,?,?,?,?,?)")
        ->execute([$patient, $doctor, $service, $date, $symptoms, $diagnosis, $next]);
    return [$pdo->lastInsertId(), $date];
}
function add_lab($pdo, $doctor, $visit, $test, $result) {
    $status = $result === null ? 'Pending' : 'Done';
    $pdo->prepare("INSERT INTO lab_request (visit_id, requested_by, test_name, result, status, result_date) VALUES (?,?,?,?,?,?)")
        ->execute([$visit, $doctor, $test, $result, $status, $result === null ? null : date('Y-m-d H:i:s')]);
}
function add_rx($pdo, $visit, $drug, $dosage, $route, $qty, $dispensed, $date) {
    $pdo->prepare("INSERT INTO prescription (visit_id, drug_id, dosage, route, quantity, status, dispensed_date) VALUES (?,?,?,?,?,?,?)")
        ->execute([$visit, $drug, $dosage, $route, $qty, $dispensed ? 'Dispensed' : 'Pending', $dispensed ? $date : null]);
}
function add_pay($pdo, $finance, $visit, $amount, $date) {
    $pdo->prepare("INSERT INTO payment (visit_id, received_by, amount, method, payment_date) VALUES (?,?,?,?,?)")
        ->execute([$visit, $finance, $amount, 'Cash', $date]);
}

// visit: patient, service, days ago, symptoms, diagnosis, follow-up in days (negative = overdue)
list($v, $d) = add_visit($pdo, $doctor, 1, 1, 6, 'Fever and headache', 'Malaria', -2);
add_lab($pdo, $doctor, $v, 'Malaria test', 'Positive');
add_rx($pdo, $v, 3, '1 course over 3 days', 'Oral', 1, true, $d);
add_pay($pdo, $finance, $v, 28000, $d);

list($v, $d) = add_visit($pdo, $doctor, 2, 1, 5, 'Cough and chest pain', 'Chest infection', null);
add_rx($pdo, $v, 2, '1 capsule 3 times a day', 'Oral', 15, true, $d);
add_pay($pdo, $finance, $v, 27500, $d);

list($v, $d) = add_visit($pdo, $doctor, 3, 6, 4, 'Stomach pain', 'Gastritis', 5);
add_pay($pdo, $finance, $v, 40000, $d);

list($v, $d) = add_visit($pdo, $doctor, 4, 1, 3, 'Back pain', 'Muscle strain', 7);
add_rx($pdo, $v, 6, '1 injection', 'Injection', 1, true, $d);
add_pay($pdo, $finance, $v, 22500, $d);

list($v, $d) = add_visit($pdo, $doctor, 5, 1, 2, 'Diarrhoea', 'Mild dehydration', null);
add_rx($pdo, $v, 4, '1 sachet after each loose stool', 'Oral', 6, true, $d);
add_pay($pdo, $finance, $v, 26000, $d);

list($v, $d) = add_visit($pdo, $doctor, 1, 1, 1, 'Headache', 'Tension headache', null);
add_rx($pdo, $v, 1, '2 tablets 3 times a day', 'Oral', 12, false, $d);   // not paid, not dispensed

list($v, $d) = add_visit($pdo, $doctor, 2, 1, 0, 'Fever, body weakness', 'Waiting for lab result', 3);
add_lab($pdo, $doctor, $v, 'Typhoid test (Widal)', null);                 // pending lab request

$pdo->exec("INSERT INTO audit_log (staff_id, action) VALUES (NULL, 'System setup: demo users and sample data created')");
?>
<h2>Setup finished</h2>
<p>Demo users were created. All use the password <b>password123</b>:</p>
<ul>
  <li>admin (management)</li>
  <li>clinician (Medical Officer)</li>
  <li>lab (laboratory)</li>
  <li>pharmacy (dispensary)</li>
  <li>finance (finance)</li>
</ul>
<p><b>Now delete setup.php</b>, then <a href="login.php">go to the login page</a>.</p>