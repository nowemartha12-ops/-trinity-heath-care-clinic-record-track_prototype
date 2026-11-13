<?php
require_once __DIR__ . '/includes/auth.php';
require_role([]);   // management only

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $roles = ['management', 'clinician', 'lab', 'dispensary', 'finance'];

    if ($name === '' || $username === '' || strlen($password) < 6 || !in_array($role, $roles)) {
        flash('Fill in all fields. The password must have at least 6 characters.');
    } else {
        try {
            $pdo->prepare("INSERT INTO staff (department_id, full_name, role, username, password_hash) VALUES (?,?,?,?,?)")
                ->execute([$_POST['department_id'] ?: null, $name, $role, $username, password_hash($password, PASSWORD_DEFAULT)]);
            log_action($pdo, "Created user $username ($role)");
            flash('User created.');
        } catch (PDOException $ex) {
            flash('That username is already used.');
        }
    }
    header('Location: users.php');
    exit;
}

$staff = $pdo->query("SELECT s.full_name, s.username, s.role, d.department_name
                      FROM staff s LEFT JOIN department d ON d.department_id = s.department_id
                      ORDER BY s.staff_id")->fetchAll();
$departments = $pdo->query("SELECT * FROM department ORDER BY department_name")->fetchAll();
$log = $pdo->query("SELECT a.created_at, a.action, s.full_name
                    FROM audit_log a LEFT JOIN staff s ON s.staff_id = a.staff_id
                    ORDER BY a.log_id DESC LIMIT 25")->fetchAll();

$title = 'Users';
include __DIR__ . '/includes/header.php';
?>
<h1>Users</h1>
<table>
  <tr><th>Name</th><th>Username</th><th>Role</th><th>Department</th></tr>
  <?php foreach ($staff as $s): ?>
    <tr><td><?= e($s['full_name']) ?></td><td><?= e($s['username']) ?></td><td><?= e($s['role']) ?></td><td><?= e($s['department_name']) ?></td></tr>
  <?php endforeach; ?>
</table>

<h2>Add a user</h2>
<form method="post" class="box">
  <div class="grid2">
    <div><label>Full name</label><input type="text" name="full_name" required></div>
    <div><label>Username</label><input type="text" name="username" required></div>
    <div><label>Password</label><input type="password" name="password" required></div>
    <div><label>Role</label>
      <select name="role">
        <option value="clinician">clinician (Medical Officer / nurse)</option>
        <option value="lab">lab (laboratory and radiography)</option>
        <option value="dispensary">dispensary</option>
        <option value="finance">finance</option>
        <option value="management">management</option>
      </select></div>
  </div>
  <label>Department</label>
  <select name="department_id">
    <option value="">None</option>
    <?php foreach ($departments as $d): ?><option value="<?= e($d['department_id']) ?>"><?= e($d['department_name']) ?></option><?php endforeach; ?>
  </select>
  <p><button type="submit">Create user</button></p>
</form>

<h2>Activity log (last 25 actions)</h2>
<table>
  <tr><th>Time</th><th>User</th><th>Action</th></tr>
  <?php foreach ($log as $l): ?>
    <tr><td><?= e($l['created_at']) ?></td><td><?= e($l['full_name'] ?? 'System') ?></td><td><?= e($l['action']) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php include __DIR__ . '/includes/footer.php'; ?>