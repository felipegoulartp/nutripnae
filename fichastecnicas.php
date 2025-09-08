<?php
// cardapio_auto/fichastecnicas.php

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
error_log("--- Início fichastecnicas.php (v1.0 - Gerenciador de Fichas Técnicas) --- SESSION_ID: " . session_id());

// 3. Verificação de Autenticação
$is_logged_in = isset($_SESSION['user_id']);
$logged_user_id = $_SESSION['user_id'] ?? null;
$logged_username = $_SESSION['username'] ?? 'Visitante';

if (!$is_logged_in || !$logged_user_id) {
    error_log("fichastecnicas.php: Acesso não autenticado. Redirecionando para login. Session ID: " . session_id());
    header('Location: login.php');
    exit;
}
error_log("fichastecnicas.php: Usuário autenticado. UserID: $logged_user_id, Username: $logged_username.");

// 4. Variáveis Iniciais, Conexão com BD e Carregamento de Dados
$page_title = "Gerenciador de Fichas Técnicas";
$db_connection_error = false;
$pdo = null;
$preparacoes_usuario_assoc = []; // Será um array associativo { 'id_prep': {dados_prep}, ... }
$erro_carregamento_dados = null;
$dados_base_ok = false; // Flag para verificar se dados.php carregou corretamente

try {
    // Inclui a conexão com o banco de dados
    require_once 'includes/db_connect.php';
    if (!isset($pdo)) {
        throw new \RuntimeException("Objeto PDO não foi definido por db_connect.php");
    }
    error_log("fichastecnicas.php: Conexão com BD estabelecida.");

    // Carrega as preparações personalizadas do usuário do banco de dados
    $sql_prep = "SELECT preparacoes_personalizadas_json FROM cardapio_usuarios WHERE id = :user_id LIMIT 1";
    $stmt_prep = $pdo->prepare($sql_prep);
    $stmt_prep->bindParam(':user_id', $logged_user_id, PDO::PARAM_INT);
    $stmt_prep->execute();
    $json_preps_from_db = $stmt_prep->fetchColumn();
    error_log("fichastecnicas.php: JSON de preparações do BD para UserID $logged_user_id: " . ($json_preps_from_db ? substr($json_preps_from_db,0,100)."..." : 'NULO/VAZIO'));

    if ($json_preps_from_db && $json_preps_from_db !== 'null' && $json_preps_from_db !== '{}' && $json_preps_from_db !== '[]') {
        $decoded = json_decode($json_preps_from_db, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $preparacoes_usuario_assoc = $decoded;
        } else {
            error_log("Erro ao decodificar JSON de preparações do BD para UserID $logged_user_id: " . json_last_error_msg() . ". JSON (início): " . substr($json_preps_from_db, 0, 200) . ". Resetando para array vazio.");
        }
    } else {
        error_log("fichastecnicas.php: Nenhuma preparação personalizada (JSON nulo, vazio ou '[]') no BD para UserID $logged_user_id.");
    }

    // Inclui dados.php para ter acesso aos arrays de alimentos
    // Captura qualquer saída inesperada de dados.php
    ob_start();
    require_once __DIR__ . '/dados.php';
    $dados_php_output = ob_get_clean();
    if (!empty($dados_php_output)) error_log("fichastecnicas.php: Saída inesperada de dados.php: " . substr($dados_php_output, 0, 200));

    // Verifica se os arrays essenciais de dados.php foram definidos e não estão vazios
    if (isset($dados_ok) && $dados_ok === true && isset($alimentos_db) && !empty($alimentos_db) && isset($lista_selecionaveis_db) && !empty($lista_selecionaveis_db)) {
        $dados_base_ok = true;
        // Renomeia para evitar conflitos e deixar claro que são para esta página
        $alimentos_db_ft = $alimentos_db;
        $lista_selecionaveis_db_ft = $lista_selecionaveis_db;
        error_log("fichastecnicas.php: dados.php carregado com sucesso.");
    } else {
        error_log("fichastecnicas.php: Falha ao carregar dados.php. \$dados_ok: " . (isset($dados_ok) ? var_export($dados_ok, true) : 'N/D'));
        if (!$erro_carregamento_dados) $erro_carregamento_dados = "Erro ao carregar dados base de alimentos.";
    }

} catch (\PDOException $e) {
    $db_connection_error = true;
    $erro_carregamento_dados = "Erro crítico: Não foi possível conectar ao banco de dados.";
    error_log("Erro PDO em fichastecnicas.php (UserID $logged_user_id): " . $e->getMessage());
} catch (\Throwable $th) {
     // Captura qualquer outra exceção ou erro
     if (!$erro_carregamento_dados) $erro_carregamento_dados = "Erro inesperado ao carregar dados: " . $th->getMessage();
     error_log("Erro Throwable em fichastecnicas.php: " . $th->getMessage() . "\nTrace: " . $th->getTraceAsString());
     if (ob_get_level() > 0) ob_end_clean(); // Limpa buffer de saída se houver erro
}

// 5. Prepara JSONs para o JavaScript
// Prepara as preparações do usuário para JS (array de objetos)
$preparacoes_array_para_js = !empty($preparacoes_usuario_assoc) ? array_values($preparacoes_usuario_assoc) : [];
$temp_preparacoes_json = json_encode($preparacoes_array_para_js);

$preparacoes_usuario_json_para_js = (json_last_error() === JSON_ERROR_NONE) ? $temp_preparacoes_json : '[]';
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("fichastecnicas.php: Erro ao encodar \$preparacoes_array_para_js para JSON: " . json_last_error_msg());
    $preparacoes_usuario_json_para_js = '[]'; // Fallback seguro para JSON vazio
}
error_log("fichastecnicas.php: JSON de preparações (array) para JS: " . substr($preparacoes_usuario_json_para_js, 0, 200) . "...");


