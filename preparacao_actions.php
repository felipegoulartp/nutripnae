<?php
// cardapio_auto/preparacao_actions.php

// --- Bloco Padronizado de Configuração de Sessão ---
$session_cookie_path = '/';
$session_name = "CARDAPIOSESSID";
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => $session_cookie_path, 'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', 'httponly' => true, 'samesite' => 'Lax'
    ]);
}
session_name($session_name);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Configurações de Erro e Header ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros em produção para respostas AJAX
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error_actions.log'); // Log específico para actions
date_default_timezone_set('America/Sao_Paulo'); // Ajuste para seu fuso horário
header('Content-Type: application/json; charset=utf-8');

// --- Função para Enviar Resposta JSON e Terminar ---
function json_response($success, $message = '', $data = [], $http_status_code = 200) {
    http_response_code($http_status_code);
    $response_data = [
        'success' => $success,
        'message' => $message,
        // Garante que 'todas_preparacoes_atualizadas' esteja sempre presente como um ARRAY NUMÉRICO
        'todas_preparacoes_atualizadas' => array_values($data['todas_preparacoes_atualizadas'] ?? ($data['preparations'] ?? []))
    ];
    // Adiciona outros campos de $data que não sejam 'todas_preparacoes_atualizadas' ou 'preparations'
    foreach ($data as $key => $value) {
        if (!in_array($key, ['todas_preparacoes_atualizadas', 'preparations'])) {
            $response_data[$key] = $value;
        }
    }
    echo json_encode($response_data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

// --- Verificação de Autenticação ---
$logged_user_id = $_SESSION['user_id'] ?? null;
if (!$logged_user_id) {
    error_log("preparacao_actions.php: Acesso não autenticado (UserID não encontrado na sessão). Session ID: " . session_id());
    json_response(false, 'Acesso não autorizado. Faça o login novamente.', [], 403); // 403 Forbidden
}
error_log("preparacao_actions.php: Acesso AUTENTICADO. UserID: $logged_user_id. Ação: " . ($_POST['action'] ?? 'N/A') . ". Session ID: " . session_id());


// --- Inicialização de Variáveis Globais para este Script ---
$pdo = null;
$alimentos_db_completo = []; // Array com todos os dados dos alimentos de dados.php
$dados_base_carregados_ok = false;

// --- Tenta Conectar ao BD e Carregar dados.php ---
try {
    require_once 'includes/db_connect.php'; // Deve definir $pdo
    if (!isset($pdo)) {
        throw new \RuntimeException("Objeto PDO não foi definido por db_connect.php");
    }
    error_log("preparacao_actions.php: Conexão com BD OK.");

    // Carregar dados de alimentos de dados.php
    ob_start();
    require_once __DIR__ . '/dados.php'; // Espera que defina $alimentos_db e $dados_ok
    $output_dados_php = ob_get_clean();
    if (!empty($output_dados_php)) {
        error_log("preparacao_actions.php: Saída inesperada de dados.php: " . substr($output_dados_php,0,100));
    }

    if (isset($dados_ok) && $dados_ok === true && isset($alimentos_db) && !empty($alimentos_db)) {
        $alimentos_db_completo = $alimentos_db;
        $dados_base_carregados_ok = true;
        error_log("preparacao_actions.php: dados.php carregado com sucesso. " . count($alimentos_db_completo) . " alimentos base disponíveis.");
    } else {
        $vars_ok_log = "dados_ok: ".(isset($dados_ok)?var_export($dados_ok,true):'N/D').", alimentos_db: ".(isset($alimentos_db)?'Definido':'Não').", !empty(alimentos_db): ".(!empty($alimentos_db)?'Sim':'Não');
        error_log("preparacao_actions.php: CRÍTICO - dados.php NÃO carregou corretamente ou variáveis essenciais estão ausentes. $vars_ok_log. Cálculos nutricionais serão impossíveis.");
        // Não sai do script aqui, mas $dados_base_carregados_ok será false, e os cálculos não ocorrerão.
    }

} catch (\Throwable $e) { // Captura exceções de db_connect ou require de dados.php
    error_log("preparacao_actions.php: Erro CRÍTICO na inicialização (conexão BD ou carregamento de dados.php): " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    json_response(false, 'Erro interno crítico do servidor ao inicializar. Tente novamente mais tarde.', ['debug_message' => $e->getMessage()], 500);
}

// --- Lista de Nutrientes para Rastrear e Calcular (DEVE CORRESPONDER às chaves em dados.php) ---
// As chaves aqui devem ser EXATAMENTE as mesmas chaves usadas no array $alimentos_db em dados.php
$tracked_nutrients_map = [
    'kcal' => 0.0,
    'carboidratos' => 0.0,
    'proteina' => 0.0,
    'lipideos' => 0.0,
    'colesterol' => 0.0,
    'fibra_dieta' => 0.0, // Verifique se esta é a chave correta para fibras em dados.php
    'retinol' => 0.0,
    'vitamina_c' => 0.0,
    'calcio' => 0.0,
    'ferro' => 0.0,
    'sodio' => 0.0,
];


// --- Função Centralizada para Buscar, Modificar e Salvar Preparações (adaptada do seu código) ---
function gerenciarPreparacoesDoUsuario($pdo_conn, $userId, $modificacaoCallback) {
    $current_preparations_on_failure = []; // Para retornar em caso de falha antes de ler o BD
    try {
        $pdo_conn->beginTransaction();

        $stmt_get = $pdo_conn->prepare("SELECT preparacoes_personalizadas_json FROM cardapio_usuarios WHERE id = :user_id FOR UPDATE");
        $stmt_get->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt_get->execute();
        $json_preps_db = $stmt_get->fetchColumn();
        
        $preparacoes_atuais = [];
        if ($json_preps_db && $json_preps_db !== 'null' && $json_preps_db !== '{}' && $json_preps_db !== '[]') {
            $decoded = json_decode($json_preps_db, true);
            // Verifica se é um array e se não é um array numérico simples (indicando que já é associativo)
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Se for um array numérico (como `[{}, {}]`), converte para associativo por 'id'
                // Isso garante que sempre trabalhamos com um array associativo para fácil acesso por ID
                $is_numeric_array = true;
                foreach ($decoded as $key => $value) {
                    if (!is_numeric($key)) {
                        $is_numeric_array = false;
                        break;
                    }
                }
                if ($is_numeric_array) {
                    foreach ($decoded as $item) {
                        if (isset($item['id'])) {
                            $preparacoes_atuais[$item['id']] = $item;
                        }
                    }
                } else {
                    $preparacoes_atuais = $decoded;
                }
            } else {
                error_log("Erro ao decodificar JSON de preparações do BD para UserID $userId: " . json_last_error_msg() . ". JSON (início): " . substr($json_preps_db, 0, 200) . ". Resetando para array vazio.");
                // Não lança erro, mas começa do zero se o JSON estiver corrompido
            }
        }
        $current_preparations_on_failure = $preparacoes_atuais; // Atualiza o fallback

        // Aplicar a modificação usando o callback
        $resultado_modificacao = $modificacaoCallback($preparacoes_atuais);

        if (!$resultado_modificacao['success']) {
            $pdo_conn->rollBack(); // Desfaz a transação se o callback indicar falha
            error_log("Callback de modificação falhou para UserID $userId. Mensagem: " . ($resultado_modificacao['message'] ?? 'N/A'));
            return ['success' => false, 'message' => $resultado_modificacao['message'] ?? 'Erro durante a modificação dos dados.', 'preparations' => $preparacoes_atuais];
        }

        $preparacoes_modificadas = $resultado_modificacao['preparacoes'];
        if (isset($resultado_modificacao['log_message'])) {
            error_log($resultado_modificacao['log_message'] . " para UserID $userId.");
        }

        $json_para_salvar = empty($preparacoes_modificadas) ? '{}' : json_encode($preparacoes_modificadas, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR); // JSON_PARTIAL_OUTPUT_ON_ERROR tenta salvar o que for válido se houver erro de UTF-8
        if (json_last_error() !== JSON_ERROR_NONE) {
            $pdo_conn->rollBack();
            error_log("Erro CRÍTICO ao re-codificar JSON de preparações para UserID $userId: " . json_last_error_msg());
            return ['success' => false, 'message' => 'Erro interno crítico ao processar dados para salvar (JSON encode).', 'preparations' => $preparacoes_atuais];
        }

        $stmt_save = $pdo_conn->prepare("UPDATE cardapio_usuarios SET preparacoes_personalizadas_json = :json_data WHERE id = :user_id");
        $stmt_save->bindParam(':json_data', $json_para_salvar, PDO::PARAM_STR);
        $stmt_save->bindParam(':user_id', $userId, PDO::PARAM_INT);

        if ($stmt_save->execute()) {
            $pdo_conn->commit();
            // Retorna o ID da preparação salva para o frontend
            $saved_prep_id = $resultado_modificacao['saved_prep_id'] ?? null; 
            return ['success' => true, 'preparations' => $preparacoes_modificadas, 'saved_prep_id' => $saved_prep_id]; // Sucesso
        } else {
            $pdo_conn->rollBack();
            error_log("Falha CRÍTICA ao salvar preparações no BD para UserID $userId. Erro PDO: " . implode(":", $stmt_save->errorInfo()));
            return ['success' => false, 'message' => 'Erro crítico ao salvar preparações no banco de dados.', 'preparations' => $preparacoes_atuais];
        }

    } catch (\PDOException $e) {
        if ($pdo_conn->inTransaction()) { $pdo_conn->rollBack(); }
        error_log("PDOException CRÍTICA em gerenciarPreparacoesDoUsuario para UserID $userId: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erro de banco de dados crítico: ' . $e->getMessage(), 'preparations' => $current_preparations_on_failure];
    } catch (\Throwable $t) { // Captura outros erros inesperados
        if ($pdo_conn->inTransaction()) { $pdo_conn->rollBack(); }
        error_log("Throwable CRÍTICO em gerenciarPreparacoesDoUsuario para UserID $userId: " . $t->getMessage());
        return ['success' => false, 'message' => 'Erro geral crítico no servidor: ' . $t->getMessage(), 'preparations' => $current_preparations_on_failure];
    }
}

// --- Roteamento da Ação ---
$action = $_POST['action'] ?? null;
$response_data_payload = []; // Para dados extras na resposta JSON, como 'todas_preparacoes_atualizadas'

switch ($action) {
    case 'create_preparacao':
    case 'update_preparacao':
        $preparacao_data_json = $_POST['preparacao_data'] ?? null;
        if (!$preparacao_data_json) {
            json_response(false, 'Dados da preparação não recebidos.');
        }

        $ficha_recebida_do_js = json_decode($preparacao_data_json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($ficha_recebida_do_js) || empty($ficha_recebida_do_js['id']) || empty($ficha_recebida_do_js['nome'])) {
            error_log("JSON inválido ou dados essenciais faltando para $action. UserID $logged_user_id. Erro JSON: ".json_last_error_msg().". Data RAW (início): " . substr($preparacao_data_json,0,200));
            json_response(false, 'Dados da preparação inválidos ou incompletos. Verifique os campos obrigatórios.');
        }

        // Inicia a ficha que será salva com os dados recebidos
        $ficha_para_salvar = $ficha_recebida_do_js;

        // --- CÁLCULO DE NUTRIENTES E CUSTO ---
        // Inicializa os arrays de nutrientes e custo na ficha que será salva
        $ficha_para_salvar['nutrientes_totais_receita'] = $tracked_nutrients_map; // Usa o mapa global
        $ficha_para_salvar['nutrientes_por_porcao'] = $tracked_nutrients_map;   // Usa o mapa global (será preenchido)
        $ficha_para_salvar['custo_total_calculado'] = 0.0; // Inicializa o custo total

        $peso_liquido_total_calculado_g = 0.0; // Soma dos pesos líquidos dos ingredientes

        if (!$dados_base_carregados_ok) {
            error_log("Ação $action para UserID $logged_user_id: Impossível calcular nutrientes e custo, pois dados.php ($alimentos_db_completo) não carregou corretamente. A ficha será salva sem informações nutricionais/custo calculadas pelo backend.");
            // A ficha ainda pode ser salva, mas sem os campos 'nutrientes_totais_receita', 'nutrientes_por_porcao' e 'custo_total_calculado' calculados aqui.
            // Se você quiser impedir o salvamento, descomente a linha abaixo:
            // json_response(false, "Erro: Dados base de alimentos não disponíveis. Não foi possível calcular nutrientes da ficha.");
        } else if (isset($ficha_para_salvar['ingredientes']) && is_array($ficha_para_salvar['ingredientes']) && !empty($ficha_para_salvar['ingredientes'])) {
            foreach ($ficha_para_salvar['ingredientes'] as $ingrediente_info) {
                if (!isset($ingrediente_info['foodId'], $ingrediente_info['qty']) || !is_numeric($ingrediente_info['qty']) || floatval($ingrediente_info['qty']) <= 0) {
                    error_log("Ingrediente inválido ou com quantidade zerada/negativa na preparação '{$ficha_para_salvar['nome']}' para UserID $logged_user_id. Ingrediente: " . json_encode($ingrediente_info));
                    json_response(false, "Um dos ingredientes da preparação está com dados inválidos (ID ou quantidade). Verifique os ingredientes.");
                }
                $food_id = (string)$ingrediente_info['foodId'];
                $quantidade_bruta_g = floatval($ingrediente_info['qty']);
                $fc_ingrediente = floatval($ingrediente_info['fc'] ?? 1.0); // Pega FC do ingrediente, default 1.0
                $custo_unit_kg = floatval($ingrediente_info['custo_unit_kg'] ?? 0.0); // Pega custo unitário

                // Calcula o peso líquido do ingrediente
                $peso_liquido_ingrediente_g = ($fc_ingrediente > 0) ? ($quantidade_bruta_g / $fc_ingrediente) : 0;
                $peso_liquido_total_calculado_g += $peso_liquido_ingrediente_g;

                // Calcula o custo do ingrediente e acumula no custo total da receita
                $ficha_para_salvar['custo_total_calculado'] += ($peso_liquido_ingrediente_g / 1000.0) * $custo_unit_kg;

                if (isset($alimentos_db_completo[$food_id])) {
                    $alimento_base_data = $alimentos_db_completo[$food_id];
                    foreach ($tracked_nutrients_map as $nutriente_key => $default_value) {
                        if (isset($alimento_base_data[$nutriente_key]) && is_numeric($alimento_base_data[$nutriente_key])) {
                            // Contribuição do nutriente para a receita total
                            // (Valor do nutriente por 100g do alimento base / 100) * Peso líquido do ingrediente
                            $contribuicao = (floatval($alimento_base_data[$nutriente_key]) / 100.0) * $peso_liquido_ingrediente_g;
                            $ficha_para_salvar['nutrientes_totais_receita'][$nutriente_key] += $contribuicao;
                        }
                    }
                } else {
                    error_log("Alerta: Alimento base com ID '$food_id' (referenciado na ficha '{$ficha_para_salvar['nome']}') não foi encontrado em dados.php (\$alimentos_db_completo). Seus nutrientes não serão somados. UserID $logged_user_id.");
                    // Opcional: Falhar aqui se um alimento não for encontrado
                    // json_response(false, "Erro: O alimento base com ID '$food_id' não foi encontrado. A ficha não pode ser salva.");
                }
            }

            // Determina o rendimento final para normalização dos nutrientes por porção
            // Se o usuário forneceu 'rendimento_peso_total_g' (manual), usa esse valor.
            // Caso contrário, usa a soma dos pesos líquidos dos ingredientes.
            $rendimento_peso_final_g = floatval($ficha_para_salvar['rendimento_peso_total_g'] ?? 0.0);
            if ($rendimento_peso_final_g <= 0) {
                $rendimento_peso_final_g = $peso_liquido_total_calculado_g; // Usa o peso líquido total dos ingredientes
                // Atualiza o campo na ficha para salvar o valor calculado se não foi fornecido manualmente
                $ficha_para_salvar['rendimento_peso_total_g'] = round($rendimento_peso_final_g, 2);
            }
            
            // Arredonda o custo total calculado
            $ficha_para_salvar['custo_total_calculado'] = round($ficha_para_salvar['custo_total_calculado'], 2);


            // Calcular nutrientes por porção padrão
            $porcao_padrao_g = floatval($ficha_para_salvar['porcao_padrao_g'] ?? 100.0);
            if ($porcao_padrao_g <= 0) $porcao_padrao_g = 100.0; // Default seguro para evitar divisão por zero

            if ($rendimento_peso_final_g > 0) {
                foreach ($ficha_para_salvar['nutrientes_totais_receita'] as $nutriente_key => $total_na_receita) {
                    // Valor do nutriente por porção padrão = (Total do nutriente na receita / Peso total da receita) * Peso da porção padrão
                    $valor_por_porcao = (floatval($total_na_receita) / $rendimento_peso_final_g) * $porcao_padrao_g;
                    $ficha_para_salvar['nutrientes_por_porcao'][$nutriente_key] = round($valor_por_porcao, 2); // Arredonda para 2 casas decimais
                }
            } else {
                 error_log("Peso total da receita '{$ficha_para_salvar['nome']}' é zero ou inválido. Nutrientes por porção não calculados. UserID $logged_user_id.");
                 // Garante que os valores por porção sejam 0 se o rendimento for 0
                 $ficha_para_salvar['nutrientes_por_porcao'] = array_map(function() { return 0.0; }, $tracked_nutrients_map);
            }
        } else {
             error_log("Ficha '{$ficha_para_salvar['nome']}' não possui ingredientes ou a lista está vazia. Nutrientes totais e por porção serão zerados (ou como definidos no mapa). UserID $logged_user_id.");
             // Zera todos os valores se não houver ingredientes
             $ficha_para_salvar['nutrientes_totais_receita'] = array_map(function() { return 0.0; }, $tracked_nutrients_map);
             $ficha_para_salvar['nutrientes_por_porcao'] = array_map(function() { return 0.0; }, $tracked_nutrients_map);
             $ficha_para_salvar['custo_total_calculado'] = 0.0;
             $ficha_para_salvar['rendimento_peso_total_g'] = 0.0;
        }
        // --- FIM DO CÁLCULO DE NUTRIENTES E CUSTO ---

        // Callback para a função de gerenciamento
        $callback = function($preparacoes_existentes) use ($ficha_para_salvar, $action) {
            $id_prep = $ficha_para_salvar['id'];
            
            $timestamp_iso8601 = date('c'); // Formato ISO 8601 (ex: 2023-10-27T15:30:00+00:00)
            $ficha_para_salvar['updated_at'] = $timestamp_iso8601;

            if ($action === 'create_preparacao' || !isset($preparacoes_existentes[$id_prep]['created_at'])) {
                $ficha_para_salvar['created_at'] = $timestamp_iso8601;
            } else {
                // Mantém o created_at original se estiver atualizando e ele já existir
                $ficha_para_salvar['created_at'] = $preparacoes_existentes[$id_prep]['created_at'] ?? $timestamp_iso8601;
            }
            
            $preparacoes_existentes[$id_prep] = $ficha_para_salvar; // Adiciona/atualiza a ficha completa
            $log_msg = "Ficha ID $id_prep " . ($action === 'create_preparacao' ? "CRIADA" : "ATUALIZADA");
            return ['success' => true, 'preparacoes' => $preparacoes_existentes, 'log_message' => $log_msg, 'saved_prep_id' => $id_prep];
        };

        $manage_response = gerenciarPreparacoesDoUsuario($pdo, $logged_user_id, $callback);
        
        if ($manage_response['success']) {
            $response_data_payload['todas_preparacoes_atualizadas'] = $manage_response['preparations'];
            $response_data_payload['preparacao_salva_id'] = $manage_response['saved_prep_id']; // Adiciona o ID da ficha salva
            json_response(true, 'Ficha técnica ' . ($action === 'create_preparacao' ? 'criada' : 'atualizada') . ' com sucesso!', $response_data_payload);
        } else {
            $response_data_payload['todas_preparacoes_atualizadas'] = $manage_response['preparations']; // Mesmo em falha, retorna o estado anterior
            json_response(false, $manage_response['message'] ?? 'Erro desconhecido ao salvar ficha técnica.', $response_data_payload);
        }
        break;

    case 'delete_preparacao':
        $preparacao_id_delete = $_POST['preparacao_id'] ?? null;
        if (empty($preparacao_id_delete) || !is_string($preparacao_id_delete)) { // Validação básica do ID
            json_response(false, 'ID da preparação para excluir não fornecido ou inválido.');
        }
        
        $callback_delete = function($preparacoes_existentes) use ($preparacao_id_delete) {
            $log_msg = "";
            if (isset($preparacoes_existentes[$preparacao_id_delete])) {
                unset($preparacoes_existentes[$preparacao_id_delete]);
                $log_msg = "Ficha ID $preparacao_id_delete DELETADA";
            } else {
                $log_msg = "Tentativa de deletar ficha ID $preparacao_id_delete (não encontrada)";
            }
            return ['success' => true, 'preparacoes' => $preparacoes_existentes, 'log_message' => $log_msg];
        };
            
        $manage_response_delete = gerenciarPreparacoesDoUsuario($pdo, $logged_user_id, $callback_delete);

        if ($manage_response_delete['success']) {
            $response_data_payload['todas_preparacoes_atualizadas'] = $manage_response_delete['preparations'];
            json_response(true, 'Ficha técnica excluída com sucesso!', $response_data_payload);
        } else {
            $response_data_payload['todas_preparacoes_atualizadas'] = $manage_response_delete['preparations'];
            json_response(false, $manage_response_delete['message'] ?? 'Erro ao excluir ficha.', $response_data_payload);
        }
        break;

    default:
        json_response(false, 'Ação inválida ou não especificada.');
        break;
}
?>
