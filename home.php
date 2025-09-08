<?php
// cardapio_auto/home.php

// 1. Configuração de Sessão (ANTES DE TUDO)
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

// 2. Configuração de Erros
error_reporting(E_ALL);
ini_set('display_errors', 0); // Para DEV (mude para 0 em produção)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_log("--- Início home.php --- SESSION_ID: " . session_id());

// 3. Verificação de Autenticação
$is_logged_in = isset($_SESSION['user_id']);
$logged_user_id = $_SESSION['user_id'] ?? null;
$logged_username = $_SESSION['username'] ?? 'Visitante'; // Fallback

if (!$is_logged_in || !$logged_user_id) { // Checagem mais robusta
    error_log("home.php: Acesso não autenticado ou user_id ausente. Redirecionando para login. Session ID: " . session_id());
    header('Location: login.php');
    exit;
}
error_log("home.php: Usuário autenticado. UserID: $logged_user_id, Username: $logged_username.");


// 4. Variáveis Iniciais e Conexão com BD
$page_title = "Dashboard - NutriPNAE"; // Título atualizado para dashboard
$pdo = null;
$projetos = []; // Cardápios
$erro_busca_projetos = null;
$preparacoes_usuario = [];
$erro_busca_preparacoes = null;
$alimentos_custos = []; // Dados para o bloco de custos

try {
    require_once 'includes/db_connect.php';
    if (!isset($pdo)) {
        throw new \RuntimeException("Objeto PDO não foi definido por db_connect.php");
    }
    error_log("home.php: Conexão com BD estabelecida.");

    // Buscar cardápios (projetos) do usuário
    $sql_projetos = "SELECT id, nome_projeto, updated_at FROM cardapio_projetos WHERE usuario_id = :usuario_id ORDER BY updated_at DESC";
    $stmt_projetos = $pdo->prepare($sql_projetos);
    $stmt_projetos->bindParam(':usuario_id', $logged_user_id, PDO::PARAM_INT);
    $stmt_projetos->execute();
    $projetos = $stmt_projetos->fetchAll();
    error_log("home.php: " . count($projetos) . " cardápios (projetos) carregados para UserID $logged_user_id.");

    // Buscar preparações personalizadas (fichas técnicas) do usuário
    $sql_prep_home = "SELECT preparacoes_personalizadas_json FROM cardapio_usuarios WHERE id = :user_id LIMIT 1";
    $stmt_prep_home = $pdo->prepare($sql_prep_home);
    $stmt_prep_home->bindParam(':user_id', $logged_user_id, PDO::PARAM_INT);
    $stmt_prep_home->execute();
    $json_preps_home = $stmt_prep_home->fetchColumn();

    if ($json_preps_home && $json_preps_home !== 'null' && $json_preps_home !== '{}' && $json_preps_home !== '[]') {
        $decoded_preps_home = json_decode($json_preps_home, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_preps_home)) {
            $preparacoes_usuario = $decoded_preps_home;
            error_log("home.php: " . count($preparacoes_usuario) . " preparações decodificadas para UserID $logged_user_id.");
        } else {
            $erro_busca_preparacoes = "Falha ao ler dados de fichas técnicas salvas (formato inválido).";
            error_log("home.php: Falha ao decodificar preparacoes_personalizadas_json do BD para UserID $logged_user_id. Erro JSON: " . json_last_error_msg());
        }
    } else {
        error_log("home.php: Nenhuma preparação personalizada (JSON vazio ou ausente) no BD para UserID $logged_user_id.");
    }

    // Buscar custos de alimentos
    $food_prices_file = __DIR__ . '/food_prices.json';
    $food_prices_from_file = [];
    if (file_exists($food_prices_file) && is_readable($food_prices_file)) {
        $prices_json_content = file_get_contents($food_prices_file);
        $decoded_file_prices = json_decode($prices_json_content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_file_prices)) {
            $food_prices_from_file = $decoded_file_prices;
        } else {
            error_log("home.php: Erro ao decodificar food_prices.json. Conteúdo inválido. Tratando como vazio.");
        }
    }

    // Carregar alimentos base
    $alimentos_db_base_for_costs = [];
    $sql_all_foods = "SELECT id, nome FROM ingredientes ORDER BY nome ASC"; // Replace with your actual table
    $stmt_all_foods = $pdo->prepare($sql_all_foods);
    $stmt_all_foods->execute();
    $alimentos_db_base_for_costs = $stmt_all_foods->fetchAll(PDO::FETCH_ASSOC);

    // Combinar alimentos base com seus preços do arquivo
    foreach ($alimentos_db_base_for_costs as $food) {
        $cost = 0;
        // Verifica se o ID do alimento existe e se o preço da categoria 'default' está definido
        if (isset($food_prices_from_file[$food['id']]['default']['price'])) {
            $cost = (float)$food_prices_from_file[$food['id']]['default']['price'];
        }
        $alimentos_custos[] = [
            'id' => $food['id'],
            'nome' => $food['nome'],
            'custo_unitario_mock' => $cost // Usa o custo real do arquivo, 0 se não encontrado
        ];
    }
    error_log("home.php: " . count($alimentos_custos) . " alimentos com custos carregados (incluindo de food_prices.json).");

} catch (\PDOException $e) {
    // Define erros para serem capturados e exibidos pelo JS, se necessário.
    $erro_busca_projetos = "Erro crítico: Não foi possível conectar ao banco de dados ou carregar cardápios. " . $e->getMessage();
    $erro_busca_preparacoes = "Erro crítico: Não foi possível conectar ao banco de dados ou carregar fichas técnicas. " . $e->getMessage();
    error_log("Erro PDO em home.php (UserID $logged_user_id): " . $e->getMessage());
} catch (\Throwable $th) {
    $erro_busca_projetos = "Erro inesperado ao carregar dados dos cardápios: " . $th->getMessage();
    $erro_busca_preparacoes = "Erro inesperado ao carregar dados das fichas técnicas: " . $th->getMessage();
    error_log("Erro Throwable em home.php: " . $th->getMessage());
}

// 5. Carregamento de Dados Base (dados.php)
$dados_base_ok_home = false;
$alimentos_db_home = [];
$lista_selecionaveis_db_home = [];
try {
    ob_start();
    require_once __DIR__ . '/dados.php';
    $dados_php_output = ob_get_clean();
    if (!empty($dados_php_output)) {
        error_log("home.php: Saída inesperada de dados.php: " . substr($dados_php_output, 0, 200));
    }
    // Verifica se as variáveis esperadas foram definidas por dados.php
    if (isset($dados_ok) && $dados_ok === true && isset($alimentos_db) && !empty($alimentos_db) && isset($lista_selecionaveis_db) && !empty($lista_selecionaveis_db)) {
        $dados_base_ok_home = true;
        $alimentos_db_home = $alimentos_db;
        $lista_selecionaveis_db_home = $lista_selecionaveis_db;
        error_log("home.php: dados.php carregado com sucesso.");
    } else {
        error_log("home.php: Falha ao carregar dados.php ou variáveis essenciais ausentes/inválidas. \$dados_ok: " . (isset($dados_ok) ? var_export($dados_ok, true) : 'N/D'));
        $dados_base_ok_home = false;
        // As mensagens de erro serão passadas ao JS e exibidas por lá
        $erro_busca_projetos = $erro_busca_projetos ?: "Erro ao carregar dados base de alimentos.";
        $erro_busca_preparacoes = $erro_busca_preparacoes ?: "Erro ao carregar dados base de alimentos.";
    }
} catch (Throwable $e) {
    if (ob_get_level() > 0) ob_end_clean();
    error_log("home.php: Erro fatal ao tentar incluir dados.php: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    $dados_base_ok_home = false;
    // As mensagens de erro serão passadas ao JS e exibidas por lá
    $erro_busca_projetos = $erro_busca_projetos ?: "Erro crítico ao carregar dados base da aplicação: " . $e->getMessage();
    $erro_busca_preparacoes = $erro_busca_preparacoes ?: "Erro crítico ao carregar dados base da aplicação: " . $e->getMessage();
}

// 6. Prepara JSONs para o JavaScript
if (!is_array($preparacoes_usuario)) {
    $preparacoes_usuario = [];
}
// Garante que as preparações sejam um objeto associativo para o JS
$preparacoes_usuario_obj = new stdClass();
foreach ($preparacoes_usuario as $key => $value) {
    if (is_array($value) && isset($value['id'])) {
        $preparacoes_usuario_obj->{$value['id']} = $value;
    } else {
        // Se a preparação não tem um 'id' ou não é um array válido, usa a chave como ID
        $preparacoes_usuario_obj->{$key} = $value;
    }
}

$preparacoes_usuario_json_para_js = json_encode($preparacoes_usuario_obj);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("home.php: Erro ao encodar \$preparacoes_usuario para JSON: " . json_last_error_msg());
    $preparacoes_usuario_json_para_js = '{}';
}

$alimentos_base_para_js = [];
if ($dados_base_ok_home && is_array($lista_selecionaveis_db_home)) {
    foreach ($lista_selecionaveis_db_home as $id => $data) {
        if (is_numeric($id) && isset($data['nome']) && isset($alimentos_db_home[$id])) {
            $alimentos_base_para_js[(string)$id] = ['id' => (string)$id, 'nome' => $data['nome'], 'isPreparacao' => false];
        }
    }
}
$temp_alimentos_base_json = json_encode($alimentos_base_para_js);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("home.php: Erro ao encodar \$alimentos_base_para_js para JSON: " . json_last_error_msg());
    $alimentos_base_json_para_js = '{}';
} else {
    $alimentos_base_json_para_js = ($temp_alimentos_base_json === '[]') ? '{}' : $temp_alimentos_base_json;
}