// Prepara os alimentos base para JS (objeto associativo por ID)
$alimentos_base_para_js = [];
if ($dados_base_ok && is_array($lista_selecionaveis_db_ft)) {
    foreach ($lista_selecionaveis_db_ft as $id => $data) {
        // Garante que o ID é numérico e que o alimento existe em $alimentos_db_ft
        if (is_numeric($id) && isset($data['nome']) && isset($alimentos_db_ft[$id])) {
            $custo_kg_l = $alimentos_db_ft[$id]['custo_kg'] ?? 0; // Assume 'custo_kg' como chave para custo
            $fc_padrao_alimento = $alimentos_db_ft[$id]['fc_padrao'] ?? 1.0; // Adiciona FC padrão se existir

            // Adiciona dados nutricionais se existirem, caso contrário, inicializa com 0
            $nutri_data = [
                'energia_kcal' => $alimentos_db_ft[$id]['kcal'] ?? 0,
                'carboidratos_g' => $alimentos_db_ft[$id]['carboidratos'] ?? 0,
                'proteinas_g' => $alimentos_db_ft[$id]['proteina'] ?? 0,
                'lipideos_g' => $alimentos_db_ft[$id]['lipideos'] ?? 0,
                'colesterol_mg' => $alimentos_db_ft[$id]['colesterol'] ?? 0, // Adicione outros nutrientes conforme necessário
                'fibras_g' => $alimentos_db_ft[$id]['fibra_dieta'] ?? 0,
                'vitamina_a_mcg' => $alimentos_db_ft[$id]['retinol'] ?? 0,
                'vitamina_c_mg' => $alimentos_db_ft[$id]['vitamina_c'] ?? 0,
                'calcio_mg' => $alimentos_db_ft[$id]['calcio'] ?? 0,
                'ferro_mg' => $alimentos_db_ft[$id]['ferro'] ?? 0,
                'sodio_mg' => $alimentos_db_ft[$id]['sodio'] ?? 0,
            ];

            $alimentos_base_para_js[(string)$id] = [
                'id' => (string)$id,
                'nome' => $data['nome'],
                'isPreparacao' => false, // Indica que é um alimento base, não uma preparação
                'custo_kg_l' => (float)$custo_kg_l,
                'fc_padrao' => (float)$fc_padrao_alimento,
                'nutri_data' => $nutri_data // Inclui os dados nutricionais
            ];
        }
    }
}
$temp_alimentos_base_json = json_encode($alimentos_base_para_js);
$alimentos_base_json_para_js = (json_last_error() === JSON_ERROR_NONE) ? $temp_alimentos_base_json : '{}';
if (json_last_error() !== JSON_ERROR_NONE) error_log("fichastecnicas.php: Erro ao encodar \$alimentos_base_para_js para JSON: " . json_last_error_msg());
error_log("fichastecnicas.php: JSON de alimentos base (objeto) para JS: " . substr($alimentos_base_json_para_js, 0, 200) . "...");
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
    <style>
        :root {
            --font-primary: 'Poppins', sans-serif;
            --font-secondary: 'Roboto', sans-serif;
            --font-handwriting: 'Permanent Marker', cursive;
            --font-size-base: 14px;

            --color-primary: #2196F3; /* Azul vibrante (NutriPNAE) */
            --color-primary-dark: #1976D2; /* Azul mais escuro */
            --color-primary-light: #BBDEFB; /* Azul mais claro */
            --color-primary-xtralight: #EBF4FF;

            --color-accent: #FFC107; /* Amarelo dourado */
            --color-accent-dark: #FFA000; /* Amarelo mais escuro */

            --color-secondary: #6c757d;
            --color-secondary-light: #adb5bd;

            --color-bg-light: #f2f2f2; /* Fundo claro (cinza claro) */
            --color-bg-white: #FFFFFF; /* Fundo branco para cards e elementos */

            --color-text-dark: #343a40;
            --color-text-light: #6c757d;
            --color-text-on-dark: #FFFFFF;

            --color-border: #DEE2E6;
            --color-light-border: #E9ECEF;

            --color-success: #28a745;
            --color-success-light: #e2f4e6;
            --color-success-dark: #1e7e34;

            --color-warning: #ffc107;
            --color-warning-light: #fff8e1;
            --color-warning-dark: #d39e00;

            --color-error: #dc3545;
            --color-error-light: #f8d7da;
            --color-error-dark: #a71d2a;

            --color-info: #17a2b8;
            --color-info-light: #d1ecf1;
            --color-info-dark: #117a8b;

            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --box-shadow-hover: 0 6px 16px rgba(0, 0, 0, 0.12);
            --transition-speed: 0.25s;

            /* Cores específicas das plataformas para o menu e logos */
            --nutrigestor-red: #EA1D2C; /* iFood Red */
            --nutrigestor-dark: #B51522; /* Darker iFood Red */
            --nutridev-purple: #8A2BE2; /* Roxo forte para NutriDEV */
            --nutridev-dark: #6A1B9A; /* Roxo mais escuro para NutriDEV */
        }

        /* Reset e Base */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font-secondary);
            line-height: 1.6;
            color: var(--color-text-dark);
            /* Gradient background: cinza claro com branco */
            background: linear-gradient(180deg, #FFFFFF 0%, #F5F5F5 100%); /* White to very light gray */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden; /* Prevent horizontal scroll on small screens */
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--font-primary);
            margin-top: 0;
            margin-bottom: 0.5em;
            color: var(--color-text-dark);
        }

        p { margin-bottom: 1em; font-size: 1.05em; }
        a { color: var(--color-primary); text-decoration: none; transition: color var(--transition-speed); }
        a:hover { color: var(--color-primary-dark); }

        /* Navbar Superior (do home.php) */
        .navbar {
            background-color: var(--color-bg-white);
            padding: 15px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            position: sticky;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }
        .navbar .container {
            max-width: 1800px; /* Aumentado para melhor aproveitamento do espaço */
            margin: 0 auto;
            padding: 0 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar-brand-group {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-left: 0;
            padding-left: 0;
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            font-family: var(--font-primary);
            font-size: 1.7em;
            font-weight: 700;
            white-space: nowrap;
        }
        .navbar-brand i {
            margin-right: 8px;
            font-size: 1.2em;
        }
        .navbar-brand.pnae { color: var(--color-primary-dark); }
        .navbar-brand.nutrigestor { color: var(--nutrigestor-red); }
        .navbar-brand.nutridev { color: var(--nutridev-purple); }

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .user-greeting {
            font-size: 1.1em; /* Larger user greeting */
            color: var(--color-text-dark);
            font-weight: 500;
            font-family: var(--font-primary);
        }
        .btn-header-action {
            padding: 8px 18px;
            border: 1px solid var(--color-primary-light);
            color: var(--color-primary);
            background-color: transparent;
            border-radius: var(--border-radius);
            font-family: var(--font-primary);
            font-weight: 500;
            font-size: 0.9em;
            transition: background-color var(--transition-speed), color var(--transition-speed), border-color var(--transition-speed), transform 0.1s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }
        .btn-header-action:hover {
            background-color: var(--color-accent); /* Dourado no hover */
            color: var(--color-text-dark); /* Texto escuro para contraste */
            border-color: var(--color-accent-dark);
            transform: translateY(-1px);
        }
        .btn-header-action.logout {
            background-color: var(--color-error);
            color: var(--color-text-on-dark);
            border-color: var(--color-error);
        }
        .btn-header-action.logout:hover {
            background-color: var(--color-error-dark);
            border-color: var(--color-error-dark);
            color: var(--color-text-on-dark);
        }

        /* Main Content Wrapper (Sidebar + Content) */
        .main-wrapper {
            display: flex;
            flex-grow: 1;
            width: 100%;
            padding-top: 0; /* No top padding, navbar handles it */
        }

        /* Sidebar styles */
        .sidebar {
            width: 350px; /* Increased width for sidebar */
            background-color: var(--color-bg-white);
            box-shadow: 2px 0 8px rgba(0,0,0,0.1);
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            z-index: 990;
            transition: width 0.3s ease, transform 0.3s ease;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            flex-shrink: 0; /* Prevent sidebar from shrinking */
        }

        .sidebar-toggle-button {
            display: none; /* Hidden by default, shown on mobile */
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            flex-grow: 1;
        }

        /* Standardize sidebar buttons */
        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 20px; /* Standardized padding */
            color: var(--color-text-dark);
            text-decoration: none;
            font-size: 1.1em;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease, border-left-color 0.2s ease;
            border-left: 4px solid transparent;
            margin-bottom: 5px; /* Spacing between top-level items */
        }
        .sidebar-nav a .fas {
            margin-right: 15px;
            font-size: 1.2em;
        }
        .sidebar-nav a:hover {
            background-color: var(--color-primary-xtralight);
            color: var(--color-primary-dark);
            border-left-color: var(--color-primary);
        }
        .sidebar-nav a.active {
            background-color: var(--color-primary-xtralight);
            color: var(--color-primary-dark);
            border-left-color: var(--color-primary-dark);
            font-weight: 600;
        }

        .sidebar-nav .menu-section-title {
            padding: 10px 20px;
            font-weight: bold;
            color: var(--color-text-light);
            font-size: 0.9em;
            text-transform: uppercase;
            border-bottom: 1px solid var(--color-light-border);
            margin-top: 15px;
            margin-bottom: 5px;
        }

        .sidebar-nav details {
            margin-bottom: 5px;
        }
        .sidebar-nav details summary {
            display: flex;
            align-items: center;
            padding: 12px 20px; /* Standardized padding */
            color: var(--color-text-dark);
            text-decoration: none;
            font-size: 1.1em;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease, border-left-color 0.2s ease;
            border-left: 4px solid transparent;
            list-style: none; /* Remove default marker */
        }
        .sidebar-nav details summary::-webkit-details-marker {
            display: none; /* Remove default marker in WebKit */
        }

        .sidebar-nav details summary .fas {
            margin-right: 15px;
            font-size: 1.2em;
        }
        .sidebar-nav details summary:hover {
            background-color: var(--color-primary-xtralight);
            color: var(--color-primary-dark);
            border-left-color: var(--color-primary);
        }
        .sidebar-nav details summary.active {
            background-color: var(--color-primary-xtralight);
            color: var(--color-primary-dark);
            border-left-color: var(--color-primary-dark);
            font-weight: 600;
        }

        /* Specific colors for summary icons */
        details.nutripnae-tools summary .fas { color: var(--color-primary-dark); }
        details.nutrigestor-tools summary .fas { color: var(--nutrigestor-red); }
        details.nutridev-tools summary .fas { color: var(--nutridev-purple); }

        .sidebar-nav ul {
            list-style: none;
            padding-left: 30px; /* Indent sub-menu items */
            padding-top: 5px;
            padding-bottom: 5px;
            background-color: #f8f8f8; /* Slightly different background for open sub-menus */
            border-left: 4px solid var(--color-light-border);
        }
        .sidebar-nav ul li a {
            display: flex;
            align-items: center;
            padding: 8px 10px;
            color: var(--color-text-light);
            font-size: 1em;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .sidebar-nav ul li a .fas {
            margin-right: 10px;
            font-size: 0.9em;
        }
        .sidebar-nav ul li a:hover {
            background-color: var(--color-light-border);
            color: var(--color-text-dark);
        }
        .sidebar-nav ul li a.active {
            font-weight: 600;
            color: var(--color-primary-dark);
            background-color: var(--color-primary-light);
        }
        /* Specific colors for submenu icons */
        details.nutripnae-tools ul li a .fas { color: var(--color-primary); }
        details.nutrigestor-tools ul li a .fas { color: var(--nutrigestor-red); }
        details.nutridev-tools ul li a .fas { color: var(--nutridev-purple); }


        /* Content area */
        .content-area {
            flex-grow: 1;
            padding: 20px;
            background-color: transparent;
        }
        .content-area .container {
            max-width: 1800px; /* Aumentado para melhor aproveitamento do espaço */
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Page specific styles from fichastecnicas.php */
        .fichas-tecnicas-page-title {
            font-family: var(--font-primary); color: var(--color-text-dark);
            font-size: 2.2em; font-weight: 700; margin-bottom: 25px; text-align: left;
        }

        .fichas-tecnicas-layout {
            display: flex;
            gap: 25px;
            max-width: 1800px; /* Alinhado com o container principal */
            margin: 0 auto;
            align-items: flex-start; /* Alinha o topo dos containers */
        }

        .lista-fichas-container, .editor-ficha-container {
            background-color: var(--color-bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid var(--color-light-border);
            padding: 25px;
            display: flex;
            flex-direction: column;
        }

        .lista-fichas-container {
            flex: 0 0 420px; /* Fixed width */
            min-height: 400px;
            height: calc(100vh - 180px); /* Adjust height to fill screen below navbar */
        }
        .editor-ficha-container {
            flex-grow: 1;
            min-height: 500px;
            height: calc(100vh - 180px); /* Adjust height to fill screen below navbar */
            overflow-y: auto;
        }

        @media (max-width: 992px) {
            .fichas-tecnicas-layout { flex-direction: column; }
            .lista-fichas-container, .editor-ficha-container { flex: 1 1 auto; max-height: none; height: auto; }
            .lista-fichas-container { min-height: 300px; }
        }

        .section-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid var(--color-border);
        }
        .section-header h2 {
            margin: 0; font-size: 1.5em; font-family: var(--font-primary); color: var(--color-primary-dark);
        }

        #new-ficha-btn-list-header { /* Botão no header da lista de fichas */
            background-color: var(--color-success); color: var(--color-text-on-dark); border: none;
            padding: 9px 18px; font-size: 0.9em; font-weight: 600;
            font-family: var(--font-primary); border-radius: 20px; cursor: pointer;
            transition: background-color 0.2s ease, transform 0.15s ease-out, box-shadow 0.15s ease-out;
            display: inline-flex; align-items: center; gap: 6px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        #new-ficha-btn-list-header:hover {
            background-color: var(--color-success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
        }

        #lista-fichas-tecnicas-ul {
            list-style: none; padding: 0; margin: 0;
            flex-grow: 1; overflow-y: auto; padding-right: 8px;
        }
        #lista-fichas-tecnicas-ul::-webkit-scrollbar { width: 10px; }
        .editor-ficha-container::-webkit-scrollbar { width: 10px; } /* Scrollbar para o editor */

        #lista-fichas-tecnicas-ul::-webkit-scrollbar-track,
        .editor-ficha-container::-webkit-scrollbar-track { background: var(--color-primary-xtralight); border-radius: 5px; }
        #lista-fichas-tecnicas-ul::-webkit-scrollbar-thumb,
        .editor-ficha-container::-webkit-scrollbar-thumb {
            background-color: var(--color-secondary-light); border-radius: 5px;
            border: 2px solid var(--color-primary-xtralight);
        }
        #lista-fichas-tecnicas-ul::-webkit-scrollbar-thumb:hover,
        .editor-ficha-container::-webkit-scrollbar-thumb:hover { background-color: var(--color-secondary); }


        .ficha-tecnica-item-li {
            background-color: var(--color-bg-white); border: 1px solid var(--color-light-border);
            border-left: 4px solid var(--color-primary-light); /* Changed to primary-light for NutriPNAE consistency */
            border-radius: var(--border-radius); margin-bottom: 10px; padding: 12px 15px;
            cursor: pointer; transition: box-shadow var(--transition-speed), border-left-color var(--transition-speed), background-color var(--transition-speed);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ficha-tecnica-item-li:hover, .ficha-tecnica-item-li.selected {
            box-shadow: var(--box-shadow-hover); border-left-color: var(--color-primary-dark); /* Darker primary on hover/selected */
            background-color: var(--color-primary-xtralight); /* Lighter primary background on hover/selected */
        }
        .ficha-tecnica-item-li.selected { border-left-width: 5px; font-weight: 500; }

        .ficha-item-info { flex-grow: 1; }
        .ficha-item-info h3 {
            margin: 0 0 4px 0; font-size: 1.05em; font-weight: 600; color: var(--color-text-dark);
            font-family: var(--font-primary);
        }
        .ficha-item-info h3 .fas { margin-right: 8px; color:var(--color-primary); } /* Icon color for list items */
        .ficha-item-meta { font-size: 0.8em; color: var(--color-text-light); display: block; }
        .ficha-item-actions {
            margin-top: 0;
            text-align: right;
            display: flex;
            gap: 4px; /* Reduced gap */
        }
        .action-button-icon-small {
            background: none; border: none; cursor: pointer; padding: 5px; /* Increased padding slightly */
            font-size: 1em; /* Standard size */
            color: var(--color-secondary);
            transition: color var(--transition-speed), transform 0.1s ease, background-color var(--transition-speed);
            line-height: 1; border-radius: 50%;
            width: 30px; height: 30px; /* Standard size for button area */
            display: inline-flex; justify-content: center; align-items: center;
        }
        .action-button-icon-small:hover { transform: scale(1.1); }
        .action-button-icon-small.delete-btn-small:hover { color: var(--color-error-dark); background-color: var(--color-error-light); }
        .action-button-icon-small.edit-btn-small:hover { color: var(--color-primary-dark); background-color: var(--color-primary-xtralight); }
        .action-button-icon-small.duplicate-btn-small:hover { color: var(--color-info-dark); background-color: var(--color-info-light); }

        #no-fichas-msg-li { text-align: center; color: var(--color-text-light); padding: 20px 0; list-style-type: none;}


        #ficha-tecnica-editor-form { display: block; }
        .form-section-title {
            font-family: var(--font-primary); font-size: 1.3em; font-weight: 600;
            color: var(--color-primary-dark); margin-top: 20px; margin-bottom: 15px;
            padding-bottom: 8px; border-bottom: 1px solid var(--color-primary-light);
        }
        .form-section-title:first-of-type { margin-top: 0; }

        #editor-form-title-h2 {
             font-family: var(--font-primary); font-size: 1.6em; font-weight: 600;
            color: var(--color-primary-dark); margin-bottom: 20px;
            padding-bottom: 10px; border-bottom: 1px solid var(--color-border);
        }

        /* Ajuste de largura para inputs gerais */
        #ficha-tecnica-editor-form input.auth-input { /* Apenas inputs, não textareas */
            width: 100%;
            max-width: 250px; /* Largura uniforme para inputs de dados gerais */
            padding: 8px 10px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            font-size: 0.9em;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
        }

        /* Ajuste específico para as textareas */
        #ficha-modo-preparo, #ficha-observacoes, #ficha-tecnica-editor-form textarea.auth-input {
            width: 100%;
            max-width: 100%; /* Permite que a textarea ocupe 100% da largura do pai */
            padding: 8px 10px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            font-size: 0.9em;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
            resize: vertical; /* Permite redimensionamento vertical */
            min-height: 70px;
        }

        #ficha-tecnica-editor-form .auth-input:focus, #ficha-tecnica-editor-form textarea.auth-input:focus {
            border-color: var(--color-primary); box-shadow: 0 0 0 2px var(--color-primary-xtralight); outline: none;
        }
        
        .input-addon-wrapper { position: relative; display: flex; align-items: center;} /* Added flex for better alignment */
        .input-addon-wrapper .auth-input { padding-right: 35px; flex-grow: 1;} /* Made input flexible */
        .input-addon {
            position: absolute; right: 1px; top: 1px; bottom: 1px;
            background-color: var(--color-light-border); color: var(--color-text-light);
            padding: 0 10px;
            display: flex; align-items: center;
            font-size: 0.85em;
            border-top-right-radius: var(--border-radius);
            border-bottom-right-radius: var(--border-radius);
        }
        #ficha-tecnica-editor-form .auth-input[readonly] { background-color: #e9ecef; cursor: default; }
        #ficha-tecnica-editor-form .auth-input.is-invalid { border-color: var(--color-error); box-shadow: 0 0 0 2px var(--color-error-light); }


        /* Novo estilo para organizar os campos de Dados Gerais em coluna e alinhados */
        .form-general-data-layout .form-group {
            display: flex; /* Usa flexbox para alinhar label e input */
            align-items: center; /* Alinha verticalmente */
            margin-bottom: 10px; /* Espaçamento entre os grupos */
        }

        .form-general-data-layout .form-group label {
            flex-basis: 180px; /* Aumenta a largura fixa para as labels para acomodar textos mais longos */
            text-align: left; /* Alinha o texto da label à esquerda */
            margin-right: 10px; /* Espaçamento entre label e input */
            white-space: nowrap; /* Evita quebra de linha na label */
            font-weight: 500; /* Consistent with other labels */
            color: var(--color-text-dark); /* Consistent color */
            font-size: 0.9em; /* Consistent size */
        }

        .form-general-data-layout .form-group .auth-input,
        .form-general-data-layout .form-group .input-addon-wrapper {
            flex-grow: 1; /* Permite que o input preencha o restante do espaço */
            max-width: 250px; /* Mantém a largura uniforme dos inputs */
        }
        /* Ajuste para o campo de nome, para que ele possa ser mais largo */
        .form-general-data-layout .form-group.full-width-name .auth-input {
            max-width: 400px; /* Largura máxima para o nome */
        }


        #ficha-ingredients-list-editor {
            list-style-type: none; padding-left: 0; margin-top: 10px;
            border:1px solid var(--color-light-border); padding:10px;
            border-radius:var(--border-radius); background-color: #fdfdfd; min-height: 100px;
            max-height: 300px;
            overflow-y: auto; /* Scrollbar apenas aqui */
        }
        #ficha-ingredients-list-editor::-webkit-scrollbar { width: 8px; }
        #ficha-ingredients-list-editor::-webkit-scrollbar-track { background: var(--color-primary-xtralight); border-radius: 4px; }
        #ficha-ingredients-list-editor::-webkit-scrollbar-thumb { background-color: var(--color-secondary-light); border-radius: 4px; }
        #ficha-ingredients-list-editor::-webkit-scrollbar-thumb:hover { background-color: var(--color-secondary); }

        #ficha-ingredients-list-editor li:not(.placeholder-ingredient-editor) {
            display: grid;
            grid-template-columns: minmax(150px, 2fr) repeat(3, minmax(90px, 1fr)) minmax(200px, 1.5fr) auto; /* Ajuste para o layout de custo/peso */
            gap: 10px;
            align-items: center; /* Alinha verticalmente os itens do grid */
            padding: 10px 8px;
            background-color: var(--color-bg-white); border: 1px solid var(--color-light-border);
            border-radius: var(--border-radius); margin-bottom: 8px;
            font-size: 0.95em; /* Aumenta a fonte */
        }
         #ficha-ingredients-list-editor li:not(.placeholder-ingredient-editor) .ing-label {
            font-size: 0.85em; /* Aumenta a fonte do label do ingrediente */
            color: var(--color-text-light); display: block; margin-bottom: 2px; text-align:left;
        }
        #ficha-ingredients-list-editor li .ingredient-name-editor {
            font-weight: 500; align-self: center; font-size: 1.1em; /* Aumenta a fonte do nome do alimento */
            color: var(--color-text-dark);
        }
        #ficha-ingredients-list-editor li input[type="number"].ing-input {
            width: 100%; padding: 5px; font-size: 1em; text-align: right;
            border: 1px solid var(--color-border); border-radius: var(--border-radius);
        }

        /* Novo estilo para Peso Líquido e Custo Ing. lado a lado */
        #ficha-ingredients-list-editor li .ing-display-group {
            display: flex; /* Altera para flex-direction: row (default) */
            flex-direction: row; /* Explicitamente definido como linha */
            gap: 10px; /* Espaçamento entre os dois blocos (Peso Líquido e Custo Ing.) */
            flex-wrap: wrap; /* Permite quebrar linha em telas menores */
            justify-content: flex-end; /* Alinha à direita dentro do seu grid column */
        }
        #ficha-ingredients-list-editor li .ing-display-row {
            display: flex;
            flex-direction: column; /* Label em cima, valor embaixo para cada par */
            align-items: flex-end; /* Alinha o conteúdo à direita dentro de sua coluna */
        }
        #ficha-ingredients-list-editor li .ing-display-row .ing-label {
            margin-bottom: 2px; /* Pequeno espaçamento entre label e valor */
            font-size: 0.75em; /* Label ligeiramente menor para compactação */
            text-align: right;
            width: 100%; /* Garante que a label ocupe a largura total de seu container */
            color: var(--color-text-light);
        }
        #ficha-ingredients-list-editor li .ing-display {
            font-weight:bold; text-align:right; padding: 5px; border: 1px solid var(--color-light-border);
            background-color: #f8f9fa; border-radius: var(--border-radius);
            flex-grow: 1; /* Permite que o display cresça */
            min-width: 70px; /* Garante largura mínima para o display */
            max-width: 100px; /* Controla a largura máxima */
            color: var(--color-primary-dark);
        }

        #ficha-ingredients-list-editor li .ingredient-remove-btn-editor {
            background: none; border: none; color: var(--color-error); font-size: 1.2em; cursor: pointer; padding: 5px; /* Ajusta o tamanho do ícone */
            align-self: center;
        }
        #ficha-ingredients-list-editor li .ingredient-remove-btn-editor:hover { color: var(--color-error-dark); }
        .placeholder-ingredient-editor {
            text-align:center; color: var(--color-text-light); padding:15px; list-style-type:none;
            border:1px dashed var(--color-light-border); background-color: #f9f9f9; border-radius: var(--border-radius);
        }


        #ficha-search-results-editor {
            list-style: none;padding: 0;margin: 0;border-top: none;max-height: 180px;overflow-y: auto;
            position: absolute; background: white; z-index: 1010; width:100%;
            border: 1px solid var(--color-border); border-top:none; display:none; box-shadow: var(--box-shadow);
            border-bottom-left-radius: var(--border-radius);
            border-bottom-right-radius: var(--border-radius);
        }
        #ficha-search-results-editor li {padding: 7px 10px; cursor: pointer;font-size: 0.85em; border-bottom: 1px solid var(--color-light-border);}
        #ficha-search-results-editor li:last-child {border-bottom: none;}
        #ficha-search-results-editor li:hover {background-color: var(--color-primary-xtralight);color: var(--color-primary-dark);}

        /* Estilos para a nova seção de Valor Nutricional */
        .nutritional-data-container-wrapper {
            display: flex;
            flex-wrap: wrap; /* Permite quebra de linha em telas menores */
            gap: 20px;
            margin-top: 10px;
            justify-content: space-around; /* Centraliza e distribui os blocos */
        }

        .nutritional-data-block {
            flex: 1; /* Permite que cada bloco ocupe o espaço disponível */
            min-width: 300px; /* Largura mínima para evitar que fiquem muito estreitos */
            background-color: var(--color-bg-white);
            border: 1px solid var(--color-light-border);
            border-radius: var(--border-radius);
            padding: 15px;
            box-shadow: var(--box-shadow);
        }

        .nutritional-data-block h4 {
            font-family: var(--font-primary);
            font-size: 1.1em;
            font-weight: 600;
            color: var(--color-primary-dark);
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--color-primary-light);
            text-align: center;
        }

        .nutritional-data-layout {
            display: grid;
            grid-template-columns: 1fr 1fr; /* Duas colunas para label e valor */
            gap: 8px; /* Espaçamento menor entre os itens nutricionais */
        }
        .nutritional-data-layout .form-group {
            display: contents; /* Faz com que os filhos sejam os itens do grid */
        }
        .nutritional-data-layout .form-group label {
            text-align: left;
            font-weight: 500;
            color: var(--color-text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .nutritional-data-layout .form-group .nutri-display {
            padding: 5px 8px;
            background-color: #e9ecef;
            border: 1px solid var(--color-light-border);
            border-radius: var(--border-radius);
            font-weight: bold;
            text-align: right;
            color: var(--color-primary-dark);
            cursor: default;
            width: 100%; /* Garante que o span ocupe a largura da coluna */
        }

        .custom-portion-input-group {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            gap: 10px;
        }
        .custom-portion-input-group label {
            font-weight: 500;
            color: var(--color-text-dark);
            white-space: nowrap;
        }
        .custom-portion-input-group input {
            flex-grow: 1;
            padding: 8px 10px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            font-size: 0.9em;
            text-align: right;
        }
        .custom-portion-input-group span {
            font-weight: 500;
            color: var(--color-text-light);
        }


        .form-actions-footer {
            margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--color-border);
            display: flex; justify-content: flex-end; gap: 12px;
        }
        .form-actions-footer .form-button {
            padding: 10px 22px; font-size: 0.9em; font-weight: 500;
            font-family: var(--font-primary); border-radius: 20px;
            border: none; cursor: pointer; transition: background-color var(--transition-speed), transform 0.1s ease, box-shadow 0.1s ease;
        }
        .form-button.save { background-color: var(--color-success); color: white; }
        .form-button.save:hover { background-color: var(--color-success-dark); transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .form-button.cancel { background-color: var(--color-secondary); color: white; }
        .form-button.cancel:hover { background-color: var(--color-text-dark); transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

        .error-container, .success-container, .info-container {
            padding: 15px; border-radius: var(--border-radius); text-align: center; margin-bottom: 20px;
            font-size: 0.9em; display: flex; align-items: center; justify-content: center;
        }
        .error-container { background-color: var(--color-error-light); color: var(--color-error-dark); border: 1px solid var(--color-error); }
        .success-container { background-color: var(--color-success-light); color: var(--color-success-dark); border: 1px solid var(--color-success); }
        .info-container { background-color: var(--color-info-light); color: var(--color-info-dark); border: 1px solid var(--color-info); }
        .error-container i, .success-container i, .info-container i { margin-right: 8px; }


        /* Custom Message Box (replacing alert() and confirm()) - Copied from home.php */
        .custom-message-box-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            z-index: 2000; /* Higher z-index than modals */
            backdrop-filter: blur(3px);
            animation: fadeInModal 0.2s ease-out;
        }

        .custom-message-box-content {
            background-color: var(--color-bg-white);
            padding: 25px 30px;
            border-radius: var(--border-radius);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            width: 90%;
            text-align: center;
            animation: slideInModal 0.2s ease-out;
            border: 1px solid var(--color-light-border);
            position: relative;
        }

        .custom-message-box-content p {
            font-size: 1.1em;
            color: var(--color-text-dark);
            margin-bottom: 20px;
        }

        .message-box-close-btn {
            background-color: var(--color-primary);
            color: var(--color-text-on-dark);
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s ease, transform 0.1s ease;
            font-family: var(--font-primary);
        }

        .message-box-close-btn:hover {
            background-color: var(--color-primary-dark);
            transform: translateY(-1px);
        }
        @keyframes slideInModal {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes fadeInModal { from { opacity: 0; } to { opacity: 1; } }

        .main-footer-bottom {
            text-align: center; padding: 20px; margin-top: auto;
            background-color: var(--color-primary-dark);
            color: var(--color-primary-xtralight);
            font-size: 0.9em; border-top: 1px solid var(--color-primary);
        }

        /* Responsive Adjustments (from home.php) */
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
            .fichas-tecnicas-layout {
                flex-direction: column;
                max-width: 95%; /* Adjust max-width for smaller screens */
            }
            .lista-fichas-container, .editor-ficha-container {
                flex: 1 1 auto; max-height: none; height: auto;
                width: 100%; /* Ensure they take full width */
            }
            .nutritional-data-container-wrapper {
                flex-direction: column; /* Stack blocks vertically on smaller screens */
            }
            .nutritional-data-block {
                min-width: unset; /* Remove min-width when stacked */
                width: 100%;
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
            .fichas-tecnicas-page-title {
                font-size: 1.8em;
                text-align: center; /* Center title on mobile */
            }
            .section-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            .section-header h2 {
                font-size: 1.3em;
            }
            #new-ficha-btn-list-header {
                width: 100%;
                justify-content: center;
            }
            /* Adjust ingredient list for mobile */
            #ficha-ingredients-list-editor li:not(.placeholder-ingredient-editor) {
                grid-template-columns: 1fr; /* Stack elements */
                padding: 15px;
            }
            #ficha-ingredients-list-editor li .ing-display-group {
                justify-content: flex-start; /* Align left */
            }
            #ficha-ingredients-list-editor li .ing-display-row {
                align-items: flex-start; /* Align labels and values left */
            }
        }
    </style>
