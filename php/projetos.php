<?php
session_start();
require 'functions.php';
verificar_sessao();

$dados = ler_json('db.json');
if (!isset($dados['projetos'])) $dados['projetos'] = [];
$erro = '';

// EXCLUIR
if (isset($_GET['excluir'])) {
  $id = $_GET['excluir'];
  $dados['projetos'] = array_values(array_filter($dados['projetos'], fn($p) => $p['id'] !== $id));
  salvar_json('db.json', $dados);
  header('Location: projetos.php?msg=excluido'); exit;
}

// MOVER ETAPA RAPIDAMENTE NO KANBAN
if (isset($_GET['mover']) && isset($_GET['etapa'])) {
  $id = $_GET['mover'];
  $nova_etapa = trim($_GET['etapa']);
  foreach ($dados['projetos'] as &$p) {
    if ($p['id'] === $id) {
      $p['status'] = $nova_etapa;
      break;
    }
  }
  unset($p);
  salvar_json('db.json', $dados);
  header('Location: projetos.php?view=kanban&msg=salvo'); exit;
}

// SALVAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_edit = trim($_POST['id_edit'] ?? '');
  $cobrado = floatval(str_replace(',','.',str_replace('.','', $_POST['valor_cobrado'] ?? '0')));
  $pago    = floatval(str_replace(',','.',str_replace('.','', $_POST['valor_freelancer'] ?? '0')));
  $novo = [
    'id'                  => $id_edit ?: gerar_id(),
    'cliente'             => trim($_POST['cliente'] ?? ''),
    'servico'             => trim($_POST['servico'] ?? ''),
    'prazo_entrega'       => trim($_POST['prazo_entrega'] ?? ''),
    'freelancer'          => trim($_POST['freelancer'] ?? ''),
    'valor_cobrado'       => $cobrado,
    'valor_freelancer'    => $pago,
    'lucro_liquido'       => $cobrado - $pago,
    'status'              => trim($_POST['status'] ?? 'Briefing'),
    'url_homologacao'     => trim($_POST['url_homologacao'] ?? ''),
    'uptime_status'       => trim($_POST['uptime_status'] ?? 'Online / Rápido'),
    'aprovacao_cliente'   => trim($_POST['aprovacao_cliente'] ?? 'Em Análise'),
    'comentarios_cliente' => trim($_POST['comentarios_cliente'] ?? ''),
    'criado_em'           => $id_edit ? '' : date('Y-m-d H:i:s'),
  ];
  if (empty($novo['cliente'])) {
    $erro = 'Cliente é obrigatório.';
  } else {
    if ($id_edit) {
      foreach ($dados['projetos'] as &$p) {
        if ($p['id'] === $id_edit) {
          $novo['criado_em'] = $p['criado_em'];
          $p = $novo;
          break;
        }
      }
      unset($p);
    } else {
      $dados['projetos'][] = $novo;
    }
    salvar_json('db.json', $dados);
    header('Location: projetos.php?msg=salvo'); exit;
  }
}

$msg_ok = $_GET['msg'] ?? '';
$view   = $_GET['view'] ?? 'lista';
$edit_proj = null;
if (isset($_GET['editar'])) {
  foreach ($dados['projetos'] as $p) {
    if ($p['id'] === $_GET['editar']) { $edit_proj = $p; break; }
  }
}

$filtro_status = $_GET['status'] ?? '';
$lista = $dados['projetos'];
if ($filtro_status) $lista = array_filter($lista, fn($p) => ($p['status'] ?? '') === $filtro_status);
$lista = array_reverse(array_values($lista));

$servicos = lista_nomes_servicos();
$statuses = [
  'Briefing',
  'Copywriting & UI/UX',
  'Desenvolvimento / Execução',
  'Velocidade & Traqueamento',
  'Revisão / Aprovação',
  'Entregue',
];
$uptimes = ['Online / Rápido', 'Lento', 'Fora do Ar — Pausar Tráfego'];
$aprovacoes = ['Em Análise', 'Ajustes Solicitados', 'Aprovado'];

