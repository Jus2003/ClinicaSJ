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

// Filtros básicos
$fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-7 days'));
$fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d', strtotime('+200 days'));
$estado_filter = $_GET['estado'] ?? 'todas';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $citaId = $_POST['cita_id'] ?? 0;

    try {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();

        switch ($action) {
            case 'confirmar_cita':
                // Obtener estado anterior
                $sqlEstado = "SELECT estado_cita FROM citas WHERE id_cita = :cita_id";
                $stmtEstado = $db->prepare($sqlEstado);
                $stmtEstado->execute(['cita_id' => $citaId]);
                $estadoAnterior = $stmtEstado->fetchColumn();

                $citaModel->updateEstadoCita($citaId, 'confirmada');

                // Enviar notificaciones
                try {
                    require_once 'includes/notificaciones-citas.php';
                    $notificador = new NotificacionesCitas($db);
                    $notificador->notificarCambioEstado($citaId, $estadoAnterior, 'confirmada');
                } catch (Exception $e) {
                    error_log("Error enviando notificaciones: " . $e->getMessage());
                }

                $success = "Cita confirmada exitosamente";
                break;

            case 'cancelar_cita':
                $motivo = $_POST['motivo_cancelacion'] ?? 'Cancelada por el usuario';

                // Obtener estado anterior
                $sqlEstado = "SELECT estado_cita FROM citas WHERE id_cita = :cita_id";
                $stmtEstado = $db->prepare($sqlEstado);
                $stmtEstado->execute(['cita_id' => $citaId]);
                $estadoAnterior = $stmtEstado->fetchColumn();

                $citaModel->updateEstadoCita($citaId, 'cancelada', $motivo);

                // Enviar notificaciones
                try {
                    require_once 'includes/notificaciones-citas.php';
                    $notificador = new NotificacionesCitas($db);
                    $notificador->notificarCambioEstado($citaId, $estadoAnterior, 'cancelada', $motivo);
                } catch (Exception $e) {
                    error_log("Error enviando notificaciones: " . $e->getMessage());
                }

                $success = "Cita cancelada exitosamente";
                break;

            case 'completar_cita':
                $observaciones = $_POST['observaciones'] ?? '';

                // Obtener estado anterior
                $sqlEstado = "SELECT estado_cita FROM citas WHERE id_cita = :cita_id";
                $stmtEstado = $db->prepare($sqlEstado);
                $stmtEstado->execute(['cita_id' => $citaId]);
                $estadoAnterior = $stmtEstado->fetchColumn();

                $citaModel->updateEstadoCita($citaId, 'completada', $observaciones);

                // Enviar notificaciones
                try {
                    require_once 'includes/notificaciones-citas.php';
                    $notificador = new NotificacionesCitas($db);
                    $notificador->notificarCambioEstado($citaId, $estadoAnterior, 'completada');
                } catch (Exception $e) {
                    error_log("Error enviando notificaciones: " . $e->getMessage());
                }

                $success = "Cita completada exitosamente";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener citas según rol - CONSULTA DIRECTA SIN USAR MÉTODOS COMPLEJOS
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$citas = [];
$tituloAgenda = "Agenda";
$vistaRol = "general";

// Construir consulta base según rol
$whereConditions = [];
$params = [];

// Filtros de fecha
$whereConditions[] = "c.fecha_cita BETWEEN :fecha_inicio AND :fecha_fin";
$params['fecha_inicio'] = $fechaInicio;
$params['fecha_fin'] = $fechaFin;

// Filtro de estado
if ($estado_filter !== 'todas') {
    $whereConditions[] = "c.estado_cita = :estado";
    $params['estado'] = $estado_filter;
}

switch ($_SESSION['role_id']) {
    case 3: // Médico
        $whereConditions[] = "c.id_medico = :user_id";
        $params['user_id'] = $_SESSION['user_id'];
        $tituloAgenda = "Mi Agenda";
        $vistaRol = "medico";
        break;

    case 4: // Paciente
        $whereConditions[] = "c.id_paciente = :user_id";
        $params['user_id'] = $_SESSION['user_id'];
        $tituloAgenda = "Mis Citas";
        $vistaRol = "paciente";
        break;

    default: // Admin/Recepcionista
        $tituloAgenda = "Agenda General";
        $vistaRol = "admin";
        break;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Consulta SQL simplificada para evitar duplicados
$sql = "SELECT DISTINCT 
            c.id_cita,
            c.fecha_cita,
            c.hora_cita,
            c.estado_cita,
            c.tipo_cita,
            c.motivo_consulta,
            c.observaciones,
            CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
            p.cedula as paciente_cedula,
            p.telefono as paciente_telefono,
            CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
            e.nombre_especialidad,
            s.nombre_sucursal
        FROM citas c
        JOIN usuarios p ON c.id_paciente = p.id_usuario
        JOIN usuarios m ON c.id_medico = m.id_usuario
        JOIN especialidades e ON c.id_especialidad = e.id_especialidad
        JOIN sucursales s ON c.id_sucursal = s.id_sucursal
        {$whereClause}
        ORDER BY c.fecha_cita ASC, c.hora_cita ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agregar información de triaje y pagos SOLO si hay citas
if (!empty($citas) && ($vistaRol === 'paciente' || $vistaRol === 'medico')) {
    foreach ($citas as &$cita) {
        // Verificar triaje completado
        $sqlTriaje = "SELECT COUNT(*) as total FROM triaje_respuestas 
                      WHERE id_cita = :cita_id AND tipo_triaje = 'digital'";
        $stmtTriaje = $db->prepare($sqlTriaje);
        $stmtTriaje->execute(['cita_id' => $cita['id_cita']]);
        $triaje = $stmtTriaje->fetch(PDO::FETCH_ASSOC);
        $cita['triaje_completado'] = $triaje['total'] > 0;

        // Verificar estado de pago
        $sqlPago = "SELECT estado_pago FROM pagos WHERE id_cita = :cita_id LIMIT 1";
        $stmtPago = $db->prepare($sqlPago);
        $stmtPago->execute(['cita_id' => $cita['id_cita']]);
        $pago = $stmtPago->fetch(PDO::FETCH_ASSOC);
        $cita['estado_pago'] = $pago ? $pago['estado_pago'] : 'pendiente';
    }
}

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container mt-3">
    <h2><i class="fas fa-calendar"></i> <?= $tituloAgenda ?></h2>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= $success ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtros simplificados -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row">
                <input type="hidden" name="action" value="citas/agenda">

                <div class="col-md-4 mb-2">
                    <label>Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="<?= $fechaInicio ?>">
                </div>

                <div class="col-md-4 mb-2">
                    <label>Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" value="<?= $fechaFin ?>">
                </div>

                <div class="col-md-4 mb-2">
                    <label>Estado</label>
                    <select class="form-select" name="estado">
                        <option value="todas" <?= ($estado_filter === 'todas') ? 'selected' : '' ?>>Todas</option>
                        <option value="agendada" <?= ($estado_filter === 'agendada') ? 'selected' : '' ?>>Agendadas</option>
                        <option value="confirmada" <?= ($estado_filter === 'confirmada') ? 'selected' : '' ?>>Confirmadas</option>
                        <option value="completada" <?= ($estado_filter === 'completada') ? 'selected' : '' ?>>Completadas</option>
                        <option value="cancelada" <?= ($estado_filter === 'cancelada') ? 'selected' : '' ?>>Canceladas</option>
                    </select>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de citas -->
    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="fas fa-list"></i> 
                Citas encontradas: <?= count($citas) ?>
            </h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($citas)): ?>
                <div class="text-center p-4">
                    <i class="fas fa-calendar-times fa-2x text-muted mb-3"></i>
                    <h5>No hay citas programadas</h5>
                    <p class="text-muted">No se encontraron citas en el rango de fechas seleccionado.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha/Hora</th>
                                <?php if ($vistaRol === 'medico'): ?>
                                    <th>Paciente</th>
                                <?php elseif ($vistaRol === 'paciente'): ?>
                                    <th>Médico</th>
                                    <th>Especialidad</th>
                                <?php else: ?>
                                    <th>Paciente</th>
                                    <th>Médico</th>
                                <?php endif; ?>
                                <th>Estado</th>
                                <?php if ($vistaRol === 'paciente' || $vistaRol === 'medico'): ?>
                                    <th>Triaje</th>
                                    <th>Pago</th>
                                <?php endif; ?>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($citas as $cita): ?>
                                <tr>
                                    <td>
                                        <strong><?= date('d/m/Y', strtotime($cita['fecha_cita'])) ?></strong>
                                        <br>
                                        <small class="text-muted"><?= date('H:i', strtotime($cita['hora_cita'])) ?></small>
                                    </td>

                                    <?php if ($vistaRol === 'medico'): ?>
                                        <td>
                                            <strong><?= htmlspecialchars($cita['paciente_nombre']) ?></strong>
                                            <?php if (!empty($cita['paciente_cedula'])): ?>
                                                <br><small class="text-muted">CI: <?= htmlspecialchars($cita['paciente_cedula']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                    <?php elseif ($vistaRol === 'paciente'): ?>
                                        <td>
                                            <strong>Dr. <?= htmlspecialchars($cita['medico_nombre']) ?></strong>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($cita['nombre_especialidad']) ?>
                                        </td>
                                    <?php else: ?>
                                        <td><?= htmlspecialchars($cita['paciente_nombre']) ?></td>
                                        <td>Dr. <?= htmlspecialchars($cita['medico_nombre']) ?></td>
                                    <?php endif; ?>

                                    <td>
                                        <?php
                                        $badgeColor = match ($cita['estado_cita']) {
                                            'agendada' => 'bg-warning',
                                            'confirmada' => 'bg-success',
                                            'completada' => 'bg-primary',
                                            'cancelada' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeColor ?>">
                                            <?= ucfirst($cita['estado_cita']) ?>
                                        </span>
                                    </td>

                                    <?php if ($vistaRol === 'paciente' || $vistaRol === 'medico'): ?>
                                        <!-- Columna Triaje -->
                                        <td>
                                            <?php if ($vistaRol === 'paciente'): ?>
                                                <?php if ($cita['estado_cita'] === 'confirmada'): ?>
                                                    <?php if ($cita['triaje_completado']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check"></i> Hecho
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="fas fa-clock"></i> Pendiente
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            <?php elseif ($vistaRol === 'medico'): ?>
                                                <?php if ($cita['triaje_completado']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check"></i> Disponible
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark">
                                                        <i class="fas fa-minus"></i> Sin triaje
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Columna Pago -->
                                        <td>
                                            <?php
                                            $pagoClass = match ($cita['estado_pago']) {
                                                'pagado' => 'bg-success',
                                                'pendiente' => 'bg-danger',
                                                default => 'bg-danger'
                                            };
                                            ?>
                                            <span class="badge <?= $pagoClass ?>">
                                                <i class="fas fa-<?= $cita['estado_pago'] === 'pagado' ? 'check' : 'dollar-sign' ?>"></i>
                                                <?= ucfirst($cita['estado_pago']) ?>
                                            </span>
                                        </td>
                                    <?php endif; ?>

                                    <!-- Columna Acciones -->
                                    <td>
                                        <div class="btn-group btn-group-sm">

                                            <?php if ($vistaRol === 'paciente'): ?>
                                                <!-- Botones para Pacientes -->

                                                <!-- Triaje Digital -->
                                                <?php if ($cita['estado_cita'] === 'confirmada' && !$cita['triaje_completado']): ?>
                                                    <a href="index.php?action=consultas/triaje/completar&cita_id=<?= $cita['id_cita'] ?>" 
                                                       class="btn btn-success btn-sm" title="Completar Triaje">
                                                        <i class="fas fa-clipboard-list"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <!-- Pago -->
                                                <?php if ($cita['estado_pago'] === 'pendiente' && in_array($cita['estado_cita'], ['confirmada', 'completada'])): ?>
                                                    <a href="index.php?action=citas/pagar&cita_id=<?= $cita['id_cita'] ?>" 
                                                       class="btn btn-warning btn-sm" title="Pagar">
                                                        <i class="fas fa-credit-card"></i>
                                                    </a>
                                                <?php endif; ?>

                                            <?php elseif ($vistaRol === 'medico'): ?>
                                                <!-- Botones para Médicos -->

                                                <!-- Ver Triaje -->
                                                <?php if ($cita['triaje_completado']): ?>
                                                    <a href="index.php?action=consultas/triaje/ver&cita_id=<?= $cita['id_cita'] ?>" 
                                                       class="btn btn-info btn-sm" title="Ver Triaje">
                                                        <i class="fas fa-clipboard-list"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <!-- Atender Paciente -->
                                                <?php if ($cita['estado_cita'] === 'confirmada'): ?>
                                                    <a href="index.php?action=consultas/atender&cita_id=<?= $cita['id_cita'] ?>" 
                                                       class="btn btn-success btn-sm" title="Atender">
                                                        <i class="fas fa-user-md"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <!-- Completar Cita -->
                                                <?php if ($cita['estado_cita'] === 'confirmada'): ?>
                                                    <button class="btn btn-primary btn-sm" 
                                                            onclick="completarCita(<?= $cita['id_cita'] ?>)" title="Completar">
                                                        <i class="fas fa-check-double"></i>
                                                    </button>
                                                <?php endif; ?>

                                            <?php endif; ?>

                                            <!-- Botón Ver Detalles (para todos) -->
                                            <button class="btn btn-outline-info btn-sm" 
                                                    onclick="verDetallesCita(<?= $cita['id_cita'] ?>, '<?= addslashes(json_encode($cita)) ?>')" 
                                                    title="Ver Detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <!-- Confirmar cita (solo para agendadas y no pacientes) -->
                                            <?php if ($cita['estado_cita'] === 'agendada' && $vistaRol !== 'paciente'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="confirmar_cita">
                                                    <input type="hidden" name="cita_id" value="<?= $cita['id_cita'] ?>">
                                                    <button type="submit" class="btn btn-outline-success btn-sm" title="Confirmar">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <!-- Cancelar cita -->
                                            <?php if (in_array($cita['estado_cita'], ['agendada', 'confirmada'])): ?>
                                                <button class="btn btn-outline-danger btn-sm" 
                                                        onclick="cancelarCita(<?= $cita['id_cita'] ?>)" title="Cancelar">
                                                    <i class="fas fa-times"></i>
                                                </button>
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

<!-- Modal Detalles -->
<div class="modal fade" id="modalDetalles">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-alt"></i> Detalles de Cita
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detallesContent">
                <!-- Contenido dinámico -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cancelar -->
<div class="modal fade" id="modalCancelar">
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
                <input type="hidden" name="cita_id" id="citaCancelarId">

                <div class="modal-body">
                    <p>¿Está seguro que desea cancelar esta cita?</p>
                    <div class="mb-3">
                        <label class="form-label">Motivo de cancelación</label>
                        <textarea name="motivo_cancelacion" class="form-control" rows="3" required 
                                  placeholder="Ingrese el motivo de la cancelación..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Cancelación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Completar Cita -->
<div class="modal fade" id="modalCompletar">
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
                <input type="hidden" name="cita_id" id="citaCompletarId">

                <div class="modal-body">
                    <p>Marcar la cita como completada.</p>
                    <div class="mb-3">
                        <label class="form-label">Observaciones finales (opcional)</label>
                        <textarea name="observaciones" class="form-control" rows="3" 
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

<script>
// Función para ver detalles
    function verDetallesCita(citaId, citaData) {
        const cita = JSON.parse(citaData);

        const content = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary mb-3">
                    <i class="fas fa-calendar-alt"></i> Información de la Cita
                </h6>
                <table class="table table-sm">
                    <tr><td><strong>ID:</strong></td><td>${cita.id_cita}</td></tr>
                    <tr><td><strong>Fecha:</strong></td><td>${new Date(cita.fecha_cita).toLocaleDateString('es-ES')}</td></tr>
                    <tr><td><strong>Hora:</strong></td><td>${cita.hora_cita}</td></tr>
                    <tr><td><strong>Estado:</strong></td><td><span class="badge bg-info">${cita.estado_cita}</span></td></tr>
                    ${cita.tipo_cita ? `<tr><td><strong>Tipo:</strong></td><td>${cita.tipo_cita}</td></tr>` : ''}
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary mb-3">
                    <i class="fas fa-users"></i> Participantes
                </h6>
                <table class="table table-sm">
                    ${cita.paciente_nombre ? `<tr><td><strong>Paciente:</strong></td><td>${cita.paciente_nombre}</td></tr>` : ''}
                    ${cita.medico_nombre ? `<tr><td><strong>Médico:</strong></td><td>Dr. ${cita.medico_nombre}</td></tr>` : ''}
                    ${cita.nombre_especialidad ? `<tr><td><strong>Especialidad:</strong></td><td>${cita.nombre_especialidad}</td></tr>` : ''}
                    ${cita.nombre_sucursal ? `<tr><td><strong>Sucursal:</strong></td><td>${cita.nombre_sucursal}</td></tr>` : ''}
                </table>
            </div>
        </div>
        
        ${cita.triaje_completado !== undefined ? `
        <div class="row mt-3">
            <div class="col-md-6">
                <h6 class="text-primary mb-3">
                    <i class="fas fa-clipboard-list"></i> Triaje Digital
                </h6>
                <div class="alert ${cita.triaje_completado ? 'alert-success' : 'alert-warning'} mb-0">
                    <i class="fas fa-${cita.triaje_completado ? 'check-circle' : 'clock'}"></i>
                    ${cita.triaje_completado ? 'Completado' : 'Pendiente'}
                </div>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary mb-3">
                    <i class="fas fa-credit-card"></i> Estado del Pago
                </h6>
                <div class="alert ${cita.estado_pago === 'pagado' ? 'alert-success' : 'alert-danger'} mb-0">
                    <i class="fas fa-${cita.estado_pago === 'pagado' ? 'check-circle' : 'dollar-sign'}"></i>
                    ${cita.estado_pago.charAt(0).toUpperCase() + cita.estado_pago.slice(1)}
                </div>
            </div>
        </div>` : ''}
        
        ${cita.motivo_consulta ? `
        <div class="mt-3">
            <h6 class="text-primary mb-3">
                <i class="fas fa-notes-medical"></i> Motivo de Consulta
            </h6>
            <div class="alert alert-info mb-0">${cita.motivo_consulta}</div>
        </div>` : ''}
    `;

        document.getElementById('detallesContent').innerHTML = content;
        new bootstrap.Modal(document.getElementById('modalDetalles')).show();
    }

// Función para cancelar cita
    function cancelarCita(citaId) {
        document.getElementById('citaCancelarId').value = citaId;
        new bootstrap.Modal(document.getElementById('modalCancelar')).show();
    }

// Función para completar cita
    function completarCita(citaId) {
        document.getElementById('citaCompletarId').value = citaId;
        new bootstrap.Modal(document.getElementById('modalCompletar')).show();
    }

// Prevenir doble envío de formularios
    document.addEventListener('DOMContentLoaded', function () {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function () {
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    const originalText = submitButton.innerHTML;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';

                    // Rehabilitar después de 3 segundos por si hay error
                    setTimeout(() => {
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalText;
                    }, 3000);
                }
            });
        });
    });
</script>

<style>
    .table td {
        vertical-align: middle;
    }

    .badge {
        font-size: 0.75rem;
        padding: 0.35rem 0.65rem;
    }

    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        margin-right: 0.1rem;
    }

    /* Mejorar hover de botones */
    .btn:hover {
        transform: translateY(-1px);
        transition: all 0.2s ease;
    }

    /* Badges con iconos */
    .badge i {
        margin-right: 0.25rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .btn-group-sm .btn {
            padding: 0.2rem 0.4rem;
            font-size: 0.7rem;
        }

        .badge {
            font-size: 0.65rem;
        }
    }

    /* Animaciones suaves */
    .table-hover tbody tr:hover {
        background-color: rgba(0,123,255,0.075);
        transition: background-color 0.2s ease;
    }

    /* Mejorar contraste de badges */
    .badge.bg-light {
        color: #495057 !important;
        border: 1px solid #dee2e6;
    }
</style>