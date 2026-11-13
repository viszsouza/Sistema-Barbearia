<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';
require_once BASE_PATH . '/includes/notifications.php';
require_permission('appointments.view');

$pageTitle  = 'Agendamentos';
$activeMenu = 'agendamentos';

// ------------------------------------------------------------------ AÇÕES
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    require_permission('appointments.manage');
    $action = (string) input('action');

    if ($action === 'status') {
        $id     = (int) input('id');
        $status = (string) input('status');
        if (change_status($id, $status, (int) current_user()['id'], 'Alterado pelo painel')) {
            flash('Situação atualizada para "' . status_label($status) . '".');
        } else {
            flash('Não foi possível alterar a situação. Motivo: ' . (status_error() ?: 'erro desconhecido'), 'error');
        }
    }

    if ($action === 'create') {
        $name  = (string) input('customer_name');
        $phone = (string) input('customer_phone');
        $email = (string) input('customer_email');

        if (mb_strlen($name) < 3 || !is_valid_phone($phone)) {
            flash('Informe nome e telefone válidos do cliente.', 'error');
        } else {
            try {
                $appointment = create_appointment([
                    'service_id'  => (int) input('service_id'),
                    'barber_id'   => (int) input('barber_id'),
                    'date'        => (string) input('date'),
                    'time'        => (string) input('time'),
                    'customer_id' => find_or_create_customer($name, $phone, $email ?: null),
                    'notes'       => (string) input('notes') ?: null,
                    'status'      => (string) input('status', 'confirmed'),
                    'source'      => (string) input('source', 'admin'),
                    'created_by'  => (int) current_user()['id'],
                ]);
                notify_appointment_created($appointment);
                flash('Agendamento ' . $appointment['code'] . ' criado.');
            } catch (RuntimeException $e) {
                flash($e->getMessage(), 'error');
            }
        }
    }

    redirect(ADMIN_URL . '/agendamentos.php?' . http_build_query(array_filter([
        'de' => input('redirect_de', null, $_POST), 'ate' => input('redirect_ate', null, $_POST),
    ])));
}

// ---------------------------------------------------------------- FILTROS
$from    = (string) input('de', date('Y-m-d'), $_GET);
$to      = (string) input('ate', date('Y-m-d', strtotime('+14 days')), $_GET);
$status  = (string) input('status', '', $_GET);
$barber  = (int) input('barbeiro', 0, $_GET);
$search  = (string) input('busca', '', $_GET);
$page    = max(1, (int) input('p', 1, $_GET));
$perPage = 25;

$where  = ['a.appointment_date BETWEEN :from AND :to'];
$params = [':from' => $from, ':to' => $to];

