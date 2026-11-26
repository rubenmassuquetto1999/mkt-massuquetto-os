<?php
session_start();
require 'functions.php';
verificar_sessao();

$dados = ler_json('db.json');

$clientes_proj  = array_unique(array_column($dados['projetos'] ?? [], 'cliente'));
$clientes_leads = array_unique(array_column($dados['leads'] ?? [], 'empresa'));
$clientes = array_values(array_filter(array_unique(array_merge($clientes_proj, $clientes_leads))));
sort($clientes);

$gerar = $_SERVER['REQUEST_METHOD'] === 'POST';

$cliente      = trim($_POST['cliente'] ?? '');
$catalogo_com = servicos_comerciais($dados);
$pacote = trim($_POST['pacote'] ?? ($_GET['pacote'] ?? ''));
$setup_pre = 0.0;
$mensal_pre = 0.0;
if (!$gerar && $pacote && isset($catalogo_com[$pacote])) {
  $srv_pre = $catalogo_com[$pacote];
  if (($srv_pre['modelo_cobranca'] ?? '') === 'Setup Único') {
    $setup_pre = floatval($srv_pre['preco_base'] ?? 0);
  } else {
    $mensal_pre = floatval($srv_pre['preco_base'] ?? 0);
  }
}
$valor_setup  = $gerar ? floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor_setup'] ?? '0'))) : $setup_pre;
$valor_mensal = $gerar ? floatval(str_replace(',', '.', str_replace('.', '', $_POST['valor_mensal'] ?? '0'))) : $mensal_pre;
$dia_venc     = intval($_POST['dia_vencimento'] ?? 10);
$prazo        = intval($_POST['prazo'] ?? 12);
$data_hoje    = date('d/m/Y');
$data_inicio  = date('d/m/Y');

$data_fim = '';
if ($gerar) {
  $dt = new DateTime();
  $dt->modify("+{$prazo} months");
  $data_fim = $dt->format('d/m/Y');
}

$servicos = array_merge(lista_nomes_servicos(), ['Gestão Completa (Site + Tráfego)', 'Consultoria']);
$prazos   = [3, 6, 12];

function extenso_meses(int $m): string {
  $ext = [1=>'um',2=>'dois',3=>'três',4=>'quatro',5=>'cinco',6=>'seis',
          7=>'sete',8=>'oito',9=>'nove',10=>'dez',11=>'onze',12=>'doze'];
  return $ext[$m] ?? (string)$m;
}

// =========================================================================
// GERADOR DE PDF A4 EM PHP PURO (Sem bibliotecas externas / Sem frameworks)
// =========================================================================
function pdf_encode(string $utf8): string {
  $map = [
    "\xE2\x80\x94" => "\x97",
    "\xE2\x80\x93" => "\x96",
    "\xE2\x80\x9C" => "\x93",
    "\xE2\x80\x9D" => "\x94",
    "\xE2\x80\x98" => "\x91",
    "\xE2\x80\x99" => "\x92",
    "\xE2\x80\xA2" => "\x95",
  ];
  $utf8 = strtr($utf8, $map);
  $out = '';
  $len = strlen($utf8);
  for ($i = 0; $i < $len; $i++) {
    $b = ord($utf8[$i]);
    if ($b < 0x80) {
      $ch = $utf8[$i];
    } elseif (($b & 0xE0) === 0xC0 && $i + 1 < $len) {
      $cp = (($b & 0x1F) << 6) | (ord($utf8[$i + 1]) & 0x3F);
      $i++;
      $ch = ($cp <= 0xFF) ? chr($cp) : '?';
    } elseif (($b & 0xF0) === 0xE0 && $i + 2 < $len) {
      $i += 2;
      $ch = '?';
    } elseif (($b & 0xF8) === 0xF0 && $i + 3 < $len) {
      $i += 3;
      $ch = '?';
    } else {
      $ch = '?';
    }
    if ($ch === '\\' || $ch === '(' || $ch === ')') {
      $out .= '\\' . $ch;
    } else {
      $out .= $ch;
    }
  }
  return $out;
}

