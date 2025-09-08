<?php
// Configurações de erro (manter como está)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

// Verifica e inclui o autoload do Composer
if (!file_exists('vendor/autoload.php')) {
    die("ERRO FATAL: vendor/autoload.php não encontrado. Execute 'composer require phpoffice/phpspreadsheet'");
}
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

// Cria uma nova planilha
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Configurações gerais
$spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
$spreadsheet->getDefaultStyle()->getFont()->setSize(10);

// Configuração da página
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
$sheet->getPageSetup()->setFitToPage(true);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);
$sheet->getPageMargins()->setTop(0.5)->setRight(0.4)->setLeft(0.4)->setBottom(0.5);

// Definir estilos
$headerStyle = [
    'font' => ['bold' => true, 'size' => 11],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']]
];

$subHeaderStyle = [
    'font' => ['bold' => true, 'size' => 10],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']]
];

$periodoStyle = [
    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FF0000']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']]
];

$tableHeaderStyle = [
    'font' => ['bold' => true, 'size' => 9],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

$refeicaoLabelStyle = [
    'font' => ['bold' => true, 'size' => 9],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

$horarioStyle = [
    'font' => ['size' => 8],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

$cellStyle = [
    'font' => ['size' => 8],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

$composicaoStyle = [
    'font' => ['size' => 9],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];

$orientacoesStyle = [
    'font' => ['size' => 8],
    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'horizontal' => Alignment::HORIZONTAL_LEFT, 'wrapText' => true]
];

// Definir larguras das colunas
$sheet->getColumnDimension('A')->setWidth(25); // Refeição
$sheet->getColumnDimension('B')->setWidth(12); // Horário
$sheet->getColumnDimension('C')->setWidth(20); // Segunda
$sheet->getColumnDimension('D')->setWidth(20); // Terça
$sheet->getColumnDimension('E')->setWidth(20); // Quarta
$sheet->getColumnDimension('F')->setWidth(20); // Quinta
$sheet->getColumnDimension('G')->setWidth(20); // Sexta

// Preencher cabeçalho
$currentRow = 1;

// Linha 1: SECRETARIA
$sheet->mergeCells('A'.$currentRow.':G'.$currentRow)
      ->getCell('A'.$currentRow)
      ->setValue('SECRETARIA MUNICIPAL DE EDUCAÇÃO DE UBERLÂNDIA-MG');
$sheet->getStyle('A'.$currentRow)->applyFromArray($headerStyle);
$sheet->getRowDimension($currentRow)->setRowHeight(18);
$currentRow++;

// Linha 2: PROGRAMA
$sheet->mergeCells('A'.$currentRow.':G'.$currentRow)
      ->getCell('A'.$currentRow)
      ->setValue('PROGRAMA NACIONAL DE ALIMENTAÇÃO ESCOLAR - PNAE / PROGRAMA MUNICIPAL DE ALIMENTAÇÃO ESCOLAR - PMAE');
$sheet->getStyle('A'.$currentRow)->applyFromArray($headerStyle);
$sheet->getRowDimension($currentRow)->setRowHeight(18);
$currentRow++;

// Linha 3: CARDÁPIO
$sheet->mergeCells('A'.$currentRow.':G'.$currentRow)
      ->getCell('A'.$currentRow)
      ->setValue('CARDÁPIO DO ENSINO FUNDAMENTAL - PERÍODO INTEGRAL');
$sheet->getStyle('A'.$currentRow)->applyFromArray($headerStyle);
$sheet->getRowDimension($currentRow)->setRowHeight(18);
$currentRow++;

// Linha 4: LOCAL
$sheet->mergeCells('A'.$currentRow.':G'.$currentRow)
      ->getCell('A'.$currentRow)
      ->setValue('ZONA URBANA');
$sheet->getStyle('A'.$currentRow)->applyFromArray($subHeaderStyle);
$sheet->getRowDimension($currentRow)->setRowHeight(16);
$currentRow++;

// Linha 5: FAIXA ETÁRIA
$sheet->mergeCells('A'.$currentRow.':G'.$currentRow)
      ->getCell('A'.$currentRow)
      ->setValue('FAIXA ETÁRIA (6-10 anos)');
$sheet->getStyle('A'.$currentRow)->applyFromArray($subHeaderStyle);
$sheet->getRowDimension($currentRow)->setRowHeight(16);
$currentRow++;

// Linha 6: MÊS
$sheet->mergeCells('A'.$currentRow.':G'.$currentRow)
      ->getCell('A'.$currentRow)
      ->setValue('MÊS 2025');
$sheet->getStyle('A'.$currentRow)->applyFromArray($periodoStyle);
$sheet->getRowDimension($currentRow)->setRowHeight(16);
$currentRow++;

// Cabeçalho da tabela
$sheet->getCell('A'.$currentRow)->setValue('REFEIÇÃO');
$sheet->getCell('B'.$currentRow)->setValue('SUGESTÃO DE HORÁRIO');

// Datas e dias da semana
$dates = [
    '2025-04-07 00:00:00',
    '2025-04-08 00:00:00',
    '2025-04-09 00:00:00',
    '2025-04-10 00:00:00',
    '2025-04-11 00:00:00'
];

$diasSemana = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira'];

for ($i = 0; $i < 5; $i++) {
    $col = chr(ord('C') + $i);
    $sheet->getCell($col.$currentRow)->setValue($dates[$i]."\n".$diasSemana[$i]);
    $sheet->getStyle($col.$currentRow)->getFont()->getColor()->setARGB('FF0000'); // Data em vermelho
}

$sheet->getStyle('A'.$currentRow.':G'.$currentRow)->applyFromArray($tableHeaderStyle);
$sheet->getRowDimension($currentRow)->setRowHeight(35);
$currentRow++;

// Adicionar refeições (exemplo com café da manhã)
$refeicoes = [
    [
        'nome' => 'CAFÉ DA MANHÃ<br>(Somente para o ensino integral)',
        'horario' => '07:30:00',
        'dias' => ['', '', '', '', '']
    ],
    [
        'nome' => 'REFEIÇÃO PRINCIPAL MANHÃ<br>(Parcial e integral)',
        'horario' => '9:15 -10:00',
        'dias' => ['', '', '', '', '']
    ],
    [
        'nome' => 'LANCHE <br> (Somente para o ensino integral)',
        'horario' => '13:00:00',
        'dias' => ['', '', '', '', '']
    ],
    [
        'nome' => 'REFEIÇÃO PRINCIPAL TARDE<br>(Parcial e integral)',
        'horario' => '15:15 - 16:00',
        'dias' => ['', '', '', 'PARCIAL:', 'PARCIAL:']
    ]
];

foreach ($refeicoes as $refeicao) {
    $sheet->getCell('A'.$currentRow)->setValue($refeicao['nome']);
    $sheet->getCell('B'.$currentRow)->setValue($refeicao['horario']);
    
    for ($i = 0; $i < 5; $i++) {
        $col = chr(ord('C') + $i);
        $sheet->getCell($col.$currentRow)->setValue($refeicao['dias'][$i]);
    }
    
    $sheet->getStyle('A'.$currentRow.':G'.$currentRow)->applyFromArray($cellStyle);
    $sheet->getStyle('A'.$currentRow)->applyFromArray($refeicaoLabelStyle);
    $sheet->getStyle('B'.$currentRow)->applyFromArray($horarioStyle);
    
    $sheet->getRowDimension($currentRow)->setRowHeight(-1); // Ajuste automático
    $currentRow++;
}

// Adicionar composição nutricional
$currentRow++; // Pular uma linha

$sheet->mergeCells('A'.$currentRow.':G'.$currentRow)
      ->getCell('A'.$currentRow)
      ->setValue('Composição nutricional (Média Semanal)');
$sheet->getStyle('A'.$currentRow.':G'.$currentRow)->applyFromArray($tableHeaderStyle);
$currentRow++;

// Cabeçalhos da composição
$sheet->getCell('A'.$currentRow)->setValue('');
$sheet->getCell('B'.$currentRow)->setValue('Energia (Kcal)');
$sheet->getCell('C'.$currentRow)->setValue('CHO');
$sheet->getCell('D'.$currentRow)->setValue('PTN');
$sheet->getCell('E'.$currentRow)->setValue('LPD');
$sheet->getStyle('A'.$currentRow.':E'.$currentRow)->applyFromArray($tableHeaderStyle);
$currentRow++;

// Dados da composição - Ensino Parcial
$sheet->getCell('A'.$currentRow)->setValue('Ensino Parcial');
$sheet->getCell('B'.$currentRow)->setValue('371,7 kcal');
$sheet->getCell('C'.$currentRow)->setValue('52g (57%)');
$sheet->getCell('D'.$currentRow)->setValue('16g (17%)');
$sheet->getCell('E'.$currentRow)->setValue('11g (25%)');
$sheet->getStyle('A'.$currentRow.':E'.$currentRow)->applyFromArray($composicaoStyle);
$currentRow++;

// Dados da composição - Ensino Integral
$sheet->getCell('A'.$currentRow)->setValue('Ensino Integral');
$sheet->getCell('B'.$currentRow)->setValue('1202 kcal');
$sheet->getCell('C'.$currentRow)->setValue('185g (60%)');
$sheet->getCell('D'.$currentRow)->setValue('48g (16%)');
$sheet->getCell('E'.$currentRow)->setValue('29g (24%)');
$sheet->getStyle('A'.$currentRow.':E'.$currentRow)->applyFromArray($composicaoStyle);
$currentRow++;

// Adicionar orientações
$currentRow++; // Pular uma linha
$sheet->getCell('A'.$currentRow)->setValue('Orientações:');
$sheet->getStyle('A'.$currentRow)->getFont()->setBold(true);
$currentRow++;

$orientacoes = "Ao receber os hortifrútis reserve aqueles que estão no cardápio nas preparações de segunda-feira e da terça-feira da semana seguinte. 
Os hortifrútis estão sujeitos a mudanças nas entregas, devido a alterações climáticas e outros motivos, podendo impactar no cardápio, neste caso, é recomendado que façam substituições nos hortifrútis.
As escolas que não dispõem de forno para as preparações devem adaptar para fazê-las cozidas.  
O orégano e o acafrão podem ser utilizado nas preparações, mesmo que não esteja indicado no cardápio.
De acordo com a resolução n° 6 de 8 de maio de 2020 a oferta de frutas para o ensino INTEGRAL é de no mínimo 4 dias da semana e para o ensino PARCIAL são 2 dias da semana.
Receitas disponíveis no livro de receitas do PMAE ou enviadas em anexo junto com o cardápio;
CARDÁPIO SUJEITO A ALTERAÇÕES DE ACORDO COM A DISPONIBILIDADE DOS ALIMENTOS.";

$sheet->mergeCells('A'.$currentRow.':G'.($currentRow + 6))
      ->getCell('A'.$currentRow)
      ->setValue($orientacoes);
$sheet->getStyle('A'.$currentRow.':G'.($currentRow + 6))->applyFromArray($orientacoesStyle);
$currentRow += 7;

// Adicionar elaborado por
$sheet->getCell('E'.$currentRow)->setValue('ELABORADO POR:');
$sheet->getStyle('E'.$currentRow)->getFont()->setBold(true);
$currentRow++;

$elaboradoPor = "Paula Alvares Borges Ferreira  CRN9 23537
Juliana Freitas Chiareto  CRN9 21711
Nutricionistas QT

REVISADO POR:                                                                                                                                                                                                                                                                                                                                                                                                  
Geise de Castro Fonseca CRN9 1590
Nutricionista RT";

$sheet->mergeCells('E'.$currentRow.':G'.($currentRow + 4))
      ->getCell('E'.$currentRow)
      ->setValue($elaboradoPor);
$sheet->getStyle('E'.$currentRow.':G'.($currentRow + 4))->applyFromArray($orientacoesStyle);
$currentRow += 5;

// Adicionar rodapé (repetir cabeçalho)
$sheet->mergeCells('A'.$currentRow.':G'.$currentRow)
      ->getCell('A'.$currentRow)
      ->setValue('SECRETARIA MUNICIPAL DE EDUCAÇÃO DE UBERLÂNDIA-MG');
$sheet->getStyle('A'.$currentRow)->applyFromArray($headerStyle);
$sheet->getRowDimension($currentRow)->setRowHeight(18);
$currentRow++;

// Continuar com os outros elementos do rodapé (igual ao cabeçalho inicial)

// Configurar o download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="cardapio_modelo.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;