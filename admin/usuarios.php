<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/availability.php';
require_permission('users.manage');

$pageTitle  = 'Usuários';
$activeMenu = 'usuarios';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_guard();
    $action = (string) input('action');
    $id     = (int) input('id');

    if ($action === 'save') {
        $name  = (string) input('name');
        $email = (string) input('email');
        $role  = (string) input('role');
        $pass  = (string) input('password');

        if (mb_strlen($name) < 3 || !is_valid_email($email) || !array_key_exists($role, permission_map())) {
            flash('Confira nome, e-mail e papel informados.', 'error');
        } elseif (!$id && mb_strlen($pass) < 8) {
            flash('A senha precisa de pelo menos 8 caracteres.', 'error');
        } else {
            if ($id) {
                if ((int) $id === (int) current_user()['id'] && $role !== 'super_admin') {
                    flash('Você não pode remover seu próprio acesso de super admin.', 'error');
                    redirect(ADMIN_URL . '/usuarios.php');
                }
                $sql = 'UPDATE users SET name=:n, email=:e, role=:r, status=:s WHERE id=:id';
                $params = [':n' => $name, ':e' => $email, ':r' => $role, ':s' => isset($_POST['status']) ? 1 : 0, ':id' => $id];
                if ($pass !== '') {
                    $sql = 'UPDATE users SET name=:n, email=:e, role=:r, status=:s, password_hash=:p WHERE id=:id';
                    $params[':p'] = password_hash($pass, PASSWORD_DEFAULT);
                }
                db()->prepare($sql)->execute($params);
                flash('Usuário atualizado.');
            } else {
                $exists = db()->prepare('SELECT 1 FROM users WHERE email = :e');
                $exists->execute([':e' => $email]);
                if ($exists->fetchColumn()) {
                    flash('Já existe um usuário com este e-mail.', 'error');
                } else {
                    db()->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (:n, :e, :p, :r, 1)')
                        ->execute([':n' => $name, ':e' => $email, ':p' => password_hash($pass, PASSWORD_DEFAULT), ':r' => $role]);
                    flash('Usuário criado.');
                }
            }
        }
    }

    if ($action === 'toggle' && $id) {
        if ($id === (int) current_user()['id']) {
            flash('Você não pode desativar seu próprio usuário.', 'error');
        } else {
            db()->prepare('UPDATE users SET status = 1 - status WHERE id = :id')->execute([':id' => $id]);
            flash('Situação do usuário alterada.');
        }
    }

    if ($action === 'delete' && $id) {
        if ($id === (int) current_user()['id']) {
            flash('Você não pode excluir seu próprio usuário.', 'error');
        } else {
            db()->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);
            flash('Usuário excluído.');
        }
    }

    redirect(ADMIN_URL . '/usuarios.php');
}

$users = db()->query('SELECT * FROM users ORDER BY name')->fetchAll();

require __DIR__ . '/_layout.php';
?>

<div class="box">
  <div class="box__head">
    <h2><?= count($users) ?> usuários administrativos</h2>
    <button class="btn btn--primary btn--sm" type="button" data-modal-open="modal-user"
            data-fill='<?= e(json_encode(['id'=>'','name'=>'','email'=>'','role'=>'receptionist','status'=>1,'_title'=>'Novo usuário'])) ?>'>+ Novo usuário</button>
  </div>
  <div class="box__body box__body--flush">
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Nome</th><th>E-mail</th><th>Papel</th><th>Último acesso</th><th>Situação</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><strong><?= e($u['name']) ?></strong><?= (int) $u['id'] === (int) current_user()['id'] ? ' <span class="muted">(você)</span>' : '' ?></td>
              <td><?= e($u['email']) ?></td>
              <td><span class="badge badge--role"><?= e(role_label($u['role'])) ?></span></td>
              <td class="muted"><?= $u['last_login_at'] ? e(format_date($u['last_login_at'])) : 'Nunca acessou' ?></td>
              <td><span class="badge badge--<?= (int) $u['status'] ? 'on' : 'off' ?>"><?= (int) $u['status'] ? 'Ativo' : 'Inativo' ?></span></td>
              <td>
                <div class="actions">
                  <button class="btn btn--default btn--sm" type="button" data-modal-open="modal-user"
                          data-fill='<?= e(json_encode(['id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'role'=>$u['role'],'status'=>$u['status'],'_title'=>'Editar usuário'])) ?>'>Editar</button>
                  <?php if ((int) $u['id'] !== (int) current_user()['id']): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn--default btn--sm"><?= (int) $u['status'] ? 'Desativar' : 'Ativar' ?></button></form>
                    <form method="post" data-confirm="Excluir o usuário &quot;<?= e($u['name']) ?>&quot;?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><button class="btn btn--danger btn--sm">Excluir</button></form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<dialog class="modal" id="modal-user">
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="">
    <div class="modal__head"><h3 data-modal-title>Novo usuário</h3><button class="modal__close" type="button" data-modal-close aria-label="Fechar">&times;</button></div>
    <div class="modal__body">
      <div class="field"><label for="u-name">Nome</label><input id="u-name" name="name" required></div>
      <div class="field"><label for="u-email">E-mail</label><input id="u-email" name="email" type="email" required></div>
      <div class="field">
        <label for="u-role">Papel</label>
        <select id="u-role" name="role">
          <option value="receptionist">Recepcionista — agenda e clientes</option>
          <option value="admin">Administrador — tudo, exceto usuários</option>
          <option value="super_admin">Super admin — acesso completo</option>
        </select>
      </div>
      <div class="field"><label for="u-pass">Senha</label><input id="u-pass" name="password" type="password" autocomplete="new-password"><span class="field__hint">Deixe em branco para manter a senha atual ao editar.</span></div>
      <div class="field field--inline"><input id="u-status" name="status" type="checkbox" value="1" checked><label for="u-status">Ativo</label></div>
    </div>
    <div class="modal__foot">
      <button class="btn btn--default" type="button" data-modal-close>Cancelar</button>
      <button class="btn btn--primary" type="submit">Salvar usuário</button>
    </div>
  </form>
</dialog>

<?php require __DIR__ . '/_layout_end.php'; ?>
