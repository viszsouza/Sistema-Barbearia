<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');
csrf_guard(true);

$data   = body();
$action = (string) ($data['action'] ?? '');

if ($action === 'login') {
    if (!throttle('customer_login', 6, 600)) {
        json_error('Muitas tentativas. Aguarde alguns minutos.', 429);
    }
    $identifier = trim((string) ($data['identifier'] ?? ''));
    $password   = (string) ($data['password'] ?? '');

    if ($identifier === '' || $password === '') {
        json_error('Informe e-mail/telefone e senha.', 422);
    }
    if (!attempt_customer_login($identifier, $password)) {
        json_error('E-mail, telefone ou senha incorretos.', 401);
    }
    throttle_clear('customer_login');
    json_out(['ok' => true, 'redirect' => PUBLIC_URL . '/cliente.php']);
}

if ($action === 'register') {
    $name     = trim((string) ($data['name'] ?? ''));
    $phone    = trim((string) ($data['phone'] ?? ''));
    $email    = trim((string) ($data['email'] ?? ''));
    $password = (string) ($data['password'] ?? '');

    $errors = [];
    if (mb_strlen($name) < 3)          $errors['name']     = 'Informe seu nome completo.';
    if (!is_valid_phone($phone))       $errors['phone']    = 'Telefone inválido.';
    if (!is_valid_email($email))       $errors['email']    = 'E-mail inválido.';
    if (mb_strlen($password) < 8)      $errors['password'] = 'A senha precisa de pelo menos 8 caracteres.';
    if ($errors) {
        json_error('Revise os campos destacados.', 422, ['fields' => $errors]);
    }

    $phone = format_phone($phone);
    $hash  = password_hash($password, PASSWORD_DEFAULT);

    $stmt = db()->prepare('SELECT id, password_hash FROM customers WHERE phone = :p OR email = :e LIMIT 1');
    $stmt->execute([':p' => $phone, ':e' => $email]);
    $existing = $stmt->fetch();

    if ($existing && $existing['password_hash']) {
        json_error('Já existe uma conta com esse telefone ou e-mail. Faça login.', 409);
    }

    if ($existing) {
        // Cliente já atendido na barbearia, agora criando acesso ao site.
        db()->prepare('UPDATE customers SET name = :n, email = :e, password_hash = :h WHERE id = :id')
            ->execute([':n' => $name, ':e' => $email, ':h' => $hash, ':id' => $existing['id']]);
        $id = (int) $existing['id'];
    } else {
        db()->prepare('INSERT INTO customers (name, phone, email, password_hash) VALUES (:n, :p, :e, :h)')
            ->execute([':n' => $name, ':p' => $phone, ':e' => $email, ':h' => $hash]);
        $id = (int) db()->lastInsertId();
    }

    session_regenerate_id(true);
    $_SESSION['customer'] = ['id' => $id, 'name' => $name, 'email' => $email, 'phone' => $phone];
    json_out(['ok' => true, 'redirect' => PUBLIC_URL . '/cliente.php']);
}

if ($action === 'logout') {
    logout_customer();
    json_out(['ok' => true, 'redirect' => PUBLIC_URL . '/index.php']);
}

json_error('Ação desconhecida.');
