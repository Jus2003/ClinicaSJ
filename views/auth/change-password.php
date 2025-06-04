<?php
require_once 'models/User.php';

// Verificar que esté logueado y requiera cambio
if (!isset($_SESSION['user_id']) || $_SESSION['requiere_cambio_contrasena'] != 1) {
    header('Location: index.php?action=dashboard');
    exit;
}

$error = '';
$success = '';

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = "Por favor complete todos los campos";
    } elseif (strlen($newPassword) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "Las contraseñas no coinciden";
    } else {
        $userModel = new User();
        if ($userModel->changePassword($_SESSION['user_id'], $newPassword)) {
            $_SESSION['requiere_cambio_contrasena'] = 0;
            $success = "Contraseña cambiada exitosamente";
            // Redirigir después de 2 segundos
            header("refresh:2;url=index.php?action=dashboard");
        } else {
            $error = "Error al cambiar la contraseña";
        }
    }
}

include 'views/includes/header.php';
?>

<div class="container-fluid vh-100 d-flex align-items-center justify-content-center bg-light">
    <div class="row w-100">
        <div class="col-md-4 offset-md-4">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark text-center">
                    <h4><i class="fas fa-key"></i> Cambio de Contraseña Requerido</h4>
                    <p class="mb-0">Por seguridad, debe cambiar su contraseña</p>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                            <br><small>Redirigiendo al dashboard...</small>
                        </div>
                    <?php else: ?>
                        
                        <div class="alert alert-info" role="alert">
                            <i class="fas fa-info-circle"></i>
                            <strong>Hola, <?php echo $_SESSION['nombre_completo']; ?></strong><br>
                            Por motivos de seguridad, debe establecer una nueva contraseña antes de continuar.
                        </div>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">
                                    <i class="fas fa-lock"></i> Nueva Contraseña
                                </label>
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       placeholder="Mínimo 6 caracteres" required minlength="6">
                                <div class="form-text">La contraseña debe tener al menos 6 caracteres</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock"></i> Confirmar Contraseña
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       placeholder="Repita su contraseña" required minlength="6">
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-save"></i> Cambiar Contraseña
                                </button>
                            </div>
                        </form>
                        
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center">
                    <a href="index.php?action=logout" class="btn btn-link btn-sm text-muted">
                        <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    body {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    .card {
        border: none;
        border-radius: 15px;
    }
    .card-header {
        border-radius: 15px 15px 0 0 !important;
    }
    .form-control:focus {
        border-color: #f5576c;
        box-shadow: 0 0 0 0.2rem rgba(245, 87, 108, 0.25);
    }
</style>

</body>
</html>