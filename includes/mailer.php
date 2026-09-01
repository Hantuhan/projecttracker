<?php

declare(strict_types=1);

/**
 * Lightweight SMTP client (no Composer required — Hostinger friendly).
 */
function send_smtp_mail(PDO $pdo, string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    $host = setting($pdo, 'smtp_host');
    $port = (int) (setting($pdo, 'smtp_port', '587') ?: 587);
    $user = setting($pdo, 'smtp_user');
    $pass = setting($pdo, 'smtp_pass');
    $fromEmail = setting($pdo, 'smtp_from_email') ?: $user;
    $fromName = setting($pdo, 'smtp_from_name', 'Project Tracker');
    $encryption = strtolower(setting($pdo, 'smtp_encryption', 'tls') ?: 'tls');

    if (!$host || !$fromEmail || !$toEmail) {
        return false;
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
    $fp = @fsockopen($remote, $port, $errno, $errstr, 20);
    if (!$fp) {
        error_log("SMTP connect failed: $errstr ($errno)");
        return false;
    }
    stream_set_timeout($fp, 20);

    $read = static function () use ($fp): string {
        $data = '';
        while ($line = fgets($fp, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $write = static function (string $cmd) use ($fp): void {
        fwrite($fp, $cmd . "\r\n");
    };

    $expect = static function (string $response, string $code): bool {
        return str_starts_with(trim($response), $code);
    };

    try {
        if (!$expect($read(), '220')) {
            throw new RuntimeException('Bad greeting');
        }
        $write('EHLO localhost');
        $ehlo = $read();
        if (!$expect($ehlo, '250')) {
            $write('HELO localhost');
            if (!$expect($read(), '250')) {
                throw new RuntimeException('EHLO/HELO failed');
            }
        }

        if ($encryption === 'tls') {
            $write('STARTTLS');
            if (!$expect($read(), '220')) {
                throw new RuntimeException('STARTTLS failed');
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS handshake failed');
            }
            $write('EHLO localhost');
            if (!$expect($read(), '250')) {
                throw new RuntimeException('EHLO after TLS failed');
            }
        }

        if ($user && $pass !== null && $pass !== '') {
            $write('AUTH LOGIN');
            if (!$expect($read(), '334')) {
                throw new RuntimeException('AUTH rejected');
            }
            $write(base64_encode($user));
            if (!$expect($read(), '334')) {
                throw new RuntimeException('Username rejected');
            }
            $write(base64_encode($pass));
            if (!$expect($read(), '235')) {
                throw new RuntimeException('Password rejected');
            }
        }

        $write('MAIL FROM:<' . $fromEmail . '>');
        if (!$expect($read(), '250')) {
            throw new RuntimeException('MAIL FROM failed');
        }
        $write('RCPT TO:<' . $toEmail . '>');
        if (!$expect($read(), '250')) {
            throw new RuntimeException('RCPT TO failed');
        }
        $write('DATA');
        if (!$expect($read(), '354')) {
            throw new RuntimeException('DATA failed');
        }

        $boundary = 'b_' . bin2hex(random_bytes(8));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . encode_address($fromName, $fromEmail),
            'To: ' . encode_address($toName, $toEmail),
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $plain = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $htmlBody));
        $body = implode("\r\n", $headers) . "\r\n\r\n";
        $body .= "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$plain}\r\n\r\n";
        $body .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$htmlBody}\r\n\r\n";
        $body .= "--{$boundary}--\r\n.";

        $write($body);
        if (!$expect($read(), '250')) {
            throw new RuntimeException('Message rejected');
        }
        $write('QUIT');
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        error_log('SMTP error: ' . $e->getMessage());
        if (is_resource($fp)) {
            fclose($fp);
        }
        return false;
    }
}

function encode_address(string $name, string $email): string
{
    $safe = trim($name) !== '' ? '=?UTF-8?B?' . base64_encode($name) . '?= ' : '';
    return $safe . '<' . $email . '>';
}

function notify_task_update(PDO $pdo, array $task, string $event, ?array $actor = null): void
{
    if (empty($task['assignee_id'])) {
        return;
    }

    $notifyAssign = setting($pdo, 'notify_on_assign', '1') === '1';
    $notifyStatus = setting($pdo, 'notify_on_status', '1') === '1';

    if ($event === 'assigned' && !$notifyAssign) {
        return;
    }
    if ($event === 'status' && !$notifyStatus) {
        return;
    }

    $stmt = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ?');
    $stmt->execute([$task['assignee_id']]);
    $assignee = $stmt->fetch();
    if (!$assignee || !$assignee['email']) {
        return;
    }

    // Don't email yourself for your own change
    if ($actor && (int) $actor['id'] === (int) $assignee['id']) {
        return;
    }

    $projectStmt = $pdo->prepare('SELECT name FROM projects WHERE id = ?');
    $projectStmt->execute([$task['project_id']]);
    $project = $projectStmt->fetch();
    $projectName = $project['name'] ?? 'Project';
    $appName = setting($pdo, 'app_name', 'Project Tracker');
    $actorName = $actor['name'] ?? 'Someone';

    if ($event === 'assigned') {
        $subject = "[{$appName}] Assigned: {$task['title']}";
        $intro = "{$actorName} assigned you a task.";
    } else {
        $subject = "[{$appName}] Updated: {$task['title']}";
        $intro = "{$actorName} updated a task assigned to you.";
    }

    $html = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:0 auto;color:#1e293b">'
        . '<h2 style="margin:0 0 12px;font-size:18px">' . e($appName) . '</h2>'
        . '<p>' . e($intro) . '</p>'
        . '<table style="width:100%;border-collapse:collapse;background:#f8fafc;border-radius:8px">'
        . '<tr><td style="padding:10px 14px;color:#64748b;width:120px">Task</td><td style="padding:10px 14px;font-weight:600">' . e($task['title']) . '</td></tr>'
        . '<tr><td style="padding:10px 14px;color:#64748b">Project</td><td style="padding:10px 14px">' . e($projectName) . '</td></tr>'
        . '<tr><td style="padding:10px 14px;color:#64748b">Status</td><td style="padding:10px 14px">' . e(status_label($task['status'])) . '</td></tr>'
        . '<tr><td style="padding:10px 14px;color:#64748b">Priority</td><td style="padding:10px 14px">' . e(priority_label($task['priority'])) . '</td></tr>'
        . (!empty($task['due_date']) ? '<tr><td style="padding:10px 14px;color:#64748b">Due</td><td style="padding:10px 14px">' . e($task['due_date']) . '</td></tr>' : '')
        . '</table>'
        . (!empty($task['description']) ? '<p style="margin-top:16px;color:#475569">' . nl2br(e($task['description'])) . '</p>' : '')
        . '</div>';

    send_smtp_mail($pdo, $assignee['email'], $assignee['name'], $subject, $html);
}

function notify_user_invite(PDO $pdo, array $invitee, string $tempPassword, array $inviter): void
{
    $appName = setting($pdo, 'app_name', 'Project Tracker');
    global $config;
    $loginUrl = rtrim((string) ($config['app']['url'] ?? ''), '/') . '/login.php';
    if ($loginUrl === '/login.php') {
        $loginUrl = 'login.php';
    }

    $subject = "[{$appName}] You've been invited — awaiting approval";
    $html = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:0 auto;color:#1e293b">'
        . '<h2 style="margin:0 0 12px;font-size:18px">' . e($appName) . '</h2>'
        . '<p>' . e($inviter['name']) . ' invited you to join the team.</p>'
        . '<p>Your account is <strong>pending admin approval</strong>. You will get another email when you can sign in.</p>'
        . '<table style="width:100%;border-collapse:collapse;background:#f8fafc;border-radius:8px">'
        . '<tr><td style="padding:10px 14px;color:#64748b;width:120px">Email</td><td style="padding:10px 14px">' . e($invitee['email']) . '</td></tr>'
        . '<tr><td style="padding:10px 14px;color:#64748b">Temp password</td><td style="padding:10px 14px;font-family:monospace">' . e($tempPassword) . '</td></tr>'
        . '</table>'
        . ($loginUrl !== 'login.php' ? '<p style="margin-top:16px"><a href="' . e($loginUrl) . '">Open login</a></p>' : '')
        . '</div>';

    send_smtp_mail($pdo, $invitee['email'], $invitee['name'], $subject, $html);
}

function notify_user_approved(PDO $pdo, array $member, ?string $password = null): void
{
    $appName = setting($pdo, 'app_name', 'Project Tracker');
    global $config;
    $loginUrl = rtrim((string) ($config['app']['url'] ?? ''), '/') . '/login.php';
    if ($loginUrl === '/login.php') {
        $loginUrl = 'login.php';
    }

    $subject = "[{$appName}] Your account was approved";
    $html = '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:0 auto;color:#1e293b">'
        . '<h2 style="margin:0 0 12px;font-size:18px">' . e($appName) . '</h2>'
        . '<p>Good news — an admin approved your account. You can sign in now.</p>'
        . '<table style="width:100%;border-collapse:collapse;background:#f8fafc;border-radius:8px">'
        . '<tr><td style="padding:10px 14px;color:#64748b;width:120px">Email</td><td style="padding:10px 14px">' . e($member['email']) . '</td></tr>'
        . ($password
            ? '<tr><td style="padding:10px 14px;color:#64748b">Password</td><td style="padding:10px 14px;font-family:monospace">' . e($password) . '</td></tr>'
            : '<tr><td style="padding:10px 14px;color:#64748b">Password</td><td style="padding:10px 14px">Use the temporary password from your invite email (or ask admin to reset).</td></tr>')
        . '</table>'
        . ($loginUrl !== 'login.php' ? '<p style="margin-top:16px"><a href="' . e($loginUrl) . '">Sign in</a></p>' : '')
        . '</div>';

    send_smtp_mail($pdo, $member['email'], $member['name'], $subject, $html);
}
