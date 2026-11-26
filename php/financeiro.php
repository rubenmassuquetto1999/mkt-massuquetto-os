<?php
session_start();
require 'functions.php';
verificar_sessao();

$dados = ler_json('db.json');
if (!isset($dados['financeiro'])) $dados['financeiro'] = [];
$erro = '';

// MARCAR COMO PAGO
if (isset($_GET['pagar'])) {
  $id = $_GET['pagar'];
  foreach ($dados['financeiro'] as &$f) {
    if ($f['id'] === $id) {
      $f['status'] = 'Pago';
      $f['pago_em'] = date('Y-m-d H:i:s');
      break;
    }
  }
  unset($f);
  salvar_json('db.json', $dados);
  header('Location: financeiro.php?msg=pago'); exit;
}

// EXCLUIR
if (isset($_GET['excluir'])) {
  $id = $_GET['excluir'];
  $dados['financeiro'] = array_values(array_filter($dados['financeiro'], fn($f) => $f['id'] !== $id));
  salvar_json('db.json', $dados);
  header('Location: financeiro.php?msg=excluido'); exit;
}

// SALVAR (novo ou editar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_edit = trim($_POST['id_edit'] ?? '');
  $novo = [
    'id'          => $id_edit ?: gerar_id(),
    'cliente'     => trim($_POST['cliente'] ?? ''),
    'tipo'        => trim($_POST['tipo'] ?? 'Mensalidade Recorrente'),
    'valor'       => floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0'))),
    'vencimento'  => trim($_POST['vencimento'] ?? ''),
    'status'      => trim($_POST['status'] ?? 'Pendente'),
    'criado_em'   => $id_edit ? '' : date('Y-m-d H:i:s'),
    'pago_em'     => '',
  ];
  if (empty($novo['cliente'])) {
    $erro = 'Cliente é obrigatório.';
  } elseif (empty($novo['vencimento'])) {
    $erro = 'Data de vencimento é obrigatória.';
  } else {
    if ($id_edit) {
      foreach ($dados['financeiro'] as &$f) {
        if ($f['id'] === $id_edit) {
          $novo['criado_em'] = $f['criado_em'];
          $novo['pago_em']   = $f['pago_em'] ?? '';
          $f = $novo;
          break;
        }
      }
      unset($f);
    } else {
      $dados['financeiro'][] = $novo;
    }
    salvar_json('db.json', $dados);
    header('Location: financeiro.php?msg=salvo'); exit;
  }
}

$msg_ok = $_GET['msg'] ?? '';
$edit_fin = null;
if (isset($_GET['editar'])) {
  foreach ($dados['financeiro'] as $f) {
    if ($f['id'] === $_GET['editar']) { $edit_fin = $f; break; }
  }
}

$filtro_status = $_GET['status'] ?? '';
$lista = $dados['financeiro'];
if ($filtro_status) $lista = array_filter($lista, fn($f) => ($f['status'] ?? '') === $filtro_status);
$lista = array_reverse(array_values($lista));

// Totalizadores
$total_pago     = array_sum(array_column(array_filter($dados['financeiro'], fn($f) => ($f['status'] ?? '') === 'Pago'), 'valor'));
$total_pendente = array_sum(array_column(array_filter($dados['financeiro'], fn($f) => ($f['status'] ?? '') === 'Pendente'), 'valor'));
$total_atrasado = 0;
foreach (array_filter($dados['financeiro'], fn($f) => ($f['status'] ?? '') === 'Atrasado') as $fa) {
  $enc = calcular_encargos($fa);
  $total_atrasado += $enc['total'];
}

$clientes_proj  = array_unique(array_column($dados['projetos'] ?? [], 'cliente'));
$clientes_leads = array_unique(array_column($dados['leads'] ?? [], 'nome'));
$clientes = array_unique(array_merge($clientes_proj, $clientes_leads));
sort($clientes);

