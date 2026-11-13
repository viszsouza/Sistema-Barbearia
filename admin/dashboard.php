<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';
require_permission('dashboard.view');

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';

$today     = date('Y-m-d');
$monthFrom = date('Y-m-01');
$monthTo   = date('Y-m-t');
$prevFrom  = date('Y-m-01', strtotime('-1 month'));
$prevTo    = date('Y-m-t', strtotime('-1 month'));

$q = static function (string $sql, array $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
};

// ------------------------------------------------------------------ CARDS
$todayTotal = (int) $q("SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND status <> 'cancelled'", [$today])->fetchColumn();
$todayDone  = (int) $q("SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND status = 'completed'", [$today])->fetchColumn();
$todayWait  = (int) $q("SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND status IN ('pending','confirmed')", [$today])->fetchColumn();
$todayMoney = (float) $q("SELECT COALESCE(SUM(price),0) FROM appointments WHERE appointment_date = ? AND status = 'completed'", [$today])->fetchColumn();

$customers  = (int) db()->query('SELECT COUNT(*) FROM customers WHERE status = 1')->fetchColumn();
$monthMoney = (float) $q("SELECT COALESCE(SUM(price),0) FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'completed'", [$monthFrom, $monthTo])->fetchColumn();
$prevMoney  = (float) $q("SELECT COALESCE(SUM(price),0) FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'completed'", [$prevFrom, $prevTo])->fetchColumn();
$monthDone  = (int) $q("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'completed'", [$monthFrom, $monthTo])->fetchColumn();

$moneyDelta = $prevMoney > 0 ? (($monthMoney - $prevMoney) / $prevMoney) * 100 : null;

// -------------------------------------------------------- INDICADORES EXTRA
$ticket     = $monthDone > 0 ? $monthMoney / $monthDone : 0.0;
$cancelled  = (int) $q("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'cancelled'", [$monthFrom, $monthTo])->fetchColumn();
$noShow     = (int) $q("SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN ? AND ? AND status = 'no_show'", [$monthFrom, $monthTo])->fetchColumn();
$newClients = (int) $q('SELECT COUNT(*) FROM customers WHERE created_at BETWEEN ? AND ?', [$monthFrom . ' 00:00:00', $monthTo . ' 23:59:59'])->fetchColumn();
$returning  = (int) $q(
    "SELECT COUNT(*) FROM (
        SELECT customer_id FROM appointments
        WHERE status = 'completed' GROUP BY customer_id HAVING COUNT(*) > 1
     ) t"
)->fetchColumn();

// Taxa de ocupação do mês: minutos agendados / minutos disponíveis dos barbeiros
$bookedMin = (int) $q("SELECT COALESCE(SUM(duration_min),0) FROM appointments
                       WHERE appointment_date BETWEEN ? AND ? AND status IN ('completed','in_progress','confirmed','pending')",
                      [$monthFrom, $monthTo])->fetchColumn();

$capacityMin = 0;
$activeBarbers = (int) db()->query('SELECT COUNT(*) FROM barbers WHERE status = 1')->fetchColumn();
foreach (db()->query('SELECT weekday, is_open, open_time, close_time, break_start, break_end FROM business_hours') as $bh) {
    if (!(int) $bh['is_open']) continue;
    $minutes = time_to_minutes($bh['close_time']) - time_to_minutes($bh['open_time']);
    if ($bh['break_start'] && $bh['break_end']) {
        $minutes -= time_to_minutes($bh['break_end']) - time_to_minutes($bh['break_start']);
    }
    // quantas vezes esse dia da semana cai no mês
    $count = 0;
    for ($d = $monthFrom; $d <= $monthTo; $d = date('Y-m-d', strtotime($d . ' +1 day'))) {
        if ((int) date('w', strtotime($d)) === (int) $bh['weekday']) $count++;
    }
    $capacityMin += $minutes * $count * $activeBarbers;
}
$occupancy = $capacityMin > 0 ? min(100, ($bookedMin / $capacityMin) * 100) : 0;

// --------------------------------------------------------------- GRÁFICOS
$byDay = $q(
    "SELECT appointment_date, COUNT(*) AS total FROM appointments
     WHERE appointment_date BETWEEN ? AND ? AND status <> 'cancelled'
     GROUP BY appointment_date",
    [date('Y-m-d', strtotime('-13 days')), $today]
)->fetchAll(PDO::FETCH_KEY_PAIR);

$days = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $days[$d] = (int) ($byDay[$d] ?? 0);
}

