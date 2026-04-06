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

$title = 'Register';
$errors = ['name' => '', 'email' => '', 'password' => '', 'general' => ''];

if (is_post()) {
  csrf_verify();

  $name = str_trim($_POST['name'] ?? '');
  $email = strtolower(str_trim($_POST['email'] ?? ''));
  $password = (string)($_POST['password'] ?? '');

  if ($name === '' || mb_strlen($name) < 2) $errors['name'] = 'Name must be at least 2 characters.';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
  if (strlen($password) < 6) $errors['password'] = 'Password must be at least 6 characters.';

  if (!$errors['name'] && !$errors['email'] && !$errors['password']) {
    if (find_user_by_email($email)) {
      $errors['email'] = 'Email already registered. Try logging in.';
    } else {
      try {
        $uid = create_user($name, $email, $password);
        $user = find_user_by_id($uid);

        if (!$user) {
          throw new RuntimeException('User not found after registration.');
        }

        start_otp_flow($user, 'register');
        flash_set('success', 'Account created. Enter the code to verify your email.');
        redirect(base_url('/verify_otp.php'));
      } catch (Throwable $e) {
        $errors['general'] = 'Something went wrong. Please try again.';
      }
    }
  }
}

require_once __DIR__ . '/../src/partials/header.php';
?>
<div class="auth-wrap">
  <section class="card auth-card">
    <h1>Create account</h1>
    <p class="muted">Create your account, verify your email, and start tracking your expenses in a simple way.</p>

    <?php if ($errors['general']): ?>
      <div class="alert"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= csrf_field() ?>

      <div class="field">
        <label>Name</label>
        <input name="name" value="<?= e($_POST['name'] ?? '') ?>" placeholder="Your name" required>
        <?php if ($errors['name']): ?><div class="hint error"><?= e($errors['name']) ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label>Email</label>
        <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com" required>
        <?php if ($errors['email']): ?><div class="hint error"><?= e($errors['email']) ?></div><?php endif; ?>
      </div>

      <div class="field">
        <label>Password</label>
        <input type="password" name="password" placeholder="At least 6 characters" required>
        <?php if ($errors['password']): ?><div class="hint error"><?= e($errors['password']) ?></div><?php endif; ?>
      </div>

      <button class="btn btn-block" type="submit">Create account</button>

      <div class="auth-links muted small">
        <span>
          Already have an account?
          <a href="<?= e(base_url('/login.php')) ?>">Login</a>
        </span>
        <span>
          <a href="<?= e(base_url('/forgot_password.php')) ?>">Forgot password?</a>
        </span>
      </div>
    </form>
  </section>

  <aside class="card auth-side">
    <h2>What happens next</h2>
    <ul class="checklist">
      <li>Create your account</li>
      <li>Get a one-time code</li>
      <li>Verify your email</li>
      <li>Open your dashboard</li>
    </ul>
  </aside>
</div>
<?php require_once __DIR__ . '/../src/partials/footer.php'; ?>
