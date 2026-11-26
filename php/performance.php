<?php
session_start();
require 'functions.php';
verificar_sessao();

$dados = ler_json('db.json');
if (!isset($dados['performance'])) $dados['performance'] = [];
if (!isset($dados['otimizacoes'])) $dados['otimizacoes'] = [];
$erro = '';

// EXCLUIR CONTA / METRICA
if (isset($_GET['excluir'])) {
  $id = $_GET['excluir'];
  $dados['performance'] = array_values(array_filter($dados['performance'], fn($p) => $p['id'] !== $id));
  salvar_json('db.json', $dados);
  header('Location: performance.php?msg=excluido'); exit;
}

// EXCLUIR OTIMIZACAO
if (isset($_GET['excluir_opt'])) {
  $id = $_GET['excluir_opt'];
  $dados['otimizacoes'] = array_values(array_filter($dados['otimizacoes'], fn($o) => $o['id'] !== $id));
  salvar_json('db.json', $dados);
  header('Location: performance.php?msg=excluido'); exit;
}

// SALVAR METRICAS OU DIARIO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $acao_form = $_POST['acao_form'] ?? 'performance';
  if ($acao_form === 'otimizacao') {
    $cliente = trim($_POST['cliente'] ?? '');
    $acao    = trim($_POST['acao'] ?? '');
    if (empty($cliente) || empty($acao)) {
      $erro = 'Informe o cliente e a otimização realizada.';
    } else {
      $dados['otimizacoes'][] = [
        'id'          => gerar_id(),
        'cliente'     => $cliente,
        'plataforma'  => trim($_POST['plataforma'] ?? 'Google Ads'),
        'acao'        => $acao,
        'responsavel' => trim($_POST['responsavel'] ?? ($_SESSION['usuario'] ?? 'Admin')),
        'criado_em'   => date('Y-m-d H:i:s'),
      ];
      salvar_json('db.json', $dados);
      header('Location: performance.php?msg=opt_salva'); exit;
    }
  } else {
    $id_edit = trim($_POST['id_edit'] ?? '');
    $novo = [
      'id'              => $id_edit ?: gerar_id(),
      'cliente'         => trim($_POST['cliente'] ?? ''),
      'plataforma'      => trim($_POST['plataforma'] ?? 'Google Ads + Meta Ads'),
      'periodo'         => trim($_POST['periodo'] ?? date('Y-m')),
      'orcamento_teto'  => floatval(str_replace(',','.',str_replace('.','', $_POST['orcamento_teto'] ?? '0'))),
      'saldo_atual'     => floatval(str_replace(',','.',str_replace('.','', $_POST['saldo_atual'] ?? '0'))),
      'investido'       => floatval(str_replace(',','.',str_replace('.','', $_POST['investido'] ?? '0'))),
      'leads_gerados'   => intval($_POST['leads_gerados'] ?? 0),
      'vendas_fechadas' => intval($_POST['vendas_fechadas'] ?? 0),
      'receita_gerada'  => floatval(str_replace(',','.',str_replace('.','', $_POST['receita_gerada'] ?? '0'))),
      'atualizado_em'   => date('Y-m-d H:i:s'),
    ];
    if (empty($novo['cliente'])) {
      $erro = 'Cliente é obrigatório.';
    } else {
      if ($id_edit) {
        foreach ($dados['performance'] as &$p) {
          if ($p['id'] === $id_edit) { $p = $novo; break; }
        }
        unset($p);
      } else {
        $dados['performance'][] = $novo;
      }
      salvar_json('db.json', $dados);
      header('Location: performance.php?msg=salvo'); exit;
    }
  }
}

$edit_perf = null;
if (isset($_GET['editar'])) {
  foreach ($dados['performance'] as $p) {
    if ($p['id'] === $_GET['editar']) { $edit_perf = $p; break; }
  }
}

$lista_perf = array_reverse($dados['performance']);
$lista_opt  = array_reverse($dados['otimizacoes']);

