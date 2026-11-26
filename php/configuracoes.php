<?php
session_start();
require 'functions.php';
verificar_sessao();

$dados = ler_json('db.json');
if (!isset($dados['smtp'])) {
  $dados['smtp'] = [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'user' => 'rubenmassuquetto1999@gmail.com',
    'pass' => '',
    'from' => 'rubenmassuquetto1999@gmail.com',
  ];
}

$msg = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $acao = $_POST['acao'] ?? '';

  if ($acao === 'salvar_smtp') {
    $dados['smtp'] = [
      'host' => trim($_POST['host'] ?? ''),
      'port' => intval($_POST['port'] ?? 587),
      'user' => trim($_POST['user'] ?? ''),
      'pass' => trim($_POST['pass'] ?? ''),
      'from' => trim($_POST['from'] ?? ''),
    ];
    salvar_json('db.json', $dados);
    $msg = 'Configurações de SMTP salvas com sucesso!';
  } elseif ($acao === 'testar_email') {
    $para = $dados['smtp']['user'] ?: 'rubenmassuquetto1999@gmail.com';
    $assunto = '[MKT Massuquetto OS] — Teste de Envio SMTP';
    $mensagem = "Olá Ruben,\n\nEste é um e-mail de teste enviado pelo sistema MKT Massuquetto OS.\nSe você recebeu esta mensagem, o servidor SMTP está configurado corretamente!\n\nAtenciosamente,\nMKT Massuquetto OS";
    
    $enviado = enviar_email_smtp($para, $assunto, $mensagem);
    if ($enviado) {
      $msg = 'E-mail de teste enviado com sucesso para ' . htmlspecialchars($para) . '! Verifique sua caixa de entrada ou spam.';
    } else {
      $erro = 'Falha ao enviar e-mail via SMTP. Verifique as credenciais, porta ou senha de app (App Password).';
    }
  }
}

$smtp = $dados['smtp'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MKT Massuquetto — Configurações &amp; SMTP</title>
<link rel="icon" type="image/png" href="<?= htmlspecialchars(LOGO_URL) ?>">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">Configurações de E-mail (SMTP)</h1>
      <span class="page-sub">Configure o servidor SMTP (Gmail, Hostinger, Outlook) para envio real de e-mails e recuperação de senha</span>
    </div>
  </div>

  <?php if($msg): ?><div class="alert alert-ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if($erro): ?><div class="alert alert-err"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:28px;">
    <div class="card-header">
      <h2 class="card-title">⚙️ Servidor SMTP para Disparo Real</h2>
    </div>
    <form method="POST" action="configuracoes.php" class="form-grid">
      <input type="hidden" name="acao" value="salvar_smtp">
      <div class="form-group">
        <label>Servidor SMTP (Host)</label>
        <input type="text" name="host" required value="<?= htmlspecialchars($smtp['host'] ?? 'smtp.gmail.com') ?>" placeholder="ex: smtp.gmail.com">
      </div>
      <div class="form-group">
        <label>Porta SMTP</label>
        <input type="number" name="port" required value="<?= intval($smtp['port'] ?? 587) ?>" placeholder="587 ou 465">
      </div>
      <div class="form-group">
        <label>E-mail / Usuário SMTP</label>
        <input type="email" name="user" required value="<?= htmlspecialchars($smtp['user'] ?? 'rubenmassuquetto1999@gmail.com') ?>" placeholder="seu@email.com">
      </div>
      <div class="form-group">
        <label>Senha de App (App Password)</label>
        <input type="password" name="pass" value="<?= htmlspecialchars($smtp['pass'] ?? '') ?>" placeholder="Senha de aplicativo do e-mail">
      </div>
      <div class="form-group form-span-2">
        <label>E-mail Remetente (From)</label>
        <input type="email" name="from" required value="<?= htmlspecialchars($smtp['from'] ?? 'rubenmassuquetto1999@gmail.com') ?>" placeholder="no-reply@sualoja.com">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 Salvar Configurações SMTP</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title">📨 Testar Envio de E-mail</h2>
    </div>
    <div class="card-body">
      <p style="color:var(--text-muted);margin-bottom:16px;font-size:.9rem;">
        Clique abaixo para enviar um e-mail de teste para <strong><?= htmlspecialchars($smtp['user'] ?: 'rubenmassuquetto1999@gmail.com') ?></strong> usando as configurações acima.
      </p>
      <form method="POST" action="configuracoes.php">
        <input type="hidden" name="acao" value="testar_email">
        <button type="submit" class="btn btn-accent">🚀 Enviar E-mail de Teste Agora</button>
      </form>
    </div>
  </div>
</div>
<div class="dev-sign">Desenvolvido por: Ruben Massuquetto</div>
</body>
</html>
