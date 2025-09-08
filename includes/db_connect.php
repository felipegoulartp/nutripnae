<?php
// cardapio_auto/includes/db_connect.php

define('DB_HOST', 'localhost'); // Geralmente 'localhost' ou '127.0.0.1'
define('DB_NAME', 'u537701159_pnae'); // Substitua pelo nome do seu banco de dados
define('DB_USER', 'u537701159_pnae');   // Substitua pelo seu usuário do MySQL/PostgreSQL
define('DB_PASS', 'Vermelho31*');     // Substitua pela sua senha
$charset = 'utf8mb4';

// Configurações do DSN (Data Source Name)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lança exceções em caso de erro
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Retorna arrays associativos por padrão
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Usa prepared statements nativos
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Não é necessário logar a conexão aqui toda vez,
    // mas é útil para a depuração inicial.
    // error_log("db_connect.php: Conexão com BD estabelecida com sucesso.");
} catch (\PDOException $e) {
    // Em um ambiente de produção, você não deve exibir detalhes do erro ao usuário.
    // Logar o erro é crucial.
    error_log("Erro de conexão com o BD em db_connect.php: " . $e->getMessage());
    // Você pode decidir como lidar com o erro aqui.
    // Para este exemplo, vamos deixar o script que incluiu este morrer ou lançar a exceção.
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// A variável $pdo estará disponível para qualquer script que inclua este arquivo.
?>