$byMonth = $q(
    "SELECT DATE_FORMAT(appointment_date, '%Y-%m') AS ym, SUM(price) AS total
     FROM appointments WHERE status = 'completed' AND appointment_date >= ?
     GROUP BY ym ORDER BY ym",
    [date('Y-m-01', strtotime('-5 months'))]
)->fetchAll(PDO::FETCH_KEY_PAIR);

$topServices = $q(
    "SELECT s.name, COUNT(*) AS total FROM appointments a
     JOIN services s ON s.id = a.service_id
     WHERE a.status = 'completed' AND a.appointment_date BETWEEN ? AND ?
     GROUP BY s.id ORDER BY total DESC LIMIT 5",
    [$monthFrom, $monthTo]
)->fetchAll();

$topBarbers = $q(
    "SELECT b.name, COUNT(*) AS total FROM appointments a
     JOIN barbers b ON b.id = a.barber_id
     WHERE a.status = 'completed' AND a.appointment_date BETWEEN ? AND ?
     GROUP BY b.id ORDER BY total DESC",
    [$monthFrom, $monthTo]
)->fetchAll();

$topHours = $q(
    "SELECT DATE_FORMAT(start_time,'%H:00') AS hour, COUNT(*) AS total FROM appointments
     WHERE status <> 'cancelled' AND appointment_date BETWEEN ? AND ?
     GROUP BY hour ORDER BY total DESC LIMIT 6",
    [$monthFrom, $monthTo]
)->fetchAll();

$next = $q(
    "SELECT a.*, c.name AS customer_name, b.name AS barber_name, s.name AS service_name
     FROM appointments a
     JOIN customers c ON c.id = a.customer_id
     JOIN barbers b ON b.id = a.barber_id
     JOIN services s ON s.id = a.service_id
     WHERE a.appointment_date = ? AND a.status IN ('pending','confirmed','in_progress')
     ORDER BY a.start_time LIMIT 8",
    [$today]
)->fetchAll();

$greeting = (int) date('H') < 12 ? 'Bom dia' : ((int) date('H') < 18 ? 'Boa tarde' : 'Boa noite');

require __DIR__ . '/_layout.php';

/** Desenha um gráfico de barras verticais. */
function bar_chart(array $data, callable $format): string
{
    $max = max(array_map('floatval', $data ?: [0])) ?: 1;
    $html = '<div class="chart">';
    foreach ($data as $label => $value) {
        $height = max(2, ((float) $value / $max) * 100);
        $html .= '<div class="chart__col" title="' . e((string) $label) . ': ' . e($format($value)) . '">'
              . '<span class="chart__value">' . e($format($value)) . '</span>'
              . '<div class="chart__bar" style="height:' . round($height) . '%"></div>'
              . '<span class="chart__label">' . e((string) $label) . '</span></div>';
    }
    return $html . '</div>';
}
?>

<h1 style="margin-bottom:4px"><?= e($greeting) ?>, <?= e(explode(' ', $user['name'])[0]) ?>.</h1>
<p class="muted">Hoje é <?= e(format_date_long($today)) ?>.</p>

<div class="cards">
  <div class="card">
    <div class="card__label">Agendamentos hoje</div>
    <div class="card__value"><?= $todayTotal ?></div>
    <div class="card__hint"><?= $todayDone ?> <?= $todayDone === 1 ? 'concluído' : 'concluídos' ?> · <?= $todayWait ?> aguardando</div>
  </div>
  <div class="card">
    <div class="card__label">Faturado hoje</div>
    <div class="card__value"><?= money($todayMoney) ?></div>
    <div class="card__hint">Somente atendimentos concluídos</div>
  </div>
  <div class="card">
    <div class="card__label">Faturamento do mês</div>
    <div class="card__value"><?= money($monthMoney) ?></div>
    <div class="card__hint<?= $moneyDelta === null ? '' : ($moneyDelta >= 0 ? ' card__hint--up' : ' card__hint--down') ?>">
      <?= $moneyDelta === null ? 'Sem base do mês anterior' : (($moneyDelta >= 0 ? '+' : '') . number_format($moneyDelta, 1, ',', '.') . '% vs. mês anterior') ?>
    </div>
  </div>
  <div class="card">
    <div class="card__label">Clientes cadastrados</div>
    <div class="card__value"><?= $customers ?></div>
    <div class="card__hint"><?= $newClients ?> novos neste mês</div>
  </div>
</div>

