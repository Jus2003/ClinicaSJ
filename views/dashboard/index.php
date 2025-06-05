<?php
// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Verificar si requiere cambio de contraseña
if (isset($_SESSION['requiere_cambio_contrasena']) && $_SESSION['requiere_cambio_contrasena'] == 1) {
    header('Location: index.php?action=auth/change-password');
    exit;
}

include 'views/includes/header.php';
include 'views/includes/navbar.php'; // Los menús ya se cargan aquí

// Función para obtener saludo según la hora
function getSaludo() {
    $hora = (int) date('H');
    if ($hora >= 6 && $hora < 12) {
        return ['saludo' => 'Buenos días', 'icono' => 'fas fa-sun', 'color' => 'warning'];
    } elseif ($hora >= 12 && $hora < 18) {
        return ['saludo' => 'Buenas tardes', 'icono' => 'fas fa-cloud-sun', 'color' => 'info'];
    } else {
        return ['saludo' => 'Buenas noches', 'icono' => 'fas fa-moon', 'color' => 'dark'];
    }
}

$saludoData = getSaludo();
?>

<div class="container-fluid mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <!-- Header del Dashboard mejorado -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="text-primary fw-bold mb-2">
                        <i class="fas fa-tachometer-alt me-3"></i>Dashboard
                    </h1>
                    <p class="text-muted mb-0 fs-5">Panel de Control - Sistema Clínica SJ</p>
                </div>
                <div class="text-end">
                    <div class="badge bg-light text-dark fs-6 px-3 py-2">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <?php
                        $meses = [
                            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
                            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
                            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
                        ];
                        $dias = [
                            'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles',
                            'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo'
                        ];

                        $fecha_actual = new DateTime();
                        $dia_semana = $dias[$fecha_actual->format('l')];
                        $dia = $fecha_actual->format('d');
                        $mes = $meses[(int) $fecha_actual->format('n')];
                        $año = $fecha_actual->format('Y');

                        echo ucfirst($dia_semana) . ', ' . $dia . ' de ' . $mes . ' de ' . $año;
                        ?>
                    </div>
                    <div class="badge bg-primary fs-6 px-3 py-2 mt-1">
                        <i class="fas fa-clock me-2"></i>
                        <span id="current-time"><?php echo date('H:i:s'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Mensaje de Bienvenida mejorado -->
            <div class="card border-0 shadow mb-4 welcome-card">
                <div class="card-body bg-gradient-primary text-white p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <div class="d-flex align-items-center mb-3">
                                <div class="welcome-icon me-3">
                                    <i class="<?php echo $saludoData['icono']; ?> fa-2x"></i>
                                </div>
                                <div>
                                    <h3 class="mb-1 fw-bold">
                                        <?php echo $saludoData['saludo']; ?>, <?php echo explode(' ', $_SESSION['nombre_completo'])[0]; ?>!
                                    </h3>
                                    <p class="mb-0 opacity-75 fs-5">
                                        Bienvenido de vuelta al sistema
                                    </p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-auto">
                                    <div class="user-info-item">
                                        <i class="fas fa-user-tag me-2"></i>
                                        <span class="fw-semibold"><?php echo $_SESSION['role_name']; ?></span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="user-info-item">
                                        <i class="fas fa-id-badge me-2"></i>
                                        <span>ID: <?php echo $_SESSION['user_id']; ?></span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="user-info-item">
                                        <i class="fas fa-sign-in-alt me-2"></i>
                                        <span>Última sesión: <?php echo isset($_SESSION['ultimo_acceso']) ? date('d/m/Y H:i', strtotime($_SESSION['ultimo_acceso'])) : 'Primera vez'; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 text-center">
                            <div class="user-avatar">
                                <?php
                                $roleIcons = [
                                    'Administrador' => ['icon' => 'fas fa-user-shield', 'color' => 'danger'],
                                    'Médico' => ['icon' => 'fas fa-user-md', 'color' => 'success'],
                                    'Recepcionista' => ['icon' => 'fas fa-user-tie', 'color' => 'info'],
                                    'Paciente' => ['icon' => 'fas fa-user', 'color' => 'warning']
                                ];
                                $roleData = $roleIcons[$_SESSION['role_name']] ?? ['icon' => 'fas fa-user', 'color' => 'secondary'];
                                ?>
                                <div class="avatar-circle bg-white bg-opacity-25">
                                    <i class="<?php echo $roleData['icon']; ?> fa-4x text-white"></i>
                                </div>
                                <div class="mt-3">
                                    <span class="badge bg-white bg-opacity-25 text-white fs-6 px-3 py-2">
                                        <?php echo $_SESSION['role_name']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas rápidas -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card border-0 shadow-sm stats-card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title opacity-75 mb-2">Menús Disponibles</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo count($menus); ?></h3>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-th-large fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card border-0 shadow-sm stats-card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title opacity-75 mb-2">Opciones Totales</h6>
                                    <h3 class="mb-0 fw-bold">
                                        <?php
                                        $totalSubmenus = 0;
                                        foreach ($menus as $menu) {
                                            $totalSubmenus += count($menu['submenus']);
                                        }
                                        echo $totalSubmenus;
                                        ?>
                                    </h3>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-list fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card border-0 shadow-sm stats-card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title opacity-75 mb-2">Sesión Activa</h6>
                                    <h3 class="mb-0 fw-bold" id="session-time">00:00</h3>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-clock fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card border-0 shadow-sm stats-card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title opacity-75 mb-2">Estado Sistema</h6>
                                    <h3 class="mb-0 fw-bold">Online</h3>
                                </div>
                                <div class="stats-icon">
                                    <i class="fas fa-heartbeat fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Menús Disponibles -->
            <?php if (!empty($menus)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1 text-primary fw-bold">
                                    <i class="fas fa-th-large me-3"></i>Opciones Disponibles
                                </h4>
                                <p class="text-muted mb-0">Accede a las funcionalidades según tus permisos</p>
                            </div>
                            <div class="badge bg-primary fs-6 px-3 py-2">
                                <?php echo count($menus); ?> módulo<?php echo count($menus) != 1 ? 's' : ''; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-4">
                            <?php foreach ($menus as $index => $menu): ?>
                                <div class="col-xl-4 col-lg-6 col-md-6">
                                    <div class="card h-100 border-0 shadow-sm menu-card">
                                        <div class="card-header bg-gradient-light border-0 p-3">
                                            <div class="d-flex align-items-center">
                                                <div class="menu-icon me-3">
                                                    <i class="<?php echo $menu['icono']; ?> fa-lg text-primary"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0 fw-semibold text-dark">
                                                        <?php echo $menu['nombre_menu']; ?>
                                                    </h6>
                                                    <small class="text-muted">
                                                        <?php echo count($menu['submenus']); ?> opción<?php echo count($menu['submenus']) != 1 ? 'es' : ''; ?>
                                                    </small>
                                                </div>
                                                <div class="badge bg-light text-dark">
                                                    <?php echo count($menu['submenus']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($menu['submenus'] as $submenu): ?>
                                                    <a href="index.php?action=<?php echo $submenu['uri_submenu']; ?>" 
                                                       class="list-group-item list-group-item-action border-0 py-3 px-3 submenu-item">
                                                        <div class="d-flex align-items-center">
                                                            <div class="submenu-icon me-3">
                                                                <i class="<?php echo $submenu['icono']; ?> text-primary"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <span class="fw-medium text-dark">
                                                                    <?php echo $submenu['nombre_submenu']; ?>
                                                                </span>
                                                            </div>
                                                            <div class="submenu-arrow">
                                                                <i class="fas fa-chevron-right text-muted small"></i>
                                                            </div>
                                                        </div>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <div class="empty-state">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                            <h4 class="text-dark mb-3">Sin Permisos Asignados</h4>
                            <p class="text-muted mb-4">
                                No tienes permisos asignados o no hay menús disponibles para tu rol actual.
                            </p>
                            <div class="alert alert-warning border-0 d-inline-block">
                                <i class="fas fa-info-circle me-2"></i>
                                Contacta al administrador del sistema para obtener los permisos necesarios.
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Accesos rápidos adicionales -->
            <div class="row g-4 mt-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 p-4">
                            <h5 class="mb-0 text-primary fw-semibold">
                                <i class="fas fa-bolt me-2"></i>Accesos Rápidos
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <a href="#" class="btn btn-outline-primary w-100 py-3 quick-action">
                                        <i class="fas fa-user me-2"></i>Mi Perfil
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="index.php?action=auth/change-password" class="btn btn-outline-warning w-100 py-3 quick-action">
                                        <i class="fas fa-key me-2"></i>Cambiar Contraseña
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="#" class="btn btn-outline-info w-100 py-3 quick-action">
                                        <i class="fas fa-question-circle me-2"></i>Ayuda
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="index.php?action=logout" class="btn btn-outline-danger w-100 py-3 quick-action">
                                        <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0 p-4">
                            <h5 class="mb-0 text-primary fw-semibold">
                                <i class="fas fa-info-circle me-2"></i>Información del Sistema
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="system-info">
                                <div class="info-item mb-3">
                                    <small class="text-muted">Versión del Sistema</small>
                                    <div class="fw-semibold">v1.0.0</div>
                                </div>
                                <div class="info-item mb-3">
                                    <small class="text-muted">Último Mantenimiento</small>
                                    <div class="fw-semibold"><?php echo date('d/m/Y'); ?></div>
                                </div>
                                <div class="info-item">
                                    <small class="text-muted">Estado del Servidor</small>
                                    <div class="d-flex align-items-center">
                                        <span class="status-dot bg-success me-2"></span>
                                        <span class="fw-semibold text-success">Operativo</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Gradientes y colores */
    .bg-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .bg-gradient-light {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    /* Tarjeta de bienvenida */
    .welcome-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        overflow: hidden;
    }

    .welcome-icon {
        width: 60px;
        height: 60px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .user-info-item {
        background: rgba(255, 255, 255, 0.15);
        padding: 8px 12px;
        border-radius: 20px;
        font-size: 0.9rem;
    }

    .avatar-circle {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        backdrop-filter: blur(10px);
    }

    /* Tarjetas de estadísticas */
    .stats-card {
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15) !important;
    }

    .stats-icon {
        opacity: 0.3;
    }

    /* Tarjetas de menú */
    .menu-card {
        border-radius: 12px;
        transition: all 0.3s ease;
        overflow: hidden;
    }

    .menu-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.15) !important;
    }

    .menu-icon {
        width: 50px;
        height: 50px;
        background: rgba(102, 126, 234, 0.1);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .submenu-item {
        transition: all 0.2s ease;
        border-left: 3px solid transparent !important;
    }

    .submenu-item:hover {
        background-color: rgba(102, 126, 234, 0.05) !important;
        border-left-color: #667eea !important;
        padding-left: 20px !important;
    }

    .submenu-icon {
        width: 35px;
        height: 35px;
        background: rgba(102, 126, 234, 0.1);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .submenu-arrow {
        transition: transform 0.2s ease;
    }

    .submenu-item:hover .submenu-arrow {
        transform: translateX(5px);
    }

    /* Accesos rápidos */
    .quick-action {
        transition: all 0.2s ease;
        border-radius: 10px;
        font-weight: 500;
    }

    .quick-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }

    /* Estado del sistema */
    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
        }
        70% {
            box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
        }
    }

    /* Estado vacío */
    .empty-state {
        max-width: 400px;
        margin: 0 auto;
    }

    /* Mejoras responsive */
    @media (max-width: 768px) {
        .container-fluid {
            padding-left: 15px;
            padding-right: 15px;
        }

        .avatar-circle {
            width: 80px;
            height: 80px;
        }

        .avatar-circle i {
            font-size: 2rem !important;
        }

        .welcome-icon {
            width: 50px;
            height: 50px;
        }

        .user-info-item {
            font-size: 0.8rem;
            padding: 6px 10px;
        }
    }

    /* Animaciones AOS */
    [data-aos] {
        pointer-events: none;
    }

    [data-aos].aos-animate {
        pointer-events: auto;
    }

    /* Sombras mejoradas */
    .shadow-sm {
        box-shadow: 0 2px 4px rgba(0,0,0,0.06), 0 1px 3px rgba(0,0,0,0.1) !important;
    }

    .shadow {
        box-shadow: 0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.06) !important;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Actualizar reloj en tiempo real
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-ES', {
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('current-time').textContent = timeString;
        }

        // Actualizar cada segundo
        setInterval(updateClock, 1000);

        // Contador de tiempo de sesión
        let sessionStart = new Date();
        function updateSessionTime() {
            const now = new Date();
            const diff = now - sessionStart;
            const minutes = Math.floor(diff / 60000);
            const hours = Math.floor(minutes / 60);
            const displayMinutes = minutes % 60;

            const timeStr = hours > 0
                    ? `${hours.toString().padStart(2, '0')}:${displayMinutes.toString().padStart(2, '0')}`
                    : `${displayMinutes} min`;

            document.getElementById('session-time').textContent = timeStr;
        }

        // Actualizar tiempo de sesión cada minuto
        setInterval(updateSessionTime, 60000);
        updateSessionTime();

        // Efectos de hover mejorados para las tarjetas
        const menuCards = document.querySelectorAll('.menu-card');
        menuCards.forEach(card => {
            card.addEventListener('mouseenter', function () {
                this.style.transform = 'translateY(-8px) scale(1.02)';
            });

            card.addEventListener('mouseleave', function () {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Animación de entrada para las tarjetas
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        });

        // Aplicar animación a todas las tarjetas
        document.querySelectorAll('.card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.6s ease';
            observer.observe(card);
        });

        // Efecto de typewriter para el saludo (opcional)
        const welcomeText = document.querySelector('.welcome-card h3');
        if (welcomeText) {
            const text = welcomeText.textContent;
            welcomeText.textContent = '';
            let i = 0;

            function typeWriter() {
                if (i < text.length) {
                    welcomeText.textContent += text.charAt(i);
                    i++;
                    setTimeout(typeWriter, 50);
                }
            }

            setTimeout(typeWriter, 500);
        }
    });

    // Función para mostrar notificaciones de bienvenida
    function showWelcomeNotification() {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('¡Bienvenido!', {
                body: 'Has iniciado sesión correctamente en el sistema.',
                icon: '/path/to/icon.png'
            });
        }
    }

    // Solicitar permisos de notificación
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                showWelcomeNotification();
            }
        });
    }
</script>

</body>
</html>