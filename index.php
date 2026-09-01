<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
$user = require_login();

$projects = accessible_projects($pdo, $user);
$projectIds = project_ids($projects);

$stats = [
    'projects' => count($projects),
    'todo' => 0,
    'in_progress' => 0,
    'review' => 0,
    'done' => 0,
    'overdue' => 0,
    'mine' => 0,
];

$recent = [];
$mine = [];

if ($projectIds) {
    $in = implode(',', array_fill(0, count($projectIds), '?'));

    $stmt = $pdo->prepare("SELECT status, COUNT(*) AS c FROM tasks WHERE project_id IN ($in) GROUP BY status");
    $stmt->execute($projectIds);
    foreach ($stmt->fetchAll() as $row) {
        $stats[$row['status']] = (int) $row['c'];
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tasks
         WHERE project_id IN ($in) AND due_date < CURDATE() AND status != 'done'"
    );
    $stmt->execute($projectIds);
    $stats['overdue'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM tasks WHERE project_id IN ($in) AND assignee_id = ? AND status != 'done'"
    );
    $stmt->execute([...$projectIds, $user['id']]);
    $stats['mine'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT t.*, p.name AS project_name, p.color AS project_color, u.name AS assignee_name
         FROM tasks t
         JOIN projects p ON p.id = t.project_id
         LEFT JOIN users u ON u.id = t.assignee_id
         WHERE t.project_id IN ($in)
         ORDER BY t.updated_at DESC
         LIMIT 8"
    );
    $stmt->execute($projectIds);
    $recent = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT t.*, p.name AS project_name, p.color AS project_color
         FROM tasks t
         JOIN projects p ON p.id = t.project_id
         WHERE t.project_id IN ($in) AND t.assignee_id = ? AND t.status != 'done'
         ORDER BY t.due_date IS NULL, t.due_date ASC
         LIMIT 8"
    );
    $stmt->execute([...$projectIds, $user['id']]);
    $mine = $stmt->fetchAll();
}

$totalOpen = $stats['todo'] + $stats['in_progress'] + $stats['review'];
$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p class="muted">Hello, <?= e($user['name']) ?>. Here’s where things stand.</p>
  </div>
  <button class="btn btn-primary" type="button" data-open-task <?= !$projects ? 'disabled' : '' ?>>New task</button>
</div>

<section class="stat-grid">
  <article class="stat">
    <span class="stat-label">Open tasks</span>
    <strong class="stat-value"><?= $totalOpen ?></strong>
  </article>
  <article class="stat">
    <span class="stat-label">Assigned to me</span>
    <strong class="stat-value"><?= $stats['mine'] ?></strong>
  </article>
  <article class="stat">
    <span class="stat-label">Overdue</span>
    <strong class="stat-value accent-warn"><?= $stats['overdue'] ?></strong>
  </article>
  <article class="stat">
    <span class="stat-label">Projects</span>
    <strong class="stat-value"><?= $stats['projects'] ?></strong>
  </article>
</section>

<section class="panel">
  <h2>Pipeline</h2>
  <div class="pipeline">
    <?php
    $pipe = [
        'todo' => $stats['todo'],
        'in_progress' => $stats['in_progress'],
        'review' => $stats['review'],
        'done' => $stats['done'],
    ];
    $max = max(1, max($pipe));
    foreach ($pipe as $key => $count):
    ?>
      <div class="pipe-col">
        <div class="pipe-bar" style="--h: <?= round(($count / $max) * 100) ?>%"></div>
        <strong><?= $count ?></strong>
        <span><?= e(status_label($key)) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<div class="two-col">
  <section class="panel">
    <div class="panel-head">
      <h2>My open tasks</h2>
      <a href="list.php?mine=1">View all</a>
    </div>
    <?php if (!$mine): ?>
      <p class="muted">Nothing assigned to you right now.</p>
    <?php else: ?>
      <ul class="task-list compact">
        <?php foreach ($mine as $task): ?>
          <li>
            <button type="button" class="task-row" data-edit-task="<?= (int) $task['id'] ?>">
              <span class="dot" style="background:<?= e($task['project_color']) ?>"></span>
              <span class="task-title"><?= e($task['title']) ?></span>
              <span class="badge status-<?= e($task['status']) ?>"><?= e(status_label($task['status'])) ?></span>
              <?php if ($task['due_date']): ?>
                <span class="due <?= $task['due_date'] < date('Y-m-d') ? 'overdue' : '' ?>"><?= e($task['due_date']) ?></span>
              <?php endif; ?>
            </button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="panel">
    <div class="panel-head">
      <h2>Recently updated</h2>
      <a href="list.php">List view</a>
    </div>
    <?php if (!$recent): ?>
      <p class="muted">No tasks yet. Create a project and add your first task.</p>
    <?php else: ?>
      <ul class="task-list compact">
        <?php foreach ($recent as $task): ?>
          <li>
            <button type="button" class="task-row" data-edit-task="<?= (int) $task['id'] ?>">
              <span class="dot" style="background:<?= e($task['project_color']) ?>"></span>
              <span class="task-title"><?= e($task['title']) ?></span>
              <span class="meta"><?= e($task['assignee_name'] ?? 'Unassigned') ?></span>
            </button>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<?php
$members = active_users($pdo);
require __DIR__ . '/includes/task_modal.php';
$pageScript = 'tasks.js';
require __DIR__ . '/includes/footer.php';
?>
