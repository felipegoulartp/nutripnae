<?php
// cardapio_auto/login.php

// --- Bloco Padronizado de Configuração de Sessão ---
// Define o caminho do cookie de sessão. Usar '/' significa que o cookie estará disponível em todo o domínio.
$session_cookie_path = '/';
// Define um nome único para a sessão para evitar conflitos com outras aplicações no mesmo servidor.
$session_name = "CARDAPIOSESSID";

// Inicia a sessão APENAS se ela ainda não estiver iniciada.
// Isso previne avisos de 'session already started' se o script for incluído múltiplas vezes.
if (session_status() === PHP_SESSION_NONE) {
    // Configura os parâmetros do cookie de sessão para maior segurança.
    // 'lifetime' => 0: O cookie expira quando o navegador é fechado.
    // 'path' => $session_cookie_path: O caminho definido acima.
    // 'domain' => $_SERVER['HTTP_HOST'] ?? '': Garante que o cookie só seja enviado para o domínio atual,
    //                                         seguro contra falsificação.
    // 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on': O cookie só será enviado em HTTPS.
    // 'httponly' => true: O cookie não é acessível por JavaScript, protegendo contra ataques XSS.
    // 'samesite' => 'Lax': Proteção contra CSRF (Cross-Site Request Forgery), permitindo alguns envios.
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $session_cookie_path,
        'domain' => $_SERVER['HTTP_HOST'] ?? '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
// Define o nome da sessão ANTES de iniciar, caso não tenha sido iniciada.
session_name($session_name);
// Inicia a sessão novamente, se ainda não estiver iniciada (redundância segura).
if (session_status() === PHP_SESSION_NONE) {
     session_start();
}

// Configurações de erro para depuração e log.
// error_reporting(E_ALL): Reporta todos os erros PHP.
error_reporting(E_ALL);
// ini_set('display_errors', 0): EM PRODUÇÃO, mantenha 0 para não exibir erros diretamente no navegador por segurança.
ini_set('display_errors', 0);
// ini_set('log_errors', 1): Habilita o log de erros em arquivo.
ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php_error.log'): Define o caminho para o arquivo de log de erros.
ini_set('error_log', __DIR__ . '/php_error.log');
// Registra o acesso à página no log de erros para rastreamento.
error_log("--- Acesso a login.php (LP NutriPNAE) --- | SID: " . session_id());

// Inicializa variáveis para mensagens de erro/sucesso e input do usuário.
$errors = []; // Array para armazenar mensagens de erro.
$username_input = ''; // Armazena o nome de usuário digitado pelo usuário.
$success_message = ''; // Mensagem de sucesso (para login ou outras ações, se aplicável).
$page_title = "NutriPNAE - Inovação em Gestão da Alimentação Escolar"; // Título da página atualizado.
$db_connection_error = false; // Flag para indicar se houve um erro na conexão com o banco de dados.
$pdo = null; // Inicializa a variável PDO como nula.

// --- Bloco de Conexão com o Banco de Dados ---
// IMPORTANTE: Certifique-se de que 'includes/db_connect.php' existe e está configurado corretamente.
// Este é o ponto mais comum de falha no login se o banco de dados não estiver conectado ou credenciais estiverem erradas.
try {
    // Tenta incluir o arquivo de conexão. Se o 'db_connect.php' lançar uma PDOException (erro de conexão),
    // ela será capturada pelo bloco 'catch' abaixo.
    include_once __DIR__ . '/includes/db_connect.php';
    // Após a inclusão, a variável $pdo deve estar definida e conter a instância PDO da conexão.
    // Se $pdo ainda for nulo aqui, significa que 'db_connect.php' não conseguiu estabelecer a conexão.
    if (!isset($pdo) || $pdo === null) {
        throw new PDOException("db_connect.php falhou em estabelecer a conexão PDO.");
    }
} catch (PDOException $e) {
    // Se a conexão falhar (seja por exceção de 'db_connect.php' ou a verificação acima),
    // define a flag de erro e adiciona uma mensagem amigável ao array de erros.
    $db_connection_error = true;
    error_log("Erro Crítico de Conexão com o Banco de Dados: " . $e->getMessage());
    $errors[] = "Erro interno do servidor: Não foi possível acessar o serviço de login no momento. Por favor, entre em contato com o suporte.";
    $pdo = null; // Garante que $pdo seja nulo se a conexão falhou.
}

// Redireciona o usuário para a página inicial se já estiver logado.
// Isso evita que usuários autenticados acessem a página de login.
if (isset($_SESSION['user_id'])) {
    header('Location: home.php'); // Redireciona para a página principal (home.php).
    exit(); // Termina o script.
}

// Processa o formulário de login.
// Verifica se a requisição é um POST e se o botão de login foi clicado.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    // Apenas tenta processar o login se não houver erro de conexão com o DB e $pdo estiver disponível.
    if (!$db_connection_error && $pdo) {
        // Sanatiza o nome de usuário (tratado como e-mail para fins de filtro).
        $username_input = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_EMAIL);
        // Obtém a senha bruta. FILTER_UNSAFE_RAW é usado porque password_verify() espera a senha bruta.
        $password = filter_input(INPUT_POST, 'password', FILTER_UNSAFE_RAW);

        // Validação básica dos campos: verifica se usuário e senha não estão vazios.
        if (empty($username_input) || empty($password)) {
            $errors[] = "Por favor, preencha todos os campos para fazer login.";
        } else {
            try {
                // Prepara a consulta SQL para buscar o usuário pelo username.
                // Usar prepared statements previne ataques de SQL Injection.
                $stmt = $pdo->prepare("SELECT id, username, password_hash, is_active FROM cardapio_usuarios WHERE username = :username");
                // Executa a consulta, passando o nome de usuário como um parâmetro seguro.
                $stmt->execute([':username' => $username_input]);
                // Busca a linha do usuário como um array associativo.
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Verifica se o usuário existe e se a senha fornecida corresponde ao hash armazenado.
                // password_verify() é a função segura para verificar senhas hashed com password_hash().
                if ($user && password_verify($password, $user['password_hash'])) {
                    // Se o usuário existe e a senha está correta, verifica se a conta está ativa.
                    if ($user['is_active']) {
                        // Login bem-sucedido: define as variáveis de sessão.
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        // Regenera o ID da sessão para prevenir ataque de fixação de sessão.
                        session_regenerate_id(true);
                        // Redireciona para a página principal.
                        header('Location: home.php');
                        exit(); // Termina o script.
                    } else {
                        // Se a conta estiver inativa.
                        $errors[] = "Sua conta está inativa. Por favor, entre em contato com o suporte.";
                    }
                } else {
                    // Se o usuário não existe ou a senha está incorreta.
                    $errors[] = "Nome de usuário ou senha incorretos.";
                }
            } catch (PDOException $e) {
                // Loga qualquer erro de PDO que ocorra durante a execução da query de login.
                error_log("Erro de PDO ao tentar login na query: " . $e->getMessage());
                // Exibe uma mensagem genérica de erro para o usuário por segurança.
                $errors[] = "Ocorreu um erro ao tentar fazer login. Por favor, tente novamente mais tarde.";
            }
        }
    } else if ($db_connection_error) {
        // Se a conexão com o DB falhou antes, a mensagem de erro já foi adicionada.
        // Não é necessário adicionar outra mensagem aqui.
    }
}

