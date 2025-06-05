<?php
require_once 'models/Role.php';

// Verificar autenticación y permisos de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php?action=dashboard');
    exit;
}

$roleModel = new Role();
$error = '';
$success = '';
$editingRole = null;

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'create':
                $nombre = trim($_POST['nombre_rol']);
                $descripcion = trim($_POST['descripcion']);
                $permisos = $_POST['permisos'] ?? [];

                if (empty($nombre)) {
                    throw new Exception("El nombre del rol es obligatorio");
                }

                $roleId = $roleModel->createRole($nombre, $descripcion);

                // Guardar permisos si se especificaron
                if (!empty($permisos)) {
                    $roleModel->saveRolePermissions($roleId, $permisos);
                }

                $success = "Rol '{$nombre}' creado exitosamente";
                break;

            case 'update':
                $roleId = $_POST['role_id'];
                $nombre = trim($_POST['nombre_rol']);
                $descripcion = trim($_POST['descripcion']);
                $permisos = $_POST['permisos'] ?? [];

                if (empty($nombre)) {
                    throw new Exception("El nombre del rol es obligatorio");
                }

                $roleModel->updateRole($roleId, $nombre, $descripcion);
                $roleModel->saveRolePermissions($roleId, $permisos);

                $success = "Rol '{$nombre}' actualizado exitosamente";
                break;

            case 'delete':
                $roleId = $_POST['role_id'];
                $role = $roleModel->getRoleById($roleId);
                $roleModel->deleteRole($roleId);
                $success = "Rol '{$role['nombre_rol']}' eliminado exitosamente";
                break;

            case 'clone':
                $fromRoleId = $_POST['from_role_id'];
                $toRoleId = $_POST['to_role_id'];
                $roleModel->clonePermissions($fromRoleId, $toRoleId);
                $success = "Permisos clonados exitosamente";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener rol para editar si se especifica
if (isset($_GET['edit'])) {
    $editingRole = $roleModel->getRoleById($_GET['edit']);
    if (!$editingRole) {
        header('Location: index.php?action=admin/roles');
        exit;
    }
}

