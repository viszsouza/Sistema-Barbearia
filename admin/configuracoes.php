<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';
require_permission('settings.manage');

$pageTitle  = 'Configurações';
$activeMenu = 'configuracoes';

$fields = [
    'business_name', 'business_tagline', 'business_phone', 'business_whatsapp', 'business_email',
    'business_address', 'business_city', 'business_state', 'business_maps_embed',
    'social_instagram', 'social_facebook',
    'slot_step', 'min_advance_minutes', 'max_advance_days', 'cancel_limit_hours',
    'confirmation_message', 'cancellation_message', 'stat_clients', 'stat_years',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    foreach ($fields as $key) {
        set_setting($key, (string) input($key, ''));
    }
    set_setting('notify_email_enabled', isset($_POST['notify_email_enabled']) ? '1' : '0');
    set_setting('notify_whatsapp_enabled', isset($_POST['notify_whatsapp_enabled']) ? '1' : '0');
    flash('Configurações salvas.');
    redirect(ADMIN_URL . '/configuracoes.php');
}

$values = [];
foreach ($fields as $key) { $values[$key] = (string) setting($key, ''); }

require __DIR__ . '/_layout.php';
?>

<form method="post">
<?= csrf_field() ?>

<div class="grid-2">
  <div class="box">
    <div class="box__head"><h2>Dados da barbearia</h2></div>
    <div class="box__body">
      <div class="field"><label for="business_name">Nome</label><input id="business_name" name="business_name" value="<?= e($values['business_name']) ?>" required></div>
      <div class="field"><label for="business_tagline">Frase de efeito</label><input id="business_tagline" name="business_tagline" value="<?= e($values['business_tagline']) ?>"></div>
      <div class="row">
        <div class="field"><label for="business_phone">Telefone</label><input id="business_phone" name="business_phone" value="<?= e($values['business_phone']) ?>"></div>
        <div class="field"><label for="business_whatsapp">WhatsApp (só números, com DDI)</label><input id="business_whatsapp" name="business_whatsapp" value="<?= e($values['business_whatsapp']) ?>" placeholder="5581999998888"></div>
      </div>
      <div class="field"><label for="business_email">E-mail</label><input id="business_email" name="business_email" type="email" value="<?= e($values['business_email']) ?>"></div>
      <div class="field"><label for="business_address">Endereço completo</label><input id="business_address" name="business_address" value="<?= e($values['business_address']) ?>"></div>
      <div class="row">
        <div class="field"><label for="business_city">Cidade</label><input id="business_city" name="business_city" value="<?= e($values['business_city']) ?>"></div>
        <div class="field"><label for="business_state">Estado (UF)</label><input id="business_state" name="business_state" maxlength="2" value="<?= e($values['business_state']) ?>"></div>
      </div>
      <div class="field"><label for="business_maps_embed">URL de embed do Google Maps</label><input id="business_maps_embed" name="business_maps_embed" value="<?= e($values['business_maps_embed']) ?>"></div>
      <div class="row">
        <div class="field"><label for="social_instagram">Instagram</label><input id="social_instagram" name="social_instagram" value="<?= e($values['social_instagram']) ?>"></div>
        <div class="field"><label for="social_facebook">Facebook</label><input id="social_facebook" name="social_facebook" value="<?= e($values['social_facebook']) ?>"></div>
      </div>
    </div>
  </div>

  <div class="stack">
    <div class="box">
      <div class="box__head"><h2>Regras de agendamento</h2></div>
      <div class="box__body">
        <div class="row">
          <div class="field"><label for="slot_step">Intervalo entre horários (min)</label><input id="slot_step" name="slot_step" type="number" min="5" step="5" value="<?= e($values['slot_step']) ?>"></div>
          <div class="field"><label for="min_advance_minutes">Antecedência mínima (min)</label><input id="min_advance_minutes" name="min_advance_minutes" type="number" min="0" value="<?= e($values['min_advance_minutes']) ?>"></div>
        </div>
        <div class="row">
          <div class="field"><label for="max_advance_days">Agendar com até quantos dias de antecedência</label><input id="max_advance_days" name="max_advance_days" type="number" min="1" value="<?= e($values['max_advance_days']) ?>"></div>
          <div class="field"><label for="cancel_limit_hours">Cancelamento online até (horas antes)</label><input id="cancel_limit_hours" name="cancel_limit_hours" type="number" min="0" value="<?= e($values['cancel_limit_hours']) ?>"></div>
        </div>
      </div>
    </div>

    <div class="box">
      <div class="box__head"><h2>Notificações</h2></div>
      <div class="box__body">
        <div class="field field--inline"><input id="ne" name="notify_email_enabled" type="checkbox" value="1"<?= setting('notify_email_enabled', '1') === '1' ? ' checked' : '' ?>><label for="ne">Enviar confirmação por e-mail</label></div>
        <div class="field field--inline"><input id="nw" name="notify_whatsapp_enabled" type="checkbox" value="1"<?= setting('notify_whatsapp_enabled', '0') === '1' ? ' checked' : '' ?>><label for="nw">Enviar confirmação por WhatsApp</label></div>
        <p class="field__hint">O envio por WhatsApp depende de um driver de API configurado em <code>includes/notifications.php</code> (ver observação técnica no README).</p>
        <div class="field"><label for="confirmation_message">Mensagem de confirmação</label><textarea id="confirmation_message" name="confirmation_message"><?= e($values['confirmation_message']) ?></textarea></div>
        <div class="field"><label for="cancellation_message">Mensagem de cancelamento</label><textarea id="cancellation_message" name="cancellation_message"><?= e($values['cancellation_message']) ?></textarea></div>
      </div>
    </div>

    <div class="box">
      <div class="box__head"><h2>Números de destaque (seção "Sobre")</h2></div>
      <div class="box__body row">
        <div class="field"><label for="stat_clients">Clientes atendidos</label><input id="stat_clients" name="stat_clients" value="<?= e($values['stat_clients']) ?>"></div>
        <div class="field"><label for="stat_years">Anos de ofício</label><input id="stat_years" name="stat_years" value="<?= e($values['stat_years']) ?>"></div>
      </div>
    </div>
  </div>
</div>

<button class="btn btn--primary" type="submit">Salvar configurações</button>
</form>

<?php require __DIR__ . '/_layout_end.php'; ?>
