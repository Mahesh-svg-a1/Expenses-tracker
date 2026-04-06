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

$title = 'Login';
$errors = ['email' => '', 'password' => '', 'general' => ''];

if (is_post()) {
  csrf_verify();

  $email = strtolower(str_trim($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
  if ($password === '') $errors['password'] = 'Password is required.';

  if (!$errors['email'] && !$errors['password']) {
    $user = find_user_by_email($email);

    if (!$user || !password_verify($password, $user['password_hash'])) {
      $errors['general'] = 'Invalid email or password.';
    } else {
      start_otp_flow($user, 'login');

      if (!(bool)($user['is_verified'] ?? false)) {
        flash_set('warning', 'Your account is not verified yet. Enter the code to continue.');
      } else {
        flash_set('success', 'We sent a login code to your email.');
      }

      redirect(base_url('/verify_otp.php'));
    }
  }
}

require_once __DIR__ . '/../src/partials/header.php';
?>
<div class="auth-wrap">
  <section class="card auth-card">
    <h1>Login</h1>
    <p class="muted">Enter your password and then confirm the login with the code we send.</p>

    <?php if ($errors['general']): ?>
      <div class="alert"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= csrf_field() ?>

      <div class="field">
        <label>Email</label>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com" required>
        <?php if ($errors['email']): ?><div class="hint error"><?= e($errors['email']) ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label>Password</label>
        <input type="password" name="password" placeholder="Your password" required>
        <?php if ($errors['password']): ?><div class="hint error"><?= e($errors['password']) ?></div><?php endif; ?>
      </div>

      <button class="btn btn-block" type="submit">Continue</button>

      <div class="auth-links muted small">
        <span>
          New here?
          <a href="<?= e(base_url('/register.php')) ?>">Create an account</a>
        </span>
        <span>
          <a href="<?= e(base_url('/forgot_password.php')) ?>">Forgot password?</a>
        </span>
      </div>
    </form>
  </section>

  <aside class="card auth-side">
    <h2>A quick note</h2>
    <p class="muted">
      A short daily entry is enough. You do not need to remember every payment later.
    </p>

    <div class="mini-stat">
      <div class="mini-stat-label">Good habit</div>
      <div class="mini-stat-value">Write it down while it is fresh</div>
    </div>
  </aside>
</div>
<?php require_once __DIR__ . '/../src/partials/footer.php'; ?>
