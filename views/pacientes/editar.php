<?php
// Verificar permisos (solo admin o recepcionista)
if (!in_array($_SESSION['role_id'], [1, 2])) {
    header('Location: index.php?action=dashboard');
    exit;
}

require_once 'models/User.php';
require_once 'includes/cedula-api.php';

$userModel = new User();
$error = '';
$success = '';

// Obtener ID del paciente a editar
$pacienteId = $_GET['id'] ?? 0;
if (!$pacienteId) {
    header('Location: index.php?action=pacientes/gestionar');
    exit;
}

// Obtener datos del paciente
$paciente = $userModel->getUserById($pacienteId);
if (!$paciente || $paciente['id_rol'] != 4) {
    header('Location: index.php?action=pacientes/gestionar');
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar campos obligatorios
        $requiredFields = ['username', 'email', 'nombre', 'apellido'];
        foreach ($requiredFields as $field) {
            if (empty(trim($_POST[$field]))) {
                throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
            }
        }

        // Validar email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El email no tiene un formato válido");
        }

        // Validar cédula si se proporciona
        if (!empty($_POST['cedula']) && !validarCedulaEcuatoriana($_POST['cedula'])) {
            throw new Exception("La cédula ingresada no es válida");
        }

        $data = [
            'username' => trim($_POST['username']),
            'email' => trim($_POST['email']),
            'cedula' => trim($_POST['cedula']) ?: null,
            'nombre' => trim($_POST['nombre']),
            'apellido' => trim($_POST['apellido']),
            'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?: null,
            'genero' => $_POST['genero'] ?: null,
            'telefono' => trim($_POST['telefono']) ?: null,
            'direccion' => trim($_POST['direccion']) ?: null,
            'id_rol' => 4, // Mantener rol de paciente
            'id_sucursal' => null
        ];

        // Si se proporciona nueva contraseña, actualizarla
        if (!empty($_POST['nueva_password'])) {
            if (strlen($_POST['nueva_password']) < 6) {
                throw new Exception("La contraseña debe tener al menos 6 caracteres");
            }
            if ($_POST['nueva_password'] !== $_POST['confirmar_password']) {
                throw new Exception("Las contraseñas no coinciden");
            }
            // Cifrar en base64
            $data['password'] = base64_encode($_POST['nueva_password']);
        }

        $userModel->updateUser($pacienteId, $data);
        $success = "Datos del paciente actualizados exitosamente";

        // Recargar datos actualizados
        $paciente = $userModel->getUserById($pacienteId);
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
                        <i class="fas fa-user-edit"></i> Editar Paciente
                    </h2>
                    <p class="text-muted mb-0">
                        Modificar información del paciente: 
                        <strong><?php echo htmlspecialchars($paciente['nombre'] . ' ' . $paciente['apellido']); ?></strong>
                    </p>
                </div>
                <div>
                    <a href="index.php?action=pacientes/gestionar" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver a Gestión
                    </a>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user-edit"></i> Información del Paciente
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="needs-validation" novalidate>
                                <!-- Datos personales -->
                                <div class="row">
                                    <!-- Cédula con consulta automática -->
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-id-card"></i> Cédula de Identidad 
                                                <small class="text-muted">(opcional - se actualizarán los datos automáticamente)</small>
                                            </label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="cedula" id="cedula"
                                                       value="<?php echo htmlspecialchars($paciente['cedula'] ?? ''); ?>" 
                                                       placeholder="Ingrese número de cédula" 
                                                       maxlength="10" 
                                                       oninput="validarCedulaInput()">
                                                <div class="input-group-text" id="cedulaStatus">
                                                    <i class="fas fa-question text-muted"></i>
                                                </div>
                                            </div>
                                            <div id="cedulaResult" class="mt-2" style="display: none;"></div>
                                            <div class="form-text">
                                                <i class="fas fa-info-circle"></i> 10 dígitos - Al consultar se actualizarán nombres y apellidos automáticamente
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Datos básicos -->
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-user"></i> Nombre de Usuario <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" name="username" id="username"
                                                   value="<?php echo htmlspecialchars($paciente['username']); ?>" required>
                                            <div class="form-text">
                                                Usado para acceder al sistema
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-envelope"></i> Email <span class="text-danger">*</span>
                                            </label>
                                            <input type="email" class="form-control" name="email" id="email"
                                                   value="<?php echo htmlspecialchars($paciente['email']); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Información personal -->
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-user"></i> Nombres <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" name="nombre" id="nombre"
                                                   value="<?php echo htmlspecialchars($paciente['nombre']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-user"></i> Apellidos <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" name="apellido" id="apellido"
                                                   value="<?php echo htmlspecialchars($paciente['apellido']); ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-calendar"></i> Fecha de Nacimiento
                                            </label>
                                            <input type="date" class="form-control" name="fecha_nacimiento" id="fecha_nacimiento"
                                                   value="<?php echo $paciente['fecha_nacimiento']; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-venus-mars"></i> Género
                                            </label>
                                            <select class="form-select" name="genero">
                                                <option value="">Seleccionar...</option>
                                                <option value="M" <?php echo ($paciente['genero'] === 'M') ? 'selected' : ''; ?>>Masculino</option>
                                                <option value="F" <?php echo ($paciente['genero'] === 'F') ? 'selected' : ''; ?>>Femenino</option>
                                                <option value="O" <?php echo ($paciente['genero'] === 'O') ? 'selected' : ''; ?>>Otro</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Contacto -->
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-phone"></i> Teléfono
                                            </label>
                                            <input type="tel" class="form-control" name="telefono"
                                                   value="<?php echo htmlspecialchars($paciente['telefono'] ?? ''); ?>" 
                                                   placeholder="+593-99-123-4567">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-map-marker-alt"></i> Dirección
                                            </label>
                                            <input type="text" class="form-control" name="direccion"
                                                   value="<?php echo htmlspecialchars($paciente['direccion'] ?? ''); ?>" 
                                                   placeholder="Dirección completa">
                                        </div>
                                    </div>
                                </div>

                                <!-- Sección de contraseña -->
                                <hr class="my-4">
                                <h6 class="text-primary">
                                    <i class="fas fa-key"></i> Cambiar Contraseña (Opcional)
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-lock"></i> Nueva Contraseña
                                            </label>
                                            <input type="password" class="form-control" name="nueva_password" id="nueva_password"
                                                   placeholder="Dejar vacío para mantener la actual">
                                            <div class="form-text">Mínimo 6 caracteres</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">
                                                <i class="fas fa-lock"></i> Confirmar Contraseña
                                            </label>
                                            <input type="password" class="form-control" name="confirmar_password" id="confirmar_password"
                                                   placeholder="Repetir nueva contraseña">
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-info border-0">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Nota:</strong> Si cambia la contraseña, esta se cifrará automáticamente. 
                                    El paciente podrá usar la nueva contraseña inmediatamente.
                                </div>

                                <hr>

                                <div class="d-flex justify-content-between">
                                    <a href="index.php?action=pacientes/gestionar" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Panel lateral con información adicional -->
                <div class="col-xl-4">
                    <!-- Estado del paciente -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-info-circle"></i> Estado del Paciente
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div class="avatar-xl bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                                    <i class="fas fa-user fa-2x text-white"></i>
                                </div>
                                <h5><?php echo htmlspecialchars($paciente['nombre'] . ' ' . $paciente['apellido']); ?></h5>
                                <span class="badge bg-<?php echo $paciente['activo'] ? 'success' : 'danger'; ?> fs-6">
                                    <?php echo $paciente['activo'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </div>

                            <table class="table table-sm">
                                <tr>
                                    <td><strong>ID:</strong></td>
                                    <td><?php echo $paciente['id_usuario']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Registrado:</strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($paciente['fecha_registro'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Último acceso:</strong></td>
                                    <td><?php echo $paciente['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($paciente['ultimo_acceso'])) : 'Nunca'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Acciones rápidas -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-tools"></i> Acciones Rápidas
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="index.php?action=pacientes/historial&id=<?php echo $paciente['id_usuario']; ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="fas fa-file-medical"></i> Ver Historial Médico
                                </a>
                                <button type="button" class="btn btn-outline-<?php echo $paciente['activo'] ? 'warning' : 'success'; ?>" 
                                        onclick="toggleStatus()">
                                    <i class="fas fa-<?php echo $paciente['activo'] ? 'user-times' : 'user-check'; ?>"></i> 
                                    <?php echo $paciente['activo'] ? 'Desactivar' : 'Activar'; ?> Paciente
                                </button>
                                <a href="index.php?action=pacientes/gestionar" class="btn btn-outline-secondary">
                                    <i class="fas fa-list"></i> Ver Todos los Pacientes
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .avatar-xl {
        width: 80px;
        height: 80px;
    }
</style>

<!-- Script para consulta de cédula -->
<script>
    let timeoutId;

    function validarCedulaInput() {
        clearTimeout(timeoutId);
        const cedula = document.getElementById('cedula').value.trim();
        const status = document.getElementById('cedulaStatus');
        const result = document.getElementById('cedulaResult');

        if (cedula.length === 0) {
            status.innerHTML = '<i class="fas fa-question text-muted"></i>';
            result.style.display = 'none';
            return;
        }

        if (cedula.length === 10) {
            status.innerHTML = '<i class="fas fa-spinner fa-spin text-info"></i>';

            timeoutId = setTimeout(() => {
                consultarCedula(cedula);
            }, 1000);
        } else {
            status.innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i>';
            result.style.display = 'none';
        }
    }

    function consultarCedula(cedula) {
        fetch('views/api/consultar-cedula.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({cedula: cedula})
        })
                .then(response => response.json())
                .then(data => {
                    const status = document.getElementById('cedulaStatus');
                    const result = document.getElementById('cedulaResult');

                    if (data.success) {
                        status.innerHTML = '<i class="fas fa-check-circle text-success"></i>';

                        // Preguntar si desea actualizar los datos
                        if (confirm('¿Desea actualizar los nombres y apellidos con los datos encontrados?')) {
                            document.getElementById('nombre').value = data.nombres || '';
                            document.getElementById('apellido').value = data.apellidos || '';

                            if (data.fecha_nacimiento) {
                                document.getElementById('fecha_nacimiento').value = data.fecha_nacimiento;
                            }
                        }

                        result.innerHTML = `
                <div class="alert alert-success border-0">
                    <i class="fas fa-check-circle"></i>
                    <strong>Datos encontrados:</strong><br>
                    <strong>Nombres:</strong> ${data.nombres}<br>
                    <strong>Apellidos:</strong> ${data.apellidos}
                    ${data.fecha_nacimiento ? '<br><strong>Fecha de nacimiento:</strong> ' + data.fecha_nacimiento : ''}
                </div>
            `;
                        result.style.display = 'block';
                    } else {
                        status.innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i>';
                        result.innerHTML = `
                <div class="alert alert-warning border-0">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${data.error || 'No se pudieron obtener los datos de la cédula'}
                </div>
            `;
                        result.style.display = 'block';
                    }
                })
                .catch(error => {
                    const status = document.getElementById('cedulaStatus');
                    const result = document.getElementById('cedulaResult');

                    status.innerHTML = '<i class="fas fa-exclamation-triangle text-danger"></i>';
                    result.innerHTML = `
            <div class="alert alert-danger border-0">
                <i class="fas fa-exclamation-triangle"></i>
                Error al consultar la cédula
            </div>
        `;
                    result.style.display = 'block';
                });
    }

    function toggleStatus() {
        const isActive = <?php echo $paciente['activo'] ? 'true' : 'false'; ?>;
        const action = isActive ? 'desactivar' : 'activar';

        if (confirm(`¿Está seguro que desea ${action} este paciente?`)) {
            // Implementar funcionalidad AJAX aquí si es necesario
            window.location.href = `index.php?action=pacientes/gestionar&toggle=${<?php echo $paciente['id_usuario']; ?>}`;
        }
    }

// Validación de contraseñas en tiempo real
    document.getElementById('nueva_password')?.addEventListener('input', function () {
        const password = this.value;
        const confirmField = document.getElementById('confirmar_password');

        if (password.length > 0 && password.length < 6) {
            this.classList.add('is-invalid');
        } else {
            this.classList.remove('is-invalid');
        }

        // Limpiar confirmación si cambia la contraseña
        if (confirmField.value) {
            confirmField.value = '';
            confirmField.classList.remove('is-valid', 'is-invalid');
        }
    });

    document.getElementById('confirmar_password')?.addEventListener('input', function () {
        const password = document.getElementById('nueva_password').value;
        const confirm = this.value;

        if (confirm && password === confirm) {
            this.classList.add('is-valid');
            this.classList.remove('is-invalid');
        } else if (confirm) {
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
        }
    });
</script>

