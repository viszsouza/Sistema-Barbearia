<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';
require_permission('appointments.view');

$pageTitle  = 'Calendário';
$activeMenu = 'calendario';

$viewParam = (string) ($_GET['v'] ?? 'semana');
$view   = in_array($viewParam, ['dia', 'semana', 'mes'], true) ? $viewParam : 'semana';
$anchor = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['d'] ?? '')) ? (string) $_GET['d'] : date('Y-m-d');
$barber = (int) ($_GET['barbeiro'] ?? 0);

// Intervalo conforme a visualização
if ($view === 'dia') {
    $from = $to = $anchor;
    $label = format_date_long($anchor);
    $prev = date('Y-m-d', strtotime($anchor . ' -1 day'));
    $next = date('Y-m-d', strtotime($anchor . ' +1 day'));
} elseif ($view === 'semana') {
    $from  = date('Y-m-d', strtotime($anchor . ' -' . (int) date('w', strtotime($anchor)) . ' days'));
    $to    = date('Y-m-d', strtotime($from . ' +6 days'));
    $label = format_date($from) . ' — ' . format_date($to);
    $prev  = date('Y-m-d', strtotime($from . ' -7 days'));
    $next  = date('Y-m-d', strtotime($from . ' +7 days'));
} else {
    $from  = date('Y-m-01', strtotime($anchor));
    $to    = date('Y-m-t', strtotime($anchor));
    $label = month_name((int) date('n', strtotime($anchor))) . ' de ' . date('Y', strtotime($anchor));
    $prev  = date('Y-m-d', strtotime($from . ' -1 month'));
    $next  = date('Y-m-d', strtotime($from . ' +1 month'));
}

$sql = "SELECT a.*, c.name AS customer_name, b.name AS barber_name, s.name AS service_name
        FROM appointments a
        JOIN customers c ON c.id = a.customer_id
        JOIN barbers   b ON b.id = a.barber_id
        JOIN services  s ON s.id = a.service_id
        WHERE a.appointment_date BETWEEN :from AND :to";
$params = [':from' => $from, ':to' => $to];
if ($barber > 0) { $sql .= ' AND a.barber_id = :b'; $params[':b'] = $barber; }
$sql .= ' ORDER BY a.appointment_date, a.start_time';

$stmt = db()->prepare($sql);
$stmt->execute($params);

$byDate = [];
foreach ($stmt->fetchAll() as $row) {
    $byDate[$row['appointment_date']][] = $row;
}

$barbers = db()->query('SELECT id, name FROM barbers WHERE status = 1 ORDER BY display_order, name')->fetchAll();

$link = static fn(array $over) => ADMIN_URL . '/calendario.php?' . http_build_query(array_merge(
    ['v' => $view, 'd' => $anchor, 'barbeiro' => $barber ?: null], $over
));

require __DIR__ . '/_layout.php';

function slot_card(array $a): string
{
    return '<a class="slot-card slot-card--' . e($a['status']) . '" href="' . e(ADMIN_URL)
        . '/agendamentos.php?busca=' . e(urlencode($a['code'])) . '&de=' . e($a['appointment_date']) . '&ate=' . e($a['appointment_date']) . '">'
        . '<strong>' . e(format_time($a['start_time'])) . '</strong> ' . e($a['customer_name']) . '<br>'
        . '<span class="muted">' . e($a['service_name']) . ' · ' . e($a['barber_name']) . '</span></a>';
}
?>

<div class="box">
  <div class="box__head">
    <div class="agenda-nav">
      <div class="agenda-views">
        <a href="<?= e($link(['v' => 'dia'])) ?>"<?= $view === 'dia' ? ' aria-current="page"' : '' ?>>Dia</a>
        <a href="<?= e($link(['v' => 'semana'])) ?>"<?= $view === 'semana' ? ' aria-current="page"' : '' ?>>Semana</a>
        <a href="<?= e($link(['v' => 'mes'])) ?>"<?= $view === 'mes' ? ' aria-current="page"' : '' ?>>Mês</a>
      </div>
      <a class="btn btn--default btn--sm" href="<?= e($link(['d' => $prev])) ?>">‹</a>
      <a class="btn btn--default btn--sm" href="<?= e($link(['d' => date('Y-m-d')])) ?>">Hoje</a>
      <a class="btn btn--default btn--sm" href="<?= e($link(['d' => $next])) ?>">›</a>
      <strong style="font-family:Oswald,sans-serif;margin-left:6px"><?= e($label) ?></strong>
    </div>

    <form method="get" class="inline">
      <input type="hidden" name="v" value="<?= e($view) ?>">
      <input type="hidden" name="d" value="<?= e($anchor) ?>">
      <select name="barbeiro" data-autosubmit>
        <option value="">Todos os barbeiros</option>
        <?php foreach ($barbers as $b): ?>
          <option value="<?= (int) $b['id'] ?>"<?= $barber === (int) $b['id'] ? ' selected' : '' ?>><?= e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <div class="box__body">
    <?php if ($view === 'dia'): ?>
      <?php $list = $byDate[$anchor] ?? []; ?>
      <?php if (!$list): ?>
        <div class="empty"><strong>Nada marcado neste dia</strong>Crie um agendamento para preencher a agenda.
          <a class="btn btn--primary btn--sm" href="<?= e(ADMIN_URL) ?>/agendamentos.php?novo=1">+ Novo agendamento</a></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Horário</th><th>Cliente</th><th>Serviço</th><th>Barbeiro</th><th>Situação</th><th class="num">Valor</th></tr></thead>
            <tbody>
              <?php foreach ($list as $a): ?>
                <tr>
                  <td class="nowrap"><strong><?= e(format_time($a['start_time'])) ?></strong> – <?= e(format_time($a['end_time'])) ?></td>
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

    <?php elseif ($view === 'semana'): ?>
      <div class="week">
        <?php for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime($d . ' +1 day'))): ?>
          <div class="week__col">
            <div class="week__head">
              <strong><?= e(weekday_name((int) date('w', strtotime($d)), true)) ?> <?= e(date('d/m', strtotime($d))) ?></strong>
              <span class="muted"><?= count($byDate[$d] ?? []) ?> agendamento(s)</span>
            </div>
            <div class="week__body">
              <?php foreach ($byDate[$d] ?? [] as $a) { echo slot_card($a); } ?>
              <?php if (empty($byDate[$d])): ?><span class="muted" style="font-size:.8rem">—</span><?php endif; ?>
            </div>
          </div>
        <?php endfor; ?>
      </div>

    <?php else: ?>
      <div class="month">
        <?php foreach (['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'] as $dow): ?>
          <div class="month__dow"><?= e($dow) ?></div>
        <?php endforeach; ?>
        <?php
        $offset = (int) date('w', strtotime($from));
        for ($i = 0; $i < $offset; $i++) { echo '<div class="month__day month__day--out"></div>'; }
        for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime($d . ' +1 day'))):
          $count = count($byDate[$d] ?? []);
          $money = array_sum(array_map(fn($a) => $a['status'] === 'completed' ? (float) $a['price'] : 0, $byDate[$d] ?? []));
        ?>
          <div class="month__day">
            <a href="<?= e($link(['v' => 'dia', 'd' => $d])) ?>" style="text-decoration:none;color:inherit">
              <span class="month__num"><?= (int) date('j', strtotime($d)) ?></span>
              <?php if ($count): ?><br><span class="month__count"><?= $count ?> agend.</span><?php endif; ?>
              <?php if ($money > 0): ?><br><span class="muted"><?= money($money) ?></span><?php endif; ?>
            </a>
          </div>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