// JSON para os custos dos alimentos
$alimentos_custos_json_para_js = json_encode($alimentos_custos);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("home.php: Erro ao encodar \$alimentos_custos para JSON: " . json_last_error_msg());
    $alimentos_custos_json_para_js = '[]';
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - NutriPNAE</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Permanent+Marker&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/global.css"> <!-- Inclui o CSS global padronizado -->
    <style>
        /* AQUI SÓ DEVE FICAR O CSS ESPECÍFICO DESTA PÁGINA! */
        /* Dashboard sections */
        .dashboard-main-title {
            font-size: 2.2em;
            color: var(--color-text-dark);
            margin-bottom: 8px;
        }
        .dashboard-main-subtitle {
            font-size: 1.1em;
            color: var(--color-text-light);
            margin-bottom: 25px;
        }

        .platform-section-wrapper {
            background-color: var(--color-bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid var(--color-light-border);
            padding: 25px;
            margin-bottom: 30px;
            /* display: none; */ /* Padrão pode ser 'block' para a seção principal, e JS controla as outras */
        }
        .platform-section-wrapper.active-platform {
            display: block; /* Garante que a plataforma ativa seja exibida */
        }

        .platform-section-wrapper.nutripnae-dashboard {
            border-left: 8px solid var(--color-primary);
        }
        .platform-section-wrapper h2 {
            font-size: 2em;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--color-primary-dark);
        }
        /* Cor dos ícones para NutriPNAE Dashboard */
        .platform-section-wrapper.nutripnae-dashboard h2,
        .platform-section-wrapper.nutripnae-dashboard .dashboard-card-header h3 {
            color: var(--color-primary-dark);
        }
        .platform-section-wrapper.nutripnae-dashboard h2 .fas,
        .platform-section-wrapper.nutripnae-dashboard .dashboard-card-header h3 .fas {
            color: var(--color-primary);
        }

        .platform-section-wrapper h2 .fas {
            font-size: 1.1em;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(calc(33.333% - 20px), 1fr));
            gap: 30px;
            margin-top: 15px;
        }

        .dashboard-card {
            background-color: var(--color-bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid var(--color-light-border);
            padding: 20px;
            display: flex;
            flex-direction: column;
            min-height: 400px;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-hover);
        }

        .dashboard-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--color-border);
            padding-bottom: 10px;
            flex-shrink: 0;
        }
        .dashboard-card-header h3 {
            margin: 0;
            font-size: 1.2em;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .dashboard-card-header h3 .fas {
            font-size: 0.9em;
        }

        .action-button {
            background-color: var(--color-success);
            color: var(--color-text-on-dark);
            border: none;
            padding: 9px 18px;
            font-size: 0.9em;
            font-weight: 600;
            font-family: var(--font-primary);
            border-radius: 20px;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.15s ease-out, box-shadow 0.15s ease-out;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .action-button:hover {
            background-color: var(--color-success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
        }

        /* Fixed height for content lists with scrollbars */
        .content-list-container {
            max-height: 250px;
            overflow-y: auto;
            padding-right: 8px;
        }
        .content-list-container::-webkit-scrollbar { width: 10px; }
        .content-list-container::-webkit-scrollbar-track { background: var(--color-primary-xtralight); border-radius: 5px; }
        .content-list-container::-webkit-scrollbar-thumb {
            background-color: var(--color-secondary-light); border-radius: 5px;
            border: 2px solid var(--color-primary-xtralight);
        }
        .content-list-container::-webkit-scrollbar-thumb:hover { background-color: var(--color-secondary); }

        .project-list, .cost-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .project-item, .ficha-tecnica-item, .cost-item {
            background-color: var(--color-bg-white);
            border: 1px solid var(--color-light-border);
            border-left: 4px solid var(--color-primary-light);
            border-radius: var(--border-radius);
            margin-bottom: 10px;
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: box-shadow var(--transition-speed), border-left-color var(--transition-speed);
        }
        .ficha-tecnica-item { border-left-color: var(--color-primary-light); }
        .cost-item { border-left-color: var(--color-primary-light); }
        .project-item:hover { box-shadow: var(--box-shadow-hover); border-left-color: var(--color-primary-dark); }
        .ficha-tecnica-item:hover { box-shadow: var(--box-shadow-hover); border-left-color: var(--color-primary-dark); }
        .cost-item:hover { box-shadow: var(--box-shadow-hover); border-left-color: var(--color-primary-dark); }

        .project-info { flex-grow: 1; margin-right: 10px; }
        .project-info h3 { margin: 0 0 4px 0; font-size: 1.05em; font-weight: 600; font-family: var(--font-primary); color: var(--color-text-dark); }
        .project-info h3 a { color: var(--color-primary-dark); text-decoration: none; }
        .project-info h3 a:hover { color: var(--color-primary); text-decoration: underline;}
        .project-meta { font-size: 0.8em; color: var(--color-text-light); display: block; }

        .item-actions { display: flex; gap: 4px; align-items: center; }
        .action-button-icon {
            background: none; border: none; cursor: pointer;
            padding: 5px; font-size: 1em;
            color: var(--color-secondary);
            transition: color var(--transition-speed), transform 0.1s ease, background-color var(--transition-speed);
            line-height: 1; border-radius: 50%;
            width: 30px; height: 30px;
            display: inline-flex; justify-content: center; align-items: center;
        }
        .action-button-icon:hover { transform: scale(1.1); }
        .action-button-icon.rename-btn:hover,
        .action-button-icon.edit-btn:hover { color: var(--color-primary-dark); background-color: var(--color-primary-xtralight); }
        .action-button-icon.duplicate-btn:hover { color: var(--color-info-dark); background-color: var(--color-info-light); }
        .action-button-icon.delete-btn:hover { color: var(--color-error-dark); background-color: var(--color-error-light); }

        /* Styles for cost items */
        .cost-item .cost-info { flex-grow: 1; margin-right: 10px; }
        .cost-item .cost-name {
            font-size: 1.05em; font-weight: 600; font-family: var(--font-primary); color: var(--color-text-dark);
        }
        .cost-item .cost-input-group {
            display: flex; align-items: center; gap: 4px;
        }
        .cost-item .cost-input {
            width: 90px;
            padding: 5px 7px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            font-size: 0.9em;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
            text-align: right;
        }
        .cost-item .cost-input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-light); outline: none; }
        .cost-item .currency-prefix {
            font-size: 0.9em;
            color: var(--color-text-light);
        }
        .cost-item .save-cost-btn {
            background-color: var(--color-primary);
            color: var(--color-text-on-dark);
            border: none;
            padding: 5px 10px;
            font-size: 0.8em;
            font-weight: 500;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .cost-item .save-cost-btn:hover {
            background-color: var(--color-primary-dark);
        }
        .cost-item .save-cost-btn.disabled {
            background-color: var(--color-secondary-light);
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Search input for costs */
        .cost-search-container {
            margin-bottom: 12px;
        }
        .cost-search-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            font-size: 0.95em;
        }
        .cost-search-input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px var(--color-primary-light);
            outline: none;
        }

        .no-content-message {
            flex-grow: 1; overflow-y: hidden; margin-top: 0;
            text-align: center; color: var(--color-text-light);
            padding: 20px 0; display:flex; align-items:center; justify-content:center; height:100%;
        }

        /* MODALS (copied from register.php for consistency) */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(52, 58, 64, 0.7); justify-content: center; align-items: center;
            z-index: 1050; padding: 15px; box-sizing: border-box; backdrop-filter: blur(4px);
            animation: fadeInModal 0.25s ease-out;
        }
        @keyframes fadeInModal { from { opacity: 0; } to { opacity: 1; } }
        .modal-content {
            background-color: var(--color-bg-white); padding: 25px 30px; border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); max-width: 450px; width: 95%; max-height: 90vh;
            display: flex; flex-direction: column; animation: scaleUpModal 0.25s ease-out forwards;
            border: 1px solid var(--color-light-border);
        }
        @keyframes scaleUpModal { from { transform: scale(0.97); opacity: 0.8; } to { transform: scale(1); opacity: 1; } }
        .modal-header {
            border-bottom: 1px solid var(--color-light-border); padding-bottom: 12px; margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h2 { font-size: 1.3em; margin: 0; color: var(--color-primary-dark); font-weight: 600; font-family: var(--font-primary); }
        .modal-close-btn {
            background:none; border:none; font-size: 1.6rem; cursor:pointer;
            color: var(--color-secondary-light); padding: 0 5px; line-height: 1;
            transition: color var(--transition-speed);
        }
        .modal-close-btn:hover { color: var(--color-error); }
        .modal-body { margin-bottom: 20px; flex-grow: 1; }
        .modal-body label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--color-primary-dark); font-size: 0.9em; }
        .modal-body .auth-input {
            width: 100%; padding: 10px 12px; border: 1px solid var(--color-border);
            border-radius: var(--border-radius); font-size: 1em; box-sizing: border-box;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
        }
        .modal-body .auth-input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-xtralight); outline: none; }
        .modal-footer {
            border-top: 1px solid var(--color-light-border); padding-top: 15px; text-align: right;
            display: flex; justify-content: flex-end; gap: 10px;
        }
        .modal-button { padding: 9px 20px; font-size: 0.85em; margin-left: 0; }
        .modal-button.cancel { background-color: var(--color-secondary); color:var(--color-text-on-dark); }
        .modal-button.cancel:hover:not(:disabled) { background-color: #5a6268; }
        .modal-button.confirm { background-color: var(--color-success); color:var(--color-text-on-dark); }
        .modal-button.confirm:hover:not(:disabled) { background-color: var(--color-success-dark); }

        .error-container {
            background-color: var(--color-bg-white); padding: 30px; border-radius: var(--border-radius);
            box-shadow: var(--box-shadow); text-align: center; border: 1px solid var(--color-error);
            max-width: 600px; margin: 50px auto;
        }
        .error-container h1 { color: var(--color-error); margin-bottom: 15px; font-family: var(--font-primary); font-size: 1.6em; }

        /* MODAL FICHA TÉCNICA */
        #ficha-tecnica-modal .modal-content { max-width: 850px; }
        #ficha-tecnica-modal .form-group { margin-bottom: 15px; }
        #ficha-tecnica-modal label { font-size: 0.85em; font-weight: 500; color: var(--color-text-dark); margin-bottom: 5px; }
        #ficha-tecnica-modal textarea.auth-input { resize: vertical; min-height: 80px;}
        #ficha-ingredients-list {
            padding-left: 0; list-style-type: none; margin-top: 10px; max-height:200px;
            overflow-y:auto; border:1px solid var(--color-light-border); padding:10px;
            border-radius:var(--border-radius); background-color: #fdfdfd;
        }
        #ficha-ingredients-list li:not(.placeholder-ingredient) {
            display: flex; justify-content: space-between; align-items: center;
            padding: 8px 12px; background-color: #f8f9fa; border: 1px solid var(--color-light-border);
            border-radius: var(--border-radius); margin-bottom: 8px; font-size: 0.9em;
        }
        #ficha-ingredients-list .ingredient-name { flex-basis: 40%; margin-right: 10px; }
        #ficha-search-results {
            list-style: none;padding: 0;margin: 0;border-top: none;max-height: 200px;overflow-y: auto;
            position: absolute; background: white; z-index: 1051; width:100%;
            border: 1px solid var(--color-border); border-top:none; display:none; box-shadow: var(--box-shadow);
        }
        #ficha-search-results li {padding: 8px 12px;cursor: pointer;font-size: 0.9em;border-bottom: 1px solid var(--color-light-border);}
        #ficha-search-results li:last-child {border-bottom: none;}
        #ficha-search-results li:hover {background-color: var(--color-primary-xtralight);color: var(--color-primary-dark);}

        .main-footer-bottom {
            text-align: center; padding: 20px; margin-top: auto;
            background-color: var(--color-primary-dark);
            color: var(--color-primary-xtralight);
            font-size: 0.9em; border-top: 1px solid var(--color-primary);
        }

        /* Responsive Adjustments */
        @media (max-width: 1024px) {
            .navbar .container {
                flex-direction: column;
                gap: 15px;
            }
            .navbar-brand-group {
                order: 1;
            }
            .navbar-actions {
                order: 2;
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
            .user-greeting {
                display: none; /* Hide on smaller screens to save space */
            }
            /* Adjust grid for tablets */
            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* Allow 2 columns */
            }
        }

        @media (max-width: 768px) {
            .main-wrapper {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                box-shadow: none; /* Remove shadow on mobile for flatter look */
                padding: 10px 0;
            }
            .sidebar-toggle-button {
                display: block;
                background-color: var(--color-primary-dark);
                color: var(--color-text-on-dark);
                border: none;
                padding: 10px 15px;
                border-radius: var(--border-radius);
                cursor: pointer;
                font-size: 1em;
                margin: 10px auto; /* Center button */
                width: fit-content;
                align-self: center; /* Center horizontally in flex column */
            }
            .sidebar-nav {
                display: none; /* Hidden by default */
                padding-top: 10px;
                padding-bottom: 10px;
            }
            .sidebar-nav.active {
                display: flex;
                flex-direction: column;
            }
            .sidebar-nav details summary {
                border-left: none; /* Remove left border on mobile */
                justify-content: center; /* Center text and icon */
            }
            .sidebar-nav details ul {
                padding-left: 15px; /* Less indent for sub-menus on mobile */
            }
            .sidebar-footer {
                display: flex;
                justify-content: center;
                padding: 15px;
            }

            .content-area {
                padding: 15px;
            }
            .dashboard-grid {
                grid-template-columns: 1fr; /* Stack cards vertically */
                gap: 20px;
            }
            .platform-section-wrapper {
                padding: 20px;
            }
            .platform-section-wrapper h2 {
                font-size: 1.8em;
                flex-direction: column; /* Stack icon and text */
                text-align: center;
                gap: 8px;
            }
            .dashboard-card-header h3 {
                font-size: 1.3em;
            }
        }
    </style>
