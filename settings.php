<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
$user = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'test') {
        $to = $user['email'];
        $ok = send_smtp_mail(
            $pdo,
            $to,
            $user['name'],
            'Project Tracker SMTP test',
            '<p>SMTP is working. Task update emails will send from this server.</p>'
        );
        flash($ok ? 'success' : 'error', $ok ? 'Test email sent to ' . $to : 'Test email failed. Check SMTP settings and Hostinger mail logs.');
        redirect('settings.php');
    }

    $keys = [
        'app_name',
        'smtp_host',
        'smtp_port',
        'smtp_user',
        'smtp_pass',
        'smtp_from_email',
        'smtp_from_name',
        'smtp_encryption',
        'notify_on_assign',
        'notify_on_status',
    ];

    foreach ($keys as $key) {
        if ($key === 'smtp_pass' && ($_POST[$key] ?? '') === '') {
            continue; // keep existing password
        }
        if (in_array($key, ['notify_on_assign', 'notify_on_status'], true)) {
            set_setting($pdo, $key, isset($_POST[$key]) ? '1' : '0');
            continue;
        }
        set_setting($pdo, $key, trim((string) ($_POST[$key] ?? '')));
    }

    flash('success', 'Settings saved.');
    redirect('settings.php');
}

$values = [];
foreach ([
    'app_name', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_from_email',
    'smtp_from_name', 'smtp_encryption', 'notify_on_assign', 'notify_on_status',
] as $key) {
    $values[$key] = setting($pdo, $key, '');
}

$pageTitle = 'Settings';
$currentPage = 'settings';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Settings</h1>
    <p class="muted">SMTP powers email alerts when tasks are assigned or status changes.</p>
  </div>
</div>

<section class="panel narrow">
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">

    <h2>App</h2>
    <label>App name <input name="app_name" value="<?= e($values['app_name'] ?: 'Project Tracker') ?>"></label>

    <h2>SMTP</h2>
    <p class="muted tiny">Use Hostinger email (or Gmail/SendGrid). Typical Hostinger: host <code>smtp.hostinger.com</code>, port <code>465</code> SSL or <code>587</code> TLS.</p>
    <div class="form-grid">
      <label>Host <input name="smtp_host" value="<?= e($values['smtp_host']) ?>" placeholder="smtp.hostinger.com"></label>
      <label>Port <input name="smtp_port" value="<?= e($values['smtp_port'] ?: '587') ?>"></label>
      <label>Username <input name="smtp_user" value="<?= e($values['smtp_user']) ?>"></label>
      <label>Password <input type="password" name="smtp_pass" placeholder="Leave blank to keep current"></label>
      <label>From email <input type="email" name="smtp_from_email" value="<?= e($values['smtp_from_email']) ?>"></label>
      <label>From name <input name="smtp_from_name" value="<?= e($values['smtp_from_name'] ?: 'Project Tracker') ?>"></label>
      <label>Encryption
        <select name="smtp_encryption">
          <option value="tls" <?= ($values['smtp_encryption'] ?: 'tls') === 'tls' ? 'selected' : '' ?>>TLS (587)</option>
          <option value="ssl" <?= $values['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
          <option value="none" <?= $values['smtp_encryption'] === 'none' ? 'selected' : '' ?>>None</option>
        </select>
      </label>
    </div>

    <h2>Notifications</h2>
    <label class="check">
      <input type="checkbox" name="notify_on_assign" <?= ($values['notify_on_assign'] ?? '1') === '1' ? 'checked' : '' ?>>
      Email assignee when a task is assigned to them
    </label>
    <label class="check">
      <input type="checkbox" name="notify_on_status" <?= ($values['notify_on_status'] ?? '1') === '1' ? 'checked' : '' ?>>
      Email assignee when task status changes
    </label>

    <div class="modal-actions">
      <button class="btn btn-primary" type="submit">Save settings</button>
      <button class="btn" type="submit" name="action" value="test">Send test email</button>
    </div>
  </form>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
