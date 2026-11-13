<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';
require_permission('reports.view');

$pageTitle  = 'Relatórios';
$activeMenu = 'relatorios';

$period = (string) input('periodo', 'mes', $_GET);
$today  = date('Y-m-d');

switch ($period) {
    case 'hoje':      $from = $to = $today; break;
    case 'semana':    $from = date('Y-m-d', strtotime('-6 days')); $to = $today; break;
    case 'ano':       $from = date('Y-01-01'); $to = date('Y-12-31'); break;
    case 'personalizado':
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['de'] ?? '')) ? $_GET['de'] : date('Y-m-01');
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['ate'] ?? '')) ? $_GET['ate'] : $today;
        break;
    case 'mes':
    default:
        $period = 'mes';
        $from = date('Y-m-01'); $to = date('Y-m-t');
}

$q = static function (string $sql, array $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
};

$revenue    = (float) $q("SELECT COALESCE(SUM(price),0) FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status='completed'", [$from, $to])->fetchColumn();
$completed  = (int) $q("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status='completed'", [$from, $to])->fetchColumn();
$cancelled  = (int) $q("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status='cancelled'", [$from, $to])->fetchColumn();
$noShow     = (int) $q("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status='no_show'", [$from, $to])->fetchColumn();
$totalAppts = (int) $q("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status<>'cancelled'", [$from, $to])->fetchColumn();
$ticket     = $completed > 0 ? $revenue / $completed : 0.0;

$newClients = (int) $q('SELECT COUNT(*) FROM customers WHERE created_at BETWEEN ? AND ?', [$from . ' 00:00:00', $to . ' 23:59:59'])->fetchColumn();

$returningClients = (int) $q(
    "SELECT COUNT(DISTINCT a.customer_id) FROM appointments a
     WHERE a.appointment_date BETWEEN ? AND ? AND a.status = 'completed'
       AND EXISTS (SELECT 1 FROM appointments a2 WHERE a2.customer_id = a.customer_id AND a2.status='completed' AND a2.appointment_date < a.appointment_date)",
    [$from, $to]
)->fetchColumn();

$byService = $q(
    "SELECT s.name, COUNT(*) AS total, SUM(a.price) AS revenue FROM appointments a
     JOIN services s ON s.id = a.service_id
     WHERE a.appointment_date BETWEEN ? AND ? AND a.status = 'completed'
     GROUP BY s.id ORDER BY revenue DESC",
    [$from, $to]
)->fetchAll();

$byBarber = $q(
    "SELECT b.name, COUNT(*) AS total, SUM(a.price) AS revenue FROM appointments a
     JOIN barbers b ON b.id = a.barber_id
     WHERE a.appointment_date BETWEEN ? AND ? AND a.status = 'completed'
     GROUP BY b.id ORDER BY revenue DESC",
    [$from, $to]
)->fetchAll();

$byPayment = $q(
    "SELECT p.method, COUNT(*) AS total, SUM(p.amount) AS revenue FROM payment_records p
     JOIN appointments a ON a.id = p.appointment_id
     WHERE a.appointment_date BETWEEN ? AND ?
     GROUP BY p.method ORDER BY revenue DESC",
    [$from, $to]
)->fetchAll();

$topCustomers = $q(
    "SELECT c.name, COUNT(*) AS total, SUM(a.price) AS revenue FROM appointments a
     JOIN customers c ON c.id = a.customer_id
     WHERE a.appointment_date BETWEEN ? AND ? AND a.status = 'completed'
     GROUP BY c.id ORDER BY revenue DESC LIMIT 10",
    [$from, $to]
)->fetchAll();

require __DIR__ . '/_layout.php';
?>

