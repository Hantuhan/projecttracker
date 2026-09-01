<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), (string) $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

function setting(PDO $pdo, string $key, ?string $default = null): ?string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $cache[$key] = $row ? $row['setting_value'] : $default;
    return $cache[$key];
}

function set_setting(PDO $pdo, string $key, ?string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function status_label(string $status): string
{
    return match ($status) {
        'todo' => 'To Do',
        'in_progress' => 'In Progress',
        'review' => 'Review',
        'done' => 'Done',
        default => ucfirst($status),
    };
}

function priority_label(string $priority): string
{
    return ucfirst($priority);
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function user_can_access_project(PDO $pdo, int $userId, string $role, int $projectId): bool
{
    if ($role === 'admin') {
        return true;
    }
    $stmt = $pdo->prepare(
        'SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?
         UNION
         SELECT 1 FROM projects WHERE id = ? AND created_by = ?'
    );
    $stmt->execute([$projectId, $userId, $projectId, $userId]);
    return (bool) $stmt->fetchColumn();
}

function accessible_projects(PDO $pdo, array $user): array
{
    if ($user['role'] === 'admin') {
        return $pdo->query(
            "SELECT * FROM projects WHERE status = 'active' ORDER BY name"
        )->fetchAll();
    }
    $stmt = $pdo->prepare(
        "SELECT DISTINCT p.* FROM projects p
         LEFT JOIN project_members pm ON pm.project_id = p.id
         WHERE p.status = 'active'
           AND (pm.user_id = ? OR p.created_by = ?)
         ORDER BY p.name"
    );
    $stmt->execute([$user['id'], $user['id']]);
    return $stmt->fetchAll();
}

function fetch_subtasks(PDO $pdo, int $taskId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, task_id, title, is_done, position
         FROM subtasks WHERE task_id = ? ORDER BY position ASC, id ASC'
    );
    $stmt->execute([$taskId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['task_id'] = (int) $row['task_id'];
        $row['is_done'] = (int) $row['is_done'];
        $row['position'] = (int) $row['position'];
    }
    return $rows;
}

/** Attach subtask_total / subtask_done counts to a list of tasks */
function attach_subtask_counts(PDO $pdo, array $tasks): array
{
    if (!$tasks) {
        return $tasks;
    }
    $ids = array_map(static fn($t) => (int) $t['id'], $tasks);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT task_id,
                COUNT(*) AS total,
                SUM(CASE WHEN is_done = 1 THEN 1 ELSE 0 END) AS done
         FROM subtasks WHERE task_id IN ($in) GROUP BY task_id"
    );
    $stmt->execute($ids);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[(int) $row['task_id']] = [
            'subtask_total' => (int) $row['total'],
            'subtask_done' => (int) $row['done'],
        ];
    }
    foreach ($tasks as &$task) {
        $id = (int) $task['id'];
        $task['subtask_total'] = $map[$id]['subtask_total'] ?? 0;
        $task['subtask_done'] = $map[$id]['subtask_done'] ?? 0;
    }
    return $tasks;
}

function fetch_comments(PDO $pdo, int $taskId): array
{
    $stmt = $pdo->prepare(
        'SELECT c.id, c.task_id, c.user_id, c.body, c.created_at, u.name AS user_name
         FROM task_comments c
         LEFT JOIN users u ON u.id = c.user_id
         WHERE c.task_id = ?
         ORDER BY c.created_at ASC'
    );
    $stmt->execute([$taskId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['task_id'] = (int) $row['task_id'];
        $row['user_id'] = $row['user_id'] !== null ? (int) $row['user_id'] : null;
    }
    return $rows;
}
