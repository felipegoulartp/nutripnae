<?php
// cardapio_auto/register.php

// --- Bloco Padronizado de Configuração de Sessão ---
$session_cookie_path = '/';
$session_name = "CARDAPIOSESSID";

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $session_cookie_path,
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
session_name($session_name);
if (session_status() === PHP_SESSION_NONE) {
     session_start();
}

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', 0); // EM PRODUÇÃO, MUDE PARA 0. Deixe 1 durante desenvolvimento.
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_log("--- Iniciando register.php (com planos estilizados) --- | SID: " . session_id());

$errors = [];
$username_input = '';
$email_input = '';
$page_title = "Crie sua Conta - NutriPNAE & NutriGestor & NutriDEV";
$db_connection_error = false;
$pdo = null;
$registration_success = false;

try {
    require_once 'includes/db_connect.php';
    error_log("DEBUG Register (Planos Estilizados): db_connect.php incluído com sucesso.");
} catch (\PDOException $e) {
    $db_connection_error = true;
    $errors[] = "Erro crítico [DB Connect]: Não foi possível conectar ao banco de dados.";
    error_log("CRITICAL Register (Planos Estilizados): Falha ao incluir/conectar db_connect.php - " . $e->getMessage());
} catch (\Throwable $th) {
     $db_connection_error = true;
     $errors[] = "Erro crítico [Include]: Falha ao carregar dependências.";
     error_log("CRITICAL Register (Planos Estilizados): Falha Throwable nos includes iniciais - " . $th->getMessage());
}

