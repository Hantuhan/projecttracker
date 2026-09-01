<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
$user = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // Invite → pending until approved
    if ($action === 'invite') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role = $_POST['role'] ?? 'member';
        if (!in_array($role, ['admin', 'member'], true)) {
            $role = 'member';
        }
        if ($name === '' || $email === '') {
            flash('error', 'Name and email are required.');
            redirect('team.php');
        }
        $tempPass = generate_temp_password();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password, role, status, invited_by)
                 VALUES (?, ?, ?, ?, 'pending', ?)"
            );
            $stmt->execute([
                $name,
                $email,
                password_hash($tempPass, PASSWORD_DEFAULT),
                $role,
                $user['id'],
            ]);
            $invitee = ['name' => $name, 'email' => $email];
            notify_user_invite($pdo, $invitee, $tempPass, $user);
            flash('success', 'Invite created as pending. Temp password emailed if SMTP is set (also shown once): ' . $tempPass);
        } catch (PDOException $e) {
            flash('error', 'Could not invite. Email may already exist.');
        }
        redirect('team.php');
    }

    // Add active member immediately (skip approval)
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = $_POST['role'] ?? 'member';
        if (!in_array($role, ['admin', 'member'], true)) {
            $role = 'member';
        }
        if ($name === '' || $email === '' || strlen($password) < 6) {
            flash('error', 'Name, email, and password (6+ chars) are required.');
            redirect('team.php');
        }
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password, role, status, invited_by, approved_at)
                 VALUES (?, ?, ?, ?, 'active', ?, NOW())"
            );
            $stmt->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
                $user['id'],
            ]);
            flash('success', 'Active member added.');
        } catch (PDOException $e) {
            flash('error', 'Could not add user. Email may already exist.');
        }
        redirect('team.php');
    }

    if ($action === 'approve') {
        $id = (int) ($_POST['id'] ?? 0);
        $newPass = trim((string) ($_POST['password'] ?? ''));
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'pending'");
        $stmt->execute([$id]);
        $member = $stmt->fetch();
        if (!$member) {
            flash('error', 'Pending user not found.');
            redirect('team.php');
        }
        $plain = null;
        if ($newPass !== '') {
            if (strlen($newPass) < 6) {
                flash('error', 'Password must be at least 6 characters.');
                redirect('team.php');
            }
            $pdo->prepare(
                "UPDATE users SET status = 'active', approved_at = NOW(), password = ? WHERE id = ?"
            )->execute([password_hash($newPass, PASSWORD_DEFAULT), $id]);
            $plain = $newPass;
        } else {
            $pdo->prepare(
                "UPDATE users SET status = 'active', approved_at = NOW() WHERE id = ?"
            )->execute([$id]);
        }
        $member['status'] = 'active';
        notify_user_approved($pdo, $member, $plain);
        flash('success', 'User approved. They can log in now.');
        redirect('team.php');
    }

    if ($action === 'reject') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $user['id']) {
            flash('error', 'You cannot reject yourself.');
            redirect('team.php');
        }
        $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ? AND status = 'pending'")
            ->execute([$id]);
        flash('success', 'Invite rejected.');
        redirect('team.php');
    }

    if ($action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role = $_POST['role'] ?? 'member';
        $password = (string) ($_POST['password'] ?? '');
        $status = $_POST['status'] ?? 'active';
        if (!in_array($role, ['admin', 'member'], true)) {
            $role = 'member';
        }
        if (!in_array($status, ['pending', 'active', 'rejected'], true)) {
            $status = 'active';
        }
        if ($id === (int) $user['id'] && ($role !== 'admin' || $status !== 'active')) {
            flash('error', 'You cannot demote or deactivate yourself.');
            redirect('team.php');
        }
        if ($password !== '') {
            $stmt = $pdo->prepare(
                'UPDATE users SET name = ?, email = ?, role = ?, status = ?, password = ? WHERE id = ?'
            );
            $stmt->execute([$name, $email, $role, $status, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE users SET name = ?, email = ?, role = ?, status = ? WHERE id = ?'
            );
            $stmt->execute([$name, $email, $role, $status, $id]);
        }
        flash('success', 'User updated.');
        redirect('team.php');
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) $user['id']) {
            flash('error', 'You cannot delete your own account.');
            redirect('team.php');
        }
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        flash('success', 'User removed.');
        redirect('team.php');
    }
}

