<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';
require_permission('services.view');

$pageTitle  = 'Serviços';
$activeMenu = 'servicos';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    require_permission('services.manage');
    $action = (string) input('action');
    $id     = (int) input('id');

    if ($action === 'save') {
        $name  = (string) input('name');
        $price = (float) str_replace(',', '.', (string) input('price', '0'));
        $dur   = (int) input('duration_min');

        if (mb_strlen($name) < 2 || $price < 0 || $dur < 5) {
            flash('Confira nome, preço e duração (mínimo 5 minutos).', 'error');
        } else {
            $fields = [
                ':name'        => $name,
                ':slug'        => slugify($name) . ($id ? '' : '-' . substr((string) time(), -4)),
                ':description' => (string) input('description') ?: null,
                ':price'       => $price,
                ':duration'    => $dur,
                ':category'    => (int) input('category_id') ?: null,
                ':order'       => (int) input('display_order'),
                ':status'      => isset($_POST['status']) ? 1 : 0,
            ];
            if ($id) {
                unset($fields[':slug']);
                $fields[':id'] = $id;
                db()->prepare('UPDATE services SET name=:name, description=:description, price=:price,
                               duration_min=:duration, category_id=:category, display_order=:order, status=:status
                               WHERE id=:id')->execute($fields);
                flash('Serviço atualizado.');
            } else {
                db()->prepare('INSERT INTO services (name, slug, description, price, duration_min, category_id, display_order, status)
                               VALUES (:name, :slug, :description, :price, :duration, :category, :order, :status)')->execute($fields);
                flash('Serviço criado.');
            }
        }
    }

    if ($action === 'duplicate' && $id) {
        db()->prepare("INSERT INTO services (category_id, name, slug, description, price, duration_min, display_order, status)
                       SELECT category_id, CONCAT(name, ' (cópia)'), CONCAT(slug, '-copia-', :suffix), description, price, duration_min, display_order, 0
                       FROM services WHERE id = :id")
            ->execute([':suffix' => substr((string) time(), -5), ':id' => $id]);
        flash('Serviço duplicado como rascunho (desativado).');
    }

    if ($action === 'toggle' && $id) {
        db()->prepare('UPDATE services SET status = 1 - status WHERE id = :id')->execute([':id' => $id]);
        flash('Situação do serviço alterada.');
    }

    if ($action === 'delete' && $id) {
        $used = db()->prepare('SELECT COUNT(*) FROM appointments WHERE service_id = :id');
        $used->execute([':id' => $id]);
        if ((int) $used->fetchColumn() > 0) {
            db()->prepare('UPDATE services SET status = 0 WHERE id = :id')->execute([':id' => $id]);
            flash('Este serviço já tem agendamentos, então foi desativado em vez de excluído.', 'warning');
        } else {
            db()->prepare('DELETE FROM services WHERE id = :id')->execute([':id' => $id]);
            flash('Serviço excluído.');
        }
    }

    redirect(ADMIN_URL . '/servicos.php');
}

$services = db()->query(
    'SELECT s.*, c.name AS category_name FROM services s
     LEFT JOIN service_categories c ON c.id = s.category_id
     ORDER BY s.display_order, s.name'
)->fetchAll();
$categories = db()->query('SELECT id, name FROM service_categories ORDER BY display_order')->fetchAll();

require __DIR__ . '/_layout.php';
?>

<div class="box">
  <div class="box__head">
    <h2><?= count($services) ?> serviços cadastrados</h2>
    <?php if (can('services.manage')): ?>
      <button class="btn btn--primary btn--sm" type="button" data-modal-open="modal-service"
              data-fill='<?= e(json_encode(['id'=>'','name'=>'','description'=>'','price'=>'','duration_min'=>30,'category_id'=>'','display_order'=>0,'status'=>1,'_title'=>'Novo serviço'])) ?>'>+ Novo serviço</button>
    <?php endif; ?>
  </div>

  <div class="box__body box__body--flush">
    <?php if (!$services): ?>
      <div class="empty"><strong>Nenhum serviço cadastrado</strong>Crie o primeiro serviço para que ele apareça no site.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Nome</th><th>Categoria</th><th class="num">Preço</th><th class="num">Duração</th><th class="num">Ordem</th><th>Situação</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($services as $s): ?>
              <tr>
                <td><strong><?= e($s['name']) ?></strong><br><span class="muted" style="font-size:.8rem"><?= e(mb_substr((string) $s['description'], 0, 70)) ?></span></td>
                <td class="muted"><?= e((string) $s['category_name']) ?></td>
                <td class="num"><?= money((float) $s['price']) ?></td>
                <td class="num"><?= e(format_duration((int) $s['duration_min'])) ?></td>
                <td class="num"><?= (int) $s['display_order'] ?></td>
                <td><span class="badge badge--<?= (int) $s['status'] ? 'on' : 'off' ?>"><?= (int) $s['status'] ? 'Ativo' : 'Inativo' ?></span></td>
                <td>
                  <?php if (can('services.manage')): ?>
                    <div class="actions">
                      <button class="btn btn--default btn--sm" type="button" data-modal-open="modal-service"
                              data-fill='<?= e(json_encode([
                                  'id' => $s['id'], 'name' => $s['name'], 'description' => $s['description'],
                                  'price' => $s['price'], 'duration_min' => $s['duration_min'],
                                  'category_id' => $s['category_id'], 'display_order' => $s['display_order'],
                                  'status' => $s['status'], '_title' => 'Editar serviço',
                              ], JSON_UNESCAPED_UNICODE)) ?>'>Editar</button>
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn--default btn--sm">Duplicar</button></form>
                      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn--default btn--sm"><?= (int) $s['status'] ? 'Desativar' : 'Ativar' ?></button></form>
                      <form method="post" data-confirm="Excluir o serviço &quot;<?= e($s['name']) ?>&quot;?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn--danger btn--sm">Excluir</button></form>
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

<?php if (can('services.manage')): ?>
<dialog class="modal" id="modal-service">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="">
    <div class="modal__head"><h3 data-modal-title>Novo serviço</h3><button class="modal__close" type="button" data-modal-close aria-label="Fechar">&times;</button></div>
    <div class="modal__body">
      <div class="field"><label for="s-name">Nome</label><input id="s-name" name="name" required></div>
      <div class="field"><label for="s-desc">Descrição</label><textarea id="s-desc" name="description"></textarea></div>
      <div class="row">
        <div class="field"><label for="s-price">Preço (R$)</label><input id="s-price" name="price" inputmode="decimal" required></div>
        <div class="field"><label for="s-dur">Duração (minutos)</label><input id="s-dur" name="duration_min" type="number" min="5" step="5" required></div>
      </div>
      <div class="row">
        <div class="field">
          <label for="s-cat">Categoria</label>
          <select id="s-cat" name="category_id"><option value="">Sem categoria</option>
            <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label for="s-order">Ordem de exibição</label><input id="s-order" name="display_order" type="number" value="0"></div>
      </div>
      <div class="field field--inline"><input id="s-status" name="status" type="checkbox" value="1" checked><label for="s-status">Publicar no site</label></div>
      <p class="field__hint">A duração define quantos minutos o horário fica bloqueado na agenda do barbeiro.</p>
    </div>
    <div class="modal__foot">
      <button class="btn btn--default" type="button" data-modal-close>Cancelar</button>
      <button class="btn btn--primary" type="submit">Salvar serviço</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<?php require __DIR__ . '/_layout_end.php'; ?>