// Obtener datos para la vista
$search = $_GET['search'] ?? '';
$roles = $roleModel->getAllRolesForAdmin($search);
$menuStructure = $roleModel->getMenuStructureWithPermissions($editingRole['id_rol'] ?? null);

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container-fluid mt-4 mb-5">
    <div class="row">
        <div class="col-12">
            <!-- Header mejorado -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-primary fw-bold mb-2">
                        <i class="fas fa-user-shield me-2"></i>Gestión de Roles y Permisos
                    </h2>
                    <p class="text-muted mb-0 fs-6">Administrar roles del sistema y sus permisos de acceso</p>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($editingRole): ?>
                        <a href="index.php?action=admin/roles" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Volver a la Lista
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary shadow-sm" onclick="showCreateForm()">
                            <i class="fas fa-plus me-2"></i>Nuevo Rol
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mensajes mejorados -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle me-3 fs-5"></i>
                        <div>
                            <strong>¡Éxito!</strong> <?php echo $success; ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-3 fs-5"></i>
                        <div>
                            <strong>Error:</strong> <?php echo $error; ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Lista de Roles mejorada -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-gradient-primary text-white border-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-semibold">
                                    <i class="fas fa-list me-2"></i>Roles del Sistema
                                </h5>
                                <div class="d-flex gap-2">
                                    <div class="input-group input-group-sm" style="width: 200px;">
                                        <input type="text" class="form-control bg-white border-0" 
                                               placeholder="Buscar roles..." 
                                               value="<?php echo htmlspecialchars($search); ?>"
                                               onchange="searchRoles(this.value)">
                                        <span class="input-group-text bg-white border-0">
                                            <i class="fas fa-search text-muted"></i>
                                        </span>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light" 
                                            onclick="showCloneModal()" 
                                            title="Clonar permisos"
                                            data-bs-toggle="tooltip">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php if (empty($roles)): ?>
                                    <div class="list-group-item text-center py-4">
                                        <i class="fas fa-search text-muted fa-2x mb-3"></i>
                                        <p class="text-muted mb-0">No se encontraron roles</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($roles as $role): ?>
                                        <div class="list-group-item list-group-item-action border-0 <?php echo $editingRole && $editingRole['id_rol'] == $role['id_rol'] ? 'active' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <h6 class="mb-0 fw-semibold <?php echo $editingRole && $editingRole['id_rol'] == $role['id_rol'] ? 'text-white' : 'text-dark'; ?>">
                                                            <?php echo htmlspecialchars($role['nombre_rol']); ?>
                                                        </h6>
                                                        <?php if ($role['activo'] == 0): ?>
                                                            <span class="badge bg-secondary ms-2 fs-7">Inactivo</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($role['descripcion']): ?>
                                                        <p class="mb-2 small <?php echo $editingRole && $editingRole['id_rol'] == $role['id_rol'] ? 'text-white-50' : 'text-muted'; ?>">
                                                            <?php echo htmlspecialchars($role['descripcion']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <div class="d-flex align-items-center">
                                                        <small class="<?php echo $editingRole && $editingRole['id_rol'] == $role['id_rol'] ? 'text-white-50' : 'text-muted'; ?>">
                                                            <i class="fas fa-users me-1"></i>
                                                            <?php echo $role['usuarios_activos']; ?> usuario<?php echo $role['usuarios_activos'] != 1 ? 's' : ''; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="d-flex flex-column gap-1">
                                                    <a href="index.php?action=admin/roles&edit=<?php echo $role['id_rol']; ?>" 
                                                       class="btn btn-outline-primary btn-sm" 
                                                       title="Editar rol"
                                                       data-bs-toggle="tooltip">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($role['usuarios_activos'] == 0 && $role['activo'] == 1): ?>
                                                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                                                onclick="confirmDelete(<?php echo $role['id_rol']; ?>, '<?php echo htmlspecialchars($role['nombre_rol']); ?>')"
                                                                title="Eliminar rol"
                                                                data-bs-toggle="tooltip">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario y Permisos mejorados -->
                <div class="col-lg-7">
                    <div id="createForm" class="card border-0 shadow-sm" style="display: <?php echo $editingRole ? 'block' : 'none'; ?>">
                        <div class="card-header bg-gradient-primary text-white border-0">
                            <h5 class="mb-0 fw-semibold">
                                <i class="fas fa-<?php echo $editingRole ? 'edit' : 'plus'; ?> me-2"></i>
                                <?php echo $editingRole ? 'Editar Rol: ' . htmlspecialchars($editingRole['nombre_rol']) : 'Crear Nuevo Rol'; ?>
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" id="roleForm">
                                <input type="hidden" name="action" value="<?php echo $editingRole ? 'update' : 'create'; ?>">
                                <?php if ($editingRole): ?>
                                    <input type="hidden" name="role_id" value="<?php echo $editingRole['id_rol']; ?>">
                                <?php endif; ?>

                                <!-- Datos básicos del rol -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            Nombre del Rol <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control form-control-lg" name="nombre_rol" 
                                               value="<?php echo $editingRole ? htmlspecialchars($editingRole['nombre_rol']) : ''; ?>" 
                                               required placeholder="Ej: Editor de Contenido">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Descripción</label>
                                        <input type="text" class="form-control form-control-lg" name="descripcion" 
                                               value="<?php echo $editingRole ? htmlspecialchars($editingRole['descripcion']) : ''; ?>" 
                                               placeholder="Descripción opcional del rol">
                                    </div>
                                </div>

                                <!-- Matriz de Permisos mejorada -->
                                <div class="border rounded-3 p-4 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h6 class="mb-0 fw-semibold text-dark">
                                            <i class="fas fa-shield-alt me-2 text-primary"></i>Permisos del Rol
                                        </h6>
                                        <div class="btn-group btn-group-sm shadow-sm">
                                            <button type="button" class="btn btn-success" onclick="selectAllPermissions()">
                                                <i class="fas fa-check-double me-1"></i>Todo
                                            </button>
                                            <button type="button" class="btn btn-warning" onclick="clearAllPermissions()">
                                                <i class="fas fa-times me-1"></i>Limpiar
                                            </button>
                                        </div>
                                    </div>

                                    <div class="permissions-matrix">
                                        <?php foreach ($menuStructure as $menu): ?>
                                            <div class="menu-section mb-4">
                                                <div class="menu-header bg-white border rounded-3 p-3 mb-3 shadow-sm">
                                                    <div class="d-flex align-items-center">
                                                        <input type="checkbox" class="form-check-input menu-checkbox me-3" 
                                                               id="menu_<?php echo $menu['id_menu']; ?>"
                                                               onchange="toggleMenu(<?php echo $menu['id_menu']; ?>)">
                                                        <label class="form-check-label fw-bold flex-grow-1 text-primary" 
                                                               for="menu_<?php echo $menu['id_menu']; ?>">
                                                            <i class="<?php echo $menu['icono']; ?> me-2"></i>
                                                            <?php echo $menu['nombre_menu']; ?>
                                                        </label>
                                                        <div class="badge bg-primary rounded-pill">
                                                            <span id="count_<?php echo $menu['id_menu']; ?>">0</span>/<?php echo count($menu['submenus']); ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="submenus-container ms-4">
                                                    <?php foreach ($menu['submenus'] as $submenu): ?>
                                                        <div class="submenu-item bg-white border rounded-3 p-3 mb-3 shadow-sm hover-lift">
                                                            <div class="row align-items-center">
                                                                <div class="col-md-5">
                                                                    <div class="d-flex align-items-center mb-2">
                                                                        <input type="checkbox" 
                                                                               class="form-check-input submenu-checkbox me-3" 
                                                                               id="submenu_<?php echo $submenu['id_submenu']; ?>"
                                                                               data-menu="<?php echo $menu['id_menu']; ?>"
                                                                               onchange="toggleSubmenu(<?php echo $submenu['id_submenu']; ?>)"
                                                                               <?php echo $submenu['permisos']['tiene_permiso'] ? 'checked' : ''; ?>>
                                                                        <label class="form-check-label fw-semibold" 
                                                                               for="submenu_<?php echo $submenu['id_submenu']; ?>">
                                                                            <i class="<?php echo $submenu['icono']; ?> me-2 text-info"></i>
                                                                            <?php echo $submenu['nombre_submenu']; ?>
                                                                        </label>
                                                                    </div>
                                                                    <small class="text-muted d-block">
                                                                        <i class="fas fa-link me-1"></i>
                                                                        <?php echo $submenu['uri_submenu']; ?>
                                                                    </small>
                                                                </div>
                                                                <div class="col-md-7">
                                                                    <div class="crud-permissions" 
                                                                         id="crud_<?php echo $submenu['id_submenu']; ?>"
                                                                         style="display: <?php echo $submenu['permisos']['tiene_permiso'] ? 'block' : 'none'; ?>">
                                                                        <div class="btn-group w-100 shadow-sm" role="group">
                                                                            <input type="checkbox" class="btn-check" 
                                                                                   id="crear_<?php echo $submenu['id_submenu']; ?>"
                                                                                   name="permisos[<?php echo $submenu['id_submenu']; ?>][crear]" 
                                                                                   value="1"
                                                                                   <?php echo $submenu['permisos']['crear'] ? 'checked' : ''; ?>>
                                                                            <label class="btn btn-outline-success btn-sm fw-semibold" 
                                                                                   for="crear_<?php echo $submenu['id_submenu']; ?>"
                                                                                   data-bs-toggle="tooltip" title="Crear">
                                                                                <i class="fas fa-plus"></i>
                                                                            </label>

                                                                            <input type="checkbox" class="btn-check" 
                                                                                   id="leer_<?php echo $submenu['id_submenu']; ?>"
                                                                                   name="permisos[<?php echo $submenu['id_submenu']; ?>][leer]" 
                                                                                   value="1"
                                                                                   <?php echo $submenu['permisos']['leer'] ? 'checked' : ''; ?>>
                                                                            <label class="btn btn-outline-info btn-sm fw-semibold" 
                                                                                   for="leer_<?php echo $submenu['id_submenu']; ?>"
                                                                                   data-bs-toggle="tooltip" title="Leer">
                                                                                <i class="fas fa-eye"></i>
                                                                            </label>

                                                                            <input type="checkbox" class="btn-check" 
                                                                                   id="editar_<?php echo $submenu['id_submenu']; ?>"
                                                                                   name="permisos[<?php echo $submenu['id_submenu']; ?>][editar]" 
                                                                                   value="1"
                                                                                   <?php echo $submenu['permisos']['editar'] ? 'checked' : ''; ?>>
                                                                            <label class="btn btn-outline-warning btn-sm fw-semibold" 
                                                                                   for="editar_<?php echo $submenu['id_submenu']; ?>"
                                                                                   data-bs-toggle="tooltip" title="Editar">
                                                                                <i class="fas fa-edit"></i>
                                                                            </label>

                                                                            <input type="checkbox" class="btn-check" 
                                                                                   id="eliminar_<?php echo $submenu['id_submenu']; ?>"
                                                                                   name="permisos[<?php echo $submenu['id_submenu']; ?>][eliminar]" 
                                                                                   value="1"
                                                                                   <?php echo $submenu['permisos']['eliminar'] ? 'checked' : ''; ?>>
                                                                            <label class="btn btn-outline-danger btn-sm fw-semibold" 
                                                                                   for="eliminar_<?php echo $submenu['id_submenu']; ?>"
                                                                                   data-bs-toggle="tooltip" title="Eliminar">
                                                                                <i class="fas fa-trash"></i>
                                                                            </label>
                                                                        </div>
                                                                        <small class="text-muted d-block text-center mt-2 fw-medium">
                                                                            C • R • U • D
                                                                        </small>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-3 mt-4">
                                    <button type="button" class="btn btn-secondary btn-lg" onclick="cancelForm()">
                                        <i class="fas fa-times me-2"></i>Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-primary btn-lg shadow-sm">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $editingRole ? 'Actualizar Rol' : 'Crear Rol'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Mensaje cuando no hay formulario activo mejorado -->
                    <div id="noFormMessage" class="text-center py-5" style="display: <?php echo $editingRole ? 'none' : 'block'; ?>">
                        <div class="mb-4">
                            <i class="fas fa-shield-alt fa-4x text-primary opacity-75 mb-3"></i>
                            <h4 class="text-dark fw-bold mb-2">Gestión de Roles y Permisos</h4>
                            <p class="text-muted fs-5 mb-4">Seleccione un rol de la lista para editarlo o cree un nuevo rol para comenzar.</p>
                        </div>
                        <div class="d-flex justify-content-center gap-3">
                            <button type="button" class="btn btn-primary btn-lg shadow-sm" onclick="showCreateForm()">
                                <i class="fas fa-plus me-2"></i>Crear Nuevo Rol
                            </button>
                            <button type="button" class="btn btn-outline-info btn-lg" onclick="showCloneModal()">
                                <i class="fas fa-copy me-2"></i>Clonar Permisos
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación de eliminación mejorado -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title fw-semibold">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3">
                    <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                    <p class="fs-5 mb-3">¿Está seguro que desea eliminar el rol <strong id="roleName" class="text-danger"></strong>?</p>
                </div>
                <div class="alert alert-warning border-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Nota:</strong> Esta acción desactivará el rol y todos sus permisos asociados.
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancelar
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="role_id" id="roleIdToDelete">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Eliminar Rol
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para clonar permisos mejorado -->
<div class="modal fade" id="cloneModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white border-0">
                <h5 class="modal-title fw-semibold">
                    <i class="fas fa-copy me-2"></i>Clonar Permisos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="clone">
                    <div class="text-center mb-4">
                        <i class="fas fa-clone fa-3x text-info mb-3"></i>
                        <p class="text-muted">Copie todos los permisos de un rol a otro</p>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Desde:</label>
                            <select class="form-select form-select-lg" name="from_role_id" required>
                                <option value="">Seleccionar rol origen...</option>
                                <?php foreach ($roles as $role): ?>
                                    <?php if ($role['activo'] == 1): ?>
                                        <option value="<?php echo $role['id_rol']; ?>">
                                            <?php echo htmlspecialchars($role['nombre_rol']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Hacia:</label>
                            <select class="form-select form-select-lg" name="to_role_id" required>
                                <option value="">Seleccionar rol destino...</option>
                                <?php foreach ($roles as $role): ?>
                                    <?php if ($role['activo'] == 1): ?>
                                        <option value="<?php echo $role['id_rol']; ?>">
                                            <?php echo htmlspecialchars($role['nombre_rol']); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-info border-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Esto reemplazará todos los permisos del rol destino con los del rol origen.
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-copy me-2"></i>Clonar Permisos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Gradientes mejorados */
    .bg-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    /* Efectos hover y transiciones */
    .hover-lift {
        transition: all 0.3s ease;
    }

    .hover-lift:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
    }

    .list-group-item-action:hover {
        background-color: #f8f9fa;
        transform: translateX(3px);
        transition: all 0.2s ease;
    }

    .list-group-item.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    /* Permisos matrix */
    .permissions-matrix {
        max-height: 650px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #dee2e6 #f8f9fa;
    }

    .permissions-matrix::-webkit-scrollbar {
        width: 6px;
    }

    .permissions-matrix::-webkit-scrollbar-track {
        background: #f8f9fa;
        border-radius: 3px;
    }

    .permissions-matrix::-webkit-scrollbar-thumb {
        background: #dee2e6;
        border-radius: 3px;
    }

    .menu-section {
        border-left: 4px solid #667eea;
        padding-left: 15px;
        margin-left: 10px;
    }

    .submenu-item {
        transition: all 0.3s ease;
        border: 1px solid #e9ecef !important;
    }

    .submenu-item:hover {
        border-color: #667eea !important;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.1);
    }

    /* Botones CRUD mejorados */
    .crud-permissions .btn {
        font-size: 0.8rem;
        padding: 0.375rem 0.5rem;
        font-weight: 600;
        transition: all 0.2s ease;
    }

    .crud-permissions .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }

    /* Badges y elementos pequeños */
    .badge {
        font-size: 0.75rem;
        font-weight: 600;
    }

    .fs-7 {
        font-size: 0.85rem;
    }

    /* Cards con mejor sombra */
    .card {
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        transition: all 0.3s ease;
    }

    .shadow-sm {
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06) !important;
    }

    /* Formularios mejorados */
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    /* Checkboxes personalizados */
    .form-check-input:checked {
        background-color: #667eea;
        border-color: #667eea;
    }

    .form-check-input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
    }

    /* Alertas mejoradas */
    .alert {
        border-radius: 0.75rem;
        padding: 1rem 1.25rem;
    }

    /* Botones con mejor hover */
    .btn {
        transition: all 0.2s ease;
        font-weight: 500;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
    }

    /* Tooltips */
    .tooltip {
        font-size: 0.8rem;
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .container-fluid {
            padding-left: 15px;
            padding-right: 15px;
        }

        .crud-permissions .btn {
            font-size: 0.7rem;
            padding: 0.25rem 0.4rem;
        }

        .permissions-matrix {
            max-height: 400px;
        }

        .menu-section {
            margin-left: 5px;
            padding-left: 10px;
        }
    }

    /* Loading animation para botones */
    .btn-loading {
        position: relative;
        pointer-events: none;
    }

    .btn-loading::after {
        content: "";
        position: absolute;
        width: 16px;
        height: 16px;
        top: 50%;
        left: 50%;
        margin-left: -8px;
        margin-top: -8px;
        border: 2px solid transparent;
        border-top-color: #ffffff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }
        100% {
            transform: rotate(360deg);
        }
    }
