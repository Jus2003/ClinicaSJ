<?php
// Verificar permisos (solo admin o recepcionista)
if (!in_array($_SESSION['role_id'], [1, 2])) {
    header('Location: index.php?action=dashboard');
    exit;
}

require_once 'models/User.php';

$userModel = new User();
$error = '';
$success = '';

// Manejar acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        switch ($_POST['action']) {
            case 'toggle_status':
                $userId = (int) $_POST['user_id'];
                $userModel->toggleUserStatus($userId);
                echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
                break;

            case 'delete_user':
                $userId = (int) $_POST['user_id'];
                // En lugar de eliminar, desactivamos permanentemente
                $userModel->toggleUserStatus($userId);
                echo json_encode(['success' => true, 'message' => 'Paciente eliminado correctamente']);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Obtener todos los pacientes
$pacientes = $userModel->getPacientes();

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
                        <i class="fas fa-users"></i> Gestionar Pacientes
                    </h2>
                    <p class="text-muted mb-0">Administración de pacientes registrados en el sistema</p>
                </div>
                <div>
                    <a href="index.php?action=pacientes/registrar" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Nuevo Paciente
                    </a>
                </div>
            </div>

            <!-- Mensajes -->
            <div id="mensajes-container"></div>

            <!-- Filtros y búsqueda -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">
                                <i class="fas fa-search"></i> Buscar paciente
                            </label>
                            <input type="text" class="form-control" id="buscarPaciente" 
                                   placeholder="Nombre, apellido, cédula o email...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">
                                <i class="fas fa-filter"></i> Estado
                            </label>
                            <select class="form-select" id="filtroEstado">
                                <option value="">Todos</option>
                                <option value="1" selected>Activos</option>
                                <option value="0">Inactivos</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">
                                <i class="fas fa-sort"></i> Ordenar por
                            </label>
                            <select class="form-select" id="ordenarPor">
                                <option value="nombre">Nombre</option>
                                <option value="fecha_registro">Fecha de registro</option>
                                <option value="ultimo_acceso">Último acceso</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-outline-secondary w-100" onclick="limpiarFiltros()">
                                <i class="fas fa-times"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estadísticas rápidas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-primary text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x mb-2"></i>
                            <h3 class="mb-0" id="totalPacientes"><?php echo count($pacientes); ?></h3>
                            <small>Total Pacientes</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-success text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-user-check fa-2x mb-2"></i>
                            <h3 class="mb-0" id="pacientesActivos"><?php echo count(array_filter($pacientes, fn($p) => $p['activo'] == 1)); ?></h3>
                            <small>Activos</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-warning text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-user-times fa-2x mb-2"></i>
                            <h3 class="mb-0" id="pacientesInactivos"><?php echo count(array_filter($pacientes, fn($p) => $p['activo'] == 0)); ?></h3>
                            <small>Inactivos</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm bg-info text-white">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-plus fa-2x mb-2"></i>
                            <h3 class="mb-0"><?php echo count(array_filter($pacientes, fn($p) => date('Y-m-d', strtotime($p['fecha_registro'])) >= date('Y-m-d', strtotime('-30 days')))); ?></h3>
                            <small>Nuevos (30 días)</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de pacientes -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pb-0">
                    <h5 class="card-title mb-3">
                        <i class="fas fa-list"></i> Lista de Pacientes
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="tablaPacientes">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Paciente</th>
                                    <th>Contacto</th>
                                    <th>Información</th>
                                    <th style="width: 100px;">Estado</th>
                                    <th style="width: 120px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pacientes as $index => $paciente): ?>
                                    <tr data-paciente-id="<?php echo $paciente['id_usuario']; ?>" 
                                        data-estado="<?php echo $paciente['activo']; ?>"
                                        data-searchable="<?php echo strtolower($paciente['nombre'] . ' ' . $paciente['apellido'] . ' ' . $paciente['cedula'] . ' ' . $paciente['email']); ?>">
                                        <td>
                                            <div class="fw-bold text-muted"><?php echo $index + 1; ?></div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle bg-primary text-white me-3">
                                                    <?php echo strtoupper(substr($paciente['nombre'], 0, 1) . substr($paciente['apellido'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold">
                                                        <?php echo htmlspecialchars($paciente['nombre'] . ' ' . $paciente['apellido']); ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($paciente['username']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <?php if ($paciente['email']): ?>
                                                    <div class="mb-1">
                                                        <i class="fas fa-envelope text-muted"></i> 
                                                        <?php echo htmlspecialchars($paciente['email']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($paciente['telefono']): ?>
                                                    <div class="mb-1">
                                                        <i class="fas fa-phone text-muted"></i> 
                                                        <?php echo htmlspecialchars($paciente['telefono']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <?php if ($paciente['cedula']): ?>
                                                    <div class="mb-1">
                                                        <i class="fas fa-id-card text-muted"></i> 
                                                        <?php echo htmlspecialchars($paciente['cedula']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($paciente['fecha_nacimiento']): ?>
                                                    <div class="mb-1">
                                                        <i class="fas fa-birthday-cake text-muted"></i> 
                                                        <?php
                                                        $edad = date_diff(date_create($paciente['fecha_nacimiento']), date_create('today'))->y;
                                                        echo date('d/m/Y', strtotime($paciente['fecha_nacimiento'])) . " ({$edad} años)";
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="text-muted">
                                                    <i class="fas fa-calendar-plus"></i> 
                                                    Registro: <?php echo date('d/m/Y', strtotime($paciente['fecha_registro'])); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $paciente['activo'] ? 'success' : 'danger'; ?> fs-6">
                                                <i class="fas fa-<?php echo $paciente['activo'] ? 'check-circle' : 'times-circle'; ?>"></i>
                                                <?php echo $paciente['activo'] ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="verPaciente(<?php echo $paciente['id_usuario']; ?>)"
                                                        title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" 
                                                        onclick="editarPaciente(<?php echo $paciente['id_usuario']; ?>)"
                                                        title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-<?php echo $paciente['activo'] ? 'warning' : 'success'; ?>" 
                                                        onclick="toggleEstado(<?php echo $paciente['id_usuario']; ?>, <?php echo $paciente['activo']; ?>)"
                                                        title="<?php echo $paciente['activo'] ? 'Desactivar' : 'Activar'; ?>">
                                                    <i class="fas fa-<?php echo $paciente['activo'] ? 'user-times' : 'user-check'; ?>"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (empty($pacientes)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No hay pacientes registrados</h5>
                            <p class="text-muted">Comience registrando su primer paciente</p>
                            <a href="index.php?action=pacientes/registrar" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Registrar Primer Paciente
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para ver detalles del paciente -->
<div class="modal fade" id="modalVerPaciente" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user"></i> Detalles del Paciente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalPacienteContent">
                <!-- Contenido cargado dinámicamente -->
            </div>
        </div>
    </div>
</div>

<style>
    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 14px;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(0, 123, 255, 0.05);
    }

    .btn-group-sm > .btn {
        margin: 0 1px;
    }

    .card {
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
    }
</style>

<script>
// Variables globales
    let pacientes = <?php echo json_encode($pacientes); ?>;
    let pacientesFiltrados = [...pacientes];

// Función de búsqueda en tiempo real
    document.getElementById('buscarPaciente').addEventListener('input', function () {
        filtrarPacientes();
    });

    document.getElementById('filtroEstado').addEventListener('change', function () {
        filtrarPacientes();
    });

    document.getElementById('ordenarPor').addEventListener('change', function () {
        ordenarPacientes();
    });

    function filtrarPacientes() {
        const busqueda = document.getElementById('buscarPaciente').value.toLowerCase();
        const estado = document.getElementById('filtroEstado').value;

        const filas = document.querySelectorAll('#tablaPacientes tbody tr');
        let visibles = 0;

        filas.forEach(fila => {
            const textoSearchable = fila.getAttribute('data-searchable');
            const estadoPaciente = fila.getAttribute('data-estado');

            const coincideBusqueda = !busqueda || textoSearchable.includes(busqueda);
            const coincedeEstado = !estado || estadoPaciente === estado;

            if (coincideBusqueda && coincedeEstado) {
                fila.style.display = '';
                visibles++;
            } else {
                fila.style.display = 'none';
            }
        });

        // Actualizar contador
        document.querySelector('.card-title').innerHTML =
                `<i class="fas fa-list"></i> Lista de Pacientes (${visibles} mostrados)`;
    }

    function limpiarFiltros() {
        document.getElementById('buscarPaciente').value = '';
        document.getElementById('filtroEstado').value = '1';
        document.getElementById('ordenarPor').value = 'nombre';
        filtrarPacientes();
    }

    function verPaciente(userId) {
        const paciente = pacientes.find(p => p.id_usuario == userId);
        if (!paciente)
            return;

        const content = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary"><i class="fas fa-user"></i> Información Personal</h6>
                <table class="table table-sm">
                    <tr><td><strong>Nombre completo:</strong></td><td>${paciente.nombre} ${paciente.apellido}</td></tr>
                    <tr><td><strong>Usuario:</strong></td><td>${paciente.username}</td></tr>
                    <tr><td><strong>Cédula:</strong></td><td>${paciente.cedula || 'No registrada'}</td></tr>
                    <tr><td><strong>Género:</strong></td><td>${paciente.genero === 'M' ? 'Masculino' : paciente.genero === 'F' ? 'Femenino' : paciente.genero || 'No especificado'}</td></tr>
                    <tr><td><strong>Fecha de nacimiento:</strong></td><td>${paciente.fecha_nacimiento ? new Date(paciente.fecha_nacimiento).toLocaleDateString('es-ES') : 'No registrada'}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary"><i class="fas fa-envelope"></i> Contacto</h6>
                <table class="table table-sm">
                    <tr><td><strong>Email:</strong></td><td>${paciente.email}</td></tr>
                    <tr><td><strong>Teléfono:</strong></td><td>${paciente.telefono || 'No registrado'}</td></tr>
                    <tr><td><strong>Dirección:</strong></td><td>${paciente.direccion || 'No registrada'}</td></tr>
                </table>
                
                <h6 class="text-primary mt-3"><i class="fas fa-info-circle"></i> Estado del Sistema</h6>
                <table class="table table-sm">
                    <tr><td><strong>Estado:</strong></td><td><span class="badge bg-${paciente.activo ? 'success' : 'danger'}">${paciente.activo ? 'Activo' : 'Inactivo'}</span></td></tr>
                    <tr><td><strong>Fecha de registro:</strong></td><td>${new Date(paciente.fecha_registro).toLocaleDateString('es-ES')}</td></tr>
                </table>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-sm" onclick="editarPaciente(${paciente.id_usuario})">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                    <button class="btn btn-outline-info btn-sm" onclick="window.open('index.php?action=pacientes/historial&id=${paciente.id_usuario}', '_blank')">
                        <i class="fas fa-file-medical"></i> Ver Historial
                    </button>
                </div>
            </div>
        </div>
    `;

        document.getElementById('modalPacienteContent').innerHTML = content;
        new bootstrap.Modal(document.getElementById('modalVerPaciente')).show();
    }

    function editarPaciente(userId) {
        window.location.href = `index.php?action=pacientes/editar&id=${userId}`;
    }

    function toggleEstado(userId, estadoActual) {
        const accion = estadoActual ? 'desactivar' : 'activar';

        if (confirm(`¿Está seguro que desea ${accion} este paciente?`)) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle_status&user_id=${userId}`
            })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            mostrarMensaje('success', data.message);
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            mostrarMensaje('error', data.message);
                        }
                    })
                    .catch(error => {
                        mostrarMensaje('error', 'Error de conexión');
                    });
        }
    }

    function mostrarMensaje(tipo, mensaje) {
        const alertClass = tipo === 'success' ? 'alert-success' : 'alert-danger';
        const iconClass = tipo === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';

        const html = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <i class="fas ${iconClass}"></i> ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

        document.getElementById('mensajes-container').innerHTML = html;

        // Auto-ocultar después de 5 segundos
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.remove();
            }
        }, 5000);
    }

// Inicializar filtros al cargar la página
    document.addEventListener('DOMContentLoaded', function () {
        filtrarPacientes();
    });
</script>

