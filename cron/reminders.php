<?php

declare(strict_types=1);

/**
 * Run due-date reminders via cron (CLI or HTTP with secret key).
 *
 * Hostinger hPanel → Cron Jobs (daily):
 *   curl -s "https://yourdomain.com/cron/reminders.php?key=YOUR_KEY"
 *
 * Local Docker:
 *   docker compose exec web php /var/www/html/cron/reminders.php
 */

$isCli = PHP_SAPI === 'cli';

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../includes/reminders.php';

if (!$isCli) {
    $key = (string) ($_GET['key'] ?? '');
    $expected = ensure_reminder_cron_key($pdo);
    if ($key === '' || !hash_equals($expected, $key)) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo 'Forbidden';
        exit;
    }
    header('Content-Type: application/json');
}

$stats = send_due_reminders($pdo);
$payload = json_encode([
    'ok' => true,
    'at' => date('c'),
    'stats' => $stats,
], JSON_PRETTY_PRINT);

if ($isCli) {
    echo $payload . PHP_EOL;
} else {
    echo $payload;
}
