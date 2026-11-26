<?php
session_start();
require 'functions.php';
verificar_sessao();

$dados = ler_json('db.json');
if (!isset($dados['servicos_config']) || !is_array($dados['servicos_config'])) {
  $dados['servicos_config'] = [];
}

$catalogo = servicos_comerciais($dados);
$nomes = array_keys($catalogo);
$ativo = $_GET['servico'] ?? ($_POST['servico_nome'] ?? $nomes[0]);
if (!isset($catalogo[$ativo])) {
  $ativo = $nomes[0];
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_config') {
  $srv_alvo = trim($_POST['servico_nome'] ?? '');
  if (isset($catalogo[$srv_alvo])) {
    $preco = floatval(str_replace(',', '.', str_replace('.', '', $_POST['preco_base'] ?? '0')));
    $custo = floatval(str_replace(',', '.', str_replace('.', '', $_POST['custo_freelancer'] ?? '0')));
    $prazo = max(1, intval($_POST['prazo_dias'] ?? 15));
    $modelo = trim($_POST['modelo_cobranca'] ?? 'Setup Único');
    $status_oferta = trim($_POST['status_oferta'] ?? 'Ativo para Venda');

    $dados['servicos_config'][$srv_alvo] = [
      'modelo_cobranca' => $modelo,
      'preco_base' => $preco,
      'custo_freelancer' => $custo,
      'prazo_dias' => $prazo,
      'status_oferta' => $status_oferta,
    ];
    salvar_json('db.json', $dados);
    header('Location: servicos.php?servico=' . urlencode($srv_alvo) . '&msg=salvo');
    exit;
  } else {
    $erro = 'Serviço inválido.';
  }
}

$msg_ok = $_GET['msg'] ?? '';
$atual_srv = $catalogo[$ativo];

$leads = $dados['leads'] ?? [];
$projetos = $dados['projetos'] ?? [];

// Métricas por serviço a partir do db.json
$metricas_srv = [];
foreach ($catalogo as $nome => $srv) {
  $leads_srv = array_filter($leads, fn($l) => ($l['servico'] ?? '') === $nome && ($l['status'] ?? '') !== 'Perdido');
  $proj_srv = array_filter($projetos, fn($p) => ($p['servico'] ?? '') === $nome);
  $receita_srv = array_sum(array_map(fn($p) => floatval($p['valor_cobrado'] ?? 0), $proj_srv));
  $lucro_srv = array_sum(array_map(fn($p) => floatval($p['lucro_liquido'] ?? 0), $proj_srv));
  $metricas_srv[$nome] = [
    'leads' => count($leads_srv),
    'projetos' => count($proj_srv),
    'receita' => $receita_srv,
    'lucro' => $lucro_srv,
  ];
}

$preco_base = floatval($atual_srv['preco_base'] ?? 0);
$custo_freela = floatval($atual_srv['custo_freelancer'] ?? 0);
$lucro_base = $preco_base - $custo_freela;
$margem_pct = $preco_base > 0 ? round(($lucro_base / $preco_base) * 100) : 0;

// Leads e Projetos do serviço selecionado
$leads_atual = array_values(array_filter(array_reverse($leads), fn($l) => ($l['servico'] ?? '') === $ativo));
$projetos_atual = array_values(array_filter(array_reverse($projetos), fn($p) => ($p['servico'] ?? '') === $ativo));

// Texto pronto de proposta comercial para enviar ao cliente
$sufixo_modelo = ($atual_srv['modelo_cobranca'] ?? '') === 'Mensalidade Recorrente' ? '/mês' : ' (Setup)';
$itens_txt = '';
foreach ($atual_srv['beneficios'] as $b) {
  $itens_txt .= "✅ " . $b . "\n";
}
$texto_proposta = "*Proposta Comercial — MKT Massuquetto*\n"
  . "Serviço: *" . $ativo . "*\n\n"
  . $atual_srv['resumo'] . "\n\n"
  . "*O que está incluso na entrega:*\n"
  . $itens_txt . "\n"
  . "⏱️ Prazo padrão de entrega/início: " . intval($atual_srv['prazo_dias']) . " dias\n"
  . "💰 Investimento base: *" . formatar_moeda($preco_base) . $sufixo_modelo . "*\n\n"
  . "Podemos seguir com a emissão do contrato e início do onboarding?";

