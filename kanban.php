<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
$user = require_login();

$projects = accessible_projects($pdo, $user);
$projectIds = array_column($projects, 'id');
$members = active_users($pdo);

$filterProject = isset($_GET['project']) ? (int) $_GET['project'] : ($projectIds[0] ?? 0);
if ($filterProject && !in_array($filterProject, $projectIds, true) && $user['role'] !== 'admin') {
    $filterProject = $projectIds[0] ?? 0;
}

$columns = [
    'todo' => [],
    'in_progress' => [],
    'review' => [],
    'done' => [],
];

if ($filterProject) {
    $stmt = $pdo->prepare(
        "SELECT t.*, u.name AS assignee_name
         FROM tasks t
         LEFT JOIN users u ON u.id = t.assignee_id
         WHERE t.project_id = ?
         ORDER BY t.position ASC, t.id ASC"
    );
    $stmt->execute([$filterProject]);
    $fetched = $stmt->fetchAll();
    $fetched = attach_subtask_counts($pdo, $fetched);
    foreach ($fetched as $task) {
        $columns[$task['status']][] = $task;
    }
}

$pageTitle = 'Kanban';
$currentPage = 'kanban';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Kanban</h1>
    <p class="muted">Drag cards between columns to update status.</p>
  </div>
  <div class="head-actions">
    <form method="get" class="inline-form">
      <select name="project" onchange="this.form.submit()">
        <?php if (!$projects): ?>
          <option value="">No projects</option>
        <?php endif; ?>
        <?php foreach ($projects as $p): ?>
          <option value="<?= (int) $p['id'] ?>" <?= $filterProject === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <button class="btn btn-primary" type="button" data-open-task <?= !$filterProject ? 'disabled' : '' ?>>New task</button>
  </div>
</div>

<?php if (!$filterProject): ?>
  <section class="panel"><p class="muted">Create a project first, then come back to the board.</p></section>
<?php else: ?>
<div class="kanban" data-project="<?= (int) $filterProject ?>">
  <?php foreach ($columns as $status => $cards): ?>
    <section class="kanban-col" data-status="<?= e($status) ?>">
      <header>
        <h2><?= e(status_label($status)) ?></h2>
        <span class="count"><?= count($cards) ?></span>
      </header>
      <div class="kanban-list" data-drop-zone="<?= e($status) ?>">
        <?php foreach ($cards as $task): ?>
          <article class="kanban-card" draggable="true" data-task-id="<?= (int) $task['id'] ?>" data-edit-task="<?= (int) $task['id'] ?>">
            <div class="card-top">
              <span class="prio prio-<?= e($task['priority']) ?>"><?= e(priority_label($task['priority'])) ?></span>
              <?php if ($task['due_date']): ?>
                <span class="due <?= $task['due_date'] < date('Y-m-d') && $task['status'] !== 'done' ? 'overdue' : '' ?>"><?= e($task['due_date']) ?></span>
              <?php endif; ?>
            </div>
            <strong><?= e($task['title']) ?></strong>
            <?php if (!empty($task['subtask_total'])): ?>
              <span class="subtask-chip"><?= (int) $task['subtask_done'] ?>/<?= (int) $task['subtask_total'] ?> subtasks</span>
            <?php endif; ?>
            <?php if ($task['assignee_name']): ?>
              <span class="assignee"><?= e($task['assignee_name']) ?></span>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
require __DIR__ . '/includes/task_modal.php';
$pageScript = 'tasks.js';
require __DIR__ . '/includes/footer.php';
?>
