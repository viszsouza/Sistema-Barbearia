<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');

$serviceId = isset($_GET['service_id']) ? (int) $_GET['service_id'] : 0;

if ($serviceId > 0) {
    $rows = barbers_for_service($serviceId);
} else {
    $rows = db()->query('SELECT * FROM barbers WHERE status = 1 ORDER BY display_order, name')->fetchAll();
}

$barbers = array_map(static fn(array $b): array => [
    'id'          => (int) $b['id'],
    'name'        => $b['name'],
    'slug'        => $b['slug'],
    'headline'    => $b['headline'],
    'photo'       => $b['photo'],
    'specialties' => $b['specialties'],
], $rows);

json_out(['ok' => true, 'barbers' => $barbers]);
