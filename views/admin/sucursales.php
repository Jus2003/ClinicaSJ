<?php
require_once 'models/Sucursal.php';

// Verificar autenticación y permisos de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php?action=dashboard');
    exit;
}

$sucursalModel = new Sucursal();

// Procesar eliminación lógica
if (isset($_POST['delete_sucursal'])) {
    $sucursalId = $_POST['sucursal_id'];
    if ($sucursalModel->deleteSucursal($sucursalId)) {
        $success = "Sucursal eliminada correctamente";
    } else {
        $error = "Error al eliminar la sucursal";
    }
}

// Parámetros de búsqueda y paginación
$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$ciudad_filter = $_GET['ciudad_filter'] ?? '';
$provincia_filter = $_GET['provincia_filter'] ?? '';
$limit = 10;

// Obtener sucursales y total para paginación
$sucursales = $sucursalModel->getAllSucursalesForAdmin($page, $limit, $search, $ciudad_filter, $provincia_filter);
$totalSucursales = $sucursalModel->countSucursales($search, $ciudad_filter, $provincia_filter);
$totalPages = ceil($totalSucursales / $limit);

// Obtener ciudades y provincias para filtros
$ciudades = $sucursalModel->getCiudades();
$provincias = $sucursalModel->getProvincias();

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
                        <i class="fas fa-building"></i> Gestión de Sucursales
                    </h2>
                    <p class="text-muted mb-0">Administrar sucursales del sistema</p>
                </div>
                <div>
                    <a href="index.php?action=admin/sucursales/create" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nueva Sucursal
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
                        <input type="hidden" name="action" value="admin/sucursales">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Buscar</label>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Nombre, dirección, teléfono..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Filtrar por Ciudad</label>
                                <select class="form-select" name="ciudad_filter">
                                    <option value="">Todas las ciudades</option>
                                    <?php foreach ($ciudades as $ciudad): ?>
                                        <option value="<?php echo $ciudad['ciudad']; ?>" 
                                                <?php echo $ciudad_filter == $ciudad['ciudad'] ? 'selected' : ''; ?>>
                                            <?php echo $ciudad['ciudad']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Filtrar por Provincia</label>
                                <select class="form-select" name="provincia_filter">
                                    <option value="">Todas las provincias</option>
                                    <?php foreach ($provincias as $provincia): ?>
                                        <option value="<?php echo $provincia['provincia']; ?>" 
                                                <?php echo $provincia_filter == $provincia['provincia'] ? 'selected' : ''; ?>>
                                            <?php echo $provincia['provincia']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                    <a href="index.php?action=admin/sucursales" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de sucursales -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Lista de Sucursales 
                        <span class="badge bg-primary"><?php echo $totalSucursales; ?></span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($sucursales)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Sucursal</th>
                                        <th>Ubicación</th>
                                        <th>Contacto</th>
                                        <th>Especialidades</th>
                                        <th>Estado</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sucursales as $sucursal): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center me-3">
                                                        <i class="fas fa-building text-white"></i>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($sucursal['nombre_sucursal']); ?></strong>
                                                        <?php if ($sucursal['codigo_postal']): ?>
                                                            <br><small class="text-muted">CP: <?php echo $sucursal['codigo_postal']; ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <i class="fas fa-map-marker-alt text-muted me-1"></i>
                                                    <?php echo htmlspecialchars($sucursal['direccion']); ?>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo $sucursal['ciudad']; ?>
                                                    <?php if ($sucursal['provincia']): ?>
                                                        , <?php echo $sucursal['provincia']; ?>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($sucursal['telefono']): ?>
                                                    <div>
                                                        <i class="fas fa-phone text-muted me-1"></i>
                                                        <?php echo htmlspecialchars($sucursal['telefono']); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($sucursal['email']): ?>
                                                    <div>
                                                        <i class="fas fa-envelope text-muted me-1"></i>
                                                        <a href="mailto:<?php echo $sucursal['email']; ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($sucursal['email']); ?>
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo $sucursal['total_especialidades']; ?> especialidades
                                                </span>
                                                <?php if ($sucursal['total_medicos'] > 0): ?>
                                                    <br><small class="text-muted">
                                                        <?php echo $sucursal['total_medicos']; ?> médicos
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($sucursal['activo'] == 1): ?>
                                                    <span class="badge bg-success">Activa</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactiva</span>
                                                <?php endif; ?>
                                                <br><small class="text-muted">
                                                    Creada: <?php echo date('d/m/Y', strtotime($sucursal['fecha_creacion'])); ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group" role="group">
                                                    <a href="index.php?action=admin/sucursales/view&id=<?php echo $sucursal['id_sucursal']; ?>" 
                                                       class="btn btn-sm btn-outline-info" title="Ver detalles">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="index.php?action=admin/sucursales/edit&id=<?php echo $sucursal['id_sucursal']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($sucursal['total_medicos'] == 0): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="confirmDelete(<?php echo $sucursal['id_sucursal']; ?>, '<?php echo htmlspecialchars($sucursal['nombre_sucursal']); ?>')"
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
                            <i class="fas fa-building fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No se encontraron sucursales</h5>
                            <p class="text-muted">No hay sucursales que coincidan con los criterios de búsqueda.</p>
                            <a href="index.php?action=admin/sucursales/create" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Crear Primera Sucursal
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
                                    <?php echo min($page * $limit, $totalSucursales); ?> de <?php echo $totalSucursales; ?> sucursales
                                </small>
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?action=admin/sucursales&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&ciudad_filter=<?php echo $ciudad_filter; ?>&provincia_filter=<?php echo $provincia_filter; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?action=admin/sucursales&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&ciudad_filter=<?php echo $ciudad_filter; ?>&provincia_filter=<?php echo $provincia_filter; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?action=admin/sucursales&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&ciudad_filter=<?php echo $ciudad_filter; ?>&provincia_filter=<?php echo $provincia_filter; ?>">
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
                <p>¿Está seguro que desea eliminar la sucursal <strong id="sucursalName"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i>
                    <strong>Nota:</strong> Esta acción desactivará la sucursal y afectará las citas y horarios asociados.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="sucursal_id" id="sucursalIdToDelete">
                    <button type="submit" name="delete_sucursal" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Eliminar Sucursal
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
    function confirmDelete(sucursalId, sucursalName) {
        document.getElementById('sucursalName').textContent = sucursalName;
        document.getElementById('sucursalIdToDelete').value = sucursalId;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>

</body>
</html>