<div class="box">
  <div class="box__body">
    <form class="filters" method="get">
      <div class="field">
        <label for="periodo">Período</label>
        <select id="periodo" name="periodo" data-autosubmit>
          <option value="hoje"<?= $period === 'hoje' ? ' selected' : '' ?>>Hoje</option>
          <option value="semana"<?= $period === 'semana' ? ' selected' : '' ?>>Últimos 7 dias</option>
          <option value="mes"<?= $period === 'mes' ? ' selected' : '' ?>>Este mês</option>
          <option value="ano"<?= $period === 'ano' ? ' selected' : '' ?>>Este ano</option>
          <option value="personalizado"<?= $period === 'personalizado' ? ' selected' : '' ?>>Personalizado</option>
        </select>
      </div>
      <?php if ($period === 'personalizado'): ?>
        <div class="field"><label for="de">De</label><input id="de" type="date" name="de" value="<?= e($from) ?>"></div>
        <div class="field"><label for="ate">Até</label><input id="ate" type="date" name="ate" value="<?= e($to) ?>"></div>
        <input type="hidden" name="periodo" value="personalizado">
        <button class="btn btn--default" type="submit">Aplicar</button>
      <?php endif; ?>
    </form>
    <p class="muted" style="margin:14px 0 0">Período analisado: <?= e(format_date($from)) ?> a <?= e(format_date($to)) ?></p>
  </div>
</div>

<div class="cards">
  <div class="card"><div class="card__label">Faturamento</div><div class="card__value"><?= money($revenue) ?></div><div class="card__hint"><?= $completed ?> atendimentos concluídos</div></div>
  <div class="card"><div class="card__label">Ticket médio</div><div class="card__value"><?= money($ticket) ?></div></div>
  <div class="card"><div class="card__label">Agendamentos (ativos)</div><div class="card__value"><?= $totalAppts ?></div><div class="card__hint"><?= $cancelled ?> cancelados · <?= $noShow ?> não compareceram</div></div>
  <div class="card"><div class="card__label">Clientes novos</div><div class="card__value"><?= $newClients ?></div><div class="card__hint"><?= $returningClients ?> clientes recorrentes atendidos</div></div>
</div>

<div class="grid-2">
  <div class="box">
    <div class="box__head"><h3>Faturamento por serviço</h3></div>
    <div class="box__body box__body--flush">
      <?php if (!$byService): ?><div class="empty">Sem dados no período.</div><?php else: ?>
        <div class="table-wrap"><table class="data">
          <thead><tr><th>Serviço</th><th class="num">Qtd.</th><th class="num">Faturamento</th></tr></thead>
          <tbody><?php foreach ($byService as $r): ?>
            <tr><td><?= e($r['name']) ?></td><td class="num"><?= (int) $r['total'] ?></td><td class="num"><?= money((float) $r['revenue']) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="box">
    <div class="box__head"><h3>Faturamento por barbeiro</h3></div>
    <div class="box__body box__body--flush">
      <?php if (!$byBarber): ?><div class="empty">Sem dados no período.</div><?php else: ?>
        <div class="table-wrap"><table class="data">
          <thead><tr><th>Barbeiro</th><th class="num">Qtd.</th><th class="num">Faturamento</th></tr></thead>
          <tbody><?php foreach ($byBarber as $r): ?>
            <tr><td><?= e($r['name']) ?></td><td class="num"><?= (int) $r['total'] ?></td><td class="num"><?= money((float) $r['revenue']) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="box">
    <div class="box__head"><h3>Formas de pagamento</h3></div>
    <div class="box__body box__body--flush">
      <?php if (!$byPayment): ?><div class="empty">Sem pagamentos registrados no período.</div><?php else: ?>
        <div class="table-wrap"><table class="data">
          <thead><tr><th>Forma</th><th class="num">Qtd.</th><th class="num">Total</th></tr></thead>
          <tbody><?php foreach ($byPayment as $r): ?>
            <tr><td><?= e(ucfirst((string) $r['method'])) ?></td><td class="num"><?= (int) $r['total'] ?></td><td class="num"><?= money((float) $r['revenue']) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="box">
    <div class="box__head"><h3>Clientes que mais gastaram</h3></div>
    <div class="box__body box__body--flush">
      <?php if (!$topCustomers): ?><div class="empty">Sem dados no período.</div><?php else: ?>
        <div class="table-wrap"><table class="data">
          <thead><tr><th>Cliente</th><th class="num">Visitas</th><th class="num">Total</th></tr></thead>
          <tbody><?php foreach ($topCustomers as $r): ?>
            <tr><td><?= e($r['name']) ?></td><td class="num"><?= (int) $r['total'] ?></td><td class="num"><?= money((float) $r['revenue']) ?></td></tr>
          <?php endforeach; ?></tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
