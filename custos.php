<?php
// cardapio_auto/custos.php - Página de Análise de Custos para Cardápios Industriais

// 1. Configuração de Sessão (ANTES DE TUDO)
$session_cookie_path = '/'; 
@session_set_cookie_params([
    'lifetime' => 0, 'path' => $session_cookie_path, 'domain' => $_SERVER['HTTP_HOST'] ?? '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', 'httponly' => true, 'samesite' => 'Lax'
]);
@session_name("CARDAPIOSESSID");
if (session_status() === PHP_SESSION_NONE) {
     @session_start();
}

// 2. Configuração de Erros
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desabilitar em produção
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_log("--- Iniciando custos.php (Análise de Custos) --- | SID: " . session_id());

// 3. Verificação de Autenticação
$is_logged_in = isset($_SESSION['user_id']);
$logged_user_id = $_SESSION['user_id'] ?? null;
$logged_username = $_SESSION['username'] ?? 'Visitante';

if (!$is_logged_in || !$logged_user_id) {
    error_log("custos.php: Acesso não autenticado. Redirecionando para login. Session ID: " . session_id());
    header('Location: login.php');
    exit;
}
error_log("custos.php: Usuário autenticado. UserID: $logged_user_id, Username: $logged_username.");


// 4. Conexão com Banco de Dados
$pdo = null;
$db_connection_error = false;
try {
    require_once 'includes/db_connect.php';
    if (!isset($pdo)) { throw new \RuntimeException("PDO não definido por db_connect.php"); }
    error_log("custos.php: Conexão com BD estabelecida.");

} catch (\PDOException $e) {
    $db_connection_error = true;
    error_log("PDOException em custos.php (conexão): " . $e->getMessage());
} catch (\Throwable $th) {
    $db_connection_error = true;
    error_log("Throwable em custos.php (conexão): " . $th->getMessage());
}

// 5. Carregamento de Dados Essenciais para Custos
$page_title_content = "Análise de Custos - NutriPNAE"; // Título da página HTML
$cardapio_id_selecionado = filter_input(INPUT_GET, 'cardapio_id', FILTER_VALIDATE_INT);
$cardapio_data_carregado = null; // Dados brutos do cardápio do banco
$cardapio_nome = "Selecione um Cardápio";
$load_error_message = null;

// Layout padrão de dias (Sábado e Domingo inclusos)
$dias_keys = ['seg', 'ter', 'qua', 'qui', 'sex', 'sab', 'dom'];
$dias_semana_nomes = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado', 'Domingo'];

// Estrutura padrão para um cardápio vazio, caso nada seja carregado ou seja inválido
$default_refeicoes_layout = [
    'ref_1' => ['label' => 'CAFÉ DA MANHÃ', 'horario' => "07:00"],
    'ref_2' => ['label' => 'ALMOÇO', 'horario' => "12:00"],
    'ref_3' => ['label' => 'LANCHE', 'horario' => "15:00"],
    'ref_4' => ['label' => 'JANTAR', 'horario' => "19:00"]
];
$initial_cardapio_structure = [
    'refeicoes' => $default_refeicoes_layout,
    'dias' => [],
    'datas_dias' => array_fill_keys($dias_keys, ''),
    'dias_desativados' => array_fill_keys($dias_keys, false),
    'num_pessoas' => 100,
    'nome_projeto' => 'Cardápio Padrão'
];
foreach ($dias_keys as $dia) {
    $initial_cardapio_structure['dias'][$dia] = array_fill_keys(array_keys($default_refeicoes_layout), []);
}

// Dados para preencher o select de projetos
$projetos_disponiveis = [];

if (!$db_connection_error) {
    try {
        // Carregar todos os projetos de cardápio do usuário para o select
        $sql_projetos = "SELECT id, nome_projeto, dados_json FROM cardapio_projetos WHERE usuario_id = :usuario_id ORDER BY nome_projeto ASC";
        $stmt_projetos = $pdo->prepare($sql_projetos);
        $stmt_projetos->bindParam(':usuario_id', $logged_user_id, PDO::PARAM_INT);
        $stmt_projetos->execute();
        $projetos_disponiveis = $stmt_projetos->fetchAll(PDO::FETCH_ASSOC);

        // Se um cardapio_id foi selecionado, carrega seus dados
        if ($cardapio_id_selecionado) {
            $selected_project = null;
            foreach ($projetos_disponiveis as $p) {
                if ($p['id'] == $cardapio_id_selecionado) {
                    $selected_project = $p;
                    break;
                }
            }

            if ($selected_project) {
                $cardapio_nome = htmlspecialchars($selected_project['nome_projeto']);
                $cardapio_data_carregado = json_decode($selected_project['dados_json'], true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $load_error_message = "Dados do cardápio selecionado estão corrompidos ou em formato inválido.";
                    $cardapio_data_carregado = $initial_cardapio_structure; // Fallback para estrutura padrão
                } else {
                    // --- Garantir a estrutura mínima do cardápio carregado ---
                    // Garante que 'refeicoes' exista e tenha um layout padrão se vazio
                    $cardapio_data_carregado['refeicoes'] = (isset($cardapio_data_carregado['refeicoes']) && is_array($cardapio_data_carregado['refeicoes']) && !empty($cardapio_data_carregado['refeicoes'])) ?
                                                            $cardapio_data_carregado['refeicoes'] : $default_refeicoes_layout;
                    
                    // Certifica-se de que os dias e refeições existam com arrays vazios se não vierem do JSON
                    $temp_dias = [];
                    $valid_refeicoes_keys = array_keys($cardapio_data_carregado['refeicoes']);
                    foreach ($dias_keys as $dia) {
                        $temp_dias[$dia] = [];
                        foreach ($valid_refeicoes_keys as $ref_key) {
                            $temp_dias[$dia][$ref_key] = $cardapio_data_carregado['dias'][$dia][$ref_key] ?? [];
                            // Filtra itens inválidos (ex: sem foodId ou qty)
                            $temp_dias[$dia][$ref_key] = array_values(array_filter($temp_dias[$dia][$ref_key], fn($item) => is_array($item) && isset($item['foodId'], $item['qty']) && is_scalar($item['foodId']) && is_numeric($item['qty']) && $item['qty'] > 0));
                        }
                    }
                    $cardapio_data_carregado['dias'] = $temp_dias;
                    
                    // Garante que dias_desativados está completo e correto para todos os 7 dias da semana
                    $full_dias_desativados = array_fill_keys($dias_keys, false);
                    if(isset($cardapio_data_carregado['dias_desativados']) && is_array($cardapio_data_carregado['dias_desativados'])) {
                        foreach($cardapio_data_carregado['dias_desativados'] as $day_key => $status) {
                            if (in_array($day_key, $dias_keys)) { // Apenas para dias que realmente existem em $dias_keys
                                $full_dias_desativados[$day_key] = (bool)$status;
                            }
                        }
                    }
                    $cardapio_data_carregado['dias_desativados'] = $full_dias_desativados;

                    $cardapio_data_carregado['num_pessoas'] = $cardapio_data_carregado['num_pessoas'] ?? 100;
                    $cardapio_data_carregado['nome_projeto'] = $cardapio_data_carregado['nome_projeto'] ?? $selected_project['nome_projeto']; // Fallback nome
                }
            } else {
                $load_error_message = "Cardapio não encontrado ou acesso negado.";
                $cardapio_id_selecionado = null; // Zera para indicar que nada válido foi selecionado
                $cardapio_data_carregado = $initial_cardapio_structure; // Fallback para estrutura padrão
            }
        } else {
            // Se nenhum cardapio_id foi fornecido, usa a estrutura inicial vazia
            $cardapio_data_carregado = $initial_cardapio_structure;
        }

    } catch (\PDOException $e) {
        $db_connection_error = true;
        $load_error_message = "Erro BD ao buscar cardápios: " . $e->getMessage();
        error_log("PDOException custos.php (carregar cardápios): " . $e->getMessage());
        $cardapio_data_carregado = $initial_cardapio_structure; // Fallback
    } catch (\Throwable $th) {
        $db_connection_error = true;
        $load_error_message = "Erro inesperado ao carregar cardápios: " . $th->getMessage();
        error_log("Throwable custos.php (carregar cardápios): " . $th->getMessage());
        $cardapio_data_carregado = $initial_cardapio_structure; // Fallback
    }
}


// 6. Carrega dados base de alimentos e preparações
$dados_base_ok = false;
$alimentos_db = []; // Alimentos base com dados nutricionais
$lista_selecionaveis_db = []; // Alimentos selecionáveis (ID => nome)
$preparacoes_usuario = []; // Preparações personalizadas do usuário

try {
    $dados_file = __DIR__ . '/dados.php';
    if (!file_exists($dados_file) || !is_readable($dados_file)) {
        throw new Exception("dados.php não encontrado ou não legível em " . $dados_file);
    }
    ob_start();
    require $dados_file;
    $output = ob_get_clean(); // Captura qualquer saída inesperada
    if (!empty($output)) {
        error_log("AVISO: Saída inesperada de dados.php em custos.php: ".substr($output,0,500));
    }
    // Verifica se as variáveis esperadas foram definidas por dados.php
    $dados_ok_interno = $dados_ok ?? false;
    $dados_essenciais_ok = (isset($lista_selecionaveis_db, $alimentos_db) && is_array($lista_selecionaveis_db) && !empty($lista_selecionaveis_db) && is_array($alimentos_db) && !empty($alimentos_db));
    $dados_base_ok = ($dados_ok_interno === true && $dados_essenciais_ok);
    if(!$dados_base_ok){
        $erro_msg_dbase="Falha ao carregar dados.php.";
        if(!$dados_ok_interno)$erro_msg_dbase.="(flag de sucesso não definida)";
        if(!$dados_essenciais_ok)$erro_msg_dbase.="(variáveis essenciais vazias/ausentes)";
        $load_error_message=($load_error_message?" | ":"").$erro_msg_dbase;
    }

    // Carrega preparações personalizadas do usuário
    if ($logged_user_id && isset($pdo)) {
        $sql_prep = "SELECT preparacoes_personalizadas_json FROM cardapio_usuarios WHERE id = :user_id LIMIT 1";
        $stmt_prep = $pdo->prepare($sql_prep);
        $stmt_prep->bindParam(':user_id', $logged_user_id, PDO::PARAM_INT);
        $stmt_prep->execute();
        $json_preps = $stmt_prep->fetchColumn();
        if ($json_preps && $json_preps !== 'null' && $json_preps !== '{}' && $json_preps !== '[]') {
            $decoded_preps = json_decode($json_preps, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_preps) && (empty($decoded_preps) || array_keys($decoded_preps) !== range(0, count($decoded_preps)-1))) {
                $preparacoes_usuario = $decoded_preps;
            }
        }
    }


} catch (Throwable $e) {
    if(ob_get_level()>0)ob_end_clean();
    $dados_base_ok = false;
    $load_error_message = ($load_error_message?" | ":"")."Erro fatal ao incluir dados.php: ".$e->getMessage();
    error_log("Erro fatal ao incluir dados.php em custos.php: " . $e->getMessage());
}