</head>
<body>
    <?php include_once 'includes/message_box.php'; ?>
    <?php include_once 'includes/header.php'; ?>

    <div class="main-wrapper">
        <?php include_once 'includes/sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="content-area">
            <div class="container">
                <h1 class="dashboard-main-title">Bem-vindo ao seu Dashboard NutriPNAE!</h1>
                <p class="dashboard-main-subtitle">Gerencie suas atividades e acesse as ferramentas da plataforma NutriPNAE.</p>

                <!-- NutriPNAE Dashboard Section -->
                <section class="platform-section-wrapper nutripnae-dashboard active-platform" id="nutripnae-dashboard-section">
                    <h2><i class="fas fa-school"></i> NutriPNAE Dashboard</h2>
                    <div class="dashboard-grid">
                        <section class="dashboard-card" id="cardapios-section">
                            <div class="dashboard-card-header">
                                <h3><i class="fas fa-clipboard-list" style="color: var(--color-primary);"></i> Meus Cardápios</h3>
                                <button id="new-project-btn" class="action-button"><i class="fas fa-plus"></i> Novo Cardápio</button>
                            </div>
                            <div class="content-list-container">
                                <ul class="project-list" id="cardapios-list-ul">
                                    <?php if (empty($projetos)): ?>
                                        <p id="no-projects-msg" class="no-content-message">Você ainda não criou nenhum cardápio. Clique em "Novo Cardápio" para começar!</p>
                                    <?php else: ?>
                                        <?php foreach ($projetos as $projeto): ?>
                                            <li class="project-item" data-project-id="<?php echo $projeto['id']; ?>" data-project-name="<?php echo htmlspecialchars($projeto['nome_projeto']); ?>">
                                                <div class="project-info">
                                                    <h3>
                                                        <a href="index.php?projeto_id=<?php echo $projeto['id']; ?>" title="Abrir Cardápio: <?php echo htmlspecialchars($projeto['nome_projeto']); ?>">
                                                            <?php echo htmlspecialchars($projeto['nome_projeto']); ?>
                                                        </a>
                                                    </h3>
                                                    <span class="project-meta">
                                                        Última modificação: <?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($projeto['updated_at']))); ?>
                                                    </span>
                                                </div>
                                                <div class="item-actions">
                                                    <button class="duplicate-project-btn action-button-icon duplicate-btn" title="Duplicar Cardápio"><i class="fas fa-copy"></i></button>
                                                    <button class="rename-project-btn action-button-icon rename-btn" title="Renomear Cardápio"><i class="fas fa-pencil-alt"></i></button>
                                                    <button class="delete-project-btn action-button-icon delete-btn" title="Excluir Cardápio"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                                <?php if (!empty($projetos)): ?>
                                    <p id="no-projects-msg" class="no-content-message" style="display:none;">Você ainda não criou nenhum cardápio. Clique em "Novo Cardápio" para começar!</p>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="dashboard-card" id="fichas-tecnicas-section">
                            <div class="dashboard-card-header">
                                <h3><i class="fas fa-file-invoice" style="color: var(--color-primary);"></i> Minhas Fichas Técnicas</h3>
                                <button id="new-ficha-tecnica-btn" class="action-button"><i class="fas fa-plus"></i> Nova Ficha</button>
                            </div>
                            <div class="content-list-container">
                                <div id="lista-fichas-tecnicas">
                                    <p id="no-fichas-msg" class="no-content-message">Você ainda não criou nenhuma ficha técnica.</p>
                                </div>
                            </div>
                        </section>

                        <!-- Novo Bloco de Custos -->
                        <section class="dashboard-card" id="custos-section">
                            <div class="dashboard-card-header">
                                <h3><i class="fas fa-dollar-sign" style="color: var(--color-primary);"></i> Gerenciar Custos de Alimentos</h3>
                                <button id="save-all-costs-btn" class="action-button" style="background-color: var(--color-primary);"><i class="fas fa-save"></i> Salvar Tudo</button>
                            </div>
                            <div class="cost-search-container">
                                <input type="text" id="cost-food-search-input" class="cost-search-input" placeholder="Pesquisar alimento por nome...">
                            </div>
                            <div class="content-list-container">
                                <ul class="cost-list" id="alimentos-custos-list-ul">
                                    <?php if (empty($alimentos_custos)): ?>
                                        <p id="no-custos-msg" class="no-content-message">Nenhum dado de custo de alimento disponível.</p>
                                    <?php else: ?>
                                        <?php foreach ($alimentos_custos as $alimento): ?>
                                            <li class="cost-item" data-food-id="<?php echo $alimento['id']; ?>">
                                                <div class="cost-info">
                                                    <span class="cost-name"><?php echo htmlspecialchars($alimento['nome']); ?></span>
                                                </div>
                                                <div class="cost-input-group">
                                                    <span class="currency-prefix">R$</span>
                                                    <input type="number" class="cost-input auth-input" value="<?php echo number_format($alimento['custo_unitario_mock'], 2, '.', ''); ?>" min="0" step="0.01">
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </section>

                        <!-- Novos blocos de ferramentas abaixo -->
                        <section class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h3><i class="fas fa-clipboard-check" style="color: var(--color-primary);"></i> Checklists</h3>
                            </div>
                            <p style="color: var(--color-text-light);">Gerencie e personalize seus checklists para inspeções e rotinas.</p>
                            <div class="item-actions" style="margin-top: auto; justify-content: flex-end; padding-top: 15px; border-top: 1px solid var(--color-light-border);">
                                <a href="checklists.php" class="action-button" style="background-color: var(--color-primary);"><i class="fas fa-tasks"></i> Acessar Checklists</a>
                            </div>
                        </section>

                        <section class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h3><i class="fas fa-exchange-alt" style="color: var(--color-primary);"></i> Remanejamentos</h3>
                            </div>
                            <p style="color: var(--color-text-light);">Otimize o uso de alimentos com ferramentas de remanejamento.</p>
                            <div class="item-actions" style="margin-top: auto; justify-content: flex-end; padding-top: 15px; border-top: 1px solid var(--color-light-border);">
                                <a href="remanejamentos.php" class="action-button" style="background-color: var(--color-primary);"><i class="fas fa-random"></i> Fazer Remanejamento</a>
                            </div>
                        </section>

                        <section class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h3><i class="fas fa-child" style="color: var(--color-primary);"></i> Nutrição Especial</h3>
                            </div>
                            <p style="color: var(--color-text-light);">Ferramentas dedicadas a dietas e necessidades nutricionais especiais.</p>
                            <div class="item-actions" style="margin-top: auto; justify-content: flex-end; padding-top: 15px; border-top: 1px solid var(--color-light-border);">
                                <a href="nutriespecial.php" class="action-button" style="background-color: var(--color-primary);"><i class="fas fa-notes-medical"></i> Gerenciar Dietas</a>
                            </div>
                        </section>

                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Modals (kept for functionality) -->
    <div id="new-project-modal" class="modal-overlay">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Criar Novo Cardápio</h2>
          <button type="button" class="modal-close-btn" title="Fechar">×</button>
        </div>
        <div class="modal-body">
          <form id="new-project-form" onsubmit="return false;">
              <label for="new-project-name">Nome do Cardápio:</label>
              <input type="text" id="new-project-name" name="nome_projeto" class="auth-input" required maxlength="100">
          </form>
        </div>
        <div class="modal-footer">
           <button type="button" class="modal-button cancel modal-close-btn">Cancelar</button>
           <button type="button" id="confirm-new-project-btn" class="modal-button confirm" style="background-color: var(--color-accent); color: var(--color-text-dark);">Criar</button>
        </div>
      </div>
    </div>

    <div id="rename-project-modal" class="modal-overlay">
      <div class="modal-content">
        <div class="modal-header">
          <h2>Renomear Cardápio</h2>
          <button type="button" class="modal-close-btn" title="Fechar">×</button>
        </div>
        <div class="modal-body">
           <form id="rename-project-form" onsubmit="return false;">
                <input type="hidden" id="rename-project-id" name="projeto_id">
                <label for="rename-project-name">Novo nome:</label>
                <input type="text" id="rename-project-name" name="novo_nome" class="auth-input" required maxlength="100">
           </form>
        </div>
        <div class="modal-footer">
           <button type="button" class="modal-button cancel modal-close-btn">Cancelar</button>
           <button type="button" id="confirm-rename-project-btn" class="modal-button confirm" style="background-color: var(--color-accent); color: var(--color-text-dark);">Salvar</button>
        </div>
      </div>
    </div>

    <div id="ficha-tecnica-modal" class="modal-overlay">
      <div class="modal-content">
        <div class="modal-header">
          <h2 id="ficha-tecnica-modal-title">Nova Ficha Técnica</h2>
          <button type="button" class="modal-close-btn modal-close-btn-ficha" title="Fechar">×</button>
        </div>
        <div class="modal-body" style="max-height: 70vh; overflow-y: auto; padding-right:15px;">
            <form id="ficha-tecnica-form" onsubmit="return false;">
                <input type="hidden" id="ficha-tecnica-id" name="ficha_tecnica_id">
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label for="ficha-nome">Nome da Preparação:</label>
                        <input type="text" id="ficha-nome" name="nome" class="auth-input" required>
                    </div>
                    <div class="form-group">
                        <label for="ficha-porcao-padrao">Porção Padrão (g):</label>
                        <input type="number" id="ficha-porcao-padrao" name="porcao_padrao_g" class="auth-input" min="1" value="100">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label for="ficha-rendimento-porcoes">Rendimento (Nº Porções):</label>
                        <input type="number" id="ficha-rendimento-porcoes" name="rendimento_porcoes" class="auth-input" min="0" placeholder="Opcional">
                    </div>
                    <div class="form-group">
                        <label for="ficha-rendimento-peso-total">Peso Preparo Final (g):</label>
                        <input type="number" id="ficha-rendimento-peso-total" name="rendimento_peso_total_g" class="auth-input" min="0" title="Peso total após o preparo. Se vazio, usará o calculado.">
                        <small style="font-size:0.8em; color:var(--color-text-light)">Deixe em branco para usar o valor calculado.</small>
                    </div>
                    <div class="form-group">
                        <label for="ficha-custo-total">Custo Total Estimado (R$):</label>
                        <input type="number" id="ficha-custo-total" name="custo_total_reais" class="auth-input" min="0" step="0.01" placeholder="Ex: 12.50">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; margin-top: 15px;">
                    <div class="form-group">
                        <label for="ficha-fator-coccao">Fator de Cocção Geral (FCoc):</label>
                        <input type="number" id="ficha-fator-coccao" name="fator_coccao" class="auth-input" step="0.01" value="1.00">
                        <small style="font-size:0.8em; color:var(--color-text-light)">Ex: 1.1 (aumenta 10%), 0.9 (reduz 10%). Padrão: 1.0.</small>
                    </div>
                    <div class="form-group">
                        <label>Rendimento Total (g) Auto-Calculado:</label>
                        <input type="text" id="ficha-rendimento-calculado-display" class="auth-input" readonly style="background-color: #e9ecef; font-weight: bold;">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="ficha-modo-preparo">Modo de Preparo:</label>
                    <textarea id="ficha-modo-preparo" name="modo_preparo" class="auth-input" rows="5"></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="ficha-observacoes">Observações/Dicas:</label>
                    <textarea id="ficha-observacoes" name="observacoes" class="auth-input" rows="3"></textarea>
                </div>
                <hr style="margin: 25px 0;">
                <h4 style="margin-bottom: 15px;">Ingredientes da Preparação</h4>
                <div class="form-group" style="position: relative; margin-bottom: 10px;">
                    <label for="ficha-ingredient-search">Buscar Ingrediente Base para Adicionar:</label>
                    <input type="text" id="ficha-ingredient-search" class="auth-input" placeholder="Digite para buscar alimento base...">
                    <ul id="ficha-search-results"></ul>
                </div>
                <ul id="ficha-ingredients-list">
                    <li class="placeholder-ingredient" style="text-align:center; color: var(--color-text-light); padding:10px; list-style-type:none;">- Nenhum ingrediente adicionado -</li>
                </ul>
            </form>
        </div>
        <div class="modal-footer">
           <button type="button" class="modal-button cancel modal-close-btn-ficha">Cancelar</button>
           <button type="button" id="confirm-save-ficha-tecnica-btn" class="modal-button confirm" style="background-color: var(--color-accent); color: var(--color-text-dark);">Salvar Ficha Técnica</button>
        </div>
      </div>
    </div>


    <?php include_once 'includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="assets/js/global.js"></script> <!-- Inclui o JavaScript global padronizado -->
