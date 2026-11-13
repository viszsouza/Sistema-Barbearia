<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';
require_permission('holidays.manage');

$pageTitle  = 'Feriados';
$activeMenu = 'feriados';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $action = (string) input('action');

    if ($action === 'save') {
        $name = (string) input('name');
        $date = (string) input('holiday_date');
        if (mb_strlen($name) < 2 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            flash('Informe nome e data válidos.', 'error');
        } else {
            db()->prepare(
                'INSERT INTO holidays (name, holiday_date, description, status) VALUES (:n, :d, :desc, 1)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), status = 1'
            )->execute([':n' => $name, ':d' => $date, ':desc' => (string) input('description') ?: null]);
            flash('Feriado salvo.');
        }
    }

    if ($action === 'toggle') {
        db()->prepare('UPDATE holidays SET status = 1 - status WHERE id = :id')->execute([':id' => (int) input('id')]);
        flash('Situação do feriado alterada.');
    }

    if ($action === 'delete') {
        db()->prepare('DELETE FROM holidays WHERE id = :id')->execute([':id' => (int) input('id')]);
        flash('Feriado removido.');
    }

    redirect(ADMIN_URL . '/feriados.php');
}

$holidays = db()->query('SELECT * FROM holidays ORDER BY holiday_date')->fetchAll();

require __DIR__ . '/_layout.php';
?>

<div class="grid-2">
  <div class="box">
    <div class="box__head"><h2>Novo feriado</h2></div>
    <div class="box__body">
      <p class="muted">Nos dias marcados como feriado, nenhum horário aparece disponível para agendamento — mesmo que o dia normalmente esteja aberto.</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="row">
          <div class="field"><label for="h-name">Nome</label><input id="h-name" name="name" required></div>
          <div class="field"><label for="h-date">Data</label><input id="h-date" type="date" name="holiday_date" required></div>
        </div>
        <div class="field"><label for="h-desc">Descrição (opcional)</label><input id="h-desc" name="description"></div>
        <button class="btn btn--primary" type="submit">Salvar feriado</button>
      </form>
    </div>
  </div>

  <div class="box">
    <div class="box__head"><h2>Feriados cadastrados</h2></div>
    <div class="box__body box__body--flush">
      <?php if (!$holidays): ?>
        <div class="empty">Nenhum feriado cadastrado.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Data</th><th>Nome</th><th>Situação</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($holidays as $h): ?>
                <tr>
                  <td class="nowrap"><?= e(format_date($h['holiday_date'])) ?></td>
                  <td><?= e($h['name']) ?><br><span class="muted" style="font-size:.8rem"><?= e((string) $h['description']) ?></span></td>
                  <td><span class="badge badge--<?= (int) $h['status'] ? 'on' : 'off' ?>"><?= (int) $h['status'] ? 'Ativo' : 'Inativo' ?></span></td>
                  <td>
                    <div class="actions">
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $h['id'] ?>"><button class="btn btn--default btn--sm"><?= (int) $h['status'] ? 'Desativar' : 'Ativar' ?></button></form>
                      <form method="post" data-confirm="Remover o feriado &quot;<?= e($h['name']) ?>&quot;?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $h['id'] ?>"><button class="btn btn--danger btn--sm">Remover</button></form>
                    </div>
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

<?php require __DIR__ . '/_layout_end.php'; ?>
