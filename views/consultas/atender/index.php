<?php
// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Verificar permisos - solo admin, recepcionistas y médicos
if (!in_array($_SESSION['role_id'], [1, 2, 3])) {
    header('Location: index.php?action=dashboard');
    exit;
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Variables para mensajes y paginación
$success = '';
$error = '';
$page = (int) ($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtros
$buscar = trim($_GET['buscar'] ?? '');
$fecha = $_GET['fecha'] ?? '';
$estado = $_GET['estado'] ?? 'confirmada';

// Obtener ID de cita específica (cuando viene desde agenda)
$cita_id = (int) ($_GET['cita_id'] ?? 0);

// Procesar formulario de consulta médica
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'completar_consulta') {
            $cita_id = (int) $_POST['cita_id'];
            $diagnostico_principal = trim($_POST['diagnostico_principal'] ?? '');
            $tratamiento = trim($_POST['tratamiento'] ?? '');
            $observaciones = trim($_POST['observaciones_medicas'] ?? '');

            // Validar datos requeridos
            if (empty($diagnostico_principal)) {
                throw new Exception('El diagnóstico principal es obligatorio');
            }

            // Verificar que la cita existe y está confirmada
            $sqlVerificar = "SELECT c.*, CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre 
                FROM citas c 
                INNER JOIN usuarios p ON c.id_paciente = p.id_usuario 
                WHERE c.id_cita = :cita_id AND c.estado_cita IN ('confirmada', 'en_curso')";

            // Si es médico, solo sus citas
            if ($_SESSION['role_id'] == 3) {
                $sqlVerificar .= " AND c.id_medico = :medico_id";
            }

            $stmtVerificar = $db->prepare($sqlVerificar);
            $paramsVerificar = ['cita_id' => $cita_id];
            if ($_SESSION['role_id'] == 3) {
                $paramsVerificar['medico_id'] = $_SESSION['user_id'];
            }
            $stmtVerificar->execute($paramsVerificar);
            $cita = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$cita) {
                throw new Exception('Cita no encontrada o no autorizada');
            }

            $db->beginTransaction();

            // Crear registro de consulta
            $sqlConsulta = "INSERT INTO consultas (id_cita, diagnostico_principal, tratamiento, observaciones_medicas, fecha_consulta) 
                           VALUES (:cita_id, :diagnostico, :tratamiento, :observaciones, NOW())";
            $stmtConsulta = $db->prepare($sqlConsulta);
            $stmtConsulta->execute([
                'cita_id' => $cita_id,
                'diagnostico' => $diagnostico_principal,
                'tratamiento' => $tratamiento,
                'observaciones' => $observaciones
            ]);

            // Actualizar estado de la cita a completada
            $sqlUpdateCita = "UPDATE citas SET estado_cita = 'completada' WHERE id_cita = :cita_id";
            $stmtUpdateCita = $db->prepare($sqlUpdateCita);
            $stmtUpdateCita->execute(['cita_id' => $cita_id]);

            $db->commit();

            $success = "Consulta médica completada exitosamente para {$cita['paciente_nombre']}";
            $cita_id = 0; // Limpiar para mostrar lista
        } elseif ($action === 'iniciar_consulta') {
            $cita_id = (int) $_POST['cita_id'];

            // Cambiar estado de cita a "en_curso"
            $sqlUpdate = "UPDATE citas SET estado_cita = 'en_curso' WHERE id_cita = :cita_id AND estado_cita = 'confirmada'";

            // Si es médico, solo sus citas
            if ($_SESSION['role_id'] == 3) {
                $sqlUpdate .= " AND id_medico = :medico_id";
            }

            $stmtUpdate = $db->prepare($sqlUpdate);
            $paramsUpdate = ['cita_id' => $cita_id];
            if ($_SESSION['role_id'] == 3) {
                $paramsUpdate['medico_id'] = $_SESSION['user_id'];
            }
            $stmtUpdate->execute($paramsUpdate);

            if ($stmtUpdate->rowCount() > 0) {
                $success = "Consulta iniciada correctamente";
            } else {
                $error = "No se pudo iniciar la consulta";
            }
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Construir consulta para obtener citas
$whereConditions = [];
$params = [];

// Si hay una cita específica, mostrar solo esa
if ($cita_id > 0) {
    $whereConditions[] = "c.id_cita = :cita_id";
    $params['cita_id'] = $cita_id;
} else {
    // Filtros normales
    if ($estado !== 'todas') {
        $whereConditions[] = "c.estado_cita = :estado";
        $params['estado'] = $estado;
    } else {
        // Solo mostrar citas relevantes
        $whereConditions[] = "c.estado_cita IN ('confirmada', 'en_curso')";
    }

    if ($fecha) {
        $whereConditions[] = "c.fecha_cita = :fecha";
        $params['fecha'] = $fecha;
    }

    if ($buscar) {
        $whereConditions[] = "(p.nombre LIKE :buscar OR p.apellido LIKE :buscar OR p.cedula LIKE :buscar)";
        $params['buscar'] = "%{$buscar}%";
    }
}

// Si es médico, solo sus citas
if ($_SESSION['role_id'] == 3) {
    $whereConditions[] = "c.id_medico = :medico_id";
    $params['medico_id'] = $_SESSION['user_id'];
}

// Si es recepcionista, solo de su sucursal
if ($_SESSION['role_id'] == 2) {
    $whereConditions[] = "c.id_sucursal = (SELECT id_sucursal FROM usuarios WHERE id_usuario = :user_id)";
    $params['user_id'] = $_SESSION['user_id'];
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Consulta principal
$sql = "SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.estado_cita, c.tipo_cita, c.motivo_consulta,
               CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
               p.cedula as paciente_cedula,
               p.telefono as paciente_telefono,
               p.email as paciente_email,
               p.fecha_nacimiento,
               CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
               e.nombre_especialidad,
               s.nombre_sucursal,
               con.id_consulta,
               con.diagnostico_principal,
               con.tratamiento,
               con.observaciones_medicas,
               con.fecha_consulta
        FROM citas c
        INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
        INNER JOIN usuarios m ON c.id_medico = m.id_usuario
        INNER JOIN especialidades e ON c.id_especialidad = e.id_especialidad
        INNER JOIN sucursales s ON c.id_sucursal = s.id_sucursal
        LEFT JOIN consultas con ON c.id_cita = con.id_cita
        {$whereClause}
        ORDER BY c.fecha_cita ASC, c.hora_cita ASC";

// Preparar y ejecutar la consulta
$stmt = $db->prepare($sql);
$stmt->execute($params);

// Si no es cita específica, aplicar paginación
if ($cita_id === 0) {
    $allCitas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $citas = array_slice($allCitas, $offset, $limit);
} else {
    $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar total para paginación (solo si no es cita específica)
$totalCitas = 0;
if ($cita_id === 0) {
    $sqlCount = "SELECT COUNT(*) FROM citas c
                 INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
                 INNER JOIN usuarios m ON c.id_medico = m.id_usuario
                 INNER JOIN especialidades e ON c.id_especialidad = e.id_especialidad
                 INNER JOIN sucursales s ON c.id_sucursal = s.id_sucursal
                 {$whereClause}";
    $stmtCount = $db->prepare($sqlCount);
    $paramsCount = $params;
    unset($paramsCount['limit'], $paramsCount['offset']);
    $stmtCount->execute($paramsCount);
    $totalCitas = $stmtCount->fetchColumn();
    $totalPages = ceil($totalCitas / $limit);
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
                    <h2 class="h3 mb-1">
                        <i class="fas fa-user-nurse text-success me-2"></i>
                        Atender Pacientes
                    </h2>
                    <p class="text-muted mb-0">
                        <?php if ($cita_id > 0): ?>
                            Atendiendo consulta médica específica
                        <?php else: ?>
                            Gestión de consultas médicas confirmadas y en curso
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($cita_id > 0): ?>
                    <a href="index.php?action=consultas/atender" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver a Lista
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mensajes -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($cita_id === 0): ?>
                <!-- Filtros de búsqueda -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="action" value="consultas/atender">

                            <div class="col-md-3">
                                <label for="buscar" class="form-label">Buscar Paciente</label>
                                <input type="text" class="form-control" id="buscar" name="buscar" 
                                       value="<?php echo htmlspecialchars($buscar); ?>" 
                                       placeholder="Nombre, apellido o cédula">
                            </div>

                            <div class="col-md-2">
                                <label for="fecha" class="form-label">Fecha</label>
                                <input type="date" class="form-control" id="fecha" name="fecha" 
                                       value="<?php echo $fecha; ?>">
                            </div>

                            <div class="col-md-2">
                                <label for="estado" class="form-label">Estado</label>
                                <select class="form-select" id="estado" name="estado">
                                    <option value="confirmada" <?php echo $estado === 'confirmada' ? 'selected' : ''; ?>>Confirmadas</option>
                                    <option value="en_curso" <?php echo $estado === 'en_curso' ? 'selected' : ''; ?>>En Curso</option>
                                    <option value="todas" <?php echo $estado === 'todas' ? 'selected' : ''; ?>>Todas</option>
                                </select>
                            </div>

                            <div class="col-md-3 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                                <a href="index.php?action=consultas/atender" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Limpiar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Lista de citas / Formulario de consulta -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if (empty($citas)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No hay citas para atender</h5>
                            <p class="text-muted">
                                <?php if ($cita_id > 0): ?>
                                    La cita solicitada no fue encontrada o no tienes permisos para acceder a ella.
                                <?php else: ?>
                                    No hay citas confirmadas o en curso en este momento.
                                <?php endif; ?>
                            </p>
                            <a href="index.php?action=citas/gestionar" class="btn btn-primary">
                                <i class="fas fa-calendar-check"></i> Ver Gestión de Citas
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($citas as $cita): ?>
                            <div class="row mb-4 p-4 border rounded">
                                <!-- Información del Paciente -->
                                <div class="col-lg-4">
                                    <div class="card border-primary h-100">
                                        <div class="card-header bg-primary text-white">
                                            <h6 class="mb-0">
                                                <i class="fas fa-user me-2"></i>Información del Paciente
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Nombre:</strong> <?php echo $cita['paciente_nombre']; ?></p>
                                            <p><strong>Cédula:</strong> <?php echo $cita['paciente_cedula']; ?></p>
                                            <p><strong>Teléfono:</strong> <?php echo $cita['paciente_telefono']; ?></p>
                                            <p><strong>Email:</strong> <?php echo $cita['paciente_email']; ?></p>
                                            <?php if ($cita['fecha_nacimiento']): ?>
                                                <p><strong>Edad:</strong> 
                                                    <?php
                                                    $edad = date_diff(date_create($cita['fecha_nacimiento']), date_create('today'))->y;
                                                    echo $edad . ' años';
                                                    ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Información de la Cita -->
                                <div class="col-lg-4">
                                    <div class="card border-info h-100">
                                        <div class="card-header bg-info text-white">
                                            <h6 class="mb-0">
                                                <i class="fas fa-calendar-alt me-2"></i>Información de la Cita
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></p>
                                            <p><strong>Hora:</strong> <?php echo date('H:i', strtotime($cita['hora_cita'])); ?></p>
                                            <p><strong>Tipo:</strong> 
                                                <span class="badge <?php echo $cita['tipo_cita'] === 'presencial' ? 'bg-primary' : 'bg-success'; ?>">
                                                    <?php echo ucfirst($cita['tipo_cita']); ?>
                                                </span>
                                            </p>
                                            <p><strong>Estado:</strong> 
                                                <span class="badge <?php
                                                echo $cita['estado_cita'] === 'confirmada' ? 'bg-warning' :
                                                        ($cita['estado_cita'] === 'en_curso' ? 'bg-info' : 'bg-success');
                                                ?>">
                                                          <?php echo ucfirst(str_replace('_', ' ', $cita['estado_cita'])); ?>
                                                </span>
                                            </p>
                                            <p><strong>Especialidad:</strong> <?php echo $cita['nombre_especialidad']; ?></p>
                                            <p><strong>Médico:</strong> <?php echo $cita['medico_nombre']; ?></p>
                                            <p><strong>Sucursal:</strong> <?php echo $cita['nombre_sucursal']; ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Consulta Médica -->
                                <div class="col-lg-4">
                                    <div class="card border-success h-100">
                                        <div class="card-header bg-success text-white">
                                            <h6 class="mb-0">
                                                <i class="fas fa-stethoscope me-2"></i>Consulta Médica
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <?php if ($cita['id_consulta']): ?>
                                                <!-- Consulta ya completada -->
                                                <div class="alert alert-success">
                                                    <i class="fas fa-check-circle me-2"></i>Consulta completada
                                                </div>
                                                <p><strong>Fecha consulta:</strong> <?php echo date('d/m/Y H:i', strtotime($cita['fecha_consulta'])); ?></p>
                                                <p><strong>Diagnóstico:</strong> <?php echo $cita['diagnostico_principal']; ?></p>
                                                <?php if ($cita['tratamiento']): ?>
                                                    <p><strong>Tratamiento:</strong> <?php echo $cita['tratamiento']; ?></p>
                                                <?php endif; ?>

                                                <div class="mt-3">
                                                    <a href="index.php?action=consultas/recetas/crear&consulta_id=<?php echo $cita['id_consulta']; ?>" 
                                                       class="btn btn-primary btn-sm">
                                                        <i class="fas fa-prescription"></i> Crear Receta
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <!-- Formulario para completar consulta -->
                                                <?php if ($cita['estado_cita'] === 'confirmada'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="iniciar_consulta">
                                                        <input type="hidden" name="cita_id" value="<?php echo $cita['id_cita']; ?>">
                                                        <button type="submit" class="btn btn-info btn-sm w-100 mb-2">
                                                            <i class="fas fa-play"></i> Iniciar Consulta
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($cita['estado_cita'] === 'en_curso' || $cita['estado_cita'] === 'confirmada'): ?>
                                                    <button type="button" class="btn btn-success btn-sm w-100" 
                                                            onclick="mostrarFormularioConsulta(<?php echo $cita['id_cita']; ?>)">
                                                        <i class="fas fa-user-md"></i> Completar Consulta
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Motivo de consulta -->
                                <?php if ($cita['motivo_consulta']): ?>
                                    <div class="col-12 mt-3">
                                        <div class="alert alert-light">
                                            <strong><i class="fas fa-comment-medical me-2"></i>Motivo de consulta:</strong>
                                            <?php echo $cita['motivo_consulta']; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <!-- Paginación -->
                        <?php if ($cita_id === 0 && $totalPages > 1): ?>
                            <nav aria-label="Navegación de páginas">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?action=consultas/atender&page=<?php echo $page - 1; ?>&buscar=<?php echo urlencode($buscar); ?>&fecha=<?php echo $fecha; ?>&estado=<?php echo $estado; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?action=consultas/atender&page=<?php echo $i; ?>&buscar=<?php echo urlencode($buscar); ?>&fecha=<?php echo $fecha; ?>&estado=<?php echo $estado; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?action=consultas/atender&page=<?php echo $page + 1; ?>&buscar=<?php echo urlencode($buscar); ?>&fecha=<?php echo $fecha; ?>&estado=<?php echo $estado; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para completar consulta -->
<div class="modal fade" id="modalConsulta" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-md me-2"></i>Completar Consulta Médica
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formConsulta">
                <div class="modal-body">
                    <input type="hidden" name="action" value="completar_consulta">
                    <input type="hidden" name="cita_id" id="modalCitaId">

                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="diagnostico_principal" class="form-label">
                                Diagnóstico Principal <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="diagnostico_principal" name="diagnostico_principal" 
                                      rows="3" required placeholder="Ingrese el diagnóstico principal de la consulta"></textarea>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="tratamiento" class="form-label">Tratamiento Indicado</label>
                            <textarea class="form-control" id="tratamiento" name="tratamiento" 
                                      rows="3" placeholder="Ingrese el tratamiento recomendado"></textarea>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="observaciones_medicas" class="form-label">Observaciones Médicas</label>
                            <textarea class="form-control" id="observaciones_medicas" name="observaciones_medicas" 
                                      rows="3" placeholder="Observaciones adicionales, recomendaciones, etc."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Completar Consulta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function mostrarFormularioConsulta(citaId) {
        document.getElementById('modalCitaId').value = citaId;
        const modal = new bootstrap.Modal(document.getElementById('modalConsulta'));
        modal.show();
    }

// Auto-focus en el primer campo cuando se abre el modal
    document.getElementById('modalConsulta').addEventListener('shown.bs.modal', function () {
        document.getElementById('diagnostico_principal').focus();
    });
</script>