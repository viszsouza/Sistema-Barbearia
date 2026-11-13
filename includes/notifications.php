<?php
declare(strict_types=1);

/**
 * Camada de notificações.
 * Hoje envia e-mail; WhatsApp e SMS já têm o contrato pronto — basta
 * implementar o driver correspondente e ativar em Configurações.
 */

function notify_queue(?int $appointmentId, string $channel, string $event, string $recipient, ?string $subject, string $body): int
{
    $stmt = db()->prepare(
        'INSERT INTO notifications (appointment_id, channel, event, recipient, subject, body)
         VALUES (:a, :c, :e, :r, :s, :b)'
    );
    $stmt->execute([
        ':a' => $appointmentId, ':c' => $channel, ':e' => $event,
        ':r' => $recipient, ':s' => $subject, ':b' => $body,
    ]);
    return (int) db()->lastInsertId();
}

function notify_mark(int $id, string $status, ?string $error = null): void
{
    db()->prepare('UPDATE notifications SET status = :s, error = :e, sent_at = IF(:s2 = \'sent\', NOW(), NULL) WHERE id = :id')
        ->execute([':s' => $status, ':e' => $error, ':s2' => $status, ':id' => $id]);
}

/** Driver de e-mail (mail() do PHP — funciona na maioria das hospedagens). */
function notify_send_email(int $notificationId, string $to, string $subject, string $body): bool
{
    $from    = setting('business_email', 'no-reply@localhost');
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . setting('business_name', APP_NAME) . ' <' . $from . '>',
        'Reply-To: ' . $from,
    ];

    $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
    notify_mark($notificationId, $sent ? 'sent' : 'failed', $sent ? null : 'Falha no envio pelo servidor.');
    return $sent;
}

/** Driver de WhatsApp — ponto de integração (Cloud API, Z-API, Evolution...). */
function notify_send_whatsapp(int $notificationId, string $phone, string $body): bool
{
    // Implemente aqui a chamada à API escolhida e chame notify_mark().
    notify_mark($notificationId, 'failed', 'Driver de WhatsApp ainda não configurado.');
    return false;
}

/** Monta e enfileira a confirmação do agendamento. */
function notify_appointment_created(array $appointment): void
{
    $business = setting('business_name', APP_NAME);
    $subject  = "Agendamento confirmado — {$business}";

    $body = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#1b1a18">'
        . '<h2 style="margin:0 0 16px">Agendamento confirmado</h2>'
        . '<p>Olá, ' . e($appointment['customer_name']) . '. Seu horário está reservado.</p>'
        . '<table cellpadding="6" style="border-collapse:collapse">'
        . '<tr><td><strong>Código</strong></td><td>' . e($appointment['code']) . '</td></tr>'
        . '<tr><td><strong>Serviço</strong></td><td>' . e($appointment['service_name']) . '</td></tr>'
        . '<tr><td><strong>Barbeiro</strong></td><td>' . e($appointment['barber_name']) . '</td></tr>'
        . '<tr><td><strong>Data</strong></td><td>' . format_date($appointment['appointment_date']) . '</td></tr>'
        . '<tr><td><strong>Horário</strong></td><td>' . format_time($appointment['start_time']) . '</td></tr>'
        . '<tr><td><strong>Valor</strong></td><td>' . money((float) $appointment['price']) . '</td></tr>'
        . '</table>'
        . '<p>' . e((string) setting('confirmation_message', '')) . '</p>'
        . '<p style="color:#6b6760;font-size:13px">' . e((string) setting('business_address', '')) . '</p>'
        . '</div>';

    if ((string) setting('notify_email_enabled', '1') === '1' && $appointment['customer_email']) {
        $id = notify_queue((int) $appointment['id'], 'email', 'appointment.created', $appointment['customer_email'], $subject, $body);
        notify_send_email($id, $appointment['customer_email'], $subject, $body);
    }

    if ((string) setting('notify_whatsapp_enabled', '0') === '1') {
        $text = sprintf(
            "Olá, %s! Seu agendamento está confirmado: %s com %s em %s às %s. Código %s.",
            $appointment['customer_name'], $appointment['service_name'], $appointment['barber_name'],
            format_date($appointment['appointment_date']), format_time($appointment['start_time']), $appointment['code']
        );
        $id = notify_queue((int) $appointment['id'], 'whatsapp', 'appointment.created', $appointment['customer_phone'], null, $text);
        notify_send_whatsapp($id, $appointment['customer_phone'], $text);
    }
}

function notify_appointment_cancelled(array $appointment): void
{
    if ((string) setting('notify_email_enabled', '1') !== '1' || !$appointment['customer_email']) {
        return;
    }
    $subject = 'Agendamento cancelado — ' . setting('business_name', APP_NAME);
    $body = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px">'
        . '<h2>Agendamento cancelado</h2>'
        . '<p>O agendamento ' . e($appointment['code']) . ' de '
        . format_date($appointment['appointment_date']) . ' às ' . format_time($appointment['start_time'])
        . ' foi cancelado.</p><p>' . e((string) setting('cancellation_message', '')) . '</p></div>';

    $id = notify_queue((int) $appointment['id'], 'email', 'appointment.cancelled', $appointment['customer_email'], $subject, $body);
    notify_send_email($id, $appointment['customer_email'], $subject, $body);
}
