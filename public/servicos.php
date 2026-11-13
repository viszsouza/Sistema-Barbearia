<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/availability.php';

$rows = db()->query(
    'SELECT s.*, c.name AS category
     FROM services s LEFT JOIN service_categories c ON c.id = s.category_id
     WHERE s.status = 1 ORDER BY c.display_order, s.display_order, s.name'
)->fetchAll();

$groups = [];
foreach ($rows as $row) {
    $groups[$row['category'] ?: 'Outros'][] = $row;
}

$pageTitle       = 'Serviços e preços — ' . setting('business_name', APP_NAME);
$pageDescription = 'Corte masculino, barba na navalha, degradê e sobrancelha em ' . setting('business_city', '') . '. Veja preços, duração e agende online.';
$activeNav       = 'servicos';
require BASE_PATH . '/includes/header.php';
?>
<section class="section">
  <div class="wrap">
    <div class="head">
      <h1>Serviços e preços</h1>
      <p>Toda a tabela vem do sistema da barbearia. O preço e a duração que você vê aqui são os mesmos aplicados na cadeira.</p>
    </div>

    <?php foreach ($groups as $category => $items): ?>
      <h2 style="margin-top:48px;font-size:var(--step-1);color:var(--brass)"><?= e((string) $category) ?></h2>
      <div class="services services--3">
        <?php foreach ($items as $s): ?>
          <article class="service">
            <div class="service__top">
              <h3 class="service__name"><?= e($s['name']) ?></h3>
              <span class="service__price"><?= money((float) $s['price']) ?></span>
            </div>
            <span class="service__meta"><?= e(format_duration((int) $s['duration_min'])) ?></span>
            <p><?= e((string) $s['description']) ?></p>
            <a class="btn btn--primary btn--sm" href="agendamento.php?servico=<?= (int) $s['id'] ?>">Agendar</a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <?php if (!$rows): ?>
      <div class="empty"><strong>Nenhum serviço publicado</strong>Cadastre os serviços pelo painel administrativo para que apareçam aqui.</div>
    <?php endif; ?>
  </div>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
