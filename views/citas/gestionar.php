<?php
require_once 'config/database.php';

// Verificar que sea admin o recepcionista
if (!in_array($_SESSION['role_id'], [1, 2])) {
    header('Location: index.php?action=dashboard');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Filtros
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$estado = $_GET['estado'] ?? 'todos';
$buscar = $_GET['buscar'] ?? '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'cambiar_estado':
                $citaId = $_POST['cita_id'];
                $nuevoEstado = $_POST['nuevo_estado'];
                $motivo = $_POST['motivo'] ?? '';

                // Validar estados permitidos
                $estadosPermitidos = ['agendada', 'confirmada', 'en_curso', 'completada', 'cancelada', 'no_asistio'];
                if (!in_array($nuevoEstado, $estadosPermitidos)) {
                    throw new Exception('Estado no válido');
                }

                // Obtener estado anterior
                $sqlEstadoAnterior = "SELECT estado_cita FROM citas WHERE id_cita = :cita_id";
                $stmtEstadoAnterior = $db->prepare($sqlEstadoAnterior);
                $stmtEstadoAnterior->execute(['cita_id' => $citaId]);
                $estadoAnterior = $stmtEstadoAnterior->fetchColumn();

                if (!$estadoAnterior) {
                    throw new Exception('Cita no encontrada');
                }

                // Actualizar estado de la cita
                if ($nuevoEstado === 'cancelada') {
                    $sql = "UPDATE citas SET estado_cita = :estado, fecha_cancelacion = NOW(), motivo_cancelacion = :motivo WHERE id_cita = :cita_id";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        'estado' => $nuevoEstado,
                        'motivo' => $motivo,
                        'cita_id' => $citaId
                    ]);
                } else {
                    $sql = "UPDATE citas SET estado_cita = :estado WHERE id_cita = :cita_id";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        'estado' => $nuevoEstado,
                        'cita_id' => $citaId
                    ]);
                }

                // Enviar notificaciones por correo
                try {
                    require_once 'includes/notificaciones-citas.php';
                    $notificaciones = new NotificacionesCitas();
                    $notificaciones->notificarCambioEstado($citaId, $estadoAnterior, $nuevoEstado, $motivo);
                } catch (Exception $e) {
                    error_log("Error enviando notificaciones de cambio de estado: " . $e->getMessage());
                    // No interrumpir el proceso aunque falle el envío de emails
                }

                $success = "Estado de la cita actualizado exitosamente";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Construir consulta base
$whereConditions = [];
$params = [];

// Filtrar por fecha
if ($fecha) {
    $whereConditions[] = "c.fecha_cita = :fecha";
    $params['fecha'] = $fecha;
}

// Solo mostrar citas de la sucursal del recepcionista (si no es admin)
if ($_SESSION['role_id'] == 2) {
    $whereConditions[] = "c.id_sucursal = (SELECT id_sucursal FROM usuarios WHERE id_usuario = :user_id)";
    $params['user_id'] = $_SESSION['user_id'];
}

// Filtro por estado
if ($estado !== 'todos') {
    $whereConditions[] = "c.estado_cita = :estado";
    $params['estado'] = $estado;
}

