<?php
session_start();
require 'functions.php';
verificar_sessao();

$dados = ler_json('db.json');
if (!isset($dados['leads'])) $dados['leads'] = [];
if (!isset($dados['projetos'])) $dados['projetos'] = [];
$leads = &$dados['leads'];
$erro = '';

// EXCLUIR
if (isset($_GET['excluir'])) {
  $id = $_GET['excluir'];
  $dados['leads'] = array_values(array_filter($leads, fn($l) => $l['id'] !== $id));
  salvar_json('db.json', $dados);
  header('Location: leads.php?msg=excluido'); exit;
}

// CONVERTER LEAD EM PROJETO/CLIENTE
if (isset($_GET['converter'])) {
  $id = $_GET['converter'];
  $lead_conv = null;
  foreach ($dados['leads'] as &$l) {
    if ($l['id'] === $id) {
      $l['status'] = 'Fechado';
      $l['venda_cliente'] = 'Vendido';
      $lead_conv = $l;
      break;
    }
  }
  unset($l);
  if ($lead_conv) {
    $cliente_nome = !empty($lead_conv['empresa']) ? $lead_conv['empresa'] : $lead_conv['nome'];
    $valor = floatval($lead_conv['valor_estimado'] ?? 0);
    $novo_proj = [
      'id'                  => gerar_id(),
      'cliente'             => $cliente_nome,
      'servico'             => $lead_conv['servico'] ?? 'Sites & Landing Pages',
      'prazo_entrega'       => date('Y-m-d', strtotime('+15 days')),
      'freelancer'          => '',
      'valor_cobrado'       => $valor,
      'valor_freelancer'    => 0,
      'lucro_liquido'       => $valor,
      'status'              => 'Briefing',
      'url_homologacao'     => '',
      'uptime_status'       => 'Online / Rápido',
      'aprovacao_cliente'   => 'Em Análise',
      'comentarios_cliente' => '',
      'criado_em'           => date('Y-m-d H:i:s'),
    ];
    $dados['projetos'][] = $novo_proj;
    salvar_json('db.json', $dados);
    header('Location: projetos.php?editar=' . urlencode($novo_proj['id']) . '&msg=convertido');
    exit;
  }
  header('Location: leads.php');
  exit;
}

// SALVAR (novo ou editar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_edit = trim($_POST['id_edit'] ?? '');
  $novo = [
    'id'             => $id_edit ?: gerar_id(),
    'nome'           => trim($_POST['nome'] ?? ''),
    'empresa'        => trim($_POST['empresa'] ?? ''),
    'whatsapp'       => trim($_POST['whatsapp'] ?? ''),
    'servico'        => trim($_POST['servico'] ?? ''),
    'urgencia'       => trim($_POST['urgencia'] ?? 'Média'),
    'valor_estimado' => floatval(str_replace(',','.',str_replace('.','', $_POST['valor_estimado'] ?? '0'))),
    'observacoes'    => trim($_POST['observacoes'] ?? ''),
    'status'         => trim($_POST['status'] ?? 'Novo'),
    'venda_cliente'  => trim($_POST['venda_cliente'] ?? 'Em Atendimento'),
    'criado_em'      => $id_edit ? '' : date('Y-m-d H:i:s'),
  ];
  if (empty($novo['nome'])) {
    $erro = 'Nome é obrigatório.';
  } else {
    if ($id_edit) {
      foreach ($dados['leads'] as &$l) {
        if ($l['id'] === $id_edit) {
          $novo['criado_em'] = $l['criado_em'];
          $l = $novo;
          break;
        }
      }
      unset($l);
    } else {
      $dados['leads'][] = $novo;
    }
    salvar_json('db.json', $dados);
    header('Location: leads.php?msg=salvo'); exit;
  }
}

$msg_ok = $_GET['msg'] ?? '';
$filtro_status = $_GET['status'] ?? '';
$filtro_busca = strtolower(trim($_GET['busca'] ?? ''));
$servico_pre = $_GET['servico'] ?? '';

$edit_lead = null;
if (isset($_GET['editar'])) {
  foreach ($dados['leads'] as $l) {
    if ($l['id'] === $_GET['editar']) { $edit_lead = $l; break; }
  }
}

$lista = $dados['leads'];
if ($filtro_status) $lista = array_filter($lista, fn($l) => ($l['status'] ?? '') === $filtro_status);
if ($filtro_busca) $lista = array_filter($lista, fn($l) =>
  str_contains(strtolower($l['nome'] ?? ''), $filtro_busca) ||
  str_contains(strtolower($l['empresa'] ?? ''), $filtro_busca) ||
  str_contains(strtolower($l['observacoes'] ?? ''), $filtro_busca)
);
$lista = array_reverse(array_values($lista));

