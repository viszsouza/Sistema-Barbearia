<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/availability.php';

$code        = strtoupper(trim((string) ($_GET['codigo'] ?? '')));
$appointment = $code ? get_appointment_by_code($code) : null;

$pageTitle       = $appointment ? 'Agendamento ' . $appointment['code'] : 'Agendamento não encontrado';
$pageDescription = 'Confirmação do seu horário na ' . setting('business_name', APP_NAME) . '.';
require BASE_PATH . '/includes/header.php';
?>
<section class="section">
  <div class="wrap">
    <?php if (!$appointment): ?>
      <div class="empty" style="max-width:560px;margin-inline:auto">
        <strong>Agendamento não encontrado</strong>
        Confira o código recebido ou entre na sua conta para ver seus horários.
        <div style="margin-top:20px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
          <a class="btn btn--ghost btn--sm" href="cliente.php">Entrar na minha conta</a>
          <a class="btn btn--primary btn--sm" href="agendamento.php">Agendar horário</a>
        </div>
      </div>
    <?php else: ?>
      <div class="ticket">
        <div class="ticket__head">
          <?php if ($appointment['status'] === 'cancelled'): ?>
            <h1 style="font-size:var(--step-2);margin:0">Agendamento cancelado</h1>
            <p class="dim" style="margin:10px auto 0"><?= e((string) setting('cancellation_message', '')) ?></p>
          <?php else: ?>
            <h1 style="font-size:var(--step-2);margin:0">Agendamento confirmado</h1>
            <p class="dim" style="margin:10px auto 0"><?= e((string) setting('confirmation_message', '')) ?></p>
          <?php endif; ?>
          <div class="ticket__code"><?= e($appointment['code']) ?></div>
        </div>

        <dl class="ticket__body">
          <div class="ticket__row"><dt>SERVIÇO</dt><dd><?= e($appointment['service_name']) ?></dd></div>
          <div class="ticket__row"><dt>BARBEIRO</dt><dd><?= e($appointment['barber_name']) ?></dd></div>
          <div class="ticket__row"><dt>DATA</dt><dd><?= e(format_date_long($appointment['appointment_date'])) ?></dd></div>
          <div class="ticket__row"><dt>HORÁRIO</dt><dd><?= e(format_time($appointment['start_time'])) ?> — <?= e(format_time($appointment['end_time'])) ?></dd></div>
          <div class="ticket__row"><dt>DURAÇÃO</dt><dd><?= e(format_duration((int) $appointment['duration_min'])) ?></dd></div>
          <div class="ticket__row"><dt>VALOR</dt><dd style="color:var(--brass)"><?= money((float) $appointment['price']) ?></dd></div>
          <div class="ticket__row"><dt>CLIENTE</dt><dd><?= e($appointment['customer_name']) ?></dd></div>
          <div class="ticket__row"><dt>SITUAÇÃO</dt><dd><span class="badge badge--<?= e($appointment['status']) ?>"><?= e(status_label($appointment['status'])) ?></span></dd></div>
        </dl>

        <div class="ticket__actions">
          <?php if ($appointment['status'] !== 'cancelled'): ?>
            <a class="btn btn--primary" href="ics.php?codigo=<?= e(urlencode($appointment['code'])) ?>">Adicionar ao calendário</a>
            <a class="btn btn--ghost" href="<?= e(whatsapp_link(sprintf(
                'Olá! Acabei de realizar um agendamento para %s às %s (código %s).',
                format_date($appointment['appointment_date']),
                format_time($appointment['start_time']),
                $appointment['code']
            ))) ?>" target="_blank" rel="noopener">Falar com a barbearia</a>
          <?php endif; ?>
          <a class="btn btn--ghost" href="cliente.php">Ver meus agendamentos</a>
          <a class="btn btn--ghost" href="index.php">Voltar para o início</a>
        </div>
      </div>

      <?php if ($appointment['status'] !== 'cancelled' && can_cancel($appointment)): ?>
        <p class="dim" style="text-align:center;margin-top:22px;font-size:.9rem">
          Precisa desmarcar? Dá para cancelar online até <?= (int) setting('cancel_limit_hours', '24') ?>h antes,
          em <a href="cliente.php" style="color:var(--brass-lit)">meus agendamentos</a>.
        </p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php require BASE_PATH . '/includes/footer.php'; ?>