// Filtro por búsqueda
if ($buscar) {
    $whereConditions[] = "(p.nombre LIKE :buscar OR p.apellido LIKE :buscar OR p.cedula LIKE :buscar OR m.nombre LIKE :buscar OR m.apellido LIKE :buscar)";
    $params['buscar'] = "%{$buscar}%";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$sql = "SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.estado_cita, c.tipo_cita, c.motivo_consulta,
               CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
               p.cedula as paciente_cedula,
               p.telefono as paciente_telefono,
               p.email as paciente_email,
               CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
               e.nombre_especialidad,
               s.nombre_sucursal,
               c.motivo_cancelacion,
               c.fecha_cancelacion
        FROM citas c
        JOIN usuarios p ON c.id_paciente = p.id_usuario
        JOIN usuarios m ON c.id_medico = m.id_usuario
        JOIN especialidades e ON c.id_especialidad = e.id_especialidad
        JOIN sucursales s ON c.id_sucursal = s.id_sucursal
        {$whereClause}
        ORDER BY c.fecha_cita DESC, c.hora_cita DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        <i class="fas fa-calendar-check"></i> Gestionar Citas
                    </h2>
                    <p class="text-muted mb-0">Administre y controle el estado de las citas médicas</p>
                </div>
                <div>
                    <a href="index.php?action=citas/agendar" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Nueva Cita
                    </a>
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

            <!-- Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="index.php">
                        <input type="hidden" name="action" value="citas/gestionar">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" name="fecha" value="<?php echo htmlspecialchars($fecha); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="estado">
                                    <option value="todos" <?php echo $estado === 'todos' ? 'selected' : ''; ?>>Todos</option>
                                    <option value="agendada" <?php echo $estado === 'agendada' ? 'selected' : ''; ?>>Agendada</option>
                                    <option value="confirmada" <?php echo $estado === 'confirmada' ? 'selected' : ''; ?>>Confirmada</option>
                                    <option value="en_curso" <?php echo $estado === 'en_curso' ? 'selected' : ''; ?>>En Curso</option>
                                    <option value="completada" <?php echo $estado === 'completada' ? 'selected' : ''; ?>>Completada</option>
                                    <option value="cancelada" <?php echo $estado === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                    <option value="no_asistio" <?php echo $estado === 'no_asistio' ? 'selected' : ''; ?>>No Asistió</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Buscar</label>
                                <input type="text" class="form-control" name="buscar" 
                                       placeholder="Paciente, médico, cédula..." 
                                       value="<?php echo htmlspecialchars($buscar); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i> Filtrar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de citas -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> 
                            Citas encontradas: <?php echo count($citas); ?>
                        </h5>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($citas)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No se encontraron citas</h5>
                            <p class="text-muted">Intente ajustar los filtros de búsqueda</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha y Hora</th>
                                        <th>Paciente</th>
                                        <th>Médico</th>
                                        <th>Especialidad</th>
                                        <th>Tipo</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($citas as $cita): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold">
                                                    <?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('H:i', strtotime($cita['hora_cita'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($cita['paciente_nombre']); ?></div>
                                                <small class="text-muted">
                                                    CI: <?php echo $cita['paciente_cedula'] ?: 'No registrada'; ?>
                                                    <?php if ($cita['paciente_telefono']): ?>
                                                        <br>Tel: <?php echo $cita['paciente_telefono']; ?>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($cita['medico_nombre']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($cita['nombre_sucursal']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($cita['nombre_especialidad']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $cita['tipo_cita'] === 'virtual' ? 'info' : 'secondary'; ?>">
                                                    <i class="fas fa-<?php echo $cita['tipo_cita'] === 'virtual' ? 'video' : 'hospital'; ?>"></i>
                                                    <?php echo ucfirst($cita['tipo_cita']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $estadoColor = [
                                                    'agendada' => 'primary',
                                                    'confirmada' => 'success',
                                                    'en_curso' => 'warning',
                                                    'completada' => 'success',
                                                    'cancelada' => 'danger',
                                                    'no_asistio' => 'secondary'
                                                ];
                                                $color = $estadoColor[$cita['estado_cita']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?> fs-6">
                                                    <?php echo ucwords(str_replace('_', ' ', $cita['estado_cita'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-info" 
                                                            onclick="verDetalleCita(<?php echo $cita['id_cita']; ?>)"
                                                            title="Ver detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-warning" 
                                                            onclick="cambiarEstado(<?php echo $cita['id_cita']; ?>, '<?php echo $cita['estado_cita']; ?>')"
                                                            title="Cambiar estado">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
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

<!-- Modal para cambiar estado -->
<div class="modal fade" id="modalCambiarEstado" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Cambiar Estado de Cita
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="cambiar_estado">
                    <input type="hidden" name="cita_id" id="modalCitaId">

                    <div class="mb-3">
                        <label class="form-label">Estado Actual</label>
                        <input type="text" class="form-control" id="estadoActual" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nuevo Estado <span class="text-danger">*</span></label>
                        <select class="form-select" name="nuevo_estado" id="nuevoEstado" required>
                            <option value="">Seleccione...</option>
                            <option value="agendada">Agendada</option>
                            <option value="confirmada">Confirmada</option>
                            <option value="en_curso">En Curso</option>
                            <option value="completada">Completada</option>
                            <option value="cancelada">Cancelada</option>
                            <option value="no_asistio">No Asistió</option>
                        </select>
                    </div>

                    <div class="mb-3" id="motivoContainer" style="display: none;">
                        <label class="form-label">Motivo de Cancelación</label>
                        <textarea class="form-control" name="motivo" id="motivoCancelacion" 
                                  rows="3" placeholder="Especifique el motivo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Actualizar Estado
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para ver detalles -->
<div class="modal fade" id="modalDetalleCita" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-alt"></i> Detalles de la Cita
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalDetalleContent">
                <!-- Contenido cargado dinámicamente -->
            </div>
        </div>
    </div>
</div>

<script>
    function cambiarEstado(citaId, estadoActual) {
        document.getElementById('modalCitaId').value = citaId;
        document.getElementById('estadoActual').value = ucwords(estadoActual.replace('_', ' '));
        document.getElementById('nuevoEstado').value = '';
        document.getElementById('motivoContainer').style.display = 'none';

        const modal = new bootstrap.Modal(document.getElementById('modalCambiarEstado'));
        modal.show();
    }

    document.getElementById('nuevoEstado').addEventListener('change', function () {
        const motivoContainer = document.getElementById('motivoContainer');
        const motivoInput = document.getElementById('motivoCancelacion');

        if (this.value === 'cancelada') {
            motivoContainer.style.display = 'block';
            motivoInput.required = true;
        } else {
            motivoContainer.style.display = 'none';
            motivoInput.required = false;
            motivoInput.value = '';
        }
    });

    function verDetalleCita(citaId) {
        // Buscar los datos de la cita en la tabla
        const filas = document.querySelectorAll('tbody tr');
        let citaData = null;

        filas.forEach(fila => {
            const acciones = fila.querySelector('td:last-child button[onclick*="' + citaId + '"]');
            if (acciones) {
                const celdas = fila.querySelectorAll('td');
                citaData = {
                    fecha: celdas[0].querySelector('div').textContent,
                    hora: celdas[0].querySelector('small').textContent,
                    paciente: celdas[1].querySelector('div').textContent,
                    contacto: celdas[1].querySelector('small').innerHTML,
                    medico: celdas[2].querySelector('div').textContent,
                    sucursal: celdas[2].querySelector('small').textContent,
                    especialidad: celdas[3].textContent,
                    tipo: celdas[4].textContent.trim(),
                    estado: celdas[5].textContent.trim()
                };
            }
        });

        if (citaData) {
            document.getElementById('modalDetalleContent').innerHTML = `
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-calendar"></i> Información de la Cita</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Fecha:</strong></td><td>${citaData.fecha}</td></tr>
                        <tr><td><strong>Hora:</strong></td><td>${citaData.hora}</td></tr>
                        <tr><td><strong>Tipo:</strong></td><td>${citaData.tipo}</td></tr>
                        <tr><td><strong>Estado:</strong></td><td>${citaData.estado}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-user"></i> Información del Paciente</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Nombre:</strong></td><td>${citaData.paciente}</td></tr>
                        <tr><td><strong>Contacto:</strong></td><td>${citaData.contacto}</td></tr>
                    </table>
                    
                    <h6><i class="fas fa-user-md"></i> Información del Médico</h6>
                    <table class="table table-sm">
                        <tr><td><strong>Médico:</strong></td><td>${citaData.medico}</td></tr>
                        <tr><td><strong>Especialidad:</strong></td><td>${citaData.especialidad}</td></tr>
                        <tr><td><strong>Sucursal:</strong></td><td>${citaData.sucursal}</td></tr>
                    </table>
                </div>
            </div>
        `;

            const modal = new bootstrap.Modal(document.getElementById('modalDetalleCita'));
            modal.show();
        }
    }

    function ucwords(str) {
        return str.toLowerCase().replace(/\b[a-z]/g, function (letter) {
            return letter.toUpperCase();
        });
    }
</script>