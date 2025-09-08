<?php
// cardapio_auto/project_actions.php
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
    error_log("Acesso não autenticado a project_actions.php.");
     http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado. Faça o login novamente.']);
    exit;
}
// --- Fim Verificação ---

// Inicializa a resposta
$response = ['success' => false, 'message' => 'Ação inválida ou não fornecida.'];
$action = $_POST['action'] ?? $_GET['action'] ?? null; // Permite GET para testes rápidos se necessário, mas POST é o ideal.
$pdo = null;

// Log para depurar dados recebidos
$request_data = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
error_log("project_actions.php: UserID $logged_user_id, Ação '$action', Dados: " . print_r($request_data, true));


// Tenta conectar ao BD
try {
    require_once 'includes/db_connect.php'; // Define $pdo

    // --- Processa a Ação ---
    if ($action === 'create') {
        $nome_projeto = trim($request_data['nome_projeto'] ?? '');
        if (empty($nome_projeto)) {
            $response['message'] = 'O nome do cardápio é obrigatório.';
        } elseif (mb_strlen($nome_projeto, 'UTF-8') > 100) { // Usar mb_strlen para multibyte
            $response['message'] = 'O nome do cardápio é muito longo (máx 100 caracteres).';
        } else {
            // CHECKPOINT: Confirme o nome da coluna 'dados_json' ou altere para 'dados_cardapio_json' se for o caso.
            $sql = "INSERT INTO cardapio_projetos (usuario_id, nome_projeto, dados_json, created_at, updated_at) VALUES (:uid, :nome, :json, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $dados_iniciais_vazios = '{}'; // Começa vazio
            if ($stmt->execute([':uid' => $logged_user_id, ':nome' => $nome_projeto, ':json' => $dados_iniciais_vazios])) {
                $novo_id = $pdo->lastInsertId();
                $response['success'] = true;
                $response['message'] = 'Cardápio criado com sucesso!';
                $response['projeto_id'] = $novo_id;
                error_log("Cardápio criado: ID " . $novo_id . " por User ID " . $logged_user_id);
            } else {
                $response['message'] = 'Erro ao salvar o novo cardápio no banco de dados.';
                 error_log("Falha INSERT (Criar Cardápio) User $logged_user_id. Erro PDO: " . implode(":", $stmt->errorInfo()));
            }
        }
    } elseif ($action === 'rename') {
        $projeto_id = filter_var($request_data['projeto_id'] ?? null, FILTER_VALIDATE_INT);
        $novo_nome = trim($request_data['novo_nome'] ?? '');
        if (!$projeto_id) $response['message'] = 'ID do cardápio inválido.';
        elseif (empty($novo_nome)) $response['message'] = 'O novo nome não pode estar vazio.';
        elseif (mb_strlen($novo_nome, 'UTF-8') > 100) $response['message'] = 'O novo nome é muito longo.';
        else {
            // Adiciona updated_at na query de rename
            $sql = "UPDATE cardapio_projetos SET nome_projeto = :nome, updated_at = NOW() WHERE id = :pid AND usuario_id = :uid";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([':nome' => $novo_nome, ':pid' => $projeto_id, ':uid' => $logged_user_id])) {
                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Cardápio renomeado com sucesso!';
                     error_log("Cardápio renomeado: ID $projeto_id para '$novo_nome' por User ID $logged_user_id");
                } else {
                    $response['message'] = 'Cardápio não encontrado ou permissão negada para renomear.';
                     error_log("Falha UPDATE (Renomear Cardápio): rowCount 0. User $logged_user_id tentou renomear Proj $projeto_id.");
                }
            } else {
                $response['message'] = 'Erro ao atualizar o nome do cardápio no banco de dados.';
                 error_log("Falha EXECUTE (Renomear Cardápio) User $logged_user_id, Proj $projeto_id. Erro PDO: " . implode(":", $stmt->errorInfo()));
            }
        }
    } elseif ($action === 'delete') {
        $projeto_id = filter_var($request_data['projeto_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$projeto_id) $response['message'] = 'ID do cardápio inválido.';
        else {
            // CHECKPOINT: Considere o que fazer com dados relacionados em outras tabelas (ON DELETE CASCADE ou manual).
            $sql = "DELETE FROM cardapio_projetos WHERE id = :pid AND usuario_id = :uid";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([':pid' => $projeto_id, ':uid' => $logged_user_id])) {
                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Cardápio excluído com sucesso!';
                     error_log("Cardápio excluído: ID $projeto_id por User ID $logged_user_id");
                } else {
                    $response['message'] = 'Cardápio não encontrado ou permissão negada para excluir.';
                     error_log("Falha DELETE (Excluir Cardápio): rowCount 0. User $logged_user_id tentou excluir Proj $projeto_id.");
                }
            } else {
                $response['message'] = 'Erro ao excluir o cardápio do banco de dados.';
                 error_log("Falha EXECUTE (Excluir Cardápio) User $logged_user_id, Proj $projeto_id. Erro PDO: " . implode(":", $stmt->errorInfo()));
            }
        }
    } elseif ($action === 'duplicate') { // NOVA AÇÃO
        $projeto_id_original = filter_var($request_data['projeto_id'] ?? null, FILTER_VALIDATE_INT);

        if (!$projeto_id_original) {
            $response['message'] = 'ID do cardápio original não fornecido para duplicação.';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Buscar dados do projeto original
                // CHECKPOINT: Verifique se 'dados_json' é o nome correto da coluna com o conteúdo do cardápio.
                // Se tiver outros campos para copiar, adicione-os aqui.
                $stmt_original = $pdo->prepare("SELECT nome_projeto, dados_json FROM cardapio_projetos WHERE id = :id AND usuario_id = :usuario_id");
                $stmt_original->bindParam(':id', $projeto_id_original, PDO::PARAM_INT);
                $stmt_original->bindParam(':usuario_id', $logged_user_id, PDO::PARAM_INT);
                $stmt_original->execute();
                $original = $stmt_original->fetch(PDO::FETCH_ASSOC);

                if (!$original) {
                    $response['message'] = 'Cardápio original não encontrado ou não pertence a você.';
                    $pdo->rollBack();
                } else {
                    // 2. Criar novo nome para a cópia, evitando duplicatas
                    $base_novo_nome = $original['nome_projeto'];
                    $sufixo_copia = " (Cópia)";
                    $novo_nome_projeto = $base_novo_nome . $sufixo_copia;
                    $contador = 2;

                    // Verifica se já existe um projeto com o nome da cópia
                    $stmt_check_nome = $pdo->prepare("SELECT COUNT(*) FROM cardapio_projetos WHERE nome_projeto = :nome AND usuario_id = :uid");
                    $stmt_check_nome->bindParam(':uid', $logged_user_id, PDO::PARAM_INT);
                    
                    $nome_ja_existe = true;
                    while($nome_ja_existe) {
                        $stmt_check_nome->bindParam(':nome', $novo_nome_projeto, PDO::PARAM_STR);
                        $stmt_check_nome->execute();
                        if ($stmt_check_nome->fetchColumn() == 0) {
                            $nome_ja_existe = false; // Nome está livre
                        } else {
                            $novo_nome_projeto = $base_novo_nome . $sufixo_copia . " " . $contador;
                            $contador++;
                        }
                    }

                    // 3. Inserir novo projeto (cópia)
                    // CHECKPOINT: Garanta que todos os campos relevantes sejam copiados.
                    $sql_novo = "INSERT INTO cardapio_projetos (usuario_id, nome_projeto, dados_json, created_at, updated_at) 
                                 VALUES (:uid, :nome, :dados_originais, NOW(), NOW())";
                    $stmt_novo = $pdo->prepare($sql_novo);
                    
                    $stmt_novo->bindParam(':uid', $logged_user_id, PDO::PARAM_INT);
                    $stmt_novo->bindParam(':nome', $novo_nome_projeto, PDO::PARAM_STR);
                    // Copia o JSON do cardápio original
                    $stmt_novo->bindParam(':dados_originais', $original['dados_json'], PDO::PARAM_STR); 

                    if ($stmt_novo->execute()) {
                        $novo_projeto_id = $pdo->lastInsertId();
                        $pdo->commit();

                        // Formata a data para exibir na UI imediatamente
                        $data_formatada = date("d/m/Y H:i");

                        $response['success'] = true;
                        $response['message'] = 'Cardápio duplicado com sucesso!';
                        $response['novo_projeto'] = [
                            'id' => $novo_projeto_id,
                            'nome_projeto' => $novo_nome_projeto,
                            'updated_at_formatada' => $data_formatada // JS espera isso
                            // Adicione aqui quaisquer outros campos que o JS precise para renderizar o novo item na lista
                        ];
                        error_log("Cardápio duplicado: ID Original $projeto_id_original -> Novo ID $novo_projeto_id ('$novo_nome_projeto') por User ID $logged_user_id");
                    } else {
                        $pdo->rollBack();
                        $response['message'] = 'Erro ao salvar a cópia do cardápio.';
                        error_log("Falha INSERT (Duplicar Cardápio) User $logged_user_id. Erro PDO: " . implode(":", $stmt_novo->errorInfo()));
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { // Só faz rollback se a transação foi iniciada com sucesso
                    $pdo->rollBack();
                }
                $response['message'] = 'Erro no banco de dados ao duplicar o cardápio.';
                error_log("Erro PDOException (Duplicar Cardápio) User $logged_user_id, ProjOrig $projeto_id_original: " . $e->getMessage());
            }
        }
    }
    // Se $action não corresponder a nenhuma das ações acima, a $response inicial será usada.

} catch (\PDOException $e) {
    $response['success'] = false;
    $response['message'] = 'Erro interno do servidor [DB].';
    error_log("Erro PDOException GERAL em project_actions.php para User ID $logged_user_id, Ação '$action': " . $e->getMessage() . ". Trace: " . $e->getTraceAsString());
     http_response_code(500);
} catch (\Throwable $th) {
     $response['success'] = false;
     $response['message'] = 'Erro interno do servidor [General].';
     error_log("Erro Throwable GERAL em project_actions.php para User ID $logged_user_id, Ação '$action': " . $th->getMessage() . ". Trace: " . $th->getTraceAsString());
     http_response_code(500);
}

// Envia a resposta JSON final
echo json_encode($response);
exit;
?>