<?php

declare(strict_types=1);

/**
 * Send due-date reminder emails. Returns counts: before, due, overdue, skipped, errors.
 */
function send_due_reminders(PDO $pdo): array
{
    $stats = ['before' => 0, 'due' => 0, 'overdue' => 0, 'skipped' => 0, 'errors' => 0];

    if (setting($pdo, 'notify_reminders', '1') !== '1') {
        return $stats;
    }

    $daysBefore = max(0, (int) (setting($pdo, 'reminder_days_before', '1') ?: 1));
    $onDue = setting($pdo, 'reminder_on_due', '1') === '1';
    $onOverdue = setting($pdo, 'reminder_overdue', '1') === '1';

    $today = date('Y-m-d');

    if ($daysBefore > 0) {
        $target = date('Y-m-d', strtotime("+{$daysBefore} days"));
        $tasks = fetch_reminder_tasks($pdo, 'due_date = ?', [$target]);
        foreach ($tasks as $task) {
            if (reminder_already_sent($pdo, (int) $task['id'], 'before', $task['due_date'])) {
                $stats['skipped']++;
                continue;
            }
            if (send_reminder_email($pdo, $task, 'before', $daysBefore)) {
                log_reminder($pdo, (int) $task['id'], 'before', $task['due_date']);
                $stats['before']++;
            } else {
                $stats['errors']++;
            }
        }
    }

    if ($onDue) {
        $tasks = fetch_reminder_tasks($pdo, 'due_date = ?', [$today]);
        foreach ($tasks as $task) {
            if (reminder_already_sent($pdo, (int) $task['id'], 'due', $task['due_date'])) {
                $stats['skipped']++;
                continue;
            }
            if (send_reminder_email($pdo, $task, 'due', 0)) {
                log_reminder($pdo, (int) $task['id'], 'due', $task['due_date']);
                $stats['due']++;
            } else {
                $stats['errors']++;
            }
        }
    }

    if ($onOverdue) {
        $tasks = fetch_reminder_tasks($pdo, 'due_date < ?', [$today]);
        foreach ($tasks as $task) {
            // One overdue email per calendar day
            if (reminder_already_sent($pdo, (int) $task['id'], 'overdue', $today)) {
                $stats['skipped']++;
                continue;
            }
            if (send_reminder_email($pdo, $task, 'overdue', 0)) {
                log_reminder($pdo, (int) $task['id'], 'overdue', $today);
                $stats['overdue']++;
            } else {
                $stats['errors']++;
            }
        }
    }

    return $stats;
}

function fetch_reminder_tasks(PDO $pdo, string $dateClause, array $params): array
{
    $sql = "SELECT t.*, p.name AS project_name, u.name AS assignee_name, u.email AS assignee_email
            FROM tasks t
            JOIN projects p ON p.id = t.project_id AND p.status = 'active'
            JOIN users u ON u.id = t.assignee_id AND u.status = 'active'
            WHERE t.status != 'done'
              AND t.assignee_id IS NOT NULL
              AND t.due_date IS NOT NULL
              AND {$dateClause}
            ORDER BY t.due_date ASC, t.id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function reminder_already_sent(PDO $pdo, int $taskId, string $type, string $referenceDate): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM task_reminder_log
         WHERE task_id = ? AND reminder_type = ? AND reference_date = ? LIMIT 1'
    );
    $stmt->execute([$taskId, $type, $referenceDate]);
    return (bool) $stmt->fetchColumn();
}

function log_reminder(PDO $pdo, int $taskId, string $type, string $referenceDate): void
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO task_reminder_log (task_id, reminder_type, reference_date) VALUES (?, ?, ?)'
    );
    $stmt->execute([$taskId, $type, $referenceDate]);
}

function send_reminder_email(PDO $pdo, array $task, string $type, int $daysBefore): bool
{
    $appName = setting($pdo, 'app_name', 'Project Tracker');
    $email = $task['assignee_email'] ?? '';
    $name = $task['assignee_name'] ?? 'User';
    if (!$email) {
        return false;
    }

    if ($type === 'before') {
        $subject = "[{$appName}] Due in {$daysBefore} day" . ($daysBefore === 1 ? '' : 's') . ": {$task['title']}";
        $intro = "This task is due in {$daysBefore} day" . ($daysBefore === 1 ? '' : 's') . '.';
    } elseif ($type === 'due') {
        $subject = "[{$appName}] Due today: {$task['title']}";
        $intro = 'This task is due today.';
    } else {
        $subject = "[{$appName}] Overdue: {$task['title']}";
        $intro = 'This task is past its due date.';
    }

    $html = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:0 auto;color:#1e293b">'
        . '<h2 style="margin:0 0 12px;font-size:18px">' . e($appName) . ' reminder</h2>'
        . '<p>' . e($intro) . '</p>'
        . '<table style="width:100%;border-collapse:collapse;background:#f8fafc;border-radius:8px">'
        . '<tr><td style="padding:10px 14px;color:#64748b;width:120px">Task</td><td style="padding:10px 14px;font-weight:600">' . e($task['title']) . '</td></tr>'
        . '<tr><td style="padding:10px 14px;color:#64748b">Project</td><td style="padding:10px 14px">' . e($task['project_name'] ?? '') . '</td></tr>'
        . '<tr><td style="padding:10px 14px;color:#64748b">Status</td><td style="padding:10px 14px">' . e(status_label($task['status'])) . '</td></tr>'
        . '<tr><td style="padding:10px 14px;color:#64748b">Priority</td><td style="padding:10px 14px">' . e(priority_label($task['priority'])) . '</td></tr>'
        . '<tr><td style="padding:10px 14px;color:#64748b">Due</td><td style="padding:10px 14px">' . e($task['due_date']) . '</td></tr>'
        . '</table>'
        . (!empty($task['description']) ? '<p style="margin-top:16px;color:#475569">' . nl2br(e($task['description'])) . '</p>' : '')
        . '</div>';

    return send_smtp_mail($pdo, $email, $name, $subject, $html);
}

function ensure_reminder_cron_key(PDO $pdo): string
{
    $key = setting($pdo, 'reminder_cron_key', '');
    if ($key !== '') {
        return $key;
    }
    $key = bin2hex(random_bytes(16));
    set_setting($pdo, 'reminder_cron_key', $key);
    return $key;
}
