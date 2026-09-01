<?php

declare(strict_types=1);

// Harden session cookie before starting
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    if (basename($_SERVER['PHP_SELF'] ?? '') !== 'install.php') {
        header('Location: install/install.php');
        exit;
    }
    return;
}

$config = require $configPath;
date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['db']['host'],
        $config['db']['name'],
        $config['db']['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database connection failed. Check config/config.php.';
    exit;
}

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/reminders.php';