<script>
$(document).ready(function() {
console.log("Dashboard (home.php) JS v4.5 carregado."); // Updated JS version

// Helper to check for empty lists
function checkEmptyList(listUlSelector, emptyMsgSelector, defaultEmptyText) {
    const $listUl = $(listUlSelector);
    const $emptyMsg = $(emptyMsgSelector);

    if ($listUl.length && $listUl.children('li:visible').length === 0) { // Check for visible children
        if (defaultEmptyText && !$emptyMsg.is(':visible')) {
             $emptyMsg.text(defaultEmptyText);
        }
        $emptyMsg.show();
    } else if ($listUl.children('li:visible').length > 0) {
        $emptyMsg.hide();
    }
}

// References to modals
const $newProjectModal = $('#new-project-modal');
const $renameProjectModal = $('#rename-project-modal');
const $fichaTecnicaModal = $('#ficha-tecnica-modal');

// Functions to open/close modals
function openModal(modaljQueryObject) {
    modaljQueryObject.css('display', 'flex').hide().fadeIn(200);
    modaljQueryObject.find('input:visible:not([type="hidden"]), textarea:visible').first().focus();
}

function closeModal(modaljQueryObject) {
    modaljQueryObject.fadeOut(150, function() { $(this).css('display', 'none'); });
}

// Close modals with ESC or by clicking outside
$(document).on('keydown', function(e) { if (e.key === "Escape") { $('.modal-overlay:visible').last().each(function() { closeModal($(this)); }); } });
$('.modal-overlay').on('click', function(e) { if ($(e.target).is(this)) { closeModal($(this)); } });
$('.modal-close-btn').on('click', function() { closeModal($(this).closest('.modal-overlay')); });

// Helper function for HTML escaping
function htmlspecialchars(str) {
    if (typeof str !== 'string') return '';
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return str.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Helper function to sanitize string for search (removes accents, converts to lower case)
function sanitizeString(str) {
    if (typeof str !== 'string') return '';
    return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9\s]/g, '');
}


/* --- Funcionalidade de Cardápios (Projetos) --- */
const $cardapiosListUl = $('#cardapios-list-ul');

// Open new menu modal
$('#new-project-btn').on('click', function() { $('#new-project-name').val(''); openModal($newProjectModal); });

// Confirm new menu creation
$('#confirm-new-project-btn').on('click', function() {
    const nomeProjeto = $('#new-project-name').val().trim();
    if (!nomeProjeto) { displayMessageBox('Por favor, digite um nome para o cardápio.'); return; }
    $.ajax({
        url: 'project_actions.php', method: 'POST', data: { action: 'create', nome_projeto: nomeProjeto }, dataType: 'json',
        success: function(response) {
            if (response.success && response.projeto_id) {
                displayMessageBox('Cardápio criado com sucesso! Redirecionando...', false, () => {
                    window.location.href = 'index.php?projeto_id=' + response.projeto_id;
                });
            } else {
                displayMessageBox('Erro ao criar o cardápio: ' + (response.message || 'Erro desconhecido. Por favor, tente novamente.'));
            }
        }, error: function(jqXHR, textStatus, errorThrown) {
            displayMessageBox('Erro de comunicação ao criar o cardápio. Status: ' + textStatus + ', Erro: ' + errorThrown + '. Verifique o console para detalhes técnicos.');
            console.error("Erro AJAX Criar Projeto:", jqXHR.responseText, textStatus, errorThrown);
        }
    });
});
$('#new-project-form').on('submit', function(e){ e.preventDefault(); $('#confirm-new-project-btn').click(); });


// Open rename menu modal
$cardapiosListUl.on('click', '.rename-project-btn', function() {
    const item = $(this).closest('.project-item');
    $('#rename-project-id').val(item.data('project-id'));
    $('#rename-project-name').val(item.data('project-name'));
    openModal($renameProjectModal);
});

// Confirm rename menu
$('#confirm-rename-project-btn').on('click', function() {
    const projectId = $('#rename-project-id').val(); const novoNome = $('#rename-project-name').val().trim();
    if (!novoNome) { displayMessageBox('O novo nome não pode estar vazio.'); return; }
    $.ajax({
        url: 'project_actions.php', method: 'POST', data: { action: 'rename', projeto_id: projectId, novo_nome: novoNome }, dataType: 'json',
        success: function(response) {
            if (response.success) {
                const projectItem = $cardapiosListUl.find('.project-item[data-project-id="' + projectId + '"]');
                projectItem.find('.project-info h3 a').text(htmlspecialchars(novoNome));
                projectItem.data('project-name', novoNome);
                closeModal($renameProjectModal);
                displayMessageBox('Cardápio renomeado com sucesso!');
            } else {
                displayMessageBox('Erro ao renomear: ' + (response.message || 'Erro desconhecido. Por favor, tente novamente.'));
            }
        }, error: function(jqXHR, textStatus, errorThrown) {
            displayMessageBox('Erro de comunicação ao renomear o cardápio. Status: ' + textStatus + ', Erro: ' + errorThrown + '. Verifique o console para detalhes técnicos.');
            console.error("Erro AJAX Renomear Projeto:", jqXHR.responseText, textStatus, errorThrown);
        }
    });
});
$('#rename-project-form').on('submit', function(e){ e.preventDefault(); $('#confirm-rename-project-btn').click(); });

// Duplicate menu
$cardapiosListUl.on('click', '.duplicate-project-btn', function() {
    const projectItem = $(this).closest('.project-item');
    const projectId = projectItem.data('project-id');
    const projectName = projectItem.data('project-name');

    displayMessageBox(`Tem certeza que deseja duplicar o cardápio "<b>${htmlspecialchars(projectName)}</b>"?`, true, (result) => {
        if (result) {
            $.ajax({
                url: 'project_actions.php', method: 'POST', data: { action: 'duplicate', projeto_id: projectId }, dataType: 'json',
                success: function(response) {
                    if (response.success && response.novo_projeto) {
                        const novoProjeto = response.novo_projeto;
                        const newItemHtml = `
                            <li class="project-item" data-project-id="${novoProjeto.id}" data-project-name="${htmlspecialchars(novoProjeto.nome_projeto)}">
                                <div class="project-info">
                                    <h3>
                                        <a href="index.php?projeto_id=${novoProjeto.id}" title="Abrir Cardápio: ${htmlspecialchars(novoProjeto.nome_projeto)}">
                                            ${htmlspecialchars(novoProjeto.nome_projeto)}
                                        </a>
                                    </h3>
                                    <span class="project-meta">
                                        Última modificação: ${novoProjeto.updated_at_formatada}
                                    </span>
                                </div>
                                <div class="item-actions">
                                    <button class="duplicate-project-btn action-button-icon duplicate-btn" title="Duplicar Cardápio"><i class="fas fa-copy"></i></button>
                                    <button class="rename-project-btn action-button-icon rename-btn" title="Renomear Cardápio"><i class="fas fa-pencil-alt"></i></button>
                                    <button class="delete-project-btn action-button-icon delete-btn" title="Excluir Cardápio"><i class="fas fa-trash"></i></button>
                                </div>
                            </li>`;
                        $cardapiosListUl.prepend(newItemHtml);
                        checkEmptyList('#cardapios-list-ul', '#no-projects-msg', 'Você ainda não criou nenhum cardápio. Clique em "Novo Cardápio" para começar!');
                        displayMessageBox(`Cardápio "<b>${htmlspecialchars(projectName)}</b>" duplicado como "<b>${htmlspecialchars(novoProjeto.nome_projeto)}</b>"!`);
                    } else {
                        displayMessageBox('Erro ao duplicar o cardápio: ' + (response.message || 'Erro desconhecido. Por favor, tente novamente.'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    displayMessageBox('Erro de comunicação ao duplicar o cardápio. Status: ' + textStatus + ', Erro: ' + errorThrown + '. Verifique o console para detalhes técnicos.');
                    console.error("Erro AJAX Duplicar Projeto:", jqXHR.responseText, textStatus, errorThrown);
                }
            });
        }
    });
});

// Delete menu
$cardapiosListUl.on('click', '.delete-project-btn', function() {
    const projectItem = $(this).closest('.project-item');
    const projectId = projectItem.data('project-id');
    const projectName = projectItem.data('project-name');

    displayMessageBox(`Tem certeza que deseja excluir o cardápio "<b>${htmlspecialchars(projectName)}</b>"? Esta ação é irreversível.`, true, (result) => {
        if (result) {
            $.ajax({
                url: 'project_actions.php', method: 'POST', data: { action: 'delete', projeto_id: projectId }, dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        projectItem.fadeOut(300, function() {
                            $(this).remove();
                            checkEmptyList('#cardapios-list-ul', '#no-projects-msg', 'Você ainda não criou nenhum cardápio. Clique em "Novo Cardápio" para começar!');
                        });
                        displayMessageBox('Cardápio excluído com sucesso!');
                    } else {
                        displayMessageBox('Erro ao excluir: ' + (response.message || 'Erro desconhecido. Por favor, tente novamente.'));
                    }
                }, error: function(jqXHR, textStatus, errorThrown) {
                    displayMessageBox('Erro de comunicação ao excluir o cardápio. Status: ' + textStatus + ', Erro: ' + errorThrown + '. Verifique o console para detalhes técnicos.');
                    console.error("Erro AJAX Excluir Projeto:", jqXHR.responseText, textStatus, errorThrown);
                }
            });
        }
    });
});

/* --- Ficha Técnica Functionality --- */
const $fichasTecnicasListContainer = $('#lista-fichas-tecnicas');
const $fichaIngredientSearch = $('#ficha-ingredient-search');
const $fichaSearchResults = $('#ficha-search-results');
const $fichaIngredientsList = $('#ficha-ingredients-list');

let todasPreparacoesUsuario = {};
let alimentosBaseParaFicha = {};

// Function to generate a unique ID for new preparations (client-side)
function generatePreparacaoId() {
    return 'prep_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

// Load initial ficha técnica data
function carregarDadosIniciaisFichas() {
    try {
        todasPreparacoesUsuario = <?php echo $preparacoes_usuario_json_para_js ?: '{}'; ?>;
        alimentosBaseParaFicha = <?php echo $alimentos_base_json_para_js ?: '{}'; ?>;

        if (typeof todasPreparacoesUsuario !== 'object' || todasPreparacoesUsuario === null) { todasPreparacoesUsuario = {}; }
        if (typeof alimentosBaseParaFicha !== 'object' || alimentosBaseParaFicha === null) { alimentosBaseParaFicha = {}; }

        console.log("Fichas Iniciais - Preparacoes:", Object.keys(todasPreparacoesUsuario).length, "Alimentos Base:", Object.keys(alimentosBaseParaFicha).length);

    } catch (e) {
        console.error("Erro GERAL ao inicializar dados JS das Fichas a partir do PHP:", e);
        todasPreparacoesUsuario = {};
        alimentosBaseParaFicha = {};
    }
    renderListaFichasTecnicas();
}

// Render the list of fichas técnicas
function renderListaFichasTecnicas() {
    $fichasTecnicasListContainer.empty();

    if (Object.keys(todasPreparacoesUsuario).length === 0) {
        $fichasTecnicasListContainer.html('<p id="no-fichas-msg" class="no-content-message">Você ainda não criou nenhuma ficha técnica.</p>');
         checkEmptyList('#lista-fichas-tecnicas > ul.project-list', '#lista-fichas-tecnicas > p#no-fichas-msg', 'Você ainda não criou nenhuma ficha técnica.');
        return;
    }

    const $ulFichas = $('<ul class="project-list"></ul>');
    const sortedPrepKeys = Object.keys(todasPreparacoesUsuario).sort((a,b) => {
        const nomeA = todasPreparacoesUsuario[a]?.nome || ''; const nomeB = todasPreparacoesUsuario[b]?.nome || '';
        return nomeA.localeCompare(nomeB, 'pt-BR', {sensitivity: 'base'});
    });

    sortedPrepKeys.forEach(prepId => {
        const prep = todasPreparacoesUsuario[prepId];
        if (!prep || !prep.id || !prep.nome) return;
        let ultimaModStr = 'N/A';
        if (prep.updated_at) {
            try {
                const dateStr = String(prep.updated_at).replace(' ', 'T');
                const dateObj = new Date(dateStr + (dateStr.includes('Z') || dateStr.includes('+') || dateStr.includes('GMT') ? '' : 'Z'));
                if (!isNaN(dateObj.getTime())) {
                    ultimaModStr = dateObj.toLocaleString('pt-BR', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'});
                } else { ultimaModStr = htmlspecialchars(String(prep.updated_at)); }
            } catch (e) { ultimaModStr = htmlspecialchars(String(prep.updated_at)); }
        }
        const custoStr = prep.custo_total_reais ? `| Custo: R$ ${parseFloat(prep.custo_total_reais).toFixed(2).replace('.',',')}` : '';
        const htmlFicha = `
            <li class="ficha-tecnica-item" data-prep-id="${prep.id}" data-prep-name="${htmlspecialchars(prep.nome)}">
                <div class="project-info">
                    <h3>
                        <a href="#" class="edit-ficha-tecnica-btn" title="Editar Ficha: ${htmlspecialchars(prep.nome)}">
                            <i class="fas fa-file-invoice" style="margin-right: 8px; color:var(--color-primary);"></i>${htmlspecialchars(prep.nome)}
                        </a>
                    </h3>
                    <span class="project-meta">
                        Porção: ${prep.porcao_padrao_g || 100}g
                        ${prep.rendimento_porcoes ? `| Rend. Porções: ${prep.rendimento_porcoes}` : ''}
                        ${prep.rendimento_peso_total_g ? `| Peso Preparo: ${prep.rendimento_peso_total_g}g` : ''}
                        ${custoStr} | Modif.: ${ultimaModStr}
                    </span>
                </div>
                <div class="item-actions">
                    <button class="edit-ficha-tecnica-btn action-button-icon edit-btn" title="Editar Ficha"><i class="fas fa-pencil-alt"></i></button>
                    <button class="delete-ficha-tecnica-btn action-button-icon delete-btn" title="Excluir Ficha"><i class="fas fa-trash"></i></button>
                </div>
            </li>`;
        $ulFichas.append(htmlFicha);
    });
    $fichasTecnicasListContainer.append($ulFichas);
    checkEmptyList('#lista-fichas-tecnicas > ul.project-list', '#lista-fichas-tecnicas > p#no-fichas-msg', 'Você ainda não criou nenhuma ficha técnica.');
}

// Clear ficha técnica modal
function limparModalFichaTecnica() {
    $('#ficha-tecnica-form')[0].reset();
    $('#ficha-tecnica-id').val('');
    $('#ficha-tecnica-modal-title').text('Nova Ficha Técnica');
    $('#ficha-fator-coccao').val('1.00');
    $('#ficha-rendimento-calculado-display').val('');
    $('#ficha-porcao-padrao').val('100'); // Ensure default is 100
    $fichaIngredientsList.empty().html('<li class="placeholder-ingredient" style="text-align:center; color: var(--color-text-light); padding:10px; list-style-type:none;">- Nenhum ingrediente adicionado -</li>');
    $fichaSearchResults.empty().hide();
    $('#ficha-ingredient-search').val('');
    $('#ficha-rendimento-peso-total').attr('placeholder', 'Soma dos ingredientes (auto)');
    console.log("Ficha modal cleared.");
}

// Open new ficha técnica modal
$('#new-ficha-tecnica-btn').on('click', function() {
    limparModalFichaTecnica();
    openModal($fichaTecnicaModal);
    console.log("New ficha modal opened.");
});

// Close ficha técnica modal
$fichaTecnicaModal.find('.modal-close-btn-ficha').on('click', function() { closeModal($fichaTecnicaModal); });

// Edit ficha técnica
$fichasTecnicasListContainer.on('click', '.edit-ficha-tecnica-btn', function(e) {
    e.preventDefault();
    const prepId = $(this).closest('.ficha-tecnica-item').data('prep-id');
    const prepData = todasPreparacoesUsuario[prepId];
    if (!prepData) { displayMessageBox("Erro: Preparação não encontrada."); return; }

    limparModalFichaTecnica();
    $('#ficha-tecnica-modal-title').text('Editar Ficha Técnica');
    $('#ficha-tecnica-id').val(prepData.id);
    $('#ficha-nome').val(prepData.nome || '');
    $('#ficha-porcao-padrao').val(prepData.porcao_padrao_g || 100);
    $('#ficha-rendimento-porcoes').val(prepData.rendimento_porcoes || '');
    $('#ficha-rendimento-peso-total').val(prepData.rendimento_peso_total_g || '');
    $('#ficha-custo-total').val(prepData.custo_total_reais ? parseFloat(prepData.custo_total_reais).toFixed(2) : '');
    $('#ficha-fator-coccao').val(prepData.fator_coccao ? parseFloat(prepData.fator_coccao).toFixed(2) : '1.00');
    $('#ficha-modo-preparo').val(prepData.modo_preparo || '');
    $('#ficha-observacoes').val(prepData.observacoes || '');

    $fichaIngredientsList.empty();
    let ingredientesSource = prepData.ingredientes;

    if (!Array.isArray(ingredientesSource) && prepData.ingredientes_json) {
        try { ingredientesSource = JSON.parse(prepData.ingredientes_json); }
        catch (parseError) { console.error("Error parsing ingredientes_json for edit:", parseError); ingredientesSource = []; }
    } else if (!Array.isArray(ingredientesSource)) {
        ingredientesSource = [];
    }

    if (ingredientesSource && ingredientesSource.length > 0) {
        ingredientesSource.forEach(ing => {
            const foodIdStr = String(ing.foodId);
            const nomeDisplay = ing.nomeOriginal || (alimentosBaseParaFicha[foodIdStr] ? alimentosBaseParaFicha[foodIdStr].nome : `Alimento ID ${foodIdStr}`);
            const pesoBruto = ing.qty;
            const fcIngrediente = ing.fc || 1.00;
            adicionarIngredienteNaListaModal(foodIdStr, nomeDisplay, pesoBruto, fcIngrediente);
        });
    }
    if ($fichaIngredientsList.children(':not(.placeholder-ingredient)').length === 0) {
         $fichaIngredientsList.html('<li class="placeholder-ingredient" style="text-align:center; color: var(--color-text-light); padding:10px; list-style-type:none;">- Nenhum ingrediente adicionado -</li>');
    }
    calcularTotaisDaFichaNoModal();
    openModal($fichaTecnicaModal);
    console.log("Edit ficha modal opened for:", prepData.nome);
});

// Search for ingredients for ficha técnica
$fichaIngredientSearch.on('input', function() {
    const searchTerm = sanitizeString($(this).val());
    $fichaSearchResults.empty().hide();
    if (searchTerm.length < 1) { return; }
    let count = 0;
    if (typeof alimentosBaseParaFicha !== 'object' || alimentosBaseParaFicha === null) {
        console.error("alimentosBaseParaFicha is not a valid object.");
        $fichaSearchResults.append('<li class="no-results">- Erro ao carregar alimentos base -</li>').show(); return;
    }
    const sortedAlimentosBase = Object.values(alimentosBaseParaFicha)
        .filter(food => food && food.id && food.nome)
        .sort((a, b) => (a.nome || '').localeCompare(b.nome || '', 'pt-BR', { sensitivity: 'base' }));

    for (const food of sortedAlimentosBase) {
        if (sanitizeString(food.nome).includes(searchTerm)) {
            $fichaSearchResults.append(`<li data-id="${food.id}" data-nome="${htmlspecialchars(food.nome)}">${htmlspecialchars(food.nome)}</li>`);
            count++; if (count >= 10) break;
        }
    }
    if (count > 0) $fichaSearchResults.show();
    else $fichaSearchResults.append(`<li class="no-results">- Nenhum alimento base encontrado para "${htmlspecialchars($(this).val())}" -</li>`).show();
});
$fichaIngredientSearch.on('focus', function() {
    if($(this).val().trim().length > 0) {
         $(this).trigger('input');
    }
});

// Add selected ingredient to modal list
$fichaSearchResults.on('click', 'li:not(.no-results)', function() {
    const foodId = $(this).data('id').toString();
    const foodName = $(this).data('nome');
    // Ensure `alimentosBaseParaFicha` is properly populated for fc_padrao
    const fcPadraoDoAlimento = alimentosBaseParaFicha[foodId] && alimentosBaseParaFicha[foodId].hasOwnProperty('fc_padrao') ? alimentosBaseParaFicha[foodId].fc_padrao : 1.00;

    adicionarIngredienteNaListaModal(foodId, foodName, 100, fcPadraoDoAlimento);
    $fichaIngredientSearch.val('').focus();
    $fichaSearchResults.empty().hide();
});

function adicionarIngredienteNaListaModal(id, nome, qtdBrutaPadrao = 100, fcPadrao = 1.00) {
     $fichaIngredientsList.find('.placeholder-ingredient').remove();
    if ($fichaIngredientsList.find(`li[data-id="${id}"]`).length > 0) {
        displayMessageBox(`O ingrediente "<b>${htmlspecialchars(nome)}</b>" já foi adicionado.`);
        $fichaIngredientsList.find(`li[data-id="${id}"] .ingredient-peso-bruto-input`).focus().select();
        return;
    }
    const pesoLiquidoInicial = (parseFloat(qtdBrutaPadrao) / parseFloat(fcPadrao)).toFixed(2);
    const liHtml = `
        <li data-id="${id}" data-food-name="${htmlspecialchars(nome)}">
            <span class="ingredient-name" style="flex-basis: 40%;">${htmlspecialchars(nome)}</span>
            <div style="display: flex; align-items: center; gap: 10px; flex-grow:1; justify-content: flex-end;">
                <div style="text-align: right;">
                    <label for="bruto-${id}" style="font-size:0.8em; display:block; margin-bottom:2px;">Peso Bruto (g)</label>
                    <input type="number" id="bruto-${id}" class="ingredient-peso-bruto-input auth-input" value="${parseFloat(qtdBrutaPadrao).toFixed(2)}" min="0.01" step="any" style="width: 80px; font-size:0.9em; padding:4px;">
                </div>
                <div style="text-align: right;">
                    <label for="fc-${id}" style="font-size:0.8em; display:block; margin-bottom:2px;">Fator Cor.</label>
                    <input type="number" id="fc-${id}" class="ingredient-fc-input auth-input" value="${parseFloat(fcPadrao).toFixed(2)}" min="0.01" step="0.01" style="width: 70px; font-size:0.9em; padding:4px;">
                </div>
                <div style="text-align: right;">
                    <label style="font-size:0.8em; display:block; margin-bottom:2px;">Peso Líquido (g)</label>
                    <span class="ingredient-peso-liquido-display" style="display:inline-block; width: 80px; font-weight:bold; font-size:0.9em; padding:4px 6px; text-align:right; background-color:#f0f0f0; border-radius:4px; border:1px solid #ccc;">${pesoLiquidoInicial}</span>
                </div>
                <button type="button" class="ingredient-remove-btn action-button-icon delete-btn" title="Remover ${htmlspecialchars(nome)}"><i class="fas fa-times"></i></button>
            </div>
        </li>`;
    $fichaIngredientsList.append(liHtml);
    calcularTotaisDaFichaNoModal();
}

function calcularPesoLiquidoIngrediente(liElement) {
    const $li = $(liElement);
    const pesoBruto = parseFloat($li.find('.ingredient-peso-bruto-input').val()) || 0;
    const fc = parseFloat($li.find('.ingredient-fc-input').val()) || 1.0;
    let pesoLiquido = (fc > 0) ? (pesoBruto / fc) : 0;
    $li.find('.ingredient-peso-liquido-display').text(pesoLiquido.toFixed(2));
    calcularTotaisDaFichaNoModal();
}

function calcularTotaisDaFichaNoModal() {
    let somaPesosLiquidos = 0;
    $('#ficha-ingredients-list li:not(.placeholder-ingredient)').each(function() {
        const pesoLiquidoStr = $(this).find('.ingredient-peso-liquido-display').text();
        somaPesosLiquidos += parseFloat(pesoLiquidoStr) || 0;
    });
    const fatorCoccaoGeral = parseFloat($('#ficha-fator-coccao').val()) || 1.0;
    const rendimentoTotalCalculado = somaPesosLiquidos * fatorCoccaoGeral;
    $('#ficha-rendimento-calculado-display').val(rendimentoTotalCalculado.toFixed(2) + ' g');
}

$fichaIngredientsList.on('input', '.ingredient-peso-bruto-input, .ingredient-fc-input', function() { calcularPesoLiquidoIngrediente($(this).closest('li')); });
$('#ficha-fator-coccao').on('input', calcularTotaisDaFichaNoModal);

$fichaIngredientsList.on('click', '.ingredient-remove-btn', function() { $(this).closest('li').remove(); if ($fichaIngredientsList.children(':not(.placeholder-ingredient)').length === 0) { $fichaIngredientsList.html('<li class="placeholder-ingredient" style="text-align:center; color: var(--color-text-light); padding:10px; list-style-type:none;">- Nenhum ingrediente adicionado -</li>'); } calcularTotaisDaFichaNoModal(); });

$('#confirm-save-ficha-tecnica-btn').on('click', function() {
    const prepIdInput = $('#ficha-tecnica-id').val();
    const prepId = prepIdInput ? prepIdInput : generatePreparacaoId();
    const nome = $('#ficha-nome').val().trim();
    const porcaoPadraoG = parseInt($('#ficha-porcao-padrao').val(), 10) || 100;
    const rendimentoPorcoesInput = $('#ficha-rendimento-porcoes').val();
    const rendimentoPorcoes = rendimentoPorcoesInput ? parseInt(rendimentoPorcoesInput, 10) : null;

    let rendimentoPesoTotalG;
    const rendimentoPesoTotalGInput = $('#ficha-rendimento-peso-total').val().trim();
    if (rendimentoPesoTotalGInput) {
        rendimentoPesoTotalG = parseFloat(rendimentoPesoTotalGInput.replace(',', '.'));
    } else {
        const displayCalculado = $('#ficha-rendimento-calculado-display').val();
        rendimentoPesoTotalG = parseFloat(displayCalculado.replace(' g','')) || 0;
    }

    const custoTotalReaisInput = $('#ficha-custo-total').val();
    const custoTotalReais = custoTotalReaisInput ? parseFloat(custoTotalReaisInput.replace(',', '.')) : null;
    const fatorCoccaoGeral = parseFloat($('#ficha-fator-coccao').val()) || 1.0;
    const modoPreparo = $('#ficha-modo-preparo').val().trim();
    const observacoes = $('#ficha-observacoes').val().trim();

    if (!nome) { displayMessageBox("O nome da preparação é obrigatório."); $('#ficha-nome').focus(); return; }

    const ingredientesForm = [];
    let formValido = true;
    $('#ficha-ingredients-list li:not(.placeholder-ingredient)').each(function() {
        const foodId = $(this).data('id').toString();
        const qtyBrutaInput = $(this).find('.ingredient-peso-bruto-input').val();
        const qtyBruta = parseFloat(qtyBrutaInput);
        const fcIngrediente = parseFloat($(this).find('.ingredient-fc-input').val()) || 1.0;

        if (foodId && !isNaN(qtyBruta) && qtyBruta > 0 && !isNaN(fcIngrediente) && fcIngrediente > 0) {
            ingredientesForm.push({
                foodId: foodId, qty: qtyBruta, fc: fcIngrediente,
                nomeOriginal: $(this).data('food-name') || (alimentosBaseParaFicha[foodId] ? alimentosBaseParaFicha[foodId].nome : `Alimento ID ${foodId}`)
            });
        } else {
            displayMessageBox("Verifique os dados dos ingredientes. Pesos e FC devem ser números positivos.");
            $(this).find('input').first().focus(); formValido = false; return false;
        }
    });

    if (!formValido) return;
    if (ingredientesForm.length === 0) { displayMessageBox("Adicione pelo menos um ingrediente."); $fichaIngredientSearch.focus(); return; }

    const fichaData = {
        id: prepId, nome: nome, porcao_padrao_g: porcaoPadraoG,
        rendimento_porcoes: rendimentoPorcoes,
        rendimento_peso_total_g: rendimentoPesoTotalG,
        custo_total_reais: custoTotalReais,
        fator_coccao_geral: fatorCoccaoGeral,
        modo_preparo: modoPreparo, observacoes: observacoes,
        ingredientes: ingredientesForm,
        ingredientes_json: JSON.stringify(ingredientesForm.map(ing => ({foodId:ing.foodId, qty:ing.qty, fc:ing.fc }))),
        isPreparacao: true,
        updated_at: new Date().toISOString()
    };

    console.log("Sending Ficha Técnica:", fichaData);
    $.ajax({
        url: 'preparacao_actions.php', method: 'POST',
        data: { action: prepIdInput ? 'update_preparacao' : 'create_preparacao', preparacao_data: JSON.stringify(fichaData) },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.todas_preparacoes_atualizadas) {
                todasPreparacoesUsuario = response.todas_preparacoes_atualizadas;
                renderListaFichasTecnicas(); closeModal($fichaTecnicaModal);
                displayMessageBox(response.message || "Ficha técnica salva com sucesso!");
            } else {
                displayMessageBox('Erro ao salvar: ' + (response.message || 'Erro desconhecido. Por favor, tente novamente.'));
                console.error("Error saving ficha:", response.message, response.debug_info);
            }
        }, error: function(jqXHR, textStatus, errorThrown) {
            displayMessageBox('Erro de comunicação ao salvar a ficha técnica. Status: ' + textStatus + ', Erro: ' + errorThrown + '. Verifique o console para detalhes técnicos.');
            console.error("Error AJAX Ficha:", jqXHR.responseText, textStatus, errorThrown);
        }
    });
});

$('#ficha-tecnica-form').on('keydown', 'input:not(#ficha-ingredient-search)', function(e) { if (e.key === 'Enter' && !$(e.target).is('textarea')) { e.preventDefault(); $('#confirm-save-ficha-tecnica-btn').click(); } });

$fichasTecnicasListContainer.on('click', '.delete-ficha-tecnica-btn', function() {
    const prepItem = $(this).closest('.ficha-tecnica-item');
    const prepId = prepItem.data('prep-id'); const prepName = prepItem.data('prep-name');
    displayMessageBox(`Tem certeza que deseja excluir a ficha "<b>${htmlspecialchars(prepName)}</b>"? Esta ação é irreversível.`, true, (result) => {
        if (result) {
            $.ajax({
                url: 'preparacao_actions.php', method: 'POST',
                data: { action: 'delete_preparacao', preparacao_id: prepId }, dataType: 'json',
                success: function(response) {
                    if (response.success && response.todas_preparacoes_atualizadas) {
                        todasPreparacoesUsuario = response.todas_preparacoes_atualizadas;
                        renderListaFichasTecnicas();
                        displayMessageBox(response.message || "Ficha excluída!");
                    } else {
                        displayMessageBox('Erro ao excluir: ' + (response.message || 'Erro desconhecido. Por favor, tente novamente.'));
                    }
                }, error: function(jqXHR, textStatus, errorThrown) {
                    displayMessageBox('Erro de comunicação ao excluir a ficha técnica. Status: ' + textStatus + ', Erro: ' + errorThrown + '. Verifique o console para detalhes técnicos.');
                    console.error("Error AJAX Ficha:", jqXHR.responseText, textStatus, errorThrown);
                }
            });
        }
    });
});

$(document).on('click', function(e) { if (!$fichaIngredientSearch.is(e.target) && !$fichaSearchResults.is(e.target) && $fichaSearchResults.has(e.target).length === 0) { $fichaSearchResults.hide(); } });


/* --- Manage Costs Functionality --- */
let alimentosCustosData = []; // This will hold the initial food cost data from PHP
let filteredAlimentosCustos = []; // To store filtered data

// Load initial food cost data from PHP
function carregarDadosIniciaisCustos() {
    try {
        alimentosCustosData = <?php echo $alimentos_custos_json_para_js ?: '[]'; ?>;
        if (!Array.isArray(alimentosCustosData)) { alimentosCustosData = []; }
        console.log("Initial Costs - Foods:", alimentosCustosData.length);
    } catch (e) {
        console.error("GENERAL Error initializing JS Costs data from PHP:", e);
        alimentosCustosData = [];
    }
    filteredAlimentosCustos = [...alimentosCustosData]; // Initialize filtered list
    renderListaCustos(filteredAlimentosCustos); // Render initially
    attachCostEventListeners(); // Attach event listeners
}

function renderListaCustos(data) {
    const $alimentosCustosListUl = $('#alimentos-custos-list-ul');
    $alimentosCustosListUl.empty(); // Clear existing list

    if (data.length === 0) {
        $alimentosCustosListUl.html('<p id="no-custos-msg" class="no-content-message">Nenhum dado de custo de alimento disponível para os filtros.</p>');
        return;
    }

    data.forEach(alimento => {
        // Ensure custo_unitario_mock is a number, default to 0 if not defined
        const costValue = parseFloat(alimento.custo_unitario_mock || 0).toFixed(2);
        const liHtml = `
            <li class="cost-item" data-food-id="${alimento.id}">
                <div class="cost-info">
                    <span class="cost-name">${htmlspecialchars(alimento.nome)}</span>
                </div>
                <div class="cost-input-group">
                    <span class="currency-prefix">R$</span>
                    <input type="number" class="cost-input auth-input" value="${costValue}" min="0" step="0.01">
                </div>
            </li>`;
        $alimentosCustosListUl.append(liHtml);
    });

    // Re-initialize inputs with original values for tracking changes
    $alimentosCustosListUl.find('.cost-input').each(function() {
        $(this).data('original-value', parseFloat($(this).val()));
    });
     // Disable save button initially if no changes
    $('#save-all-costs-btn').prop('disabled', true).addClass('disabled');
    checkEmptyList('#alimentos-custos-list-ul', '#no-custos-msg', 'Nenhum dado de custo de alimento disponível.');
}

function attachCostEventListeners() {
    const $alimentosCustosListUl = $('#alimentos-custos-list-ul');
    const $saveAllCostsBtn = $('#save-all-costs-btn');
    const $costFoodSearchInput = $('#cost-food-search-input');
    let changedCosts = {}; // {foodId: newCost}

    $alimentosCustosListUl.off('input', '.cost-input').on('input', '.cost-input', function() {
        const $input = $(this);
        const foodId = $input.closest('.cost-item').data('food-id');
        const originalCost = parseFloat($input.data('original-value'));
        const newCost = parseFloat($input.val());

        if (isNaN(newCost) || newCost < 0) {
            $input.addClass('error-input'); // Add visual error
            $saveAllCostsBtn.prop('disabled', true).addClass('disabled');
            return;
        } else {
            $input.removeClass('error-input');
        }

        if (newCost !== originalCost) {
            changedCosts[foodId] = newCost;
        } else {
            delete changedCosts[foodId];
        }

        if (Object.keys(changedCosts).length > 0) {
            $saveAllCostsBtn.prop('disabled', false).removeClass('disabled');
        } else {
            $saveAllCostsBtn.prop('disabled', true).addClass('disabled');
        }
    });

    $saveAllCostsBtn.off('click').on('click', function() {
        if (Object.keys(changedCosts).length === 0) {
            displayMessageBox('Nenhuma alteração de custo para salvar.');
            return;
        }

        displayMessageBox('Deseja realmente salvar as alterações de custo?', true, (result) => {
            if (result) {
                // Transform changedCosts into the expected format for save_food_prices.php
                const updatedPricesPayload = {};
                for (const foodId in changedCosts) {
                    if (changedCosts.hasOwnProperty(foodId)) {
                        // Assuming we are only updating the 'default' category from this simplified manager
                        updatedPricesPayload[foodId] = {
                            'default': {
                                price: changedCosts[foodId],
                                unit: 'g' // Defaulting to 'g' as per general food unit
                            }
                        };
                    }
                }

                $.ajax({
                    url: 'save_food_prices.php', // PHP file to save prices
                    method: 'POST',
                    data: { food_prices: JSON.stringify(updatedPricesPayload) }, // Send the transformed data
                    dataType: 'json',
                    beforeSend: function() {
                        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...'); // 'this' refers to the button
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update the internal alimentosCustosData and original-value for the inputs
                            for (const foodId in changedCosts) {
                                if (changedCosts.hasOwnProperty(foodId)) {
                                    const index = alimentosCustosData.findIndex(item => String(item.id) === String(foodId));
                                    if (index !== -1) {
                                        alimentosCustosData[index].custo_unitario_mock = changedCosts[foodId];
                                    }
                                    // Update the original value for the input field
                                    const $input = $alimentosCustosListUl.find(`.cost-item[data-food-id="${foodId}"] .cost-input`);
                                    if ($input.length) {
                                        $input.data('original-value', changedCosts[foodId]);
                                    }
                                }
                            }

                            displayMessageBox('Custos atualizados com sucesso!');
                            changedCosts = {}; // Clear changed costs after successful save
                            $saveAllCostsBtn.prop('disabled', true).addClass('disabled');
                        } else {
                            displayMessageBox('Erro ao salvar preços: ' + (response.message || 'Desconhecido.'), 'fa-times-circle');
                            console.error("Error in save_food_prices response:", response.message, response.debug_info);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        displayMessageBox('Erro de comunicação ao salvar preços. Tente novamente.', 'fa-times-circle');
                        console.error("AJAX Error save_food_prices:", jqXHR.status, jqXHR.responseText, textStatus, errorThrown);
                    },
                    complete: function() {
                        $saveAllCostsBtn.prop('disabled', false).html('<i class="fas fa-save"></i> Salvar Tudo'); // Reset button text
                    }
                });
            }
        });
    });

    // Search functionality for costs
    $costFoodSearchInput.off('input').on('input', function() {
        const searchTerm = sanitizeString($(this).val());
        filteredAlimentosCustos = alimentosCustosData.filter(alimento =>
            sanitizeString(alimento.nome).includes(searchTerm)
        );
        renderListaCustos(filteredAlimentosCustos);
        // Ensure changes are re-applied if filtered out and back in
        for (const foodId in changedCosts) {
            const $input = $alimentosCustosListUl.find(`.cost-item[data-food-id="${foodId}"] .cost-input`);
            if ($input.length) {
                $input.val(changedCosts[foodId]);
            }
        }
         if (Object.keys(changedCosts).length > 0) {
            $saveAllCostsBtn.prop('disabled', false).removeClass('disabled');
        } else {
            $saveAllCostsBtn.prop('disabled', true).addClass('disabled');
        }
    });
}


// Initialize data and render lists
carregarDadosIniciaisFichas();
carregarDadosIniciaisCustos(); // Loads cost data and attaches listeners
checkEmptyList('#cardapios-list-ul', '#no-projects-msg', 'Você ainda não criou nenhum cardápio. Clique em "Novo Cardápio" para começar!');


/* --- Sidebar Toggle Functionality (for mobile) --- */
// O botão de toggle agora está dentro do sidebar-nav no HTML
const $sidebar = $('#sidebar');
const $sidebarToggleButton = $('#sidebar-toggle-button');
const $sidebarNav = $('#sidebar-nav'); // A navegação inteira é o que se expande/colapsa em mobile

$sidebarToggleButton.on('click', function() {
    // Em desktop, alterna a classe 'collapsed' no sidebar
    // Em mobile, alterna a classe 'active' no sidebar-nav para mostrar/esconder o menu
    if (window.innerWidth > 1024) { // Comportamento para desktop
        $sidebar.toggleClass('collapsed');
        if ($sidebar.hasClass('collapsed')) {
            $(this).find('span').text('Expandir Menu');
            $(this).find('i').removeClass('fa-chevron-left').addClass('fa-chevron-right');
        } else {
            $(this).find('span').text('Minimizar Menu');
            $(this).find('i').removeClass('fa-chevron-right').addClass('fa-chevron-left');
        }
    } else { // Comportamento para mobile/tablet
        $sidebarNav.toggleClass('active');
        if ($sidebarNav.hasClass('active')) {
            $(this).find('span').text('Fechar Menu');
            $(this).find('i').removeClass('fa-bars').addClass('fa-times');
        } else {
            $(this).find('span').text('Menu');
            $(this).find('i').removeClass('fa-times').addClass('fa-bars');
        }
    }
});

// Function to display the correct dashboard section (mantida para compatibilidade, mas o home.php agora só tem uma seção principal)
function showPlatformDashboard(platformSectionId) {
    // Esta função é mais relevante para o home.php quando ele tem múltiplas seções de dashboard
    // No layout atualizado, home.php foca apenas no dashboard NutriPNAE
    // Mas mantemos a estrutura para extensões futuras ou se o usuário quiser reintroduzir outras plataformas
    $('.platform-section-wrapper').removeClass('active-platform');
    $('#' + platformSectionId).addClass('active-platform');

    // Lógica para ativar o link correto no sidebar (já está no PHP, mas pode ser útil para re-renderizações dinâmicas)
    $('.sidebar-nav a').removeClass('active');
    $('.sidebar-nav details summary').removeClass('active');
    
    // Ativa o link do dashboard NutriPNAE (ou o summary principal)
    $('details.nutripnae-tools').prop('open', true).find('summary').addClass('active');
    // Se houvesse um link direto para o dashboard NutriPNAE, ele seria ativado aqui.
}

// Event listeners for platform selection in sidebar
$(document).on('click', '[data-platform-link]', function(e) {
    e.preventDefault(); // Prevent default link behavior
    const platformSectionId = $(this).data('platform-link');
    // Se for um link de dashboard, vai para home.php com o parâmetro
    if (platformSectionId && platformSectionId.includes('dashboard-section')) {
        window.location.href = 'home.php?platform=' + platformSectionId;
    } else {
        // Para outros links de página diretos, navega normalmente
        window.location.href = $(this).attr('href');
    }
});

// Event listeners for platform selection in navbar
$(document).on('click', '.navbar-brand', function(e) {
    e.preventDefault(); // Prevent default link behavior
    const platformSectionId = $(this).data('platform-id'); // Use data-platform-id
    // Redireciona sempre para home.php com o parâmetro da plataforma
    window.location.href = 'home.php?platform=' + platformSectionId;
});


// Initial load: show NutriPNAE dashboard and set its sidebar link as active
// A lógica de ativação inicial do sidebar já está no PHP do sidebar.php
// Esta chamada JS pode ser removida se o PHP for suficiente, mas mantida para garantir.
showPlatformDashboard('nutripnae-dashboard-section');


// Hide sidebar on larger screens if it was opened on mobile
function checkSidebarToggleVisibility() {
    if (window.innerWidth <= 1024) { // Mobile/Tablet
        $sidebarToggleButton.show(); // Mostra o botão de toggle
        $sidebar.removeClass('collapsed'); // Garante que o sidebar não esteja colapsado em mobile
        $sidebarNav.removeClass('active'); // Garante que o menu esteja fechado por padrão em mobile
        $sidebarToggleButton.html('<i class="fas fa-bars"></i> <span>Menu</span>'); // Texto e ícone padrão para mobile
    } else { // Desktop
        $sidebarToggleButton.show(); // Mostra o botão de toggle
        $sidebarNav.removeClass('active'); // Garante que o menu lateral esteja sempre visível em desktop (não escondido pelo 'active')
        // O estado de colapsado/expandido é mantido pelo JS no desktop
        if ($sidebar.hasClass('collapsed')) {
            $sidebarToggleButton.html('<i class="fas fa-chevron-right"></i> <span>Expandir Menu</span>');
        } else {
            $sidebarToggleButton.html('<i class="fas fa-chevron-left"></i> <span>Minimizar Menu</span>');
        }
    }
}

// Initial check and on resize
checkSidebarToggleVisibility();
$(window).on('resize', checkSidebarToggleVisibility);

});
</script>
</body>
</html>
