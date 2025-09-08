<?php
// cardapio_auto/index.php - Página de Edição e Montagem de Cardápios

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
$is_development = true; // Mude para false em produção final
ini_set('display_errors', $is_development ? 1 : 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_log("--- Iniciando index.php (Calculadora Nutricional) --- | SID: " . session_id());

// 3. Verificação de Autenticação
$is_logged_in = isset($_SESSION['user_id']);
$logged_user_id = $_SESSION['user_id'] ?? null;
$logged_username = $_SESSION['username'] ?? 'Usuário';

if (!$is_logged_in || !$logged_user_id) {
    ob_start(); header('Location: login.php'); ob_end_flush(); exit;
}

// 4. Conexão com Banco de Dados e Carregamento do Projeto
$projeto_id = filter_input(INPUT_GET, 'projeto_id', FILTER_VALIDATE_INT);
$projeto_nome_original = "Cardápio Inválido";
$cardapio_data_db = null;
$db_connection_error = false;
$load_error_message = null;
$pdo = null;
$cardapio_processado = null;

$refeicoes_layout_default = ['ref_1' => ['label' => 'REFEIÇÃO 1', 'horario' => "09:00"], 'ref_2' => ['label' => 'REFEIÇÃO 2', 'horario' => "14:30"]];
$dias_keys = ['seg', 'ter', 'qua', 'qui', 'sex'];
$dias_semana_nomes = ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta'];
$faixa_etaria_default_key = 'fund_6_10';

if (!$projeto_id) {
    ob_start(); header('Location: home.php?erro=projeto_invalido'); ob_end_flush(); exit;
}

try {
    require_once 'includes/db_connect.php';
    if (!isset($pdo)) { throw new \RuntimeException("PDO não definido por db_connect.php"); }

    $sql = "SELECT nome_projeto, dados_json FROM cardapio_projetos WHERE id = :projeto_id AND usuario_id = :usuario_id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':projeto_id', $projeto_id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $logged_user_id, PDO::PARAM_INT);
    $stmt->execute();
    $projeto = $stmt->fetch();

    if (!$projeto) {
        ob_start(); header('Location: home.php?erro=acesso_negado'); ob_end_flush(); exit;
    }
    $projeto_nome_original = $projeto['nome_projeto'];
    $cardapio_data_db = $projeto['dados_json'];
} catch (\PDOException $e) {
    $db_connection_error = true; $load_error_message = "Erro BD ao buscar dados.";
    error_log("PDOException index.php (Proj $projeto_id): " . $e->getMessage());
} catch (\Throwable $th) {
     $db_connection_error = true; $load_error_message = "Erro inesperado ao carregar.";
     error_log("Throwable index.php (Proj $projeto_id): " . $th->getMessage());
}

$faixa_etaria_inicial_key = $faixa_etaria_default_key;
if (!$db_connection_error && $projeto) {
    if ($cardapio_data_db && $cardapio_data_db !== '{}' && $cardapio_data_db !== 'null' && $cardapio_data_db !== '[]') {
        $decoded_data = json_decode($cardapio_data_db, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_data) && isset($decoded_data['dias'], $decoded_data['refeicoes'])) {
            $cardapio_processado = $decoded_data;
            $cardapio_processado['refeicoes'] = (is_array($cardapio_processado['refeicoes']) && !empty($cardapio_processado['refeicoes'])) ? $cardapio_processado['refeicoes'] : $refeicoes_layout_default;
            $cardapio_processado['datas_dias'] = $cardapio_processado['datas_dias'] ?? array_fill_keys($dias_keys, '');
            $cardapio_processado['dias_desativados'] = $cardapio_processado['dias_desativados'] ?? array_fill_keys($dias_keys, false);
            $cardapio_processado['faixa_etaria_selecionada'] = $cardapio_processado['faixa_etaria_selecionada'] ?? $faixa_etaria_default_key;
            $faixa_etaria_inicial_key = $cardapio_processado['faixa_etaria_selecionada'];
            $refeicoes_validas_keys = array_keys($cardapio_processado['refeicoes']);
            foreach ($dias_keys as $dia) {
                 if (!isset($cardapio_processado['dias'][$dia]) || !is_array($cardapio_processado['dias'][$dia])) { $cardapio_processado['dias'][$dia] = array_fill_keys($refeicoes_validas_keys, []); }
                foreach ($refeicoes_validas_keys as $ref_key) {
                     if (!isset($cardapio_processado['dias'][$dia][$ref_key]) || !is_array($cardapio_processado['dias'][$dia][$ref_key])) { $cardapio_processado['dias'][$dia][$ref_key] = []; }
                     $cardapio_processado['dias'][$dia][$ref_key] = array_values(array_filter($cardapio_processado['dias'][$dia][$ref_key], fn($item) => is_array($item) && isset($item['foodId'], $item['qty'], $item['instanceGroup'], $item['placementId']) && is_scalar($item['foodId']) && is_numeric($item['qty']) && $item['qty'] > 0 && is_numeric($item['instanceGroup']) && $item['instanceGroup'] > 0 && !empty($item['placementId']) ));
                }
                $cardapio_processado['dias'][$dia] = array_intersect_key($cardapio_processado['dias'][$dia], $cardapio_processado['refeicoes']);
            }
        } else {
            $load_error_message = "Atenção: Dados salvos corrompidos/formato antigo. Iniciando com padrão."; $cardapio_processado = null;
        }
    } else { $cardapio_processado = null; }
}
if ($cardapio_processado === null) {
    $cardapio_processado = ['refeicoes' => $refeicoes_layout_default, 'dias' => [], 'datas_dias' => array_fill_keys($dias_keys, ''), 'dias_desativados' => array_fill_keys($dias_keys, false), 'faixa_etaria_selecionada' => $faixa_etaria_default_key ];
    foreach ($dias_keys as $dia) { $cardapio_processado['dias'][$dia] = array_fill_keys(array_keys($refeicoes_layout_default), []); }
    $faixa_etaria_inicial_key = $faixa_etaria_default_key;
    if (!$load_error_message && !$db_connection_error && $cardapio_data_db !== null && $cardapio_data_db !== '{}' && $cardapio_data_db !== 'null') { $load_error_message = $load_error_message ?: "Dados em formato inesperado. Iniciado com padrão."; }
}

$preparacoes_usuario = [];
if (!$db_connection_error && $logged_user_id && isset($pdo)) { try { $sql_prep = "SELECT preparacoes_personalizadas_json FROM cardapio_usuarios WHERE id = :user_id LIMIT 1"; $stmt_prep = $pdo->prepare($sql_prep); $stmt_prep->bindParam(':user_id', $logged_user_id, PDO::PARAM_INT); $stmt_prep->execute(); $json_preps = $stmt_prep->fetchColumn(); if ($json_preps && $json_preps !== 'null' && $json_preps !== '{}' && $json_preps !== '[]') { $decoded_preps = json_decode($json_preps, true); if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_preps) && (empty($decoded_preps) || array_keys($decoded_preps) !== range(0, count($decoded_preps)-1))) { $preparacoes_usuario = $decoded_preps; } } } catch (PDOException $e) { $load_error_message = ($load_error_message?" | ":"")."Erro carregar preparações.";}}

$dados_base_ok = false; $lista_selecionaveis_db = []; $alimentos_db = []; $todas_porcoes_db = []; $todos_pnae_ref = [];
try {
    $dados_file = __DIR__ . '/dados.php'; if (!file_exists($dados_file) || !is_readable($dados_file)) { throw new Exception("dados.php não encontrado."); } ob_start(); require $dados_file; $output = ob_get_clean(); if (!empty($output)) { error_log("AVISO: Saída dados.php (index): ".substr($output,0,50)); }
    $dados_ok_interno = $dados_ok ?? false; $dados_essenciais_ok = (isset($lista_selecionaveis_db,$alimentos_db,$todas_porcoes_db,$todos_pnae_ref)&&is_array($lista_selecionaveis_db)&&!empty($lista_selecionaveis_db)&&is_array($alimentos_db)&&!empty($alimentos_db)&&is_array($todas_porcoes_db)&&!empty($todas_porcoes_db)&&is_array($todos_pnae_ref)&&!empty($todos_pnae_ref)); $dados_base_ok=($dados_ok_interno===true&&$dados_essenciais_ok);
    if(!$dados_base_ok){$erro_msg_dbase="Falha dados.php.";if(!$dados_ok_interno)$erro_msg_dbase.="(flag)";if(!$dados_essenciais_ok)$erro_msg_dbase.="(vazio)";$load_error_message=($load_error_message?" | ":"").$erro_msg_dbase;}
} catch (Throwable $e) { if(ob_get_level()>0)ob_end_clean();$dados_base_ok=false;$load_error_message=($load_error_message?" | ":"")."Erro fatal dados.php: ".$e->getMessage();}

$alimentos_id_nome_map=[]; $alimentos_para_js=[]; $faixa_pnae_opcoes=[]; $faixa_pnae_titulo_inicial='Selecione Faixa';$todas_porcoes_db_json='{}'; $cardapio_inicial_formatado_para_js=[]; $initial_instance_groups=[];
$refeicoes_layout_atual=$cardapio_processado['refeicoes']; $refeicoes_keys_atuais=array_keys($refeicoes_layout_atual); $faixa_etaria_inicial_key=$cardapio_processado['faixa_etaria_selecionada'];
if($dados_base_ok && !$db_connection_error){foreach($lista_selecionaveis_db as $id=>$data){if(isset($data['nome'])&&isset($alimentos_db[$id])){$id_str=(string)$id;$alimentos_id_nome_map[$id_str]=$data['nome'];$alimentos_para_js[$id_str]=['id'=>$id_str,'nome'=>$data['nome'],'porcao_padrao'=>(int)($todas_porcoes_db[$faixa_etaria_default_key][$id_str]??100),'isPreparacao'=>false,'ingredientes'=>[]];}}if(!empty($preparacoes_usuario)){foreach($preparacoes_usuario as $prep_id=>$prep_data){if(isset($prep_data['nome'],$prep_data['ingredientes'],$prep_data['porcao_padrao_g'])){$id_str=(string)$prep_id;$alimentos_id_nome_map[$id_str]=$prep_data['nome'];$ings_corr=[];if(is_array($prep_data['ingredientes'])){$ings_corr=$prep_data['ingredientes'];}elseif(isset($prep_data['ingredientes_json'])){$dec_ings=json_decode($prep_data['ingredientes_json'],true);if(json_last_error()===JSON_ERROR_NONE&&is_array($dec_ings)){$ings_corr=$dec_ings;}}$alimentos_para_js[$id_str]=['id'=>$id_str,'nome'=>$prep_data['nome'],'porcao_padrao'=>max(1,(int)($prep_data['porcao_padrao_g']??100)),'isPreparacao'=>true,'ingredientes'=>$ings_corr];}}}uasort($alimentos_para_js,fn($a,$b)=>strcasecmp($a['nome']??'',$b['nome']??''));foreach($todos_pnae_ref as $key=>$ref){$faixa_pnae_opcoes[$key]=htmlspecialchars($ref['faixa']??$key);}if(!isset($faixa_pnae_opcoes[$faixa_etaria_inicial_key])){$faixa_etaria_inicial_key=key($faixa_pnae_opcoes)?:$faixa_etaria_default_key;$cardapio_processado['faixa_etaria_selecionada']=$faixa_etaria_inicial_key;} $faixa_pnae_titulo_inicial=$faixa_pnae_opcoes[$faixa_etaria_inicial_key]??'Faixa Inválida'; $todas_porcoes_db_json=json_encode($todas_porcoes_db);foreach($dias_keys as $dia){$cardapio_inicial_formatado_para_js[$dia]=[];foreach($refeicoes_keys_atuais as $ref_key){$its_valid=$cardapio_processado['dias'][$dia][$ref_key]??[];foreach($its_valid as $it_v){$fId=$it_v['foodId'];$iG=$it_v['instanceGroup'];if(!isset($initial_instance_groups[$fId])||$iG>$initial_instance_groups[$fId]){$initial_instance_groups[$fId]=$iG;}}usort($its_valid,function($a,$b)use($alimentos_id_nome_map){$nA=$alimentos_id_nome_map[$a['foodId']]??'';$nB=$alimentos_id_nome_map[$b['foodId']]??'';$c=strcasecmp($nA,$nB);if($c===0)return($a['instanceGroup']??1)<=>($b['instanceGroup']??1);return $c;});$cardapio_inicial_formatado_para_js[$dia][$ref_key]=$its_valid;}}}
else{if($dados_base_ok){foreach($todos_pnae_ref as $key => $ref){$faixa_pnae_opcoes[$key]=htmlspecialchars($ref['faixa']??$key);}if(!isset($faixa_pnae_opcoes[$faixa_etaria_inicial_key])){$faixa_etaria_inicial_key=key($faixa_pnae_opcoes)?:$faixa_etaria_default_key;}$faixa_pnae_titulo_inicial=$faixa_pnae_opcoes[$faixa_etaria_inicial_key]??'Erro Carga';}else{$faixa_pnae_opcoes[$faixa_etaria_default_key]='Padrão(Erro Carga)';$faixa_etaria_inicial_key=$faixa_etaria_default_key;$faixa_pnae_titulo_inicial='Erro Carga Dados';}foreach($dias_keys as $dia){$cardapio_inicial_formatado_para_js[$dia]=array_fill_keys($refeicoes_keys_atuais,[]);}}

