<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';
require_permission('barbers.view');

$pageTitle  = 'Barbeiros';
$activeMenu = 'barbeiros';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    require_permission('barbers.manage');
    $action = (string) input('action');
    $id     = (int) input('id');

    if ($action === 'save') {
        $name = (string) input('name');
        if (mb_strlen($name) < 3) {
            flash('Informe o nome do barbeiro.', 'error');
        } else {
            $data = [
                ':name'        => $name,
                ':headline'    => (string) input('headline') ?: null,
                ':bio'         => (string) input('bio') ?: null,
                ':specialties' => (string) input('specialties') ?: null,
                ':phone'       => (string) input('phone') ?: null,
                ':email'       => (string) input('email') ?: null,
                ':order'       => (int) input('display_order'),
                ':status'      => isset($_POST['status']) ? 1 : 0,
            ];

            if ($id) {
                $data[':id'] = $id;
                db()->prepare('UPDATE barbers SET name=:name, headline=:headline, bio=:bio, specialties=:specialties,
                               phone=:phone, email=:email, display_order=:order, status=:status WHERE id=:id')->execute($data);
            } else {
                $data[':slug'] = slugify($name);
                db()->prepare('INSERT INTO barbers (name, slug, headline, bio, specialties, phone, email, display_order, status)
                               VALUES (:name, :slug, :headline, :bio, :specialties, :phone, :email, :order, :status)')->execute($data);
                $id = (int) db()->lastInsertId();

                // Horário padrão: segue a barbearia
                $ins = db()->prepare('INSERT INTO barber_hours (barber_id, weekday, is_working, start_time, end_time, break_start, break_end)
                                      SELECT :b, weekday, is_open, open_time, close_time, break_start, break_end FROM business_hours');
                $ins->execute([':b' => $id]);
            }

            // Serviços executados
            db()->prepare('DELETE FROM barber_services WHERE barber_id = :b')->execute([':b' => $id]);
            $link = db()->prepare('INSERT INTO barber_services (barber_id, service_id) VALUES (:b, :s)');
            foreach ((array) ($_POST['services'] ?? []) as $serviceId) {
                $link->execute([':b' => $id, ':s' => (int) $serviceId]);
            }

            flash('Barbeiro salvo.');
        }
    }

    if ($action === 'hours' && $id) {
        $upsert = db()->prepare(
            'INSERT INTO barber_hours (barber_id, weekday, is_working, start_time, end_time, break_start, break_end)
             VALUES (:b, :w, :working, :start, :end, :bs, :be)
             ON DUPLICATE KEY UPDATE is_working=VALUES(is_working), start_time=VALUES(start_time),
             end_time=VALUES(end_time), break_start=VALUES(break_start), break_end=VALUES(break_end)'
        );
        for ($w = 0; $w <= 6; $w++) {
            $working = isset($_POST['working'][$w]) ? 1 : 0;
            $upsert->execute([
                ':b' => $id, ':w' => $w, ':working' => $working,
                ':start' => $working ? ($_POST['start'][$w] ?: null) : null,
                ':end'   => $working ? ($_POST['end'][$w] ?: null) : null,
                ':bs'    => $working ? ($_POST['break_start'][$w] ?: null) : null,
                ':be'    => $working ? ($_POST['break_end'][$w] ?: null) : null,
            ]);
        }
        flash('Horário do barbeiro atualizado.');
    }

    if ($action === 'toggle' && $id) {
        db()->prepare('UPDATE barbers SET status = 1 - status WHERE id = :id')->execute([':id' => $id]);
        flash('Situação do barbeiro alterada.');
    }

    redirect(ADMIN_URL . '/barbeiros.php' . ($action === 'hours' ? '?horarios=' . $id : ''));
}

$barbers  = db()->query('SELECT * FROM barbers ORDER BY display_order, name')->fetchAll();
$services = db()->query('SELECT id, name FROM services WHERE status = 1 ORDER BY display_order, name')->fetchAll();

$barberServices = [];
foreach (db()->query('SELECT barber_id, service_id FROM barber_services') as $row) {
    $barberServices[(int) $row['barber_id']][] = (int) $row['service_id'];
}

$editHours = (int) ($_GET['horarios'] ?? 0);
$hoursRows = [];
if ($editHours) {
    $stmt = db()->prepare('SELECT * FROM barber_hours WHERE barber_id = :b');
    $stmt->execute([':b' => $editHours]);
    foreach ($stmt->fetchAll() as $row) { $hoursRows[(int) $row['weekday']] = $row; }
}

require __DIR__ . '/_layout.php';
?>

<div class="box">
  <div class="box__head">
    <h2><?= count($barbers) ?> barbeiros</h2>
    <?php if (can('barbers.manage')): ?>
      <button class="btn btn--primary btn--sm" type="button" data-modal-open="modal-barber"
              data-fill='<?= e(json_encode(['id'=>'','name'=>'','headline'=>'','bio'=>'','specialties'=>'','phone'=>'','email'=>'','display_order'=>0,'status'=>1,'services'=>[],'_title'=>'Novo barbeiro'])) ?>'>+ Novo barbeiro</button>
    <?php endif; ?>
  </div>

  <div class="box__body box__body--flush">
    <?php if (!$barbers): ?>
      <div class="empty"><strong>Nenhum barbeiro cadastrado</strong>Sem barbeiro ativo, o site não consegue oferecer horários.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Nome</th><th>Especialidades</th><th>Serviços</th><th>Contato</th><th>Situação</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($barbers as $b): $linked = $barberServices[(int) $b['id']] ?? []; ?>
              <tr>
                <td><strong><?= e($b['name']) ?></strong><br><span class="muted" style="font-size:.8rem"><?= e((string) $b['headline']) ?></span></td>
                <td class="muted"><?= e((string) $b['specialties']) ?></td>
                <td><?= count($linked) ?> serviço(s)</td>
                <td class="muted" style="font-size:.85rem"><?= e((string) $b['phone']) ?><br><?= e((string) $b['email']) ?></td>
                <td><span class="badge badge--<?= (int) $b['status'] ? 'on' : 'off' ?>"><?= (int) $b['status'] ? 'Ativo' : 'Inativo' ?></span></td>
                <td>
                  <?php if (can('barbers.manage')): ?>
                    <div class="actions">
                      <button class="btn btn--default btn--sm" type="button" data-modal-open="modal-barber"
                              data-fill='<?= e(json_encode([
                                'id'=>$b['id'],'name'=>$b['name'],'headline'=>$b['headline'],'bio'=>$b['bio'],
                                'specialties'=>$b['specialties'],'phone'=>$b['phone'],'email'=>$b['email'],
                                'display_order'=>$b['display_order'],'status'=>$b['status'],
                                'services'=>$linked,'_title'=>'Editar barbeiro',
                              ], JSON_UNESCAPED_UNICODE)) ?>'>Editar</button>
                      <a class="btn btn--default btn--sm" href="?horarios=<?= (int) $b['id'] ?>">Horários</a>
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="btn btn--default btn--sm"><?= (int) $b['status'] ? 'Desativar' : 'Ativar' ?></button></form>
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($editHours && can('barbers.manage')):
  $current = array_values(array_filter($barbers, fn($b) => (int) $b['id'] === $editHours))[0] ?? null; ?>
  <?php if ($current): ?>
  <div class="box">
    <div class="box__head">
      <h2>Horário de <?= e($current['name']) ?></h2>
      <a class="btn btn--default btn--sm" href="<?= e(ADMIN_URL) ?>/barbeiros.php">Fechar</a>
    </div>
    <div class="box__body">
      <p class="muted">O sistema usa a interseção entre este horário e o horário da barbearia. Dia desmarcado é folga fixa.</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="hours">
        <input type="hidden" name="id" value="<?= $editHours ?>">
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Dia</th><th>Trabalha</th><th>Início</th><th>Fim</th><th>Intervalo de</th><th>até</th></tr></thead>
            <tbody>
              <?php foreach ([1,2,3,4,5,6,0] as $w): $r = $hoursRows[$w] ?? []; ?>
                <tr>
                  <td><strong><?= e(weekday_name($w)) ?></strong></td>
                  <td><input type="checkbox" name="working[<?= $w ?>]" value="1"<?= !empty($r['is_working']) ? ' checked' : '' ?>></td>
                  <td><input type="time" name="start[<?= $w ?>]" value="<?= e(substr((string) ($r['start_time'] ?? ''), 0, 5)) ?>"></td>
                  <td><input type="time" name="end[<?= $w ?>]" value="<?= e(substr((string) ($r['end_time'] ?? ''), 0, 5)) ?>"></td>
                  <td><input type="time" name="break_start[<?= $w ?>]" value="<?= e(substr((string) ($r['break_start'] ?? ''), 0, 5)) ?>"></td>
                  <td><input type="time" name="break_end[<?= $w ?>]" value="<?= e(substr((string) ($r['break_end'] ?? ''), 0, 5)) ?>"></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button class="btn btn--primary" style="margin-top:16px" type="submit">Salvar horário</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
