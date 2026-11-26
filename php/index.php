<?php
session_start();
require 'functions.php';
verificar_sessao();

if (!empty($_GET['_db_fresh'])) { $_SESSION = []; session_regenerate_id(true); header('Location: login.php'); exit; }

$dados = ler_json('db.json');
$leads = $dados['leads'] ?? [];
$projetos = $dados['projetos'] ?? [];
$financeiro = $dados['financeiro'] ?? [];
$performance = $dados['performance'] ?? [];

// Faturamento: soma projetos + cobranças pagas
$faturamento_projetos = array_sum(array_column($projetos, 'valor_cobrado'));
$faturamento_financeiro = array_sum(array_column(array_filter($financeiro, fn($f) => ($f['status'] ?? '') === 'Pago'), 'valor'));
$faturamento = $faturamento_projetos + $faturamento_financeiro;

$lucro = array_sum(array_column($projetos, 'lucro_liquido'));
$leads_ativos = count(array_filter($leads, fn($l) => !in_array($l['status'] ?? '', ['Fechado', 'Perdido'])));
$projetos_andamento = count(array_filter($projetos, fn($p) => ($p['status'] ?? '') !== 'Entregue'));

$ultimos_leads = array_slice(array_reverse($leads), 0, 5);

// Próximas cobranças a vencer (Pendente ou Atrasado), ordenadas por vencimento
$cobrancas_abertas = array_values(array_filter($financeiro, fn($f) => ($f['status'] ?? '') !== 'Pago'));
usort($cobrancas_abertas, fn($a, $b) => strcmp($a['vencimento'] ?? '', $b['vencimento'] ?? ''));
$proximas_cobrancas = array_slice($cobrancas_abertas, 0, 5);

$alertas_saldo = array_filter($performance, fn($p) => floatval($p['saldo_atual'] ?? 0) <= 250);
$alertas_uptime = array_filter($projetos, fn($p) => ($p['uptime_status'] ?? 'Online / Rápido') !== 'Online / Rápido');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MKT Massuquetto — Painel Geral</title>
<link rel="icon" type="image/png" href="<?= htmlspecialchars(LOGO_URL) ?>">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">Painel Geral</h1>
      <span class="page-sub">Visão consolidada da agência MKT Massuquetto — Sites, Tráfego Pago &amp; Performance</span>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="servicos.php" class="btn btn-outline btn-sm">✨ Serviços</a>
      <a href="leads.php" class="btn btn-primary btn-sm">➕ Novo Lead</a>
    </div>
  </div>

  <?php if(!empty($alertas_saldo)): ?>
  <div class="alert alert-warn">
    ⚠️ <strong>Saldo baixo em Tráfego Pago:</strong>
    <?php foreach($alertas_saldo as $as): ?>
      <strong><?= htmlspecialchars($as['cliente']) ?></strong> (<?= formatar_moeda(floatval($as['saldo_atual'])) ?>) &nbsp;
    <?php endforeach; ?>
    — <a href="performance.php" style="text-decoration:underline;">Ver Central de Tráfego</a>
  </div>
  <?php endif; ?>

  <?php if(!empty($alertas_uptime)): ?>
  <div class="alert alert-err">
    🚨 <strong>Alerta de Uptime/Velocidade:</strong>
    <?php foreach($alertas_uptime as $au): ?>
      <strong><?= htmlspecialchars($au['cliente']) ?></strong> (<?= htmlspecialchars($au['uptime_status']) ?>) &nbsp;
    <?php endforeach; ?>
    — <a href="projetos.php" style="text-decoration:underline;">Verificar Projetos</a>
  </div>
  <?php endif; ?>

  <div class="cards-grid">
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(0,231,193,.12);color:#00E7C1;">💰</div>
      <div class="indicator-info">
        <span class="indicator-label">Faturamento Total</span>
        <span class="indicator-value"><?= formatar_moeda($faturamento) ?></span>
      </div>
    </div>
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(108,58,227,.15);color:#9b6dff;">📈</div>
      <div class="indicator-info">
        <span class="indicator-label">Lucro Líquido</span>
        <span class="indicator-value" style="color:#00E7C1;"><?= formatar_moeda($lucro) ?></span>
      </div>
    </div>
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(255,180,0,.1);color:#ffb400;">🎯</div>
      <div class="indicator-info">
        <span class="indicator-label">Leads Ativos</span>
        <span class="indicator-value"><?= $leads_ativos ?></span>
      </div>
    </div>
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(0,180,255,.1);color:#00b4ff;">🚀</div>
      <div class="indicator-info">
        <span class="indicator-label">Projetos em Andamento</span>
        <span class="indicator-value"><?= $projetos_andamento ?></span>
      </div>
    </div>
  </div>

  <div class="dashboard-split">
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Últimos Clientes Interessados</h2>
        <a href="leads.php" class="btn btn-outline btn-sm">Ver todos</a>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>Empresa</th>
              <th>Serviço</th>
              <th>Valor Est.</th>
              <th>Etapa</th>
              <th>WhatsApp</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($ultimos_leads)): ?>
            <tr><td colspan="6" class="empty-row">Nenhum interessado cadastrado.</td></tr>
            <?php else: ?>
            <?php foreach($ultimos_leads as $lead): ?>
            <tr>
              <td><?= htmlspecialchars($lead['nome']) ?></td>
              <td><?= htmlspecialchars($lead['empresa']) ?></td>
              <td><span class="badge badge-service"><?= htmlspecialchars($lead['servico']) ?></span></td>
              <td><?= formatar_moeda(floatval($lead['valor_estimado'])) ?></td>
              <td><span class="badge badge-status badge-<?= slug_status($lead['status']) ?>"><?= htmlspecialchars($lead['status']) ?></span></td>
              <td>
                <?php $wn = preg_replace('/\D/','',$lead['whatsapp']); ?>
                <a href="https://wa.me/55<?= $wn ?>" target="_blank" class="btn btn-whatsapp btn-sm">💬 WA</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">Próximas Cobranças a Vencer</h2>
        <a href="financeiro.php" class="btn btn-outline btn-sm">Ver financeiro</a>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Tipo</th>
              <th>Vencimento</th>
              <th>Valor Atualizado</th>
              <th>Status</th>
              <th>Ação</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($proximas_cobrancas)): ?>
            <tr><td colspan="6" class="empty-row">Nenhuma cobrança pendente ou atrasada.</td></tr>
            <?php else: ?>
            <?php foreach($proximas_cobrancas as $cob): ?>
            <?php $enc = calcular_encargos($cob); ?>
            <tr>
              <td><?= htmlspecialchars($cob['cliente']) ?></td>
              <td><?= htmlspecialchars($cob['tipo']) ?></td>
              <td><?= formatar_data($cob['vencimento']) ?></td>
              <td class="lucro-cell"><?= formatar_moeda($enc['total']) ?></td>
              <td><span class="badge badge-fin-<?= slug_status($cob['status']) ?>"><?= htmlspecialchars($cob['status']) ?></span></td>
              <td>
                <a href="financeiro.php?pagar=<?= urlencode($cob['id']) ?>" class="btn btn-success btn-sm" onclick="return confirm('Marcar cobrança como Pago?')">✅ Pago</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<div class="dev-sign">Desenvolvido por: Ruben</div>
</body>
</html>
