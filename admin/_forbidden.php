<?php
$pageTitle = 'Acesso negado';
$activeMenu = '';
require __DIR__ . '/_layout.php';
?>
<div class="box">
  <div class="box__body">
    <div class="empty">
      <strong>Você não tem acesso a esta área</strong>
      Seu perfil é <?= e(role_label(current_user()['role'])) ?>. Peça a um super admin para ajustar suas permissões.
      <a class="btn btn--default" href="<?= e(ADMIN_URL) ?>/dashboard.php">Voltar ao dashboard</a>
    </div>
  </div>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
