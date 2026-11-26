<?php
$atual = basename($_SERVER['PHP_SELF']);
$logo = defined('LOGO_URL') ? LOGO_URL : 'https://i.postimg.cc/Vkhy4Y6x/1.png';
?>
<nav class="navbar">
  <a href="index.php" class="navbar-brand">
    <img src="<?= htmlspecialchars($logo) ?>" alt="MKT Massuquetto" class="brand-logo">
    <span class="brand-name">MKT <strong>Massuquetto</strong></span>
  </a>
  <div class="navbar-links">
    <a href="index.php" class="nav-link <?= $atual === 'index.php' ? 'active' : '' ?>">📊 Painel</a>
    <a href="servicos.php" class="nav-link <?= $atual === 'servicos.php' ? 'active' : '' ?>">💼 Serviços</a>
    <a href="leads.php" class="nav-link <?= $atual === 'leads.php' ? 'active' : '' ?>">🎯 CRM</a>
    <a href="onboarding.php" class="nav-link <?= $atual === 'onboarding.php' ? 'active' : '' ?>">🔐 Onboarding</a>
    <a href="projetos.php" class="nav-link <?= $atual === 'projetos.php' ? 'active' : '' ?>">🗂️ Projetos</a>
    <a href="performance.php" class="nav-link <?= $atual === 'performance.php' ? 'active' : '' ?>">📈 Tráfego</a>
    <a href="financeiro.php" class="nav-link <?= $atual === 'financeiro.php' ? 'active' : '' ?>">💳 Financeiro</a>
    <a href="contrato.php" class="nav-link <?= $atual === 'contrato.php' ? 'active' : '' ?>">📄 Contrato</a>
    <a href="portal.php" class="nav-link <?= $atual === 'portal.php' ? 'active' : '' ?>">🌐 Portal</a>
    <a href="configuracoes.php" class="nav-link <?= $atual === 'configuracoes.php' ? 'active' : '' ?>">⚙️ SMTP</a>
  </div>
  <div class="navbar-user">
    <span class="user-info">👤 <?= htmlspecialchars($_SESSION['usuario'] ?? 'Admin') ?></span>
    <a href="logout.php" class="btn btn-ghost btn-sm">Sair</a>
  </div>
</nav>
