<?php
require_once 'models/Cita.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

$citaModel = new Cita();
$error = '';
$success = '';

// Filtros
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d', strtotime('+7 days'));
$estado_filter = $_GET['estado'] ?? 'todas';

// Procesar acciones (cambiar estado de citas)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'confirmar_cita':
                $citaId = $_POST['cita_id'];
                $citaModel->updateEstadoCita($citaId, 'confirmada');
                $success = "Cita confirmada exitosamente";
                break;

            case 'cancelar_cita':
                $citaId = $_POST['cita_id'];
                $motivo = $_POST['motivo_cancelacion'] ?? 'Cancelada por el usuario';
                $citaModel->updateEstadoCita($citaId, 'cancelada', $motivo);
                $success = "Cita cancelada exitosamente";
                break;

            case 'completar_cita':
                $citaId = $_POST['cita_id'];
                $observaciones = $_POST['observaciones'] ?? '';
                $citaModel->updateEstadoCita($citaId, 'completada', $observaciones);
                $success = "Cita marcada como completada";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener citas según el rol
$citas = [];
switch ($_SESSION['role_id']) {
    case 3: // Médico
        $citas = $citaModel->getCitasMedicoConFiltros($_SESSION['user_id'], $fechaInicio, $fechaFin, $estado_filter);
        $tituloAgenda = "Mi Agenda Médica";
        $vistaRol = "medico";
        break;

    case 4: // Paciente
        $citas = $citaModel->getCitasPacienteConFiltros($_SESSION['user_id'], $fechaInicio, $fechaFin, $estado_filter);
        $tituloAgenda = "Mis Citas Médicas";
        $vistaRol = "paciente";
        break;

    case 1: // Administrador
    case 2: // Recepcionista
        $citas = $citaModel->getCitasGlobalesConFiltros($fechaInicio, $fechaFin, $estado_filter, $_SESSION['user_id']);
        $tituloAgenda = "Agenda General";
        $vistaRol = "admin";
        break;

    default:
        header('Location: index.php?action=dashboard');
        exit;
}