$total_investido = array_sum(array_column($dados['performance'], 'investido'));
$total_leads     = array_sum(array_column($dados['performance'], 'leads_gerados'));
$total_receita   = array_sum(array_column($dados['performance'], 'receita_gerada'));
$cpl_medio       = $total_leads > 0 ? $total_investido / $total_leads : 0;
$roas_global     = $total_investido > 0 ? $total_receita / $total_investido : 0;

$alertas_saldo = array_filter($dados['performance'], function($p) {
  $saldo = floatval($p['saldo_atual'] ?? 0);
  $inv   = floatval($p['investido'] ?? 0);
  $teto  = floatval($p['orcamento_teto'] ?? 0);
  return $saldo <= 250 || ($teto > 0 && $inv >= ($teto * 0.9));
});

$clientes_proj  = array_unique(array_column($dados['projetos'] ?? [], 'cliente'));
$clientes_leads = array_unique(array_column($dados['leads'] ?? [], 'empresa'));
$clientes = array_values(array_filter(array_unique(array_merge($clientes_proj, $clientes_leads))));
sort($clientes);

$plataformas = ['Google Ads + Meta Ads', 'Google Ads', 'Meta Ads (Insta/Face)'];
$msg_ok = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MKT Massuquetto — Central de Performance &amp; Tráfego Pago</title>
<link rel="icon" type="image/png" href="<?= htmlspecialchars(LOGO_URL) ?>">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">Central de Performance &amp; Tráfego Pago</h1>
      <span class="page-sub">Dashboard de ROI/CPL (Google &amp; Meta Ads), controle de saldo/orçamento e diário de otimizações</span>
    </div>
  </div>

  <?php if($msg_ok === 'salvo'):     ?><div class="alert alert-ok">✅ Métricas de campanha salvas!</div><?php endif; ?>
  <?php if($msg_ok === 'opt_salva'): ?><div class="alert alert-ok">📝 Registro adicionado ao Diário de Otimizações!</div><?php endif; ?>
  <?php if($msg_ok === 'excluido'):  ?><div class="alert alert-ok">🗑️ Registro removido.</div><?php endif; ?>
  <?php if($erro):                   ?><div class="alert alert-err">❌ <?= htmlspecialchars($erro) ?></div><?php endif; ?>

  <?php if(!empty($alertas_saldo)): ?>
  <div class="alert alert-warn">
    🚨 <strong>Alerta de Saldo / Teto Mensal:</strong>
    <?php foreach($alertas_saldo as $al): ?>
      <span><strong><?= htmlspecialchars($al['cliente']) ?></strong> (Saldo: <?= formatar_moeda(floatval($al['saldo_atual'])) ?> / Teto: <?= formatar_moeda(floatval($al['orcamento_teto'])) ?>)</span> &nbsp;
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="cards-grid" style="margin-bottom:28px;">
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(108,58,227,.15);color:#9b6dff;">💸</div>
      <div class="indicator-info">
        <span class="indicator-label">Investimento em Mídia</span>
        <span class="indicator-value"><?= formatar_moeda($total_investido) ?></span>
      </div>
    </div>
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(0,180,255,.1);color:#00b4ff;">🎯</div>
      <div class="indicator-info">
        <span class="indicator-label">Leads / CPL Médio</span>
        <span class="indicator-value"><?= $total_leads ?> <small style="font-size:.82rem;color:var(--text-muted);">/ <?= formatar_moeda($cpl_medio) ?></small></span>
      </div>
    </div>
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(0,231,193,.12);color:#00E7C1;">💰</div>
      <div class="indicator-info">
        <span class="indicator-label">Receita Rastreada</span>
        <span class="indicator-value" style="color:#00E7C1;"><?= formatar_moeda($total_receita) ?></span>
      </div>
    </div>
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(255,180,0,.1);color:#ffb400;">🚀</div>
      <div class="indicator-info">
        <span class="indicator-label">ROAS Consolidado</span>
        <span class="indicator-value" style="color:#00E7C1;"><?= number_format($roas_global, 2, ',', '.') ?>x</span>
      </div>
    </div>
  </div>

  <!-- Formulário de Performance -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header">
      <h2 class="card-title"><?= $edit_perf ? '✏️ Editar Conta / Campanha' : '➕ Registrar Performance &amp; Orçamento' ?></h2>
    </div>
    <form method="POST" action="performance.php" class="form-grid">
      <input type="hidden" name="acao_form" value="performance">
      <input type="hidden" name="id_edit" value="<?= htmlspecialchars($edit_perf['id'] ?? '') ?>">
      <div class="form-group">
        <label>Cliente *</label>
        <input type="text" name="cliente" required list="lista-clientes-perf"
          value="<?= htmlspecialchars($edit_perf['cliente'] ?? '') ?>" placeholder="Nome do cliente">
        <datalist id="lista-clientes-perf">
          <?php foreach($clientes as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-group">
        <label>Plataforma</label>
        <select name="plataforma">
          <?php foreach($plataformas as $pl): ?>
          <option <?= (($edit_perf['plataforma'] ?? '') === $pl) ? 'selected' : '' ?>><?= $pl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Período (AAAA-MM)</label>
        <input type="text" name="periodo" value="<?= htmlspecialchars($edit_perf['periodo'] ?? date('Y-m')) ?>" placeholder="2026-11">
      </div>
      <div class="form-group">
        <label>Orçamento Teto Mensal (R$)</label>
        <input type="text" name="orcamento_teto" value="<?= number_format(floatval($edit_perf['orcamento_teto'] ?? 0), 2, ',', '.') ?>">
      </div>
      <div class="form-group">
        <label>Saldo Atual em Conta (R$)</label>
        <input type="text" name="saldo_atual" value="<?= number_format(floatval($edit_perf['saldo_atual'] ?? 0), 2, ',', '.') ?>">
      </div>
      <div class="form-group">
        <label>Valor Investido (R$)</label>
        <input type="text" name="investido" value="<?= number_format(floatval($edit_perf['investido'] ?? 0), 2, ',', '.') ?>">
      </div>
      <div class="form-group">
        <label>Leads Gerados</label>
        <input type="number" name="leads_gerados" min="0" value="<?= intval($edit_perf['leads_gerados'] ?? 0) ?>">
      </div>
      <div class="form-group">
        <label>Vendas Fechadas</label>
        <input type="number" name="vendas_fechadas" min="0" value="<?= intval($edit_perf['vendas_fechadas'] ?? 0) ?>">
      </div>
      <div class="form-group">
        <label>Receita Gerada (R$)</label>
        <input type="text" name="receita_gerada" value="<?= number_format(floatval($edit_perf['receita_gerada'] ?? 0), 2, ',', '.') ?>">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $edit_perf ? 'Atualizar Performance' : 'Salvar Performance' ?></button>
        <?php if($edit_perf): ?>
        <a href="performance.php" class="btn btn-outline">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Tabela Dashboard Unificado de ROI -->
  <div class="card" style="margin-bottom:28px;">
    <div class="card-header">
      <h2 class="card-title">Dashboard Unificado de ROI por Cliente</h2>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Plataforma</th>
            <th>Investido / Teto</th>
            <th>Saldo</th>
            <th>Leads</th>
            <th>CPL</th>
            <th>Receita</th>
            <th>ROAS</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($lista_perf)): ?>
          <tr><td colspan="9" class="empty-row">Nenhuma conta cadastrada.</td></tr>
          <?php else: ?>
          <?php foreach($lista_perf as $p): ?>
          <?php
            $inv   = floatval($p['investido'] ?? 0);
            $teto  = floatval($p['orcamento_teto'] ?? 0);
            $saldo = floatval($p['saldo_atual'] ?? 0);
            $leads = intval($p['leads_gerados'] ?? 0);
            $rec   = floatval($p['receita_gerada'] ?? 0);
            $cpl   = $leads > 0 ? $inv / $leads : 0;
            $roas  = $inv > 0 ? $rec / $inv : 0;
            $alerta_baixo = ($saldo <= 250 || ($teto > 0 && $inv >= $teto * 0.9));
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($p['cliente']) ?></strong><br><small style="color:var(--text-muted);"><?= htmlspecialchars($p['periodo']) ?></small></td>
            <td><span class="badge badge-service"><?= htmlspecialchars($p['plataforma']) ?></span></td>
            <td><?= formatar_moeda($inv) ?> <small style="color:var(--text-muted);">/ <?= formatar_moeda($teto) ?></small></td>
            <td>
              <span class="badge <?= $alerta_baixo ? 'badge-fin-atrasado' : 'badge-fin-pago' ?>">
                <?= formatar_moeda($saldo) ?> <?= $alerta_baixo ? '⚠️' : '' ?>
              </span>
            </td>
            <td><?= $leads ?> (<?= intval($p['vendas_fechadas'] ?? 0) ?> vendas)</td>
            <td><?= formatar_moeda($cpl) ?></td>
            <td class="lucro-cell"><?= formatar_moeda($rec) ?></td>
            <td><strong style="color:var(--green);"><?= number_format($roas, 2, ',', '.') ?>x</strong></td>
            <td class="actions-cell">
              <a href="portal.php?cliente=<?= urlencode($p['cliente']) ?>" class="btn btn-success btn-sm" title="Ver Portal do Cliente">🌐</a>
              <a href="performance.php?editar=<?= urlencode($p['id']) ?>" class="btn btn-outline btn-sm">✏️</a>
              <a href="performance.php?excluir=<?= urlencode($p['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Excluir registro?')">🗑️</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Diário de Otimizações -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">📓 Diário de Otimizações</h2>
    </div>
    <form method="POST" action="performance.php" class="form-grid" style="border-bottom:1px solid var(--border);">
      <input type="hidden" name="acao_form" value="otimizacao">
      <div class="form-group">
        <label>Cliente *</label>
        <input type="text" name="cliente" required list="lista-clientes-perf" placeholder="Cliente otimizado">
      </div>
      <div class="form-group">
        <label>Canal</label>
        <select name="plataforma">
          <option>Google Ads</option>
          <option>Meta Ads</option>
          <option>Landing Page / CRO</option>
          <option>SEO Local / GMN</option>
        </select>
      </div>
      <div class="form-group">
        <label>Gestor Responsável</label>
        <input type="text" name="responsavel" value="Ruben Massuquetto">
      </div>
      <div class="form-group form-span-3">
        <label>Otimização Aplicada *</label>
        <input type="text" name="acao" required placeholder="Ex: Troca de criativo de vídeo, negativação de termos, ajuste de lance CPA...">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">📝 Registrar Otimização</button>
      </div>
    </form>

    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Data</th>
            <th>Cliente</th>
            <th>Canal</th>
            <th>Alteração / Justificativa</th>
            <th>Gestor</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($lista_opt)): ?>
          <tr><td colspan="6" class="empty-row">Nenhuma otimização registrada.</td></tr>
          <?php else: ?>
          <?php foreach($lista_opt as $opt): ?>
          <tr>
            <td style="white-space:nowrap;font-size:.8rem;color:var(--text-muted);"><?= htmlspecialchars($opt['criado_em']) ?></td>
            <td><strong><?= htmlspecialchars($opt['cliente']) ?></strong></td>
            <td><span class="badge badge-service"><?= htmlspecialchars($opt['plataforma']) ?></span></td>
            <td><?= htmlspecialchars($opt['acao']) ?></td>
            <td><?= htmlspecialchars($opt['responsavel']) ?></td>
            <td>
              <a href="performance.php?excluir_opt=<?= urlencode($opt['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Excluir log?')">🗑️</a>
            </td>
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
