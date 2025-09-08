<?php
// cardapio_auto/controles.php

// 1. Configuração de Sessão
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
ini_set('display_errors', 1); // Para DEV (mude para 0 em produção)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_log("--- Início controles.php --- SESSION_ID: " . session_id());

// 3. Verificação de Autenticação
$is_logged_in = isset($_SESSION['user_id']);
$logged_user_id = $_SESSION['user_id'] ?? null;
$logged_username = $_SESSION['username'] ?? 'Visitante';

if (!$is_logged_in || !$logged_user_id) {
    error_log("controles.php: Acesso não autenticado. Redirecionando para login. Session ID: " . session_id());
    header('Location: login.php');
    exit;
}
error_log("controles.php: Usuário autenticado. UserID: $logged_user_id, Username: $logged_username.");

$page_title = "Controles e Planilhas de Acompanhamento";

// Nota: Para esta funcionalidade de "Controles", usaremos localStorage para simplicidade,
// pois o pedido não especificou persistência em banco de dados para as planilhas em si.
// Se a persistência em BD for necessária no futuro, esta lógica precisará ser adaptada.

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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" xintegrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --font-primary: 'Poppins', sans-serif; --font-secondary: 'Roboto', sans-serif; --font-size-base: 14px;
            --primary-color: #005A9C; --primary-dark: #003A6A; --primary-light: #4D94DB; --primary-xtralight: #EBF4FF; 
            --accent-color: #FFC107; --accent-dark: #E0A800;
            --secondary-color: #6c757d; --secondary-light: #adb5bd; --bg-color: #F4F7FC; --card-bg: #FFFFFF;
            --text-color: #343a40; --text-light: #6c757d; --text-on-dark: #FFFFFF; --border-color: #DEE2E6; --light-border: #E9ECEF;
            --success-color: #28a745; --success-light: #e2f4e6; --success-dark: #1e7e34;
            --warning-color: #ffc107; --warning-light: #fff8e1; --warning-dark: #d39e00;
            --error-color: #dc3545;   --error-light: #f8d7da;   --error-dark: #a71d2a;
            --info-color: #17a2b8;    --info-light: #d1ecf1;    --info-dark: #117a8b; --white-color: #FFFFFF;
            --border-radius: 8px; --box-shadow: 0 4px 12px rgba(0, 77, 148, 0.08); --box-shadow-hover: 0 6px 16px rgba(0, 77, 148, 0.12);
            --transition-speed: 0.25s;
            --bg-color-start-gradient: #E0E8F4; --bg-color-end-gradient: #F0F4F9; --bg-color-page: #F4F7FC;
            --nutrigestor-red: #D9242B; --nutrigestor-text-dark: #D9242B;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font-secondary); line-height: 1.6;
            background: linear-gradient(180deg, var(--bg-color-start-gradient) 0%, var(--bg-color-end-gradient) 40%, var(--bg-color-page) 70%, var(--bg-color-page) 100%);
            color: var(--text-color); font-size: var(--font-size-base);
            display: flex; flex-direction: column; min-height: 100vh;
        }
        a { color: var(--primary-color); text-decoration: none; transition: color var(--transition-speed); }
        a:hover { color: var(--primary-dark); }

        .site-header {
            background-color: var(--white-color); padding: 15px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 1000;
        }
        .site-header .container {
            max-width: 1600px; margin: 0 auto; padding: 0 25px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .navbar-brand-group { display: flex; align-items: center; gap: 25px; }
        .nav-logo { display: flex; align-items: center; text-decoration: none; font-family: var(--font-primary); font-weight: 700;}
        .nav-logo .logo-icon { font-size: 1.6em; margin-right: 8px; }
        .nutripnae-logo-home { font-size: 1.6em; color: var(--primary-dark); }
        .nutripnae-logo-home .nutripnae-icon-home { color: var(--accent-color); }
        .nutrigestor-logo-home { font-size: 1.5em; }
        .nutrigestor-logo-home .nutrigestor-icon-home { color: var(--nutrigestor-red); }
        .nutrigestor-text-prefix-home { color: var(--nutrigestor-red); font-weight: 700; }
        .nutrigestor-text-suffix-home { color: var(--nutrigestor-text-dark); font-weight: 700; }
        .navbar-menu-container-home { display: flex; align-items: center; gap: 20px; }
        .main-nav-home { list-style: none; display: flex; gap: 8px; margin: 0; padding: 0; }
        .main-nav-home li a {
            padding: 10px 15px; color: var(--text-light); font-family: var(--font-primary);
            font-weight: 500; font-size: 0.95em; border-radius: var(--border-radius);
            transition: background-color var(--transition-speed), color var(--transition-speed);
        }
        .main-nav-home li a:hover { background-color: var(--primary-xtralight); color: var(--primary-color); }
        .main-nav-home li a.active { background-color: var(--primary-xtralight); color: var(--primary-dark); font-weight: 600; }
        .nav-actions-home { display: flex; align-items: center; gap: 15px; }
        .user-greeting-display { font-size: 0.9em; color: var(--text-light); font-family: var(--font-secondary); }
        .btn-header-action.logout-button-home {
            padding: 8px 18px; border: 1px solid var(--primary-light); color: var(--primary-color);
            background-color: transparent; border-radius: var(--border-radius); font-family: var(--font-primary);
            font-weight: 500; font-size: 0.9em;
            transition: background-color var(--transition-speed), color var(--transition-speed), border-color var(--transition-speed);
            display: inline-flex; align-items: center;
        }
        .btn-header-action.logout-button-home:hover { background-color: var(--primary-color); color: var(--white-color); border-color: var(--primary-color); }
        @media (max-width: 1024px) {
            .site-header .container { flex-direction: column; gap: 15px; }
            .navbar-brand-group { order: 1; }
            .navbar-menu-container-home { order: 2; width: 100%; justify-content: center; flex-wrap: wrap; }
            .main-nav-home { margin-bottom: 10px; }
        }
        @media (max-width: 768px) {
            .main-nav-home { gap: 0; justify-content: center; flex-wrap: wrap; }
            .main-nav-home li a { padding: 8px 10px; font-size: 0.9em; }
            .nav-actions-home { width: 100%; justify-content: center; }
            .user-greeting-display { display: none; }
        }

        .main-content-wrapper { flex-grow: 1; padding: 25px; max-width: 1200px; margin: 0 auto; }

        .page-title {
            font-family: var(--font-primary); color: var(--primary-dark);
            font-size: 2em; font-weight: 700; margin-bottom: 25px; text-align: center;
        }

        .section-container {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid var(--light-border);
            padding: 25px;
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
        }
        .section-header h2 {
            font-family: var(--font-primary);
            color: var(--primary-dark);
            font-size: 1.5em;
            font-weight: 600;
            margin: 0;
        }
        .action-button {
            background-color: var(--success-color);
            color: var(--white-color);
            border: none;
            padding: 10px 20px;
            font-size: 0.95em;
            font-weight: 600;
            font-family: var(--font-primary);
            border-radius: 25px;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.15s ease-out, box-shadow 0.15s ease-out;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
        }
        .action-button:hover {
            background-color: var(--success-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(40, 167, 69, 0.25);
        }

        /* Lista de Planilhas */
        #control-sheets-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .control-sheet-item {
            background-color: var(--white-color);
            border: 1px solid var(--light-border);
            border-left: 4px solid var(--info-color);
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: box-shadow var(--transition-speed), border-color var(--transition-speed);
            cursor: pointer;
        }
        .control-sheet-item:hover {
            box-shadow: var(--box-shadow-hover);
            border-left-color: var(--info-dark);
        }
        .control-sheet-item.active {
            border-left-color: var(--primary-dark);
            background-color: var(--primary-xtralight);
            box-shadow: var(--box-shadow-hover);
        }
        .control-sheet-info h3 {
            margin: 0 0 5px 0;
            font-size: 1.15em;
            font-weight: 600;
            font-family: var(--font-primary);
            color: var(--primary-dark);
        }
        .control-sheet-info span {
            font-size: 0.8em;
            color: var(--text-light);
            display: block;
        }
        .item-actions {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        .action-button-icon {
            background: none; border: none; cursor: pointer;
            padding: 6px; font-size: 1.1em;
            color: var(--secondary-color);
            transition: color var(--transition-speed), transform 0.1s ease, background-color var(--transition-speed);
            line-height: 1; border-radius: 50%;
            width: 32px; height: 32px;
            display: inline-flex; justify-content: center; align-items: center;
        }
        .action-button-icon:hover { transform: scale(1.1); }
        .action-button-icon.edit-btn:hover { color: var(--primary-dark); background-color: var(--primary-xtralight); }
        .action-button-icon.delete-btn:hover { color: var(--error-dark); background-color: var(--error-light); }

        /* Modal para Nova Planilha */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(52, 58, 64, 0.7); justify-content: center; align-items: center;
            z-index: 1050; padding: 15px; box-sizing: border-box; backdrop-filter: blur(4px);
            animation: fadeInModal 0.25s ease-out;
        }
        @keyframes fadeInModal { from { opacity: 0; } to { opacity: 1; } }
        .modal-content {
            background-color: var(--card-bg); padding: 25px 30px; border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); max-width: 600px; width: 95%; max-height: 90vh;
            display: flex; flex-direction: column; animation: scaleUpModal 0.25s ease-out forwards;
            border: 1px solid var(--light-border);
        }
        @keyframes scaleUpModal { from { transform: scale(0.97); opacity: 0.8; } to { transform: scale(1); opacity: 1; } }
        .modal-header {
            border-bottom: 1px solid var(--light-border); padding-bottom: 12px; margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h2 { font-size: 1.3em; margin: 0; color: var(--primary-dark); font-weight: 600; font-family: var(--font-primary); }
        .modal-close-btn {
            background:none; border:none; font-size: 1.6rem; cursor:pointer;
            color: var(--secondary-light); padding: 0 5px; line-height: 1;
            transition: color var(--transition-speed);
        }
        .modal-close-btn:hover { color: var(--error-color); }
        .modal-body { margin-bottom: 20px; flex-grow: 1; overflow-y: auto; padding-right: 10px;}
        .modal-body label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--primary-dark); font-size: 0.9em; }
        .modal-body .auth-input {
            width: 100%; padding: 10px 12px; border: 1px solid var(--border-color);
            border-radius: var(--border-radius); font-size: 1em; box-sizing: border-box;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
        }
        .modal-body .auth-input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px var(--primary-xtralight); outline: none; }
        .modal-footer {
            border-top: 1px solid var(--light-border); padding-top: 15px; text-align: right;
            display: flex; justify-content: flex-end; gap: 10px;
        }
        .modal-button { padding: 9px 20px; font-size: 0.85em; margin-left: 0; }
        .modal-button.cancel { background-color: var(--secondary-color); color:var(--text-on-dark); }
        .modal-button.cancel:hover:not(:disabled) { background-color: #5a6268; }
        .modal-button.confirm { background-color: var(--success-color); color:var(--text-on-dark); }
        .modal-button.confirm:hover:not(:disabled) { background-color: var(--success-dark); }

        .form-group { margin-bottom: 15px; }
        .form-group.inline-radio { display: flex; flex-wrap: wrap; gap: 15px; align-items: center; }
        .form-group.inline-radio label { margin-bottom: 0; display: flex; align-items: center; gap: 5px; cursor: pointer; }
        .form-group.inline-radio input[type="radio"] { margin-right: 5px; }

        #equipment-names-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
            padding: 10px;
            border: 1px dashed var(--light-border);
            border-radius: var(--border-radius);
            background-color: #f8f9fa;
        }
        #equipment-names-container input {
            padding: 8px;
            font-size: 0.9em;
        }

        /* Visualização do Calendário */
        #calendar-view-section {
            display: none; /* Hidden by default */
            flex-direction: column;
            gap: 20px;
        }
        #calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        #calendar-header h3 {
            font-family: var(--font-primary);
            font-size: 1.8em;
            color: var(--primary-dark);
            margin: 0;
        }
        #calendar-nav-buttons button {
            background-color: var(--primary-color);
            color: var(--white-color);
            border: none;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background-color var(--transition-speed);
        }
        #calendar-nav-buttons button:hover {
            background-color: var(--primary-dark);
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr); /* 7 dias da semana */
            gap: 5px;
            background-color: var(--white-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 10px;
            overflow-x: auto; /* Para rolagem em telas pequenas */
        }
        .calendar-grid .day-name {
            background-color: var(--primary-xtralight);
            color: var(--primary-dark);
            font-weight: 600;
            text-align: center;
            padding: 8px 5px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .calendar-grid .day-cell {
            background-color: #fdfdfd;
            border: 1px solid var(--light-border);
            border-radius: 4px;
            padding: 8px;
            min-height: 120px; /* Altura mínima para cada dia */
            display: flex;
            flex-direction: column;
            gap: 5px;
            position: relative;
        }
        .calendar-grid .day-cell.empty {
            background-color: #f0f0f0;
            border: 1px dashed var(--border-color);
        }
        .calendar-grid .day-number {
            font-weight: 700;
            color: var(--text-color);
            font-size: 1.1em;
            margin-bottom: 5px;
            text-align: right;
        }
        .calendar-grid .day-inputs {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .calendar-grid .day-inputs .equipment-entry {
            background-color: var(--primary-xtralight);
            border: 1px solid var(--primary-light);
            border-radius: 5px;
            padding: 5px;
            font-size: 0.85em;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .calendar-grid .day-inputs .equipment-entry label {
            font-size: 0.8em;
            color: var(--primary-dark);
            margin-bottom: 2px;
        }
        .calendar-grid .day-inputs .equipment-entry input[type="number"],
        .calendar-grid .day-inputs .equipment-entry input[type="text"] {
            width: 100%;
            padding: 3px 5px;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            font-size: 0.8em;
        }
        .calendar-grid .day-inputs .equipment-entry input[type="checkbox"] {
            margin-right: 5px;
        }
        .calendar-grid .day-inputs .equipment-entry textarea {
            width: 100%;
            min-height: 30px;
            padding: 3px 5px;
            border: 1px solid var(--border-color);
            border-radius: 3px;
            font-size: 0.8em;
            resize: vertical;
        }
        .calendar-grid .day-cell.today {
            border: 2px solid var(--accent-color);
            box-shadow: 0 0 8px rgba(255, 193, 7, 0.5);
        }
        .calendar-grid .day-cell.completed {
            background-color: var(--success-light);
            border-color: var(--success-color);
        }
        .calendar-grid .day-cell.partially-completed {
            background-color: var(--warning-light);
            border-color: var(--warning-color);
        }
        .calendar-grid .day-cell.pending {
            background-color: var(--error-light);
            border-color: var(--error-color);
        }

        /* Resumo do Calendário */
        .calendar-summary {
            background-color: var(--primary-xtralight);
            border: 1px solid var(--primary-light);
            border-radius: var(--border-radius);
            padding: 15px;
            font-size: 0.95em;
            color: var(--primary-dark);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }
        .calendar-summary strong {
            color: var(--text-color);
        }

        .print-button-container {
            text-align: center;
            margin-top: 30px;
        }

        /* Media Queries para Responsividade */
        @media (max-width: 768px) {
            .main-content-wrapper { padding: 15px; }
            .page-title { font-size: 1.5em; margin-bottom: 15px; }
            .section-container { padding: 15px; }
            .section-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .section-header h2 { font-size: 1.2em; }
            .action-button { width: 100%; justify-content: center; }
            .control-sheet-item { flex-direction: column; align-items: flex-start; gap: 10px; padding: 10px 15px; }
            .item-actions { width: 100%; justify-content: flex-end; }
            .modal-content { padding: 15px; }
            .modal-body { padding-right: 0; } /* Remove padding-right for small screens on modal body */
            #equipment-names-container { grid-template-columns: 1fr; }
            #calendar-header { flex-direction: column; gap: 10px; }
            #calendar-header h3 { font-size: 1.2em; }
            .calendar-grid { grid-template-columns: 1fr; /* Stack days vertically */ }
            .calendar-grid .day-cell { min-height: auto; }
            .calendar-grid .day-name { display: none; } /* Hide day names in stacked view */
        }
    </style>
</head>
<body>

    <header class="site-header">
        <div class="container">
            <div class="navbar-brand-group">
                <a href="home.php" class="nav-logo nutripnae-logo-home" title="NutriPNAE Dashboard">
                    <i class="fas fa-utensils logo-icon nutripnae-icon-home"></i><span class="nutripnae-text-home">NUTRIPNAE</span>
                </a>
                <a href="landpage.php" class="nav-logo nutrigestor-logo-home" title="Conheça o NutriGestor">
                    <i class="fas fa-concierge-bell logo-icon nutrigestor-icon-home"></i><span class="nutrigestor-text-prefix-home">Nutri</span><span class="nutrigestor-text-suffix-home">Gestor</span>
                </a>
            </div>
            <div class="navbar-menu-container-home">
                <ul class="main-nav-home">
                    <li><a href="home.php">Início</a></li>
                    <li><a href="cardapios.php">Cardápios</a></li>
                    <li><a href="fichastecnicas.php">Fichas Técnicas</a></li>
                    <li><a href="custos.php">Custos</a></li>
                    <li><a href="ajuda.php">Ajuda</a></li>
                    <li><a href="checklistanvisa.php">Checklist ANVISA</a></li>
                    <li><a href="controles.php" class="active">Controles</a></li> </ul>
                <div class="nav-actions-home">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <span class="user-greeting-display">Olá, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Visitante'); ?>!</span>
                        <a href="logout.php" class="btn-header-action logout-button-home"><i class="fas fa-sign-out-alt" style="margin-right: 5px;"></i>Sair</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="main-content-wrapper">
        <h1 class="page-title"><?php echo htmlspecialchars($page_title); ?></h1>

        <section class="section-container" id="my-control-sheets">
            <div class="section-header">
                <h2>Minhas Planilhas de Acompanhamento</h2>
                <button id="new-control-sheet-btn" class="action-button"><i class="fas fa-plus"></i> Nova Planilha</button>
            </div>
            <ul id="control-sheets-list">
                <p id="no-sheets-msg" style="text-align: center; color: var(--text-light); padding: 20px;">Nenhuma planilha de acompanhamento criada ainda. Clique em "Nova Planilha" para começar!</p>
            </ul>
        </section>

        <section class="section-container" id="calendar-view-section">
            <div id="calendar-header">
                <button id="prev-period-btn" class="action-button"><i class="fas fa-chevron-left"></i> Anterior</button>
                <h3 id="current-period-display"></h3>
                <button id="next-period-btn" class="action-button">Próximo <i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="calendar-grid" id="calendar-grid">
                <div class="day-name">Dom</div>
                <div class="day-name">Seg</div>
                <div class="day-name">Ter</div>
                <div class="day-name">Qua</div>
                <div class="day-name">Qui</div>
                <div class="day-name">Sex</div>
                <div class="day-name">Sáb</div>
                </div>
            <div class="calendar-summary" id="calendar-summary">
                </div>
            <div class="print-button-container">
                <button id="print-calendar-btn" class="action-button"><i class="fas fa-print"></i> Imprimir Planilha</button>
            </div>
        </section>

    </div>

    <div id="control-sheet-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Criar Nova Planilha</h2>
                <button type="button" class="modal-close-btn" title="Fechar">×</button>
            </div>
            <div class="modal-body">
                <form id="control-sheet-form">
                    <input type="hidden" id="sheet-id">
                    <div class="form-group">
                        <label for="sheet-name">Nome do Processo/Registro:</label>
                        <input type="text" id="sheet-name" class="auth-input" required placeholder="Ex: Controle de Temperatura - Freezers">
                    </div>
                    <div class="form-group">
                        <label>Tipo de Acompanhamento:</label>
                        <div class="inline-radio">
                            <label><input type="radio" name="sheet-type" value="daily" checked> Diário</label>
                            <label><input type="radio" name="sheet-type" value="weekly"> Semanal</label>
                            <label><input type="radio" name="sheet-type" value="monthly"> Mensal</label>
                            <label><input type="radio" name="sheet-type" value="annual"> Anual</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="equipment-count">Número de Equipamentos na Cozinha:</label>
                        <input type="number" id="equipment-count" class="auth-input" min="1" value="1">
                    </div>
                    <div class="form-group">
                        <label>Nome dos Equipamentos:</label>
                        <div id="equipment-names-container">
                            <input type="text" class="equipment-name-input auth-input" placeholder="Nome do Equipamento 1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="default-responsible">Funcionário Responsável (Padrão):</label>
                        <input type="text" id="default-responsible" class="auth-input" placeholder="Ex: João Silva">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-button cancel modal-close-btn">Cancelar</button>
                <button type="button" id="save-control-sheet-btn" class="modal-button confirm">Salvar Planilha</button>
            </div>
        </div>
    </div>

    <footer class="main-footer-bottom">
        <p>© <?php echo date("Y"); ?> NutriPNAE & NutriGestor. Todos os direitos reservados.</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script>
        $(document).ready(function() {
            console.log("Controles.php JS carregado.");

            // --- Variáveis Globais e Configurações ---
            const USER_ID = '<?php echo $logged_user_id; ?>'; // Obtido do PHP
            const LOCAL_STORAGE_KEY = `user_controls_${USER_ID}`;
            let controlSheets = []; // Array para armazenar todas as planilhas do usuário
            let currentSheet = null; // A planilha atualmente selecionada/visualizada
            let currentCalendarDate = new Date(); // Data atual para navegação do calendário

            // --- Cache de Elementos DOM ---
            const $newControlSheetBtn = $('#new-control-sheet-btn');
            const $controlSheetModal = $('#control-sheet-modal');
            const $modalTitle = $('#modal-title');
            const $controlSheetForm = $('#control-sheet-form');
            const $sheetIdInput = $('#sheet-id');
            const $sheetNameInput = $('#sheet-name');
            const $equipmentCountInput = $('#equipment-count');
            const $equipmentNamesContainer = $('#equipment-names-container');
            const $defaultResponsibleInput = $('#default-responsible');
            const $saveControlSheetBtn = $('#save-control-sheet-btn');
            const $controlSheetsList = $('#control-sheets-list');
            const $noSheetsMsg = $('#no-sheets-msg');
            const $calendarViewSection = $('#calendar-view-section');
            const $currentPeriodDisplay = $('#current-period-display');
            const $calendarGrid = $('#calendar-grid');
            const $calendarSummary = $('#calendar-summary');
            const $prevPeriodBtn = $('#prev-period-btn');
            const $nextPeriodBtn = $('#next-period-btn');
            const $printCalendarBtn = $('#print-calendar-btn');

            // --- Funções Utilitárias ---
            function openModal(modaljQueryObject) {
                modaljQueryObject.css('display', 'flex').hide().fadeIn(200);
                modaljQueryObject.find('input:visible:not([type="hidden"]), textarea:visible').first().focus();
            }

            function closeModal(modaljQueryObject) {
                modaljQueryObject.fadeOut(150, function() { $(this).css('display', 'none'); });
            }

            function generateUniqueId() {
                return `sheet_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`;
            }

            function htmlspecialchars(str) {
                if (typeof str !== 'string') return '';
                const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
                return str.replace(/[&<>"']/g, function(m) { return map[m]; });
            }

            // --- Gerenciamento de Dados (localStorage) ---
            function loadControlSheets() {
                const storedSheets = localStorage.getItem(LOCAL_STORAGE_KEY);
                if (storedSheets) {
                    try {
                        controlSheets = JSON.parse(storedSheets);
                        if (!Array.isArray(controlSheets)) {
                            controlSheets = [];
                        }
                    } catch (e) {
                        console.error("Erro ao carregar planilhas do localStorage:", e);
                        controlSheets = [];
                    }
                }
                renderControlSheetsList();
            }

            function saveControlSheets() {
                localStorage.setItem(LOCAL_STORAGE_KEY, JSON.stringify(controlSheets));
                renderControlSheetsList(); // Atualiza a lista após salvar
            }

            // --- Renderização da Lista de Planilhas ---
            function renderControlSheetsList() {
                $controlSheetsList.empty();
                if (controlSheets.length === 0) {
                    $noSheetsMsg.show();
                    $calendarViewSection.hide(); // Esconde o calendário se não houver planilhas
                    return;
                }
                $noSheetsMsg.hide();

                controlSheets.forEach(sheet => {
                    const isActive = currentSheet && currentSheet.id === sheet.id ? 'active' : '';
                    const itemHtml = `
                        <li class="control-sheet-item ${isActive}" data-id="${sheet.id}">
                            <div class="control-sheet-info">
                                <h3><i class="fas fa-clipboard-list" style="margin-right: 8px; color:var(--info-color);"></i>${htmlspecialchars(sheet.name)}</h3>
                                <span>Tipo: ${getFriendlySheetType(sheet.type)} | Equipamentos: ${sheet.equipmentNames.length} | Responsável: ${htmlspecialchars(sheet.defaultResponsible || 'N/A')}</span>
                            </div>
                            <div class="item-actions">
                                <button class="edit-sheet-btn action-button-icon edit-btn" title="Editar Planilha"><i class="fas fa-pencil-alt"></i></button>
                                <button class="delete-sheet-btn action-button-icon delete-btn" title="Excluir Planilha"><i class="fas fa-trash"></i></button>
                            </div>
                        </li>
                    `;
                    $controlSheetsList.append(itemHtml);
                });
            }

            function getFriendlySheetType(type) {
                switch (type) {
                    case 'daily': return 'Diário';
                    case 'weekly': return 'Semanal';
                    case 'monthly': return 'Mensal';
                    case 'annual': return 'Anual';
                    default: return 'Desconhecido';
                }
            }

            // --- Criação/Edição de Planilha (Modal) ---
            $newControlSheetBtn.on('click', function() {
                $modalTitle.text('Criar Nova Planilha');
                $controlSheetForm[0].reset();
                $sheetIdInput.val('');
                $equipmentCountInput.val(1).trigger('input'); // Reset to 1 and trigger update
                openModal($controlSheetModal);
            });

            // Dinamicamente adicionar/remover campos de nome de equipamento
            $equipmentCountInput.on('input', function() {
                const count = parseInt($(this).val(), 10) || 1;
                $equipmentNamesContainer.empty();
                for (let i = 0; i < count; i++) {
                    const placeholder = `Nome do Equipamento ${i + 1}`;
                    $equipmentNamesContainer.append(`
                        <input type="text" class="equipment-name-input auth-input" placeholder="${placeholder}" required>
                    `);
                }
            });
            // Trigger initial update for 1 equipment
            $equipmentCountInput.trigger('input');

            $saveControlSheetBtn.on('click', function() {
                const sheetId = $sheetIdInput.val() || generateUniqueId();
                const name = $sheetNameInput.val().trim();
                const type = $('input[name="sheet-type"]:checked').val();
                const equipmentNames = [];
                $('.equipment-name-input').each(function() {
                    const eqName = $(this).val().trim();
                    if (eqName) equipmentNames.push(eqName);
                });
                const defaultResponsible = $defaultResponsibleInput.val().trim();

                if (!name) { alert('Por favor, insira um nome para o processo/registro.'); return; }
                if (equipmentNames.length === 0) { alert('Por favor, insira pelo menos um nome de equipamento.'); return; }

                const newSheet = {
                    id: sheetId,
                    name: name,
                    type: type,
                    equipmentNames: equipmentNames,
                    defaultResponsible: defaultResponsible,
                    creationDate: new Date().toISOString().split('T')[0], // YYYY-MM-DD
                    entries: {} // Para armazenar os dados do calendário
                };

                const existingIndex = controlSheets.findIndex(s => s.id === sheetId);
                if (existingIndex > -1) {
                    // Update existing sheet, preserving existing entries
                    newSheet.entries = controlSheets[existingIndex].entries; // Keep existing entries
                    controlSheets[existingIndex] = newSheet;
                } else {
                    controlSheets.push(newSheet);
                }

                saveControlSheets();
                closeModal($controlSheetModal);
                selectControlSheet(newSheet.id); // Seleciona a planilha recém-salva/editada
            });

            // Editar planilha existente
            $controlSheetsList.on('click', '.edit-sheet-btn', function(e) {
                e.stopPropagation(); // Evita que o clique no botão edite a planilha
                const sheetId = $(this).closest('.control-sheet-item').data('id');
                const sheetToEdit = controlSheets.find(s => s.id === sheetId);

                if (sheetToEdit) {
                    $modalTitle.text('Editar Planilha');
                    $sheetIdInput.val(sheetToEdit.id);
                    $sheetNameInput.val(sheetToEdit.name);
                    $(`input[name="sheet-type"][value="${sheetToEdit.type}"]`).prop('checked', true);
                    $equipmentCountInput.val(sheetToEdit.equipmentNames.length).trigger('input');
                    sheetToEdit.equipmentNames.forEach((name, index) => {
                        $equipmentNamesContainer.find(`.equipment-name-input:eq(${index})`).val(name);
                    });
                    $defaultResponsibleInput.val(sheetToEdit.defaultResponsible);
                    openModal($controlSheetModal);
                }
            });

            // Excluir planilha
            $controlSheetsList.on('click', '.delete-sheet-btn', function(e) {
                e.stopPropagation(); // Evita que o clique no botão edite a planilha
                const sheetId = $(this).closest('.control-sheet-item').data('id');
                const sheetName = $(this).closest('.control-sheet-item').find('h3').text();

                if (confirm(`Tem certeza que deseja excluir a planilha "${htmlspecialchars(sheetName)}"? Esta ação não pode ser desfeita.`)) {
                    controlSheets = controlSheets.filter(s => s.id !== sheetId);
                    saveControlSheets();
                    if (currentSheet && currentSheet.id === sheetId) {
                        currentSheet = null; // Desseleciona se a excluída era a atual
                        $calendarViewSection.hide();
                    }
                    renderControlSheetsList(); // Re-renderiza a lista
                }
            });

            // Fechar modais
            $('.modal-overlay').on('click', function(e) { if ($(e.target).is(this)) { closeModal($(this)); } });
            $('.modal-close-btn').on('click', function() { closeModal($(this).closest('.modal-overlay')); });

            // --- Seleção e Visualização da Planilha (Calendário) ---
            $controlSheetsList.on('click', '.control-sheet-item', function() {
                const sheetId = $(this).data('id');
                selectControlSheet(sheetId);
            });

            function selectControlSheet(sheetId) {
                currentSheet = controlSheets.find(s => s.id === sheetId);
                if (currentSheet) {
                    $controlSheetsList.find('.control-sheet-item').removeClass('active');
                    $controlSheetsList.find(`[data-id="${sheetId}"]`).addClass('active');
                    currentCalendarDate = new Date(); // Reset calendar to current month/year
                    renderCalendar();
                    $calendarViewSection.fadeIn();
                    $('html, body').animate({
                        scrollTop: $calendarViewSection.offset().top - 50
                    }, 500);
                } else {
                    $calendarViewSection.hide();
                }
            }

            // --- Renderização do Calendário ---
            function renderCalendar() {
                if (!currentSheet) return;

                $calendarGrid.empty();
                // Add day names header again for clarity (they are removed by .empty())
                const dayNames = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                dayNames.forEach(day => {
                    $calendarGrid.append(`<div class="day-name">${day}</div>`);
                });

                const year = currentCalendarDate.getFullYear();
                const month = currentCalendarDate.getMonth(); // 0-11
                const today = new Date();
                const todayFormatted = today.toISOString().split('T')[0];

                // Set current period display
                let periodText = '';
                if (currentSheet.type === 'daily' || currentSheet.type === 'weekly' || currentSheet.type === 'monthly') {
                    periodText = currentCalendarDate.toLocaleString('pt-BR', { month: 'long', year: 'numeric' });
                } else if (currentSheet.type === 'annual') {
                    periodText = year.toString();
                }
                $currentPeriodDisplay.text(`${htmlspecialchars(currentSheet.name)} - ${periodText.charAt(0).toUpperCase() + periodText.slice(1)}`);

                // Get first day of the month and number of days in month
                const firstDayOfMonth = new Date(year, month, 1);
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const startingDay = firstDayOfMonth.getDay(); // 0 for Sunday, 1 for Monday...

                // Add empty cells for days before the 1st
                for (let i = 0; i < startingDay; i++) {
                    $calendarGrid.append('<div class="day-cell empty"></div>');
                }

                // Render day cells
                for (let day = 1; day <= daysInMonth; day++) {
                    const date = new Date(year, month, day);
                    const formattedDate = date.toISOString().split('T')[0]; // YYYY-MM-DD
                    const isToday = formattedDate === todayFormatted ? 'today' : '';

                    let cellClass = '';
                    let allCompleted = true;
                    let anyCompleted = false;
                    let hasEntries = false;

                    const entryKey = getEntryKey(date, currentSheet.type);
                    const dayEntries = currentSheet.entries[entryKey];

                    if (dayEntries) {
                        hasEntries = true;
                        currentSheet.equipmentNames.forEach((eqName, eqIndex) => {
                            const eqData = dayEntries[`eq_${eqIndex}`];
                            if (eqData && eqData.cleaning) {
                                anyCompleted = true;
                            } else {
                                allCompleted = false;
                            }
                        });
                    } else {
                        allCompleted = false; // No entries means not all completed
                    }

                    if (hasEntries) {
                        if (allCompleted) {
                            cellClass = 'completed';
                        } else if (anyCompleted) {
                            cellClass = 'partially-completed';
                        } else {
                            cellClass = 'pending'; // Has entries but none completed
                        }
                    }

                    const dayCellHtml = `
                        <div class="day-cell ${isToday} ${cellClass}" data-date="${formattedDate}" data-entry-key="${entryKey}">
                            <div class="day-number">${day}</div>
                            <div class="day-inputs">
                                ${generateEquipmentInputs(formattedDate, entryKey)}
                            </div>
                        </div>
                    `;
                    $calendarGrid.append(dayCellHtml);
                }
                updateCalendarSummary();
            }

            // Helper to get the correct entry key based on sheet type
            function getEntryKey(date, type) {
                const year = date.getFullYear();
                const month = (date.getMonth() + 1).toString().padStart(2, '0');
                const day = date.getDate().toString().padStart(2, '0');

                switch (type) {
                    case 'daily':
                        return `${year}-${month}-${day}`;
                    case 'weekly':
                        // Simple week number calculation (might not align with ISO weeks)
                        const startOfYear = new Date(year, 0, 1);
                        const diff = date.getTime() - startOfYear.getTime();
                        const oneWeek = 1000 * 60 * 60 * 24 * 7;
                        const weekNumber = Math.ceil(diff / oneWeek);
                        return `${year}-W${weekNumber.toString().padStart(2, '0')}`;
                    case 'monthly':
                        return `${year}-${month}`;
                    case 'annual':
                        return `${year}`;
                    default:
                        return '';
                }
            }

            function generateEquipmentInputs(date, entryKey) {
                if (!currentSheet) return '';

                let inputsHtml = '';
                const dayEntries = currentSheet.entries[entryKey] || {};

                currentSheet.equipmentNames.forEach((eqName, eqIndex) => {
                    const eqId = `eq_${eqIndex}`;
                    const eqData = dayEntries[eqId] || {};
                    const temp = eqData.temp !== undefined ? eqData.temp : '';
                    const cleaningChecked = eqData.cleaning ? 'checked' : '';
                    const responsible = eqData.responsible || currentSheet.defaultResponsible || '';
                    const obs = eqData.obs || '';

                    // Determine if inputs should be shown for this specific day/period
                    let showInputs = false;
                    if (currentSheet.type === 'daily') {
                        showInputs = true;
                    } else if (currentSheet.type === 'weekly') {
                        // Show inputs only for the first day of the week
                        const d = new Date(date);
                        showInputs = (d.getDay() === 1 || (d.getDay() === 0 && d.getDate() === 1 && d.getMonth() === 0)); // Monday or Jan 1st if Sunday
                    } else if (currentSheet.type === 'monthly') {
                        // Show inputs only for the first day of the month
                        const d = new Date(date);
                        showInputs = (d.getDate() === 1);
                    } else if (currentSheet.type === 'annual') {
                        // Show inputs only for Jan 1st
                        const d = new Date(date);
                        showInputs = (d.getMonth() === 0 && d.getDate() === 1);
                    }

                    if (showInputs) {
                        inputsHtml += `
                            <div class="equipment-entry" data-eq-id="${eqId}">
                                <strong>${htmlspecialchars(eqName)}</strong>
                                <label>Temp. (°C): <input type="number" step="0.1" value="${temp}" class="input-temp" data-eq-name="${htmlspecialchars(eqName)}"></label>
                                <label><input type="checkbox" ${cleaningChecked} class="input-cleaning"> Limpeza Concluída</label>
                                <label>Responsável: <input type="text" value="${responsible}" class="input-responsible"></label>
                                <label>Obs: <textarea class="input-obs">${htmlspecialchars(obs)}</textarea></label>
                            </div>
                        `;
                    }
                });
                return inputsHtml;
            }

            // --- Manipulação de Dados do Calendário ---
            $calendarGrid.on('input', '.input-temp, .input-cleaning, .input-responsible, .input-obs', function() {
                const $dayCell = $(this).closest('.day-cell');
                const entryKey = $dayCell.data('entry-key');
                const $eqEntry = $(this).closest('.equipment-entry');
                const eqId = $eqEntry.data('eq-id');

                if (!currentSheet.entries[entryKey]) {
                    currentSheet.entries[entryKey] = {};
                }
                if (!currentSheet.entries[entryKey][eqId]) {
                    currentSheet.entries[entryKey][eqId] = {};
                }

                const eqData = currentSheet.entries[entryKey][eqId];
                if ($(this).hasClass('input-temp')) {
                    eqData.temp = parseFloat($(this).val());
                } else if ($(this).hasClass('input-cleaning')) {
                    eqData.cleaning = $(this).is(':checked');
                } else if ($(this).hasClass('input-responsible')) {
                    eqData.responsible = $(this).val();
                } else if ($(this).hasClass('input-obs')) {
                    eqData.obs = $(this).val();
                }
                
                // Update cell class based on completion
                updateDayCellCompletion($dayCell, entryKey);

                saveControlSheets();
                updateCalendarSummary();
            });

            function updateDayCellCompletion($dayCell, entryKey) {
                const dayEntries = currentSheet.entries[entryKey];
                if (!dayEntries) {
                    $dayCell.removeClass('completed partially-completed pending');
                    return;
                }

                let allCompleted = true;
                let anyCompleted = false;

                currentSheet.equipmentNames.forEach((eqName, eqIndex) => {
                    const eqId = `eq_${eqIndex}`;
                    const eqData = dayEntries[eqId];
                    if (eqData && eqData.cleaning) {
                        anyCompleted = true;
                    } else {
                        allCompleted = false;
                    }
                });

                $dayCell.removeClass('completed partially-completed pending');
                if (allCompleted) {
                    $dayCell.addClass('completed');
                } else if (anyCompleted) {
                    $dayCell.addClass('partially-completed');
                } else {
                    $dayCell.addClass('pending');
                }
            }


            // --- Navegação do Calendário ---
            $prevPeriodBtn.on('click', function() {
                if (currentSheet.type === 'annual') {
                    currentCalendarDate.setFullYear(currentCalendarDate.getFullYear() - 1);
                } else {
                    currentCalendarDate.setMonth(currentCalendarDate.getMonth() - 1);
                }
                renderCalendar();
            });

            $nextPeriodBtn.on('click', function() {
                if (currentSheet.type === 'annual') {
                    currentCalendarDate.setFullYear(currentCalendarDate.getFullYear() + 1);
                } else {
                    currentCalendarDate.setMonth(currentCalendarDate.getMonth() + 1);
                }
                renderCalendar();
            });

            // --- Resumo do Calendário ---
            function updateCalendarSummary() {
                if (!currentSheet) {
                    $calendarSummary.empty();
                    return;
                }

                let totalPeriods = 0;
                let completedPeriods = 0;
                let partiallyCompletedPeriods = 0;
                let pendingPeriods = 0;

                const year = currentCalendarDate.getFullYear();
                const month = currentCalendarDate.getMonth(); // 0-11
                const daysInMonth = new Date(year, month + 1, 0).getDate();

                if (currentSheet.type === 'daily') {
                    totalPeriods = daysInMonth;
                    for (let day = 1; day <= daysInMonth; day++) {
                        const date = new Date(year, month, day);
                        const entryKey = getEntryKey(date, 'daily');
                        const dayEntries = currentSheet.entries[entryKey];

                        if (dayEntries) {
                            let allEqCompleted = true;
                            let anyEqCompleted = false;
                            currentSheet.equipmentNames.forEach((eqName, eqIndex) => {
                                const eqData = dayEntries[`eq_${eqIndex}`];
                                if (eqData && eqData.cleaning) {
                                    anyEqCompleted = true;
                                } else {
                                    allEqCompleted = false;
                                }
                            });
                            if (allEqCompleted) {
                                completedPeriods++;
                            } else if (anyEqCompleted) {
                                partiallyCompletedPeriods++;
                            } else {
                                pendingPeriods++;
                            }
                        } else {
                            pendingPeriods++; // No entry means pending
                        }
                    }
                } else if (currentSheet.type === 'weekly') {
                    // Count weeks in the current month
                    const firstDayOfMonth = new Date(year, month, 1);
                    const lastDayOfMonth = new Date(year, month, daysInMonth);
                    let currentWeek = getEntryKey(firstDayOfMonth, 'weekly');
                    let weeksInMonth = new Set();
                    for (let day = 1; day <= daysInMonth; day++) {
                        weeksInMonth.add(getEntryKey(new Date(year, month, day), 'weekly'));
                    }
                    totalPeriods = weeksInMonth.size;

                    weeksInMonth.forEach(weekKey => {
                        const weekEntries = currentSheet.entries[weekKey];
                        if (weekEntries) {
                            let allEqCompleted = true;
                            let anyEqCompleted = false;
                            currentSheet.equipmentNames.forEach((eqName, eqIndex) => {
                                const eqData = weekEntries[`eq_${eqIndex}`];
                                if (eqData && eqData.cleaning) {
                                    anyEqCompleted = true;
                                } else {
                                    allEqCompleted = false;
                                }
                            });
                            if (allEqCompleted) {
                                completedPeriods++;
                            } else if (anyEqCompleted) {
                                partiallyCompletedPeriods++;
                            } else {
                                pendingPeriods++;
                            }
                        } else {
                            pendingPeriods++;
                        }
                    });

                } else if (currentSheet.type === 'monthly') {
                    totalPeriods = 1; // Only one period for the month
                    const entryKey = getEntryKey(currentCalendarDate, 'monthly');
                    const monthEntries = currentSheet.entries[entryKey];
                    if (monthEntries) {
                        let allEqCompleted = true;
                        let anyEqCompleted = false;
                        currentSheet.equipmentNames.forEach((eqName, eqIndex) => {
                            const eqData = monthEntries[`eq_${eqIndex}`];
                            if (eqData && eqData.cleaning) {
                                anyEqCompleted = true;
                            } else {
                                allEqCompleted = false;
                            }
                        });
                        if (allEqCompleted) {
                            completedPeriods = 1;
                        } else if (anyEqCompleted) {
                            partiallyCompletedPeriods = 1;
                        } else {
                            pendingPeriods = 1;
                        }
                    } else {
                        pendingPeriods = 1;
                    }
                } else if (currentSheet.type === 'annual') {
                    totalPeriods = 1; // Only one period for the year
                    const entryKey = getEntryKey(currentCalendarDate, 'annual');
                    const yearEntries = currentSheet.entries[entryKey];
                    if (yearEntries) {
                        let allEqCompleted = true;
                        let anyEqCompleted = false;
                        currentSheet.equipmentNames.forEach((eqName, eqIndex) => {
                            const eqData = yearEntries[`eq_${eqIndex}`];
                            if (eqData && eqData.cleaning) {
                                anyEqCompleted = true;
                            } else {
                                allEqCompleted = false;
                            }
                        });
                        if (allEqCompleted) {
                            completedPeriods = 1;
                        } else if (anyEqCompleted) {
                            partiallyCompletedPeriods = 1;
                        } else {
                            pendingPeriods = 1;
                        }
                    } else {
                        pendingPeriods = 1;
                    }
                }

                const summaryHtml = `
                    <div>Períodos Totais: <strong>${totalPeriods}</strong></div>
                    <div>Completos: <strong style="color: var(--success-dark);">${completedPeriods}</strong></div>
                    <div>Parcialmente Completos: <strong style="color: var(--warning-dark);">${partiallyCompletedPeriods}</strong></div>
                    <div>Pendentes: <strong style="color: var(--error-dark);">${pendingPeriods}</strong></div>
                `;
                $calendarSummary.html(summaryHtml);
            }

            // --- Funcionalidade de Impressão ---
            $printCalendarBtn.on('click', function() {
                const printContent = $calendarViewSection.html();
                const originalBody = $('body').html();
                
                $('body').html(`
                    <style>
                        body { font-family: var(--font-secondary); font-size: 12px; color: #333; margin: 0; padding: 20px; }
                        #calendar-view-section { display: block !important; width: 100%; padding: 0; box-shadow: none; border: none; }
                        #calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
                        #calendar-header h3 { font-size: 1.5em; color: #000; margin: 0; }
                        #calendar-nav-buttons { display: none; } /* Hide nav buttons in print */
                        .calendar-grid {
                            display: grid;
                            grid-template-columns: repeat(7, 1fr);
                            gap: 5px;
                            border: 1px solid #ccc;
                            padding: 10px;
                            background-color: #fff;
                        }
                        .calendar-grid .day-name { background-color: #e0e0e0; color: #000; font-weight: bold; text-align: center; padding: 8px 5px; border-radius: 4px; font-size: 0.85em; }
                        .calendar-grid .day-cell { background-color: #fdfdfd; border: 1px solid #eee; border-radius: 4px; padding: 8px; min-height: 100px; display: flex; flex-direction: column; gap: 5px; position: relative; }
                        .calendar-grid .day-cell.empty { background-color: #f8f8f8; border: 1px dashed #ccc; }
                        .calendar-grid .day-number { font-weight: bold; color: #000; font-size: 1em; margin-bottom: 5px; text-align: right; }
                        .calendar-grid .day-inputs .equipment-entry { background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; padding: 5px; font-size: 0.8em; display: flex; flex-direction: column; gap: 2px; page-break-inside: avoid; }
                        .calendar-grid .day-inputs .equipment-entry label { font-size: 0.75em; color: #333; margin-bottom: 0; }
                        .calendar-grid .day-inputs .equipment-entry input[type="number"],
                        .calendar-grid .day-inputs .equipment-entry input[type="text"],
                        .calendar-grid .day-inputs .equipment-entry textarea { width: 100%; padding: 2px 4px; border: 1px solid #ccc; border-radius: 2px; font-size: 0.75em; }
                        .calendar-grid .day-inputs .equipment-entry input[type="checkbox"] { margin-right: 3px; }
                        .calendar-grid .day-cell.today { border: 2px solid #FFC107; }
                        .calendar-grid .day-cell.completed { background-color: #d4edda; border-color: #28a745; }
                        .calendar-grid .day-cell.partially-completed { background-color: #fff3cd; border-color: #ffc107; }
                        .calendar-grid .day-cell.pending { background-color: #f8d7da; border-color: #dc3545; }
                        .calendar-summary { background-color: #e0e8f4; border: 1px solid #4D94DB; border-radius: 8px; padding: 15px; font-size: 0.9em; color: #003A6A; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-top: 20px; }
                        .calendar-summary strong { color: #000; }
                        .print-button-container { display: none; } /* Hide print button in print dialog */

                        /* Ensure content fits on page */
                        @page { size: A4 landscape; margin: 1cm; }
                        @media print {
                            html, body {
                                width: 297mm; /* A4 landscape width */
                                height: 210mm; /* A4 landscape height */
                                overflow: hidden;
                            }
                            .main-content-wrapper { padding: 0; max-width: none; margin: 0; }
                            .page-title { display: none; } /* Hide main page title */
                            .site-header, .main-footer-bottom { display: none; } /* Hide header and footer */
                            #calendar-view-section { margin-top: 0; }
                        }
                    </style>
                ` + printContent);

                window.print();

                // Restore original body content after printing
                $('body').html(originalBody);
                // Re-attach event listeners as they are lost when replacing html
                attachAllEventListeners();
            });


            // --- Inicialização ---
            function attachAllEventListeners() {
                // Modals
                $('.modal-overlay').off('click').on('click', function(e) { if ($(e.target).is(this)) { closeModal($(this)); } });
                $('.modal-close-btn').off('click').on('click', function() { closeModal($(this).closest('.modal-overlay')); });

                // New/Save Sheet
                $newControlSheetBtn.off('click').on('click', function() {
                    $modalTitle.text('Criar Nova Planilha');
                    $controlSheetForm[0].reset();
                    $sheetIdInput.val('');
                    $equipmentCountInput.val(1).trigger('input');
                    openModal($controlSheetModal);
                });
                $equipmentCountInput.off('input').on('input', function() {
                    const count = parseInt($(this).val(), 10) || 1;
                    $equipmentNamesContainer.empty();
                    for (let i = 0; i < count; i++) {
                        const placeholder = `Nome do Equipamento ${i + 1}`;
                        $equipmentNamesContainer.append(`
                            <input type="text" class="equipment-name-input auth-input" placeholder="${placeholder}" required>
                        `);
                    }
                });
                $saveControlSheetBtn.off('click').on('click', function() {
                    const sheetId = $sheetIdInput.val() || generateUniqueId();
                    const name = $sheetNameInput.val().trim();
                    const type = $('input[name="sheet-type"]:checked').val();
                    const equipmentNames = [];
                    $('.equipment-name-input').each(function() {
                        const eqName = $(this).val().trim();
                        if (eqName) equipmentNames.push(eqName);
                    });
                    const defaultResponsible = $defaultResponsibleInput.val().trim();

                    if (!name) { alert('Por favor, insira um nome para o processo/registro.'); return; }
                    if (equipmentNames.length === 0) { alert('Por favor, insira pelo menos um nome de equipamento.'); return; }

                    const newSheet = {
                        id: sheetId,
                        name: name,
                        type: type,
                        equipmentNames: equipmentNames,
                        defaultResponsible: defaultResponsible,
                        creationDate: new Date().toISOString().split('T')[0],
                        entries: {}
                    };

                    const existingIndex = controlSheets.findIndex(s => s.id === sheetId);
                    if (existingIndex > -1) {
                        newSheet.entries = controlSheets[existingIndex].entries;
                        controlSheets[existingIndex] = newSheet;
                    } else {
                        controlSheets.push(newSheet);
                    }

                    saveControlSheets();
                    closeModal($controlSheetModal);
                    selectControlSheet(newSheet.id);
                });

                // Edit/Delete Sheet from list
                $controlSheetsList.off('click', '.edit-sheet-btn').on('click', '.edit-sheet-btn', function(e) {
                    e.stopPropagation();
                    const sheetId = $(this).closest('.control-sheet-item').data('id');
                    const sheetToEdit = controlSheets.find(s => s.id === sheetId);
                    if (sheetToEdit) {
                        $modalTitle.text('Editar Planilha');
                        $sheetIdInput.val(sheetToEdit.id);
                        $sheetNameInput.val(sheetToEdit.name);
                        $(`input[name="sheet-type"][value="${sheetToEdit.type}"]`).prop('checked', true);
                        $equipmentCountInput.val(sheetToEdit.equipmentNames.length).trigger('input');
                        sheetToEdit.equipmentNames.forEach((name, index) => {
                            $equipmentNamesContainer.find(`.equipment-name-input:eq(${index})`).val(name);
                        });
                        $defaultResponsibleInput.val(sheetToEdit.defaultResponsible);
                        openModal($controlSheetModal);
                    }
                });
                $controlSheetsList.off('click', '.delete-sheet-btn').on('click', '.delete-sheet-btn', function(e) {
                    e.stopPropagation();
                    const sheetId = $(this).closest('.control-sheet-item').data('id');
                    const sheetName = $(this).closest('.control-sheet-item').find('h3').text();
                    if (confirm(`Tem certeza que deseja excluir a planilha "${htmlspecialchars(sheetName)}"? Esta ação não pode ser desfeita.`)) {
                        controlSheets = controlSheets.filter(s => s.id !== sheetId);
                        saveControlSheets();
                        if (currentSheet && currentSheet.id === sheetId) {
                            currentSheet = null;
                            $calendarViewSection.hide();
                        }
                        renderControlSheetsList();
                    }
                });

                // Select Sheet to view calendar
                $controlSheetsList.off('click', '.control-sheet-item').on('click', '.control-sheet-item', function() {
                    const sheetId = $(this).data('id');
                    selectControlSheet(sheetId);
                });

                // Calendar input handling
                $calendarGrid.off('input', '.input-temp, .input-cleaning, .input-responsible, .input-obs').on('input', '.input-temp, .input-cleaning, .input-responsible, .input-obs', function() {
                    const $dayCell = $(this).closest('.day-cell');
                    const entryKey = $dayCell.data('entry-key');
                    const $eqEntry = $(this).closest('.equipment-entry');
                    const eqId = $eqEntry.data('eq-id');

                    if (!currentSheet.entries[entryKey]) {
                        currentSheet.entries[entryKey] = {};
                    }
                    if (!currentSheet.entries[entryKey][eqId]) {
                        currentSheet.entries[entryKey][eqId] = {};
                    }

                    const eqData = currentSheet.entries[entryKey][eqId];
                    if ($(this).hasClass('input-temp')) {
                        eqData.temp = parseFloat($(this).val());
                    } else if ($(this).hasClass('input-cleaning')) {
                        eqData.cleaning = $(this).is(':checked');
                    } else if ($(this).hasClass('input-responsible')) {
                        eqData.responsible = $(this).val();
                    } else if ($(this).hasClass('input-obs')) {
                        eqData.obs = $(this).val();
                    }
                    
                    updateDayCellCompletion($dayCell, entryKey);
                    saveControlSheets();
                    updateCalendarSummary();
                });

                // Calendar navigation
                $prevPeriodBtn.off('click').on('click', function() {
                    if (currentSheet.type === 'annual') {
                        currentCalendarDate.setFullYear(currentCalendarDate.getFullYear() - 1);
                    } else {
                        currentCalendarDate.setMonth(currentCalendarDate.getMonth() - 1);
                    }
                    renderCalendar();
                });
                $nextPeriodBtn.off('click').on('click', function() {
                    if (currentSheet.type === 'annual') {
                        currentCalendarDate.setFullYear(currentCalendarDate.getFullYear() + 1);
                    } else {
                        currentCalendarDate.setMonth(currentCalendarDate.getMonth() + 1);
                    }
                    renderCalendar();
                });

                // Print button
                $printCalendarBtn.off('click').on('click', function() {
                    const printContent = $calendarViewSection.html();
                    const originalBody = $('body').html();
                    
                    $('body').html(`
                        <style>
                            body { font-family: var(--font-secondary); font-size: 12px; color: #333; margin: 0; padding: 20px; }
                            #calendar-view-section { display: block !important; width: 100%; padding: 0; box-shadow: none; border: none; }
                            #calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
                            #calendar-header h3 { font-size: 1.5em; color: #000; margin: 0; }
                            #calendar-nav-buttons { display: none; }
                            .calendar-grid {
                                display: grid;
                                grid-template-columns: repeat(7, 1fr);
                                gap: 5px;
                                border: 1px solid #ccc;
                                padding: 10px;
                                background-color: #fff;
                            }
                            .calendar-grid .day-name { background-color: #e0e0e0; color: #000; font-weight: bold; text-align: center; padding: 8px 5px; border-radius: 4px; font-size: 0.85em; }
                            .calendar-grid .day-cell { background-color: #fdfdfd; border: 1px solid #eee; border-radius: 4px; padding: 8px; min-height: 100px; display: flex; flex-direction: column; gap: 5px; position: relative; }
                            .calendar-grid .day-cell.empty { background-color: #f8f8f8; border: 1px dashed #ccc; }
                            .calendar-grid .day-number { font-weight: bold; color: #000; font-size: 1em; margin-bottom: 5px; text-align: right; }
                            .calendar-grid .day-inputs .equipment-entry { background-color: #f0f0f0; border: 1px solid #ddd; border-radius: 3px; padding: 5px; font-size: 0.8em; display: flex; flex-direction: column; gap: 2px; page-break-inside: avoid; }
                            .calendar-grid .day-inputs .equipment-entry label { font-size: 0.75em; color: #333; margin-bottom: 0; }
                            .calendar-grid .day-inputs .equipment-entry input[type="number"],
                            .calendar-grid .day-inputs .equipment-entry input[type="text"],
                            .calendar-grid .day-inputs .equipment-entry textarea { width: 100%; padding: 2px 4px; border: 1px solid #ccc; border-radius: 2px; font-size: 0.75em; }
                            .calendar-grid .day-inputs .equipment-entry input[type="checkbox"] { margin-right: 3px; }
                            .calendar-grid .day-cell.today { border: 2px solid #FFC107; }
                            .calendar-grid .day-cell.completed { background-color: #d4edda; border-color: #28a745; }
                            .calendar-grid .day-cell.partially-completed { background-color: #fff3cd; border-color: #ffc107; }
                            .calendar-grid .day-cell.pending { background-color: #f8d7da; border-color: #dc3545; }
                            .calendar-summary { background-color: #e0e8f4; border: 1px solid #4D94DB; border-radius: 8px; padding: 15px; font-size: 0.9em; color: #003A6A; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-top: 20px; }
                            .calendar-summary strong { color: #000; }
                            .print-button-container { display: none; }

                            @page { size: A4 landscape; margin: 1cm; }
                            @media print {
                                html, body {
                                    width: 297mm;
                                    height: 210mm;
                                    overflow: hidden;
                                }
                                .main-content-wrapper { padding: 0; max-width: none; margin: 0; }
                                .page-title { display: none; }
                                .site-header, .main-footer-bottom { display: none; }
                                #calendar-view-section { margin-top: 0; }
                            }
                        </style>
                    ` + printContent);
                    window.print();
                    $('body').html(originalBody);
                    attachAllEventListeners(); // Re-attach after restoring
                });
            }

            // Initial load
            loadControlSheets();
            attachAllEventListeners(); // Attach all event listeners initially
        });
    </script>
</body>
</html>