// Combina alimentos base e preparações personalizadas para o JS
$alimentos_para_js = [];
if ($dados_base_ok) {
    foreach ($lista_selecionaveis_db as $id => $data) {
        if (isset($data['nome']) && isset($alimentos_db[$id])) {
            $id_str = (string)$id;
            $alimentos_para_js[$id_str] = [
                'id' => $id_str,
                'nome' => $data['nome'],
                'isPreparacao' => false,
                'nutrientes' => $alimentos_db[$id], // Inclui dados nutricionais
                'default_unit' => 'g' // Unidade padrão para alimentos base
            ];
        }
    }
    if (!empty($preparacoes_usuario)) {
        foreach ($preparacoes_usuario as $prep_id => $prep_data) {
            if (isset($prep_data['nome'], $prep_data['ingredientes'], $prep_data['porcao_padrao_g'])) {
                $id_str = (string)$prep_id;
                $alimentos_para_js[$id_str] = [
                    'id' => $id_str,
                    'nome' => $prep_data['nome'],
                    'isPreparacao' => true,
                    'ingredientes' => $prep_data['ingredientes'], // Array de {foodId, qty, nomeOriginal}
                    'porcao_padrao_g' => $prep_data['porcao_padrao_g'],
                    'default_unit' => 'g' // Preparações geralmente são medidas em gramas
                ];
            }
        }
    }
    // Ordena alfabeticamente
    uasort($alimentos_para_js, fn($a, $b) => strcasecmp($a['nome'] ?? '', $b['nome'] ?? ''));
}

// Carrega preços dos alimentos de um arquivo JSON
$food_prices = [];
$food_prices_file = __DIR__ . '/food_prices.json';
if (file_exists($food_prices_file) && is_readable($food_prices_file)) {
    $prices_json = file_get_contents($food_prices_file);
    $decoded_prices = json_decode($prices_json, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_prices)) {
        $food_prices = $decoded_prices;
    } else {
        error_log("Erro ao decodificar food_prices.json. Conteúdo inválido. Resetando arquivo.");
        file_put_contents($food_prices_file, json_encode([])); // Resetar arquivo corrompido
    }
} else {
    // Se o arquivo não existe, tenta criá-lo vazio
    if (!file_put_contents($food_prices_file, json_encode([]))) {
        error_log("Não foi possível criar food_prices.json. Verifique as permissões de pasta.");
    }
}