function pdf_text_width(string $utf8, float $fontSize): float {
  $chars = preg_split('//u', $utf8, -1, PREG_SPLIT_NO_EMPTY);
  $w = 0.0;
  foreach ($chars as $c) {
    if ($c === ' ') {
      $w += 0.28;
    } elseif (strpos('ilIjtfr.,:;!\'`1()[]|-ªº', $c) !== false) {
      $w += 0.30;
    } elseif (strpos('MWmwOQGDUH@%', $c) !== false) {
      $w += 0.78;
    } elseif (preg_match('/[A-ZÁÀÂÃÉÊÍÓÔÕÚÇ]/u', $c)) {
      $w += 0.66;
    } else {
      $w += 0.53;
    }
  }
  return $w * $fontSize;
}

function pdf_wrap_lines(string $text, float $fontSize, float $maxWidth): array {
  $words = preg_split('/\s+/u', trim($text));
  if (!$words) return [];
  $lines = [];
  $cur = '';
  foreach ($words as $word) {
    $test = $cur === '' ? $word : ($cur . ' ' . $word);
    if (pdf_text_width($test, $fontSize) <= $maxWidth || $cur === '') {
      $cur = $test;
    } else {
      $lines[] = $cur;
      $cur = $word;
    }
  }
  if ($cur !== '') $lines[] = $cur;
  return $lines;
}

