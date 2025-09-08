<?php
// cardapio_auto/meuscardapios.php - Página de Visualização e Gestão de Cardápios

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
ini_set('display_errors', 0); // Para produção/final
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_log("--- Início meuscardapios.php --- SESSION_ID: " . session_id());

// 3. Verificação de Autenticação
$is_logged_in = isset($_SESSION['user_id']);
$logged_user_id = $_SESSION['user_id'] ?? null;
$logged_username = $_SESSION['username'] ?? 'Visitante'; // Fallback

if (!$is_logged_in || !$logged_user_id) { // Checagem mais robusta
    error_log("meuscardapios.php: Acesso não autenticado ou user_id ausente. Redirecionando para login. Session ID: " . session_id());
    header('Location: login.php');
    exit;
}
error_log("meuscardapios.php: Usuário autenticado. UserID: $logged_user_id, Username: $logged_username.");

// 4. Variáveis Iniciais e Conexão com BD
$page_title = "Meus Cardápios - NutriPNAE";
$pdo = null;
$projetos = []; // Cardápios do usuário
$erro_busca_projetos = null;

try {
    require_once 'includes/db_connect.php';
    if (!isset($pdo)) {
        throw new \RuntimeException("Objeto PDO não foi definido por db_connect.php");
    }
    error_log("meuscardapios.php: Conexão com BD estabelecida.");

    // Buscar cardápios (projetos) do usuário
    // Adicionamos 'created_at' para uma possível organização por criação
    $sql_projetos = "SELECT id, nome_projeto, created_at, updated_at, dados_json FROM cardapio_projetos WHERE usuario_id = :usuario_id ORDER BY updated_at DESC";
    $stmt_projetos = $pdo->prepare($sql_projetos);
    $stmt_projetos->bindParam(':usuario_id', $logged_user_id, PDO::PARAM_INT);
    $stmt_projetos->execute();
    $projetos = $stmt_projetos->fetchAll(PDO::FETCH_ASSOC);
    error_log("meuscardapios.php: " . count($projetos) . " cardápios (projetos) carregados para UserID $logged_user_id.");

} catch (\PDOException $e) {
    $erro_busca_projetos = "Erro crítico: Não foi possível conectar ao banco de dados ou carregar cardápios. " . $e->getMessage();
    error_log("Erro PDO em meuscardapios.php (UserID $logged_user_id): " . $e->getMessage());
} catch (\Throwable $th) {
    $erro_busca_projetos = "Erro inesperado ao carregar dados dos cardápios: " . $th->getMessage();
    error_log("Erro Throwable em meuscardapios.php: " . $th->getMessage());
}

// Processar cardápios para agrupar por Mês/Ano ou Categoria (se houver)
$grouped_projetos = [];
foreach ($projetos as $projeto) {
    $date_obj = new DateTime($projeto['updated_at']);
    $group_key = $date_obj->format('Y-m'); // Agrupar por Ano-Mês
    $group_label = $date_obj->format('F Y'); // Formato "Mês Ano" para exibição

    // Traduzir o mês para português
    $meses = [
        'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março', 'April' => 'Abril',
        'May' => 'Maio', 'June' => 'Junho', 'July' => 'Julho', 'August' => 'Agosto',
        'September' => 'Setembro', 'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro'
    ];
    $group_label = strtr($group_label, $meses);
    
    // Podemos também tentar extrair a faixa etária do JSON para exibir.
    $faixa_etaria_display = 'N/A';
    if (!empty($projeto['dados_json'])) {
        $decoded_json = json_decode($projeto['dados_json'], true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded_json['faixa_etaria_selecionada'])) {
            // Placeholder para um mapeamento real de faixa etária para nome amigável
            // No caso real, você precisaria ter o $todos_pnae_ref disponível aqui ou buscá-lo.
            $pnae_faixas_mock = [
                'bercario' => 'Berçário',
                'creche' => 'Creche',
                'pre_escola' => 'Pré-escola',
                'fund_6_10' => 'Fundamental (6-10 anos)',
                'fund_11_15' => 'Fundamental (11-15 anos)',
                'medio' => 'Ensino Médio',
                'eja_19_30' => 'EJA (19-30 anos)',
                'eja_31_60' => 'EJA (31-60 anos)',
            ];
            $faixa_etaria_key = $decoded_json['faixa_etaria_selecionada'];
            $faixa_etaria_display = $pnae_faixas_mock[$faixa_etaria_key] ?? htmlspecialchars($faixa_etaria_key);
        }
    }
    
    $projeto['faixa_etaria_display'] = $faixa_etaria_display;

    if (!isset($grouped_projetos[$group_key])) {
        $grouped_projetos[$group_key] = [
            'label' => $group_label,
            'cardapios' => []
        ];
    }
    $grouped_projetos[$group_key]['cardapios'][] = $projeto;
}

// Sort groups by key (date) in descending order
krsort($grouped_projetos);

