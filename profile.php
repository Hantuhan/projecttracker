<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash('error', 'Name is required.');
            redirect('profile.php');
        }
        $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $user['id']]);
        $_SESSION['user']['name'] = $name;
        flash('success', 'Profile updated.');
        redirect('profile.php');
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password'])) {
            flash('error', 'Current password is incorrect.');
            redirect('profile.php');
        }
        if (strlen($new) < 6) {
            flash('error', 'New password must be at least 6 characters.');
            redirect('profile.php');
        }
        if ($new !== $confirm) {
            flash('error', 'New passwords do not match.');
            redirect('profile.php');
        }
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        flash('success', 'Password changed.');
        redirect('profile.php');
    }
}

$pageTitle = 'Profile';
$currentPage = 'profile';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Profile</h1>
    <p class="muted">Update your name or password.</p>
  </div>
</div>

<div class="two-col">
  <section class="panel">
    <h2>Account</h2>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile">
      <label>Name <input name="name" value="<?= e($user['name']) ?>" required></label>
      <label>Email <input value="<?= e($user['email']) ?>" disabled></label>
      <label>Role <input value="<?= e($user['role']) ?>" disabled></label>
      <button class="btn btn-primary" type="submit">Save profile</button>
    </form>
  </section>

  <section class="panel">
    <h2>Change password</h2>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <label>Current password <input type="password" name="current_password" required></label>
      <label>New password <input type="password" name="new_password" minlength="6" required></label>
      <label>Confirm new password <input type="password" name="confirm_password" minlength="6" required></label>
      <button class="btn btn-primary" type="submit">Update password</button>
    </form>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
