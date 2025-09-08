$(document).ready(function() {
    console.log("Global JS carregado.");

    // Custom Message Box Function (replaces alert() and confirm())
    window.displayMessageBox = function(message, isConfirm = false, callback = null) {
        const $overlay = $('#custom-message-box-overlay');
        const $messageText = $('#message-box-text');
        const $closeBtn = $overlay.find('.message-box-close-btn');

        $messageText.html(message); // Allows HTML for bold/styling

        // Remove listeners para evitar duplicação e garante que o botão OK seja o único (ou Confirm/Cancel)
        $closeBtn.off('click');
        $overlay.find('.modal-button.cancel').remove(); // Remove o botão de cancelar antigo se existir

        if (isConfirm) {
            const $cancelBtn = $('<button class="modal-button cancel" style="margin-right: 10px;">Cancelar</button>');
            $closeBtn.text('Confirmar').css('background-color', 'var(--color-primary)').on('click', () => {
                $overlay.fadeOut(150, () => {
                    $cancelBtn.remove(); // Remove cancel button when confirmed
                    if (callback) callback(true);
                });
            });
            $cancelBtn.on('click', () => {
                $overlay.fadeOut(150, () => {
                    $cancelBtn.remove();
                    if (callback) callback(false);
                });
            });
            $closeBtn.before($cancelBtn); // Add cancel button before confirm
        } else {
            $closeBtn.text('OK').css('background-color', 'var(--color-primary)').on('click', () => {
                $overlay.fadeOut(150, () => { if (callback) callback(); });
            });
        }

        $overlay.css('display', 'flex').hide().fadeIn(200);
    };


    /* --- Sidebar Toggle Functionality --- */
    const $sidebar = $('#sidebar');
    const $sidebarToggleButton = $('#sidebar-toggle-button');
    // Não há mais um $sidebarToggleContainer separado no HTML atualizado, o botão está dentro do sidebar-nav
    const $sidebarNav = $('#sidebar-nav'); // A navegação inteira é o que se expande/colapsa em mobile

    $sidebarToggleButton.on('click', function() {
        // Em desktop, alterna a classe 'collapsed' no sidebar
        // Em mobile, alterna a classe 'active' no sidebar-nav para mostrar/esconder o menu
        if (window.innerWidth > 1024) { // Comportamento para desktop
            $sidebar.toggleClass('collapsed');
            if ($sidebar.hasClass('collapsed')) {
                $(this).find('span').text('Expandir Menu');
                $(this).find('i').removeClass('fa-chevron-left').addClass('fa-chevron-right');
            } else {
                $(this).find('span').text('Minimizar Menu');
                $(this).find('i').removeClass('fa-chevron-right').addClass('fa-chevron-left');
            }
        } else { // Comportamento para mobile/tablet
            $sidebarNav.toggleClass('active');
            if ($sidebarNav.hasClass('active')) {
                $(this).find('span').text('Fechar Menu');
                $(this).find('i').removeClass('fa-bars').addClass('fa-times');
            } else {
                $(this).find('span').text('Menu');
                $(this).find('i').removeClass('fa-times').addClass('fa-bars');
            }
        }
    });

    // Function to handle platform link navigation (from navbar and sidebar)
    function handlePlatformLink(e) {
        e.preventDefault(); // Prevent default link behavior
        const platformTarget = $(this).data('platform-id') || $(this).data('platform-link');
        // Se for um link de dashboard, vai para home.php com o parâmetro
        if (platformTarget && platformTarget.includes('dashboard-section')) {
            window.location.href = 'home.php?platform=' + platformTarget;
        } else if ($(this).attr('href')) {
            // Para outros links de página diretos, navega normalmente
            window.location.href = $(this).attr('href');
        }
    }

    // Apply event listener to navbar brands and sidebar links
    $('.navbar-brand').on('click', handlePlatformLink);
    $('.sidebar-nav a, .sidebar-nav details summary').on('click', handlePlatformLink);

    // Adjust sidebar toggle button and sidebar state on load and resize
    function checkSidebarToggleVisibility() {
        if (window.innerWidth <= 1024) { // Mobile/Tablet
            $sidebarToggleButton.show(); // Mostra o botão de toggle
            $sidebar.removeClass('collapsed'); // Garante que o sidebar não esteja colapsado em mobile
            $sidebarNav.removeClass('active'); // Garante que o menu esteja fechado por padrão em mobile
            $sidebarToggleButton.html('<i class="fas fa-bars"></i> <span>Menu</span>'); // Texto e ícone padrão para mobile
        } else { // Desktop
            $sidebarToggleButton.show(); // Mostra o botão de toggle
            $sidebarNav.removeClass('active'); // Garante que o menu lateral esteja sempre visível em desktop (não escondido pelo 'active')
            // O estado de colapsado/expandido é mantido pelo JS no desktop
            if ($sidebar.hasClass('collapsed')) {
                $sidebarToggleButton.html('<i class="fas fa-chevron-right"></i> <span>Expandir Menu</span>');
            } else {
                $sidebarToggleButton.html('<i class="fas fa-chevron-left"></i> <span>Minimizar Menu</span>');
            }
        }
    }

    // Initial check and on resize
    checkSidebarToggleVisibility();
    $(window).on('resize', checkSidebarToggleVisibility);

    // Logic to highlight the current page in the sidebar based on URL
    // Esta lógica agora está no PHP do sidebar.php, mas mantemos o JS para casos mais dinâmicos ou se houver necessidade de re-aplicar
    const currentPagePath = window.location.pathname.split('/').pop();
    const urlParams = new URLSearchParams(window.location.search);
    const currentPlatformParam = urlParams.get('platform');

    // Remove active class from all links first to ensure only one is active
    $('.sidebar-nav a').removeClass('active');
    $('.sidebar-nav details summary').removeClass('active'); // Close all details initially

    // Logic para home.php (Dashboard)
    if (currentPagePath === 'home.php') {
        // Se não há parâmetro 'platform' ou é 'nutripnae-dashboard-section', ativa o NutriPNAE
        if (!currentPlatformParam || currentPlatformParam === 'nutripnae-dashboard-section') {
            $('details.nutripnae-tools').prop('open', true).find('summary').addClass('active');
            // Como não há um link direto para "Dashboard" no novo sidebar, ativamos a seção principal
            // Se houver um link "Dashboard" no futuro, ele seria ativado aqui.
        }
        // Não há mais NutriGestor ou NutriDEV no sidebar para ativar aqui, mas a lógica seria similar se voltassem
    } else {
        // Para outras páginas (index.php, cardapios.php, fichastecnicas.php, etc.)
        $('.sidebar-nav a[href="' + currentPagePath + '"]').addClass('active');
        $('.sidebar-nav a[href="' + currentPagePath + '"]').parents('details').prop('open', true).find('summary').addClass('active');
    }

    // Garante que os detalhes pais de qualquer link ativo estejam abertos
    $('.sidebar-nav a.active').parents('details').prop('open', true).find('summary').addClass('active');
});