function gerar_pdf_contrato_a4(array $info): string {
  $pageW = 595.28;
  $pageH = 841.89;
  $marginL = 52.0;
  $marginR = 52.0;
  $usableW = $pageW - $marginL - $marginR; // 491.28 pt
  $topY = $pageH - 54.0;
  $bottomY = 62.0;

  $clausulas = [
    [
      'titulo' => 'IDENTIFICAÇÃO DAS PARTES',
      'paragrafos' => [
        'CONTRATADA: MKT Massuquetto, representada por Ruben Massuquetto, com sede na cidade de Campo Largo, Estado do Paraná, Foro de Campo Largo - PR, doravante denominada simplesmente CONTRATADA.',
        'CONTRATANTE: ' . $info['cliente'] . ', doravante denominado simplesmente CONTRATANTE.',
      ],
    ],
    [
      'titulo' => 'CLÁUSULA 1ª — DO OBJETO',
      'paragrafos' => [
        'O presente contrato tem por objeto a prestação de serviços de Marketing Digital pela CONTRATADA ao CONTRATANTE, compreendendo o pacote denominado ' . $info['pacote'] . ', incluindo todas as atividades de planejamento, execução, monitoramento e otimização pertinentes ao escopo acordado entre as partes.',
      ],
    ],
    [
      'titulo' => 'CLÁUSULA 2ª — DO PRAZO DE VIGÊNCIA',
      'paragrafos' => [
        'O presente contrato vigorará pelo prazo de ' . $info['prazo'] . ' (' . extenso_meses($info['prazo']) . ') meses, com início em ' . $info['data_inicio'] . ' e término previsto em ' . $info['data_fim'] . ', podendo ser renovado mediante acordo escrito entre as partes com antecedência mínima de 30 (trinta) dias antes do vencimento.',
      ],
    ],
    [
      'titulo' => 'CLÁUSULA 3ª — DO VALOR E FORMA DE PAGAMENTO',
      'paragrafos' => array_values(array_filter([
        $info['valor_setup'] > 0
          ? '3.1 Taxa de Setup: Pelo início dos serviços, o CONTRATANTE pagará à CONTRATADA o valor de ' . formatar_moeda($info['valor_setup']) . ' a título de taxa de setup, devida no ato da assinatura deste instrumento.'
          : '',
        ($info['valor_setup'] > 0 ? '3.2' : '3.1') . ' Mensalidade: Pelos serviços mensais prestados, o CONTRATANTE pagará à CONTRATADA o valor de ' . formatar_moeda($info['valor_mensal']) . ' mensais, com vencimento todo dia ' . $info['dia_venc'] . ' de cada mês, a partir do mês subsequente à assinatura.',
        ($info['valor_setup'] > 0 ? '3.3' : '3.2') . ' Forma de Pagamento: O pagamento deverá ser realizado por transferência bancária (PIX, TED ou DOC) para conta indicada pela CONTRATADA, ou outra forma acordada entre as partes.',
      ])),
    ],
    [
      'titulo' => 'CLÁUSULA 4ª — DO ATRASO NO PAGAMENTO',
      'paragrafos' => [
        'O atraso no pagamento das parcelas implicará multa de 2% (dois por cento) sobre o valor da parcela em atraso, acrescida de juros moratórios de 1% (um por cento) ao mês, calculados pro rata die a partir do vencimento até a data do efetivo pagamento, sem prejuízo de demais encargos legais.',
      ],
    ],
    [
      'titulo' => 'CLÁUSULA 5ª — DAS OBRIGAÇÕES DA CONTRATADA',
      'paragrafos' => [
        'A CONTRATADA obriga-se a: (i) executar os serviços com diligência, competência e dedicação; (ii) manter sigilo sobre as informações do CONTRATANTE; (iii) comunicar imediatamente qualquer impedimento que comprometa a execução dos serviços; (iv) prestar relatórios mensais de desempenho quando aplicável ao escopo; (v) zelar pela imagem e reputação digital do CONTRATANTE.',
      ],
    ],
    [
      'titulo' => 'CLÁUSULA 6ª — DAS OBRIGAÇÕES DO CONTRATANTE',
      'paragrafos' => [
        'O CONTRATANTE obriga-se a: (i) efetuar os pagamentos nos prazos acordados; (ii) fornecer à CONTRATADA todos os materiais, acessos e informações necessários para a execução dos serviços; (iii) aprovar ou solicitar revisão de materiais produzidos em até 5 (cinco) dias úteis; (iv) não contratar terceiros para serviços idênticos aos previstos neste contrato durante sua vigência sem anuência prévia da CONTRATADA.',
      ],
    ],
    [
      'titulo' => 'CLÁUSULA 7ª — DA RESCISÃO',
      'paragrafos' => [
        '7.1 Qualquer das partes poderá rescindir o presente contrato mediante notificação escrita com antecedência mínima de 30 (trinta) dias.',
        '7.2 A rescisão antecipada por iniciativa do CONTRATANTE, sem justa causa, implicará o pagamento de multa equivalente a 20% (vinte por cento) do valor total remanescente do contrato.',
        '7.3 A rescisão por inadimplência do CONTRATANTE poderá ser efetuada pela CONTRATADA imediatamente, sem necessidade de aviso prévio.',
      ],
    ],
    [
      'titulo' => 'CLÁUSULA 8ª — DA PROPRIEDADE INTELECTUAL',
      'paragrafos' => [
        'Todos os materiais criados pela CONTRATADA durante a vigência deste contrato serão de propriedade do CONTRATANTE somente após a quitação integral de todos os valores devidos. Durante o inadimplemento, a CONTRATADA reserva-se o direito de suspender os serviços e materiais sem qualquer ônus.',
      ],
    ],
    [
      'titulo' => 'CLÁUSULA 9ª — DA CONFIDENCIALIDADE',
      'paragrafos' => [
        'Ambas as partes comprometem-se a manter em sigilo absoluto todas as informações confidenciais obtidas em razão deste contrato, não podendo divulgá-las a terceiros sem prévia autorização escrita da outra parte, sob pena de responsabilização civil e criminal.',
      ],
    ],
    [
      'titulo' => 'CLÁUSULA 10ª — DO FORO',
      'paragrafos' => [
        'As partes elegem o Foro da Comarca de Campo Largo, Estado do Paraná, como competente para dirimir quaisquer dúvidas ou litígios oriundos do presente contrato, com renúncia expressa a qualquer outro, por mais privilegiado que seja.',
      ],
    ],
  ];

  $pages = [];
  $curStream = '';
  $y = $topY;

  $drawText = function (string $font, float $size, float $x, float $ty, string $txt, array $rgb = [0.1, 0.1, 0.1]) use (&$curStream): void {
    $enc = pdf_encode($txt);
    $curStream .= sprintf(
      "BT /%s %.2f Tf %.3f %.3f %.3f rg 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
      $font,
      $size,
      $rgb[0],
      $rgb[1],
      $rgb[2],
      $x,
      $ty,
      $enc
    );
  };

  $drawCentered = function (string $font, float $size, float $ty, string $txt, array $rgb = [0.1, 0.1, 0.1]) use ($pageW, $drawText): void {
    $w = pdf_text_width($txt, $size);
    $x = max(40.0, ($pageW - $w) / 2.0);
    $drawText($font, $size, $x, $ty, $txt, $rgb);
  };

  $newPage = function () use (&$pages, &$curStream, &$y, $topY): void {
    if ($curStream !== '') {
      $pages[] = $curStream;
    }
    $curStream = '';
    $y = $topY;
  };

  // Cabeçalho na Página 1 (faixa superior roxa discreta + título centralizado)
  $curStream .= sprintf("0.424 0.227 0.890 rg 0 %.2f %.2f 6 re f\n", $pageH - 6, $pageW);
  $drawCentered('F2', 13.0, $y, 'CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE MARKETING DIGITAL', [0.12, 0.12, 0.14]);
  $y -= 17.0;
  $drawCentered('F1', 9.5, $y, 'MKT Massuquetto — Ruben Massuquetto  |  Campo Largo - PR', [0.38, 0.38, 0.42]);
  $y -= 12.0;
  $curStream .= sprintf("0.78 0.78 0.82 RG 0.8 w %.2f %.2f m %.2f %.2f l S\n", $marginL, $y, $pageW - $marginR, $y);
  $y -= 22.0;

  // Renderiza cada cláusula mantendo título + corpo juntos sem quebra brusca
  $titleSize = 10.0;
  $bodySize = 10.0;
  $lineH = 14.2;

  foreach ($clausulas as $cl) {
    $wrappedParas = [];
    $totalLines = 0;
    foreach ($cl['paragrafos'] as $p) {
      $lines = pdf_wrap_lines($p, $bodySize, $usableW);
      $wrappedParas[] = $lines;
      $totalLines += count($lines);
    }
    $blockHeight = 16.0 + ($totalLines * $lineH) + (count($wrappedParas) * 5.0) + 10.0;

    if (($y - $blockHeight) < $bottomY) {
      $newPage();
    }

    $drawText('F2', $titleSize, $marginL, $y, $cl['titulo'], [0.35, 0.18, 0.72]);
    $y -= 15.0;

    foreach ($wrappedParas as $lines) {
      foreach ($lines as $ln) {
        if (($y - $lineH) < $bottomY) {
          $newPage();
        }
        $drawText('F1', $bodySize, $marginL, $y, $ln, [0.12, 0.12, 0.12]);
        $y -= $lineH;
      }
      $y -= 4.5;
    }
    $y -= 6.0;
  }

  // Bloco completo de data e assinaturas mantido sempre inteiro na mesma folha A4
  $sigBlockHeight = 115.0;
  if (($y - $sigBlockHeight) < $bottomY) {
    $newPage();
  }

  $y -= 10.0;
  $drawCentered('F1', 9.5, $y, 'Campo Largo - PR, ' . $info['data_hoje'] . '.', [0.28, 0.28, 0.30]);
  $y -= 52.0;

  $colW = 215.0;
  $leftLineX1 = $marginL + 10.0;
  $leftLineX2 = $leftLineX1 + $colW;
  $rightLineX2 = $pageW - $marginR - 10.0;
  $rightLineX1 = $rightLineX2 - $colW;

  $curStream .= sprintf(
    "0.25 0.25 0.25 RG 0.8 w %.2f %.2f m %.2f %.2f l S %.2f %.2f m %.2f %.2f l S\n",
    $leftLineX1, $y, $leftLineX2, $y,
    $rightLineX1, $y, $rightLineX2, $y
  );

  $drawColCenter = function (string $font, float $size, float $x1, float $x2, float $ty, string $txt, array $rgb = [0.12, 0.12, 0.12]) use ($drawText): void {
    $w = pdf_text_width($txt, $size);
    $x = $x1 + max(0.0, (($x2 - $x1) - $w) / 2.0);
    $drawText($font, $size, $x, $ty, $txt, $rgb);
  };

  $y -= 13.0;
  $drawColCenter('F2', 9.5, $leftLineX1, $leftLineX2, $y, 'MKT Massuquetto');
  $drawColCenter('F2', 9.5, $rightLineX1, $rightLineX2, $y, $info['cliente']);
  $y -= 12.0;
  $drawColCenter('F1', 8.8, $leftLineX1, $leftLineX2, $y, 'Ruben Massuquetto — CONTRATADA', [0.35, 0.35, 0.38]);
  $drawColCenter('F1', 8.8, $rightLineX1, $rightLineX2, $y, 'CONTRATANTE', [0.35, 0.35, 0.38]);

  $pages[] = $curStream;
  $totalPages = count($pages);

  // Rodapé com numeração de página em cada folha A4
  for ($p = 0; $p < $totalPages; $p++) {
    $footerTxt = 'MKT Massuquetto  |  Página ' . ($p + 1) . ' de ' . $totalPages;
    $fw = pdf_text_width($footerTxt, 8.0);
    $fx = ($pageW - $fw) / 2.0;
    $pages[$p] .= sprintf(
      "BT /F1 8.00 Tf 0.50 0.50 0.54 rg 1 0 0 1 %.2f 28.00 Tm (%s) Tj ET\n",
      $fx,
      pdf_encode($footerTxt)
    );
  }

  // Montagem binária padrão PDF 1.4
  $objects = [];
  $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
  $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
  $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

  $kidsRefs = [];
  $nextObj = 5;
  foreach ($pages as $stream) {
    $pageObj = $nextObj++;
    $contentObj = $nextObj++;
    $kidsRefs[] = "{$pageObj} 0 R";
    $objects[$pageObj] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentObj} 0 R >>";
    $objects[$contentObj] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
  }
  $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $kidsRefs) . "] /Count {$totalPages} >>";
  ksort($objects);

  $pdf = "%PDF-1.4\n";
  $offsets = [];
  foreach ($objects as $num => $body) {
    $offsets[$num] = strlen($pdf);
    $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
  }

  $xrefOffset = strlen($pdf);
  $maxObj = max(array_keys($objects));
  $pdf .= "xref\n0 " . ($maxObj + 1) . "\n";
  $pdf .= "0000000000 65535 f \n";
  for ($i = 1; $i <= $maxObj; $i++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
  }
  $pdf .= "trailer\n<< /Size " . ($maxObj + 1) . " /Root 1 0 R >>\n";
  $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

  return $pdf;
}