if (!$db_connection_error) {
     if (isset($_SESSION['user_id'])) {
         error_log("DEBUG Register (Planos Estilizados): Usuário já logado (ID: ".$_SESSION['user_id']."). Redirecionando para home.");
         ob_start();
         header('Location: home.php');
         ob_end_flush();
         exit;
     }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_user'])) {
        error_log("DEBUG Register (Planos Estilizados): Recebido POST para registro.");
        $username_input = trim($_POST['username'] ?? '');
        $email_input = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($username_input)) $errors[] = "Nome de usuário é obrigatório.";
        else if (strlen($username_input) < 3) $errors[] = "Nome de usuário deve ter pelo menos 3 caracteres.";
        if (empty($email_input)) $errors[] = "Email é obrigatório.";
        elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) $errors[] = "Formato de email inválido.";
        if (empty($password)) $errors[] = "Senha é obrigatória.";
        if (strlen($password) < 6) $errors[] = "A senha deve ter pelo menos 6 caracteres.";
        if ($password !== $password_confirm) $errors[] = "As senhas não coincidem.";

        if (empty($errors)) {
            try {
                $sql_check_email = "SELECT 1 FROM cardapio_usuarios WHERE email = :email LIMIT 1";
                $stmt_check_email = $pdo->prepare($sql_check_email);
                $stmt_check_email->bindParam(':email', $email_input, PDO::PARAM_STR);
                $stmt_check_email->execute();
                if ($stmt_check_email->fetch()) {
                    $errors[] = "Este e-mail já está cadastrado. Tente <a href='login.php'>fazer login</a>.";
                }

                $sql_check_user = "SELECT 1 FROM cardapio_usuarios WHERE username = :username LIMIT 1";
                $stmt_check_user = $pdo->prepare($sql_check_user);
                $stmt_check_user->bindParam(':username', $username_input, PDO::PARAM_STR);
                $stmt_check_user->execute();
                if ($stmt_check_user->fetch()) {
                    $errors[] = "Este nome de usuário já está em uso. Por favor, escolha outro.";
                }
            } catch (PDOException $e) {
                $errors[] = "Erro [DB Check]: Não foi possível verificar os dados. Tente novamente.";
                error_log("CRITICAL Register (Planos Estilizados) (Check User/Email): " . $e->getMessage());
            }
        }

        if (empty($errors)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            if ($password_hash === false) {
                 $errors[] = "Erro crítico [Hash]: Não foi possível processar a senha.";
                 error_log("CRITICAL Register (Planos Estilizados): Falha no password_hash para " . $username_input);
            } else {
                // Determine trial period based on selected plan (this will be more complex with actual plan selection)
                // For now, let's assume a default trial or it comes from the form (not implemented in this specific register form)
                $trial_ends_at_value = date('Y-m-d H:i:s', strtotime('+7 days')); // Default 7 days trial
                $current_plan_id_value = 'trial_default'; // Default plan ID

                try {
                    $sql_insert = "INSERT INTO cardapio_usuarios (username, email, password_hash, trial_ends_at, current_plan_id) VALUES (:username, :email, :password_hash, :trial_ends_at, :current_plan_id)";
                    $stmt_insert = $pdo->prepare($sql_insert);
                    $stmt_insert->bindParam(':username', $username_input, PDO::PARAM_STR);
                    $stmt_insert->bindParam(':email', $email_input, PDO::PARAM_STR);
                    $stmt_insert->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
                    $stmt_insert->bindParam(':trial_ends_at', $trial_ends_at_value, PDO::PARAM_STR);
                    $stmt_insert->bindParam(':current_plan_id', $current_plan_id_value, PDO::PARAM_STR);

                    if ($stmt_insert->execute()) {
                        $newUserId = $pdo->lastInsertId();
                        $_SESSION['success_message'] = "Conta criada com sucesso! Você iniciou um teste gratuito de 7 dias. Faça login para começar.";
                        $registration_success = true;
                        error_log("SUCESSO Register (Planos Estilizados): Usuário ID: " . $newUserId . " (" . $username_input . ") registrado.");
                    } else {
                        $errors[] = "Erro [DB Insert]: Falha ao registrar usuário.";
                        error_log("CRITICAL Register (Planos Estilizados): Falha INSERT para " . $username_input . ". Erro PDO: " . print_r($stmt_insert->errorInfo(), true));
                    }
                } catch (PDOException $e) {
                     $errors[] = "Erro [DB Insert Ex]: Falha no banco de dados ao registrar.";
                     error_log("CRITICAL Register (Planos Estilizados) (Insert PDOException): " . $e->getMessage() . " | Code: " . $e->getCode());
                     if (strpos(strtolower($e->getMessage()), 'duplicate entry') !== false || $e->getCode() == '23000') {
                         if (strpos(strtolower($e->getMessage()), 'email') !== false) {
                            $errors[] = "Este e-mail já está cadastrado (detectado pelo banco de dados). Tente <a href='login.php'>fazer login</a>.";
                         } elseif (strpos(strtolower($e->getMessage()), 'username') !== false) {
                            $errors[] = "Este nome de usuário já está em uso (detectado pelo banco de dados). Por favor, escolha outro.";
                         } else {
                            $errors[] = "Erro: Nome de usuário ou e-mail já existe [DB constraint].";
                         }
                     }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Variáveis CSS baseadas no login.php */
        :root {
            --color-primary: #2196F3; /* Azul vibrante */
            --color-primary-dark: #1976D2; /* Azul mais escuro */
            --color-primary-light: #BBDEFB; /* Azul mais claro */
            --color-accent: #FFC107; /* Amarelo dourado */
            --color-accent-dark: #FFA000; /* Amarelo mais escuro */
            --color-text-dark: #333; /* Texto escuro padrão */
            --color-text-light: #f8f8f8; /* Texto claro para fundos escuros */
            --color-background-light: #f2f2f2; /* Fundo claro */
            --color-background-gray: #e0e0e0; /* Fundo cinza suave */
            --color-background-dark: #3f51b5; /* Fundo azul escuro */
            --color-error: #D32F2F; /* Vermelho para erros */
            --color-success: #388E3C; /* Verde para sucesso */
            --color-nutrigestor: #EA1D2C; /* iFood Red */
            --color-nutrigestor-dark: #B51522; /* Darker iFood Red */
            --color-nutridev: #8A2BE2; /* Roxo forte para NutriDEV */
            --color-nutridev-dark: #6A1B9A; /* Roxo mais escuro para NutriDEV */
            --font-main: 'Roboto', sans-serif;
            --font-headings: 'Poppins', sans-serif;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
        }

        /* Reset e base */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-main);
            line-height: 1.6;
            color: var(--color-text-dark);
            /* Gradiente escuro para o fundo da página */
            background-color: #1a1a1a; /* Fallback para navegadores antigos */
            background-image: linear-gradient(160deg, #333333 0%, #222222 50%, #111111 100%);
            background-repeat: no-repeat;
            background-size: cover;
            background-attachment: fixed;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Títulos */
        h1, h2, h3, h4, h5, h6 {
            font-family: var(--font-headings);
            margin-top: 0;
            margin-bottom: 0.5em;
            color: #fff; /* Títulos em branco para o fundo escuro */
        }

        h1 {
            font-size: 3.2em;
            line-height: 1.2;
            color: #fff;
        }
        h2 {
            font-size: 2.6em;
            line-height: 1.3;
            position: relative;
            padding-bottom: 15px;
            margin-bottom: 25px;
            text-align: center;
        }
        h2::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background-color: var(--color-accent);
            border-radius: 2px;
        }
        h3 {
            font-size: 2.1em;
            margin-bottom: 15px;
            text-align: center; /* Padrão para cards de plano */
            color: #000; /* Mantido preto para os títulos dentro dos cards brancos */
        }

        p {
            margin-bottom: 1em;
            font-weight: normal;
            font-size: 1.1em;
            color: #f8f8f8; /* Texto de parágrafo branco para o fundo escuro */
        }

        strong, b {
            font-weight: 700 !important;
        }

        a {
            color: var(--color-primary);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        a:hover {
            color: var(--color-primary-dark);
        }

        /* Utilities */
        .container {
            max-width: 1500px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .text-center { text-align: center; }
        .section-padding { padding: 80px 0; }
        .section-padding-sm { padding: 40px 0; }

        /* Buttons (adaptados do login.php) */
        .btn {
            display: inline-block;
            padding: 14px 28px;
            font-size: 1.05em;
            font-weight: 600;
            border-radius: var(--border-radius);
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            white-space: nowrap; /* Evita quebra de linha em botões grandes */
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            color: var(--color-text-light);
            border: none;
        }
        .btn-accent {
            background: linear-gradient(135deg, var(--color-accent), var(--color-accent-dark));
            color: var(--color-text-dark);
            border: none;
        }
        .btn-nutrigestor-red {
            background: linear-gradient(135deg, var(--color-nutrigestor), var(--color-nutrigestor-dark));
            color: var(--color-text-light);
            border: none;
        }
        .btn-nutridev {
            background: linear-gradient(135deg, var(--color-nutridev), var(--color-nutridev-dark));
            color: var(--color-text-light);
            border: none;
        }
        .btn-outline-primary {
            background-color: transparent;
            color: var(--color-primary);
            border: 2px solid var(--color-primary);
            box-shadow: none; /* remove default shadow if using outlines */
        }
        .btn-outline-primary:hover {
            background-color: var(--color-accent); /* Changed to gold */
            color: var(--color-text-dark); /* Changed to dark text for contrast */
        }
        .btn-lg {
            padding: 15px 30px;
            font-size: 1.1em;
        }
        .btn-block {
            display: block;
            width: 100%;
        }

        /* Alerts (do login.php) */
        .alert {
            padding: 10px 20px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            text-align: center;
            font-weight: 600;
        }
        .alert-error {
            background-color: #ffebee;
            color: var(--color-error);
            border: 1px solid var(--color-error);
        }
        .alert-success {
            background-color: #e8f5e9;
            color: var(--color-success);
            border: 1px solid var(--color-success);
        }

        /* Navbar (do login.php) */
        .navbar {
            background-color: #fff;
            padding: 15px 0;
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
        }
        .navbar .container {
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
            font-family: var(--font-headings);
            font-size: 1.7em;
            font-weight: 700;
            white-space: nowrap;
        }
        .navbar-brand i {
            margin-right: 8px;
            font-size: 1.2em;
        }
        .navbar-brand.pnae { color: var(--color-primary-dark); }
        .navbar-brand.nutrigestor { color: var(--color-nutrigestor); }
        .navbar-brand.nutridev { color: var(--color-nutridev); }

        /* Hero Section (Adaptada do login.php para Register) */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('images/fundo_hero_nutripnae.png') no-repeat center center/cover;
            color: var(--color-text-light);
            padding: 80px 0;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 350px;
        }
        .hero-content {
            max-width: 900px;
            padding: 0 20px;
        }
        .hero h1 {
            color: #fff;
            font-size: 3.2em;
            margin-bottom: 20px;
            line-height: 1.1;
        }
        .hero p {
            font-size: 1.3em;
            margin-bottom: 30px;
            opacity: 0.95;
            line-height: 1.6;
        }

        /* Pricing Section (Baseada no login.php, mas adaptada para planos) */
        .pricing-section {
            background-color: transparent; /* Remove fixed background for section, let body gradient show */
            padding: 80px 0;
            text-align: center;
        }
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-top: 50px;
            align-items: stretch; /* Garante que todos os cards tenham a mesma altura */
        }
        .plan-card {
            background-color: #fff;
            border-radius: var(--border-radius);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15); /* Sombra consistente */
            padding: 30px;
            text-align: left;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-top: 5px solid transparent; /* Bordas coloridas como em login.php */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 520px; /* Diminuído de 550px */
        }
        .plan-card:hover {
            transform: translateY(-10px);
        }
        /* Sombra discreta com gradiente nas cores da plataforma no hover */
        .plan-card.pnae-plan:hover {
            box-shadow: 0 16px 32px rgba(33, 150, 243, 0.4); /* Azul para NutriPNAE, sombra mais forte */
        }
        .plan-card.nutrigestor-plan:hover {
            box-shadow: 0 16px 32px rgba(234, 29, 44, 0.4); /* Vermelho para NutriGestor, sombra mais forte */
        }
        .plan-card.nutridev-product:hover {
            box-shadow: 0 16px 32px rgba(138, 43, 226, 0.4); /* Roxo para NutriDEV, sombra mais forte */
        }

        /* Cores das bordas e ícones dos planos */
        .plan-card.pnae-plan { border-top-color: var(--color-primary); }
        .plan-card.nutrigestor-plan { border-top-color: var(--color-nutrigestor); }
        .plan-card.nutridev-product { border-top-color: var(--color-nutridev); }

        .plan-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .plan-icon {
            font-size: 3.8em;
            margin-bottom: 15px;
            display: block;
            line-height: 1;
        }
        .plan-card.pnae-plan .plan-icon { color: var(--color-primary-dark); }
        .plan-card.nutrigestor-plan .plan-icon { color: var(--color-nutrigestor); }
        .plan-card.nutridev-product .plan-icon { color: var(--color-nutridev); }

        .plan-name {
            font-size: 2.1em;
            font-weight: 700;
            margin-bottom: 10px;
            color: #000;
        }

        /* Novo estilo para a área de preço com risco e gratuidade */
        .plan-price-wrapper {
            position: relative;
            text-align: center;
            margin-bottom: 15px;
            min-height: 100px; /* Ensure space for both price elements */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .original-price {
            font-family: var(--font-headings);
            font-size: 1.8em;
            font-weight: 600;
            color: #999;
            text-decoration: line-through;
            display: block;
            margin-bottom: 5px;
        }

        .original-price .currency, .original-price .period {
            font-size: 0.7em;
            vertical-align: super;
            margin-right: 2px;
        }

        .free-promo {
            font-family: var(--font-headings);
            font-size: 1.5em;
            font-weight: 900;
            color: var(--color-success);
            display: block;
            text-transform: uppercase;
            animation: pulse 1.5s infinite alternate;
        }

        @keyframes pulse {
            from { transform: scale(1); opacity: 1; }
            to { transform: scale(1.03); opacity: 0.9; }
        }

        /* For Ebooks and special items within features */
        .plan-features .item-title { /* For "Ebook: Dominando o NutriPNAE", "Ebook: Otimize com NutriGestor", "Acesso Vitalício: Comunidade NutriDEV Online" */
            font-weight: bold;
            color: var(--color-text-dark);
            margin-top: 15px;
            margin-bottom: 5px;
            font-size: 1.05em;
        }
        .plan-features .item-details { /* For sub-lists under ebooks */
            list-style: none;
            padding-left: 20px;
            margin-top: 5px;
        }
        .plan-features .item-details li {
            font-weight: normal;
            font-size: 0.95em; /* Maintained slightly smaller for nested details */
            color: #666;
            margin-bottom: 5px;
        }
        .plan-features .item-details li i.fa-arrow-right {
            color: #888;
            font-size: 0.9em;
        }
        .plan-features .item-details .original-price-inline { /* For R$ 30,00 and R$ 100,00 within text */
            text-decoration: line-through;
            color: #999;
            margin-right: 5px;
            font-weight: normal;
            /* Font size for annual text */
            font-size: 0.9em; /* Smaller font size for annual price */
        }
        .plan-features .item-details .free-text-inline { /* For GRÁTIS text within bullet points */
            color: var(--color-success);
            font-weight: bold;
            font-size: 0.9em; /* Smaller font size for GRÁTIS */
        }

        .plan-description {
            font-size: 1.05em;
            color: #555;
            margin-bottom: 20px;
            min-height: 70px;
            text-align: center;
        }

        .plan-features {
            list-style: none;
            padding: 0;
            margin-bottom: 30px;
            flex-grow: 1; /* Permite que a lista se estenda e mantenha os botões alinhados */
            text-align: left; /* Alinha os itens da lista à esquerda */
        }
        .plan-features li {
            margin-bottom: 12px;
            position: relative;
            padding-left: 28px;
            font-size: 1.05em; /* Standardized to this size */
            color: #555;
            font-weight: normal;
        }
        .plan-features li i {
            position: absolute;
            left: 0;
            top: 3px;
            font-size: 1.1em;
            color: var(--color-success);
        }
        .plan-card.pnae-plan .plan-features li i { color: var(--color-primary); }
        .plan-card.nutrigestor-plan .plan-features li i { color: var(--color-nutrigestor); }
        .plan-card.nutridev-product .plan-features li i { color: var(--color-nutridev); }

        .plan-cta {
            margin-top: auto;
            text-align: center;
        }

        /* Registration Form Section (adaptado do login.php) */
        .registration-form-section {
            padding: 80px 0;
            /* Fundo gradiente mais escuro */
            background-image: linear-gradient(160deg, #333333 0%, #222222 50%, #111111 100%);
            background-repeat: no-repeat;
            background-size: cover;
            background-attachment: fixed; /* Fix the background as it scrolls */
            text-align: center;
        }
        .auth-container {
            width: 100%;
            max-width: 750px; /* Increased from 700px */
            margin: 0 auto;
            padding: 45px;
            background-color: #fff;
            border-radius: var(--border-radius);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.25); /* Default shadow */
            transition: box-shadow 0.3s ease; /* Transition for hover effect */
            text-align: left;
            position: relative; /* Needed for pseudo-elements for background star */
            overflow: hidden; /* To keep the star within bounds */
        }

        /* Subtle background star/element for auth-container */
        .auth-container::before {
            content: "\f005"; /* Font Awesome star icon */
            font-family: "Font Awesome 6 Free";
            font-weight: 900; /* Solid icon */
            position: absolute;
            top: -30px; /* Position it slightly off-center */
            left: -30px;
            font-size: 250px; /* Large size */
            color: rgba(255, 193, 7, 0.05); /* Faded accent color */
            z-index: 0; /* Behind content */
            transform: rotate(20deg); /* Slight rotation */
            pointer-events: none; /* Make it unclickable */
        }
        .auth-container::after {
            content: "\f121"; /* Font Awesome code icon (another option) */
            font-family: "Font Awesome 6 Free";
            font-weight: 900; /* Solid icon */
            position: absolute;
            bottom: -30px;
            right: -30px;
            font-size: 250px;
            color: rgba(138, 43, 226, 0.05); /* Faded NutriDEV color */
            z-index: 0;
            transform: rotate(-20deg);
            pointer-events: none;
        }


        .auth-container:hover {
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.35); /* Slightly stronger shadow on hover */
        }

        .auth-container h2 {
            text-align: center;
            margin-bottom: 15px; /* Reduced margin to make space for beta text */
            color: var(--color-primary-dark);
            font-size: 2.1em;
            position: relative; /* Ensure it's above pseudo-elements */
            z-index: 1;
        }
        .auth-container .beta-tag {
            display: block;
            text-align: center;
            font-family: var(--font-headings);
            font-size: 1.4em;
            font-weight: 700;
            color: var(--color-accent-dark);
            margin-bottom: 15px;
            text-transform: uppercase;
            position: relative;
            z-index: 1;
        }
        .auth-container .form-subtitle {
            text-align: center;
            margin-bottom: 20px; /* Adjusted margin */
            color: #555;
            font-size: 1.15em;
            position: relative;
            z-index: 1;
        }
        .auth-container .beta-info-text {
            text-align: center;
            margin-bottom: 30px;
            color: #666;
            font-size: 0.95em;
            line-height: 1.4;
            padding: 0 15px;
            position: relative;
            z-index: 1;
        }
        .form-group {
            margin-bottom: 22px;
            position: relative;
            z-index: 1;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 1.1em;
        }
        .auth-input {
            width: 100%;
            padding: 14px;
            border: 1px solid #ccc;
            border-radius: var(--border-radius);
            font-size: 1.05em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .auth-input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.2);
            outline: none;
        }
        .auth-link {
            text-align: center;
            margin-top: 25px;
            font-size: 1.05em;
        }
        .auth-link a {
            color: var(--color-text-dark); /* Texto "Já possui uma conta?" em preto */
        }
        .auth-link a:hover {
            color: var(--color-primary-dark); /* Mantém o hover azul padrão ou altera para dourado se preferir */
        }


        /* Error and Success Messages (do login.php) */
        .error-message {
            background-color: #ffebee;
            color: var(--color-error);
            border: 1px solid var(--color-error);
            padding: 15px 25px;
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            text-align: left;
            font-weight: 500;
            font-size: 1.1em;
            position: relative;
            z-index: 1;
        }
        .error-message ul {
            list-style: none;
            padding: 0;
            margin-top: 10px;
        }
        .error-message li {
            margin-bottom: 5px;
        }
        .success-message {
            background-color: #e8f5e9;
            color: var(--color-success);
            border: 1px solid var(--color-success);
            padding: 25px;
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            text-align: center;
            font-weight: 600;
            font-size: 1.15em;
            position: relative;
            z-index: 1;
        }
        .success-message .fa-check-circle {
            font-size: 1.6em;
            margin-right: 12px;
            vertical-align: middle;
        }

        /* Database Connection Error (do login.php) */
        .error-db-container {
            background-color: #fff;
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
            border: 1px solid var(--color-error);
            max-width: 600px;
            margin: 80px auto;
        }
        .error-db-container h1 {
            color: var(--color-error);
            margin-bottom: 20px;
            font-size: 2.2em;
        }
        .error-db-container h1 i {
            margin-right: 10px;
        }

        /* Footer (do login.php) */
        .footer-simple {
            background-color: var(--color-primary-dark);
            color: var(--color-text-light);
            padding: 50px 0;
            text-align: center;
            font-size: 1em;
            margin-top: auto;
        }
        .footer-simple .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        .footer-simple p {
            margin: 0;
            opacity: 0.9;
        }
        .footer-simple a {
            color: var(--color-text-light);
            font-weight: 400;
            transition: color 0.3s ease;
        }
        .footer-simple a:hover {
            color: var(--color-accent);
        }

        /* Responsive Adjustments (adaptados do login.php) */
        @media (max-width: 992px) {
            .pricing-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 25px;
            }
            .hero h1 {
                font-size: 2.8em;
            }
            .hero p {
                font-size: 1.2em;
            }
            h2 {
                font-size: 2.2em;
            }
            h3 {
                font-size: 1.8em;
            }
            .plan-card {
                min-height: 500px; /* Adjust min-height for smaller screens if needed */
            }
        }

        @media (max-width: 768px) {
            h1 { font-size: 2.2em; }
            h2 { font-size: 1.8em; }
            h3 { font-size: 1.5em; }

            .section-padding { padding: 60px 0; }
            .section-padding-sm { padding: 30px 0; }

            .hero {
                padding: 60px 0;
                min-height: 300px;
            }

            .pricing-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            .plan-card {
                padding: 25px;
                min-height: auto; /* Allow height to be flexible on small screens */
            }
            .plan-price {
                font-size: 2.2em;
            }

            .auth-container {
                padding: 25px;
            }
            .auth-container::before, .auth-container::after {
                font-size: 150px; /* Smaller background icons on mobile */
                top: -10px;
                left: -10px;
                bottom: -10px;
                right: -10px;
            }
            .original-price {
                font-size: 1.5em; /* Smaller on mobile */
            }
            .free-promo {
                font-size: 1.2em; /* Smaller on mobile */
            }
        }
    </style>
