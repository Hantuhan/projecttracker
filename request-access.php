<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '' || $email === '' || strlen($password) < 6) {
        $error = 'Name, email, and password (6+ chars) are required.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password, role, status)
                 VALUES (?, ?, ?, 'member', 'pending')"
            );
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

            // Notify admins if SMTP is configured
            $appName = setting($pdo, 'app_name', 'Project Tracker');
            $admins = $pdo->query("SELECT name, email FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
            foreach ($admins as $admin) {
                send_smtp_mail(
                    $pdo,
                    $admin['email'],
                    $admin['name'],
                    "[{$appName}] Access request from {$name}",
                    '<p><strong>' . e($name) . '</strong> (' . e($email) . ') requested access.</p>'
                    . '<p>Approve them under Team → Pending approval.</p>'
                );
            }

            $success = 'Request submitted. An admin must approve your account before you can sign in.';
        } catch (PDOException $e) {
            $error = 'Could not submit request. Email may already be registered.';
        }
    }
}

$pageTitle = 'Request access';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
  <div class="brand auth-brand">
    <span class="brand-mark"></span>
    <span class="brand-name"><?= e(setting($pdo, 'app_name', 'Project Tracker')) ?></span>
  </div>
  <h1>Request access</h1>
  <p class="muted">Submit your details. An admin will approve before you can log in.</p>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="flash flash-success"><?= e($success) ?></div><?php endif; ?>
  <?php if (!$success): ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Name <input name="name" required autofocus></label>
    <label>Email <input type="email" name="email" required></label>
    <label>Password <input type="password" name="password" minlength="6" required></label>
    <button class="btn btn-primary" type="submit">Request access</button>
  </form>
  <?php endif; ?>
  <p class="muted tiny" style="margin-top:1rem"><a href="login.php">Back to login</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
