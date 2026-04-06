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

$title = 'Forgot Password';
$errors = ['email' => '', 'general' => ''];

if (is_post()) {
  csrf_verify();

  $email = strtolower(str_trim($_POST['email'] ?? ''));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
  } else {
    $user = find_user_by_email($email);

    if ($user) {
      start_otp_flow($user, 'reset');
      flash_set('success', 'We sent a reset code to your email.');
      redirect(base_url('/verify_otp.php'));
    }

    flash_set('success', 'If that email exists, a reset code has been sent.');
    redirect(base_url('/login.php'));
  }
}

require_once __DIR__ . '/../src/partials/header.php';
?>
<div class="auth-wrap">
  <section class="card auth-card">
    <h1>Forgot password</h1>
    <p class="muted">Enter your email and we will send you a code to reset your password.</p>

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

      <button class="btn btn-block" type="submit">Send reset code</button>

      <div class="auth-links muted small">
        <span><a href="<?= e(base_url('/login.php')) ?>">Back to login</a></span>
        <span><a href="<?= e(base_url('/register.php')) ?>">Create account</a></span>
      </div>
    </form>
  </section>

  <aside class="card auth-side">
    <h2>How it works</h2>
    <ul class="checklist">
      <li>Enter the email linked to your account</li>
      <li>Receive a one-time code</li>
      <li>Verify the code</li>
      <li>Create a new password</li>
    </ul>
  </aside>
</div>
<?php require_once __DIR__ . '/../src/partials/footer.php'; ?>
