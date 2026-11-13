<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/availability.php';

$barbers = db()->query('SELECT * FROM barbers WHERE status = 1 ORDER BY display_order, name')->fetchAll();

$serviceStmt = db()->prepare(
    'SELECT s.name FROM services s
     INNER JOIN barber_services bs ON bs.service_id = s.id
     WHERE bs.barber_id = :b AND s.status = 1 ORDER BY s.display_order'
);
$hourStmt = db()->prepare('SELECT * FROM barber_hours WHERE barber_id = :b ORDER BY FIELD(weekday,1,2,3,4,5,6,0)');

$pageTitle       = 'Nossos barbeiros — ' . setting('business_name', APP_NAME);
$pageDescription = 'Conheça a equipe da ' . setting('business_name', APP_NAME) . ': especialidades, horários de trabalho e agendamento direto com cada barbeiro.';
$activeNav       = 'barbeiros';
require BASE_PATH . '/includes/header.php';
?>
<section class="section">
  <div class="wrap">
    <div class="head">
      <h1>Nossos barbeiros</h1>
      <p>Três mãos diferentes, o mesmo padrão de acabamento. Agende com quem você já conhece ou experimente outro.</p>
    </div>

    <?php foreach ($barbers as $b):
      $serviceStmt->execute([':b' => $b['id']]);
      $barberServices = $serviceStmt->fetchAll(PDO::FETCH_COLUMN);
      $hourStmt->execute([':b' => $b['id']]);
      $barberHours = $hourStmt->fetchAll();
    ?>
      <article class="split" style="margin-bottom:64px;align-items:start">
        <div>
          <h2 style="margin-bottom:4px"><?= e($b['name']) ?></h2>
          <p style="color:var(--brass);margin-bottom:18px"><?= e((string) $b['headline']) ?></p>
          <p><?= e((string) $b['bio']) ?></p>

          <h3 style="font-size:1.05rem;margin-top:28px">Especialista em</h3>
          <div class="tags">
            <?php foreach (array_filter(array_map('trim', explode(',', (string) $b['specialties']))) as $tag): ?>
              <span class="tag"><?= e($tag) ?></span>
            <?php endforeach; ?>
          </div>

          <h3 style="font-size:1.05rem;margin-top:28px">Serviços que atende</h3>
          <p class="dim"><?= e(implode(' · ', $barberServices)) ?></p>

          <?php if ($barberHours): ?>
            <h3 style="font-size:1.05rem;margin-top:28px">Horário de trabalho</h3>
            <div style="max-width:320px">
              <?php foreach ($barberHours as $h): ?>
                <div class="schedule-line">
                  <span><?= e(weekday_name((int) $h['weekday'], true)) ?></span>
                  <span><?= (int) $h['is_working'] ? e(format_time($h['start_time']) . ' – ' . format_time($h['end_time'])) : 'Folga' ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <a class="btn btn--primary" style="margin-top:26px" href="agendamento.php?barbeiro=<?= (int) $b['id'] ?>">Agendar com este barbeiro</a>
        </div>
        <div class="split__media" style="background-image:url('<?= e($b['photo'] ? ASSETS_URL . '/images/uploads/' . $b['photo'] : ASSETS_URL . '/images/barbeiro-' . $b['slug'] . '.svg') ?>')" role="img" aria-label="<?= e($b['name']) ?>"></div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
