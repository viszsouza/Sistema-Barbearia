<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_method('POST');
csrf_guard(true);

$data   = body();
$action = (string) ($data['action'] ?? 'create');

switch ($action) {
    case 'create':    handle_create($data);    break;
    case 'cancel':    handle_cancel($data);    break;
    case 'reschedule':handle_reschedule($data);break;
    default:          json_error('Ação desconhecida.');
}

// ----------------------------------------------------------------- CRIAR
function handle_create(array $data): void
{
    if (!throttle('booking', 12, 600)) {
        json_error('Muitas tentativas seguidas. Aguarde alguns minutos.', 429);
    }

    $serviceId = (int) ($data['service_id'] ?? 0);
    $barberId  = (int) ($data['barber_id'] ?? 0);
    $date      = trim((string) ($data['date'] ?? ''));
    $time      = substr(trim((string) ($data['time'] ?? '')), 0, 5);
    $name      = trim((string) ($data['name'] ?? ''));
    $phone     = trim((string) ($data['phone'] ?? ''));
    $email     = trim((string) ($data['email'] ?? ''));
    $notes     = trim((string) ($data['notes'] ?? ''));

    $errors = [];
    if ($serviceId <= 0)                                  $errors['service_id'] = 'Escolha um serviço.';
    if ($barberId <= 0)                                   $errors['barber_id']  = 'Escolha um barbeiro.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))      $errors['date']       = 'Escolha uma data.';
    if (!preg_match('/^\d{2}:\d{2}$/', $time))            $errors['time']       = 'Escolha um horário.';
    if (mb_strlen($name) < 3)                             $errors['name']       = 'Informe seu nome completo.';
    if (!is_valid_phone($phone))                          $errors['phone']      = 'Informe um telefone válido com DDD.';
    if ($email !== '' && !is_valid_email($email))         $errors['email']      = 'E-mail inválido.';
    if (mb_strlen($notes) > 500)                          $errors['notes']      = 'Observação muito longa.';

    if ($errors) {
        json_error('Revise os campos destacados.', 422, ['fields' => $errors]);
    }

    try {
        $customerId = current_customer()['id'] ?? find_or_create_customer($name, $phone, $email ?: null);

        $appointment = create_appointment([
            'service_id'  => $serviceId,
            'barber_id'   => $barberId,
            'date'        => $date,
            'time'        => $time,
            'customer_id' => (int) $customerId,
            'notes'       => $notes ?: null,
            'status'      => 'pending',
            'source'      => 'site',
        ]);
    } catch (RuntimeException $e) {
        json_error($e->getMessage(), 409);
    }

    notify_appointment_created($appointment);
    throttle_clear('booking');

    json_out([
        'ok'   => true,
        'code' => $appointment['code'],
        'redirect' => PUBLIC_URL . '/confirmacao.php?codigo=' . urlencode($appointment['code']),
    ]);
}

// --------------------------------------------------------------- CANCELAR
function handle_cancel(array $data): void
{
    $code = strtoupper(trim((string) ($data['code'] ?? '')));
    $appointment = $code ? get_appointment_by_code($code) : null;

    if (!$appointment) {
        json_error('Agendamento não encontrado.', 404);
    }

    // Só o dono do agendamento (sessão) ou quem informar o telefone pode cancelar.
    $customer = current_customer();
    $phoneOk  = only_digits((string) ($data['phone'] ?? '')) !== ''
        && only_digits($appointment['customer_phone']) === only_digits((string) $data['phone']);

    if (!(($customer && (int) $customer['id'] === (int) $appointment['customer_id']) || $phoneOk)) {
        json_error('Não foi possível confirmar que este agendamento é seu.', 403);
    }

    if (!can_cancel($appointment)) {
        $limit = (int) setting('cancel_limit_hours', '24');
        json_error("Este agendamento não pode mais ser cancelado online (limite de {$limit}h). Entre em contato com a barbearia.", 422);
    }

    if (!change_status((int) $appointment['id'], 'cancelled', null, 'Cancelado pelo cliente')) {
        json_error('Não foi possível cancelar agora. Tente novamente.', 500);
    }

    notify_appointment_cancelled($appointment);
    json_out(['ok' => true, 'message' => (string) setting('cancellation_message', 'Agendamento cancelado.')]);
}

// ------------------------------------------------------------- REAGENDAR
function handle_reschedule(array $data): void
{
    $customer = current_customer();
    if (!$customer) {
        json_error('Entre na sua conta para reagendar.', 401);
    }

    $code = strtoupper(trim((string) ($data['code'] ?? '')));
    $appointment = $code ? get_appointment_by_code($code) : null;

    if (!$appointment || (int) $appointment['customer_id'] !== (int) $customer['id']) {
        json_error('Agendamento não encontrado.', 404);
    }
    if (!can_cancel($appointment)) {
        json_error('Este agendamento não pode mais ser alterado online.', 422);
    }

    $date     = trim((string) ($data['date'] ?? ''));
    $time     = substr(trim((string) ($data['time'] ?? '')), 0, 5);
    $barberId = (int) ($data['barber_id'] ?? $appointment['barber_id']);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
        json_error('Escolha a nova data e horário.', 422);
    }

    try {
        $new = create_appointment([
            'service_id'  => (int) $appointment['service_id'],
            'barber_id'   => $barberId,
            'date'        => $date,
            'time'        => $time,
            'customer_id' => (int) $customer['id'],
            'notes'       => $appointment['notes'],
            'status'      => 'pending',
            'source'      => 'site',
        ]);
    } catch (RuntimeException $e) {
        json_error($e->getMessage(), 409);
    }

    change_status((int) $appointment['id'], 'cancelled', null, 'Reagendado para ' . $new['code']);
    notify_appointment_created($new);

    json_out([
        'ok'   => true,
        'code' => $new['code'],
        'redirect' => PUBLIC_URL . '/confirmacao.php?codigo=' . urlencode($new['code']),
    ]);
}