$servicos  = lista_nomes_servicos();
$urgencias = ['Alta', 'Média', 'Baixa'];
$statuses  = ['Novo', 'Qualificado', 'Proposta Enviada', 'Fechado', 'Perdido'];
$catalogo_com = servicos_comerciais($dados);
$servico_selecionado = $edit_lead['servico'] ?? ($servico_pre && in_array($servico_pre, $servicos) ? $servico_pre : $servicos[0]);
$valor_est_padrao = $edit_lead ? floatval($edit_lead['valor_estimado'] ?? 0) : ($servico_pre && isset($catalogo_com[$servico_pre]) ? floatval($catalogo_com[$servico_pre]['preco_base'] ?? 0) : 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MKT Massuquetto — CRM de Clientes e Interessados</title>
<link rel="icon" type="image/png" href="<?= htmlspecialchars(LOGO_URL) ?>">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">CRM de Clientes e Interessados</h1>
      <span class="page-sub">Gerencie leads, urgência, observações e converta em Projeto/Cliente com 1 clique</span>
    </div>
    <a href="servicos.php" class="btn btn-outline">💼 Tabela &amp; Escopo de Serviços</a>
  </div>

  <?php if($msg_ok === 'salvo'): ?><div class="alert alert-ok">✅ Lead salvo com sucesso!</div><?php endif; ?>
  <?php if($msg_ok === 'excluido'): ?><div class="alert alert-ok">🗑️ Lead removido.</div><?php endif; ?>
  <?php if($erro): ?><div class="alert alert-err">❌ <?= htmlspecialchars($erro) ?></div><?php endif; ?>

  <div class="card" style="margin-bottom:24px;" id="novo-lead">
    <div class="card-header">
      <h2 class="card-title"><?= $edit_lead ? '✏️ Editar Interessado' : '➕ Novo Interessado' ?></h2>
    </div>
    <form method="POST" action="leads.php" class="form-grid">
      <input type="hidden" name="id_edit" value="<?= htmlspecialchars($edit_lead['id'] ?? '') ?>">
      <div class="form-group">
        <label>Nome *</label>
        <input type="text" name="nome" required value="<?= htmlspecialchars($edit_lead['nome'] ?? '') ?>" placeholder="Nome completo">
      </div>
      <div class="form-group">
        <label>Empresa</label>
        <input type="text" name="empresa" value="<?= htmlspecialchars($edit_lead['empresa'] ?? '') ?>" placeholder="Razão social ou nome fantasia">
      </div>
      <div class="form-group">
        <label>WhatsApp</label>
        <input type="text" name="whatsapp" value="<?= htmlspecialchars($edit_lead['whatsapp'] ?? '') ?>" placeholder="11999999999">
      </div>
      <div class="form-group">
        <label>Serviço Desejado</label>
        <select name="servico">
          <?php foreach($servicos as $s): ?>
          <option <?= ($servico_selecionado === $s) ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Nível de Urgência</label>
        <select name="urgencia">
          <?php foreach($urgencias as $u): ?>
          <option <?= (($edit_lead['urgencia'] ?? 'Média') === $u) ? 'selected' : '' ?>><?= $u ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Valor Estimado (R$)</label>
        <input type="text" name="valor_estimado" value="<?= number_format($valor_est_padrao, 2, ',', '.') ?>" placeholder="0,00">
      </div>
      <div class="form-group">
        <label>Etapa</label>
        <select name="status">
          <?php foreach($statuses as $s): ?>
          <option <?= (($edit_lead['status'] ?? 'Novo') === $s) ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group form-span-2">
        <label>Observações</label>
        <input type="text" name="observacoes" value="<?= htmlspecialchars($edit_lead['observacoes'] ?? '') ?>" placeholder="Detalhes da demanda, canal de origem ou próximos passos">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $edit_lead ? 'Atualizar Interessado' : 'Cadastrar Interessado' ?></button>
        <?php if($edit_lead): ?>
        <a href="leads.php" class="btn btn-outline">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Interessados Cadastrados</h2>
      <div class="filter-row">
        <form method="GET" action="leads.php" class="filter-form">
          <input type="text" name="busca" placeholder="🔍 Buscar..." value="<?= htmlspecialchars($filtro_busca) ?>">
          <select name="status">
            <option value="">Todas as etapas</option>
            <?php foreach($statuses as $s): ?>
            <option <?= $filtro_status === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
          <a href="leads.php" class="btn btn-ghost btn-sm">Limpar</a>
        </form>
      </div>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Empresa</th>
            <th>Serviço</th>
            <th>Urgência</th>
            <th>Valor Est.</th>
            <th>Etapa</th>
            <th>Observações</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($lista)): ?>
          <tr><td colspan="8" class="empty-row">Nenhum interessado encontrado.</td></tr>
          <?php else: ?>
          <?php foreach($lista as $lead): ?>
          <?php
            $wn = preg_replace('/\D/', '', $lead['whatsapp'] ?? '');
            $urg = $lead['urgencia'] ?? 'Média';
          ?>
          <tr>
            <td><?= htmlspecialchars($lead['nome']) ?></td>
            <td><?= htmlspecialchars($lead['empresa'] ?? '') ?></td>
            <td><span class="badge badge-service"><?= htmlspecialchars($lead['servico']) ?></span></td>
            <td><span class="badge badge-urg-<?= slug_status($urg) ?>"><?= htmlspecialchars($urg) ?></span></td>
            <td><?= formatar_moeda(floatval($lead['valor_estimado'] ?? 0)) ?></td>
            <td><span class="badge badge-status badge-<?= slug_status($lead['status']) ?>"><?= htmlspecialchars($lead['status']) ?></span></td>
            <td class="obs-cell" title="<?= htmlspecialchars($lead['observacoes'] ?? '') ?>"><?= htmlspecialchars($lead['observacoes'] ?? '—') ?></td>
            <td class="actions-cell">
              <a href="https://wa.me/55<?= $wn ?>" target="_blank" class="btn btn-whatsapp btn-sm" title="Abrir WhatsApp">💬</a>
              <a href="leads.php?converter=<?= urlencode($lead['id']) ?>" class="btn btn-success btn-sm" title="Transformar em Projeto/Cliente" onclick="return confirm('Converter este lead em Projeto/Cliente?')">🚀 Projeto</a>
              <a href="leads.php?editar=<?= urlencode($lead['id']) ?>" class="btn btn-outline btn-sm" title="Editar">✏️</a>
              <a href="leads.php?excluir=<?= urlencode($lead['id']) ?>" class="btn btn-danger btn-sm" title="Excluir" onclick="return confirm('Excluir este lead?')">🗑️</a>
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
