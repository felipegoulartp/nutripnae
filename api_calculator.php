<?php
// --- Configuração de Erros e Cabeçalhos ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // 0 para produção/API
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

$response = [];
$alimentos_db = null;
$todos_pnae_ref = null; 
$dados_ok = false;

// --- Carregamento Robusto de Dados ---
try {
    $dados_file = __DIR__ . '/dados.php';
    if (!file_exists($dados_file) || !is_readable($dados_file)) {
        throw new Exception('Erro Interno: dados.php não encontrado.');
    }
    ob_start();
    require_once $dados_file;
    $output = ob_get_clean();
    if (!empty($output)) {
        error_log("Saída inesperada durante include de dados.php (api_calculator): " . $output);
    }

    if (($dados_ok ?? false) !== true) {
         throw new Exception('Erro Interno: Falha ao carregar dados essenciais (dados.php não retornou $dados_ok=true).');
    }
    if (!isset($alimentos_db) || !is_array($alimentos_db) || empty($alimentos_db)) {
        throw new Exception('Erro Interno: $alimentos_db inválido ou vazio.');
    }
    if (!isset($todos_pnae_ref) || !is_array($todos_pnae_ref) || empty($todos_pnae_ref)) {
         throw new Exception('Erro Interno: $todos_pnae_ref (simplificado) inválido ou vazio.');
    }

} catch (Throwable $e) {
    error_log("Erro Crítico ao carregar dados.php em api_calculator.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    ob_end_clean(); 
    echo json_encode(['error' => 'Erro Crítico na Carga de Dados do Servidor (API). Verifique logs.']);
    exit;
}

// --- Leitura e Validação dos Dados da Requisição POST (Payload) ---
$payload_json = $_POST['payload'] ?? null;
if (!$payload_json) { ob_end_clean(); echo json_encode(['error'=>'Payload não recebido']); exit; }
$apiData = json_decode($payload_json, true);
if (json_last_error() !== JSON_ERROR_NONE) { ob_end_clean(); echo json_encode(['error'=>'JSON inválido']); exit; }

$cardapio_recebido = $apiData['cardapio'] ?? null;
$dias_ativos_recebidos = $apiData['dias_ativos'] ?? [];
$faixa_etaria_id = $apiData['faixa_etaria'] ?? null;
$preparacoes_defs = $apiData['meta']['preparacoes_defs'] ?? [];
$log_erros_calculo = [];

// Validações Essenciais
if (!$cardapio_recebido || !is_array($cardapio_recebido)) { ob_end_clean(); echo json_encode(['error' => 'Estrutura de cardápio inválida recebida.']); exit; }
if (!$faixa_etaria_id || !isset($todos_pnae_ref[$faixa_etaria_id])) { ob_end_clean(); echo json_encode(['error' => 'Faixa etária não selecionada ou inválida.']); exit; }
$pnae_ref_usar = $todos_pnae_ref[$faixa_etaria_id];

// --- Inicializar Estruturas de Dados ---
$totais_diarios = [];
$totais_semanais = [];
$nutrientes_keys = [];

$primeiro_alimento_valido = null; foreach ($alimentos_db as $data) { if (is_array($data) && isset($data['nome'])) { $primeiro_alimento_valido = $data; break; } }
if (!$primeiro_alimento_valido) { ob_end_clean(); echo json_encode(['error' => 'Erro: Nenhuma definição de alimento encontrada.']); exit; }
foreach (array_keys($primeiro_alimento_valido) as $key) { if ($key !== 'nome' && is_numeric($primeiro_alimento_valido[$key])) { $nutrientes_keys[] = $key; } }
if (empty($nutrientes_keys)) { ob_end_clean(); echo json_encode(['error' => 'Erro: Nenhuma chave de nutriente encontrada.']); exit; }

$totais_semanais = array_fill_keys($nutrientes_keys, 0.0);
foreach ($dias_ativos_recebidos as $dia) { $totais_diarios[$dia] = array_fill_keys($nutrientes_keys, 0.0); }

// --- Processar Cardápio e Calcular Totais Diários e Semanais ---
foreach ($dias_ativos_recebidos as $dia) {
    if (!isset($cardapio_recebido[$dia])) continue;
    $refeicoes_do_dia = $cardapio_recebido[$dia];
    foreach ($refeicoes_do_dia as $ref_key => $itens_refeicao) {
        if (!is_array($itens_refeicao)) continue;
        foreach ($itens_refeicao as $item) {
            $item_id_raw = $item['foodId'] ?? $item['id'] ?? null;
            if (!is_array($item) || $item_id_raw === null || !isset($item['qty'])) {
                $log_erros_calculo[] = "Item malformado ignorado no dia '{$dia}', refeição '{$ref_key}'.";
                continue;
            }
            $item_id = (string)$item_id_raw;
            $item_qty = filter_var($item['qty'], FILTER_VALIDATE_FLOAT); // Quantidade do item no cardápio (peso bruto se for alimento, ou porção da preparação)
            $is_preparacao = $item['is_prep'] ?? (strpos($item_id, 'prep_') === 0);

            if ($item_qty === false || $item_qty <= 0) {
                $log_erros_calculo[] = "Quantidade inválida para item '{$item_id}'.";
                continue;
            }

            $nutrientes_item = array_fill_keys($nutrientes_keys, 0.0);

            if (!$is_preparacao) { // Alimento Base
                $id_alimento_num = filter_var($item_id, FILTER_VALIDATE_INT);
                if ($id_alimento_num === false || !isset($alimentos_db[$id_alimento_num])) {
                    $log_erros_calculo[] = "Alimento base com ID '{$item_id}' não encontrado no DB.";
                    continue;
                }
                $alimento_info = $alimentos_db[$id_alimento_num];
                foreach ($nutrientes_keys as $nutriente) {
                    if (isset($alimento_info[$nutriente]) && is_numeric($alimento_info[$nutriente])) {
                        $nutrientes_item[$nutriente] = (floatval($alimento_info[$nutriente]) / 100.0) * $item_qty;
                    }
                }
            } else { // Preparação
                if (!isset($preparacoes_defs[$item_id])) {
                     $log_erros_calculo[] = "Definição da preparação '{$item_id}' não encontrada no payload.";
                     continue;
                }
                $prep_info = $preparacoes_defs[$item_id];
                if (!isset($prep_info['ingredientes']) || !is_array($prep_info['ingredientes']) || empty($prep_info['ingredientes'])) {
                    $log_erros_calculo[] = "Preparação '{$item_id}' sem ingredientes.";
                    continue;
                }
                $nutrientes_receita_total = array_fill_keys($nutrientes_keys, 0.0);
                $peso_liquido_total_receita = 0.0; // Usar peso líquido total da receita

                foreach ($prep_info['ingredientes'] as $ing) {
                    $ing_id_raw = $ing['foodId'] ?? null;
                    $ing_qty_raw = $ing['qty'] ?? 0; // Quantidade é o Peso Bruto (PB) do ingrediente na ficha técnica
                    $ing_fc = $ing['fc'] ?? 1.0; // Fator de Cocção (FC) do ingrediente na ficha técnica

                    if ($ing_id_raw === null) continue;

                    $ing_id_num = filter_var($ing_id_raw, FILTER_VALIDATE_INT);
                    $ing_qty_pb = filter_var($ing_qty_raw, FILTER_VALIDATE_FLOAT); // Peso Bruto do ingrediente

                    if ($ing_id_num === false || !isset($alimentos_db[$ing_id_num]) || $ing_qty_pb === false || $ing_qty_pb <= 0) {
                        $log_erros_calculo[] = "Ingrediente inválido na preparação '{$item_id}': ID {$ing_id_raw}, Qtd {$ing_qty_raw}.";
                        continue;
                    }
                    $alimento_info_db = $alimentos_db[$ing_id_num];

                    // Calcula o peso líquido do ingrediente para a soma total da receita
                    $ing_peso_liquido = ($ing_fc > 0) ? ($ing_qty_pb / $ing_fc) : 0;
                    $peso_liquido_total_receita += $ing_peso_liquido;

                    // Soma os nutrientes com base no peso líquido do ingrediente
                    foreach ($nutrientes_keys as $nutriente) {
                        if (isset($alimento_info_db[$nutriente]) && is_numeric($alimento_info_db[$nutriente])) {
                            $nutrientes_receita_total[$nutriente] += (floatval($alimento_info_db[$nutriente]) / 100.0) * $ing_peso_liquido;
                        }
                    }
                }
                
                // Se a preparação tiver um 'rendimento_peso_total_g' definido na ficha técnica, use-o
                // Caso contrário, use o peso líquido total calculado dos ingredientes
                $rendimento_preparacao_final_g = floatval($prep_info['rendimento_peso_total_g'] ?? 0);
                if ($rendimento_preparacao_final_g <= 0) {
                    $rendimento_preparacao_final_g = $peso_liquido_total_receita;
                }

                if ($rendimento_preparacao_final_g <= 0) {
                     $log_erros_calculo[] = "Peso total da receita '{$item_id}' é zero ou inválido. Nutrientes da preparação não calculados.";
                     continue;
                }

                // Calcula os nutrientes da preparação por 100g da preparação final
                $fator_escala_prep = 100 / $rendimento_preparacao_final_g;
                $nutrientes_por_100g_prep = array_fill_keys($nutrientes_keys, 0.0);
                foreach ($nutrientes_keys as $nutriente) {
                    $nutrientes_por_100g_prep[$nutriente] = $nutrientes_receita_total[$nutriente] * $fator_escala_prep;
                }

                // Multiplica os nutrientes por 100g da preparação pela quantidade do item no cardápio
                // (que é a porção da preparação em gramas)
                foreach ($nutrientes_keys as $nutriente) {
                    $nutrientes_item[$nutriente] = $nutrientes_por_100g_prep[$nutriente] * ($item_qty / 100.0);
                }
            }

            foreach ($nutrientes_keys as $nutriente) {
                if (isset($totais_diarios[$dia][$nutriente])) { $totais_diarios[$dia][$nutriente] += $nutrientes_item[$nutriente]; }
                if (isset($totais_semanais[$nutriente])) { $totais_semanais[$nutriente] += $nutrientes_item[$nutriente]; }
            }
        }
    }
}

// --- Mapeamento Chaves Internas -> API ---
$map_keys_nutrientes = [
    'kcal' => 'kcal', 'carboidratos' => 'cho', 'proteina' => 'ptn', 'lipideos' => 'lpd',
    'calcio' => 'ca', 'ferro' => 'fe', 'retinol' => 'vita', 'vitamina_c' => 'vitc', 'sodio' => 'na',
    // Adicione outras chaves que você usa em dados.php e queira na API
    'colesterol' => 'colesterol', // Exemplo: se você tem 'colesterol' em dados.php
    'fibra_dieta' => 'fibra_dieta' // Exemplo: se você tem 'fibra_dieta' em dados.php
];
$api_nutrients_keys = array_values($map_keys_nutrientes);

// --- Calcular Médias Semanais ---
$medias_semanais_api = [];
$num_dias_calc = count($dias_ativos_recebidos);
if ($num_dias_calc > 0) {
    foreach ($map_keys_nutrientes as $interno => $api_key) {
        $medias_semanais_api[$api_key] = isset($totais_semanais[$interno]) ? ($totais_semanais[$interno] / $num_dias_calc) : 0.0;
    }
} else {
    $medias_semanais_api = array_fill_keys($api_nutrients_keys, 0.0);
}

// --- Formatar Totais Diários para API ---
$totais_diarios_api = [];
$dias_semana_completa = $apiData['meta']['dias_keys'] ?? ['seg', 'ter', 'qua', 'qui', 'sex'];
foreach ($dias_semana_completa as $dia_key) {
    $totais_diarios_api[$dia_key] = array_fill_keys($api_nutrients_keys, 0.0);
    if (isset($totais_diarios[$dia_key])) {
        foreach ($map_keys_nutrientes as $interno => $api_key) {
            if (isset($totais_diarios[$dia_key][$interno])) {
                $totais_diarios_api[$dia_key][$api_key] = $totais_diarios[$dia_key][$interno];
            }
        }
    }
}

// --- Prepara Resposta Final ---
$response = [
    'daily_totals'          => $totais_diarios_api,
    'weekly_average'        => $medias_semanais_api,
    'pnae_ref_selected'     => $pnae_ref_usar,
    'debug_errors'          => $log_erros_calculo
];

ob_end_clean();

// --- Retornar JSON ---
$json_response = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE | JSON_PARTIAL_OUTPUT_ON_ERROR);
if (json_last_error() !== JSON_ERROR_NONE) {
     $json_error_msg = json_last_error_msg();
     error_log("JSON Encode Error (api_calculator.php): " . $json_error_msg);
     echo json_encode(['error' => 'Falha interna ao gerar resposta JSON (API): ' . htmlspecialchars($json_error_msg)]);
} else {
    echo $json_response;
}
exit;
?>