// Remove dados_json from each project to avoid sending large data to JS unnecessarily
foreach ($grouped_projetos as &$group) {
    foreach ($group['cardapios'] as &$cardapio) {
        unset($cardapio['dados_json']);
    }
}
unset($group, $cardapio); // Unset references

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

        /* Keyframes para animações */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scaleUp { from { transform: scale(0.97); opacity: 0.8; } to { transform: scale(1); opacity: 1; } }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
        @keyframes fadeInModal { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scaleInModal { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        @keyframes slideInModal { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }


        /* Reset e Base */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; min-height: 100vh; }
        body {
            font-family: var(--font-secondary);
            line-height: 1.6;
            color: var(--color-text-dark);
            background: linear-gradient(180deg, #FFFFFF 0%, #F5F5F5 100%);
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

        /* Navbar Superior */
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
            max-width: 1800px;
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
            font-size: 1.1em;
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
            background-color: var(--color-accent);
            color: var(--color-text-dark);
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
            padding-top: 0;
            position: relative; /* Para posicionar o botão de toggle */
        }

        /* Sidebar styles */
        .sidebar {
            width: var(--sidebar-width, 280px); /* Usar variável CSS para largura */
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
            flex-shrink: 0;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width, 60px);
            overflow-x: hidden;
        }

        /* Sidebar Toggle Button */
        .sidebar-toggle-container {
            position: absolute;
            top: 50%;
            left: var(--sidebar-width, 280px); /* Posição inicial para o botão */
            transform: translateY(-50%);
            z-index: 1001; /* Acima do sidebar */
            transition: left 0.3s ease;
        }

        .sidebar.collapsed .sidebar-toggle-container {
            left: var(--sidebar-collapsed-width, 60px); /* Move o botão com o sidebar recolhido */
        }

        .sidebar-toggle-button {
            background-color: var(--color-primary);
            color: var(--color-text-on-dark);
            border: none;
            padding: 8px 10px;
            border-radius: 0 8px 8px 0; /* Apenas o lado direito arredondado */
            cursor: pointer;
            font-size: 1em;
            display: flex;
            align-items: center;
            box-shadow: 2px 0 8px rgba(0,0,0,0.1);
            transition: background-color 0.2s ease, transform 0.2s ease;
        }
        .sidebar-toggle-button:hover {
            background-color: var(--color-primary-dark);
            transform: translateX(2px);
        }
        .sidebar-toggle-button i {
            margin-right: 5px;
            transition: transform 0.3s ease; /* Animação da seta */
        }
        .sidebar.collapsed .sidebar-toggle-button i {
            transform: rotate(180deg); /* Vira a seta para a direita quando recolhido */
        }
        /* Esconde o texto no botão quando recolhido */
        .sidebar.collapsed .sidebar-toggle-button span {
            display: none;
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
            padding: 12px 20px;
            color: var(--color-text-dark);
            text-decoration: none;
            font-size: 1.05em;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease, border-left-color 0.2s ease;
            border-left: 4px solid transparent;
            margin-bottom: 5px;
            white-space: nowrap; /* Impede quebras de linha no modo expandido */
        }
        .sidebar-nav a .fas {
            margin-right: 15px;
            font-size: 1.1em;
            flex-shrink: 0; /* Impede que o ícone encolha no modo recolhido */
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
            white-space: nowrap;
        }

        .sidebar-nav details {
            margin-bottom: 5px;
        }
        .sidebar-nav details summary {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--color-text-dark);
            text-decoration: none;
            font-size: 1.05em;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease, border-left-color 0.2s ease;
            border-left: 4px solid transparent;
            list-style: none;
            white-space: nowrap;
        }
        .sidebar-nav details summary::-webkit-details-marker {
            display: none;
        }
        .sidebar-nav details summary::after {
            font-family: "Font Awesome 6 Free";
            content: "\f054"; /* chevron-right */
            font-weight: 900;
            margin-left: auto;
            font-size: 0.8em;
            color: var(--color-secondary-light);
            transition: transform 0.2s ease;
        }
        .sidebar-nav details[open] summary::after {
            transform: rotate(90deg); /* Gira para baixo quando aberto */
        }

        .sidebar-nav details summary .fas {
            margin-right: 15px;
            font-size: 1.1em;
            flex-shrink: 0;
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
            padding-left: 30px;
            padding-top: 5px;
            padding-bottom: 5px;
            background-color: #f8f8f8;
            border-left: 4px solid var(--color-light-border);
        }
        .sidebar-nav ul li a {
            display: flex;
            align-items: center;
            padding: 8px 10px;
            color: var(--color-text-light);
            font-size: 0.95em;
            transition: background-color 0.2s ease, color 0.2s ease;
            white-space: nowrap;
        }
        .sidebar-nav ul li a .fas {
            margin-right: 10px;
            font-size: 0.85em;
            flex-shrink: 0;
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

        /* Sidebar collapsed state */
        .sidebar.collapsed .sidebar-nav a,
        .sidebar.collapsed .sidebar-nav details summary {
            justify-content: center; /* Centraliza ícones */
            padding-left: 0;
            padding-right: 0;
            border-left-color: transparent; /* Remove borda na visão recolhida */
        }
        .sidebar.collapsed .sidebar-nav a .fas,
        .sidebar.collapsed .sidebar-nav details summary .fas {
            margin-right: 0; /* Remove margem do ícone */
        }
        .sidebar.collapsed .sidebar-nav a span,
        .sidebar.collapsed .sidebar-nav details summary span,
        .sidebar.collapsed .sidebar-nav .menu-section-title,
        .sidebar.collapsed .sidebar-nav details ul,
        .sidebar.collapsed .sidebar-nav details summary::after {
            display: none; /* Esconde texto, submenus e a seta de detalhes */
        }
        .sidebar.collapsed .sidebar-nav details {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .sidebar.collapsed .sidebar-nav details summary {
             padding: 12px 0; /* Ajusta padding para o ícone centralizado */
        }


        /* Content area */
        .content-area {
            flex-grow: 1;
            padding: 20px;
            background-color: transparent;
        }
        .content-area .container {
            max-width: 1800px; /* Largura máxima para o conteúdo */
            width: 100%; /* Garante que o container ocupe a largura disponível */
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Page specific styles for meuscardapios.php */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-title {
            font-family: var(--font-primary);
            color: var(--color-primary-dark);
            font-size: 2.2em;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.05);
        }
        .page-title i {
            font-size: 1.1em;
            color: var(--color-accent);
        }

        .page-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .action-button {
            padding: 8px 18px;
            background-color: var(--color-primary);
            color: var(--color-text-on-dark);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            font-family: var(--font-primary);
            transition: background-color var(--transition-speed), box-shadow var(--transition-speed), transform var(--transition-speed);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            line-height: 1.5;
            height: 38px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .action-button i { font-size: 0.95em; }
        .action-button:hover:not(:disabled) { background-color: var(--color-primary-dark); box-shadow: 0 4px 8px rgba(0, 90, 156, 0.1); transform: translateY(-1px); }
        .action-button:active:not(:disabled) { transform: translateY(0); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); }
        .action-button:disabled { background-color: #adb5bd; color: #f8f9fa; cursor: not-allowed; opacity: 0.7; box-shadow: none; transform: none;}

        .search-filter-area {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
            width: 100%;
        }
        .search-filter-area input[type="text"] {
            flex-grow: 1;
            padding: 10px 15px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            font-size: 1em;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
        }
        .search-filter-area input[type="text"]:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px var(--color-primary-xtralight);
            outline: none;
        }

        .cardapios-grid-container {
            display: flex;
            flex-direction: column;
            gap: 25px; /* Espaço entre os grupos de cardápios */
        }

        .cardapio-group-section {
            background-color: var(--color-bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid var(--color-light-border);
            padding: 25px;
        }

        .cardapio-group-section h3 {
            font-size: 1.6em;
            color: var(--color-text-dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cardapio-group-section h3 .fas {
            color: var(--color-info);
            font-size: 0.9em;
        }

        .cardapios-list {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* Grid responsivo */
            gap: 20px;
        }

        .cardapio-item {
            background-color: var(--color-bg-light);
            border: 1px solid var(--color-light-border);
            border-left: 5px solid var(--color-primary); /* Barra lateral para destaque */
            border-radius: var(--border-radius);
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out, border-color 0.2s ease;
        }
        .cardapio-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-hover);
            border-left-color: var(--color-primary-dark);
        }

        .cardapio-info h4 {
            margin: 0 0 5px 0;
            font-size: 1.15em;
            color: var(--color-text-dark);
        }
        .cardapio-info h4 a {
            color: inherit;
            text-decoration: none;
        }
        .cardapio-info h4 a:hover {
            text-decoration: underline;
        }
        .cardapio-info p {
            margin: 0;
            font-size: 0.85em;
            color: var(--color-text-light);
        }
        .cardapio-info p strong {
            color: var(--color-text-dark);
        }

        .item-actions {
            display: flex;
            gap: 8px;
            margin-top: auto; /* Empurra os botões para baixo */
            padding-top: 10px;
            border-top: 1px solid var(--color-light-border);
            justify-content: flex-end; /* Alinha à direita */
        }
        .action-button-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px;
            font-size: 0.9em;
            color: var(--color-secondary);
            transition: color var(--transition-speed), transform 0.1s ease, background-color var(--transition-speed);
            line-height: 1;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
        }
        .action-button-icon:hover { transform: scale(1.1); }
        .action-button-icon.edit-btn:hover { color: var(--color-primary-dark); background-color: var(--color-primary-xtralight); }
        .action-button-icon.duplicate-btn:hover { color: var(--color-info-dark); background-color: var(--color-info-light); }
        .action-button-icon.delete-btn:hover { color: var(--color-error-dark); background-color: var(--color-error-light); }


        .no-content-message {
            text-align: center;
            color: var(--color-text-light);
            padding: 20px;
            font-style: italic;
            background-color: var(--color-bg-white);
            border-radius: var(--border-radius);
            margin-top: 20px;
            border: 1px dashed var(--color-light-border);
        }

        .error-container {
            background-color: var(--color-bg-white);
            padding: 30px; border-radius: var(--border-radius);
            box-shadow: var(--box-shadow); text-align: center; border: 1px solid var(--color-error);
            max-width: 600px; margin: 50px auto;
        }
        .error-container h1 { color: var(--color-error); margin-bottom: 15px; font-family: var(--font-primary); font-size: 1.6em; }
        .error-container h1 i { margin-right: 10px; color: var(--color-error-dark); }
        .error-container p { color: var(--color-text-light); margin-bottom: 10px; font-size: 1em; }
        .error-container p small { font-size: 0.9em; color: var(--color-secondary); }

        /* Custom Message Box */
        .custom-message-box-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.6); justify-content: center; align-items: center;
            z-index: 2000; backdrop-filter: blur(3px); animation: fadeInModal 0.2s ease-out;
        }
        .custom-message-box-content {
            background-color: var(--color-bg-white); padding: 25px 30px; border-radius: var(--border-radius);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2); max-width: 400px; width: 90%; text-align: center;
            animation: slideInModal 0.2s ease-out forwards; border: 1px solid var(--color-light-border); position: relative;
        }
        .custom-message-box-content p { font-size: 1.1em; color: var(--color-text-dark); margin-bottom: 20px; }
        .message-box-close-btn {
            background-color: var(--color-primary); color: var(--color-text-on-dark); border: none;
            padding: 10px 25px; border-radius: 25px; cursor: pointer; font-weight: 500;
            transition: background-color 0.2s ease, transform 0.1s ease; font-family: var(--font-primary);
        }
        .message-box-close-btn:hover { background-color: var(--color-primary-dark); transform: translateY(-1px); }
        .message-box-close-btn.cancel { background-color: var(--color-secondary); }
        .message-box-close-btn.cancel:hover { background-color: #5a6268; }


        /* Modals (New/Rename Project) */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(52, 58, 64, 0.7); justify-content: center; align-items: center;
            z-index: 1050; padding: 15px; box-sizing: border-box; backdrop-filter: blur(4px);
            animation: fadeInModal 0.25s ease-out;
        }
        .modal-content {
            background-color: var(--color-bg-white); padding: 25px 30px; border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); max-width: 450px; width: 95%; max-height: 90vh;
            display: flex; flex-direction: column; animation: scaleInModal 0.25s ease-out forwards;
            border: 1px solid var(--color-light-border);
        }
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

        .main-footer-bottom {
            text-align: center;
            padding: 20px; margin-top: auto;
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
            .navbar-brand-group { order: 1; }
            .navbar-actions { order: 2; width: 100%; justify-content: center; flex-wrap: wrap; }
            .user-greeting { display: none; }

            .main-wrapper { flex-direction: column; }
            .sidebar {
                width: 100%; height: auto; position: relative; box-shadow: none; padding: 10px 0;
            }
            .sidebar-toggle-container {
                position: static; transform: none; display: flex; justify-content: center;
                width: 100%; padding: 0; margin-bottom: 10px;
            }
            .sidebar-toggle-button {
                border-radius: var(--border-radius); padding: 10px 15px; width: fit-content;
            }
            .sidebar-toggle-button span { display: inline; }
            .sidebar-toggle-button i { transform: none !important; margin-right: 8px; }
            .sidebar.collapsed { width: 100%; } /* Força a largura total em mobile mesmo se a classe collapsed estiver lá */
            .sidebar-nav { display: none; padding-top: 10px; padding-bottom: 10px; }
            .sidebar-nav.active { display: flex; flex-direction: column; }
            .sidebar-nav details summary { border-left: none; justify-content: center; }
            .sidebar-nav details ul { padding-left: 15px; }

            .content-area { padding: 15px; }
            .page-header { flex-direction: column; align-items: stretch; }
            .page-title { font-size: 1.8em; text-align: center; justify-content: center; }
            .page-actions { justify-content: center; }
            .search-filter-area { flex-direction: column; gap: 10px; }
            .search-filter-area input[type="text"] { width: 100%; }

            .cardapio-group-section { padding: 20px; }
            .cardapio-group-section h3 { font-size: 1.4em; text-align: center; flex-direction: column; gap: 8px; }
            .cardapios-list { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); }
            .cardapio-item { padding: 12px; }
            .cardapio-info h4 { font-size: 1.05em; }
        }

        @media (max-width: 768px) {
            body { font-size: 13px; }
            .navbar .container { padding: 0 15px; }
            .page-title { font-size: 1.6em; }
            .action-button { font-size: 0.8rem; padding: 6px 12px; gap: 5px; height: 35px;}
            .action-button-icon { width: 28px; height: 28px; font-size: 0.8em;}
            .cardapio-group-section h3 { font-size: 1.2em; }
            .cardapios-list { grid-template-columns: 1fr; /* Uma coluna em telas menores */ }
            .cardapio-item { border-left-width: 4px; }
        }

        @media (max-width: 480px) {
            body { font-size: 12px; }
            .navbar .container { max-width: 95%; }
            .navbar-brand-group{gap:1rem;}.navbar-brand{font-size:1.4rem;}
            .btn-header-action{font-size:0.75rem;padding:0.5rem 1rem;}
            .content-area .container { padding: 10px; margin: 5px auto; }
        }
    </style>
