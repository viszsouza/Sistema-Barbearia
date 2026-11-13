<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
logout_admin();
redirect(ADMIN_URL . '/login.php');