$tipos    = ['Taxa de Setup Única', 'Mensalidade Recorrente'];
$statuses = ['Pendente', 'Pago', 'Atrasado'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MKT Massuquetto — Financeiro</title>
<link rel="icon" type="image/png" href="<?= htmlspecialchars(LOGO_URL) ?>">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-wrapper">
  <div class="page-header">
    <h1 class="page-title">Financeiro: Setup e Mensalidades</h1>
    <span class="page-sub">Cobranças, mensalidades recorrentes e cálculo automático de multa (2%) e juros (1% a.m.)</span>
  </div>

  <?php if($msg_ok === 'salvo'):    ?><div class="alert alert-ok">✅ Cobrança salva com sucesso!</div><?php endif; ?>
  <?php if($msg_ok === 'excluido'): ?><div class="alert alert-ok">🗑️ Cobrança removida.</div><?php endif; ?>
  <?php if($msg_ok === 'pago'):     ?><div class="alert alert-ok">💚 Cobrança marcada como Pago!</div><?php endif; ?>
  <?php if($erro):                  ?><div class="alert alert-err">❌ <?= htmlspecialchars($erro) ?></div><?php endif; ?>

  <!-- Totalizadores -->
  <div class="cards-grid" style="margin-bottom:28px;">
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(0,231,193,.12);color:#00E7C1;">✅</div>
      <div class="indicator-info">
        <span class="indicator-label">Total Recebido</span>
        <span class="indicator-value" style="color:#00E7C1;"><?= formatar_moeda($total_pago) ?></span>
      </div>
    </div>
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(255,180,0,.1);color:#ffb400;">⏳</div>
      <div class="indicator-info">
        <span class="indicator-label">A Receber</span>
        <span class="indicator-value"><?= formatar_moeda($total_pendente) ?></span>
      </div>
    </div>
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(255,91,91,.1);color:#ff5b5b;">🚨</div>
      <div class="indicator-info">
        <span class="indicator-label">Em Atraso (c/ encargos)</span>
        <span class="indicator-value" style="color:#ff5b5b;"><?= formatar_moeda($total_atrasado) ?></span>
      </div>
    </div>
    <div class="card indicator-card">
      <div class="indicator-icon" style="background:rgba(108,58,227,.15);color:#9b6dff;">📊</div>
      <div class="indicator-info">
        <span class="indicator-label">Total Cobranças</span>
        <span class="indicator-value"><?= count($dados['financeiro']) ?></span>
      </div>
    </div>
  </div>

  <!-- Formulário -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-header">
      <h2 class="card-title"><?= $edit_fin ? '✏️ Editar Cobrança' : '➕ Nova Cobrança' ?></h2>
    </div>
    <form method="POST" action="financeiro.php" class="form-grid">
      <input type="hidden" name="id_edit" value="<?= htmlspecialchars($edit_fin['id'] ?? '') ?>">
      <div class="form-group">
        <label>Cliente *</label>
        <input type="text" name="cliente" required list="lista-clientes"
          value="<?= htmlspecialchars($edit_fin['cliente'] ?? '') ?>"
          placeholder="Nome do cliente">
        <datalist id="lista-clientes">
          <?php foreach($clientes as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-group">
        <label>Tipo</label>
        <select name="tipo">
          <?php foreach($tipos as $t): ?>
          <option <?= (($edit_fin['tipo'] ?? 'Mensalidade Recorrente') === $t) ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Valor (R$)</label>
        <input type="text" name="valor"
          value="<?= number_format(floatval($edit_fin['valor'] ?? 0), 2, ',', '.') ?>"
          placeholder="0,00">
      </div>
      <div class="form-group">
        <label>Data de Vencimento *</label>
        <input type="date" name="vencimento" required
          value="<?= htmlspecialchars($edit_fin['vencimento'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <?php foreach($statuses as $s): ?>
          <option <?= (($edit_fin['status'] ?? 'Pendente') === $s) ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $edit_fin ? 'Atualizar Cobrança' : 'Cadastrar Cobrança' ?></button>
        <?php if($edit_fin): ?>
        <a href="financeiro.php" class="btn btn-outline">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Lista -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Cobranças Cadastradas</h2>
      <div class="filter-row">
        <form method="GET" action="financeiro.php" class="filter-form">
          <select name="status">
            <option value="">Todos os status</option>
            <?php foreach($statuses as $s): ?>
            <option <?= $filtro_status === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-outline btn-sm">Filtrar</button>
          <a href="financeiro.php" class="btn btn-ghost btn-sm">Limpar</a>
        </form>
      </div>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Tipo</th>
            <th>Valor</th>
            <th>Vencimento</th>
            <th>Multa/Juros</th>
            <th>Total Atualizado</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($lista)): ?>
          <tr><td colspan="8" class="empty-row">Nenhuma cobrança encontrada.</td></tr>
          <?php else: ?>
          <?php foreach($lista as $fin): ?>
          <?php
            $enc = calcular_encargos($fin);
            $status_slug = slug_status($fin['status']);
          ?>
          <tr>
            <td><?= htmlspecialchars($fin['cliente']) ?></td>
            <td><?= htmlspecialchars($fin['tipo']) ?></td>
            <td><?= formatar_moeda(floatval($fin['valor'])) ?></td>
            <td>
              <?= formatar_data($fin['vencimento']) ?>
              <?php if(($fin['status'] ?? '') === 'Atrasado' && ($enc['dias'] ?? 0) > 0): ?>
                <br><small style="color:#ff5b5b;font-size:.72rem;"><?= $enc['dias'] ?> dias em atraso (<?= $enc['meses'] ?> mês/es)</small>
              <?php endif; ?>
            </td>
            <td>
              <?php if(($fin['status'] ?? '') === 'Atrasado'): ?>
                <span style="color:#ff5b5b;font-size:.82rem;">
                  Multa 2%: <?= formatar_moeda($enc['multa']) ?><br>
                  Juros 1% a.m.: <?= formatar_moeda($enc['juros']) ?>
                </span>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:.82rem;">—</span>
              <?php endif; ?>
            </td>
            <td class="lucro-cell"><?= formatar_moeda($enc['total']) ?></td>
            <td>
              <span class="badge badge-fin-<?= $status_slug ?>"><?= htmlspecialchars($fin['status']) ?></span>
            </td>
            <td class="actions-cell">
              <?php if(($fin['status'] ?? '') !== 'Pago'): ?>
              <a href="financeiro.php?pagar=<?= urlencode($fin['id']) ?>"
                 class="btn btn-success btn-sm"
                 onclick="return confirm('Marcar como Pago?')">✅ Pago</a>
              <?php endif; ?>
              <a href="financeiro.php?editar=<?= urlencode($fin['id']) ?>" class="btn btn-outline btn-sm">✏️</a>
              <a href="financeiro.php?excluir=<?= urlencode($fin['id']) ?>"
                 class="btn btn-danger btn-sm"
                 onclick="return confirm('Excluir esta cobrança?')">🗑️</a>
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
