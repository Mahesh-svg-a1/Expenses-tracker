<?php
// src/partials/header.php
declare(strict_types=1);

$user = auth_user();
$flash = flash_get();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= e($title ?? 'Expense Tracker') ?></title>
  <link rel="stylesheet" href="<?= e(base_url('/assets/css/styles.css')) ?>">
  <script defer src="<?= e(base_url('/assets/js/app.js')) ?>"></script>
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">
        <div class="logo">ET</div>
        <div>
          <div class="brand-title">Expense Tracker</div>
          <div class="brand-sub">A simple place to keep track of daily spending.</div>
        </div>
      </div>

      <nav class="nav">
        <?php if ($user): ?>
          <a class="nav-link" href="<?= e(base_url('/dashboard.php')) ?>">Dashboard</a>
          <a class="nav-link" href="<?= e(base_url('/expenses.php')) ?>">Expenses</a>
          <a class="nav-link" href="<?= e(base_url('/categories.php')) ?>">Categories</a>
          <a class="nav-link" href="<?= e(base_url('/budgets.php')) ?>">Budgets</a>
          <a class="nav-link" href="<?= e(base_url('/reports.php')) ?>">Reports</a>
          <div class="nav-sep"></div>
          <div class="nav-user">
            <span class="chip"><?= e($user['name']) ?></span>
            <a class="btn btn-ghost" href="<?= e(base_url('/logout.php')) ?>">Logout</a>
          </div>
        <?php else: ?>
          <a class="nav-link" href="<?= e(base_url('/login.php')) ?>">Login</a>
          <a class="btn" href="<?= e(base_url('/register.php')) ?>">Register</a>
        <?php endif; ?>
      </nav>
    </header>

    <main class="content">
      <?php if (!empty($flash)): ?>
        <div class="flash-stack">
          <?php foreach ($flash as $f): ?>
            <div class="flash flash-<?= e($f['type']) ?>">
              <div class="flash-dot"></div>
              <div><?= e($f['message']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
