<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

$installed = false;
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim($_POST['db_host'] ?? 'localhost');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = (string) ($_POST['db_pass'] ?? '');
    $appUrl = rtrim(trim($_POST['app_url'] ?? ''), '/');
    $adminName = trim($_POST['admin_name'] ?? 'Admin');
    $adminEmail = strtolower(trim($_POST['admin_email'] ?? ''));
    $adminPass = (string) ($_POST['admin_pass'] ?? '');
    $timezone = trim($_POST['timezone'] ?? 'Asia/Manila');

    if ($name === '' || $user === '' || $adminEmail === '' || strlen($adminPass) < 6) {
        $error = 'Please fill all required fields. Admin password must be at least 6 characters.';
    } else {
        try {
            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $schema = file_get_contents(__DIR__ . '/schema.sql');
            $pdo->exec($schema);

            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password, role, status, approved_at) VALUES (?, ?, ?, 'admin', 'active', NOW())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), role = 'admin', status = 'active', approved_at = NOW()"
            );
            $stmt->execute([$adminName, $adminEmail, $hash]);

            $config = "<?php\n\nreturn [\n"
                . "    'db' => [\n"
                . "        'host' => " . var_export($host, true) . ",\n"
                . "        'name' => " . var_export($name, true) . ",\n"
                . "        'user' => " . var_export($user, true) . ",\n"
                . "        'pass' => " . var_export($pass, true) . ",\n"
                . "        'charset' => 'utf8mb4',\n"
                . "    ],\n"
                . "    'app' => [\n"
                . "        'name' => 'Project Tracker',\n"
                . "        'url' => " . var_export($appUrl ?: '', true) . ",\n"
                . "        'timezone' => " . var_export($timezone, true) . ",\n"
                . "    ],\n"
                . "];\n";

            $written = @file_put_contents(__DIR__ . '/../config/config.php', $config);
            if ($written === false) {
                throw new RuntimeException('Could not write config/config.php. Create it manually from config.sample.php.');
            }

            $success = 'Installation complete. You can log in now.';
            $installed = true;
        } catch (Throwable $e) {
            $error = 'Install failed: ' . $e->getMessage();
        }
    }
}

$configExists = file_exists(__DIR__ . '/../config/config.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Install · Project Tracker</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-shell">
  <div class="auth-card install-card">
    <h1>Install Project Tracker</h1>
    <p class="muted">Create a MySQL database in Hostinger hPanel, then fill this form.</p>

    <?php if ($error): ?><div class="flash flash-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="flash flash-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if ($installed || $configExists): ?>
      <p><a class="btn btn-primary" href="../login.php">Go to login</a></p>
      <p class="muted tiny">For security, delete or protect the <code>install/</code> folder after setup.</p>
    <?php else: ?>
    <form method="post" class="stack">
      <label>DB host <input name="db_host" value="localhost" required></label>
      <label>DB name <input name="db_name" required placeholder="u123_tracker"></label>
      <label>DB user <input name="db_user" required></label>
      <label>DB password <input name="db_pass" type="password"></label>
      <label>App URL <input name="app_url" placeholder="https://yourdomain.com"></label>
      <label>Timezone
        <select name="timezone">
          <option value="Asia/Manila">Asia/Manila</option>
          <option value="UTC">UTC</option>
          <option value="America/New_York">America/New_York</option>
          <option value="Europe/London">Europe/London</option>
          <option value="Asia/Singapore">Asia/Singapore</option>
        </select>
      </label>
      <hr>
      <label>Admin name <input name="admin_name" value="Admin" required></label>
      <label>Admin email <input name="admin_email" type="email" required></label>
      <label>Admin password <input name="admin_pass" type="password" minlength="6" required></label>
      <button class="btn btn-primary" type="submit">Install</button>
    </form>
    <?php endif; ?>
  </div>
</body>
</html>
