<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';

$services = db()->query(
    'SELECT id, name, description, price, duration_min FROM services WHERE status = 1 ORDER BY display_order, name'
)->fetchAll();

$barbers = db()->query('SELECT id, name, headline, slug, photo FROM barbers WHERE status = 1 ORDER BY display_order, name')->fetchAll();

// Quais serviços cada barbeiro atende (usado para filtrar a etapa 2 sem nova requisição)
$links = [];
foreach (db()->query('SELECT barber_id, service_id FROM barber_services') as $row) {
    $links[(int) $row['service_id']][] = (int) $row['barber_id'];
}

$ready    = !empty($services) && !empty($barbers); // sem serviço ou barbeiro não há o que agendar
$customer = current_customer();

$bootstrap = [
    'apiBase'      => API_URL,
    'csrf'         => csrf_token(),
    'services'     => array_map(fn($s) => [
        'id'       => (int) $s['id'],
        'name'     => $s['name'],
        'desc'     => (string) $s['description'],
        'price'    => (float) $s['price'],
        'priceText'=> money((float) $s['price']),
        'duration' => (int) $s['duration_min'],
        'durationText' => format_duration((int) $s['duration_min']),
    ], $services),
    'barbers'      => array_map(fn($b) => [
        'id'       => (int) $b['id'],
        'name'     => $b['name'],
        'headline' => (string) $b['headline'],
    ], $barbers),
    'serviceBarbers' => $links,
    'preselect'    => [
        'service' => isset($_GET['servico']) ? (int) $_GET['servico'] : null,
        'barber'  => isset($_GET['barbeiro']) ? (int) $_GET['barbeiro'] : null,
    ],
    'customer'     => $customer ? ['name' => $customer['name'], 'phone' => $customer['phone'], 'email' => $customer['email']] : null,
    'today'        => date('Y-m-d'),
    'maxDate'      => date('Y-m-d', strtotime('+' . (int) setting('max_advance_days', '60') . ' days')),
];

$pageTitle       = 'Agendar horário — ' . setting('business_name', APP_NAME);
$pageDescription = 'Escolha o serviço, o barbeiro, a data e o horário. Disponibilidade em tempo real.';
$activeNav       = '';
$pageScript      = $ready ? ASSETS_URL . '/js/agendamento.js' : null;
require BASE_PATH . '/includes/header.php';
?>

