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

// Procesar acciones (mantener igual)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $citaId = $_POST['cita_id'] ?? 0;

    try {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();

        switch ($action) {
            case 'confirmar_cita':
                $sqlEstado = "SELECT estado_cita FROM citas WHERE id_cita = :cita_id";
                $stmtEstado = $db->prepare($sqlEstado);
                $stmtEstado->execute(['cita_id' => $citaId]);
                $estadoAnterior = $stmtEstado->fetchColumn();

                $citaModel->updateEstadoCita($citaId, 'confirmada');

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
                $sqlEstado = "SELECT estado_cita FROM citas WHERE id_cita = :cita_id";
                $stmtEstado = $db->prepare($sqlEstado);
                $stmtEstado->execute(['cita_id' => $citaId]);
                $estadoAnterior = $stmtEstado->fetchColumn();

                $citaModel->updateEstadoCita($citaId, 'cancelada', $motivo);

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
                $sqlEstado = "SELECT estado_cita FROM citas WHERE id_cita = :cita_id";
                $stmtEstado = $db->prepare($sqlEstado);
                $stmtEstado->execute(['cita_id' => $citaId]);
                $estadoAnterior = $stmtEstado->fetchColumn();

                $citaModel->updateEstadoCita($citaId, 'completada', $observaciones);

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

// NUEVA CONSULTA SIMPLIFICADA PASO A PASO
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$citas = [];
$tituloAgenda = "Agenda";
$vistaRol = "general";

try {
    // PASO 1: Construir filtros básicos
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

    // PASO 2: Agregar filtros por rol
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

    // PASO 3: CONSULTA PRINCIPAL ULTRA SIMPLIFICADA
    $sql = "SELECT 
                c.id_cita,
                c.fecha_cita,
                c.hora_cita,
                c.estado_cita,
                c.tipo_cita,
                c.motivo_consulta,
                c.observaciones,
                c.id_paciente,
                c.id_medico,
                c.id_especialidad,
                c.id_sucursal
            FROM citas c
            {$whereClause}
            ORDER BY c.fecha_cita ASC, c.hora_cita ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $citasBase = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener datos de triaje y pago para TODAS las vistas (no solo pacientes y médicos)
    foreach ($citasBase as $cita) {
        $citaCompleta = $cita;

        // Obtener datos del paciente
        $sqlPaciente = "SELECT CONCAT(nombre, ' ', apellido) as nombre_completo, cedula, telefono 
                    FROM usuarios WHERE id_usuario = :id_paciente";
        $stmtPaciente = $db->prepare($sqlPaciente);
        $stmtPaciente->execute(['id_paciente' => $cita['id_paciente']]);
        $paciente = $stmtPaciente->fetch(PDO::FETCH_ASSOC);

        // Obtener datos del médico
        $sqlMedico = "SELECT CONCAT(nombre, ' ', apellido) as nombre_completo 
                  FROM usuarios WHERE id_usuario = :id_medico";
        $stmtMedico = $db->prepare($sqlMedico);
        $stmtMedico->execute(['id_medico' => $cita['id_medico']]);
        $medico = $stmtMedico->fetch(PDO::FETCH_ASSOC);

        // Obtener especialidad
        $sqlEspecialidad = "SELECT nombre_especialidad FROM especialidades WHERE id_especialidad = :id_especialidad";
        $stmtEspecialidad = $db->prepare($sqlEspecialidad);
        $stmtEspecialidad->execute(['id_especialidad' => $cita['id_especialidad']]);
        $especialidad = $stmtEspecialidad->fetch(PDO::FETCH_ASSOC);

        // Obtener sucursal
        $sqlSucursal = "SELECT nombre_sucursal FROM sucursales WHERE id_sucursal = :id_sucursal";
        $stmtSucursal = $db->prepare($sqlSucursal);
        $stmtSucursal->execute(['id_sucursal' => $cita['id_sucursal']]);
        $sucursal = $stmtSucursal->fetch(PDO::FETCH_ASSOC);

        // Combinar datos
        $citaCompleta['paciente_nombre'] = $paciente ? $paciente['nombre_completo'] : 'N/A';
        $citaCompleta['paciente_cedula'] = $paciente ? $paciente['cedula'] : '';
        $citaCompleta['paciente_telefono'] = $paciente ? $paciente['telefono'] : '';
        $citaCompleta['medico_nombre'] = $medico ? $medico['nombre_completo'] : 'N/A';
        $citaCompleta['nombre_especialidad'] = $especialidad ? $especialidad['nombre_especialidad'] : 'N/A';
        $citaCompleta['nombre_sucursal'] = $sucursal ? $sucursal['nombre_sucursal'] : 'N/A';

        // OBTENER DATOS DE TRIAJE Y PAGO PARA TODOS (cambiado)
        // Triaje
        $sqlTriaje = "SELECT COUNT(*) as total FROM triaje_respuestas 
                  WHERE id_cita = :cita_id AND tipo_triaje = 'digital'";
        $stmtTriaje = $db->prepare($sqlTriaje);
        $stmtTriaje->execute(['cita_id' => $cita['id_cita']]);
        $triaje = $stmtTriaje->fetch(PDO::FETCH_ASSOC);
        $citaCompleta['triaje_completado'] = ($triaje && $triaje['total'] > 0);

        // Pago
        $sqlPago = "SELECT estado_pago FROM pagos WHERE id_cita = :cita_id ORDER BY id_pago DESC LIMIT 1";
        $stmtPago = $db->prepare($sqlPago);
        $stmtPago->execute(['cita_id' => $cita['id_cita']]);
        $pago = $stmtPago->fetch(PDO::FETCH_ASSOC);
        $citaCompleta['estado_pago'] = $pago ? $pago['estado_pago'] : 'pendiente';

        $citas[] = $citaCompleta;
    }
} catch (Exception $e) {
    $error = "Error al obtener las citas: " . $e->getMessage();
    $citas = [];
}

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container mt-3">
    <h2><i class="fas fa-calendar"></i> <?= $tituloAgenda ?></h2>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row">
                <input type="hidden" name="action" value="citas/agenda">

                <div class="col-md-4 mb-2">
                    <label>Fecha Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>">
                </div>

                <div class="col-md-4 mb-2">
                    <label>Fecha Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" value="<?= htmlspecialchars($fechaFin) ?>">
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
                                <th>ID</th>
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
                            <?php foreach ($citas as $index => $cita): ?>
                                <tr>
                                    <td>
                                        <small class="text-muted">#<?= $cita['id_cita'] ?></small>
                                    </td>
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
                                            <?php if (isset($cita['triaje_completado'])): ?>
                                                <?php if ($cita['triaje_completado']): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check"></i> Completado
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-clock"></i> Pendiente
                                                    </span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Columna Pago -->
                                        <td>
                                            <?php if (isset($cita['estado_pago'])): ?>
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
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>

                                    <!-- Columna Acciones -->
                                    <td>
                                        <div class="btn-group btn-group-sm">

                                            <?php if ($vistaRol === 'paciente'): ?>
                                                <!-- Botones para Pacientes -->

                                                <!-- Triaje Digital -->
                                                <?php if ($cita['estado_cita'] === 'confirmada' && isset($cita['triaje_completado']) && !$cita['triaje_completado']): ?>
                                                    <a href="index.php?action=consultas/triaje/completar&cita_id=<?= $cita['id_cita'] ?>" 
                                                       class="btn btn-success btn-sm" title="Completar Triaje">
                                                        <i class="fas fa-clipboard-list"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <!-- Pago -->
                                                <?php if (isset($cita['estado_pago']) && $cita['estado_pago'] === 'pendiente' && in_array($cita['estado_cita'], ['confirmada', 'completada'])): ?>
                                                    <a href="index.php?action=citas/pagar&cita_id=<?= $cita['id_cita'] ?>" 
                                                       class="btn btn-warning btn-sm" title="Pagar">
                                                        <i class="fas fa-credit-card"></i>
                                                    </a>
                                                <?php endif; ?>

                                            <?php elseif ($vistaRol === 'medico'): ?>
                                                <!-- Botones para Médicos -->

                                                <!-- Ver Triaje -->
                                                <!-- Ver Triaje con debug -->
                                                <?php if (isset($cita['triaje_completado']) && $cita['triaje_completado']): ?>
                                                    <?php
                                                    // Debug temporal
                                                    echo "<!-- DEBUG: ID de cita = " . ($cita['id_cita'] ?? 'NO DEFINIDO') . " -->";
                                                    ?>
                                                    <a href="index.php?action=consultas/triaje/ver&cita_id=<?= $cita['id_cita'] ?>" 
                                                       class="btn btn-info btn-sm" 
                                                       title="Ver Triaje (ID: <?= $cita['id_cita'] ?>)">
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
                                                    onclick="verDetallesCita(<?= $cita['id_cita'] ?>, '<?= htmlspecialchars(json_encode($cita), ENT_QUOTES) ?>')" 
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

                                            <!-- Descargar PDF si existe -->
                                            <?php if (isset($cita['estado_pago'])): ?>
                                                <?php
                                                // Verificar si existe PDF para esta cita
                                                $sqlPdf = "SELECT id_pago, nombre_archivo, tipo_pdf FROM pagos WHERE id_cita = ? AND archivo_pdf IS NOT NULL";
                                                $stmtPdf = $db->prepare($sqlPdf);
                                                $stmtPdf->execute([$cita['id_cita']]);
                                                $pdfInfo = $stmtPdf->fetch(PDO::FETCH_ASSOC);

                                                if ($pdfInfo):
                                                    ?>
                                                    <a href="index.php?action=descargar_pdf&pago_id=<?= $pdfInfo['id_pago'] ?>" 
                                                       class="btn btn-info btn-sm" 
                                                       title="Descargar <?= $pdfInfo['tipo_pdf'] == 'orden_pago' ? 'Orden de Pago' : 'Comprobante' ?>">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                <?php endif; ?>
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

<!-- Modal para ver triaje -->
<div class="modal fade" id="triajeModal" tabindex="-1" aria-labelledby="triajeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="triajeModalLabel">
                    <i class="fas fa-clipboard-list"></i> Triaje Digital del Paciente
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="triajeModalContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando información del triaje...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .triaje-respuesta {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-left: 4px solid #007bff;
        margin-bottom: 15px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .triaje-respuesta:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .triaje-pregunta {
        color: #495057;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .triaje-answer {
        background: white ;

        border-radius: 6px;
        padding: 12px;
        border: 1px solid #dee2e6;
    }

    .triaje-number {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 15px;
        flex-shrink: 0;
    }

    .patient-info-card {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        border-radius: 12px;
        margin-bottom: 25px;
    }
</style>

<script>
                                                            function verDetallesCita(citaId, citaData) {
                                                            let cita;
                                                            try {
                                                            cita = JSON.parse(citaData);
                                                            } catch (e) {
                                                            console.error('Error parsing cita data:', e);
                                                            return;
                                                            }

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
            <tr><td><strong> E stado:</strong></td><td><span  c lass="badge bg-info">${cita.estado_cita } </span></td></tr>
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

           function cancelarCita(citaId) {
           document.getElementById('citaCancelarId').value = citaId;
           new bootstrap.Modal(document.getElementById('modalCancelar')).show();
           }

           function completarCita(citaId) {
           document.getElementById('citaCompletarId').value = citaId;
           new bootstrap.Modal(document.getElementById('modalCompletar')).show();
           }

           function verTriaje(citaId) {
           // Mostrar modal
           const modal = new bootstrap.Modal(document.getElementById('triajeModal'));
           modal.show();
           // Mostrar loading
           document.getElementById('triajeModalContent').innerHTML = `
<div class="text-center">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
    <p class="mt-2">Cargando información del triaje...</p>
</div>
`;
        // Hacer petición AJAX con mejor manejo de errores
        fetch(`ajax/get_triaje_respuestas.php?cita_id=${citaId}`)
                .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.text(); // Cambiar a text() primero para ver qué llega
                })
                .then(text => {
                console.log('Raw response:', text); // Ver la respuesta cruda
                try {
                const data = JSON.parse(text);
                if (data.success) {
                mostrarTriaje(data);
                } else {
                mostrarError(data.error || 'Error desconocido');
                }
                } catch (parseError) {
                console.error('Error parsing JSON:', parseError);
                console.error('Raw text was:', text);
                mostrarError('Error en la respuesta del servidor. Ver consola para más detalles.');
                }
                })
                .catch(error => {
                console.error('Fetch error:', error);
                mostrarError('Error de conexión: ' + error.message);
                });
        }

        function mostrarTriaje(data) {
        const { cita, edad, respuestas } = data;
        let html = `
<div class="row">
   <!-- Información del paciente -->
   <div class="col-md-4">
       <div class="patient-info-card p-4">
           <h6 class="mb-3">
               <i class="fas fa-user me-2"></i>
               Información del Paciente
           </h6>
           <div class="mb-2">
               <strong>Nombre:</strong><br>
               ${cita.paciente_nombre || 'N/A'}
           </div>
           <div class="mb-2">
               <strong>Cédula:</strong><br>
               ${cita.paciente_cedula || 'N/A'}
           </div>
           ${edad ? `
           <div class="mb-2">
               <strong>Edad:</strong><br>
               ${edad} años
           </div>
           ` : ''}
           ${cita.genero ? `
           <div class="mb-2">
               <strong>Género:</strong><br>
               ${cita.genero.charAt(0).toUpperCase() + cita.genero.slice(1)}
           </div>
           ` : ''}
           <div class="mb-2">
               <strong>Especialidad:</strong><br>
               ${cita.nombre_especialidad || 'N/A'}
           </div>
           <div class="mb-2">
               <strong>Fecha de Cita:</strong><br>
               ${new Date(cita.fecha_cita).toLocaleDateString('es-ES')} - ${cita.hora_cita}
           </div>
           ${respuestas.length > 0 ? `
           <div class="mt-3 p-2 bg-light rounded text-dark">
               <small>
                   <i class="fas fa-clock me-1"></i>
                   Triaje completado: ${new Date(respuestas[0].fecha_respuesta).toLocaleDateString('es-ES')} ${new Date(respuestas[0].fecha_respuesta).toLocaleTimeString('es-ES')}
               </small>
           </div>
           ` : ''}
       </div>
   </div>
           
   <!-- Respuestas del triaje -->
   <div class="col-md-8">
       <h6 class="mb-3">
           <i class="fas fa-clipboard-list me-2"></i>
           Respuestas del Triaje (${respuestas.length} preguntas)
       </h6>
               
       <div class="triaje-respuestas" style="max-height: 400px; overflow-y: auto;">
`;
   
if (respuestas.length === 0) {
html += `
           <div class="text-center py-4">
               <i class="fas fa-exclamation-circle text-warning" style="font-size: 2rem;"></i>
               <h6 class="mt-3 text-muted">Sin respuestas de triaje</h6>
               <p class="text-muted">El paciente aún no ha completado el triaje digital.</p>
           </div>
`;
} else {
respuestas.forEach((respuesta, index) => {
   html += `
           <div class="triaje-respuesta p-3">
               <div class="d-flex align-items-start">
                   <div class="triaje-number">
                       ${index + 1}
                   </div>
                   <div class="flex-grow-1">
                       <div class="triaje-pregunta">
                           ${respuesta.pregunta}
                       </div>
                       <div class="triaje-answer">
                           <strong>Respuesta:</strong> ${respuesta.respuesta}
                           ${respuesta.valor_numerico ? `
                           <span class="badge bg-primary ms-2">
                               Valor: ${respuesta.valor_numerico}
                           </span>
                           ` : ''}
                       </div>
                   </div>
               </div>
           </div>
   `;
});
}
   
html += `
       </div>
   </div>
</div>
`;
   
document.getElementById('triajeModalContent').innerHTML = html;
}

function mostrarError(mensaje) {
document.getElementById('triajeModalContent').innerHTML = `
<div class="text-center py-4">
   <i class="fas fa-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
   <h6 class="mt-3 text-danger">Error al cargar el triaje</h6>
   <p class="text-muted">${mensaje}</p>
</div>
`;
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

       setTimeout(() => {
           submitButton.disabled = false;
           submitButton.innerHTML = originalText;
       }, 3000);
   }
});
});
});
</script>