</head>
<body class="page-meuscardapios">
    <div id="custom-message-box-overlay" class="custom-message-box-overlay">
        <div class="custom-message-box-content">
            <p id="message-box-text"></p>
            <button class="message-box-close-btn">OK</button>
        </div>
    </div>

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
           <button type="button" id="confirm-new-project-btn" class="modal-button confirm">Criar</button>
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
           <button type="button" id="confirm-rename-project-btn" class="modal-button confirm">Salvar</button>
        </div>
      </div>
    </div>

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
                    <span class="user-greeting">Olá, <span style="font-size: 1.2em; font-weight: 700; color: var(--color-primary-dark);">
                        <?php echo htmlspecialchars($logged_username); ?></span>!</span>
                    <a href="ajuda.php" class="btn-header-action"><i class="fas fa-question-circle"></i> Ajuda</a>
                    <a href="logout.php" class="btn-header-action logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="main-wrapper">
        <aside class="sidebar" id="sidebar">
            <nav class="sidebar-nav" id="sidebar-nav">
                <a href="https://nutripnae.com" class="sidebar-top-link"><i class="fas fa-home"></i> <span>Página Principal</span></a>
                <a href="home.php" class="sidebar-top-link" data-platform-link="nutripnae-dashboard-section"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>

                <details class="nutripnae-tools" open>
                    <summary><i class="fas fa-school"></i> <span>NutriPNAE</span></summary>
                    <ul>
                        <details class="nutripnae-tools" style="margin-left: -30px;" open>
                            <summary style="border-left: none; padding-left: 30px;"><i class="fas fa-clipboard-list" style="color: var(--color-primary);"></i> <span>Gerenciar Cardápios</span></summary>
                            <ul>
                                <li><a href="index.php"><i class="fas fa-plus" style="color: var(--color-primary);"></i> <span>Novo Cardápio Semanal</span></a></li>
                                <li><a href="cardapios.php" class="active"><i class="fas fa-folder-open" style="color: var(--color-primary);"></i> <span>Meus Cardápios</span></a></li>
                            </ul>
                        </details>
                        <li><a href="fichastecnicas.php"><i class="fas fa-file-invoice" style="color: var(--color-primary);"></i> <span>Fichas Técnicas</span></a></li>
                        <li><a href="custos.php"><i class="fas fa-dollar-sign" style="color: var(--color-primary);"></i> <span>Análise de Custos</span></a></li>
                        <li><a href="checklists.php"><i class="fas fa-check-square" style="color: var(--color-primary);"></i> <span>Checklists</span></a></li>
                        <li><a href="remanejamentos.php"><i class="fas fa-random" style="color: var(--color-primary);"></i> <span>Remanejamentos</span></a></li>
                        <li><a href="nutriespecial.php"><i class="fas fa-child" style="color: var(--color-primary);"></i> <span>Nutrição Especial</span></a></li>
                        <li><a href="controles.php"><i class="fas fa-cogs" style="color: var(--color-primary);"></i> <span>Outros Controles</span></a></li>
                    </ul>
                </details>

                <details class="nutrigestor-tools">
                    <summary><i class="fas fa-concierge-bell"></i> <span>NutriGestor</span></summary>
                    <ul>
                        <li><a href="home.php?platform=nutrigestor-dashboard-section" data-platform-link="nutrigestor-dashboard-section"><i class="fas fa-chart-line"></i> <span>Dashboard Gestor</span></a></li>
                        <li><a href="nutrigestor-cardapios.php"><i class="fas fa-clipboard-list"></i> <span>Gerenciar Cardápios</span></a></li>
                        <li><a href="nutrigestor-fichastecnicas.php"><i class="fas fa-file-invoice"></i> <span>Fichas Técnicas</span></a></li>
                        <li><a href="nutrigestor-custos.php"><i class="fas fa-dollar-sign"></i> <span>Cálculo de Custos</span></a></li>
                        <li><a href="nutrigestor-pedidos.php"><i class="fas fa-shopping-basket"></i> <span>Controle de Pedidos</span></a></li>
                        <li><a href="nutrigestor-cmv.php"><i class="fas fa-calculator"></i> <span>CMV e Margem</span></a></li>
                    </ul>
                </details>

                <details class="nutridev-tools">
                    <summary><i class="fas fa-laptop-code"></i> <span>NutriDEV (em breve)</span></summary>
                    <ul>
                        <li><a href="home.php?platform=nutridev-dashboard-section" data-platform-link="nutridev-dashboard-section"><i class="fas fa-terminal"></i> <span>Autonomia Digital</span></a></li>
                        <li><a href="nutridev-templates.php"><i class="fas fa-layer-group"></i> <span>Modelos Personalizáveis</span></a></li>
                    </ul>
                </details>

                <a href="ajuda.php" class="sidebar-top-link"><i class="fas fa-question-circle"></i> <span>Ajuda e Suporte</span></a>
            </nav>
        </aside>

        <div class="sidebar-toggle-container">
            <button class="sidebar-toggle-button" id="sidebar-toggle-button" title="Minimizar/Maximizar Menu">
                <i class="fas fa-chevron-left"></i> <span>Minimizar</span>
            </button>
        </div>

        <main class="content-area">
            <div class="container">
                <?php if ($erro_busca_projetos): ?>
                    <div class="error-container" style="margin-top: 30px;">
                        <h1><i class="fas fa-exclamation-triangle"></i> Erro ao Carregar Cardápios</h1>
                        <p><?php echo htmlspecialchars($erro_busca_projetos); ?></p>
                        <p>Por favor, recarregue a página ou contate o suporte se o problema persistir.</p>
                        <p><small>(Detalhes técnicos registrados nos logs.)</small></p>
                    </div>
                <?php else: ?>
                    <div class="page-header">
                        <h1 class="page-title"><i class="fas fa-folder-open"></i> Meus Cardápios</h1>
                        <div class="page-actions">
                            <button type="button" id="new-project-btn" class="action-button"><i class="fas fa-plus-circle"></i> Novo Cardápio</button>
                            </div>
                    </div>

                    <div class="search-filter-area">
                        <input type="text" id="cardapio-search-input" placeholder="Buscar cardápio por nome...">
                    </div>

                    <div class="cardapios-grid-container" id="cardapios-list-container">
                        <?php if (empty($grouped_projetos)): ?>
                            <p id="no-cardapios-msg" class="no-content-message">Você ainda não criou nenhum cardápio. Clique em "Novo Cardápio" para começar!</p>
                        <?php else: ?>
                            <?php foreach ($grouped_projetos as $group_key => $group_data): ?>
                                <section class="cardapio-group-section" data-group-key="<?php echo htmlspecialchars($group_key); ?>">
                                    <h3><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($group_data['label']); ?></h3>
                                    <ul class="cardapios-list">
                                        <?php foreach ($group_data['cardapios'] as $projeto): ?>
                                            <li class="cardapio-item" data-project-id="<?php echo $projeto['id']; ?>" data-project-name="<?php echo htmlspecialchars($projeto['nome_projeto']); ?>">
                                                <div class="cardapio-info">
                                                    <h4>
                                                        <a href="index.php?projeto_id=<?php echo $projeto['id']; ?>" title="Abrir Cardápio: <?php echo htmlspecialchars($projeto['nome_projeto']); ?>">
                                                            <?php echo htmlspecialchars($projeto['nome_projeto']); ?>
                                                        </a>
                                                    </h4>
                                                    <p>Última Modificação: <strong><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($projeto['updated_at']))); ?></strong></p>
                                                    <p>Criado em: <strong><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($projeto['created_at']))); ?></strong></p>
                                                    <p>Faixa Etária: <strong><?php echo htmlspecialchars($projeto['faixa_etaria_display']); ?></strong></p>
                                                </div>
                                                <div class="item-actions">
                                                    <a href="index.php?projeto_id=<?php echo $projeto['id']; ?>" class="action-button-icon edit-btn" title="Abrir/Editar Cardápio"><i class="fas fa-edit"></i></a>
                                                    <button class="duplicate-project-btn action-button-icon duplicate-btn" title="Duplicar Cardápio"><i class="fas fa-copy"></i></button>
                                                    <button class="rename-project-btn action-button-icon edit-btn" title="Renomear Cardápio"><i class="fas fa-pencil-alt"></i></button>
                                                    <button class="delete-project-btn action-button-icon delete-btn" title="Excluir Cardápio"><i class="fas fa-trash-alt"></i></button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </section>
                            <?php endforeach; ?>
                            <p id="no-cardapios-msg" class="no-content-message" style="display:none;">Nenhum cardápio encontrado com os filtros aplicados.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <footer class="main-footer-bottom">
        <p>© <?php echo date("Y"); ?> NutriPNAE. Todos os direitos reservados.</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script>
    $(document).ready(function() {
        console.log("Meus Cardápios (meuscardapios.php) JS v1.0 carregado.");

        // Custom Message Box Function (replaces alert() and confirm())
        function displayMessageBox(message, isConfirm = false, callback = null) {
            const $overlay = $('#custom-message-box-overlay');
            const $messageText = $('#message-box-text');
            const $closeBtn = $overlay.find('.message-box-close-btn');

            $messageText.html(message); // Allows HTML for bold/styling

            if (isConfirm) {
                const $cancelBtn = $('<button class="modal-button cancel message-box-close-btn" style="margin-right: 10px;">Cancelar</button>');
                $closeBtn.text('Confirmar').css('background-color', 'var(--color-primary)').off('click').on('click', () => {
                    $overlay.fadeOut(150, () => {
                        $cancelBtn.remove(); // Remove cancel button when confirmed
                        if (callback) callback(true);
                    });
                });
                $cancelBtn.off('click').on('click', () => {
                    $overlay.fadeOut(150, () => {
                        $cancelBtn.remove();
                        if (callback) callback(false);
                    });
                });
                $closeBtn.before($cancelBtn); // Add cancel button before confirm
            } else {
                $closeBtn.text('OK').css('background-color', 'var(--color-primary)').off('click').on('click', () => {
                    $overlay.fadeOut(150, () => { if (callback) callback(); });
                });
            }
            $overlay.css('display', 'flex').hide().fadeIn(200);
        }

        // Helper to escape HTML characters for display
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

        // References to modals
        const $newProjectModal = $('#new-project-modal');
        const $renameProjectModal = $('#rename-project-modal');

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

        /* --- Funcionalidade de Cardápios (Projetos) --- */
        const $cardapiosListContainer = $('#cardapios-list-container');
        const $noCardapiosMsg = $('#no-cardapios-msg');
        let allProjectsData = <?php echo json_encode($projetos); ?>; // Data inicial do PHP

        // Atualiza a exibição da lista de cardápios com base em filtros/busca
        function renderFilteredCardapios(searchTerm = '') {
            const sanitizedSearchTerm = sanitizeString(searchTerm);
            let hasVisibleCardapios = false;

            // Esconder todas as seções de grupo inicialmente
            $('.cardapio-group-section').hide();
            $noCardapiosMsg.hide(); // Hide the global no-content message

            // Criar um novo objeto para agrupar projetos filtrados
            const filteredGroupedProjects = {};

            allProjectsData.forEach(projeto => {
                const projectMatchesSearch = sanitizedSearchTerm === '' ||
                                            sanitizeString(projeto.nome_projeto).includes(sanitizedSearchTerm) ||
                                            sanitizeString(projeto.faixa_etaria_display).includes(sanitizedSearchTerm);

                if (projectMatchesSearch) {
                    const date_obj = new Date(projeto.updated_at);
                    const group_key = date_obj.getFullYear() + '-' + (date_obj.getMonth() + 1).toString().padStart(2, '0');
                    const group_label_base = date_obj.toLocaleString('default', { month: 'long', year: 'numeric' });
                    const meses = {
                        'January': 'Janeiro', 'February': 'Fevereiro', 'March': 'Março', 'April': 'Abril',
                        'May': 'Maio', 'June': 'Junho', 'July': 'Julho', 'August': 'Agosto',
                        'September': 'Setembro', 'October': 'Outubro', 'November': 'Novembro', 'December': 'Dezembro'
                    };
                    const group_label = group_label_base.replace(/\w+/g, function(word) {
                        return meses[word] || word;
                    });

                    if (!filteredGroupedProjects[group_key]) {
                        filteredGroupedProjects[group_key] = {
                            label: group_label,
                            cardapios: []
                        };
                    }
                    filteredGroupedProjects[group_key].cardapios.push(projeto);
                    hasVisibleCardapios = true;
                }
            });

            // Limpa o container para re-renderizar
            $cardapiosListContainer.empty();

            if (!hasVisibleCardapios) {
                $cardapiosListContainer.html('<p id="no-cardapios-msg" class="no-content-message">Nenhum cardápio encontrado com os filtros aplicados.</p>');
                $noCardapiosMsg.show();
                return;
            }

            // Ordenar os grupos por data (chave) de forma decrescente
            const sortedGroupKeys = Object.keys(filteredGroupedProjects).sort().reverse();

            sortedGroupKeys.forEach(group_key => {
                const group_data = filteredGroupedProjects[group_key];
                const $groupSection = $(`
                    <section class="cardapio-group-section" data-group-key="${htmlspecialchars(group_key)}">
                        <h3><i class="fas fa-calendar-alt"></i> ${htmlspecialchars(group_data.label)}</h3>
                        <ul class="cardapios-list"></ul>
                    </section>
                `);
                const $ulList = $groupSection.find('.cardapios-list');

                // Ordenar os cardapios dentro de cada grupo por nome (ou updated_at)
                group_data.cardapios.sort((a, b) => a.nome_projeto.localeCompare(b.nome_projeto, 'pt-BR', { sensitivity: 'base' }));

                group_data.cardapios.forEach(projeto => {
                    const formattedUpdated = new Date(projeto.updated_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                    const formattedCreated = new Date(projeto.created_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });

                    const $cardapioItem = $(`
                        <li class="cardapio-item" data-project-id="${projeto.id}" data-project-name="${htmlspecialchars(projeto.nome_projeto)}">
                            <div class="cardapio-info">
                                <h4>
                                    <a href="index.php?projeto_id=${projeto.id}" title="Abrir Cardápio: ${htmlspecialchars(projeto.nome_projeto)}">
                                        ${htmlspecialchars(projeto.nome_projeto)}
                                    </a>
                                </h4>
                                <p>Última Modificação: <strong>${formattedUpdated}</strong></p>
                                <p>Criado em: <strong>${formattedCreated}</strong></p>
                                <p>Faixa Etária: <strong>${htmlspecialchars(projeto.faixa_etaria_display)}</strong></p>
                            </div>
                            <div class="item-actions">
                                <a href="index.php?projeto_id=${projeto.id}" class="action-button-icon edit-btn" title="Abrir/Editar Cardápio"><i class="fas fa-edit"></i></a>
                                <button class="duplicate-project-btn action-button-icon duplicate-btn" title="Duplicar Cardápio"><i class="fas fa-copy"></i></button>
                                <button class="rename-project-btn action-button-icon edit-btn" title="Renomear Cardápio"><i class="fas fa-pencil-alt"></i></button>
                                <button class="delete-project-btn action-button-icon delete-btn" title="Excluir Cardápio"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </li>
                    `);
                    $ulList.append($cardapioItem);
                });
                $cardapiosListContainer.append($groupSection);
            });
        }

        // Evento de busca/filtro
        $('#cardapio-search-input').on('keyup', function() {
            renderFilteredCardapios($(this).val());
        });

        // Open new menu modal
        $('#new-project-btn').on('click', function() {
            $('#new-project-name').val('');
            openModal($newProjectModal);
        });

        // Confirm new menu creation
        $('#confirm-new-project-btn').on('click', function() {
            const nomeProjeto = $('#new-project-name').val().trim();
            if (!nomeProjeto) {
                displayMessageBox('Por favor, digite um nome para o cardápio.');
                return;
            }
            $.ajax({
                url: 'project_actions.php',
                method: 'POST',
                data: { action: 'create', nome_projeto: nomeProjeto },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.projeto_id) {
                        displayMessageBox('Cardápio criado com sucesso! Redirecionando...', false, () => {
                            window.location.href = 'index.php?projeto_id=' + response.projeto_id;
                        });
                    } else {
                        displayMessageBox('Erro ao criar o cardápio: ' + (response.message || 'Erro desconhecido. Por favor, tente novamente.'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    displayMessageBox('Erro de comunicação ao criar o cardápio. Status: ' + textStatus + ', Erro: ' + errorThrown + '. Verifique o console para detalhes técnicos.');
                    console.error("Erro AJAX Criar Projeto:", jqXHR.responseText, textStatus, errorThrown);
                }
            });
        });
        $('#new-project-form').on('submit', function(e){ e.preventDefault(); $('#confirm-new-project-btn').click(); });

        // Open rename menu modal
        $cardapiosListContainer.on('click', '.rename-project-btn', function() {
            const item = $(this).closest('.cardapio-item');
            $('#rename-project-id').val(item.data('project-id'));
            $('#rename-project-name').val(item.data('project-name'));
            openModal($renameProjectModal);
        });

        // Confirm rename menu
        $('#confirm-rename-project-btn').on('click', function() {
            const projectId = $('#rename-project-id').val();
            const novoNome = $('#rename-project-name').val().trim();
            if (!novoNome) {
                displayMessageBox('O novo nome não pode estar vazio.');
                return;
            }
            $.ajax({
                url: 'project_actions.php',
                method: 'POST',
                data: { action: 'rename', projeto_id: projectId, novo_nome: novoNome },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Atualiza allProjectsData e re-renderiza para refletir a mudança
                        const projectIndex = allProjectsData.findIndex(p => String(p.id) === String(projectId));
                        if (projectIndex !== -1) {
                            allProjectsData[projectIndex].nome_projeto = novoNome;
                            allProjectsData[projectIndex].updated_at = response.updated_at_server || new Date().toISOString(); // Atualiza data
                        }
                        renderFilteredCardapios($('#cardapio-search-input').val()); // Re-renderiza com o nome atualizado
                        closeModal($renameProjectModal);
                        displayMessageBox('Cardápio renomeado com sucesso!');
                    } else {
                        displayMessageBox('Erro ao renomear: ' + (response.message || 'Erro desconhecido. Por favor, tente novamente.'));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    displayMessageBox('Erro de comunicação ao renomear o cardápio. Status: ' + textStatus + ', Erro: ' + errorThrown + '. Verifique o console para detalhes técnicos.');
                    console.error("Erro AJAX Renomear Projeto:", jqXHR.responseText, textStatus, errorThrown);
                }
            });
        });
        $('#rename-project-form').on('submit', function(e){ e.preventDefault(); $('#confirm-rename-project-btn').click(); });

        // Duplicate menu
        $cardapiosListContainer.on('click', '.duplicate-project-btn', function() {
            const projectItem = $(this).closest('.cardapio-item');
            const projectId = projectItem.data('project-id');
            const projectName = projectItem.data('project-name');

            displayMessageBox(`Tem certeza que deseja duplicar o cardápio "<b>${htmlspecialchars(projectName)}</b>"?`, true, (result) => {
                if (result) {
                    $.ajax({
                        url: 'project_actions.php',
                        method: 'POST',
                        data: { action: 'duplicate', projeto_id: projectId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success && response.novo_projeto) {
                                // Adiciona o novo projeto ao allProjectsData
                                // O campo created_at e updated_at no response.novo_projeto são strings de data.
                                // O valor de faixa_etaria_display precisa ser inferido ou retornado pela API.
                                // Por simplicidade, vamos usar o mock de faixa etaria ou inferir do original.
                                const originalProject = allProjectsData.find(p => String(p.id) === String(projectId));
                                const newProjectData = {
                                    id: response.novo_projeto.id,
                                    nome_projeto: response.novo_projeto.nome_projeto,
                                    created_at: response.novo_projeto.created_at,
                                    updated_at: response.novo_projeto.updated_at,
                                    faixa_etaria_display: originalProject ? originalProject.faixa_etaria_display : 'N/A' // Reutiliza do original ou define N/A
                                };
                                allProjectsData.unshift(newProjectData); // Adiciona no início para aparecer primeiro (ou use push e ordene)
                                renderFilteredCardapios($('#cardapio-search-input').val()); // Re-renderiza a lista
                                displayMessageBox(`Cardápio "<b>${htmlspecialchars(projectName)}</b>" duplicado como "<b>${htmlspecialchars(response.novo_projeto.nome_projeto)}</b>"!`);
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
        $cardapiosListContainer.on('click', '.delete-project-btn', function() {
            const projectItem = $(this).closest('.cardapio-item');
            const projectId = projectItem.data('project-id');
            const projectName = projectItem.data('project-name');

            displayMessageBox(`Tem certeza que deseja excluir o cardápio "<b>${htmlspecialchars(projectName)}</b>"? Esta ação é irreversível.`, true, (result) => {
                if (result) {
                    $.ajax({
                        url: 'project_actions.php',
                        method: 'POST',
                        data: { action: 'delete', projeto_id: projectId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Remove o projeto do allProjectsData
                                allProjectsData = allProjectsData.filter(p => String(p.id) !== String(projectId));
                                renderFilteredCardapios($('#cardapio-search-input').val()); // Re-renderiza a lista
                                displayMessageBox('Cardápio excluído com sucesso!');
                            } else {
                                displayMessageBox('Erro ao excluir: ' + (response.message || 'Erro desconhecido. Por favor, tente novamente.'));
                            }
                        },
                        error: function(jqXHR, textStatus, errorThrown) {
                            displayMessageBox('Erro de comunicação ao excluir o cardápio. Status: ' + textStatus + ', Erro: ' + errorThrown + '. Verifique o console para detalhes técnicos.');
                            console.error("Erro AJAX Excluir Projeto:", jqXHR.responseText, textStatus, errorThrown);
                        }
                    });
                }
            });
        });

        /* --- Sidebar Toggle Functionality (for mobile) --- */
        const $sidebar = $('#sidebar');
        const $sidebarToggleButton = $('#sidebar-toggle-button');

        $sidebarToggleButton.on('click', function() {
            $sidebar.toggleClass('collapsed');
            if ($sidebar.hasClass('collapsed')) {
                $(this).find('span').text('Expandir');
                $(this).find('i').removeClass('fa-chevron-left').addClass('fa-chevron-right');
            } else {
                $(this).find('span').text('Minimizar');
                $(this).find('i').removeClass('fa-chevron-right').addClass('fa-chevron-left');
            }
        });

        // Function to handle platform link navigation (from navbar and sidebar)
        function handlePlatformLink(e) {
            e.preventDefault(); // Prevent default link behavior
            const platformTarget = $(this).data('platform-id') || $(this).data('platform-link');
            // If it's a dashboard link, go to home.php with the platform parameter
            if (platformTarget && platformTarget.includes('dashboard-section')) {
                window.location.href = 'home.php?platform=' + platformTarget;
            } else if ($(this).attr('href')) {
                // For other direct page links, navigate normally
                window.location.href = $(this).attr('href');
            }
        }

        // Apply event listener to navbar brands and sidebar links
        $('.navbar-brand').on('click', handlePlatformLink);
        $('.sidebar-nav a, .sidebar-nav details summary').on('click', handlePlatformLink);

        // Initial load: ensure the correct sidebar link for this page is active
        $('a[href="cardapios.php"]').addClass('active');
        $('a[href="cardapios.php"]').parents('details').prop('open', true).find('summary').addClass('active');

        // Adjust sidebar toggle button and sidebar state on load and resize
        function checkSidebarToggleVisibility() {
            if (window.innerWidth <= 1024) { // Mobile/Tablet
                $sidebarToggleContainer.show(); // Show the button container
                $sidebar.removeClass('collapsed'); // Ensure sidebar is expanded by default on mobile
                $sidebarToggleButton.find('span').text('Minimizar'); // Ensure text is correct for expanded
                $sidebarToggleButton.find('i').removeClass('fa-chevron-right').addClass('fa-chevron-left');
                // For mobile, the entire nav might be toggled by a different button (e.g., from home.php)
                // Here, we ensure it starts open.
            } else { // Desktop
                $sidebarToggleContainer.show(); // Always show button on desktop to allow collapse
                $sidebar.removeClass('collapsed'); // Ensure sidebar is expanded by default on desktop
                $sidebarToggleButton.find('span').text('Minimizar');
                $sidebarToggleButton.find('i').removeClass('fa-chevron-right').addClass('fa-chevron-left');
            }
        }

        // Initial check and on resize
        checkSidebarToggleVisibility();
        $(window).on('resize', checkSidebarToggleVisibility);

        // Renderiza os cardápios na carga inicial
        renderFilteredCardapios('');
    });
    </script>
</body>
</html>