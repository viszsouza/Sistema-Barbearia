<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/availability.php';

$appointment = get_appointment_by_code((string) ($_GET['codigo'] ?? ''));
if (!$appointment || $appointment['status'] === 'cancelled') {
    http_response_code(404);
    exit('Agendamento não encontrado.');
}

$start = new DateTime($appointment['appointment_date'] . ' ' . $appointment['start_time'], new DateTimeZone(APP_TZ));
$end   = new DateTime($appointment['appointment_date'] . ' ' . $appointment['end_time'], new DateTimeZone(APP_TZ));
$start->setTimezone(new DateTimeZone('UTC'));
$end->setTimezone(new DateTimeZone('UTC'));

$fold = static fn(string $line): string => $line . "\r\n";

$ics  = $fold('BEGIN:VCALENDAR') . $fold('VERSION:2.0') . $fold('PRODID:-//' . setting('business_name', APP_NAME) . '//PT-BR')
      . $fold('BEGIN:VEVENT')
      . $fold('UID:' . $appointment['code'] . '@' . parse_url(APP_URL, PHP_URL_HOST))
      . $fold('DTSTAMP:' . gmdate('Ymd\THis\Z'))
      . $fold('DTSTART:' . $start->format('Ymd\THis\Z'))
      . $fold('DTEND:' . $end->format('Ymd\THis\Z'))
      . $fold('SUMMARY:' . $appointment['service_name'] . ' — ' . setting('business_name', APP_NAME))
      . $fold('DESCRIPTION:Barbeiro: ' . $appointment['barber_name'] . '. Código: ' . $appointment['code'])
      . $fold('LOCATION:' . str_replace(',', '\\,', (string) setting('business_address', '')))
      . $fold('END:VEVENT') . $fold('END:VCALENDAR');

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="agendamento-' . $appointment['code'] . '.ics"');
echo $ics;