// Prepara dados para JS
$alimentos_completos_json = json_encode($alimentos_para_js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
// Usar cardapio_data_carregado, que tem a estrutura garantida
$cardapio_data_json_to_js = json_encode($cardapio_data_carregado, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$refeicoes_layout_json_to_js = json_encode($cardapio_data_carregado['refeicoes'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$dias_keys_json = json_encode($dias_keys, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$dias_nomes_json = json_encode(array_combine($dias_keys, $dias_semana_nomes), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$food_prices_json = json_encode($food_prices, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title_content; ?> - NutriPNAE</title>
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

            --disabled-day-bg: #f1f3f5; /* Background for disabled days */
            --disabled-day-text: #ced4da; /* Text color for disabled days */
            --disabled-day-border: #e9ecef; /* Border color for disabled days */

            --sidebar-width: 280px; /* Largura padrão do sidebar */
            --sidebar-collapsed-width: 60px; /* Largura do sidebar quando recolhido */
        }

        /* Keyframes para animações */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scaleUp { from { transform: scale(0.97); opacity: 0.8; } to { transform: scale(1); opacity: 1; } }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
        @keyframes fadeInModal { from { opacity: 0; } to { opacity: 1; } }
        @keyframes scaleInModal { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        @keyframes slideInModal { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } } /* Adicionado para modais */


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
            overflow-x: hidden;
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
            position: relative; /* Adicionado para o botão de toggle da sidebar */
        }

        /* Sidebar styles */
        .sidebar {
            width: var(--sidebar-width);
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

        /* Sidebar collapsed state */
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
            overflow-x: hidden;
        }

        /* Sidebar Toggle Button (para desktop) */
        .sidebar-toggle-container {
            position: absolute;
            top: 50%;
            left: var(--sidebar-width); /* Posição inicial para o botão */
            transform: translateY(-50%);
            z-index: 1001; /* Acima do sidebar */
            transition: left 0.3s ease;
        }

        .sidebar.collapsed .sidebar-toggle-container {
            left: var(--sidebar-collapsed-width); /* Move o botão com o sidebar recolhido */
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.15); /* Sombra para destacar */
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
        .sidebar.collapsed .sidebar-toggle-button span {
            display: none; /* Esconde o texto no botão quando recolhido */
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
            font-size: 1.05em; /* Ligeiramente menor */
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
            list-style: none; /* Remove default marker */
            white-space: nowrap;
        }
        .sidebar-nav details summary::-webkit-details-marker {
            display: none; /* Remove default marker in WebKit */
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

        /* Content area */
        .content-area {
            flex-grow: 1;
            padding: 20px;
            background-color: transparent;
        }
        .content-area .container {
            max-width: 1800px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Page specific styles for custos.php */
        .costs-page-title {
            font-family: var(--font-primary);
            color: var(--color-primary-dark);
            font-size: 2.2em;
            font-weight: 700;
            margin-bottom: 25px;
            text-align: center; /* Centraliza o título */
            display: flex;
            align-items: center;
            justify-content: center; /* Centraliza o conteúdo flex */
            gap: 15px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.05);
        }
        .costs-page-title i {
            font-size: 1.1em;
            color: var(--color-accent);
        }

        .costs-main-content-area {
            max-width: 1500px;
            margin: 0 auto;
            padding: 25px 30px;
            background-color: var(--color-bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            animation: fadeIn 0.25s ease-out;
            border: 1px solid var(--color-light-border);
        }

        .config-area {
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 15px 20px;
            padding: 15px 20px;
            background-color: var(--color-primary-xtralight);
            border-radius: var(--border-radius);
            border: 1px solid var(--color-light-border);
        }
        .config-area label {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--color-primary-dark);
            white-space: nowrap;
        }
        .config-area input[type="number"],
        .config-area input[type="text"],
        .config-area select {
            padding: 8px 12px;
            font-size: 0.9rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--color-border);
            min-width: 150px;
            background-color: var(--color-bg-white);
            cursor: pointer;
            height: 38px;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
            font-family: var(--font-secondary);
        }
        .config-area input[type="number"]:focus,
        .config-area input[type="text"]:focus,
        .config-area select:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px var(--color-primary-xtralight);
            outline: none;
        }
        .config-area select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%231976D2'%3E%3Cpath fill-rule='evenodd' d='M8 10.5a.5.5 0 0 1-.354-.146l-4-4a.5.5 0 0 1 .708-.708L8 9.293l3.646-3.647a.5.5 0 0 1 .708.708l-4 4A.5.5 0 0 1 8 10.5Z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 16px 16px;
            padding-right: 35px;
            flex-grow: 1;
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

        .action-button.success { background-color: var(--color-success); }
        .action-button.success:hover:not(:disabled) { background-color: var(--color-success-dark); }
        .action-button.info { background-color: var(--color-info); }
        .action-button.info:hover:not(:disabled) { background-color: var(--color-info-dark); }
        .action-button.danger { background-color: var(--color-error); }
        .action-button.danger:hover:not(:disabled) { background-color: var(--color-error-dark); }

        /* Estilos para a tabela de visualização do cardápio (somente leitura) */
        .cardapio-visualizacao-table-container {
            overflow-x: auto;
            margin-top: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            background-color: var(--color-bg-white);
        }
        .cardapio-visualizacao-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 900px; /* Largura mínima para evitar colunas muito apertadas */
        }
        .cardapio-visualizacao-table th, .cardapio-visualizacao-table td {
            border-bottom: 1px solid var(--color-light-border);
            border-right: 1px solid var(--color-light-border);
            padding: 8px 12px;
            text-align: left; /* Alinhamento dos itens */
            vertical-align: top;
            font-size: 0.85rem;
            position: relative;
            white-space: normal;
        }
        .cardapio-visualizacao-table th {
            background-color: #eef2f7;
            color: var(--color-primary-dark);
            font-weight: 600;
            font-family: var(--font-primary);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 1px solid var(--color-border);
        }
        /* Larguras das colunas: Refeição (120px) */
        .cardapio-visualizacao-table th:first-child { width: 150px; } /* Refeição - Aumentado */
        /* Largura para os dias, distribuindo o restante */
        .cardapio-visualizacao-table th:not(:first-child) { width: calc((100% - 150px) / 7); } /* Removida coluna de Horário */


        .cardapio-visualizacao-table th:last-child, .cardapio-visualizacao-table td:last-child { border-right: none; }
        .cardapio-visualizacao-table tr:last-child td { border-bottom: none; }

        .cardapio-visualizacao-table td {
            background-color: var(--color-bg-white);
            padding: 10px;
        }
        .cardapio-visualizacao-table .meal-header-label {
             font-weight: 600;
             color: var(--color-text-dark);
             font-size: 0.9em;
             margin-bottom: 5px;
             display: block;
        }
        .cardapio-visualizacao-table .meal-horario-label {
             font-size: 0.8em;
             color: var(--color-text-light);
             display: block;
        }
        .cardapio-visualizacao-table .item-list-display {
            list-style: none; padding: 0; margin: 0;
        }
        .cardapio-visualizacao-table .item-list-display li {
            font-size: 0.8em; padding: 3px 0; border-bottom: 1px dashed var(--color-light-border);
            color: var(--color-text-dark); display: flex; justify-content: space-between; align-items: center;
        }
        .cardapio-visualizacao-table .item-list-display li:last-child { border-bottom: none; }
        .cardapio-visualizacao-table .item-name-display {
            flex-grow: 1; margin-right: 5px;
        }
        .cardapio-visualizacao-table .item-qty-display {
            font-weight: 600; color: var(--color-primary-dark);
        }
        .cardapio-visualizacao-table .day-disabled-overlay {
            background-color: rgba(241, 243, 245, 0.8); /* Cor de fundo do disabled-day-bg com opacidade */
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2em; font-weight: bold; color: var(--disabled-day-text);
            pointer-events: none; /* Garante que o texto dentro seja clicável */
            z-index: 2; /* Garante que fique por cima do conteúdo da célula */
        }


        /* Estilos para a tabela de custos */
        .costs-table-container { overflow-x: auto; margin-top: 30px; margin-bottom: 20px; border: 1px solid var(--color-border); border-radius: var(--border-radius); box-shadow: var(--box-shadow); background-color: var(--color-bg-white); }
        .costs-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 900px; } /* Aumentado para melhor visualização */
        .costs-table th, .costs-table td {
            border-bottom: 1px solid var(--color-light-border); border-right: 1px solid var(--color-light-border);
            padding: 10px 12px; /* Aumentado o padding */
            text-align: center; vertical-align: middle; font-size: 0.9rem; /* Aumentado o tamanho da fonte */
            white-space: normal;
        }
        .costs-table th:first-child { width: 180px; } /* Largura para a coluna de refeição */
        .costs-table th:last-child { width: 120px; } /* Largura para a coluna Total Diário/Semanal */
        .costs-table th:nth-last-child(2) { width: 120px; } /* Largura para a coluna Média Diária no tfoot */


        .costs-table th:last-child, .costs-table td:last-child { border-right: none; }
        .costs-table tr:last-child td { border-bottom: none; }

        .costs-table thead th { background-color: #eef2f7; color: var(--color-primary-dark); font-weight: 600; font-family: var(--font-primary); font-size: 0.85rem; /* Ajustado */ text-transform: uppercase; letter-spacing: 0.8px; border-bottom: 1px solid var(--color-border); }
        .costs-table tbody tr.total-row td { background-color: #f8f9fa; font-weight: 600; color: var(--color-primary-dark); }
        .costs-table tfoot tr td { background-color: var(--color-primary-xtralight); font-weight: 700; color: var(--color-primary-dark); font-size: 0.95rem; /* Ajustado */ }
        .costs-table td.refeicao-label { background-color: #f8f9fa; font-weight: 500; text-align: left; border-right: 1px solid var(--color-border); }
        .costs-table td.total-dia { font-weight: 700; color: var(--color-primary-dark); }
        .costs-table td.total-semana { background-color: var(--color-primary-light); color: var(--color-text-on-dark); font-weight: 700; }

        #status-message { margin-top: 20px; font-weight: 500; padding: 10px 15px; border-radius: var(--border-radius); text-align: center; font-size: 0.95em; border: 1px solid transparent; display: flex; align-items: center; justify-content: center; gap: 10px; transition: background-color var(--transition-speed), border-color var(--transition-speed), color var(--transition-speed); }
        #status-message i { font-size: 1.1em; }
        .status.loading { color: var(--color-info-dark); background-color: var(--color-info-light); border-color: var(--color-info); animation: pulse 1.5s infinite ease-in-out; }
        .status.error { color: var(--color-error-dark); background-color: var(--color-error-light); border-color: var(--color-error); }
        .status.success { color: var(--color-success-dark); background-color: var(--color-success-light); border-color: var(--color-success); }
        .status.warning { color: var(--color-warning-dark); background-color: var(--color-warning-light); border-color: var(--color-warning); }
        .status.info { color: var(--color-secondary); background-color: #e9ecef; border-color: #ced4da; }

        .error-container { background-color: var(--color-bg-white); padding: 30px; border-radius: var(--border-radius); box-shadow: var(--box-shadow); text-align: center; border: 1px solid var(--color-error); max-width: 600px; margin: 50px auto; }
        .error-container h1 { color: var(--color-error-dark); margin-bottom: 15px; font-family: var(--font-primary); font-size: 1.6em; display: flex; align-items: center; justify-content: center; }
        .error-container h1 i { margin-right: 10px; color: var(--color-error); }
        .error-container p { color: var(--color-text-light); margin-bottom: 10px; font-size: 1em; }
        .error-container p small { font-size: 0.9em; color: var(--color-secondary); }

        .main-footer-bottom {
            text-align: center;
            padding: 20px;
            margin-top: auto;
            background-color: var(--color-primary-dark);
            color: var(--color-primary-xtralight);
            font-size: 0.9em;
            border-top: 1px solid var(--color-primary);
        }

        /* Modals (Custom Message Box, Manage Prices) */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.6); justify-content: center; align-items: center;
            z-index: 1050; padding: 15px; box-sizing: border-box; backdrop-filter: blur(3px); animation: fadeInModal 0.2s ease-out;
        }
        .modal-content {
            background-color: var(--color-bg-white); padding: 25px 30px; border-radius: var(--border-radius);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2); max-width: 600px; width: 90%; max-height: 90vh; display: flex; flex-direction: column;
            animation: slideInModal 0.2s ease-out; border: 1px solid var(--color-light-border);
        }
        .modal-header {
            border-bottom: 1px solid var(--color-light-border); padding-bottom: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h2 { font-size: 1.3em; margin: 0; color: var(--color-primary-dark); font-weight: 600; font-family: var(--font-primary); }
        .modal-close-btn { background:none; border:none; font-size: 1.6rem; cursor:pointer; color: var(--color-secondary-light); padding: 0 5px; line-height: 1; transition: color var(--transition-speed); }
        .modal-close-btn:hover { color: var(--color-error); }
        .modal-body { overflow-y: auto; flex-grow: 1; margin-bottom: 15px; padding-right: 10px; scrollbar-width: thin; scrollbar-color: var(--color-primary-light) var(--color-primary-xtralight); }
        .modal-body::-webkit-scrollbar { width: 10px; }
        .modal-body::-webkit-scrollbar-track { background: var(--color-primary-xtralight); border-radius: 5px; }
        .modal-body::-webkit-scrollbar-thumb { background-color: var(--color-primary-light); border-radius: 5px; border: 2px solid var(--color-primary-xtralight); }

        /* Estilos específicos para inputs em modais */
        .modal-body input[type="text"],
        .modal-body input[type="number"],
        .modal-body select,
        .modal-body textarea {
            display: block; width: 100%; padding: 9px 12px; margin-bottom: 18px;
            border: 1px solid var(--color-border); border-radius: var(--border-radius); box-sizing: border-box; font-size: 0.95em; font-family: var(--font-secondary); transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
        }
        .modal-body input[type="text"]:focus,
        .modal-body input[type="number"]:focus,
        .modal-body select:focus,
        .modal-body textarea:focus {
            border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-xtralight); outline: none;
        }


        .modal-footer { border-top: 1px solid var(--color-light-border); padding-top: 15px; text-align: right; display: flex; justify-content: flex-end; gap: 10px;}
        .modal-button { padding: 9px 20px; font-size: 0.85em; margin-left: 0; }
        .modal-button.confirm { background-color: var(--color-success); color: var(--color-text-on-dark); }
        .modal-button.confirm:hover:not(:disabled) { background-color: var(--color-success-dark); }
        .modal-button.cancel { background-color: var(--color-secondary); color: var(--color-text-on-dark); }
        .modal-button.cancel:hover:not(:disabled) { background-color: #5a6268; }

        /* Preço Modal Específicos */
        #manage-prices-modal .price-list-container {
            max-height: 400px; overflow-y: auto; border: 1px solid var(--color-light-border);
            border-radius: var(--border-radius); background-color: #f8f9fa;
        }
        #manage-prices-modal .price-list-container ul { list-style: none; padding: 0; margin: 0; }
        #manage-prices-modal .price-list-container li {
            padding: 10px 15px; border-bottom: 1px solid var(--color-light-border);
            display: flex; align-items: center; gap: 10px; background-color: var(--color-bg-white);
            transition: background-color 0.15s ease; flex-wrap: wrap;
        }
        #manage-prices-modal .price-list-container li:last-child { border-bottom: none; }
        #manage-prices-modal .price-list-container li:hover { background-color: var(--color-primary-xtralight); }
        #manage-prices-modal .price-list-container li .item-name { flex-grow: 1; font-weight: 500; color: var(--color-primary-dark); flex-basis: 100%; margin-bottom: 5px; }
        #manage-prices-modal .price-list-container li .price-input-group {
            display: flex; align-items: center; gap: 5px; margin-right: 15px;
        }
        #manage-prices-modal .price-list-container li .price-input-group label {
            font-size: 0.85em; color: var(--color-text-light); white-space: nowrap;
        }
        #manage-prices-modal .price-list-container li .price-input-group input[type="number"] {
            width: 80px; padding: 6px 8px; border: 1px solid var(--color-border); border-radius: 4px; text-align: right; font-size: 0.85rem;
            margin-bottom: 0; /* Override default modal input margin */
        }
        #manage-prices-modal .price-list-container li .price-input-group select {
            padding: 6px 8px; border: 1px solid var(--color-border); border-radius: 4px; font-size: 0.85rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%231976D2'%3E%3Cpath fill-rule='evenodd' d='M8 10.5a.5.5 0 0 1-.354-.146l-4-4a.5.5 0 0 1 .708-.708L8 9.293l3.646-3.647a.5.5 0 0 1 .708.708l-4 4A.5.5 0 0 1 8 10.5Z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 5px center; background-size: 14px 14px; padding-right: 25px;
            width: 70px; /* Ajuste para largura razoável */
            margin-bottom: 0; /* Override default modal input margin */
            appearance: none;
        }
        #manage-prices-modal .price-list-container li .price-input-group .currency-symbol {
            font-weight: 600; color: var(--color-text-light); white-space: nowrap;
        }
        /* Requisição modal */
        #requisition-modal .modal-content { max-width: 800px; }
        #requisition-modal .modal-body { display: flex; flex-direction: column; }
        #requisition-modal textarea { width: 100%; min-height: 150px; padding: 10px; border: 1px solid var(--color-border); border-radius: var(--border-radius); resize: vertical; font-family: var(--font-secondary); }
        #requisition-modal .requisition-summary { border: 1px solid var(--color-light-border); border-radius: var(--border-radius); padding: 15px; margin-bottom: 20px; background-color: var(--color-bg-light); }
        #requisition-modal .requisition-summary p { margin: 5px 0; font-size: 0.95em; color: var(--color-text-dark); }
        #requisition-modal .requisition-list { list-style: none; padding: 0; margin: 15px 0; max-height: 300px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: var(--border-radius); background-color: var(--color-bg-white); }
        #requisition-modal .requisition-list li { padding: 8px 12px; border-bottom: 1px dashed var(--color-light-border); display: flex; justify-content: space-between; align-items: center; font-size: 0.9em; }
        #requisition-modal .requisition-list li:last-child { border-bottom: none; }
        #requisition-modal .requisition-list li span:first-child { font-weight: 500; color: var(--color-primary-dark); }
        #requisition-modal .requisition-list li span:last-child { font-weight: 600; color: var(--color-text-dark); }
        #requisition-modal .print-button { margin-top: 15px; }


        @media (max-width: 1200px) {
            .navbar .container { width: 95%; }
            .costs-main-content-area { padding: 20px; }
            .costs-table { min-width: 768px; } /* Ajuste a largura mínima para tablets */
        }
        @media (max-width: 1024px) {
            .navbar .container { flex-direction: column; gap: 15px; }
            .navbar-brand-group { order: 1; }
            .navbar-actions { order: 2; width: 100%; justify-content: center; flex-wrap: wrap; }
            .user-greeting { display: none; }
            .main-wrapper { flex-direction: column; }
            .sidebar {
                width: 100%; height: auto; position: relative; box-shadow: none; padding: 10px 0;
            }
            /* Botão de toggle da sidebar para mobile */
            .sidebar-toggle-container {
                position: static; transform: none; display: flex; justify-content: center;
                width: 100%; padding: 0; margin-bottom: 10px;
            }
            .sidebar-toggle-button {
                border-radius: var(--border-radius); padding: 10px 15px; width: fit-content;
            }
            .sidebar-toggle-button span { display: inline; }
            .sidebar-toggle-button i { transform: none !important; margin-right: 8px; }
            /* Esconde os elementos do menu lateral em mobile até serem ativados */
            .sidebar-nav { display: none; padding-top: 10px; padding-bottom: 10px; }
            .sidebar-nav.active { display: flex; flex-direction: column; } /* Ativa o display flex no mobile */
            .sidebar-nav details summary { border-left: none; justify-content: center; }
            .sidebar-nav details ul { padding-left: 15px; }

            .content-area { padding: 15px; }
            .costs-page-title { font-size: 1.8em; text-align: center; justify-content: center; }
            .config-area { flex-direction: column; align-items: stretch; gap: 15px; }
            .config-area input, .config-area select { min-width: unset; width: 100%; }
        }
        @media (max-width: 768px) {
            body { font-size: 13px; }
            .costs-main-content-area { padding: 15px; }
            /* Tabelas em modo scrollable para mobile */
            .costs-table, .cardapio-visualizacao-table {
                display: block; overflow-x: auto; white-space: nowrap;
                border: none; box-shadow: none; min-width: unset; /* Remove min-width para mobile */
            }
            .costs-table thead, .costs-table tbody, .costs-table tr,
            .cardapio-visualizacao-table thead, .cardapio-visualizacao-table tbody, .cardapio-visualizacao-table tr { display: block; }
            .costs-table thead, .cardapio-visualizacao-table thead { position: relative; }
            /* Larguras fixas para colunas em mobile */
            .costs-table th, .costs-table td,
            .cardapio-visualizacao-table th, .cardapio-visualizacao-table td {
                 display: inline-block; /* Para simular colunas fixas */
                 width: 150px; /* Largura padrão para colunas de dados */
                 vertical-align: top; padding: 10px;
                 border-right: 1px solid var(--color-light-border); /* Manter borda vertical */
                 box-sizing: border-box; /* Incluir padding e border na largura */
            }
            .costs-table th:first-child, .costs-table td:first-child { width: 180px; } /* Refeição / Rótulo */
            .cardapio-visualizacao-table th:first-child, .cardapio-visualizacao-table td:first-child { width: 150px; } /* Refeição Label */
            /* Largura da coluna de Horário em mobile para visualização */
            .cardapio-visualizacao-table th:nth-child(2), .cardapio-visualizacao-table td:nth-child(2) { width: 90px; }


            .costs-table th:last-child, .costs-table td:last-child { border-right: none; }
            .costs-table tr:last-child td, .cardapio-visualizacao-table tr:last-child td { border-bottom: none; }
            .costs-table tbody tr, .cardapio-visualizacao-table tbody tr {
                border-bottom: 2px solid var(--color-primary-light);
                margin-bottom: 15px; padding-bottom: 10px; display: flex; flex-direction: row; /* Volta a ser row para scroll horizontal */
                flex-wrap: nowrap; /* Impede quebras de linha */
            }
            .costs-table td.refeicao-label, .cardapio-visualizacao-table td.refeicao-label { width: 100% !important; display: block !important; } /* Ajuste */

            /* Ajustes nos modais para mobile */
            .modal-content { padding: 15px 20px; max-height: 85vh; }
            .modal-body { padding-right: 5px; }
            .modal-header h2 { font-size: 1.2em; }
            .modal-button { margin-left: 0; padding: 8px 15px; }
            #manage-prices-modal .price-list-container li .price-input-group { flex-wrap: wrap; margin-right: 0; width: 100%; justify-content: flex-end; }
            #manage-prices-modal .price-list-container li .item-name { flex-basis: auto; margin-bottom: 0; }
        }
        @media (max-width: 480px) {
            body { font-size: 12px; }
            .main-wrapper { padding-top: 120px; }
            .navbar .container{max-width: 95%;} .navbar-brand-group{gap:1rem;}.navbar-brand{font-size:1.4rem;}
            .btn-header-action{font-size:0.75rem;padding:0.5rem 1rem;}
            .config-area { padding: 10px; }
            .action-button { font-size: 0.7rem; padding: 6px 10px; gap: 4px; height: 32px;}
            .costs-main-content-area { padding: 10px; margin: 5px auto; }
            /* Colunas ainda menores em telas muito pequenas */
            .costs-table th, .costs-table td,
            .cardapio-visualizacao-table th, .cardapio-visualizacao-table td { width: 100px; } /* Largura padrão */
            .costs-table th:first-child, .costs-table td:first-child { width: 150px; } /* Refeição / Rótulo */
            .cardapio-visualizacao-table th:first-child, .cardapio-visualizacao-table td:first-child { width: 120px; } /* Refeição Label */
            /* A coluna de horário não existe mais na tabela de visualização */


            #manage-prices-modal .price-list-container li .price-input-group input[type="number"] { width: 60px; }
            #manage-prices-modal .price-list-container li .price-input-group select { width: 60px; }
            #manage-prices-modal .price-list-container li .price-input-group label { flex-grow: 1; text-align: left;}
        }
    </style>
