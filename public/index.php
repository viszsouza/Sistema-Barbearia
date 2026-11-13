<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/availability.php';

$services = db()->query('SELECT * FROM services WHERE status = 1 ORDER BY display_order, name LIMIT 6')->fetchAll();
$barbers  = db()->query('SELECT * FROM barbers WHERE status = 1 ORDER BY display_order, name')->fetchAll();
$quotes   = db()->query('SELECT * FROM testimonials WHERE status = 1 ORDER BY posted_on DESC LIMIT 3')->fetchAll();
$hours    = db()->query('SELECT * FROM business_hours ORDER BY FIELD(weekday,1,2,3,4,5,6,0)')->fetchAll();

$city = (string) setting('business_city', '');
$pageTitle       = setting('business_name', APP_NAME) . ' — Barbearia em ' . $city . ' | Agende online';
$pageDescription = 'Barbearia em ' . $city . ': corte masculino, barba na navalha e degradê. Agende seu horário online em menos de um minuto.';
$activeNav       = 'home';

require BASE_PATH . '/includes/header.php';
?>

<section class="hero">
  <div class="hero__photo" style="background-image:url('<?= e(ASSETS_URL) ?>/images/hero.svg')" role="img" aria-label="Interior da barbearia"></div>
  <div class="wrap hero__inner">
    <p class="hero__kicker"><?= e((string) setting('business_tagline', '')) ?></p>
    <h1>Seu estilo começa aqui.</h1>
    <p class="hero__sub">Experiência, precisão e estilo em cada corte. Escolha o barbeiro, veja os horários livres de verdade e reserve em um minuto.</p>
    <div class="hero__actions">
      <a class="btn btn--primary" href="agendamento.php">Agendar horário</a>
      <a class="btn btn--ghost" href="servicos.php">Conhecer serviços</a>
    </div>
  </div>
</section>

<!-- ------------------------------------------------------------ SERVIÇOS -->
<section class="section" id="servicos">
  <div class="wrap">
    <div class="head">
      <h2>O que fazemos</h2>
      <p>Preços e durações reais, direto da nossa agenda. Nada de orçamento surpresa na cadeira.</p>
    </div>

    <div class="services services--3">
      <?php foreach ($services as $s): ?>
        <article class="service">
          <div class="service__top">
            <h3 class="service__name"><?= e($s['name']) ?></h3>
            <span class="service__price"><?= money((float) $s['price']) ?></span>
          </div>
          <span class="service__meta"><?= e(format_duration((int) $s['duration_min'])) ?> na cadeira</span>
          <p><?= e((string) $s['description']) ?></p>
          <a class="btn btn--ghost btn--sm" href="agendamento.php?servico=<?= (int) $s['id'] ?>">Agendar</a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- --------------------------------------------------------------- SOBRE -->
<section class="section section--raised" id="sobre">
  <div class="wrap split">
    <div>
      <h2>Uma cadeira, um ofício</h2>
      <p>Abrimos em 2014 com duas cadeiras e uma ideia simples: quem senta aqui sai com o corte que pediu, no tempo que combinamos. Tesoura, máquina e navalha — e a paciência de acertar o acabamento antes de soltar a capa.</p>
      <p>O ambiente é de barbearia mesmo: café na mão, conversa boa e música em volume que deixa conversar. Sem fila de espera, porque a agenda é levada a sério.</p>

      <div class="stats">
        <div class="stat">
          <div class="stat__value">+<?= e((string) setting('stat_clients', '0')) ?></div>
          <div class="stat__label">clientes atendidos</div>
        </div>
        <div class="stat">
          <div class="stat__value"><?= e((string) setting('stat_years', '0')) ?></div>
          <div class="stat__label">anos de ofício</div>
        </div>
        <div class="stat">
          <div class="stat__value"><?= count($barbers) ?></div>
          <div class="stat__label">barbeiros na casa</div>
        </div>
        <div class="stat">
          <div class="stat__value"><?= (int) db()->query('SELECT COUNT(*) FROM services WHERE status = 1')->fetchColumn() ?></div>
          <div class="stat__label">serviços no cardápio</div>
        </div>
      </div>
    </div>
    <div class="split__media" style="background-image:url('<?= e(ASSETS_URL) ?>/images/sobre.svg')" role="img" aria-label="Ambiente da barbearia"></div>
  </div>
</section>

<!-- ----------------------------------------------------------- BARBEIROS -->
<section class="section" id="barbeiros">
  <div class="wrap">
    <div class="head">
      <h2>Quem vai te atender</h2>
      <p>Cada um com a sua mão. Escolha pelo nome ou deixe com quem estiver livre.</p>
    </div>

    <div class="barbers">
      <?php foreach ($barbers as $b): ?>
        <article class="barber">
          <div class="barber__photo" style="background-image:url('<?= e($b['photo'] ? ASSETS_URL . '/images/uploads/' . $b['photo'] : ASSETS_URL . '/images/barbeiro-' . $b['slug'] . '.svg') ?>')" role="img" aria-label="<?= e($b['name']) ?>"></div>
          <div class="barber__body">
            <h3 class="barber__name"><?= e($b['name']) ?></h3>
            <span class="barber__role"><?= e((string) $b['headline']) ?></span>
            <p class="barber__bio"><?= e((string) $b['bio']) ?></p>
            <div class="tags">
              <?php foreach (array_filter(array_map('trim', explode(',', (string) $b['specialties']))) as $tag): ?>
                <span class="tag"><?= e($tag) ?></span>
              <?php endforeach; ?>
            </div>
            <a class="btn btn--ghost btn--sm" style="margin-top:16px" href="agendamento.php?barbeiro=<?= (int) $b['id'] ?>">Agendar com <?= e(explode(' ', $b['name'])[0]) ?></a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- --------------------------------------------------------- DIFERENCIAIS -->
