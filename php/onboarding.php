<?php
session_start();
require 'functions.php';
verificar_sessao();

$dados = ler_json('db.json');
if (!isset($dados['onboardings'])) $dados['onboardings'] = [];

$catalogo = catalogo_servicos();
$servicos = lista_nomes_servicos();
$servico_sel = $_GET['servico'] ?? ($_POST['servico'] ?? $servicos[0]);
if (!isset($catalogo[$servico_sel])) $servico_sel = $servicos[0];

// EXCLUIR
if (isset($_GET['excluir'])) {
  $id = $_GET['excluir'];
  $dados['onboardings'] = array_values(array_filter($dados['onboardings'], fn($o) => $o['id'] !== $id));
  salvar_json('db.json', $dados);
  header('Location: onboarding.php?msg=excluido'); exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id_edit = trim($_POST['id_edit'] ?? '');
  $cliente = trim($_POST['cliente'] ?? '');
  $servico = trim($_POST['servico'] ?? $servicos[0]);
  if (!isset($catalogo[$servico])) $servico = $servicos[0];

  if (empty($cliente)) {
    $erro = 'Informe o nome do cliente.';
  } else {
    $novo = [
      'id'      => $id_edit ?: gerar_id(),
      'cliente' => $cliente,
      'servico' => $servico,
      'respostas' => [
        'q1' => trim($_POST['q1'] ?? ''),
        'q2' => trim($_POST['q2'] ?? ''),
        'q3' => trim($_POST['q3'] ?? ''),
      ],
      'cofre' => [
        'google_ads_id' => trim($_POST['google_ads_id'] ?? ''),
        'meta_bm'       => trim($_POST['meta_bm'] ?? ''),
        'hospedagem'    => trim($_POST['hospedagem'] ?? ''),
        'dominio'       => trim($_POST['dominio'] ?? ''),
        'gmn'           => trim($_POST['gmn'] ?? ''),
      ],
      'traqueamento' => [
        'pixel_meta' => !empty($_POST['pixel_meta']),
        'capi'       => !empty($_POST['capi']),
        'gtm'        => !empty($_POST['gtm']),
        'ga4'        => !empty($_POST['ga4']),
      ],
      'atualizado_em' => date('Y-m-d H:i:s'),
    ];

    if ($id_edit) {
      foreach ($dados['onboardings'] as &$o) {
        if ($o['id'] === $id_edit) { $o = $novo; break; }
      }
      unset($o);
    } else {
      $dados['onboardings'][] = $novo;
    }
    salvar_json('db.json', $dados);
    header('Location: onboarding.php?msg=salvo'); exit;
  }
}

$edit_onb = null;
if (isset($_GET['editar'])) {
  foreach ($dados['onboardings'] as $o) {
    if ($o['id'] === $_GET['editar']) {
      $edit_onb = $o;
      $servico_sel = $o['servico'] ?? $servico_sel;
      break;
    }
  }
}

$perguntas = $catalogo[$servico_sel]['perguntas_briefing'];
$clientes_proj  = array_unique(array_column($dados['projetos'] ?? [], 'cliente'));
$clientes_leads = array_unique(array_column($dados['leads'] ?? [], 'empresa'));
$clientes = array_values(array_filter(array_unique(array_merge($clientes_proj, $clientes_leads))));
sort($clientes);

