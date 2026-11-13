<?php
require_once __DIR__ . '/includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE username = ?");
    $stmt->execute([trim($_POST['username'] ?? '')]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['staff_id'] = $user['staff_id'];
        $_SESSION['name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        log_action($pdo, 'Logged in');
        header('Location: index.php');
        exit;
    }
    $error = 'Wrong username or password.';
}

$title = 'Login';
include __DIR__ . '/includes/header.php';
?>
<div class="box" style="max-width:380px;margin:60px auto;">
  <h1>Trinity Health Care Clinic Record Track</h1>
  <?php if ($error): ?><p class="warn"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <label>Username</label>
    <input type="text" name="username" required autofocus>
    <label>Password</label>
    <input type="password" name="password" required>
    <p><button type="submit">Log in</button></p>
  </form>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>