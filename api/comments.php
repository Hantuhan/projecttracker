<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
$user = current_user();
if (!$user) {
    json_response(['error' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
if (in_array($method, ['POST', 'DELETE'], true)) {
    verify_csrf();
}

function comment_task_ok(PDO $pdo, array $user, int $taskId): ?array
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
    if (!$taskId || !comment_task_ok($pdo, $user, $taskId)) {
        json_response(['error' => 'Not found'], 404);
    }
    json_response(['comments' => fetch_comments($pdo, $taskId)]);
}

$action = $input['action'] ?? 'create';

if ($action === 'create') {
    $taskId = (int) ($input['task_id'] ?? 0);
    $body = trim((string) ($input['body'] ?? ''));
    if (!$taskId || $body === '') {
        json_response(['error' => 'Comment cannot be empty'], 422);
    }
    if (!comment_task_ok($pdo, $user, $taskId)) {
        json_response(['error' => 'Not found'], 404);
    }
    $stmt = $pdo->prepare(
        'INSERT INTO task_comments (task_id, user_id, body) VALUES (?, ?, ?)'
    );
    $stmt->execute([$taskId, $user['id'], $body]);
    $id = (int) $pdo->lastInsertId();
    json_response([
        'comment' => [
            'id' => $id,
            'task_id' => $taskId,
            'user_id' => (int) $user['id'],
            'user_name' => $user['name'],
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ], 201);
}

if ($action === 'delete') {
    $id = (int) ($input['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM task_comments WHERE id = ?');
    $stmt->execute([$id]);
    $comment = $stmt->fetch();
    if (!$comment || !comment_task_ok($pdo, $user, (int) $comment['task_id'])) {
        json_response(['error' => 'Not found'], 404);
    }
    if ($user['role'] !== 'admin' && (int) $comment['user_id'] !== (int) $user['id']) {
        json_response(['error' => 'Forbidden'], 403);
    }
    $pdo->prepare('DELETE FROM task_comments WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['error' => 'Unknown action'], 400);
