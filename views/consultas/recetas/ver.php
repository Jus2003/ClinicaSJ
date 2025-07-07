<?php
// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Verificar permisos (solo pacientes)
if ($_SESSION['role_id'] != 4) {
    header('Location: index.php?action=consultas/recetas');
    exit;
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Variables para filtros (limitados para pacientes)
$estado_filter = $_GET['estado'] ?? 'todas';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Construir consulta base - solo recetas del paciente logueado
$whereConditions = [];
$params = [];

// Filtro principal: solo recetas del paciente actual
$whereConditions[] = "cit.id_paciente = :id_paciente";
$params['id_paciente'] = $_SESSION['user_id'];

// Filtro por estado
if ($estado_filter !== 'todas') {
    $whereConditions[] = "r.estado = :estado";
    $params['estado'] = $estado_filter;
}

// Filtro por rango de fechas
if ($fecha_desde) {
    $whereConditions[] = "DATE(r.fecha_emision) >= :fecha_desde";
    $params['fecha_desde'] = $fecha_desde;
}

if ($fecha_hasta) {
    $whereConditions[] = "DATE(r.fecha_emision) <= :fecha_hasta";
    $params['fecha_hasta'] = $fecha_hasta;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Consulta principal
$sql = "SELECT r.*, 
               CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
               e.nombre_especialidad,
               s.nombre_sucursal,
               cit.fecha_cita,
               cit.hora_cita,
               cit.motivo_consulta
        FROM recetas r
        INNER JOIN consultas c ON r.id_consulta = c.id_consulta
        INNER JOIN citas cit ON c.id_cita = cit.id_cita
        INNER JOIN usuarios m ON cit.id_medico = m.id_usuario
        INNER JOIN especialidades e ON cit.id_especialidad = e.id_especialidad
        INNER JOIN sucursales s ON cit.id_sucursal = s.id_sucursal
        {$whereClause}
        ORDER BY r.fecha_emision DESC
        LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue(":{$key}", $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$recetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar total para paginación
$sqlCount = "SELECT COUNT(*) as total
             FROM recetas r
             INNER JOIN consultas c ON r.id_consulta = c.id_consulta
             INNER JOIN citas cit ON c.id_cita = cit.id_cita
             INNER JOIN usuarios m ON cit.id_medico = m.id_usuario
             INNER JOIN especialidades e ON cit.id_especialidad = e.id_especialidad
             INNER JOIN sucursales s ON cit.id_sucursal = s.id_sucursal
             {$whereClause}";

$stmtCount = $db->prepare($sqlCount);
foreach ($params as $key => $value) {
    $stmtCount->bindValue(":{$key}", $value);
}
$stmtCount->execute();
$totalRecetas = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecetas / $limit);

// Obtener estadísticas del paciente
$sqlStats = "SELECT 
                COUNT(*) as total_recetas,
                SUM(CASE WHEN r.estado = 'activa' THEN 1 ELSE 0 END) as recetas_activas,
                SUM(CASE WHEN r.estado = 'dispensada' THEN 1 ELSE 0 END) as recetas_dispensadas,
                SUM(CASE WHEN r.estado = 'vencida' OR (r.estado = 'activa' AND r.fecha_vencimiento < CURDATE()) THEN 1 ELSE 0 END) as recetas_vencidas
             FROM recetas r
             INNER JOIN consultas c ON r.id_consulta = c.id_consulta
             INNER JOIN citas cit ON c.id_cita = cit.id_cita
             WHERE cit.id_paciente = :id_paciente";

$stmtStats = $db->prepare($sqlStats);
$stmtStats->execute(['id_paciente' => $_SESSION['user_id']]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

// Obtener recetas próximas a vencer (activas que vencen en los próximos 7 días)
$sqlProximasVencer = "SELECT COUNT(*) as proximas_vencer
                      FROM recetas r
                      INNER JOIN consultas c ON r.id_consulta = c.id_consulta
                      INNER JOIN citas cit ON c.id_cita = cit.id_cita
                      WHERE cit.id_paciente = :id_paciente
                      AND r.estado = 'activa'
                      AND r.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";

$stmtProximasVencer = $db->prepare($sqlProximasVencer);
$stmtProximasVencer->execute(['id_paciente' => $_SESSION['user_id']]);
$proximasVencer = $stmtProximasVencer->fetch(PDO::FETCH_ASSOC)['proximas_vencer'];

// Función para obtener clase de badge según estado
function getEstadoBadgeClass($estado) {
    switch ($estado) {
        case 'activa': return 'bg-success';
        case 'dispensada': return 'bg-info';
        case 'vencida': return 'bg-warning';
        case 'cancelada': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

// Función para formatear estado
function formatearEstado($estado) {
    switch ($estado) {
        case 'activa': return 'Activa';
        case 'dispensada': return 'Dispensada';
        case 'vencida': return 'Vencida';
        case 'cancelada': return 'Cancelada';
        default: return ucfirst($estado);
    }
}

// Función para verificar si está vencida
function estaVencida($fecha_vencimiento, $estado) {
    return ($estado === 'activa' && strtotime($fecha_vencimiento) < time()) || $estado === 'vencida';
}

// Función para verificar si está próxima a vencer
function proximaAVencer($fecha_vencimiento, $estado) {
    if ($estado !== 'activa') return false;
    $dias = (strtotime($fecha_vencimiento) - time()) / (60 * 60 * 24);
    return $dias <= 7 && $dias > 0;
}

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-prescription text-primary"></i> 
                    Mis Recetas Médicas
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <small class="text-muted">
                        <i class="fas fa-user me-1"></i>
                        Vista del paciente
                    </small>
                </div>
            </div>

            <!-- Alertas importantes -->
            <?php if ($proximasVencer > 0): ?>
                <div class="alert alert-warning border-0 shadow-sm mb-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                        <div>
                            <h6 class="alert-heading mb-1">¡Atención! Recetas próximas a vencer</h6>
                            <p class="mb-0">
                                Tienes <strong><?php echo $proximasVencer; ?></strong> receta(s) que vence(n) en los próximos 7 días.
                                <a href="#" onclick="filtrarProximasVencer()" class="alert-link">Ver recetas</a>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Estadísticas del paciente -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-0 shadow-sm bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-1"><?php echo $stats['total_recetas']; ?></h3>
                                    <p class="mb-0 small">Total Recetas</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-prescription fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-0 shadow-sm bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-1"><?php echo $stats['recetas_activas']; ?></h3>
                                    <p class="mb-0 small">Activas</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-0 shadow-sm bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-1"><?php echo $stats['recetas_dispensadas']; ?></h3>
                                    <p class="mb-0 small">Dispensadas</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-hand-holding-medical fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-0 shadow-sm bg-warning text-dark">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-1"><?php echo $stats['recetas_vencidas']; ?></h3>
                                    <p class="mb-0 small">Vencidas</p>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros simplificados para pacientes -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <input type="hidden" name="action" value="consultas/recetas/ver">
                        
                        <div class="col-md-3">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="todas" <?php echo $estado_filter === 'todas' ? 'selected' : ''; ?>>Todas mis recetas</option>
                                <option value="activa" <?php echo $estado_filter === 'activa' ? 'selected' : ''; ?>>Activas</option>
                                <option value="dispensada" <?php echo $estado_filter === 'dispensada' ? 'selected' : ''; ?>>Dispensadas</option>
                                <option value="vencida" <?php echo $estado_filter === 'vencida' ? 'selected' : ''; ?>>Vencidas</option>
                                <option value="cancelada" <?php echo $estado_filter === 'cancelada' ? 'selected' : ''; ?>>Canceladas</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="fecha_desde" class="form-label">Desde</label>
                            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                                   value="<?php echo htmlspecialchars($fecha_desde); ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="fecha_hasta" class="form-label">Hasta</label>
                            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                                   value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filtrar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de recetas -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list text-muted me-2"></i>
                            Mis Recetas 
                            <span class="badge bg-primary ms-2"><?php echo $totalRecetas; ?></span>
                        </h5>
                        <?php if ($totalRecetas > 0): ?>
                            <small class="text-muted">
                                Página <?php echo $page; ?> de <?php echo $totalPages; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body p-0">
                    <?php if (!empty($recetas)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Código</th>
                                        <th>Medicamento</th>
                                        <th>Médico</th>
                                        <th>Fecha Emisión</th>
                                        <th>Vencimiento</th>
                                        <th>Estado</th>
                                        <th width="100">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recetas as $receta): ?>
                                        <tr class="<?php echo estaVencida($receta['fecha_vencimiento'], $receta['estado']) ? 'table-warning' : ''; ?>">
                                            <td>
                                                <span class="fw-bold text-primary"><?php echo htmlspecialchars($receta['codigo_receta']); ?></span>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($receta['medicamento']); ?></div>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($receta['concentracion']); ?> - 
                                                        <?php echo htmlspecialchars($receta['forma_farmaceutica']); ?>
                                                    </small>
                                                    <br>
                                                    <small class="text-info">
                                                        <strong>Dosis:</strong> <?php echo htmlspecialchars($receta['dosis']); ?> - 
                                                        <?php echo htmlspecialchars($receta['frecuencia']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($receta['medico_nombre']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($receta['nombre_especialidad']); ?></small>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($receta['nombre_sucursal']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div><?php echo date('d/m/Y', strtotime($receta['fecha_emision'])); ?></div>
                                                    <small class="text-muted"><?php echo date('H:i', strtotime($receta['fecha_emision'])); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="<?php echo estaVencida($receta['fecha_vencimiento'], $receta['estado']) ? 'text-danger fw-bold' : ''; ?>">
                                                    <?php echo date('d/m/Y', strtotime($receta['fecha_vencimiento'])); ?>
                                                </div>
                                                <?php if (proximaAVencer($receta['fecha_vencimiento'], $receta['estado'])): ?>
                                                    <small class="text-warning">
                                                        <i class="fas fa-clock"></i> Próxima a vencer
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo getEstadoBadgeClass($receta['estado']); ?>">
                                                    <?php echo formatearEstado($receta['estado']); ?>
                                                </span>
                                                <?php if (estaVencida($receta['fecha_vencimiento'], $receta['estado'])): ?>
                                                    <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Vencida</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="index.php?action=consultas/recetas/detalle&id=<?php echo $receta['id_receta']; ?>" 
                                                       class="btn btn-outline-primary" title="Ver detalles completos">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <a href="index.php?action=consultas/recetas/imprimir&id=<?php echo $receta['id_receta']; ?>" 
                                                       class="btn btn-outline-success" title="Descargar receta" target="_blank">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-prescription fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No tienes recetas médicas</h5>
                            <p class="text-muted">Las recetas aparecerán aquí después de tus consultas médicas.</p>
                            <div class="mt-3">
                                <a href="index.php?action=citas/agendar" class="btn btn-primary">
                                    <i class="fas fa-calendar-plus"></i> Agendar Cita Médica
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-muted">
                                    Mostrando <?php echo (($page - 1) * $limit) + 1; ?> - 
                                    <?php echo min($page * $limit, $totalRecetas); ?> de <?php echo $totalRecetas; ?> recetas
                                </small>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?action=consultas/recetas/ver&page=<?php echo $page - 1; ?>&estado=<?php echo $estado_filter; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?action=consultas/recetas/ver&page=<?php echo $i; ?>&estado=<?php echo $estado_filter; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?action=consultas/recetas/ver&page=<?php echo $page + 1; ?>&estado=<?php echo $estado_filter; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Información útil para pacientes -->
<div class="mt-4">
    <div class="row">
        <div class="col-md-8">
            <div class="alert alert-info border-0">
                <h6 class="alert-heading">
                    <i class="fas fa-lightbulb me-2"></i>Consejos importantes sobre tus recetas
                </h6>
                <ul class="mb-0 small">
                    <li><strong>Revisa las fechas de vencimiento</strong> regularmente</li>
                    <li><strong>Sigue las indicaciones</strong> de dosis y frecuencia exactamente como se prescribió</li>
                    <li><strong>No suspendas el tratamiento</strong> sin consultar con tu médico</li>
                    <li><strong>Conserva las recetas</strong> en lugar seguro y a temperatura ambiente</li>
                    <li><strong>Consulta a tu médico</strong> si tienes dudas sobre algún medicamento</li>
                </ul>
            </div>
        </div>
        <div class="col-md-4">
            <div class="alert alert-warning border-0">
                <h6 class="alert-heading">
                    <i class="fas fa-exclamation-triangle me-2"></i>¿Necesitas ayuda?
                </h6>
                <p class="mb-2 small">Si tienes dudas sobre tus medicamentos:</p>
                <a href="index.php?action=citas/agendar" class="btn btn-warning btn-sm">
                    <i class="fas fa-calendar-plus"></i> Agendar Consulta
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function filtrarProximasVencer() {
    // Cambiar filtros para mostrar solo recetas activas próximas a vencer
    const fechaHoy = new Date().toISOString().split('T')[0];
    const fecha7Dias = new Date(Date.now() + 7*24*60*60*1000).toISOString().split('T')[0];
    
    const url = new URL(window.location);
    url.searchParams.set('estado', 'activa');
    url.searchParams.set('fecha_desde', fechaHoy);
    url.searchParams.set('fecha_hasta', fecha7Dias);
    
    window.location.href = url.toString();
}
</script>