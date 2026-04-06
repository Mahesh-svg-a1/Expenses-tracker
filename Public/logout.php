<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/auth.php';

logout_user();
session_start(); // start again to set flash
flash_set('success', 'Logged out successfully.');
redirect(base_url('/login.php'));
