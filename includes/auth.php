<?php

declare(strict_types=1);

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    global $pdo;
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }

    // Re-validate against DB so rejected/deleted/demoted users lose access
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare('SELECT id, name, email, role, status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $user['id']]);
        $row = $stmt->fetch();
        if (!$row || ($row['status'] ?? '') !== 'active') {
            logout_user();
            flash('error', 'Your account is no longer active. Please contact an admin.');
            redirect('login.php');
        }
        $_SESSION['user'] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'role' => $row['role'],
        ];
        $user = $_SESSION['user'];
    }

    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        flash('error', 'Admin access required.');
        redirect('index.php');
    }
    return $user;
}

/**
 * @return true|string true on success, or error message key
 */
function attempt_login(PDO $pdo, string $email, string $password)
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        return 'invalid';
    }

    $status = $user['status'] ?? 'active';
    if ($status === 'pending') {
        return 'pending';
    }
    if ($status === 'rejected') {
        return 'rejected';
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
    return true;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function generate_temp_password(int $length = 10): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $out = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

function active_users(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT id, name, email, role FROM users WHERE status = 'active' ORDER BY name"
    )->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
    }
    return $rows;
}