if ($gerar && ($_POST['acao'] ?? '') === 'baixar_pdf' && $cliente && $pacote) {
  $pdfBytes = gerar_pdf_contrato_a4([
    'cliente'      => $cliente,
    'pacote'       => $pacote,
    'valor_setup'  => $valor_setup,
    'valor_mensal' => $valor_mensal,
    'dia_venc'     => $dia_venc,
    'prazo'        => $prazo,
    'data_inicio'  => $data_inicio,
    'data_fim'     => $data_fim,
    'data_hoje'    => $data_hoje,
  ]);
  $slugCli = preg_replace('/[^a-zA-Z0-9_-]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $cliente) ?: 'cliente');
  $nomeArquivo = 'Contrato-' . trim($slugCli, '-') . '-A4.pdf';
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
  header('Content-Length: ' . strlen($pdfBytes));
  header('Cache-Control: no-store, no-cache, must-revalidate');
  echo $pdfBytes;
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MKT Massuquetto — Gerador de Contrato</title>
<link rel="icon" type="image/png" href="<?= htmlspecialchars(LOGO_URL) ?>">
<link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">Gerador de Contrato</h1>
      <span class="page-sub">Gere o contrato de prestação de serviços de Marketing Digital da MKT Massuquetto em folha A4</span>
    </div>
  </div>

  <div class="card" style="margin-bottom:28px;">
    <div class="card-header">
      <h2 class="card-title">📋 Dados do Contrato</h2>
    </div>
    <form method="POST" action="contrato.php" class="form-grid">
      <div class="form-group">
        <label>Cliente / Contratante *</label>
        <input type="text" name="cliente" required list="lista-clientes"
          value="<?= htmlspecialchars($cliente) ?>"
          placeholder="Selecione ou digite o cliente">
        <datalist id="lista-clientes">
          <?php foreach($clientes as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-group">
        <label>Pacote / Serviços Inclusos *</label>
        <input type="text" name="pacote" required list="lista-servicos"
          value="<?= htmlspecialchars($pacote) ?>"
          placeholder="Ex: Gestão de Tráfego Pago">
        <datalist id="lista-servicos">
          <?php foreach($servicos as $s): ?>
          <option value="<?= htmlspecialchars($s) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>
      <div class="form-group">
        <label>Valor do Setup (R$)</label>
        <input type="text" name="valor_setup"
          value="<?= number_format($valor_setup, 2, ',', '.') ?>"
          placeholder="0,00">
      </div>
      <div class="form-group">
        <label>Valor da Mensalidade (R$) *</label>
        <input type="text" name="valor_mensal" required
          value="<?= ($gerar || $valor_mensal > 0) ? number_format($valor_mensal, 2, ',', '.') : '' ?>"
          placeholder="0,00">
      </div>
      <div class="form-group">
        <label>Dia do Vencimento</label>
        <select name="dia_vencimento">
          <?php for($d = 1; $d <= 28; $d++): ?>
          <option <?= ($gerar && $dia_venc === $d) ? 'selected' : (!$gerar && $d === 10 ? 'selected' : '') ?>><?= $d ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Prazo de Vigência</label>
        <select name="prazo">
          <?php foreach($prazos as $p): ?>
          <option value="<?= $p ?>" <?= ($gerar && $prazo === $p) ? 'selected' : (!$gerar && $p === 12 ? 'selected' : '') ?>><?= $p ?> meses</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">📄 Gerar Contrato</button>
      </div>
    </form>
  </div>

  <?php if($gerar && $cliente && $pacote): ?>
  <div class="contrato-print-btn">
    <form method="POST" action="contrato.php" style="display:inline;">
      <input type="hidden" name="acao" value="baixar_pdf">
      <input type="hidden" name="cliente" value="<?= htmlspecialchars($cliente) ?>">
      <input type="hidden" name="pacote" value="<?= htmlspecialchars($pacote) ?>">
      <input type="hidden" name="valor_setup" value="<?= number_format($valor_setup, 2, ',', '.') ?>">
      <input type="hidden" name="valor_mensal" value="<?= number_format($valor_mensal, 2, ',', '.') ?>">
      <input type="hidden" name="dia_vencimento" value="<?= $dia_venc ?>">
      <input type="hidden" name="prazo" value="<?= $prazo ?>">
      <button type="submit" class="btn btn-outline">🖨️ Imprimir / Salvar PDF (A4)</button>
    </form>
    <button type="button" onclick="window.print()" class="btn btn-ghost">🖨️ Imprimir na Tela</button>
  </div>

  <div class="contrato-output" id="contrato-doc">
    <div style="text-align:center;margin-bottom:14px;">
      <img src="<?= htmlspecialchars(LOGO_URL) ?>" alt="MKT Massuquetto" style="max-height:64px;max-width:180px;object-fit:contain;border-radius:8px;">
    </div>
    <h1>CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE MARKETING DIGITAL</h1>
    <span class="contrato-sub">MKT Massuquetto — Ruben Massuquetto &nbsp;|&nbsp; Campo Largo - PR</span>

    <div class="contrato-clausula">
      <h2>Identificação das Partes</h2>
      <p>
        <strong>CONTRATADA:</strong> MKT Massuquetto, representada por <strong>Ruben Massuquetto</strong>,
        com sede na cidade de Campo Largo, Estado do Paraná, Foro de Campo Largo - PR,
        doravante denominada simplesmente <strong>CONTRATADA</strong>.
      </p>
      <p>
        <strong>CONTRATANTE:</strong> <?= htmlspecialchars($cliente) ?>,
        doravante denominado simplesmente <strong>CONTRATANTE</strong>.
      </p>
    </div>

    <div class="contrato-clausula">
      <h2>Cláusula 1ª — Do Objeto</h2>
      <p>
        O presente contrato tem por objeto a prestação de serviços de Marketing Digital pela CONTRATADA
        ao CONTRATANTE, compreendendo o pacote denominado <strong><?= htmlspecialchars($pacote) ?></strong>,
        incluindo todas as atividades de planejamento, execução, monitoramento e otimização
        pertinentes ao escopo acordado entre as partes.
      </p>
    </div>

    <div class="contrato-clausula">
      <h2>Cláusula 2ª — Do Prazo de Vigência</h2>
      <p>
        O presente contrato vigorará pelo prazo de <strong><?= $prazo ?> (<?= extenso_meses($prazo) ?>)</strong> meses,
        com início em <strong><?= $data_inicio ?></strong> e término previsto em <strong><?= $data_fim ?></strong>,
        podendo ser renovado mediante acordo escrito entre as partes com antecedência mínima de 30 (trinta) dias
        antes do vencimento.
      </p>
    </div>

    <div class="contrato-clausula">
      <h2>Cláusula 3ª — Do Valor e Forma de Pagamento</h2>
      <?php if($valor_setup > 0): ?>
      <p>
        <strong>3.1 Taxa de Setup:</strong> Pelo início dos serviços, o CONTRATANTE pagará à CONTRATADA
        o valor de <strong><?= formatar_moeda($valor_setup) ?></strong> a título de taxa de setup,
        devida no ato da assinatura deste instrumento.
      </p>
      <?php endif; ?>
      <p>
        <strong><?= $valor_setup > 0 ? '3.2' : '3.1' ?> Mensalidade:</strong>
        Pelos serviços mensais prestados, o CONTRATANTE pagará à CONTRATADA
        o valor de <strong><?= formatar_moeda($valor_mensal) ?></strong> mensais,
        com vencimento todo dia <strong><?= $dia_venc ?></strong> de cada mês,
        a partir do mês subsequente à assinatura.
      </p>
      <p>
        <strong><?= $valor_setup > 0 ? '3.3' : '3.2' ?> Forma de Pagamento:</strong>
        O pagamento deverá ser realizado por transferência bancária (PIX, TED ou DOC)
        para conta indicada pela CONTRATADA, ou outra forma acordada entre as partes.
      </p>
    </div>

    <div class="contrato-clausula">
      <h2>Cláusula 4ª — Do Atraso no Pagamento</h2>
      <p>
        O atraso no pagamento das parcelas implicará multa de <strong>2% (dois por cento)</strong>
        sobre o valor da parcela em atraso, acrescida de juros moratórios de
        <strong>1% (um por cento) ao mês</strong>, calculados pro rata die a partir do vencimento
        até a data do efetivo pagamento, sem prejuízo de demais encargos legais.
      </p>
    </div>

    <div class="contrato-clausula">
      <h2>Cláusula 5ª — Das Obrigações da Contratada</h2>
      <p>
        A CONTRATADA obriga-se a: (i) executar os serviços com diligência, competência e
        dedicação; (ii) manter sigilo sobre as informações do CONTRATANTE; (iii) comunicar
        imediatamente qualquer impedimento que comprometa a execução dos serviços;
        (iv) prestar relatórios mensais de desempenho quando aplicável ao escopo;
        (v) zelar pela imagem e reputação digital do CONTRATANTE.
      </p>
    </div>

    <div class="contrato-clausula">
      <h2>Cláusula 6ª — Das Obrigações do Contratante</h2>
      <p>
        O CONTRATANTE obriga-se a: (i) efetuar os pagamentos nos prazos acordados;
        (ii) fornecer à CONTRATADA todos os materiais, acessos e informações necessários
        para a execução dos serviços; (iii) aprovar ou solicitar revisão de materiais
        produzidos em até 5 (cinco) dias úteis; (iv) não contratar terceiros para
        serviços idênticos aos previstos neste contrato durante sua vigência
        sem anuência prévia da CONTRATADA.
      </p>
    </div>

    <div class="contrato-clausula">
      <h2>Cláusula 7ª — Da Rescisão</h2>
      <p>
        <strong>7.1</strong> Qualquer das partes poderá rescindir o presente contrato
        mediante notificação escrita com antecedência mínima de <strong>30 (trinta) dias</strong>.
      </p>
      <p>
        <strong>7.2</strong> A rescisão antecipada por iniciativa do CONTRATANTE, sem justa causa,
        implicará o pagamento de multa equivalente a <strong>20% (vinte por cento)</strong>
        do valor total remanescente do contrato.
      </p>
      <p>
        <strong>7.3</strong> A rescisão por inadimplência do CONTRATANTE poderá ser efetuada
        pela CONTRATADA imediatamente, sem necessidade de aviso prévio.
      </p>
    </div>

    <div class="contrato-clausula">
      <h2>Cláusula 8ª — Da Propriedade Intelectual</h2>
      <p>
        Todos os materiais criados pela CONTRATADA durante a vigência deste contrato
        serão de propriedade do CONTRATANTE somente após a quitação integral de todos
        os valores devidos. Durante o inadimplemento, a CONTRATADA reserva-se o direito
        de suspender os serviços e materiais sem qualquer ônus.
      </p>
    </div>

    <div class="contrato-clausula">
      <h2>Cláusula 9ª — Da Confidencialidade</h2>
      <p>
        Ambas as partes comprometem-se a manter em sigilo absoluto todas as informações
        confidenciais obtidas em razão deste contrato, não podendo divulgá-las a
        terceiros sem prévia autorização escrita da outra parte, sob pena de
        responsabilização civil e criminal.
      </p>
    </div>

    <div class="contrato-clausula">
      <h2>Cláusula 10ª — Do Foro</h2>
      <p>
        As partes elegem o <strong>Foro da Comarca de Campo Largo, Estado do Paraná</strong>,
        como competente para dirimir quaisquer dúvidas ou litígios oriundos do presente
        contrato, com renúncia expressa a qualquer outro, por mais privilegiado que seja.
      </p>
    </div>

    <div class="contrato-bloco-assinaturas">
      <p style="margin-top:24px; text-align:center; color:var(--text-muted); font-size:.85rem;">
        Campo Largo - PR, <?= $data_hoje ?>.
      </p>

      <div class="contrato-assinaturas">
        <div class="assinatura-box">
          <div class="assinatura-linha"></div>
          <strong>MKT Massuquetto</strong><br>
          Ruben Massuquetto<br>
          CONTRATADA
        </div>
        <div class="assinatura-box">
          <div class="assinatura-linha"></div>
          <strong><?= htmlspecialchars($cliente) ?></strong><br>
          CONTRATANTE
        </div>
      </div>
    </div>
  </div>

  <div class="contrato-print-btn" style="margin-bottom:0;">
    <form method="POST" action="contrato.php" style="display:inline;">
      <input type="hidden" name="acao" value="baixar_pdf">
      <input type="hidden" name="cliente" value="<?= htmlspecialchars($cliente) ?>">
      <input type="hidden" name="pacote" value="<?= htmlspecialchars($pacote) ?>">
      <input type="hidden" name="valor_setup" value="<?= number_format($valor_setup, 2, ',', '.') ?>">
      <input type="hidden" name="valor_mensal" value="<?= number_format($valor_mensal, 2, ',', '.') ?>">
      <input type="hidden" name="dia_vencimento" value="<?= $dia_venc ?>">
      <input type="hidden" name="prazo" value="<?= $prazo ?>">
      <button type="submit" class="btn btn-primary">🖨️ Imprimir / Salvar PDF (A4)</button>
    </form>
    <button type="button" onclick="window.print()" class="btn btn-outline">🖨️ Imprimir na Tela</button>
  </div>
  <?php endif; ?>

</div>
<div class="dev-sign">Desenvolvido por: Ruben Massuquetto</div>
</body>
</html>
