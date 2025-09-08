<?php
// cardapio_auto/login.php OU home.php OU index.php OU outro_arquivo.php
// --- Bloco Padronizado de Configuração de Sessão ---
// Certifique-se de que este bloco esteja no TOPO ABSOLUTO do arquivo, antes de QUALQUER saída.

$session_cookie_path = '/'; // Caminho CONSISTENTE para este aplicativo
$session_name = "CARDAPIOSESSID";        // Nome CONSISTENTE da sessão

// Tenta configurar os parâmetros ANTES de iniciar, se a sessão ainda não existir
if (session_status() === PHP_SESSION_NONE) {
    // Remova o @ durante o desenvolvimento para ver possíveis erros/avisos
    session_set_cookie_params([
        'lifetime' => 0, // Expira ao fechar navegador
        'path' => $session_cookie_path,
        'domain' => $_SERVER['HTTP_HOST'] ?? '', // Usa o domínio atual (geralmente seguro)
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // True se HTTPS
        'httponly' => true, // Ajuda a prevenir XSS (importante!)
        'samesite' => 'Lax' // Boa proteção CSRF padrão ('Strict' é mais seguro mas pode quebrar links externos)
    ]);
}

session_name($session_name); // Define o nome da sessão

// Inicia a sessão APENAS se ela ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
     // Remova o @ durante o desenvolvimento
     session_start();
}              // Primeira coisa lógica

// Configurações de erro
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros em produção
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');

$userIdToLog = $_SESSION['user_id'] ?? null; // Pega o ID antes de destruir

// Tenta logar o evento de logout (não crítico se falhar)
if ($userIdToLog) {
    try {
        require_once 'includes/db_connect.php'; // Conecta só se precisar logar
        if(isset($pdo)){
            date_default_timezone_set('America/Sao_Paulo'); // Definir fuso horário

            $eventType = 'user_logout';
            $ipAddress = 'Desconhecido';
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) { $ipAddress = $_SERVER['HTTP_CLIENT_IP']; }
            elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR']; }
            elseif (!empty($_SERVER['REMOTE_ADDR'])) { $ipAddress = $_SERVER['REMOTE_ADDR']; }
            $ipAddress = filter_var($ipAddress, FILTER_VALIDATE_IP) ?: 'IP Inválido';

            $eventDataJson = json_encode(['ip_address' => $ipAddress], JSON_UNESCAPED_UNICODE);
             if ($eventDataJson === false) { $eventDataJson = json_encode(['error' => 'JSON encode failed']); }

            // Inserção comentada - Assumindo que não há tabela admin_events_feed neste projeto
            /*
            $stmt_feed = $pdo->prepare("INSERT INTO admin_events_feed (event_type, user_id, event_data, created_at) VALUES (:event_type, :user_id, :event_data, NOW())");
            $stmt_feed->execute([':event_type' => $eventType, ':user_id' => $userIdToLog, ':event_data' => $eventDataJson]);
            error_log("Log de Logout registrado para User ID: " . $userIdToLog);
            */
            error_log("Logout iniciado para User ID: " . $userIdToLog); // Log simples
        }
    } catch (PDOException $e) {
        error_log("Erro PDO ao tentar logar logout para User ID $userIdToLog: " . $e->getMessage());
    } catch (Throwable $t) {
        error_log("Erro geral ao tentar logar logout para User ID $userIdToLog: " . $t->getMessage());
    }
}

// LIMPA E DESTROI A SESSÃO (sempre)
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// REDIRECIONA PARA LOGIN
ob_start(); // Garante que nada foi enviado antes
header("Location: login.php");
ob_end_flush();
exit();
?>