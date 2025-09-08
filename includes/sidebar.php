<?php
// Define a página atual para destacar no menu
$current_page = basename($_SERVER['PHP_SELF']);
$current_platform_param = $_GET['platform'] ?? null; // Para home.php
?>
<aside class="sidebar" id="sidebar">
    <nav class="sidebar-nav" id="sidebar-nav">
        <!-- Botão de Minimizar/Expandir Menu -->
        <a href="#" class="sidebar-toggle-button" id="sidebar-toggle-button">
            <i class="fas fa-chevron-left"></i> <span>Minimizar Menu</span>
        </a>

        <!-- NutriPNAE Tools Section -->
        <details class="nutripnae-tools" open>
            <summary><i class="fas fa-school"></i> <span>NutriPNAE</span></summary>
            <ul>
                <!-- Links diretos para Cardápios -->
                <li><a href="index.php" class="<?php echo ($current_page == 'index.php' ? 'active' : ''); ?>"><i class="fas fa-plus" style="color: var(--color-primary);"></i> <span>Novo Cardápio Semanal</span></a></li>
                <li><a href="cardapios.php" class="<?php echo ($current_page == 'cardapios.php' ? 'active' : ''); ?>"><i class="fas fa-folder-open" style="color: var(--color-primary);"></i> <span>Meus Cardápios</span></a></li>
                
                <li><a href="fichastecnicas.php" class="<?php echo ($current_page == 'fichastecnicas.php' ? 'active' : ''); ?>"><i class="fas fa-file-invoice" style="color: var(--color-primary);"></i> <span>Fichas Técnicas</span></a></li>
                <li><a href="custos.php" class="<?php echo ($current_page == 'custos.php' ? 'active' : ''); ?>"><i class="fas fa-dollar-sign" style="color: var(--color-primary);"></i> <span>Análise de Custos</span></a></li>
                <li><a href="checklists.php" class="<?php echo ($current_page == 'checklists.php' ? 'active' : ''); ?>"><i class="fas fa-check-square" style="color: var(--color-primary);"></i> <span>Checklists</span></a></li>
                <li><a href="remanejamentos.php" class="<?php echo ($current_page == 'remanejamentos.php' ? 'active' : ''); ?>"><i class="fas fa-random" style="color: var(--color-primary);"></i> <span>Remanejamentos</span></a></li>
                <li><a href="nutriespecial.php" class="<?php echo ($current_page == 'nutriespecial.php' ? 'active' : ''); ?>"><i class="fas fa-child" style="color: var(--color-primary);"></i> <span>Nutrição Especial</span></a></li>
                <li><a href="controles.php" class="<?php echo ($current_page == 'controles.php' ? 'active' : ''); ?>"><i class="fas fa-cogs" style="color: var(--color-primary);"></i> <span>Outros Controles</span></a></li>
            </ul>
        </details>

        <!-- NutriGestor e NutriDEV removidos do menu lateral conforme seu código -->
        
        <!-- Links de Ajuda e Suporte (mantidos no final, mas sem os outros top-level) -->
        <a href="ajuda.php" class="sidebar-top-link <?php echo ($current_page == 'ajuda.php' ? 'active' : ''); ?>"><i class="fas fa-question-circle"></i> <span>Ajuda e Suporte</span></a>
    </nav>
</aside>