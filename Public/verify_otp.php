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

$pendingUser = current_otp_user();
if (!$pendingUser) {
  flash_set('warning', 'Please start the login, signup, or reset process again.');
  redirect(base_url('/login.php'));
}

$title = 'Verify Code';
$errors = ['otp' => '', 'general' => ''];
$otpLength = otp_length();
$otpSource = current_otp_source();

if (is_post()) {
  csrf_verify();

  $action = str_trim($_POST['action'] ?? 'verify');

  if ($action === 'resend') {
    $result = resend_otp_for_current_user();
    if ($result['ok']) {
      redirect(base_url('/verify_otp.php'));
    }

    $errors['general'] = $result['message'];
    $pendingUser = current_otp_user() ?? $pendingUser;
  }

  if ($action === 'verify') {
    $otp = preg_replace('/\D+/', '', (string)($_POST['otp'] ?? ''));

    if (strlen($otp) !== $otpLength) {
      $errors['otp'] = 'Enter the ' . $otpLength . '-digit code.';
    } else {
      $result = verify_current_otp($otp);

      if ($result['ok']) {
        if ($otpSource === 'reset') {
          set_pending_password_reset_user((int)$result['user']['id']);
          flash_set('success', 'Code verified. You can now create a new password.');
          redirect(base_url('/reset_password.php'));
        }

        login_user($result['user']);
        flash_set('success', $otpSource === 'register' ? 'Email verified. Welcome to your account.' : 'Login successful.');
        redirect(base_url('/dashboard.php'));
      }

      $errors['general'] = $result['message'];
      $pendingUser = current_otp_user() ?? $pendingUser;
    }
  }
}

$resendWait = seconds_until_otp_resend($pendingUser);
$backUrl = $otpSource === 'reset' ? base_url('/forgot_password.php') : base_url('/login.php');
$backLabel = $otpSource === 'reset' ? 'Start reset again' : 'Back to login';

require_once __DIR__ . '/../src/partials/header.php';
?>
<div class="auth-wrap">
  <section class="card auth-card">
    <h1>Verify code</h1>
    <p class="muted">
      <?= $otpSource === 'register'
        ? 'Enter the code we sent to finish setting up your account.'
        : ($otpSource === 'reset'
          ? 'Enter the code we sent so you can reset your password.'
          : 'Enter the code we sent to finish logging you in.') ?>
    </p>

    <?php if ($errors['general']): ?>
      <div class="alert"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify">

      <div class="field">
        <label>Verification code</label>
        <input
          type="text"
          name="otp"
          inputmode="numeric"
          maxlength="<?= $otpLength ?>"
          pattern="\d{<?= $otpLength ?>}"
          placeholder="Enter <?= $otpLength ?> digits"
          value="<?= e($_POST['otp'] ?? '') ?>"
          required
        >
        <?php if ($errors['otp']): ?><div class="hint error"><?= e($errors['otp']) ?></div><?php endif; ?>
      </div>

      <button class="btn btn-block" type="submit">Verify code</button>
    </form>

    <form method="post" class="form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="resend">
      <button
        class="btn btn-ghost btn-block"
        type="submit"
        <?= $resendWait > 0 ? 'disabled' : '' ?>
        data-resend-seconds="<?= $resendWait ?>"
        data-resend-label="Resend code"
      >
        <?= $resendWait > 0 ? 'Resend in ' . $resendWait . 's' : 'Resend code' ?>
      </button>
    </form>

    <div class="auth-links muted small">
      <span>Need a different email?</span>
      <span><a href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a></span>
    </div>
  </section>

  <aside class="card auth-side">
    <h2>Verification details</h2>
    <p class="muted">We will check the code for this email:</p>

    <div class="mini-stat">
      <div class="mini-stat-label">Email</div>
      <div class="mini-stat-value"><?= e((string)$pendingUser['email']) ?></div>
    </div>

    <ul class="checklist">
      <li>Code length: <?= $otpLength ?> digits</li>
      <li>Code expires in <?= otp_expires_minutes() ?> minutes</li>
      <li>You can resend after <?= otp_resend_cooldown_seconds() ?> seconds</li>
    </ul>
  </aside>
</div>
<?php require_once __DIR__ . '/../src/partials/footer.php'; ?>
