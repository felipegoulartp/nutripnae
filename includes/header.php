<nav class="navbar">
    <div class="container">
        <div class="navbar-brand-group">
            <a href="#" class="navbar-brand pnae" data-platform-id="nutripnae-dashboard-section">
                <i class="fas fa-utensils"></i>NutriPNAE
            </a>
            <a href="#" class="navbar-brand nutrigestor" data-platform-id="nutrigestor-dashboard-section">
                <i class="fas fa-concierge-bell"></i>NutriGestor
            </a>
            <!-- NutriDEV removido do menu superior conforme seu código -->
        </div>
        <div class="navbar-actions">
            <?php if (isset($is_logged_in) && $is_logged_in): ?>
                <span class="user-greeting">Olá, <span style="font-size: 1.2em; font-weight: 700; color: var(--color-accent);"><?php echo htmlspecialchars($logged_username ?? 'Usuário'); ?></span>!</span>
                <a href="ajuda.php" class="btn-header-action"><i class="fas fa-question-circle"></i> Ajuda</a>
                <a href="documentos.php" class="btn-header-action"><i class="fas fa-file-alt"></i> Documentos</a>
                <a href="faleconosco.php" class="btn-header-action"><i class="fas fa-comments"></i> Fale Conosco</a>
                <a href="logout.php" class="btn-header-action logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
            <?php endif; ?>
        </div>
    </div>
</nav>