<?php
declare(strict_types=1);

/**
 * Autenticação administrativa e de clientes, com controle de permissões.
 */

// ===================================================== MAPA DE PERMISSÕES
/**
 * Cada papel recebe uma lista de habilidades. "*" = tudo.
 */
function permission_map(): array
{
    return [
        'super_admin'  => ['*'],
        'admin'        => [
            'dashboard.view', 'appointments.view', 'appointments.manage',
            'customers.view', 'customers.manage',
            'barbers.view', 'barbers.manage',
            'services.view', 'services.manage',
            'hours.view', 'hours.manage',
            'blocks.manage', 'holidays.manage',
            'reports.view', 'testimonials.manage',
        ],
        'receptionist' => [
            'dashboard.view', 'appointments.view', 'appointments.manage',
            'customers.view', 'customers.manage',
            'barbers.view', 'services.view', 'hours.view',
        ],
    ];
}

function role_label(string $role): string
{
    return [
        'super_admin'  => 'Super admin',
        'admin'        => 'Administrador',
        'receptionist' => 'Recepcionista',
    ][$role] ?? $role;
}

// ====================================================== SESSÃO DO ADMIN
function current_user(): ?array
{
    return $_SESSION['admin_user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/** Verifica se o usuário logado tem a permissão informada. */
function can(string $ability): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    $abilities = permission_map()[$user['role']] ?? [];
    return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
}

/** Middleware: exige login. */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? null;
        redirect(ADMIN_URL . '/login.php');
    }
}

/** Middleware: exige uma permissão específica. */
function require_permission(string $ability): void
{
    require_login();
    if (!can($ability)) {
        http_response_code(403);
        require BASE_PATH . '/admin/_forbidden.php';
        exit;
    }
}

/** Tenta autenticar um usuário administrativo. */
function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email AND status = 1 LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // password_verify em hash fictício mantém o tempo de resposta constante
    $hash = $user['password_hash'] ?? '$2y$12$usuarioinexistenteusuarioinexistenteusuarioinexiste';

    if (!password_verify($password, $hash) || !$user) {
        return false;
    }

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $up = db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
        $up->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $user['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['admin_user'] = [
        'id'    => (int) $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];
    $_SESSION['_regenerated_at'] = time();

    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
        ->execute([':id' => $user['id']]);

    return true;
}

function logout_admin(): void
{
    unset($_SESSION['admin_user']);
    session_regenerate_id(true);
}

// ==================================================== SESSÃO DO CLIENTE
function current_customer(): ?array
{
    return $_SESSION['customer'] ?? null;
}

function require_customer(): void
{
    if (!current_customer()) {
        redirect(PUBLIC_URL . '/cliente.php');
    }
}

function attempt_customer_login(string $identifier, string $password): bool
{
    $field = is_valid_email($identifier) ? 'email' : 'phone';
    $value = $field === 'phone' ? format_phone($identifier) : $identifier;

    $stmt = db()->prepare("SELECT * FROM customers WHERE {$field} = :v AND status = 1 LIMIT 1");
    $stmt->execute([':v' => $value]);
    $customer = $stmt->fetch();

    $hash = $customer['password_hash'] ?? '$2y$12$clienteinexistenteclienteinexistenteclienteinexis';

    if (!$customer || !$customer['password_hash'] || !password_verify($password, $hash)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['customer'] = [
        'id'    => (int) $customer['id'],
        'name'  => $customer['name'],
        'email' => $customer['email'],
        'phone' => $customer['phone'],
    ];
    $_SESSION['_regenerated_at'] = time();
    return true;
}

function logout_customer(): void
{
    unset($_SESSION['customer']);
    session_regenerate_id(true);
}

// ================================================ LIMITE DE TENTATIVAS
/** Rate limit simples por sessão, para telas de login. */
function throttle(string $key, int $maxAttempts = 5, int $decaySeconds = 300): bool
{
    $bucket = $_SESSION['throttle'][$key] ?? ['count' => 0, 'start' => time()];

    if (time() - $bucket['start'] > $decaySeconds) {
        $bucket = ['count' => 0, 'start' => time()];
    }
    $bucket['count']++;
    $_SESSION['throttle'][$key] = $bucket;

    return $bucket['count'] <= $maxAttempts;
}

function throttle_clear(string $key): void
{
    unset($_SESSION['throttle'][$key]);
}