$page_title_for_html = "Cardápio: " . htmlspecialchars($projeto_nome_original);
$alimentos_disponiveis_json = json_encode($alimentos_id_nome_map); $alimentos_completos_json = json_encode($alimentos_para_js);
$cardapio_inicial_json = json_encode($cardapio_inicial_formatado_para_js); $initial_instance_groups_json = json_encode($initial_instance_groups);
$datas_dias_json = json_encode($cardapio_processado['datas_dias'] ?? array_fill_keys($dias_keys, '')); $dias_desativados_json = json_encode($cardapio_processado['dias_desativados'] ?? array_fill_keys($dias_keys, false));
$refeicoes_layout_json = json_encode($refeicoes_layout_atual); $dias_keys_json = json_encode($dias_keys); $dias_nomes_json = json_encode(array_combine($dias_keys, $dias_semana_nomes));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title_for_html; ?> - NutriPNAE & NutriGestor</title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="assets/css/global.css"> <!-- Inclui o CSS global padronizado -->
    <style>
        /* AQUI SÓ DEVE FICAR O CSS ESPECÍFICO DESTA PÁGINA! */
        /* Page specific styles for index.php */
        .page-title {
            font-family: var(--font-primary); color: var(--color-text-dark);
            font-size: 2.2em; font-weight: 700; margin-bottom: 25px; text-align: left;
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--color-primary-dark);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.05);
            width: 100%; /* Ocupa a largura total do container */
            justify-content: center; /* Centraliza o título */
        }
        .page-title i {
            font-size: 1.1em;
            color: var(--color-accent);
        }

        .main-cardapio-area {
            max-width: 100%; /* Ajuste para centralizar as tabelas */
            margin: 0 auto;
            padding: 25px 30px;
            background-color: var(--color-bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            animation: fadeIn var(--transition-speed) ease-out;
            border: 1px solid var(--color-light-border);
        }

        .config-actions-area {
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
        .faixa-etaria-selector { flex-basis: auto; display: flex; align-items: center; gap: 8px; margin-right: auto; }
        .faixa-etaria-selector label { font-weight: 500; font-size: 0.9rem; color: var(--color-primary-dark); white-space: nowrap; }
        #faixa-etaria-select {
            padding: 8px 12px; font-size: 0.9rem; border-radius: var(--border-radius); border: 1px solid var(--color-border);
            min-width: 220px; background-color: var(--color-bg-white); cursor: pointer; height: 38px;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed); font-family: var(--font-secondary);
            appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%231976D2'%3E%3Cpath fill-rule='evenodd' d='M8 10.5a.5.5 0 0 1-.354-.146l-4-4a.5.5 0 0 1 .708-.708L8 9.293l3.646-3.647a.5.5 0 0 1 .708.708l-4 4A.5.5 0 0 1 8 10.5Z'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 10px center; background-size: 16px 16px; padding-right: 35px; flex-grow: 1;
        }
        #faixa-etaria-select:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-xtralight); outline: none; }
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end; /* Alinha os botões à direita */
            flex-grow: 1; /* Permite que ocupe o espaço restante */
        }
        .action-button {
            padding: 8px 18px; background-color: var(--color-primary); color: var(--color-text-on-dark); border: none;
            border-radius: var(--border-radius); cursor: pointer; font-size: 0.85rem; font-weight: 500; font-family: var(--font-primary);
            transition: background-color var(--transition-speed), box-shadow var(--transition-speed), transform var(--transition-speed);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); display: inline-flex; align-items: center; gap: 8px; line-height: 1.5; height: 38px;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .action-button i { font-size: 0.95em; }
        .action-button:hover:not(:disabled) { background-color: var(--color-primary-dark); box-shadow: 0 4px 8px rgba(0, 90, 156, 0.1); transform: translateY(-1px); }
        .action-button:active:not(:disabled) { transform: translateY(0); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); }
        .action-button:disabled { background-color: #adb5bd; color: #f8f9fa; cursor: not-allowed; opacity: 0.7; box-shadow: none; transform: none;}
        .action-button.cancel { background-color: var(--color-error); } .action-button.cancel:hover:not(:disabled) { background-color: var(--color-error-dark); }
        .action-button.export-excel { background-color: var(--color-success); } .action-button.export-excel:hover:not(:disabled) { background-color: var(--color-success-dark); }
        #add-refeicao-btn { background-color: var(--color-info); } #add-refeicao-btn:hover:not(:disabled) { background-color: var(--color-info-dark); }
        #nova-preparacao-btn { background-color: var(--color-warning); color: var(--color-primary-dark); }
        #nova-preparacao-btn:hover:not(:disabled) { background-color: var(--color-warning-dark); color: var(--color-text-on-dark); }
        .item-manipulation-buttons {
            display: flex; gap: 8px;
            border: 1px solid var(--color-light-border);
            border-radius: var(--border-radius);
            padding: 5px;
            background-color: var(--color-bg-white);
        }
        .item-manipulation-buttons button {
            background-color: var(--color-secondary-light);
            color: var(--color-secondary);
            padding: 6px 12px;
            font-size: 0.8rem;
            border-radius: 6px;
            box-shadow: none;
        }
        .item-manipulation-buttons button:hover:not(:disabled) { background-color: var(--color-secondary); color: var(--color-text-on-dark); }
        .item-manipulation-buttons button:disabled { opacity: 0.5; cursor: not-allowed; }

        #save-project-btn { background-color: var(--color-success); } #save-project-btn.unsaved { animation: pulse 1.5s infinite ease-in-out; }
        #save-project-btn:hover:not(:disabled) { background-color: var(--color-success-dark); }
        #save-status { font-size: 0.8em; margin-left: 5px; display: inline-flex; align-items:center; min-width: 50px; text-align: right; font-weight: 500;}
        .cardapio-montagem-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 30px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            table-layout: fixed;
        }
        .cardapio-montagem-table th, .cardapio-montagem-table td {
            border-bottom: 1px solid var(--color-light-border);
            border-right: 1px solid var(--color-light-border);
            padding: 8px 10px;
            text-align: center;
            vertical-align: middle;
            font-size: 0.85rem;
            position: relative;
            transition: background-color var(--transition-speed);
            white-space: normal;
            overflow: visible;
        }
        .cardapio-montagem-table th:last-child, .cardapio-montagem-table td:last-child { border-right: none; }
        .cardapio-montagem-table tr:last-child td { border-bottom: none; }
        .cardapio-montagem-table thead th {
            background-color: #eef2f7;
            color: var(--color-primary-dark);
            font-weight: 600;
            font-family: var(--font-primary);
            z-index: 10;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 1px solid var(--color-border);
        }
        /* Definição de largura fixa para as colunas */
        .cardapio-montagem-table th:first-child, /* Refeição */
        .cardapio-montagem-table td.label-cell.refeicao-label {
            width: 120px; /* Reduzido */
        }
        .cardapio-montagem-table th:nth-child(2), /* Horário */
        .cardapio-montagem-table td.label-cell.horario-cell {
            width: 70px; /* Reduzido */
        }
        .cardapio-montagem-table th[data-dia-col], /* Colunas dos dias */
        .cardapio-montagem-table td.editable-cell {
            width: 250px; /* Aumentado */
        }
        .cardapio-montagem-table th:last-child, /* Ação */
        .cardapio-montagem-table td.action-cell {
            width: 40px;
        }


        .cardapio-montagem-table thead th .dia-controles { margin-top: 5px; display: flex; flex-direction: column; align-items: center; gap: 4px;}
        .cardapio-montagem-table thead th .dia-data-input { width: 65px; padding: 3px 5px; font-size: 0.75rem; text-align: center; border: 1px solid #ced4da; border-radius: 4px; background: var(--color-bg-white); color: var(--color-text-dark); font-family: var(--font-secondary); transition: border-color var(--transition-speed); }
        .cardapio-montagem-table thead th .dia-data-input:focus { border-color: var(--color-primary); outline: none; }
        .cardapio-montagem-table thead th .toggle-feriado-btn { padding: 2px 8px; font-size: 0.7rem; border-radius: var(--border-radius); border: 1px solid var(--color-secondary-light); background-color: var(--color-bg-white); color: var(--color-secondary); cursor: pointer; transition: all var(--transition-speed); font-weight: 500; }
        .cardapio-montagem-table thead th .toggle-feriado-btn:hover { background-color: var(--color-secondary-light); color: var(--color-text-on-dark); border-color: var(--color-secondary); }
        .cardapio-montagem-table thead th .toggle-feriado-btn.active { background-color: var(--color-warning-light); color: var(--color-warning-dark); border-color: var(--color-warning); }
        .cardapio-montagem-table thead th .toggle-feriado-btn.active:hover { background-color: var(--color-warning); color: var(--color-text-on-dark); }
        .cardapio-montagem-table thead th .dia-nome { font-weight: bold; display: block; font-size: 0.9rem; }
        .cardapio-montagem-table th.dia-desativado { background-color: var(--disabled-day-bg); color: var(--disabled-day-text); }
        .cardapio-montagem-table td.dia-desativado { background-color: var(--disabled-day-bg) !important; pointer-events: none; }
        .cardapio-montagem-table td.dia-desativado ul, .cardapio-montagem-table td.dia-desativado .add-item-cell-btn { opacity: 0.4; }
        .cardapio-montagem-table td.label-cell { background-color: #f8f9fa; font-weight: 500; text-align: center; vertical-align: middle; cursor: pointer; transition: background-color var(--transition-speed); border-right: 1px solid var(--color-border); white-space: normal; }
        .cardapio-montagem-table td.label-cell:hover { background-color: #e9ecef; }
        .cardapio-montagem-table td.label-cell span.editable-label { display: inline-block; padding: 4px 6px; min-width: 90%; min-height: 1.4em; font-size: 0.85rem; font-weight: 600; }
        .cardapio-montagem-table td.label-cell input.label-input { display: none; width: 95%; padding: 5px; font-size: 0.85rem; border: 1px solid var(--color-primary); border-radius: 4px; text-align: center; background-color: var(--color-bg-white); box-sizing: border-box; font-family: var(--font-secondary); }
        .cardapio-montagem-table td.label-cell span.editing { display: none; } .cardapio-montagem-table td.label-cell input.editing { display: inline-block; }
        .cardapio-montagem-table td.horario-cell { font-size: 0.82rem; color: var(--color-text-light); white-space: normal; }
        .cardapio-montagem-table td.horario-cell span.editable-label { white-space: pre-line; font-weight: 400; }
        .cardapio-montagem-table td.horario-cell input.label-input { font-size: 0.82rem; }
        .cardapio-montagem-table td.action-cell { padding: 0; vertical-align: middle; border-left: 1px solid var(--color-border); background-color: #f8f9fa; }
        .remove-row-btn { background: none; border: none; color: var(--color-secondary-light); cursor: pointer; padding: 8px; font-size: 0.9rem; opacity: 0.6; transition: all var(--transition-speed) ease; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
        .cardapio-montagem-table tr:hover .remove-row-btn { opacity: 1; color: var(--color-error); background-color: var(--color-error-light); }
        .cardapio-montagem-table tr:hover .remove-row-btn:hover { color: var(--color-error-dark); transform: scale(1.1); }
        .cardapio-montagem-table tr:first-child:only-child td.action-cell { visibility: hidden; }
        .editable-cell { min-height: 80px; background-color: var(--color-bg-white); transition: background-color var(--transition-speed) ease; vertical-align: top; text-align: left; padding: 35px 10px 10px 10px; cursor: pointer; white-space: normal; }
        .editable-cell:hover:not(.dia-desativado) { background-color: var(--color-primary-xtralight); }
        .editable-cell.target-cell-for-paste { background-color: var(--color-success-light) !important; border: 2px dashed var(--color-success-dark) !important; }
        .add-item-cell-btn { position: absolute; top: 6px; right: 6px; font-size: 0.8rem; padding: 0; line-height: 1; cursor: pointer; background-color: var(--color-primary); color: white; border: none; border-radius: 50%; transition: all var(--transition-speed) ease; display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); z-index: 5; pointer-events: auto; }
        .add-item-cell-btn:hover:not(.dia-desativado *) { background-color: var(--color-primary-dark); box-shadow: 0 2px 5px rgba(0, 90, 156, 0.2); transform: scale(1.1); }
        .add-item-cell-btn i { font-size: 0.75rem; }
        .selected-items-list { list-style: none; padding: 0; margin: 0; }
        .selected-items-list li { background-color: #f8f9fa; border: 1px solid var(--color-light-border); color: var(--color-text-dark); padding: 6px 8px; margin-bottom: 6px; border-radius: var(--border-radius); font-size: 0.85rem; line-height: 1.4; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: background-color var(--transition-speed), box-shadow var(--transition-speed), border-color var(--transition-speed), transform var(--transition-speed); position: relative; box-shadow: none; }
        .selected-items-list li:hover:not(.dia-desativado *) { background-color: var(--color-bg-white); border-color: var(--color-primary-light); box-shadow: 0 2px 5px rgba(0, 90, 156, 0.08); z-index: 2; transform: translateX(2px); }
        .selected-items-list li.item-selecionado { background-color: var(--color-primary-xtralight); border-color: var(--color-primary-light); font-weight: 500; }
        .selected-items-list li .item-details { display: flex; align-items: center; flex-grow: 1; gap: 5px;}
        .selected-items-list li .item-name { pointer-events: none; flex-grow: 1; }
        .selected-items-list li .item-instance-group-number { font-size: 0.7em; vertical-align: super; color: var(--color-primary-dark); font-weight: bold; margin-left: 1px; pointer-events: none; }
        .selected-items-list li .item-qty-display { font-weight: 600; color: var(--color-primary-dark); pointer-events: none; padding: 2px 6px; background-color: var(--color-bg-white); border: 1px solid var(--color-light-border); border-radius: 4px; font-size: 0.8rem; margin-left: auto; transition: background-color 0.2s; }
        .selected-items-list li:hover .item-qty-display { background-color: var(--color-primary-xtralight); border-color: var(--color-primary-light); }
        /* A NOVA PARTE PARA A QUANTIDADE EDITÁVEL SEM MODAL */
        .qty-input-inline-wrapper { display: flex; align-items: center; margin-left: auto; padding-left: 5px; }
        .item-qty-input-inline { width: 60px; text-align: right; padding: 3px 6px; font-size: 0.8rem; border-radius: 4px; border: 1px solid var(--color-border); font-weight: 600; color: var(--color-primary-dark); }
        .item-qty-input-inline:focus { border-color: var(--color-primary); box-shadow: 0 0 0 2px var(--color-primary-xtralight); outline:none; }
        /* FIM DA NOVA PARTE */
        .selected-items-list li .item-actions { display: flex; align-items: center; gap: 2px; margin-left: 5px; }
        .selected-items-list li .item-edit-qty-btn,
        .selected-items-list li .item-remove-btn { background: none; border: none; color: var(--color-secondary-light); cursor: pointer; font-size: 0.9rem; padding: 0 4px; opacity: 0; transition: opacity var(--transition-speed), color var(--transition-speed); line-height: 1; }
        .selected-items-list li:hover:not(.dia-desativado *) .item-edit-qty-btn,
        .selected-items-list li:hover:not(.dia-desativado *) .item-remove-btn { opacity: 0.7; }
        .selected-items-list li .item-edit-qty-btn:hover { opacity: 1; color: var(--color-info); } /* Mantém botão editar, mas sem função modal */
        .selected-items-list li .item-remove-btn:hover { opacity: 1; color: var(--color-error); }
        .selected-items-list li.placeholder { background: none; border: none; color: var(--color-text-light); font-style: italic; text-align: center; padding: 10px 0; font-size: 0.8rem; display: block; cursor: default; box-shadow: none; }
        .selected-items-list li.placeholder:hover { background: none; border-color: transparent; }
        .resultados-simplificados-section { margin-top: 30px; }
        #resultados-diarios-table td { font-size: 0.8rem; padding: 6px 8px; }
        #resultados-diarios-table thead th { font-size: 0.75rem; padding: 8px 8px; }
        #resultados-diarios-table tfoot td { font-weight: bold; background-color: #eef2f7; color: var(--color-primary-dark); }
        #resultados-diarios-table td[data-nutrient*="_vet"] { color: var(--color-text-light); font-style: italic; }
        #status-message { margin-top: 20px; font-weight: 500; padding: 10px 15px; border-radius: var(--border-radius); text-align: center; font-size: 0.95em; border: 1px solid transparent; display: flex; align-items: center; justify-content: center; gap: 10px; transition: background-color var(--transition-speed), border-color var(--transition-speed), color var(--transition-speed); }
        #status-message i { font-size: 1.1em; }
        .status.loading { color: var(--color-info-dark); background-color: var(--color-info-light); border-color: var(--color-info); animation: pulse 1.5s infinite ease-in-out; } .status.error { color: var(--color-error-dark); background-color: var(--color-error-light); border-color: var(--color-error); } .status.success { color: var(--color-success-dark); background-color: var(--color-success-light); border-color: var(--color-success); } .status.warning { color: var(--color-warning-dark); background-color: var(--color-warning-light); border-color: var(--color-warning); } .status.info { color: var(--color-secondary); background-color: #e9ecef; border-color: #ced4da; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); display: none; justify-content: center; align-items: center; z-index: 1050; padding: 15px; box-sizing: border-box; backdrop-filter: blur(4px); animation: fadeInModal 0.25s ease-out; }
        .modal-content { background-color: var(--color-bg-white); padding: 20px 25px; border-radius: var(--border-radius); box-shadow: 0 10px 30px rgba(0,0,0,0.15); max-width: 700px; width: 95%; max-height: 90vh; display: flex; flex-direction: column; animation: scaleInModal 0.25s ease-out forwards; border: 1px solid var(--color-light-border); }
        .modal-header { border-bottom: 1px solid var(--color-light-border); padding-bottom: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h2 { font-size: 1.3em; margin: 0; color: var(--color-primary-dark); font-weight: 600; font-family: var(--font-primary); }
        .modal-close-btn { background:none; border:none; font-size: 1.6rem; cursor:pointer; color: var(--color-secondary-light); padding: 0 5px; line-height: 1; transition: color var(--transition-speed); } .modal-close-btn:hover { color: var(--color-error); }
        .modal-body { overflow-y: auto; flex-grow: 1; margin-bottom: 15px; padding-right: 10px; scrollbar-width: thin; scrollbar-color: var(--color-primary-light) var(--color-primary-xtralight); }
        .modal-body::-webkit-scrollbar { width: 10px; } .modal-body::-webkit-scrollbar-track { background: var(--color-primary-xtralight); border-radius: 5px; } .modal-body::-webkit-scrollbar-thumb { background-color: var(--color-primary-light); border-radius: 5px; border: 2px solid var(--color-primary-xtralight); }
        #modal-search, .auth-input { display: block; width: 100%; padding: 9px 12px; margin-bottom: 18px; border: 1px solid var(--color-border); border-radius: var(--border-radius); box-sizing: border-box; font-size: 0.95em; font-family: var(--font-secondary); transition: border-color var(--transition-speed), box-shadow var(--transition-speed); }
        #modal-search:focus, .auth-input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-xtralight); outline: none; }
        #modal-selected-items h5, #modal-search-items h5 { font-size: 0.95em; font-weight: 600; margin: 0 0 10px 0; color: var(--color-primary-dark); padding-bottom: 6px; border-bottom: 1px solid var(--color-light-border); }
        .modal-search-list { list-style: none; padding: 0; max-height: 350px; overflow-y: auto; margin: 0; }
        .modal-search-list li { margin-bottom: 2px; }
        .modal-search-list label { display: block; padding: 8px 10px; font-size: 0.9rem; transition: background-color 0.15s ease; border-radius: 4px; cursor: pointer; display: flex; align-items: center; }
        .modal-search-list label:hover { background-color: var(--color-primary-xtralight); }
        .modal-search-list input[type="checkbox"] { margin-right: 10px; transform: scale(1.05); accent-color: var(--color-primary); cursor: pointer; flex-shrink: 0; }
        .modal-search-list label span { flex-grow: 1; }
        .modal-search-list .no-results { text-align:center; color: var(--color-text-light); padding: 15px 0; font-size: 0.9em; font-style: italic; }
        .modal-footer { border-top: 1px solid var(--color-light-border); padding-top: 15px; text-align: right; display: flex; justify-content: flex-end; gap: 10px;}
        .modal-button { padding: 9px 20px; font-size: 0.85em; margin-left: 0; }
        .modal-button.confirm { background-color: var(--color-success); color: var(--color-text-on-dark); } .modal-button.confirm:hover:not(:disabled) { background-color: var(--color-success-dark); }
        .modal-button.cancel { background-color: var(--color-secondary); color: var(--color-text-on-dark); } .modal-button.cancel:hover:not(:disabled) { background-color: #5a6268; }
        #instance-group-choice-modal .modal-content { max-width: 450px; } #instance-group-choice-modal .modal-body { padding-top: 10px; }
        #instance-group-choice-modal h3 { font-size: 1.1em; margin-bottom: 15px; font-weight: 500; color: var(--color-primary-dark); text-align: center; }
        #instance-group-choice-modal p { text-align: center; font-size: 0.9em; color: var(--color-text-light); }
        #group-choice-options { list-style: none; padding: 0; margin: 0 0 15px 0; max-height: 250px; overflow-y: auto; } #group-choice-options li { margin-bottom: 10px; }
        #group-choice-options label { display: block; padding: 10px 12px; border: 1px solid var(--color-light-border); border-radius: var(--border-radius); cursor: pointer; transition: background-color var(--transition-speed), border-color var(--transition-speed); font-size: 0.9rem; display: flex; align-items: center; }
        #group-choice-options label:hover { background-color: var(--color-primary-xtralight); border-color: var(--color-primary-light); }
        #group-choice-options input[type="radio"] { margin-right: 12px; transform: scale(1.1); accent-color: var(--color-primary); flex-shrink: 0; }
        #group-choice-options .group-option-label { flex-grow: 1; } #group-choice-options .group-option-qty { font-weight: 600; color: var(--color-primary-dark); margin-left: 8px; font-size: 0.9em; } #group-choice-options .new-group-label { font-style: italic; color: var(--color-info-dark); }
        
        /* --- ESTILOS DO NOVO MODAL DE FICHA TÉCNICA --- */
        #nova-preparacao-modal .modal-content { max-width: 1000px; }
        #nova-preparacao-modal .modal-body { display: grid; grid-template-columns: 1fr 350px; gap: 25px; }
        #nova-preparacao-modal .modal-main-col { display: flex; flex-direction: column; gap: 20px; }
        #nova-preparacao-modal .modal-side-col { display: flex; flex-direction: column; gap: 20px; }
        #nova-preparacao-modal .prep-section { display: flex; flex-direction: column; gap: 8px; }
        #nova-preparacao-modal .prep-details-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
        #nova-preparacao-modal label { font-weight: 500; color: var(--color-primary-dark); display: block; margin-bottom: 4px; font-size: 0.9em; }
        #nova-preparacao-modal input[type="text"], #nova-preparacao-modal input[type="number"], #nova-preparacao-modal textarea { width: 100%; padding: 8px 10px; border: 1px solid var(--color-border); border-radius: var(--border-radius); transition: border-color var(--transition-speed); font-family: var(--font-secondary); font-size: 0.9rem;}
        #nova-preparacao-modal input[type="text"]:focus, #nova-preparacao-modal input[type="number"]:focus, #nova-preparacao-modal textarea:focus { border-color: var(--color-primary); outline: none; box-shadow: 0 0 0 2px var(--color-primary-xtralight); }
        #nova-preparacao-modal textarea { resize: vertical; min-height: 100px; }
        #nova-preparacao-modal h3 { font-size: 1.1em; font-weight: 600; margin-bottom: 8px; padding-bottom: 5px; border-bottom: 1px solid var(--color-light-border); color: var(--color-primary-dark); }
        
        #prep-ingredient-search-wrapper { position: relative; margin-bottom: 10px; }
        #prep-search-results { list-style: none; padding: 0; margin: 0; max-height: 180px; overflow-y: auto; border: 1px solid var(--color-light-border); border-top: none; border-radius: 0 0 var(--border-radius) var(--border-radius); background-color: var(--color-bg-white); position: absolute; width: 100%; z-index: 1051; display: none; box-shadow: var(--box-shadow); }
        #prep-search-results li { padding: 8px 12px; font-size: 0.9rem; cursor: pointer; border-bottom: 1px solid var(--color-light-border); }
        #prep-search-results li:last-child { border-bottom: none; } #prep-search-results li:hover { background-color: var(--color-primary-xtralight); }
        
        #prep-ingredients-table { width: 100%; border-collapse: collapse; }
        #prep-ingredients-table th, #prep-ingredients-table td { padding: 8px; border: 1px solid var(--color-light-border); text-align: left; font-size: 0.85rem; vertical-align: middle; }
        #prep-ingredients-table thead th { background-color: #eef2f7; font-weight: 600; color: var(--color-primary-dark); text-align: center; }
        #prep-ingredients-table tbody tr:nth-child(odd) { background-color: #f8f9fa; }
        #prep-ingredients-table input[type="number"] { width: 70px; text-align: right; }
        #prep-ingredients-table .prep-ing-pb { font-weight: bold; display: inline-block; width: 70px; text-align: right; }
        #prep-ingredients-table .prep-ing-remove-btn { background: none; border: none; color: var(--color-secondary-light); cursor: pointer; font-size: 1rem; transition: color var(--transition-speed); }
        #prep-ingredients-table .prep-ing-remove-btn:hover { color: var(--color-error); }
        #prep-ingredients-tbody .placeholder td { text-align: center; font-style: italic; color: var(--color-text-light); background-color: var(--color-bg-white); }
        
        #prep-nutri-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; background-color: #fdfdfd;}
        #prep-nutri-table td { padding: 6px 10px; border: 1px solid var(--color-light-border); }
        #prep-nutri-table td:first-child { font-weight: 500; color: var(--color-text-dark); width: 65%; }
        #prep-nutri-table td:last-child { font-weight: bold; text-align: right; color: var(--color-primary-dark); }
        .prep-nutri-title {font-size: 0.8em; font-weight: 400; color: var(--color-text-light); }
        
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

        @media (max-width: 1200px) {
            .navbar .container { width: 95%;}
            .content-area .container { padding: 0 10px; }
            .main-cardapio-area { padding: 20px; }
            .cardapio-montagem-table th, .cardapio-montagem-table td { padding: 6px 8px; }
            .action-buttons { gap: 8px; }
            .action-button { padding: 8px 14px; font-size: 0.8rem; }
            #nova-preparacao-modal .modal-content {max-width: 95%;}
            #nova-preparacao-modal .modal-body {grid-template-columns: 1fr 300px;}
        }
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
                display: none;
            }
            .main-wrapper {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                box-shadow: none;
                padding: 10px 0;
            }
            /* Removido sidebar-toggle-container e seus estilos específicos para mobile aqui,
               pois o botão agora está dentro do sidebar.php */
            .sidebar-toggle-button { /* Estilos do botão de toggle quando dentro do sidebar */
                display: flex;
                background-color: var(--color-primary-dark);
                color: var(--color-text-on-dark);
                border-radius: var(--border-radius);
                padding: 10px 15px;
                width: fit-content;
                margin: 10px auto; /* Centraliza o botão */
                justify-content: center;
            }
            .sidebar-toggle-button span { display: inline; margin-left: 8px; }
            .sidebar-toggle-button i { margin-right: 0; transform: none !important; }


            .sidebar.collapsed {
                width: 100%; /* Força a largura total em mobile mesmo se a classe collapsed estiver lá */
            }

            .sidebar-nav {
                display: none; /* Esconde o nav por padrão em mobile */
                padding-top: 10px;
                padding-bottom: 10px;
            }
            .sidebar-nav.active {
                display: flex;
                flex-direction: column;
            }
            /* O sidebar não deve colapsar em mobile com o botão, apenas aparecer/desaparecer */
            .sidebar.collapsed {
                width: 100%; /* Força a largura total em mobile mesmo se a classe collapsed estiver lá */
            }

            .sidebar-nav details summary {
                border-left: none;
                justify-content: center;
            }
            .sidebar-nav details ul {
                padding-left: 15px;
            }
            .content-area {
                padding: 15px;
            }
            .page-title {
                font-size: 1.8em;
                text-align: center;
                justify-content: center;
            }
            .config-actions-area { flex-direction: column; align-items: stretch; gap: 15px; }
            .faixa-etaria-selector { flex-basis: auto; justify-content: space-between; }
            #faixa-etaria-select { width: auto; min-width: 0; flex-grow: 1; margin-left: 10px; }
            .action-buttons { justify-content: center; }
            .item-manipulation-buttons { margin-left: 0; justify-content: center; }

            #nova-preparacao-modal .modal-body {grid-template-columns: 1fr; }
            #nova-preparacao-modal .modal-side-col {margin-top: 20px;}
        }
        @media (max-width: 768px) {
            body { font-size: 13px; }
            .main-cardapio-area { padding: 15px; }
            .cardapio-montagem-table:not(#resultados-diarios-table) { display: block; overflow-x: auto; white-space: nowrap; border: none; box-shadow: none;}
            .cardapio-montagem-table:not(#resultados-diarios-table) thead, .cardapio-montagem-table:not(#resultados-diarios-table) tbody, .cardapio-montagem-table:not(#resultados-diarios-table) tr { display: block; }
            .cardapio-montagem-table:not(#resultados-diarios-table) thead { position: relative; }
            .cardapio-montagem-table:not(#resultados-diarios-table) th { display: inline-block; width: 180px; vertical-align: top; padding: 10px; }
            .cardapio-montagem-table:not(#resultados-diarios-table) th:first-child, .cardapio-montagem-table:not(#resultados-diarios-table) th:nth-child(2), .cardapio-montagem-table:not(#resultados-diarios-table) th:last-child { display: none; } /* Esconde cabeçalhos em mobile, eles serão "recriados" via JS no CSS */
            .cardapio-montagem-table:not(#resultados-diarios-table) tbody tr { border-bottom: 2px solid var(--color-primary-light); margin-bottom: 15px; padding-bottom: 10px; display: flex; flex-direction: column;}
            .cardapio-montagem-table:not(#resultados-diarios-table) td {
                display: block; text-align: left; border: none; border-bottom: 1px solid var(--color-light-border);
                padding-left: 10px; position: relative; white-space: normal; width: 100% !important;
            }
            .cardapio-montagem-table:not(#resultados-diarios-table) td:last-child { border-bottom: none; }
            /* Ajusta células de label para serem exibidas corretamente */
            .cardapio-montagem-table:not(#resultados-diarios-table) td.label-cell,
            .cardapio-montagem-table:not(#resultados-diarios-table) td.horario-cell,
            .cardapio-montagem-table:not(#resultados-diarios-table) td.action-cell {
                border-bottom: 1px solid var(--color-light-border);
                padding: 10px;
                height: auto;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .cardapio-montagem-table:not(#resultados-diarios-table) td.label-cell::before,
            .cardapio-montagem-table:not(#resultados-diarios-table) td.horario-cell::before,
            .cardapio-montagem-table:not(#resultados-diarios-table) td.action-cell::before {
                display: none; /* Remove o pseudo-elemento que exibia o label do cabeçalho */
            }
            .cardapio-montagem-table:not(#resultados-diarios-table) td.label-cell span.editable-label,
            .cardapio-montagem-table:not(#resultados-diarios-table) td.label-cell input.label-input {
                display: inline-block;
                width: auto;
                min-width: 150px;
            }
            .cardapio-montagem-table:not(#resultados-diarios-table) td.action-cell {
                height: 40px;
            }
            .remove-row-btn { position: static; width: auto; height: auto; padding: 5px 10px; }
            .cardapio-montagem-table:not(#resultados-diarios-table) tr td.action-cell { visibility: visible !important; }

            .editable-cell { padding: 10px; min-height: 60px;}
            .editable-cell::before { display: none; } /* Remove o pseudo-elemento que mostrava o dia */
            .add-item-cell-btn { top: 5px; right: 5px; width: 26px; height: 26px; }
            .add-item-cell-btn i { font-size: 0.8rem; }
            .selected-items-list li { font-size: 0.8rem; padding: 5px 8px; }
            .modal-content { padding: 15px 20px; max-height: 85vh; }
            .modal-body { padding-right: 5px; }
            .modal-header h2 { font-size: 1.2em; }
            .modal-search-list { max-height: 300px; }
            .modal-footer { display: flex; justify-content: flex-end; gap: 8px;}
            .modal-button { margin-left: 0; padding: 8px 15px; }
        }
        @media (max-width: 480px) {
            body { font-size: 12px; }
            .navbar .container{max-width: 95%;}
            .navbar-brand-group{gap:1rem;}.navbar-brand{font-size:1.4rem;}
            .btn-header-action{font-size:0.75rem; padding:0.5rem 1rem; }
            .config-actions-area { padding: 10px; }
            .faixa-etaria-selector label { font-size: 0.85rem;}
            .action-buttons { gap: 5px; }
            .action-button { font-size: 0.7rem; padding: 6px 10px; gap: 4px; height: 32px;}
            .content-area .container { padding: 10px; margin: 5px auto; }
            .cardapio-montagem-table:not(#resultados-diarios-table) th { width: 150px; }
            h3 { font-size: 1.05em; }
            #resultados-diarios-table td { font-size: 0.75rem; padding: 4px 6px; }
            #resultados-diarios-table thead th { font-size: 0.7rem; padding: 6px 6px; }
            #selection-modal .modal-content, #instance-group-choice-modal .modal-content, #nova-preparacao-modal .modal-content { max-width: 95%; width: 95%; }
        }
    </style>
</head>
<body class="page-index">
    <?php include_once 'includes/message_box.php'; ?>
    <?php include_once 'includes/header.php'; ?>

    <div class="main-wrapper">
        <?php include_once 'includes/sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="content-area">
            <div class="container">
                <h1 class="page-title"><i class="fas fa-clipboard-list"></i> Montar Cardápio Semanal</h1>

                <?php if ($db_connection_error || !$dados_base_ok): ?>
                    <div class="error-container" style="margin-top: 30px;">
                        <h1><i class="fas fa-exclamation-triangle"></i> Erro ao Carregar Cardápio</h1>
                        <p>Não foi possível carregar os dados necessários para o montador.</p>
                        <?php if ($db_connection_error): ?> <p><?php echo htmlspecialchars($load_error_message ?: 'Problema ao acessar dados do projeto.'); ?></p>
                        <?php elseif (!$dados_base_ok): ?> <p><?php echo htmlspecialchars($load_error_message ?: 'Problema ao carregar dados base de alimentos.'); ?></p>
                        <?php endif; ?>
                        <p>Por favor, <a href="home.php">volte para seus projetos</a> ou contate o suporte.</p>
                        <p><small>(Detalhes técnicos registrados nos logs.)</small></p>
                    </div>
                <?php else: ?>
                    <input type="hidden" id="current-project-id" value="<?php echo $projeto_id; ?>">
                    <div class="main-cardapio-area">
                        <?php if ($load_error_message && !$db_connection_error && $dados_base_ok): ?>
                            <div id="status-message-load" class="status <?php echo (strpos(strtolower($load_error_message), 'corrompido') !== false || strpos(strtolower($load_error_message), 'inválida') !== false || strpos(strtolower($load_error_message), 'inesperado') !== false) ? 'warning' : 'info'; ?>" style="margin-bottom: 20px; padding: 10px 15px; border-radius: var(--border-radius); text-align: center; font-size: 0.95em; border: 1px solid transparent; display: flex; align-items: center; justify-content: center; gap: 10px;">
                                <i class="fas <?php echo (strpos(strtolower($load_error_message), 'corrompido') !== false || strpos(strtolower($load_error_message), 'inválida') !== false || strpos(strtolower($load_error_message), 'inesperado') !== false) ? 'fa-exclamation-triangle' : 'fa-info-circle'; ?>"></i> <?php echo htmlspecialchars($load_error_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <section class="config-actions-area">
                            <div class="faixa-etaria-selector">
                                <label for="faixa-etaria-select">Cardápio: <strong><?php echo htmlspecialchars($projeto_nome_original); ?></strong> <span style="margin: 0 5px; font-size: 0.9em; color: var(--color-secondary);">|</span> Faixa Etária:</label>
                                <select id="faixa-etaria-select" title="Selecione a faixa etária para aplicar as porções padrão e referências PNAE">
                                    <option value="">-- Selecione --</option>
                                    <?php foreach ($faixa_pnae_opcoes as $key => $label): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($key === $faixa_etaria_inicial_key) ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="action-buttons">
                                <button type="button" id="save-project-btn" class="action-button" title="Salvar alterações neste cardápio"> <i class="fas fa-save"></i> Salvar Cardápio </button>
                                <span id="save-status"></span>
                                <button type="button" id="nova-preparacao-btn" class="action-button" title="Criar uma nova preparação personalizada (ficha técnica)"><i class="fas fa-mortar-pestle"></i> Nova Preparação</button>
                                <button type="button" id="add-refeicao-btn" class="action-button" title="Adicionar uma nova linha de refeição à tabela"><i class="fas fa-plus-circle"></i> Add Refeição</button>
                                <button type="button" id="calcular-nutrientes-btn" class="action-button" title="Recalcular os totais nutricionais do cardápio"><i class="fas fa-calculator"></i> Calcular Nutrientes</button>
                                <div class="item-manipulation-buttons">
                                    <button type="button" id="item-copy-btn" class="action-button" title="Copiar itens selecionados (Ctrl+C)" disabled><i class="fas fa-copy"></i> Copiar</button>
                                    <button type="button" id="item-cut-btn" class="action-button" title="Recortar itens selecionados (Ctrl+X)" disabled><i class="fas fa-cut"></i> Recortar</button>
                                    <button type="button" id="item-paste-btn" class="action-button" title="Colar itens (Ctrl+V)" disabled><i class="fas fa-paste"></i> Colar</button>
                                    <button type="button" id="item-delete-btn" class="action-button cancel" title="Excluir itens selecionados (Del/Backspace)" disabled><i class="fas fa-trash-alt"></i> Excluir</button>
                                </div>
                                <button type="button" id="exportar-xlsx-btn" class="action-button export-excel" title="Exportar cardápio para Excel (XLSX)"><i class="fas fa-file-excel"></i> Exportar XLSX</button>
                                <button type="button" id="limpar-cardapio-btn" class="action-button cancel" title="Limpar todos os itens do cardápio atual"><i class="fas fa-eraser"></i> Limpar Tudo</button>
                            </div>
                        </section>
                        <div style="overflow-x: auto;"> <table class="cardapio-montagem-table" id="cardapio-grid"> <thead></thead> <tbody></tbody> </table> </div>
                        <form id="export-xlsx-form" action="export_xlsx.php" method="post" target="_blank" style="display: none;"> <input type="hidden" name="export_data" id="export-data-input"> <input type="hidden" name="project_name" id="export-project-name-input" value="<?php echo htmlspecialchars($projeto_nome_original); ?>"> </form>
                        <section class="resultados-simplificados-section">
                            <h3><i class="fas fa-chart-bar" style="color: var(--color-success-dark);"></i> Análise Diária e Média Semanal <span style="font-size: 0.8em; color: var(--color-text-light);">(Cardápio: <?php echo htmlspecialchars($projeto_nome_original); ?>)</span></h3>
                            <div style="overflow-x: auto; margin-bottom: 20px;">
                                <table class="cardapio-montagem-table" id="resultados-diarios-table">
                                <thead><tr> <th rowspan="2">DIAS</th> <th rowspan="2">Energia (Kcal)</th> <th colspan="3">Proteína</th> <th colspan="3">Lipídeos</th> <th colspan="3">Carboidratos</th> <th rowspan="2">Cálcio (mg)</th> <th rowspan="2">Ferro (mg)</th> <th rowspan="2">Vit. A (mcg RAE)</th> <th rowspan="2">Vit. C (mg)</th> <th rowspan="2">Sódio (mg)</th> </tr> <tr> <th>(g)</th><th>Kcal</th><th>% VET</th> <th>(g)</th><th>Kcal</th><th>% VET</th> <th>(g)</th><th>Kcal</th><th>% VET</th> </tr></thead>
                                <tbody> <?php foreach ($dias_keys as $dk): $dia_nome_tbl = $dias_semana_nomes[array_search($dk, $dias_keys)] ?? 'Dia'; ?> <tr id="daily-<?php echo $dk; ?>"><td><?php echo $dia_nome_tbl; ?></td> <td data-nutrient="kcal">0</td> <td data-nutrient="ptn">0,0</td><td data-nutrient="ptn_kcal">0,00</td><td data-nutrient="ptn_vet">-</td> <td data-nutrient="lpd">0,0</td><td data-nutrient="lpd_kcal">0,00</td><td data-nutrient="lpd_vet">-</td> <td data-nutrient="cho">0,0</td><td data-nutrient="cho_kcal">0,00</td><td data-nutrient="cho_vet">-</td> <td data-nutrient="ca">0</td><td data-nutrient="fe">0,0</td><td data-nutrient="vita">0</td><td data-nutrient="vitc">0,0</td><td data-nutrient="na">0</td> </tr> <?php endforeach; ?> </tbody>
                                <tfoot> <tr id="weekly-avg"><td>Média</td> <td data-nutrient="kcal">0</td> <td data-nutrient="ptn">0,0</td><td data-nutrient="ptn_kcal">0,00</td><td data-nutrient="ptn_vet">-</td> <td data-nutrient="lpd">0,0</td><td data-nutrient="lpd_kcal">0,00</td><td data-nutrient="lpd_vet">-</td> <td data-nutrient="cho">0,0</td><td data-nutrient="cho_kcal">0,00</td><td data-nutrient="cho_vet">-</td> <td data-nutrient="ca">0</td><td data-nutrient="fe">0,0</td><td data-nutrient="vita">0</td><td data-nutrient="vitc">0,0</td><td data-nutrient="na">0</td> </tr></tfoot>
                                </table>
                            </div>
                        </section>
                        <section id="referencia-pnae-section" style="margin-top: 30px;"> <h3 id="referencia-pnae-title"><i class="fas fa-book-open" style="color: var(--color-info-dark);"></i> Referências PNAE</h3> <div style="overflow-x: auto; margin-bottom: 20px;"> <div id="referencia-pnae-container"> <p style="text-align: center; color: var(--color-text-light); padding: 15px; background-color: #f8f9fa; border: 1px solid var(--color-light-border); border-radius: var(--border-radius);"> Selecione uma Faixa Etária acima para visualizar os valores de referência. </p> </div> </div> </section>
                        <div id="status-message" class="status info"><i class="fas fa-info-circle"></i> Carregando cardápio...</div>
                    </div>
                    <div id="selection-modal" class="modal-overlay"> <div class="modal-content"> <div class="modal-header"> <h2 id="modal-title">Adicionar Alimentos</h2> <button type="button" class="modal-close-btn" title="Fechar">×</button> </div> <div class="modal-body"> <div id="modal-search-container"> <input type="text" id="modal-search" placeholder="Digite para buscar..." autocomplete="off"> </div> <div id="modal-search-items"> <h5>Selecione os itens:</h5> <ul class="modal-search-list"></ul> </div> </div> <div class="modal-footer"> <button type="button" id="modal-cancel" class="action-button cancel modal-button"><i class="fas fa-times"></i> Cancelar</button> <button type="button" id="modal-confirm" class="action-button confirm modal-button"><i class="fas fa-check"></i> Processar</button> </div> </div> </div>
                    <div id="instance-group-choice-modal" class="modal-overlay"> <div class="modal-content"> <div class="modal-header"> <h2 id="group-choice-modal-title">Escolher Grupo</h2> <button type="button" class="modal-close-btn" title="Fechar">×</button> </div> <div class="modal-body"> <h3 id="group-choice-food-name">Adicionar [Nome]?</h3> <p>Este item já existe. Escolha como adicioná-lo:</p> <ul id="group-choice-options"></ul> </div> <div class="modal-footer"> <button type="button" id="group-choice-cancel" class="action-button cancel modal-button"><i class="fas fa-times"></i> Cancelar</button> <button type="button" id="group-choice-confirm" class="action-button confirm modal-button"><i class="fas fa-check"></i> Confirmar</button> </div> </div> </div>
                    
                    <!-- INÍCIO DO NOVO MODAL DE FICHA TÉCNICA -->
                    <div id="nova-preparacao-modal" class="modal-overlay">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2><i class="fas fa-file-invoice" style="color:var(--color-primary-dark);"></i> Ficha Técnica de Preparo</h2>
                                <button type="button" class="modal-close-btn" title="Fechar">×</button>
                            </div>
                            <div class="modal-body">
                                <div class="modal-main-col">
                                    <div class="prep-section">
                                        <div class="prep-details-grid">
                                            <div>
                                                <label for="prep-nome">Nome da Preparação:</label>
                                                <input type="text" id="prep-nome" placeholder="Ex: Baião de Dois Magrinho" required>
                                            </div>
                                            <div>
                                                <label for="prep-rendimento">Rendimento (porções):</label>
                                                <input type="number" id="prep-rendimento" value="12" min="1" step="1">
                                            </div>
                                            <div>
                                                <label for="prep-porcao-g">Peso da Porção (g):</label>
                                                <input type="number" id="prep-porcao-g" value="100" min="1" step="1">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="prep-section">
                                        <h3>Ingredientes</h3>
                                        <div id="prep-ingredient-search-wrapper">
                                            <label for="prep-ingredient-search">Buscar Ingrediente Base:</label>
                                            <input type="text" id="prep-ingredient-search" placeholder="Buscar para adicionar à tabela...">
                                            <ul id="prep-search-results"></ul>
                                        </div>
                                        <div style="overflow-x: auto;">
                                            <table id="prep-ingredients-table">
                                                <thead>
                                                    <tr>
                                                        <th>Ingrediente</th>
                                                        <th>PL (g/mL)</th>
                                                        <th>FC</th>
                                                        <th>PB (g/mL)</th>
                                                        <th>Ação</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="prep-ingredients-tbody">
                                                    <tr class="placeholder">
                                                        <td colspan="5">- Nenhum ingrediente adicionado -</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div class="prep-section">
                                        <h3>Modo de Preparo</h3>
                                        <textarea id="prep-modo-preparo" placeholder="Descreva os passos para preparar esta receita..."></textarea>
                                    </div>
                                </div>
                                <div class="modal-side-col">
                                    <div class="prep-section">
                                        <h3>Valor Nutritivo por porção <span id="prep-nutri-title-portion-size" class="prep-nutri-title">(100 g)</span></h3>
                                        <table id="prep-nutri-table">
                                            <tbody>
                                                <tr><td>Energia (Kcal)</td><td data-nutrient="kcal">0</td></tr>
                                                <tr><td>Carboidrato (g)</td><td data-nutrient="cho">0,00</td></tr>
                                                <tr><td>Proteínas (g)</td><td data-nutrient="ptn">0,00</td></tr>
                                                <tr><td>Lipídios (g)</td><td data-nutrient="lpd">0,00</td></tr>
                                                <tr><td>Colesterol (mg)</td><td data-nutrient="col">0,00</td></tr>
                                                <tr><td>Fibras (g)</td><td data-nutrient="fib">0,00</td></tr>
                                                <tr><td>Vitamina A (mcg)</td><td data-nutrient="vita">0,00</td></tr>
                                                <tr><td>Vitamina C (mg)</td><td data-nutrient="vitc">0,00</td></tr>
                                                <tr><td>Cálcio (mg)</td><td data-nutrient="ca">0</td></tr>
                                                <tr><td>Ferro (mg)</td><td data-nutrient="fe">0,00</td></tr>
                                                <tr><td>Sódio (mg)</td><td data-nutrient="na">0</td></tr>
                                            </tbody>
                                        </table>
                                        <small id="prep-calc-status" style="text-align:center; color: var(--color-text-light); margin-top: 5px;"></small>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" id="prep-cancel" class="action-button cancel modal-button"><i class="fas fa-times"></i> Cancelar</button>
                                <button type="button" id="prep-save" class="action-button confirm modal-button"><i class="fas fa-save"></i> Salvar Preparação</button>
                            </div>
                        </div>
                    </div>
                    <!-- FIM DO NOVO MODAL DE FICHA TÉCNICA -->

                <?php endif; ?>
            </div>
        </main>
    </div>
    <?php include_once 'includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="assets/js/global.js"></script> <!-- Inclui o JavaScript global padronizado -->
    <script>
    //<![CDATA[
    $(document).ready(function() {
        $('#add-refeicao-btn').on('click', function(e) { e.preventDefault(); if ($(this).prop('disabled') || typeof dadosBaseOk === 'undefined' || !dadosBaseOk) { console.warn("Adicionar refeição bloqueado:", { btnDisabled: $(this).prop('disabled'), dadosBaseOk }); return; } addNewRefeicaoRow(); });
        const currentProjectId = $('#current-project-id').val(); const statusMessage = $('#status-message'); const faixaEtariaSelect = $('#faixa-etaria-select');
        const dadosBaseOk = <?php echo $dados_base_ok ? 'true' : 'false'; ?>; const loadError = <?php echo $db_connection_error ? 'true' : 'false'; ?>;
        const $tableBody = $('#cardapio-grid tbody'); const $tableHead = $('#cardapio-grid thead'); let saveTimeout; let isSaving = false; const saveStatusSpan = $('#save-status');
        // Adicionada a referência completa para referenciaValoresPnae
        const referenciaValoresPnae = { 'bercario': { faixa: 'Creche (7-11 meses)', colunas: [ { key: 'nivel', label: 'Ref. PNAE' }, { key: 'n_ref', label: 'Nº ref.' }, { key: 'energia', label: 'Kcal' }, { key: 'proteina_g_10', label: 'PTN (g) 10%' },{ key: 'proteina_g_15', label: '15%' },{ key: 'lipidio_g_15', label: 'LIP (g) 15%' },{ key: 'lipidio_g_30', label: '30%' },{ key: 'carboidrato_g_55', label: 'CHO (g) 55%' },{ key: 'carboidrato_g_65', label: '65%' },{ key: 'ca_mg', label: 'Ca(mg)' },{ key: 'fe_mg', label: 'Fe(mg)' },{ key: 'vita_ug', label: 'Vit.A(µg)' },{ key: 'vitc_mg', label: 'Vit.C(mg)' } ], refs: [ { nivel: '30% VET', n_ref: '2', valores: { energia: 203, proteina_g_10: 5, proteina_g_15: 8, lipidio_g_15: 3, lipidio_g_30: 7, carboidrato_g_55: 28, carboidrato_g_65: 33, ca_mg: 78, fe_mg: 2.1, vita_ug: 150, vitc_mg: 15 } }, { nivel: '70% VET', n_ref: '3', valores: { energia: 475, proteina_g_10: 12, proteina_g_15: 18, lipidio_g_15: 8, lipidio_g_30: 16, carboidrato_g_55: 65, carboidrato_g_65: 77, ca_mg: 182, fe_mg: 4.8, vita_ug: 350, vitc_mg: 35 } } ] }, 'creche': { faixa: 'Creche (1-3 anos)', colunas: [ { key: 'nivel', label: 'Ref. PNAE' }, { key: 'n_ref', label: 'Nº ref.' }, { key: 'energia', label: 'Kcal' }, { key: 'proteina_g_10', label: 'PTN (g) 10%' },{ key: 'proteina_g_15', label: '15%' },{ key: 'lipidio_g_15', label: 'LIP (g) 15%' },{ key: 'lipidio_g_30', label: '30%' },{ key: 'carboidrato_g_55', label: 'CHO (g) 55%' },{ key: 'carboidrato_g_65', label: '65%' },{ key: 'ca_mg', label: 'Ca(mg)' },{ key: 'fe_mg', label: 'Fe(mg)' },{ key: 'vita_ug', label: 'Vit.A(µg)' },{ key: 'vitc_mg', label: 'Vit.C(mg)' } ], refs: [ { nivel: '30% VET', n_ref: '2', valores: { energia: 304, proteina_g_10: 8, proteina_g_15: 11, lipidio_g_15: 5, lipidio_g_30: 10, carboidrato_g_55: 42, carboidrato_g_65: 49, ca_mg: 150, fe_mg: 0.9, vita_ug: 63, vitc_mg: 3.9 } }, { nivel: '70% VET', n_ref: '3', valores: { energia: 708, proteina_g_10: 18, proteina_g_15: 27, lipidio_g_15: 12, lipidio_g_30: 24, carboidrato_g_55: 97, carboidrato_g_65: 115, ca_mg: 350, fe_mg: 2.1, vita_ug: 147, vitc_mg: 9.1 } } ] }, 'pre_escola': { faixa: 'Pré-escola', colunas: [ { key: 'nivel', label: 'Ref. PNAE' }, { key: 'n_ref', label: 'Nº ref.' }, { key: 'energia', label: 'Kcal' }, { key: 'proteina_g_10', label: 'PTN (g) 10%' },{ key: 'proteina_g_15', label: '15%' },{ key: 'lipidio_g_15', label: 'LIP (g) 15%' },{ key: 'lipidio_g_30', label: '30%' },{ key: 'carboidrato_g_55', label: 'CHO (g) 55%' },{ key: 'carboidrato_g_65', label: '65%' },{ key: 'na_mg', label: 'Na(mg)' } ], refs: [ { nivel: '20% VET', n_ref: '1', valores: { energia: 270, proteina_g_10: 7, proteina_g_15: 10, lipidio_g_15: 5, lipidio_g_30: 9, carboidrato_g_55: 37, carboidrato_g_65: 44, na_mg: 600 } }, { nivel: '30% VET', n_ref: '2', valores: { energia: 405, proteina_g_10: 10, proteina_g_15: 15, lipidio_g_15: 7, lipidio_g_30: 14, carboidrato_g_55: 56, carboidrato_g_65: 66, na_mg: 800 } }, { nivel: '70% VET', n_ref: '3', valores: { energia: 945, proteina_g_10: 24, proteina_g_15: 35, lipidio_g_15: 16, lipidio_g_30: 32, carboidrato_g_55: 130, carboidrato_g_65: 154, na_mg: 1400 } } ] }, 'fund_6_10': { faixa: 'Ens. Fund. (6-10 anos)', colunas: [ { key: 'nivel', label: 'Ref. PNAE' }, { key: 'n_ref', label: 'Nº ref.' }, { key: 'energia', label: 'Kcal' }, { key: 'proteina_g_10', label: 'PTN (g) 10%' },{ key: 'proteina_g_15', label: '15%' },{ key: 'lipidio_g_15', label: 'LIP (g) 15%' },{ key: 'lipidio_g_30', label: '30%' },{ key: 'carboidrato_g_55', label: 'CHO (g) 55%' },{ key: 'carboidrato_g_65', label: '65%' },{ key: 'na_mg', label: 'Na(mg)' } ], refs: [ { nivel: '20% VET', n_ref: '1', valores: { energia: 329, proteina_g_10: 8, proteina_g_15: 12, lipidio_g_15: 5, lipidio_g_30: 11, carboidrato_g_55: 45, carboidrato_g_65: 53, na_mg: 600 } }, { nivel: '30% VET', n_ref: '2', valores: { energia: 493, proteina_g_10: 12, proteina_g_15: 18, lipidio_g_15: 8, lipidio_g_30: 16, carboidrato_g_55: 68, carboidrato_g_65: 80, na_mg: 800 } }, { nivel: '70% VET', n_ref: '3', valores: { energia: 1150, proteina_g_10: 29, proteina_g_15: 43, lipidio_g_15: 19, lipidio_g_30: 38, carboidrato_g_55: 158, carboidrato_g_65: 187, na_mg: 1400 } } ] }, 'fund_11_15': { faixa: 'Ens. Fund. (11-15 anos)', colunas: [ { key: 'nivel', label: 'Ref. PNAE' }, { key: 'n_ref', label: 'Nº ref.' }, { key: 'energia', label: 'Kcal' }, { key: 'proteina_g_10', label: 'PTN (g) 10%' },{ key: 'proteina_g_15', label: '15%' },{ key: 'lipidio_g_15', label: 'LIP (g) 15%' },{ key: 'lipidio_g_30', label: '30%' },{ key: 'carboidrato_g_55', label: 'CHO (g) 55%' },{ key: 'carboidrato_g_65', label: '65%' },{ key: 'na_mg', label: 'Na(mg)' } ], refs: [ { nivel: '20% VET', n_ref: '1', valores: { energia: 473, proteina_g_10: 12, proteina_g_15: 18, lipidio_g_15: 8, lipidio_g_30: 16, carboidrato_g_55: 65, carboidrato_g_65: 77, na_mg: 600 } }, { nivel: '30% VET', n_ref: '2', valores: { energia: 710, proteina_g_10: 18, proteina_g_15: 27, lipidio_g_15: 12, lipidio_g_30: 24, carboidrato_g_55: 98, carboidrato_g_65: 115, na_mg: 800 } }, { nivel: '70% VET', n_ref: '3', valores: { energia: 1656, proteina_g_10: 41, proteina_g_15: 62, lipidio_g_15: 28, lipidio_g_30: 55, carboidrato_g_55: 228, carboidrato_g_65: 269, na_mg: 1400 } } ] }, 'medio': { faixa: 'Ensino Médio', colunas: [ { key: 'nivel', label: 'Ref. PNAE' }, { key: 'n_ref', label: 'Nº ref.' }, { key: 'energia', label: 'Kcal' }, { key: 'proteina_g_10', label: 'PTN (g) 10%' },{ key: 'proteina_g_15', label: '15%' },{ key: 'lipidio_g_15', label: 'LIP (g) 15%' },{ key: 'lipidio_g_30', label: '30%' },{ key: 'carboidrato_g_55', label: 'CHO (g) 55%' },{ key: 'carboidrato_g_65', 'label': '65%' },{ key: 'na_mg', label: 'Na(mg)' } ], refs: [ { nivel: '20% VET', n_ref: '1', valores: { energia: 543, proteina_g_10: 14, proteina_g_15: 20, lipidio_g_15: 9, lipidio_g_30: 18, carboidrato_g_55: 75, carboidrato_g_65: 88, na_mg: 600 } }, { nivel: '30% VET', n_ref: '2', valores: { energia: 815, proteina_g_10: 20, proteina_g_15: 31, lipidio_g_15: 14, lipidio_g_30: 27, carboidrato_g_55: 112, carboidrato_g_65: 132, na_mg: 800 } }, { nivel: '70% VET', n_ref: '3', valores: { energia: 1902, proteina_g_10: 48, proteina_g_15: 71, lipidio_g_15: 32, lipidio_g_30: 63, carboidrato_g_55: 262, carboidrato_g_65: 309, na_mg: 1400 } } ] }, 'eja_19_30': { faixa: 'EJA (19-30 anos)', colunas: [ { key: 'nivel', label: 'Ref. PNAE' }, { key: 'n_ref', label: 'Nº ref.' }, { key: 'energia', label: 'Kcal' }, { key: 'proteina_g_10', label: 'PTN (g) 10%' },{ key: 'proteina_g_15', label: '15%' },{ key: 'lipidio_g_15', label: 'LIP (g) 15%' },{ key: 'lipidio_g_30', label: '30%' },{ key: 'carboidrato_g_55', label: 'CHO (g) 55%' },{ key: 'carboidrato_g_65', 'label': '65%' },{ key: 'na_mg', label: 'Na(mg)' } ], refs: [ { nivel: '20% VET', n_ref: '1', valores: { energia: 477, proteina_g_10: 12, proteina_g_15: 18, lipidio_g_15: 8, lipidio_g_30: 16, carboidrato_g_55: 66, carboidrato_g_65: 77, na_mg: 600 } }, { nivel: '30% VET', n_ref: '2', valores: { energia: 715, proteina_g_10: 18, proteina_g_15: 27, lipidio_g_15: 12, lipidio_g_30: 24, carboidrato_g_55: 98, carboidrato_g_65: 116, na_mg: 800 } }, { nivel: '70% VET', n_ref: '3', valores: { energia: 1668, proteina_g_10: 42, proteina_g_15: 63, lipidio_g_15: 28, lipidio_g_30: 56, carboidrato_g_55: 229, carboidrato_g_65: 271, na_mg: 1400 } } ] }, 'eja_31_60': { faixa: 'EJA (31-60 anos)', colunas: [ { key: 'nivel', label: 'Ref. PNAE' }, { key: 'n_ref', label: 'Nº ref.' }, { key: 'energia', label: 'Kcal' }, { key: 'proteina_g_10', label: 'PTN (g) 10%' },{ key: 'proteina_g_15', label: '15%' },{ key: 'lipidio_g_15', label: 'LIP (g) 15%' },{ key: 'lipidio_g_30', label: '30%' },{ key: 'carboidrato_g_55', label: 'CHO (g) 55%' },{ key: 'carboidrato_g_65', 'label': '65%' },{ key: 'na_mg', label: 'Na(mg)' } ], refs: [ { nivel: '20% VET', n_ref: '1', valores: { energia: 459, proteina_g_10: 11, proteina_g_15: 17, lipidio_g_15: 8, lipidio_g_30: 15, carboidrato_g_55: 63, carboidrato_g_65: 75, na_mg: 600 } }, { nivel: '30% VET', n_ref: '2', valores: { energia: 689, proteina_g_10: 17, proteina_g_15: 26, lipidio_g_15: 11, lipidio_g_30: 23, carboidrato_g_55: 95, carboidrato_g_65: 112, na_mg: 800 } }, { nivel: '70% VET', n_ref: '3', valores: { energia: 1607, proteina_g_10: 40, proteina_g_15: 60, lipidio_g_15: 27, lipidio_g_30: 54, carboidrato_g_55: 221, carboidrato_g_65: 261, na_mg: 1400 } } ] } };

        if (loadError || !dadosBaseOk || !currentProjectId) { console.error("ERRO FATAL: Carregamento inicial falhou ou ID do projeto ausente. Script JS interrompido."); if (!loadError && !dadosBaseOk) { showStatus('error', 'Erro: Dados base de alimentos não carregados.', 'fa-database'); } else if (!currentProjectId && !loadError) { showStatus('error', 'Erro: ID do projeto não especificado.', 'fa-link-slash'); } $('.action-button, #faixa-etaria-select, input, select').prop('disabled', true).css('cursor', 'not-allowed'); return; }
        let alimentosCompletos = <?php echo $alimentos_completos_json; ?>;
        let alimentosIdNomeMap = <?php echo $alimentos_disponiveis_json; ?>;
        const todasPorcoesDb = <?php echo $todas_porcoes_db_json; ?>;
        let cardapioAtual = <?php echo $cardapio_inicial_json; ?>;
        let refeicoesLayout = <?php echo $refeicoes_layout_json; ?>;
        let datasDias = <?php echo $datas_dias_json; ?>;
        let diasDesativados = <?php echo $dias_desativados_json; ?>;
        let faixaEtariaSelecionada = <?php echo json_encode($faixa_etaria_inicial_key); ?>;
        const initialInstanceGroups = <?php echo $initial_instance_groups_json; ?>;
        const diasKeys = <?php echo $dias_keys_json; ?>;
        const diasNomesMap = <?php echo $dias_nomes_json; ?>;
        let alimentosParaModalListaJS = JSON.parse(JSON.stringify(alimentosCompletos));
        let requestActive = false; let calculationTimeout; let currentEditingLi = null;
        const mainSelectionModal = $('#selection-modal'); const groupChoiceModal = $('#instance-group-choice-modal'); const novaPreparacaoModal = $('#nova-preparacao-modal');
        let modalCurrentSelections = new Set(); let foodInstanceGroupCounters = {}; let groupChoiceQueue = []; let selectedItemLi = null; let gridHasChanged = false;
        let selectedItemsCollection = $(); let lastClickedLi = null; let targetCellForPaste = null; let internalClipboard = { type: null, itemsData: [] };
        
        // --- NOVA LÓGICA PARA O MODAL DE PREPARAÇÃO ---
        let prepCalculationTimeout;
        let prepRequestActive = false;

        // A função displayMessageBox agora está em global.js e é acessível globalmente
        // function displayMessageBox(message, isConfirm = false, callback = null) { /* ... */ }

        function showStatus(type, message, iconClass = 'fa-info-circle') { statusMessage.removeClass('loading error success warning info').addClass(`status ${type}`).html(`<i class="fas ${iconClass}"></i> ${message}`); }
        function sanitizeString(str) { if (typeof str !== 'string') return ''; return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9\s]/g, ''); }
        function generatePlacementId() { return `place_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`; }
        function generateRefKey() { return `ref_dyn_${Date.now()}_${Math.random().toString(36).substring(2, 5)}`; }
        function generatePreparacaoId() { return `prep_${Date.now()}_${Math.random().toString(36).substring(2, 7)}`; }
        function getNextInstanceGroupNumber(foodId) { return (foodInstanceGroupCounters[foodId] || 0) + 1; }
        function findExistingInstanceGroups(foodId) { const groups = {}; diasKeys.forEach(dia => { if (!diasDesativados[dia] && cardapioAtual[dia]) { Object.keys(refeicoesLayout).forEach(refKey => { if (cardapioAtual[dia][refKey]) { cardapioAtual[dia][refKey].forEach(item => { if (item && item.foodId === foodId && item.instanceGroup && item.qty) { if (!(item.instanceGroup in groups)) { groups[item.instanceGroup] = item.qty; } } }); } }); } }); return Object.entries(groups).map(([group, qty]) => ({ group: parseInt(group, 10), qty: parseInt(qty, 10) })).sort((a, b) => a.group - b.group); }
        function initializeInstanceGroupCounters() { foodInstanceGroupCounters = {}; diasKeys.forEach(dia => { if (cardapioAtual[dia]) { Object.keys(refeicoesLayout).forEach(refKey => { if (cardapioAtual[dia][refKey] && Array.isArray(cardapioAtual[dia][refKey])) { cardapioAtual[dia][refKey].forEach(item => { if (item && item.foodId && item.instanceGroup) { const currentMax = foodInstanceGroupCounters[item.foodId] || 0; if (item.instanceGroup > currentMax) { foodInstanceGroupCounters[item.foodId] = item.instanceGroup; } } }); } }); } }); if (typeof initialInstanceGroups === 'object' && initialInstanceGroups !== null) { for (const foodId in initialInstanceGroups) { const initialMax = initialInstanceGroups[foodId]; const currentMax = foodInstanceGroupCounters[foodId] || 0; if (initialMax > currentMax) { foodInstanceGroupCounters[foodId] = initialMax; } } } }
        function htmlspecialchars(str) { if (typeof str !== 'string') return ''; const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }; return str.replace(/[&<>"']/g, m => map[m]); }
        function updateRemoveButtonsVisibility() { const totalRefeicoes = Object.keys(refeicoesLayout).length; $tableBody.find('tr').each(function(){ $(this).find('td.action-cell').css('visibility', totalRefeicoes > 1 ? 'visible' : 'hidden'); }); }
        function markGridChanged() { if (!currentProjectId) return; gridHasChanged = true; saveStatusSpan.text('Alterado').css('color', 'orange'); $('#save-project-btn').removeClass('saved').addClass('unsaved'); clearTimeout(saveTimeout); saveTimeout = setTimeout(saveProjectData, 5000); }
        function getGridStateForSaving() { return { refeicoes: { ...refeicoesLayout }, dias: { ...cardapioAtual }, datas_dias: { ...datasDias }, dias_desativados: { ...diasDesativados }, faixa_etaria_selecionada: faixaEtariaSelect.val() || null }; }
        function saveProjectData() { if (!gridHasChanged || isSaving || !currentProjectId) return; isSaving = true; gridHasChanged = false; saveStatusSpan.text('Salvando...').css('color', 'var(--color-info)'); $('#save-project-btn').prop('disabled', true); const dataToSave = getGridStateForSaving(); const jsonData = JSON.stringify(dataToSave); $.ajax({ url: 'save_project.php', method: 'POST', data: { projeto_id: currentProjectId, dados_json: jsonData }, dataType: 'json', success: function(response) { if (response.success) { saveStatusSpan.text('Salvo').css('color', 'var(--color-success)'); $('#save-project-btn').removeClass('unsaved').addClass('saved'); setTimeout(() => { if (!gridHasChanged) saveStatusSpan.text(''); }, 3000); } else { saveStatusSpan.text('Erro!').css('color', 'var(--color-error)'); gridHasChanged = true; $('#save-project-btn').removeClass('saved').addClass('unsaved'); displayMessageBox('Erro ao salvar: ' + (response.message || 'Desconhecido.')); } }, error: function() { saveStatusSpan.text('Falha!').css('color', 'var(--color-error)'); gridHasChanged = true; $('#save-project-btn').removeClass('saved').addClass('unsaved'); displayMessageBox('Erro comunicação ao salvar.'); }, complete: function() { isSaving = false; $('#save-project-btn').prop('disabled', false); } }); }
        $('#save-project-btn').on('click', function() { clearTimeout(saveTimeout); saveProjectData(); });
        function renderCardapioGrid() { $tableHead.empty(); $tableBody.empty(); let headerHtml = '<tr><th style="width: 120px;">Refeição</th><th style="width: 70px;">Horário</th>'; diasKeys.forEach(diaKey => { const diaNome = diasNomesMap[diaKey] || diaKey.toUpperCase(); const isDesativado = diasDesativados[diaKey] ?? false; const dataDia = datasDias[diaKey] ?? ''; const thClass = isDesativado ? 'dia-desativado' : ''; const btnClass = isDesativado ? 'active' : ''; const btnIcon = isDesativado ? 'fa-toggle-on' : 'fa-toggle-off'; const btnTitle = isDesativado ? `Ativar ${diaNome}` : `Desativar ${diaNome} (Feriado)`; headerHtml += `<th data-dia-col="${diaKey}" style="width: 250px;" class="${thClass}"><span class="dia-nome">${diaNome}</span><div class="dia-controles"><input type="text" class="dia-data-input" data-dia="${diaKey}" placeholder="dd/mm" title="Data" value="${htmlspecialchars(dataDia)}"><button type="button" class="toggle-feriado-btn ${btnClass}" data-dia="${diaKey}" title="${btnTitle}"><i class="fas ${btnIcon}"></i> Feriado</button></div></th>`; }); headerHtml += '<th style="width: 40px;">Ação</th></tr>'; $tableHead.html(headerHtml); if (typeof refeicoesLayout !== 'object' || refeicoesLayout === null || Object.keys(refeicoesLayout).length === 0) { $tableBody.html(`<tr><td colspan="${diasKeys.length + 3}" style="color:red;text-align:center;padding:20px;">Erro: Layout de refeições não carregado.</td></tr>`); return; } Object.entries(refeicoesLayout).forEach(([refKey, refInfo]) => { if (refInfo && typeof refInfo.label === 'string' && typeof refInfo.horario === 'string') { const rowHtml = createRowHTML(refKey, refInfo.label, refInfo.horario); $tableBody.append(rowHtml); } }); $tableBody.find('tr').each(function() { const row = $(this); const refKey = row.data('refeicao-key'); row.find('td.editable-cell').each(function(){ const cell = $(this); const diaKey = cell.data('dia'); let itemsDaCelula = (cardapioAtual?.[diaKey]?.[refKey]) ? cardapioAtual[diaKey][refKey] : []; if (!Array.isArray(itemsDaCelula)) itemsDaCelula = []; updateCellDisplay(cell, itemsDaCelula); }); }); updateRemoveButtonsVisibility(); }
        function createRowHTML(k, l, h) { let cHtml = ''; diasKeys.forEach(d => { const dN = diasNomesMap[d] || d.toUpperCase(); const isD = diasDesativados[d] ?? false; const dC = isD ? ' dia-desativado':''; const iDC = (cardapioAtual?.[d]?.[k]) ? cardapioAtual[d][k] : []; const iJ = JSON.stringify(iDC); cHtml += `<td class="editable-cell${dC}" data-label="${dN}" data-dia="${d}" title="Add/Colar"><button type="button" class="add-item-cell-btn" title="Add ${dN} - ${htmlspecialchars(l)}"><i class="fas fa-plus"></i></button><ul class="selected-items-list" data-selecionados='${htmlspecialchars(iJ)}'>${iDC.length === 0 ? '<li class="placeholder">- Vazio -</li>' : ''}</ul></td>`; }); return `<tr data-refeicao-key="${k}"><td class="label-cell refeicao-label" data-label="Ref." title="Editar"><span class="editable-label">${htmlspecialchars(l)}</span><input type="text" class="label-input" value="${htmlspecialchars(l)}"></td><td class="label-cell horario-cell" data-label="Hor." title="Editar"><span class="editable-label">${htmlspecialchars(h)}</span><input type="text" class="label-input" value="${htmlspecialchars(h)}"></td>${cHtml}<td class="action-cell" data-label="Rem."><button type="button" class="remove-row-btn" title="Remover"><i class="fas fa-trash-alt"></i></button></td></tr>`;}
        function updateCellDisplay(cellElement, itemsData) { const listElement = cellElement.find('ul.selected-items-list'); listElement.empty(); if (itemsData && Array.isArray(itemsData) && itemsData.length > 0) { const sortedItems = [...itemsData].sort((a, b) => { const nA = alimentosIdNomeMap[a.foodId] || ''; const nB = alimentosIdNomeMap[b.foodId] || ''; const c = nA.localeCompare(nB, 'pt-BR', { sensitivity: 'base' }); if (c === 0) return (a.instanceGroup || 1) - (b.instanceGroup || 1); return c; }); sortedItems.forEach(item => { if (!item || typeof item.foodId==='undefined' || !alimentosIdNomeMap[item.foodId] || typeof item.instanceGroup==='undefined' || typeof item.placementId==='undefined') return; const fId=item.foodId.toString(), iG=item.instanceGroup, pId=item.placementId; const fN=alimentosIdNomeMap[fId]; const vQ=Math.max(1,parseInt(item.qty,10)||100); const isPrep=(alimentosCompletos?.[fId])?(alimentosCompletos[fId].isPreparacao??false):false; const li=$(`<li data-food-id="${fId}" data-instance-group="${iG}" data-placement-id="${pId}" title="Sel./Editar Qtd."></li>`); const nameS=$(`<span class="item-name"></span>`).text(fN).append(`<sup class="item-instance-group-number">${iG}</sup>`); if(isPrep)nameS.prepend('<i class="fas fa-mortar-pestle" style="color:var(--color-warning-dark);margin-right:4px;font-size:0.9em;" title="Prep."></i> '); const qtyWrapper = $(`<span class="qty-input-inline-wrapper"></span>`); const qtyInput = $(`<input type="number" class="item-qty-input-inline" value="${vQ}" min="1" step="1" title="Alterar quantidade (Grupo ${iG})">`); qtyWrapper.append(qtyInput).append('<span>g</span>'); const removeB=$(`<button class="item-remove-btn" title="Rem ${fN} (Grp ${iG})"><i class="fas fa-times"></i></button>`); const detD=$('<div class="item-details"></div>').append(nameS); const actD=$('<div class="item-actions"></div>').append(removeB); li.append(detD).append(qtyWrapper).append(actD); if(selectedItemsCollection.filter(`[data-placement-id="${pId}"]`).length > 0) li.addClass('item-selecionado'); listElement.append(li); }); } else { listElement.append('<li class="placeholder">- Vazio -</li>'); } }
        function addItemToCell(cellElement, foodId, instanceGroup, fixedQuantity) { if (!dadosBaseOk || cellElement.length === 0 || cellElement.hasClass('dia-desativado') || !foodId || !instanceGroup || !alimentosIdNomeMap[foodId]) return; const diaKey = cellElement.data('dia'); const refKey = cellElement.closest('tr').data('refeicao-key'); if (!cardapioAtual[diaKey]) cardapioAtual[diaKey] = {}; if (!cardapioAtual[diaKey][refKey]) cardapioAtual[diaKey][refKey] = []; let itemQty; if (fixedQuantity !== null && !isNaN(fixedQuantity) && fixedQuantity > 0) itemQty = parseInt(fixedQuantity, 10); else { const faixaKey = faixaEtariaSelect.val(); itemQty = (faixaKey && todasPorcoesDb?.[faixaKey]?.[foodId]) ? parseInt(todasPorcoesDb[faixaKey][foodId], 10) : (alimentosCompletos[foodId]?.porcao_padrao || 100); } itemQty = Math.max(1, isNaN(itemQty) ? 100 : itemQty); const newP = { foodId, qty: itemQty, instanceGroup, placementId: generatePlacementId() }; cardapioAtual[diaKey][refKey].push(newP); if (instanceGroup > (foodInstanceGroupCounters[foodId] || 0)) foodInstanceGroupCounters[foodId] = instanceGroup; cardapioAtual[diaKey][refKey].sort((a, b) => { const nA=alimentosIdNomeMap[a.foodId]||'', nB=alimentosIdNomeMap[b.foodId]||'', c=nA.localeCompare(nB,'pt-BR',{sensitivity:'base'}); return c===0?(a.instanceGroup||1)-(b.instanceGroup||1):c; }); cellElement.find('ul.selected-items-list').attr('data-selecionados', JSON.stringify(cardapioAtual[diaKey][refKey])); updateCellDisplay(cellElement, cardapioAtual[diaKey][refKey]); if (fixedQuantity !== null) updateQuantityForInstanceGroup(foodId, instanceGroup, itemQty); markGridChanged(); }
        function removeItemFromCell(liElement) { if (!liElement?.length || liElement.hasClass('placeholder')) return false; const pId = liElement.data('placement-id'); const cell = liElement.closest('td.editable-cell'); if (!pId || !cell.length || cell.hasClass('dia-desativado')) return false; const dK = cell.data('dia'); const rK = cell.closest('tr').data('refeicao-key'); if (!cardapioAtual?.[dK]?.[rK] || !Array.isArray(cardapioAtual[dK][rK])) return false; const initL = cardapioAtual[dK][rK].length; cardapioAtual[dK][rK] = cardapioAtual[dK][rK].filter(i => !(i && i.placementId === pId)); if (cardapioAtual[dK][rK].length < initL) { markGridChanged(); updateCellDisplay(cell, cardapioAtual[dK][rK]); return true; } return false; }
        function updateQuantityForInstanceGroup(foodId, instanceGroup, newQuantity) { let changed = false; newQuantity = Math.max(1, parseInt(newQuantity,10) || 1); diasKeys.forEach(dia => { if (cardapioAtual[dia]) { Object.keys(refeicoesLayout).forEach(refKey => { if (cardapioAtual[dia][refKey]) { let cellChanged = false; cardapioAtual[dia][refKey].forEach(item => { if (item && item.foodId === foodId && item.instanceGroup === instanceGroup && item.qty !== newQuantity) { item.qty = newQuantity; cellChanged = true; changed = true; } }); if (cellChanged) { const cellElement = $tableBody.find(`tr[data-refeicao-key="${refKey}"] td[data-dia="${dia}"]`); if(cellElement.length) { cellElement.find('ul.selected-items-list').attr('data-selecionados', JSON.stringify(cardapioAtual[dia][refKey])); updateCellDisplay(cellElement, cardapioAtual[dia][refKey]); } } } }); } }); if (changed) { markGridChanged(); triggerCalculation(); } }
        $tableBody.on('change blur', '.item-qty-input-inline', function(e) { const $input = $(this); const $li = $input.closest('li'); const foodId = $li.data('food-id').toString(); const instanceGroup = $li.data('instance-group'); let newQuantity = parseInt($input.val(), 10); if (isNaN(newQuantity) || newQuantity < 1) newQuantity = 1; $input.val(newQuantity); updateQuantityForInstanceGroup(foodId, instanceGroup, newQuantity); if(e.type === 'blur') $input.closest('td').find('ul.selected-items-list li.item-selecionado').removeClass('item-selecionado'); });
        $tableBody.on('click', '.item-qty-input-inline', function(e) { e.stopPropagation(); });
        $tableBody.on('keydown', '.item-qty-input-inline', function(e) { if (e.key === 'Enter') { e.preventDefault(); $(this).blur(); } });
        function updateGridQuantitiesForAgeGroup(newFaixaKey) { if (!newFaixaKey || !todasPorcoesDb?.[newFaixaKey]) return; const porcoesDaFaixa = todasPorcoesDb[newFaixaKey]; let globalChange = false; diasKeys.forEach(diaKey => { if (!diasDesativados[diaKey] && cardapioAtual[diaKey]) { Object.keys(refeicoesLayout).forEach(refKey => { if (cardapioAtual[diaKey][refKey]) { let cellDataChanged = false; cardapioAtual[diaKey][refKey].forEach(item => { if (item?.foodId && item.qty && item.instanceGroup && item.placementId) { const foodId = item.foodId.toString(); const newDefaultQty = parseInt(porcoesDaFaixa[foodId] ?? (alimentosCompletos[foodId]?.porcao_padrao || 100) , 10); const safeNewQty = Math.max(1, isNaN(newDefaultQty) ? 100 : newDefaultQty); if (item.qty !== safeNewQty) { item.qty = safeNewQty; cellDataChanged = true; globalChange = true; } } }); if (cellDataChanged) { const cellElement = $tableBody.find(`tr[data-refeicao-key="${refKey}"] td[data-dia="${diaKey}"]`); if (cellElement.length) { cellElement.find('ul.selected-items-list').attr('data-selecionados', JSON.stringify(cardapioAtual[diaKey][refKey])); updateCellDisplay(cellElement, cardapioAtual[diaKey][refKey]); } } } }); } }); if (globalChange) markGridChanged(); }
        function saveLabelEdit(inputElement) { if (!inputElement.hasClass('editing')) return; const newValue = inputElement.val().trim(); const spanElement = inputElement.siblings('span.editable-label'); const cellElement = inputElement.closest('td'); const rowElement = inputElement.closest('tr'); const refKey = rowElement.data('refeicao-key'); spanElement.text(newValue); inputElement.removeClass('editing').hide(); spanElement.removeClass('editing'); if (refKey && refeicoesLayout[refKey]) { if (cellElement.hasClass('refeicao-label')) { refeicoesLayout[refKey].label = newValue; } else if (cellElement.hasClass('horario-cell')) { refeicoesLayout[refKey].horario = newValue; } markGridChanged(); } }
        function addNewRefeicaoRow() {const newKey=generateRefKey(); const nums=Object.keys(refeicoesLayout).map(k=>refeicoesLayout[k].label.match(/REFEIÇÃO (\d+)/)).filter(m=>m).map(m=>parseInt(m[1])); const nextN=nums.length>0?Math.max(...nums)+1:Object.keys(refeicoesLayout).length+1; refeicoesLayout[newKey]={label:`NOVA REFEIÇÃO ${nextN}`,horario:"HH:MM"}; if(typeof cardapioAtual!=='object'||cardapioAtual===null)cardapioAtual={}; diasKeys.forEach(dK=>{if(typeof cardapioAtual[dK]!=='object'||cardapioAtual[dK]===null)cardapioAtual[dK]={};cardapioAtual[dK][newKey]=[];if(typeof datasDias[dK]==='undefined')datasDias[dK]='';if(typeof diasDesativados[dK]==='undefined')diasDesativados[dK]=false;}); renderCardapioGrid(); const newRE=$tableBody.find(`tr[data-refeicao-key="${newKey}"]`); if(newRE.length>0)newRE.find('.refeicao-label span.editable-label').click(); updateRemoveButtonsVisibility(); markGridChanged();}
        function openMainSelectionModal(cellElement) { if (!dadosBaseOk || cellElement.hasClass('dia-desativado')) return; const targetCell = cellElement; modalCurrentSelections.clear(); const diaKey = targetCell.data('dia'); const refeicaoKey = targetCell.closest('tr').data('refeicao-key'); const diaNome = diasNomesMap[diaKey] || diaKey; const refeicaoNome = targetCell.closest('tr').find('.refeicao-label .editable-label').text().trim() || refeicaoKey; mainSelectionModal.find('#modal-title').text(`Adicionar em: ${diaNome} - ${refeicaoNome}`); populateMainModalSearchList(''); mainSelectionModal.data('targetCell', targetCell); mainSelectionModal.css('display', 'flex').hide().fadeIn(200); mainSelectionModal.find('#modal-search').val('').focus(); }
        function populateMainModalSearchList(searchTerm) { const ulSearch = mainSelectionModal.find('.modal-search-list'); ulSearch.empty(); const term = sanitizeString(searchTerm); let count = 0; if (typeof alimentosParaModalListaJS === 'object' && alimentosParaModalListaJS !== null && Object.keys(alimentosParaModalListaJS).length > 0) { const sortedList = Object.values(alimentosParaModalListaJS).sort((a, b) => (a.nome || '').localeCompare(b.nome || '', 'pt-BR', { sensitivity: 'base' })); for (const itemData of sortedList) { const foodId = itemData.id; const nome = itemData.nome || 'Inválido'; const nomeSanitized = sanitizeString(nome); const isPrep = itemData.isPreparacao || false; if (term === '' || nomeSanitized.includes(term)) { const isChecked = modalCurrentSelections.has(foodId); ulSearch.append(createSearchListItem(foodId, nome, isChecked, isPrep)); count++; } } } if (count === 0) ulSearch.append('<li class="no-results">- Nenhum item -</li>'); }
        function createSearchListItem(id, name, isChecked, isPreparacao = false) { const li = $('<li></li>'); const label = $('<label></label>').attr('for', `mchk_${id}`); const checkbox = $('<input type="checkbox" class="modal-add-item-chk">').val(id).attr('id', `mchk_${id}`).prop('checked', isChecked); const span = $('<span></span>').text(` ${name}`); if (isPreparacao) { span.prepend('<i class="fas fa-mortar-pestle" style="color: var(--color-warning-dark); margin-right: 5px;" title="Prep."></i> '); } label.append(checkbox).append(span); li.append(label); return li; }
        mainSelectionModal.on('change', '.modal-add-item-chk', function() { const foodId = $(this).val(); if ($(this).is(':checked')) modalCurrentSelections.add(foodId); else modalCurrentSelections.delete(foodId); });
        mainSelectionModal.on('keyup', '#modal-search', function() { populateMainModalSearchList($(this).val()); });
        mainSelectionModal.on('click', '#modal-confirm', function() { if (!dadosBaseOk) return; const targetCell = mainSelectionModal.data('targetCell'); if (!targetCell?.length || targetCell.hasClass('dia-desativado')) { closeModal(mainSelectionModal); return; } const foodIdsToAdd = Array.from(modalCurrentSelections); if (foodIdsToAdd.length === 0) { closeModal(mainSelectionModal); return; } groupChoiceQueue = foodIdsToAdd.map(foodId => ({ foodId: foodId, targetCell: targetCell })); closeModal(mainSelectionModal); processNextGroupChoice(); });
        function processNextGroupChoice() { if (groupChoiceQueue.length === 0) { triggerCalculation(); return; } const currentChoice = groupChoiceQueue.shift(); const foodId = currentChoice.foodId; const targetCell = currentChoice.targetCell; if (!foodId || !targetCell?.length || targetCell.hasClass('dia-desativado')) { processNextGroupChoice(); return; } const foodName = alimentosIdNomeMap[foodId] || `ID ${foodId}`; const existingGroups = findExistingInstanceGroups(foodId); if (existingGroups.length > 0) { openGroupChoiceModal(foodId, foodName, targetCell, existingGroups); } else { addItemToCell(targetCell, foodId, 1, null); processNextGroupChoice(); } }
        function openGroupChoiceModal(foodId, foodName, targetCell, existingGroups) { groupChoiceModal.data({foodId, targetCellRef: { refKey:targetCell.closest('tr').data('refeicao-key'), diaKey:targetCell.data('dia') }}); groupChoiceModal.find('#group-choice-food-name').text(`Adicionar ${foodName}?`); const optList=groupChoiceModal.find('#group-choice-options').empty(); existingGroups.forEach((gI,idx)=>{ const li=$('<li></li>'), rId=`gc_${gI.group}`,lbl=$(`<label for="${rId}"></label>`),r=$('<input type="radio" name="group_choice" required>').attr('id',rId).val(gI.group).data('qty',gI.qty); if(idx===0)r.prop('checked',true); lbl.append(r).append(`<span class="group-option-label">Add ao Grupo ${gI.group}</span>`).append(`<span class="group-option-qty">(${gI.qty}g)</span>`); li.append(lbl); optList.append(li); }); const nextGN=getNextInstanceGroupNumber(foodId); const nLi=$('<li></li>'),nId='gc_new',nLbl=$(`<label for="${nId}"></label>`),nR=$('<input type="radio" name="group_choice" required>').attr('id',nId).val('new').data('next-group',nextGN); nLbl.append(nR).append(`<span class="option-label new-group-label">Novo Grupo (${nextGN})</span>`).append(`<span class="group-option-qty">(Qtd Padrão)</span>`); nLi.append(nLbl); optList.append(nLi); groupChoiceModal.css('display','flex').hide().fadeIn(150); }
        $('#group-choice-confirm').on('click', function() { if(!dadosBaseOk)return; const selOpt=groupChoiceModal.find('input[name="group_choice"]:checked'); if(!selOpt.length){displayMessageBox("Selecione uma opção para continuar.");return;} const sFoodId=groupChoiceModal.data('foodId'); const tcRef=groupChoiceModal.data('targetCellRef'); let tcFS=null; if(tcRef?.refKey&&tcRef?.diaKey)tcFS=$tableBody.find(`tr[data-refeicao-key="${tcRef.refKey}"] td.editable-cell[data-dia="${tcRef.diaKey}"]`); const cVal=selOpt.val(); let iG,qA=null; if(cVal==='new'){iG=selOpt.data('next-group');qA=null;}else{iG=parseInt(cVal,10);qA=selOpt.data('qty');} if(tcFS?.length&&!tcFS.hasClass('dia-desativado')&&sFoodId&&iG)addItemToCell(tcFS,sFoodId,iG,qA); else displayMessageBox("Erro ao adicionar item."); groupChoiceModal.removeData('foodId').removeData('targetCellRef'); closeModal(groupChoiceModal); processNextGroupChoice(); });
        
        // --- INÍCIO DA LÓGICA DO NOVO MODAL DE FICHA TÉCNICA ---

        // Abrir o modal
        $('#nova-preparacao-btn').on('click', function() {
            if (!dadosBaseOk) return;
            // Resetar campos
            novaPreparacaoModal.find('#prep-nome').val('');
            novaPreparacaoModal.find('#prep-rendimento').val('12');
            novaPreparacaoModal.find('#prep-porcao-g').val('100');
            novaPreparacaoModal.find('#prep-ingredient-search').val('');
            novaPreparacaoModal.find('#prep-search-results').empty().hide();
            novaPreparacaoModal.find('#prep-ingredients-tbody').html('<tr class="placeholder"><td colspan="5">- Nenhum ingrediente adicionado -</td></tr>');
            novaPreparacaoModal.find('#prep-modo-preparo').val('');
            
            // Resetar tabela nutricional
            clearPreparacaoNutriDisplay();
            $('#prep-nutri-title-portion-size').text('(100 g)');

            novaPreparacaoModal.css('display', 'flex').hide().fadeIn(200).find('#prep-nome').focus();
        });
        
        // Busca de ingredientes
        $('#prep-ingredient-search').on('keyup', function() { 
            const sT = sanitizeString($(this).val());
            const rUl = $('#prep-search-results').empty();
            if (sT.length < 2) { rUl.hide(); return; }
            let c = 0;
            if (typeof alimentosCompletos === 'object' && alimentosCompletos !== null) {
                const sBF = Object.values(alimentosCompletos).filter(i => i && !i.isPreparacao).sort((a,b) => (a.nome||'').localeCompare(b.nome||'','pt-BR',{sensitivity:'base'}));
                for (const f of sBF) {
                    if (f?.id && f.nome && sanitizeString(f.nome).includes(sT)) {
                        rUl.append(`<li data-id="${f.id}" data-nome="${htmlspecialchars(f.nome)}">${htmlspecialchars(f.nome)}</li>`);
                        c++;
                    }
                }
            }
            if (c === 0) rUl.append('<li class="no-results">- Nenhum resultado -</li>');
            rUl.show();
        });

        // Adicionar ingrediente da busca para a tabela
        $(document).on('click', '#prep-search-results li:not(.no-results)', function() {
            if (!dadosBaseOk) return;
            const foodId = $(this).data('id').toString();
            const foodName = $(this).data('nome');
            addIngredientToPrepTable(foodId, foodName);
            $('#prep-ingredient-search').val('').focus();
            $('#prep-search-results').empty().hide();
        });

        function addIngredientToPrepTable(foodId, foodName) {
            const tbody = $('#prep-ingredients-tbody');
            if(tbody.find(`tr[data-id="${foodId}"]`).length > 0) {
                displayMessageBox(`O ingrediente "${foodName}" já está na lista.`);
                return;
            }
            tbody.find('.placeholder').remove();
            const defaultQty = alimentosCompletos?.[foodId]?.porcao_padrao ?? 100;
            const newRow = `
                <tr data-id="${foodId}">
                    <td>${htmlspecialchars(foodName)}</td>
                    <td><input type="number" class="prep-ing-pl" value="${defaultQty}" min="1" step="1"></td>
                    <td><input type="number" class="prep-ing-fc" value="1.00" min="0.1" step="0.01"></td>
                    <td><span class="prep-ing-pb">${defaultQty.toFixed(2).replace('.',',')}</span></td>
                    <td style="text-align:center;"><button type="button" class="prep-ing-remove-btn" title="Remover ${htmlspecialchars(foodName)}"><i class="fas fa-times-circle"></i></button></td>
                </tr>`;
            tbody.append(newRow);
            triggerPreparacaoCalculation();
        }

        // Remover ingrediente da tabela
        novaPreparacaoModal.on('click', '.prep-ing-remove-btn', function() {
            const row = $(this).closest('tr');
            const tbody = row.parent();
            row.remove();
            if (tbody.children().length === 0) {
                tbody.html('<tr class="placeholder"><td colspan="5">- Nenhum ingrediente adicionado -</td></tr>');
            }
            triggerPreparacaoCalculation();
        });

        // Atualizar Peso Bruto (PB) e disparar cálculo ao alterar PL ou FC
        novaPreparacaoModal.on('input', '.prep-ing-pl, .prep-ing-fc', function() {
            const row = $(this).closest('tr');
            const pl = parseFloat(row.find('.prep-ing-pl').val()) || 0;
            const fc = parseFloat(row.find('.prep-ing-fc').val()) || 0;
            const pb = pl * fc;
            row.find('.prep-ing-pb').text(pb.toFixed(2).replace('.',','));
            triggerPreparacaoCalculation();
        });
        
        // Atualizar o título do valor nutritivo
        novaPreparacaoModal.on('input', '#prep-porcao-g', function() {
            const porcao_g = parseInt($(this).val(), 10) || 100;
            $('#prep-nutri-title-portion-size').text(`(${porcao_g} g)`);
            triggerPreparacaoCalculation(); // Recalcular ao mudar peso da porção
        });
         novaPreparacaoModal.on('input', '#prep-rendimento', triggerPreparacaoCalculation);

        // Disparar o cálculo da preparação com debounce
        function triggerPreparacaoCalculation() {
            clearTimeout(prepCalculationTimeout);
            prepCalculationTimeout = setTimeout(calculatePreparacaoNutrients, 500);
        }

        // Função principal para calcular nutrientes da preparação
        function calculatePreparacaoNutrients() {
            if (prepRequestActive || !dadosBaseOk) return;
            
            const ingredients = [];
            $('#prep-ingredients-tbody tr:not(.placeholder)').each(function() {
                const row = $(this);
                const pbText = row.find('.prep-ing-pb').text().replace(',', '.');
                const qty = parseFloat(pbText) || 0;
                if (qty > 0) {
                    ingredients.push({
                        id: row.data('id').toString(),
                        qty: qty,
                        is_prep: alimentosCompletos[row.data('id').toString()]?.isPreparacao ?? false
                    });
                }
            });

            if (ingredients.length === 0) {
                clearPreparacaoNutriDisplay();
                return;
            }
            
            // Reutiliza a API de cálculo, enviando os ingredientes como um cardápio temporário
            const apiData = {
                cardapio: {
                    'prep_calc_day': {
                        'prep_calc_meal': ingredients
                    }
                },
                dias_ativos: ['prep_calc_day'],
                faixa_etaria: 'fund_6_10', // Faixa de referência, não impacta o cálculo bruto
                meta: { preparacoes_defs: {} }
            };

            prepRequestActive = true;
            $('#prep-calc-status').text('Calculando...').show();
            
            $.ajax({
                url: 'api_calculator.php',
                method: 'POST',
                data: { payload: JSON.stringify(apiData) },
                dataType: 'json',
                success: function(response) {
                    if (response && response.daily_totals && response.daily_totals.prep_calc_day) {
                        updatePreparacaoNutriDisplay(response.daily_totals.prep_calc_day);
                        $('#prep-calc-status').text('Cálculo atualizado.').fadeOut(2000);
                    } else {
                        $('#prep-calc-status').text('Erro no cálculo.').css('color', 'red');
                        console.error("Erro API Prep Calc:", response);
                    }
                },
                error: function(jqXHR) {
                    $('#prep-calc-status').text('Falha de comunicação.').css('color', 'red');
                    console.error("Erro AJAX Prep Calc:", jqXHR.responseText);
                },
                complete: function() {
                    prepRequestActive = false;
                }
            });
        }
        
        function updatePreparacaoNutriDisplay(totalNutrients) {
            const rendimento = parseInt($('#prep-rendimento').val(), 10) || 1;
            const porcao_g = parseInt($('#prep-porcao-g').val(), 10) || 100;
            let totalWeight = 0;
            $('#prep-ingredients-tbody .prep-ing-pb').each(function(){
                totalWeight += parseFloat($(this).text().replace(',', '.')) || 0;
            });

            // Fator de correção para ajustar os nutrientes do total para a porção
            // Se o peso total for 0, o fator é 0 para evitar divisão por zero.
            const factor = totalWeight > 0 ? (porcao_g / totalWeight) : 0;
            
            const nutriTable = $('#prep-nutri-table');
            const formatters = {
                kcal: v => v != null && !isNaN(v) ? Math.round(v).toLocaleString('pt-BR') : '0',
                g: v => v != null && !isNaN(v) ? v.toFixed(2).replace('.', ',') : '0,00',
                mg_int: v => v != null && !isNaN(v) ? Math.round(v).toLocaleString('pt-BR') : '0',
                mg_dec: v => v != null && !isNaN(v) ? v.toFixed(2).replace('.', ',') : '0,00'
            };

            const perPortion = {};
            for (const key in totalNutrients) {
                perPortion[key] = (totalNutrients[key] || 0) * factor;
            }

            nutriTable.find('td[data-nutrient="kcal"]').text(formatters.kcal(perPortion.kcal));
            nutriTable.find('td[data-nutrient="cho"]').text(formatters.g(perPortion.cho));
            nutriTable.find('td[data-nutrient="ptn"]').text(formatters.g(perPortion.ptn));
            nutriTable.find('td[data-nutrient="lpd"]').text(formatters.g(perPortion.lpd));
            nutriTable.find('td[data-nutrient="col"]').text(formatters.mg_dec(perPortion.col));
            nutriTable.find('td[data-nutrient="fib"]').text(formatters.g(perPortion.fib));
            nutriTable.find('td[data-nutrient="vita"]').text(formatters.mg_dec(perPortion.vita));
            nutriTable.find('td[data-nutrient="vitc"]').text(formatters.mg_dec(perPortion.vitc));
            nutriTable.find('td[data-nutrient="ca"]').text(formatters.mg_int(perPortion.ca));
            nutriTable.find('td[data-nutrient="fe"]').text(formatters.mg_dec(perPortion.fe));
            nutriTable.find('td[data-nutrient="na"]').text(formatters.mg_int(perPortion.na));
        }
        
        function clearPreparacaoNutriDisplay() {
            const nutriTable = $('#prep-nutri-table');
            nutriTable.find('td[data-nutrient]').each(function(){
                const nutrient = $(this).data('nutrient');
                if (['cho', 'ptn', 'lpd', 'col', 'fib', 'vita', 'vitc', 'fe'].includes(nutrient)) {
                    $(this).text('0,00');
                } else {
                    $(this).text('0');
                }
            });
            $('#prep-calc-status').hide();
        }

        // Salvar a preparação
        $('#prep-save').on('click', function() {
            if (!dadosBaseOk) { displayMessageBox("Dados base não carregados."); return; }

            const nomePrep = $('#prep-nome').val().trim();
            if (!nomePrep) { displayMessageBox("Insira um nome para a preparação."); $('#prep-nome').focus(); return; }

            const ingredientesDaNovaPrepa = [];
            $('#prep-ingredients-tbody tr:not(.placeholder)').each(function() {
                const row = $(this);
                const foodId = row.data('id').toString(); // Pega o foodId da linha
                const pl = parseFloat(row.find('.prep-ing-pl').val()) || 0;
                const fc = parseFloat(row.find('.prep-ing-fc').val()) || 0;
                const pb = parseFloat(row.find('.prep-ing-pb').text().replace(',', '.')) || 0; // Pega o PB calculado

                if (foodId && pl > 0 && fc > 0) {
                    ingredientesDaNovaPrepa.push({ 
                        foodId: foodId, 
                        qty: pb, // Enviamos o PB, como o backend espera (e o backend deve aplicar o FC)
                        fc: fc, // Enviamos o FC para o backend usar no cálculo
                        nomeOriginal: row.data('food-name') // Nome original para referência
                    });
                }
            });

            if (ingredientesDaNovaPrepa.length === 0) { displayMessageBox("Adicione ingredientes à preparação."); $('#prep-ingredient-search').focus(); return; }
            
            const valoresNutricionais = {};
            $('#prep-nutri-table td[data-nutrient]').each(function() {
                valoresNutricionais[$(this).data('nutrient')] = $(this).text();
            });

            const newPrepId = generatePreparacaoId();
            const dadosNovaPreparacaoParaBackend = {
                id: newPrepId,
                nome: nomePrep,
                rendimento_porcoes: parseInt($('#prep-rendimento').val(), 10) || 1,
                porcao_padrao_g: parseInt($('#prep-porcao-g').val(), 10) || 100,
                modo_preparo: $('#prep-modo-preparo').val().trim(),
                ingredientes: ingredientesDaNovaPrepa, // Envia ingredientes com PB e FC
                valores_nutricionais_porcao: valoresNutricionais,
                isPreparacao: true
            };

            $.ajax({
                url: 'preparacao_actions.php',
                method: 'POST',
                data: { action: 'create_preparacao', preparacao_data: JSON.stringify(dadosNovaPreparacaoParaBackend) },
                dataType: 'json',
                beforeSend: function() { $('#prep-save').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Salvando...'); },
                success: function(response) {
                    if (response.success && response.todas_preparacoes_atualizadas) {
                        // Atualiza o objeto global alimentosCompletos com a nova preparação
                        // É crucial que a estrutura aqui espelhe o que o API Calculator espera
                        // O API Calculator espera `ingredientes` com `qty` (peso bruto) e `fc`
                        // A `preparacao_actions.php` já retorna `todas_preparacoes_atualizadas` com `qty` (peso bruto) e `fc`
                        alimentosCompletos = { ...alimentosCompletos, ...response.todas_preparacoes_atualizadas };
                        // Atualiza o mapa de nomes
                        for (const prepId in response.todas_preparacoes_atualizadas) {
                            alimentosIdNomeMap[prepId] = response.todas_preparacoes_atualizadas[prepId].nome;
                        }
                        // Atualiza a lista para o modal de seleção (se estiver aberto)
                        alimentosParaModalListaJS = JSON.parse(JSON.stringify(alimentosCompletos)); // Recria para incluir a nova prep
                        
                        displayMessageBox(`Preparação "${htmlspecialchars(nomePrep)}" criada com sucesso!`, false);
                        closeModal(novaPreparacaoModal);
                        if ($('#selection-modal').is(':visible')) {
                            populateMainModalSearchList($('#modal-search').val());
                        }
                    } else {
                        displayMessageBox('Erro ao salvar preparação: ' + (response.message || 'Erro desconhecido.'), false);
                    }
                },
                error: function(jqXHR) { displayMessageBox('Erro de comunicação ao salvar preparação.', false); console.error("Erro AJAX #prep-save:", jqXHR.responseText); },
                complete: function() { $('#prep-save').prop('disabled', false).html('<i class="fas fa-save"></i> Salvar Preparação'); }
            });
        });


        // --- FIM DA LÓGICA DO NOVO MODAL DE FICHA TÉCNICA ---

        function closeModal(modalElement) { modalElement.fadeOut(150, function() { $(this).css('display', 'none'); if (modalElement.is(mainSelectionModal)) { modalCurrentSelections.clear(); $(this).find('#modal-search').val(''); $(this).find('.modal-search-list').empty(); $(this).removeData('targetCell'); } if (modalElement.is(groupChoiceModal)) { $(this).find('#group-choice-options').empty(); $(this).removeData('foodId').removeData('targetCellRef'); } if (modalElement.is(novaPreparacaoModal)) { /* Reset já é feito na abertura */ } }); }
        $(document).on('click', '.modal-close-btn, #modal-cancel, #group-choice-cancel, #prep-cancel', function() { closeModal($(this).closest('.modal-overlay')); });
        $('.modal-overlay').on('click', function(e) { if ($(e.target).is($(this))) closeModal($(this)); });
        $(document).on('keydown', function(e) { if (e.key === "Escape") $('.modal-overlay:visible').each(function(){ closeModal($(this)); }); });
        
        // Função para preparar os dados do cardápio para envio à API de cálculo
        function lerCardapioParaApi() { 
            const cS = getGridStateForSaving(); 
            let hasItems = false; 
            const activeDays = new Set(); 
            diasKeys.forEach(dK => { 
                if (!cS.dias_desativados[dK] && cS.dias[dK]) {
                    Object.values(cS.dias[dK]).forEach(mealItems => { 
                        if (Array.isArray(mealItems) && mealItems.length > 0) { 
                            hasItems = true; 
                            activeDays.add(dK); 
                        } 
                    }); 
                }
            }); 
            
            const faixaKey = cS.faixa_etaria_selecionada; 
            const activeDaysArray = Array.from(activeDays); 
            
            if (!faixaKey) return { error: 'faixa_etaria' }; 
            if (!hasItems || activeDaysArray.length === 0) return { error: 'sem_itens_ativos' }; 
            
            const cardapioApiFormat = {}; 
            activeDaysArray.forEach(dK => { 
                cardapioApiFormat[dK] = {}; 
                if(cS.dias[dK]) {
                    Object.entries(cS.dias[dK]).forEach(([refKey, items]) => { 
                        cardapioApiFormat[dK][refKey] = items.map(item => ({ 
                            id: item.foodId,
                            qty: item.qty, // Este é o peso bruto que o API Calculator espera
                            instanceGroup: item.instanceGroup,
                            is_prep: alimentosCompletos?.[item.foodId]?.isPreparacao ?? false
                        })); 
                    }); 
                }
            }); 
            
            // Prepara as definições de preparações para o API Calculator
            const preparacoesDefs = {};
            for (const foodId in alimentosCompletos) {
                if (alimentosCompletos[foodId].isPreparacao) {
                    // Garante que os ingredientes da preparação tenham 'qty' (peso bruto) e 'fc'
                    // como o API Calculator espera para o cálculo interno
                    preparacoesDefs[foodId] = {
                        nome: alimentosCompletos[foodId].nome,
                        ingredientes: alimentosCompletos[foodId].ingredientes.map(ing => ({
                            foodId: ing.foodId,
                            qty: ing.qty, // Peso bruto do ingrediente na preparação
                            fc: ing.fc // Fator de cocção do ingrediente na preparação
                        }))
                    };
                }
            }

            const finalData = { 
                cardapio: cardapioApiFormat, 
                dias_ativos: activeDaysArray, 
                faixa_etaria: faixaKey, 
                refeicoes_info: cS.refeicoes, 
                meta:{
                    faixa_etaria_texto:$("#faixa-etaria-select option:selected").text().trim(), 
                    datas: cS.datas_dias, 
                    preparacoes_defs: preparacoesDefs, // Inclui as definições completas das preparações
                    dias_keys: diasKeys 
                }
            }; 
            return finalData; 
        }

        function lerResultadosParaExport() { const r = { analise_diaria: {}, media_semanal: {} }; $('#resultados-diarios-table tbody tr').each(function() { const dK = $(this).attr('id').replace('daily-', ''); r.analise_diaria[dK] = {}; $(this).find('td[data-nutrient]').each(function() { r.analise_diaria[dK][$(this).data('nutrient')] = $(this).text(); }); }); $('#weekly-avg td[data-nutrient]').each(function() { r.media_semanal[$(this).data('nutrient')] = $(this).text(); }); return r; }
        function triggerCalculation() { if (!dadosBaseOk) return; clearTimeout(calculationTimeout); calculationTimeout = setTimeout(() => { initializeInstanceGroupCounters(); const dTS = lerCardapioParaApi(); if (dTS && !dTS.error) calcularNutrientes(dTS); else { limparResultados(); if (dTS?.error==='faixa_etaria') showStatus('warning', 'Selecione a faixa etária para calcular os nutrientes.', 'fa-users'); else if (dTS?.error==='sem_itens_ativos') showStatus('info', 'Adicione itens ao cardápio para calcular os nutrientes.', 'fa-info-circle'); else showStatus('info', 'Nenhum dado para calcular. Adicione itens ou selecione a faixa etária.', 'fa-info-circle'); } }, 600); }
        function calcularNutrientes(apiData) { if (requestActive || !dadosBaseOk) return; requestActive = true; showStatus('loading', 'Calculando...', 'fa-spinner fa-spin'); $('#calcular-nutrientes-btn').prop('disabled', true); $.ajax({ url: 'api_calculator.php', method: 'POST', data: { payload: JSON.stringify(apiData) }, dataType: 'json', success: function(response) { if (response && !response.error && response.daily_totals && response.weekly_average) { updateResultsDisplay(response.daily_totals, response.weekly_average); if (response.debug_errors?.length > 0) { showStatus('warning', 'Avisos. Ver console.', 'fa-exclamation-triangle'); console.warn("Avisos API:", response.debug_errors); } else showStatus('success', 'Cálculo OK!', 'fa-check-circle'); } else { const eM = response?.error || 'Inválida.'; limparResultados(); showStatus('error', `Erro ao calcular nutrientes: ${eM}`, 'fa-times-circle'); console.error("Erro API Calc:", response); } }, error: function(j,tS,eT) { console.error("Erro AJAX Calc:",tS,eT,j.responseText); limparResultados(); let d=`Erro ${j.status}: ${eT||tS}`; try{const err=JSON.parse(j.responseText);if(err?.error)d+=` - ${err.error}`; }catch(e){} showStatus('error', `Falha no cálculo: ${d}`, 'fa-server'); }, complete: function() { requestActive=false; $('#calcular-nutrientes-btn').prop('disabled', false); setTimeout(() => { if(!requestActive&&statusMessage.hasClass('loading'))showStatus('info','Pronto.','fa-info-circle');},800);}}); }
        function updateResultsDisplay(dailyTotals, weeklyAverage) { const ATWATER={ptn:4,lpd:9,cho:4}; const formatters={kcal:v=>v!=null&&!isNaN(v)?Math.round(v).toLocaleString('pt-BR'):'0',ptn:v=>v!=null&&!isNaN(v)?v.toFixed(1).replace('.',','):'0,0',lpd:v=>v!=null&&!isNaN(v)?v.toFixed(1).replace('.',','):'0,0',cho:v=>v!=null&&!isNaN(v)?v.toFixed(1).replace('.',','):'0,0',ca:v=>v!=null&&!isNaN(v)?Math.round(v).toLocaleString('pt-BR'):'0',fe:v=>v!=null&&!isNaN(v)?v.toFixed(1).replace('.',','):'0,0',vita:v=>v!=null&&!isNaN(v)?Math.round(v).toLocaleString('pt-BR'):'0',vitc:v=>v!=null&&!isNaN(v)?v.toFixed(1).replace('.',','):'0,0',na:v=>v!=null&&!isNaN(v)?Math.round(v).toLocaleString('pt-BR'):'0', ptn_kcal:v=>v!=null&&!isNaN(v)?v.toFixed(2).replace('.',','):'0,00',lpd_kcal:v=>v!=null&&!isNaN(v)?v.toFixed(2).replace('.',','):'0,00',cho_kcal:v=>v!=null&&!isNaN(v)?v.toFixed(2).replace('.',','):'0,00',ptn_vet:v=>v!=null&&!isNaN(v)&&v>0?Math.round(v)+'%':'-',lpd_vet:v=>v!=null&&!isNaN(v)&&v>0?Math.round(v)+'%':'-',cho_vet:v=>v!=null&&!isNaN(v)&&v>0?Math.round(v)+'%':'-'}; function calcVET(g,tk,af){if(tk>0&&g!=null&&!isNaN(g))return((g*af)/tk)*100;return null;} diasKeys.forEach(dia=>{const r=$(`#daily-${dia}`),dD=dailyTotals[dia]||{},tKD=dD.kcal; ['kcal','ptn','lpd','cho','ca','fe','vita','vitc','na'].forEach(n=>r.find(`td[data-nutrient="${n}"]`).text(formatters[n](dD[n]))); const pK=(dD.ptn!=null&&!isNaN(dD.ptn))?dD.ptn*ATWATER.ptn:null;const lK=(dD.lpd!=null&&!isNaN(dD.lpd))?dD.lpd*ATWATER.lpd:null;const cK=(dD.cho!=null&&!isNaN(dD.cho))?dD.cho*ATWATER.cho:null; r.find('td[data-nutrient="ptn_kcal"]').text(formatters.ptn_kcal(pK)); r.find('td[data-nutrient="lpd_kcal"]').text(formatters.lpd_kcal(lK)); r.find('td[data-nutrient="cho_kcal"]').text(formatters.cho_kcal(cK)); r.find('td[data-nutrient="ptn_vet"]').text(formatters.ptn_vet(calcVET(dD.ptn,tKD,ATWATER.ptn))); r.find('td[data-nutrient="lpd_vet"]').text(formatters.lpd_vet(calcVET(dD.lpd,tKD,ATWATER.lpd))); r.find('td[data-nutrient="cho_vet"]').text(formatters.cho_vet(calcVET(dD.cho,tKD,ATWATER.cho))); }); const aR=$(`#weekly-avg`),wA=weeklyAverage||{},tKA=wA.kcal; ['kcal','ptn','lpd','cho','ca','fe','vita','vitc','na'].forEach(n=>aR.find(`td[data-nutrient="${n}"]`).text(formatters[n](wA[n]))); const pKA=(wA.ptn!=null&&!isNaN(wA.ptn))?wA.ptn*ATWATER.ptn:null;const lKA=(wA.lpd!=null&&!isNaN(wA.lpd))?wA.lpd*ATWATER.lpd:null;const cKA=(wA.cho!=null&&!isNaN(wA.cho))?wA.cho*ATWATER.cho:null; aR.find('td[data-nutrient="ptn_kcal"]').text(formatters.ptn_kcal(pKA)); aR.find('td[data-nutrient="lpd_kcal"]').text(formatters.lpd_kcal(lKA)); aR.find('td[data-nutrient="cho_kcal"]').text(formatters.cho_kcal(cKA)); aR.find('td[data-nutrient="ptn_vet"]').text(formatters.ptn_vet(calcVET(wA.ptn,tKA,ATWATER.ptn))); aR.find('td[data-nutrient="lpd_vet"]').text(formatters.lpd_vet(calcVET(wA.lpd,tKA,ATWATER.lpd))); aR.find('td[data-nutrient="cho_vet"]').text(formatters.cho_vet(calcVET(wA.cho,tKA,ATWATER.cho))); }
        function limparResultados() { $('#resultados-diarios-table tbody tr, #resultados-diarios-table tfoot tr').each(function(){ $(this).find('td[data-nutrient]').each(function(){ const n=$(this).data('nutrient'); let dV='0'; if(['ptn','lpd','cho','fe','vitc'].includes(n))dV='0,0'; else if(n?.includes('_kcal')&&n!=='kcal')dV='0,00'; else if(n?.includes('_vet'))dV='-'; $(this).text(dV);});});}
        $('#calcular-nutrientes-btn').on('click', function(e) { e.preventDefault(); if (!$(this).prop('disabled') && dadosBaseOk) triggerCalculation(); });
        faixaEtariaSelect.on('change', function() { if(!dadosBaseOk)return;const nFK=$(this).val();faixaEtariaSelecionada=nFK;updateReferenciaTable(nFK);if(nFK){updateGridQuantitiesForAgeGroup(nFK);clearSelectionAndClipboard();clearPasteTarget();markGridChanged();triggerCalculation();}else{limparResultados();showStatus('warning','Selecione faixa etária para visualizar referências e calcular nutrientes.','fa-users');}});
        $('#limpar-cardapio-btn').on('click', function(e) { e.preventDefault(); if (!dadosBaseOk||$(this).prop('disabled'))return; displayMessageBox("Você tem certeza que deseja limpar TODOS os itens do cardápio? Esta ação não pode ser desfeita.", true, (confirmed) => { if (confirmed) { diasKeys.forEach(dK=>{if(cardapioAtual[dK])Object.keys(cardapioAtual[dK]).forEach(rK=>{cardapioAtual[dK][rK]=[];});});datasDias=Object.fromEntries(diasKeys.map(k=>[k,'']));diasDesativados=Object.fromEntries(diasKeys.map(k=>[k,false])); renderCardapioGrid(); initializeInstanceGroupCounters(); limparResultados(); clearSelectionAndClipboard(); clearPasteTarget(); markGridChanged();showStatus('info','Cardápio limpo com sucesso! Lembre-se de salvar as alterações.','fa-eraser'); } }); });
        $(document).on('click', '.add-item-cell-btn', function(e) { e.stopPropagation(); if(!dadosBaseOk)return; openMainSelectionModal($(this).closest('td.editable-cell')); });
        $('#exportar-xlsx-btn').on('click', function(e){ e.preventDefault(); if(!dadosBaseOk||$(this).prop('disabled'))return;const dC=lerCardapioParaApi(); if(dC&&!dC.error){const dR=lerResultadosParaExport();const dTE={...dC,resultados_display:dR,projeto_nome:$('#export-project-name-input').val()};$('#export-data-input').val(JSON.stringify(dTE));$('#export-xlsx-form').submit();}else displayMessageBox("Não é possível exportar: o cardápio está vazio ou a faixa etária não foi selecionada.");});
        $(document).on('change', '.dia-data-input', function() { const dK=$(this).data('dia'), nD=$(this).val().trim(); if(datasDias[dK]!==nD){datasDias[dK]=nD;markGridChanged();}});
        $(document).on('click', '.toggle-feriado-btn', function() { const b=$(this),dK=b.data('dia'),th=$tableHead.find(`th[data-dia-col="${dK}"]`),c=$tableBody.find(`td.editable-cell[data-dia="${dK}"]`),dN=diasNomesMap[dK]||dK.toUpperCase(),eD=th.hasClass('dia-desativado'); if(eD){diasDesativados[dK]=false;b.removeClass('active').attr('title',`Ativar ${dN}`).find('i').removeClass('fa-toggle-on').addClass('fa-toggle-off');th.removeClass('dia-desativado');c.removeClass('dia-desativado');}else{displayMessageBox(`Marcar ${dN} como inativo (feriado)? Todos os itens e cálculos deste dia serão ignorados.`, true, (confirmed) => { if (confirmed) { diasDesativados[dK]=true;b.addClass('active').attr('title',`Ativar ${dN}`).find('i').removeClass('fa-toggle-off').addClass('fa-toggle-on');th.addClass('dia-desativado');c.addClass('dia-desativado');c.find('.item-selecionado').removeClass('item-selecionado');if(selectedItemsCollection.closest('td').data('dia')===dK)clearSelectionAndClipboard();if(targetCellForPaste?.data('dia')===dK)clearPasteTarget(); markGridChanged();triggerCalculation();}}); return;} markGridChanged();triggerCalculation();});
        $(document).on('click', '.selected-items-list li:not(.placeholder)', function(e) { if (!dadosBaseOk || $(e.target).closest('.item-actions, .item-qty-input-inline').length > 0 || $(this).closest('td.editable-cell').hasClass('dia-desativado')) return; const cL=$(this);lastClickedLi=cL; if (e.ctrlKey || e.metaKey) { cL.toggleClass('item-selecionado'); selectedItemsCollection=$('.selected-items-list li.item-selecionado'); } else { if(selectedItemsCollection.length===1&&selectedItemsCollection.is(cL)){cL.removeClass('item-selecionado');selectedItemsCollection=$();}else{selectedItemsCollection.removeClass('item-selecionado');cL.addClass('item-selecionado');selectedItemsCollection=cL;} } clearPasteTarget(); updateManipulationButtons(); });
        $(document).on('click', 'td.editable-cell', function(e) { const cell=$(this); if(cell.hasClass('dia-desativado'))return; const isCellClick = $(e.target).is(cell); const clickedOnItemOrButton = $(e.target).closest('li:not(.placeholder), .add-item-cell-btn').length > 0; if (internalClipboard.itemsData.length > 0 && (isCellClick || $(e.target).is(cell.find('.placeholder')))) { if (!targetCellForPaste || !targetCellForPaste.is(cell)) { $('.target-cell-for-paste').removeClass('target-cell-for-paste'); cell.addClass('target-cell-for-paste'); targetCellForPaste = cell; updateManipulationButtons();}} else if (!clickedOnItemOrButton) { clearPasteTarget(); updateManipulationButtons();}});
        $(document).on('dblclick', 'td.editable-cell', function(e) { const cell=$(this); if(cell.hasClass('dia-desativado')||!$(e.target).is(cell))return; if(selectedItemsCollection.length>0){selectedItemsCollection.removeClass('item-selecionado');selectedItemsCollection=$();} clearPasteTarget(); const itemsInCell = cell.find('ul.selected-items-list li:not(.placeholder)'); if(itemsInCell.length>0){itemsInCell.addClass('item-selecionado');selectedItemsCollection=itemsInCell;} updateManipulationButtons();});
        function clearSelectionAndClipboard() { if(selectedItemsCollection.length>0){selectedItemsCollection.removeClass('item-selecionado');selectedItemsCollection=$();} internalClipboard={type:null,itemsData:[]}; clearPasteTarget(); updateManipulationButtons();}
        function clearPasteTarget() { if(targetCellForPaste){targetCellForPaste.removeClass('target-cell-for-paste');targetCellForPaste=null;} $('#item-paste-btn').prop('disabled',true);}
        function updateManipulationButtons() { const hasS=selectedItemsCollection.length>0; const canP=internalClipboard.itemsData.length>0&&targetCellForPaste!==null&&!targetCellForPaste.hasClass('dia-desativado'); $('#item-copy-btn').prop('disabled',!hasS); $('#item-cut-btn').prop('disabled',!hasS); $('#item-delete-btn').prop('disabled',!hasS); $('#item-paste-btn').prop('disabled',!canP);}
        $('#item-delete-btn').on('click',function(){if(!dadosBaseOk||selectedItemsCollection.length===0||$(this).prop('disabled'))return;const cnt=selectedItemsCollection.length;const fIN=selectedItemsCollection.first().find('.item-name').contents().filter(function(){return this.nodeType===3;}).text().trim();displayMessageBox(cnt===1?`Remover "${fIN}"?`:`Remover ${cnt} itens?`, true, (confirmed) => { if (confirmed) { let iERC=0;const aC=new Map();const iTR=selectedItemsCollection.toArray().map(el=>$(el));iTR.forEach(($liE)=>{const pId=$liE.data('placement-id'),cell=$liE.closest('td.editable-cell'),dK=cell.data('dia'),rK=cell.closest('tr').data('refeicao-key'),cK=`${dK}-${rK}`;if(removeItemFromCell($liE)){iERC++;if(!aC.has(cK))aC.set(cK,cell);}});if(iERC>0){aC.forEach((cE,key)=>{const dK=key.split('-')[0],rK=key.split('-')[1],cID=cardapioAtual[dK]?.[rK]||[];updateCellDisplay(cE,cID);});initializeInstanceGroupCounters();triggerCalculation();}selectedItemsCollection=$();updateManipulationButtons();} });});
        $tableBody.on('click','.item-remove-btn',function(e){e.stopPropagation();if(!dadosBaseOk)return;const $liR=$(this).closest('li');if(!$liR.length||$liR.hasClass('placeholder'))return;displayMessageBox(`Remover o item "${$liR.find('.item-name').text().trim()}"?`, true, (confirmed) => { if (confirmed) { if(removeItemFromCell($liR)){initializeInstanceGroupCounters();triggerCalculation();}} });});
        $('#item-copy-btn').on('click',function(){if(!dadosBaseOk||selectedItemsCollection.length===0||$(this).prop('disabled'))return;clearPasteTarget();internalClipboard={type:'copy',itemsData:[]};let cN=[];selectedItemsCollection.each(function(){const li=$(this);const iD={foodId:li.data('food-id').toString(),qty:parseInt(li.find('.item-qty-input-inline').val(),10)||100,instanceGroup:li.data('instance-group'),placementId:li.data('placement-id')};internalClipboard.itemsData.push(iD);cN.push(alimentosIdNomeMap[iD.foodId]||`ID ${iD.foodId}`);li.css('transition','none').addClass('item-selecionado').css('background-color','var(--color-info-light)');setTimeout(()=>{li.css('transition','').css('background-color','');},350);});const sM=internalClipboard.itemsData.length===1?`"${cN[0]}" copiado.`:`${internalClipboard.itemsData.length} itens copiados.`;showStatus('info',sM,'fa-copy');updateManipulationButtons();});
        $('#item-cut-btn').on('click',function(){if(!dadosBaseOk||selectedItemsCollection.length===0||$(this).prop('disabled'))return;internalClipboard={type:'cut',itemsData:[]};let cN=[],iSR=0;selectedItemsCollection.each(function(){const li=$(this);const iD={foodId:li.data('food-id').toString(),qty:parseInt(li.find('.item-qty-input-inline').val(),10)||100,instanceGroup:li.data('instance-group'),placementId:li.data('placement-id')};internalClipboard.itemsData.push(iD);cN.push(alimentosIdNomeMap[iD.foodId]||`ID ${iD.foodId}`);});const iTR=selectedItemsCollection.toArray();iTR.forEach(lE=>{if(removeItemFromCell($(lE)))iSR++;});if(iSR>0){const sM=iSR===1?`"${cN[0]}" recortado.`:`${iSR} itens recortados.`;showStatus('info',sM,'fa-cut');initializeInstanceGroupCounters();triggerCalculation();}else{internalClipboard={type:null,itemsData:[]};showStatus('error','Erro ao recortar.','fa-times-circle');}selectedItemsCollection=$();updateManipulationButtons();});
        $('#item-paste-btn').on('click',function(){if(!dadosBaseOk||internalClipboard.itemsData.length===0||!targetCellForPaste||targetCellForPaste.hasClass('dia-desativado')||$(this).prop('disabled'))return;const tC=targetCellForPaste,tDK=tC.data('dia'),tRK=tC.closest('tr').data('refeicao-key');let iPC=0,nP=[];internalClipboard.itemsData.forEach(iTP=>{const fId=iTP.foodId,oIG=iTP.instanceGroup,oQ=iTP.qty;const eIT=checkIfFoodIdExistsInCell(fId,tDK,tRK);let nIG;if(eIT)nIG=getNextInstanceGroupNumber(fId);else nIG=oIG;addItemToCell(tC,fId,nIG,oQ);iPC++;nP.push(alimentosIdNomeMap[fId]||`ID ${fId}`);});if(iPC>0){const fPN=nP[0],sM=iPC===1?`"${fPN}" colado.`:`${iPC} itens colados.`;showStatus('success',sM,'fa-paste');if(internalClipboard.type==='cut')internalClipboard={type:null,itemsData:[]};clearPasteTarget();updateManipulationButtons();triggerCalculation();markGridChanged();}});
        function checkIfFoodIdExistsInCell(foodId, diaKey, refKey) { if (cardapioAtual[diaKey]?.[refKey] && Array.isArray(cardapioAtual[diaKey][refKey])) return cardapioAtual[diaKey][refKey].some(item => item?.foodId === foodId); return false; }
        function getHighestInstanceGroup(foodId) { return foodInstanceGroupCounters[foodId] || 1; }
        $(document).on('keydown',function(e){if($(e.target).is('input,textarea,select')||$('.modal-overlay:visible').length>0)return;const iC=e.ctrlKey||e.metaKey;if(iC&&e.key.toLowerCase()==='c'){if(selectedItemsCollection.length>0&&!$('#item-copy-btn').prop('disabled')){e.preventDefault();$('#item-copy-btn').click();}}else if(iC&&e.key.toLowerCase()==='x'){if(selectedItemsCollection.length>0&&!$('#item-cut-btn').prop('disabled')){e.preventDefault();$('#item-cut-btn').click();}}else if(iC&&e.key.toLowerCase()==='v'){if(internalClipboard.itemsData.length>0&&targetCellForPaste&&!$('#item-paste-btn').prop('disabled')){e.preventDefault();$('#item-paste-btn').click();}}else if(e.key==='Delete'||e.key==='Backspace'){if(selectedItemsCollection.length>0&&!$('#item-delete-btn').prop('disabled')){e.preventDefault();$('#item-delete-btn').click();}}});
        $(document).on('click', '.remove-row-btn', function(){ const row=$(this).closest('tr'),refKey=row.data('refeicao-key'),refLabel=row.find('.refeicao-label .editable-label').text().trim(); if(Object.keys(refeicoesLayout).length<=1){displayMessageBox("Não é possível remover a última refeição do cardápio.");return;} displayMessageBox(`Remover a refeição "${refLabel}" e todos os itens dela?`, true, (confirmed) => { if (confirmed) { if(refeicoesLayout[refKey])delete refeicoesLayout[refKey];diasKeys.forEach(dK=>{if(cardapioAtual?.[dK]?.[refKey])delete cardapioAtual[dK][refKey];});row.fadeOut(300,function(){$(this).remove();updateRemoveButtonsVisibility();initializeInstanceGroupCounters();triggerCalculation();markGridChanged();});} });});
        $(document).on('click', '.label-cell span.editable-label', function(){ const s=$(this),i=s.siblings('.label-input'); if(s.hasClass('editing')||i.is(':visible'))return; s.closest('tr').find('.label-input.editing').each(function(){saveLabelEdit($(this));}); s.addClass('editing'); i.val(s.text().trim()).addClass('editing').show().focus().select(); });
        $(document).on('blur', '.label-input.editing', function(){ saveLabelEdit($(this)); });
        function inicializarInterface() { renderCardapioGrid(); initializeInstanceGroupCounters(); updateManipulationButtons(); limparResultados(); updateReferenciaTable(faixaEtariaSelecionada); const iLM = <?php echo json_encode($load_error_message); ?>; if(iLM&&(iLM.includes('corrompido')||iLM.includes('inválida')||iLM.includes('inesperado')))showStatus('warning',iLM); else if(faixaEtariaSelecionada){let hI=false;if(typeof cardapioAtual==='object'&&cardapioAtual!==null)Object.values(cardapioAtual).forEach(dia=>{if(typeof dia==='object'&&dia!==null)Object.values(dia).forEach(ref=>{if(Array.isArray(ref)&&ref.length>0)hI=true;});}); if(hI){showStatus('info','Cardápio carregado. Calculando...','fa-sync');triggerCalculation();}else showStatus('info','Cardápio pronto. Comece a adicionar itens!','fa-edit');}else if(!faixaEtariaSelecionada)showStatus('warning','Selecione a faixa etária para visualizar referências e calcular nutrientes.','fa-users'); gridHasChanged=false;saveStatusSpan.text('');$('#save-project-btn').removeClass('unsaved saved');}
        function updateReferenciaTable(faixaKey) {const container=$('#referencia-pnae-container'),h3E=$('#referencia-pnae-title');container.empty();const dR=referenciaValoresPnae[faixaKey];function fmtV(v,k=''){if(v==null||typeof v==='undefined'||v==='')return'-';let sV=v.toString();if(sV==='-')return'-';if(sV==='a')return'a';if(typeof v==='number'){if(isNaN(v))return'-';if(k.includes('_g_')||['fe_mg','vitc_mg'].includes(k)){if(v%1!==0)return v.toFixed(1).replace('.',',');}if(k.includes('proteina_g_')||k.includes('lipidio_g_')||k.includes('carboidrato_g_')){if(v%1!==0)return v.toFixed(1).replace('.',',');}return Math.round(v).toLocaleString('pt-BR');}return htmlspecialchars(sV);}const tSB="Val. Ref. PNAE";if(dR&&dR.faixa)h3E.text(`${tSB} - ${dR.faixa}`);else h3E.text(tSB);if(dR&&dR.refs?.length>0){const uCFV=['bercario','creche'].includes(faixaKey);let tH=`<table class="cardapio-montagem-table" id="referencia-pnae-table" style="width:100%;table-layout:fixed;"><thead><tr><th rowspan="2" style="vertical-align:bottom;width:6.82%;">Ref.<br>PNAE</th><th rowspan="2" style="vertical-align:middle;width:5%;">Nº ref.</th><th rowspan="2" style="vertical-align:middle;width:9%;">Kcal</th><th colspan="3" style="text-align:center;width:12.27%;">PTN (G)</th><th colspan="3" style="text-align:center;width:12.27%;">LIP (G)</th><th colspan="3" style="text-align:center;width:12.27%;">CHO (G)</th><th rowspan="2" style="vertical-align:middle;width:8%;">Ca(mg)</th><th rowspan="2" style="vertical-align:middle;width:8%;">Fe(mg)</th><th rowspan="2" style="vertical-align:middle;width:8%;">Vit.A(µg)</th><th rowspan="2" style="vertical-align:middle;width:8%;">Vit.C(mg)</th><th rowspan="2" style="vertical-align:middle;width:8%;">Na(mg)</th></tr><tr><th style="width:5.49%;">10%VET</th><th style="1.29%;"></th><th style="width:5.49%;">15%VET</th><th style="5.49%;">15%VET</th><th style="1.29%;"></th><th style="width:5.49%;">30%VET</th><th style="5.49%;">55%VET</th><th style="1.29%;"></th><th style="width:5.49%;">65%VET</th></tr></thead><tbody>`;dR.refs.forEach(rR=>{const nT=htmlspecialchars(rR.nivel.replace("das necessidades nutricionais/dia","do GET"));tH+=`<tr><td style="text-align:left;vertical-align:middle;">${nT}</td><td>${htmlspecialchars(rR.n_ref||'-')}</td><td>${fmtV(rR.valores?.energia,'energia')}</td><td>${fmtV(rR.valores?.proteina_g_10,'proteina_g_10')}</td><td>a</td><td>${fmtV(rR.valores?.proteina_g_15,'proteina_g_15')}</td><td>${fmtV(rR.valores?.lipidio_g_15,'lipidio_g_15')}</td><td>a</td><td>${fmtV(rR.valores?.lipidio_g_30,'lipidio_g_30')}</td><td>${fmtV(rR.valores?.carboidrato_g_55,'carboidrato_g_55')}</td><td>a</td><td>${fmtV(rR.valores?.carboidrato_g_65,'carboidrato_g_65')}</td><td>${uCFV?fmtV(rR.valores?.ca_mg,'ca_mg'):'-'}</td><td>${uCFV?fmtV(rR.valores?.fe_mg,'fe_mg'):'-'}</td><td>${uCFV?fmtV(rR.valores?.vita_ug,'vita_ug'):'-'}</td><td>${uCFV?fmtV(rR.valores?.vitc_mg,'vitc_mg'):'-'}</td><td>${!uCFV?fmtV(rR.valores?.na_mg,'na_mg'):'-'}</td></tr>`;});tH+='</tbody></table>';container.html(tH);}else{container.html(`<p style="text-align:center;color:var(--color-text-light);padding:15px;background-color:#f8f9fa;border:1px solid var(--color-light-border);border-radius:var(--border-radius);">Selecione uma Faixa Etária acima para visualizar os valores de referência.</p>`);}}
        
        // A função displayMessageBox agora está em global.js e é acessível globalmente
        // function displayMessageBox(message, isConfirm = false, callback = null) { /* ... */ }

        // A função showStatus agora está em global.js e é acessível globalmente
        // function showStatus(type, message, iconClass = 'fa-info-circle') { /* ... */ }

        // O restante das funções globais (sanitizeString, generatePlacementId, etc.) são acessíveis via window.
        // As constantes $sidebar, $sidebarToggleButton, $sidebarNav são definidas em global.js e não precisam ser redefinidas aqui.
        // As funções handlePlatformLink e checkSidebarToggleVisibility também estão em global.js.

        inicializarInterface();
    });
    //]]>
    </script>
</body>
</html>
