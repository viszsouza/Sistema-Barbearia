<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';
require_permission('hours.view');

$pageTitle  = 'Horário de funcionamento';
$activeMenu = 'horarios';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    require_permission('hours.manage');

    $upsert = db()->prepare(
        'INSERT INTO business_hours (weekday, is_open, open_time, close_time, break_start, break_end)
         VALUES (:w, :open, :ot, :ct, :bs, :be)
         ON DUPLICATE KEY UPDATE is_open=VALUES(is_open), open_time=VALUES(open_time),
         close_time=VALUES(close_time), break_start=VALUES(break_start), break_end=VALUES(break_end)'
    );
    for ($w = 0; $w <= 6; $w++) {
        $open = isset($_POST['open'][$w]) ? 1 : 0;
        $upsert->execute([
            ':w' => $w, ':open' => $open,
            ':ot' => $open ? ($_POST['open_time'][$w] ?: null) : null,
            ':ct' => $open ? ($_POST['close_time'][$w] ?: null) : null,
            ':bs' => $open ? ($_POST['break_start'][$w] ?: null) : null,
            ':be' => $open ? ($_POST['break_end'][$w] ?: null) : null,
        ]);
    }
    flash('Horário de funcionamento atualizado.');
    redirect(ADMIN_URL . '/horarios.php');
}

$rows = [];
foreach (db()->query('SELECT * FROM business_hours') as $r) { $rows[(int) $r['weekday']] = $r; }

require __DIR__ . '/_layout.php';
?>

<div class="box">
  <div class="box__head"><h2>Horário de funcionamento da barbearia</h2></div>
  <div class="box__body">
    <p class="muted">Esse é o horário geral. Cada barbeiro pode ter um horário próprio em <a href="<?= e(ADMIN_URL) ?>/barbeiros.php">Barbeiros</a> — o sistema usa a interseção dos dois.</p>

    <form method="post">
      <?= csrf_field() ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Dia</th><th>Aberto</th><th>Abertura</th><th>Fechamento</th><th>Intervalo de</th><th>até</th></tr></thead>
          <tbody>
            <?php foreach ([1,2,3,4,5,6,0] as $w): $r = $rows[$w] ?? []; ?>
              <tr>
                <td><strong><?= e(weekday_name($w)) ?></strong></td>
                <td><input type="checkbox" name="open[<?= $w ?>]" value="1"<?= !empty($r['is_open']) ? ' checked' : '' ?>></td>
                <td><input type="time" name="open_time[<?= $w ?>]" value="<?= e(substr((string) ($r['open_time'] ?? ''), 0, 5)) ?>"></td>
                <td><input type="time" name="close_time[<?= $w ?>]" value="<?= e(substr((string) ($r['close_time'] ?? ''), 0, 5)) ?>"></td>
                <td><input type="time" name="break_start[<?= $w ?>]" value="<?= e(substr((string) ($r['break_start'] ?? ''), 0, 5)) ?>"></td>
                <td><input type="time" name="break_end[<?= $w ?>]" value="<?= e(substr((string) ($r['break_end'] ?? ''), 0, 5)) ?>"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (can('hours.manage')): ?>
        <button class="btn btn--primary" style="margin-top:16px" type="submit">Salvar horário</button>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php require __DIR__ . '/_layout_end.php'; ?>
