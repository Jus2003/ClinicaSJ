<?php
// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Verificar permisos (solo admin y médicos)
if (!in_array($_SESSION['role_id'], [1, 3])) {
    header('Location: index.php?action=consultas/recetas');
    exit;
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Variables para filtros
$buscar = $_GET['buscar'] ?? '';
$estado_filter = $_GET['estado'] ?? 'todas';
$medico_filter = $_GET['medico'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Variables para mensajes
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Manejar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['cambiar_estado']) && isset($_POST['id_receta']) && isset($_POST['nuevo_estado'])) {
            $id_receta = (int)$_POST['id_receta'];
            $nuevo_estado = $_POST['nuevo_estado'];
            
            // Verificar que el médico solo pueda editar sus propias recetas
            $wherePermiso = '';
            $paramsPermiso = ['id_receta' => $id_receta];
            
            if ($_SESSION['role_id'] == 3) { // Médico
                $wherePermiso = " AND c.id_cita IN (SELECT id_cita FROM citas WHERE id_medico = :id_medico)";
                $paramsPermiso['id_medico'] = $_SESSION['user_id'];
            }
            
            // Verificar que la receta existe y el usuario tiene permisos
            $sqlVerificar = "SELECT r.id_receta FROM recetas r 
                           INNER JOIN consultas c ON r.id_consulta = c.id_consulta 
                           WHERE r.id_receta = :id_receta" . $wherePermiso;
            $stmtVerificar = $db->prepare($sqlVerificar);
            $stmtVerificar->execute($paramsPermiso);
            
            if ($stmtVerificar->rowCount() > 0) {
                $sqlUpdate = "UPDATE recetas SET estado = :estado WHERE id_receta = :id_receta";
                $stmtUpdate = $db->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    'estado' => $nuevo_estado,
                    'id_receta' => $id_receta
                ]);
                
                $success = "Estado de la receta actualizado exitosamente";
            } else {
                $error = "No tienes permisos para modificar esta receta";
            }
        }
    } catch (Exception $e) {
        $error = "Error al procesar la solicitud: " . $e->getMessage();
    }
}

// Construir consulta base con filtros
$whereConditions = [];
$params = [];

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

// Filtro por médico (solo para admin)
if ($medico_filter && $_SESSION['role_id'] == 1) {
    $whereConditions[] = "cit.id_medico = :medico";
    $params['medico'] = $medico_filter;
}

// Si es médico, solo mostrar sus recetas
if ($_SESSION['role_id'] == 3) {
    $whereConditions[] = "cit.id_medico = :id_medico";
    $params['id_medico'] = $_SESSION['user_id'];
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

// Obtener médicos para filtro (solo admin)
$medicos = [];
if ($_SESSION['role_id'] == 1) {
    $sqlMedicos = "SELECT id_usuario, CONCAT(nombre, ' ', apellido) as nombre_completo 
                   FROM usuarios WHERE id_rol = 3 AND activo = 1 ORDER BY nombre, apellido";
    $stmtMedicos = $db->prepare($sqlMedicos);
    $stmtMedicos->execute();
    $medicos = $stmtMedicos->fetchAll(PDO::FETCH_ASSOC);
}

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
                    Gestión de Recetas Médicas
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="index.php?action=consultas/recetas/crear" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nueva Receta
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mensajes -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3">
                        <input type="hidden" name="action" value="consultas/recetas/index">
                        
                        <div class="col-md-3">
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

                        <?php if ($_SESSION['role_id'] == 1): ?>
                        <div class="col-md-2">
                            <label for="medico" class="form-label">Médico</label>
                            <select class="form-select" id="medico" name="medico">
                                <option value="">Todos</option>
                                <?php foreach ($medicos as $medico): ?>
                                    <option value="<?php echo $medico['id_usuario']; ?>" 
                                            <?php echo $medico_filter == $medico['id_usuario'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($medico['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

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

                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
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
                                        <th width="120">Acciones</th>
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
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="index.php?action=consultas/recetas/detalle&id=<?php echo $receta['id_receta']; ?>" 
                                                       class="btn btn-outline-primary" title="Ver detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <?php if (in_array($receta['estado'], ['activa', 'dispensada'])): ?>
                                                        <button type="button" class="btn btn-outline-warning" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#cambiarEstadoModal"
                                                                data-id="<?php echo $receta['id_receta']; ?>"
                                                                data-codigo="<?php echo htmlspecialchars($receta['codigo_receta']); ?>"
                                                                data-estado="<?php echo $receta['estado']; ?>"
                                                                title="Cambiar estado">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
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
                            <a href="index.php?action=consultas/recetas/crear" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Crear Primera Receta
                            </a>
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
                                            <a class="page-link" href="?action=consultas/recetas/index&page=<?php echo $page - 1; ?>&buscar=<?php echo urlencode($buscar); ?>&estado=<?php echo $estado_filter; ?>&medico=<?php echo $medico_filter; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?action=consultas/recetas/index&page=<?php echo $i; ?>&buscar=<?php echo urlencode($buscar); ?>&estado=<?php echo $estado_filter; ?>&medico=<?php echo $medico_filter; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?action=consultas/recetas/index&page=<?php echo $page + 1; ?>&buscar=<?php echo urlencode($buscar); ?>&estado=<?php echo $estado_filter; ?>&medico=<?php echo $medico_filter; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>">
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

<!-- Modal para cambiar estado -->
<div class="modal fade" id="cambiarEstadoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit text-warning"></i> Cambiar Estado de Receta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas cambiar el estado de la receta <strong id="modal-codigo"></strong>?</p>
                    
                    <div class="mb-3">
                        <label for="nuevo_estado" class="form-label">Nuevo Estado</label>
                        <select class="form-select" id="nuevo_estado" name="nuevo_estado" required>
                            <option value="activa">Activa</option>
                            <option value="dispensada">Dispensada</option>
                            <option value="vencida">Vencida</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>
                    
                    <input type="hidden" id="modal-id" name="id_receta">
                    <input type="hidden" name="cambiar_estado" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Cambiar Estado
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Script para el modal de cambiar estado
document.addEventListener('DOMContentLoaded', function() {
    const cambiarEstadoModal = document.getElementById('cambiarEstadoModal');
    
    cambiarEstadoModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const codigo = button.getAttribute('data-codigo');
        const estadoActual = button.getAttribute('data-estado');
        
        document.getElementById('modal-id').value = id;
        document.getElementById('modal-codigo').textContent = codigo;
        document.getElementById('nuevo_estado').value = estadoActual;
    });
});
</script>