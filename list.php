<?php

declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';
$user = require_login();

$projects = accessible_projects($pdo, $user);
$projectIds = array_column($projects, 'id');
$members = active_users($pdo);

$filterProject = isset($_GET['project']) ? (int) $_GET['project'] : 0;
$filterStatus = $_GET['status'] ?? '';
$filterMine = isset($_GET['mine']);
$q = trim($_GET['q'] ?? '');

$tasks = [];
if ($projectIds) {
    $params = $projectIds;
    $sql = "SELECT t.*, p.name AS project_name, p.color AS project_color, u.name AS assignee_name
            FROM tasks t
            JOIN projects p ON p.id = t.project_id
            LEFT JOIN users u ON u.id = t.assignee_id
            WHERE t.project_id IN (" . implode(',', array_fill(0, count($projectIds), '?')) . ")";

    if ($filterProject && in_array($filterProject, $projectIds, true)) {
        $sql .= ' AND t.project_id = ?';
        $params[] = $filterProject;
    }
    if (in_array($filterStatus, ['todo', 'in_progress', 'review', 'done'], true)) {
        $sql .= ' AND t.status = ?';
        $params[] = $filterStatus;
    }
    if ($filterMine) {
        $sql .= ' AND t.assignee_id = ?';
        $params[] = $user['id'];
    }
    if ($q !== '') {
        $sql .= ' AND (t.title LIKE ? OR t.description LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    $sql .= " ORDER BY FIELD(t.status,'todo','in_progress','review','done'), t.priority DESC, t.due_date IS NULL, t.due_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = attach_subtask_counts($pdo, $stmt->fetchAll());
}

// CSV export of current filtered list
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tasks-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Title', 'Project', 'Status', 'Priority', 'Assignee', 'Due', 'Subtasks done', 'Subtasks total', 'Description']);
    foreach ($tasks as $task) {
        fputcsv($out, [
            $task['title'],
            $task['project_name'],
            status_label($task['status']),
            priority_label($task['priority']),
            $task['assignee_name'] ?? '',
            $task['due_date'] ?? '',
            $task['subtask_done'] ?? 0,
            $task['subtask_total'] ?? 0,
            $task['description'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'List';
$currentPage = 'list';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>List</h1>
    <p class="muted"><?= count($tasks) ?> task<?= count($tasks) === 1 ? '' : 's' ?></p>
  </div>
  <div class="head-actions">
    <?php
    $exportQs = $_GET;
    $exportQs['export'] = 'csv';
    ?>
    <a class="btn" href="list.php?<?= e(http_build_query($exportQs)) ?>">Export CSV</a>
    <button class="btn btn-primary" type="button" data-open-task>New task</button>
  </div>
</div>

<form class="filters" method="get">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search tasks…">
  <select name="project">
    <option value="">All projects</option>
    <?php foreach ($projects as $p): ?>
      <option value="<?= (int) $p['id'] ?>" <?= $filterProject === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="status">
    <option value="">All statuses</option>
    <?php foreach (['todo', 'in_progress', 'review', 'done'] as $s): ?>
      <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="check">
    <input type="checkbox" name="mine" value="1" <?= $filterMine ? 'checked' : '' ?>> Mine only
  </label>
  <button class="btn" type="submit">Filter</button>
</form>

<section class="panel table-wrap">
  <?php if (!$tasks): ?>
    <p class="muted empty">No tasks match these filters.</p>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Task</th>
        <th>Project</th>
        <th>Status</th>
        <th>Priority</th>
        <th>Subtasks</th>
        <th>Assignee</th>
        <th>Due</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($tasks as $task): ?>
        <tr class="clickable" data-edit-task="<?= (int) $task['id'] ?>">
          <td>
            <strong><?= e($task['title']) ?></strong>
            <?php if ($task['description']): ?>
              <div class="cell-sub"><?= e(strlen($task['description']) > 80 ? substr($task['description'], 0, 77) . '…' : $task['description']) ?></div>
            <?php endif; ?>
          </td>
          <td><span class="project-pill"><i style="background:<?= e($task['project_color']) ?>"></i><?= e($task['project_name']) ?></span></td>
          <td><span class="badge status-<?= e($task['status']) ?>"><?= e(status_label($task['status'])) ?></span></td>
          <td><span class="prio prio-<?= e($task['priority']) ?>"><?= e(priority_label($task['priority'])) ?></span></td>
          <td>
            <?php if (!empty($task['subtask_total'])): ?>
              <span class="subtask-chip"><?= (int) $task['subtask_done'] ?>/<?= (int) $task['subtask_total'] ?></span>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td><?= e($task['assignee_name'] ?? '—') ?></td>
          <td class="<?= ($task['due_date'] && $task['due_date'] < date('Y-m-d') && $task['status'] !== 'done') ? 'overdue' : '' ?>">
            <?= e($task['due_date'] ?? '—') ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</section>

<?php
require __DIR__ . '/includes/task_modal.php';
$pageScript = 'tasks.js';
require __DIR__ . '/includes/footer.php';
?>
