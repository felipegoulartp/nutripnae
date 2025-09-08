<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'daily_quantities' => [], 'weekly_quantities' => []];
$logged_user_id = $_SESSION['user_id'] ?? null;
$project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);

if (!$logged_user_id) {
    $response['message'] = 'Usuário não autenticado.';
    echo json_encode($response);
    exit;
}
if (!$project_id) {
    $response['message'] = 'ID do projeto inválido.';
    echo json_encode($response);
    exit;
}

try {
    require_once 'includes/db_connect.php'; // Sua conexão com o banco de dados
    require_once 'dados.php'; // Para ter acesso a $alimentos_db e preparações

    // Carregar dados do cardápio
    $sql = "SELECT dados_json FROM cardapio_projetos WHERE id = :project_id AND usuario_id = :user_id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $logged_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $project_data = $stmt->fetch();

    if (!$project_data) {
        $response['message'] = 'Projeto não encontrado ou acesso negado.';
        echo json_encode($response);
        exit;
    }

    $cardapio_json = $project_data['dados_json'];
    $cardapio_decoded = json_decode($cardapio_json, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($cardapio_decoded) || !isset($cardapio_decoded['dias'])) {
        $response['message'] = 'Dados do cardápio corrompidos.';
        echo json_encode($response);
        exit;
    }

    $alimentos_completos = []; // Alimentos base e preparações do usuário (para recursão)
    // Carregar alimentos base
    foreach ($lista_selecionaveis_db as $id => $data) {
        if (isset($data['nome']) && isset($alimentos_db[$id])) {
            $id_str = (string)$id;
            $alimentos_completos[$id_str] = [
                'id' => $id_str,
                'nome' => $data['nome'],
                'isPreparacao' => false,
                'ingredientes' => []
            ];
        }
    }
    // Carregar preparações personalizadas do usuário (similar ao custos.php)
    $sql_prep = "SELECT preparacoes_personalizadas_json FROM cardapio_usuarios WHERE id = :user_id LIMIT 1";
    $stmt_prep = $pdo->prepare($sql_prep);
    $stmt_prep->bindParam(':user_id', $logged_user_id, PDO::PARAM_INT);
    $stmt_prep->execute();
    $json_preps = $stmt_prep->fetchColumn();
    if ($json_preps && $json_preps !== 'null' && $json_preps !== '{}' && $json_preps !== '[]') {
        $decoded_preps = json_decode($json_preps, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_preps)) {
            foreach ($decoded_preps as $prep_id => $prep_data) {
                if (isset($prep_data['nome'], $prep_data['ingredientes'], $prep_data['porcao_padrao_g'])) {
                    $id_str = (string)$prep_id;
                    $alimentos_completos[$id_str] = [
                        'id' => $id_str,
                        'nome' => $prep_data['nome'],
                        'isPreparacao' => true,
                        'ingredientes' => $prep_data['ingredientes']
                    ];
                }
            }
        }
    }

    $daily_quantities = [];
    $weekly_quantities_agg = []; // Agregado semanal para cada foodId

    $dias_keys = ['seg', 'ter', 'qua', 'qui', 'sex']; // Assumindo dias fixos

    foreach ($dias_keys as $diaKey) {
        if (!isset($cardapio_decoded['dias_desativados'][$diaKey]) || !$cardapio_decoded['dias_desativados'][$diaKey]) { // Apenas dias ativos
            $daily_quantities[$diaKey] = [];
            $refeicoes_do_dia = $cardapio_decoded['dias'][$diaKey] ?? [];

            foreach ($refeicoes_do_dia as $refeicaoItems) {
                foreach ($refeicaoItems as $item) {
                    $foodId = $item['foodId'];
                    $qty = $item['qty'];

                    // Função para agregar quantidades, lidando com preparações recursivamente
                    function aggregate_item_quantities(&$target_quantities, $foodId, $qty, $alimentos_completos_ref) {
                        if (isset($alimentos_completos_ref[$foodId]) && $alimentos_completos_ref[$foodId]['isPreparacao']) {
                            // É uma preparação, some os ingredientes
                            $prep_ingredientes = $alimentos_completos_ref[$foodId]['ingredientes'] ?? [];
                            $total_prep_weight = 0;
                            foreach ($prep_ingredientes as $ing) {
                                $total_prep_weight += $ing['qty'];
                            }

                            if ($total_prep_weight > 0) {
                                foreach ($prep_ingredientes as $ing) {
                                    $ing_food_id = $ing['foodId'];
                                    $ing_qty_in_prep = $ing['qty'];
                                    // Calcula a proporção do ingrediente no total da preparação
                                    $proportion = $ing_qty_in_prep / $total_prep_weight;
                                    // Adiciona a quantidade proporcional do ingrediente ao total
                                    $actual_ing_qty = $qty * $proportion;
                                    // Recursivamente para ingredientes que também são preparações
                                    aggregate_item_quantities($target_quantities, $ing_food_id, $actual_ing_qty, $alimentos_completos_ref);
                                }
                            }
                        } else {
                            // É um alimento base, adicione diretamente
                            $target_quantities[$foodId] = ($target_quantities[$foodId] ?? 0) + $qty;
                        }
                    }

                    aggregate_item_quantities($daily_quantities[$diaKey], $foodId, $qty, $alimentos_completos);
                    aggregate_item_quantities($weekly_quantities_agg, $foodId, $qty, $alimentos_completos);
                }
            }
        }
    }

    $response['success'] = true;
    $response['daily_quantities'] = $daily_quantities;
    $response['weekly_quantities'] = $weekly_quantities_agg;

} catch (PDOException $e) {
    $response['message'] = 'Erro de banco de dados: ' . $e->getMessage();
    error_log("PDOException get_cardapio_quantities.php: " . $e->getMessage());
} catch (Throwable $th) {
    $response['message'] = 'Erro inesperado: ' . $th->getMessage();
    error_log("Throwable get_cardapio_quantities.php: " . $th->getMessage());
}

echo json_encode($response);
?>