</style>

<script>
    // Inicializar tooltips y contadores al cargar la página
    document.addEventListener('DOMContentLoaded', function () {
        // Inicializar tooltips de Bootstrap
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        updateAllMenuCounters();

        // Añadir efecto de loading a formularios
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function (e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.classList.add('btn-loading');
                    submitBtn.disabled = true;
                }
            });
        });
    });

    function showCreateForm() {
        document.getElementById('createForm').style.display = 'block';
        document.getElementById('noFormMessage').style.display = 'none';

        // Smooth scroll mejorado
        document.getElementById('createForm').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });

        // Focus en el primer input
        setTimeout(() => {
            const firstInput = document.querySelector('#createForm input[name="nombre_rol"]');
            if (firstInput)
                firstInput.focus();
        }, 500);
    }

    function cancelForm() {
        // Confirmar si hay cambios
        const form = document.getElementById('roleForm');
        const formData = new FormData(form);
        let hasChanges = false;

        for (let [key, value] of formData.entries()) {
            if (value && key !== 'action' && key !== 'role_id') {
                hasChanges = true;
                break;
            }
        }

        if (hasChanges) {
            if (confirm('¿Está seguro que desea cancelar? Se perderán los cambios no guardados.')) {
                window.location.href = 'index.php?action=admin/roles';
            }
        } else {
            window.location.href = 'index.php?action=admin/roles';
        }
    }

    function searchRoles(query) {
        // Debounce para búsqueda
        clearTimeout(window.searchTimeout);
        window.searchTimeout = setTimeout(() => {
            window.location.href = `index.php?action=admin/roles&search=${encodeURIComponent(query)}`;
        }, 300);
    }

    function confirmDelete(roleId, roleName) {
        document.getElementById('roleName').textContent = roleName;
        document.getElementById('roleIdToDelete').value = roleId;

        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }

    function showCloneModal() {
        const modal = new bootstrap.Modal(document.getElementById('cloneModal'));
        modal.show();
    }

    // Funciones para manejo de permisos mejoradas
    function toggleMenu(menuId) {
        const menuCheckbox = document.getElementById(`menu_${menuId}`);
        const submenuCheckboxes = document.querySelectorAll(`input[data-menu="${menuId}"]`);

        submenuCheckboxes.forEach(checkbox => {
            checkbox.checked = menuCheckbox.checked;
            toggleSubmenu(checkbox.id.replace('submenu_', ''));
        });

        updateMenuCounter(menuId);

        // Efecto visual
        const menuSection = menuCheckbox.closest('.menu-section');
        if (menuCheckbox.checked) {
            menuSection.style.borderLeftColor = '#28a745';
        } else {
            menuSection.style.borderLeftColor = '#667eea';
        }
    }

    function toggleSubmenu(submenuId) {
        const submenuCheckbox = document.getElementById(`submenu_${submenuId}`);
        const crudContainer = document.getElementById(`crud_${submenuId}`);

        if (submenuCheckbox.checked) {
            crudContainer.style.display = 'block';
            // Auto-seleccionar permiso de lectura como mínimo
            document.getElementById(`leer_${submenuId}`).checked = true;

            // Efecto de aparición suave
            crudContainer.style.opacity = '0';
            crudContainer.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                crudContainer.style.transition = 'all 0.3s ease';
                crudContainer.style.opacity = '1';
                crudContainer.style.transform = 'translateY(0)';
            }, 10);
        } else {
            crudContainer.style.display = 'none';
            // Desmarcar todos los permisos CRUD
            ['crear', 'leer', 'editar', 'eliminar'].forEach(perm => {
                document.getElementById(`${perm}_${submenuId}`).checked = false;
            });
        }

        // Actualizar contador del menú padre
        const menuId = submenuCheckbox.getAttribute('data-menu');
        updateMenuCounter(menuId);
    }

    function updateMenuCounter(menuId) {
        const submenuCheckboxes = document.querySelectorAll(`input[data-menu="${menuId}"]`);
        const checkedCount = Array.from(submenuCheckboxes).filter(cb => cb.checked).length;
        const counterElement = document.getElementById(`count_${menuId}`);

        // Animación del contador
        counterElement.style.transform = 'scale(1.2)';
        counterElement.textContent = checkedCount;
        setTimeout(() => {
            counterElement.style.transform = 'scale(1)';
        }, 150);

        // Actualizar estado del checkbox del menú
        const menuCheckbox = document.getElementById(`menu_${menuId}`);
        if (checkedCount === 0) {
            menuCheckbox.checked = false;
            menuCheckbox.indeterminate = false;
        } else if (checkedCount === submenuCheckboxes.length) {
            menuCheckbox.checked = true;
            menuCheckbox.indeterminate = false;
        } else {
            menuCheckbox.checked = false;
            menuCheckbox.indeterminate = true;
        }

        // Cambiar color del badge
        const badge = counterElement.closest('.badge');
        if (checkedCount === 0) {
            badge.className = 'badge bg-secondary rounded-pill';
        } else if (checkedCount === submenuCheckboxes.length) {
            badge.className = 'badge bg-success rounded-pill';
        } else {
            badge.className = 'badge bg-warning rounded-pill';
        }
    }

    function updateAllMenuCounters() {
        const menuCheckboxes = document.querySelectorAll('.menu-checkbox');
        menuCheckboxes.forEach(checkbox => {
            const menuId = checkbox.id.replace('menu_', '');
            updateMenuCounter(menuId);
        });
    }

    function selectAllPermissions() {
        // Mostrar confirmación
        if (!confirm('¿Está seguro que desea seleccionar todos los permisos? Esto otorgará acceso completo al rol.')) {
            return;
        }

        // Animación de selección
        const buttons = document.querySelectorAll('.submenu-checkbox');
        buttons.forEach((checkbox, index) => {
            setTimeout(() => {
                checkbox.checked = true;
                toggleSubmenu(checkbox.id.replace('submenu_', ''));
            }, index * 50);
        });

        // Seleccionar todos los permisos CRUD después de un delay
        setTimeout(() => {
            document.querySelectorAll('.crud-permissions input[type="checkbox"]').forEach(cb => {
                cb.checked = true;
            });
            updateAllMenuCounters();
        }, buttons.length * 50 + 100);
    }

    function clearAllPermissions() {
        if (!confirm('¿Está seguro que desea limpiar todos los permisos?')) {
            return;
        }

        document.querySelectorAll('.submenu-checkbox').forEach(checkbox => {
            checkbox.checked = false;
            toggleSubmenu(checkbox.id.replace('submenu_', ''));
        });

        updateAllMenuCounters();
    }

    // Función para validar formulario antes del envío
    function validateForm() {
        const nombreRol = document.querySelector('input[name="nombre_rol"]').value.trim();

        if (!nombreRol) {
            alert('El nombre del rol es obligatorio');
            return false;
        }

        // Verificar si al menos un permiso está seleccionado
        const permisosSeleccionados = document.querySelectorAll('.submenu-checkbox:checked');
        if (permisosSeleccionados.length === 0) {
            if (!confirm('No ha seleccionado ningún permiso. ¿Desea continuar creando un rol sin permisos?')) {
                return false;
            }
        }

        return true;
    }

    // Añadir validación al formulario
    document.addEventListener('DOMContentLoaded', function () {
        const roleForm = document.getElementById('roleForm');
        if (roleForm) {
            roleForm.addEventListener('submit', function (e) {
                if (!validateForm()) {
                    e.preventDefault();
                }
            });
        }
    });
</script>

</body>
</html>