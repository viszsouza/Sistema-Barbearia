<?php
/** Cabeçalho do site público. Defina $pageTitle, $pageDescription e $activeNav antes do require. */
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__) . '/config/config.php';
}
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';

$businessName = (string) setting('business_name', APP_NAME);
$pageTitle       = $pageTitle       ?? $businessName;
$pageDescription = $pageDescription ?? (string) setting('business_tagline', '');
$activeNav       = $activeNav       ?? '';
$currentUrl      = PUBLIC_URL . '/' . basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');

$navItems = [
    'home'        => ['Início',      PUBLIC_URL . '/index.php'],
    'servicos'    => ['Serviços',    PUBLIC_URL . '/servicos.php'],
    'barbeiros'   => ['Barbeiros',   PUBLIC_URL . '/barbeiros.php'],
    'contato'     => ['Contato',     PUBLIC_URL . '/contato.php'],
    'cliente'     => ['Meus horários', PUBLIC_URL . '/cliente.php'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<link rel="canonical" href="<?= e($currentUrl) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:url" content="<?= e($currentUrl) ?>">
<meta property="og:site_name" content="<?= e($businessName) ?>">
<meta property="og:image" content="<?= e(ASSETS_URL) ?>/images/hero.svg">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#100F0D">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@300;400;500;600&family=Karla:ital,wght@0,400;0,500;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/css/public.css">
<script type="application/ld+json">
<?= json_encode([
    '@context'  => 'https://schema.org',
    '@type'     => 'HairSalon',
    'name'      => $businessName,
    'image'     => ASSETS_URL . '/images/hero.svg',
    'url'       => PUBLIC_URL . '/index.php',
    'telephone' => setting('business_phone', ''),
    'email'     => setting('business_email', ''),
    'priceRange'=> 'R$ 20 – R$ 70',
    'address'   => [
        '@type'           => 'PostalAddress',
        'streetAddress'   => setting('business_address', ''),
        'addressLocality' => setting('business_city', ''),
        'addressRegion'   => setting('business_state', ''),
        'addressCountry'  => 'BR',
    ],
    'openingHoursSpecification' => array_values(array_filter(array_map(function ($row) {
        if (!(int) $row['is_open']) return null;
        $map = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        return [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => 'https://schema.org/' . $map[(int) $row['weekday']],
            'opens'     => substr((string) $row['open_time'], 0, 5),
            'closes'    => substr((string) $row['close_time'], 0, 5),
        ];
    }, db()->query('SELECT * FROM business_hours ORDER BY weekday')->fetchAll()))),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
</script>
</head>
<body>
<a class="sr-only" href="#conteudo">Pular para o conteúdo</a>

<header class="topbar">
  <div class="wrap topbar__inner">
    <a class="brand" href="<?= e(PUBLIC_URL) ?>/index.php">
      <span class="brand__mark">NAVALHA <span>&amp;</span> CIA</span>
      <span class="brand__sub">BARBEARIA</span>
    </a>

    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="nav-principal">
      <span class="sr-only">Abrir menu</span><span></span><span></span><span></span>
    </button>

    <nav class="nav" id="nav-principal" aria-label="Navegação principal">
      <?php foreach ($navItems as $key => [$label, $url]): ?>
        <a href="<?= e($url) ?>"<?= $activeNav === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <a class="btn btn--primary btn--sm" href="<?= e(PUBLIC_URL) ?>/agendamento.php">Agendar horário</a>
    </nav>
  </div>
</header>
<div class="pole" aria-hidden="true"></div>

<main id="conteudo">
