<?php include 'views/includes/menu-helper.php'; ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="index.php?action=dashboard">
            <i class="fas fa-hospital"></i> Clínica SJ
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (isset($menus) && !empty($menus)): ?>
                    <?php foreach ($menus as $menu): ?>
                        <li class="nav-item dropdown dropdown-hover">
                            <a class="nav-link dropdown-toggle px-3 py-2" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="<?php echo $menu['icono']; ?> me-2"></i><?php echo $menu['nombre_menu']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-custom">
                                <?php foreach ($menu['submenus'] as $submenu): ?>
                                    <li>
                                        <a class="dropdown-item py-2" href="index.php?action=<?php echo $submenu['uri_submenu']; ?>">
                                            <i class="<?php echo $submenu['icono']; ?> me-2"></i><?php echo $submenu['nombre_submenu']; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav">
                <li class="nav-item dropdown dropdown-hover">
                    <a class="nav-link dropdown-toggle px-3 py-2" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i><?php echo $_SESSION['nombre_completo'] ?? 'Usuario'; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom">
                        <li><a class="dropdown-item py-2" href="#"><i class="fas fa-user me-2"></i>Mi Perfil</a></li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item py-2" href="index.php?action=logout"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
    /* Estilos para dropdown con hover */
    .dropdown-hover:hover .dropdown-menu {
        display: block;
        margin-top: 0;
    }

    .dropdown-menu-custom {
        border: none;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        border-radius: 8px;
        padding: 8px 0;
        margin-top: 2px;
    }

    .dropdown-item {
        transition: all 0.2s ease;
        border-radius: 4px;
        margin: 0 8px;
    }

    .dropdown-item:hover {
        background-color: #f8f9fa;
        transform: translateX(4px);
    }

    .nav-link {
        transition: all 0.2s ease;
    }

    .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
        border-radius: 4px;
    }

    .navbar-brand {
        transition: all 0.2s ease;
    }

    .navbar-brand:hover {
        transform: scale(1.05);
    }

    /* Animación suave para el dropdown */
    .dropdown-menu {
        opacity: 0;
        transform: translateY(-10px);
        transition: all 0.3s ease;
        display: block;
        visibility: hidden;
    }

    .dropdown-hover:hover .dropdown-menu {
        opacity: 1;
        transform: translateY(0);
        visibility: visible;
    }

    /* Responsive adjustments */
    @media (max-width: 991.98px) {
        .dropdown-hover:hover .dropdown-menu {
            display: none;
        }

        .dropdown-menu {
            opacity: 1;
            transform: none;
            visibility: visible;
        }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Script adicional para mejorar la experiencia del dropdown en hover
    document.addEventListener('DOMContentLoaded', function () {
        const dropdowns = document.querySelectorAll('.dropdown-hover');

        dropdowns.forEach(dropdown => {
            const dropdownMenu = dropdown.querySelector('.dropdown-menu');
            let timeout;

            dropdown.addEventListener('mouseenter', function () {
                clearTimeout(timeout);
                dropdownMenu.classList.add('show');
            });

            dropdown.addEventListener('mouseleave', function () {
                timeout = setTimeout(() => {
                    dropdownMenu.classList.remove('show');
                }, 100);
            });

            // Mantener abierto si el mouse está sobre el menú
            dropdownMenu.addEventListener('mouseenter', function () {
                clearTimeout(timeout);
            });

            dropdownMenu.addEventListener('mouseleave', function () {
                timeout = setTimeout(() => {
                    dropdownMenu.classList.remove('show');
                }, 100);
            });
        });
    });
</script>