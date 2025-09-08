<?php
// cardapios_semanais.php - Página para Elaboração de Cardápios Semanais
// CHECKPOINT PHP: Início (v_layout_final_com_correcoes_v2)

$logged_username = $_SESSION['username'] ?? 'Nutri Exemplo'; 

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_log("--- Início cardapios_semanais.php v_layout_final_com_correcoes_v2 " . date('Y-m-d H:i:s') . " ---");

$page_title = "Elaborar Cardápio Semanal";
$tipo_cardapio_selecionado = "FUNDAMENTAL PARCIAL 6-10 ANOS";
$mes_selecionado = "MAIO";
$ano_selecionado = "2025";

function format_for_display(?string $text): string {
    if ($text === null) return '';
    if (!is_string($text)) {
        error_log("format_for_display tipo inesperado: " . gettype($text));
        return '';
    }
    return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
}

$semana_base_modelo = [
    'cabecalho_superior' => [
        'secretaria_editavel' => 'SECRETARIA MUNICIPAL DE EDUCAÇÃO DE UBERLÂNDIA-MG',
        'programa_pnae_editavel' => 'PROGRAMA NACIONAL DE ALIMENTAÇÃO ESCOLAR - PNAE / PROGRAMA MUNICIPAL DE ALIMENTAÇÃO ESCOLAR - PMAE',
        'titulo_cardapio_editavel' => "CARDÁPIO DO ENSINO FUNDAMENTAL - PERÍODO PARCIAL",
        'zona_editavel' => 'ZONA URBANA',
        'faixa_etaria_editavel' => "FAIXA ETÁRIA (6 a 10 anos)",
        // 'mes_ano_extenso_editavel' será gerado dinamicamente no loop
    ],
    'dias' => [ // Chaves correspondem ao que será usado para buscar os pratos
        'segunda' => ['dia_semana_nome_fixo' => 'Segunda-feira', 'data_editavel' => 'XX/XX/XX', 'principal' => "Arroz branco\nFeijão de caldo\nFarofinha de ovo\nCabotiá cozida", 'sobremesa' => ''],
        'terca'   => ['dia_semana_nome_fixo' => 'Terça-feira',   'data_editavel' => 'XX/XX/XX', 'principal' => "Arroz branco\nFeijão de caldo\nFrango (peito) ao molho com batata doce\nSalada de repolho", 'sobremesa' => ''],
        'quarta'  => ['dia_semana_nome_fixo' => 'Quarta-feira',  'data_editavel' => 'XX/XX/XX', 'principal' => "Sopa de macarrão (parafuso) com carne bovina (pedaço) e batata", 'sobremesa' => 'Banana'],
        'quinta'  => ['dia_semana_nome_fixo' => 'Quinta-feira',  'data_editavel' => 'XX/XX/XX', 'principal' => "Galinhada (coxa e sobrecoxa)\nTutu de feijão\nSalada de tomate e alface", 'sobremesa' => ''],
        'sexta'   => ['dia_semana_nome_fixo' => 'Sexta-feira',   'data_editavel' => 'XX/XX/XX', 'principal' => "Arroz com pernil\nFeijão de caldo\nSalada de couve", 'sobremesa' => 'Mexerica']
    ],
    'refeicoes_linhas_map' => [ // Define a ordem e os nomes das linhas de refeição na tabela
        'principal' => ['nome_fixo_tabela' => 'PRINCIPAL', 'horario_editavel' => "Manhã 10:30\nTarde 16:00"],
    ],
    'sobremesa_linha_nome_fixo_tabela' => 'SOBREMESA',
    'composicao_nutricional_titulo_lateral_editavel' => "Composição\nnutricional\n(Média Semanal)",
    'nutrientes_map' => [ // Usado para gerar a seção de nutrientes dinamicamente
        'energia' => ['rotulo_th_fixo' => 'Energia (Kcal)', 'valor_editavel' => '393 kcal', 'is_multilinha' => false],
        'cho'     => ['rotulo_th_fixo' => 'CHO',             'valor_editavel' => "55 g\n59%",  'is_multilinha' => true],
        'ptn'     => ['rotulo_th_fixo' => 'PTN',             'valor_editavel' => "16 g\n15%",  'is_multilinha' => true],
        'lpd'     => ['rotulo_th_fixo' => 'LPD',             'valor_editavel' => "12 g\n27%",  'is_multilinha' => true]
    ],
    'orientacoes_titulo_fixo' => 'Orientações:',
    'orientacoes_texto_editavel' => "Ao receber os hortifrútis, reserve aqueles que estão no cardápio nas preparações de segunda-feira e da terça-feira da semana seguinte. Os hortifrútis estão sujeitos a mudanças nas entregas, devido a alterações climáticas e outros motivos, podendo impactar no cardápio, neste caso, é recomendado que façam substituições nos hortifrútis.\nAs escolas que não dispõem de forno para as preparações devem adaptar para fazê-las cozidas.\nO orégano e o açafrão podem ser utilizado nas preparações, mesmo que não esteja indicado no cardápio.\nDe acordo com a resolução nº 6 de 8 de maio de 2020 a oferta de frutas para o ensino fundamental parcial é de no mínimo 2 dias da semana.\nAs receitas estão disponíveis nos livros de receitas do PMAE ou enviadas em anexo junto com o cardápio.",
    'elaborado_por_titulo_fixo' => 'ELABORADO POR:',
    'elaborado_por_texto_editavel' => "Paula Alvares Borges Ferreira CRN9 23537\nJuliana Freitas Chiareto CRN9 21711\nNutricionistas QT",
    'revisado_por_titulo_fixo' => 'REVISADO POR:',
    'revisado_por_texto_editavel' => "Geise de Castro Fonseca CRN9 1590\nNutricionista RT",
    'nota_rodape_editavel' => 'CARDÁPIO SUJEITO A ALTERAÇÕES DE ACORDO COM A DISPONIBILIDADE DOS ALIMENTOS.'
];

