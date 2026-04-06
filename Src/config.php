<?php
// src/config.php

declare(strict_types=1);

return [
  'db' => [
    'host' => '127.0.0.1',
    'name' => 'expense_tracker',
    'user' => 'root',
    'pass' => '', // XAMPP default is empty
    'charset' => 'utf8mb4',
  ],
  'app' => [
    // IMPORTANT:
    // If you access the project like: http://localhost/expense-tracker/public/
    // then base_url should be '/expense-tracker/public'
    'base_url' => '/expense-tracker/public',
    'timezone' => 'Asia/Kathmandu',
  ],
  'otp' => [
    'length' => 6,
    'expires_minutes' => 10,
    'resend_cooldown_seconds' => 60,
    'debug_show_code' => true,
    'from_email' => 'no-reply@expense-tracker.local',
  ],
];
