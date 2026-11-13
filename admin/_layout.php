<?php
/** Layout do painel. Defina $pageTitle e $activeMenu antes do require. */
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__) . '/config/config.php';
}
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';

require_login();

$user       = current_user();
$pageTitle  = $pageTitle  ?? 'Painel';
$activeMenu = $activeMenu ?? '';

$menu = [
    ['dashboard',    'Dashboard',    'dashboard.php',    'dashboard.view',      'M3 12h7V3H3v9zm0 9h7v-7H3v7zm11 0h7V12h-7v9zm0-18v7h7V3h-7z'],
    ['agendamentos', 'Agendamentos', 'agendamentos.php', 'appointments.view',   'M4 6h16M4 12h16M4 18h10'],
    ['calendario',   'Calendário',   'calendario.php',   'appointments.view',   'M4 5h16v16H4zM4 9h16M9 3v4M15 3v4'],
    ['clientes',     'Clientes',     'clientes.php',     'customers.view',      'M16 19v-1a4 4 0 0 0-8 0v1M12 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z'],
    ['barbeiros',    'Barbeiros',    'barbeiros.php',    'barbers.view',        'M5 5l14 14M19 5L5 19M7 17a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm10 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4z'],
    ['servicos',     'Serviços',     'servicos.php',     'services.view',       'M4 7h16M4 12h16M4 17h7'],
    ['horarios',     'Horários',     'horarios.php',     'hours.view',          'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z'],
    ['bloqueios',    'Folgas e bloqueios', 'bloqueios.php', 'blocks.manage',    'M5 5l14 14M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z'],
    ['feriados',     'Feriados',     'feriados.php',     'holidays.manage',     'M4 5h16v16H4zM4 9h16M9 3v4M15 3v4M9 14l2 2 4-4'],
    ['relatorios',   'Relatórios',   'relatorios.php',   'reports.view',        'M4 20V10M10 20V4M16 20v-8M22 20H2'],
    ['configuracoes','Configurações',   'configuracoes.php','settings.manage',  'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 7.5 19l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.6 1.6 0 0 0 3 13.6H3a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 4.7 7l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.6 1.6 0 0 0 10 3.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0 1.1 2.7H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1.3z'],
    ['usuarios',     'Usuários',     'usuarios.php',     'users.manage',        'M17 20v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M9.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM22 20v-1a4 4 0 0 0-3-3.9M16 4.1a4 4 0 0 1 0 7.8'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle) ?> — Painel <?= e((string) setting('business_name', APP_NAME)) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600&family=Karla:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/css/admin.css">
</head>
<body>
<div class="shell">

  <aside class="sidebar" id="sidebar">
    <div class="sidebar__brand">
      NAVALHA <span>&amp;</span> CIA
      <small>PAINEL DE GESTÃO</small>
    </div>

    <nav class="sidenav" aria-label="Menu do painel">
      <?php foreach ($menu as [$key, $label, $file, $ability, $path]): ?>
        <?php if (!can($ability)) continue; ?>
        <a href="<?= e(ADMIN_URL) ?>/<?= e($file) ?>"<?= $activeMenu === $key ? ' aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" aria-hidden="true" stroke-linecap="round" stroke-linejoin="round"><path d="<?= e($path) ?>"/></svg>
          <?= e($label) ?>
        </a>
      <?php endforeach; ?>
      <hr>
      <a href="<?= e(PUBLIC_URL) ?>/index.php" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" aria-hidden="true" stroke-linecap="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/></svg>
        Ver o site
      </a>
      <a href="<?= e(ADMIN_URL) ?>/logout.php">
        <svg viewBox="0 0 24 24" aria-hidden="true" stroke-linecap="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        Sair
      </a>
    </nav>

    <div class="sidebar__user">
      <strong><?= e($user['name']) ?></strong>
      <?= e(role_label($user['role'])) ?>
    </div>
  </aside>
  <div class="scrim" id="scrim" hidden></div>

  <div class="main">
    <header class="topbar">
      <div class="inline">
        <button class="menu-toggle" type="button" id="menu-toggle" aria-controls="sidebar" aria-expanded="false">
          <span class="sr-only">Abrir menu</span><span></span><span></span><span></span>
        </button>
        <span class="topbar__title"><?= e($pageTitle) ?></span>
      </div>
      <div class="topbar__actions">
        <?php if (can('appointments.manage')): ?>
          <a class="btn btn--primary btn--sm" href="<?= e(ADMIN_URL) ?>/agendamentos.php?novo=1">+ Novo<span class="hide-sm"> agendamento</span></a>
        <?php endif; ?>
      </div>
    </header>

    <div class="content">
      <?= render_flash() ?>
