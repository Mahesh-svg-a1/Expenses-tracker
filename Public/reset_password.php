<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/csrf.php';

date_default_timezone_set((string)config('app.timezone', 'UTC'));

if (auth_user()) {
  redirect(base_url('/dashboard.php'));
}

$resetUser = current_password_reset_user();
if (!$resetUser) {
  flash_set('warning', 'Please verify the reset code first.');
  redirect(base_url('/forgot_password.php'));
}

$title = 'Reset Password';
$errors = ['password' => '', 'confirm_password' => '', 'general' => ''];

if (is_post()) {
  csrf_verify();

  $password = (string)($_POST['password'] ?? '');
  $confirmPassword = (string)($_POST['confirm_password'] ?? '');

  if (strlen($password) < 6) {
    $errors['password'] = 'Password must be at least 6 characters.';
  }

  if ($confirmPassword === '') {
    $errors['confirm_password'] = 'Please confirm your password.';
  } elseif ($password !== $confirmPassword) {
    $errors['confirm_password'] = 'Passwords do not match.';
  }

  if (!$errors['password'] && !$errors['confirm_password']) {
    update_user_password((int)$resetUser['id'], $password);
    clear_pending_password_reset_user();
    flash_set('success', 'Your password has been updated. Please login again.');
    redirect(base_url('/login.php'));
  }
}

require_once __DIR__ . '/../src/partials/header.php';
?>
<div class="auth-wrap">
  <section class="card auth-card">
    <h1>Create a new password</h1>
    <p class="muted">Choose a simple password you will remember. You can change it again later.</p>

    <?php if ($errors['general']): ?>
      <div class="alert"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= csrf_field() ?>

      <div class="field">
        <label>New password</label>
        <input type="password" name="password" placeholder="At least 6 characters" required>
        <?php if ($errors['password']): ?><div class="hint error"><?= e($errors['password']) ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label>Confirm password</label>
        <input type="password" name="confirm_password" placeholder="Type it again" required>
        <?php if ($errors['confirm_password']): ?><div class="hint error"><?= e($errors['confirm_password']) ?></div><?php endif; ?>
      </div>

      <button class="btn btn-block" type="submit">Save password</button>

      <div class="auth-links muted small">
        <span>Resetting for: <?= e((string)$resetUser['email']) ?></span>
        <span><a href="<?= e(base_url('/forgot_password.php')) ?>">Start over</a></span>
      </div>
    </form>
  </section>

  <aside class="card auth-side">
    <h2>Simple password tips</h2>
    <ul class="checklist">
      <li>Use at least 6 characters</li>
      <li>Pick something easy for you to remember</li>
      <li>Do not reuse old passwords if you can avoid it</li>
    </ul>
  </aside>
</div>
<?php require_once __DIR__ . '/../src/partials/footer.php'; ?>
