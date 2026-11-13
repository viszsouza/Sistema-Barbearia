<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
redirect(is_logged_in() ? ADMIN_URL . '/dashboard.php' : ADMIN_URL . '/login.php');
