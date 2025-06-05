<?php
require_once 'models/User.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

$userModel = new User();
$error = '';
$success = '';

// Obtener datos actuales del usuario
$usuario = $userModel->getUserById($_SESSION['user_id']);

// Procesar actualización de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'username' => trim($_POST['username']),
            'email' => trim($_POST['email']),
            'nombre' => trim($_POST['nombre']),
            'apellido' => trim($_POST['apellido']),
            'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?: null,
            'genero' => $_POST['genero'] ?: null,
            'telefono' => trim($_POST['telefono'] ?? '') ?: null,
            'direccion' => trim($_POST['direccion'] ?? '') ?: null,
            'cedula' => trim($_POST['cedula'] ?? '') ?: null
        ];
        
        // Validaciones básicas
        if (empty($data['username']) || empty($data['email']) || 
            empty($data['nombre']) || empty($data['apellido'])) {
            throw new Exception("Por favor complete todos los campos obligatorios");
        }
        
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El email no tiene un formato válido");
        }
        
        // Actualizar datos personales
        $userModel->updateUserProfile($_SESSION['user_id'], $data);
        
        // Actualizar datos en sesión
        $_SESSION['nombre_completo'] = $data['nombre'] . ' ' . $data['apellido'];
        $_SESSION['username'] = $data['username'];
        $_SESSION['email'] = $data['email'];
        
        $success = "Datos actualizados exitosamente";
        
        // Recargar datos del usuario
        $usuario = $userModel->getUserById($_SESSION['user_id']);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-primary">
                        <i class="fas fa-id-card"></i> Mi Perfil - Datos Personales
                    </h2>
                    <p class="text-muted mb-0">Actualizar información personal</p>
                </div>
                <div>
                    <a href="index.php?action=dashboard" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver al Dashboard
                    </a>
                </div>
            </div>

            <!-- Mensajes -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Información del Usuario -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-user"></i> Información de Cuenta
                            </h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="avatar-lg bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                                <i class="fas fa-user fa-2x text-white"></i>
                            </div>
                            <h5><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></h5>
                            <p class="text-muted mb-2">
                                <span class="badge bg-<?php
                                echo match($usuario['id_rol']) {
                                    1 => 'danger',
                                    2 => 'warning',
                                    3 => 'success',
                                    4 => 'info',
                                    default => 'secondary'
                                };
                                ?>">
                                    <?php echo $usuario['nombre_rol']; ?>
                                </span>
                            </p>
                            
                            <hr>
                            
                            <div class="text-start">
                                <p class="mb-1"><strong>Registro:</strong></p>
                                <p class="text-muted small"><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_registro'])); ?></p>
                                
                                <?php if ($usuario['ultimo_acceso']): ?>
                                    <p class="mb-1"><strong>Último acceso:</strong></p>
                                    <p class="text-muted small"><?php echo date('d/m/Y H:i', strtotime($usuario['ultimo_acceso'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Enlaces rápidos -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h6 class="mb-0">
                                <i class="fas fa-cogs"></i> Configuración de Perfil
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <a href="index.php?action=perfil/datos" class="list-group-item list-group-item-action active">
                                    <i class="fas fa-id-card me-2"></i>
                                    Datos Personales
                                </a>
                                <a href="index.php?action=perfil/password" class="list-group-item list-group-item-action">
                                    <i class="fas fa-key me-2"></i>
                                    Cambiar Contraseña
                                </a>
                                <a href="index.php?action=perfil/notificaciones" class="list-group-item list-group-item-action">
                                    <i class="fas fa-bell me-2"></i>
                                    Notificaciones
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario de Datos -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0">
                                <i class="fas fa-edit"></i> Actualizar Datos Personales
                            </h5>
                        </div>
                        <form method="POST">
                            <div class="card-body">
                                <div class="row">
                                    <!-- Datos básicos -->
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre de Usuario <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="username" 
                                                   value="<?php echo htmlspecialchars($usuario['username']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Cédula</label>
                                            <input type="text" class="form-control" name="cedula" 
                                                   value="<?php echo htmlspecialchars($usuario['cedula'] ?? ''); ?>"
                                                   maxlength="10">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Teléfono</label>
                                            <input type="text" class="form-control" name="telefono" 
                                                   value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="nombre" 
                                                   value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Apellido <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="apellido" 
                                                   value="<?php echo htmlspecialchars($usuario['apellido']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Fecha de Nacimiento</label>
                                            <input type="date" class="form-control" name="fecha_nacimiento" 
                                                   value="<?php echo $usuario['fecha_nacimiento']; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Género</label>
                                            <select class="form-select" name="genero">
                                                <option value="">Seleccionar...</option>
                                                <option value="M" <?php echo ($usuario['genero'] == 'M') ? 'selected' : ''; ?>>Masculino</option>
                                                <option value="F" <?php echo ($usuario['genero'] == 'F') ? 'selected' : ''; ?>>Femenino</option>
                                                <option value="O" <?php echo ($usuario['genero'] == 'O') ? 'selected' : ''; ?>>Otro</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="mb-3">
                                            <label class="form-label">Dirección</label>
                                            <textarea class="form-control" name="direccion" rows="3"><?php echo htmlspecialchars($usuario['direccion'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="index.php?action=dashboard" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Actualizar Datos
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .avatar-lg {
        width: 80px;
        height: 80px;
    }
    
    .list-group-item.active {
        background-color: #667eea;
        border-color: #667eea;
    }
</style>

</body>
</html>