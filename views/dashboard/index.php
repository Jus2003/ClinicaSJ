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
?>

<div class="container-fluid mt-4">
    <!-- Resto del contenido igual pero usando $menus que ya existe -->
    <div class="row">
        <div class="col-12">
            <!-- Header del Dashboard -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-primary">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </h2>
                    <p class="text-muted mb-0">Panel de Control - Sistema Clínica SJ</p>
                </div>
                <div class="text-end">
                    <small class="text-muted">
                        <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i'); ?>
                    </small>
                </div>
            </div>
            
            <!-- Mensaje de Bienvenida -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body bg-gradient-primary text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-2">
                                <i class="fas fa-hand-wave"></i> 
                                ¡Hola, <?php echo $_SESSION['nombre_completo']; ?>!
                            </h4>
                            <p class="mb-0 opacity-75">
                                <i class="fas fa-user-tag"></i> 
                                Eres <strong><?php echo $_SESSION['role_name']; ?></strong> en el sistema
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="d-inline-block p-3 bg-white bg-opacity-25 rounded-circle">
                                <?php 
                                $roleIcons = [
                                    'Administrador' => 'fas fa-user-shield',
                                    'Médico' => 'fas fa-user-md',
                                    'Recepcionista' => 'fas fa-user-tie',
                                    'Paciente' => 'fas fa-user'
                                ];
                                $icon = $roleIcons[$_SESSION['role_name']] ?? 'fas fa-user';
                                ?>
                                <i class="<?php echo $icon; ?> fa-3x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Menús Disponibles -->
            <?php if (!empty($menus)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0 text-primary">
                            <i class="fas fa-th-large"></i> Opciones Disponibles
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($menus as $menu): ?>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="card h-100 border-0 shadow-sm hover-card">
                                        <div class="card-header bg-light border-0">
                                            <h6 class="mb-0 text-primary">
                                                <i class="<?php echo $menu['icono']; ?>"></i> 
                                                <?php echo $menu['nombre_menu']; ?>
                                            </h6>
                                        </div>
                                        <div class="card-body pt-0">
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($menu['submenus'] as $submenu): ?>
                                                    <a href="index.php?action=<?php echo $submenu['uri_submenu']; ?>" 
                                                       class="list-group-item list-group-item-action border-0 py-2">
                                                        <i class="<?php echo $submenu['icono']; ?> text-muted me-2"></i>
                                                        <?php echo $submenu['nombre_submenu']; ?>
                                                        <i class="fas fa-chevron-right float-end text-muted small mt-1"></i>
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
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    No tienes permisos asignados o no hay menús disponibles para tu rol.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .hover-card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .hover-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
    }
    .list-group-item-action:hover {
        background-color: #f8f9fa;
    }
    .bg-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
</style>

</body>
</html>