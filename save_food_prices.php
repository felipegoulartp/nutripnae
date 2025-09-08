<?php
// cardapio_auto/save_food_prices.php

// 1. Configuração de Sessão (ANTES DE TUDO)
// Garante que a sessão seja iniciada para acessar $_SESSION
$session_cookie_path = '/'; 
@session_set_cookie_params([
    'lifetime' => 0, 'path' => $session_cookie_path, 'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', 'httponly' => true, 'samesite' => 'Lax'
]);
@session_name("CARDAPIOSESSID");
if (session_status() === PHP_SESSION_NONE) {
     @session_start();
}

// 2. Configuração de Erros
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desabilitar em produção
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

// 3. Verificação de Autenticação
$is_logged_in = isset($_SESSION['user_id']);
$logged_user_id = $_SESSION['user_id'] ?? null;

// Se o usuário não está autenticado, retorna um erro e sai.
if (!$is_logged_in || !$logged_user_id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

// O resto do script só roda se o usuário estiver autenticado.
header('Content-Type: application/json; charset=utf-8');

$input_json = $_POST['food_prices'] ?? null;

if (!$input_json) {
    echo json_encode(['success' => false, 'message' => 'Nenhum dado de preço recebido.']);
    exit;
}

$updated_prices = json_decode($input_json, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'JSON inválido: ' . json_last_error_msg()]);
    exit;
}

$food_prices_file = __DIR__ . '/food_prices.json';

try {
    // Ler os preços atuais do arquivo
    $current_prices = [];
    if (file_exists($food_prices_file) && is_readable($food_prices_file)) {
        $file_content = file_get_contents($food_prices_file);
        $decoded_file_content = json_decode($file_content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_file_content)) {
            $current_prices = $decoded_file_content;
        } else {
            error_log("Erro: food_prices.json contém JSON inválido ou não é um array. Conteúdo: " . substr($file_content, 0, 200));
            // Opcional: redefinir o arquivo se estiver corrompido
            file_put_contents($food_prices_file, json_encode([]));
        }
    }

    // Mesclar os preços atualizados com os preços existentes
    // A lógica de mesclagem é importante:
    // Para cada foodId recebido, sobrescreve/adiciona as categorias de preço.
    foreach ($updated_prices as $food_id => $categories) {
        if (!isset($current_prices[$food_id])) {
            $current_prices[$food_id] = [];
        }
        foreach ($categories as $category_name => $price_data) {
            // Garante que price é float e unit é string
            $price_data['price'] = floatval($price_data['price']);
            $price_data['unit'] = strval($price_data['unit']);
            $current_prices[$food_id][$category_name] = $price_data;
        }
    }

    // Salvar o array completo de preços no arquivo JSON
    $result = file_put_contents($food_prices_file, json_encode($current_prices, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    if ($result !== false) {
        echo json_encode(['success' => true, 'message' => 'Preços salvos com sucesso.']);
    } else {
        error_log("Erro ao escrever no arquivo food_prices.json. Verifique as permissões de pasta.");
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar preços no servidor. Verifique permissões.']);
    }

} catch (Throwable $e) {
    error_log("Erro crítico em save_food_prices.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor ao salvar preços.']);
}
?>