$pending = $pdo->query(
    "SELECT id, name, email, role, created_at FROM users WHERE status = 'pending' ORDER BY created_at DESC"
)->fetchAll();

$team = $pdo->query(
    "SELECT id, name, email, role, status, created_at, approved_at
     FROM users WHERE status != 'pending'
     ORDER BY FIELD(status,'active','rejected'), name"
)->fetchAll();

$pageTitle = 'Team';
$currentPage = 'team';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Team</h1>
    <p class="muted">Invite people, then approve them before they can sign in.</p>
  </div>
</div>

<?php if ($pending): ?>
<section class="panel approval-panel">
  <div class="panel-head">
    <h2>Pending approval (<?= count($pending) ?>)</h2>
  </div>
  <ul class="project-list">
    <?php foreach ($pending as $member): ?>
      <li class="project-item pending-item">
        <div class="project-item-head">
          <span class="avatar"><?= e(strtoupper(substr($member['name'], 0, 1))) ?></span>
          <div>
            <strong><?= e($member['name']) ?></strong>
            <div class="cell-sub"><?= e($member['email']) ?> · <?= e($member['role']) ?> · invited <?= e(substr((string) $member['created_at'], 0, 16)) ?></div>
          </div>
          <span class="badge status-review">Pending</span>
        </div>
        <form method="post" class="approve-row">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
          <label class="grow">Set password on approve (optional)
            <input type="password" name="password" minlength="6" placeholder="Keep invite temp password">
          </label>
          <button class="btn btn-primary" type="submit" name="action" value="approve">Approve</button>
          <button class="btn btn-danger" type="submit" name="action" value="reject" onclick="return confirm('Reject this invite?')">Reject</button>
        </form>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<div class="two-col">
  <section class="panel">
    <h2>Invite (needs approval)</h2>
    <p class="muted tiny">Creates a pending account and emails a temp password if SMTP is set.</p>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="invite">
      <label>Name <input name="name" required></label>
      <label>Email <input type="email" name="email" required></label>
      <label>Role
        <select name="role">
          <option value="member">Member</option>
          <option value="admin">Admin</option>
        </select>
      </label>
      <button class="btn btn-primary" type="submit">Send invite</button>
    </form>

    <details class="nested-details">
      <summary>Or add active member now</summary>
      <form method="post" class="stack nested">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <label>Name <input name="name" required></label>
        <label>Email <input type="email" name="email" required></label>
        <label>Password <input type="password" name="password" minlength="6" required></label>
        <label>Role
          <select name="role">
            <option value="member">Member</option>
            <option value="admin">Admin</option>
          </select>
        </label>
        <button class="btn" type="submit">Add &amp; activate</button>
      </form>
    </details>
  </section>

  <section class="panel">
    <h2>Members (<?= count($team) ?>)</h2>
    <ul class="project-list">
      <?php foreach ($team as $member): ?>
        <li class="project-item">
          <div class="project-item-head">
            <span class="avatar"><?= e(strtoupper(substr($member['name'], 0, 1))) ?></span>
            <div>
              <strong><?= e($member['name']) ?></strong>
              <div class="cell-sub"><?= e($member['email']) ?> · <?= e($member['role']) ?></div>
            </div>
            <span class="badge <?= $member['status'] === 'active' ? 'status-done' : 'status-todo' ?>"><?= e($member['status']) ?></span>
          </div>
          <details>
            <summary>Edit</summary>
            <form method="post" class="stack nested">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
              <label>Name <input name="name" value="<?= e($member['name']) ?>" required></label>
              <label>Email <input type="email" name="email" value="<?= e($member['email']) ?>" required></label>
              <label>New password <input type="password" name="password" minlength="6" placeholder="Leave blank to keep"></label>
              <label>Role
                <select name="role">
                  <option value="member" <?= $member['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                  <option value="admin" <?= $member['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
              </label>
              <label>Status
                <select name="status">
                  <option value="active" <?= $member['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="pending" <?= $member['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                  <option value="rejected" <?= $member['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
              </label>
              <div class="modal-actions">
                <button class="btn btn-primary" type="submit">Save</button>
                <?php if ((int) $member['id'] !== (int) $user['id']): ?>
                <button class="btn btn-danger" type="submit" name="action" value="delete" onclick="return confirm('Remove this user?')">Delete</button>
                <?php endif; ?>
              </div>
            </form>
          </details>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
