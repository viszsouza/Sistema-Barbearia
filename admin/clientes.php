<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';
require_permission('customers.view');

$pageTitle  = 'Clientes';
$activeMenu = 'clientes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    require_permission('customers.manage');
    $action = (string) input('action');
    $id     = (int) input('id');

    if ($action === 'save') {
        $name  = (string) input('name');
        $phone = (string) input('phone');
        if (mb_strlen($name) < 3 || !is_valid_phone($phone)) {
            flash('Informe nome e telefone válidos.', 'error');
        } else {
            db()->prepare('UPDATE customers SET name=:n, phone=:p, email=:e, notes=:notes WHERE id=:id')->execute([
                ':n' => $name, ':p' => format_phone($phone),
                ':e' => (string) input('email') ?: null,
                ':notes' => (string) input('notes') ?: null,
                ':id' => $id,
            ]);
            flash('Cliente atualizado.');
        }
    }

    if ($action === 'toggle' && $id) {
        db()->prepare('UPDATE customers SET status = 1 - status WHERE id = :id')->execute([':id' => $id]);
        flash('Situação do cliente alterada.');
    }

    redirect(ADMIN_URL . '/clientes.php' . ($id && $action !== 'toggle' ? '?ver=' . $id : ''));
}

$search  = (string) input('busca', '', $_GET);
$page    = max(1, (int) input('p', 1, $_GET));
$perPage = 25;

$where  = '1=1';
$params = [];
if ($search !== '') {
    $where = '(name LIKE :q OR phone LIKE :q OR email LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}

$count = db()->prepare("SELECT COUNT(*) FROM customers WHERE {$where}");
$count->execute($params);
$total = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
$page  = min($page, $pages);

$stmt = db()->prepare(
    "SELECT c.*,
        (SELECT COUNT(*) FROM appointments a WHERE a.customer_id = c.id) AS total_appts,
        (SELECT MAX(appointment_date) FROM appointments a WHERE a.customer_id = c.id AND a.status = 'completed') AS last_visit,
        (SELECT COALESCE(SUM(price),0) FROM appointments a WHERE a.customer_id = c.id AND a.status = 'completed') AS total_spent
     FROM customers c
     WHERE {$where}
     ORDER BY c.name
     LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage)
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Perfil individual
$profile = null; $profileHistory = [];
if (!empty($_GET['ver'])) {
    $pstmt = db()->prepare('SELECT * FROM customers WHERE id = :id');
    $pstmt->execute([':id' => (int) $_GET['ver']]);
    $profile = $pstmt->fetch() ?: null;

    if ($profile) {
        $hstmt = db()->prepare(
            "SELECT a.*, b.name AS barber_name, s.name AS service_name
             FROM appointments a JOIN barbers b ON b.id=a.barber_id JOIN services s ON s.id=a.service_id
             WHERE a.customer_id = :id ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT 40"
        );
        $hstmt->execute([':id' => $profile['id']]);
        $profileHistory = $hstmt->fetchAll();
    }
}

require __DIR__ . '/_layout.php';
?>

<?php if ($profile): ?>
  <div class="box">
    <div class="box__head">
      <h2><?= e($profile['name']) ?></h2>
      <a class="btn btn--default btn--sm" href="<?= e(ADMIN_URL) ?>/clientes.php">Voltar à lista</a>
    </div>
    <div class="box__body">
      <?php if (can('customers.manage')): ?>
      <form method="post" class="row" style="margin-bottom:24px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int) $profile['id'] ?>">
        <div class="field"><label>Nome</label><input name="name" value="<?= e($profile['name']) ?>" required></div>
        <div class="field"><label>Telefone</label><input name="phone" value="<?= e($profile['phone']) ?>" required></div>
        <div class="field"><label>E-mail</label><input name="email" type="email" value="<?= e((string) $profile['email']) ?>"></div>
        <div class="field" style="grid-column:1/-1"><label>Observações internas</label><textarea name="notes"><?= e((string) $profile['notes']) ?></textarea></div>
        <div style="grid-column:1/-1"><button class="btn btn--primary btn--sm" type="submit">Salvar</button></div>
      </form>
      <?php endif; ?>

      <h3>Histórico de agendamentos</h3>
      <?php if (!$profileHistory): ?>
        <div class="empty">Nenhum agendamento registrado ainda.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Data</th><th>Serviço</th><th>Barbeiro</th><th class="num">Valor</th><th>Situação</th></tr></thead>
            <tbody>
              <?php foreach ($profileHistory as $a): ?>
                <tr>
                  <td><?= e(format_date($a['appointment_date'])) ?> <?= e(format_time($a['start_time'])) ?></td>
                  <td><?= e($a['service_name']) ?></td>
                  <td><?= e($a['barber_name']) ?></td>
                  <td class="num"><?= money((float) $a['price']) ?></td>
                  <td><span class="badge badge--<?= e($a['status']) ?>"><?= e(status_label($a['status'])) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>

  <div class="box">
    <div class="box__head">
      <h2><?= $total ?> clientes</h2>
      <form method="get" class="inline">
        <input name="busca" placeholder="Nome, telefone ou e-mail" value="<?= e($search) ?>" style="min-width:220px">
        <button class="btn btn--default btn--sm" type="submit">Buscar</button>
      </form>
    </div>
    <div class="box__body box__body--flush">
      <?php if (!$rows): ?>
        <div class="empty"><strong>Nenhum cliente encontrado</strong>Clientes são criados automaticamente ao agendar pelo site ou pelo painel.</div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="data">
            <thead><tr><th>Nome</th><th>Telefone</th><th>E-mail</th><th class="num">Agendamentos</th><th>Última visita</th><th class="num">Total gasto</th><th>Situação</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($rows as $c): ?>
                <tr>
                  <td><strong><?= e($c['name']) ?></strong></td>
                  <td><?= e($c['phone']) ?></td>
                  <td class="muted"><?= e((string) $c['email']) ?></td>
                  <td class="num"><?= (int) $c['total_appts'] ?></td>
                  <td class="muted"><?= $c['last_visit'] ? e(format_date($c['last_visit'])) : '—' ?></td>
                  <td class="num"><?= money((float) $c['total_spent']) ?></td>
                  <td><span class="badge badge--<?= (int) $c['status'] ? 'on' : 'off' ?>"><?= (int) $c['status'] ? 'Ativo' : 'Inativo' ?></span></td>
                  <td>
                    <div class="actions">
                      <a class="btn btn--default btn--sm" href="?ver=<?= (int) $c['id'] ?>">Ver</a>
                      <?php if (can('customers.manage')): ?>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn--default btn--sm"><?= (int) $c['status'] ? 'Desativar' : 'Ativar' ?></button></form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($pages > 1): ?>
          <div class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
              <a href="?<?= e(http_build_query(array_filter(['busca' => $search, 'p' => $i]))) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
