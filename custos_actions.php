<?php
// cardapio_auto/custos_actions.php

// 1. Configuração de Sessão
$session_cookie_path = '/';
$session_name = "CARDAPIOSESSID";
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => $session_cookie_path, 'domain' => $_SERVER['HTTP_HOST'] ?? '', 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', 'httponly' => true, 'samesite' => 'Lax']);
}
session_name($session_name);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Configuração de Erros e Resposta JSON
error_reporting(0); // Para produção, não mostrar erros diretamente
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
header('Content-Type: application/json');

// Função de resposta
function send_json_response($data) {
    echo json_encode($data);
    exit;
}

// 3. Verificação de Autenticação
if (!isset($_SESSION['user_id'])) {
    error_log("custos_actions.php: Tentativa de acesso não autenticada. Session ID: " . session_id());
    send_json_response(['success' => false, 'message' => 'Acesso não autorizado. Faça login novamente.']);
}
$logged_user_id = $_SESSION['user_id'];

// 4. Conexão com Banco de Dados
$pdo = null;
try {
    require_once 'includes/db_connect.php'; // Define $pdo
    if (!$pdo) {
        throw new \RuntimeException("Falha ao obter objeto PDO de db_connect.php");
    }
} catch (\Throwable $e) {
    error_log("CRITICAL (custos_actions.php): Erro ao conectar/incluir BD: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => 'Erro crítico de conexão com o banco de dados.']);
}

// 5. Processamento da Ação
$action = $_POST['action'] ?? null;

if ($action === 'save_custos') {
    $custos_data_json = $_POST['custos_data'] ?? null;
    if (!$custos_data_json) {
        send_json_response(['success' => false, 'message' => 'Dados de custos não recebidos.']);
    }

    $custos_data_array = json_decode($custos_data_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("custos_actions.php (UserID: $logged_user_id): Erro ao decodificar JSON de custos: " . json_last_error_msg() . ". JSON Recebido: " . substr($custos_data_json, 0, 200));
        send_json_response(['success' => false, 'message' => 'Formato de dados de custos inválido. Erro JSON: ' . json_last_error_msg()]);
    }

    // Validação e sanitização dos custos (exemplo simples)
    $custos_sanitizados = [];
    if (is_array($custos_data_array)) {
        foreach ($custos_data_array as $alimento_id => $data) {
            // Valida se alimento_id é numérico ou string que pode ser convertida para número
            if (!is_numeric($alimento_id)) {
                 error_log("custos_actions.php (UserID: $logged_user_id): ID de alimento inválido ignorado: " . $alimento_id);
                 continue; // Ignora IDs de alimentos inválidos
            }
            $alimento_id_str = (string)$alimento_id; // Garante que seja string para a chave do array

            if (is_array($data) && isset($data['custo_kg'])) {
                $custo_kg = filter_var($data['custo_kg'], FILTER_VALIDATE_FLOAT);
                if ($custo_kg !== false && $custo_kg >= 0) {
                    $custos_sanitizados[$alimento_id_str] = ['custo_kg' => round($custo_kg, 2)]; // Arredonda para 2 casas decimais
                } else {
                     error_log("custos_actions.php (UserID: $logged_user_id): Custo inválido para alimento ID $alimento_id_str ignorado: " . print_r($data['custo_kg'], true));
                }
            }
            // Se $data for null (conforme lógica JS), essa chave não será adicionada a $custos_sanitizados,
            // o que efetivamente a remove se ela existia antes (se a lógica for sobrescrever todo o JSON).
            // Se a intenção for só atualizar/adicionar, então o merge com dados existentes seria necessário antes.
            // Por simplicidade, vamos sobrescrever todo o JSON.
        }
    }
    
    $json_para_salvar = json_encode($custos_sanitizados);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("custos_actions.php (UserID: $logged_user_id): Erro ao re-encodar JSON de custos sanitizados: " . json_last_error_msg());
        send_json_response(['success' => false, 'message' => 'Erro ao processar dados de custos para salvar.']);
    }

    try {
        $sql_update_custos = "UPDATE cardapio_usuarios SET custos_alimentos_json = :custos_json WHERE id = :user_id";
        $stmt = $pdo->prepare($sql_update_custos);
        $stmt->bindParam(':custos_json', $json_para_salvar, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $logged_user_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            error_log("custos_actions.php (UserID: $logged_user_id): Custos de alimentos salvos com sucesso. JSON: " . substr($json_para_salvar, 0, 200));
            send_json_response(['success' => true, 'message' => 'Custos salvos com sucesso!', 'custos_atualizados' => $custos_sanitizados]);
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("custos_actions.php (UserID: $logged_user_id): Erro SQL ao salvar custos: " . ($errorInfo[2] ?? 'Erro desconhecido'));
            send_json_response(['success' => false, 'message' => 'Erro ao salvar os custos no banco de dados.']);
        }
    } catch (\PDOException $e) {
        error_log("custos_actions.php (UserID: $logged_user_id): Exceção PDO ao salvar custos: " . $e->getMessage());
        send_json_response(['success' => false, 'message' => 'Exceção no banco de dados ao salvar custos.']);
    }

} else {
    error_log("custos_actions.php (UserID: $logged_user_id): Ação inválida recebida: " . htmlspecialchars($action ?? 'N/A'));
    send_json_response(['success' => false, 'message' => 'Ação inválida.']);
}
?>