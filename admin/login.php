<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/auth.php';

if (is_logged_in()) {
    redirect(ADMIN_URL . '/dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = 'Sessão expirada. Tente novamente.';
    } elseif (!throttle('admin_login', 5, 600)) {
        $error = 'Muitas tentativas seguidas. Aguarde 10 minutos.';
    } else {
        $email    = (string) input('email', '');
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $error = 'Informe e-mail e senha.';
        } elseif (attempt_login($email, $password)) {
            throttle_clear('admin_login');
            $target = $_SESSION['redirect_after_login'] ?? null;
            unset($_SESSION['redirect_after_login']);
            redirect($target ?: ADMIN_URL . '/dashboard.php');
        } else {
            $error = 'E-mail ou senha incorretos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Entrar no painel — <?= e((string) setting('business_name', APP_NAME)) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600&family=Karla:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/css/admin.css">
</head>
<body class="login-page">
  <main class="login-card">
    <div class="login-mark">NAVALHA <span>&amp;</span> CIA</div>
    <h1>Entrar no painel</h1>
    <p class="dim">Área restrita à equipe da barbearia.</p>

    <?php if ($error): ?>
      <div class="alert alert--error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" autocomplete="username" required autofocus
               value="<?= e((string) ($_POST['email'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="password">Senha</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
      </div>
      <button class="btn btn--primary" style="width:100%" type="submit">Entrar</button>
    </form>

    <p class="dim" style="margin:22px 0 0;text-align:center;font-size:.86rem">
      <a href="<?= e(PUBLIC_URL) ?>/index.php">Voltar ao site</a>
    </p>
  </main>
</body>
</html>
