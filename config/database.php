<?php
/**
 * Conexão com o banco (PDO).
 * Em hospedagem compartilhada, preencha as constantes abaixo.
 * Em ambientes com variáveis de ambiente, elas têm prioridade.
 */
declare(strict_types=1);

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'barbearia');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');

/**
 * Retorna sempre a mesma instância de PDO.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        $pdo->exec("SET time_zone = '-03:00'");
    } catch (PDOException $e) {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            throw $e;
        }
        http_response_code(503);
        exit('Não foi possível conectar ao banco de dados. Tente novamente em instantes.');
    }

    return $pdo;
}