$modelos = ['Setup Único', 'Mensalidade Recorrente', 'Setup + Mensalidade'];
$status_ofertas = ['Ativo para Venda', 'Sob Consulta', 'Pausado'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MKT Massuquetto — Catálogo Comercial &amp; Escopo</title>
<link rel="icon" type="image/png" href="<?= htmlspecialchars(LOGO_URL) ?>">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">Catálogo Comercial &amp; Escopo de Serviços</h1>
      <span class="page-sub">Tabela de preços, margem por entrega, escopo técnico padrão e propostas para clientes</span>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="leads.php?servico=<?= urlencode($ativo) ?>" class="btn btn-primary">➕ Novo Lead neste Serviço</a>
      <a href="projetos.php?servico=<?= urlencode($ativo) ?>" class="btn btn-accent">🗂️ Abrir Projeto</a>
    </div>
  </div>

  <?php if($msg_ok === 'salvo'): ?><div class="alert alert-ok">✅ Parâmetros comerciais de <strong><?= htmlspecialchars($ativo) ?></strong> atualizados!</div><?php endif; ?>
  <?php if($erro): ?><div class="alert alert-err">❌ <?= htmlspecialchars($erro) ?></div><?php endif; ?>

  <!-- VISÃO GERAL DE PREÇOS E MARGEM DOS 5 SERVIÇOS -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header">
      <h2 class="card-title">💼 Portfólio &amp; Tabela Comercial MKT Massuquetto</h2>
      <span class="page-sub" style="margin:0;">Clique em um serviço para ajustar preços, conferir o escopo ou enviar proposta</span>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Serviço</th>
            <th>Modelo</th>
            <th>Preço Base</th>
            <th>Custo Freela</th>
            <th>Lucro Base</th>
            <th>Prazo</th>
            <th>Demanda Atual</th>
            <th>Status</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($catalogo as $nome => $srv):
            $pb = floatval($srv['preco_base'] ?? 0);
            $cf = floatval($srv['custo_freelancer'] ?? 0);
            $lb = $pb - $cf;
            $mg = $pb > 0 ? round(($lb / $pb) * 100) : 0;
            $met = $metricas_srv[$nome];
            $st_of = $srv['status_oferta'] ?? 'Ativo para Venda';
            $badge_st = $st_of === 'Ativo para Venda' ? 'badge-fin-pago' : ($st_of === 'Sob Consulta' ? 'badge-fin-pendente' : 'badge-fin-atrasado');
          ?>
          <tr style="<?= $nome === $ativo ? 'background:rgba(108,58,227,.1);' : '' ?>">
            <td><strong><?= $srv['icone'] ?> <?= htmlspecialchars($nome) ?></strong></td>
            <td><span class="badge badge-service"><?= htmlspecialchars($srv['modelo_cobranca']) ?></span></td>
            <td><?= formatar_moeda($pb) ?></td>
            <td style="color:var(--text-muted);"><?= formatar_moeda($cf) ?></td>
            <td class="lucro-cell"><?= formatar_moeda($lb) ?> <span style="font-size:.75rem;color:var(--text-muted);font-weight:500;">(<?= $mg ?>%)</span></td>
            <td><?= intval($srv['prazo_dias']) ?> dias</td>
            <td>
              <span style="font-size:.82rem;color:#c4a8ff;"><?= $met['leads'] ?> lead(s)</span> •
              <span style="font-size:.82rem;color:var(--green);"><?= $met['projetos'] ?> proj.</span>
            </td>
            <td><span class="badge <?= $badge_st ?>"><?= htmlspecialchars($st_of) ?></span></td>
            <td>
              <a href="servicos.php?servico=<?= urlencode($nome) ?>#detalhe-servico" class="btn <?= $nome === $ativo ? 'btn-primary' : 'btn-outline' ?> btn-sm">
                <?= $nome === $ativo ? 'Selecionado' : 'Gerenciar' ?>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ABAS DE NAVEGAÇÃO RÁPIDA -->
  <div class="servicos-tabs" id="detalhe-servico">
    <?php foreach($catalogo as $nome => $srv): ?>
    <a href="servicos.php?servico=<?= urlencode($nome) ?>#detalhe-servico" class="servico-tab <?= $nome === $ativo ? 'active' : '' ?>">
      <span><?= $srv['icone'] ?></span>
      <span><?= htmlspecialchars($nome) ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- CONFIGURAÇÃO COMERCIAL EDITÁVEL DO SERVIÇO -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header">
      <div>
        <h2 class="card-title">⚙️ Precificação &amp; Parâmetros Comerciais — <?= $atual_srv['icone'] ?> <?= htmlspecialchars($ativo) ?></h2>
        <span class="page-sub" style="margin-top:4px;">Margem atual estimada: <strong style="color:var(--green);"><?= formatar_moeda($lucro_base) ?> (<?= $margem_pct ?>%)</strong> • Receita em projetos ativos: <strong><?= formatar_moeda($metricas_srv[$ativo]['receita']) ?></strong></span>
      </div>
    </div>
    <form method="POST" action="servicos.php?servico=<?= urlencode($ativo) ?>#detalhe-servico" class="form-grid">
      <input type="hidden" name="acao" value="salvar_config">
      <input type="hidden" name="servico_nome" value="<?= htmlspecialchars($ativo) ?>">

      <div class="form-group">
        <label>Modelo de Cobrança</label>
        <select name="modelo_cobranca">
          <?php foreach($modelos as $m): ?>
          <option <?= ($atual_srv['modelo_cobranca'] === $m) ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Preço Base Sugerido (R$)</label>
        <input type="text" name="preco_base" value="<?= number_format($preco_base, 2, ',', '.') ?>" required>
      </div>
      <div class="form-group">
        <label>Custo Estimado Freelancer (R$)</label>
        <input type="text" name="custo_freelancer" value="<?= number_format($custo_freela, 2, ',', '.') ?>" required>
      </div>
      <div class="form-group">
        <label>Prazo Padrão de Entrega (dias)</label>
        <input type="number" name="prazo_dias" min="1" max="180" value="<?= intval($atual_srv['prazo_dias']) ?>" required>
      </div>
      <div class="form-group">
        <label>Disponibilidade Comercial</label>
        <select name="status_oferta">
          <?php foreach($status_ofertas as $so): ?>
          <option <?= (($atual_srv['status_oferta'] ?? '') === $so) ? 'selected' : '' ?>><?= htmlspecialchars($so) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">💾 Salvar Preço e Parâmetros</button>
      </div>
    </form>
  </div>

  <!-- ESCOPO TÉCNICO + SCRIPT DE PROPOSTA PARA CLIENTE -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header">
      <div>
        <h2 class="card-title">📋 Escopo Técnico &amp; Entregáveis Padrão — <?= $atual_srv['icone'] ?> <?= htmlspecialchars($ativo) ?></h2>
        <span class="page-sub" style="margin-top:6px;max-width:850px;"><?= htmlspecialchars($atual_srv['resumo']) ?></span>
      </div>
      <span class="badge badge-service"><?= htmlspecialchars($atual_srv['modelo_cobranca']) ?> • <?= formatar_moeda($preco_base) ?></span>
    </div>
    <div class="card-body">
      <div class="pilares-grid">
        <?php foreach($atual_srv['pilares'] as $pilar): ?>
        <div class="pilar-box">
          <h3><?= htmlspecialchars($pilar['titulo']) ?></h3>
          <p><?= htmlspecialchars($pilar['desc']) ?></p>
        </div>
        <?php endforeach; ?>
      </div>

      <h3 style="font-size:.98rem;color:#fff;margin-top:12px;">Entregáveis Inclusos no Pacote</h3>
      <div class="beneficios-grid">
        <?php foreach($atual_srv['beneficios'] as $ben): ?>
        <div class="beneficio-item">✅ <?= htmlspecialchars($ben) ?></div>
        <?php endforeach; ?>
      </div>

      <div style="margin-top:22px;padding-top:18px;border-top:1px solid var(--border);">
        <h3 style="font-size:.95rem;color:var(--green);margin-bottom:8px;">💬 Texto de Proposta Pronto para Enviar ao Cliente (WhatsApp)</h3>
        <div class="form-group" style="margin-bottom:12px;">
          <textarea readonly rows="8" style="background:var(--bg);border:1px solid var(--border);border-radius:8px;color:#e0e0e0;padding:12px;font-size:.86rem;line-height:1.5;width:100%;resize:vertical;"><?= htmlspecialchars($texto_proposta) ?></textarea>
        </div>
        <div class="form-actions">
          <a href="https://wa.me/?text=<?= urlencode($texto_proposta) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp">💬 Enviar Proposta no WhatsApp</a>
          <a href="leads.php?servico=<?= urlencode($ativo) ?>#novo-lead" class="btn btn-primary">🎯 Registrar Lead deste Serviço</a>
          <a href="projetos.php?servico=<?= urlencode($ativo) ?>" class="btn btn-accent">🗂️ Abrir Projeto / Entrega</a>
          <a href="onboarding.php?servico=<?= urlencode($ativo) ?>" class="btn btn-outline">🔐 Iniciar Briefing</a>
          <a href="contrato.php?pacote=<?= urlencode($ativo) ?>" class="btn btn-outline">📄 Gerar Contrato</a>
        </div>
      </div>
    </div>
  </div>

  <!-- LEADS E PROJETOS VINCULADOS A ESTE SERVIÇO -->
  <div class="dashboard-split" style="grid-template-columns:repeat(auto-fit,minmax(320px,1fr));margin-top:0;">
    <div class="card">
      <div class="card-header">
        <h2 class="card-title">🎯 Leads Interessados em <?= htmlspecialchars($ativo) ?></h2>
        <a href="leads.php?servico=<?= urlencode($ativo) ?>" class="btn btn-outline btn-sm">+ Novo Lead</a>
      </div>
      <div class="table-wrap">
        <table class="table" style="min-width:0;">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Valor Est.</th>
              <th>Etapa</th>
              <th>Ação</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($leads_atual)): ?>
            <tr><td colspan="4" class="empty-row">Nenhum lead cadastrado neste serviço.</td></tr>
            <?php else: foreach($leads_atual as $l): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($l['nome']) ?></strong>
                <?php if(!empty($l['empresa'])): ?><br><span style="font-size:.78rem;color:var(--text-muted);"><?= htmlspecialchars($l['empresa']) ?></span><?php endif; ?>
              </td>
              <td><?= formatar_moeda(floatval($l['valor_estimado'] ?? 0)) ?></td>
              <td><span class="badge badge-<?= slug_status($l['status'] ?? 'Novo') ?>"><?= htmlspecialchars($l['status'] ?? 'Novo') ?></span></td>
              <td><a href="leads.php?editar=<?= urlencode($l['id']) ?>" class="btn btn-outline btn-sm">Editar</a></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h2 class="card-title">🗂️ Projetos em <?= htmlspecialchars($ativo) ?></h2>
        <a href="projetos.php?servico=<?= urlencode($ativo) ?>" class="btn btn-outline btn-sm">+ Novo Projeto</a>
      </div>
      <div class="table-wrap">
        <table class="table" style="min-width:0;">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Lucro Líq.</th>
              <th>Etapa</th>
              <th>Ação</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($projetos_atual)): ?>
            <tr><td colspan="4" class="empty-row">Nenhum projeto cadastrado neste serviço.</td></tr>
            <?php else: foreach($projetos_atual as $p): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($p['cliente']) ?></strong>
                <?php if(!empty($p['freelancer'])): ?><br><span style="font-size:.78rem;color:var(--text-muted);">Freela: <?= htmlspecialchars($p['freelancer']) ?></span><?php endif; ?>
              </td>
              <td class="lucro-cell"><?= formatar_moeda(floatval($p['lucro_liquido'] ?? 0)) ?></td>
              <td><span class="badge badge-proj-<?= slug_status($p['status'] ?? 'Briefing') ?>"><?= htmlspecialchars($p['status'] ?? 'Briefing') ?></span></td>
              <td><a href="projetos.php?editar=<?= urlencode($p['id']) ?>" class="btn btn-outline btn-sm">Abrir</a></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<div class="dev-sign">Desenvolvido por: Ruben Massuquetto</div>
</body>
</html>
