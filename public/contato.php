<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/availability.php';

$hours = db()->query('SELECT * FROM business_hours ORDER BY FIELD(weekday,1,2,3,4,5,6,0)')->fetchAll();

$pageTitle       = 'Contato e endereço — ' . setting('business_name', APP_NAME);
$pageDescription = 'Endereço, telefone, WhatsApp e horário de funcionamento da ' . setting('business_name', APP_NAME) . '.';
$activeNav       = 'contato';
require BASE_PATH . '/includes/header.php';
?>
<section class="section">
  <div class="wrap split">
    <div>
      <h1>Contato</h1>
      <p class="lead">Dúvida sobre um serviço, encaixe de última hora ou grupo grande? Fale com a gente.</p>

      <div class="panel" style="margin-top:28px">
        <div class="schedule-line"><span>Endereço</span><span><?= e((string) setting('business_address', '')) ?></span></div>
        <div class="schedule-line"><span>Telefone</span><a href="tel:<?= e(only_digits((string) setting('business_phone', ''))) ?>"><?= e((string) setting('business_phone', '')) ?></a></div>
        <div class="schedule-line"><span>E-mail</span><a href="mailto:<?= e((string) setting('business_email', '')) ?>"><?= e((string) setting('business_email', '')) ?></a></div>
      </div>

      <a class="btn btn--primary" style="margin-top:20px" href="<?= e(whatsapp_link('Olá! Vim pelo site e gostaria de tirar uma dúvida.')) ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>

      <h2 style="margin-top:44px;font-size:var(--step-1)">Horário de funcionamento</h2>
      <div style="max-width:360px">
        <?php foreach ($hours as $h): ?>
          <div class="schedule-line">
            <span><?= e(weekday_name((int) $h['weekday'])) ?></span>
            <span><?php
              if (!(int) $h['is_open']) { echo 'Fechado'; }
              elseif ($h['break_start']) { echo e(format_time($h['open_time']) . '–' . format_time($h['break_start']) . ' · ' . format_time($h['break_end']) . '–' . format_time($h['close_time'])); }
              else { echo e(format_time($h['open_time']) . ' – ' . format_time($h['close_time'])); }
            ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <iframe class="map-frame" style="height:520px" src="<?= e((string) setting('business_maps_embed', '')) ?>" loading="lazy" title="Mapa da barbearia"></iframe>
    </div>
  </div>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
