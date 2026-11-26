<?php
session_start();
require 'functions.php';

$dados = ler_json('db.json');

// Gera token determinístico simples para compartilhamento de link do cliente sem expor painel admin
function token_cliente(string $cliente): string {
  return substr(sha1('mktmassuquetto_' . strtolower(trim($cliente))), 0, 12);
}

$clientes_perf = array_column($dados['performance'] ?? [], 'cliente');
$clientes_proj = array_column($dados['projetos'] ?? [], 'cliente');
$clientes_fin  = array_column($dados['financeiro'] ?? [], 'cliente');
$clientes_lead = array_column($dados['leads'] ?? [], 'empresa');
$todos_clientes = array_values(array_filter(array_unique(array_merge($clientes_perf, $clientes_proj, $clientes_fin, $clientes_lead))));
sort($todos_clientes);

$cliente_sel = trim($_GET['cliente'] ?? ($_POST['cliente'] ?? ($todos_clientes[0] ?? '')));
$token_req   = trim($_GET['token'] ?? ($_POST['token'] ?? ''));

$is_admin = !empty($_SESSION['logado']);
$token_valido = ($cliente_sel !== '' && $token_req !== '' && hash_equals(token_cliente($cliente_sel), $token_req));

if (!$is_admin && !$token_valido) {
  header('Location: login.php');
  exit;
}

// ACOES NO PORTAL: Aprovar protótipo ou atualizar status comercial de lead
$msg_portal = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $acao = $_POST['acao_portal'] ?? '';
  if ($acao === 'aprovar_projeto') {
    $proj_id = trim($_POST['proj_id'] ?? '');
    $aprovacao = trim($_POST['aprovacao_cliente'] ?? 'Em Análise');
    $comentario = trim($_POST['comentarios_cliente'] ?? '');
    foreach ($dados['projetos'] as &$p) {
      if ($p['id'] === $proj_id) {
        $p['aprovacao_cliente'] = $aprovacao;
        if ($comentario !== '') {
          $p['comentarios_cliente'] = $comentario;
        }
        break;
      }
    }
    unset($p);
    salvar_json('db.json', $dados);
    $redir = 'portal.php?cliente=' . urlencode($cliente_sel) . ($token_req ? '&token=' . urlencode($token_req) : '') . '&msg=aprovacao';
    header('Location: ' . $redir);
    exit;
  } elseif ($acao === 'status_venda_lead') {
    $lead_id = trim($_POST['lead_id'] ?? '');
    $venda_status = trim($_POST['venda_cliente'] ?? 'Em Atendimento');
    foreach ($dados['leads'] as &$l) {
      if ($l['id'] === $lead_id) {
        $l['venda_cliente'] = $venda_status;
        if ($venda_status === 'Vendido') $l['status'] = 'Fechado';
        if ($venda_status === 'Perdido') $l['status'] = 'Perdido';
        break;
      }
    }
    unset($l);
    salvar_json('db.json', $dados);
    $redir = 'portal.php?cliente=' . urlencode($cliente_sel) . ($token_req ? '&token=' . urlencode($token_req) : '') . '&msg=lead';
    header('Location: ' . $redir);
    exit;
  }
}

$msg_ok = $_GET['msg'] ?? '';
$cli_lower = strtolower(trim($cliente_sel));

$perf_cli = array_values(array_filter($dados['performance'] ?? [], fn($p) => strtolower(trim($p['cliente'] ?? '')) === $cli_lower));
$proj_cli = array_values(array_filter($dados['projetos'] ?? [], fn($p) => strtolower(trim($p['cliente'] ?? '')) === $cli_lower));
$fin_cli  = array_values(array_filter($dados['financeiro'] ?? [], fn($f) => strtolower(trim($f['cliente'] ?? '')) === $cli_lower));
$opt_cli  = array_reverse(array_values(array_filter($dados['otimizacoes'] ?? [], fn($o) => strtolower(trim($o['cliente'] ?? '')) === $cli_lower)));
$leads_cli = array_reverse(array_values(array_filter($dados['leads'] ?? [], fn($l) =>
  strtolower(trim($l['empresa'] ?? '')) === $cli_lower || strtolower(trim($l['nome'] ?? '')) === $cli_lower
)));

$cobrancas_atrasadas = array_filter($fin_cli, fn($f) => ($f['status'] ?? '') === 'Atrasado');

