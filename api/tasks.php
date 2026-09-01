<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
$user = current_user();
if (!$user) {
    json_response(['error' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    verify_csrf();
}

function fetch_task(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT t.*, u.name AS assignee_name, p.name AS project_name
         FROM tasks t
         LEFT JOIN users u ON u.id = t.assignee_id
         JOIN projects p ON p.id = t.project_id
         WHERE t.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id) {
        $task = fetch_task($pdo, $id);
        if (!$task || !user_can_access_project($pdo, (int) $user['id'], $user['role'], (int) $task['project_id'])) {
            json_response(['error' => 'Not found'], 404);
        }
        $task['subtasks'] = fetch_subtasks($pdo, $id);
        $task['subtask_total'] = count($task['subtasks']);
        $task['subtask_done'] = count(array_filter($task['subtasks'], static fn($s) => (int) $s['is_done'] === 1));
        $task['comments'] = fetch_comments($pdo, $id);
        json_response(['task' => $task]);
    }
    json_response(['error' => 'Missing id'], 400);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}

if ($method === 'POST' && empty($input['id'])) {
    $projectId = (int) ($input['project_id'] ?? 0);
    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '' || !$projectId) {
        json_response(['error' => 'Title and project are required'], 422);
    }
    if (!user_can_access_project($pdo, (int) $user['id'], $user['role'], $projectId)) {
        json_response(['error' => 'Forbidden'], 403);
    }

    $status = $input['status'] ?? 'todo';
    $priority = $input['priority'] ?? 'medium';
    if (!in_array($status, ['todo', 'in_progress', 'review', 'done'], true)) {
        $status = 'todo';
    }
    if (!in_array($priority, ['low', 'medium', 'high'], true)) {
        $priority = 'medium';
    }
    $assigneeId = !empty($input['assignee_id']) ? (int) $input['assignee_id'] : null;
    $due = !empty($input['due_date']) ? $input['due_date'] : null;
    $description = trim((string) ($input['description'] ?? '')) ?: null;

    $posStmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM tasks WHERE project_id = ? AND status = ?');
    $posStmt->execute([$projectId, $status]);
    $position = (int) $posStmt->fetchColumn();

    $stmt = $pdo->prepare(
        'INSERT INTO tasks (project_id, title, description, status, priority, assignee_id, due_date, position, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $projectId, $title, $description, $status, $priority,
        $assigneeId, $due, $position, $user['id'],
    ]);
    $task = fetch_task($pdo, (int) $pdo->lastInsertId());
    if ($task && $assigneeId) {
        notify_task_update($pdo, $task, 'assigned', $user);
    }
    json_response(['task' => $task], 201);
}

$id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
$task = $id ? fetch_task($pdo, $id) : null;
if (!$task || !user_can_access_project($pdo, (int) $user['id'], $user['role'], (int) $task['project_id'])) {
    json_response(['error' => 'Not found'], 404);
}

if ($method === 'DELETE' || (($input['action'] ?? '') === 'delete')) {
    $pdo->prepare('DELETE FROM tasks WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

// Update / move
$oldAssignee = $task['assignee_id'] ? (int) $task['assignee_id'] : null;
$oldStatus = $task['status'];

$fields = [];
$params = [];

$map = [
    'title' => 'title',
    'description' => 'description',
    'project_id' => 'project_id',
    'status' => 'status',
    'priority' => 'priority',
    'assignee_id' => 'assignee_id',
    'due_date' => 'due_date',
    'position' => 'position',
];

foreach ($map as $key => $col) {
    if (!array_key_exists($key, $input)) {
        continue;
    }
    $val = $input[$key];
    if ($key === 'title') {
        $val = trim((string) $val);
        if ($val === '') {
            json_response(['error' => 'Title required'], 422);
        }
    }
    if ($key === 'description') {
        $val = trim((string) $val) ?: null;
    }
    if ($key === 'project_id') {
        $val = (int) $val;
        if (!user_can_access_project($pdo, (int) $user['id'], $user['role'], $val)) {
            json_response(['error' => 'Forbidden project'], 403);
        }
    }
    if ($key === 'status' && !in_array($val, ['todo', 'in_progress', 'review', 'done'], true)) {
        continue;
    }
    if ($key === 'priority' && !in_array($val, ['low', 'medium', 'high'], true)) {
        continue;
    }
    if ($key === 'assignee_id') {
        $val = $val === '' || $val === null ? null : (int) $val;
    }
    if ($key === 'due_date') {
        $val = $val === '' || $val === null ? null : $val;
    }
    if ($key === 'position') {
        $val = (int) $val;
    }
    $fields[] = "$col = ?";
    $params[] = $val;
}

if (!$fields) {
    json_response(['error' => 'Nothing to update'], 400);
}

$params[] = $id;
$pdo->prepare('UPDATE tasks SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
$updated = fetch_task($pdo, $id);

$newAssignee = $updated['assignee_id'] ? (int) $updated['assignee_id'] : null;
if ($newAssignee && $newAssignee !== $oldAssignee) {
    notify_task_update($pdo, $updated, 'assigned', $user);
} elseif ($newAssignee && $updated['status'] !== $oldStatus) {
    notify_task_update($pdo, $updated, 'status', $user);
}

json_response(['task' => $updated]);
