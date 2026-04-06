<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

function auth_user(): ?array
{
  return $_SESSION['user'] ?? null;
}

function require_auth(): void
{
  if (!auth_user()) {
    flash_set('warning', 'Please login to continue.');
    redirect(base_url('/login.php'));
  }
}

function login_user(array $userRow): void
{
  // minimal session user payload
  $_SESSION['user'] = [
    'id' => (int)$userRow['id'],
    'name' => (string)$userRow['name'],
    'email' => (string)$userRow['email'],
  ];

  // basic session fixation mitigation
  session_regenerate_id(true);
}

function logout_user(): void
{
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
  }
  session_destroy();
}

function find_user_by_id(int $userId): ?array
{
  $pdo = db();
  $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
  $stmt->execute([$userId]);
  $row = $stmt->fetch();
  return $row ?: null;
}

function find_user_by_email(string $email): ?array
{
  $pdo = db();
  $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $row = $stmt->fetch();
  return $row ?: null;
}

function create_user(string $name, string $email, string $password): int
{
  $pdo = db();
  $hash = password_hash($password, PASSWORD_DEFAULT);

  $stmt = $pdo->prepare('
    INSERT INTO users (name, email, password_hash, is_verified)
    VALUES (?, ?, ?, 0)
  ');
  $stmt->execute([$name, $email, $hash]);

  return (int)$pdo->lastInsertId();
}

function otp_length(): int
{
  $length = (int)config('otp.length', 6);
  if ($length < 4) return 4;
  if ($length > 8) return 8;
  return $length;
}

function otp_expires_minutes(): int
{
  $minutes = (int)config('otp.expires_minutes', 10);
  return $minutes > 0 ? $minutes : 10;
}

function otp_resend_cooldown_seconds(): int
{
  $seconds = (int)config('otp.resend_cooldown_seconds', 60);
  return $seconds >= 0 ? $seconds : 60;
}

function normalize_otp_source(string $source): string
{
  $allowed = ['login', 'register', 'reset'];
  return in_array($source, $allowed, true) ? $source : 'login';
}

function generate_otp_code(): string
{
  $length = otp_length();
  $min = (int)str_pad('1', $length, '0');
  $max = (int)str_repeat('9', $length);
  return (string)random_int($min, $max);
}

function save_user_otp(int $userId, string $code): void
{
  $pdo = db();
  $hash = password_hash($code, PASSWORD_DEFAULT);
  $expiresAt = date('Y-m-d H:i:s', time() + (otp_expires_minutes() * 60));
  $sentAt = date('Y-m-d H:i:s');

  $stmt = $pdo->prepare('
    UPDATE users
    SET otp_code_hash = ?, otp_expires_at = ?, otp_sent_at = ?
    WHERE id = ?
    LIMIT 1
  ');
  $stmt->execute([$hash, $expiresAt, $sentAt, $userId]);
}

function clear_user_otp(int $userId): void
{
  $pdo = db();
  $stmt = $pdo->prepare('
    UPDATE users
    SET otp_code_hash = NULL, otp_expires_at = NULL, otp_sent_at = NULL
    WHERE id = ?
    LIMIT 1
  ');
  $stmt->execute([$userId]);
}

function mark_user_verified(int $userId): void
{
  $pdo = db();
  $stmt = $pdo->prepare('
    UPDATE users
    SET is_verified = 1, verified_at = NOW()
    WHERE id = ?
    LIMIT 1
  ');
  $stmt->execute([$userId]);
}

function send_otp_email(array $user, string $code): bool
{
  if (!function_exists('mail')) {
    return false;
  }

  $minutes = otp_expires_minutes();
  $subject = 'Your Expense Tracker OTP';
  $message = "Hello {$user['name']},\n\n";
  $message .= "Your OTP code is: {$code}\n";
  $message .= "It will expire in {$minutes} minute(s).\n\n";
  $message .= "If you did not request this code, you can ignore this email.\n";

  $headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
  ];

  $fromEmail = str_trim((string)config('otp.from_email', ''));
  if ($fromEmail !== '') {
    $headers[] = 'From: ' . $fromEmail;
  }

  return @mail((string)$user['email'], $subject, $message, implode("\r\n", $headers));
}

function set_pending_otp_session(int $userId, string $source): void
{
  $_SESSION['pending_otp_user_id'] = $userId;
  $_SESSION['pending_otp_source'] = normalize_otp_source($source);
}

function clear_pending_otp_session(): void
{
  unset($_SESSION['pending_otp_user_id'], $_SESSION['pending_otp_source']);
}

function current_otp_source(): string
{
  $source = (string)($_SESSION['pending_otp_source'] ?? 'login');
  return normalize_otp_source($source);
}

function current_otp_user(): ?array
{
  $userId = (int)($_SESSION['pending_otp_user_id'] ?? 0);
  if ($userId <= 0) {
    return null;
  }

  return find_user_by_id($userId);
}

function seconds_until_otp_resend(array $user): int
{
  $sentAt = (string)($user['otp_sent_at'] ?? '');
  if ($sentAt === '') {
    return 0;
  }

  $sentTime = strtotime($sentAt);
  if ($sentTime === false) {
    return 0;
  }

  $waitSeconds = otp_resend_cooldown_seconds() - (time() - $sentTime);
  return $waitSeconds > 0 ? $waitSeconds : 0;
}

function start_otp_flow(array $user, string $source): void
{
  $code = generate_otp_code();
  save_user_otp((int)$user['id'], $code);
  set_pending_otp_session((int)$user['id'], $source);

  if (send_otp_email($user, $code)) {
    flash_set('success', 'OTP sent to your email address.');
    return;
  }

  flash_set('warning', 'OTP email could not be sent from this local server.');
  if ((bool)config('otp.debug_show_code', true)) {
    flash_set('warning', 'Demo OTP: ' . $code);
  }
}

function resend_otp_for_current_user(): array
{
  $user = current_otp_user();
  if (!$user) {
    return ['ok' => false, 'message' => 'OTP session expired. Please login again.'];
  }

  $waitSeconds = seconds_until_otp_resend($user);
  if ($waitSeconds > 0) {
    return ['ok' => false, 'message' => 'Please wait ' . $waitSeconds . ' second(s) before resending.'];
  }

  start_otp_flow($user, current_otp_source());
  return ['ok' => true, 'message' => 'A new OTP has been sent.'];
}

function verify_current_otp(string $code): array
{
  $user = current_otp_user();
  if (!$user) {
    return ['ok' => false, 'message' => 'OTP session expired. Please login again.'];
  }

  if ($code === '') {
    return ['ok' => false, 'message' => 'Enter the OTP code.'];
  }

  $storedHash = (string)($user['otp_code_hash'] ?? '');
  $expiresAt = (string)($user['otp_expires_at'] ?? '');

  if ($storedHash === '' || $expiresAt === '') {
    return ['ok' => false, 'message' => 'OTP not found. Please resend a new code.'];
  }

  $expiresTime = strtotime($expiresAt);
  if ($expiresTime === false || $expiresTime < time()) {
    return ['ok' => false, 'message' => 'OTP expired. Please resend a new code.'];
  }

  if (!password_verify($code, $storedHash)) {
    return ['ok' => false, 'message' => 'Invalid OTP code.'];
  }

  if (!(bool)($user['is_verified'] ?? false)) {
    mark_user_verified((int)$user['id']);
  }

  clear_user_otp((int)$user['id']);
  clear_pending_otp_session();

  $freshUser = find_user_by_id((int)$user['id']);
  if (!$freshUser) {
    return ['ok' => false, 'message' => 'User account was not found after OTP verification.'];
  }

  return ['ok' => true, 'message' => 'OTP verified successfully.', 'user' => $freshUser];
}

function set_pending_password_reset_user(int $userId): void
{
  $_SESSION['password_reset_user_id'] = $userId;
}

function current_password_reset_user(): ?array
{
  $userId = (int)($_SESSION['password_reset_user_id'] ?? 0);
  if ($userId <= 0) {
    return null;
  }

  return find_user_by_id($userId);
}

function clear_pending_password_reset_user(): void
{
  unset($_SESSION['password_reset_user_id']);
}

function update_user_password(int $userId, string $password): void
{
  $pdo = db();
  $hash = password_hash($password, PASSWORD_DEFAULT);

  $stmt = $pdo->prepare('
    UPDATE users
    SET password_hash = ?
    WHERE id = ?
    LIMIT 1
  ');
  $stmt->execute([$hash, $userId]);
}
