<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');

$serviceId = (int) ($_GET['service_id'] ?? 0);
$barberId  = isset($_GET['barber_id']) && $_GET['barber_id'] !== '' && $_GET['barber_id'] !== 'any'
    ? (int) $_GET['barber_id'] : null;

if ($serviceId <= 0) {
    json_error('Informe o serviço.');
}

// --- Modo calendário: dias com vaga em um mês ------------------------------
if (!empty($_GET['month'])) {
    if (!preg_match('/^\d{4}-\d{2}$/', (string) $_GET['month'])) {
        json_error('Mês inválido.');
    }
    [$year, $month] = array_map('intval', explode('-', (string) $_GET['month']));
    if ($month < 1 || $month > 12) {
        json_error('Mês inválido.');
    }

    json_out([
        'ok'    => true,
        'month' => $_GET['month'],
        'days'  => bookable_days($serviceId, $barberId, $year, $month),
        'today' => date('Y-m-d'),
        'max_date' => date('Y-m-d', strtotime('+' . (int) setting('max_advance_days', '60') . ' days')),
    ]);
}

// --- Modo dia: horários livres --------------------------------------------
$date = (string) ($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    json_error('Data inválida.');
}

$result = availability($serviceId, $barberId, $date);

json_out([
    'ok'      => true,
    'date'    => $date,
    'slots'   => $result['slots'],
    'message' => $result['closed_reason'],
]);