// Processa o formulário de contato.
// Verifica se a requisição é um POST e se o botão de envio do formulário de contato foi clicado.
$contact_form_success_message = ''; // Mensagem de sucesso do formulário de contato.
$contact_form_error_message = ''; // Mensagem de erro do formulário de contato.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact_form'])) {
    // Sanitiza e valida os inputs do formulário de contato.
    $contact_name = filter_input(INPUT_POST, 'contact_name', FILTER_SANITIZE_STRING);
    $contact_email = filter_input(INPUT_POST, 'contact_email', FILTER_VALIDATE_EMAIL);
    $contact_phone = filter_input(INPUT_POST, 'contact_phone', FILTER_SANITIZE_STRING);
    $contact_message = filter_input(INPUT_POST, 'contact_message', FILTER_SANITIZE_STRING);
    $contact_subject = "Mensagem do Formulário de Contato NutriPNAE"; // Assunto atualizado

    // Validação dos campos obrigatórios do formulário de contato.
    if (empty($contact_name) || empty($contact_email) || empty($contact_message)) {
        $contact_form_error_message = "Por favor, preencha nome, e-mail e mensagem.";
    } elseif (!$contact_email) {
        $contact_form_error_message = "Por favor, insira um e-mail válido.";
    } else {
        // Prepara o corpo do e-mail.
        $to = "contato@nutripnae.com"; // SUBSTITUA PELO SEU EMAIL DE CONTATO REAL
        $headers = "From: " . $contact_email . "\r\n";
        $headers .= "Reply-To: " . $contact_email . "\r\n";
        $headers .= "Content-type: text/plain; charset=UTF-8\r\n";

        $email_body = "Nome: " . htmlspecialchars($contact_name) . "\n";
        $email_body .= "Email: " . htmlspecialchars($contact_email) . "\n";
        $email_body .= "Telefone: " . htmlspecialchars(($contact_phone ? $contact_phone : 'Não fornecido')) . "\n\n";
        $email_body .= "Mensagem:\n" . htmlspecialchars($contact_message);

        // Tenta enviar o e-mail.
        if (mail($to, $contact_subject, $email_body, $headers)) {
            $contact_form_success_message = "Mensagem enviada com sucesso! Em breve entraremos em contato.";
            // Limpa os campos do formulário após o sucesso para evitar reenvio.
            $_POST['contact_name'] = '';
            $_POST['contact_email'] = '';
            $_POST['contact_phone'] = '';
            $_POST['contact_message'] = '';
        } else {
            $contact_form_error_message = "Ocorreu um erro ao enviar sua mensagem. Por favor, tente novamente mais tarde.";
            error_log("Erro ao enviar email do formulário de contato para: " . $contact_email);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="NutriPNAE: solução completa para gestão da alimentação escolar. Planejamento de cardápios, controle de custos, conformidade com FNDE e mais.">
    <meta name="keywords" content="NutriPNAE, alimentação escolar, PNAE, gestão de cardápio, nutricionista, conformidade FNDE, custos, estoque, merenda escolar, software para nutricionistas">
    <meta name="author" content="NutriPNAE">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <!-- Corrigindo o erro 404 para site.webmanifest com um fallback em data URI -->
    <link rel="manifest" href="/site.webmanifest" onerror="this.onerror=null;this.href='data:application/json;base64,eyJpY29ucyI6IFt7InNyYyI6ICJodHRwczovL3BsYWNlaG9sZC5jby8xOTJ4MTkyLzAwMDAwMC9GRkZGRkY%2FdGV4dD1BcHBLb24iLCJzaXplcyI6ICIxOTJ4MTkyIiwidHlwZSI6ICJpbWFnZS9wbmciLCJwdXJwb3NlcyI6ICJhbnlttmFza2FibGUifV0sIm5hbWUiOiAiRmFsbGJhY2sgQXBwIiwic2hvcnRfbmFtZSI6ICJBcHBLb24iLCJzdGFydF91cmwiOiAiLi8iLCJkaXNwbGF5IjogInN0YW5kbG9uZSIsImJhY2tncm91bmRfY29sb3IiOiAiI2ZmZmZmZiIsInRoZW1lX2NvbG9yOiAiIzAwMDAwMCJ9fQ%3D%3D';">
    <meta property="og:title" content="NutriPNAE - Inovação em Gestão da Alimentação Escolar">
    <meta property="og:description" content="Sua solução completa para gestão da alimentação escolar, otimização de cardápios e conformidade com o FNDE.">
    <meta property="og:image" content="https://nutripnae.com/images/og-image.jpg"> <!-- Substitua pela URL da sua imagem -->
    <meta property="og:url" content="https://nutripnae.com/">
    <meta property="og:type" content="website">

    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Google Fonts -->
    <!-- Garantindo que os pesos 400 (normal) e 700 (bold) sejam carregados para Roboto e Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        /* Define a fonte Roboto para todo o corpo do documento. */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f0f4f8; /* Cor de fundo suave */
        }

        /* --- CORREÇÃO 1: Garante o negrito em todos os textos marcados com <strong> ou <b> --- */
        /* Aplica um peso de fonte forte para garantir que o negrito seja visível. */
        /* O '!important' é usado para dar a esta regra a maior prioridade. */
        strong, b {
            font-weight: 700 !important; /* Força o peso da fonte para 700 (negrito) */
        }

        /* --- Ajustes para imagens e blocos de funcionalidades para alinhar com a seção de desafios/soluções --- */
        /* Garante que as imagens dentro dos itens de funcionalidade tenham o mesmo tamanho e estilo. */
        .feature-item img {
            width: 100%; /* Ocupa a largura total do seu container imediato */
            height: 200px; /* Altura fixa para consistência com 'solution-block-detailed' */
            object-fit: cover; /* 'cover' para preencher o espaço, pode cortar a imagem */
            background-color: var(--color-background-gray); /* Cor de fundo para combinar */
            margin-bottom: 20px; /* Mantém a margem inferior */
            display: block; /* Garante que 'margin: auto' funcione para centralizar */
            border-radius: var(--border-radius); /* Aplica border-radius em todos os cantos */
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }


        :root {
            --color-primary: #2196F3; /* Azul vibrante */
            --color-primary-dark: #1976D2; /* Azul mais escuro */
            --color-primary-light: #BBDEFB; /* Azul mais claro */
            --color-primary-xtralight: #EBF4FF; /* Azul extra claro */
            --color-accent: #FFC107; /* Amarelo dourado */
            --color-accent-dark: #FFA000; /* Amarelo mais escuro */
            --color-text-dark: #333; /* Texto escuro padrão */
            --color-text-light: #f8f8f8; /* Texto claro para fundos escuros */
            --color-background-light: #f2f2f2; /* Fundo claro */
            --color-background-gray: #e0e0e0; /* Fundo cinza suave */
            --color-background-dark: #3f51b5; /* Fundo azul escuro */
            --color-error: #D32F2F; /* Vermelho para erros */
            --color-success: #388E3C; /* Verde para sucesso */
            /* Cores específicas do NutriGestor (mantidas para o link no navbar) */
            --color-nutrigestor: #EA1D2C; /* iFood Red */
            --color-nutrigestor-dark: #B51522; /* Darker iFood Red */
            --font-main: 'Roboto', sans-serif;
            --font-headings: 'Poppins', sans-serif;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
        }

        body {
            font-family: var(--font-main);
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            color: var(--color-text-dark);
            background-color: var(--color-background-light);
            line-height: 1.6;
        }

        *, *::before, *::after {
            box-sizing: inherit;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: var(--font-headings);
            margin-top: 0;
            margin-bottom: 0.5em;
        }

        /* Títulos de seção principais (h2) */
        h2 {
            font-size: 2.2em;
            line-height: 1.3;
            color: #000; /* Cor preta para os títulos principais */
            position: relative;
            padding-bottom: 15px; /* Espaço para o detalhe dourado */
            margin-bottom: 25px; /* Espaço abaixo do título e do detalhe */
            text-align: center; /* Garantir centralização para o detalhe */
        }
        h2::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            width: 80px; /* Largura do detalhe dourado */
            height: 4px; /* Altura do detalhe dourado */
            background-color: var(--color-accent); /* Cor dourada */
            border-radius: 2px;
        }

        /* Estilo para outros títulos de seção (h3, h4) com detalhe dourado */
        .features-section h3.section-subtitle, /* Adicionado para a nova seção de funcionalidades */
        .contact-section h3.section-subtitle,
        .faq-section h3.section-subtitle {
            font-size: 1.8em; /* Tamanho ajustado para subtítulos de seção */
            line-height: 1.4;
            color: #000; /* Preto para todos os títulos de seção */
            position: relative;
            padding-bottom: 10px; /* Espaço para o detalhe dourado */
            margin-bottom: 20px; /* Espaço abaixo do título e do detalhe */
            text-align: center; /* Garantir centralização para o detalhe */
        }
        .features-section h3.section-subtitle::after, /* Adicionado para a nova seção de funcionalidades */
        .contact-section h3.section-subtitle::after,
        .faq-section h3.section-subtitle::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            width: 60px; /* Largura menor para subtítulos */
            height: 3px; /* Altura menor para subtítulos */
            background-color: var(--color-accent); /* Cor dourada */
            border-radius: 1.5px;
        }

        /* Títulos de blocos dentro das seções (h3, h4, h5) */
        .segment-card h3 {
            font-size: 1.8em;
            margin-bottom: 15px;
            color: #000; /* Cor preta para títulos de card/feature */
            text-align: center; /* Mantido centralizado para segment cards */
        }

        .feature-card .feature-header h3 { /* Ajuste para o título do card de funcionalidade */
            font-size: 1.6em;
            margin: 0;
            color: #000;
        }
        .feature-card h5 { /* Título das funcionalidades essenciais dentro do details */
            font-size: 1.3em;
            color: #000;
            margin-top: 0;
            margin-bottom: 0;
            padding-bottom: 0;
        }


        h1 { font-size: 2.8em; line-height: 1.2; color: #fff; }
        h4 { font-size: 1.5em; }

        p {
            margin-bottom: 1em;
            font-weight: normal; /* Garante que parágrafos não sejam negrito por padrão */
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
        .text-primary { color: var(--color-primary); }
        .text-accent { color: var(--color-accent); }
        .bg-primary { background-color: var(--color-primary); }
        .bg-primary-dark { background-color: var(--color-primary-dark); }
        .bg-accent { background-color: var(--color-accent); }
        .bg-light-gray { background-color: var(--color-background-gray); }
        .section-padding { padding: 80px 0; }
        .section-padding-sm { padding: 40px 0; }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            font-size: 1em;
            font-weight: 600;
            border-radius: var(--border-radius);
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            color: var(--color-text-light);
            border: none;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--color-primary-dark), #1565C0);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }

        .btn-outline {
            background-color: transparent;
            color: var(--color-primary);
            border: 2px solid var(--color-primary);
        }

        .btn-outline:hover {
            background-color: var(--color-primary);
            color: var(--color-text-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        .btn-accent {
            background: linear-gradient(135deg, var(--color-accent), var(--color-accent-dark));
            color: var(--color-text-dark);
            border: none;
        }

        .btn-accent:hover {
            background: linear-gradient(135deg, var(--color-accent-dark), #FF8F00);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }

        .btn-lg {
            padding: 15px 30px;
            font-size: 1.1em;
        }

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

        /* Navbar - Ajustado para o padrão do home.php */
        .navbar {
            background-color: var(--color-bg-white); /* Fundo branco para o navbar */
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
            /* Removido gap para ajustar espaçamento individualmente */
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
            color: var(--color-primary-dark); /* Cor do logo NutriPNAE para azul escuro */
            transition: color var(--transition-speed);
            margin-right: 25px; /* Espaçamento entre os logos no navbar */
        }
        .navbar-brand i {
            margin-right: 8px;
            font-size: 1.2em;
            color: var(--color-primary); /* Cor do ícone NutriPNAE para azul */
        }
        .navbar-brand.pnae:hover {
            color: var(--color-accent-dark); /* Cor do texto dourado escuro no hover */
        }
        .navbar-brand.pnae:hover i {
            color: var(--color-accent); /* Cor do ícone dourado no hover */
        }

        /* Estilo para o logo NutriGestor no menu superior (mantido como no home.php) */
        .navbar-brand.nutrigestor {
            color: var(--color-nutrigestor); /* Cor do logo NutriGestor para vermelho */
        }
        .navbar-brand.nutrigestor i {
            color: var(--color-nutrigestor); /* Cor do ícone NutriGestor para vermelho */
        }
        .navbar-brand.nutrigestor:hover {
            color: var(--color-nutrigestor-dark); /* Cor do texto vermelho escuro no hover */
        }
        .navbar-brand.nutrigestor:hover i {
            color: var(--color-nutrigestor-dark); /* Cor do ícone vermelho escuro no hover */
        }

        .navbar-toggler {
            display: none; /* Escondido por padrão, visível apenas em mobile */
            background: none;
            border: none;
            font-size: 1.8em;
            cursor: pointer;
            color: var(--color-primary-dark); /* Cor do ícone do toggler */
        }

        .navbar-nav {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
            align-items: center;
            gap: 8px;
        }

        .navbar-nav li a,
        .navbar-nav li .btn {
            font-family: var(--font-main);
            font-weight: 500;
            font-size: 0.85em;
            padding: 8px 15px;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
            text-transform: uppercase;
            text-decoration: none;
            white-space: nowrap;
        }

        .navbar-nav li a {
            color: var(--color-text-dark); /* Cor do texto dos links de navegação */
            background-color: transparent;
            border: 1px solid transparent;
        }

        .navbar-nav li a:hover {
            background-color: var(--color-primary-xtralight); /* Fundo azul claro no hover */
            border-color: var(--color-primary-light); /* Borda azul clara no hover */
            color: var(--color-primary-dark); /* Texto azul escuro no hover */
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .navbar-nav li #open-login-modal-btn {
            background-color: transparent;
            color: var(--color-primary);
            border: 2px solid var(--color-primary);
            box-shadow: none;
        }
        .navbar-nav li #open-login-modal-btn:hover {
            background-color: var(--color-primary);
            color: var(--color-text-light);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        .navbar-nav li .btn-primary {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            color: var(--color-text-light);
            border: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .navbar-nav li .btn-primary:hover {
            background: linear-gradient(135deg, var(--color-primary-dark), #1565C0);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }


        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('images/fundo_hero_nutripnae.png') no-repeat center center/cover;
            color: var(--color-text-light);
            padding: 120px 0;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 500px;
        }

        .hero-content {
            max-width: 900px;
            padding: 0 20px;
        }

        .hero h1 {
            color: #fff;
            font-size: 3.8em;
            margin-bottom: 25px;
            line-height: 1.1;
        }

        .hero p {
            font-size: 1.5em;
            margin-bottom: 40px;
            opacity: 0.95;
            line-height: 1.6;
        }

        .hero .btn-group {
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        /* Funcionalidades do NutriPNAE - Nova Seção de Módulos */
        .features-section {
            padding: 80px 0;
            background-color: #fff;
            text-align: center;
        }
        .features-grid {
            display: flex; /* Usar flexbox para empilhar horizontalmente */
            flex-direction: column; /* Empilha os cards verticalmente */
            gap: 40px; /* Aumentado o espaçamento entre os cards */
            margin-top: 50px;
            align-items: center; /* Centraliza os cards na página */
        }
        .feature-card {
            background-color: var(--color-background-light);
            border-radius: var(--border-radius);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15); /* Sombra mais destacada */
            padding: 30px;
            text-align: left;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-top: 5px solid var(--color-primary); /* Borda superior azul */
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            max-width: 900px; /* Largura maior para os blocos horizontais */
            width: 100%; /* Garante que ocupe a largura máxima permitida */
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.25);
        }
        .feature-card .feature-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .feature-card .icon-box {
            min-width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.8em;
            flex-shrink: 0;
            background-color: var(--color-primary-light);
            color: var(--color-primary-dark);
        }
        .feature-card h3 {
            font-size: 1.6em;
            margin: 0;
            color: #000;
        }
        .feature-card .feature-image {
            width: 100%;
            height: 200px; /* Altura fixa para as imagens */
            background-color: var(--color-background-gray);
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .feature-card .feature-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--border-radius);
        }
        .feature-card p {
            font-size: 1em;
            line-height: 1.6;
            margin-bottom: 15px;
            color: #555;
            flex-grow: 1; /* Permite que o parágrafo ocupe espaço flexível */
        }
        .feature-card details {
            margin-top: 15px;
            border-top: 1px dashed #eee;
            padding-top: 15px;
        }
        .feature-card details summary {
            font-weight: 600;
            color: var(--color-primary-dark);
            cursor: pointer;
            list-style: none; /* Remove a seta padrão */
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .feature-card details summary::-webkit-details-marker {
            display: none;
        }
        .feature-card details summary i {
            transition: transform 0.2s ease;
        }
        .feature-card details[open] summary i {
            transform: rotate(90deg);
        }
        .feature-card details ul {
            list-style: none;
            padding: 10px 0 0 0;
            margin: 0;
        }
        .feature-card details ul li {
            margin-bottom: 8px;
            position: relative;
            padding-left: 25px;
            font-size: 0.95em;
            color: #666;
        }
        .feature-card details ul li i {
            position: absolute;
            left: 0;
            top: 4px;
            font-size: 0.9em;
            color: var(--color-primary);
        }


        /* Testimonials */
        .testimonials-section {
            background-color: var(--color-primary);
            color: var(--color-text-light);
            padding: 80px 0;
            text-align: center;
        }

        .testimonials-section h2 {
            color: #fff;
            margin-bottom: 50px;
        }
        .testimonials-section h2::after {
            background-color: var(--color-accent);
        }

        .testimonial-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            justify-content: center;
        }

        .testimonial-item {
            background-color: #fff;
            color: var(--color-text-dark);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 1px solid #eee;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .testimonial-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
        }

        .testimonial-item blockquote {
            font-size: 1.1em;
            line-height: 1.8;
            margin-bottom: 25px;
            font-style: italic;
            opacity: 0.95;
            color: var(--color-text-dark);
            font-weight: normal;
        }
        .testimonial-item blockquote strong {
            font-weight: bold !important;
        }

        .testimonial-author {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 15px;
        }

        .testimonial-author img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
            border: 3px solid #ccc;
        }

        .author-info {
            text-align: center;
        }

        .author-info h4 {
            color: #000;
            margin: 0;
            font-size: 1.2em;
        }

        .author-info p {
            margin: 0;
            font-size: 0.9em;
            opacity: 0.8;
            color: #555;
        }

        /* Main CTA */
        .main-cta-section {
            background-color: var(--color-background-light);
            padding: 80px 0;
            text-align: center;
        }

        .main-cta-section h2 {
            color: #000;
            margin-bottom: 20px;
        }

        .main-cta-section p {
            font-size: 1.2em;
            max-width: 800px;
            margin: 0 auto 40px auto;
        }

        /* FAQ Section */
        .faq-section {
            padding: 80px 0;
            background-color: var(--color-background-gray);
            text-align: center;
        }

        .faq-section h2 {
            color: #000;
            margin-bottom: 40px;
        }

        .faq-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .faq-item {
            background-color: #fff;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: box-shadow 0.3s ease;
        }
        .faq-item:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .faq-question {
            padding: 20px 25px;
            background-color: var(--color-primary-light);
            color: var(--color-primary-dark);
            font-weight: 600;
            font-size: 1.1em;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: background-color 0.3s ease, border-radius 0.3s ease;
        }

        .faq-question:hover {
            background-color: #cce0ff;
        }

        .faq-question i {
            transition: transform 0.3s ease;
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease-out, padding 0.5s ease-out;
            padding: 0 25px;
            font-size: 0.95em;
            line-height: 1.6;
        }

        .faq-answer.expanded {
            max-height: 600px; /* Ajuste conforme o conteúdo máximo esperado */
            padding: 25px;
        }

        .faq-answer p {
            margin-bottom: 10px;
            color: #555;
            font-weight: normal;
        }
        .faq-answer ul {
            list-style: none;
            padding: 0;
            margin-top: 10px;
        }
        .faq-answer ul li {
            margin-bottom: 8px; /* Ajustado para 8px */
            position: relative;
            padding-left: 25px;
            font-size: 0.95em;
            color: #666;
            font-weight: normal;
        }
        .faq-answer ul li strong {
            font-weight: bold !important;
        }
        .faq-answer ul li i {
            position: absolute;
            left: 0;
            top: 4px;
            font-size: 0.9em;
            color: var(--color-primary-dark);
        }

        .faq-question.expanded {
            border-bottom: none;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .faq-question.expanded i {
            transform: rotate(180deg);
        }


        /* Contact Form */
        .contact-section {
            padding: 80px 0;
            background-color: #fff;
            text-align: center;
        }

        .contact-section h2 {
            color: #000;
            margin-bottom: 40px;
        }

        .contact-form-container {
            max-width: 700px;
            margin: 0 auto;
            background-color: var(--color-background-light);
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: left;
        }

        .contact-form-container .form-group {
            margin-bottom: 20px;
        }

        .contact-form-container label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--color-text-dark);
        }

        .contact-form-container input[type="text"],
        .contact-form-container input[type="email"],
        .contact-form-container input[type="tel"],
        .contact-form-container textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: var(--border-radius);
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .contact-form-container input[type="text"]:focus,
        .contact-form-container input[type="email"]:focus,
        .contact-form-container input[type="tel"]:focus,
        .contact-form-container textarea:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.2);
            outline: none;
        }

        .contact-form-container textarea {
            min-height: 120px;
            resize: vertical;
        }

        .contact-form-container .btn {
            width: 100%;
            margin-top: 10px;
        }

        /* Footer */
        .footer {
            background-color: var(--color-primary-dark);
            color: var(--color-text-light);
            padding: 50px 0;
            text-align: center;
            font-size: 0.9em;
        }

        .footer .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
        }

        .footer-social-links {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 20px;
        }

        .footer-social-links li a {
            color: var(--color-text-light);
            font-size: 1.8em;
            transition: color 0.3s ease, transform 0.3s ease;
        }

        .footer-social-links li a:hover {
            color: var(--color-accent);
            transform: translateY(-3px);
        }

        .footer-nav-links {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .footer-nav-links li a {
            color: var(--color-text-light);
            font-weight: 400;
            transition: color 0.3s ease;
        }

        .footer-nav-links li a:hover {
            color: var(--color-accent);
        }

        .footer p {
            margin: 0;
            opacity: 0.9;
        }

        /* Modal Login */
        .login-modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .login-modal.active {
            display: flex;
            opacity: 1;
            visibility: visible;
        }

        .login-modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 450px;
            position: relative;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
        }

        .login-modal.active .login-modal-content {
            transform: translateY(0);
        }

        .close-button {
            color: #aaa;
            position: absolute;
            top: 15px;
            right: 25px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-button:hover,
        .close-button:focus {
            color: #333;
            text-decoration: none;
        }

        .login-modal-content h2 {
            text-align: center;
            color: var(--color-primary-dark);
            margin-bottom: 25px;
        }

        .login-modal-content .form-group {
            margin-bottom: 15px;
        }

        .login-modal-content label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--color-text-dark);
        }

        .login-modal-content input[type="text"],
        .login-modal-content input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .login-modal-content input[type="text"]:focus,
        .login-modal-content input[type="password"]:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(33, 150, 243, 0.2);
            outline: none;
        }

        .login-modal-content .btn-primary {
            width: 100%;
            padding: 12px;
            font-size: 1.1em;
            margin-top: 20px;
        }

        .login-modal-content .login-options {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9em;
        }

        .login-modal-content .login-options a {
            margin: 0 10px;
            color: var(--color-primary);
        }

        /* Mensagens de erro/sucesso no modal */
        .modal-alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: var(--border-radius);
            text-align: center;
            font-weight: 500;
        }

        .modal-alert.error {
            background-color: #ffebee;
            color: var(--color-error);
            border: 1px solid var(--color-error);
        }

        .modal-alert.success {
            background-color: #e8f5e9;
            color: var(--color-success);
            border: 1px solid var(--color-success);
        }

        /* Custom Message Box (replaces alert()) */
        .custom-message-box-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 10000;
        }
        .message-box-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 400px;
            animation: fadeInScale 0.3s ease-out;
        }
        .message-box-content p {
            margin-bottom: 20px;
            font-size: 1.1em;
            color: #333;
        }
        .message-box-close-btn {
            background-color: var(--color-primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }
        .message-box-close-btn:hover {
            background-color: var(--color-primary-dark);
        }
        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }


        /* Responsive Adjustments */
        @media (max-width: 992px) {
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
            /* Ajuste para o grid de funcionalidades, agora com 1 coluna */
            .features-grid {
                grid-template-columns: 1fr; /* Uma coluna em telas menores */
                max-width: 95%; /* Ajuste a largura máxima para telas menores */
                margin: 50px auto 0 auto; /* Centraliza o grid */
            }

            .testimonial-grid {
                grid-template-columns: 1fr;
            }
            .footer .container {
                flex-direction: column;
                gap: 20px;
            }
        }

        @media (max-width: 768px) {
            h1 { font-size: 2.2em; }
            h2 { font-size: 1.8em; }
            h3 { font-size: 1.5em; }

            .section-padding { padding: 60px 0; }
            .section-padding-sm { padding: 30px 0; }

            .hero {
                padding: 80px 0;
            }

            .contact-form-container {
                padding: 25px;
            }

            .login-modal-content {
                width: 95%;
                padding: 20px;
            }

            /* Navbar mobile adjustments */
            .navbar-nav {
                flex-direction: column;
                position: absolute;
                width: 100%;
                left: 0;
                top: 70px; /* Adjust based on navbar height */
                background-color: #fff;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                padding: 20px 0;
                display: none; /* Hidden by default */
            }
            .navbar-nav.active {
                display: flex; /* Shown when active */
            }
            .navbar-toggler {
                display: block; /* Always show toggler on mobile */
            }
            .navbar-nav li {
                width: 100%;
                text-align: center;
            }
            .navbar-nav li a,
            .navbar-nav li .btn {
                width: calc(100% - 40px); /* Adjust for padding */
                margin: 0 auto;
                padding: 12px 0;
                white-space: normal;
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

    <!-- Navbar Superior -->
    <nav class="navbar">
        <div class="container">
            <div class="navbar-brand-group">
                <!-- Logo NutriPNAE usando ícone Font Awesome -->
                <a href="#home" class="navbar-brand pnae">
                    <i class="fas fa-utensils"></i>NutriPNAE
                </a>
                <!-- Link para NutriGestor, mantido conforme home.php (sem a porcaria do NutriDEV) -->
                <a href="restaurantes.php" class="navbar-brand nutrigestor">
                    <i class="fas fa-concierge-bell"></i>NutriGestor
                </a>
            </div>
            <!-- Botão do Toggler (para navegação mobile) -->
            <button class="navbar-toggler" id="navbar-toggler">
                <i class="fas fa-bars"></i>
            </button>
            <!-- Links da Navegação Principal -->
            <ul class="navbar-nav" id="navbar-nav">
                <li><a href="#funcionalidades">Funcionalidades</a></li>
                <li><a href="#testimonials-section">Depoimentos</a></li>
                <li><a href="#faq-section">FAQ</a></li>
                <li><a href="#contact-section">Contato</a></li>
                <li><button class="btn btn-outline" id="open-login-modal-btn">Entrar</button></li>
                <li><a href="register.php" class="btn btn-primary">Criar Conta</a></li>
            </ul>
        </div>
    </nav>

    <!-- Hero Section (Seção Principal de Destaque) -->
    <section class="hero" id="home">
        <div class="hero-content">
            <h1>Sua Solução Completa e Gratuita para a Gestão da Alimentação Escolar</h1>
            <p>
                O NutriPNAE transforma a gestão da alimentação escolar, garantindo conformidade com o FNDE,
                otimizando cardápios e processos, e elevando a qualidade nutricional. Sua solução para uma
                merenda mais eficiente, transparente e saudável.
                <br><br>Comece a sua jornada de excelência hoje mesmo, <strong>totalmente sem custo</strong>!
            </p>
            <div class="btn-group">
                <a href="register.php" class="btn btn-accent btn-lg">Comece a Usar Gratuitamente</a>
            </div>
        </div>
    </section>

    <!-- Seção "Funcionalidades do NutriPNAE" -->
    <section class="features-section section-padding" id="funcionalidades">
        <div class="container">
            <h2 class="text-center">Funcionalidades do NutriPNAE</h2>
            <p class="text-center">A plataforma NutriPNAE oferece um conjunto robusto de ferramentas para otimizar cada etapa da gestão da alimentação escolar, promovendo eficiência, conformidade e qualidade.</p>
            <div class="features-grid">
                <!-- Módulo do Nutricionista (RT) Card -->
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="icon-box"><i class="fas fa-user-tie"></i></div>
                        <h3>Módulo do Nutricionista (RT)</h3>
                    </div>
                    <div class="feature-image">
                        <img src="images/solucoes_alimentacao_escolar.png" alt="Nutricionista trabalhando">
                    </div>
                    <p>Este módulo é o coração da plataforma para o profissional de nutrição. Permite elaborar cardápios semanais/mensais para cada etapa de ensino, com cálculo nutricional automático e verificação de conformidade com as referências do PNAE. Garante que os cardápios sejam balanceados e adequados a cada faixa etária, com sugestão de porções e combinações.</p>
                    <p>O nutricionista tem acesso a um banco de receitas padronizadas para cadastrar ou consultar fichas técnicas de preparo, controlando ingredientes, quantidades por porção e modo de preparo. Isso facilita a padronização e o cálculo de rendimento e custo estimado por receita.</p>
                    <p>Além disso, o módulo integra a gestão de compras, permitindo planejar quantidades necessárias, gerar listas de compras e emitir editais de chamada pública para agricultura familiar. O controle de estoque centralizado com alertas de mínimo e validade, e a ferramenta de recomendação para priorizar a agricultura familiar, otimizam a aquisição de alimentos.</p>
                    <details>
                        <summary><h5><i class="fas fa-plus-circle"></i> Ver Funcionalidades Essenciais</h5></summary>
                        <ul>
                            <li><i class="fas fa-check-circle"></i> <strong>Planejamento de Cardápios:</strong> Cálculo nutricional automático e adequação às diretrizes do FNDE.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Fichas Técnicas e Receitas:</strong> Banco de receitas padronizadas com cálculo de rendimento e custo.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Gestão de Compras e Estoque Central:</strong> Planejamento de quantidades, listas de compras e controle de estoque.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Prioridade à Agricultura Familiar:</strong> Recomendação de produtos e fornecedores locais.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Relatórios e Prestação de Contas:</strong> Geração automática de relatórios exigidos pelo FNDE.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Monitoramento Nutricional:</strong> Registro de avaliações básicas dos alunos e correlação com a alimentação.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Teste de Aceitabilidade:</strong> Aplicação e compilação de resultados de testes de aceitação de preparações.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Comunicação e Capacitação:</strong> Fórum/chat integrado, biblioteca digital de EAN e cursos online.</li>
                        </ul>
                    </details>
                </div>

                <!-- Módulo do Gestor Escolar (Direção/Equipe da Escola) Card -->
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="icon-box"><i class="fas fa-chalkboard-teacher"></i></div>
                        <h3>Módulo do Gestor Escolar</h3>
                    </div>
                    <div class="feature-image">
                        <img src="https://placehold.co/100%25x200/cccccc/555555?text=Gestor+Escolar" alt="Gestor Escolar">
                    </div>
                    <p>Este módulo é essencial para a equipe da escola, como diretores, coordenadores e merendeiras líderes. Ele permite acompanhar a execução do cardápio no dia a dia, garantindo que as refeições sejam servidas conforme o planejado. A visualização clara do cardápio aprovado para cada dia inclui informações sobre alérgenos, receitas e orientações de preparo, permitindo que as merendeiras consultem a ficha técnica diretamente pelo tablet ou celular na cozinha.</p>
                    <p>Uma funcionalidade chave é o registro diário de refeições servidas e alunos presentes, o que permite calcular o consumo real versus o previsto. Se houver sobras ou faltas de algum item, esse registro alimenta o sistema para ajustes futuros nas quantidades. O módulo também oferece um gerenciamento simplificado do estoque local da despensa da escola, com registro de entrada e saída de alimentos e alertas sobre produtos próximos ao vencimento ou em baixa quantidade, ajudando a evitar a falta de merenda ou o desperdício.</p>
                    <p>Além disso, o módulo serve como um canal de feedback e ocorrências, permitindo que a escola reporte problemas como alimentos em más condições ou equipamentos quebrados, ou até mesmo a baixa aceitação de uma receita. A equipe escolar também tem acesso a vídeos curtos e manuais de boas práticas de higiene e técnicas de preparo, disponibilizados pelo nutricionista, promovendo a capacitação contínua.</p>
                    <details>
                        <summary><h5><i class="fas fa-plus-circle"></i> Ver Funcionalidades Essenciais</h5></summary>
                        <ul>
                            <li><i class="fas fa-check-circle"></i> <strong>Consulta de Cardápio e Fichas:</strong> Visualização clara do cardápio aprovado e acesso a fichas técnicas.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Registro de Refeições Servidas:</strong> Informação diária sobre refeições servidas e alunos presentes.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Controle de Estoque na Escola:</strong> Gerenciamento simplificado do estoque local e alertas.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Feedback e Ocorrências:</strong> Canal para reportar problemas e sugestões.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Orientações e Treinamento:</strong> Acesso a materiais de capacitação e boas práticas.</li>
                        </ul>
                    </details>
                </div>

                <!-- Módulo do Produtor Rural/Fornecedor Card -->
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="icon-box"><i class="fas fa-tractor"></i></div>
                        <h3>Módulo do Produtor Rural/Fornecedor</h3>
                    </div>
                    <div class="feature-image">
                        <img src="https://placehold.co/100%25x200/cccccc/555555?text=Produtor+Rural" alt="Produtor Rural">
                    </div>
                    <p>Este módulo foi criado para facilitar a participação de pequenos produtores rurais e agricultores familiares no PNAE, conectando a oferta local à demanda escolar de forma transparente. O produtor pode realizar um cadastro simples, inserindo seus dados pessoais, da empresa, documentação e listando os gêneros alimentícios que pode ofertar, incluindo informações sobre sazonalidade, preços estimados e quantidades disponíveis. A plataforma pode interagir com bases governamentais, como a Declaração de Aptidão ao Pronaf (DAP), para validar se o fornecedor é um agricultor familiar, agilizando o processo de habilitação.</p>
                    <p>Um painel intuitivo lista as chamadas públicas abertas ou previstas, bem como as demandas mensais das escolas e da secretaria. O produtor recebe notificações de oportunidades de fornecimento que são pertinentes ao seu perfil, como chamadas para compra de frutas ou hortaliças regionais. Ele pode então enviar suas propostas de fornecimento diretamente pelo sistema, informando preços e quantidades que consegue atender, simplificando a participação em processos licitatórios.</p>
                    <p>Após vencer uma chamada ou ser selecionado para fornecimento, o produtor acompanha o cronograma de entregas combinadas. Ao realizar cada entrega na escola ou no centro de distribuição, a escola pode acusar o recebimento no sistema, gerando um registro de entrega. Isso permite que o produtor acompanhe o status de seus pagamentos processados pela prefeitura, garantindo maior transparência e segurança financeira. Além disso, o módulo oferece um canal de comunicação e suporte técnico para tirar dúvidas sobre especificações dos alimentos ou agendar visitas técnicas à propriedade, promovendo uma co-criação no abastecimento.</p>
                    <details>
                        <summary><h5><i class="fas fa-plus-circle"></i> Ver Funcionalidades Essenciais</h5></summary>
                        <ul>
                            <li><i class="fas fa-check-circle"></i> <strong>Cadastro e Habilitação:</strong> Registro de dados e produtos, com validação de agricultor familiar.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Marketplace de Compras Públicas:</strong> Painel com chamadas públicas e envio de propostas.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Gestão de Entregas e Pagamentos:</strong> Acompanhamento de cronograma e status de pagamento.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Comunicação e Suporte Técnico:</strong> Canal para dúvidas e agendamento de visitas técnicas.</li>
                        </ul>
                    </details>
                </div>

                <!-- Módulo do Conselheiro CAE Card -->
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="icon-box"><i class="fas fa-users-cog"></i></div>
                        <h3>Módulo do Conselheiro CAE</h3>
                    </div>
                    <div class="feature-image">
                        <img src="https://placehold.co/100%25x200/cccccc/555555?text=CAE+Council" alt="Conselheiro CAE">
                    </div>
                    <p>Este módulo oferece aos membros do Conselho de Alimentação Escolar (CAE) acesso seguro e transparente a todas as informações necessárias para o controle social e fiscal do programa. Os conselheiros podem visualizar os cardápios planejados e em execução em todas as escolas, verificando se estão alinhados às diretrizes do PNAE e se contemplam a cultura alimentar local, o que é uma competência fundamental do CAE. O sistema destaca itens como o uso da agricultura familiar e a presença de alimentos regionais, facilitando a análise crítica.</p>
                    <p>O módulo também disponibiliza um espaço para registrar relatórios de visitas de inspeção do CAE às escolas. O conselheiro pode preencher um checklist digital sobre as condições da cozinha, higiene, uso de EPIs pelas merendeiras, qualidade dos alimentos e aceitação pelos alunos, formalizando e centralizando as observações. Esses relatórios ficam disponíveis para que a Secretaria de Educação tome as devidas providências e também como parte da prestação de contas do programa.</p>
                    <p>Além disso, o conselheiro pode acompanhar a situação da prestação de contas anual do município, verificando se foi enviada dentro do prazo e se foi aprovada, o que garante maior transparência sobre a situação do município. Em caso de irregularidades graves, o CAE pode registrar ocorrências formais no sistema, que geram ofícios digitais para os órgãos competentes. Um fórum interno do CAE permite a troca de informações entre conselheiros e a comunicação direta com nutricionistas e gestores sobre recomendações, fortalecendo a atuação consultiva e fiscalizadora do conselho.</p>
                    <details>
                        <summary><h5><i class="fas fa-plus-circle"></i> Ver Funcionalidades Essenciais</h5></summary>
                        <ul>
                            <li><i class="fas fa-check-circle"></i> <strong>Acesso a Cardápios e Dados:</strong> Visualização de cardápios e alinhamento às diretrizes do PNAE.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Monitoramento de Execução e Visitas:</strong> Registro de relatórios de inspeção e checklists digitais.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Controle Social e Denúncias:</strong> Acompanhamento da prestação de contas e registro de ocorrências.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Espaço Colaborativo:</strong> Fórum interno para troca de informações e recomendações.</li>
                        </ul>
                    </details>
                </div>

                <!-- Módulo da Secretaria de Educação (Gestor Central) Card -->
                <div class="feature-card">
                    <div class="feature-header">
                        <div class="icon-box"><i class="fas fa-building"></i></div>
                        <h3>Módulo da Secretaria de Educação (Gestor Central)</h3>
                    </div>
                    <div class="feature-image">
                        <img src="https://placehold.co/100%25x200/cccccc/555555?text=City+Hall" alt="Secretaria de Educação">
                    </div>
                    <p>Este componente centraliza a visão gerencial do programa para a coordenação em nível municipal ou estadual. Oferece um dashboard consolidado com indicadores-chave de desempenho de todas as unidades escolares, como o percentual de cumprimento de dias letivos com merenda oferecida, a evolução da porcentagem de compras da agricultura familiar, o custo per capita por aluno e os índices de aceitabilidade médios por receita. Esses KPIs são cruciais para o planejamento estratégico e para identificar escolas com desempenho fora do padrão, permitindo intervenções rápidas e direcionadas.</p>
                    <p>O gestor tem a capacidade de aprovar planos de menu submetidos pelos nutricionistas e contratos de compra após licitação ou chamada pública. O módulo também inclui ferramentas de auditoria interna, permitindo verificar o histórico de alterações nos cardápios, analisar a conformidade de despesas e checar se os relatórios do CAE indicam problemas não solucionados. Isso garante maior controle e transparência sobre a execução do programa em nível central.</p>
                    <p>A plataforma facilita a prestação de contas integrada, consolidando automaticamente as informações financeiras e de atividades necessárias para a prestação de contas anual ao FNDE. O sistema pré-preenche os formulários oficiais com os dados registrados ao longo do ano, otimizando o processo e reduzindo o risco de reprovação de contas por inconsistências. Além disso, o módulo serve como um centro de comunicação institucional, permitindo enviar comunicados às escolas, nutricionistas e conselheiros, e recebendo notificações críticas do sistema para que as providências de gestão necessárias sejam tomadas.</p>
                    <details>
                        <summary><h5><i class="fas fa-plus-circle"></i> Ver Funcionalidades Essenciais</h5></summary>
                        <ul>
                            <li><i class="fas fa-check-circle"></i> <strong>Dashboard Gerencial:</strong> Visão consolidada de indicadores de desempenho do programa.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Aprovação e Auditoria de Processos:</strong> Aprovação de planos de menu e contratos, e auditoria interna.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Prestação de Contas Integrada:</strong> Consolidação automática de informações para o FNDE.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Comunicação Institucional:</strong> Envio de comunicados e recebimento de notificações críticas.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Integração Orçamentária:</strong> Acompanhamento da execução orçamentária com sistemas da prefeitura/estado.</li>
                        </ul>
                    </details>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section (Depoimentos) -->
    <section class="testimonials-section section-padding" id="testimonials-section">
        <div class="container">
            <h2 class="text-center">O que nossas nutricionistas dizem</h2>
            <div class="testimonial-grid">
                <div class="testimonial-item">
                    <div class="testimonial-author">
                        <!-- Image of Nutritionist Ana Paula -->
                        <img src="images/testimonial_ana_paula.png" alt="Nutricionista Ana Paula" onerror="this.onerror=null;this.src='https://placehold.co/60x60/cccccc/555555?text=Ana';">
                        <div class="author-info">
                            <h4>Ana Paula</h4>
                            <p>Nutricionista Escolar</p>
                        </div>
                    </div>
                    <blockquote>"O NutriPNAE transformou a minha rotina! Agora consigo planejar cardápios, gerenciar o estoque e preparar a prestação de contas com uma agilidade que eu nem imaginava. É uma ferramenta indispensável para garantir a qualidade da merenda e a conformidade com o FNDE. Recomendo a todas as colegas!"</blockquote>
                </div>

                <div class="testimonial-item">
                    <div class="testimonial-author">
                        <!-- Image of UAN Coordinator Maria Luiza -->
                        <img src="images/testimonial_maria_luiza.png" alt="Coordenadora de UAN Maria Luiza" onerror="this.onerror=null;this.src='https://placehold.co/60x60/cccccc/555555?text=Maria';">
                        <div class="author-info">
                            <h4>Maria Luiza</h4>
                            <p>Coordenadora de UAN</p>
                        </div>
                    </div>
                    <blockquote>"A organização das fichas técnicas e o controle de validade dos produtos na plataforma NutriPNAE me poupam horas de trabalho manual. Sinto que tenho um controle muito maior sobre todos os processos, o que me permite focar mais na educação alimentar e na qualidade das refeições. A equipe por trás do NutriPNAE realmente entende as nossas necessidades."</blockquote>
                </div>

                <div class="testimonial-item">
                    <div class="testimonial-author">
                        <!-- Image of Public Network Nutritionist Carla Vieira -->
                        <img src="images/testimonial_carla_vieira.png" alt="Nutricionista de Rede Pública Carla Vieira" onerror="this.onerror=null;this.src='https://placehold.co/60x60/cccccc/555555?text=Carla';">
                        <div class="author-info">
                            <h4>Carla Vieira</h4>
                            <p>Nutricionista de Rede Pública</p>
                        </div>
                    </div>
                    <blockquote>"Como nutricionista da rede pública, a comunicação eficiente com as escolas e a gestão centralizada dos cardápios são cruciais. O NutriPNAE facilitou imensamente esses processos, tornando a fiscalização e o acompanhamento muito mais transparentes e ágeis. É uma ferramenta que realmente faz a diferença para quem busca excelência na alimentação escolar."</blockquote>
                </div>
            </div>
        </div>
    </section>

    <!-- Main CTA Section (Chamada para Ação Principal) -->
    <section class="main-cta-section section-padding">
        <div class="container">
            <h2 class="text-center">Comece a Usar o NutriPNAE Gratuitamente Hoje Mesmo!</h2>
            <p>
                Não deixe que a complexidade da gestão da alimentação escolar e a burocracia atrapalhem seu potencial.
                O NutriPNAE é a chave para transformar sua rotina, garantir conformidade, e impulsionar seus resultados.
                Dê o próximo passo em direção à eficiência e inovação, sem nenhum custo.
            </p>
            <a href="register.php" class="btn btn-primary btn-lg">Comece Gratuitamente Agora</a>
        </div>
    </section>

    <!-- FAQ Section (Perguntas Frequentes) -->
    <section class="faq-section section-padding" id="faq-section">
        <div class="container">
            <h2 class="text-center">Perguntas Frequentes</h2>
            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question">
                        O que é o PNAE (Programa Nacional de Alimentação Escolar)? <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p style="margin-top: 20px;">O Programa Nacional de Alimentação Escolar (PNAE) é uma iniciativa fundamental do governo brasileiro, responsável por fornecer alimentação nutritiva para milhões de estudantes em escolas públicas. Ele visa garantir a segurança alimentar e nutricional, contribuir para o desenvolvimento, aprendizado e rendimento escolar dos alunos.</p>
                        <p>A gestão do PNAE é complexa, envolvendo:</p>
                        <ul>
                            <li><i class="fas fa-bullseye"></i> <strong>Planejamento de Cardápios:</strong> Elaborar refeições balanceadas e adequadas para diferentes faixas etárias e necessidades nutricionais.</li>
                            <li><i class="fas fa-money-check-alt"></i> <strong>Gestão Financeira:</strong> Controlar o orçamento, realizar a aquisição de alimentos (com prioridade para a agricultura familiar) e prestar contas detalhadamente.</li>
                            <li><i class="fas fa-chart-line"></i> <strong>Monitoramento e Avaliação:</strong> Acompanhar a execução do programa para garantir a conformidade com as normativas do FNDE.</li>
                        </ul>
                        <p>O NutriPNAE surge como a solução ideal para simplificar essa gestão, automatizando processos e garantindo que você tenha mais tempo para o essencial: a qualidade da alimentação e a educação nutricional dos alunos!</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        O NutriPNAE atende a todas as exigências do FNDE? <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Sim, absolutamente! O NutriPNAE é desenvolvido e continuamente atualizado para estar em total conformidade com todas as resoluções e diretrizes do Fundo Nacional de Desenvolvimento da Educação (FNDE).</p>
                        <ul>
                            <li><i class="fas fa-check-circle"></i> <strong>Adequação Nutricional:</strong> Nossos cálculos automáticos garantem que os cardápios atendam às necessidades nutricionais por faixa etária.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Relatórios Complacentes:</strong> Geração de relatórios que facilitam a prestação de contas e auditorias.</li>
                            <li><i class="fas fa-check-circle"></i> <strong>Alertas e Sugestões:</strong> A plataforma oferece alertas em tempo real para inadequações e sugestões de substituições, assegurando a conformidade constante.</li>
                        </ul>
                        <p>Com o NutriPNAE, você tem a tranquilidade de saber que sua gestão está sempre alinhada às exigências legais, minimizando riscos e otimizando seu trabalho.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        Preciso de conhecimentos técnicos para usar o NutriPNAE? <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Absolutamente não! O NutriPNAE é projetado com foco na <strong>facilidade de uso e na intuitividade</strong>. Você não precisa de conhecimentos avançados em tecnologia para utilizá-lo.</p>
                        <ul>
                            <li><i class="fas fa-lightbulb"></i> <strong>Interface Amigável:</strong> Nossa interface é limpa e direta, facilitando a navegação.</li>
                            <li><i class="fas fa-book-open"></i> <strong>Materiais de Apoio:</strong> Oferecemos tutoriais, guias e uma base de conhecimento rica.</li>
                            <li><i class="fas fa-headset"></i> <strong>Suporte Completo:</strong> Nossa equipe de suporte está sempre pronta para auxiliar com qualquer dúvida ou necessidade que possa surgir.</li>
                        </ul>
                        <p>Nosso objetivo é que você possa focar no seu trabalho como nutricionista do PNAE, enquanto nós cuidamos da tecnologia!</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Form Section (Formulário de Contato) -->
    <section class="contact-section section-padding" id="contact-section">
        <div class="container">
            <h2 class="text-center">Fale Conosco</h2>
            <p class="text-center">
                Tem alguma dúvida específica, precisa de uma demonstração detalhada das funcionalidades
                ou quer explorar como o NutriPNAE pode se adaptar às suas necessidades únicas?
                Entre em contato conosco! Nossa equipe especializada está pronta para atendê-lo e
                ajudá-lo a encontrar a melhor forma de otimizar sua gestão da alimentação escolar.
            </p>

            <div class="contact-form-container">
                <?php if (!empty($contact_form_success_message)): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($contact_form_success_message); ?></div>
                <?php endif; ?>
                <?php if (!empty($contact_form_error_message)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($contact_form_error_message); ?></div>
                <?php endif; ?>
                <form action="#contact-section" method="POST"> <!-- O action="#contact-section" mantém o usuário na mesma seção após o envio -->
                    <input type="hidden" name="submit_contact_form" value="1">
                    <div class="form-group">
                        <label for="contact_name">Nome:</label>
                        <input type="text" id="contact_name" name="contact_name" value="<?php echo htmlspecialchars(isset($_POST['contact_name']) ? $_POST['contact_name'] : ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_email">E-mail:</label>
                        <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars(isset($_POST['contact_email']) ? $_POST['contact_email'] : ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="contact_phone">Telefone (opcional):</label>
                        <input type="tel" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars(isset($_POST['contact_phone']) ? $_POST['contact_phone'] : ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="contact_message">Mensagem:</label>
                        <textarea id="contact_message" name="contact_message" required><?php echo htmlspecialchars(isset($_POST['contact_message']) ? $_POST['contact_message'] : ''); ?></textarea>
                    </div>
                    <button type="submit" name="submit_contact_form" class="btn btn-primary">Enviar Mensagem</button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer (Rodapé) -->
    <footer class="footer">
        <div class="container">
            <div class="footer-social-links">
                <li><a href="#"><i class="fab fa-facebook-f"></i></a></li>
                <li><a href="#"><i class="fab fa-instagram"></i></a></li>
                <li><a href="#"><i class="fab fa-linkedin-in"></i></a></li>
            </div>
            <ul class="footer-nav-links">
                <li><a href="#">Política de Privacidade</a></li>
                <li><a href="#">Termos de Uso</a></li>
                <li><a href="#">Trabalhe Conosco</a></li>
            </ul>
            <p>&copy; <?php echo date("Y"); ?> NutriPNAE. Todos os direitos reservados.</p>
        </div>
    </footer>

    <!-- Modal de Login -->
    <div id="login-modal" class="login-modal">
        <div class="login-modal-content">
            <span class="close-button" id="close-login-modal-btn">&times;</span>
            <h2>Acessar Sua Conta</h2>
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="modal-alert error"><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (isset($success_message) && !empty($success_message)): ?>
                <div class="modal-alert success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            <form action="" method="POST"> <!-- Action vazio para postar na própria página -->
                <div class="form-group">
                    <label for="modal-username">Usuário:</label>
                    <input type="text" id="modal-username" name="username" value="<?php echo htmlspecialchars($username_input); ?>" required autofocus>
                </div>
                <div class="form-group">
                    <label for="modal-password">Senha:</label>
                    <input type="password" id="modal-password" name="password" required>
                </div>
                <button type="submit" name="login_submit" class="btn btn-primary">Entrar</button> <!-- Adicionado name="login_submit" -->
            </form>
            <div class="login-options">
                <a href="#">Esqueceu a senha?</a>
                <span>|</span>
                <a href="register.php">Criar uma conta</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Navbar Toggler for Mobile
            const navbarToggler = document.getElementById('navbar-toggler');
            const navbarNav = document.getElementById('navbar-nav');

            if (navbarToggler) {
                navbarToggler.addEventListener('click', function() {
                    navbarNav.classList.toggle('active');
                });
            }

            // FAQ Section Functionality
            const faqQuestions = document.querySelectorAll('.faq-question');
            faqQuestions.forEach(question => {
                question.addEventListener('click', () => {
                    const answer = question.nextElementSibling;
                    question.classList.toggle('expanded');
                    answer.classList.toggle('expanded');
                });
            });

            // Modal Login Functionality
            const loginModal = document.getElementById('login-modal');
            const openLoginModalBtn = document.getElementById('open-login-modal-btn');
            const closeLoginModalBtn = document.getElementById('close-login-modal-btn');
            const modalUsernameField = document.getElementById('modal-username');

            function openModal() {
                if (loginModal) {
                    loginModal.classList.add('active');
                    if (modalUsernameField) {
                        modalUsernameField.focus();
                    }
                }
            }

            function closeModal() {
                if (loginModal) {
                    loginModal.classList.remove('active');
                }
            }

            if (openLoginModalBtn) openLoginModalBtn.addEventListener('click', openModal);
            if (closeLoginModalBtn) closeLoginModalBtn.addEventListener('click', closeModal);

            if (loginModal) {
                loginModal.addEventListener('click', function(event) {
                    // Fecha o modal se o clique ocorrer fora do conteúdo do modal
                    if (event.target === loginModal) {
                        closeModal();
                    }
                });
            }

            document.addEventListener('keydown', function(event) {
                // Fecha o modal ao pressionar a tecla 'Escape'
                if (event.key === "Escape" && loginModal && loginModal.classList.contains('active')) {
                    closeModal();
                }
            });

            // Abre o modal se houver erros de login após um POST request
            <?php if (!empty($errors) && $_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['submit_contact_form'])): ?>
                openModal();
            <?php endif; ?>

            // Smooth Scroll for Navbar Links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    let href = this.getAttribute('href');
                    if (href === '#' || href.startsWith('tel:') || href.startsWith('mailto:')) return; // Ignora links tel/mailto ou apenas '#'

                    try {
                        const targetElement = document.querySelector(href);
                        if (targetElement) {
                            e.preventDefault();
                            let offsetValue = document.querySelector('.navbar').offsetHeight + 10;
                            if (href === '#home') offsetValue = 0; // Sem offset para a seção inicial
                            window.scrollTo({
                                top: targetElement.offsetTop - offsetValue,
                                behavior: 'smooth'
                            });
                        }
                    } catch (error) {
                        console.warn("Smooth scroll target not found:", href);
                    }
                });
            });

            // Custom Message Box Function (substitui alert())
            function displayMessageBox(message) {
                const messageBox = document.createElement('div');
                messageBox.classList.add('custom-message-box-overlay'); /* Usar a classe de overlay */
                messageBox.innerHTML = `
                    <div class="message-box-content">
                        <p>${message}</p>
                        <button class="message-box-close-btn">OK</button>
                    </div>
                `;
                document.body.appendChild(messageBox);
                messageBox.style.display = 'flex'; /* Exibir o overlay */

                messageBox.querySelector('.message-box-close-btn').addEventListener('click', () => {
                    document.body.removeChild(messageBox);
                });
            }

            console.log("Landing page JS (NutriPNAE) loaded.");
        });
    </script>
</body>
</html>
