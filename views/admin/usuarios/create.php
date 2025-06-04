<?php
require_once 'models/User.php';
require_once 'models/Role.php';
require_once 'models/Sucursal.php';
require_once 'models/Especialidad.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php?action=dashboard');
    exit;
}

$userModel = new User();
$roleModel = new Role();
$sucursalModel = new Sucursal();
$especialidadModel = new Especialidad();

$selectedRole = $_GET['role'] ?? '';
$error = '';
$success = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'username' => trim($_POST['username']),
            'email' => trim($_POST['email']),
            'password' => trim($_POST['password']),
            'cedula' => trim($_POST['cedula']) ?: null,
            'nombre' => trim($_POST['nombre']),
            'apellido' => trim($_POST['apellido']),
            'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?: null,
            'genero' => $_POST['genero'] ?: null,
            'telefono' => trim($_POST['telefono']) ?: null,
            'direccion' => trim($_POST['direccion']) ?: null,
            'id_rol' => $_POST['id_rol'],
            'id_sucursal' => $_POST['id_sucursal'] ?: null,
            'especialidades' => $_POST['especialidades'] ?? []
        ];

        // Validaciones básicas
        if (empty($data['username']) || empty($data['email']) || empty($data['password']) ||
                empty($data['nombre']) || empty($data['apellido']) || empty($data['id_rol'])) {
            throw new Exception("Por favor complete todos los campos obligatorios");
        }

        if (strlen($data['password']) < 6) {
            throw new Exception("La contraseña debe tener al menos 6 caracteres");
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El email no tiene un formato válido");
        }

        // Validaciones específicas por rol
        if (in_array($data['id_rol'], [2, 3]) && empty($data['id_sucursal'])) {
            throw new Exception("Debe seleccionar una sucursal para este rol");
        }

        if ($data['id_rol'] == 3 && empty($data['especialidades'])) {
            throw new Exception("Debe seleccionar al menos una especialidad para los médicos");
        }

        $userId = $userModel->createUser($data);
        $success = "Usuario creado exitosamente";

        // Limpiar formulario
        $_POST = [];
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener datos para formulario
$roles = $roleModel->getAllRoles();
$sucursales = $sucursalModel->getAllSucursales();
$especialidades = $especialidadModel->getAllEspecialidades();

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
                        <i class="fas fa-user-plus"></i> Nuevo Usuario
                    </h2>
                    <p class="text-muted mb-0">Crear un nuevo usuario en el sistema</p>
                </div>
                <div>
                    <a href="index.php?action=admin/usuarios" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
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

            <!-- Formulario -->
            <form method="POST" id="userForm">
                <div class="row">
                    <!-- Paso 1: Seleccionar Rol -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-tag"></i> Paso 1: Seleccionar Rol
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Tipo de Usuario <span class="text-danger">*</span></label>
                                    <select class="form-select" name="id_rol" id="roleSelect" required onchange="updateForm()">
                                        <option value="">Seleccione un rol...</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['id_rol']; ?>" 
                                                    <?php echo (($_POST['id_rol'] ?? $selectedRole) == $role['id_rol']) ? 'selected' : ''; ?>>
                                                        <?php echo $role['nombre_rol']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        Seleccione el rol para mostrar los campos correspondientes
                                    </div>
                                </div>

                                <!-- Información del rol -->
                                <div id="roleInfo" class="alert alert-info" style="display: none;">
                                    <small id="roleDescription"></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Paso 2: Formulario dinámico -->
                    <div class="col-lg-8">
                        <div id="userFormContainer" style="display: none;">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white border-bottom">
                                    <h5 class="mb-0">
                                        <i class="fas fa-user-edit"></i> Paso 2: Datos del Usuario
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">
                                                    Cédula 
                                                    <button type="button" class="btn btn-sm btn-outline-info ms-1" 
                                                            onclick="consultarCedula()" id="btnConsultarCedula" disabled>
                                                        <i class="fas fa-search"></i> Consultar
                                                    </button>
                                                </label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" name="cedula" id="cedulaInput"
                                                           value="<?php echo $_POST['cedula'] ?? ''; ?>" 
                                                           placeholder="Ingrese número de cédula" 
                                                           maxlength="10" 
                                                           oninput="validarCedulaInput()">
                                                    <div class="input-group-text" id="cedulaStatus">
                                                        <i class="fas fa-question text-muted"></i>
                                                    </div>
                                                </div>
                                                <div id="cedulaResult" class="mt-2" style="display: none;"></div>
                                                <div class="form-text">10 dígitos - Al consultar se completarán nombres y apellidos automáticamente</div>
                                            </div>
                                        </div>

                                        <!-- Datos básicos -->
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Nombre de Usuario <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="username" 
                                                       value="<?php echo $_POST['username'] ?? ''; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" name="email" 
                                                       value="<?php echo $_POST['email'] ?? ''; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                                                <input type="password" class="form-control" name="password" 
                                                       minlength="6" required>
                                                <div class="form-text">Mínimo 6 caracteres</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Cédula</label>
                                                <input type="text" class="form-control" name="cedula" 
                                                       value="<?php echo $_POST['cedula'] ?? ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="nombre" 
                                                       value="<?php echo $_POST['nombre'] ?? ''; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Apellido <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" name="apellido" 
                                                       value="<?php echo $_POST['apellido'] ?? ''; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Fecha de Nacimiento</label>
                                                <input type="date" class="form-control" name="fecha_nacimiento" 
                                                       value="<?php echo $_POST['fecha_nacimiento'] ?? ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Género</label>
                                                <select class="form-select" name="genero">
                                                    <option value="">Seleccionar...</option>
                                                    <option value="M" <?php echo (($_POST['genero'] ?? '') == 'M') ? 'selected' : ''; ?>>Masculino</option>
                                                    <option value="F" <?php echo (($_POST['genero'] ?? '') == 'F') ? 'selected' : ''; ?>>Femenino</option>
                                                    <option value="O" <?php echo (($_POST['genero'] ?? '') == 'O') ? 'selected' : ''; ?>>Otro</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Teléfono</label>
                                                <input type="text" class="form-control" name="telefono" 
                                                       value="<?php echo $_POST['telefono'] ?? ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3" id="sucursalField" style="display: none;">
                                                <label class="form-label">Sucursal <span class="text-danger" id="sucursalRequired">*</span></label>
                                                <select class="form-select" name="id_sucursal" id="sucursalSelect">
                                                    <option value="">Seleccionar sucursal...</option>
                                                    <?php foreach ($sucursales as $sucursal): ?>
                                                        <option value="<?php echo $sucursal['id_sucursal']; ?>"
                                                                <?php echo (($_POST['id_sucursal'] ?? '') == $sucursal['id_sucursal']) ? 'selected' : ''; ?>>
                                                                    <?php echo $sucursal['nombre_sucursal']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="mb-3">
                                                <label class="form-label">Dirección</label>
                                                <textarea class="form-control" name="direccion" rows="2"><?php echo $_POST['direccion'] ?? ''; ?></textarea>
                                            </div>
                                        </div>

                                        <!-- Campo especial para médicos -->
                                        <div class="col-12" id="especialidadesField" style="display: none;">
                                            <div class="mb-3">
                                                <label class="form-label">Especialidades <span class="text-danger">*</span></label>
                                                <div class="row">
                                                    <?php foreach ($especialidades as $especialidad): ?>
                                                        <div class="col-md-4 mb-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" 
                                                                       name="especialidades[]" 
                                                                       value="<?php echo $especialidad['id_especialidad']; ?>"
                                                                       id="esp_<?php echo $especialidad['id_especialidad']; ?>"
                                                                       <?php echo in_array($especialidad['id_especialidad'], $_POST['especialidades'] ?? []) ? 'checked' : ''; ?>>
                                                                <label class="form-check-label" for="esp_<?php echo $especialidad['id_especialidad']; ?>">
                                                                    <?php echo $especialidad['nombre_especialidad']; ?>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="form-text">Seleccione una o más especialidades</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-light">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="index.php?action=admin/usuarios" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Crear Usuario
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Descripciones de roles
    const roleDescriptions = {
        '1': 'Administrador: Acceso completo al sistema, gestión de usuarios y configuraciones.',
        '2': 'Recepcionista: Gestión de pacientes, citas y pagos. Requiere asignación de sucursal.',
        '3': 'Médico: Atención de pacientes y gestión de consultas. Requiere sucursal y especialidades.',
        '4': 'Paciente: Acceso limitado para gestión personal de citas y datos.'
    };

    function updateForm() {
        const roleSelect = document.getElementById('roleSelect');
        const selectedRole = roleSelect.value;
        const formContainer = document.getElementById('userFormContainer');
        const roleInfo = document.getElementById('roleInfo');
        const roleDescription = document.getElementById('roleDescription');
        const sucursalField = document.getElementById('sucursalField');
        const especialidadesField = document.getElementById('especialidadesField');
        const sucursalSelect = document.getElementById('sucursalSelect');
        const sucursalRequired = document.getElementById('sucursalRequired');

        if (selectedRole) {
            // Mostrar formulario
            formContainer.style.display = 'block';

            // Mostrar información del rol
            roleInfo.style.display = 'block';
            roleDescription.textContent = roleDescriptions[selectedRole] || 'Rol seleccionado';

            // Resetear campos
            sucursalField.style.display = 'none';
            especialidadesField.style.display = 'none';
            sucursalSelect.required = false;
            sucursalRequired.style.display = 'none';

            // Configurar campos según el rol
            if (selectedRole === '2' || selectedRole === '3') {
                // Recepcionista o Médico requieren sucursal
                sucursalField.style.display = 'block';
                sucursalSelect.required = true;
                sucursalRequired.style.display = 'inline';
            }

            if (selectedRole === '3') {
                // Médico requiere especialidades
                especialidadesField.style.display = 'block';
            }
        } else {
            formContainer.style.display = 'none';
            roleInfo.style.display = 'none';
        }
    }

    // Ejecutar al cargar la página si hay un rol seleccionado
    document.addEventListener('DOMContentLoaded', function () {
        updateForm();
    });

    // Funciones para consulta de cédula
    function validarCedulaInput() {
        const cedulaInput = document.getElementById('cedulaInput');
        const btnConsultar = document.getElementById('btnConsultarCedula');
        const cedulaStatus = document.getElementById('cedulaStatus');
        const cedulaResult = document.getElementById('cedulaResult');
        const cedula = cedulaInput.value.replace(/\D/g, ''); // Solo números

        // Actualizar el input solo con números
        cedulaInput.value = cedula;

        // Resetear resultado anterior
        cedulaResult.style.display = 'none';

        if (cedula.length === 10) {
            if (validarCedulaEcuatoriana(cedula)) {
                cedulaStatus.innerHTML = '<i class="fas fa-check text-success"></i>';
                btnConsultar.disabled = false;
                cedulaInput.classList.remove('is-invalid');
                cedulaInput.classList.add('is-valid');
            } else {
                cedulaStatus.innerHTML = '<i class="fas fa-times text-danger"></i>';
                btnConsultar.disabled = true;
                cedulaInput.classList.remove('is-valid');
                cedulaInput.classList.add('is-invalid');
            }
        } else if (cedula.length > 0) {
            cedulaStatus.innerHTML = '<i class="fas fa-clock text-warning"></i>';
            btnConsultar.disabled = true;
            cedulaInput.classList.remove('is-valid', 'is-invalid');
        } else {
            cedulaStatus.innerHTML = '<i class="fas fa-question text-muted"></i>';
            btnConsultar.disabled = true;
            cedulaInput.classList.remove('is-valid', 'is-invalid');
        }
    }

    function validarCedulaEcuatoriana(cedula) {
        if (cedula.length !== 10)
            return false;

        const digitos = cedula.split('').map(d => parseInt(d));
        let suma = 0;

        for (let i = 0; i < 9; i++) {
            let digito = digitos[i];

            if (i % 2 === 0) {
                digito *= 2;
                if (digito > 9)
                    digito -= 9;
            }

            suma += digito;
        }

        const digitoVerificador = digitos[9];
        const residuo = suma % 10;
        const resultado = residuo === 0 ? 0 : 10 - residuo;

        return digitoVerificador === resultado;
    }

    async function consultarCedula() {
        const cedula = document.getElementById('cedulaInput').value;
        const btnConsultar = document.getElementById('btnConsultarCedula');
        const cedulaResult = document.getElementById('cedulaResult');
        const nombreInput = document.querySelector('input[name="nombre"]');
        const apellidoInput = document.querySelector('input[name="apellido"]');

        if (!cedula || cedula.length !== 10) {
            return;
        }

        // Mostrar loading
        btnConsultar.disabled = true;
        btnConsultar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando...';

        try {
            const response = await fetch('views/api/consultar-cedula.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({cedula: cedula})
            });

            const data = await response.json();

            if (data.success) {
                // Éxito - completar campos
                cedulaResult.innerHTML = `
                <div class="alert alert-success alert-sm mb-0">
                    <i class="fas fa-check-circle"></i> 
                    <strong>Datos encontrados:</strong> ${data.nombres} ${data.apellidos}
                </div>
            `;
                cedulaResult.style.display = 'block';

                // Completar campos automáticamente
                if (data.nombres) {
                    nombreInput.value = data.nombres;
                    nombreInput.classList.add('is-valid');
                }
                if (data.apellidos) {
                    apellidoInput.value = data.apellidos;
                    apellidoInput.classList.add('is-valid');
                }

                // Highlight de los campos completados
                setTimeout(() => {
                    nombreInput.classList.remove('is-valid');
                    apellidoInput.classList.remove('is-valid');
                }, 3000);

            } else {
                // Error
                cedulaResult.innerHTML = `
                <div class="alert alert-warning alert-sm mb-0">
                    <i class="fas fa-exclamation-triangle"></i> 
                    ${data.error || 'No se encontraron datos para esta cédula'}
                </div>
            `;
                cedulaResult.style.display = 'block';
            }

        } catch (error) {
            cedulaResult.innerHTML = `
            <div class="alert alert-danger alert-sm mb-0">
                <i class="fas fa-times-circle"></i> 
                Error de conexión. Verifique su internet e inténtelo nuevamente.
            </div>
        `;
            cedulaResult.style.display = 'block';
        } finally {
            // Restaurar botón
            btnConsultar.disabled = false;
            btnConsultar.innerHTML = '<i class="fas fa-search"></i> Consultar';
        }
    }

// CSS adicional para alerts pequeños
    const style = document.createElement('style');
    style.textContent = `
    .alert-sm {
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
    }
    .input-group-text {
        min-width: 40px;
        justify-content: center;
    }
`;
    document.head.appendChild(style);
</script>

</body>
</html>
}