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
            'id_rol' => 4, // Rol de paciente
            'id_sucursal' => null
        ];

        $result = $userModel->createUser($data);

        $success = "Paciente registrado exitosamente. Se han enviado las credenciales al email: " . $data['email'];

        // Limpiar formulario después del éxito
        $_POST = [];
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
                        <i class="fas fa-user-plus"></i> Registrar Paciente
                    </h2>
                    <p class="text-muted mb-0">Registro de nuevo paciente en el sistema</p>
                </div>
                <div>
                    <a href="index.php?action=pacientes_gestionar" class="btn btn-outline-secondary">
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

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-user-plus"></i> Nuevo Paciente
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="row">
                            <!-- Cédula con consulta automática -->
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-id-card"></i> Cédula de Identidad 
                                        <small class="text-muted">(opcional - se completarán los datos automáticamente)</small>
                                    </label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="cedula" id="cedula"
                                               value="<?php echo $_POST['cedula'] ?? ''; ?>" 
                                               placeholder="Ingrese número de cédula" 
                                               maxlength="10" 
                                               oninput="validarCedulaInput()">
                                        <div class="input-group-text" id="cedulaStatus">
                                            <i class="fas fa-question text-muted"></i>
                                        </div>
                                    </div>
                                    <div id="cedulaResult" class="mt-2" style="display: none;"></div>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle"></i> 10 dígitos - Al consultar se completarán nombres y apellidos automáticamente
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
                                           value="<?php echo $_POST['username'] ?? ''; ?>" required>
                                    <div class="form-text">
                                        Será usado para acceder al sistema. Ej: juan.perez
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-envelope"></i> Email <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" class="form-control" name="email" id="email"
                                           value="<?php echo $_POST['email'] ?? ''; ?>" required>
                                    <div class="form-text">
                                        Se enviará la contraseña temporal a este email
                                    </div>
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
                                           value="<?php echo $_POST['nombre'] ?? ''; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-user"></i> Apellidos <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" name="apellido" id="apellido"
                                           value="<?php echo $_POST['apellido'] ?? ''; ?>" required>
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
                                           value="<?php echo $_POST['fecha_nacimiento'] ?? ''; ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-venus-mars"></i> Género
                                    </label>
                                    <select class="form-select" name="genero">
                                        <option value="">Seleccionar...</option>
                                        <option value="M" <?php echo (($_POST['genero'] ?? '') === 'M') ? 'selected' : ''; ?>>Masculino</option>
                                        <option value="F" <?php echo (($_POST['genero'] ?? '') === 'F') ? 'selected' : ''; ?>>Femenino</option>
                                        <option value="O" <?php echo (($_POST['genero'] ?? '') === 'O') ? 'selected' : ''; ?>>Otro</option>
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
                                           value="<?php echo $_POST['telefono'] ?? ''; ?>" 
                                           placeholder="+593-99-123-4567">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-map-marker-alt"></i> Dirección
                                    </label>
                                    <input type="text" class="form-control" name="direccion"
                                           value="<?php echo $_POST['direccion'] ?? ''; ?>" 
                                           placeholder="Dirección completa">
                                </div>
                            </div>
                        </div>

                        <!-- Información de contraseña -->
                        <div class="row">
                            <div class="col-12">
                                <div class="alert alert-info border-0">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Contraseña automática:</strong><br>
                                    Se generará una contraseña temporal automáticamente y se enviará por email al paciente. 
                                    El paciente deberá cambiarla en su primer acceso al sistema.
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <a href="index.php?action=pacientes_gestionar" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Registrar Paciente
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

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
        fetch('views/api/consultar-cedula.php', {// ← Aquí está el cambio
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

                        // Completar campos automáticamente
                        document.getElementById('nombre').value = data.nombres || '';
                        document.getElementById('apellido').value = data.apellidos || '';

                        if (data.fecha_nacimiento) {
                            document.getElementById('fecha_nacimiento').value = data.fecha_nacimiento;
                        }

                        // Generar username sugerido
                        if (data.nombres && data.apellidos) {
                            const username = (data.nombres.split(' ')[0] + '.' + data.apellidos.split(' ')[0]).toLowerCase()
                                    .replace(/[áàäâ]/g, 'a')
                                    .replace(/[éèëê]/g, 'e')
                                    .replace(/[íìïî]/g, 'i')
                                    .replace(/[óòöô]/g, 'o')
                                    .replace(/[úùüû]/g, 'u')
                                    .replace(/ñ/g, 'n');
                            document.getElementById('username').value = username;
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
</script>
