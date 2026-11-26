<?php
if (!defined('LOGO_URL')) {
  define('LOGO_URL', 'https://i.postimg.cc/Vkhy4Y6x/1.png');
}

function ler_json(string $arquivo): array {
  if (!file_exists($arquivo)) return [];
  $fp = fopen($arquivo, 'r');
  if (!$fp) return [];
  flock($fp, LOCK_SH);
  $conteudo = fread($fp, max(1, filesize($arquivo)));
  flock($fp, LOCK_UN);
  fclose($fp);
  $decoded = json_decode($conteudo, true);
  return is_array($decoded) ? $decoded : [];
}

function salvar_json(string $arquivo, array $dados): bool {
  $fp = fopen($arquivo, 'c+');
  if (!$fp) return false;
  flock($fp, LOCK_EX);
  ftruncate($fp, 0);
  rewind($fp);
  fwrite($fp, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
  flock($fp, LOCK_UN);
  fclose($fp);
  return true;
}

function verificar_sessao(): void {
  if (empty($_SESSION['logado'])) {
    header('Location: login.php');
    exit;
  }
}

function formatar_moeda(float $valor): string {
  return 'R$ ' . number_format($valor, 2, ',', '.');
}

function formatar_data(string $data): string {
  if (empty($data)) return '—';
  $dt = DateTime::createFromFormat('Y-m-d', substr($data, 0, 10));
  return $dt ? $dt->format('d/m/Y') : htmlspecialchars($data);
}

function validar_email(string $email): bool {
  return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function gerar_id(): string {
  return uniqid('', true);
}

function slug_status(string $status): string {
  $mapa = [
    'ç' => 'c', 'Ç' => 'c',
    'ã' => 'a', 'Ã' => 'a',
    'á' => 'a', 'Á' => 'a',
    'à' => 'a', 'À' => 'a',
    'â' => 'a', 'Â' => 'a',
    'é' => 'e', 'É' => 'e',
    'ê' => 'e', 'Ê' => 'e',
    'í' => 'i', 'Í' => 'i',
    'ó' => 'o', 'Ó' => 'o',
    'ô' => 'o', 'Ô' => 'o',
    'õ' => 'o', 'Õ' => 'o',
    'ú' => 'u', 'Ú' => 'u',
    '/' => '',  '&' => '',
    ' ' => '',
  ];
  return strtolower(strtr($status, $mapa));
}

function calcular_encargos(array $cobranca): array {
  $valor = floatval($cobranca['valor'] ?? 0);
  if (($cobranca['status'] ?? '') !== 'Atrasado') {
    return ['multa' => 0, 'juros' => 0, 'total' => $valor, 'dias' => 0, 'meses' => 0];
  }
  try {
    $venc = new DateTime($cobranca['vencimento']);
    $hoje = new DateTime(date('Y-m-d'));
    $dias = $hoje > $venc ? (int)$hoje->diff($venc)->days : 0;
  } catch (Exception $e) {
    $dias = 0;
  }
  $meses = max(1, (int)ceil($dias / 30));
  $multa = $valor * 0.02;
  $juros = $valor * 0.01 * $meses;
  $total = $valor + $multa + $juros;
  return ['multa' => $multa, 'juros' => $juros, 'total' => $total, 'dias' => $dias, 'meses' => $meses];
}

function catalogo_servicos(): array {
  return [
    'Sites & Landing Pages' => [
      'icone' => '💻',
      'modelo_cobranca' => 'Setup Único',
      'preco_base' => 3200.00,
      'custo_freelancer' => 1100.00,
      'prazo_dias' => 15,
      'status_oferta' => 'Ativo para Venda',
      'resumo' => 'Sua vitrine 24 horas precisa ser impecável. Desenvolvemos sites que não são apenas bonitos, mas máquinas de conversão projetadas para transformar visitantes em leads qualificados.',
      'pilares' => [
        ['titulo' => 'Design Conversivo', 'desc' => 'Copywriting persuasivo e UX focada em guiar o usuário para a ação.'],
        ['titulo' => 'Velocidade Máxima', 'desc' => 'Páginas otimizadas para carregar instantaneamente, reduzindo a taxa de rejeição.'],
        ['titulo' => 'Mobile-First', 'desc' => 'Experiência perfeita em smartphones, onde acontece a maioria das buscas hoje.'],
        ['titulo' => 'Gestão Autônoma', 'desc' => 'Painel intuitivo para você gerenciar conteúdos sem depender de agência.'],
      ],
      'beneficios' => [
        'Instalação de Pixels e Tags',
        'Hospedagem de Alta Performance',
        'Integração com WhatsApp',
        'Formulários Inteligentes',
      ],
      'perguntas_briefing' => [
        'Qual o objetivo principal da página (captura de leads, vendas, agendamento)?',
        'Já possui domínio e hospedagem registrados? Qual o endereço?',
        'Cite 2 ou 3 sites de referência visual que você admira.',
      ],
    ],
    'Gestão de Tráfego Pago' => [
      'icone' => '📣',
      'modelo_cobranca' => 'Mensalidade Recorrente',
      'preco_base' => 2500.00,
      'custo_freelancer' => 800.00,
      'prazo_dias' => 7,
      'status_oferta' => 'Ativo para Venda',
      'resumo' => 'Apareça para o público certo, no momento certo. Gerenciamos seus investimentos em Google Ads e Meta Ads com foco total em retorno sobre investimento (ROI).',
      'pilares' => [
        ['titulo' => 'Google Ads Estratégico', 'desc' => 'Apareça no topo das buscas quando alguém procurar exatamente pelo que você vende.'],
        ['titulo' => 'Meta Ads (Insta/Face)', 'desc' => 'Anúncios visuais que despertam desejo e capturam a atenção do seu público ideal.'],
        ['titulo' => 'Otimização Contínua', 'desc' => 'Testes A/B constantes para extrair o máximo de performance de cada centavo investido.'],
        ['titulo' => 'Relatórios de ROI', 'desc' => 'Transparência total com dashboards que mostram o lucro gerado pelas campanhas.'],
      ],
      'beneficios' => [
        'Segmentação de Público Alvo',
        'Remarketing Persistente',
        'Escala de Faturamento',
        'Campanhas de Leads Locais',
      ],
      'perguntas_briefing' => [
        'Qual a verba mensal prevista para investimento em mídia (Google/Meta)?',
        'Qual região geográfica (cidade, raio em km ou nacional) deseja atender?',
        'Qual o ticket médio do produto/serviço principal anunciado?',
      ],
    ],
    'SEO Local / GMN' => [
      'icone' => '📍',
      'modelo_cobranca' => 'Mensalidade Recorrente',
      'preco_base' => 1800.00,
      'custo_freelancer' => 600.00,
      'prazo_dias' => 10,
      'status_oferta' => 'Ativo para Venda',
      'resumo' => 'Seja a primeira opção do seu bairro ou cidade. Otimizamos sua presença no Google Meu Negócio e nas buscas regionais para atrair clientes que estão por perto.',
      'pilares' => [
        ['titulo' => 'Domínio do Maps', 'desc' => 'Apareça com destaque no Google Maps quando buscarem por serviços na sua região.'],
        ['titulo' => 'Otimização de Perfil', 'desc' => 'Configuração completa do GMN para gerar chamadas e rotas diretamente para você.'],
        ['titulo' => 'Palavras-Chave Locais', 'desc' => 'Foco nos termos que os seus vizinhos usam para encontrar empresas como a sua.'],
        ['titulo' => 'Autoridade Regional', 'desc' => 'Construção de citações e avaliações que validam seu negócio perante o Google.'],
      ],
      'beneficios' => [
        'Gestão de Avaliações',
        'Postagens Recorrentes no GMN',
        'Fotos Profissionais (Opcional)',
        'Relatório de Chamadas',
      ],
      'perguntas_briefing' => [
        'O perfil do Google Meu Negócio já está verificado em sua posse?',
        'Qual o endereço completo, telefone comercial e horário de funcionamento?',
        'Quais os principais bairros/cidades e serviços que deseja rankear?',
      ],
    ],
    'Branding & Visual' => [
      'icone' => '🎨',
      'modelo_cobranca' => 'Setup Único',
      'preco_base' => 4500.00,
      'custo_freelancer' => 1500.00,
      'prazo_dias' => 20,
      'status_oferta' => 'Ativo para Venda',
      'resumo' => 'O posicionamento de marca é o que diferencia uma empresa comum de uma autoridade. Criamos identidades visuais que comunicam valor e confiança instantânea.',
      'pilares' => [
        ['titulo' => 'Identidade Visual', 'desc' => 'Logotipos e paletas de cores que traduzem a alma do seu negócio.'],
        ['titulo' => 'Manual de Marca', 'desc' => 'Diretrizes para manter sua marca consistente em todos os canais digitais e físicos.'],
        ['titulo' => 'Design Premium', 'desc' => 'Assets visuais para redes sociais e anúncios que elevam a percepção de valor.'],
        ['titulo' => 'Estratégia de Voz', 'desc' => 'Definição de como sua marca deve falar para conectar emocionalmente com o cliente.'],
      ],
      'beneficios' => [
        'Design de Logotipo',
        'Paleta de Cores e Tipografia',
        'Brandbook Completo',
        'Assets para Social Media',
      ],
      'perguntas_briefing' => [
        'Quais valores e sensações a marca precisa transmitir ao público?',
        'Existem cores ou estilos visuais que você NÃO quer utilizar?',
        'Onde a marca será mais aplicada (site, fachada, embalagens, social media)?',
      ],
    ],
    'Social Media & Content' => [
      'icone' => '📱',
      'modelo_cobranca' => 'Mensalidade Recorrente',
      'preco_base' => 1200.00,
      'custo_freelancer' => 400.00,
      'prazo_dias' => 7,
      'status_oferta' => 'Ativo para Venda',
      'resumo' => 'Suas redes sociais não devem ser apenas um mural de fotos, mas um canal ativo de aquisição de clientes e construção de comunidade ao redor da sua marca.',
      'pilares' => [
        ['titulo' => 'Linha Editorial', 'desc' => 'Planejamento de conteúdo focado no que seu público realmente quer consumir.'],
        ['titulo' => 'Design de Conteúdo', 'desc' => 'Posts, Reels e Stories com alta qualidade visual e técnica.'],
        ['titulo' => 'Engajamento Ativo', 'desc' => 'Estratégias para aumentar a interação e fidelidade da sua base de seguidores.'],
        ['titulo' => 'Vitrine Estratégica', 'desc' => 'Transformação do perfil em uma ferramenta de vendas clara e objetiva.'],
      ],
      'beneficios' => [
        'Calendário de Postagens',
        'Gestão de Comunidade',
        'Roteiros para Vídeos',
        'Análise de Métricas Mensais',
      ],
      'perguntas_briefing' => [
        'Quais redes sociais serão gerenciadas e qual a frequência semanal desejada?',
        'Há alguém disponível na empresa para gravar vídeos/Reels com nossos roteiros?',
        'Quais as principais objeções e dúvidas que seus clientes têm antes de comprar?',
      ],
    ],
  ];
}

function servicos_comerciais(array $dados): array {
  $base = catalogo_servicos();
  $salvos = $dados['servicos_config'] ?? [];
  foreach ($base as $nome => $srv) {
    if (isset($salvos[$nome]) && is_array($salvos[$nome])) {
      foreach (['modelo_cobranca', 'preco_base', 'custo_freelancer', 'prazo_dias', 'status_oferta'] as $campo) {
        if (isset($salvos[$nome][$campo])) {
          $base[$nome][$campo] = $salvos[$nome][$campo];
        }
      }
    }
  }
  return $base;
}

function lista_nomes_servicos(): array {
  return array_keys(catalogo_servicos());
}

function enviar_email_smtp(string $para, string $assunto, string $mensagem): bool {
  $dados = ler_json('db.json');
  $smtp = $dados['smtp'] ?? [];
  $host = trim($smtp['host'] ?? '');
  $port = intval($smtp['port'] ?? 587);
  $user = trim($smtp['user'] ?? '');
  $pass = trim($smtp['pass'] ?? '');
  $from = trim($smtp['from'] ?? 'rubenmassuquetto1999@gmail.com');

  if (empty($host) || empty($user) || empty($pass)) {
    $headers = "From: {$from}\r\nReply-To: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    return @mail($para, $assunto, $mensagem, $headers);
  }

  $socket = @fsockopen(($port == 465 ? 'ssl://' : '') . $host, $port, $errno, $errstr, 15);
  if (!$socket) {
    $headers = "From: {$from}\r\nReply-To: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    return @mail($para, $assunto, $mensagem, $headers);
  }

  $get_response = function($socket) {
    $data = "";
    while ($str = fgets($socket, 515)) {
      $data .= $str;
      if (substr($str, 3, 1) === " ") break;
    }
    return $data;
  };

  $get_response($socket);
  fputs($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
  $get_response($socket);

  if ($port == 587) {
    fputs($socket, "STARTTLS\r\n");
    $get_response($socket);
    @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    fputs($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
    $get_response($socket);
  }

  fputs($socket, "AUTH LOGIN\r\n");
  $get_response($socket);
  fputs($socket, base64_encode($user) . "\r\n");
  $get_response($socket);
  fputs($socket, base64_encode($pass) . "\r\n");
  $res = $get_response($socket);

  if (strpos($res, '235') === false) {
    fclose($socket);
    $headers = "From: {$from}\r\nReply-To: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    return @mail($para, $assunto, $mensagem, $headers);
  }

  fputs($socket, "MAIL FROM: <{$user}>\r\n");
  $get_response($socket);
  fputs($socket, "RCPT TO: <{$para}>\r\n");
  $get_response($socket);
  fputs($socket, "DATA\r\n");
  $get_response($socket);

  $email_content = "From: MKT Massuquetto <{$user}>\r\n"
                 . "To: <{$para}>\r\n"
                 . "Subject: =?UTF-8?B?" . base64_encode($assunto) . "?=\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
                 . $mensagem . "\r\n.\r\n";

  fputs($socket, $email_content);
  $get_response($socket);
  fputs($socket, "QUIT\r\n");
  fclose($socket);
  return true;
}
