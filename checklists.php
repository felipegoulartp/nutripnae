<?php
// cardapio_auto/checklists.php

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
ini_set('display_errors', 0); // Para DEV (mude para 0 em produção)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_log("--- Início checklists.php --- SESSION_ID: " . session_id());

// 3. Verificação de Autenticação
$is_logged_in = isset($_SESSION['user_id']);
$logged_user_id = $_SESSION['user_id'] ?? null;
$logged_username = $_SESSION['username'] ?? 'Visitante'; // Fallback

if (!$is_logged_in || !$logged_user_id) { // Checagem mais robusta
    error_log("checklists.php: Acesso não autenticado ou user_id ausente. Redirecionando para login. Session ID: " . session_id());
    header('Location: login.php');
    exit;
}
error_log("checklists.php: Usuário autenticado. UserID: $logged_user_id, Username: $logged_username.");

// Dados para o checklist da ANVISA (pré-carregado como modelo)
// Estes dados serão usados para popular o formulário de criação/edição ao carregar o modelo.
$anvisa_checklist_data = [
    'id' => 'anvisa_rdc_216',
    'title' => 'Checklist ANVISA RDC 216/2004 (Modelo)',
    'description' => 'Este é um modelo pré-definido do Checklist de Boas Práticas segundo a RDC 216/2004 da ANVISA.',
    'header_fields' => [
        ['id' => 'firma', 'label' => 'Firma', 'type' => 'text'],
        ['id' => 'denominacao', 'label' => 'Denominação', 'type' => 'text'],
        ['id' => 'endereco', 'label' => 'Endereço', 'type' => 'text'],
        ['id' => 'cidade', 'label' => 'Cidade', 'type' => 'text'],
        ['id' => 'responsavel', 'label' => 'Responsável', 'type' => 'text'],
        ['id' => 'rg_cpf', 'label' => 'RG e CPF', 'type' => 'text'],
        ['id' => 'ramo', 'label' => 'Ramo', 'type' => 'text'],
        ['id' => 'inscricao_gdf', 'label' => 'Inscrição no GDF', 'type' => 'text'],
        ['id' => 'alvara', 'label' => 'Alvará', 'type' => 'text'],
    ],
    'segments' => [
        [
            'id' => 'segment_1',
            'title' => '1. EDIFICAÇÃO, INSTALAÇÕES, EQUIPAMENTOS, MÓVEIS E UTENSÍLIOS',
            'total_points' => 37,
            'questions' => [
                ['id' => 'q1_1', 'text' => 'A edificação e as instalações devem ser projetadas de forma a possibilitar um fluxo ordenado e sem cruzamentos em todas as etapas da preparação de alimentos e a facilitar as operações de manutenção, limpeza e, quando for o caso, desinfecção.', 'importance' => 'I'],
                ['id' => 'q1_2', 'text' => 'O acesso às instalações deve ser controlado e independente, não comum a outros usos.', 'importance' => 'N'],
                ['id' => 'q1_3', 'text' => 'O dimensionamento da edificação e das instalações deve ser compatível com todas as operações.', 'importance' => 'N'],
                ['id' => 'q1_4', 'text' => 'Deve existir separação entre as diferentes atividades por meios físicos ou por outros meios eficazes de forma a evitar a contaminação cruzada.', 'importance' => 'N'],
                ['id' => 'q1_5', 'text' => 'As instalações físicas como piso, parede e teto devem possuir revestimento liso, impermeável e lavável.', 'importance' => 'I'],
                ['id' => 'q1_6', 'text' => 'As instalações físicas como piso, parede e teto são mantidos íntegros, conservados, livres de rachaduras, trincas, goteiras, vazamentos, infiltrações, bolores, descascamentos, dentre outros e não devem transmitir contaminantes aos alimentos.', 'importance' => 'I'],
                ['id' => 'q1_7', 'text' => 'As portas e as janelas devem ser mantidas ajustadas aos batentes.', 'importance' => 'N'],
                ['id' => 'q1_8', 'text' => 'As portas da área de preparação e armazenamento de alimentos devem ser dotadas de fechamento automático.', 'importance' => 'N'],
                ['id' => 'q1_9', 'text' => 'As aberturas externas das áreas de armazenamento e preparação de alimentos, inclusive o sistema de exaustão, devem ser providas de telas milimetradas para impedir o acesso de vetores e pragas urbanas.', 'importance' => 'I'],
                ['id' => 'q1_10', 'text' => 'As telas devem ser removíveis para facilitar a limpeza periódica.', 'importance' => 'R'],
                ['id' => 'q1_11', 'text' => 'As instalações devem ser abastecidas de água corrente.', 'importance' => 'I'],
                ['id' => 'q1_12', 'text' => 'As instalações dispor de conexões com rede de esgoto ou fossa séptica.', 'importance' => 'I'],
                ['id' => 'q1_13', 'text' => 'Os ralos devem ser sifonados e as grelhas devem possuir dispositivo que permitam seu fechamento.', 'importance' => 'N'],
                ['id' => 'q1_14', 'text' => 'Caixas de gordura e de esgoto devem possuir dimensão compatível ao volume de resíduos.', 'importance' => 'N'],
                ['id' => 'q1_15', 'text' => 'As caixas de gordura e de esgoto estar localizadas fora da área de preparação e armazenamento de alimentos.', 'importance' => 'I'],
                ['id' => 'q1_16', 'text' => 'As caixas de gordura e de esgoto apresentar adequado estado de conservação e funcionamento.', 'importance' => 'N'],
                ['id' => 'q1_17', 'text' => 'As áreas internas e externas do estabelecimento devem estar livres de objetos em desuso ou estranhos ao ambiente.', 'importance' => 'N'],
                ['id' => 'q1_18', 'text' => 'As áreas internas e externas não sendo permitida a presença de animais.', 'importance' => 'I'],
                ['id' => 'q1_19', 'text' => 'A iluminação da área de preparação deve proporcionar a visualização de forma que as atividades sejam realizadas sem comprometer a higiene e as características sensoriais dos alimentos.', 'importance' => 'N'],
                ['id' => 'q1_20', 'text' => 'As luminárias localizadas sobre a área de preparação dos alimentos devem ser apropriadas e estar protegidas contra explosão e quedas acidentais.', 'importance' => 'I'],
                ['id' => 'q1_21', 'text' => 'As instalações elétricas devem estar embutidas ou protegidas em tubulações externas e íntegras de tal forma a permitir a higienização dos ambientes.', 'importance' => 'N'],
                ['id' => 'q1_22', 'text' => 'A ventilação deve garantir a renovação do ar e a manutenção do ambiente livre de fungos, gases, fumaça, pós, partículas em suspensão, condensação de vapores dentre outros que possam comprometer a qualidade higiênico-sanitária do alimento. O fluxo de ar não deve incidir diretamente sobre os alimentos.', 'importance' => 'N'],
                ['id' => 'q1_23', 'text' => 'Os equipamentos e os filtros para climatização devem estar conservados.', 'importance' => 'N'],
                ['id' => 'q1_24', 'text' => 'A limpeza dos componentes do sistema de climatização, a troca de filtros e a manutenção programada e periódica destes equipamentos devem ser registradas e realizadas conforme legislação específica.', 'importance' => 'R'],
                ['id' => 'q1_25', 'text' => 'As instalações sanitárias e os vestiários não devem se comunicar diretamente com a área de preparação e armazenamento de alimentos ou refeitórios.', 'importance' => 'I'],
                ['id' => 'q1_26', 'text' => 'As instalações sanitárias e os vestiários devendo ser mantidos organizados e em adequado estado de conservação.', 'importance' => 'N'],
                ['id' => 'q1_27', 'text' => 'As portas externas das instalações sanitárias e os vestiários são dotadas de fechamento automático.', 'importance' => 'N'],
                ['id' => 'q1_28', 'text' => 'As instalações sanitárias devem possuir lavatórios.', 'importance' => 'I'],
                ['id' => 'q1_29', 'text' => 'As instalações sanitárias possuem produtos destinados à higiene pessoal tais como papel higiênico, sabonete líquido inodoro anti-séptico ou sabonete líquido inodoro e produto anti-séptico e toalhas de papel não reciclado ou outro sistema higiênico e seguro para secagem das mãos.', 'importance' => 'I'],
                ['id' => 'q1_30', 'text' => 'Os coletores dos resíduos das instalações sanitárias devem ser dotados de tampa e acionados sem contato manual.', 'importance' => 'N'],
                ['id' => 'q1_31', 'text' => 'Existir lavatórios exclusivos para a higiene das mãos na área de manipulação, em posições estratégicas em relação ao fluxo de preparo dos alimentos e em número suficiente de modo a atender toda a área de preparação.', 'importance' => 'I'],
                ['id' => 'q1_32', 'text' => 'Os lavatórios devem possuir sabonete líquido inodoro anti-séptico ou sabonete líquido inodoro e produto anti-séptico, toalhas de papel não reciclado ou outro sistema higiênico e seguro de secagem das mãos e coletor de papel, acionado sem contato manual.', 'importance' => 'I'],
                ['id' => 'q1_33', 'text' => 'Os equipamentos, móveis e utensílios que entram em contato com alimentos devem ser de materiais que não transmitam substâncias tóxicas, odores, nem sabores aos mesmos, conforme estabelecido em legislação específica.', 'importance' => 'I'],
                ['id' => 'q1_34', 'text' => 'Os equipamentos, móveis e utensílios ser mantidos em adequado estado de conservação e ser resistentes à corrosão e a repetidas operações de limpeza e desinfecção.', 'importance' => 'N'],
                ['id' => 'q1_35', 'text' => 'Devem ser realizadas manutenção programada e periódica dos equipamentos e utensílios e calibração dos instrumentos ou equipamentos de medição, mantendo registro da realização dessas operações.', 'importance' => 'R'],
                ['id' => 'q1_36', 'text' => 'As superfícies dos equipamentos, móveis e utensílios utilizados na preparação, embalagem, armazenamento, transporte, distribuição e exposição à venda dos alimentos devem ser lisas, impermeáveis, laváveis.', 'importance' => 'I'],
                ['id' => 'q1_37', 'text' => 'As superfícies dos equipamentos, móveis e utensílios utilizados estar isentas de rugosidades, frestas e outras imperfeições que possam comprometer a higienização dos mesmos e serem fontes de contaminação dos alimentos.', 'importance' => 'I'],
            ]
        ],
        [
            'id' => 'segment_2',
            'title' => '2. HIGIENIZAÇÃO DE INSTALAÇÕES, EQUIPAMENTOS, MÓVEIS E UTENSÍLIOS',
            'total_points' => 12,
            'questions' => [
                ['id' => 'q2_1', 'text' => 'As instalações, os equipamentos, os móveis e os utensílios devem ser mantidos em condições higiênico-sanitárias apropriadas.', 'importance' => 'I'],
                ['id' => 'q2_2', 'text' => 'As operações de higienização devem ser realizadas por funcionários comprovadamente capacitados e com frequência que garanta a manutenção dessas condições e minimize o risco de contaminação do alimento.', 'importance' => 'N'],
                ['id' => 'q2_3', 'text' => 'As caixas de gordura devem ser periodicamente limpas. O descarte dos resíduos deve atender ao disposto em legislação específica.', 'importance' => 'N'],
                ['id' => 'q2_4', 'text' => 'As operações de limpeza e, se for o caso, de desinfecção das instalações e equipamentos, quando não forem realizadas rotineiramente, devem ser registradas.', 'importance' => 'R'],
                ['id' => 'q2_5', 'text' => 'A área de preparação do alimento deve ser higienizada quantas vezes forem necessárias e imediatamente após o término do trabalho.', 'importance' => 'I'],
                ['id' => 'q2_6', 'text' => 'Devem ser tomadas precauções para impedir a contaminação dos alimentos causada por produtos saneantes, pela suspensão de partículas e pela formação de aerossóis. Substâncias odorizantes e ou desodorantes em quaisquer das suas formas não devem ser utilizadas nas áreas de preparação e armazenamento dos alimentos.', 'importance' => 'I'],
                ['id' => 'q2_7', 'text' => 'Os produtos saneantes utilizados devem estar regularizados pelo Ministério da Saúde.', 'importance' => 'I'],
                ['id' => 'q2_8', 'text' => 'A diluição, o tempo de contato e modo de uso/aplicação dos produtos saneantes devem obedecer às instruções recomendadas pelo fabricante.', 'importance' => 'N'],
                ['id' => 'q2_9', 'text' => 'Os produtos saneantes devem ser identificados e guardados em local reservado para essa finalidade.', 'importance' => 'N'],
                ['id' => 'q2_10', 'text' => 'Os utensílios e equipamentos utilizados na higienização devem ser próprios para a atividade e estar conservados, limpos e disponíveis em número suficiente e guardados em local reservado para essa finalidade.', 'importance' => 'N'],
                ['id' => 'q2_11', 'text' => 'Os utensílios utilizados na higienização de instalações devem ser distintos daqueles usados para higienização das partes dos equipamentos e utensílios que entrem em contato com o alimento.', 'importance' => 'N'],
                ['id' => 'q2_12', 'text' => 'Os funcionários responsáveis pela atividade de higienização das instalações sanitárias devem utilizar uniformes apropriados e diferenciados daqueles utilizados na manipulação de alimentos.', 'importance' => 'N'],
            ]
        ],
        [
            'id' => 'segment_3',
            'title' => '3. CONTROLE INTEGRADO DE PRAGAS E VETORES',
            'total_points' => 5,
            'questions' => [
                ['id' => 'q3_1', 'text' => 'A edificação, as instalações, os equipamentos, os móveis e os utensílios devem ser livres de vetores e pragas urbanas.', 'importance' => 'I'],
                ['id' => 'q3_2', 'text' => 'Deve existir um conjunto de ações eficazes e contínuas de controle de vetores e pragas urbanas, com o objetivo de impedir a atração, o abrigo, o acesso e ou proliferação dos mesmos.', 'importance' => 'N'],
                ['id' => 'q3_3', 'text' => 'Quando as medidas de prevenção adotadas não forem eficazes, o controle químico deve ser empregado e executado por empresa especializada, conforme legislação específica, com produtos desinfestantes regularizados pelo Ministério da Saúde.', 'importance' => 'N'],
                ['id' => 'q3_4', 'text' => 'Quando da aplicação do controle químico, a empresa especializada deve estabelecer procedimentos pré e pós-tratamento a fim de evitar a contaminação dos alimentos, equipamentos e utensílios.', 'importance' => 'N'],
                ['id' => 'q3_5', 'text' => 'Quando aplicável, os equipamentos e os utensílios, antes de serem reutilizados, devem ser higienizados para a remoção dos resíduos de produtos desinfestantes.', 'importance' => 'N'],
            ]
        ],
        [
            'id' => 'segment_4',
            'title' => '4. ABASTECIMENTO DE ÁGUA',
            'total_points' => 7,
            'questions' => [
                ['id' => 'q4_1', 'text' => 'Deve ser utilizada somente água potável para manipulação de alimentos.', 'importance' => 'I'],
                ['id' => 'q4_2', 'text' => 'Quando utilizada solução alternativa de abastecimento de água, a potabilidade deve ser atestada semestralmente mediante laudos laboratoriais, sem prejuízo de outras exigências previstas em legislação específica.', 'importance' => 'N'],
                ['id' => 'q4_3', 'text' => 'O gelo para utilização em alimentos deve ser fabricado a partir de água potável, mantido em condição higiênico-sanitária que evite sua contaminação.', 'importance' => 'N'],
                ['id' => 'q4_4', 'text' => 'O vapor, quando utilizado em contato direto com alimentos ou com superfícies que entrem em contato com alimentos, deve ser produzido a partir de água potável e não pode representar fonte de contaminação.', 'importance' => 'N'],
                ['id' => 'q4_5', 'text' => 'O reservatório de água deve ser edificado e ou revestido de materiais que não comprometam a qualidade da água, conforme legislação específica.', 'importance' => 'N'],
                ['id' => 'q4_6', 'text' => 'Deve estar livre de rachaduras, vazamentos, infiltrações, descascamentos dentre outros defeitos e em adequado estado de higiene e conservação, devendo estar devidamente tampado.', 'importance' => 'N'],
                ['id' => 'q4_7', 'text' => 'O reservatório de água deve ser higienizado, em um intervalo máximo de seis meses, devendo ser mantidos registros da operação.', 'importance' => 'N'],
            ]
        ],
        [
            'id' => 'segment_5',
            'title' => '5. MANEJO DOS RESÍDUOS',
            'total_points' => 3,
            'questions' => [
                ['id' => 'q5_1', 'text' => 'O estabelecimento deve dispor de recipientes identificados e íntegros, de fácil higienização e transporte, em número e capacidade suficientes para conter os resíduos.', 'importance' => 'N'],
                ['id' => 'q5_2', 'text' => 'Os coletores utilizados para deposição dos resíduos das áreas de preparação e armazenamento de alimentos devem ser dotados de tampas acionadas sem contato manual.', 'importance' => 'N'],
                ['id' => 'q5_3', 'text' => 'Os resíduos devem ser frequentemente coletados e estocados em local fechado e isolado da área de preparação e armazenamento dos alimentos, de forma a evitar focos de contaminação e atração de vetores e pragas urbanas.', 'importance' => 'N'],
            ]
        ],
        [
            'id' => 'segment_6',
            'title' => '6. MANIPULADORES',
            'total_points' => 14,
            'questions' => [
                ['id' => 'q6_1', 'text' => 'O controle da saúde dos manipuladores deve ser registrado e realizado de acordo com a legislação específica.', 'importance' => 'N'],
                ['id' => 'q6_2', 'text' => 'Os manipuladores que apresentarem lesões e ou sintomas de enfermidades que possam comprometer a qualidade higiênico-sanitária dos alimentos devem ser afastados da atividade de preparação de alimentos enquanto persistirem essas condições de saúde.', 'importance' => 'I'],
                ['id' => 'q6_3', 'text' => 'Os manipuladores devem ter asseio pessoal, apresentando-se com uniformes compatíveis à atividade, conservados e limpos.', 'importance' => 'I'],
                ['id' => 'q6_4', 'text' => 'Os uniformes devem ser trocados, no mínimo, diariamente e usados exclusivamente nas dependências internas do estabelecimento.', 'importance' => 'N'],
                ['id' => 'q6_5', 'text' => 'As roupas e os objetos pessoais devem ser guardados em local específico e reservado para esse fim.', 'importance' => 'N'],
                ['id' => 'q6_6', 'text' => 'Os manipuladores devem lavar cuidadosamente as mãos ao chegar ao trabalho, antes e após manipular alimentos, após qualquer interrupção do serviço, após tocar materiais contaminados, após usar os sanitários e sempre que se fizer necessário.', 'importance' => 'I'],
                ['id' => 'q6_7', 'text' => 'Devem ser afixados cartazes de orientação aos manipuladores sobre a correta lavagem e anti-sepsia das mãos e demais hábitos de higiene, em locais de fácil visualização, inclusive nas instalações sanitárias e lavatórios.', 'importance' => 'N'],
                ['id' => 'q6_8', 'text' => 'Os manipuladores não devem fumar, falar desnecessariamente, cantar, assobiar, espirrar, cuspir, tossir, comer, manipular dinheiro ou praticar outros atos que possam contaminar o alimento, durante o desempenho das atividades.', 'importance' => 'I'],
                ['id' => 'q6_9', 'text' => 'Os manipuladores devem usar cabelos presos e protegidos por redes, toucas ou outro acessório apropriado para esse fim, não sendo permitido o uso de barba.', 'importance' => 'I'],
                ['id' => 'q6_10', 'text' => 'As unhas devem estar curtas e sem esmalte ou base.', 'importance' => 'I'],
                ['id' => 'q6_11', 'text' => 'Durante a manipulação, devem ser retirados todos os objetos de adorno pessoal e a maquiagem.', 'importance' => 'N'],
                ['id' => 'q6_12', 'text' => 'Os manipuladores de alimentos devem ser supervisionados e capacitados periodicamente em higiene pessoal, em manipulação higiênica dos alimentos e em doenças transmitidas por alimentos.', 'importance' => 'I'],
                ['id' => 'q6_13', 'text' => 'A capacitação deve ser comprovada mediante documentação.', 'importance' => 'N'],
                ['id' => 'q6_14', 'text' => 'Os visitantes devem cumprir os requisitos de higiene e de saúde estabelecidos para os manipuladores.', 'importance' => 'N'],
            ]
        ],
        [
            'id' => 'segment_7',
            'title' => '7. MATÉRIAS-PRIMAS, INGREDIENTES E EMBALAGENS',
            'total_points' => 13,
            'questions' => [
                ['id' => 'q7_1', 'text' => 'Os serviços de alimentação devem especificar os critérios para avaliação e seleção dos fornecedores de matérias-primas, ingredientes e embalagens.', 'importance' => 'N'],
                ['id' => 'q7_2', 'text' => 'O transporte desses insumos deve ser realizado em condições adequadas de higiene e conservação.', 'importance' => 'I'],
                ['id' => 'q7_3', 'text' => 'A recepção das matérias-primas, dos ingredientes e das embalagens deve ser realizada em área protegida e limpa.', 'importance' => 'N'],
                ['id' => 'q7_4', 'text' => 'Devem ser adotadas medidas para evitar que esses insumos contaminem o alimento preparado.', 'importance' => 'I'],
                ['id' => 'q7_5', 'text' => 'As matérias-primas, os ingredientes e as embalagens devem ser submetidos à inspeção e aprovados na recepção.', 'importance' => 'N'],
                ['id' => 'q7_6', 'text' => 'As embalagens primárias das matérias-primas e dos ingredientes devem estar íntegras.', 'importance' => 'I'],
                ['id' => 'q7_7', 'text' => 'A temperatura das matérias-primas e ingredientes que necessitem de condições especiais de conservação deve ser verificada nas etapas de recepção e de armazenamento.', 'importance' => 'N'],
                ['id' => 'q7_8', 'text' => 'Os lotes das matérias-primas, dos ingredientes ou das embalagens reprovados ou com prazos de validade vencidos devem ser imediatamente devolvidos ao fornecedor e, na impossibilidade, devem ser devidamente identificados e armazenados separadamente. Deve ser determinada a destinação final dos mesmos.', 'importance' => 'I'],
                ['id' => 'q7_9', 'text' => 'As matérias-primas, os ingredientes e as embalagens devem ser armazenados em local limpo e organizado, de forma a garantir proteção contra contaminantes.', 'importance' => 'N'],
                ['id' => 'q7_10', 'text' => 'Devem estar adequadamente acondicionados e identificados, sendo que sua utilização deve respeitar o prazo de validade.', 'importance' => 'N'],
                ['id' => 'q7_11', 'text' => 'Para os alimentos dispensados da obrigatoriedade da indicação do prazo de validade, deve ser observada a ordem de entrada dos mesmos.', 'importance' => 'R'],
                ['id' => 'q7_12', 'text' => 'As matérias-primas, os ingredientes e as embalagens devem ser armazenados sobre paletes, estrados e ou prateleiras, respeitando-se o espaçamento mínimo necessário para garantir adequada ventilação, limpeza e, quando for o caso, desinfecção do local.', 'importance' => 'N'],
                ['id' => 'q7_13', 'text' => 'Os paletes, estrados e ou prateleiras devem ser de material liso, resistente, impermeável e lavável.', 'importance' => 'N'],
            ]
        ],
        [
            'id' => 'segment_8',
            'title' => '8. PREPARAÇÃO DO ALIMENTO',
            'total_points' => 23,
            'questions' => [
                ['id' => 'q8_1', 'text' => 'As matérias-primas, os ingredientes e as embalagens utilizados para preparação do alimento devem estar em condições higiênico-sanitárias adequadas e em conformidade com a legislação específica.', 'importance' => 'I'],
                ['id' => 'q8_2', 'text' => 'O quantitativo de funcionários, equipamentos, móveis e ou utensílios disponíveis devem ser compatíveis com volume, diversidade e complexidade das preparações alimentícias.', 'importance' => 'N'],
                ['id' => 'q8_3', 'text' => 'Durante a preparação dos alimentos, devem ser adotadas medidas a fim de minimizar o risco de contaminação cruzada.', 'importance' => 'I'],
                ['id' => 'q8_4', 'text' => 'Deve-se evitar o contato direto ou indireto entre alimentos crus, semi-preparados e prontos para o consumo.', 'importance' => 'I'],
                ['id' => 'q8_5', 'text' => 'Os funcionários que manipulam alimentos crus devem realizar a lavagem e a anti-sepsia das mãos antes de manusear alimentos preparados.', 'importance' => 'I'],
                ['id' => 'q8_6', 'text' => 'As matérias-primas e os ingredientes caracterizados como produtos perecíveis devem ser expostos à temperatura ambiente somente pelo tempo mínimo necessário para a preparação do alimento, a fim de não comprometer a qualidade higiênico-sanitária do alimento preparado.', 'importance' => 'I'],
                ['id' => 'q8_7', 'text' => 'Quando as matérias-primas e os ingredientes não forem utilizados em sua totalidade, devem ser adequadamente acondicionados e identificados com, no mínimo, as seguintes informações: designação do produto, data de fracionamento e prazo de validade após a abertura ou retirada da embalagem original.', 'importance' => 'N'],
                ['id' => 'q8_8', 'text' => 'Quando aplicável, antes de iniciar a preparação dos alimentos, deve-se proceder à adequada limpeza das embalagens primárias das matérias-primas e dos ingredientes, minimizando o risco de contaminação.', 'importance' => 'N'],
                ['id' => 'q8_9', 'text' => 'O tratamento térmico deve garantir que todas as partes do alimento atinjam a temperatura de, no mínimo, 70C (setenta graus Celsius). Temperaturas inferiores podem ser utilizadas no tratamento térmico desde que as combinações de tempo e temperatura sejam suficientes para assegurar a qualidade higiênico-sanitária dos alimentos.', 'importance' => 'I'],
                ['id' => 'q8_10', 'text' => 'A eficácia do tratamento térmico deve ser avaliada pela verificação da temperatura e do tempo utilizados e, quando aplicável, pelas mudanças na textura e cor na parte central do alimento.', 'importance' => 'N'],
                ['id' => 'q8_11', 'text' => 'Para os alimentos que forem submetidos à fritura, além dos controles estabelecidos para um tratamento térmico, deve-se instituir medidas que garantam que o óleo e a gordura utilizados não constituam uma fonte de contaminação química do alimento preparado.', 'importance' => 'I'],
                ['id' => 'q8_12', 'text' => 'Os óleos e gorduras utilizados devem ser aquecidos a temperaturas não superiores a 180C (cento e oitenta graus Celsius), sendo substituídos imediatamente sempre que houver alteração evidente das características físico-químicas ou sensoriais, tais como aroma e sabor, e formação intensa de espuma e fumaça.', 'importance' => 'I'],
                ['id' => 'q8_13', 'text' => 'Para os alimentos congelados, antes do tratamento térmico, deve-se proceder ao descongelamento, a fim de garantir adequada penetração do calor. Excetuam-se os casos em que o fabricante do alimento recomenda que o mesmo seja submetido ao tratamento térmico ainda congelado, devendo ser seguidas as orientações constantes da rotulagem.', 'importance' => 'N'],
                ['id' => 'q8_14', 'text' => 'O descongelamento deve ser conduzido de forma a evitar que as áreas superficiais dos alimentos se mantenham em condições favoráveis à multiplicação microbiana. O descongelamento deve ser efetuado em condições de refrigeração à temperatura inferior a 5C (cinco graus Celsius) ou em forno de microondas quando o alimento for submetido imediatamente à cocção.', 'importance' => 'I'],
                ['id' => 'q8_15', 'text' => 'Os alimentos submetidos ao descongelamento devem ser mantidos sob refrigeração se não forem imediatamente utilizados, não devendo ser recongelados.', 'importance' => 'I'],
                ['id' => 'q8_16', 'text' => 'Após serem submetidos à cocção, os alimentos preparados devem ser mantidos em condições de tempo e de temperatura que não favoreçam a multiplicação microbiana. Para conservação a quente, os alimentos devem ser submetidos à temperatura superior a 60C (sessenta graus Celsius) por, no máximo, 6 (seis) horas. Para conservação sob refrigeração ou congelamento, os alimentos devem ser previamente submetidos ao processo de resfriamento.', 'importance' => 'I'],
                ['id' => 'q8_17', 'text' => 'O processo de resfriamento de um alimento preparado deve ser realizado de forma a minimizar o risco de contaminação cruzada e a permanência do mesmo em temperaturas que favoreçam a multiplicação microbiana. A temperatura do alimento preparado deve ser reduzida de 60C (sessenta graus Celsius) a 10C (dez graus Celsius) em até duas horas.', 'importance' => 'I'],
                ['id' => 'q8_18', 'text' => 'Em seguida, o mesmo deve ser conservado sob refrigeração a temperaturas inferiores a 5C (cinco graus Celsius), ou congelado à temperatura igual ou inferior a -18C (dezoito graus Celsius negativos).', 'importance' => 'I'],
                ['id' => 'q8_19', 'text' => 'O prazo máximo de consumo do alimento preparado e conservado sob refrigeração à temperatura de 4C (quatro graus Celsius), ou inferior, deve ser de 5 (cinco) dias. Quando forem utilizadas temperaturas superiores a 4C (quatro graus Celsius) e inferiores a 5C (cinco graus Celsius), o prazo máximo de consumo deve ser reduzido, de forma a garantir as condições higiênico-sanitárias do alimento preparado.', 'importance' => 'N'],
                ['id' => 'q8_20', 'text' => 'Caso o alimento preparado seja armazenado sob refrigeração ou congelamento deve-se apor no invólucro do mesmo, no mínimo, as seguintes informações: designação, data de preparo e prazo de validade. A temperatura de armazenamento deve ser regularmente monitorada e registrada.', 'importance' => 'N'],
                ['id' => 'q8_21', 'text' => 'Quando aplicável, os alimentos a serem consumidos crus devem ser submetidos a processo de higienização a fim de reduzir a contaminação superficial.', 'importance' => 'I'],
                ['id' => 'q8_22', 'text' => 'Os produtos utilizados na higienização dos alimentos devem estar regularizados no órgão competente do Ministério da Saúde e serem aplicados de forma a evitar a presença de resíduos no alimento preparado.', 'importance' => 'I'],
                ['id' => 'q8_23', 'text' => 'O estabelecimento deve implementar e manter documentado o controle e garantia da qualidade dos alimentos preparados.', 'importance' => 'N'],
            ]
        ],
        [
            'id' => 'segment_9',
            'title' => '9. ARMAZENAMENTO E TRANSPORTE DO ALIMENTO PREPARADO',
            'total_points' => 4,
            'questions' => [
                ['id' => 'q9_1', 'text' => 'Os alimentos preparados mantidos na área de armazenamento ou aguardando o transporte devem estar identificados e protegidos contra contaminantes. Na identificação deve constar, no mínimo, a designação do produto, a data de preparo e o prazo de validade.', 'importance' => 'I'],
                ['id' => 'q9_2', 'text' => 'O armazenamento e o transporte do alimento preparado, da distribuição até a entrega ao consumo, deve ocorrer em condições de tempo e temperatura que não comprometam sua qualidade higiênico-sanitária. A temperatura do alimento preparado deve ser monitorada durante essas etapas.', 'importance' => 'I'],
                ['id' => 'q9_3', 'text' => 'Os meios de transporte do alimento preparado devem ser higienizados, sendo adotadas medidas a fim de garantir a ausência de vetores e pragas urbanas.', 'importance' => 'N'],
                ['id' => 'q9_4', 'text' => 'Os veículos devem ser dotados de cobertura para proteção da carga, não devendo transportar outras cargas que comprometam a qualidade higiênico-sanitária do alimento preparado.', 'importance' => 'I'],
            ]
        ],
        [
            'id' => 'segment_10',
            'title' => '10. EXPOSIÇÃO AO CONSUMO DO ALIMENTO PREPARADO',
            'total_points' => 10,
            'questions' => [
                ['id' => 'q10_1', 'text' => 'As áreas de exposição do alimento preparado e de consumação ou refeitório devem ser mantidas organizadas e em adequadas condições higiênico-sanitárias.', 'importance' => 'N'],
                ['id' => 'q10_2', 'text' => 'Os equipamentos, móveis e utensílios disponíveis nessas áreas devem ser compatíveis com as atividades, em número suficiente e em adequado estado de conservação.', 'importance' => 'N'],
                ['id' => 'q10_3', 'text' => 'Os manipuladores devem adotar procedimentos que minimizem o risco de contaminação dos alimentos preparados por meio da anti-sepsia das mãos e pelo uso de utensílios ou luvas descartáveis.', 'importance' => 'I'],
                ['id' => 'q10_4', 'text' => 'Os equipamentos necessários à exposição ou distribuição de alimentos preparados sob temperaturas controladas, devem ser devidamente dimensionados, e estar em adequado estado de higiene, conservação e funcionamento.', 'importance' => 'I'],
                ['id' => 'q10_5', 'text' => 'A temperatura desses equipamentos deve ser regularmente monitorada.', 'importance' => 'N'],
                ['id' => 'q10_6', 'text' => 'O equipamento de exposição do alimento preparado na área de consumação deve dispor de barreiras de proteção que previnam a contaminação do mesmo em decorrência da proximidade ou da ação do consumidor e de outras fontes.', 'importance' => 'I'],
                ['id' => 'q10_7', 'text' => 'Os utensílios utilizados na consumação do alimento, tais como pratos, copos, talheres, devem ser descartáveis ou, quando feitos de material não-descartável, devidamente higienizados, sendo armazenados em local protegido.', 'importance' => 'N'],
                ['id' => 'q10_8', 'text' => 'Os ornamentos e plantas localizados na área de consumação ou refeitório não devem constituir fonte de contaminação para os alimentos preparados.', 'importance' => 'N'],
                ['id' => 'q10_9', 'text' => 'A área do serviço de alimentação onde se realiza a atividade de recebimento de dinheiro, cartões e outros meios utilizados para o pagamento de despesas, deve ser reservada.', 'importance' => 'I'],
                ['id' => 'q10_10', 'text' => 'Os funcionários responsáveis por essa atividade não devem manipular alimentos preparados, embalados ou não.', 'importance' => 'I'],
            ]
        ],
        [
            'id' => 'segment_11',
            'title' => '11. DOCUMENTAÇÃO E REGISTRO',
            'total_points' => 8,
            'questions' => [
                ['id' => 'q11_1', 'text' => 'Os serviços de alimentação devem dispor de Manual de Boas Práticas e de Procedimentos Operacionais Padronizados. Esses documentos devem estar acessíveis aos funcionários envolvidos e disponíveis à autoridade sanitária, quando requerido.', 'importance' => 'I'],
                ['id' => 'q11_2', 'text' => 'Os POP devem conter as instruções sequenciais das operações e a frequência de execução, especificando o nome, o cargo e ou a função dos responsáveis pelas atividades. Devem ser aprovados, datados e assinados pelo responsável do estabelecimento.', 'importance' => 'N'],
                ['id' => 'q11_3', 'text' => 'Os registros devem ser mantidos por período mínimo de 30 (trinta) dias contados a partir da data de preparação dos alimentos.', 'importance' => 'N'],
                ['id' => 'q11_4', 'text' => 'Os serviços de alimentação devem implementar Procedimentos Operacionais Padronizados relacionados aos seguintes itens: a) Higienização de instalações, equipamentos e móveis; b) Controle integrado de vetores e pragas urbanas; c) Higienização do reservatório; d) Higiene e saúde dos manipuladores.', 'importance' => 'I'],
                ['id' => 'q11_5', 'text' => 'Os POP referentes às operações de higienização de instalações, equipamentos e móveis devem conter as seguintes informações: natureza da superfície a ser higienizada, método de higienização, princípio ativo selecionado e sua concentração, tempo de contato dos agentes químicos e ou físicos utilizados na operação de higienização, temperatura e outras informações que se fizerem necessárias. Quando aplicável, os POP devem contemplar a operação de desmonte dos equipamentos.', 'importance' => 'N'],
                ['id' => 'q11_6', 'text' => 'Os POP relacionados ao controle integrado de vetores e pragas urbanas devem contemplar as medidas preventivas e corretivas destinadas a impedir a atração, o abrigo, o acesso e ou a proliferação de vetores e pragas urbanas. No caso da adoção de controle químico, o estabelecimento deve apresentar comprovante de execução de serviço fornecido pela empresa especializada contratada, contendo as informações estabelecidas em legislação sanitária específica.', 'importance' => 'N'],
                ['id' => 'q11_7', 'text' => 'Os POP referentes à higienização do reservatório devem especificar as informações constantes do item 4.11.5, mesmo quando realizada por empresa terceirizada e, neste caso, deve ser apresentado o certificado de execução do serviço.', 'importance' => 'N'],
                ['id' => 'q11_8', 'text' => 'Os POP relacionados à higiene e saúde dos manipuladores devem contemplar as etapas, a frequência e os princípios ativos usados na lavagem e anti-sepsia das mãos dos manipuladores, assim como as medidas adotadas nos casos em que os manipuladores apresentem lesão nas mãos, sintomas de enfermidade ou suspeita de problema de saúde que possa comprometer a qualidade higiênico-sanitária dos alimentos. Deve-se especificar os exames aos quais os manipuladores de alimentos são submetidos, bem como a periodicidade de sua execução. O programa de capacitação dos manipuladores em higiene deve ser descrito, sendo determinada a carga horária, o conteúdo programático e a frequência de sua realização, mantendo-se em arquivo os registros da participação nominal dos funcionários.', 'importance' => 'N'],
            ]
        ],
        [
            'id' => 'segment_12',
            'title' => '12. RESPONSABILIDADE',
            'total_points' => 2,
            'questions' => [
                ['id' => 'q12_1', 'text' => 'O responsável pelas atividades de manipulação dos alimentos deve ser o proprietário ou funcionário designado, devidamente capacitado, sem prejuízo dos casos onde há previsão legal para responsabilidade técnica.', 'importance' => 'I'],
                ['id' => 'q12_2', 'text' => 'O responsável pelas atividades de manipulação dos alimentos deve ser comprovadamente submetido a curso de capacitação, abordando, no mínimo, os seguintes temas: a) Contaminantes alimentares; b) Doenças transmitidas por alimentos; c) Manipulação higiênica dos alimentos; d) Boas Práticas.', 'importance' => 'I'],
            ]
        ],
    ],
    'importance_mapping' => [
        'I' => ['label' => 'Imprescindível', 'interdiction' => 'Interdição Imediata'],
        'N' => ['label' => 'Necessário', 'interdiction' => 'Interdição'],
        'R' => ['label' => 'Recomendável', 'interdiction' => 'Pode levar à interdição'],
        'INF' => ['label' => 'Informativo', 'interdiction' => 'Não interdita'],
    ],
    'scoring_rules' => [
        'C' => 1, // Conforme = 1 ponto
        'NC' => 0, // Não Conforme = 0 pontos
        'NSA' => 0, // Não se Aplica = 0 pontos (mas afeta o total para cálculo percentual)
    ],
    'report_thresholds' => [
        'approved' => 80, // >= 80%
        'approved_with_restrictions' => 60, // >= 60% e < 80%
        'reproved' => 59, // < 60%
    ]
];

