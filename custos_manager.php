<?php
// cardapio_auto/custos_manager.php

// 1. Configuração de Sessão
$session_cookie_path = '/';
$session_name = "CARDAPIOSESSID"; // Mesmo nome da sessão
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => $session_cookie_path, 'domain' => $_SERVER['HTTP_HOST'] ?? '', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', 'httponly' => true, 'samesite' => 'Lax']);
}
session_name($session_name);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Configuração de Erros e Resposta JSON
error_reporting(0); 
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
header('Content-Type: application/json');

function send_json_response($success, $message = '', $data = null) {
    $response = ['success' => (bool)$success, 'message' => $message];
    if ($data !== null) {
        if ($success && isset($data['custos_atualizados'])) { 
            $response['custos_atualizados'] = $data['custos_atualizados'];
        } else {
            $response['data'] = $data; 
        }
    }
    echo json_encode($response);
    exit;
}

// 3. Verificação de Autenticação
if (!isset($_SESSION['user_id'])) {
    error_log("custos_manager.php: Tentativa de acesso não autenticada.");
    send_json_response(false, 'Acesso não autorizado. Faça login novamente.');
}

$action = $_POST['action'] ?? null;
$custos_file_path = __DIR__ . '/custos_alimentos.json'; 

if ($action === 'save_custos') {
    $custos_data_json_string = $_POST['custos_data'] ?? null;
    if ($custos_data_json_string === null) {
        send_json_response(false, 'Dados de custos não recebidos.');
    }

    $novos_custos_recebidos = json_decode($custos_data_json_string, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("custos_manager.php: Erro ao decodificar JSON recebido: " . json_last_error_msg() . ". JSON: " . substr($custos_data_json_string,0,200));
        send_json_response(false, 'Formato de dados de custos inválido.');
    }

    // A lógica agora é que $novos_custos_recebidos contém o ESTADO COMPLETO DESEJADO do JSON.
    // Se um ID não está em $novos_custos_recebidos, ele não deveria estar no arquivo final.
    // Então, simplesmente sanitizamos e salvamos o que foi recebido.
    $custos_para_salvar_final = [];
    if (is_array($novos_custos_recebidos)) {
        foreach ($novos_custos_recebidos as $alimento_id => $data_custo_novo) {
            $alimento_id_str = (string)$alimento_id;
            if (is_array($data_custo_novo) && isset($data_custo_novo['custo_kg'])) {
                $custo_kg_val = filter_var($data_custo_novo['custo_kg'], FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]);
                if ($custo_kg_val !== false) { 
                    $custos_para_salvar_final[$alimento_id_str] = ['custo_kg' => round($custo_kg_val, 2)];
                } else {
                     error_log("custos_manager.php: Custo inválido para ID {$alimento_id_str} ignorado e não será salvo: " . print_r($data_custo_novo['custo_kg'], true));
                }
            }
        }
    }
    
    $json_final_para_salvar = json_encode($custos_para_salvar_final, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("custos_manager.php: Erro ao re-encodar JSON para salvar: " . json_last_error_msg());
        send_json_response(false, 'Erro ao processar dados de custos para salvar.');
    }

    if (!is_dir(__DIR__)) { @mkdir(__DIR__, 0755, true); }

    if (@file_put_contents($custos_file_path, $json_final_para_salvar) !== false) {
        error_log("custos_manager.php: Custos salvos em custos_alimentos.json. UserID: ".$_SESSION['user_id']);
        send_json_response(true, 'Custos salvos com sucesso!', ['custos_atualizados' => $custos_para_salvar_final]);
    } else {
        $error = error_get_last();
        error_log("custos_manager.php: Falha ao escrever custos_alimentos.json. UserID: ".$_SESSION['user_id'].". Erro PHP: " . ($error['message'] ?? 'Desconhecido'));
        send_json_response(false, 'Erro ao salvar os custos. Verifique as permissões do arquivo/pasta no servidor.');
    }

} else {
    error_log("custos_manager.php: Ação inválida: " . htmlspecialchars($action ?? 'N/A'));
    send_json_response(false, 'Ação inválida.');
}
?>