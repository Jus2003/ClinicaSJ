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

        // Validar cédula ecuatoriana si se proporciona
        if (!empty($data['cedula']) && !preg_match('/^[0-9]{10}$/', $data['cedula'])) {
            throw new Exception("La cédula debe tener 10 dígitos");
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

// Función para calcular edad
function calcularEdad($fechaNacimiento) {
    if (!$fechaNacimiento)
        return null;
    $hoy = new DateTime();
    $nacimiento = new DateTime($fechaNacimiento);
    return $hoy->diff($nacimiento)->y;
}

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <!-- Header mejorado -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="text-primary fw-bold mb-2">
                        <i class="fas fa-user-circle me-3"></i>Mi Perfil
                    </h1>
                    <p class="text-muted mb-0 fs-5">Gestiona tu información personal y configuración de cuenta</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="index.php?action=dashboard" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>Dashboard
                    </a>
                    <button type="button" class="btn btn-primary btn-lg" onclick="document.getElementById('profileForm').scrollIntoView({behavior: 'smooth'})">
                        <i class="fas fa-edit me-2"></i>Editar Datos
                    </button>
                </div>
            </div>

            <!-- Mensajes mejorados -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-3 fs-5"></i>
                        <div>
                            <strong>Error:</strong> <?php echo $error; ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle me-3 fs-5"></i>
                        <div>
                            <strong>¡Éxito!</strong> <?php echo $success; ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Información del Usuario mejorada -->
                <div class="col-lg-4">
                    <!-- Tarjeta principal del usuario -->
                    <div class="card border-0 shadow-sm mb-4 user-profile-card">
                        <div class="card-body text-center p-4">
                            <div class="position-relative mb-3">
                                <div class="avatar-xl bg-gradient-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow">
                                    <?php
                                    $roleIcons = [
                                        1 => 'fas fa-user-shield', // Administrador
                                        2 => 'fas fa-user-md', // Médico
                                        3 => 'fas fa-user-tie', // Recepcionista
                                        4 => 'fas fa-user', // Paciente
                                    ];
                                    $icon = $roleIcons[$usuario['id_rol']] ?? 'fas fa-user';
                                    ?>
                                    <i class="<?php echo $icon; ?> fa-3x text-white"></i>
                                </div>
                                <div class="status-indicator bg-success"></div>
                            </div>

                            <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></h4>
                            <p class="text-muted mb-2">@<?php echo htmlspecialchars($usuario['username']); ?></p>

                            <span class="badge bg-<?php
                            echo match ($usuario['id_rol']) {
                                1 => 'danger',
                                2 => 'success',
                                3 => 'info',
                                4 => 'warning',
                                default => 'secondary'
                            };
                            ?> fs-6 px-3 py-2 mb-3">
                                <i class="<?php echo $icon; ?> me-2"></i><?php echo $usuario['nombre_rol']; ?>
                            </span>

                            <hr class="my-3">

                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="stat-item">
                                        <h5 class="fw-bold text-primary mb-0"><?php echo $usuario['id_usuario']; ?></h5>
                                        <small class="text-muted">ID Usuario</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-item">
                                        <h5 class="fw-bold text-success mb-0">
                                            <?php
                                            $edad = calcularEdad($usuario['fecha_nacimiento']);
                                            echo $edad ? $edad : '--';
                                            ?>
                                        </h5>
                                        <small class="text-muted">Años</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información de cuenta -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-gradient-light border-0 p-3">
                            <h6 class="mb-0 fw-semibold">
                                <i class="fas fa-info-circle me-2 text-primary"></i>Información de Cuenta
                            </h6>
                        </div>
                        <div class="card-body p-3">
                            <div class="info-item mb-3">
                                <small class="text-muted d-block">Fecha de Registro</small>
                                <span class="fw-semibold"><?php echo date('d/m/Y', strtotime($usuario['fecha_registro'])); ?></span>
                                <small class="text-muted ms-2"><?php echo date('H:i', strtotime($usuario['fecha_registro'])); ?></small>
                            </div>

                            <?php if ($usuario['ultimo_acceso']): ?>
                                <div class="info-item mb-3">
                                    <small class="text-muted d-block">Último Acceso</small>
                                    <span class="fw-semibold"><?php echo date('d/m/Y', strtotime($usuario['ultimo_acceso'])); ?></span>
                                    <small class="text-muted ms-2"><?php echo date('H:i', strtotime($usuario['ultimo_acceso'])); ?></small>
                                </div>
                            <?php endif; ?>

                            <div class="info-item">
                                <small class="text-muted d-block">Estado de Cuenta</small>
                                <span class="badge bg-success">
                                    <i class="fas fa-check-circle me-1"></i>Activa
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Enlaces de configuración -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient-light border-0 p-3">
                            <h6 class="mb-0 fw-semibold">
                                <i class="fas fa-cogs me-2 text-primary"></i>Configuración
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <a href="index.php?action=perfil/datos" class="list-group-item list-group-item-action active border-0 py-3">
                                    <i class="fas fa-id-card me-3 text-primary"></i>
                                    <span class="fw-medium">Datos Personales</span>
                                    <i class="fas fa-chevron-right float-end text-muted small mt-1"></i>
                                </a>
                                <a href="index.php?action=perfil/password" class="list-group-item list-group-item-action border-0 py-3 config-link">
                                    <i class="fas fa-key me-3 text-warning"></i>
                                    <span class="fw-medium">Cambiar Contraseña</span>
                                    <i class="fas fa-chevron-right float-end text-muted small mt-1"></i>
                                </a>
                                <a href="index.php?action=perfil/notificaciones" class="list-group-item list-group-item-action border-0 py-3 config-link">
                                    <i class="fas fa-bell me-3 text-info"></i>
                                    <span class="fw-medium">Notificaciones</span>
                                    <i class="fas fa-chevron-right float-end text-muted small mt-1"></i>
                                </a>
                                <a href="index.php?action=perfil/seguridad" class="list-group-item list-group-item-action border-0 py-3 config-link">
                                    <i class="fas fa-shield-alt me-3 text-success"></i>
                                    <span class="fw-medium">Seguridad</span>
                                    <i class="fas fa-chevron-right float-end text-muted small mt-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario de Datos mejorado -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm" id="profileForm">
                        <div class="card-header bg-gradient-primary text-white border-0 p-4">
                            <h5 class="mb-0 fw-semibold">
                                <i class="fas fa-edit me-2"></i>Actualizar Datos Personales
                            </h5>
                        </div>
                        <form method="POST" id="userForm">
                            <div class="card-body p-4">
                                <!-- Sección: Información básica -->
                                <div class="section-header mb-4">
                                    <h6 class="text-primary fw-semibold mb-3">
                                        <i class="fas fa-user me-2"></i>Información Básica
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">
                                                Nombre de Usuario <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-at"></i></span>
                                                <input type="text" class="form-control form-control-lg" name="username" 
                                                       value="<?php echo htmlspecialchars($usuario['username']); ?>" 
                                                       required placeholder="Nombre de usuario único">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">
                                                Correo Electrónico <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                                <input type="email" class="form-control form-control-lg" name="email" 
                                                       value="<?php echo htmlspecialchars($usuario['email']); ?>" 
                                                       required placeholder="correo@ejemplo.com">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">
                                                Nombre <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                <input type="text" class="form-control form-control-lg" name="nombre" 
                                                       value="<?php echo htmlspecialchars($usuario['nombre']); ?>" 
                                                       required placeholder="Tu nombre">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">
                                                Apellido <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                <input type="text" class="form-control form-control-lg" name="apellido" 
                                                       value="<?php echo htmlspecialchars($usuario['apellido']); ?>" 
                                                       required placeholder="Tu apellido">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sección: Datos de identificación -->
                                <div class="section-header mb-4">
                                    <h6 class="text-primary fw-semibold mb-3">
                                        <i class="fas fa-id-card me-2"></i>Datos de Identificación
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">Cédula de Identidad</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                                <input type="text" class="form-control form-control-lg" name="cedula" 
                                                       value="<?php echo htmlspecialchars($usuario['cedula'] ?? ''); ?>"
                                                       maxlength="10" pattern="[0-9]{10}" 
                                                       placeholder="1234567890">
                                            </div>
                                            <small class="text-muted">10 dígitos numéricos</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">Teléfono</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                                <input type="tel" class="form-control form-control-lg" name="telefono" 
                                                       value="<?php echo htmlspecialchars($usuario['telefono'] ?? ''); ?>"
                                                       placeholder="0987654321">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sección: Información personal -->
                                <div class="section-header mb-4">
                                    <h6 class="text-primary fw-semibold mb-3">
                                        <i class="fas fa-user-circle me-2"></i>Información Personal
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">Fecha de Nacimiento</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                                <input type="date" class="form-control form-control-lg" name="fecha_nacimiento" 
                                                       value="<?php echo $usuario['fecha_nacimiento']; ?>"
                                                       max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
                                            </div>
                                            <?php if ($usuario['fecha_nacimiento']): ?>
                                                <small class="text-muted">
                                                    Edad: <?php echo calcularEdad($usuario['fecha_nacimiento']); ?> años
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-medium">Género</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                                                <select class="form-select form-select-lg" name="genero">
                                                    <option value="">Seleccionar género...</option>
                                                    <option value="M" <?php echo ($usuario['genero'] == 'M') ? 'selected' : ''; ?>>Masculino</option>
                                                    <option value="F" <?php echo ($usuario['genero'] == 'F') ? 'selected' : ''; ?>>Femenino</option>
                                                    <option value="O" <?php echo ($usuario['genero'] == 'O') ? 'selected' : ''; ?>>Otro</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-medium">Dirección</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                                <textarea class="form-control" name="direccion" rows="3" 
                                                          placeholder="Ingresa tu dirección completa..."><?php echo htmlspecialchars($usuario['direccion'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer bg-light border-0 p-4">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Los campos marcados con (*) son obligatorios
                                        </small>
                                    </div>
                                    <div class="d-flex gap-3">
                                        <button type="button" class="btn btn-secondary btn-lg" onclick="resetForm()">
                                            <i class="fas fa-undo me-2"></i>Restablecer
                                        </button>
                                        <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                            <i class="fas fa-save me-2"></i>Guardar Cambios
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Información adicional -->
                    <div class="row g-3 mt-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center p-4">
                                    <i class="fas fa-shield-alt fa-2x text-success mb-3"></i>
                                    <h6 class="fw-semibold">Cuenta Verificada</h6>
                                    <p class="text-muted small mb-0">Tu cuenta ha sido verificada y está activa</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center p-4">
                                    <i class="fas fa-lock fa-2x text-primary mb-3"></i>
                                    <h6 class="fw-semibold">Datos Seguros</h6>
                                    <p class="text-muted small mb-0">Tu información está protegida y encriptada</p>
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

    /* Avatar mejorado */
    .avatar-xl {
        width: 120px;
        height: 120px;
        position: relative;
    }

    .status-indicator {
        position: absolute;
        bottom: 8px;
        right: 8px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        border: 3px solid white;
    }

    /* Tarjeta de perfil */
    .user-profile-card {
        border-radius: 15px;
        transition: all 0.3s ease;
    }

    .user-profile-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.1) !important;
    }

    /* Estadísticas */
    .stat-item {
        padding: 10px;
        border-radius: 8px;
        background: rgba(102, 126, 234, 0.05);
        transition: all 0.2s ease;
    }

    .stat-item:hover {
        background: rgba(102, 126, 234, 0.1);
        transform: translateY(-2px);
    }

    /* Enlaces de configuración */
    .config-link {
        transition: all 0.2s ease;
        border-left: 3px solid transparent !important;
    }

    .config-link:hover {
        background-color: rgba(102, 126, 234, 0.05) !important;
        border-left-color: #667eea !important;
        padding-left: 20px !important;
    }

    .list-group-item.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
        border-left: 3px solid #fff !important;
    }

    /* Secciones del formulario */
    .section-header {
        border-bottom: 2px solid #f8f9fa;
        padding-bottom: 15px;
    }

    /* Inputs mejorados */
    .input-group-text {
        background-color: rgba(102, 126, 234, 0.1);
        border-color: #dee2e6;
        color: #667eea;
    }

    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .form-control-lg, .form-select-lg {
        padding: 0.75rem 1rem;
        font-size: 1rem;
    }

    /* Botones mejorados */
    .btn {
        transition: all 0.2s ease;
        font-weight: 500;
        border-radius: 8px;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
    }

    /* Efectos de validación */
    .is-valid {
        border-color: #28a745;
    }

    .is-invalid {
        border-color: #dc3545;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .container-fluid {
            padding-left: 15px;
            padding-right: 15px;
        }

        .avatar-xl {
            width: 80px;
            height: 80px;
        }

        .avatar-xl i {
            font-size: 2rem !important;
        }

        .btn-lg {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
    }

    /* Animaciones */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .card {
        animation: fadeInUp 0.6s ease;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Validación en tiempo real
        const form = document.getElementById('userForm');
        const inputs = form.querySelectorAll('input, select, textarea');

        inputs.forEach(input => {
            input.addEventListener('blur', validateField);
            input.addEventListener('input', clearValidation);
        });

        // Validación de cédula ecuatoriana
        const cedulaInput = document.querySelector('input[name="cedula"]');
        if (cedulaInput) {
            cedulaInput.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').substring(0, 10);
                validateCedula(this);
            });
        }

        // Validación del formulario al enviar
        form.addEventListener('submit', function (e) {
            if (!validateForm()) {
                e.preventDefault();
                showAlert('Por favor, corrige los errores antes de continuar.', 'warning');
            } else {
                // Mostrar loading en el botón
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';
                submitBtn.disabled = true;
            }
        });
    });

    function validateField(e) {
        const field = e.target;
        const value = field.value.trim();

        // Limpiar clases previas
        field.classList.remove('is-valid', 'is-invalid');

        // Validaciones específicas
        switch (field.name) {
            case 'username':
                if (value.length < 3) {
                    setInvalid(field, 'El nombre de usuario debe tener al menos 3 caracteres');
                } else {
                    setValid(field);
                }
                break;

            case 'email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    setInvalid(field, 'Ingresa un email válido');
                } else {
                    setValid(field);
                }
                break;

            case 'nombre':
            case 'apellido':
                if (value.length < 2) {
                    setInvalid(field, 'Debe tener al menos 2 caracteres');
                } else {
                    setValid(field);
                }
                break;

            case 'telefono':
                if (value && !/^[0-9+\-\s()]{7,15}$/.test(value)) {
                    setInvalid(field, 'Formato de teléfono inválido');
                } else if (value) {
                    setValid(field);
                }
                break;

        }
    }

    function clearValidation(e) {
        const field = e.target;
        field.classList.remove('is-valid', 'is-invalid');
        removeFieldError(field);
    }

    function validateCedula(field) {
        const cedula = field.value;

        if (cedula.length === 0) {
            field.classList.remove('is-valid', 'is-invalid');
            removeFieldError(field);
            return;
        }

        if (cedula.length !== 10) {
            setInvalid(field, 'La cédula debe tener 10 dígitos');
            return;
        }

        // Validación del algoritmo de cédula ecuatoriana
        const provincia = parseInt(cedula.substring(0, 2));
        if (provincia < 1 || provincia > 24) {
            setInvalid(field, 'Código de provincia inválido');
            return;
        }

        const tercerDigito = parseInt(cedula.charAt(2));
        if (tercerDigito > 5) {
            setInvalid(field, 'Tercer dígito de cédula inválido');
            return;
        }

        // Algoritmo de validación
        const coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        let suma = 0;

        for (let i = 0; i < 9; i++) {
            let valor = parseInt(cedula.charAt(i)) * coeficientes[i];
            if (valor > 9)
                valor -= 9;
            suma += valor;
        }

        const digitoVerificador = parseInt(cedula.charAt(9));
        const residuo = suma % 10;
        const resultado = residuo === 0 ? 0 : 10 - residuo;

        if (resultado !== digitoVerificador) {
            setInvalid(field, 'Número de cédula inválido');
        } else {
            setValid(field);
        }
    }

    function setValid(field) {
        field.classList.add('is-valid');
        field.classList.remove('is-invalid');
        removeFieldError(field);
    }

    function setInvalid(field, message) {
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');
        showFieldError(field, message);
    }

    function showFieldError(field, message) {
        removeFieldError(field);
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback d-block';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    }

    function removeFieldError(field) {
        const errorDiv = field.parentNode.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    }

    function validateForm() {
        const requiredFields = ['username', 'email', 'nombre', 'apellido'];
        let isValid = true;

        requiredFields.forEach(fieldName => {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (!field.value.trim()) {
                setInvalid(field, 'Este campo es obligatorio');
                isValid = false;
            }
        });

        // Validar email
        const emailField = document.querySelector('[name="email"]');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailField.value)) {
            setInvalid(emailField, 'Email inválido');
            isValid = false;
        }

        // Validar cédula si está llena
        const cedulaField = document.querySelector('[name="cedula"]');
        if (cedulaField.value && cedulaField.classList.contains('is-invalid')) {
            isValid = false;
        }

        return isValid;
    }

    function resetForm() {
        if (confirm('¿Estás seguro de que quieres restablecer todos los cambios?')) {
            document.getElementById('userForm').reset();

            // Limpiar validaciones
            const inputs = document.querySelectorAll('#userForm input, #userForm select, #userForm textarea');
            inputs.forEach(input => {
                input.classList.remove('is-valid', 'is-invalid');
                removeFieldError(input);
            });

            showAlert('Formulario restablecido', 'info');
        }
    }

    function showAlert(message, type = 'info') {
        // Crear alerta temporal
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show border-0 shadow-sm`;
        alertDiv.innerHTML = `
           <div class="d-flex align-items-center">
               <i class="fas fa-${getAlertIcon(type)} me-3 fs-5"></i>
               <div>${message}</div>
           </div>
           <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
       `;

        // Insertar al inicio del contenedor
        const container = document.querySelector('.container-fluid .row .col-12');
        container.insertBefore(alertDiv, container.children[1]);

        // Auto-remover después de 5 segundos
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }

    function getAlertIcon(type) {
        const icons = {
            'success': 'check-circle',
            'danger': 'exclamation-triangle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    // Funciones para mejorar la experiencia del usuario
    function formatPhone(input) {
        let value = input.value.replace(/\D/g, '');
        if (value.length >= 4) {
            value = value.substring(0, 4) + '-' + value.substring(4, 10);
        }
        input.value = value;
    }

    function calculateAge() {
        const birthDate = document.querySelector('[name="fecha_nacimiento"]').value;
        if (birthDate) {
            const today = new Date();
            const birth = new Date(birthDate);
            let age = today.getFullYear() - birth.getFullYear();
            const monthDiff = today.getMonth() - birth.getMonth();

            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
                age--;
            }

            // Mostrar edad calculada
            const ageDisplay = document.querySelector('.age-display');
            if (ageDisplay) {
                ageDisplay.textContent = `Edad: ${age} años`;
            }
        }
    }

    // Eventos adicionales
    document.addEventListener('DOMContentLoaded', function () {
        // Calcular edad al cambiar fecha de nacimiento
        const birthDateInput = document.querySelector('[name="fecha_nacimiento"]');
        if (birthDateInput) {
            birthDateInput.addEventListener('change', calculateAge);
        }

        // Formatear teléfono
        const phoneInput = document.querySelector('[name="telefono"]');
        if (phoneInput) {
            phoneInput.addEventListener('input', function () {
                formatPhone(this);
            });
        }

        // Animaciones de entrada
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';

            setTimeout(() => {
                card.style.transition = 'all 0.6s ease';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Efecto de escritura para el nombre
        const userName = document.querySelector('.user-profile-card h4');
        if (userName) {
            const text = userName.textContent;
            userName.textContent = '';
            let i = 0;

            function typeWriter() {
                if (i < text.length) {
                    userName.textContent += text.charAt(i);
                    i++;
                    setTimeout(typeWriter, 50);
                }
            }

            setTimeout(typeWriter, 1000);
        }

        // Tooltip para botones
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });

    // Función para preview de cambios antes de guardar
    function previewChanges() {
        const form = document.getElementById('userForm');
        const formData = new FormData(form);

        let changes = [];
        const originalData = {
            username: '<?php echo htmlspecialchars($usuario['username']); ?>',
            email: '<?php echo htmlspecialchars($usuario['email']); ?>',
            nombre: '<?php echo htmlspecialchars($usuario['nombre']); ?>',
            apellido: '<?php echo htmlspecialchars($usuario['apellido']); ?>'
        };

        for (let [key, value] of formData.entries()) {
            if (originalData[key] && originalData[key] !== value) {
                changes.push(`${key}: "${originalData[key]}" → "${value}"`);
            }
        }

        if (changes.length > 0) {
            const changesList = changes.join('\n');
            return confirm(`Se realizarán los siguientes cambios:\n\n${changesList}\n\n¿Continuar?`);
        }

        return true;
    }

    // Detectar cambios no guardados
    window.addEventListener('beforeunload', function (e) {
        const form = document.getElementById('userForm');
        const formData = new FormData(form);
        let hasChanges = false;

        // Verificar si hay cambios
        const originalData = {
            username: '<?php echo htmlspecialchars($usuario['username']); ?>',
            email: '<?php echo htmlspecialchars($usuario['email']); ?>',
            nombre: '<?php echo htmlspecialchars($usuario['nombre']); ?>',
            apellido: '<?php echo htmlspecialchars($usuario['apellido']); ?>'
        };

        for (let [key, value] of formData.entries()) {
            if (originalData[key] && originalData[key] !== value.trim()) {
                hasChanges = true;
                break;
            }
        }

        if (hasChanges) {
            e.preventDefault();
            e.returnValue = '¿Estás seguro de que quieres salir? Los cambios no guardados se perderán.';
        }
    });
</script>

</body>
</html>