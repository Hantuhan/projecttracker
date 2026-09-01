<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color = $_POST['color'] ?? '#2563eb';
        if ($name === '') {
            flash('error', 'Project name is required.');
            redirect('projects.php');
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#2563eb';
        }
        $stmt = $pdo->prepare(
            'INSERT INTO projects (name, description, color, created_by) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $description ?: null, $color, $user['id']]);
        $projectId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO project_members (project_id, user_id) VALUES (?, ?)')
            ->execute([$projectId, $user['id']]);
        flash('success', 'Project created.');
        redirect('projects.php');
    }

    if ($action === 'update' && $user['role'] === 'admin') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color = $_POST['color'] ?? '#2563eb';
        $status = $_POST['status'] ?? 'active';
        if (!in_array($status, ['active', 'archived'], true)) {
            $status = 'active';
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#2563eb';
        }
        $stmt = $pdo->prepare(
            'UPDATE projects SET name = ?, description = ?, color = ?, status = ? WHERE id = ?'
        );
        $stmt->execute([$name, $description ?: null, $color, $status, $id]);

        $memberIds = array_map('intval', $_POST['members'] ?? []);
        $pdo->prepare('DELETE FROM project_members WHERE project_id = ?')->execute([$id]);
        $ins = $pdo->prepare('INSERT INTO project_members (project_id, user_id) VALUES (?, ?)');
        foreach (array_unique($memberIds) as $mid) {
            if ($mid > 0) {
                $ins->execute([$id, $mid]);
            }
        }
        flash('success', 'Project updated.');
        redirect('projects.php');
    }
}

$projects = accessible_projects($pdo, $user);
if ($user['role'] === 'admin') {
    $projects = $pdo->query('SELECT * FROM projects ORDER BY status, name')->fetchAll();
}

$allMembers = active_users($pdo);
$memberMap = [];
$stmt = $pdo->query('SELECT project_id, user_id FROM project_members');
foreach ($stmt->fetchAll() as $row) {
    $memberMap[(int) $row['project_id']][] = (int) $row['user_id'];
}

$counts = [];
$cStmt = $pdo->query('SELECT project_id, COUNT(*) AS c FROM tasks GROUP BY project_id');
foreach ($cStmt->fetchAll() as $row) {
    $counts[(int) $row['project_id']] = (int) $row['c'];
}

$pageTitle = 'Projects';
$currentPage = 'projects';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Projects</h1>
    <p class="muted">Organize work into shared project boards.</p>
  </div>
</div>

<div class="two-col">
  <section class="panel">
    <h2>New project</h2>
    <form method="post" class="stack">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">
      <label>Name <input name="name" required maxlength="150"></label>
      <label>Description <textarea name="description" rows="3"></textarea></label>
      <label>Color <input type="color" name="color" value="#2563eb"></label>
      <button class="btn btn-primary" type="submit">Create project</button>
    </form>
  </section>

  <section class="panel">
    <h2>Your projects</h2>
    <?php if (!$projects): ?>
      <p class="muted">No projects yet.</p>
    <?php else: ?>
      <ul class="project-list">
        <?php foreach ($projects as $p): ?>
          <li class="project-item">
            <div class="project-item-head">
              <span class="dot" style="background:<?= e($p['color']) ?>"></span>
              <div>
                <strong><?= e($p['name']) ?></strong>
                <div class="cell-sub"><?= e($p['description'] ?? '') ?></div>
              </div>
              <span class="badge"><?= (int) ($counts[(int) $p['id']] ?? 0) ?> tasks</span>
              <?php if ($p['status'] === 'archived'): ?><span class="badge">archived</span><?php endif; ?>
            </div>
            <div class="project-links">
              <a href="kanban.php?project=<?= (int) $p['id'] ?>">Kanban</a>
              <a href="list.php?project=<?= (int) $p['id'] ?>">List</a>
            </div>
            <?php if ($user['role'] === 'admin'): ?>
            <details>
              <summary>Edit / members</summary>
              <form method="post" class="stack nested">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <label>Name <input name="name" value="<?= e($p['name']) ?>" required></label>
                <label>Description <textarea name="description" rows="2"><?= e($p['description'] ?? '') ?></textarea></label>
                <label>Color <input type="color" name="color" value="<?= e($p['color']) ?>"></label>
                <label>Status
                  <select name="status">
                    <option value="active" <?= $p['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="archived" <?= $p['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                  </select>
                </label>
                <fieldset class="member-picks">
                  <legend>Team members</legend>
                  <?php foreach ($allMembers as $m): ?>
                    <label class="check">
                      <input type="checkbox" name="members[]" value="<?= (int) $m['id'] ?>"
                        <?= in_array((int) $m['id'], $memberMap[(int) $p['id']] ?? [], true) ? 'checked' : '' ?>>
                      <?= e($m['name']) ?>
                    </label>
                  <?php endforeach; ?>
                </fieldset>
                <button class="btn btn-primary" type="submit">Save</button>
              </form>
            </details>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