$anvisa_checklist_data_json = json_encode($anvisa_checklist_data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Checklists - NutriPNAE</title>
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

        /* Reset e Base */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
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

        /* Navbar Superior (do home.php) */
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
        }

        /* Sidebar styles */
        .sidebar {
            width: 350px;
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

        .sidebar-toggle-button {
            display: none;
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            flex-grow: 1;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--color-text-dark);
            text-decoration: none;
            font-size: 1.1em;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease, border-left-color 0.2s ease;
            border-left: 4px solid transparent;
            margin-bottom: 5px;
        }
        .sidebar-nav a .fas {
            margin-right: 15px;
            font-size: 1.2em;
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
            font-size: 1.1em;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease, border-left-color 0.2s ease;
            border-left: 4px solid transparent;
            list-style: none;
        }
        .sidebar-nav details summary::-webkit-details-marker {
            display: none;
        }

        .sidebar-nav details summary .fas {
            margin-right: 15px;
            font-size: 1.2em;
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
            font-size: 1em;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .sidebar-nav ul li a .fas {
            margin-right: 10px;
            font-size: 0.9em;
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

        .page-main-title {
            font-size: 2.2em;
            color: var(--color-text-dark);
            margin-bottom: 8px;
        }
        .page-main-subtitle {
            font-size: 1.1em;
            color: var(--color-text-light);
            margin-bottom: 25px;
        }

        /* CHECKLISTS SPECIFIC STYLES */
        .checklists-management-section {
            background-color: var(--color-bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid var(--color-light-border);
            padding: 25px;
            margin-bottom: 30px;
        }

        .checklists-management-section h2 {
            font-size: 2em;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--color-primary-dark);
        }

        .checklists-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .checklist-card {
            background-color: var(--color-bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid var(--color-light-border);
            padding: 20px;
            display: flex;
            flex-direction: column;
            min-height: 180px; /* Adjusted height */
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .checklist-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-hover);
        }

        .checklist-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid var(--color-border);
            padding-bottom: 10px;
            flex-shrink: 0;
        }
        .checklist-card-header h3 {
            margin: 0;
            font-size: 1.2em;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--color-primary-dark);
        }
        .checklist-card-header h3 .fas {
            font-size: 0.9em;
            color: var(--color-primary);
        }
        .checklist-card-description {
            font-size: 0.95em;
            color: var(--color-text-light);
            margin-bottom: 15px;
            flex-grow: 1;
        }
        .checklist-card-actions {
            display: flex;
            gap: 8px;
            margin-top: auto;
            justify-content: flex-end;
            padding-top: 15px;
            border-top: 1px solid var(--color-light-border);
        }
        .action-button {
            background-color: var(--color-success);
            color: var(--color-text-on-dark);
            border: none;
            padding: 9px 18px;
            font-size: 0.9em;
            font-weight: 600;
            font-family: var(--font-primary);
            border-radius: 20px;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.15s ease-out, box-shadow 0.15s ease-out;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .action-button:hover {
            background-color: var(--color-success-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2);
        }
        .action-button.secondary {
            background-color: var(--color-secondary);
        }
        .action-button.secondary:hover {
            background-color: #5a6268;
        }
        .action-button.warning {
            background-color: var(--color-warning);
            color: var(--color-text-dark);
        }
        .action-button.warning:hover {
            background-color: var(--color-warning-dark);
        }
        .action-button.danger {
            background-color: var(--color-error);
        }
        .action-button.danger:hover {
            background-color: var(--color-error-dark);
        }

        .no-content-message {
            flex-grow: 1; overflow-y: hidden; margin-top: 0;
            text-align: center; color: var(--color-text-light);
            padding: 20px 0; display:flex; align-items:center; justify-content:center; height:100%;
        }

        /* Custom Message Box (replacing alert()) */
        .custom-message-box-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            z-index: 2000;
            backdrop-filter: blur(3px);
            animation: fadeInModal 0.2s ease-out;
        }

        .custom-message-box-content {
            background-color: var(--color-bg-white);
            padding: 25px 30px;
            border-radius: var(--border-radius);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            width: 90%;
            text-align: center;
            animation: slideInModal 0.2s ease-out;
            border: 1px solid var(--color-light-border);
            position: relative;
        }

        .custom-message-box-content p {
            font-size: 1.1em;
            color: var(--color-text-dark);
            margin-bottom: 20px;
        }

        .message-box-close-btn {
            background-color: var(--color-primary);
            color: var(--color-text-on-dark);
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s ease, transform 0.1s ease;
            font-family: var(--font-primary);
        }

        .message-box-close-btn:hover {
            background-color: var(--color-primary-dark);
            transform: translateY(-1px);
        }
        @keyframes slideInModal {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* MODALS */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(52, 58, 64, 0.7); justify-content: center; align-items: center;
            z-index: 1050; padding: 15px; box-sizing: border-box; backdrop-filter: blur(4px);
            animation: fadeInModal 0.25s ease-out;
        }
        @keyframes fadeInModal { from { opacity: 0; } to { opacity: 1; } }
        .modal-content {
            background-color: var(--color-bg-white); padding: 25px 30px; border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); max-width: 900px; width: 95%; max-height: 90vh;
            display: flex; flex-direction: column; animation: scaleUpModal 0.25s ease-out forwards;
            border: 1px solid var(--color-light-border);
        }
        @keyframes scaleUpModal { from { transform: scale(0.97); opacity: 0.8; } to { transform: scale(1); opacity: 1; } }
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
        .modal-body { margin-bottom: 20px; flex-grow: 1; overflow-y: auto; }
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

        /* Checklist Builder Modal Specific Styles */
        #checklist-builder-modal .modal-content {
            max-width: 900px;
            width: 95%;
        }

        #checklist-builder-modal .form-group {
            margin-bottom: 15px;
        }
        #checklist-builder-modal textarea.auth-input {
            resize: vertical;
            min-height: 60px;
        }
        #checklist-builder-modal .section-builder,
        #checklist-builder-modal .question-builder {
            background-color: #f8f8f8;
            border: 1px solid var(--color-light-border);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 15px;
        }
        #checklist-builder-modal .section-builder-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: var(--color-primary-dark);
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px dashed var(--color-light-border);
        }
        #checklist-builder-modal .question-builder {
            margin-left: 20px; /* Indent questions */
            background-color: #fcfcfc;
        }
        #checklist-builder-modal .question-builder .form-group {
            margin-bottom: 10px;
        }
        #checklist-builder-modal .question-builder-actions,
        #checklist-builder-modal .section-builder-actions {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
            margin-top: 10px;
        }
        #checklist-builder-modal .add-question-btn,
        #checklist-builder-modal .add-section-btn {
            background-color: var(--color-info);
            color: var(--color-text-on-dark);
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.85em;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        #checklist-builder-modal .add-question-btn:hover,
        #checklist-builder-modal .add-section-btn:hover {
            background-color: var(--color-info-dark);
        }
        #checklist-builder-modal .remove-btn {
            background-color: var(--color-error);
            color: var(--color-text-on-dark);
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 0.8em;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        #checklist-builder-modal .remove-btn:hover {
            background-color: var(--color-error-dark);
        }
        #checklist-builder-modal .importance-select {
            padding: 8px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            font-size: 0.9em;
            height: 38px; /* Match input height */
        }
        #checklist-builder-modal .question-text-input,
        #checklist-builder-modal .segment-title-input {
            flex-grow: 1;
        }
        #checklist-builder-modal .importance-selection-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        #checklist-builder-modal .importance-selection-group label {
            margin-bottom: 0;
            white-space: nowrap;
        }
        #checklist-builder-modal .question-row {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            margin-bottom: 10px;
        }
        #checklist-builder-modal .question-row > div {
            flex-grow: 1;
        }
        #checklist-builder-modal .question-row .form-group {
            margin-bottom: 0;
        }
        #checklist-builder-modal .segment-header-builder {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            margin-bottom: 15px;
        }
        #checklist-builder-modal .segment-header-builder > div {
            flex-grow: 1;
        }
        #checklist-builder-modal .question-input-container {
             display: grid;
             grid-template-columns: 2fr 1fr; /* Question text and importance */
             gap: 10px;
             align-items: flex-end;
             width: 100%;
        }


        /* Styles for the actual checklist being filled out and report */
        .checklist-runner-container,
        .report-section {
            background-color: var(--color-bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid var(--color-light-border);
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            display: none; /* Hidden by default */
        }
        .checklist-runner-container h2,
        .report-section h2 {
            text-align: center;
            margin-bottom: 20px;
            color: var(--color-primary-dark);
            font-size: 1.8em;
            border-bottom: 1px solid var(--color-border);
            padding-bottom: 10px;
        }

        .header-info-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--color-border);
        }
        .header-info-section .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .header-info-section label {
            font-weight: 500;
            color: var(--color-primary-dark);
            font-size: 0.9em;
        }
        .header-info-section input[type="text"] {
            padding: 8px 12px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            font-size: 0.9em;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
        }
        .header-info-section input[type="text"]:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 2px var(--color-primary-xtralight);
            outline: none;
        }

        .segment-section-runner {
            border: 1px solid var(--color-light-border);
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            overflow: hidden;
        }
        .segment-header-runner {
            background-color: var(--color-primary-xtralight);
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: var(--font-primary);
            font-weight: 600;
            color: var(--color-primary-dark);
            font-size: 1.1em;
            border-bottom: 1px solid var(--color-light-border);
            transition: background-color var(--transition-speed);
        }
        .segment-header-runner:hover {
            background-color: #DDEBF9;
        }
        .segment-header-runner i {
            transition: transform var(--transition-speed);
        }
        .segment-header-runner.collapsed i {
            transform: rotate(-90deg);
        }
        .segment-content-runner {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out, padding 0.3s ease-out;
        }
        .segment-content-runner.expanded {
            max-height: 2000px; /* Arbitrary large value for expansion */
            padding: 15px 20px;
        }

        .question-item-runner {
            display: grid;
            grid-template-columns: 1fr auto auto auto 150px; /* Question, C, NC, NSA, Obs, Importance */
            gap: 10px;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed var(--color-light-border);
            font-size: 0.9em;
        }
        .question-item-runner:last-child {
            border-bottom: none;
        }
        .question-text-runner {
            color: var(--color-text-dark);
            font-weight: 500;
            text-align: left;
        }
        .question-options-runner {
            display: flex;
            gap: 8px;
        }
        .question-options-runner label {
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 20px;
            border: 1px solid var(--color-secondary-light);
            color: var(--color-secondary);
            transition: all var(--transition-speed);
            font-size: 0.8em;
            font-weight: 500;
            white-space: nowrap;
        }
        .question-options-runner input[type="radio"] {
            display: none;
        }
        .question-options-runner input[type="radio"]:checked + label {
            background-color: var(--color-primary);
            color: var(--color-text-on-dark);
            border-color: var(--color-primary-dark);
        }
        .question-options-runner input[type="radio"][value="C"]:checked + label { background-color: var(--color-success); border-color: var(--color-success-dark); }
        .question-options-runner input[type="radio"][value="NC"]:checked + label { background-color: var(--color-error); border-color: var(--color-error-dark); }
        .question-options-runner input[type="radio"][value="NSA"]:checked + label { background-color: var(--color-info); border-color: var(--color-info-dark); }

        .question-obs-runner textarea {
            width: 100%;
            min-height: 30px;
            padding: 6px 8px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            font-size: 0.85em;
            resize: vertical;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
        }
        .question-obs-runner textarea:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 2px var(--color-primary-xtralight);
            outline: none;
        }
        .question-importance-runner {
            font-size: 0.75em;
            font-weight: 600;
            color: var(--color-text-light);
            text-align: center;
            white-space: nowrap;
        }
        .importance-I { color: var(--color-error-dark); }
        .importance-N { color: var(--color-warning-dark); }
        .importance-R { color: var(--color-info-dark); }
        .importance-INF { color: var(--color-secondary); }


        .form-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--color-border);
        }

        .report-section {
            margin-top: 30px;
        }
        .report-section h3 {
            text-align: left;
            margin-bottom: 15px;
            color: var(--color-text-dark);
        }
        .report-header-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .report-header-summary div {
            background-color: var(--color-primary-xtralight);
            padding: 12px 15px;
            border-radius: var(--border-radius);
            border: 1px solid var(--color-primary-light);
            font-size: 0.95em;
            font-weight: 500;
            color: var(--color-primary-dark);
        }
        .report-header-summary div span {
            font-weight: 700;
            color: var(--color-text-dark);
            margin-left: 5px;
        }

        .report-segment-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .report-segment-table th, .report-segment-table td {
            border: 1px solid var(--color-light-border);
            padding: 10px;
            text-align: left;
            font-size: 0.9em;
        }
        .report-segment-table thead th {
            background-color: var(--color-primary-xtralight);
            color: var(--color-primary-dark);
            font-weight: 600;
            font-family: var(--font-primary);
        }
        .report-segment-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .report-segment-table tfoot th, .report-segment-table tfoot td {
            background-color: var(--color-primary-light);
            color: var(--color-text-on-dark);
            font-weight: 700;
        }
        .report-segment-table td:nth-child(2),
        .report-segment-table td:nth-child(3),
        .report-segment-table td:nth-child(4) {
            text-align: center;
            font-weight: 600;
        }

        .final-result-box {
            text-align: center;
            padding: 20px;
            border-radius: var(--border-radius);
            font-family: var(--font-primary);
            font-weight: 700;
            font-size: 1.5em;
            margin-top: 20px;
        }
        .result-approved { background-color: var(--color-success-light); color: var(--color-success-dark); border: 2px solid var(--color-success); }
        .result-approved-restrictions { background-color: var(--color-warning-light); color: var(--color-warning-dark); border: 2px solid var(--color-warning); }
        .result-reproved { background-color: var(--color-error-light); color: var(--color-error-dark); border: 2px solid var(--color-error); }

        .main-footer-bottom {
            text-align: center; padding: 20px; margin-top: auto;
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
            .checklists-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
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
            .sidebar-toggle-button {
                display: block;
                background-color: var(--color-primary-dark);
                color: var(--color-text-on-dark);
                border: none;
                padding: 10px 15px;
                border-radius: var(--border-radius);
                cursor: pointer;
                font-size: 1em;
                margin: 10px auto;
                width: fit-content;
                align-self: center;
            }
            .sidebar-nav {
                display: none;
                padding-top: 10px;
                padding-bottom: 10px;
            }
            .sidebar-nav.active {
                display: flex;
                flex-direction: column;
            }
            .sidebar-nav details summary {
                border-left: none;
                justify-content: center;
            }
            .sidebar-nav details ul {
                padding-left: 15px;
            }
            .sidebar-footer {
                display: flex;
                justify-content: center;
                padding: 15px;
            }

            .content-area {
                padding: 15px;
            }
            .page-main-title {
                font-size: 1.8em;
            }
            .checklists-management-section,
            .checklist-runner-container,
            .report-section {
                padding: 15px;
            }
            .checklists-management-section h2 {
                font-size: 1.8em;
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
            .checklist-card-actions {
                flex-direction: column;
            }
            .header-info-section {
                grid-template-columns: 1fr;
            }
            .question-item-runner {
                grid-template-columns: 1fr;
                gap: 8px;
                padding: 10px 0;
            }
            .question-text-runner { font-size: 0.9em; }
            .question-options-runner { justify-content: flex-start; }
            .question-obs-runner textarea { font-size: 0.8em; }
            .question-importance-runner { text-align: left; margin-top: 5px; }
            .form-actions { flex-direction: column; gap: 10px; }
            .action-button { width: 100%; }
            .report-header-summary { grid-template-columns: 1fr; }
            .report-segment-table th, .report-segment-table td { padding: 8px; font-size: 0.85em; }

            /* Builder Modal Adjustments */
            #checklist-builder-modal .modal-content {
                padding: 15px;
            }
            #checklist-builder-modal .question-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            #checklist-builder-modal .question-input-container {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            #checklist-builder-modal .segment-header-builder {
                flex-direction: column;
                align-items: flex-start;
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
        <!-- Sidebar -->
        <aside class="sidebar">
            <button class="sidebar-toggle-button" id="sidebar-toggle-button">
                <i class="fas fa-bars"></i> Menu
            </button>
            <nav class="sidebar-nav" id="sidebar-nav">
                <a href="home.php" class="sidebar-top-link"><i class="fas fa-home"></i> Página Principal</a>
                <a href="home.php" class="sidebar-top-link" data-platform-link="nutripnae-dashboard-section"><i class="fas fa-tachometer-alt"></i> Dashboard</a>

                <details class="nutripnae-tools" open>
                    <summary><i class="fas fa-school"></i> NutriPNAE</summary>
                    <ul>
                        <details class="nutripnae-tools" style="margin-left: -30px;">
                            <summary style="border-left: none; padding-left: 30px;"><i class="fas fa-clipboard-list" style="color: var(--color-primary);"></i> Gerenciar Cardápios</summary>
                            <ul>
                                <li><a href="index.php"><i class="fas fa-plus" style="color: var(--color-primary);"></i> Novo Cardápio Semanal</a></li>
                                <li><a href="cardapios.php"><i class="fas fa-folder-open" style="color: var(--color-primary);"></i> Meus Cardápios</a></li>
                            </ul>
                        </details>
                        <li><a href="fichastecnicas.php"><i class="fas fa-file-invoice" style="color: var(--color-primary);"></i> Fichas Técnicas</a></li>
                        <li><a href="custos.php"><i class="fas fa-dollar-sign" style="color: var(--color-primary);"></i> Análise de Custos</a></li>
                        <li><a href="checklists.php" class="active"><i class="fas fa-check-square" style="color: var(--color-primary);"></i> Checklists</a></li>
                        <li><a href="remanejamentos.php"><i class="fas fa-random" style="color: var(--color-primary);"></i> Remanejamentos</a></li>
                        <li><a href="nutriespecial.php"><i class="fas fa-child" style="color: var(--color-primary);"></i> Nutrição Especial</a></li>
                        <li><a href="controles.php"><i class="fas fa-cogs" style="color: var(--color-primary);"></i> Outros Controles</a></li>
                    </ul>
                </details>

                <details open class="nutrigestor-tools">
                    <summary><i class="fas fa-concierge-bell"></i> NutriGestor</summary>
                    <ul>
                        <li><a href="home.php" data-platform-link="nutrigestor-dashboard-section"><i class="fas fa-chart-line"></i> Dashboard Gestor</a></li>
                        <li><a href="nutrigestor-cardapios.php"><i class="fas fa-clipboard-list"></i> Gerenciar Cardápios</a></li>
                        <li><a href="nutrigestor-fichastecnicas.php"><i class="fas fa-file-invoice"></i> Fichas Técnicas</a></li>
                        <li><a href="nutrigestor-custos.php"><i class="fas fa-dollar-sign"></i> Cálculo de Custos</a></li>
                        <li><a href="nutrigestor-pedidos.php"><i class="fas fa-shopping-basket"></i> Controle de Pedidos</a></li>
                        <li><a href="nutrigestor-cmv.php"><i class="fas fa-calculator"></i> CMV e Margem</a></li>
                    </ul>
                </details>

                <details open class="nutridev-tools">
                    <summary><i class="fas fa-laptop-code"></i> NutriDEV (em breve)</summary>
                    <ul>
                        <li><a href="home.php" data-platform-link="nutridev-dashboard-section"><i class="fas fa-terminal"></i> Autonomia Digital</a></li>
                        <li><a href="nutridev-templates.php"><i class="fas fa-layer-group"></i> Modelos Personalizáveis</a></li>
                    </ul>
                </details>

                <a href="ajuda.php" class="sidebar-top-link"><i class="fas fa-question-circle"></i> Ajuda e Suporte</a>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <main class="content-area">
            <div class="container">
                <h1 class="page-main-title">Gerenciar Meus Checklists</h1>
                <p class="page-main-subtitle">Crie, edite e organize seus checklists personalizados ou use modelos prontos.</p>

                <section class="checklists-management-section">
                    <h2><i class="fas fa-check-square"></i> Meus Checklists Salvos</h2>
                    <div class="checklist-card-actions" style="border-top: none; padding-top: 0; justify-content: flex-start;">
                        <button type="button" id="new-checklist-btn" class="action-button"><i class="fas fa-plus"></i> Novo Checklist</button>
                        <button type="button" id="load-anvisa-template-btn" class="action-button secondary"><i class="fas fa-file-alt"></i> Carregar Modelo ANVISA</button>
                    </div>
                    <div class="checklists-grid" id="user-checklists-list">
                        <p id="no-checklists-msg" class="no-content-message">Você ainda não possui checklists salvos. Crie um novo ou carregue um modelo!</p>
                        <!-- Checklists do usuário serão renderizados aqui pelo JS -->
                    </div>
                </section>

                <!-- Seção para executar o checklist e gerar relatório -->
                <section class="checklist-runner-container" id="checklist-runner-section">
                    <h2 id="runner-checklist-title"></h2>
                    <p id="runner-checklist-description" style="text-align: center; color: var(--color-text-light); margin-bottom: 25px;"></p>

                    <form id="active-checklist-form">
                        <div class="header-info-section" id="runner-header-fields">
                            <!-- Campos de cabeçalho do checklist serão inseridos aqui -->
                        </div>

                        <div id="runner-checklist-segments">
                            <!-- Segmentos e perguntas do checklist serão inseridos aqui -->
                        </div>

                        <div class="form-actions">
                            <button type="button" id="generate-report-btn-runner" class="action-button generate-report">
                                <i class="fas fa-file-alt"></i> Gerar Relatório
                            </button>
                            <button type="button" id="clear-form-btn-runner" class="action-button clear-form">
                                <i class="fas fa-eraser"></i> Limpar Respostas
                            </button>
                            <button type="button" id="close-runner-btn" class="action-button secondary"><i class="fas fa-times"></i> Fechar Checklist</button>
                        </div>
                    </form>
                </section>

                <!-- Seção de Relatório (Hidden by default) -->
                <div id="report-section" class="report-section">
                    <h2>Relatório de Conformidade</h2>
                    <div class="report-header-summary">
                        <div>Empresa: <span id="report-firma"></span></div>
                        <div>Denominação: <span id="report-denominacao"></span></div>
                        <div>Responsável: <span id="report-responsavel"></span></div>
                        <div>Data da Auditoria: <span id="report-date"></span></div>
                    </div>

                    <h3>Resumo por Segmento</h3>
                    <table class="report-segment-table">
                        <thead>
                            <tr>
                                <th>Segmento</th>
                                <th>Itens Total</th>
                                <th>Pontuação Alcançada</th>
                                <th>Não se Aplica (NSA)</th>
                            </tr>
                        </thead>
                        <tbody id="report-segment-tbody">
                        </tbody>
                        <tfoot>
                            <tr>
                                <th>TOTAL GERAL</th>
                                <td id="report-total-questions">0</td>
                                <td id="report-total-achieved">0</td>
                                <td id="report-total-nsa">0</td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="final-result-box" id="report-final-result">
                    </div>
                    
                    <button type="button" id="print-report-btn" class="action-button generate-report" style="margin-top: 20px; width: fit-content; align-self: center;">
                        <i class="fas fa-print"></i> Imprimir Relatório
                    </button>
                </div>
            </div>
        </main>
    </div>

    <!-- Modals -->

    <!-- Modal para Criar/Editar Checklist -->
    <div id="checklist-builder-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="builder-modal-title">Criar Novo Checklist</h2>
                <button type="button" class="modal-close-btn" title="Fechar">×</button>
            </div>
            <div class="modal-body">
                <form id="checklist-builder-form">
                    <input type="hidden" id="builder-checklist-id">
                    <div class="form-group">
                        <label for="builder-checklist-title">Título do Checklist:</label>
                        <input type="text" id="builder-checklist-title" class="auth-input" required maxlength="150">
                    </div>
                    <div class="form-group">
                        <label for="builder-checklist-description">Descrição:</label>
                        <textarea id="builder-checklist-description" class="auth-input" rows="3"></textarea>
                    </div>

                    <hr style="margin: 25px 0;">
                    <h3>Campos de Cabeçalho (Informações da Auditoria/Aplicação)</h3>
                    <div id="builder-header-fields-container">
                        <!-- Campos de cabeçalho serão adicionados dinamicamente aqui -->
                        <p class="no-content-message" style="margin: 15px 0; font-size: 0.9em; padding: 0;">Nenhum campo de cabeçalho adicionado.</p>
                    </div>
                    <button type="button" id="add-header-field-btn" class="action-button secondary" style="background-color: var(--color-info); margin-bottom: 20px;">
                        <i class="fas fa-plus"></i> Adicionar Campo de Cabeçalho
                    </button>

                    <hr style="margin: 25px 0;">
                    <h3>Segmentos e Perguntas do Checklist</h3>
                    <div id="builder-segments-container">
                        <!-- Segmentos e perguntas serão adicionados dinamicamente aqui -->
                        <p class="no-content-message" style="margin: 15px 0; font-size: 0.9em; padding: 0;">Nenhum segmento adicionado.</p>
                    </div>
                    <button type="button" id="add-segment-btn" class="action-button secondary" style="background-color: var(--color-primary); margin-top: 10px;">
                        <i class="fas fa-plus"></i> Adicionar Segmento
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-button cancel modal-close-btn">Cancelar</button>
                <button type="button" id="save-checklist-btn" class="modal-button confirm">Salvar Checklist</button>
            </div>
        </div>
    </div>

    <!-- Modal para Confirmação de Exclusão -->
    <div id="delete-confirmation-modal" class="custom-message-box-overlay">
        <div class="custom-message-box-content">
            <p id="delete-message-text"></p>
            <div style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">
                <button class="modal-button cancel" id="cancel-delete-btn">Cancelar</button>
                <button class="modal-button danger" id="confirm-delete-btn">Excluir</button>
            </div>
        </div>
    </div>


    <footer class="main-footer-bottom">
        <p>© <?php echo date("Y"); ?> NutriPNAE. Todos os direitos reservados.</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script type="module">
        // Firebase imports
        import { initializeApp } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-app.js";
        import { getAuth, signInAnonymously, signInWithCustomToken, onAuthStateChanged } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-auth.js";
        import { getFirestore, collection, doc, setDoc, getDoc, deleteDoc, onSnapshot, query, serverTimestamp } from "https://www.gstatic.com/firebasejs/11.6.1/firebase-firestore.js";

        // Global variables for Firebase config from PHP (Canvas environment)
        const appId = typeof __app_id !== 'undefined' ? __app_id : 'default-app-id';
        const firebaseConfig = typeof __firebase_config !== 'undefined' ? JSON.parse(__firebase_config) : {};

        let db;
        let auth;
        let userId; // Will hold the authenticated user's ID

        const anvisaChecklistData = <?php echo $anvisa_checklist_data_json; ?>;

        // Custom Message Box Function (replaces alert() and confirm())
        function displayMessageBox(message, isConfirm = false, callback = null) {
            const $overlay = $('#custom-message-box-overlay');
            const $messageText = $('#message-box-text');
            const $closeBtn = $overlay.find('.message-box-close-btn');

            $messageText.html(message); // Allows HTML for bold/styling

            // Remove any existing cancel button if present
            $overlay.find('.modal-button.cancel').remove();

            if (isConfirm) {
                // For confirmation, add a cancel button and adjust text
                const $cancelBtn = $('<button class="modal-button cancel" style="margin-right: 10px;">Cancelar</button>');
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
                // For simple alerts, just an OK button
                $closeBtn.text('OK').css('background-color', 'var(--color-primary)').off('click').on('click', () => {
                    $overlay.fadeOut(150, () => { if (callback) callback(); });
                });
            }
            $overlay.css('display', 'flex').hide().fadeIn(200);
        }

        $(document).ready(async function() {
            console.log("Checklists JS carregado.");

            // Initialize Firebase
            try {
                const app = initializeApp(firebaseConfig);
                db = getFirestore(app);
                auth = getAuth(app);

                // Sign in with custom token or anonymously
                if (typeof __initial_auth_token !== 'undefined') {
                    await signInWithCustomToken(auth, __initial_auth_token);
                } else {
                    await signInAnonymously(auth);
                }

                // Listen for auth state changes
                onAuthStateChanged(auth, (user) => {
                    if (user) {
                        userId = user.uid;
                        console.log("Usuário Firebase autenticado. UID:", userId);
                        loadUserChecklists(); // Load checklists after successful authentication
                    } else {
                        console.log("Nenhum usuário Firebase autenticado.");
                        // Handle unauthenticated state if necessary (e.g., redirect to login or show limited view)
                    }
                });
            } catch (e) {
                console.error("Erro ao inicializar Firebase ou autenticar:", e);
                displayMessageBox("Erro ao carregar o aplicativo. Tente recarregar a página.");
            }

            // --- Helper Functions ---
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

            function generateUniqueId() {
                return 'chk_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            }

            function sanitizeString(str) {
                if (typeof str !== 'string') return '';
                return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/[^a-z0-9\s]/g, '');
            }

            // --- Modal Functions ---
            const $builderModal = $('#checklist-builder-modal');
            const $deleteConfirmModal = $('#delete-confirmation-modal');
            const $runnerSection = $('#checklist-runner-section');
            const $reportSection = $('#report-section');

            function openModal(modalJQueryObject) {
                modalJQueryObject.css('display', 'flex').hide().fadeIn(200);
                modalJQueryObject.find('input:visible:not([type="hidden"]), textarea:visible, select:visible').first().focus();
            }

            function closeModal(modalJQueryObject) {
                modalJQueryObject.fadeOut(150, function() { $(this).css('display', 'none'); });
            }

            $(document).on('keydown', function(e) { if (e.key === "Escape") { $('.modal-overlay:visible').last().each(function() { closeModal($(this)); }); } });
            $('.modal-overlay').on('click', function(e) { if ($(e.target).is(this)) { closeModal($(this)); } });
            $('.modal-close-btn').on('click', function() { closeModal($(this).closest('.modal-overlay')); });

            // --- Sidebar Toggle Functionality (from home.php) ---
            const $sidebarToggleButton = $('#sidebar-toggle-button');
            const $sidebarNav = $('#sidebar-nav');
            const $platformSections = $('.platform-section-wrapper'); // Not directly used in this page, but kept for consistency

            $sidebarToggleButton.on('click', function() {
                $sidebarNav.toggleClass('active');
                if ($sidebarNav.hasClass('active')) {
                    $(this).html('<i class="fas fa-times"></i> Fechar');
                } else {
                    $(this).html('<i class="fas fa-bars"></i> Menu');
                }
            });

            // Re-check sidebar visibility on resize
            function checkSidebarToggleVisibility() {
                if (window.innerWidth <= 768) {
                    $sidebarToggleButton.show();
                } else {
                    $sidebarToggleButton.hide();
                    $sidebarNav.removeClass('active');
                    $sidebarToggleButton.html('<i class="fas fa-bars"></i> Menu');
                }
            }
            checkSidebarToggleVisibility();
            $(window).on('resize', checkSidebarToggleVisibility);

            // --- Checklist Management List ---
            const $userChecklistsList = $('#user-checklists-list');
            const $noChecklistsMsg = $('#no-checklists-msg');

            // Listen for real-time updates to checklists from Firestore
            function loadUserChecklists() {
                if (!db || !userId) {
                    console.warn("Firestore ou UserID não disponíveis para carregar checklists.");
                    return;
                }
                const q = query(collection(db, `artifacts/${appId}/users/${userId}/checklists`));
                onSnapshot(q, (snapshot) => {
                    const checklists = [];
                    snapshot.forEach((doc) => {
                        checklists.push(doc.data());
                    });
                    renderUserChecklists(checklists);
                }, (error) => {
                    console.error("Erro ao carregar checklists do usuário:", error);
                    displayMessageBox("Erro ao carregar seus checklists. Tente novamente.");
                });
            }

            function renderUserChecklists(checklists) {
                $userChecklistsList.empty();
                if (checklists.length === 0) {
                    $noChecklistsMsg.show();
                    return;
                }
                $noChecklistsMsg.hide();

                // Sort checklists by last updated date
                checklists.sort((a, b) => {
                    const dateA = a.updatedAt ? (a.updatedAt.toDate ? a.updatedAt.toDate().getTime() : new Date(a.updatedAt).getTime()) : 0;
                    const dateB = b.updatedAt ? (b.updatedAt.toDate ? b.updatedAt.toDate().getTime() : new Date(b.updatedAt).getTime()) : 0;
                    return dateB - dateA; // Newest first
                });

                checklists.forEach(checklist => {
                    const lastUpdated = checklist.updatedAt ?
                        (checklist.updatedAt.toDate ? checklist.updatedAt.toDate().toLocaleString('pt-BR') : new Date(checklist.updatedAt).toLocaleString('pt-BR')) :
                        'N/A';
                    const cardHtml = `
                        <div class="checklist-card" data-checklist-id="${htmlspecialchars(checklist.id)}">
                            <div class="checklist-card-header">
                                <h3><i class="fas fa-list-check"></i> ${htmlspecialchars(checklist.title || 'Sem Título')}</h3>
                            </div>
                            <p class="checklist-card-description">${htmlspecialchars(checklist.description || 'Nenhuma descrição.')}</p>
                            <div style="font-size: 0.85em; color: var(--color-text-light); margin-bottom: 10px;">Última atualização: ${lastUpdated}</div>
                            <div class="checklist-card-actions">
                                <button type="button" class="action-button secondary run-checklist-btn"><i class="fas fa-play"></i> Rodar</button>
                                <button type="button" class="action-button warning edit-checklist-btn"><i class="fas fa-pencil-alt"></i> Editar</button>
                                <button type="button" class="action-button secondary duplicate-checklist-btn"><i class="fas fa-copy"></i> Duplicar</button>
                                <button type="button" class="action-button danger delete-checklist-btn"><i class="fas fa-trash"></i> Excluir</button>
                            </div>
                        </div>
                    `;
                    $userChecklistsList.append(cardHtml);
                });
            }

            // --- Checklist Builder (Modal) ---
            const $builderChecklistId = $('#builder-checklist-id');
            const $builderChecklistTitle = $('#builder-checklist-title');
            const $builderChecklistDescription = $('#builder-checklist-description');
            const $builderHeaderFieldsContainer = $('#builder-header-fields-container');
            const $builderSegmentsContainer = $('#builder-segments-container');

            function clearBuilderModal() {
                $('#checklist-builder-form')[0].reset();
                $builderChecklistId.val('');
                $builderModal.find('#builder-modal-title').text('Criar Novo Checklist');
                $builderHeaderFieldsContainer.empty().html('<p class="no-content-message" style="margin: 15px 0; font-size: 0.9em; padding: 0;">Nenhum campo de cabeçalho adicionado.</p>');
                $builderSegmentsContainer.empty().html('<p class="no-content-message" style="margin: 15px 0; font-size: 0.9em; padding: 0;">Nenhum segmento adicionado.</p>');
            }

            $('#new-checklist-btn').on('click', function() {
                clearBuilderModal();
                openModal($builderModal);
            });

            $('#load-anvisa-template-btn').on('click', function() {
                displayMessageBox('Carregar o modelo da ANVISA? Isso apagará o conteúdo atual no formulário de criação/edição.', true, (result) => {
                    if (result) {
                        loadChecklistIntoBuilder(anvisaChecklistData);
                        $builderModal.find('#builder-modal-title').text('Editar Modelo ANVISA');
                        openModal($builderModal);
                    }
                });
            });

            function addHeaderField(fieldData = { id: '', label: '', type: 'text' }) {
                $builderHeaderFieldsContainer.find('.no-content-message').remove(); // Remove placeholder
                const fieldId = fieldData.id || `header_field_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`;
                const html = `
                    <div class="form-group" data-field-id="${fieldId}">
                        <label>ID do Campo (único): <input type="text" class="auth-input field-id" value="${htmlspecialchars(fieldData.id)}" placeholder="Ex: firma_auditoria"></label>
                        <label>Rótulo do Campo: <input type="text" class="auth-input field-label" value="${htmlspecialchars(fieldData.label)}" placeholder="Ex: Nome da Empresa"></label>
                        <label>Tipo do Campo:
                            <select class="auth-input field-type">
                                <option value="text" ${fieldData.type === 'text' ? 'selected' : ''}>Texto Curto</option>
                                <option value="textarea" ${fieldData.type === 'textarea' ? 'selected' : ''}>Texto Longo</option>
                                <option value="number" ${fieldData.type === 'number' ? 'selected' : ''}>Número</option>
                                <option value="date" ${fieldData.type === 'date' ? 'selected' : ''}>Data</option>
                            </select>
                        </label>
                        <button type="button" class="remove-btn remove-header-field-btn"><i class="fas fa-trash"></i> Remover</button>
                    </div>
                `;
                $builderHeaderFieldsContainer.append(html);
            }

            $('#add-header-field-btn').on('click', function() {
                addHeaderField();
            });

            $builderHeaderFieldsContainer.on('click', '.remove-header-field-btn', function() {
                $(this).closest('.form-group').remove();
                 if ($builderHeaderFieldsContainer.children('.form-group').length === 0) {
                    $builderHeaderFieldsContainer.html('<p class="no-content-message" style="margin: 15px 0; font-size: 0.9em; padding: 0;">Nenhum campo de cabeçalho adicionado.</p>');
                }
            });


            function addSegment(segmentData = { id: '', title: '', questions: [], total_points: 0 }) {
                $builderSegmentsContainer.find('.no-content-message').remove(); // Remove placeholder
                const segmentId = segmentData.id || `segment_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`;
                const newSegmentHtml = `
                    <div class="section-builder" data-segment-id="${segmentId}">
                        <div class="segment-header-builder">
                            <div class="form-group" style="flex-grow: 1;">
                                <label for="segment-title-${segmentId}">Título do Segmento:</label>
                                <input type="text" id="segment-title-${segmentId}" class="auth-input segment-title-input" value="${htmlspecialchars(segmentData.title)}" placeholder="Ex: Higienização de Instalações" required>
                            </div>
                            <div class="form-group" style="width: 120px;">
                                <label for="segment-total-points-${segmentId}">Pontos Totais:</label>
                                <input type="number" id="segment-total-points-${segmentId}" class="auth-input segment-total-points-input" value="${segmentData.total_points || ''}" min="0" placeholder="0">
                            </div>
                            <button type="button" class="remove-btn remove-segment-btn"><i class="fas fa-trash"></i> Remover Segmento</button>
                        </div>
                        <div class="questions-list">
                            <!-- Perguntas do segmento serão adicionadas aqui -->
                            <p class="no-content-message-questions" style="margin: 10px 0; font-size: 0.9em; padding: 0 0 0 20px; color: var(--color-text-light);">Nenhuma pergunta adicionada a este segmento.</p>
                        </div>
                        <div class="section-builder-actions" style="border-top: 1px dashed var(--color-light-border); padding-top: 10px; margin-top: 10px; justify-content: flex-start;">
                            <button type="button" class="action-button secondary add-question-btn" style="background-color: var(--color-info);" data-segment-id="${segmentId}">
                                <i class="fas fa-plus"></i> Adicionar Pergunta
                            </button>
                        </div>
                    </div>
                `;
                $builderSegmentsContainer.append(newSegmentHtml);

                const $newSegment = $builderSegmentsContainer.find(`[data-segment-id="${segmentId}"]`);
                if (segmentData.questions && segmentData.questions.length > 0) {
                    segmentData.questions.forEach(q => addQuestionToSegment($newSegment, q));
                }
            }

            $('#add-segment-btn').on('click', function() {
                addSegment();
            });

            $builderSegmentsContainer.on('click', '.remove-segment-btn', function() {
                $(this).closest('.section-builder').remove();
                if ($builderSegmentsContainer.children('.section-builder').length === 0) {
                    $builderSegmentsContainer.html('<p class="no-content-message" style="margin: 15px 0; font-size: 0.9em; padding: 0;">Nenhum segmento adicionado.</p>');
                }
            });

            function addQuestionToSegment($segmentElement, questionData = { id: '', text: '', importance: 'N' }) {
                $segmentElement.find('.no-content-message-questions').remove(); // Remove placeholder
                const questionId = questionData.id || `q_${Date.now()}_${Math.random().toString(36).substr(2, 4)}`;
                const html = `
                    <div class="question-builder" data-question-id="${questionId}">
                        <div class="question-row">
                            <div class="form-group question-input-container">
                                <label for="question-text-${questionId}">Texto da Pergunta:</label>
                                <textarea id="question-text-${questionId}" class="auth-input question-text-input" rows="2" required>${htmlspecialchars(questionData.text)}</textarea>
                            </div>
                            <div class="form-group importance-selection-group" style="flex-shrink: 0;">
                                <label for="question-importance-${questionId}">Importância:</label>
                                <select id="question-importance-${questionId}" class="auth-input importance-select">
                                    <option value="I" ${questionData.importance === 'I' ? 'selected' : ''}>I (Imprescindível)</option>
                                    <option value="N" ${questionData.importance === 'N' ? 'selected' : ''}>N (Necessário)</option>
                                    <option value="R" ${questionData.importance === 'R' ? 'selected' : ''}>R (Recomendável)</option>
                                    <option value="INF" ${questionData.importance === 'INF' ? 'selected' : ''}>INF (Informativo)</option>
                                </select>
                            </div>
                            <button type="button" class="remove-btn remove-question-btn" style="align-self: center;"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                `;
                $segmentElement.find('.questions-list').append(html);
            }

            $builderSegmentsContainer.on('click', '.add-question-btn', function() {
                const $segmentElement = $(this).closest('.section-builder');
                addQuestionToSegment($segmentElement);
            });

            $builderSegmentsContainer.on('click', '.remove-question-btn', function() {
                const $questionBuilder = $(this).closest('.question-builder');
                const $segmentElement = $(this).closest('.section-builder');
                $questionBuilder.remove();
                if ($segmentElement.find('.questions-list').children('.question-builder').length === 0) {
                     $segmentElement.find('.questions-list').html('<p class="no-content-message-questions" style="margin: 10px 0; font-size: 0.9em; padding: 0 0 0 20px; color: var(--color-text-light);">Nenhuma pergunta adicionada a este segmento.</p>');
                }
            });

            function loadChecklistIntoBuilder(checklistData) {
                clearBuilderModal();
                $builderChecklistId.val(checklistData.id || '');
                $builderChecklistTitle.val(checklistData.title || '');
                $builderChecklistDescription.val(checklistData.description || '');

                if (checklistData.header_fields && checklistData.header_fields.length > 0) {
                    $builderHeaderFieldsContainer.empty();
                    checklistData.header_fields.forEach(field => addHeaderField(field));
                }

                if (checklistData.segments && checklistData.segments.length > 0) {
                    $builderSegmentsContainer.empty();
                    checklistData.segments.forEach(segment => addSegment(segment));
                }
            }

            // Save Checklist to Firestore
            $('#save-checklist-btn').on('click', async function() {
                const checklistId = $builderChecklistId.val() || generateUniqueId();
                const title = $builderChecklistTitle.val().trim();
                const description = $builderChecklistDescription.val().trim();

                if (!title) {
                    displayMessageBox('O título do checklist é obrigatório.');
                    $builderChecklistTitle.focus();
                    return;
                }

                const headerFields = [];
                let headerFieldsValid = true;
                $builderHeaderFieldsContainer.find('.form-group').each(function() {
                    const id = $(this).find('.field-id').val().trim();
                    const label = $(this).find('.field-label').val().trim();
                    const type = $(this).find('.field-type').val();
                    if (!id || !label) {
                        displayMessageBox('Todos os campos de cabeçalho devem ter ID e Rótulo preenchidos.');
                        headerFieldsValid = false;
                        return false;
                    }
                    headerFields.push({ id: id, label: label, type: type });
                });
                if (!headerFieldsValid) return;

                const segments = [];
                let segmentsValid = true;
                $builderSegmentsContainer.find('.section-builder').each(function() {
                    const segmentTitle = $(this).find('.segment-title-input').val().trim();
                    const segmentTotalPoints = parseInt($(this).find('.segment-total-points-input').val(), 10);
                    const segmentId = $(this).data('segment-id');

                    if (!segmentTitle) {
                        displayMessageBox('Todos os segmentos devem ter um título.');
                        segmentsValid = false;
                        return false;
                    }
                    if (isNaN(segmentTotalPoints) || segmentTotalPoints < 0) {
                        displayMessageBox('Os pontos totais de um segmento devem ser um número positivo.');
                        segmentsValid = false;
                        return false;
                    }

                    const questions = [];
                    let questionsValid = true;
                    $(this).find('.question-builder').each(function() {
                        const questionText = $(this).find('.question-text-input').val().trim();
                        const importance = $(this).find('.importance-select').val();
                        const questionId = $(this).data('question-id');

                        if (!questionText) {
                            displayMessageBox('Todas as perguntas devem ter um texto.');
                            questionsValid = false;
                            return false;
                        }
                        questions.push({ id: questionId, text: questionText, importance: importance });
                    });
                    if (!questionsValid) { segmentsValid = false; return false; }
                    if (questions.length === 0) {
                        displayMessageBox(`O segmento "${segmentTitle}" não possui perguntas. Adicione pelo menos uma pergunta ou remova o segmento.`);
                        segmentsValid = false;
                        return false;
                    }

                    segments.push({
                        id: segmentId,
                        title: segmentTitle,
                        total_points: segmentTotalPoints,
                        questions: questions
                    });
                });
                if (!segmentsValid) return;

                if (segments.length === 0) {
                    displayMessageBox('Adicione pelo menos um segmento ao checklist.');
                    return;
                }

                const checklistDataToSave = {
                    id: checklistId,
                    title: title,
                    description: description,
                    header_fields: headerFields,
                    segments: segments,
                    importance_mapping: anvisaChecklistData.importance_mapping, // Reuse ANVISA's importance mapping
                    scoring_rules: anvisaChecklistData.scoring_rules, // Reuse ANVISA's scoring rules
                    report_thresholds: anvisaChecklistData.report_thresholds, // Reuse ANVISA's report thresholds
                    createdAt: serverTimestamp(),
                    updatedAt: serverTimestamp(),
                    userId: userId
                };

                try {
                    const checklistRef = doc(db, `artifacts/${appId}/users/${userId}/checklists`, checklistId);
                    await setDoc(checklistRef, checklistDataToSave);
                    displayMessageBox('Checklist salvo com sucesso!');
                    closeModal($builderModal);
                    // loadUserChecklists() will be called by onSnapshot listener automatically
                } catch (e) {
                    console.error("Erro ao salvar checklist:", e);
                    displayMessageBox("Erro ao salvar o checklist. Por favor, tente novamente.");
                }
            });

            // Edit Checklist
            $userChecklistsList.on('click', '.edit-checklist-btn', async function() {
                const checklistId = $(this).closest('.checklist-card').data('checklist-id');
                try {
                    const checklistRef = doc(db, `artifacts/${appId}/users/${userId}/checklists`, checklistId);
                    const checklistSnap = await getDoc(checklistRef);
                    if (checklistSnap.exists()) {
                        const checklistData = checklistSnap.data();
                        loadChecklistIntoBuilder(checklistData);
                        $builderModal.find('#builder-modal-title').text('Editar Checklist');
                        openModal($builderModal);
                    } else {
                        displayMessageBox('Checklist não encontrado.');
                    }
                } catch (e) {
                    console.error("Erro ao carregar checklist para edição:", e);
                    displayMessageBox("Erro ao carregar checklist para edição. Tente novamente.");
                }
            });

            // Duplicate Checklist
            $userChecklistsList.on('click', '.duplicate-checklist-btn', async function() {
                const originalChecklistId = $(this).closest('.checklist-card').data('checklist-id');
                const originalChecklistTitle = $(this).closest('.checklist-card').find('h3').text().replace('<i class="fas fa-list-check"></i>', '').trim();

                displayMessageBox(`Deseja duplicar o checklist "<b>${htmlspecialchars(originalChecklistTitle)}</b>"?`, true, async (result) => {
                    if (result) {
                        try {
                            const originalChecklistRef = doc(db, `artifacts/${appId}/users/${userId}/checklists`, originalChecklistId);
                            const originalChecklistSnap = await getDoc(originalChecklistRef);

                            if (originalChecklistSnap.exists()) {
                                const originalData = originalChecklistSnap.data();
                                const newChecklistId = generateUniqueId();
                                const newChecklistData = {
                                    ...originalData,
                                    id: newChecklistId,
                                    title: `Cópia de ${originalData.title}`,
                                    createdAt: serverTimestamp(),
                                    updatedAt: serverTimestamp(),
                                    userId: userId // Ensure userId is correct for the new document
                                };

                                // Remove doc.id if present in data, as it's set by Firestore
                                delete newChecklistData.docId;

                                const newChecklistRef = doc(db, `artifacts/${appId}/users/${userId}/checklists`, newChecklistId);
                                await setDoc(newChecklistRef, newChecklistData);
                                displayMessageBox('Checklist duplicado com sucesso!');
                                // loadUserChecklists() will update automatically
                            } else {
                                displayMessageBox('Checklist original não encontrado para duplicar.');
                            }
                        } catch (e) {
                            console.error("Erro ao duplicar checklist:", e);
                            displayMessageBox("Erro ao duplicar o checklist. Por favor, tente novamente.");
                        }
                    }
                });
            });

            // Delete Checklist
            $userChecklistsList.on('click', '.delete-checklist-btn', function() {
                const checklistId = $(this).closest('.checklist-card').data('checklist-id');
                const checklistTitle = $(this).closest('.checklist-card').find('h3').text().replace('<i class="fas fa-list-check"></i>', '').trim();

                $deleteConfirmModal.find('#delete-message-text').html(`Tem certeza que deseja excluir o checklist "<b>${htmlspecialchars(checklistTitle)}</b>"? Esta ação é irreversível.`);
                openModal($deleteConfirmModal);

                $('#confirm-delete-btn').off('click').on('click', async function() {
                    try {
                        const checklistRef = doc(db, `artifacts/${appId}/users/${userId}/checklists`, checklistId);
                        await deleteDoc(checklistRef);
                        displayMessageBox('Checklist excluído com sucesso!');
                        closeModal($deleteConfirmModal);
                        // loadUserChecklists() will update automatically
                    } catch (e) {
                        console.error("Erro ao excluir checklist:", e);
                        displayMessageBox("Erro ao excluir o checklist. Por favor, tente novamente.");
                    }
                });
                $('#cancel-delete-btn').off('click').on('click', function() {
                    closeModal($deleteConfirmModal);
                });
            });


            // --- Checklist Runner Functionality ---
            let currentRunningChecklist = null; // Stores the checklist data currently being run

            $userChecklistsList.on('click', '.run-checklist-btn', async function() {
                const checklistId = $(this).closest('.checklist-card').data('checklist-id');
                try {
                    const checklistRef = doc(db, `artifacts/${appId}/users/${userId}/checklists`, checklistId);
                    const checklistSnap = await getDoc(checklistRef);

                    if (checklistSnap.exists()) {
                        currentRunningChecklist = checklistSnap.data();
                        loadChecklistIntoRunner(currentRunningChecklist);
                        $runnerSection.fadeIn();
                        $reportSection.fadeOut(); // Hide report if visible
                        // Scroll to the checklist runner section
                        $('html, body').animate({
                            scrollTop: $runnerSection.offset().top - 50
                        }, 800);
                    } else {
                        displayMessageBox('Checklist não encontrado para execução.');
                    }
                } catch (e) {
                    console.error("Erro ao carregar checklist para execução:", e);
                    displayMessageBox("Erro ao carregar checklist para execução. Tente novamente.");
                }
            });

            $('#close-runner-btn').on('click', function() {
                $runnerSection.fadeOut();
                $reportSection.fadeOut();
                currentRunningChecklist = null; // Clear current checklist
                $('#active-checklist-form')[0].reset(); // Clear form
                // Scroll back to top of checklist list
                $('html, body').animate({
                    scrollTop: $('.checklists-management-section').offset().top - 50
                }, 800);
            });

            function loadChecklistIntoRunner(checklistData) {
                $('#runner-checklist-title').text(checklistData.title || 'Checklist');
                $('#runner-checklist-description').text(checklistData.description || 'Preencha os campos abaixo para realizar a auditoria.');

                const $runnerHeaderFields = $('#runner-header-fields');
                $runnerHeaderFields.empty();
                if (checklistData.header_fields && checklistData.header_fields.length > 0) {
                    checklistData.header_fields.forEach(field => {
                        let inputHtml = '';
                        switch (field.type) {
                            case 'textarea':
                                inputHtml = `<textarea id="header_${htmlspecialchars(field.id)}" name="header_${htmlspecialchars(field.id)}" class="auth-input" rows="2"></textarea>`;
                                break;
                            case 'number':
                                inputHtml = `<input type="number" id="header_${htmlspecialchars(field.id)}" name="header_${htmlspecialchars(field.id)}" class="auth-input">`;
                                break;
                            case 'date':
                                inputHtml = `<input type="date" id="header_${htmlspecialchars(field.id)}" name="header_${htmlspecialchars(field.id)}" class="auth-input">`;
                                break;
                            default: // text
                                inputHtml = `<input type="text" id="header_${htmlspecialchars(field.id)}" name="header_${htmlspecialchars(field.id)}" class="auth-input">`;
                                break;
                        }
                        $runnerHeaderFields.append(`
                            <div class="form-group">
                                <label for="header_${htmlspecialchars(field.id)}">${htmlspecialchars(field.label)}:</label>
                                ${inputHtml}
                            </div>
                        `);
                    });
                }

                const $runnerSegments = $('#runner-checklist-segments');
                $runnerSegments.empty();
                if (checklistData.segments && checklistData.segments.length > 0) {
                    checklistData.segments.forEach(segment => {
                        const segmentHtml = `
                            <div class="segment-section-runner">
                                <div class="segment-header-runner">
                                    <span>${htmlspecialchars(segment.title)}</span>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                                <div class="segment-content-runner expanded">
                                    ${segment.questions.map(question => `
                                        <div class="question-item-runner" data-question-id="${htmlspecialchars(question.id)}">
                                            <div class="question-text-runner">${htmlspecialchars(question.text)}</div>
                                            <div class="question-options-runner">
                                                <input type="radio" id="${htmlspecialchars(question.id)}_C" name="${htmlspecialchars(question.id)}" value="C">
                                                <label for="${htmlspecialchars(question.id)}_C">C</label>
                                                <input type="radio" id="${htmlspecialchars(question.id)}_NC" name="${htmlspecialchars(question.id)}" value="NC">
                                                <label for="${htmlspecialchars(question.id)}_NC">NC</label>
                                                <input type="radio" id="${htmlspecialchars(question.id)}_NSA" name="${htmlspecialchars(question.id)}" value="NSA">
                                                <label for="${htmlspecialchars(question.id)}_NSA">NSA</label>
                                            </div>
                                            <div class="question-obs-runner">
                                                <textarea placeholder="Observação" data-question-id="${htmlspecialchars(question.id)}_obs"></textarea>
                                            </div>
                                            <div class="question-importance-runner importance-${htmlspecialchars(question.importance)}" title="Importância: ${htmlspecialchars(checklistData.importance_mapping[question.importance].label)}. Interdição: ${htmlspecialchars(checklistData.importance_mapping[question.importance].interdiction)}">
                                                ${htmlspecialchars(question.importance)}
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                        $runnerSegments.append(segmentHtml);
                    });
                }
                // Expand all segments by default when loaded
                $runnerSegments.find('.segment-content-runner').addClass('expanded');
            }

            // Toggle segment content visibility in runner
            $runnerSection.on('click', '.segment-header-runner', function() {
                $(this).toggleClass('collapsed');
                $(this).next('.segment-content-runner').toggleClass('expanded');
            });

            // --- Report Generation from Runner ---
            $('#generate-report-btn-runner').on('click', function() {
                if (!currentRunningChecklist) {
                    displayMessageBox('Nenhum checklist ativo para gerar relatório.');
                    return;
                }

                const answers = {};
                const observations = {};

                // Get header info
                currentRunningChecklist.header_fields.forEach(field => {
                    answers[field.id] = $(`#active-checklist-form #header_${field.id}`).val().trim();
                });

                // Get question answers and observations
                currentRunningChecklist.segments.forEach(segment => {
                    segment.questions.forEach(question => {
                        const selectedValue = $(`#active-checklist-form input[name="${question.id}"]:checked`).val();
                        answers[question.id] = selectedValue || ''; // Store 'C', 'NC', 'NSA', or empty if not answered
                        observations[question.id] = $(`#active-checklist-form textarea[data-question-id="${question.id}_obs"]`).val().trim();
                    });
                });

                generateReportFromRunner(answers, observations, currentRunningChecklist);
            });

            function generateReportFromRunner(answers, observations, checklistMeta) {
                let totalQuestionsOverall = 0;
                let totalAchievedOverall = 0;
                let totalNSAOverall = 0;

                const $reportSegmentTbody = $('#report-segment-tbody');
                $reportSegmentTbody.empty(); // Clear previous report rows

                checklistMeta.segments.forEach(segment => {
                    let segmentAchievedPoints = 0;
                    let segmentNSA = 0;

                    segment.questions.forEach(question => {
                        totalQuestionsOverall++; // Count all questions for grand total
                        const answer = answers[question.id];

                        if (answer === 'C') {
                            segmentAchievedPoints += checklistMeta.scoring_rules.C;
                            totalAchievedOverall += checklistMeta.scoring_rules.C;
                        } else if (answer === 'NSA') {
                            segmentNSA++;
                            totalNSAOverall++;
                        }
                    });

                    const rowHtml = `
                        <tr>
                            <td>${htmlspecialchars(segment.title)}</td>
                            <td>${segment.total_points}</td>
                            <td>${segmentAchievedPoints}</td>
                            <td>${segmentNSA}</td>
                        </tr>
                    `;
                    $reportSegmentTbody.append(rowHtml);
                });

                // Update overall totals
                $('#report-total-questions').text(totalQuestionsOverall);
                $('#report-total-achieved').text(totalAchievedOverall);
                $('#report-total-nsa').text(totalNSAOverall);

                // Calculate final overall result
                const effectiveOverallTotal = totalQuestionsOverall - totalNSAOverall;
                let finalPercentage = 0;
                if (effectiveOverallTotal > 0) {
                    finalPercentage = (totalAchievedOverall / effectiveOverallTotal) * 100;
                }

                // Determine classification
                let resultClass = '';
                let resultText = '';
                if (finalPercentage >= checklistMeta.report_thresholds.approved) {
                    resultClass = 'result-approved';
                    resultText = 'APROVADO';
                } else if (finalPercentage >= checklistMeta.report_thresholds.approved_with_restrictions) {
                    resultClass = 'result-approved-restrictions';
                    resultText = 'APROVADO COM RESTRIÇÕES';
                } else {
                    resultClass = 'result-reproved';
                    resultText = 'REPROVADO';
                }

                $('#report-final-result').removeClass().addClass('final-result-box ' + resultClass);
                $('#report-final-result').html(`
                    Resultado Final: <span>${finalPercentage.toFixed(2).replace('.', ',')}%</span> - ${resultText}
                `);

                // Populate header info in report
                $('#report-firma').text(answers.firma || 'N/A');
                $('#report-denominacao').text(answers.denominacao || 'N/A');
                $('#report-responsavel').text(answers.responsavel || 'N/A');
                $('#report-date').text(new Date().toLocaleDateString('pt-BR'));

                $reportSection.fadeIn(); // Show the report
                $('html, body').animate({
                    scrollTop: $reportSection.offset().top - 50
                }, 800); // Scroll to report
            }

            // Clear form in runner
            $('#clear-form-btn-runner').on('click', function() {
                displayMessageBox('Tem certeza que deseja limpar todas as respostas? Esta ação é irreversível para esta sessão.', true, (result) => {
                    if (result) {
                        $('#active-checklist-form')[0].reset();
                        $reportSection.fadeOut(); // Hide report section
                        $runnerSection.find('.segment-header-runner').removeClass('collapsed'); // Expand all segments
                        $runnerSection.find('.segment-content-runner').addClass('expanded'); // Ensure content is expanded
                    }
                });
            });

            // Print Report
            $('#print-report-btn').on('click', function() {
                const printContent = $reportSection.html();
                const originalBody = $('body').html();
                
                // Temporarily replace body content for printing
                $('body').html(`
                    <style>
                        body { font-family: var(--font-secondary); font-size: 12px; color: #333; }
                        .report-section { display: block !important; width: 100%; padding: 20px; box-shadow: none; border: none; }
                        .report-section h2, .report-section h3 { color: #000; text-align: center; margin-bottom: 15px; }
                        .report-header-summary { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; border: 1px solid #ccc; padding: 10px; border-radius: 5px; }
                        .report-header-summary div { background-color: #f0f0f0; padding: 8px; border-radius: 3px; }
                        .report-segment-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                        .report-segment-table th, .report-segment-table td { border: 1px solid #ccc; padding: 8px; text-align: left; }
                        .report-segment-table thead th { background-color: #e0e0e0; font-weight: bold; }
                        .report-segment-table tfoot th, .report-segment-table tfoot td { background-color: #a0a0a0; color: white; font-weight: bold; }
                        .final-result-box { text-align: center; padding: 15px; border-radius: 5px; font-weight: bold; font-size: 1.2em; margin-top: 20px; }
                        .result-approved { background-color: #d4edda; color: #155724; border: 1px solid #28a745; }
                        .result-approved-restrictions { background-color: #fff3cd; color: #856404; border: 1px solid #ffc107; }
                        .result-reproved { background-color: #f8d7da; color: #721c24; border: 1px solid #dc3545; }
                        @media print {
                            .action-button { display: none; }
                        }
                    </style>
                ` + printContent);

                window.print();

                // Restore original body content after printing
                $('body').html(originalBody);
                // Re-attach all event listeners as they are lost when replacing html
                attachAllEventListeners();
            });

            // Function to attach all event listeners (used after print to re-attach)
            function attachAllEventListeners() {
                // Re-attach sidebar toggle
                $sidebarToggleButton.off('click').on('click', function() {
                    $sidebarNav.toggleClass('active');
                    if ($sidebarNav.hasClass('active')) {
                        $(this).html('<i class="fas fa-times"></i> Fechar');
                    } else {
                        $(this).html('<i class="fas fa-bars"></i> Menu');
                    }
                });

                // Re-attach modal close behaviors
                $(document).off('keydown').on('keydown', function(e) { if (e.key === "Escape") { $('.modal-overlay:visible').last().each(function() { closeModal($(this)); }); } });
                $('.modal-overlay').off('click').on('click', function(e) { if ($(e.target).is(this)) { closeModal($(this)); } });
                $('.modal-close-btn').off('click').on('click', function() { closeModal($(this).closest('.modal-overlay')); });

                // Re-attach checklist management buttons (new, load ANVISA)
                $('#new-checklist-btn').off('click').on('click', function() {
                    clearBuilderModal();
                    openModal($builderModal);
                });
                $('#load-anvisa-template-btn').off('click').on('click', function() {
                    displayMessageBox('Carregar o modelo da ANVISA? Isso apagará o conteúdo atual no formulário de criação/edição.', true, (result) => {
                        if (result) {
                            loadChecklistIntoBuilder(anvisaChecklistData);
                            $builderModal.find('#builder-modal-title').text('Editar Modelo ANVISA');
                            openModal($builderModal);
                        }
                    });
                });

                // Re-attach builder modal specific events
                $('#add-header-field-btn').off('click').on('click', function() { addHeaderField(); });
                $builderHeaderFieldsContainer.off('click', '.remove-header-field-btn').on('click', '.remove-header-field-btn', function() {
                    $(this).closest('.form-group').remove();
                    if ($builderHeaderFieldsContainer.children('.form-group').length === 0) {
                        $builderHeaderFieldsContainer.html('<p class="no-content-message" style="margin: 15px 0; font-size: 0.9em; padding: 0;">Nenhum campo de cabeçalho adicionado.</p>');
                    }
                });
                $('#add-segment-btn').off('click').on('click', function() { addSegment(); });
                $builderSegmentsContainer.off('click', '.remove-segment-btn').on('click', '.remove-segment-btn', function() {
                    $(this).closest('.section-builder').remove();
                    if ($builderSegmentsContainer.children('.section-builder').length === 0) {
                        $builderSegmentsContainer.html('<p class="no-content-message" style="margin: 15px 0; font-size: 0.9em; padding: 0;">Nenhum segmento adicionado.</p>');
                    }
                });
                $builderSegmentsContainer.off('click', '.add-question-btn').on('click', '.add-question-btn', function() {
                    const $segmentElement = $(this).closest('.section-builder');
                    addQuestionToSegment($segmentElement);
                });
                $builderSegmentsContainer.off('click', '.remove-question-btn').on('click', '.remove-question-btn', function() {
                    const $questionBuilder = $(this).closest('.question-builder');
                    const $segmentElement = $(this).closest('.section-builder');
                    $questionBuilder.remove();
                    if ($segmentElement.find('.questions-list').children('.question-builder').length === 0) {
                        $segmentElement.find('.questions-list').html('<p class="no-content-message-questions" style="margin: 10px 0; font-size: 0.9em; padding: 0 0 0 20px; color: var(--color-text-light);">Nenhuma pergunta adicionada a este segmento.</p>');
                    }
                });
                $('#save-checklist-btn').off('click').on('click', function() { /* Logic handled above */ });


                // Re-attach list item actions (run, edit, duplicate, delete) - these are re-attached by loadUserChecklists
                // but need explicit re-attachment if the whole body HTML is replaced (e.g., after print)
                // Since loadUserChecklists is called on auth state change, it should be fine as long as `db` and `userId` are set.
                // However, a manual re-render of the list might be needed if state of `checklists` changes after print.
                // For now, trust the onSnapshot listener to keep the list updated.

                // Re-attach runner section buttons
                $('#close-runner-btn').off('click').on('click', function() {
                    $runnerSection.fadeOut();
                    $reportSection.fadeOut();
                    currentRunningChecklist = null;
                    $('#active-checklist-form')[0].reset();
                    $('html, body').animate({ scrollTop: $('.checklists-management-section').offset().top - 50 }, 800);
                });
                $('#generate-report-btn-runner').off('click').on('click', function() { /* Logic handled above */ });
                $('#clear-form-btn-runner').off('click').on('click', function() {
                    displayMessageBox('Tem certeza que deseja limpar todas as respostas? Esta ação é irreversível para esta sessão.', true, (result) => {
                        if (result) {
                            $('#active-checklist-form')[0].reset();
                            $reportSection.fadeOut();
                            $runnerSection.find('.segment-header-runner').removeClass('collapsed');
                            $runnerSection.find('.segment-content-runner').addClass('expanded');
                        }
                    });
                });
                $('#print-report-btn').off('click').on('click', function() { /* Logic handled above */ });

                // Re-attach segment toggles in runner
                $runnerSection.off('click', '.segment-header-runner').on('click', '.segment-header-runner', function() {
                    $(this).toggleClass('collapsed');
                    $(this).next('.segment-content-runner').toggleClass('expanded');
                });

                // Re-attach delete confirmation buttons
                $('#cancel-delete-btn').off('click').on('click', function() { closeModal($deleteConfirmModal); });
                $('#confirm-delete-btn').off('click').on('click', function() { /* Logic handled by delegate in delete handler */ });
            }

            // Initial attachment of event listeners
            attachAllEventListeners();
        });
    </script>
</body>
</html>
