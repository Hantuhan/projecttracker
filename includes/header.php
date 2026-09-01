<?php
$user = current_user();
$appName = setting($pdo, 'app_name', $config['app']['name'] ?? 'Project Tracker');
$flash = get_flash();
$pageTitle = $pageTitle ?? $appName;
$current = $currentPage ?? '';
$pendingCount = 0;
if ($user && ($user['role'] ?? '') === 'admin') {
    try {
        $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
    } catch (Throwable $e) {
        $pendingCount = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · <?= e($appName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php if ($user): ?>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand">
      <span class="brand-mark"></span>
      <span class="brand-name"><?= e($appName) ?></span>
    </div>
    <nav class="nav">
      <a href="index.php" class="<?= $current === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
      <a href="list.php" class="<?= $current === 'list' ? 'active' : '' ?>">List</a>
      <a href="kanban.php" class="<?= $current === 'kanban' ? 'active' : '' ?>">Kanban</a>
      <a href="projects.php" class="<?= $current === 'projects' ? 'active' : '' ?>">Projects</a>
      <?php if ($user['role'] === 'admin'): ?>
      <a href="team.php" class="<?= $current === 'team' ? 'active' : '' ?>">
        Team
        <?php if ($pendingCount > 0): ?>
          <span class="nav-badge"><?= $pendingCount ?></span>
        <?php endif; ?>
      </a>
      <a href="settings.php" class="<?= $current === 'settings' ? 'active' : '' ?>">Settings</a>
      <?php endif; ?>
      <a href="profile.php" class="<?= $current === 'profile' ? 'active' : '' ?>">Profile</a>
    </nav>
    <div class="sidebar-foot">
      <div class="user-chip">
        <span class="avatar"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></span>
        <div>
          <strong><?= e($user['name']) ?></strong>
          <small><?= e($user['role']) ?></small>
        </div>
      </div>
      <a class="logout" href="logout.php">Log out</a>
    </div>
  </aside>
  <main class="main">
    <?php if ($flash): ?>
      <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
<?php else: ?>
<div class="auth-shell">
  <?php if ($flash): ?>
    <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
  <?php endif; ?>
<?php endif; ?>