$inv_cli   = array_sum(array_column($perf_cli, 'investido'));
$leads_num = array_sum(array_column($perf_cli, 'leads_gerados'));
$rec_cli   = array_sum(array_column($perf_cli, 'receita_gerada'));
$cpl_cli   = $leads_num > 0 ? $inv_cli / $leads_num : 0;
$roas_cli  = $inv_cli > 0 ? $rec_cli / $inv_cli : 0;
$token_share = $cliente_sel ? token_cliente($cliente_sel) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MKT Massuquetto — Portal do Cliente (<?= htmlspecialchars($cliente_sel) ?>)</title>
<link rel="icon" type="image/png" href="<?= htmlspecialchars(LOGO_URL) ?>">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php if($is_admin): include 'navbar.php'; else: ?>
<nav class="navbar">
  <div class="navbar-brand">
    <img src="<?= htmlspecialchars(LOGO_URL) ?>" alt="MKT Massuquetto" class="brand-logo">
    <span class="brand-name">MKT <strong>Massuquetto</strong></span>
  </div>
  <div class="navbar-user">
    <span class="user-info">Relatório em Tempo Real — <?= htmlspecialchars($cliente_sel) ?></span>
  </div>
</nav>
<?php endif; ?>

<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">Portal do Cliente: <?= htmlspecialchars($cliente_sel ?: 'Selecione um cliente') ?></h1>
      <span class="page-sub">Relatórios em tempo real, aprovação visual de projetos e CRM comercial simplificado</span>
    </div>
    <?php if($is_admin && $cliente_sel): ?>
    <form method="GET" action="portal.php" class="filter-form">
      <select name="cliente" onchange="this.form.submit()">
        <?php foreach($todos_clientes as $c): ?>
        <option value="<?= htmlspecialchars($c) ?>" <?= $c === $cliente_sel ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
        <?php endforeach; ?>
      </select>
      <a href="portal.php?cliente=<?= urlencode($cliente_sel) ?>&amp;token=<?= urlencode($token_share) ?>" target="_blank" class="btn btn-outline btn-sm">🔗 Link Exclusivo do Cliente</a>
    </form>
    <?php endif; ?>
  </div>

  <?php if($msg_ok === 'aprovacao'): ?><div class="alert alert-ok">✅ Feedback de aprovação visual registrado!</div><?php endif; ?>
  <?php if($msg_ok === 'lead'): ?><div class="alert alert-ok">✅ Status comercial do lead atualizado!</div><?php endif; ?>

  <?php if(!empty($cobrancas_atrasadas)): ?>
  <div class="alert alert-err">
    ⚠️ <strong>Aviso Financeiro:</strong> Consta(m) <?= count($cobrancas_atrasadas) ?> cobrança(s) em atraso para esta conta. Regularize junto ao financeiro da MKT Massuquetto para evitar pausa de campanhas.
  </div>
  <?php endif; ?>

  <?php if($is_admin && $cliente_sel): ?>
  <div class="alert alert-ok" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <span>🔐 <strong>Link exclusivo do cliente:</strong> <code>portal.php?cliente=<?= urlencode($cliente_sel) ?>&amp;token=<?= urlencode($token_share) ?></code></span>
  </div>
  <?php endif; ?>

  <!-- Métricas de Performance -->
  <div class="cards-grid" style="margin-bottom:28px;">
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(108,58,227,.15);color:#9b6dff;">💸</div>
      <div class="indicator-info">
        <span class="indicator-label">Investido em Mídia</span>
        <span class="indicator-value"><?= formatar_moeda($inv_cli) ?></span>
      </div>
    </div>
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(0,180,255,.1);color:#00b4ff;">🎯</div>
      <div class="indicator-info">
        <span class="indicator-label">Leads Gerados</span>
        <span class="indicator-value"><?= $leads_num ?></span>
      </div>
    </div>
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(255,180,0,.1);color:#ffb400;">📉</div>
      <div class="indicator-info">
        <span class="indicator-label">Custo por Lead (CPL)</span>
        <span class="indicator-value"><?= formatar_moeda($cpl_cli) ?></span>
      </div>
    </div>
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(0,231,193,.12);color:#00E7C1;">📈</div>
      <div class="indicator-info">
        <span class="indicator-label">Retorno (ROAS)</span>
        <span class="indicator-value" style="color:#00E7C1;"><?= number_format($roas_cli, 2, ',', '.') ?>x</span>
      </div>
    </div>
  </div>

  <!-- Projetos & Aprovação Visual -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header">
      <h2 class="card-title">🎨 Projetos em Andamento &amp; Aprovação Visual</h2>
    </div>
    <div class="card-body">
      <?php if(empty($proj_cli)): ?>
        <p style="color:var(--text-muted);">Nenhum projeto ativo para este cliente.</p>
      <?php else: ?>
        <?php foreach($proj_cli as $pr): ?>
        <div class="pilar-box" style="margin-bottom:16px;">
          <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:10px;">
            <div>
              <strong style="font-size:1rem;"><?= htmlspecialchars($pr['servico']) ?></strong>
              <span class="badge badge-proj-<?= slug_status($pr['status']) ?>" style="margin-left:8px;"><?= htmlspecialchars($pr['status']) ?></span>
              <span class="badge badge-proj-<?= slug_status($pr['aprovacao_cliente'] ?? 'Em Análise') ?>" style="margin-left:4px;"><?= htmlspecialchars($pr['aprovacao_cliente'] ?? 'Em Análise') ?></span>
            </div>
            <?php if(!empty($pr['url_homologacao'])): ?>
              <a href="<?= htmlspecialchars($pr['url_homologacao']) ?>" target="_blank" class="btn btn-outline btn-sm">🔗 Abrir Protótipo / Homologação</a>
            <?php endif; ?>
          </div>
          <form method="POST" action="portal.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="acao_portal" value="aprovar_projeto">
            <input type="hidden" name="cliente" value="<?= htmlspecialchars($cliente_sel) ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token_req) ?>">
            <input type="hidden" name="proj_id" value="<?= htmlspecialchars($pr['id']) ?>">
            <select name="aprovacao_cliente" style="background:var(--surface);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:7px;">
              <?php foreach(['Em Análise', 'Ajustes Solicitados', 'Aprovado'] as $op): ?>
              <option <?= (($pr['aprovacao_cliente'] ?? 'Em Análise') === $op) ? 'selected' : '' ?>><?= $op ?></option>
              <?php endforeach; ?>
            </select>
            <input type="text" name="comentarios_cliente" value="<?= htmlspecialchars($pr['comentarios_cliente'] ?? '') ?>" placeholder="Comentar alterações ou aprovar entrega..." style="flex:1;min-width:220px;background:var(--surface);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:7px;">
            <button type="submit" class="btn btn-primary btn-sm">Enviar Feedback</button>
          </form>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- CRM Simplificado para a equipe comercial do cliente -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header">
      <h2 class="card-title">🤝 CRM Simplificado do Cliente (Retorno Comercial)</h2>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Lead / Contato</th>
            <th>Serviço</th>
            <th>Valor Est.</th>
            <th>Status Comercial</th>
            <th>Atualizar Resultado</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($leads_cli)): ?>
          <tr><td colspan="5" class="empty-row">Nenhum lead vinculado a este cliente no CRM.</td></tr>
          <?php else: ?>
          <?php foreach($leads_cli as $ld): ?>
          <?php $vc = $ld['venda_cliente'] ?? 'Em Atendimento'; ?>
          <tr>
            <td><strong><?= htmlspecialchars($ld['nome']) ?></strong><br><small style="color:var(--text-muted);"><?= htmlspecialchars($ld['whatsapp'] ?? '') ?></small></td>
            <td><span class="badge badge-service"><?= htmlspecialchars($ld['servico']) ?></span></td>
            <td><?= formatar_moeda(floatval($ld['valor_estimado'] ?? 0)) ?></td>
            <td><span class="badge badge-<?= slug_status($vc) ?>"><?= htmlspecialchars($vc) ?></span></td>
            <td>
              <form method="POST" action="portal.php" style="display:flex;gap:6px;align-items:center;">
                <input type="hidden" name="acao_portal" value="status_venda_lead">
                <input type="hidden" name="cliente" value="<?= htmlspecialchars($cliente_sel) ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token_req) ?>">
                <input type="hidden" name="lead_id" value="<?= htmlspecialchars($ld['id']) ?>">
                <button type="submit" name="venda_cliente" value="Vendido" class="btn btn-success btn-sm">✅ Vendido</button>
                <button type="submit" name="venda_cliente" value="Perdido" class="btn btn-danger btn-sm">✖ Perdido</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Últimas Otimizações + Cobranças -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">📓 Últimas Otimizações &amp; Faturas</h2>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Canal</th>
            <th>Otimização Executada pela Agência</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($opt_cli)): ?>
          <tr><td colspan="3" class="empty-row">Nenhuma otimização registrada para este cliente.</td></tr>
          <?php else: ?>
          <?php foreach($opt_cli as $o): ?>
          <tr>
            <td style="white-space:nowrap;font-size:.82rem;color:var(--text-muted);"><?= htmlspecialchars($o['criado_em']) ?></td>
            <td><span class="badge badge-service"><?= htmlspecialchars($o['plataforma']) ?></span></td>
            <td><?= htmlspecialchars($o['acao']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<div class="dev-sign">Desenvolvido por: Ruben</div>
</body>
</html>