<div class="cards">
  <div class="card"><div class="card__label">Taxa de ocupação</div><div class="card__value"><?= number_format($occupancy, 0) ?>%</div><div class="card__hint">Da capacidade do mês</div></div>
  <div class="card"><div class="card__label">Ticket médio</div><div class="card__value"><?= money($ticket) ?></div><div class="card__hint"><?= $monthDone ?> atendimentos no mês</div></div>
  <div class="card"><div class="card__label">Cancelamentos</div><div class="card__value"><?= $cancelled ?></div><div class="card__hint">No mês corrente</div></div>
  <div class="card"><div class="card__label">Não comparecimentos</div><div class="card__value"><?= $noShow ?></div><div class="card__hint"><?= $returning ?> clientes recorrentes</div></div>
</div>

<div class="grid-2">
  <div class="box">
    <div class="box__head"><h2>Agendamentos por dia</h2><span class="muted" style="font-size:.84rem">Últimos 14 dias</span></div>
    <div class="box__body"><?= bar_chart($days, fn($v) => (string) $v) ?></div>
  </div>

  <div class="box">
    <div class="box__head"><h2>Faturamento por mês</h2><span class="muted" style="font-size:.84rem">Últimos 6 meses</span></div>
    <div class="box__body">
      <?php
      $labels = [];
      for ($i = 5; $i >= 0; $i--) {
          $ym = date('Y-m', strtotime("-{$i} months"));
          $labels[substr(month_name((int) substr($ym, 5, 2)), 0, 3)] = (float) ($byMonth[$ym] ?? 0);
      }
      echo bar_chart($labels, fn($v) => 'R$ ' . number_format((float) $v / 1000, 1, ',', '.') . 'k');
      ?>
    </div>
  </div>
</div>

<div class="grid-3">
  <div class="box">
    <div class="box__head"><h3>Serviços mais realizados</h3></div>
    <div class="box__body">
      <?php if (!$topServices): ?><div class="empty">Sem dados no mês.</div><?php else:
        $max = max(array_column($topServices, 'total')); ?>
        <div class="hbar">
          <?php foreach ($topServices as $row): ?>
            <div class="hbar__row">
              <div><?= e($row['name']) ?><div class="hbar__track"><div class="hbar__fill" style="width:<?= round(((int) $row['total'] / $max) * 100) ?>%"></div></div></div>
              <span class="hbar__value"><?= (int) $row['total'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="box">
    <div class="box__head"><h3>Atendimentos por barbeiro</h3></div>
    <div class="box__body">
      <?php if (!$topBarbers): ?><div class="empty">Sem dados no mês.</div><?php else:
        $max = max(array_column($topBarbers, 'total')); ?>
        <div class="hbar">
          <?php foreach ($topBarbers as $row): ?>
            <div class="hbar__row">
              <div><?= e($row['name']) ?><div class="hbar__track"><div class="hbar__fill" style="width:<?= round(((int) $row['total'] / $max) * 100) ?>%"></div></div></div>
              <span class="hbar__value"><?= (int) $row['total'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="box">
    <div class="box__head"><h3>Horários mais procurados</h3></div>
    <div class="box__body">
      <?php if (!$topHours): ?><div class="empty">Sem dados no mês.</div><?php else:
        $max = max(array_column($topHours, 'total')); ?>
        <div class="hbar">
          <?php foreach ($topHours as $row): ?>
            <div class="hbar__row">
              <div><?= e($row['hour']) ?><div class="hbar__track"><div class="hbar__fill" style="width:<?= round(((int) $row['total'] / $max) * 100) ?>%"></div></div></div>
              <span class="hbar__value"><?= (int) $row['total'] ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="box">
  <div class="box__head">
    <h2>Próximos horários de hoje</h2>
    <a class="btn btn--default btn--sm" href="<?= e(ADMIN_URL) ?>/calendario.php">Ver agenda completa</a>
  </div>
  <div class="box__body box__body--flush">
    <?php if (!$next): ?>
      <div class="empty"><strong>Nada na agenda para o resto de hoje</strong>Aproveite para revisar os serviços ou cadastrar um encaixe.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Horário</th><th>Cliente</th><th>Serviço</th><th>Barbeiro</th><th>Situação</th><th class="num">Valor</th></tr></thead>
          <tbody>
            <?php foreach ($next as $a): ?>
              <tr>
                <td class="nowrap"><strong><?= e(format_time($a['start_time'])) ?></strong></td>
                <td><?= e($a['customer_name']) ?></td>
                <td><?= e($a['service_name']) ?></td>
                <td><?= e($a['barber_name']) ?></td>
                <td><span class="badge badge--<?= e($a['status']) ?>"><?= e(status_label($a['status'])) ?></span></td>
                <td class="num"><?= money((float) $a['price']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
