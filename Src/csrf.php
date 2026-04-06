<?php
// src/csrf.php

declare(strict_types=1);

function csrf_token(): string
{
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
  $t = csrf_token();
  return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($t, ENT_QUOTES) . '">';
}

function csrf_verify(): void
{
  if (!is_post()) return;

  $sent = $_POST['csrf_token'] ?? '';
  $real = $_SESSION['csrf_token'] ?? '';

  if (!$sent || !$real || !hash_equals($real, (string)$sent)) {
    http_response_code(419);
    die('CSRF token mismatch. Please go back and refresh.');
  }
}
