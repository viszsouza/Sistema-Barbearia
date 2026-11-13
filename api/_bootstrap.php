<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';
require_once BASE_PATH . '/includes/notifications.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

/** Garante o método HTTP esperado. */
function require_method(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $methods, true)) {
        json_error('Método não permitido.', 405);
    }
}

/** Corpo da requisição: aceita JSON ou form-urlencoded. */
function body(): array
{
    $json = json_input();
    return $json ?: $_POST;
}