$cardapios_do_mes_para_exibir = [];
$datas_ph_semanais = [
    ['05/05', '06/05', '07/05', '08/05', '09/05'], ['12/05', '13/05', '14/05', '15/05', '16/05'],
    ['19/05', '20/05', '21/05', '22/05', '23/05'], ['26/05', '27/05', '28/05', '29/05', '30/05']
];

for ($i = 0; $i < 4; $i++) {
    $semana_corrente = $semana_base_modelo;
    $semana_corrente['semana_numero'] = $i + 1;
    $semana_corrente['cabecalho_superior']['mes_ano_extenso_editavel'] = strtoupper($mes_selecionado) . " " . $ano_selecionado . " - SEMANA " . ($i + 1);
    $chaves_dias = array_keys($semana_corrente['dias']);
    if(isset($datas_ph_semanais[$i])){
        foreach($chaves_dias as $idx_dia_loop => $key_dia_loop) {
            if(isset($datas_ph_semanais[$i][$idx_dia_loop])) {
                 $semana_corrente['dias'][$key_dia_loop]['data_editavel'] = $datas_ph_semanais[$i][$idx_dia_loop] . "/" . substr($ano_selecionado, -2);
            }
        }
    }
    $cardapios_do_mes_para_exibir[] = $semana_corrente;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"> <!-- Assegure que esta é a primeira tag no <head> -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title . " - " . $tipo_cardapio_selecionado); ?></title>
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Arial:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* CHECKPOINT CSS: Início (v_layout_excel_final_com_ajustes_finais) */
        :root {
            --cor-fundo-cabecalho-claro: #DDEBF7; 
            --cor-fundo-cabecalho-escuro: #B4C6E7;
            --cor-borda-tabela: #000000; --cor-texto-padrao: #000000;
            --tam-fonte-corpo: 10pt;
            --tam-fonte-cabecalho-geral: 10pt;
            --tam-fonte-th-tabela: 9pt; 
            --familia-fonte-padrao: Arial, sans-serif;
            --primary-color: #005A9C; 
        }
        body { font-family: var(--familia-fonte-padrao); font-size: var(--tam-fonte-corpo); margin: 0; padding: 5mm; background-color: #cccccc; color: var(--cor-texto-padrao); -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
        .pagina-cardapio { background-color: white; padding:0; width: 297mm; height: 208mm; margin: 10mm auto; box-shadow: 0 0 8px rgba(0,0,0,0.2); border: 0.5pt solid #505050; overflow: hidden; page-break-after: always; display: flex; flex-direction: column; }
        .pagina-cardapio:last-child { page-break-after: auto; }

        .cardapio-semanal-container { border: 1.5pt solid var(--cor-borda-tabela); height: 100%; display: flex; flex-direction: column; }
        
        .cabecalho-geral { background-color: var(--cor-fundo-cabecalho-claro); padding: 1px 4px; text-align: center; border-bottom: 0.75pt solid var(--cor-borda-tabela); flex-shrink: 0; }
        .cabecalho-geral div { font-weight: bold; font-size: var(--tam-fonte-cabecalho-geral); margin:0; padding: 0; line-height: 1.18; /* Aumentar um pouco entrelinha */ text-transform: uppercase; }
        
        .tabela-cardapio-wrapper { flex-grow: 1; display: flex; flex-direction: column; overflow: hidden; }
        
        .tabela-cardapio { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .tabela-cardapio th, .tabela-cardapio td { border: 0.75pt solid var(--cor-borda-tabela); padding: 1px 3px; text-align: center; vertical-align: middle; word-wrap: break-word; font-size: 9.5pt; line-height: 1.12; /* Ajuste fino */ }
        .tabela-cardapio th { background-color: var(--cor-fundo-cabecalho-escuro); font-weight: bold; font-size: var(--tam-fonte-th-tabela); }
        
        .col-refeicao  { width: 10%; font-weight: bold !important; } 
        .col-horario   { width: 9%; } 
        .col-dia       { width: 12%; } /* Reduzido para dar espaço à composição */
        .col-comp-titulo   { width: 12%; background-color: var(--cor-fundo-cabecalho-claro); font-weight:bold; } /* Largura da coluna para o título "Composição" */
        .col-comp-nutri-rotulo { width: 4.5%; background-color: var(--cor-fundo-cabecalho-escuro); font-weight:bold; font-size: 8pt;} 
        .col-comp-nutri-valor  { width: 4.5%; background-color: var(--white-color); }

        .tabela-cardapio thead th > div { line-height: 1.1; font-size: 8pt; font-weight:bold; padding:0.5px 0;}
        .tabela-cardapio thead th > div.editavel { font-weight:normal; font-size: 8pt; }

        .celula-pratos, .celula-sobremesa, .celula-editavel-multilinha { text-align: left !important; vertical-align: top !important; white-space: pre-wrap; min-height: 36px; padding: 1.5px 2.5px; line-height:1.1; font-size: 9pt;} /* Fonte ajustada */
        .celula-horario { text-align: center !important; white-space: pre-wrap; vertical-align: middle !important; font-size: 9pt;} 
        td.col-refeicao > div { font-weight: bold; text-align: center !important; vertical-align: middle !important;} 
        
        .celula-editavel { text-align: center !important; vertical-align: middle !important; white-space: pre-wrap; padding: 1.5px 2.5px; font-size: 9pt; cursor:pointer; }
        .celula-editavel-multilinha.comp-valor { text-align: center !important; } /* Para g/perc ficarem centralizados */
        
        .editavel:hover { background-color: #f0f8ff !important; }
        
        .editor-inline { width: 100%; border: 1px dashed var(--primary-color); padding: 1.5px; font: inherit; line-height: inherit; box-sizing: border-box; background-color: #fff !important; }
        textarea.editor-inline { min-height: 34px; resize: none; overflow-y: hidden; white-space: pre-wrap; }
        input.editor-inline { height: 100%; white-space: normal; text-align: inherit; padding:0 2px; }

        /* Rodapé */
        .secao-rodape { padding: 2.5px; border-top: 1.5pt solid var(--cor-borda-tabela); font-size: 7.5pt; line-height: 1.05; /* Reduzido */ margin-top: auto; flex-shrink:0; }
        .rodape-flex-container { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5px; }
        .orientacoes-container { flex-basis: 62%; padding-right: 4px; } 
        .assinaturas-wrapper { flex-basis: 36%; display: flex; flex-direction: column; gap: 1px;}
        
        .titulo-rodape { font-weight: bold; display: block; margin-bottom: 0px; text-align: left; font-size:7.5pt; }
        .orientacoes-container .celula-editavel-multilinha { text-align: left; min-height: 35px; padding: 1px; line-height: 1.05; font-size: 6.5pt;}
        
        .assinatura-bloco { text-align: center; background-color: var(--cor-fundo-cabecalho-claro); border: 0.75pt solid var(--cor-borda-tabela); padding: 1px; }
        .assinatura-bloco .titulo-rodape { text-align: center; font-weight:bold; font-size:7pt; }
        .assinatura-bloco .texto-assinatura { white-space: pre-wrap; margin-top: 0.5px; min-height: 15px; line-height: 1.05; text-align: center; font-size: 7pt;}
        
        .nota-final-container { text-align: center; font-weight: bold; text-transform: uppercase; margin-top: 2px; padding: 1.5px; font-size: 7.5pt; border: 0.75pt solid var(--cor-borda-tabela); background-color:var(--cor-fundo-cabecalho-claro)}

        @media print { /* ... (estilos de impressão, geralmente precisam de menos padding e fontes menores) ... */ }
        /* CHECKPOINT CSS: Fim dos estilos */
    </style>
</head>
<body>
    <!-- ... (Botões "Imprimir" e "Salvar" - Sem mudanças) ... -->
    <div class="no-print" style="padding: 10px; text-align: center; background-color: #ddd; margin-bottom:10px;">
        <button onclick="window.print();">Imprimir Cardápio</button>
        <button id="btnSalvarCardapio" style="margin-left: 10px;">Salvar Alterações (Não funcional)</button>
        <span style="margin-left: 20px;">Exibindo: <?php echo htmlspecialchars($tipo_cardapio_selecionado . " - " . $mes_selecionado . "/" . $ano_selecionado); ?></span>
    </div>

    <?php foreach ($cardapios_do_mes_para_exibir as $indice_semana => $cardapio_semanal_item): ?>
    <div class="pagina-cardapio" id="semana-<?php echo $cardapio_semanal_item['semana_numero']; ?>">
        <div class="cardapio-semanal-container">
            <div class="cabecalho-geral">
                <?php foreach($cardapio_semanal_item['cabecalho_superior'] as $key_cab => $texto_cab): ?>
                    <div class="editavel" data-semana="<?php echo $cardapio_semanal_item['semana_numero']; ?>" data-path="cabecalho_superior.<?php echo $key_cab; ?>">
                        <?php echo format_for_display($texto_cab); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="tabela-cardapio-wrapper"> 
                <table class="tabela-cardapio">
                    <thead>
                        <tr>
                            <th class="col-refeicao">REFEIÇÃO</th>
                            <th class="col-horario">SUGESTÃO DE HORÁRIO</th>
                            <?php foreach (array_keys($cardapio_semanal_item['dias']) as $key_dia): 
                                $dia_info = $cardapio_semanal_item['dias'][$key_dia]; ?>
                                <th class="col-dia">
                                    <div class="editavel celula-editavel" data-semana="<?php echo $cardapio_semanal_item['semana_numero']; ?>" data-path="dias.<?php echo $key_dia; ?>.data_editavel"><?php echo format_for_display($dia_info['data_editavel']); ?></div>
                                    <div><?php echo format_for_display($dia_info['dia_semana_nome_fixo']); ?></div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cardapio_semanal_item['refeicoes_linhas_map'] as $key_refeicao_map => $refeicao_info_map): ?>
                        <tr>
                            <td class="col-refeicao"><div><?php echo format_for_display($refeicao_info_map['nome_fixo']); ?></div></td>
                            <td class="col-horario celula-horario celula-editavel-multilinha editavel" data-semana="<?php echo $cardapio_semanal_item['semana_numero']; ?>" data-path="refeicoes_linhas_map.<?php echo $key_refeicao_map; ?>.horario_editavel"><?php echo format_for_display($refeicao_info_map['horario_editavel']); ?></td>
                            <?php foreach ($cardapio_semanal_item['dias'] as $key_dia => $dia_info): ?>
                                <td class="celula-pratos editavel" data-semana="<?php echo $cardapio_semanal_item['semana_numero']; ?>" data-path="dias.<?php echo $key_dia; ?>.<?php echo $key_refeicao_map; ?>">
                                    <?php echo format_for_display($dia_info[$key_refeicao_map] ?? ''); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                        
                        <tr>
                            <td class="col-refeicao"><div><?php echo htmlspecialchars($cardapio_semanal_item['sobremesa_linha_nome_fixo']); ?></div></td>
                            <td class="col-horario"></td> 
                            <?php foreach ($cardapio_semanal_item['dias'] as $key_dia => $dia_info): ?>
                                <td class="celula-sobremesa editavel" data-semana="<?php echo $cardapio_semanal_item['semana_numero']; ?>" data-path="dias.<?php echo $key_dia; ?>.sobremesa">
                                    <?php echo format_for_display($dia_info['sobremesa'] ?? ''); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- LINHA PARA TÍTULO "COMPOSIÇÃO" E CABEÇALHOS DE NUTRIENTES -->
                        <tr>
                            <td class="col-comp-titulo-lateral celula-editavel-multilinha editavel" data-semana="<?php echo $cardapio_semanal_item['semana_numero']; ?>" data-path="composicao_nutricional_titulo_lateral_editavel" rowspan="2">
                                <?php echo format_for_display($cardapio_semanal_item['composicao_nutricional_titulo_lateral_editavel']); ?>
                            </td>
                            <td class="col-horario" style="border-right:none;"></td> <!-- Célula vazia abaixo do horário -->
                            <?php foreach ($cardapio_semanal_item['nutrientes_map'] as $key_nutriente => $nutri_info): ?>
                                <th class="col-comp-nutri-label <?php if ($key_nutriente === array_key_last($cardapio_semanal_item['nutrientes_map'])) echo 'borda-direita-extra'; // para última célula ter borda completa?>">
                                    <?php echo htmlspecialchars($nutri_info['rotulo_fixo']); ?>
                                </th>
                            <?php endforeach; ?>
                             <!-- Células vazias para preencher o restante da largura da tabela (para alinhar com os 5 dias) -->
                            <?php 
                            $num_dias = count($cardapio_semanal_item['dias']);
                            $num_nutrientes_colunas = count($cardapio_semanal_item['nutrientes_map']); // 4
                            // Precisamos preencher (5 dias de colunas - (1 col da comp.nutricional + 4 col dos nutrientes))
                            // A coluna comp. nutricional ocupa o lugar de duas colunas de dias (Refeicao e Horario) + o da primeira refeicao.
                            // O cálculo aqui é complexo, simplificando para o visual:
                            // 1(titulo comp) + 4(nutrientes) = 5 colunas
                            // A tabela principal tem Refeição + Horário + 5 Dias = 7 colunas
                            // As colunas da composição devem se alinhar sob as 5 colunas de dias.
                            // Título Comp ocupa colunas de Refeição + Horário + Segunda (por exemplo)
                            // Nutrientes ocupam Terça, Quarta, Quinta, Sexta
                            // ESTA LÓGICA FOI REFEITA NO CSS COM LARGURAS %
                            ?>
                        </tr>
                        <!-- LINHA PARA VALORES DOS NUTRIENTES -->
                        <tr>
                            <td class="col-horario" style="border-right:none;"></td> <!-- Célula vazia abaixo do horário -->
                            <?php foreach ($cardapio_semanal_item['nutrientes_map'] as $key_nutriente => $nutri_info): ?>
                                <td class="col-comp-nutri-valor <?php echo $nutri_info['is_multilinha'] ? 'celula-editavel-multilinha' : 'celula-editavel'; ?> editavel" 
                                    data-semana="<?php echo $cardapio_semanal_item['semana_numero']; ?>" 
                                    data-path="nutrientes_map.<?php echo $key_nutriente; ?>.valor_editavel">
                                    <?php echo format_for_display($nutri_info['valor_editavel']); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="secao-rodape">
                <div class="rodape-flex-container">
                    <div class="orientacoes-container">
                        <span class="titulo-rodape editavel" data-semana="<?php echo $cardapio_semanal_item['semana_numero']; ?>" data-path="orientacoes_titulo_fixo"><?php echo format_for_display($cardapio_semanal_item['orientacoes_titulo_fixo']); ?></span>
                        <div class="celula-editavel-multilinha editavel" data-semana="<?php echo $cardapio_semanal_item['semana_numero']; ?>" data-path="orientacoes_texto_editavel">
                            <?php echo format_for_display($cardapio_semanal_item['orientacoes_texto_editavel']); ?>
                        </div>
                    </div>
                    <div class="assinaturas-wrapper">
                        <div class="assinatura-bloco">
                            <span class="titulo-rodape editavel" data-semana="<?php echo $cardapio_semanal_item['semana_numero']; ?>" data-path="elaborado_por_titulo_fixo"><?php echo format_for_display($cardapio_semanal_item['elaborado_por_titulo_fixo']); ?></span>
                            <div class="texto-assinatura celula-editavel-multilinha editavel" data-semana="<?php echo $cardapio_semanal_item['semana_numero']; ?>" data-path="elaborado_por_texto_editavel">
                                <?php echo format_for_display($cardapio_semanal_item['elaborado_por_texto_editavel']); ?>
                            </div>
                        </div>
                        <div class="assinatura-bloco">
                            <span class="titulo-rodape editavel" data-semana="<?php echo $cardapio_semanal_item['semana_numero']; ?>" data-path="revisado_por_titulo_fixo"><?php echo format_for_display($cardapio_semanal_item['revisado_por_titulo_fixo']); ?></span>
                            <div class="texto-assinatura celula-editavel-multilinha editavel" data-semana="<?php echo $cardapio_semanal_item['semana_numero']; ?>" data-path="revisado_por_texto_editavel">
                                <?php echo format_for_display($cardapio_semanal_item['revisado_por_texto_editavel']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="nota-final-container editavel" data-semana="<?php echo $cardapio_semanal_item['semana_numero']; ?>" data-path="nota_rodape_editavel">
                     <?php echo format_for_display($cardapio_semanal_item['nota_rodape_editavel']); ?>
                </div>
            </div>
        </div> 
    </div> 
    <?php endforeach; ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        // SCRIPT JAVASCRIPT (O MESMO DA ÚLTIMA VERSÃO ENVIADA, SEM //<![CDATA[ e //]]> )
        // Assegure que ele esteja completo e correto aqui.
        $(document).ready(function() {
            console.log("cardapios_semanais.php JS v_layout_final_com_ajustes_finais: Documento pronto.");

            function htmlDecode(input) {
                var doc = new DOMParser().parseFromString(input, "text/html");
                return doc.documentElement.textContent;
            }

            function htmlEncode(value) {
                if (typeof value !== 'string') value = String(value); // Garante que é string
                return value.replace(/&/g, '&')
                            .replace(/</g, '<')
                            .replace(/>/g, '>')
                            .replace(/"/g, '"')
                            .replace(/'/g, ''')
                            .replace(/\n/g, "<br>");
            }
            
            function autoResizeEditor() { 
                if (!$(this).is('textarea')) return;
                $(this).css('height', 'auto'); 
                let scrollHeight = this.scrollHeight;
                if (this.value === '' && $(this).hasClass('editor-inline')) {
                     scrollHeight = Math.max(20, parseInt($(this).css('line-height').replace('px','')) * 1.5); 
                }
                $(this).css('height', (scrollHeight + 2) + 'px');
            }

            $('.pagina-cardapio').on('click', '.editavel', function(event) {
                var $this = $(this);
                if ($this.is('textarea, input') || $(event.target).is('textarea.editor-inline, input.editor-inline')) {
                    return; 
                }
                if ($this.children('textarea.editor-inline, input.editor-inline').length) {
                    return;
                }

                $('textarea.editor-inline, input.editor-inline').each(function() {
                    const $currentEditor = $(this);
                    const $editorPai = $currentEditor.parent('.editavel'); 
                    if ($editorPai.length && !$editorPai.is($this)) {
                        const val = $currentEditor.val();
                        $currentEditor.off('.editavel'); 
                        $editorPai.html(htmlEncode(val)); 
                    } else if (!$editorPai.length) {
                        $currentEditor.remove();
                    }
                });

                const originalHtmlForRestore = $this.html(); 
                const originalTextForTextarea = htmlDecode(originalHtmlForRestore.replace(/<br\s*\/?>/gi, "\n"));
                
                const dataPath = $this.data('path'); 
                const semana = $this.data('semana');

                let $editor;
                let isMultiLineFieldByClass = $this.hasClass('celula-pratos') || 
                                     $this.hasClass('celula-sobremesa') || 
                                     $this.hasClass('celula-editavel-multilinha') || // Aplica a mais campos agora
                                     $this.hasClass('texto-orientacoes') ||
                                     $this.hasClass('texto-assinatura');
                
                let isMultiLineByContent = originalTextForTextarea.includes("\n");
                let useTextarea = isMultiLineFieldByClass || isMultiLineByContent;
                
                // Força input para campos específicos, mesmo se tiverem quebra de linha no modelo inicial (ex: datas)
                if (dataPath && (dataPath.includes('data_editavel') || dataPath.includes('energia_kcal') || dataPath.includes('titulo_fixo') )) {
                    // Se o conteúdo original (antes da edição) não tiver \n, e NÃO for uma classe multiline, use input.
                    // A menos que seja um dos campos do rodapé que pode ter títulos longos.
                    if (!originalTextForTextarea.includes("\n") && 
                        !$this.hasClass('celula-editavel-multilinha') &&
                        !dataPath.includes('titulo_lateral') &&
                        !dataPath.includes('elaborado_por_titulo') &&
                        !dataPath.includes('revisado_por_titulo') &&
                        !dataPath.includes('orientacoes_titulo') &&
                        !dataPath.includes('nota_rodape')
                    ) {
                        useTextarea = false;
                    }
                }
                
                if (useTextarea) {
                    $editor = $('<textarea class="editor-inline"></textarea>').val(originalTextForTextarea.trim());
                } else {
                    $editor = $('<input type="text" class="editor-inline">').val(originalTextForTextarea.trim());
                    // Tenta manter o alinhamento do texto original se for input
                    $editor.css('text-align', $this.css('text-align') || 'center'); 
                }
                
                $this.empty().append($editor);
                $editor.focus().select();
                
                if ($editor.is('textarea')) {
                    $editor.on('input.editavel focus.editavel', autoResizeEditor).trigger('input');
                }

                $editor.on('blur.editavel keydown.editavel', function(e) {
                    let finalizaEdicao = false;
                    if (e.type === 'blur') {
                        finalizaEdicao = true;
                    } else if (e.type === 'keydown') {
                        if (e.key === 'Enter') {
                            if ($(this).is('input') || (e.shiftKey && $(this).is('textarea'))) {
                                if ($(this).is('input')) finalizaEdicao = true;
                            } else if ($(this).is('textarea') && !e.shiftKey) {
                                 finalizaEdicao = true;
                            }
                        } else if (e.key === 'Escape') {
                            e.preventDefault();
                            $(this).off('.editavel');
                            $this.html(originalHtmlForRestore); 
                            return; 
                        }
                    }

                    if (finalizaEdicao) {
                        const novoTexto = $(this).val();
                        $(this).off('.editavel'); 
                        $this.html(htmlEncode(novoTexto));
                        console.log("Campo editado (visual): Semana", semana, "Path:", dataPath, "Novo valor:", novoTexto);
                    }
                });
            });

            $('#btnSalvarCardapio').on('click', function() {
                alert("Funcionalidade de salvar os dados editados ainda não foi implementada.");
            });

            console.log("cardapios_semanais.php JS: Edição in-place habilitada.");
        }); // FIM DE $(document).ready()
    </script>
</body>
</html>