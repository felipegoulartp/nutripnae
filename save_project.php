<?php
// cardapio_auto/save_project.php
session_name("CARDAPIOSESSID");
session_start();

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', 0); // API não deve mostrar erros PHP
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

// Define o tipo de resposta como JSON no início
header('Content-Type: application/json; charset=utf-8');

// --- Verificação de Autenticação ---
$logged_user_id = $_SESSION['user_id'] ?? null;
if (!$logged_user_id) {
    error_log("Acesso não autenticado a save_project.php.");
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado. Faça o login novamente.']);
    exit;
}
// --- Fim Verificação ---

// Inicializa a resposta
$response = ['success' => false, 'message' => 'Requisição inválida ou dados ausentes.'];
$pdo = null;

// Log para depurar dados recebidos (útil para ver se o JSON chega)
// Cuidado: O JSON pode ser muito grande para logar inteiro em produção.
// error_log("save_project.php recebido POST para User ID $logged_user_id: " . print_r($_POST, true));


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $projeto_id = filter_input(INPUT_POST, 'projeto_id', FILTER_VALIDATE_INT);
    $dados_json = $_POST['dados_json'] ?? null;

    if (!$projeto_id) {
        $response['message'] = 'ID do projeto ausente ou inválido.';
    } elseif ($dados_json === null) {
        $response['message'] = 'Dados do cardápio ausentes (dados_json).';
    } else {
        // Valida se o JSON recebido é válido
        json_decode($dados_json);
        if (json_last_error() !== JSON_ERROR_NONE) {
             $response['message'] = 'Formato de dados inválido (JSON corrompido). Erro: ' . json_last_error_msg();
             error_log("Erro ao decodificar JSON recebido para salvar Proj ID $projeto_id (User $logged_user_id): " . json_last_error_msg());
        } else {
            // Tenta conectar e atualizar
            try {
                require_once 'includes/db_connect.php'; // Define $pdo

                $sql_update = "UPDATE cardapio_projetos
                               SET dados_json = :dados_json
                               WHERE id = :projeto_id AND usuario_id = :usuario_id";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->bindParam(':dados_json', $dados_json, PDO::PARAM_STR);
                $stmt_update->bindParam(':projeto_id', $projeto_id, PDO::PARAM_INT);
                $stmt_update->bindParam(':usuario_id', $logged_user_id, PDO::PARAM_INT);

                if ($stmt_update->execute()) {
                    if ($stmt_update->rowCount() > 0) {
                        $response['success'] = true;
                        $response['message'] = 'Cardápio salvo com sucesso!';
                         error_log("Projeto salvo: ID $projeto_id por User ID $logged_user_id");
                    } else {
                        // Verifica se o projeto existe para dar uma msg melhor
                        $checkStmt = $pdo->prepare("SELECT 1 FROM cardapio_projetos WHERE id = :pid");
                        $checkStmt->execute([':pid' => $projeto_id]);
                        if ($checkStmt->fetch()) {
                             $response['message'] = 'Erro: Permissão negada para salvar este cardápio.';
                             error_log("Falha ao salvar (Permissão): User $logged_user_id tentou salvar Proj $projeto_id de outro dono?");
                        } else {
                             $response['message'] = 'Erro: Cardápio não encontrado (ID: ' . $projeto_id . ').';
                              error_log("Falha ao salvar (Não encontrado): User $logged_user_id tentou salvar Proj $projeto_id inexistente.");
                        }
                        // Log genérico de rowCount 0
                         error_log("Falha ao salvar (rowCount 0): User $logged_user_id, Proj $projeto_id.");
                    }
                } else {
                    $response['message'] = 'Erro ao executar a atualização no banco de dados.';
                    error_log("Falha EXECUTE (Salvar Projeto) User $logged_user_id, Proj $projeto_id. Erro PDO: " . implode(":", $stmt_update->errorInfo()));
                }

            } catch (\PDOException $e) {
                 $response['success'] = false;
                 $response['message'] = 'Erro interno do servidor [DB] ao salvar.';
                 error_log("Erro PDOException GERAL em save_project.php para User ID $logged_user_id, Proj ID $projeto_id: " . $e->getMessage());
                 http_response_code(500);
            } catch (\Throwable $th) {
                 $response['success'] = false;
                 $response['message'] = 'Erro interno do servidor [General] ao salvar.';
                 error_log("Erro Throwable GERAL em save_project.php para User ID $logged_user_id, Proj ID $projeto_id: " . $th->getMessage());
                 http_response_code(500);
            }
        }
    }
} else {
     http_response_code(405); // Method Not Allowed
     $response['message'] = 'Método não permitido.';
}

// Envia a resposta JSON final
echo json_encode($response);
exit;
?>