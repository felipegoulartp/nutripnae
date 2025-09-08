<?php
// cardapio_auto/custos_manager.php

// Configuração de Sessão e Erros
$session_cookie_path = '/';
$session_name = "CARDAPIOSESSID"; // Mesmo nome da sessão usado em login.php e home.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => $session_cookie_path, 'domain' => $_SERVER['HTTP_HOST'] ?? '', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', 'httponly' => true, 'samesite' => 'Lax']);
}
session_name($session_name);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(0); // Não mostrar erros para o cliente
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
header('Content-Type: application/json');

// Função de resposta JSON
function send_json_response($success, $message = '', $data = null) {
    $response = ['success' => (bool)$success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data; // Para dados gerais
        if ($success && isset($data['custos_atualizados'])) { // Específico para esta action
            $response['custos_atualizados'] = $data['custos_atualizados'];
        }
    }
    echo json_encode($response);
    exit;
}

// Verificação de Autenticação
if (!isset($_SESSION['user_id'])) {
    error_log("custos_manager.php: Tentativa de acesso não autenticada. Session ID: " . session_id());
    send_json_response(false, 'Acesso não autorizado. Faça login novamente.');
}
// $logged_user_id = $_SESSION['user_id']; // Não usado diretamente, mas bom ter para logs futuros se necessário

$action = $_POST['action'] ?? null;
$custos_file_path = __DIR__ . '/custos_alimentos.json';

if ($action === 'save_custos') {
    $custos_data_json_string = $_POST['custos_data'] ?? null;
    if ($custos_data_json_string === null) {
        send_json_response(false, 'Dados de custos não recebidos.');
    }

    $novos_custos_array = json_decode($custos_data_json_string, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("custos_manager.php: Erro ao decodificar JSON de custos recebido: " . json_last_error_msg() . ". JSON: " . substr($custos_data_json_string,0,200));
        send_json_response(false, 'Formato de dados de custos inválido. Erro: ' . json_last_error_msg());
    }

    $custos_sanitizados_para_salvar = [];
    if (is_array($novos_custos_array)) {
        foreach ($novos_custos_array as $alimento_id => $data) {
            $alimento_id_str = (string)$alimento_id;
            if (is_array($data) && isset($data['custo_kg'])) {
                $custo_kg_val = filter_var($data['custo_kg'], FILTER_VALIDATE_FLOAT);
                if ($custo_kg_val !== false && $custo_kg_val >= 0) {
                    $custos_sanitizados_para_salvar[$alimento_id_str] = ['custo_kg' => round($custo_kg_val, 2)];
                }
            }
            // Se um ID não estiver presente em $novos_custos_array, ele será removido do arquivo final,
            // assumindo que $novos_custos_array contém o estado completo dos custos que devem ser salvos.
            // Se a intenção fosse um update parcial, a lógica aqui precisaria carregar o JSON existente e fazer merge.
            // Pela simplicidade do JS enviado, estamos sobrescrevendo o arquivo com o que foi enviado.
        }
    }
    
    $json_final_para_salvar = json_encode($custos_sanitizados_para_salvar, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("custos_manager.php: Erro ao re-encodar JSON de custos para salvar: " . json_last_error_msg());
        send_json_response(false, 'Erro ao processar dados de custos para salvar.');
    }

    if (file_put_contents($custos_file_path, $json_final_para_salvar) !== false) {
        error_log("custos_manager.php: Custos de alimentos salvos em custos_alimentos.json com sucesso. UserID: ".$_SESSION['user_id']);
        // Retorna os custos que foram efetivamente salvos para que o JS possa atualizar seu estado
        send_json_response(true, 'Custos salvos com sucesso!', ['custos_atualizados' => $custos_sanitizados_para_salvar]);
    } else {
        error_log("custos_manager.php: Falha ao escrever no arquivo custos_alimentos.json. Verifique as permissões. UserID: ".$_SESSION['user_id']);
        send_json_response(false, 'Erro ao salvar os custos. Verifique as permissões do servidor.');
    }

} else {
    error_log("custos_manager.php: Ação inválida recebida: " . htmlspecialchars($action ?? 'N/A'));
    send_json_response(false, 'Ação inválida.');
}
?>