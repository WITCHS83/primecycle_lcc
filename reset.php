<?php
require_once __DIR__ . '/config.php';
if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }

function base_url(){
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $scheme.'://'.$host;
}
function ensure_reset_table(PDO $pdo){
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS password_resets (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      token VARCHAR(64) NOT NULL UNIQUE,
      expires_at DATETIME NOT NULL,
      created_at DATETIME NOT NULL,
      INDEX(user_id),
      INDEX(token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
}

// Tema antes do CSS
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Recuperar acesso — Lifecycle Canvas</title>
<script>(function(){try{var k='lifecycle.theme',s=localStorage.getItem(k);if(!s){s=(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)?'dark':'light';localStorage.setItem(k,s);}if(s==='dark'){document.documentElement.setAttribute('data-theme','dark');}}catch(e){}})();</script>
<link rel="stylesheet" href="/assets/style.css"/>
<link rel="stylesheet" href="/assets/style.append.css"/>
<link rel="stylesheet" href="/assets/modern.append.css"/>
<style>
  body.auth-body{ display:flex; min-height:100vh; align-items:center; justify-content:center; padding:24px; }
  .auth-card{ max-width:480px; width:100%; padding:22px; border-radius:14px; }
  .auth-card h1{ margin:0 0 6px; }
  .btn.primary{ background:#765475; color:#fff; border:none; }
  .btn.primary:hover{ background:#5b3c5c; }
  .grid-2{ display:grid; grid-template-columns: 1fr 1fr; gap:10px; }
  @media (max-width:520px){ .grid-2{ grid-template-columns: 1fr; } }
  :root[data-theme="dark"] body{ background:#0b1020; color:#e5e7eb; }
  :root[data-theme="dark"] .auth-card{ background:#0f172a; border:1px solid #1f2937; box-shadow:0 6px 20px rgba(0,0,0,.45); }
  :root[data-theme="dark"] input{ background:#0f172a; color:#e5e7eb; border-color:#1f2937; }
</style>
</head>
<body class="auth-body">
<div class="card auth-card">
<?php
require_once __DIR__ . '/mailer.php';

// Roteamento simples pelos parâmetros
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$token  = $_GET['token'] ?? '';

// Mensagens
$ok=''; $err='';

try {
  if ($method==='POST' && isset($_POST['request_reset'])) {
    // Solicitação de link por e-mail
    $email = trim($_POST['email'] ?? '');
    if ($email==='' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $err = 'Informe um e-mail válido.';
    } else {
      // Verifica usuário
      $st = $pdo->prepare("SELECT id, name FROM users WHERE email=? LIMIT 1");
      $st->execute([$email]);
      $u = $st->fetch(PDO::FETCH_ASSOC);
      if (!$u) {
        // Por segurança, não revela se existe. Diz que enviou.
        $ok = 'Se este e-mail existir, enviaremos um link de redefinição.';
      } else {
        ensure_reset_table($pdo);
        $uid   = (int)$u['id'];
        $tok   = bin2hex(random_bytes(32)); // 64 chars
        $exp   = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
        $now   = (new DateTime())->format('Y-m-d H:i:s');

        // Remove tokens antigos do mesmo usuário
        $pdo->prepare("DELETE FROM password_resets WHERE user_id=?")->execute([$uid]);
        $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, ?)")
            ->execute([$uid, $tok, $exp, $now]);

        $link = base_url() . '/reset.php?token=' . $tok;

        // Envia e-mail
        $smtp_enabled = app_get('smtp_enabled','0')==='1';
        $smtpConf = $smtp_enabled ? [
          'host'   => app_get('smtp_host',''),
          'port'   => (int)app_get('smtp_port','587'),
          'secure' => strtolower(app_get('smtp_secure','tls')),
          'user'   => app_get('smtp_user',''),
          'pass'   => app_get('smtp_pass',''),
        ] : null;

        $debug='';
        $okSend = send_mail([
          'to'         => [$email => ($u['name'] ?: 'Usuário')],
          'subject'    => 'Redefinição de senha — Lifecycle Canvas',
          'html'       => '<p>Olá,</p><p>Para redefinir sua senha, clique no link abaixo (válido por 1 hora):</p><p><a href="'.h($link).'">'.h($link).'</a></p>',
          'text'       => "Olá,\nPara redefinir sua senha, use o link (válido por 1 hora):\n{$link}\n",
          'from_email' => app_get('smtp_from_email','no-reply@seu-dominio.com'),
          'from_name'  => app_get('smtp_from_name','Lifecycle Canvas'),
          'smtp'       => $smtpConf
        ], $debug);

        // Não expõe debug aqui por segurança; se quiser, logue em arquivo.
        $ok = 'Se este e-mail existir, enviaremos um link de redefinição.';
      }
    }
  } elseif ($method==='POST' && isset($_POST['reset_with_token'])) {
    // Definir nova senha a partir do token
    $token = $_POST['token'] ?? '';
    $p1 = $_POST['password'] ?? ''; $p2 = $_POST['password2'] ?? '';
    if ($token==='' || $p1==='' || $p2==='') { $err='Preencha todos os campos.'; }
    elseif ($p1!==$p2) { $err='Senhas não conferem.'; }
    elseif (strlen($p1) < 6) { $err='Senha deve ter ao menos 6 caracteres.'; }
    else {
      // Valida token
      $st = $pdo->prepare("SELECT pr.user_id FROM password_resets pr WHERE pr.token=? AND pr.expires_at > NOW() LIMIT 1");
      $st->execute([$token]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
      if (!$row) { $err='Link inválido ou expirado.'; }
      else {
        $uid = (int)$row['user_id'];
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash,$uid]);
        $pdo->prepare("DELETE FROM password_resets WHERE token=?")->execute([$token]);
        $ok = 'Senha redefinida com sucesso! Você já pode entrar.';
      }
    }
  }
} catch (Throwable $e) {
  $err = 'Erro: '.h($e->getMessage());
}
?>

  <h1><strong>Recuperar</strong> acesso</h1>
  <p class="muted" style="margin-top:-6px;margin-bottom:16px">Informe seu e-mail para receber um link de redefinição.</p>

  <?php if ($ok): ?><div class="alert ok"><?= $ok ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert err"><?= $err ?></div><?php endif; ?>

  <?php if (!$token): ?>
    <!-- Formulário de solicitação -->
    <form class="form" method="post" action="/reset.php">
      <input type="hidden" name="request_reset" value="1">
      <label>E-mail
        <input type="email" name="email" required placeholder="voce@empresa.com.br"/>
      </label>
      <div class="right"><button class="btn primary">Enviar link</button></div>
    </form>

    <hr style="border:0;border-top:1px solid #eee;margin:16px 0">
    <div class="grid-2">
      <a class="button ghost" href="/index.php">Voltar</a>
      <a class="button primary" href="/index.php">Entrar</a>
    </div>

  <?php else: ?>
    <!-- Formulário de redefinição com token -->
    <h2 style="margin-top:12px">Definir nova senha</h2>
    <form class="form" method="post" action="/reset.php?token=<?= h($token) ?>">
      <input type="hidden" name="reset_with_token" value="1">
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <label>Nova senha
        <input type="password" name="password" required placeholder="••••••••"/>
      </label>
      <label>Confirmar nova senha
        <input type="password" name="password2" required placeholder="••••••••"/>
      </label>
      <div class="right"><button class="btn primary">Redefinir senha</button></div>
    </form>
    <hr style="border:0;border-top:1px solid #eee;margin:16px 0">
    <div class="grid-2">
      <a class="button ghost" href="/index.php">Voltar</a>
      <a class="button primary" href="/index.php">Entrar</a>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
