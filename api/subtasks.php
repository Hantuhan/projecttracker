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

function task_for_user(PDO $pdo, array $user, int $taskId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM tasks WHERE id = ?');
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    if (!$task) {
        return null;
    }
    if (!user_can_access_project($pdo, (int) $user['id'], $user['role'], (int) $task['project_id'])) {
        return null;
    }
    return $task;
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}

if ($method === 'GET') {
    $taskId = (int) ($_GET['task_id'] ?? 0);
    if (!$taskId || !task_for_user($pdo, $user, $taskId)) {
        json_response(['error' => 'Not found'], 404);
    }
    json_response(['subtasks' => fetch_subtasks($pdo, $taskId)]);
}

$action = $input['action'] ?? 'create';

if ($action === 'create') {
    $taskId = (int) ($input['task_id'] ?? 0);
    $title = trim((string) ($input['title'] ?? ''));
    if (!$taskId || $title === '') {
        json_response(['error' => 'Task and title are required'], 422);
    }
    if (!task_for_user($pdo, $user, $taskId)) {
        json_response(['error' => 'Not found'], 404);
    }
    $pos = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM subtasks WHERE task_id = ?');
    $pos->execute([$taskId]);
    $position = (int) $pos->fetchColumn();
    $stmt = $pdo->prepare(
        'INSERT INTO subtasks (task_id, title, is_done, position) VALUES (?, ?, 0, ?)'
    );
    $stmt->execute([$taskId, $title, $position]);
    $id = (int) $pdo->lastInsertId();
    json_response([
        'subtask' => [
            'id' => $id,
            'task_id' => $taskId,
            'title' => $title,
            'is_done' => 0,
            'position' => $position,
        ],
    ], 201);
}

$id = (int) ($input['id'] ?? 0);
if (!$id) {
    json_response(['error' => 'Missing id'], 400);
}

$stmt = $pdo->prepare('SELECT * FROM subtasks WHERE id = ?');
$stmt->execute([$id]);
$sub = $stmt->fetch();
if (!$sub || !task_for_user($pdo, $user, (int) $sub['task_id'])) {
    json_response(['error' => 'Not found'], 404);
}

if ($action === 'delete') {
    $pdo->prepare('DELETE FROM subtasks WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

if ($action === 'toggle') {
    $done = empty($sub['is_done']) ? 1 : 0;
    $pdo->prepare('UPDATE subtasks SET is_done = ? WHERE id = ?')->execute([$done, $id]);
    $sub['is_done'] = $done;
    json_response(['subtask' => [
        'id' => (int) $sub['id'],
        'task_id' => (int) $sub['task_id'],
        'title' => $sub['title'],
        'is_done' => $done,
        'position' => (int) $sub['position'],
    ]]);
}

if ($action === 'update') {
    $title = trim((string) ($input['title'] ?? $sub['title']));
    if ($title === '') {
        json_response(['error' => 'Title required'], 422);
    }
    $done = array_key_exists('is_done', $input) ? ((int) (bool) $input['is_done']) : (int) $sub['is_done'];
    $pdo->prepare('UPDATE subtasks SET title = ?, is_done = ? WHERE id = ?')
        ->execute([$title, $done, $id]);
    json_response(['subtask' => [
        'id' => $id,
        'task_id' => (int) $sub['task_id'],
        'title' => $title,
        'is_done' => $done,
        'position' => (int) $sub['position'],
    ]]);
}

json_response(['error' => 'Unknown action'], 400);