if ($status !== '' && array_key_exists($status, appointment_statuses())) {
    $where[] = 'a.status = :status';
    $params[':status'] = $status;
}
if ($barber > 0) {
    $where[] = 'a.barber_id = :barber';
    $params[':barber'] = $barber;
}
if ($search !== '') {
    $where[] = '(c.name LIKE :q OR c.phone LIKE :q OR a.code LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

$countStmt = db()->prepare("SELECT COUNT(*) FROM appointments a JOIN customers c ON c.id = a.customer_id WHERE {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page  = min($page, $pages);

$stmt = db()->prepare(
    "SELECT a.*, c.name AS customer_name, c.phone AS customer_phone,
            b.name AS barber_name, s.name AS service_name
     FROM appointments a
     JOIN customers c ON c.id = a.customer_id
     JOIN barbers   b ON b.id = a.barber_id
     JOIN services  s ON s.id = a.service_id
     WHERE {$whereSql}
     ORDER BY a.appointment_date, a.start_time
     LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage)
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$barbers  = db()->query('SELECT id, name FROM barbers WHERE status = 1 ORDER BY display_order, name')->fetchAll();
$services = db()->query('SELECT id, name, price, duration_min FROM services WHERE status = 1 ORDER BY display_order, name')->fetchAll();
$openModal = isset($_GET['novo']);

require __DIR__ . '/_layout.php';
?>

<div class="box">
  <div class="box__head">
    <h2><?= $total ?> agendamento<?= $total === 1 ? '' : 's' ?></h2>
    <?php if (can('appointments.manage')): ?>
      <button class="btn btn--primary btn--sm" type="button" data-modal-open="modal-appointment">+ Novo agendamento</button>
    <?php endif; ?>
  </div>

  <div class="box__body">
    <form class="filters" method="get">
      <div class="field"><label for="de">De</label><input id="de" type="date" name="de" value="<?= e($from) ?>"></div>
      <div class="field"><label for="ate">Até</label><input id="ate" type="date" name="ate" value="<?= e($to) ?>"></div>
      <div class="field">
        <label for="status">Situação</label>
        <select id="status" name="status">
          <option value="">Todas</option>
          <?php foreach (appointment_statuses() as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= $status === $key ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="barbeiro">Barbeiro</label>
        <select id="barbeiro" name="barbeiro">
          <option value="">Todos</option>
          <?php foreach ($barbers as $b): ?>
            <option value="<?= (int) $b['id'] ?>"<?= $barber === (int) $b['id'] ? ' selected' : '' ?>><?= e($b['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label for="busca">Buscar</label><input id="busca" name="busca" placeholder="Nome, telefone ou código" value="<?= e($search) ?>"></div>
      <button class="btn btn--default" type="submit">Filtrar</button>
      <a class="btn btn--default" href="<?= e(ADMIN_URL) ?>/agendamentos.php">Limpar</a>
    </form>
  </div>

  <div class="box__body box__body--flush">
    <?php if (!$rows): ?>
      <div class="empty">
        <strong>Nenhum agendamento no período</strong>
        Ajuste os filtros ou crie um agendamento manualmente.
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr><th>Data</th><th>Hora</th><th>Cliente</th><th>Serviço</th><th>Barbeiro</th><th>Código</th><th class="num">Valor</th><th>Situação</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $a): ?>
              <tr>
                <td class="nowrap"><?= e(format_date($a['appointment_date'])) ?></td>
                <td class="nowrap"><strong><?= e(format_time($a['start_time'])) ?></strong><br><span class="muted" style="font-size:.78rem"><?= e(format_duration((int) $a['duration_min'])) ?></span></td>
                <td><?= e($a['customer_name']) ?><br><span class="muted" style="font-size:.8rem"><?= e($a['customer_phone']) ?></span></td>
                <td><?= e($a['service_name']) ?></td>
                <td><?= e($a['barber_name']) ?></td>
                <td class="muted"><?= e($a['code']) ?></td>
                <td class="num"><?= money((float) $a['price']) ?></td>
                <td><span class="badge badge--<?= e($a['status']) ?>"><?= e(status_label($a['status'])) ?></span></td>
                <td>
                  <?php if (can('appointments.manage')): ?>
                    <form method="post" class="actions">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="status">
                      <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                      <input type="hidden" name="redirect_de" value="<?= e($from) ?>">
                      <input type="hidden" name="redirect_ate" value="<?= e($to) ?>">
                      <select name="status" data-autosubmit>
                        <?php foreach (appointment_statuses() as $key => $label): ?>
                          <option value="<?= e($key) ?>"<?= $a['status'] === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pages > 1): ?>
        <div class="pagination">
          <?php for ($i = 1; $i <= $pages; $i++):
            $query = http_build_query(array_filter(['de' => $from, 'ate' => $to, 'status' => $status, 'barbeiro' => $barber ?: null, 'busca' => $search, 'p' => $i])); ?>
            <a href="?<?= e($query) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if (can('appointments.manage')): ?>
<dialog class="modal modal--lg" id="modal-appointment">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="redirect_de" value="<?= e($from) ?>">
    <input type="hidden" name="redirect_ate" value="<?= e($to) ?>">

    <div class="modal__head">
      <h3>Novo agendamento</h3>
      <button class="modal__close" type="button" data-modal-close aria-label="Fechar">&times;</button>
    </div>

    <div class="modal__body">
      <p class="muted" style="font-size:.88rem">Use para encaixes feitos presencialmente ou pelo WhatsApp. Se o telefone já existir, o cliente é reaproveitado.</p>

      <div class="row">
        <div class="field"><label for="c-name">Cliente</label><input id="c-name" name="customer_name" required></div>
        <div class="field"><label for="c-phone">Telefone</label><input id="c-phone" name="customer_phone" placeholder="(81) 99999-0000" required></div>
      </div>
      <div class="field"><label for="c-email">E-mail (opcional)</label><input id="c-email" name="customer_email" type="email"></div>

      <div class="field">
        <label for="a-service">Serviço</label>
        <select id="a-service" name="service_id" required>
          <?php foreach ($services as $s): ?>
            <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?> — <?= money((float) $s['price']) ?> · <?= e(format_duration((int) $s['duration_min'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="row">
        <div class="field">
          <label for="a-barber">Barbeiro</label>
          <select id="a-barber" name="barber_id" required>
            <?php foreach ($barbers as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label for="a-date">Data</label><input id="a-date" type="date" name="date" value="<?= e(date('Y-m-d')) ?>" required></div>
        <div class="field"><label for="a-time">Horário</label><input id="a-time" type="time" name="time" step="300" required></div>
      </div>

      <div class="row">
        <div class="field">
          <label for="a-status">Situação</label>
          <select id="a-status" name="status">
            <?php foreach (appointment_statuses() as $key => $label): ?>
              <option value="<?= e($key) ?>"<?= $key === 'confirmed' ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="a-source">Origem</label>
          <select id="a-source" name="source">
            <option value="admin">Presencial</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="phone">Telefone</option>
          </select>
        </div>
      </div>

      <div class="field"><label for="a-notes">Observação</label><textarea id="a-notes" name="notes"></textarea></div>
      <p class="field__hint">O horário é validado contra a agenda do barbeiro: se estiver ocupado, folga, feriado ou fora do expediente, o sistema recusa.</p>
    </div>

    <div class="modal__foot">
      <button class="btn btn--default" type="button" data-modal-close>Cancelar</button>
      <button class="btn btn--primary" type="submit">Criar agendamento</button>
    </div>
  </form>
</dialog>
<?php if ($openModal): ?>
<script>
  (function () {
    var dlg = document.getElementById('modal-appointment');
    if (dlg && !dlg.open) { dlg.showModal(); }
    if (window.history.replaceState) {
      var url = new URL(window.location.href);
      url.searchParams.delete('novo');
      window.history.replaceState({}, '', url.toString());
    }
  })();
</script>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
