<?php
// src/helpers.php

declare(strict_types=1);

function config(string $key, mixed $default = null): mixed
{
  static $cfg = null;
  if ($cfg === null) $cfg = require __DIR__ . '/config.php';

  // dot-notation: app.base_url
  $parts = explode('.', $key);
  $value = $cfg;
  foreach ($parts as $p) {
    if (!is_array($value) || !array_key_exists($p, $value)) return $default;
    $value = $value[$p];
  }
  return $value;
}

function base_url(string $path = ''): string
{
  $base = rtrim((string)config('app.base_url', ''), '/');
  $path = '/' . ltrim($path, '/');
  return $base . ($path === '/' ? '' : $path);
}

function redirect(string $to): never
{
  header('Location: ' . $to);
  exit;
}

function e(?string $value): string
{
  return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash_set(string $type, string $message): void
{
  $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array
{
  $msgs = $_SESSION['flash'] ?? [];
  unset($_SESSION['flash']);
  return $msgs;
}

function is_post(): bool
{
  return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function str_trim(?string $s): string
{
  return trim((string)$s);
}

function money_fmt(float $n): string
{
  return number_format($n, 2, '.', ',');
}

function valid_month_key(string $monthKey): bool
{
  return (bool)preg_match('/^\d{4}-\d{2}$/', $monthKey);
}