$msg_ok = $_GET['msg'] ?? '';
$lista = array_reverse($dados['onboardings']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MKT Massuquetto — Onboarding, Briefing &amp; Cofre</title>
<link rel="icon" type="image/png" href="<?= htmlspecialchars(LOGO_URL) ?>">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">Onboarding, Briefing &amp; Cofre de Acessos</h1>
      <span class="page-sub">Formulário dinâmico por serviço, acessos centralizados e trava obrigatória de traqueamento</span>
    </div>
  </div>

  <?php if($msg_ok === 'salvo'): ?><div class="alert alert-ok">✅ Onboarding e checklist de traqueamento salvos!</div><?php endif; ?>
  <?php if($msg_ok === 'excluido'): ?><div class="alert alert-ok">🗑️ Registro de onboarding removido.</div><?php endif; ?>
  <?php if($erro): ?><div class="alert alert-err">❌ <?= htmlspecialchars($erro) ?></div><?php endif; ?>

  <div class="servicos-tabs">
    <?php foreach($catalogo as $nome => $srv): ?>
    <a href="onboarding.php?servico=<?= urlencode($nome) ?><?= $edit_onb ? '&editar=' . urlencode($edit_onb['id']) : '' ?>"
       class="servico-tab <?= $nome === $servico_sel ? 'active' : '' ?>">
      <span><?= $srv['icone'] ?></span>
      <span><?= htmlspecialchars($nome) ?></span>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="card" style="margin-bottom:28px;">
    <div class="card-header">
      <h2 class="card-title"><?= $edit_onb ? '✏️ Editar Onboarding' : '📝 Novo Briefing Inteligente' ?> — <?= htmlspecialchars($servico_sel) ?></h2>
    </div>
    <form method="POST" action="onboarding.php?servico=<?= urlencode($servico_sel) ?>" class="form-grid">
      <input type="hidden" name="id_edit" value="<?= htmlspecialchars($edit_onb['id'] ?? '') ?>">
      <input type="hidden" name="servico" value="<?= htmlspecialchars($servico_sel) ?>">

      <div class="form-group">
        <label>Cliente *</label>
        <input type="text" name="cliente" required list="lista-clientes-onb"
          value="<?= htmlspecialchars($edit_onb['cliente'] ?? '') ?>"
          placeholder="Selecione ou digite o cliente">
        <datalist id="lista-clientes-onb">
          <?php foreach($clientes as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>

      <div class="form-group form-span-2">
        <label>1. <?= htmlspecialchars($perguntas[0]) ?></label>
        <input type="text" name="q1" value="<?= htmlspecialchars($edit_onb['respostas']['q1'] ?? '') ?>" placeholder="Resposta do cliente...">
      </div>

      <div class="form-group form-span-3">
        <label>2. <?= htmlspecialchars($perguntas[1]) ?></label>
        <input type="text" name="q2" value="<?= htmlspecialchars($edit_onb['respostas']['q2'] ?? '') ?>" placeholder="Resposta do cliente...">
      </div>

      <div class="form-group form-span-3">
        <label>3. <?= htmlspecialchars($perguntas[2]) ?></label>
        <input type="text" name="q3" value="<?= htmlspecialchars($edit_onb['respostas']['q3'] ?? '') ?>" placeholder="Resposta do cliente...">
      </div>

      <div class="form-group form-span-3" style="margin-top:6px;">
        <label style="color:var(--green);">🔐 Cofre de Acessos (sem enviar senhas por WhatsApp)</label>
      </div>

      <div class="form-group">
        <label>ID Google Ads</label>
        <input type="text" name="google_ads_id" value="<?= htmlspecialchars($edit_onb['cofre']['google_ads_id'] ?? '') ?>" placeholder="Ex: 123-456-7890">
      </div>
      <div class="form-group">
        <label>Meta Business Suite / BM</label>
        <input type="text" name="meta_bm" value="<?= htmlspecialchars($edit_onb['cofre']['meta_bm'] ?? '') ?>" placeholder="ID do Business Manager ou status">
      </div>
      <div class="form-group">
        <label>Hospedagem / Painel</label>
        <input type="text" name="hospedagem" value="<?= htmlspecialchars($edit_onb['cofre']['hospedagem'] ?? '') ?>" placeholder="Provedor e usuário de acesso">
      </div>
      <div class="form-group">
        <label>Domínio / DNS</label>
        <input type="text" name="dominio" value="<?= htmlspecialchars($edit_onb['cofre']['dominio'] ?? '') ?>" placeholder="Registro.br / Cloudflare">
      </div>
      <div class="form-group form-span-2">
        <label>Google Meu Negócio (GMN)</label>
        <input type="text" name="gmn" value="<?= htmlspecialchars($edit_onb['cofre']['gmn'] ?? '') ?>" placeholder="E-mail gerente ou status de verificação">
      </div>

      <div class="form-group form-span-3" style="margin-top:6px;">
        <label style="color:var(--green);">🚦 Checklist de Traqueamento (Trava Obrigatória Pré-Campanha)</label>
        <div class="check-grid" style="margin-top:4px;">
          <label class="check-card">
            <input type="checkbox" name="pixel_meta" <?= !empty($edit_onb['traqueamento']['pixel_meta']) ? 'checked' : '' ?>>
            <span>Pixel Meta instalado e disparando PageView/Lead</span>
          </label>
          <label class="check-card">
            <input type="checkbox" name="capi" <?= !empty($edit_onb['traqueamento']['capi']) ? 'checked' : '' ?>>
            <span>API de Conversões (CAPI) configurada</span>
          </label>
          <label class="check-card">
            <input type="checkbox" name="gtm" <?= !empty($edit_onb['traqueamento']['gtm']) ? 'checked' : '' ?>>
            <span>Google Tag Manager (GTM) publicado</span>
          </label>
          <label class="check-card">
            <input type="checkbox" name="ga4" <?= !empty($edit_onb['traqueamento']['ga4']) ? 'checked' : '' ?>>
            <span>Google Analytics 4 (GA4) com metas ativas</span>
          </label>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $edit_onb ? 'Atualizar Onboarding' : 'Salvar Onboarding &amp; Cofre' ?></button>
        <?php if($edit_onb): ?>
        <a href="onboarding.php" class="btn btn-outline">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Onboardings, Cofres e Trava de Traqueamento</h2>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Serviço</th>
            <th>Acessos no Cofre</th>
            <th>Traqueamento</th>
            <th>Status Trava</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($lista)): ?>
          <tr><td colspan="6" class="empty-row">Nenhum onboarding cadastrado.</td></tr>
          <?php else: ?>
          <?php foreach($lista as $onb): ?>
          <?php
            $t = $onb['traqueamento'] ?? [];
            $ok_count = (!empty($t['pixel_meta'])?1:0) + (!empty($t['capi'])?1:0) + (!empty($t['gtm'])?1:0) + (!empty($t['ga4'])?1:0);
            $pronto = ($ok_count === 4);
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($onb['cliente']) ?></strong></td>
            <td><span class="badge badge-service"><?= htmlspecialchars($onb['servico']) ?></span></td>
            <td style="font-size:.8rem;color:var(--text-muted);">
              Ads: <?= htmlspecialchars($onb['cofre']['google_ads_id'] ?: '—') ?> |
              BM: <?= htmlspecialchars($onb['cofre']['meta_bm'] ?: '—') ?><br>
              Host: <?= htmlspecialchars($onb['cofre']['hospedagem'] ?: '—') ?>
            </td>
            <td style="font-size:.82rem;">
              <?= !empty($t['pixel_meta']) ? '✅' : '⬜' ?> Pixel &nbsp;
              <?= !empty($t['capi']) ? '✅' : '⬜' ?> CAPI &nbsp;
              <?= !empty($t['gtm']) ? '✅' : '⬜' ?> GTM &nbsp;
              <?= !empty($t['ga4']) ? '✅' : '⬜' ?> GA4
            </td>
            <td>
              <?php if($pronto): ?>
                <span class="badge badge-fechado">🚀 Liberado p/ Tráfego (4/4)</span>
              <?php else: ?>
                <span class="badge badge-urg-alta">🔒 Travado (<?= $ok_count ?>/4)</span>
              <?php endif; ?>
            </td>
            <td class="actions-cell">
              <a href="onboarding.php?editar=<?= urlencode($onb['id']) ?>" class="btn btn-outline btn-sm">✏️</a>
              <a href="onboarding.php?excluir=<?= urlencode($onb['id']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Excluir este onboarding?')">🗑️</a>
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
