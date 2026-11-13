<?php
/**
 * INSTALADOR — execute uma única vez e depois APAGUE este arquivo do servidor.
 *
 * O que ele faz:
 *   1. Executa database/schema.sql (cria as tabelas, apagando-as se já existirem)
 *   2. Executa database/seed.sql (dados de demonstração — opcional)
 *   3. Cria o primeiro usuário super_admin com a senha que você definir
 *
 * Antes de rodar, configure a conexão em config/database.php (ou variáveis
 * de ambiente DB_HOST, DB_NAME, DB_USER, DB_PASS).
 */
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$step   = $_POST['step'] ?? 'form';
$error  = null;
$done   = false;

function run_sql_file(PDO $pdo, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Não foi possível ler {$path}");
    }

    // Remove linhas de comentário ANTES de dividir em comandos — evita que um
    // comentário fique colado ao início do próximo comando (ex.: um bloco de
    // cabeçalho "-- ---" seguido de "DROP TABLE ...") e faça o comando
    // inteiro ser ignorado por engano.
    $lines = array_filter(
        explode("\n", $sql),
        static fn(string $line): bool => !preg_match('/^\s*--/', $line)
    );
    $clean = implode("\n", $lines);

    // Divide por ponto-e-vírgula — suficiente para os arquivos deste projeto
    // (sem procedures nem ponto-e-vírgula dentro de strings).
    foreach (array_filter(array_map('trim', explode(';', $clean))) as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'install') {
    if (!csrf_check()) {
        $error = 'Sessão expirada. Recarregue a página e tente novamente.';
    } else {
        $withSeed = isset($_POST['with_seed']);
        $name     = trim((string) ($_POST['admin_name'] ?? ''));
        $email    = trim((string) ($_POST['admin_email'] ?? ''));
        $password = (string) ($_POST['admin_password'] ?? '');

        if (mb_strlen($name) < 3 || !is_valid_email($email) || mb_strlen($password) < 8) {
            $error = 'Confira o nome, e-mail e senha (mínimo 8 caracteres) do administrador.';
        } else {
            try {
                $pdo = db();
                run_sql_file($pdo, BASE_PATH . '/database/schema.sql');
                if ($withSeed) {
                    run_sql_file($pdo, BASE_PATH . '/database/seed.sql');
                }
                $pdo->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (:n, :e, :p, \'super_admin\', 1)')
                    ->execute([':n' => $name, ':e' => $email, ':p' => password_hash($password, PASSWORD_DEFAULT)]);

                $done = true;
            } catch (Throwable $e) {
                $error = APP_DEBUG ? $e->getMessage() : 'Não foi possível concluir a instalação. Confira os dados de conexão em config/database.php.';
            }
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
<title>Instalação — Navalha &amp; Cia</title>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600&family=Karla:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(ASSETS_URL) ?>/css/admin.css">
</head>
<body class="login-page">
  <main class="login-card" style="width:min(560px,100%)">
    <div class="login-mark">NAVALHA <span>&amp;</span> CIA</div>
    <h1>Instalação do sistema</h1>

    <?php if ($done): ?>
      <div class="alert alert--success">Instalação concluída! O banco foi criado e o usuário administrador está pronto.</div>
      <p class="dim"><strong>Importante:</strong> apague o arquivo <code>install.php</code> do servidor agora — deixá-lo publicado permite recriar o banco a qualquer momento.</p>
      <a class="btn btn--primary" style="width:100%" href="<?= e(ADMIN_URL) ?>/login.php">Ir para o painel</a>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert--error"><?= e($error) ?></div><?php endif; ?>

      <div class="alert alert--info">
        Isso vai <strong>apagar e recriar</strong> todas as tabelas do banco configurado em
        <code>config/database.php</code>. Use apenas na primeira instalação.
      </div>

      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="step" value="install">

        <div class="field field--inline">
          <input id="seed" type="checkbox" name="with_seed" value="1" checked>
          <label for="seed">Instalar com dados de demonstração (serviços, barbeiros, agenda de exemplo)</label>
        </div>

        <h3 style="margin-top:22px">Seu usuário de administrador</h3>
        <div class="field"><label for="admin_name">Nome</label><input id="admin_name" name="admin_name" required></div>
        <div class="field"><label for="admin_email">E-mail</label><input id="admin_email" name="admin_email" type="email" required></div>
        <div class="field"><label for="admin_password">Senha (mínimo 8 caracteres)</label><input id="admin_password" name="admin_password" type="password" minlength="8" required></div>

        <button class="btn btn--primary" style="width:100%" type="submit">Instalar sistema</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
