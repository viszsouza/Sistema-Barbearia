<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';

$customer = current_customer();

if (($_GET['sair'] ?? '') === '1') {
    logout_customer();
    redirect(PUBLIC_URL . '/index.php');
}

$upcoming = $history = [];
if ($customer) {
    $stmt = db()->prepare(
        "SELECT a.*, b.name AS barber_name, s.name AS service_name
         FROM appointments a
         JOIN barbers b ON b.id = a.barber_id
         JOIN services s ON s.id = a.service_id
         WHERE a.customer_id = :c
           AND a.status IN ('pending','confirmed','in_progress')
           AND TIMESTAMP(a.appointment_date, a.start_time) >= NOW()
         ORDER BY a.appointment_date, a.start_time"
    );
    $stmt->execute([':c' => $customer['id']]);
    $upcoming = $stmt->fetchAll();

    $stmt = db()->prepare(
        "SELECT a.*, b.name AS barber_name, s.name AS service_name
         FROM appointments a
         JOIN barbers b ON b.id = a.barber_id
         JOIN services s ON s.id = a.service_id
         WHERE a.customer_id = :c
           AND (a.status IN ('completed','cancelled','no_show')
                OR TIMESTAMP(a.appointment_date, a.start_time) < NOW())
         ORDER BY a.appointment_date DESC, a.start_time DESC
         LIMIT 30"
    );
    $stmt->execute([':c' => $customer['id']]);
    $history = $stmt->fetchAll();
}

$pageTitle       = $customer ? 'Meus horários — ' . setting('business_name', APP_NAME) : 'Entrar — ' . setting('business_name', APP_NAME);
$pageDescription = 'Acompanhe, cancele ou reagende seus horários na ' . setting('business_name', APP_NAME) . '.';
$activeNav       = 'cliente';
require BASE_PATH . '/includes/header.php';
?>
<section class="section">
  <div class="wrap">
  <?php if (!$customer): ?>

    <div style="max-width:460px;margin-inline:auto">
      <div class="head" style="text-align:center">
        <h1 style="font-size:var(--step-2)">Sua conta</h1>
        <p>Entrar é opcional — serve para ver o histórico, cancelar e reagendar sem precisar do código.</p>
      </div>

      <div class="tabs" role="tablist">
        <button role="tab" aria-selected="true" data-tab="login">Entrar</button>
        <button role="tab" aria-selected="false" data-tab="register">Criar conta</button>
      </div>

      <div id="auth-feedback" role="status" aria-live="polite"></div>

      <form class="panel" id="form-login" data-pane="login">
        <div class="field" data-field="identifier">
          <label for="l-id">E-mail ou telefone</label>
          <input id="l-id" name="identifier" type="text" autocomplete="username" required>
          <span class="field__error"></span>
        </div>
        <div class="field" data-field="password">
          <label for="l-pass">Senha</label>
          <input id="l-pass" name="password" type="password" autocomplete="current-password" required>
          <span class="field__error"></span>
        </div>
        <button class="btn btn--primary btn--block" type="submit">Entrar</button>
      </form>

      <form class="panel" id="form-register" data-pane="register" hidden>
        <div class="field" data-field="name">
          <label for="r-name">Nome completo</label>
          <input id="r-name" name="name" type="text" autocomplete="name" required>
          <span class="field__error"></span>
        </div>
        <div class="field" data-field="phone">
          <label for="r-phone">Telefone com DDD</label>
          <input id="r-phone" name="phone" type="tel" inputmode="numeric" placeholder="(81) 99999-0000" required>
          <span class="field__error"></span>
        </div>
        <div class="field" data-field="email">
          <label for="r-email">E-mail</label>
          <input id="r-email" name="email" type="email" autocomplete="email" required>
          <span class="field__error"></span>
        </div>
        <div class="field" data-field="password">
          <label for="r-pass">Senha (mínimo 8 caracteres)</label>
          <input id="r-pass" name="password" type="password" autocomplete="new-password" required>
          <span class="field__error"></span>
        </div>
        <button class="btn btn--primary btn--block" type="submit">Criar conta</button>
      </form>

      <p class="dim" style="text-align:center;margin-top:24px;font-size:.9rem">
        Agendou sem conta? Use o código que recebeu na
        <a href="confirmacao.php" style="color:var(--brass-lit)">página de confirmação</a>.
      </p>
    </div>

  <?php else: ?>

    <div class="head" style="display:flex;justify-content:space-between;align-items:end;gap:20px;max-width:none">
      <div>
        <h1 style="font-size:var(--step-2);margin-bottom:6px">Olá, <?= e(explode(' ', $customer['name'])[0]) ?></h1>
        <p style="margin:0">Seus horários na <?= e((string) setting('business_name', APP_NAME)) ?>.</p>
      </div>
      <a class="btn btn--ghost btn--sm" href="?sair=1">Sair</a>
    </div>

    <div id="client-feedback" role="status" aria-live="polite"></div>

    <div class="panel">
      <h2 style="font-size:var(--step-1)">Próximos horários</h2>
      <?php if (!$upcoming): ?>
        <div class="empty">
          <strong>Nenhum horário marcado</strong>
          Escolha um serviço e reserve em menos de um minuto.
          <div style="margin-top:18px"><a class="btn btn--primary btn--sm" href="agendamento.php">Agendar horário</a></div>
        </div>
      <?php else: foreach ($upcoming as $a): ?>
        <div class="appt-row">
          <div class="appt-row__main">
            <span class="appt-row__title"><?= e($a['service_name']) ?> · <?= e(format_time($a['start_time'])) ?></span>
            <span class="dim" style="font-size:.9rem"><?= e(format_date_long($a['appointment_date'])) ?> · com <?= e($a['barber_name']) ?> · <?= money((float) $a['price']) ?></span>
            <span class="dim" style="font-size:.82rem">Código <?= e($a['code']) ?></span>
          </div>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <span class="badge badge--<?= e($a['status']) ?>"><?= e(status_label($a['status'])) ?></span>
            <?php if (can_cancel($a)): ?>
              <a class="btn btn--ghost btn--sm" href="agendamento.php?servico=<?= (int) $a['service_id'] ?>&amp;barbeiro=<?= (int) $a['barber_id'] ?>">Reagendar</a>
              <button class="btn btn--ghost btn--sm" type="button" data-cancel="<?= e($a['code']) ?>"
                      data-confirm="Cancelar o agendamento <?= e($a['code']) ?>?">Cancelar</button>
            <?php else: ?>
              <span class="dim" style="font-size:.8rem">Cancelamento só pela barbearia</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <div class="panel">
      <h2 style="font-size:var(--step-1)">Histórico</h2>
      <?php if (!$history): ?>
        <div class="empty"><strong>Sem atendimentos anteriores</strong>Assim que você passar pela cadeira, o histórico aparece aqui.</div>
      <?php else: foreach ($history as $a): ?>
        <div class="appt-row">
          <div class="appt-row__main">
            <span class="appt-row__title"><?= e($a['service_name']) ?></span>
            <span class="dim" style="font-size:.9rem"><?= e(format_date($a['appointment_date'])) ?> às <?= e(format_time($a['start_time'])) ?> · <?= e($a['barber_name']) ?> · <?= money((float) $a['price']) ?></span>
          </div>
          <span class="badge badge--<?= e($a['status']) ?>"><?= e(status_label($a['status'])) ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>

  <?php endif; ?>
  </div>
