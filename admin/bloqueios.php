<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';
require_permission('blocks.manage');

$pageTitle  = 'Folgas e bloqueios';
$activeMenu = 'bloqueios';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $action = (string) input('action');

    if ($action === 'save') {
        $date    = (string) input('block_date');
        $allDay  = isset($_POST['all_day']) ? 1 : 0;
        $barber  = (int) input('barber_id') ?: null;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            flash('Escolha uma data válida.', 'error');
        } else {
            db()->prepare(
                'INSERT INTO blocked_times (barber_id, block_date, start_time, end_time, all_day, reason)
                 VALUES (:b, :d, :s, :e, :a, :r)'
            )->execute([
                ':b' => $barber, ':d' => $date,
                ':s' => $allDay ? '00:00:00' : ((string) input('start_time') ?: '00:00:00'),
                ':e' => $allDay ? '23:59:00' : ((string) input('end_time') ?: '23:59:00'),
                ':a' => $allDay, ':r' => (string) input('reason') ?: null,
            ]);
            flash('Bloqueio criado.');
        }
    }

    if ($action === 'delete') {
        db()->prepare('DELETE FROM blocked_times WHERE id = :id')->execute([':id' => (int) input('id')]);
        flash('Bloqueio removido.');
    }

    redirect(ADMIN_URL . '/bloqueios.php');
}

$rows = db()->query(
    'SELECT bt.*, b.name AS barber_name FROM blocked_times bt
     LEFT JOIN barbers b ON b.id = bt.barber_id
     WHERE bt.block_date >= CURDATE()
     ORDER BY bt.block_date, bt.start_time'
)->fetchAll();

$past = db()->query(
    'SELECT bt.*, b.name AS barber_name FROM blocked_times bt
     LEFT JOIN barbers b ON b.id = bt.barber_id
     WHERE bt.block_date < CURDATE()
     ORDER BY bt.block_date DESC LIMIT 15'
)->fetchAll();

$barbers = db()->query('SELECT id, name FROM barbers WHERE status = 1 ORDER BY display_order, name')->fetchAll();

require __DIR__ . '/_layout.php';
?>

<div class="grid-2">
  <div class="box">
    <div class="box__head"><h2>Novo bloqueio</h2></div>
    <div class="box__body">
      <p class="muted">Use para folgas, compromissos, manutenção ou qualquer indisponibilidade fora dos feriados. Deixe "Barbeiro" em branco para bloquear a barbearia inteira.</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="row">
          <div class="field"><label for="bl-barber">Barbeiro</label>
            <select id="bl-barber" name="barber_id">
              <option value="">Toda a barbearia</option>
              <?php foreach ($barbers as $b): ?><option value="<?= (int) $b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label for="bl-date">Data</label><input id="bl-date" type="date" name="block_date" required></div>
        </div>
        <div class="field field--inline"><input id="bl-allday" type="checkbox" name="all_day" value="1" checked onchange="document.getElementById('bl-times').style.display=this.checked?'none':'grid'"><label for="bl-allday">Dia inteiro</label></div>
        <div class="row" id="bl-times" style="display:none">
          <div class="field"><label for="bl-start">Início</label><input id="bl-start" type="time" name="start_time"></div>
          <div class="field"><label for="bl-end">Fim</label><input id="bl-end" type="time" name="end_time"></div>
        </div>
        <div class="field"><label for="bl-reason">Motivo (interno)</label><input id="bl-reason" name="reason" placeholder="Ex.: consulta médica"></div>
        <button class="btn btn--primary" type="submit">Criar bloqueio</button>
      </form>
    </div>
  </div>

  <div class="box">
    <div class="box__head"><h2>Bloqueios futuros</h2></div>
    <div class="box__body box__body--flush">
      <?php if (!$rows): ?>
        <div class="empty">Nenhum bloqueio agendado.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Data</th><th>Período</th><th>Barbeiro</th><th>Motivo</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= e(format_date($r['block_date'])) ?></td>
                  <td><?= (int) $r['all_day'] ? 'Dia inteiro' : e(format_time($r['start_time']) . ' – ' . format_time($r['end_time'])) ?></td>
                  <td><?= e((string) ($r['barber_name'] ?? 'Toda a barbearia')) ?></td>
                  <td class="muted"><?= e((string) $r['reason']) ?></td>
                  <td>
                    <form method="post" data-confirm="Remover este bloqueio?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn--danger btn--sm">Remover</button></form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($past): ?>
<div class="box">
  <div class="box__head"><h3>Histórico recente</h3></div>
  <div class="box__body box__body--flush">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Data</th><th>Período</th><th>Barbeiro</th><th>Motivo</th></tr></thead>
        <tbody>
          <?php foreach ($past as $r): ?>
            <tr>
              <td><?= e(format_date($r['block_date'])) ?></td>
              <td><?= (int) $r['all_day'] ? 'Dia inteiro' : e(format_time($r['start_time']) . ' – ' . format_time($r['end_time'])) ?></td>
              <td><?= e((string) ($r['barber_name'] ?? 'Toda a barbearia')) ?></td>
              <td class="muted"><?= e((string) $r['reason']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
