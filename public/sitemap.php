<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';

$pages = ['index.php', 'servicos.php', 'barbeiros.php', 'contato.php', 'agendamento.php'];

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $page) {
    echo '  <url><loc>' . htmlspecialchars(PUBLIC_URL . '/' . $page, ENT_QUOTES) . '</loc></url>' . "\n";
}
echo '</urlset>';
