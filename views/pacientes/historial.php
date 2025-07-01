<?php
// Verificar permisos (admin, recepcionista, médico o paciente)
if (!in_array($_SESSION['role_id'], [1, 2, 3, 4])) {
    header('Location: index.php?action=dashboard');
    exit;
}

require_once 'models/User.php';

$userModel = new User();
$error = '';
$success = '';

// Obtener ID del paciente
$pacienteId = $_GET['id'] ?? 0;

// Si es paciente (rol 4), mostrar su propio historial
if ($_SESSION['role_id'] == 4) {
    $pacienteId = $_SESSION['user_id'];
}

// Si no hay ID y no es paciente, mostrar lista de pacientes para seleccionar
if (!$pacienteId && $_SESSION['role_id'] != 4) {
    $pacientes = $userModel->getPacientes();
    include 'views/includes/header.php';
    include 'views/includes/navbar.php';
    ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="text-primary">
                            <i class="fas fa-file-medical"></i> Historial Médico
                        </h2>
                        <p class="text-muted mb-0">Seleccione un paciente para ver su historial médico</p>
                    </div>
                    <div>
                        <a href="index.php?action=pacientes/gestionar" class="btn btn-outline-secondary">
                            <i class="fas fa-users"></i> Gestionar Pacientes
                        </a>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-users"></i> Seleccionar Paciente
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-search"></i> Buscar paciente
                                </label>
                                <input type="text" class="form-control" id="buscarPaciente" 
                                       placeholder="Nombre, apellido, cédula o email...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    <i class="fas fa-filter"></i> Estado
                                </label>
                                <select class="form-select" id="filtroEstado">
                                    <option value="">Todos</option>
                                    <option value="1" selected>Solo activos</option>
                                    <option value="0">Solo inactivos</option>
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Paciente</th>
                                        <th>Contacto</th>
                                        <th>Estado</th>
                                        <th>Registro</th>
                                        <th width="120">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tablaPacientes">
                                    <?php foreach ($pacientes as $paciente): ?>
                                        <tr data-searchable="<?php echo strtolower($paciente['nombre'] . ' ' . $paciente['apellido'] . ' ' . $paciente['cedula'] . ' ' . $paciente['email']); ?>"
                                            data-estado="<?php echo $paciente['activo']; ?>">
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
                                                        <?php if ($paciente['cedula']): ?>
                                                            <br><small class="text-muted">
                                                                <i class="fas fa-id-card"></i> <?php echo $paciente['cedula']; ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <div class="mb-1">
                                                        <i class="fas fa-envelope text-muted"></i> 
                                                        <?php echo htmlspecialchars($paciente['email']); ?>
                                                    </div>
                                                    <?php if ($paciente['telefono']): ?>
                                                        <div>
                                                            <i class="fas fa-phone text-muted"></i> 
                                                            <?php echo htmlspecialchars($paciente['telefono']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $paciente['activo'] ? 'success' : 'danger'; ?> fs-6">
                                                    <i class="fas fa-<?php echo $paciente['activo'] ? 'check-circle' : 'times-circle'; ?>"></i>
                                                    <?php echo $paciente['activo'] ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('d/m/Y', strtotime($paciente['fecha_registro'])); ?></small>
                                            </td>
                                            <td>
                                                <a href="index.php?action=pacientes/historial&id=<?php echo $paciente['id_usuario']; ?>" 
                                                   class="btn btn-primary btn-sm">
                                                    <i class="fas fa-file-medical"></i> Ver Historial
                                                </a>
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
                                <p class="text-muted">Registre pacientes para poder ver sus historiales médicos</p>
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
    </style>

    <script>
        // Búsqueda en tiempo real
        document.getElementById('buscarPaciente').addEventListener('input', filtrarPacientes);
        document.getElementById('filtroEstado').addEventListener('change', filtrarPacientes);

        function filtrarPacientes() {
            const busqueda = document.getElementById('buscarPaciente').value.toLowerCase();
            const estado = document.getElementById('filtroEstado').value;
            const filas = document.querySelectorAll('#tablaPacientes tr');
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

            // Mostrar contador
            document.querySelector('.card-title').innerHTML =
                    `<i class="fas fa-users"></i> Seleccionar Paciente (${visibles} mostrados)`;
        }

        // Inicializar filtros
        document.addEventListener('DOMContentLoaded', function () {
            filtrarPacientes();
        });
    </script>

    <?php
    include 'views/includes/footer.php';
    exit; // Importante: salir aquí para no mostrar el resto del contenido
}

// Continuar con el código del historial individual...
// Obtener datos del paciente
$paciente = $userModel->getUserById($pacienteId);
if (!$paciente || $paciente['id_rol'] != 4) {
    if ($_SESSION['role_id'] == 4) {
        // Si es paciente y hay error, redirigir al dashboard
        header('Location: index.php?action=dashboard');
    } else {
        // Si es staff y hay error, volver a la lista
        header('Location: index.php?action=pacientes/historial');
    }
    exit;
}

// Verificar permisos: solo el propio paciente o staff pueden ver el historial
if ($_SESSION['role_id'] == 4 && $_SESSION['user_id'] != $pacienteId) {
    header('Location: index.php?action=dashboard');
    exit;
}

// Obtener historial médico del paciente
$historial = $userModel->getHistorialPaciente($pacienteId);

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
                        <i class="fas fa-file-medical"></i> 
                        <?php echo ($_SESSION['role_id'] == 4) ? 'Mi Historial Médico' : 'Historial Médico'; ?>
                    </h2>
                    <p class="text-muted mb-0">
                        <?php if ($_SESSION['role_id'] == 4): ?>
                            Su historial clínico completo
                        <?php else: ?>
                            Historial clínico de: 
                            <strong><?php echo htmlspecialchars($paciente['nombre'] . ' ' . $paciente['apellido']); ?></strong>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <?php if ($_SESSION['role_id'] != 4): ?>
                        <a href="index.php?action=pacientes/historial" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Volver a Lista
                        </a>
                        <a href="index.php?action=pacientes/editar&id=<?php echo $paciente['id_usuario']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-user-edit"></i> Editar Paciente
                        </a>
                    <?php else: ?>
                        <a href="index.php?action=dashboard" class="btn btn-outline-secondary">
                            <i class="fas fa-home"></i> Ir al Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resto del código del historial médico igual que antes... -->
            <div class="row">
                <!-- Panel lateral con información del paciente -->
                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user"></i> 
                                <?php echo ($_SESSION['role_id'] == 4) ? 'Mi Información' : 'Información del Paciente'; ?>
                            </h5>
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
                                    <td><strong>ID Paciente:</strong></td>
                                    <td><?php echo $paciente['id_usuario']; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Cédula:</strong></td>
                                    <td><?php echo $paciente['cedula'] ?: 'No registrada'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo htmlspecialchars($paciente['email']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Teléfono:</strong></td>
                                    <td><?php echo $paciente['telefono'] ?: 'No registrado'; ?></td>
                                </tr>
                                <?php if ($paciente['fecha_nacimiento']): ?>
                                    <tr>
                                        <td><strong>Fecha de Nacimiento:</strong></td>
                                        <td>
                                            <?php
                                            echo date('d/m/Y', strtotime($paciente['fecha_nacimiento']));
                                            $edad = date_diff(date_create($paciente['fecha_nacimiento']), date_create('today'))->y;
                                            echo " ({$edad} años)";
                                            ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><strong>Género:</strong></td>
                                    <td>
                                        <?php
                                        echo match ($paciente['genero']) {
                                            'M' => 'Masculino',
                                            'F' => 'Femenino',
                                            'O' => 'Otro',
                                            default => 'No especificado'
                                        };
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Registrado:</strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($paciente['fecha_registro'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Estadísticas rápidas -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-chart-pie"></i> Resumen Médico
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-2">
                                        <div class="h5 text-primary mb-1"><?php echo count($historial); ?></div>
                                        <small class="text-muted">Total Consultas</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-2">
                                        <div class="h5 text-success mb-1">
                                            <?php echo count(array_filter($historial, fn($h) => $h['estado_cita'] === 'completada')); ?>
                                        </div>
                                        <small class="text-muted">Completadas</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <div class="h5 text-warning mb-1">
                                            <?php
                                            $ultimaConsulta = !empty($historial) ? $historial[0]['fecha_cita'] : null;
                                            if ($ultimaConsulta) {
                                                $diasDesdeUltima = (new DateTime())->diff(new DateTime($ultimaConsulta))->days;
                                                echo $diasDesdeUltima;
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </div>
                                        <small class="text-muted">Días desde última</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-2">
                                        <div class="h5 text-info mb-1">
                                            <?php
                                            $especialidades = array_unique(array_column($historial, 'nombre_especialidad'));
                                            echo count($especialidades);
                                            ?>
                                        </div>
                                        <small class="text-muted">Especialidades</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historial médico -->
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history"></i> 
                                    <?php echo ($_SESSION['role_id'] == 4) ? 'Mis Consultas' : 'Historial de Consultas'; ?>
                                </h5>
                                <div class="d-flex gap-2">
                                    <select class="form-select form-select-sm" id="filtroEspecialidad" style="width: auto;">
                                        <option value="">Todas las especialidades</option>
                                        <?php
                                        $especialidades = array_unique(array_column($historial, 'nombre_especialidad'));
                                        foreach ($especialidades as $especialidad):
                                            ?>
                                            <option value="<?php echo htmlspecialchars($especialidad); ?>">
                                                <?php echo htmlspecialchars($especialidad); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select class="form-select form-select-sm" id="filtroEstadoHistorial" style="width: auto;">
                                        <option value="">Todos los estados</option>
                                        <option value="completada">Completadas</option>
                                        <option value="agendada">Agendadas</option>
                                        <option value="cancelada">Canceladas</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($historial)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-file-medical fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">
                                        <?php echo ($_SESSION['role_id'] == 4) ? 'No tiene historial médico' : 'No hay historial médico'; ?>
                                    </h5>
                                    <p class="text-muted">
                                        <?php echo ($_SESSION['role_id'] == 4) ? 'Aún no tiene consultas registradas' : 'Este paciente aún no tiene consultas registradas'; ?>
                                    </p>
                                    <?php if ($_SESSION['role_id'] == 4): ?>
                                        <a href="index.php?action=citas/agendar" class="btn btn-primary">
                                            <i class="fas fa-calendar-plus"></i> Agendar Mi Primera Cita
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="timeline">
                                    <?php foreach ($historial as $consulta): ?>
                                        <div class="timeline-item" 
                                             data-especialidad="<?php echo htmlspecialchars($consulta['nombre_especialidad']); ?>"
                                             data-estado="<?php echo $consulta['estado_cita']; ?>">
                                            <div class="timeline-marker bg-<?php
                                            echo match ($consulta['estado_cita']) {
                                                'completada' => 'success',
                                                'agendada' => 'primary',
                                                'confirmada' => 'info',
                                                'cancelada' => 'danger',
                                                'no_asistio' => 'warning',
                                                default => 'secondary'
                                            };
                                            ?>">
                                                <i class="fas fa-<?php
                                                echo match ($consulta['estado_cita']) {
                                                    'completada' => 'check',
                                                    'agendada' => 'clock',
                                                    'confirmada' => 'calendar-check',
                                                    'cancelada' => 'times',
                                                    'no_asistio' => 'exclamation',
                                                    default => 'question'
                                                };
                                                ?>"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <div class="card border-0 shadow-sm">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div>
                                                                <h6 class="mb-1">
                                                                    <i class="fas fa-user-md text-primary"></i>
                                                                    <?php echo htmlspecialchars($consulta['nombre_medico']); ?>
                                                                </h6>
                                                                <span class="badge bg-primary">
                                                                    <?php echo htmlspecialchars($consulta['nombre_especialidad']); ?>
                                                                </span>
                                                            </div>
                                                            <div class="text-end">
                                                                <div class="text-muted small">
                                                                    <?php echo date('d/m/Y', strtotime($consulta['fecha_cita'])); ?>
                                                                </div>
                                                                <span class="badge bg-<?php
                                                                echo match ($consulta['estado_cita']) {
                                                                    'completada' => 'success',
                                                                    'agendada' => 'primary',
                                                                    'confirmada' => 'info',
                                                                    'cancelada' => 'danger',
                                                                    'no_asistio' => 'warning',
                                                                    default => 'secondary'
                                                                };
                                                                ?>">
                                                                          <?php echo ucfirst($consulta['estado_cita']); ?>
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <small class="text-muted">
                                                                    <i class="fas fa-building"></i> 
                                                                    <?php echo htmlspecialchars($consulta['nombre_sucursal']); ?>
                                                                </small>
                                                            </div>
                                                            <div class="col-md-6 text-end">
                                                                <small class="text-muted">
                                                                    <i class="fas fa-video"></i> 
                                                                    <?php echo ucfirst($consulta['tipo_cita'] ?? 'presencial'); ?>
                                                                </small>
                                                            </div>
                                                        </div>

                                                        <?php if ($consulta['motivo_consulta']): ?>
                                                            <div class="mt-3">
                                                                <h6 class="text-warning mb-2">
                                                                    <i class="fas fa-clipboard"></i> Motivo de Consulta
                                                                </h6>
                                                                <p class="mb-0"><?php echo htmlspecialchars($consulta['motivo_consulta']); ?></p>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if ($consulta['diagnostico_principal']): ?>
                                                            <div class="mt-3">
                                                                <h6 class="text-success mb-2">
                                                                    <i class="fas fa-stethoscope"></i> Diagnóstico
                                                                </h6>
                                                                <p class="mb-0"><?php echo htmlspecialchars($consulta['diagnostico_principal']); ?></p>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if ($consulta['tratamiento']): ?>
                                                            <div class="mt-3">
                                                                <h6 class="text-info mb-2">
                                                                    <i class="fas fa-pills"></i> Tratamiento
                                                                </h6>
                                                                <p class="mb-0"><?php echo htmlspecialchars($consulta['tratamiento']); ?></p>
                                                            </div>
                                                        <?php endif; ?>

                                                        <?php if ($consulta['estado_cita'] === 'completada'): ?>
                                                            <div class="mt-3 d-flex gap-2">
                                                                <button class="btn btn-sm btn-outline-primary" onclick="verRecetas(<?php echo $consulta['id_cita']; ?>)">
                                                                    <i class="fas fa-prescription"></i> Ver Recetas
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-info" onclick="verDetalles(<?php echo $consulta['id_cita']; ?>)">
                                                                    <i class="fas fa-eye"></i> Ver Detalles
                                                                </button>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
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

    .timeline {
        position: relative;
        padding-left: 30px;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 30px;
    }

    .timeline-marker {
        position: absolute;
        left: -22px;
        top: 10px;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        border: 3px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .timeline-content {
        margin-left: 20px;
    }
</style>

<script>
// Filtros para el historial
    document.getElementById('filtroEspecialidad').addEventListener('change', filtrarHistorial);
    document.getElementById('filtroEstadoHistorial').addEventListener('change', filtrarHistorial);

    function filtrarHistorial() {
        const especialidad = document.getElementById('filtroEspecialidad').value;
        const estado = document.getElementById('filtroEstadoHistorial').value;

        const items = document.querySelectorAll('.timeline-item');
        let visibles = 0;

        items.forEach(item => {
            const itemEspecialidad = item.getAttribute('data-especialidad');
            const itemEstado = item.getAttribute('data-estado');

            const coincideEspecialidad = !especialidad || itemEspecialidad === especialidad;
            const coincideEstado = !estado || itemEstado === estado;

            if (coincideEspecialidad && coincideEstado) {
                item.style.display = '';
                visibles++;
            } else {
                item.style.display = 'none';
            }
        });

        // Mostrar mensaje si no hay resultados
        const timeline = document.querySelector('.timeline');
        let noResultsMsg = document.getElementById('no-results-msg');

        if (visibles === 0 && items.length > 0) {
            if (!noResultsMsg) {
                noResultsMsg = document.createElement('div');
                noResultsMsg.id = 'no-results-msg';
                noResultsMsg.className = 'text-center py-3 text-muted';
                noResultsMsg.innerHTML = '<i class="fas fa-search"></i> No se encontraron consultas con los filtros seleccionados';
                timeline.appendChild(noResultsMsg);
            }
            noResultsMsg.style.display = 'block';
        } else if (noResultsMsg) {
            noResultsMsg.style.display = 'none';
        }
    }

    function verRecetas(citaId) {
        // Implementar modal o redirección para ver recetas de la cita
        alert('Funcionalidad de ver recetas - Por implementar para cita ID: ' + citaId);
    }

    function verDetalles(citaId) {
        // Implementar modal con detalles completos de la consulta
        alert('Funcionalidad de ver detalles - Por implementar para cita ID: ' + citaId);
    }
</script>