// Estadísticas rápidas
$estadisticas = [
    'total' => count($citas),
    'agendadas' => count(array_filter($citas, fn($c) => $c['estado_cita'] === 'agendada')),
    'confirmadas' => count(array_filter($citas, fn($c) => $c['estado_cita'] === 'confirmada')),
    'completadas' => count(array_filter($citas, fn($c) => $c['estado_cita'] === 'completada')),
    'canceladas' => count(array_filter($citas, fn($c) => $c['estado_cita'] === 'cancelada'))
];

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
                        <i class="fas fa-calendar"></i> <?php echo $tituloAgenda; ?>
                    </h2>
                    <p class="text-muted mb-0">
                        <?php if ($vistaRol === 'medico'): ?>
                            Gestione sus citas médicas y consultas programadas
                        <?php elseif ($vistaRol === 'paciente'): ?>
                            Revise sus citas médicas programadas
                        <?php else: ?>
                            Vista general de todas las citas del sistema
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <?php if ($vistaRol !== 'paciente'): ?>
                        <a href="index.php?action=citas/agendar" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Nueva Cita
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mensajes -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-day text-primary" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0"><?php echo $estadisticas['total']; ?></h4>
                            <small class="text-muted">Total Citas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-clock text-warning" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0"><?php echo $estadisticas['agendadas']; ?></h4>
                            <small class="text-muted">Agendadas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle text-success" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0"><?php echo $estadisticas['confirmadas']; ?></h4>
                            <small class="text-muted">Confirmadas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-star text-info" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0"><?php echo $estadisticas['completadas']; ?></h4>
                            <small class="text-muted">Completadas</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="citas/agenda">

                        <div class="col-md-3">
                            <label class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" name="fecha_inicio" 
                                   value="<?php echo $fechaInicio; ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" name="fecha_fin" 
                                   value="<?php echo $fechaFin; ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="todas" <?php echo ($estado_filter === 'todas') ? 'selected' : ''; ?>>Todas</option>
                                <option value="agendada" <?php echo ($estado_filter === 'agendada') ? 'selected' : ''; ?>>Agendadas</option>
                                <option value="confirmada" <?php echo ($estado_filter === 'confirmada') ? 'selected' : ''; ?>>Confirmadas</option>
                                <option value="en_curso" <?php echo ($estado_filter === 'en_curso') ? 'selected' : ''; ?>>En Curso</option>
                                <option value="completada" <?php echo ($estado_filter === 'completada') ? 'selected' : ''; ?>>Completadas</option>
                                <option value="cancelada" <?php echo ($estado_filter === 'cancelada') ? 'selected' : ''; ?>>Canceladas</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Citas -->
            <div class="card border-0 shadow">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-list"></i> 
                        Citas del <?php echo date('d/m/Y', strtotime($fechaInicio)); ?> al <?php echo date('d/m/Y', strtotime($fechaFin)); ?>
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($citas)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-muted">No hay citas programadas</h5>
                            <p class="text-muted">
                                <?php if ($vistaRol === 'paciente'): ?>
                                    No tiene citas programadas en el rango seleccionado.
                                <?php else: ?>
                                    No hay citas programadas en el rango seleccionado.
                                <?php endif; ?>
                            </p>
                            <?php if ($vistaRol !== 'paciente'): ?>
                                <a href="index.php?action=citas/agendar" class="btn btn-primary">
                                    <i class="fas fa-calendar-plus"></i> Agendar Nueva Cita
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha/Hora</th>
                                        <?php if ($vistaRol === 'medico'): ?>
                                            <th>Paciente</th>
                                            <th>Contacto</th>
                                        <?php elseif ($vistaRol === 'paciente'): ?>
                                            <th>Médico</th>
                                            <th>Especialidad</th>
                                        <?php else: ?>
                                            <th>Paciente</th>
                                            <th>Médico</th>
                                            <th>Especialidad</th>
                                        <?php endif; ?>
                                        <th>Sucursal</th>
                                        <th>Tipo</th>
                                        <th>Estado</th>
                                        <th>Motivo</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($citas as $cita): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></strong>
                                                    <br>
                                                    <span class="text-muted"><?php echo date('H:i', strtotime($cita['hora_cita'])); ?></span>
                                                </div>
                                            </td>

                                            <?php if ($vistaRol === 'medico'): ?>
                                                <td>
                                                    <div>
                                                        <strong><?php echo $cita['paciente_nombre']; ?></strong>
                                                        <br>
                                                        <small class="text-muted">CI: <?php echo $cita['paciente_cedula'] ?? 'N/A'; ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if (!empty($cita['paciente_telefono'])): ?>
                                                        <a href="tel:<?php echo $cita['paciente_telefono']; ?>" class="text-decoration-none">
                                                            <i class="fas fa-phone text-primary"></i> <?php echo $cita['paciente_telefono']; ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin teléfono</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php elseif ($vistaRol === 'paciente'): ?>
                                                <td>
                                                    <strong>Dr. <?php echo $cita['medico_nombre']; ?></strong>
                                                </td>
                                                <td><?php echo $cita['nombre_especialidad']; ?></td>
                                            <?php else: ?>
                                                <td><?php echo $cita['paciente_nombre']; ?></td>
                                                <td>Dr. <?php echo $cita['medico_nombre']; ?></td>
                                                <td><?php echo $cita['nombre_especialidad']; ?></td>
                                            <?php endif; ?>

                                            <td><?php echo $cita['nombre_sucursal']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $cita['tipo_cita'] === 'virtual' ? 'info' : 'primary'; ?>">
                                                    <i class="fas fa-<?php echo $cita['tipo_cita'] === 'virtual' ? 'video' : 'hospital'; ?>"></i>
                                                    <?php echo ucfirst($cita['tipo_cita']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $badgeColors = [
                                                    'agendada' => 'warning',
                                                    'confirmada' => 'success',
                                                    'en_curso' => 'info',
                                                    'completada' => 'primary',
                                                    'cancelada' => 'danger',
                                                    'no_asistio' => 'secondary'
                                                ];
                                                $badgeColor = $badgeColors[$cita['estado_cita']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $badgeColor; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $cita['estado_cita'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php
                                                    echo strlen($cita['motivo_consulta']) > 50 ? substr($cita['motivo_consulta'], 0, 50) . '...' : $cita['motivo_consulta'];
                                                    ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <!-- Botón Ver Detalles -->
                                                    <button class="btn btn-outline-info" 
                                                            onclick="verDetallesCita(<?php echo $cita['id_cita']; ?>, '<?php echo addslashes(json_encode($cita)); ?>')"
                                                            title="Ver Detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </button>

                                                    <?php if ($vistaRol !== 'paciente'): ?>
                                                        <!-- Acciones según estado -->
                                                        <?php if ($cita['estado_cita'] === 'agendada'): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="action" value="confirmar_cita">
                                                                <input type="hidden" name="cita_id" value="<?php echo $cita['id_cita']; ?>">
                                                                <button type="submit" class="btn btn-outline-success" title="Confirmar">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>

                                                        <?php if (in_array($cita['estado_cita'], ['agendada', 'confirmada'])): ?>
                                                            <button class="btn btn-outline-danger" 
                                                                    onclick="cancelarCita(<?php echo $cita['id_cita']; ?>, '<?php echo addslashes($cita['paciente_nombre'] ?? $cita['medico_nombre']); ?>')"
                                                                    title="Cancelar">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <?php if ($cita['estado_cita'] === 'confirmada' && $vistaRol === 'medico'): ?>
                                                            <button class="btn btn-outline-primary" 
                                                                    onclick="completarCita(<?php echo $cita['id_cita']; ?>)"
                                                                    title="Marcar como Completada">
                                                                <i class="fas fa-check-double"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>

                                                    <!-- Triaje si es médico o recepcionista -->
                                                    <?php if (in_array($vistaRol, ['medico', 'admin']) && $cita['estado_cita'] === 'confirmada'): ?>
                                                        <a href="index.php?action=consultas/triaje/ver&cita_id=<?php echo $cita['id_cita']; ?>" 
                                                           class="btn btn-outline-secondary" title="Ver Triaje">
                                                            <i class="fas fa-clipboard-list"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalles de Cita -->
<div class="modal fade" id="modalDetallesCita" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-alt"></i> Detalles de la Cita
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detallesCitaContent">
                <!-- Contenido se carga dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cancelar Cita -->
<div class="modal fade" id="modalCancelarCita" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle text-warning"></i> Cancelar Cita
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="cancelar_cita">
                <input type="hidden" name="cita_id" id="cancelarCitaId">

                <div class="modal-body">
                    <p>¿Está seguro que desea cancelar la cita de <strong id="cancelarCitaNombre"></strong>?</p>

                    <div class="mb-3">
                        <label class="form-label">Motivo de cancelación</label>
                        <textarea class="form-control" name="motivo_cancelacion" rows="3" 
                                  placeholder="Ingrese el motivo de la cancelación..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, mantener cita</button>
                    <button type="submit" class="btn btn-danger">Sí, cancelar cita</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Completar Cita -->
<div class="modal fade" id="modalCompletarCita" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle text-success"></i> Completar Cita
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="completar_cita">
                <input type="hidden" name="cita_id" id="completarCitaId">

                <div class="modal-body">
                    <p>Marcar la cita como completada.</p>

                    <div class="mb-3">
                        <label class="form-label">Observaciones finales (opcional)</label>
                        <textarea class="form-control" name="observaciones" rows="3" 
                                  placeholder="Ingrese observaciones sobre la consulta..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Marcar como Completada</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .avatar-md {
        width: 48px;
        height: 48px;
        font-size: 1.2rem;
    }

    .table td {
        vertical-align: middle;
    }

    .badge {
        font-size: 0.75rem;
    }

    .btn-group-sm .btn {
        margin-right: 2px;
    }

    .card-body {
        padding: 1.5rem;
    }

    @media (max-width: 768px) {
        .table-responsive {
            font-size: 0.875rem;
        }

        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .badge {
            font-size: 0.65rem;
        }
    }

    /* Estados de citas */
    .estado-agendada {
        color: #f57c00;
    }
    .estado-confirmada {
        color: #2e7d32;
    }
    .estado-en-curso {
        color: #1976d2;
    }
    .estado-completada {
        color: #5e35b1;
    }
    .estado-cancelada {
        color: #d32f2f;
    }
    .estado-no-asistio {
        color: #616161;
    }

    /* Hover effects */
    .table-hover tbody tr:hover {
        background-color: rgba(0,123,255,0.075);
    }

    .btn-outline-info:hover,
    .btn-outline-success:hover,
    .btn-outline-danger:hover,
    .btn-outline-primary:hover,
    .btn-outline-secondary:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
</style>

<script>
// Función para ver detalles de la cita
    function verDetallesCita(citaId, citaData) {
        const cita = JSON.parse(citaData);

        const content = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary">Información de la Cita</h6>
                <table class="table table-sm">
                    <tr>
                        <td><strong>ID Cita:</strong></td>
                        <td>${cita.id_cita}</td>
                    </tr>
                    <tr>
                        <td><strong>Fecha:</strong></td>
                        <td>${new Date(cita.fecha_cita).toLocaleDateString('es-ES')}</td>
                    </tr>
                    <tr>
                        <td><strong>Hora:</strong></td>
                        <td>${cita.hora_cita}</td>
                    </tr>
                    <tr>
                        <td><strong>Tipo:</strong></td>
                        <td>${cita.tipo_cita}</td>
                    </tr>
                    <tr>
                        <td><strong>Estado:</strong></td>
                        <td><span class="badge bg-info">${cita.estado_cita}</span></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary">Participantes</h6>
                <table class="table table-sm">
                    ${cita.paciente_nombre ? `
                    <tr>
                        <td><strong>Paciente:</strong></td>
                        <td>${cita.paciente_nombre}</td>
                    </tr>
                    ${cita.paciente_cedula ? `
                    <tr>
                        <td><strong>Cédula:</strong></td>
                        <td>${cita.paciente_cedula}</td>
                    </tr>` : ''}
                    ${cita.paciente_telefono ? `
                    <tr>
                        <td><strong>Teléfono:</strong></td>
                        <td>${cita.paciente_telefono}</td>
                    </tr>` : ''}
                    ` : ''}
                    ${cita.medico_nombre ? `
                    <tr>
                        <td><strong>Médico:</strong></td>
                        <td>Dr. ${cita.medico_nombre}</td>
                    </tr>` : ''}
                    <tr>
                        <td><strong>Especialidad:</strong></td>
                        <td>${cita.nombre_especialidad}</td>
                    </tr>
                    <tr>
                        <td><strong>Sucursal:</strong></td>
                        <td>${cita.nombre_sucursal}</td>
                    </tr>
                </table>
            </div>
        </div>
        ${cita.motivo_consulta ? `
        <div class="mt-3">
            <h6 class="text-primary">Motivo de Consulta</h6>
            <p class="text-muted">${cita.motivo_consulta}</p>
        </div>` : ''}
        ${cita.observaciones ? `
        <div class="mt-3">
            <h6 class="text-primary">Observaciones</h6>
            <p class="text-muted">${cita.observaciones}</p>
        </div>` : ''}
    `;

        document.getElementById('detallesCitaContent').innerHTML = content;

        const modal = new bootstrap.Modal(document.getElementById('modalDetallesCita'));
        modal.show();
    }

// Función para cancelar cita
    function cancelarCita(citaId, nombrePaciente) {
        document.getElementById('cancelarCitaId').value = citaId;
        document.getElementById('cancelarCitaNombre').textContent = nombrePaciente;

        const modal = new bootstrap.Modal(document.getElementById('modalCancelarCita'));
        modal.show();
    }

// Función para completar cita
    function completarCita(citaId) {
        document.getElementById('completarCitaId').value = citaId;

        const modal = new bootstrap.Modal(document.getElementById('modalCompletarCita'));
        modal.show();
    }
</script>
