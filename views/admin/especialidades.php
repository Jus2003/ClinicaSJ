<?php
require_once 'models/Especialidad.php';

// Verificar autenticación y permisos de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php?action=dashboard');
    exit;
}

$especialidadModel = new Especialidad();

// Procesar eliminación lógica
if (isset($_POST['delete_especialidad'])) {
    $especialidadId = $_POST['especialidad_id'];
    if ($especialidadModel->deleteEspecialidad($especialidadId)) {
        $success = "Especialidad eliminada correctamente";
    } else {
        $error = "Error al eliminar la especialidad";
    }
}

// Parámetros de búsqueda y paginación
$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$tipo_filter = $_GET['tipo_filter'] ?? '';
$duracion_filter = $_GET['duracion_filter'] ?? '';
$limit = 10;

// Obtener especialidades y total para paginación
$especialidades = $especialidadModel->getAllEspecialidadesForAdmin($page, $limit, $search, $tipo_filter, $duracion_filter);
$totalEspecialidades = $especialidadModel->countEspecialidades($search, $tipo_filter, $duracion_filter);
$totalPages = ceil($totalEspecialidades / $limit);

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
                        <i class="fas fa-user-md"></i> Gestión de Especialidades
                    </h2>
                    <p class="text-muted mb-0">Administrar especialidades médicas del sistema</p>
                </div>
                <div>
                    <a href="index.php?action=admin/especialidades/create" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nueva Especialidad
                    </a>
                </div>
            </div>

            <!-- Mensajes -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="index.php">
                        <input type="hidden" name="action" value="admin/especialidades">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Buscar</label>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Nombre o descripción..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tipo de Consulta</label>
                                <select class="form-select" name="tipo_filter">
                                    <option value="">Todos los tipos</option>
                                    <option value="presencial" <?php echo $tipo_filter == 'presencial' ? 'selected' : ''; ?>>Solo Presencial</option>
                                    <option value="virtual" <?php echo $tipo_filter == 'virtual' ? 'selected' : ''; ?>>Solo Virtual</option>
                                    <option value="ambos" <?php echo $tipo_filter == 'ambos' ? 'selected' : ''; ?>>Presencial y Virtual</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Duración</label>
                                <select class="form-select" name="duracion_filter">
                                    <option value="">Todas las duraciones</option>
                                    <option value="30" <?php echo $duracion_filter == '30' ? 'selected' : ''; ?>>30 minutos</option>
                                    <option value="45" <?php echo $duracion_filter == '45' ? 'selected' : ''; ?>>45 minutos</option>
                                    <option value="60" <?php echo $duracion_filter == '60' ? 'selected' : ''; ?>>60 minutos</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                    <a href="index.php?action=admin/especialidades" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de especialidades -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Lista de Especialidades 
                        <span class="badge bg-primary"><?php echo $totalEspecialidades; ?></span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($especialidades)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Especialidad</th>
                                        <th>Configuración</th>
                                        <th>Modalidades</th>
                                        <th>Profesionales</th>
                                        <th>Sucursales</th>
                                        <th>Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($especialidades as $especialidad): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-3">
                                                        <i class="fas fa-stethoscope text-white"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($especialidad['nombre_especialidad']); ?></strong>
                                                        <?php if ($especialidad['descripcion']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($especialidad['descripcion'], 0, 50)); ?>...</small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <i class="fas fa-clock text-muted me-1"></i>
                                                    <strong><?php echo $especialidad['duracion_cita_minutos']; ?> min</strong>
                                                </div>
                                                <small class="text-muted">Duración de cita</small>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <?php if ($especialidad['permite_presencial']): ?>
                                                        <span class="badge bg-primary">
                                                            <i class="fas fa-hospital me-1"></i>Presencial
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($especialidad['permite_virtual']): ?>
                                                        <span class="badge bg-info">
                                                            <i class="fas fa-video me-1"></i>Virtual
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-center">
                                                    <div class="h5 text-success mb-0"><?php echo $especialidad['total_medicos']; ?></div>
                                                    <small class="text-muted">Médicos</small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-center">
                                                    <div class="h5 text-info mb-0"><?php echo $especialidad['total_sucursales']; ?></div>
                                                    <small class="text-muted">Sucursales</small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($especialidad['activo'] == 1): ?>
                                                    <span class="badge bg-success">Activa</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactiva</span>
                                                <?php endif; ?>
                                                <br><small class="text-muted">
                                                    Creada: <?php echo date('d/m/Y', strtotime($especialidad['fecha_creacion'])); ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a href="index.php?action=admin/especialidades/view&id=<?php echo $especialidad['id_especialidad']; ?>" 
                                                       class="btn btn-sm btn-outline-info" title="Ver detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="index.php?action=admin/especialidades/edit&id=<?php echo $especialidad['id_especialidad']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($especialidad['total_medicos'] == 0): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="confirmDelete(<?php echo $especialidad['id_especialidad']; ?>, '<?php echo htmlspecialchars($especialidad['nombre_especialidad']); ?>')"
                                                                title="Eliminar">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No se encontraron especialidades</h5>
                            <p class="text-muted">No hay especialidades que coincidan con los criterios de búsqueda.</p>
                            <a href="index.php?action=admin/especialidades/create" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Crear Primera Especialidad
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
                                    <?php echo min($page * $limit, $totalEspecialidades); ?> de <?php echo $totalEspecialidades; ?> especialidades
                                </small>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?action=admin/especialidades&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&tipo_filter=<?php echo $tipo_filter; ?>&duracion_filter=<?php echo $duracion_filter; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?action=admin/especialidades&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&tipo_filter=<?php echo $tipo_filter; ?>&duracion_filter=<?php echo $duracion_filter; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?action=admin/especialidades&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&tipo_filter=<?php echo $tipo_filter; ?>&duracion_filter=<?php echo $duracion_filter; ?>">
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

<!-- Modal de confirmación de eliminación -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro que desea eliminar la especialidad <strong id="especialidadName"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i>
                    <strong>Nota:</strong> Esta acción desactivará la especialidad y afectará las citas y horarios asociados.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="especialidad_id" id="especialidadIdToDelete">
                    <button type="submit" name="delete_especialidad" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Eliminar Especialidad
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .avatar-sm {
        width: 35px;
        height: 35px;
        font-size: 14px;
    }
    .table th {
        font-weight: 600;
        font-size: 0.875rem;
        color: #495057;
    }
    .btn-group .btn {
        margin-right: 2px;
    }
    .btn-group .btn:last-child {
        margin-right: 0;
    }
</style>

<script>
    function confirmDelete(especialidadId, especialidadName) {
        document.getElementById('especialidadName').textContent = especialidadName;
        document.getElementById('especialidadIdToDelete').value = especialidadId;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>

</body>
</html>