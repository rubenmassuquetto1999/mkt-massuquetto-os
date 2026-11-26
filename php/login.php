<?php
session_start();
if (!empty($_SESSION['logado'])) {
  header('Location: index.php');
  exit;
}

require_once 'functions.php';
$logo = LOGO_URL;
$erro = '';
$sucesso = '';
$acao = $_GET['acao'] ?? 'login';

$dados_db = ler_json('db.json');
if (!isset($dados_db['admin_auth'])) {
  $dados_db['admin_auth'] = ['email' => 'rubenmassuquetto1999@gmail.com', 'senha' => '123456'];
  salvar_json('db.json', $dados_db);
}
$auth = $dados_db['admin_auth'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $form_tipo = $_POST['form_tipo'] ?? 'login';
  $email_input = strtolower(trim($_POST['email'] ?? ''));

  if ($form_tipo === 'login') {
    $senha = trim($_POST['senha'] ?? '');
    
    if ($email_input === strtolower($auth['email']) && $senha === $auth['senha']) {
      $_SESSION['logado'] = true;
      $_SESSION['usuario'] = 'Ruben Massuquetto';
      session_write_close();
      header('Location: index.php');
      exit;
    } else {
      $erro = 'E-mail ou senha incorretos.';
    }
  } elseif ($form_tipo === 'recuperar') {
    if ($email_input === strtolower($auth['email'])) {
      $assunto = 'Solicitação de Acesso / Recuperação — Massuquetto OS';
      $mensagem = "Olá Ruben,\n\nRecebemos uma solicitação de recuperação de acesso para o Massuquetto OS.\nSua senha atual de acesso é: {$auth['senha']}\n\nAtenciosamente,\nMKT Massuquetto OS";
      $cabecalhos = "From: no-reply@mktmassuquetto.com\r\nReply-To: rubenmassuquetto1999@gmail.com\r\n";
      
      @mail($auth['email'], $assunto, $mensagem, $cabecalhos);
      
      $sucesso = 'Requisição de acesso enviada com sucesso para o e-mail cadastrado. Verifique sua caixa de entrada.';
    } else {
      $erro = 'E-mail não encontrado na base de dados.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MKT Massuquetto — Acesso ao Sistema</title>
<link rel="icon" type="image/png" href="<?= htmlspecialchars($logo) ?>">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
html,body{height:100%;font-family:'Segoe UI',system-ui,sans-serif;}
body{display:flex;min-height:100vh;background:#1E1E1E;color:#E8E8E8;}
.col-left{
  flex:1;min-width:0;
  background:radial-gradient(circle at 30% 30%, rgba(108,58,227,.32) 0%, #19122b 52%, #1E1E1E 100%);
  border-right:1px solid #2e2e2e;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:48px 36px;text-align:center;
  position:relative;overflow:hidden;
}
.col-left::after{
  content:'';position:absolute;width:340px;height:340px;border-radius:50%;
  background:radial-gradient(circle, rgba(0,231,193,.1) 0%, transparent 70%);
  bottom:-120px;right:-90px;pointer-events:none;
}
.login-logo{
  max-width:240px;max-height:110px;width:auto;height:auto;object-fit:contain;display:block;
  margin-bottom:18px;
  filter:drop-shadow(0 10px 28px rgba(0,0,0,.5));
}
.col-left .brand-tagline{
  font-size:.95rem;color:rgba(255,255,255,.72);font-weight:500;letter-spacing:.3px;
}
.col-right{
  flex:1;min-width:0;
  background:#1E1E1E;
  display:flex;align-items:center;justify-content:center;
  padding:48px 40px;
}
.login-box{
  width:100%;max-width:390px;
  background:#2D2D2D;border:1px solid #3a3a3a;border-radius:16px;
  padding:34px 30px;box-shadow:0 14px 40px rgba(0,0,0,.38);
}
.login-box-head{display:flex;align-items:center;gap:12px;margin-bottom:18px;}
.login-box-head img{width:42px;height:42px;object-fit:contain;border-radius:10px;background:#1E1E1E;padding:5px;border:1px solid #3a3a3a;}
.login-box h2{font-size:1.45rem;font-weight:800;color:#fff;line-height:1.2;}
.login-box p{font-size:.86rem;color:#9A9A9A;margin-top:2px;}
.form-group{margin-bottom:18px;}
.form-group label{display:block;font-size:.76rem;color:#b0b0b0;margin-bottom:7px;text-transform:uppercase;letter-spacing:.6px;font-weight:600;}
.form-group input{
  width:100%;padding:12px 15px;
  background:#1E1E1E;border:1px solid #3f3f3f;
  border-radius:10px;color:#fff;font-size:.95rem;
  transition:border .2s, box-shadow .2s;
}
.form-group input:focus{outline:none;border-color:#6C3AE3;box-shadow:0 0 0 3px rgba(108,58,227,.22);}
.btn-login{
  width:100%;padding:13px;
  background:#00E7C1;color:#1E1E1E;
  border:none;border-radius:10px;
  font-size:1rem;font-weight:800;
  cursor:pointer;transition:opacity .2s, transform .1s;
  margin-top:4px;
}
.btn-login:hover{opacity:.9;transform:translateY(-1px);}
.erro{background:rgba(255,91,91,.14);border:1px solid rgba(255,91,91,.4);color:#ff7b7b;padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:16px;}
.sucesso{background:rgba(0,231,193,.14);border:1px solid rgba(0,231,193,.4);color:#00E7C1;padding:10px 14px;border-radius:8px;font-size:.85rem;margin-bottom:16px;line-height:1.4;}
.login-links{display:flex;justify-content:flex-end;align-items:center;margin-top:12px;font-size:.82ns;font-size:.82rem;}
.login-links a{color:#00E7C1;text-decoration:none;}
.login-links a:hover{text-decoration:underline;}
.dev{text-align:center;margin-top:22px;font-size:.72rem;color:#666;}
@media(max-width:860px){
  body{flex-direction:column;}
  .col-left{min-height:auto;flex:none;padding:28px 20px;border-right:none;border-bottom:1px solid #2e2e2e;}
  .login-logo{max-width:190px;max-height:80px;margin-bottom:10px;}
  .col-right{flex:1;padding:28px 18px;}
}
</style>
</head>
<body>
<div class="col-left">
  <img src="<?= htmlspecialchars($logo) ?>" alt="Logo MKT Massuquetto" class="login-logo">
  <p class="brand-tagline">Gestão de Agência &amp; CRM</p>
</div>

<div class="col-right">
  <div class="login-box">
    <div class="login-box-head">
      <img src="<?= htmlspecialchars($logo) ?>" alt="MKT Massuquetto">
      <div>
        <h2><?= $acao === 'esqueceu' ? 'Recuperar Acesso' : 'Entrar no Painel' ?></h2>
        <p><?= $acao === 'esqueceu' ? 'Informe seu e-mail cadastrado' : 'Acesso administrativo MKT Massuquetto' ?></p>
      </div>
    </div>

    <?php if($erro): ?>
    <div class="erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if($sucesso): ?>
    <div class="sucesso">✅ <?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>

    <?php if($acao === 'esqueceu'): ?>
    <form method="POST" action="login.php?acao=esqueceu">
      <input type="hidden" name="form_tipo" value="recuperar">
      <div class="form-group">
        <label>E-mail</label>
        <input type="email" name="email" placeholder="seu@email.com" required autocomplete="email">
      </div>
      <button type="submit" class="btn-login">Enviar Requisição de Acesso</button>
      <div class="login-links" style="margin-top:16px;justify-content:center;">
        <a href="login.php">← Voltar para o Login</a>
      </div>
    </form>

    <?php else: ?>
    <form method="POST" action="login.php">
      <input type="hidden" name="form_tipo" value="login">
      <div class="form-group">
        <label>E-mail de acesso</label>
        <input type="email" name="email" placeholder="seu@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autocomplete="username">
      </div>
      <div class="form-group">
        <label>Senha</label>
        <input type="password" name="senha" placeholder="••••••" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn-login">Entrar na MKT Massuquetto</button>
      <div class="login-links">
        <a href="login.php?acao=esqueceu">Esqueceu a senha?</a>
      </div>
    </form>
    <?php endif; ?>

    <div class="dev">MKT Massuquetto • Desenvolvido por Ruben Massuquetto</div>
  </div>
</div>
</body>
</html>