</head>
<body>
    <div id="custom-message-box-overlay" class="custom-message-box-overlay">
        <div class="custom-message-box-content">
            <p id="message-box-text"></p>
            
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
                    <span class="user-greeting">Olá, <span style="font-size: 1.2em; font-weight: 700; color: var(--color-primary-dark);"><?php echo htmlspecialchars($logged_username); ?></span>!</span>
                    <a href="ajuda.php" class="btn-header-action"><i class="fas fa-question-circle"></i> Ajuda</a>
                    <a href="logout.php" class="btn-header-action logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="main-wrapper">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-toggle-container">
                <button class="sidebar-toggle-button" id="sidebar-toggle-button" title="Minimizar/Maximizar Menu">
                    <i class="fas fa-chevron-left"></i> <span>Minimizar</span>
                </button>
            </div>
            <nav class="sidebar-nav" id="sidebar-nav">
                <a href="https://nutripnae.com" class="sidebar-top-link"><i class="fas fa-home"></i> <span>Página Principal</span></a>
                <a href="home.php" class="sidebar-top-link" data-platform-link="nutripnae-dashboard-section"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>

                <details class="nutripnae-tools" open>
                    <summary><i class="fas fa-school"></i> <span>NutriPNAE</span></summary>
                    <ul>
                        <details class="nutripnae-tools" style="margin-left: -30px;">
                            <summary style="border-left: none; padding-left: 30px;"><i class="fas fa-clipboard-list" style="color: var(--color-primary);"></i> <span>Gerenciar Cardápios</span></summary>
                            <ul>
                                <li><a href="index.php"><i class="fas fa-plus" style="color: var(--color-primary);"></i> <span>Novo Cardápio Semanal</span></a></li>
                                <li><a href="cardapios.php"><i class="fas fa-folder-open" style="color: var(--color-primary);"></i> <span>Meus Cardápios</span></a></li>
                            </ul>
                        </details>
                        <li><a href="fichastecnicas.php"><i class="fas fa-file-invoice" style="color: var(--color-primary);"></i> <span>Fichas Técnicas</span></a></li>
                        <li class="active-sidebar-link"><a href="custos.php" class="active"><i class="fas fa-dollar-sign" style="color: var(--color-primary);"></i> <span>Análise de Custos</span></a></li>
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

        <main class="content-area">
            <div class="container">
                <h1 class="costs-page-title"><i class="fas fa-dollar-sign"></i> Análise de Custos do Cardápio</h1>

                <?php if ($load_error_message || !$dados_base_ok || $db_connection_error): ?>
                    <div class="error-container" style="margin-top: 30px;">
                        <h1><i class="fas fa-exclamation-triangle"></i> Erro ao Carregar Dados Essenciais</h1>
                        <p><?php echo htmlspecialchars($load_error_message ?: 'Problema genérico ao acessar dados.'); ?></p>
                        <p>Por favor, <a href="home.php">volte para o Dashboard</a> ou contate o suporte.</p>
                        <p><small>(Detalhes técnicos registrados nos logs.)</small></p>
                    </div>
                <?php else: ?>
                    <div class="costs-main-content-area">
                        <section class="config-area">
                            <label for="cardapio-select">Cardápio para Análise:</label>
                            <select id="cardapio-select" title="Selecione o cardápio para análise de custos">
                                <option value="">-- Selecione um Cardápio --</option>
                                <?php foreach ($projetos_disponiveis as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo ($cardapio_id_selecionado == $p['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['nome_projeto']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="num-pessoas-input">Nº de Pessoas:</label>
                            <input type="number" id="num-pessoas-input" min="1" value="<?php echo htmlspecialchars($cardapio_data_carregado['num_pessoas'] ?? 100); ?>" title="Número de pessoas para o cálculo">
                            
                            <label for="price-category-select">Categoria de Preço:</label>
                            <select id="price-category-select" title="Selecione a categoria de preço para os cálculos">
                                <option value="default">Preço Padrão</option>
                                <option value="last_updated">Último Preço Atualizado</option>
                                <option value="fornecedor_a">Fornecedor A</option>
                                <option value="fornecedor_b">Fornecedor B</option>
                                </select>

                            <button type="button" id="calculate-costs-btn" class="action-button success" title="Recalcular custos"><i class="fas fa-calculator"></i> Recalcular Custos</button>
                            <button type="button" id="manage-prices-btn" class="action-button info" title="Gerenciar preços dos alimentos"><i class="fas fa-tags"></i> Gerenciar Preços</button>
                            <button type="button" id="requisition-btn" class="action-button" title="Gerar requisição de alimentos"><i class="fas fa-print"></i> Requisição</button>
                            <span id="save-cardapio-status" style="display:none;"></span> </section>

                        <section class="cardapio-visualizacao-section">
                            <h3>Cardápio para Análise <span style="font-size: 0.8em; color: var(--color-text-light);">(<?php echo $cardapio_nome; ?>)</span></h3>
                            <?php if (!$cardapio_id_selecionado): ?>
                                <p id="no-cardapio-selected-msg-visualizacao" class="no-content-message">Selecione um cardápio acima para visualizá-lo.</p>
                            <?php else: ?>
                                <div class="cardapio-visualizacao-table-container">
                                    <table class="cardapio-visualizacao-table" id="cardapio-visualizacao-grid">
                                        <thead>
                                            <tr>
                                                <th>Refeição</th>
                                                <?php foreach ($dias_keys as $dk): ?>
                                                    <th><?php echo $dias_semana_nomes[array_search($dk, $dias_keys)]; ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </section>

                        <section class="cost-results-section">
                            <h3>Custos por Refeição e Totais <span style="font-size: 0.8em; color: var(--color-text-light);">(Cardápio: <?php echo $cardapio_nome; ?>)</span></h3>
                            <?php if (!$cardapio_id_selecionado): ?>
                                <p id="no-cardapio-selected-msg-custos" class="no-content-message">Selecione um cardápio acima para visualizar a análise de custos.</p>
                            <?php else: ?>
                                <div class="costs-table-container">
                                    <table class="costs-table" id="costs-grid">
                                        <thead>
                                            <tr>
                                                <th>Refeição</th>
                                                <?php foreach ($dias_keys as $dk): ?>
                                                    <th><?php echo $dias_semana_nomes[array_search($dk, $dias_keys)]; ?></th>
                                                <?php endforeach; ?>
                                                <th>Total Diário</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            </tbody>
                                        <tfoot>
                                            <tr>
                                                <td>Total Semanal</td>
                                                <td colspan="<?php echo count($dias_keys); ?>" id="total-weekly-cost" class="total-semana">R$ 0,00</td>
                                                <td>Média Diária</td>
                                                <td id="average-daily-cost" class="total-semana">R$ 0,00</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div id="additional-cost-info" style="margin-top: 20px; padding: 15px; border: 1px solid var(--color-light-border); border-radius: var(--border-radius); background-color: var(--color-bg-light);">
                                    <p><strong>Custo Per Capita Médio por Dia:</strong> <span id="avg-cost-per-capita">R$ 0,00</span></p>
                                    <p><strong>Custo Total Semanal para X Pessoas:</strong> <span id="total-weekly-cost-for-x-people">R$ 0,00</span></p>
                                    <p>Considerando um total de <span id="num-pessoas-display-info">100</span> pessoas e <span id="active-days-count-info">5</span> dias ativos.</p>
                                </div>
                            <?php endif; ?>
                        </section>
                        <div id="status-message" class="status info"><i class="fas fa-info-circle"></i> Selecione um cardápio e clique em "Recalcular Custos".</div>
                    </div>

                    <div id="manage-prices-modal" class="modal-overlay">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>Gerenciar Preços de Alimentos</h2>
                                <button type="button" class="modal-close-btn" title="Fechar">×</button>
                            </div>
                            <div class="modal-body">
                                <input type="text" id="price-modal-search" placeholder="Buscar alimento ou preparação..." autocomplete="off">
                                <div class="price-list-container">
                                    <ul id="price-list">
                                        <li class="no-results" style="text-align:center; color:var(--color-text-light); padding:15px;">Nenhum item encontrado.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" id="save-prices-btn" class="action-button confirm modal-button"><i class="fas fa-save"></i> Salvar Preços</button>
                                <button type="button" class="modal-close-btn action-button cancel modal-button"><i class="fas fa-times"></i> Fechar</button>
                            </div>
                        </div>
                    </div>

                    <div id="requisition-modal" class="modal-overlay">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2><i class="fas fa-file-invoice" style="color: var(--color-info-dark);"></i> Requisição de Alimentos</h2>
                                <button type="button" class="modal-close-btn" title="Fechar">×</button>
                            </div>
                            <div class="modal-body">
                                <div class="requisition-summary">
                                    <p><strong>Cardápio:</strong> <span id="req-cardapio-nome"></span></p>
                                    <p><strong>Nº de Pessoas:</strong> <span id="req-num-pessoas"></span></p>
                                    <p><strong>Período:</strong> <span id="req-periodo-dias"></span></p>
                                    <p><strong>Gerado em:</strong> <span id="req-data-geracao"></span></p>
                                </div>
                                
                                <label for="requisition-notes">Observações (opcional):</label>
                                <textarea id="requisition-notes" placeholder="Adicione quaisquer notas importantes para a requisição."></textarea>

                                <h4 style="margin-top: 15px; margin-bottom: 10px; color: var(--color-primary-dark);">Quantidades Totais por Alimento (Semanal)</h4>
                                <ul class="requisition-list" id="total-quantities-list">
                                    </ul>
                            </div>
                            <div class="modal-footer">
                                <button type="button" id="print-requisition-btn" class="action-button info print-button"><i class="fas fa-print"></i> Imprimir</button>
                                <button type="button" class="modal-close-btn action-button cancel modal-button"><i class="fas fa-times"></i> Fechar</button>
                            </div>
                        </div>
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
    //<![CDATA[
    $(document).ready(function() {
        console.log("Custos JS v1.0 carregado.");

        // --- Variáveis Globais ---
        // Definido aqui para escopo correto e evitar ReferenceErrors
        const $sidebar = $('#sidebar');
        const $sidebarToggleButton = $('#sidebar-toggle-button');
        const $sidebarToggleContainer = $('.sidebar-toggle-container'); // Definido aqui

        let currentCardapioId = <?php echo json_encode($cardapio_id_selecionado); ?>;
        const statusMessage = $('#status-message');
        const alimentosCompletos = <?php echo $alimentos_completos_json; ?>;
        // cardapioAtual agora é inicializado com a estrutura garantida pelo PHP
        let cardapioAtual = <?php echo $cardapio_data_json_to_js; ?>; 
        const refeicoesLayout = <?php echo $refeicoes_layout_json_to_js; ?>; // Usar refeicoes_layout_json_to_js do PHP
        const diasKeys = <?php echo $dias_keys_json; ?>;
        const diasNomesMap = <?php echo $dias_nomes_json; ?>;
        let foodPrices = <?php echo $food_prices_json; ?>;

        const managePricesModal = $('#manage-prices-modal');
        const requisitionModal = $('#requisition-modal');
        const $saveCardapioStatus = $('#save-cardapio-status');

        let weeklyTotalQuantities = {}; // Armazena as quantidades totais por alimento para a requisição
        let activeDaysCount = 0; // Armazena a contagem de dias ativos no cálculo

        // --- Funções de Utilitário ---
        function displayMessageBox(message, isConfirm = false, callback = null) {
            const $overlay = $('#custom-message-box-overlay');
            const $messageText = $('#message-box-text');
            const $closeBtn = $overlay.find('.message-box-close-btn');

            $messageText.html(message); // Allows HTML for bold/styling

            $closeBtn.off('click');
            $overlay.find('.modal-button.cancel').remove();

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
                $closeBtn.before($cancelBtn);
            } else {
                $closeBtn.text('OK').css('background-color', 'var(--color-primary)').on('click', () => {
                    $overlay.fadeOut(150, () => { if (callback) callback(); });
                });
            }
            $overlay.css('display', 'flex').hide().fadeIn(200);
        }

        // Helper to format currency
        function formatCurrency(value) {
            if (value === null || isNaN(value)) return 'R$ 0,00';
            return 'R$ ' + parseFloat(value).toFixed(2).replace('.', ',');
        }

        // Helper to sanitize strings for search
        function sanitizeString(str) {
            if (typeof str !== 'string') return '';
            return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9\s]/g, '');
        }

        // Helper to escape HTML
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

        // Function to display status messages
        function showStatus(type, message, iconClass = 'fa-info-circle') {
            statusMessage.removeClass('loading error success warning info').addClass(`status ${type}`).html(`<i class="fas ${iconClass}"></i> ${message}`);
        }

        /**
         * Calcula o custo de um item individual, incluindo preparações recursivamente.
         * @param {string} foodId ID do alimento/preparação.
         * @param {number} total_quantity_g Quantidade total em gramas (ou unidade equivalente base, ex: ml).
         * @param {string} priceCategory Categoria de preço a ser usada.
         * @returns {number} Custo total do item para a quantidade fornecida.
         */
        function calculateItemCost(foodId, total_quantity_g, priceCategory) {
            const itemData = alimentosCompletos[foodId];
            if (!itemData) {
                return 0;
            }

            let price_per_unit = 0;
            let item_default_unit = itemData.default_unit || 'g';
            let unit_type_for_price = item_default_unit;

            // Tenta obter o preço pela categoria selecionada
            if (foodPrices[foodId] && foodPrices[foodId][priceCategory]) {
                price_per_unit = parseFloat(foodPrices[foodId][priceCategory].price || 0);
                unit_type_for_price = foodPrices[foodId][priceCategory].unit || item_default_unit;
            } else if (foodPrices[foodId] && foodPrices[foodId]['default']) {
                // Fallback para o preço padrão se a categoria específica não existir
                price_per_unit = parseFloat(foodPrices[foodId]['default'].price || 0);
                unit_type_for_price = foodPrices[foodId]['default'].unit || item_default_unit;
            }

            if (price_per_unit === 0) {
                // Se for uma preparação e não tiver preço definido, calcula com base nos ingredientes
                if (itemData.isPreparacao && itemData.ingredientes && itemData.ingredientes.length > 0) {
                    let ingredients_cost = 0;
                    itemData.ingredientes.forEach(ingredient => {
                        const ingredient_qty_in_prep_g = parseFloat(ingredient.qty);
                        const prep_portion_g = parseFloat(itemData.porcao_padrao_g || 100);

                        const proportion_factor = (prep_portion_g > 0) ? ingredient_qty_in_prep_g / prep_portion_g : 0;
                        const actual_ingredient_qty_for_cost = total_quantity_g * proportion_factor;

                        ingredients_cost += calculateItemCost(ingredient.foodId, actual_ingredient_qty_for_cost, priceCategory);
                    });
                    return ingredients_cost;
                }
                return 0; // Se não houver preço e não for uma preparação, o custo é 0
            }

            let quantity_in_price_unit = total_quantity_g;

            switch (unit_type_for_price) {
                case 'kg':
                    quantity_in_price_unit = total_quantity_g / 1000;
                    break;
                case 'litro':
                    // Assume 1g = 1ml para líquidos, então 1000ml = 1 litro. Ajuste se a densidade for diferente.
                    quantity_in_price_unit = total_quantity_g / 1000;
                    break;
                case 'unidade':
                    // Para conversão de 'g' para 'unidade', precisaríamos de um fator g/unidade no alimento_db ou food_prices.
                    // Sem isso, a conversão é imprecisa. Assumimos que o preço por unidade é para 1 unidade,
                    // e a per capita já foi ajustada (ex: se 1 pão tem 50g, e per capita é 50g, a "unidade" é 1).
                    // Se o per capita é em gramas para um item precificado por unidade, o cálculo abaixo estaria incorreto.
                    // Para ser exato, precisaríamos de `fator_g_por_unidade` no DB.
                    // Por enquanto, se o preço é por unidade, assume que total_quantity_g já é o número de unidades.
                    // Isso é um ponto a ser refinado se a per capita for sempre em G.
                    break;
            }
            
            return quantity_in_price_unit * price_per_unit;
        }


        /**
         * Calcula os custos diários e semanais e atualiza a tabela de resultados.
         * Também calcula as quantidades totais por ingrediente para a requisição.
         */
        function calculateAndRenderCosts() {
            // Verifica se um cardápio foi selecionado.
            if (!currentCardapioId || !cardapioAtual || !cardapioAtual.dias || !refeicoesLayout || Object.keys(refeicoesLayout).length === 0) {
                showStatus('info', 'Selecione um cardápio para calcular os custos.', 'fa-info-circle');
                clearCostResults();
                $('#no-cardapio-selected-msg-custos').show();
                return;
            }
            
            const numPessoas = parseInt($('#num-pessoas-input').val()) || 0;
            if (numPessoas <= 0) {
                showStatus('warning', 'Número de pessoas inválido. Defina um valor maior que zero.', 'fa-exclamation-triangle');
                clearCostResults();
                return;
            }

            showStatus('loading', 'Calculando custos...', 'fa-spinner fa-spin');
            $('#calculate-costs-btn').prop('disabled', true);

            const priceCategory = $('#price-category-select').val();
            const dailyCosts = {};
            let totalWeeklyCost = 0;
            activeDaysCount = 0; // Reinicia a contagem de dias ativos globais
            weeklyTotalQuantities = {}; // Reinicia as quantidades para a requisição

            // Inicializa dailyCosts para todos os dias e refeições.
            Object.keys(refeicoesLayout).forEach(refKey => {
                if (!refeicoesLayout[refKey]) return; // Garante que a refeição existe no layout

                diasKeys.forEach(dayKey => {
                    dailyCosts[dayKey] = dailyCosts[dayKey] || { total: 0, refeicoes: {} }; // Inicializa dailyCosts[dayKey] se não existir
                    dailyCosts[dayKey].refeicoes[refKey] = 0;
                });
            });


            diasKeys.forEach(dayKey => {
                // Acessa dias_desativados de forma segura
                if (cardapioAtual.dias_desativados && cardapioAtual.dias_desativados[dayKey]) {
                    dailyCosts[dayKey].total = 0;
                    Object.keys(refeicoesLayout).forEach(refKey => {
                        dailyCosts[dayKey].refeicoes[refKey] = 0;
                    });
                    return; 
                }

                let dayTotalCost = 0;
                let hasItemsForDay = false;

                Object.keys(refeicoesLayout).forEach(refKey => {
                    let mealTotalCost = 0;
                    // Acessa itemsInMeal de forma segura
                    const itemsInMeal = (cardapioAtual.dias && cardapioAtual.dias[dayKey] && cardapioAtual.dias[dayKey][refKey]) ? cardapioAtual.dias[dayKey][refKey] : [];

                    itemsInMeal.forEach(item => {
                        if (item && item.foodId && item.qty > 0) {
                            const perCapitaQty = parseFloat(item.qty);
                            const totalQuantityForCost = perCapitaQty * numPessoas;
                            const cost = calculateItemCost(item.foodId, totalQuantityForCost, priceCategory);
                            mealTotalCost += cost;
                            hasItemsForDay = true;

                            // --- Acumular quantidades para a Requisição ---
                            const baseItemData = alimentosCompletos[item.foodId];
                            if (baseItemData) {
                                if (baseItemData.isPreparacao && baseItemData.ingredientes) {
                                    // Para preparações, somar os ingredientes base convertidos para a unidade de compra
                                    const prep_portion_g = parseFloat(baseItemData.porcao_padrao_g || 100);
                                    if (prep_portion_g > 0) {
                                        baseItemData.ingredientes.forEach(ing => {
                                            const ing_qty_in_prep_g = parseFloat(ing.qty);
                                            const ing_factor = ing_qty_in_prep_g / prep_portion_g;
                                            const actual_ing_qty_g = totalQuantityForCost * ing_factor; // Quantidade real do ingrediente base

                                            accumulateQuantity(ing.foodId, actual_ing_qty_g, priceCategory); // Passar priceCategory para pegar a unidade de compra
                                        });
                                    }
                                } else {
                                    // Para alimentos base, somar a quantidade total diretamente
                                    accumulateQuantity(item.foodId, totalQuantityForCost, priceCategory);
                                }
                            }
                        }
                    });
                    dailyCosts[dayKey].refeicoes[refKey] = mealTotalCost;
                    dayTotalCost += mealTotalCost;
                });

                dailyCosts[dayKey].total = dayTotalCost;
                if (hasItemsForDay) {
                    totalWeeklyCost += dayTotalCost;
                    activeDaysCount++;
                }
            });

            // Função auxiliar para acumular quantidades e converter para unidades de compra
            function accumulateQuantity(foodId, quantity_in_g_or_ml, priceCategoryForUnitCheck) {
                const itemData = alimentosCompletos[foodId];
                if (!itemData) return;

                let final_unit = itemData.default_unit || 'g'; // Unidade padrão do alimento no sistema
                let quantity_to_add = quantity_in_g_or_ml;

                // Tentar obter a unidade de compra preferencial do food_prices para o foodId e categoria de preço
                let preferred_buy_unit = itemData.default_unit || 'g';
                if (foodPrices[foodId] && foodPrices[foodId][priceCategoryForUnitCheck] && foodPrices[foodId][priceCategoryForUnitCheck].unit) {
                    preferred_buy_unit = foodPrices[foodId][priceCategoryForUnitCheck].unit;
                } else if (foodPrices[foodId] && foodPrices[foodId]['default'] && foodPrices[foodId]['default'].unit) {
                    preferred_buy_unit = foodPrices[foodId]['default'].unit;
                }
                
                // Conversão de unidades para a Requisição
                switch (preferred_buy_unit) {
                    case 'kg':
                        quantity_to_add = quantity_in_g_or_ml / 1000;
                        final_unit = 'kg';
                        break;
                    case 'litro':
                        quantity_to_add = quantity_in_g_or_ml / 1000; // Assume 1g = 1ml para líquidos
                        final_unit = 'litro';
                        break;
                    case 'unidade':
                        // Se a unidade de compra é 'unidade' e o alimento tem um fator de conversão de g/unidade
                        // Idealmente, esse fator viria do `alimentosCompletos[foodId].g_por_unidade` ou similar.
                        // Como não temos, para ser preciso, essa conversão de "g" para "unidade"
                        // requer que você tenha esse fator em seu `alimentos_db` ou no JSON de preços.
                        // Por simplicidade, se for "unidade", e o per capita é em "g", manteremos a soma em "g"
                        // mas indicaremos "unidade" como unidade final. Isso pode ser um ponto de imprecisão
                        // se o fator não for 1:1.
                        // Ex: se 1 unidade = 50g, e a per capita deu 200g, seriam 4 unidades.
                        // Mas sem o fator, exibiríamos "200.00 unidades" (que é a soma em gramas, não em unidades).
                        // Para resolver isso, precisaria de: `itemData.g_por_unidade`
                        // if (itemData.g_por_unidade && itemData.g_por_unidade > 0) {
                        //    quantity_to_add = quantity_in_g_or_ml / itemData.g_por_unidade;
                        // }
                        final_unit = 'unidade';
                        break;
                    default:
                        // Mantém a unidade padrão (g ou ml)
                        final_unit = itemData.default_unit || 'g';
                        break;
                }

                if (!weeklyTotalQuantities[foodId]) {
                    weeklyTotalQuantities[foodId] = {
                        name: itemData.nome,
                        total_qty: 0,
                        unit: final_unit,
                        is_prep: itemData.isPreparacao
                    };
                }
                weeklyTotalQuantities[foodId].total_qty += quantity_to_add;
            }

            const averageDailyCost = activeDaysCount > 0 ? totalWeeklyCost / activeDaysCount : 0;

            renderCostResults(dailyCosts, totalWeeklyCost, averageDailyCost, numPessoas, activeDaysCount);
            renderCardapioVisualizacao(); // Renderiza o cardápio completo sem edição

            showStatus('success', 'Cálculo de custos concluído!', 'fa-check-circle');
            $('#calculate-costs-btn').prop('disabled', false);
            // Esconde as mensagens de "Selecione um cardápio"
            $('#no-cardapio-selected-msg-visualizacao').hide();
            $('#no-cardapio-selected-msg-custos').hide();
        }

        /**
         * Renderiza a tabela de custos.
         * @param {Object} dailyCosts Custos diários por refeição e totais.
         * @param {number} totalWeeklyCost Custo semanal total.
         * @param {number} averageDailyCost Custo diário médio.
         * @param {number} numPessoas Número de pessoas consideradas.
         * @param {number} activeDaysCount Número de dias ativos no cálculo.
         */
        function renderCostResults(dailyCosts, totalWeeklyCost, averageDailyCost, numPessoas, activeDaysCount) {
            const $costsGridBody = $('#costs-grid tbody');
            $costsGridBody.empty(); // Limpa o corpo da tabela.

            // Renderiza as linhas para cada refeição.
            Object.entries(refeicoesLayout).forEach(([refKey, refInfo]) => {
                let rowHtml = `<tr><td class="refeicao-label">${htmlspecialchars(refInfo.label)}</td>`;
                diasKeys.forEach(dayKey => {
                    // Acessa dias_desativados de forma segura
                    if (cardapioAtual.dias_desativados && cardapioAtual.dias_desativados[dayKey]) { 
                        rowHtml += `<td style="color: var(--disabled-day-text); background-color: var(--disabled-day-bg);">--</td>`;
                    } else {
                        // Acessa os custos de forma segura
                        const cost = (dailyCosts[dayKey] && dailyCosts[dayKey].refeicoes && dailyCosts[dayKey].refeicoes[refKey]) ? dailyCosts[dayKey].refeicoes[refKey] : 0;
                        rowHtml += `<td>${formatCurrency(cost)}</td>`;
                    }
                });
                // Célula para o Total Semanal daquela Refeição (soma em todos os dias ativos)
                let totalRefeicaoSemanal = 0;
                diasKeys.forEach(dayKey => {
                    if (cardapioAtual.dias_desativados && !cardapioAtual.dias_desativados[dayKey]) {
                        totalRefeicaoSemanal += (dailyCosts[dayKey] && dailyCosts[dayKey].refeicoes && dailyCosts[dayKey].refeicoes[refKey]) ? dailyCosts[dayKey].refeicoes[refKey] : 0;
                    }
                });
                rowHtml += `<td class="total-dia">${formatCurrency(totalRefeicaoSemanal)}</td>`;
                rowHtml += `</tr>`; 
                $costsGridBody.append(rowHtml);
            });

            // Renderiza a linha de totais diários (abaixo das refeições).
            let dailyTotalsRowHtml = `<tr class="total-row"><td class="refeicao-label">Total Diário</td>`;
            diasKeys.forEach(dayKey => {
                // Acessa os totais diários de forma segura
                const totalCost = (dailyCosts[dayKey] && dailyCosts[dayKey].total) ? dailyCosts[dayKey].total : 0;
                // Se o dia estiver desativado, exibe "--"
                if (cardapioAtual.dias_desativados && cardapioAtual.dias_desativados[dayKey]) { 
                    dailyTotalsRowHtml += `<td class="total-dia" style="color: var(--disabled-day-text); background-color: var(--disabled-day-bg);">--</td>`;
                } else {
                    dailyTotalsRowHtml += `<td class="total-dia">${formatCurrency(totalCost)}</td>`;
                }
            });
            dailyTotalsRowHtml += `<td class="total-dia">${formatCurrency(totalWeeklyCost)}</td>`; // Custo total semanal
            dailyTotalsRowHtml += `</tr>`;
            $costsGridBody.append(dailyTotalsRowHtml);

            // Atualiza os totais no rodapé da tabela.
            $('#total-weekly-cost').text(formatCurrency(totalWeeklyCost)); 
            $('#average-daily-cost').text(formatCurrency(averageDailyCost));

            // Informações adicionais
            const avgCostPerCapita = numPessoas > 0 && activeDaysCount > 0 ? totalWeeklyCost / (numPessoas * activeDaysCount) : 0;
            $('#avg-cost-per-capita').text(formatCurrency(avgCostPerCapita));
            $('#total-weekly-cost-for-x-people').text(formatCurrency(totalWeeklyCost));
            $('#num-pessoas-display-info').text(numPessoas);
            $('#active-days-count-info').text(activeDaysCount);
            $('#additional-cost-info').show();
        }

        // Function to clear cost table results
        function clearCostResults() {
            $('#costs-grid tbody').empty();
            $('#total-weekly-cost').text('R$ 0,00');
            $('#average-daily-cost').text('R$ 0,00');
            $('#additional-cost-info').hide(); // Esconde info adicional quando não há resultados
        }

        /**
         * Renderiza a tabela de visualização do cardápio (somente leitura).
         */
        function renderCardapioVisualizacao() {
            const $tableBody = $('#cardapio-visualizacao-grid tbody');
            $tableBody.empty(); // Limpa o corpo da tabela

            if (!cardapioAtual || !cardapioAtual.dias || !refeicoesLayout || Object.keys(refeicoesLayout).length === 0) {
                $('#no-cardapio-selected-msg-visualizacao').show();
                return;
            }

            Object.entries(refeicoesLayout).forEach(([refKey, refInfo]) => {
                const $tr = $('<tr></tr>');
                // Coluna de Refeição (Nome e Horário)
                $tr.append(`<td><span class="meal-header-label">${htmlspecialchars(refInfo.label)}</span><span class="meal-horario-label">${htmlspecialchars(refInfo.horario)}</span></td>`);
                
                diasKeys.forEach(dayKey => {
                    const $td = $('<td></td>');
                    // Adiciona overlay se o dia estiver desativado
                    if (cardapioAtual.dias_desativados && cardapioAtual.dias_desativados[dayKey]) {
                        $td.addClass('disabled-day').append('<div class="day-disabled-overlay">DIA INATIVO</div>');
                    }

                    // Acessa os itens da refeição de forma segura
                    const itemsInMeal = (cardapioAtual.dias && cardapioAtual.dias[dayKey] && cardapioAtual.dias[dayKey][refKey]) ? cardapioAtual.dias[dayKey][refKey] : [];
                    const $itemList = $('<ul class="item-list-display"></ul>');

                    if (itemsInMeal.length === 0) {
                        $itemList.append('<li>- Vazio -</li>');
                    } else {
                        itemsInMeal.forEach(item => {
                            const foodData = alimentosCompletos[item.foodId];
                            if (foodData) {
                                const foodName = htmlspecialchars(foodData.nome);
                                const isPrepIcon = foodData.isPreparacao ? '<i class="fas fa-mortar-pestle" style="color:var(--color-warning-dark);margin-right:4px;font-size:0.9em;" title="Preparação"></i> ' : '';
                                const unitLabel = htmlspecialchars(foodData.default_unit || 'g');
                                $itemList.append(`<li><span class="item-name-display">${isPrepIcon}${foodName}</span> <span class="item-qty-display">${parseFloat(item.qty).toFixed(2).replace('.', ',')} ${unitLabel}</span></li>`);
                            }
                        });
                    }
                    $td.append($itemList);
                    $tr.append($td);
                });
                $tableBody.append($tr);
            });
            $('#no-cardapio-selected-msg-visualizacao').hide(); // Esconde a mensagem se a tabela for renderizada
        }


        // --- Event Listeners ---
        // Lida com a mudança de seleção do cardápio no dropdown.
        $('#cardapio-select').on('change', function() { 
            const newCardapioId = $(this).val();
            // Redireciona a página para carregar o novo cardápio selecionado via GET.
            window.location.href = `custos.php?cardapio_id=${newCardapioId}`; 
        });

        // Evento para o botão "Recalcular Custos".
        $('#calculate-costs-btn').on('click', calculateAndRenderCosts); 
        // Recalcula custos quando a categoria de preço muda.
        $('#price-category-select').on('change', calculateAndRenderCosts); 
        // Recalcula custos quando o número de pessoas muda.
        $('#num-pessoas-input').on('change', calculateAndRenderCosts); 

        // --- Gerenciamento de Preços (Modal) ---
        // Abre o modal de gerenciamento de preços.
        $('#manage-prices-btn').on('click', function() { 
            populatePriceList(''); // Popula a lista de preços inicialmente (vazia para mostrar todos).
            // Exibe o modal e foca no campo de busca.
            managePricesModal.css('display', 'flex').hide().fadeIn(200, function() {
                $('#price-modal-search').focus(); // Foca no campo de busca.
            });
        });
        // Fecha o modal de preços ao clicar no botão "Fechar".
        $('#manage-prices-modal .modal-close-btn').on('click', function() { 
            managePricesModal.fadeOut(150, function() { $(this).css('display', 'none'); });
        });
        // Fecha o modal de preços ao clicar fora dele.
        $(document).on('click', function(event) { 
            if ($(event.target).is(managePricesModal)) {
                managePricesModal.fadeOut(150, function() { $(this).css('display', 'none'); });
            }
        });
        // Lida com a busca no modal de preços.
        $('#price-modal-search').on('keyup', function() { 
            populatePriceList($(this).val());
        });

        /**
         * Popula a lista de preços no modal, com base no termo de busca.
         * Inclui campos para preço e seleção de unidade (g, kg, unid, litro).
         * @param {string} searchTerm Termo de busca para filtrar os alimentos.
         */
        function populatePriceList(searchTerm) {
            const $priceList = $('#price-list');
            $priceList.empty(); // Limpa a lista existente.
            const term = sanitizeString(searchTerm); // Sanitiza o termo de busca.
            let count = 0;

            // Ordena os alimentos alfabeticamente para exibição.
            const sortedAlimentos = Object.values(alimentosCompletos).sort((a, b) => (a.nome || '').localeCompare(b.nome || '', 'pt-BR', { sensitivity: 'base' })); 
            // Categorias de preço disponíveis.
            const priceCategories = ['default', 'last_updated', 'fornecedor_a', 'fornecedor_b']; 

            sortedAlimentos.forEach(itemData => {
                const foodId = itemData.id; 
                const nome = itemData.nome || 'Inválido';
                const nomeSanitized = sanitizeString(nome);
                const isPrep = itemData.isPreparacao || false;
           
                const itemDefaultUnit = itemData.default_unit || 'g'; 

                if (term === '' || nomeSanitized.includes(term)) {
                    const currentPrices = foodPrices[foodId] || {}; // Obtém os preços existentes para este item.
                    let priceHtml = '';

                    // Para cada categoria de preço, cria um grupo de input (preço + unidade).
                    priceCategories.forEach(category => { 
                        const price = currentPrices[category]?.price ?? ''; 
                        const unit = currentPrices[category]?.unit || itemDefaultUnit;

                        priceHtml += `
                            <div class="price-input-group">
                                <label>${category === 'default' ? 'Padrão' : category.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}:</label>
                                <span class="currency-symbol">R$</span>
                                <input type="number" step="0.01" min="0" class="price-input" data-food-id="${foodId}" data-category="${category}" value="${price}">
                                <select class="unit-select" data-food-id="${foodId}" data-category="${category}">
                                    <option value="g" ${unit === 'g' ? 'selected' : ''}>g</option>
                                    <option value="kg" ${unit === 'kg' ? 'selected' : ''}>kg</option>
                                    <option value="unidade" ${unit === 'unidade' ? 'selected' : ''}>unid</option>
                                    <option value="litro" ${unit === 'litro' ? 'selected' : ''}>litro</option>
                                </select>
                            </div>
                        `;
                    });

                    // Cria o item da lista (li) com o nome do alimento e os inputs de preço.
                    const li = $(`
                        <li>
                            <span class="item-name">${isPrep ? '<i class="fas fa-mortar-pestle" style="color:var(--color-warning-dark);margin-right:4px;font-size:0.9em;" title="Preparação"></i> ' : ''}${htmlspecialchars(nome)}</span>
                            ${priceHtml}
                        </li>
                    `);
                    $priceList.append(li); // Adiciona o item à lista.
                    count++;
                }
            });
            if (count === 0) {
                $priceList.append('<li class="no-results" style="text-align:center; color:var(--color-text-light); padding:15px;">Nenhum item encontrado.</li>');
            }
        }

        // Lida com o clique no botão "Salvar Preços" no modal de gerenciamento de preços.
        $('#save-prices-btn').on('click', function() { 
            const updatedPrices = {};
            // Itera sobre todos os itens da lista de preços no modal.
            $('#price-list li:not(.no-results)').each(function() { 
                // Para cada grupo de input (preço + unidade) dentro do item.
                $(this).find('.price-input-group').each(function() { 
                    const $input = $(this).find('.price-input');
                    const foodId = $input.data('food-id').toString();
                    const category = $input.data('category');
                    const price = parseFloat($input.val());
                    const unit = $(this).find('.unit-select').val();

                    if (!updatedPrices[foodId]) {
                        updatedPrices[foodId] = {};
                    }
                    // Armazena o preço e a unidade atualizados.
                    updatedPrices[foodId][category] = { 
                        price: isNaN(price) ? 0 : price,
                        unit: unit
                    };
                });
            });

            // Envia os preços atualizados para o backend.
            $.ajax({ 
                url: 'save_food_prices.php', // URL do script que salva os preços.
                method: 'POST', // Método HTTP POST.
                data: { food_prices: JSON.stringify(updatedPrices) }, // Dados JSON dos preços.
                dataType: 'json', // Espera JSON como resposta.
                beforeSend: function() { // Antes de enviar a requisição.
                    $('#save-prices-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...');
                },
                success: function(response) { // Em caso de sucesso.
                    if (response.success) {
                        foodPrices = updatedPrices; // Atualiza a variável global de preços.
                        showStatus('success', 'Preços salvos com sucesso!', 'fa-check-circle'); // Exibe status de sucesso.
                        managePricesModal.fadeOut(150); // Fecha o modal.
                        calculateAndRenderCosts(); // Recalcula os custos com os novos preços.
                    } else {
                        showStatus('error', 'Erro ao salvar preços: ' + (response.message || 'Desconhecido.'), 'fa-times-circle');
                        displayMessageBox('Erro ao salvar preços: ' + (response.message || 'Desconhecido. Verifique o console para mais detalhes.'), false);
                        console.error("Erro no response de save_food_prices:", response.message);
                    }
                },
                error: function(jqXHR) { // Em caso de erro na requisição AJAX.
                    showStatus('error', 'Erro de comunicação ao salvar preços. Tente novamente.', 'fa-times-circle');
                    displayMessageBox('Erro de comunicação ao salvar preços. Veja o console para detalhes.', false);
                    console.error("Erro AJAX save_food_prices:", jqXHR.status, jqXHR.responseText);
                },
                complete: function() { // Executado ao final da requisição.
                    $('#save-prices-btn').prop('disabled', false).html('<i class="fas fa-save"></i> Salvar Preços');
                }
            });
        });

        // --- Requisição de Alimentos (Modal) ---
        $('#requisition-btn').on('click', function() {
            // Verifica se há um cardápio selecionado
            if (!currentCardapioId || !cardapioAtual || !cardapioAtual.dias || !refeicoesLayout || Object.keys(refeicoesLayout).length === 0) {
                displayMessageBox('Selecione um cardápio e calcule os custos antes de gerar a requisição.', false);
                return;
            }
            if (Object.keys(weeklyTotalQuantities).length === 0) {
                 displayMessageBox('Não há alimentos calculados para a requisição. Certifique-se de que o cardápio não está vazio e clique em "Recalcular Custos".', false);
                return;
            }

            // Preenche o modal de requisição com os dados do cardápio e quantidades
            $('#req-cardapio-nome').text(cardapioAtual.nome_projeto);
            $('#req-num-pessoas').text($('#num-pessoas-input').val());
            
            // Período (dias ativos)
            let activeDaysDisplay = [];
            diasKeys.forEach(dayKey => {
                // Acessa dias_desativados de forma segura
                if (cardapioAtual.dias_desativados && !cardapioAtual.dias_desativados[dayKey]) {
                    activeDaysDisplay.push(diasNomesMap[dayKey].split('-')[0]); // Ex: "Segunda" de "Segunda-feira"
                }
            });
            let periodText = activeDaysDisplay.length === 0 ? "Nenhum dia ativo" : activeDaysDisplay.join(', ');
            $('#req-periodo-dias').text(periodText + ` (${activeDaysCount} dias)`); // Usa activeDaysCount global

            $('#req-data-geracao').text(new Date().toLocaleDateString('pt-BR'));
            $('#requisition-notes').val(''); // Limpa as notas anteriores

            const $totalQuantitiesList = $('#total-quantities-list');
            $totalQuantitiesList.empty();

            // Ordena os itens da requisição por nome
            const sortedRequisitionItems = Object.values(weeklyTotalQuantities).sort((a,b) => a.name.localeCompare(b.name, 'pt-BR', { sensitivity: 'base' }));

            sortedRequisitionItems.forEach(item => {
                let qtyFormatted;
                // Formatação específica para cada unidade
                if (item.unit === 'kg' || item.unit === 'litro') {
                    qtyFormatted = item.total_qty.toFixed(3).replace('.', ','); // Três casas para kg/litro
                } else if (item.unit === 'unidade') {
                    // Para unidades, se a quantidade for inteira, mostra como inteira. Caso contrário, duas casas.
                    qtyFormatted = Number.isInteger(item.total_qty) ? item.total_qty.toString() : item.total_qty.toFixed(2).replace('.', ',');
                }
                else { // Para gramas (g) ou outros, duas casas decimais
                    qtyFormatted = item.total_qty.toFixed(2).replace('.', ',');
                }
                
                $totalQuantitiesList.append(`
                    <li>
                        <span>${htmlspecialchars(item.name)}</span>
                        <span>${qtyFormatted} ${item.unit}</span>
                    </li>
                `);
            });

            openModal(requisitionModal);
        });

        $('#print-requisition-btn').on('click', function() {
            // Esconde os botões e elementos de controle que não devem aparecer na impressão
            const tempHideElements = $('.modal-footer, .modal-header .modal-close-btn');
            tempHideElements.hide(); // Esconde temporariamente

            const printContent = requisitionModal.find('.modal-content').html(); // Pega o HTML do conteúdo do modal

            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Requisição de Alimentos</title>
                    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
                    <style>
                        body { font-family: 'Roboto', sans-serif; margin: 20px; }
                        h2 { text-align: center; color: #1976D2; margin-bottom: 20px; }
                        .requisition-summary p { margin: 5px 0; font-size: 14px; }
                        .requisition-list { list-style: none; padding: 0; margin-top: 15px; } /* Reset margin for print */
                        .requisition-list li { padding: 8px 0; border-bottom: 1px dashed #E9ECEF; display: flex; justify-content: space-between; font-size: 14px; }
                        .requisition-list li:last-child { border-bottom: none; }
                        .requisition-list li span:first-child { font-weight: 500; color: #1976D2; }
                        .requisition-list li span:last-child { font-weight: 600; }
                        h4 { color: #343a40; margin-top: 15px; margin-bottom: 10px;} /* Estilo para o título das quantidades */
                        textarea { width: 100%; border: none; font-family: inherit; font-size: 1em; resize: none; overflow: hidden; } /* Para imprimir o textarea como texto */
                        /* Esconder elementos de UI que não são de impressão */
                        .no-print { display: none !important; }
                    </style>
                </head>
                <body>
                    <div class="printable-content">
                        ${printContent}
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();

            // Restaurar os elementos escondidos após a escrita do conteúdo de impressão
            tempHideElements.show();

            // Certificar-se de que os valores dos campos de texto (textarea) sejam transferidos corretamente para a impressão
            // Já que .html() pode não capturar o value de textareas.
            printWindow.document.getElementById('requisition-notes').textContent = $('#requisition-notes').val();


            printWindow.focus();
            printWindow.print();
        });


        // --- Inicialização ---
        // Renderiza o cardápio e calcula os custos na carga da página se um cardápio estiver selecionado.
        if (currentCardapioId) {
            renderCardapioVisualizacao(); // Renderiza o cardápio (somente leitura)
            calculateAndRenderCosts(); // Calcula e renderiza os custos
        } else {
            showStatus('info', 'Selecione um cardápio para visualizar a análise de custos.', 'fa-info-circle');
            clearCostResults(); // Limpa a tabela de resultados de custos
            $('#no-cardapio-selected-msg-visualizacao').show(); // Exibe mensagem para selecionar cardápio para visualização
            $('#no-cardapio-selected-msg-custos').show(); // Exibe mensagem para selecionar cardápio para custos
        }

        // --- Sidebar Toggle Functionality (Corrigido para evitar ReferenceError) ---
        // Acesso direto às variáveis globais da sidebar
        // As variáveis $sidebar, $sidebarToggleButton, $sidebarToggleContainer JÁ ESTÃO DEFINIDAS NO INÍCIO DO $(document).ready()

        $sidebarToggleButton.on('click', function() {
            $sidebar.toggleClass('collapsed'); // Alterna a classe 'collapsed' na sidebar
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
            
            // Para links da navbar e links principais da sidebar, redirecionar para a home com o parâmetro da plataforma
            if ($(this).hasClass('navbar-brand') || $(this).hasClass('sidebar-top-link')) {
                window.location.href = 'home.php?platform=' + platformTarget;
            } else {
                // Para links de sub-menus (Gerenciar Cardápios, Fichas Técnicas, etc.), navegar diretamente
                window.location.href = $(this).attr('href');
            }
        }
        
        // Atribui o manipulador de eventos aos links da navbar e sidebar
        $('.navbar-brand').on('click', handlePlatformLink);
        $('.sidebar-nav a, .sidebar-nav details summary').on('click', handlePlatformLink);

        // Ativa o link "Análise de Custos" na sidebar ao carregar a página
        $('a[href="custos.php"]').addClass('active');
        // Garante que o menu "NutriPNAE" esteja aberto se "Análise de Custos" está ativo
        $('a[href="custos.php"]').parents('details').prop('open', true).find('summary').addClass('active');


        // Hide/show sidebar toggle button on larger/smaller screens
        function checkSidebarToggleVisibility() {
            // Verifica se as variáveis estão definidas antes de usar.
            // Esta função será chamada no final do $(document).ready() para garantir que tudo esteja carregado.
            if (typeof $sidebarToggleContainer === 'undefined' || typeof $sidebar === 'undefined' || typeof $sidebarToggleButton === 'undefined') {
                console.error("Variáveis da sidebar não definidas. checkSidebarToggleVisibility não pode ser executada.");
                return; // Sai da função se as variáveis essenciais não existirem
            }

            if (window.innerWidth <= 1024) { // Mobile/Tablet
                $sidebarToggleContainer.show(); // Show the button container
                $sidebar.removeClass('collapsed'); // Ensure sidebar is expanded by default on mobile
                $sidebarToggleButton.find('span').text('Minimizar');
                $sidebarToggleButton.find('i').removeClass('fa-chevron-right').addClass('fa-chevron-left');
                // Adicionado: Esconde o nav-menu em telas pequenas por padrão, será ativado pelo clique no botão
                // A classe 'active' para exibir o menu mobile será toggled pelo botão.
                $('#sidebar-nav').removeClass('active'); 
            } else { // Desktop
                $sidebarToggleContainer.show(); // Always show button on desktop to allow collapse
                $sidebar.removeClass('collapsed'); // Ensure sidebar is expanded by default on desktop
                $sidebarToggleButton.find('span').text('Minimizar');
                $sidebarToggleButton.find('i').removeClass('fa-chevron-right').addClass('fa-chevron-left');
                $('#sidebar-nav').removeClass('active'); // Garante que o menu mobile esteja fechado por padrão ao redimensionar
            }
        }

        // Initial check and on resize
        // Chamar no final do $(document).ready() para garantir que todas as variáveis estejam prontas.
        checkSidebarToggleVisibility();
        $(window).on('resize', checkSidebarToggleVisibility);

    });
    //]]>
    </script>
</body>
</html>