$alertas_uptime = array_filter($dados['projetos'], fn($p) => ($p['uptime_status'] ?? 'Online / Rápido') !== 'Online / Rápido');
$catalogo_com = servicos_comerciais($dados);
$servico_pre = $_GET['servico'] ?? '';
$servico_sel = $edit_proj['servico'] ?? ($servico_pre && in_array($servico_pre, $servicos) ? $servico_pre : $servicos[0]);
$prazo_padrao = $edit_proj['prazo_entrega'] ?? ($servico_pre && isset($catalogo_com[$servico_pre]) ? date('Y-m-d', strtotime('+' . intval($catalogo_com[$servico_pre]['prazo_dias'] ?? 15) . ' days')) : '');
$cobrado_padrao = $edit_proj ? floatval($edit_proj['valor_cobrado'] ?? 0) : ($servico_pre && isset($catalogo_com[$servico_pre]) ? floatval($catalogo_com[$servico_pre]['preco_base'] ?? 0) : 0);
$freela_padrao = $edit_proj ? floatval($edit_proj['valor_freelancer'] ?? 0) : ($servico_pre && isset($catalogo_com[$servico_pre]) ? floatval($catalogo_com[$servico_pre]['custo_freelancer'] ?? 0) : 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MKT Massuquetto — Projetos e Esteira</title>
<link rel="icon" type="image/png" href="<?= htmlspecialchars(LOGO_URL) ?>">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">Projetos de Alta Conversão &amp; Freelancers</h1>
      <span class="page-sub">Esteira Kanban, aprovação visual, uptime de páginas e lucro líquido</span>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="projetos.php?view=lista" class="btn <?= $view === 'lista' ? 'btn-primary' : 'btn-outline' ?> btn-sm">📋 Lista</a>
      <a href="projetos.php?view=kanban" class="btn <?= $view === 'kanban' ? 'btn-primary' : 'btn-outline' ?> btn-sm">🗂️ Esteira Kanban</a>
    </div>
  </div>

  <?php if($msg_ok === 'salvo'): ?><div class="alert alert-ok">✅ Projeto salvo com sucesso!</div><?php endif; ?>
  <?php if($msg_ok === 'convertido'): ?><div class="alert alert-ok">🚀 Lead convertido em projeto! Defina freelancer, prazo e link de homologação.</div><?php endif; ?>
  <?php if($msg_ok === 'excluido'): ?><div class="alert alert-ok">🗑️ Projeto removido.</div><?php endif; ?>
  <?php if($erro): ?><div class="alert alert-err">❌ <?= htmlspecialchars($erro) ?></div><?php endif; ?>

  <?php if(!empty($alertas_uptime)): ?>
  <div class="alert alert-warn">
    ⚠️ <strong>Alerta de Uptime / Velocidade:</strong>
    <?php foreach($alertas_uptime as $al): ?>
      <span><strong><?= htmlspecialchars($al['cliente']) ?></strong> (<?= htmlspecialchars($al['uptime_status']) ?>)</span> &nbsp;
    <?php endforeach; ?>
    — Verifique a página antes de manter tráfego pago ativo!
  </div>
  <?php endif; ?>

  <div class="card" style="margin-bottom:24px;">
    <div class="card-header">
      <h2 class="card-title"><?= $edit_proj ? '✏️ Editar Projeto' : '➕ Novo Projeto' ?></h2>
    </div>
    <form method="POST" action="projetos.php" class="form-grid">
      <input type="hidden" name="id_edit" value="<?= htmlspecialchars($edit_proj['id'] ?? '') ?>">
      <div class="form-group">
        <label>Cliente *</label>
        <input type="text" name="cliente" required value="<?= htmlspecialchars($edit_proj['cliente'] ?? '') ?>" placeholder="Nome do cliente">
      </div>
      <div class="form-group">
        <label>Serviço Contratado</label>
        <select name="servico">
          <?php foreach($servicos as $s): ?>
          <option <?= ($servico_sel === $s) ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Prazo de Entrega</label>
        <input type="date" name="prazo_entrega" value="<?= htmlspecialchars($prazo_padrao) ?>">
      </div>
      <div class="form-group">
        <label>Freelancer Responsável</label>
        <input type="text" name="freelancer" value="<?= htmlspecialchars($edit_proj['freelancer'] ?? '') ?>" placeholder="Nome do freelancer">
      </div>
      <div class="form-group">
        <label>Valor Cobrado (R$)</label>
        <input type="text" name="valor_cobrado" value="<?= number_format($cobrado_padrao, 2, ',', '.') ?>" placeholder="0,00">
      </div>
      <div class="form-group">
        <label>Valor Pago ao Freelancer (R$)</label>
        <input type="text" name="valor_freelancer" value="<?= number_format($freela_padrao, 2, ',', '.') ?>" placeholder="0,00">
      </div>
      <div class="form-group">
        <label>Etapa da Esteira</label>
        <select name="status">
          <?php foreach($statuses as $s): ?>
          <option <?= (($edit_proj['status'] ?? 'Briefing') === $s) ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Link de Homologação / Protótipo</label>
        <input type="text" name="url_homologacao" value="<?= htmlspecialchars($edit_proj['url_homologacao'] ?? '') ?>" placeholder="https://preview.mktmassuquetto.com/...">
      </div>
      <div class="form-group">
        <label>Monitoramento Uptime &amp; Velocidade</label>
        <select name="uptime_status">
          <?php foreach($uptimes as $u): ?>
          <option <?= (($edit_proj['uptime_status'] ?? 'Online / Rápido') === $u) ? 'selected' : '' ?>><?= $u ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Aprovação Visual do Cliente</label>
        <select name="aprovacao_cliente">
          <?php foreach($aprovacoes as $a): ?>
          <option <?= (($edit_proj['aprovacao_cliente'] ?? 'Em Análise') === $a) ? 'selected' : '' ?>><?= $a ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group form-span-2">
        <label>Comentários / Solicitações de Alteração</label>
        <input type="text" name="comentarios_cliente" value="<?= htmlspecialchars($edit_proj['comentarios_cliente'] ?? '') ?>" placeholder="Feedback do protótipo ou alterações solicitadas">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $edit_proj ? 'Atualizar Projeto' : 'Cadastrar Projeto' ?></button>
        <?php if($edit_proj): ?>
        <a href="projetos.php" class="btn btn-outline">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <?php if($view === 'kanban'): ?>
  <div class="kanban-grid">
    <?php foreach($statuses as $col): ?>
    <?php $itens = array_values(array_filter($dados['projetos'], fn($p) => ($p['status'] ?? 'Briefing') === $col)); ?>
    <div class="kanban-col">
      <div class="kanban-col-header">
        <span><?= htmlspecialchars($col) ?></span>
        <span class="badge badge-service"><?= count($itens) ?></span>
      </div>
      <?php if(empty($itens)): ?>
        <span style="font-size:.78rem;color:var(--text-muted);text-align:center;padding:18px 0;">Vazio</span>
      <?php else: ?>
        <?php foreach($itens as $proj): ?>
        <div class="kanban-card">
          <div class="kanban-card-title"><?= htmlspecialchars($proj['cliente']) ?></div>
          <div><span class="badge badge-service"><?= htmlspecialchars($proj['servico']) ?></span></div>
          <div class="kanban-card-meta">Prazo: <?= formatar_data($proj['prazo_entrega'] ?? '') ?></div>
          <div class="kanban-card-meta">FL: <?= htmlspecialchars($proj['freelancer'] ?: '—') ?> | Lucro: <strong style="color:var(--green);"><?= formatar_moeda(floatval($proj['lucro_liquido'])) ?></strong></div>
          <div class="kanban-card-meta">
            <span class="badge badge-uptime-<?= slug_status($proj['uptime_status'] ?? 'Online / Rápido') ?>"><?= htmlspecialchars($proj['uptime_status'] ?? 'Online / Rápido') ?></span>
          </div>
          <?php if(!empty($proj['comentarios_cliente'])): ?>
          <div class="kanban-card-meta" style="color:#dcd0ff;">💬 <?= htmlspecialchars($proj['comentarios_cliente']) ?></div>
          <?php endif; ?>
          <div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:4px;">
            <a href="projetos.php?editar=<?= urlencode($proj['id']) ?>" class="btn btn-outline btn-sm">✏️</a>
            <?php
              $idx = array_search($col, $statuses);
              if ($idx !== false && isset($statuses[$idx + 1])):
                $prox = $statuses[$idx + 1];
            ?>
            <a href="projetos.php?mover=<?= urlencode($proj['id']) ?>&etapa=<?= urlencode($prox) ?>" class="btn btn-success btn-sm">Avançar →</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <?php else: ?>
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Lista de Projetos</h2>
      <div class="filter-row">
        <form method="GET" action="projetos.php" class="filter-form">
          <select name="status">
            <option value="">Todas as etapas</option>
            <?php foreach($statuses as $s): ?>
            <option <?= $filtro_status === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
          <a href="projetos.php" class="btn btn-ghost btn-sm">Limpar</a>
        </form>
      </div>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Serviço</th>
            <th>Prazo</th>
            <th>Freelancer</th>
            <th>Lucro Líquido</th>
            <th>Uptime</th>
            <th>Aprovação</th>
            <th>Etapa</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($lista)): ?>
          <tr><td colspan="9" class="empty-row">Nenhum projeto encontrado.</td></tr>
          <?php else: ?>
          <?php foreach($lista as $proj): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($proj['cliente']) ?></strong>
              <?php if(!empty($proj['url_homologacao'])): ?>
              <br><a href="<?= htmlspecialchars($proj['url_homologacao']) ?>" target="_blank" style="font-size:.75rem;color:var(--green);">🔗 Homologação</a>
              <?php endif; ?>
            </td>
            <td><span class="badge badge-service"><?= htmlspecialchars($proj['servico']) ?></span></td>
            <td><?= formatar_data($proj['prazo_entrega'] ?? '') ?></td>
            <td><?= htmlspecialchars($proj['freelancer'] ?: '—') ?></td>
            <td class="lucro-cell"><?= formatar_moeda(floatval($proj['lucro_liquido'])) ?></td>
            <td><span class="badge badge-uptime-<?= slug_status($proj['uptime_status'] ?? 'Online / Rápido') ?>"><?= htmlspecialchars($proj['uptime_status'] ?? 'Online / Rápido') ?></span></td>
            <td><span class="badge badge-proj-<?= slug_status($proj['aprovacao_cliente'] ?? 'Em Análise') ?>"><?= htmlspecialchars($proj['aprovacao_cliente'] ?? 'Em Análise') ?></span></td>
            <td><span class="badge badge-proj-<?= slug_status($proj['status']) ?>"><?= htmlspecialchars($proj['status']) ?></span></td>
            <td class="actions-cell">
              <a href="projetos.php?editar=<?= urlencode($proj['id']) ?>" class="btn btn-outline btn-sm">✏️</a>
              <a href="projetos.php?excluir=<?= urlencode($proj['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Excluir este projeto?')">🗑️</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<div class="dev-sign">Desenvolvido por: Ruben</div>
</body>
</html>
