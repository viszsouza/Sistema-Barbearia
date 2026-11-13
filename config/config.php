<?php
/**
 * Configuração geral do sistema.
 * Ajuste APP_URL ao publicar. Em produção, mantenha APP_DEBUG = false.
 */
declare(strict_types=1);

define('APP_NAME',  'Navalha & Cia');
define('APP_URL',   rtrim(getenv('APP_URL') ?: 'http://localhost/barbearia', '/'));
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL));
define('APP_TZ',    'America/Recife');

define('BASE_PATH',     dirname(__DIR__));
define('PUBLIC_URL',    APP_URL . '/public');
define('ADMIN_URL',     APP_URL . '/admin');
define('API_URL',       APP_URL . '/api');
define('ASSETS_URL',    APP_URL . '/assets');
define('UPLOADS_PATH',  BASE_PATH . '/assets/images/uploads');

date_default_timezone_set(APP_TZ);
mb_internal_encoding('UTF-8');

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}

// ----------------------------------------------------------- Sessão segura
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('BRBSESSID');
    session_start();

    // Renova o ID periodicamente para mitigar fixation
    if (!isset($_SESSION['_regenerated_at'])) {
        session_regenerate_id(true);
        $_SESSION['_regenerated_at'] = time();
    } elseif (time() - (int) $_SESSION['_regenerated_at'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_regenerated_at'] = time();
    }
}

// --------------------------------------------------------- Cabeçalhos HTTP
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
