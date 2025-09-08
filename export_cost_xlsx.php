<?php
// cardapio_auto/export_cost_xlsx.php

// Inclua a biblioteca PhpSpreadsheet
// Você precisará instalar PhpSpreadsheet via Composer:
// composer require phpoffice/phpspreadsheet
// E então incluir o autoload.php
require 'vendor/autoload.php'; // Ajuste o caminho se necessário

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

session_start();
header('Content-Type: application/json'); // Inicialmente JSON para erros, mudará para XLSX no sucesso

$response = ['success' => false, 'message' => ''];
$logged_user_id = $_SESSION['user_id'] ?? null;

if (!$logged_user_id) {
    $response['message'] = 'Usuário não autenticado.';
    echo json_encode($response);
    exit;
}

$export_data_json = $_POST['export_data'] ?? null;

if (!$export_data_json) {
    $response['message'] = 'Dados para exportação ausentes.';
    echo json_encode($response);
    exit;
}

$export_data = json_decode($export_data_json, true);

if (json_last_error() !== JSON_ERROR_NONE || !is_array($export_data)) {
    $response['message'] = 'Dados de exportação inválidos ou corrompidos.';
    echo json_encode($response);
    exit;
}

try {
    $project_name = $export_data['project_name'] ?? 'Cardapio';
    $daily_quantities = $export_data['daily_quantities'] ?? [];
    $weekly_quantities = $export_data['weekly_quantities'] ?? [];
    $alimentos_precos = $export_data['alimentos_precos'] ?? [];
    $alimentos_info = $export_data['alimentos_info'] ?? [];

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Custo Cardápio');

    // Estilos
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['argb' => Color::COLOR_WHITE]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF005A9C']], // Primary color
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
    ];
    $dataStyle = [
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ];
    $totalStyle = [
        'font' => ['bold' => true],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE0EBF4']], // Light background
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
    ];
    $currencyStyle = [
        'numberFormat' => ['formatCode' => 'R$ #,##0.00'],
    ];
    $weightStyle = [
        'numberFormat' => ['formatCode' => '#,##0 " g"'],
    ];

    // Cabeçalho da tabela de custos do cardápio
    $sheet->setCellValue('A1', 'Custo do Cardápio: ' . $project_name);
    $sheet->mergeCells('A1:C1');
    $sheet->getStyle('A1')->applyFromArray($headerStyle)->getFont()->setSize(14);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getRowDimension(1)->setRowHeight(30);

    $sheet->setCellValue('A3', 'Dia da Semana');
    $sheet->setCellValue('B3', 'Total Quantidade (g)');
    $sheet->setCellValue('C3', 'Custo Total (R$)');
    $sheet->getStyle('A3:C3')->applyFromArray($headerStyle);
    $sheet->getRowDimension(3)->setRowHeight(25);

    $row = 4;
    $diasSemana = ['seg' => 'Segunda', 'ter' => 'Terça', 'qua' => 'Quarta', 'qui' => 'Quinta', 'sex' => 'Sexta'];
    $totalWeeklyQty = 0;
    $totalWeeklyCost = 0;

    foreach ($diasSemana as $diaKey => $diaNome) {
        $quantities = $daily_quantities[$diaKey] ?? [];
        $dailyTotalQty = 0;
        $dailyTotalCost = 0;

        foreach ($quantities as $foodId => $qtyInGrams) {
            $foodData = $alimentos_info[$foodId] ?? null;
            $costPerGram = 0;

            if ($foodData) {
                if ($foodData['isPreparacao'] && isset($foodData['ingredientes']) && !empty($foodData['ingredientes'])) {
                    $prepCostPerGram = 0;
                    $totalPrepWeight = 0;
                    foreach ($foodData['ingredientes'] as $ing) {
                        $ingPricePerKg = floatval($alimentos_precos[$ing['foodId']] ?? 0);
                        $ingPricePerGram = $ingPricePerKg / 1000;
                        $prepCostPerGram += ($ing['qty'] * $ingPricePerGram);
                        $totalPrepWeight += $ing['qty'];
                    }
                    if ($totalPrepWeight > 0) {
                        $costPerGram = $prepCostPerGram / $totalPrepWeight;
                    }
                } else {
                    $pricePerKg = floatval($alimentos_precos[$foodId] ?? 0);
                    $costPerGram = $pricePerKg / 1000;
                }
            }
            $dailyTotalQty += $qtyInGrams;
            $dailyTotalCost += ($qtyInGrams * $costPerGram);
        }

        $sheet->setCellValue('A' . $row, $diaNome);
        $sheet->setCellValue('B' . $row, $dailyTotalQty);
        $sheet->setCellValue('C' . $row, $dailyTotalCost);
        $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($dataStyle);
        $sheet->getStyle('B' . $row)->applyFromArray($weightStyle);
        $sheet->getStyle('C' . $row)->applyFromArray($currencyStyle);
        $row++;

        $totalWeeklyQty += $dailyTotalQty;
        $totalWeeklyCost += $dailyTotalCost;
    }

    // Média Semanal
    $avgWeeklyQty = count($diasSemana) > 0 ? $totalWeeklyQty / count($diasSemana) : 0;
    $avgWeeklyCost = count($diasSemana) > 0 ? $totalWeeklyCost / count($diasSemana) : 0;

    $sheet->setCellValue('A' . $row, 'Média Semanal');
    $sheet->setCellValue('B' . $row, $avgWeeklyQty);
    $sheet->setCellValue('C' . $row, $avgWeeklyCost);
    $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray($totalStyle);
    $sheet->getStyle('B' . $row)->applyFromArray($weightStyle);
    $sheet->getStyle('C' . $row)->applyFromArray($currencyStyle);

    // Ajustar largura das colunas
    $sheet->getColumnDimension('A')->setWidth(20);
    $sheet->getColumnDimension('B')->setWidth(25);
    $sheet->getColumnDimension('C')->setWidth(25);

    // Adicionar uma nova aba para detalhes de preços por alimento
    $spreadsheet->createSheet();
    $sheetPrices = $spreadsheet->getSheet(1);
    $sheetPrices->setTitle('Preços Alimentos');

    $sheetPrices->setCellValue('A1', 'Preços Definidos por Alimento/Preparação (R$/kg)');
    $sheetPrices->mergeCells('A1:B1');
    $sheetPrices->getStyle('A1')->applyFromArray($headerStyle)->getFont()->setSize(14);
    $sheetPrices->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheetPrices->getRowDimension(1)->setRowHeight(30);

    $sheetPrices->setCellValue('A3', 'Alimento / Preparação');
    $sheetPrices->setCellValue('B3', 'Preço (R$/kg)');
    $sheetPrices->getStyle('A3:B3')->applyFromArray($headerStyle);
    $sheetPrices->getRowDimension(3)->setRowHeight(25);

    $rowPrices = 4;
    $sortedAlimentos = [];
    foreach ($alimentos_info as $id => $info) {
        $sortedAlimentos[] = ['id' => $id, 'nome' => $info['nome'], 'isPreparacao' => $info['isPreparacao']];
    }
    usort($sortedAlimentos, fn($a, $b) => strcasecmp($a['nome'], $b['nome']));

    foreach ($sortedAlimentos as $item) {
        $foodId = $item['id'];
        $foodName = $item['nome'];
        $isPreparacao = $item['isPreparacao'];
        $price = $alimentos_precos[$foodId] ?? 0;

        $displayName = $foodName;
        if ($isPreparacao) {
            $displayName .= ' (Preparação)';
        }

        $sheetPrices->setCellValue('A' . $rowPrices, $displayName);
        $sheetPrices->setCellValue('B' . $rowPrices, floatval($price));
        $sheetPrices->getStyle('A' . $rowPrices . ':B' . $rowPrices)->applyFromArray($dataStyle);
        $sheetPrices->getStyle('B' . $rowPrices)->applyFromArray($currencyStyle);
        $rowPrices++;
    }

    $sheetPrices->getColumnDimension('A')->setWidth(40);
    $sheetPrices->getColumnDimension('B')->setWidth(20);


    // Configurar cabeçalhos para download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="Custo_Cardapio_' . rawurlencode($project_name) . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    // Se ocorrer um erro após os headers de download, pode ser tarde para JSON.
    // O ideal é capturar antes ou ter um mecanismo de fallback.
    // Para este exemplo, vamos tentar retornar JSON se possível.
    if (!headers_sent()) {
        $response['message'] = 'Erro ao gerar Excel: ' . $e->getMessage();
        error_log("Erro export_cost_xlsx.php: " . $e->getMessage());
        echo json_encode($response);
    } else {
        // Fallback se os headers já foram enviados
        echo "Um erro ocorreu ao gerar o arquivo Excel. Por favor, tente novamente. Detalhes: " . $e->getMessage();
    }
    exit;
}
?>
