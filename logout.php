<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Auth.php';

Auth::logout();
header('Location: login.php');
exit;
