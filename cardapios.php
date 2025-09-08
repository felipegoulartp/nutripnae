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
    <link rel="stylesheet" href="assets/css/global.css"> <!-- Inclui o CSS global padronizado -->
    <style>
        /* AQUI SÓ DEVE FICAR O CSS ESPECÍFICO DESTA PÁGINA! */
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
            color: var(--color-text-dark); /* Alterado de --color-accent para --color-text-dark */
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
            transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
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
        .action-button:hover:not(:disabled) {
            background-color: var(--color-primary-dark);
            box-shadow: 0 4px 8px rgba(0, 90, 156, 0.1);
            transform: translateY(-1px);
        }
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
        .modal-button.confirm { background-color: var(--color-accent); color:var(--color-text-dark); } /* Botões de modal confirm com efeito dourado */
        .modal-button.confirm:hover:not(:disabled) { background-color: var(--color-accent-dark); }


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
            .sidebar-toggle-button {
                border-left: none; /* Remove a borda esquerda em mobile */
                justify-content: center; /* Centraliza o conteúdo do botão */
                padding: 10px 15px; /* Ajusta o padding para mobile */
            }
            .sidebar-toggle-button span { display: inline; } /* Mostra o texto em mobile */
            .sidebar-toggle-button i { margin-right: 8px; transform: none !important; } /* Ajusta ícone */
            .sidebar.collapsed { width: 100%; }
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
    <?php include_once 'includes/message_box.php'; ?>
    <?php include_once 'includes/header.php'; ?>

    <div class="main-wrapper">
        <?php include_once 'includes/sidebar.php'; ?>

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
    <script src="assets/js/global.js"></script> <!-- Inclui o JavaScript global padronizado -->
    <script>
    $(document).ready(function() {
        console.log("Meus Cardápios (meuscardapios.php) JS v1.0 carregado.");

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
                        'May': 'Maio', 'June': 'Junho', 'July': 'Julho', 'August' : 'Agosto',
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
        const $sidebarNav = $('#sidebar-nav');
        // Removido $platformSections pois não é usado diretamente aqui

        $sidebarToggleButton.on('click', function() {
            // A lógica de toggle do sidebar foi movida para global.js para padronização.
            // Esta função local será removida.
            // Para garantir que o botão funcione, o HTML do sidebar deve vir de includes/sidebar.php
            // e o global.js deve ser importado.
            // O global.js já tem a lógica:
            // - Para desktop: $sidebar.toggleClass('collapsed') e muda ícone/texto do botão
            // - Para mobile: $sidebarNav.toggleClass('active') e muda ícone/texto do botão
            // A visibilidade do botão de toggle em mobile/desktop é controlada por checkSidebarToggleVisibility em global.js.
        });

        // Function to handle platform link navigation (from navbar and sidebar)
        function handlePlatformLink(e) {
            e.preventDefault();
            const platformTarget = $(this).data('platform-id') || $(this).data('platform-link');
            if (platformTarget && platformTarget.includes('dashboard-section')) {
                window.location.href = 'home.php?platform=' + platformTarget;
            } else if ($(this).attr('href')) {
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
        // Esta função agora está em global.js
        // function checkSidebarToggleVisibility() { /* ... */ }

        // Initial check and on resize
        // checkSidebarToggleVisibility(); // Chamada agora em global.js
        // $(window).on('resize', checkSidebarToggleVisibility); // Evento agora em global.js

        // Renderiza os cardápios na carga inicial
        renderFilteredCardapios('');
    });
    </script>
</body>
</html>
