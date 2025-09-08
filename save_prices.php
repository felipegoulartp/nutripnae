<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];
$logged_user_id = $_SESSION['user_id'] ?? null;

if (!$logged_user_id) {
    $response['message'] = 'Usuário não autenticado.';
    echo json_encode($response);
    exit;
}

$precos_json = $_POST['precos_json'] ?? null;

if (!$precos_json) {
    $response['message'] = 'Dados de preços ausentes.';
    echo json_encode($response);
    exit;
}

try {
    require_once 'includes/db_connect.php'; // Sua conexão com o banco de dados

    $sql = "UPDATE cardapio_usuarios SET precos_alimentos_json = :precos_json WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':precos_json', $precos_json, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $logged_user_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Preços salvos com sucesso!';
    } else {
        $response['message'] = 'Falha ao atualizar preços no banco de dados.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Erro de banco de dados: ' . $e->getMessage();
    error_log("PDOException save_prices.php: " . $e->getMessage());
} catch (Throwable $th) {
    $response['message'] = 'Erro inesperado: ' . $th->getMessage();
    error_log("Throwable save_prices.php: " . $th->getMessage());
}

echo json_encode($response);
?>