<section class="section">
  <div class="wrap">
    <div class="head">
      <h1>Agendar horário</h1>
      <p>Os horários abaixo são consultados na agenda real dos barbeiros. O que aparece livre, está livre.</p>
    </div>

    <?php if ($ready): ?>
    <ol class="steps" id="steps">
      <li data-step="1" data-state="active"><b><span class="steps__w">Etapa </span>1</b><span class="steps__t">Serviço</span></li>
      <li data-step="2"><b><span class="steps__w">Etapa </span>2</b><span class="steps__t">Barbeiro</span></li>
      <li data-step="3"><b><span class="steps__w">Etapa </span>3</b><span class="steps__t">Data</span></li>
      <li data-step="4"><b><span class="steps__w">Etapa </span>4</b><span class="steps__t">Horário</span></li>
      <li data-step="5"><b><span class="steps__w">Etapa </span>5</b><span class="steps__t">Seus dados</span></li>
      <li data-step="6"><b><span class="steps__w">Etapa </span>6</b><span class="steps__t">Confirmação</span></li>
    </ol>

    <div class="booking">
      <div>
        <div id="feedback" role="status" aria-live="polite"></div>

        <!-- ETAPA 1 -->
        <section class="step-panel" data-panel="1">
          <h2>Qual serviço você quer?</h2>
          <div class="choice-grid" id="service-list"></div>
        </section>

        <!-- ETAPA 2 -->
        <section class="step-panel" data-panel="2" hidden>
          <h2>Qual barbeiro você deseja?</h2>
          <div class="choice-grid" id="barber-list"></div>
          <button class="btn btn--ghost btn--sm" type="button" data-back="1" style="margin-top:22px">Voltar</button>
        </section>

        <!-- ETAPA 3 -->
        <section class="step-panel" data-panel="3" hidden>
          <h2>Escolha a data</h2>
          <p class="dim">Dias sem vaga, folgas, feriados e datas passadas aparecem desabilitados.</p>
          <div class="calendar" id="calendar" aria-live="polite"></div>
          <button class="btn btn--ghost btn--sm" type="button" data-back="2" style="margin-top:22px">Voltar</button>
        </section>

        <!-- ETAPA 4 -->
        <section class="step-panel" data-panel="4" hidden>
          <h2>Horários livres</h2>
          <p class="dim" id="slot-context"></p>
          <div class="slots" id="slot-list" aria-live="polite"></div>
          <button class="btn btn--ghost btn--sm" type="button" data-back="3" style="margin-top:22px">Voltar</button>
        </section>

        <!-- ETAPA 5 -->
        <section class="step-panel" data-panel="5" hidden>
          <h2>Seus dados</h2>
          <form id="customer-form" novalidate>
            <div class="grid-2">
              <div class="field" data-field="name">
                <label for="f-name">Nome completo</label>
                <input id="f-name" name="name" type="text" autocomplete="name" required>
                <span class="field__error"></span>
              </div>
              <div class="field" data-field="phone">
                <label for="f-phone">Telefone com DDD</label>
                <input id="f-phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="(81) 99999-0000" required>
                <span class="field__error"></span>
              </div>
            </div>
            <div class="field" data-field="email">
              <label for="f-email">E-mail (para receber a confirmação)</label>
              <input id="f-email" name="email" type="email" autocomplete="email">
              <span class="field__error"></span>
            </div>
            <div class="field" data-field="notes">
              <label for="f-notes">Alguma observação?</label>
              <textarea id="f-notes" name="notes" maxlength="500" placeholder="Ex.: gostaria de fazer o corte igual ao da última vez."></textarea>
              <span class="field__error"></span>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
              <button class="btn btn--ghost btn--sm" type="button" data-back="4">Voltar</button>
              <button class="btn btn--primary" type="submit">Revisar agendamento</button>
            </div>
          </form>
        </section>

        <!-- ETAPA 6 -->
        <section class="step-panel" data-panel="6" hidden>
          <h2>Confirme os dados</h2>
          <div class="panel" id="review"></div>
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px">
            <button class="btn btn--ghost btn--sm" type="button" data-back="5">Voltar</button>
            <button class="btn btn--primary" type="button" id="confirm-btn">Confirmar agendamento</button>
          </div>
        </section>
      </div>

      <!-- RESUMO LATERAL -->
      <aside class="summary" id="summary">
        <h3>Seu agendamento</h3>
        <dl>
          <div><dt>SERVIÇO</dt><dd data-summary="service">—</dd></div>
          <div><dt>BARBEIRO</dt><dd data-summary="barber">—</dd></div>
          <div><dt>DATA</dt><dd data-summary="date">—</dd></div>
          <div><dt>HORÁRIO</dt><dd data-summary="time">—</dd></div>
          <div><dt>DURAÇÃO</dt><dd data-summary="duration">—</dd></div>
        </dl>
        <div class="summary__total"><span class="dim">Valor</span><strong data-summary="price">—</strong></div>
        <p class="dim" style="font-size:.82rem;margin-top:18px">Pagamento na barbearia. Cancelamento online até <?= (int) setting('cancel_limit_hours', '24') ?>h antes.</p>
      </aside>
    </div>
    <?php else: ?>
      <div class="empty">
        <strong>Agendamento online indisponível no momento</strong>
        Ainda não há barbeiros ou serviços disponíveis para reserva. Fale com a gente pelo WhatsApp.
        <div style="margin-top:18px"><a class="btn btn--primary btn--sm" href="<?= e(whatsapp_link('Olá! Gostaria de agendar um horário.')) ?>" target="_blank" rel="noopener">Falar no WhatsApp</a></div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($ready): ?>
<script id="booking-data" type="application/json"><?= json_encode($bootstrap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
<?php require BASE_PATH . '/includes/footer.php'; ?>
