<?php
// cardapio_auto/checklistanvisa.php

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
error_log("--- Início checklistanvisa.php --- SESSION_ID: " . session_id());

// 3. Verificação de Autenticação
$is_logged_in = isset($_SESSION['user_id']);
$logged_user_id = $_SESSION['user_id'] ?? null;
$logged_username = $_SESSION['username'] ?? 'Visitante';

if (!$is_logged_in || !$logged_user_id) {
    error_log("checklistanvisa.php: Acesso não autenticado. Redirecionando para login. Session ID: " . session_id());
    header('Location: login.php');
    exit;
}
error_log("checklistanvisa.php: Usuário autenticado. UserID: $logged_user_id, Username: $logged_username.");

// Dados para o checklist (extraídos do documento fornecido)
$checklist_data = [
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

// PHP para JSON para JavaScript
$checklist_data_json = json_encode($checklist_data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checklist ANVISA - RDC 216/2004</title>
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
        .nutrigestor-logo-home .nutrigestor-icon-home { color: var(--error-color); } /* Using error-color for NutriGestor red */
        .nutrigestor-text-prefix-home { color: var(--error-color); font-weight: 700; }
        .nutrigestor-text-suffix-home { color: var(--text-color); font-weight: 700; }
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

        .checklist-container {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid var(--light-border);
            padding: 25px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .header-info-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        .header-info-section .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .header-info-section label {
            font-weight: 500;
            color: var(--primary-dark);
            font-size: 0.9em;
        }
        .header-info-section input[type="text"] {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 0.9em;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
        }
        .header-info-section input[type="text"]:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px var(--primary-xtralight);
            outline: none;
        }

        .segment-section {
            border: 1px solid var(--light-border);
            border-radius: var(--border-radius);
            margin-bottom: 15px;
            overflow: hidden;
        }
        .segment-header {
            background-color: var(--primary-xtralight);
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: var(--font-primary);
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 1.1em;
            border-bottom: 1px solid var(--light-border);
            transition: background-color var(--transition-speed);
        }
        .segment-header:hover {
            background-color: #DDEBF9;
        }
        .segment-header i {
            transition: transform var(--transition-speed);
        }
        .segment-header.collapsed i {
            transform: rotate(-90deg);
        }
        .segment-content {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out, padding 0.3s ease-out;
        }
        .segment-content.expanded {
            max-height: 2000px; /* Arbitrary large value for expansion */
            padding: 15px 20px;
        }

        .question-item {
            display: grid;
            grid-template-columns: 1fr auto auto auto 150px; /* Question, C, NC, NSA, Obs, Importance */
            gap: 10px;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px dashed var(--light-border);
            font-size: 0.9em;
        }
        .question-item:last-child {
            border-bottom: none;
        }
        .question-text {
            color: var(--text-color);
            font-weight: 500;
            text-align: left;
        }
        .question-options {
            display: flex;
            gap: 8px;
        }
        .question-options label {
            cursor: pointer;
            padding: 6px 10px;
            border-radius: 20px;
            border: 1px solid var(--secondary-light);
            color: var(--secondary-color);
            transition: all var(--transition-speed);
            font-size: 0.8em;
            font-weight: 500;
            white-space: nowrap;
        }
        .question-options input[type="radio"] {
            display: none;
        }
        .question-options input[type="radio"]:checked + label {
            background-color: var(--primary-color);
            color: var(--white-color);
            border-color: var(--primary-dark);
        }
        .question-options input[type="radio"][value="C"]:checked + label { background-color: var(--success-color); border-color: var(--success-dark); }
        .question-options input[type="radio"][value="NC"]:checked + label { background-color: var(--error-color); border-color: var(--error-dark); }
        .question-options input[type="radio"][value="NSA"]:checked + label { background-color: var(--info-color); border-color: var(--info-dark); }

        .question-obs textarea {
            width: 100%;
            min-height: 30px;
            padding: 6px 8px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 0.85em;
            resize: vertical;
            transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
        }
        .question-obs textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px var(--primary-xtralight);
            outline: none;
        }
        .question-importance {
            font-size: 0.75em;
            font-weight: 600;
            color: var(--text-light);
            text-align: center;
            white-space: nowrap;
        }
        .importance-I { color: var(--error-dark); }
        .importance-N { color: var(--warning-dark); }
        .importance-R { color: var(--info-dark); }
        .importance-INF { color: var(--secondary-color); }


        .form-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        .action-button {
            padding: 10px 25px;
            font-size: 1em;
            font-weight: 600;
            font-family: var(--font-primary);
            border-radius: 25px;
            border: none;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--box-shadow);
        }
        .action-button:hover {
            box-shadow: var(--box-shadow-hover);
            transform: translateY(-2px);
        }
        .action-button.generate-report {
            background-color: var(--primary-color);
            color: var(--white-color);
        }
        .action-button.generate-report:hover {
            background-color: var(--primary-dark);
        }
        .action-button.clear-form {
            background-color: var(--secondary-color);
            color: var(--white-color);
        }
        .action-button.clear-form:hover {
            background-color: var(--text-color);
        }

        .report-section {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            border: 1px solid var(--light-border);
            padding: 25px;
            margin-top: 30px;
            display: none; /* Hidden by default */
            flex-direction: column;
            gap: 20px;
        }
        .report-section h2 {
            text-align: center;
            margin-bottom: 20px;
            color: var(--primary-dark);
            font-size: 1.8em;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }
        .report-header-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .report-header-summary div {
            background-color: var(--primary-xtralight);
            padding: 12px 15px;
            border-radius: var(--border-radius);
            border: 1px solid var(--primary-light);
            font-size: 0.95em;
            font-weight: 500;
            color: var(--primary-dark);
        }
        .report-header-summary div span {
            font-weight: 700;
            color: var(--text-color);
            margin-left: 5px;
        }

        .report-segment-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .report-segment-table th, .report-segment-table td {
            border: 1px solid var(--light-border);
            padding: 10px;
            text-align: left;
            font-size: 0.9em;
        }
        .report-segment-table thead th {
            background-color: var(--primary-xtralight);
            color: var(--primary-dark);
            font-weight: 600;
            font-family: var(--font-primary);
        }
        .report-segment-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .report-segment-table tfoot th, .report-segment-table tfoot td {
            background-color: var(--primary-light);
            color: var(--white-color);
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
        .result-approved { background-color: var(--success-light); color: var(--success-dark); border: 2px solid var(--success-color); }
        .result-approved-restrictions { background-color: var(--warning-light); color: var(--warning-dark); border: 2px solid var(--warning-color); }
        .result-reproved { background-color: var(--error-light); color: var(--error-dark); border: 2px solid var(--error-color); }

        .main-footer-bottom {
            text-align: center; padding: 20px; margin-top: auto;
            background-color: var(--primary-dark); color: var(--primary-xtralight);
            font-size: 0.9em; border-top: 1px solid var(--primary-color);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-content-wrapper { padding: 15px; }
            .checklist-container, .report-section { padding: 15px; }
            .header-info-section { grid-template-columns: 1fr; }
            .segment-header { font-size: 1em; padding: 12px 15px; }
            .segment-content.expanded { padding: 10px 15px; }
            .question-item {
                grid-template-columns: 1fr; /* Stack elements on small screens */
                gap: 8px;
                padding: 10px 0;
            }
            .question-text { font-size: 0.9em; }
            .question-options { justify-content: flex-start; }
            .question-obs textarea { font-size: 0.8em; }
            .question-importance { text-align: left; margin-top: 5px; }
            .form-actions { flex-direction: column; gap: 10px; }
            .action-button { width: 100%; }
            .report-header-summary { grid-template-columns: 1fr; }
            .report-segment-table th, .report-segment-table td { padding: 8px; font-size: 0.85em; }
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
                    <li><a href="checklistanvisa.php" class="active">Checklist ANVISA</a></li>
                </ul>
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
        <h1 class="page-title">Checklist de Boas Práticas - RDC 216/2004</h1>

        <div class="checklist-container">
            <form id="checklist-form">
                <div class="header-info-section">
                    <?php foreach ($checklist_data['header_fields'] as $field): ?>
                        <div class="form-group">
                            <label for="header_<?php echo htmlspecialchars($field['id']); ?>"><?php echo htmlspecialchars($field['label']); ?>:</label>
                            <input type="<?php echo htmlspecialchars($field['type']); ?>" id="header_<?php echo htmlspecialchars($field['id']); ?>" name="header_<?php echo htmlspecialchars($field['id']); ?>">
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($checklist_data['segments'] as $segment): ?>
                    <div class="segment-section">
                        <div class="segment-header" data-segment-id="<?php echo htmlspecialchars($segment['id']); ?>">
                            <span><?php echo htmlspecialchars($segment['title']); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="segment-content">
                            <?php foreach ($segment['questions'] as $question): ?>
                                <div class="question-item" data-question-id="<?php echo htmlspecialchars($question['id']); ?>">
                                    <div class="question-text"><?php echo htmlspecialchars($question['text']); ?></div>
                                    <div class="question-options">
                                        <input type="radio" id="<?php echo htmlspecialchars($question['id']); ?>_C" name="<?php echo htmlspecialchars($question['id']); ?>" value="C">
                                        <label for="<?php echo htmlspecialchars($question['id']); ?>_C">C</label>
                                        <input type="radio" id="<?php echo htmlspecialchars($question['id']); ?>_NC" name="<?php echo htmlspecialchars($question['id']); ?>" value="NC">
                                        <label for="<?php echo htmlspecialchars($question['id']); ?>_NC">NC</label>
                                        <input type="radio" id="<?php echo htmlspecialchars($question['id']); ?>_NSA" name="<?php echo htmlspecialchars($question['id']); ?>" value="NSA">
                                        <label for="<?php echo htmlspecialchars($question['id']); ?>_NSA">NSA</label>
                                    </div>
                                    <div class="question-obs">
                                        <textarea placeholder="Observação" data-question-id="<?php echo htmlspecialchars($question['id']); ?>_obs"></textarea>
                                    </div>
                                    <div class="question-importance importance-<?php echo htmlspecialchars($question['importance']); ?>" title="Importância: <?php echo htmlspecialchars($checklist_data['importance_mapping'][$question['importance']]['label']); ?>. Interdição: <?php echo htmlspecialchars($checklist_data['importance_mapping'][$question['importance']]['interdiction']); ?>">
                                        <?php echo htmlspecialchars($question['importance']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="button" id="generate-report-btn" class="action-button generate-report">
                        <i class="fas fa-file-alt"></i> Gerar Relatório
                    </button>
                    <button type="button" id="clear-form-btn" class="action-button clear-form">
                        <i class="fas fa-eraser"></i> Limpar Formulário
                    </button>
                </div>
            </form>
        </div>

        <div id="report-section" class="report-section">
            <h2>Relatório de Conformidade ANVISA</h2>
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
                        <th>Itens / Pontuação Total</th>
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

    <footer class="main-footer-bottom">
        <p>© <?php echo date("Y"); ?> NutriPNAE & NutriGestor. Todos os direitos reservados.</p>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script>
        $(document).ready(function() {
            // Data from PHP
            const checklistData = <?php echo $checklist_data_json; ?>;
            console.log("Checklist Data Loaded:", checklistData);

            // Cache DOM elements
            const $checklistForm = $('#checklist-form');
            const $reportSection = $('#report-section');
            const $reportSegmentTbody = $('#report-segment-tbody');
            const $reportTotalQuestions = $('#report-total-questions');
            const $reportTotalAchieved = $('#report-total-achieved');
            const $reportTotalNSA = $('#report-total-nsa');
            const $reportFinalResult = $('#report-final-result');

            // --- Helper Functions ---

            /**
             * Escapes HTML special characters to prevent XSS.
             * @param {string} str The string to escape.
             * @returns {string} The escaped string.
             */
            function htmlspecialchars(str) {
                if (typeof str !== 'string') return '';
                const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
                return str.replace(/[&<>"']/g, function(m) { return map[m]; });
            }

            // --- Checklist Interaction ---

            // Toggle segment content visibility
            $('.segment-header').on('click', function() {
                $(this).toggleClass('collapsed');
                $(this).next('.segment-content').toggleClass('expanded');
            });

            // --- Report Generation ---

            $('#generate-report-btn').on('click', function() {
                const answers = {};
                const observations = {};
                
                // Get header info
                checklistData.header_fields.forEach(field => {
                    answers[field.id] = $(`#header_${field.id}`).val().trim();
                });

                // Get question answers and observations
                checklistData.segments.forEach(segment => {
                    segment.questions.forEach(question => {
                        const selectedValue = $(`input[name="${question.id}"]:checked`).val();
                        answers[question.id] = selectedValue || ''; // Store 'C', 'NC', 'NSA', or empty if not answered
                        observations[question.id] = $(`textarea[data-question-id="${question.id}_obs"]`).val().trim();
                    });
                });

                generateReport(answers, observations);
            });

            function generateReport(answers, observations) {
                let totalQuestionsOverall = 0;
                let totalAchievedOverall = 0;
                let totalNSAOverall = 0;

                $reportSegmentTbody.empty(); // Clear previous report rows

                checklistData.segments.forEach(segment => {
                    let segmentAchievedPoints = 0;
                    let segmentNSA = 0;
                    let segmentTotalCheckableQuestions = 0; // Questions that are C or NC

                    segment.questions.forEach(question => {
                        totalQuestionsOverall++; // Count all questions for grand total
                        const answer = answers[question.id];

                        if (answer === 'C') {
                            segmentAchievedPoints += checklistData.scoring_rules.C;
                            totalAchievedOverall += checklistData.scoring_rules.C;
                            segmentTotalCheckableQuestions++;
                        } else if (answer === 'NC') {
                            segmentTotalCheckableQuestions++;
                        } else if (answer === 'NSA') {
                            segmentNSA++;
                            totalNSAOverall++;
                        }
                        // If answer is empty, it's treated as NC for scoring but not counted in segmentTotalCheckableQuestions
                        // For simplicity, we'll count it as NC if not NSA.
                        else { // If not answered, consider it as NC for the purpose of 'checkable' questions
                            segmentTotalCheckableQuestions++;
                        }
                    });

                    // Calculate effective total for the segment
                    const effectiveSegmentTotal = segment.total_points - segmentNSA;
                    const segmentPercentage = (effectiveSegmentTotal > 0) ?
                        ((segmentAchievedPoints / effectiveSegmentTotal) * 100) : 0;

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
                $reportTotalQuestions.text(totalQuestionsOverall);
                $reportTotalAchieved.text(totalAchievedOverall);
                $reportTotalNSA.text(totalNSAOverall);

                // Calculate final overall result
                const effectiveOverallTotal = totalQuestionsOverall - totalNSAOverall;
                let finalPercentage = 0;
                if (effectiveOverallTotal > 0) {
                    finalPercentage = (totalAchievedOverall / effectiveOverallTotal) * 100;
                }

                // Determine classification
                let resultClass = '';
                let resultText = '';
                if (finalPercentage >= checklistData.report_thresholds.approved) {
                    resultClass = 'result-approved';
                    resultText = 'APROVADO';
                } else if (finalPercentage >= checklistData.report_thresholds.approved_with_restrictions) {
                    resultClass = 'result-approved-restrictions';
                    resultText = 'APROVADO COM RESTRIÇÕES';
                } else {
                    resultClass = 'result-reproved';
                    resultText = 'REPROVADO';
                }

                $reportFinalResult.removeClass().addClass('final-result-box ' + resultClass);
                $reportFinalResult.html(`
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

            // --- Form Clearing ---
            $('#clear-form-btn').on('click', function() {
                if (confirm('Tem certeza que deseja limpar todo o formulário? Todas as respostas serão perdidas.')) {
                    $checklistForm[0].reset(); // Resets all form fields
                    $reportSection.fadeOut(); // Hide report section
                    $('.segment-header').removeClass('collapsed'); // Expand all segments
                    $('.segment-content').addClass('expanded'); // Ensure content is expanded
                }
            });

            // --- Print Report ---
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
                            .action-button { display: none; } /* Hide print button in print dialog */
                        }
                    </style>
                ` + printContent);

                window.print();

                // Restore original body content after printing
                $('body').html(originalBody);
                // Re-attach event listeners as they are lost when replacing html
                attachEventListeners();
            });

            // Initial setup: expand all segments
            $('.segment-content').addClass('expanded');

            // Function to attach all event listeners (used after print to re-attach)
            function attachEventListeners() {
                $('.segment-header').off('click').on('click', function() {
                    $(this).toggleClass('collapsed');
                    $(this).next('.segment-content').toggleClass('expanded');
                });
                $('#generate-report-btn').off('click').on('click', function() {
                    const answers = {};
                    const observations = {};
                    checklistData.header_fields.forEach(field => {
                        answers[field.id] = $(`#header_${field.id}`).val().trim();
                    });
                    checklistData.segments.forEach(segment => {
                        segment.questions.forEach(question => {
                            const selectedValue = $(`input[name="${question.id}"]:checked`).val();
                            answers[question.id] = selectedValue || '';
                            observations[question.id] = $(`textarea[data-question-id="${question.id}_obs"]`).val().trim();
                        });
                    });
                    generateReport(answers, observations);
                });
                $('#clear-form-btn').off('click').on('click', function() {
                    if (confirm('Tem certeza que deseja limpar todo o formulário? Todas as respostas serão perdidas.')) {
                        $checklistForm[0].reset();
                        $reportSection.fadeOut();
                        $('.segment-header').removeClass('collapsed');
                        $('.segment-content').addClass('expanded');
                    }
                });
                $('#print-report-btn').off('click').on('click', function() {
                    const printContent = $reportSection.html();
                    const originalBody = $('body').html();
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
                            @media print { .action-button { display: none; } }
                        </style>
                    ` + printContent);
                    window.print();
                    $('body').html(originalBody);
                    attachEventListeners(); // Re-attach after restoring
                });
            }
            attachEventListeners(); // Initial attachment
        });
    </script>
</body>
</html>