</section>

<script id="client-data" type="application/json"><?= json_encode(['apiBase' => API_URL, 'csrf' => csrf_token()], JSON_HEX_TAG) ?></script>
<script>
(function () {
  var D = JSON.parse(document.getElementById('client-data').textContent);

  function post(path, payload) {
    return fetch(D.apiBase + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': D.csrf },
      body: JSON.stringify(Object.assign({ csrf_token: D.csrf }, payload))
    }).then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Resposta inválida.' }; }); });
  }

  function notify(boxId, message, type) {
    var box = document.getElementById(boxId);
    if (box) box.innerHTML = '<div class="alert alert--' + type + '">' + message + '</div>';
  }

  function applyFieldErrors(form, fields) {
    form.querySelectorAll('.field').forEach(function (f) {
      f.dataset.error = 'false';
      f.querySelector('.field__error').textContent = '';
    });
    Object.keys(fields || {}).forEach(function (k) {
      var f = form.querySelector('[data-field="' + k + '"]');
      if (!f) return;
      f.dataset.error = 'true';
      f.querySelector('.field__error').textContent = fields[k];
    });
  }

  // Abas entrar / criar conta
  document.querySelectorAll('[data-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('[data-tab]').forEach(function (b) {
        b.setAttribute('aria-selected', String(b === btn));
      });
      document.querySelectorAll('[data-pane]').forEach(function (p) {
        p.hidden = p.dataset.pane !== btn.dataset.tab;
      });
    });
  });

  ['form-login', 'form-register'].forEach(function (id) {
    var form = document.getElementById(id);
    if (!form) return;
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = form.querySelector('button[type="submit"]');
      var label = btn.textContent;
      btn.disabled = true; btn.innerHTML = '<span class="spinner"></span>';

      var payload = { action: id === 'form-login' ? 'login' : 'register' };
      new FormData(form).forEach(function (v, k) { payload[k] = v; });

      post('/auth.php', payload).then(function (res) {
        if (res.ok) { window.location.href = res.redirect; return; }
        btn.disabled = false; btn.textContent = label;
        applyFieldErrors(form, res.fields);
        notify('auth-feedback', res.error || 'Não foi possível continuar.', 'error');
      }).catch(function () {
        btn.disabled = false; btn.textContent = label;
        notify('auth-feedback', 'Falha de conexão.', 'error');
      });
    });
  });

  var phoneInput = document.getElementById('r-phone');
  if (phoneInput) {
    phoneInput.addEventListener('input', function () {
      var d = this.value.replace(/\D/g, '').slice(0, 11);
      this.value = d.length <= 2 ? d
        : d.length <= 6 ? '(' + d.slice(0,2) + ') ' + d.slice(2)
        : d.length <= 10 ? '(' + d.slice(0,2) + ') ' + d.slice(2,6) + '-' + d.slice(6)
        : '(' + d.slice(0,2) + ') ' + d.slice(2,7) + '-' + d.slice(7);
    });
  }

  // Cancelamento
  document.querySelectorAll('[data-cancel]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      btn.disabled = true;
      post('/appointments.php', { action: 'cancel', code: btn.dataset.cancel }).then(function (res) {
        if (res.ok) { window.location.reload(); return; }
        btn.disabled = false;
        notify('client-feedback', res.error || 'Não foi possível cancelar.', 'error');
      }).catch(function () {
        btn.disabled = false;
        notify('client-feedback', 'Falha de conexão.', 'error');
      });
    });
  });
})();
</script>
<?php require BASE_PATH . '/includes/footer.php'; ?>