</head>
<body>

    <!-- Navbar (Barra de Navegação - Copiado do login.php) -->
    <nav class="navbar">
        <div class="container">
            <div class="navbar-brand-group">
                <a href="login.php" class="navbar-brand pnae">
                    <i class="fas fa-utensils"></i>NutriPNAE
                </a>
                <a href="restaurantes.php" class="navbar-brand nutrigestor">
                    <i class="fas fa-concierge-bell"></i>NutriGestor
                </a>
                <a href="nutridev.php" class="navbar-brand nutridev">
                    <i class="fas fa-laptop-code"></i>NutriDEV
                </a>
            </div>
            <!-- Botão do Toggler (para navegação mobile) -->
            <!-- Removido toggler e navbar-nav para simplificar em Register, já que não há navegação interna tão complexa -->
            <ul class="navbar-nav" id="navbar-nav" style="display: flex; flex-direction: row; align-items: center; gap: 8px;">
                <li><a href="login.php" class="btn btn-outline-primary">Entrar</a></li>
            </ul>
        </div>
    </nav>

    <!-- Hero Section (Adaptada do login.php) -->
    <section class="hero">
        <div class="hero-content">
            <h1>CRIE SUA CONTA E TRANSFORME SUA GESTÃO!</h1>
            <p>Escolha a solução ideal para você e comece sua jornada de eficiência e excelência hoje mesmo. <br>Temos opções para cada necessidade profissional.</p>
        </div>
    </section>

    <?php if ($db_connection_error): ?>
        <div class="container">
            <div class="error-db-container">
                <h1><i class="fas fa-database"></i>Erro Crítico de Conexão</h1>
                <p>Não foi possível conectar ao banco de dados. O registro não pode ser concluído neste momento.</p>
                <p><small>(Detalhes técnicos foram registrados. Por favor, tente novamente mais tarde.)</small></p>
            </div>
        </div>
    <?php else: ?>
        <section class="pricing-section section-padding" id="pricing-section">
            <div class="container">
                <h2 class="text-center">Nossos Planos e Produtos para Você</h2>
                <p class="text-center">Escolha a solução que melhor se adapta à sua necessidade profissional. Flexibilidade e autonomia para seu sucesso!</p>

                <div class="pricing-grid">
                    <!-- Plano NutriPNAE -->
                    <div class="plan-card pnae-plan">
                        <div class="plan-header">
                            <div class="plan-icon"><i class="fas fa-school"></i></div>
                            <h3 class="plan-name">Plataforma NutriPNAE</h3>
                            <div class="plan-price-wrapper">
                                <div class="original-price">
                                    <span class="currency">R$</span>30<span class="period">/mês</span>
                                </div>
                                <div class="free-promo">GRATUITO POR TEMPO LIMITADO!</div>
                            </div>
                            <p class="plan-description">Solução completa para nutricionistas do PNAE. Otimize a gestão, planeje cardápios e garanta a conformidade.</p>
                        </div>
                        <ul class="plan-features">
                            <li><i class="fas fa-check-circle"></i> <strong>3 Meses Grátis</strong> para nutricionistas do PNAE (comprovação necessária após o cadastro).</li>
                            <li><i class="fas fa-check-circle"></i> Acesso completo à plataforma NutriPNAE.</li>
                            <li><i class="fas fa-check-circle"></i> Cardápios Inteligentes (FNDE) e Fichas Técnicas.</li>
                            <li><i class="fas fa-check-circle"></i> Relatórios de Custos e Prestação de Contas.</li>
                            <li><i class="fas fa-check-circle"></i> Suporte prioritário via e-mail.</li>
                            <li><i class="fas fa-check-circle"></i> Assinatura Anual: <span class="original-price-inline">R$ 297,00</span> <strong class="free-text-inline">GRÁTIS!</strong></li>
                            <li class="item-title"><i class="fas fa-book"></i> Ebook: "Dominando o NutriPNAE"
                                <ul class="item-details">
                                    <li><i class="fas fa-arrow-right"></i> <strong class="free-text-inline">GRÁTIS</strong> com esta assinatura.</li>
                                    <li><i class="fas fa-arrow-right"></i> Guia detalhado e prático para capacitação e otimização de processos para nutricionistas do PNAE.</li>
                                </ul>
                            </li>
                        </ul>
                        <div class="plan-cta">
                            <a href="#registration-form-section" class="btn btn-primary btn-block">Assinar NutriPNAE</a>
                        </div>
                    </div>

                    <!-- Plano NutriGestor -->
                    <div class="plan-card nutrigestor-plan">
                        <div class="plan-header">
                            <div class="plan-icon"><i class="fas fa-concierge-bell"></i></div>
                            <h3 class="plan-name">Plataforma NutriGestor</h3>
                            <div class="plan-price-wrapper">
                                <div class="original-price">
                                    <span class="currency">R$</span>49,90<span class="period">/mês</span>
                                </div>
                                <div class="free-promo">GRATUITO POR TEMPO LIMITADO!</div>
                            </div>
                            <p class="plan-description">Gestão estratégica para restaurantes e UANs. Controle CMV, otimize estoque e impulsione a lucratividade.</p>
                        </div>
                        <ul class="plan-features">
                            <li><i class="fas fa-check-circle"></i> <strong>1 Mês Grátis de Teste.</strong></li>
                            <li><i class="fas fa-check-circle"></i> Acesso completo à plataforma NutriGestor.</li>
                            <li><i class="fas fa-check-circle"></i> Controle de CMV, Estoque e Faturamento.</li>
                            <li><i class="fas fa-check-circle"></i> Análise de DRE e Padronização de Receitas.</li>
                            <li><i class="fas fa-check-circle"></i> Suporte prioritário via e-mail e chat.</li>
                            <li><i class="fas fa-check-circle"></i> Assinatura Anual: <span class="original-price-inline">R$ 490,00</span> <strong class="free-text-inline">GRÁTIS!</strong></li>
                            <li class="item-title"><i class="fas fa-book"></i> Ebook: "Otimize com NutriGestor"
                                <ul class="item-details">
                                    <li><i class="fas fa-arrow-right"></i> <span class="original-price-inline">R$ 30,00</span> <strong class="free-text-inline">GRÁTIS</strong> com esta assinatura.</li>
                                    <li><i class="fas fa-arrow-right"></i> Estratégias para lucratividade e otimização para nutricionistas gestores de UAN.</li>
                                </ul>
                            </li>
                        </ul>
                        <div class="plan-cta">
                            <a href="#registration-form-section" class="btn btn-nutrigestor-red btn-block">Assinar NutriGestor</a>
                        </div>
                    </div>

                    <!-- Produtos Digitais NutriDEV -->
                    <div class="plan-card nutridev-product">
                        <div class="plan-header">
                            <div class="plan-icon"><i class="fas fa-laptop-code"></i></div>
                            <h3 class="plan-name">Plataforma NutriDEV</h3>
                            <div class="plan-price-wrapper">
                                <div class="original-price">
                                    <span class="currency">R$</span>100,00<span class="period">/vitalício</span>
                                </div>
                                <div class="free-promo">GRATUITO POR TEMPO LIMITADO!</div>
                            </div>
                            <p class="plan-description">Autonomia digital para nutricionistas. Construa sua presença online sem precisar de programação.</p>
                        </div>
                        <ul class="plan-features">
                            <li class="item-title"><i class="fas fa-globe"></i> Acesso Vitalício: Comunidade NutriDEV Online
                                <ul class="item-details">
                                    <li><i class="fas fa-arrow-right"></i> Fórum de Perguntas/Respostas ativo para suporte.</li>
                                    <li><i class="fas fa-arrow-right"></i> Mais de <strong>50 códigos-modelo</strong> prontos para sites e ferramentas.</li>
                                    <li><i class="fas fa-arrow-right"></i> <strong>20+ vídeo-aulas</strong> completas de desenvolvimento sem código (usando IAs gratuitas).</li>
                                    <li><i class="fas fa-arrow-right"></i> Sugestões de estrutura/host/banco de dados (hospedagem não inclusa).</li>
                                    <li><i class="fas fa-arrow-right"></i> Material para criar qualquer plataforma/site do zero, sem programação.</li>
                                </ul>
                            </li>
                        </ul>
                        <div class="plan-cta">
                            <a href="#registration-form-section" class="btn btn-nutridev btn-block">Saiba Mais / Criar Conta</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="registration-form-section section-padding" id="registration-form-section">
            <div class="container">
                <div class="auth-container">
                    <?php if ($registration_success): ?>
                        <div class="success-message">
                            <p><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($_SESSION['success_message'] ?? 'Registro realizado com sucesso!'); ?></p>
                            <p>Clique aqui para <a href="login.php">fazer login</a> e explorar a plataforma.</p>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php else: ?>
                        <h2><i class="fas fa-user-plus"></i>Crie Sua Conta <span class="beta-tag">BETA</span></h2>
                        <p class="form-subtitle">Preencha os campos abaixo para começar sua jornada.</p>
                        <p class="beta-info-text">
                            Estamos em fase Beta! Ao criar sua conta agora, você terá acesso gratuito e limitado a todas as plataformas para validação e análise de dados. Aproveite esta oportunidade!
                        </p>

                        <?php if (!empty($errors)): ?>
                            <div class="error-message">
                                <p style="font-weight: bold; margin-bottom: 0.6rem;">Oops! Verifique os seguintes pontos:</p>
                                <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>#registration-form-section" method="post" novalidate>
                            <div class="form-group">
                                <label for="username">Nome de Usuário (mín. 3 caracteres):</label>
                                <input type="text" id="username" name="username" class="auth-input" value="<?php echo htmlspecialchars($username_input); ?>" required autofocus>
                            </div>
                            <div class="form-group">
                                <label for="email">Seu Melhor Email:</label>
                                <input type="email" id="email" name="email" class="auth-input" value="<?php echo htmlspecialchars($email_input); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Crie uma Senha Segura (mín. 6 caracteres):</label>
                                <input type="password" id="password" name="password" class="auth-input" required>
                            </div>
                            <div class="form-group">
                                <label for="password_confirm">Confirme sua Senha:</label>
                                <input type="password" id="password_confirm" name="password_confirm" class="auth-input" required>
                            </div>
                            <input type="hidden" name="register_user" value="1">
                            <button type="submit" class="btn btn-primary btn-lg btn-block">Registrar e Iniciar Teste</button>
                        </form>
                        <p class="auth-link">Já possui uma conta? <a href="login.php">Faça login aqui</a></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Footer (Rodapé - Copiado do login.php) -->
    <footer class="footer-simple">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> NutriPNAE & NutriGestor. Todos os direitos reservados.</p>
            <p><a href="politica-privacidade.php">Política de Privacidade</a> | <a href="termos-uso.php">Termos de Uso</a></p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log("Página de registro com planos (revisada) carregada.");

            const planButtons = document.querySelectorAll('.plan-card .btn'); // Seleciona todos os botões de plano
            const registrationFormSection = document.getElementById('registration-form-section');

            // Adiciona evento de clique para rolagem suave ao formulário de registro
            planButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    // Impede o comportamento padrão do link, se houver
                    if (this.getAttribute('href') === '#registration-form-section') {
                        e.preventDefault();
                        if (registrationFormSection) {
                            const elementPosition = registrationFormSection.getBoundingClientRect().top + window.pageYOffset;
                            const offsetPosition = elementPosition - 80; // 80px de margem acima do formulário

                            window.scrollTo({
                                top: offsetPosition,
                                behavior: 'smooth'
                            });

                            // Foca no primeiro campo do formulário após a rolagem
                            setTimeout(() => {
                                const usernameField = document.getElementById('username');
                                if (usernameField && window.getComputedStyle(usernameField).display !== 'none') {
                                    usernameField.focus({preventScroll: true});
                                }
                            }, 750);
                        }
                    }
                });
            });

            // Rola para a seção de registro se a página for carregada após um POST (e.g., erro de validação ou sucesso)
            <?php if (($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_user'])) || $registration_success ): ?>
            if (registrationFormSection) {
                 const elementPosition = registrationFormSection.getBoundingClientRect().top + window.pageYOffset;
                 const offsetPosition = elementPosition - 80;
                 window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
            }
            <?php endif; ?>
        });
    </script>

</body>
</html>
