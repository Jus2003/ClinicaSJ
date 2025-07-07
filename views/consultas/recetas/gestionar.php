<?php
// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Verificar permisos (solo recepcionistas)
if ($_SESSION['role_id'] != 2) {
    header('Location: index.php?action=consultas/recetas');
    exit;
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Variables para filtros
$buscar = $_GET['buscar'] ?? '';
$estado_filter = $_GET['estado'] ?? 'todas';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Construir consulta base con filtros
$whereConditions = [];
$params = [];

// Solo mostrar recetas de la sucursal del recepcionista
$whereConditions[] = "cit.id_sucursal = (SELECT id_sucursal FROM usuarios WHERE id_usuario = :user_id)";
$params['user_id'] = $_SESSION['user_id'];

// Filtro por búsqueda (paciente o código de receta)
if ($buscar) {
    $whereConditions[] = "(CONCAT(p.nombre, ' ', p.apellido) LIKE :buscar OR r.codigo_receta LIKE :buscar OR r.medicamento LIKE :buscar)";
    $params['buscar'] = "%{$buscar}%";
}

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
               CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
               p.cedula as paciente_cedula,
               p.telefono as paciente_telefono,
               CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
               e.nombre_especialidad,
               s.nombre_sucursal,
               cit.fecha_cita
        FROM recetas r
        INNER JOIN consultas c ON r.id_consulta = c.id_consulta
        INNER JOIN citas cit ON c.id_cita = cit.id_cita
        INNER JOIN usuarios p ON cit.id_paciente = p.id_usuario
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
             INNER JOIN usuarios p ON cit.id_paciente = p.id_usuario
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

// Obtener estadísticas rápidas de la sucursal
$sqlStats = "SELECT 
                COUNT(*) as total_recetas,
                SUM(CASE WHEN r.estado = 'activa' THEN 1 ELSE 0 END) as recetas_activas,
                SUM(CASE WHEN r.estado = 'dispensada' THEN 1 ELSE 0 END) as recetas_dispensadas,
                SUM(CASE WHEN r.estado = 'vencida' THEN 1 ELSE 0 END) as recetas_vencidas
             FROM recetas r
             INNER JOIN consultas c ON r.id_consulta = c.id_consulta
             INNER JOIN citas cit ON c.id_cita = cit.id_cita
             WHERE cit.id_sucursal = (SELECT id_sucursal FROM usuarios WHERE id_usuario = :user_id)
             AND DATE(r.fecha_emision) = CURDATE()";

$stmtStats = $db->prepare($sqlStats);
$stmtStats->execute(['user_id' => $_SESSION['user_id']]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

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
                    Consulta de Recetas Médicas
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Vista de solo lectura para recepcionistas
                    </small>
                </div>
            </div>

            <!-- Estadísticas rápidas del día -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-0 shadow-sm bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h3 class="mb-1"><?php echo $stats['total_recetas']; ?></h3>
                                    <p class="mb-0 small">Recetas Hoy</p>
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

            <!-- Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <input type="hidden" name="action" value="consultas/recetas/gestionar">
                        
                        <div class="col-md-4">
                            <label for="buscar" class="form-label">Buscar</label>
                            <input type="text" class="form-control" id="buscar" name="buscar" 
                                   value="<?php echo htmlspecialchars($buscar); ?>" 
                                   placeholder="Paciente, código o medicamento...">
                        </div>

                        <div class="col-md-2">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado" name="estado">
                                <option value="todas" <?php echo $estado_filter === 'todas' ? 'selected' : ''; ?>>Todas</option>
                                <option value="activa" <?php echo $estado_filter === 'activa' ? 'selected' : ''; ?>>Activas</option>
                                <option value="dispensada" <?php echo $estado_filter === 'dispensada' ? 'selected' : ''; ?>>Dispensadas</option>
                                <option value="vencida" <?php echo $estado_filter === 'vencida' ? 'selected' : ''; ?>>Vencidas</option>
                                <option value="cancelada" <?php echo $estado_filter === 'cancelada' ? 'selected' : ''; ?>>Canceladas</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label for="fecha_desde" class="form-label">Desde</label>
                            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                                   value="<?php echo htmlspecialchars($fecha_desde); ?>">
                        </div>

                        <div class="col-md-2">
                            <label for="fecha_hasta" class="form-label">Hasta</label>
                            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                                   value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de recetas -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-list text-muted me-2"></i>
                            Recetas Médicas 
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
                                        <th>Paciente</th>
                                        <th>Medicamento</th>
                                        <th>Médico</th>
                                        <th>Fecha Emisión</th>
                                        <th>Estado</th>
                                        <th width="80">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recetas as $receta): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold text-primary"><?php echo htmlspecialchars($receta['codigo_receta']); ?></span>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($receta['paciente_nombre']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($receta['paciente_cedula']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($receta['medicamento']); ?></div>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($receta['concentracion']); ?> - 
                                                        <?php echo htmlspecialchars($receta['forma_farmaceutica']); ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($receta['medico_nombre']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($receta['nombre_especialidad']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div><?php echo date('d/m/Y', strtotime($receta['fecha_emision'])); ?></div>
                                                    <small class="text-muted"><?php echo date('H:i', strtotime($receta['fecha_emision'])); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo getEstadoBadgeClass($receta['estado']); ?>">
                                                    <?php echo formatearEstado($receta['estado']); ?>
                                                </span>
                                                <?php if (strtotime($receta['fecha_vencimiento']) < time() && $receta['estado'] === 'activa'): ?>
                                                    <br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Vencida</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="index.php?action=consultas/recetas/detalle&id=<?php echo $receta['id_receta']; ?>" 
                                                       class="btn btn-outline-primary" title="Ver detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <a href="index.php?action=consultas/recetas/imprimir&id=<?php echo $receta['id_receta']; ?>" 
                                                       class="btn btn-outline-success" title="Imprimir" target="_blank">
                                                        <i class="fas fa-print"></i>
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
                            <h5 class="text-muted">No se encontraron recetas</h5>
                            <p class="text-muted">No hay recetas que coincidan con los criterios de búsqueda.</p>
                            <div class="alert alert-info border-0 d-inline-block">
                                <i class="fas fa-info-circle me-2"></i>
                                Las recetas son creadas por los médicos durante las consultas.
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
                                            <a class="page-link" href="?action=consultas/recetas/gestionar&page=<?php echo $page - 1; ?>&buscar=<?php echo urlencode($buscar); ?>&estado=<?php echo $estado_filter; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?action=consultas/recetas/gestionar&page=<?php echo $i; ?>&buscar=<?php echo urlencode($buscar); ?>&estado=<?php echo $estado_filter; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?action=consultas/recetas/gestionar&page=<?php echo $page + 1; ?>&buscar=<?php echo urlencode($buscar); ?>&estado=<?php echo $estado_filter; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>">
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

<!-- Información adicional para recepcionistas -->
<div class="mt-4">
    <div class="alert alert-info border-0">
        <h6 class="alert-heading">
            <i class="fas fa-info-circle me-2"></i>Información para Recepcionistas
        </h6>
        <ul class="mb-0 small">
            <li>Esta vista muestra solo las recetas de su sucursal</li>
            <li>Puede consultar y imprimir recetas, pero no modificar su estado</li>
            <li>Para cambios en el estado de las recetas, contacte al médico correspondiente</li>
            <li>Las recetas vencidas se marcan automáticamente en rojo</li>
        </ul>
    </div>
</div>