<?php endif; ?>

<?php if (can('barbers.manage')): ?>
<dialog class="modal" id="modal-barber">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="">
    <div class="modal__head"><h3 data-modal-title>Novo barbeiro</h3><button class="modal__close" type="button" data-modal-close aria-label="Fechar">&times;</button></div>
    <div class="modal__body">
      <div class="row">
        <div class="field"><label for="b-name">Nome</label><input id="b-name" name="name" required></div>
        <div class="field"><label for="b-headline">Chamada curta</label><input id="b-headline" name="headline" placeholder="Degradê e barba desenhada"></div>
      </div>
      <div class="field"><label for="b-bio">Biografia</label><textarea id="b-bio" name="bio"></textarea></div>
      <div class="field"><label for="b-spec">Especialidades</label><input id="b-spec" name="specialties" placeholder="Degradê, Barba, Corte clássico"><span class="field__hint">Separe por vírgula — cada item vira uma etiqueta no site.</span></div>
      <div class="row">
        <div class="field"><label for="b-phone">Telefone</label><input id="b-phone" name="phone"></div>
        <div class="field"><label for="b-email">E-mail</label><input id="b-email" name="email" type="email"></div>
        <div class="field"><label for="b-order">Ordem</label><input id="b-order" name="display_order" type="number" value="0"></div>
      </div>

      <div class="field">
        <label>Serviços que atende</label>
        <div class="check-grid">
          <?php foreach ($services as $s): ?>
            <label><input type="checkbox" name="services[]" value="<?= (int) $s['id'] ?>" data-multi="services"> <?= e($s['name']) ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field field--inline"><input id="b-status" name="status" type="checkbox" value="1" checked><label for="b-status">Ativo e disponível para agendamento</label></div>
    </div>
    <div class="modal__foot">
      <button class="btn btn--default" type="button" data-modal-close>Cancelar</button>
      <button class="btn btn--primary" type="submit">Salvar barbeiro</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