<section class="section section--sunken">
  <div class="wrap">
    <div class="head"><h2>Por que a agenda funciona</h2></div>
    <div class="services services--3">
      <article class="service"><h3 class="service__name">Horário é horário</h3><p>A disponibilidade no site vem da agenda real de cada barbeiro. Se aparece livre, está livre.</p></article>
      <article class="service"><h3 class="service__name">Sem cadastro obrigatório</h3><p>Dá para reservar só com nome e telefone. Criar conta é opcional, para ver o histórico.</p></article>
      <article class="service"><h3 class="service__name">Cancelou, liberou</h3><p>Cancele online até <?= (int) setting('cancel_limit_hours', '24') ?>h antes e o horário volta para quem estiver esperando.</p></article>
    </div>
  </div>
</section>

<!-- ------------------------------------------------------------- GALERIA -->
<section class="section" id="galeria">
  <div class="wrap">
    <div class="head"><h2>Trabalhos recentes</h2></div>
    <div class="gallery">
      <?php for ($i = 1; $i <= 6; $i++): ?>
        <div style="background-image:url('<?= e(ASSETS_URL) ?>/images/galeria-<?= $i ?>.svg')" role="img" aria-label="Corte finalizado <?= $i ?>"></div>
      <?php endfor; ?>
    </div>
  </div>
</section>

<!-- ---------------------------------------------------------- DEPOIMENTOS -->
<?php if ($quotes): ?>
<section class="section section--raised">
  <div class="wrap">
    <div class="head"><h2>O que dizem</h2></div>
    <div class="quotes">
      <?php foreach ($quotes as $q): ?>
        <blockquote class="quote">
          <div class="rating" aria-label="Nota <?= (int) $q['rating'] ?> de 5"><?= str_repeat('★', (int) $q['rating']) . str_repeat('☆', 5 - (int) $q['rating']) ?></div>
          <p><?= e($q['content']) ?></p>
          <footer><?= e($q['name']) ?> · <?= format_date($q['posted_on']) ?></footer>
        </blockquote>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ------------------------------------------------------------- CTA -->
<section class="section section--tight" style="text-align:center">
  <div class="wrap">
    <h2 style="font-size:var(--step-3)">Agende seu horário</h2>
    <p class="lead" style="margin-inline:auto;max-width:48ch">Escolha o serviço, o barbeiro e o horário. Leva menos de um minuto.</p>
    <a class="btn btn--primary" href="agendamento.php" style="margin-top:14px">Ver horários livres</a>
  </div>
</section>

<!-- -------------------------------------------------------- LOCALIZAÇÃO -->
<section class="section section--sunken" id="localizacao">
  <div class="wrap split">
    <div>
      <h2>Onde estamos</h2>
      <p><?= e((string) setting('business_address', '')) ?></p>
      <p><a href="tel:<?= e(only_digits((string) setting('business_phone', ''))) ?>" style="color:var(--brass-lit)"><?= e((string) setting('business_phone', '')) ?></a></p>

      <h3 style="margin-top:32px">Horário de funcionamento</h3>
      <div style="max-width:340px">
        <?php foreach ($hours as $h): ?>
          <div class="schedule-line">
            <span><?= e(weekday_name((int) $h['weekday'])) ?></span>
            <span><?= (int) $h['is_open'] ? e(format_time($h['open_time']) . ' – ' . format_time($h['close_time'])) : 'Fechado' ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <iframe class="map-frame" src="<?= e((string) setting('business_maps_embed', '')) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Mapa da barbearia"></iframe>
    </div>
  </div>
</section>

<!-- ----------------------------------------------------------------- FAQ -->
<section class="section" id="faq">
  <div class="wrap" style="max-width:820px">
    <div class="head"><h2>Perguntas frequentes</h2></div>
    <div class="faq">
      <details open><summary>Preciso agendar?</summary><p>Atendemos por ordem de agendamento. Se aparecer alguém sem horário marcado, encaixamos quando houver janela — mas quem agendou tem prioridade.</p></details>
      <details><summary>Posso escolher o barbeiro?</summary><p>Pode. Na hora de agendar, escolha o nome ou marque "qualquer barbeiro disponível" para ver mais horários livres.</p></details>
      <details><summary>Posso cancelar meu horário?</summary><p>Sim, pelo site, até <?= (int) setting('cancel_limit_hours', '24') ?> horas antes. Depois disso, fale com a gente pelo WhatsApp.</p></details>
      <details><summary>Quais formas de pagamento?</summary><p>Dinheiro, Pix, débito e crédito. O pagamento é feito na barbearia, no fim do atendimento.</p></details>
      <details><summary>Quanto tempo dura o atendimento?</summary><p>Depende do serviço: de 15 minutos (sobrancelha) a 1 hora (corte + barba). A duração aparece em cada serviço.</p></details>
    </div>
  </div>
</section>

<?php require BASE_PATH . '/includes/footer.php'; ?>