</head>
<body>
    <!-- Custom Message Box HTML -->
    <div id="custom-message-box-overlay" class="custom-message-box-overlay">
        <div class="custom-message-box-content">
            <p id="message-box-text"></p>
            <button class="message-box-close-btn">OK</button>
        </div>
    </div>

    <!-- Navbar Superior (do home.php) -->
    <nav class="navbar">
        <div class="container">
            <div class="navbar-brand-group">
                <a href="#" class="navbar-brand pnae" data-platform-id="nutripnae-dashboard-section">
                    <i class="fas fa-utensils"></i>NutriPNAE
                </a>
                <a href="#" class="navbar-brand nutrigestor" data-platform-id="nutrigestor-dashboard-section">
                    <i class="fas fa-concierge-bell"></i>NutriGestor
                </a>
                <a href="#" class="navbar-brand nutridev" data-platform-id="nutridev-dashboard-section">
                    <i class="fas fa-laptop-code"></i>NutriDEV
                </a>
            </div>
            <div class="navbar-actions">
                <?php if ($is_logged_in): ?>
                    <span class="user-greeting">Olá, <span style="font-size: 1.2em; font-weight: 700; color: var(--color-primary-dark);"><?php echo htmlspecialchars($logged_username); ?></span>!</span>
                    <a href="ajuda.php" class="btn-header-action"><i class="fas fa-question-circle"></i> Ajuda</a>
                    <a href="logout.php" class="btn-header-action logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="main-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <button class="sidebar-toggle-button" id="sidebar-toggle-button">
                <i class="fas fa-bars"></i> Menu
            </button>
            <nav class="sidebar-nav" id="sidebar-nav">
                <!-- Página Principal Link -->
                <a href="https://nutripnae.com" class="sidebar-top-link"><i class="fas fa-home"></i> Página Principal</a>
                <!-- Dashboard Link -->
                <a href="home.php" class="sidebar-top-link" data-platform-link="nutripnae-dashboard-section"><i class="fas fa-tachometer-alt"></i> Dashboard</a>

                <!-- NutriPNAE Tools Section -->
                <details class="nutripnae-tools" open>
                    <summary><i class="fas fa-school"></i> NutriPNAE</summary>
                    <ul>
                        <!-- Gerenciar Cardápios - now a details element itself -->
                        <details class="nutripnae-tools" style="margin-left: -30px;">
                            <summary style="border-left: none; padding-left: 30px;"><i class="fas fa-clipboard-list" style="color: var(--color-primary);"></i> Gerenciar Cardápios</summary>
                            <ul>
                                <li><a href="index.php"><i class="fas fa-plus" style="color: var(--color-primary);"></i> Novo Cardápio Semanal</a></li>
                                <li><a href="cardapios.php"><i class="fas fa-folder-open" style="color: var(--color-primary);"></i> Meus Cardápios</a></li>
                            </ul>
                        </details>
                        <li><a href="fichastecnicas.php" class="active"><i class="fas fa-file-invoice" style="color: var(--color-primary);"></i> Fichas Técnicas</a></li>
                        <li><a href="custos.php"><i class="fas fa-dollar-sign" style="color: var(--color-primary);"></i> Análise de Custos</a></li>
                        <li><a href="checklists.php"><i class="fas fa-check-square" style="color: var(--color-primary);"></i> Checklists</a></li>
                        <li><a href="remanejamentos.php"><i class="fas fa-random" style="color: var(--color-primary);"></i> Remanejamentos</a></li>
                        <li><a href="nutriespecial.php"><i class="fas fa-child" style="color: var(--color-primary);"></i> Nutrição Especial</a></li>
                        <li><a href="controles.php"><i class="fas fa-cogs" style="color: var(--color-primary);"></i> Outros Controles</a></li>
                    </ul>
                </details>

                <!-- NutriGestor Tools Section -->
                <details open class="nutrigestor-tools">
                    <summary><i class="fas fa-concierge-bell"></i> NutriGestor</summary>
                    <ul>
                        <li><a href="home.php?platform=nutrigestor-dashboard-section" data-platform-link="nutrigestor-dashboard-section"><i class="fas fa-chart-line"></i> Dashboard Gestor</a></li>
                        <li><a href="nutrigestor-cardapios.php"><i class="fas fa-clipboard-list"></i> Gerenciar Cardápios</a></li>
                        <li><a href="nutrigestor-fichastecnicas.php"><i class="fas fa-file-invoice"></i> Fichas Técnicas</a></li>
                        <li><a href="nutrigestor-custos.php"><i class="fas fa-dollar-sign"></i> Cálculo de Custos</a></li>
                        <li><a href="nutrigestor-pedidos.php"><i class="fas fa-shopping-basket"></i> Controle de Pedidos</a></li>
                        <li><a href="nutrigestor-cmv.php"><i class="fas fa-calculator"></i> CMV e Margem</a></li>
                    </ul>
                </details>

                <!-- NutriDEV Tools Section -->
                <details open class="nutridev-tools">
                    <summary><i class="fas fa-laptop-code"></i> NutriDEV (em breve)</summary>
                    <ul>
                        <li><a href="home.php?platform=nutridev-dashboard-section" data-platform-link="nutridev-dashboard-section"><i class="fas fa-terminal"></i> Autonomia Digital</a></li>
                        <li><a href="nutridev-templates.php"><i class="fas fa-layer-group"></i> Modelos Personalizáveis</a></li>
                    </ul>
                </details>

                <!-- General Help/Support -->
                <a href="ajuda.php" class="sidebar-top-link"><i class="fas fa-question-circle"></i> Ajuda e Suporte</a>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="content-area">
            <div class="container">
                <h1 class="fichas-tecnicas-page-title"><?php echo htmlspecialchars($page_title); ?></h1>

                <?php if ($db_connection_error || !$dados_base_ok || $erro_carregamento_dados): ?>
                    <div class="error-container">
                        <p><strong>Atenção:</strong> <?php echo htmlspecialchars($erro_carregamento_dados ?: "Não foi possível carregar todos os dados essenciais. Algumas funcionalidades podem estar indisponíveis."); ?></p>
                    </div>
                <?php endif; ?>

                <div id="global-message-container" style="margin-bottom:15px;"></div>

                <div class="fichas-tecnicas-layout">
                    <aside class="lista-fichas-container">
                        <div class="section-header">
                            <h2>Minhas Fichas</h2>
                            <button id="new-ficha-btn-list-header"><i class="fas fa-plus"></i> Nova Ficha</button>
                        </div>
                        <ul id="lista-fichas-tecnicas-ul">
                            <li id="no-fichas-msg-li">Carregando fichas...</li>
                        </ul>
                    </aside>

                    <main class="editor-ficha-container">
                        <form id="ficha-tecnica-editor-form" onsubmit="return false;">
                            <h2 id="editor-form-title-h2">Nova Ficha Técnica</h2>
                            <input type="hidden" id="ficha-tecnica-id" name="ficha_tecnica_id">

                            <h3 class="form-section-title">Dados Gerais da Preparação</h3>
                            <div class="form-general-data-layout">
                                <div class="form-group full-width-name">
                                    <label for="ficha-nome">Nome da Preparação:</label>
                                    <input type="text" id="ficha-nome" name="nome" class="auth-input" required maxlength="255">
                                </div>
                                <div class="form-group">
                                    <label for="ficha-porcao-padrao">Porção Padrão (g):</label>
                                    <div class="input-addon-wrapper">
                                        <input type="number" id="ficha-porcao-padrao" name="porcao_padrao_g" class="auth-input" min="1" value="100">
                                        <span class="input-addon">g</span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="ficha-rendimento-porcoes">Rendimento (Nº Porções):</label>
                                    <input type="number" id="ficha-rendimento-porcoes" name="rendimento_porcoes" class="auth-input" min="0" placeholder="Opcional">
                                </div>
                                <div class="form-group">
                                    <label for="ficha-rendimento-peso-total">Peso Preparo Final (g):</label>
                                     <div class="input-addon-wrapper">
                                        <input type="number" id="ficha-rendimento-peso-total" name="rendimento_peso_total_g" class="auth-input" min="0" title="Peso total após o preparo. Se vazio, usará o calculado.">
                                        <span class="input-addon">g</span>
                                    </div>
                                    <small style="font-size:0.75em; color:var(--color-text-light); margin-left: 10px;">Deixe em branco para auto-cálculo.</small>
                                </div>
                            </div>

                            <h3 class="form-section-title">Ingredientes da Preparação</h3>
                            <div class="form-group form-grid-full" style="position: relative;">
                                <label for="ficha-ingredient-search-editor">Buscar Ingrediente Base para Adicionar:</label>
                                <input type="text" id="ficha-ingredient-search-editor" class="auth-input" placeholder="Digite para buscar alimento base...">
                                <ul id="ficha-search-results-editor"></ul>
                            </div>
                            <ul id="ficha-ingredients-list-editor">
                                <li class="placeholder-ingredient-editor">
                                    - Nenhum ingrediente adicionado -
                                </li>
                            </ul>

                            <h3 class="form-section-title">Instruções e Observações</h3>
                            <div class="form-group form-grid-full">
                                <label for="ficha-modo-preparo">Modo de Preparo:</label>
                                <textarea id="ficha-modo-preparo" name="modo_preparo" class="auth-input" rows="4"></textarea>
                            </div>
                            <div class="form-group form-grid-full">
                                <label for="ficha-observacoes">Observações/Dicas:</label>
                                <textarea id="ficha-observacoes" name="observacoes" class="auth-input" rows="3"></textarea>
                            </div>

                            <h3 class="form-section-title">Valor Nutricional</h3>
                            <div class="nutritional-data-container-wrapper">
                                <!-- Tabela de 100g da Preparação -->
                                <div class="nutritional-data-block">
                                    <h4>Por 100g de Preparação</h4>
                                    <div class="nutritional-data-layout">
                                        <div class="form-group">
                                            <label for="nutri-energia">Energia (kcal):</label>
                                            <span id="nutri-energia" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-carboidratos">Carboidratos (g):</label>
                                            <span id="nutri-carboidratos" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-proteinas">Proteínas (g):</label>
                                            <span id="nutri-proteinas" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-gorduras">Gorduras Totais (g):</label>
                                            <span id="nutri-gorduras" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-colesterol">Colesterol (mg):</label>
                                            <span id="nutri-colesterol" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-fibras">Fibras (g):</label>
                                            <span id="nutri-fibras" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-vitamina-a">Vit. A (mcg):</label>
                                            <span id="nutri-vitamina-a" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-vitamina-c">Vit. C (mg):</label>
                                            <span id="nutri-vitamina-c" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-calcio">Cálcio (mg):</label>
                                            <span id="nutri-calcio" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-ferro">Ferro (mg):</label>
                                            <span id="nutri-ferro" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-sodio">Sódio (mg):</label>
                                            <span id="nutri-sodio" class="nutri-display">0,00</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tabela de Porção Customizada -->
                                <div class="nutritional-data-block">
                                    <h4>Por Porção Customizada</h4>
                                    <div class="custom-portion-input-group">
                                        <label for="nutri-porcao-custom-input">Quantidade (g):</label>
                                        <input type="number" id="nutri-porcao-custom-input" class="auth-input" value="100" min="1" step="any">
                                        <span>g</span>
                                    </div>
                                    <div class="nutritional-data-layout">
                                        <div class="form-group">
                                            <label for="nutri-energia-custom">Energia (kcal):</label>
                                            <span id="nutri-energia-custom" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-carboidratos-custom">Carboidratos (g):</label>
                                            <span id="nutri-carboidratos-custom" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-proteinas-custom">Proteínas (g):</label>
                                            <span id="nutri-proteinas-custom" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-gorduras-custom">Gorduras Totais (g):</label>
                                            <span id="nutri-gorduras-custom" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-colesterol-custom">Colesterol (mg):</label>
                                            <span id="nutri-colesterol-custom" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-fibras-custom">Fibras (g):</label>
                                            <span id="nutri-fibras-custom" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-vitamina-a-custom">Vit. A (mcg):</label>
                                            <span id="nutri-vitamina-a-custom" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-vitamina-c-custom">Vit. C (mg):</label>
                                            <span id="nutri-vitamina-c-custom" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-calcio-custom">Cálcio (mg):</label>
                                            <span id="nutri-calcio-custom" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-ferro-custom">Ferro (mg):</label>
                                            <span id="nutri-ferro-custom" class="nutri-display">0,00</span>
                                        </div>
                                        <div class="form-group">
                                            <label for="nutri-sodio-custom">Sódio (mg):</label>
                                            <span id="nutri-sodio-custom" class="nutri-display">0,00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions-footer">
                                <button type="button" id="cancel-edit-ficha-btn" class="form-button cancel">Nova Ficha / Cancelar Edição</button>
                                <button type="button" id="confirm-save-ficha-tecnica-btn" class="form-button save">Salvar Ficha Técnica</button>
                            </div>
                        </form>
                    </main>
                </div>
            </div>
        </main>
    </div>

    <footer class="main-footer-bottom">
        <p>© <?php echo date("Y"); ?> NutriPNAE. Todos os direitos reservados.</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script>
