<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_method('GET');

$stmt = db()->query(
    'SELECT s.id, s.name, s.slug, s.description, s.price, s.duration_min, s.image, s.icon,
            c.name AS category
     FROM services s
     LEFT JOIN service_categories c ON c.id = s.category_id
     WHERE s.status = 1
     ORDER BY s.display_order, s.name'
);

$services = array_map(static function (array $row): array {
    $row['id']           = (int) $row['id'];
    $row['price']        = (float) $row['price'];
    $row['duration_min'] = (int) $row['duration_min'];
    $row['price_label']  = money((float) $row['price']);
    return $row;
}, $stmt->fetchAll());

json_out(['ok' => true, 'services' => $services]);
