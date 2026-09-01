<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = (string) ($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $result = attempt_login($pdo, $email, $password);
    if ($result === true) {
        redirect('index.php');
    }
    $error = match ($result) {
        'pending' => 'Your account is waiting for admin approval.',
        'rejected' => 'Your invite was declined. Contact an admin.',
        default => 'Invalid email or password.',
    };
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-card">
  <div class="brand auth-brand">
    <span class="brand-mark"></span>
    <span class="brand-name"><?= e(setting($pdo, 'app_name', 'Project Tracker')) ?></span>
  </div>
  <h1>Sign in</h1>
  <p class="muted">Track projects with your team.</p>
  <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Email <input type="email" name="email" required autofocus></label>
    <label>Password <input type="password" name="password" required></label>
    <button class="btn btn-primary" type="submit">Log in</button>
  </form>
  <p class="muted tiny" style="margin-top:1rem">Need an account? <a href="request-access.php">Request access</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