$(document).ready(function() {
    console.log("Fichas Técnicas JS v1.0 carregado."); // Versão atualizada do JS

    const $listaFichasUl = $('#lista-fichas-tecnicas-ul');
    const $editorForm = $('#ficha-tecnica-editor-form');
    const $editorFormTitleH2 = $('#editor-form-title-h2');
    const $newFichaBtnListHeader = $('#new-ficha-btn-list-header');
    const $globalMessageContainer = $('#global-message-container');

    const $fichaIdInput = $('#ficha-tecnica-id');
    const $fichaNomeInput = $('#ficha-nome');
    const $fichaPorcaoPadraoInput = $('#ficha-porcao-padrao');
    const $fichaRendimentoPorcoesInput = $('#ficha-rendimento-porcoes');
    const $fichaRendimentoPesoTotalInput = $('#ficha-rendimento-peso-total');
    const $fichaModoPreparoInput = $('#ficha-modo-preparo');
    const $fichaObservacoesInput = $('#ficha-observacoes'); // Input de observações
    const $fichaIngredientSearch = $('#ficha-ingredient-search-editor');
    const $fichaSearchResults = $('#ficha-search-results-editor');
    const $fichaIngredientsListUl = $('#ficha-ingredients-list-editor');

    // Elementos de exibição nutricional (100g)
    const $nutriEnergiaDisplay = $('#nutri-energia');
    const $nutriCarboidratosDisplay = $('#nutri-carboidratos');
    const $nutriProteinasDisplay = $('#nutri-proteinas');
    const $nutriGordurasDisplay = $('#nutri-gorduras');
    const $nutriColesterolDisplay = $('#nutri-colesterol');
    const $nutriFibrasDisplay = $('#nutri-fibras');
    const $nutriVitaminaADisplay = $('#nutri-vitamina-a');
    const $nutriVitaminaCDisplay = $('#nutri-vitamina-c');
    const $nutriCalcioDisplay = $('#nutri-calcio');
    const $nutriFerroDisplay = $('#nutri-ferro');
    const $nutriSodioDisplay = $('#nutri-sodio');

    // Elementos de exibição nutricional (Customizada)
    const $nutriPorcaoCustomInput = $('#nutri-porcao-custom-input');
    const $nutriEnergiaCustomDisplay = $('#nutri-energia-custom');
    const $nutriCarboidratosCustomDisplay = $('#nutri-carboidratos-custom');
    const $nutriProteinasCustomDisplay = $('#nutri-proteinas-custom');
    const $nutriGordurasCustomDisplay = $('#nutri-gorduras-custom');
    const $nutriColesterolCustomDisplay = $('#nutri-colesterol-custom');
    const $nutriFibrasCustomDisplay = $('#nutri-fibras-custom');
    const $nutriVitaminaACustomDisplay = $('#nutri-vitamina-a-custom');
    const $nutriVitaminaCCustomDisplay = $('#nutri-vitamina-c-custom');
    const $nutriCalcioCustomDisplay = $('#nutri-calcio-custom');
    const $nutriFerroCustomDisplay = $('#nutri-ferro-custom');
    const $nutriSodioCustomDisplay = $('#nutri-sodio-custom');


    let todasPreparacoesUsuarioArray = []; // Armazena todas as fichas do usuário
    let alimentosBaseParaFicha = {}; // Armazena os dados dos alimentos base
    let currentEditingFichaId = null; // ID da ficha atualmente em edição
    let calculationTimeout; // Timeout para debounce do cálculo nutricional

    // Armazena os totais nutricionais por 100g da preparação para uso no cálculo customizado
    let currentNutriTotalsPer100g = {
        energia_kcal: 0,
        carboidratos_g: 0,
        proteinas_g: 0,
        gorduras_totais_g: 0,
        colesterol_mg: 0,
        fibras_g: 0,
        vitamina_a_mcg: 0,
        vitamina_c_mg: 0,
        calcio_mg: 0,
        ferro_mg: 0,
        sodio_mg: 0,
    };

    /**
     * Exibe uma caixa de mensagem customizada (substitui alert() e confirm()).
     * @param {string} message - A mensagem a ser exibida (pode conter HTML).
     * @param {boolean} isConfirm - Se true, exibe botões "Confirmar" e "Cancelar".
     * @param {function} callback - Função a ser chamada após a interação do usuário.
     */
    function displayMessageBox(message, isConfirm = false, callback = null) {
        const $overlay = $('#custom-message-box-overlay');
        const $messageText = $('#message-box-text');
        const $closeBtn = $overlay.find('.message-box-close-btn');

        $messageText.html(message); // Permite HTML para negrito/estilo

        // Limpa listeners de eventos anteriores para evitar múltiplas vinculações
        $closeBtn.off('click');
        $overlay.find('.modal-button.cancel').remove(); // Remove o botão de cancelar antigo se existir

        if (isConfirm) {
            const $cancelBtn = $('<button class="modal-button cancel" style="margin-right: 10px;">Cancelar</button>');
            $closeBtn.text('Confirmar').css('background-color', 'var(--color-primary)').on('click', () => {
                $overlay.fadeOut(150, () => {
                    $cancelBtn.remove();
                    if (callback) callback(true);
                });
            });
            $cancelBtn.on('click', () => {
                $overlay.fadeOut(150, () => {
                    $cancelBtn.remove();
                    if (callback) callback(false);
                });
            });
            $closeBtn.before($cancelBtn); // Adiciona o botão de cancelar antes do botão de confirmar
        } else {
            $closeBtn.text('OK').css('background-color', 'var(--color-primary)').on('click', () => {
                $overlay.fadeOut(150, () => { if (callback) callback(); });
            });
        }

        $overlay.css('display', 'flex').hide().fadeIn(200);
    }


    /**
     * Exibe uma mensagem global na parte superior da página.
     * @param {string} message - A mensagem a ser exibida.
     * @param {'info'|'success'|'error'|'warning'} type - O tipo da mensagem (afeta a cor e o ícone).
     * @param {number} duration - Duração em milissegundos para a mensagem desaparecer (0 para não desaparecer automaticamente).
     */
    function showGlobalMessage(message, type = 'info', duration = 5000) {
        const alertClass = type === 'success' ? 'success-container' : (type === 'error' ? 'error-container' : (type === 'warning' ? 'error-container' : 'info-container'));
        const iconClass = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-triangle' : (type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'));

        // Remove quaisquer mensagens existentes primeiro
        $globalMessageContainer.empty();

        const $messageDiv = $(`<div class="${alertClass}" style="display:none;"><i class="fas ${iconClass}" style="margin-right:8px;"></i> ${message}</div>`);
        $globalMessageContainer.html($messageDiv);
        $messageDiv.fadeIn();

        if (duration > 0) {
            setTimeout(() => { $messageDiv.fadeOut(() => $messageDiv.remove()); }, duration);
        }
    }

    /**
     * Carrega os dados iniciais das preparações e alimentos base do PHP.
     */
    function carregarDadosIniciais() {
        console.log("Carregando dados iniciais...");
        try {
            // Assegura que os dados são arrays/objetos válidos
            let preparacoesRaw = <?php echo $preparacoes_usuario_json_para_js ?: '[]'; ?>;
            let alimentosRaw = <?php echo $alimentos_base_json_para_js ?: '{}'; ?>;

            todasPreparacoesUsuarioArray = Array.isArray(preparacoesRaw) ? preparacoesRaw : [];
            alimentosBaseParaFicha = (typeof alimentosRaw === 'object' && alimentosRaw !== null && !Array.isArray(alimentosRaw)) ? alimentosRaw : {};

            console.log("Fichas Iniciais Processadas - Preparacoes (Array):", todasPreparacoesUsuarioArray.length, "Alimentos Base (Objeto):", Object.keys(alimentosBaseParaFicha).length);

        } catch (e) {
            console.error("Erro CRÍTICO ao parsear dados JSON do PHP:", e);
            showGlobalMessage("Erro fatal ao carregar dados da página. Tente recarregar.", "error", 0);
            todasPreparacoesUsuarioArray = [];
            alimentosBaseParaFicha = {};
        }
        renderListaFichasTecnicas(); // Renderiza a lista de fichas
        resetEditorFormToNew(); // Reseta o formulário para o estado inicial
    }

    /**
     * Escapa caracteres HTML especiais.
     * @param {string} str - A string para escapar.
     * @returns {string} A string escapada.
     */
    function htmlspecialchars(str) {
        if (typeof str !== 'string') return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return str.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Sanitiza uma string para comparação (remove acentos, minúsculas, caracteres especiais).
     * @param {string} str - A string para sanitizar.
     * @returns {string} A string sanitizada.
     */
    function sanitizeString(str) {
        if (typeof str !== 'string') return '';
        return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9\s-]/g, '');
    }

    /**
     * Gera um ID único para uma nova preparação.
     * @returns {string} Um ID de preparação único.
     */
    function generatePreparacaoId() { return `prep_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`; }

    /**
     * Formata um valor numérico como moeda BRL.
     * @param {number} value - O valor numérico.
     * @returns {string} O valor formatado como moeda.
     */
    function formatCurrency(value) { return (value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }

    /**
     * Converte uma string de input (com vírgula decimal) para float.
     * @param {string|number} valueStr - O valor do input.
     * @returns {number} O valor float.
     */
    function parseFloatInput(valueStr) {
        if (typeof valueStr !== 'string') valueStr = String(valueStr);
        // Substitui ponto por nada (milhares) e vírgula por ponto (decimal)
        return parseFloat(valueStr.replace(/\./g, '').replace(',', '.')) || 0;
    }

    /**
     * Encontra uma preparação pelo seu ID no array de todas as preparações.
     * @param {string} id - O ID da preparação.
     * @returns {object|null} O objeto da preparação ou null se não encontrado.
     */
    function findPreparacaoById(id) {
        if (id === null || typeof id === 'undefined') return null;
        return todasPreparacoesUsuarioArray.find(p => String(p.id) === String(id));
    }

    /**
     * Renderiza a lista de fichas técnicas na barra lateral.
     */
    function renderListaFichasTecnicas() {
        console.log("Renderizando lista de fichas. Total de fichas no array:", todasPreparacoesUsuarioArray.length);
        $listaFichasUl.empty();
        if (todasPreparacoesUsuarioArray.length === 0) {
            $listaFichasUl.html('<li id="no-fichas-msg-li">Nenhuma ficha técnica criada ainda.</li>');
            return;
        }

        // Ordena as fichas por nome
        const sortedPreps = [...todasPreparacoesUsuarioArray].sort((a,b) => {
            const nomeA = a?.nome || ''; const nomeB = b?.nome || '';
            return nomeA.localeCompare(nomeB, 'pt-BR', {sensitivity: 'base'});
        });

        sortedPreps.forEach(prep => {
            if (!prep || typeof prep.id === 'undefined' || typeof prep.nome === 'undefined') {
                console.warn("Preparação inválida ou sem ID/Nome no array:", prep);
                return;
            }

            let ultimaModStr = 'N/A';
            if (prep.updated_at) {
                try {
                    let dateStr = String(prep.updated_at).replace(' ', 'T');
                     // Adiciona 'Z' se não houver informações de fuso horário para garantir que seja UTC
                     if (!dateStr.endsWith('Z') && !dateStr.includes('+') && !dateStr.includes('GMT') && dateStr.length > 19) {
                         dateStr = dateStr.substring(0,19);
                    }
                    if (!dateStr.endsWith('Z') && !(dateStr.includes('+') || (dateStr.includes('-') && dateStr.lastIndexOf('-') > 7) ) ){
                        dateStr += 'Z';
                    }
                    const dateObj = new Date(dateStr);
                    if (!isNaN(dateObj.getTime())) {
                        ultimaModStr = dateObj.toLocaleString('pt-BR', {day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit'});
                    } else {
                        console.warn("Data inválida para prep.updated_at:", prep.updated_at, "String processada:", dateStr);
                    }
                } catch (e) { console.error("Erro ao parsear data:", e, prep.updated_at); }
            }

            // O custo total agora é sempre calculado, não há custo_total_reais_manual para exibir aqui
            const custoFinal = parseFloat(prep.custo_total_calculado) || 0;
            const custoStr = custoFinal > 0 ? `| Custo: ${formatCurrency(custoFinal)}` : '';
            const isSelected = String(prep.id) === String(currentEditingFichaId) ? 'selected' : '';

            const htmlFicha = `
                <li class="ficha-tecnica-item-li ${isSelected}" data-prep-id="${prep.id}">
                    <div class="ficha-item-info">
                        <h3><i class="fas fa-file-invoice" style="margin-right: 8px; color:var(--color-primary);"></i>${htmlspecialchars(prep.nome)}</h3>
                        <span class="ficha-item-meta">
                            Porção: ${prep.porcao_padrao_g || 100}g ${custoStr} | Modif.: ${ultimaModStr}
                        </span>
                    </div>
                    <div class="ficha-item-actions">
                        <button class="action-button-icon-small edit-btn-small" title="Editar Ficha"><i class="fas fa-pencil-alt"></i></button>
                        <button class="action-button-icon-small duplicate-btn-small" title="Duplicar Ficha"><i class="fas fa-copy"></i></button>
                        <button class="action-button-icon-small delete-btn-small" title="Excluir Ficha"><i class="fas fa-trash"></i></button>
                    </div>
                </li>`;
            $listaFichasUl.append(htmlFicha);
        });
    }

    /**
     * Reseta o formulário do editor para o estado de "Nova Ficha Técnica".
     */
    function resetEditorFormToNew() {
        currentEditingFichaId = null;
        $editorForm[0].reset(); // Reseta todos os campos do formulário
        $editorFormTitleH2.text("Nova Ficha Técnica");
        $fichaIdInput.val('');
        $fichaPorcaoPadraoInput.val(100);
        $fichaIngredientsListUl.html('<li class="placeholder-ingredient-editor">- Nenhum ingrediente adicionado -</li>');
        $fichaSearchResults.empty().hide();
        $fichaIngredientSearch.val('');
        $editorForm.find('.auth-input.is-invalid').removeClass('is-invalid'); // Remove validação de erro
        $('.ficha-tecnica-item-li.selected').removeClass('selected'); // Desseleciona item na lista
        $fichaNomeInput.focus(); // Foca no primeiro campo
        clearNutritionalDisplay(); // Limpa os valores nutricionais exibidos
        $nutriPorcaoCustomInput.val(100); // Reseta a porção customizada para 100g
    }

    // Evento para o botão "Nova Ficha" no cabeçalho da lista
    $newFichaBtnListHeader.on('click', function() {
        resetEditorFormToNew();
    });

    // Evento para o botão "Nova Ficha / Cancelar Edição" no formulário
    $('#cancel-edit-ficha-btn').on('click', function() {
        resetEditorFormToNew();
    });

    // Evento de clique em um item da lista de fichas para carregá-lo no editor
    $listaFichasUl.on('click', '.ficha-tecnica-item-li', function(e) {
        // Ignora cliques nos botões de ação (editar, duplicar, excluir)
        if ($(e.target).closest('.action-button-icon-small').length) return;

        const prepId = $(this).data('prep-id');
        // Evita recarregar se já estiver editando a mesma ficha
        if (String(prepId) === String(currentEditingFichaId)) return;

        loadFichaIntoEditor(prepId);
    });

    // Evento de clique no botão de editar
    $listaFichasUl.on('click', '.edit-btn-small', function(e) {
        e.stopPropagation(); // Evita que o clique no botão de edição selecione a ficha
        const prepId = $(this).closest('.ficha-tecnica-item-li').data('prep-id');
        loadFichaIntoEditor(prepId);
    });

    // Evento de clique no botão de duplicar
    $listaFichasUl.on('click', '.duplicate-btn-small', function(e) {
        e.stopPropagation(); // Evita que o clique no botão de duplicação selecione a ficha
        const $listItem = $(this).closest('.ficha-tecnica-item-li');
        const originalPrepId = $listItem.data('prep-id');
        const originalPrepData = findPreparacaoById(originalPrepId);

        if (originalPrepData) {
            displayMessageBox(`Tem certeza que deseja duplicar a ficha "<b>${htmlspecialchars(originalPrepData.nome)}</b>"?`, true, (result) => {
                if (result) {
                    const newPrepData = {
                        ...originalPrepData,
                        id: generatePreparacaoId(), // Gera um novo ID para a cópia
                        nome: originalPrepData.nome + ' (Cópia)', // Adiciona "(Cópia)" ao nome
                        updated_at: new Date().toISOString(), // Atualiza o timestamp
                        // Custo e nutrição calculados serão recalculados ao carregar/salvar
                        custo_total_calculado: 0,
                        nutri_data_calculado: null
                    };

                    // Certifica-se de que a propriedade 'ingredientes' é um array para JSON.stringify
                    newPrepData.ingredientes = Array.isArray(newPrepData.ingredientes) ? newPrepData.ingredientes : [];

                    $.ajax({
                        url: 'preparacao_actions.php',
                        method: 'POST',
                        data: {
                            action: 'create_preparacao', // Ação de criação
                            preparacao_data: JSON.stringify(newPrepData)
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && Array.isArray(response.todas_preparacoes_atualizadas)) {
                                todasPreparacoesUsuarioArray = response.todas_preparacoes_atualizadas; // Atualiza dados locais
                                renderListaFichasTecnicas(); // Renderiza a lista novamente
                                showGlobalMessage(response.message || "Ficha duplicada com sucesso!", "success");
                                loadFichaIntoEditor(response.preparacao_salva_id); // Carrega a ficha duplicada no editor
                            } else {
                                showGlobalMessage('Erro ao duplicar: ' + (response.message || 'Tente novamente.'), "error");
                                console.error("Erro duplicar ficha:", response);
                            }
                        },
                        error: function(jqXHR) {
                            showGlobalMessage('Erro de comunicação ao duplicar.', "error");
                            console.error("Erro AJAX Duplicar Ficha:", jqXHR.responseText, jqXHR.status, jqXHR.statusText);
                        }
                    });
                }
            });
        } else {
            showGlobalMessage("Erro: Ficha original não encontrada para duplicação.", "error");
        }
    });

    /**
     * Carrega os dados de uma ficha técnica específica no formulário do editor.
     * @param {string} prepId - O ID da preparação a ser carregada.
     */
    function loadFichaIntoEditor(prepId) {
        const prepData = findPreparacaoById(prepId);
        if (!prepData) {
            showGlobalMessage("Erro: Preparação não encontrada para edição.", "error");
            resetEditorFormToNew();
            return;
        }

        currentEditingFichaId = String(prepId);
        $editorForm[0].reset(); // Limpa o formulário antes de preencher
        $editorFormTitleH2.text(`Editando: ${htmlspecialchars(prepData.nome)}`);
        $('.ficha-tecnica-item-li.selected').removeClass('selected'); // Remove seleção anterior
        $listaFichasUl.find(`.ficha-tecnica-item-li[data-prep-id="${prepId}"]`).addClass('selected'); // Adiciona seleção atual

        $fichaIdInput.val(prepData.id);
        $fichaNomeInput.val(prepData.nome || '');
        $fichaPorcaoPadraoInput.val(prepData.porcao_padrao_g || 100);
        $fichaRendimentoPorcoesInput.val(prepData.rendimento_porcoes || '');
        $fichaRendimentoPesoTotalInput.val(prepData.rendimento_peso_total_g || '');

        $fichaModoPreparoInput.val(prepData.modo_preparo || '');
        $fichaObservacoesInput.val(prepData.observacoes || ''); // Carrega observações

        $fichaIngredientsListUl.empty(); // Limpa a lista de ingredientes atual
        const ingredientesSource = Array.isArray(prepData.ingredientes) ? prepData.ingredientes : [];
        console.log("Ingredientes para " + prepData.nome + " (ID: "+prepId+"): ", ingredientesSource);


        if (ingredientesSource.length > 0) {
            ingredientesSource.forEach(ing => {
                if(!ing || typeof ing.foodId === 'undefined') {
                    console.warn("Ingrediente inválido encontrado na ficha " + prepId, ing);
                    return;
                }
                const foodIdStr = String(ing.foodId);
                const alimentoBase = alimentosBaseParaFicha[foodIdStr];
                const nomeDisplay = ing.nomeOriginal || (alimentoBase ? alimentoBase.nome : `Alimento ID ${foodIdStr}`);
                const pesoBruto = ing.qty || 0;
                const fcIngrediente = ing.fc || (alimentoBase ? alimentoBase.fc_padrao : 1.00);
                const custoUnitarioKg = ing.custo_unit_kg !== undefined ? ing.custo_unit_kg : (alimentoBase ? alimentoBase.custo_kg_l : 0);

                adicionarIngredienteNaListaEditor(foodIdStr, nomeDisplay, pesoBruto, fcIngrediente, custoUnitarioKg);
            });
        }
        // Adiciona placeholder se não houver ingredientes
        if ($fichaIngredientsListUl.children(':not(.placeholder-ingredient-editor)').length === 0) {
             $fichaIngredientsListUl.html('<li class="placeholder-ingredient-editor">- Nenhum ingrediente adicionado -</li>');
        }
        // Dispara o cálculo nutricional após carregar a ficha
        triggerNutritionalCalculation();
        $fichaNomeInput.focus(); // Foca no nome da ficha
    }

    // Lógica de busca de ingredientes
    $fichaIngredientSearch.on('input', function() {
        const searchTerm = sanitizeString($(this).val());
        $fichaSearchResults.empty().hide();
        if (searchTerm.length < 1) return;

        let count = 0;
        // Filtra e ordena os alimentos base para exibição
        const sortedAlimentosBase = Object.values(alimentosBaseParaFicha)
            .filter(food => food && food.id && food.nome)
            .sort((a, b) => (a.nome || '').localeCompare(b.nome || '', 'pt-BR', { sensitivity: 'base' }));

        for (const food of sortedAlimentosBase) {
            if (sanitizeString(food.nome).includes(searchTerm)) {
                $fichaSearchResults.append(`<li data-id="${food.id}" data-nome="${htmlspecialchars(food.nome)}" data-custo="${food.custo_kg_l || 0}" data-fc="${food.fc_padrao || 1.0}">${htmlspecialchars(food.nome)}</li>`);
                count++;
                if (count >= 10) break; // Limita a 10 resultados para melhor performance
            }
        }
        if (count > 0) $fichaSearchResults.show();
        else $fichaSearchResults.append(`<li class="no-results-editor">- Nenhum alimento base encontrado para "${htmlspecialchars($(this).val())}" -</li>`).show();
    });

    // Exibe resultados de busca ao focar se já houver texto
    $fichaIngredientSearch.on('focus', function() { if($(this).val().trim().length > 0) $(this).trigger('input'); });
    // Esconde resultados de busca ao clicar fora
    $(document).on('click', function(e) { if (!$fichaIngredientSearch.is(e.target) && !$fichaSearchResults.is(e.target) && $fichaSearchResults.has(e.target).length === 0) { $fichaSearchResults.hide(); } });

    // Adiciona ingrediente selecionado da busca à lista do editor
    $fichaSearchResults.on('click', 'li:not(.no-results-editor)', function() {
        const foodId = $(this).data('id').toString();
        const foodName = $(this).data('nome');
        const foodCusto = parseFloat($(this).data('custo')) || 0;
        const foodFc = parseFloat($(this).data('fc')) || 1.00;
        adicionarIngredienteNaListaEditor(foodId, foodName, 100, foodFc, foodCusto); // Adiciona com 100g e FC/Custo padrão
        $fichaIngredientSearch.val('').focus(); // Limpa e foca no campo de busca
        $fichaSearchResults.empty().hide(); // Esconde resultados
    });

    /**
     * Adiciona um ingrediente à lista do editor.
     * @param {string} id - ID do alimento.
     * @param {string} nome - Nome do alimento.
     * @param {number} qtdBruta - Quantidade bruta inicial.
     * @param {number} fc - Fator de cocção inicial.
     * @param {number} custoUnitKg - Custo unitário por kg/L inicial.
     */
    function adicionarIngredienteNaListaEditor(id, nome, qtdBruta = 100, fc = 1.00, custoUnitKg = 0) {
        $fichaIngredientsListUl.find('.placeholder-ingredient-editor').remove(); // Remove o placeholder
        if ($fichaIngredientsListUl.find(`li[data-id="${id}"]`).length > 0) {
            displayMessageBox(`O ingrediente "<b>${htmlspecialchars(nome)}</b>" já foi adicionado.`, "warning");
            $fichaIngredientsListUl.find(`li[data-id="${id}"] .ingredient-peso-bruto-input`).focus().select();
            return;
        }
        const pesoLiquidoInicial = (parseFloat(qtdBruta) / parseFloat(fc));
        const custoIngredienteInicial = (pesoLiquidoInicial / 1000) * parseFloat(custoUnitKg);

        const liHtml = `
            <li data-id="${id}" data-food-name="${htmlspecialchars(nome)}">
                <span class="ingredient-name-editor">${htmlspecialchars(nome)}</span>
                <div>
                    <label class="ing-label">Peso Bruto (g)</label>
                    <input type="number" class="auth-input ing-input ingredient-peso-bruto-input" value="${qtdBruta.toFixed(2)}" min="0.01" step="any">
                </div>
                <div>
                    <label class="ing-label">Fator Cor.</label>
                    <input type="number" class="auth-input ing-input ingredient-fc-input" value="${fc.toFixed(2)}" min="0.01" step="0.01">
                </div>
                <div>
                    <label class="ing-label">Custo Unit. (R$/kg)</label>
                    <input type="number" class="auth-input ing-input ingredient-custo-unit-input" value="${custoUnitKg.toFixed(2)}" min="0" step="0.01">
                </div>
                <div class="ing-display-group">
                    <div class="ing-display-row">
                        <label class="ing-label">Peso Líquido (g)</label>
                        <span class="ing-display ingredient-peso-liquido-display">${pesoLiquidoInicial.toFixed(2).replace('.',',')}</span>
                    </div>
                    <div class="ing-display-row">
                        <label class="ing-label">Custo Ing. (R$)</label>
                        <span class="ing-display ingredient-custo-item-display">${custoIngredienteInicial.toFixed(2).replace('.',',')}</span>
                    </div>
                </div>
                <button type="button" class="ingredient-remove-btn-editor" title="Remover ${htmlspecialchars(nome)}"><i class="fas fa-times-circle"></i></button>
            </li>`;
        $fichaIngredientsListUl.append(liHtml);
        triggerNutritionalCalculation(); // Dispara o cálculo após adicionar
    }

    // Eventos para inputs de ingredientes (peso bruto, FC, custo unitário)
    $fichaIngredientsListUl.on('input', '.ingredient-peso-bruto-input, .ingredient-fc-input, .ingredient-custo-unit-input', function() {
        const $li = $(this).closest('li');
        const pesoBruto = parseFloat($(this).val()) || 0;
        const fc = parseFloat($li.find('.ingredient-fc-input').val()) || 1.0;
        const custoUnitKg = parseFloat($li.find('.ingredient-custo-unit-input').val()) || 0;

        let pesoLiquido = (fc > 0 && !isNaN(fc)) ? (pesoBruto / fc) : 0;
        $li.find('.ingredient-peso-liquido-display').text(pesoLiquido.toFixed(2).replace('.',','));

        let custoIngrediente = (pesoLiquido / 1000) * custoUnitKg;
        $li.find('.ingredient-custo-item-display').text(custoIngrediente.toFixed(2).replace('.',','));

        triggerNutritionalCalculation(); // Dispara o cálculo
    });

    // Eventos para inputs gerais da ficha que afetam os cálculos (rendimento, porção padrão)
    $fichaRendimentoPesoTotalInput.on('input', triggerNutritionalCalculation);
    $fichaPorcaoPadraoInput.on('input', triggerNutritionalCalculation);
    $nutriPorcaoCustomInput.on('input', triggerNutritionalCalculation); // Novo: Dispara cálculo para porção customizada


    /**
     * Dispara o cálculo nutricional com um debounce para evitar cálculos excessivos.
     */
    function triggerNutritionalCalculation() {
        clearTimeout(calculationTimeout);
        calculationTimeout = setTimeout(calculateNutritionalValues, 500); // Calcula 500ms após a última alteração
    }

    /**
     * Calcula e atualiza os valores nutricionais da ficha técnica.
     */
    function calculateNutritionalValues() {
        console.log("Iniciando cálculo nutricional...");
        let totalEnergiaAcumulada = 0;
        let totalCarboidratosAcumulada = 0;
        let totalProteinasAcumulada = 0;
        let totalGordurasAcumulada = 0;
        let totalColesterolAcumulada = 0;
        let totalFibrasAcumulada = 0;
        let totalVitaminaAAcumulada = 0;
        let totalVitaminaCAcumulada = 0;
        let totalCalcioAcumulada = 0;
        let totalFerroAcumulada = 0;
        let totalSodioAcumulada = 0;
        let pesoTotalLiquidoFicha = 0; // Soma dos pesos líquidos dos ingredientes
        let totalCustoCalculado = 0; // Soma dos custos dos ingredientes

        $fichaIngredientsListUl.find('li:not(.placeholder-ingredient-editor)').each(function() {
            const foodId = $(this).data('id').toString();
            const pesoBruto = parseFloat($(this).find('.ingredient-peso-bruto-input').val()) || 0;
            const fc = parseFloat($(this).find('.ingredient-fc-input').val()) || 1.0;
            const custoUnitKg = parseFloat($(this).find('.ingredient-custo-unit-input').val()) || 0;

            const pesoLiquido = (fc > 0 && !isNaN(fc)) ? (pesoBruto / fc) : 0; // Peso líquido em gramas

            const alimentoBase = alimentosBaseParaFicha[foodId];

            if (alimentoBase && alimentoBase.nutri_data) {
                // Acumula o peso líquido total da ficha
                pesoTotalLiquidoFicha += pesoLiquido;

                // Acumula o custo total dos ingredientes
                totalCustoCalculado += (pesoLiquido / 1000) * custoUnitKg;

                // Os valores nutricionais da base de dados são por 100g.
                // Precisamos escalá-los pelo peso líquido do ingrediente (em gramas) / 100.
                const fatorEscala = pesoLiquido / 100; // Fator para converter valores por 100g para o peso líquido do ingrediente

                // Acumula os totais de nutrientes
                totalEnergiaAcumulada += (alimentoBase.nutri_data.energia_kcal || 0) * fatorEscala;
                totalCarboidratosAcumulada += (alimentoBase.nutri_data.carboidratos_g || 0) * fatorEscala;
                totalProteinasAcumulada += (alimentoBase.nutri_data.proteinas_g || 0) * fatorEscala;
                totalGordurasAcumulada += (alimentoBase.nutri_data.lipideos_g || 0) * fatorEscala;
                totalColesterolAcumulada += (alimentoBase.nutri_data.colesterol_mg || 0) * fatorEscala;
                totalFibrasAcumulada += (alimentoBase.nutri_data.fibras_g || 0) * fatorEscala;
                totalVitaminaAAcumulada += (alimentoBase.nutri_data.vitamina_a_mcg || 0) * fatorEscala;
                totalVitaminaCAcumulada += (alimentoBase.nutri_data.vitamina_c_mg || 0) * fatorEscala;
                totalCalcioAcumulada += (alimentoBase.nutri_data.calcio_mg || 0) * fatorEscala;
                totalFerroAcumulada += (alimentoBase.nutri_data.ferro_mg || 0) * fatorEscala;
                totalSodioAcumulada += (alimentoBase.nutri_data.sodio_mg || 0) * fatorEscala;

            }
        });

        // Determina o rendimento final a ser usado para normalização
        // Se o usuário inseriu um "Peso Preparo Final (g)", usa esse valor.
        // Caso contrário, usa a soma dos pesos líquidos dos ingredientes.
        const rendimentoFinalGrams = parseFloatInput($fichaRendimentoPesoTotalInput.val()) || pesoTotalLiquidoFicha;

        let fatorNormalizacao = 0;
        if (rendimentoFinalGrams > 0) {
            fatorNormalizacao = 100 / rendimentoFinalGrams; // Para obter valores por 100g da preparação final
        }

        // Atualiza os displays nutricionais por 100g da preparação final
        currentNutriTotalsPer100g = {
            energia_kcal: totalEnergiaAcumulada * fatorNormalizacao,
            carboidratos_g: totalCarboidratosAcumulada * fatorNormalizacao,
            proteinas_g: totalProteinasAcumulada * fatorNormalizacao,
            gorduras_totais_g: totalGordurasAcumulada * fatorNormalizacao,
            colesterol_mg: totalColesterolAcumulada * fatorNormalizacao,
            fibras_g: totalFibrasAcumulada * fatorNormalizacao,
            vitamina_a_mcg: totalVitaminaAAcumulada * fatorNormalizacao,
            vitamina_c_mg: totalVitaminaCAcumulada * fatorNormalizacao,
            calcio_mg: totalCalcioAcumulada * fatorNormalizacao,
            ferro_mg: totalFerroAcumulada * fatorNormalizacao,
            sodio_mg: totalSodioAcumulada * fatorNormalizacao,
        };

        $nutriEnergiaDisplay.text(currentNutriTotalsPer100g.energia_kcal.toFixed(2).replace('.', ','));
        $nutriCarboidratosDisplay.text(currentNutriTotalsPer100g.carboidratos_g.toFixed(2).replace('.', ','));
        $nutriProteinasDisplay.text(currentNutriTotalsPer100g.proteinas_g.toFixed(2).replace('.', ','));
        $nutriGordurasDisplay.text(currentNutriTotalsPer100g.gorduras_totais_g.toFixed(2).replace('.', ','));
        $nutriColesterolDisplay.text(currentNutriTotalsPer100g.colesterol_mg.toFixed(2).replace('.', ','));
        $nutriFibrasDisplay.text(currentNutriTotalsPer100g.fibras_g.toFixed(2).replace('.', ','));
        $nutriVitaminaADisplay.text(currentNutriTotalsPer100g.vitamina_a_mcg.toFixed(2).replace('.', ','));
        $nutriVitaminaCDisplay.text(currentNutriTotalsPer100g.vitamina_c_mg.toFixed(2).replace('.', ','));
        $nutriCalcioDisplay.text(currentNutriTotalsPer100g.calcio_mg.toFixed(2).replace('.', ','));
        $nutriFerroDisplay.text(currentNutriTotalsPer100g.ferro_mg.toFixed(2).replace('.', ','));
        $nutriSodioDisplay.text(currentNutriTotalsPer100g.sodio_mg.toFixed(2).replace('.', ','));

        // Agora, calcula e atualiza a tabela de porção customizada
        updateCustomNutritionalValues();
    }

    /**
     * Calcula e atualiza os valores nutricionais para a porção customizada.
     */
    function updateCustomNutritionalValues() {
        const customPortionGrams = parseFloat($nutriPorcaoCustomInput.val()) || 0;
        const factor = customPortionGrams / 100; // Fator de escala baseado na porção customizada

        $nutriEnergiaCustomDisplay.text((currentNutriTotalsPer100g.energia_kcal * factor).toFixed(2).replace('.', ','));
        $nutriCarboidratosCustomDisplay.text((currentNutriTotalsPer100g.carboidratos_g * factor).toFixed(2).replace('.', ','));
        $nutriProteinasCustomDisplay.text((currentNutriTotalsPer100g.proteinas_g * factor).toFixed(2).replace('.', ','));
        $nutriGordurasCustomDisplay.text((currentNutriTotalsPer100g.gorduras_totais_g * factor).toFixed(2).replace('.', ','));
        $nutriColesterolCustomDisplay.text((currentNutriTotalsPer100g.colesterol_mg * factor).toFixed(2).replace('.', ','));
        $nutriFibrasCustomDisplay.text((currentNutriTotalsPer100g.fibras_g * factor).toFixed(2).replace('.', ','));
        $nutriVitaminaACustomDisplay.text((currentNutriTotalsPer100g.vitamina_a_mcg * factor).toFixed(2).replace('.', ','));
        $nutriVitaminaCCustomDisplay.text((currentNutriTotalsPer100g.vitamina_c_mg * factor).toFixed(2).replace('.', ','));
        $nutriCalcioCustomDisplay.text((currentNutriTotalsPer100g.calcio_mg * factor).toFixed(2).replace('.', ','));
        $nutriFerroCustomDisplay.text((currentNutriTotalsPer100g.ferro_mg * factor).toFixed(2).replace('.', ','));
        $nutriSodioCustomDisplay.text((currentNutriTotalsPer100g.sodio_mg * factor).toFixed(2).replace('.', ','));
    }

    /**
     * Limpa todos os campos de exibição nutricional.
     */
    function clearNutritionalDisplay() {
        $nutriEnergiaDisplay.text('0,00');
        $nutriCarboidratosDisplay.text('0,00');
        $nutriProteinasDisplay.text('0,00');
        $nutriGordurasDisplay.text('0,00');
        $nutriColesterolDisplay.text('0,00');
        $nutriFibrasDisplay.text('0,00');
        $nutriVitaminaADisplay.text('0,00');
        $nutriVitaminaCDisplay.text('0,00');
        $nutriCalcioDisplay.text('0,00');
        $nutriFerroDisplay.text('0,00');
        $nutriSodioDisplay.text('0,00');

        $nutriEnergiaCustomDisplay.text('0,00');
        $nutriCarboidratosCustomDisplay.text('0,00');
        $nutriProteinasCustomDisplay.text('0,00');
        $nutriGordurasCustomDisplay.text('0,00');
        $nutriColesterolCustomDisplay.text('0,00');
        $nutriFibrasCustomDisplay.text('0,00');
        $nutriVitaminaACustomDisplay.text('0,00');
        $nutriVitaminaCCustomDisplay.text('0,00');
        $nutriCalcioCustomDisplay.text('0,00');
        $nutriFerroCustomDisplay.text('0,00');
        $nutriSodioCustomDisplay.text('0,00');

        // Reseta os totais internos também
        currentNutriTotalsPer100g = {
            energia_kcal: 0, carboidratos_g: 0, proteinas_g: 0, gorduras_totais_g: 0,
            colesterol_mg: 0, fibras_g: 0, vitamina_a_mcg: 0, vitamina_c_mg: 0,
            calcio_mg: 0, ferro_mg: 0, sodio_mg: 0,
        };
    }


    // Remove ingrediente da lista
    $fichaIngredientsListUl.on('click', '.ingredient-remove-btn-editor', function() {
        $(this).closest('li').remove();
        if ($fichaIngredientsListUl.children(':not(.placeholder-ingredient-editor)').length === 0) {
            $fichaIngredientsListUl.html('<li class="placeholder-ingredient-editor">- Nenhum ingrediente adicionado -</li>');
        }
        triggerNutritionalCalculation(); // Dispara o cálculo após remover
    });

    // Salvar/Atualizar Ficha Técnica
    $('#confirm-save-ficha-tecnica-btn').on('click', function() {
        const prepIdInput = $fichaIdInput.val();
        const prepId = prepIdInput ? prepIdInput : generatePreparacaoId(); // Gera novo ID se não for edição
        const nome = $fichaNomeInput.val().trim();

        // Validação básica do nome
        if (!nome) {
            displayMessageBox("O nome da preparação é obrigatório.", "error");
            $fichaNomeInput.addClass('is-invalid').focus();
            return;
        }
        $fichaNomeInput.removeClass('is-invalid');

        const ingredientesForm = [];
        let formIngredientesValido = true;
        $fichaIngredientsListUl.find('li:not(.placeholder-ingredient-editor)').each(function() {
            const $li = $(this);
            $li.find('input.is-invalid').removeClass('is-invalid'); // Limpa validação anterior
            const foodId = $(this).data('id').toString();
            const $qtyBrutaInput = $li.find('.ingredient-peso-bruto-input');
            const $fcIngredienteInput = $li.find('.ingredient-fc-input');
            const $custoUnitKgInput = $li.find('.ingredient-custo-unit-input');

            const qtyBruta = parseFloat($qtyBrutaInput.val());
            const fcIngrediente = parseFloat($fcIngredienteInput.val());
            const custoUnitKg = parseFloat($custoUnitKgInput.val());
            let ingredienteValido = true;

            // Validação dos campos do ingrediente
            if (isNaN(qtyBruta) || qtyBruta <= 0) { $qtyBrutaInput.addClass('is-invalid'); ingredienteValido = false; }
            if (isNaN(fcIngrediente) || fcIngrediente <= 0) { $fcIngredienteInput.addClass('is-invalid'); ingredienteValido = false; }
            if (isNaN(custoUnitKg) || custoUnitKg < 0) { $custoUnitKgInput.addClass('is-invalid'); ingredienteValido = false; }

            if (!ingredienteValido) {
                formIngredientesValido = false;
            } else {
                ingredientesForm.push({
                    foodId: foodId, qty: qtyBruta, fc: fcIngrediente, custo_unit_kg: custoUnitKg,
                    nomeOriginal: $li.data('food-name') || (alimentosBaseParaFicha[foodId] ? alimentosBaseParaFicha[foodId].nome : `Alimento ID ${foodId}`)
                });
            }
        });

        if (!formIngredientesValido) {
             showGlobalMessage("Verifique os dados dos ingredientes. Pesos e FC devem ser positivos, Custos não negativos.", "error");
             return;
        }
        if (ingredientesForm.length === 0) { showGlobalMessage("Adicione pelo menos um ingrediente.", "error"); $fichaIngredientSearch.focus(); return; }

        // Recalcula o custo total dos ingredientes para salvar
        let custoTotalCalculadoFinal = 0;
        ingredientesForm.forEach(ing => {
            const pesoLiq = (ing.qty / ing.fc);
            custoTotalCalculadoFinal += (pesoLiq / 1000) * ing.custo_unit_kg;
        });

        // Determina o rendimento final (manual tem prioridade)
        const rendimentoPesoTotalManual = parseFloatInput($fichaRendimentoPesoTotalInput.val());
        let rendimentoPesoTotalFinal;
        if(rendimentoPesoTotalManual > 0) {
            rendimentoPesoTotalFinal = rendimentoPesoTotalManual;
        } else {
             let somaPesosLiquidos = 0;
             ingredientesForm.forEach(ing => somaPesosLiquidos += (ing.qty / ing.fc));
             rendimentoPesoTotalFinal = somaPesosLiquidos;
        }

        // Captura os valores nutricionais calculados (sempre os de 100g da preparação)
        const nutriDataCalculated = {
            energia_kcal: parseFloat($nutriEnergiaDisplay.text().replace(',','.')) || 0,
            carboidratos_g: parseFloat($nutriCarboidratosDisplay.text().replace(',','.')) || 0,
            proteinas_g: parseFloat($nutriProteinasDisplay.text().replace(',','.')) || 0,
            gorduras_totais_g: parseFloat($nutriGordurasDisplay.text().replace(',','.')) || 0,
            colesterol_mg: parseFloat($nutriColesterolDisplay.text().replace(',','.')) || 0,
            fibras_g: parseFloat($nutriFibrasDisplay.text().replace(',','.')) || 0,
            vitamina_a_mcg: parseFloat($nutriVitaminaADisplay.text().replace(',','.')) || 0,
            vitamina_c_mg: parseFloat($nutriVitaminaCDisplay.text().replace(',','.')) || 0,
            calcio_mg: parseFloat($nutriCalcioDisplay.text().replace(',','.')) || 0,
            ferro_mg: parseFloat($nutriFerroDisplay.text().replace(',','.')) || 0,
            sodio_mg: parseFloat($nutriSodioDisplay.text().replace(',','.')) || 0,
        };


        // Monta o objeto de dados da ficha
        const fichaData = {
            id: prepId,
            nome: nome,
            porcao_padrao_g: parseInt($fichaPorcaoPadraoInput.val(), 10) || 100,
            rendimento_porcoes: $fichaRendimentoPorcoesInput.val() ? parseInt($fichaRendimentoPorcoesInput.val(), 10) : null,
            rendimento_peso_total_g: parseFloat(rendimentoPesoTotalFinal.toFixed(2)), // Garante que é um número
            custo_total_calculado: parseFloat(totalCustoCalculado.toFixed(2)), // Garante que é um número
            modo_preparo: $fichaModoPreparoInput.val().trim(),
            observacoes: $fichaObservacoesInput.val().trim(),
            ingredientes: ingredientesForm,
            nutri_data_calculado: nutriDataCalculated, // Adiciona os dados nutricionais calculados
            updated_at: new Date().toISOString()
        };

        console.log("Enviando Ficha Técnica para salvar:", fichaData);

        $.ajax({
            url: 'preparacao_actions.php', // Endpoint para salvar/atualizar
            method: 'POST',
            data: {
                action: prepIdInput ? 'update_preparacao' : 'create_preparacao', // Ação: update ou create
                preparacao_data: JSON.stringify(fichaData) // Envia os dados da ficha como JSON string
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && Array.isArray(response.todas_preparacoes_atualizadas)) {
                    todasPreparacoesUsuarioArray = response.todas_preparacoes_atualizadas; // Atualiza dados locais
                    renderListaFichasTecnicas(); // Renderiza a lista novamente
                    showGlobalMessage(response.message || "Ficha técnica salva com sucesso!", "success");

                    const savedId = response.preparacao_salva_id || prepId; // Usa o ID retornado se disponível
                    currentEditingFichaId = String(savedId); // Mantém a ficha selecionada no editor
                    loadFichaIntoEditor(savedId); // Recarrega a ficha salva para garantir consistência

                } else {
                    showGlobalMessage('Erro ao salvar: ' + (response.message || 'Verifique os dados.'), "error");
                    console.error("Erro salvar ficha:", response);
                }
            },
            error: function(jqXHR) {
                showGlobalMessage('Erro de comunicação com o servidor.', "error");
                console.error("Erro AJAX Ficha:", jqXHR.responseText, jqXHR.status, jqXHR.statusText);
            }
        });
    });

    // Lida com o envio do formulário com a tecla Enter
    $editorForm.on('keydown', 'input:not(#ficha-ingredient-search-editor), textarea', function(e) {
        if (e.key === 'Enter' && $(this).attr('type') !== 'submit' && !$(this).is('textarea')) {
            e.preventDefault(); // Previne o envio padrão do formulário
            // Clica no botão de salvar (ou foca nele)
            $('#confirm-save-ficha-tecnica-btn').click();
        }
    });

    // Excluir Ficha Técnica
    $listaFichasUl.on('click', '.delete-btn-small', function(e) {
        e.stopPropagation(); // Evita que o clique no botão de exclusão selecione a ficha
        const $listItem = $(this).closest('.ficha-tecnica-item-li');
        const prepId = $listItem.data('prep-id');
        const prepData = findPreparacaoById(prepId);
        const prepName = prepData?.nome || 'esta ficha';

        displayMessageBox(`Tem certeza que deseja excluir a ficha técnica "<b>${htmlspecialchars(prepName)}</b>"? Esta ação não pode ser desfeita.`, true, (result) => {
            if (result) {
                $.ajax({
                    url: 'preparacao_actions.php', method: 'POST',
                    data: { action: 'delete_preparacao', preparacao_id: prepId }, dataType: 'json',
                    success: function(response) {
                        if (response.success && Array.isArray(response.todas_preparacoes_atualizadas)) {
                            todasPreparacoesUsuarioArray = response.todas_preparacoes_atualizadas; // Atualiza dados locais
                            renderListaFichasTecnicas(); // Renderiza a lista novamente
                            showGlobalMessage(response.message || "Ficha excluída com sucesso!", "success");
                            // Se a ficha excluída era a que estava sendo editada, reseta o formulário
                            if (String(currentEditingFichaId) === String(prepId)) {
                                resetEditorFormToNew();
                            }
                        } else {
                            showGlobalMessage('Erro ao excluir: ' + (response.message || 'Tente novamente.'), "error");
                        }
                    }, error: function() { showGlobalMessage('Erro de comunicação ao excluir.', "error"); }
                });
            }
        });
    });

    // Inicializa o carregamento dos dados ao carregar a página
    carregarDadosIniciais();

    // Exibe mensagem de aviso se houver problemas no carregamento inicial (do PHP)
    <?php if (!$dados_base_ok || $db_connection_error || $erro_carregamento_dados): ?>
        showGlobalMessage("Atenção: Alguns dados essenciais não puderam ser carregados. A funcionalidade da página pode estar comprometida.", "warning", 0);
    <?php endif; ?>


    /* --- Funcionalidade de alternância da Sidebar (para mobile) --- */
    const $sidebarToggleButton = $('#sidebar-toggle-button');
    const $sidebarNav = $('#sidebar-nav');
    // const $platformSections = $('.platform-section-wrapper'); // Não diretamente usado aqui, mas mantido para consistência

    $sidebarToggleButton.on('click', function() {
        $sidebarNav.toggleClass('active');
        // Muda o ícone do botão com base no estado
        if ($sidebarNav.hasClass('active')) {
            $(this).html('<i class="fas fa-times"></i> Fechar');
        } else {
            $(this).html('<i class="fas fa-bars"></i> Menu');
        }
    });

    // Função para exibir a seção do dashboard correta (adaptada para página geral)
    function showPlatformDashboard(platformSectionId) {
        // Esta função é principalmente para as seções do dashboard da página inicial.
        // Para outras páginas, simplesmente garantimos que a barra lateral esteja ativa, se necessário.
        // O link ativo na barra lateral lida com a exibição da página atual.

        // Se você quiser destacar uma seção específica na barra lateral, faria:
        $('.sidebar-nav a').removeClass('active'); // Remove ativo de todos os links
        $('.sidebar-nav details summary').removeClass('active'); // Remove ativo de todos os resumos

        // Encontra o link correspondente na barra lateral e o ativa
        const $targetLink = $('[data-platform-link="' + platformSectionId + '"]');
        $targetLink.addClass('active');

        // Garante que os detalhes pai estejam abertos se for um sub-link
        $targetLink.parents('details').prop('open', true).find('summary').addClass('active');

        // Para esta página (fichastecnicas.php), apenas garantimos que seu próprio link esteja ativo
        $('a[href="fichastecnicas.php"]').addClass('active');
        // Também garante que seu pai esteja aberto se estiver aninhado
        $('a[href="fichastecnicas.php"]').parents('details').prop('open', true).find('summary').addClass('active');
    }

    // Listeners de eventos para seleção de plataforma na barra lateral (ajustados para navegação direta)
    $(document).on('click', '[data-platform-link]', function(e) {
        e.preventDefault(); // Previne o comportamento padrão do link
        const platformSectionId = $(this).data('platform-link');
        // Para páginas gerais como esta, um clique em um link da barra lateral deve apenas navegar
        // Se for um link de dashboard, ele vai para home.php com um parâmetro
        if (platformSectionId && platformSectionId.includes('dashboard-section')) {
            window.location.href = 'home.php?platform=' + platformSectionId;
        } else {
            // Para outros links de página diretos, navegue normalmente
            window.location.href = $(this).attr('href');
        }
    });

    // Listeners de eventos para seleção de plataforma na barra de navegação (ajustados para navegação direta)
    $(document).on('click', '.navbar-brand', function(e) {
        e.preventDefault(); // Previne o comportamento padrão do link
        const platformSectionId = $(this).data('platform-id');
        window.location.href = 'home.php?platform=' + platformSectionId;
    });

    // Carregamento inicial: garante que o link correto da barra lateral para esta página esteja ativo
    $('a[href="fichastecnicas.php"]').addClass('active');
    $('a[href="fichastecnicas.php"]').parents('details').prop('open', true).find('summary').addClass('active');

    // Oculta o botão de alternância da barra lateral em telas maiores se ele foi aberto no celular
    function checkSidebarToggleVisibility() {
        if (window.innerWidth <= 768) {
            $sidebarToggleButton.show();
        } else {
            $sidebarToggleButton.hide();
            $sidebarNav.removeClass('active'); // Garante que o menu esteja aberto em telas maiores
            $sidebarToggleButton.html('<i class="fas fa-bars"></i> Menu'); // Redefine o texto do botão
        }
    }

    // Verificação inicial e ao redimensionar
    checkSidebarToggleVisibility();
    $(window).on('resize', checkSidebarToggleVisibility);

});
    </script>
</body>
</html>
