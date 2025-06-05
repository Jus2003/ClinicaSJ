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

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-primary">
                        <i class="fas fa-user-shield"></i> Gestión de Roles y Permisos
                    </h2>
                    <p class="text-muted mb-0">Administrar roles del sistema y sus permisos</p>
                </div>
                <div>
                    <?php if ($editingRole): ?>
                        <a href="index.php?action=admin/roles" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-times"></i> Cancelar Edición
                        </a>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary" onclick="showCreateForm()">
                            <i class="fas fa-plus"></i> Nuevo Rol
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Mensajes -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Lista de Roles -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-list"></i> Roles del Sistema
                                </h5>
                                <div class="d-flex gap-2">
                                    <input type="text" class="form-control form-control-sm" 
                                           placeholder="Buscar roles..." 
                                           value="<?php echo htmlspecialchars($search); ?>"
                                           onchange="searchRoles(this.value)">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" 
                                            onclick="showCloneModal()" title="Clonar permisos">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($roles as $role): ?>
                                    <div class="list-group-item <?php echo $editingRole && $editingRole['id_rol'] == $role['id_rol'] ? 'active' : ''; ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center">
                                                    <h6 class="mb-1 <?php echo $editingRole && $editingRole['id_rol'] == $role['id_rol'] ? 'text-white' : ''; ?>">
                                                        <?php echo htmlspecialchars($role['nombre_rol']); ?>
                                                        <?php if ($role['activo'] == 0): ?>
                                                            <span class="badge bg-secondary ms-1">Inactivo</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                </div>
                                                <?php if ($role['descripcion']): ?>
                                                    <p class="mb-1 small <?php echo $editingRole && $editingRole['id_rol'] == $role['id_rol'] ? 'text-white-50' : 'text-muted'; ?>">
                                                        <?php echo htmlspecialchars($role['descripcion']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <small class="<?php echo $editingRole && $editingRole['id_rol'] == $role['id_rol'] ? 'text-white-50' : 'text-muted'; ?>">
                                                    <i class="fas fa-users"></i> <?php echo $role['usuarios_activos']; ?> usuarios asignados
                                                </small>
                                            </div>
                                            <div class="btn-group-vertical btn-group-sm">
                                                <a href="index.php?action=admin/roles&edit=<?php echo $role['id_rol']; ?>" 
                                                   class="btn btn-outline-primary btn-sm" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($role['usuarios_activos'] == 0 && $role['activo'] == 1): ?>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" 
                                                            onclick="confirmDelete(<?php echo $role['id_rol']; ?>, '<?php echo htmlspecialchars($role['nombre_rol']); ?>')"
                                                            title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario y Permisos -->
                <div class="col-lg-7">
                    <div id="createForm" class="card border-0 shadow-sm" style="display: <?php echo $editingRole ? 'block' : 'none'; ?>">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-<?php echo $editingRole ? 'edit' : 'plus'; ?>"></i> 
                                <?php echo $editingRole ? 'Editar Rol: ' . htmlspecialchars($editingRole['nombre_rol']) : 'Crear Nuevo Rol'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="roleForm">
                                <input type="hidden" name="action" value="<?php echo $editingRole ? 'update' : 'create'; ?>">
                                <?php if ($editingRole): ?>
                                    <input type="hidden" name="role_id" value="<?php echo $editingRole['id_rol']; ?>">
                                <?php endif; ?>

                                <!-- Datos básicos del rol -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Nombre del Rol <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="nombre_rol" 
                                               value="<?php echo $editingRole ? htmlspecialchars($editingRole['nombre_rol']) : ''; ?>" 
                                               required placeholder="Ej: Editor de Contenido">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Descripción</label>
                                        <input type="text" class="form-control" name="descripcion" 
                                               value="<?php echo $editingRole ? htmlspecialchars($editingRole['descripcion']) : ''; ?>" 
                                               placeholder="Descripción opcional del rol">
                                    </div>
                                </div>

                                <!-- Matriz de Permisos -->
                                <div class="border rounded p-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">
                                            <i class="fas fa-shield-alt"></i> Permisos del Rol
                                        </h6>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-success" onclick="selectAllPermissions()">
                                                <i class="fas fa-check-double"></i> Seleccionar Todo
                                            </button>
                                            <button type="button" class="btn btn-outline-warning" onclick="clearAllPermissions()">
                                                <i class="fas fa-times"></i> Limpiar Todo
                                            </button>
                                        </div>
                                    </div>

                                    <div class="permissions-matrix">
                                        <?php foreach ($menuStructure as $menu): ?>
                                            <div class="menu-section mb-3">
                                                <div class="menu-header bg-white border rounded p-2 mb-2">
                                                    <div class="d-flex align-items-center">
                                                        <input type="checkbox" class="form-check-input menu-checkbox me-2" 
                                                               id="menu_<?php echo $menu['id_menu']; ?>"
                                                               onchange="toggleMenu(<?php echo $menu['id_menu']; ?>)">
                                                        <label class="form-check-label fw-bold flex-grow-1" 
                                                               for="menu_<?php echo $menu['id_menu']; ?>">
                                                            <i class="<?php echo $menu['icono']; ?>"></i> 
                                                            <?php echo $menu['nombre_menu']; ?>
                                                        </label>
                                                        <small class="text-muted">
                                                            <span id="count_<?php echo $menu['id_menu']; ?>">0</span>/<?php echo count($menu['submenus']); ?> seleccionados
                                                        </small>
                                                    </div>
                                                </div>

                                                <div class="submenus-container ms-3">
                                                    <?php foreach ($menu['submenus'] as $submenu): ?>
                                                        <div class="submenu-item border rounded p-2 mb-2 bg-white">
                                                            <div class="row align-items-center">
                                                                <div class="col-md-5">
                                                                    <div class="d-flex align-items-center">
                                                                        <input type="checkbox" 
                                                                               class="form-check-input submenu-checkbox me-2" 
                                                                               id="submenu_<?php echo $submenu['id_submenu']; ?>"
                                                                               data-menu="<?php echo $menu['id_menu']; ?>"
                                                                               onchange="toggleSubmenu(<?php echo $submenu['id_submenu']; ?>)"
                                                                               <?php echo $submenu['permisos']['tiene_permiso'] ? 'checked' : ''; ?>>
                                                                        <label class="form-check-label" 
                                                                               for="submenu_<?php echo $submenu['id_submenu']; ?>">
                                                                            <i class="<?php echo $submenu['icono']; ?>"></i> 
                                                                            <?php echo $submenu['nombre_submenu']; ?>
                                                                        </label>
                                                                    </div>
                                                                    <small class="text-muted d-block">
                                                                        <?php echo $submenu['uri_submenu']; ?>
                                                                    </small>
                                                                </div>
                                                                <div class="col-md-7">
                                                                    <div class="crud-permissions" 
                                                                         id="crud_<?php echo $submenu['id_submenu']; ?>"
                                                                         style="display: <?php echo $submenu['permisos']['tiene_permiso'] ? 'block' : 'none'; ?>">
                                                                        <div class="btn-group w-100" role="group">
                                                                            <input type="checkbox" class="btn-check" 
                                                                                   id="crear_<?php echo $submenu['id_submenu']; ?>"
                                                                                   name="permisos[<?php echo $submenu['id_submenu']; ?>][crear]" 
                                                                                   value="1"
                                                                                   <?php echo $submenu['permisos']['crear'] ? 'checked' : ''; ?>>
                                                                            <label class="btn btn-outline-success btn-sm" 
                                                                                   for="crear_<?php echo $submenu['id_submenu']; ?>">
                                                                                <i class="fas fa-plus"></i> C
                                                                            </label>

                                                                            <input type="checkbox" class="btn-check" 
                                                                                   id="leer_<?php echo $submenu['id_submenu']; ?>"
                                                                                   name="permisos[<?php echo $submenu['id_submenu']; ?>][leer]" 
                                                                                   value="1"
                                                                                   <?php echo $submenu['permisos']['leer'] ? 'checked' : ''; ?>>
                                                                            <label class="btn btn-outline-info btn-sm" 
                                                                                   for="leer_<?php echo $submenu['id_submenu']; ?>">
                                                                                <i class="fas fa-eye"></i> R
                                                                            </label>

                                                                            <input type="checkbox" class="btn-check" 
                                                                                   id="editar_<?php echo $submenu['id_submenu']; ?>"
                                                                                   name="permisos[<?php echo $submenu['id_submenu']; ?>][editar]" 
                                                                                   value="1"
                                                                                   <?php echo $submenu['permisos']['editar'] ? 'checked' : ''; ?>>
                                                                            <label class="btn btn-outline-warning btn-sm" 
                                                                                   for="editar_<?php echo $submenu['id_submenu']; ?>">
                                                                                <i class="fas fa-edit"></i> U
                                                                            </label>

                                                                            <input type="checkbox" class="btn-check" 
                                                                                   id="eliminar_<?php echo $submenu['id_submenu']; ?>"
                                                                                   name="permisos[<?php echo $submenu['id_submenu']; ?>][eliminar]" 
                                                                                   value="1"
                                                                                   <?php echo $submenu['permisos']['eliminar'] ? 'checked' : ''; ?>>
                                                                            <label class="btn btn-outline-danger btn-sm" 
                                                                                   for="eliminar_<?php echo $submenu['id_submenu']; ?>">
                                                                                <i class="fas fa-trash"></i> D
                                                                            </label>
                                                                        </div>
                                                                        <small class="text-muted d-block text-center mt-1">
                                                                            Crear | Leer | Editar | Eliminar
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

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="button" class="btn btn-secondary" onclick="cancelForm()">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> 
                                        <?php echo $editingRole ? 'Actualizar Rol' : 'Crear Rol'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Mensaje cuando no hay formulario activo -->
                    <div id="noFormMessage" class="text-center py-5" style="display: <?php echo $editingRole ? 'none' : 'block'; ?>">
                        <i class="fas fa-shield-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Gestión de Roles y Permisos</h5>
                        <p class="text-muted">Seleccione un rol de la lista para editarlo o cree un nuevo rol.</p>
                        <button type="button" class="btn btn-primary" onclick="showCreateForm()">
                            <i class="fas fa-plus"></i> Crear Nuevo Rol
                        </button>
                    </div>
                </div>
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
                <p>¿Está seguro que desea eliminar el rol <strong id="roleName"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i>
                    <strong>Nota:</strong> Esta acción desactivará el rol y todos sus permisos.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="role_id" id="roleIdToDelete">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Eliminar Rol
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal para clonar permisos -->
<div class="modal fade" id="cloneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-copy"></i> Clonar Permisos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="clone">
                    <div class="mb-3">
                        <label class="form-label">Copiar permisos desde:</label>
                        <select class="form-select" name="from_role_id" required>
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
                    <div class="mb-3">
                        <label class="form-label">Hacia el rol:</label>
                        <select class="form-select" name="to_role_id" required>
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
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Esto reemplazará todos los permisos del rol destino con los del rol origen.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-copy"></i> Clonar Permisos
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .permissions-matrix {
        max-height: 600px;
        overflow-y: auto;
    }
    .menu-section {
        border-left: 3px solid #dee2e6;
        padding-left: 10px;
    }
    .submenu-item {
        transition: background-color 0.2s;
    }
    .submenu-item:hover {
        background-color: #f8f9fa !important;
    }
    .crud-permissions .btn {
        font-size: 0.75rem;
    }
    .list-group-item.active {
        background-color: #667eea;
        border-color: #667eea;
    }
</style>

<script>
    // Inicializar contadores al cargar la página
    document.addEventListener('DOMContentLoaded', function() {
        updateAllMenuCounters();
    });

    function showCreateForm() {
        document.getElementById('createForm').style.display = 'block';
        document.getElementById('noFormMessage').style.display = 'none';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function cancelForm() {
        window.location.href = 'index.php?action=admin/roles';
    }

    function searchRoles(query) {
        window.location.href = `index.php?action=admin/roles&search=${encodeURIComponent(query)}`;
    }

    function confirmDelete(roleId, roleName) {
        document.getElementById('roleName').textContent = roleName;
        document.getElementById('roleIdToDelete').value = roleId;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    function showCloneModal() {
        new bootstrap.Modal(document.getElementById('cloneModal')).show();
    }

    // Funciones para manejo de permisos
    function toggleMenu(menuId) {
        const menuCheckbox = document.getElementById(`menu_${menuId}`);
        const submenuCheckboxes = document.querySelectorAll(`input[data-menu="${menuId}"]`);
        
        submenuCheckboxes.forEach(checkbox => {
            checkbox.checked = menuCheckbox.checked;
            toggleSubmenu(checkbox.id.replace('submenu_', ''));
        });
        
        updateMenuCounter(menuId);
    }

    function toggleSubmenu(submenuId) {
        const submenuCheckbox = document.getElementById(`submenu_${submenuId}`);
        const crudContainer = document.getElementById(`crud_${submenuId}`);
        
        if (submenuCheckbox.checked) {
            crudContainer.style.display = 'block';
            // Auto-seleccionar permiso de lectura como mínimo
            document.getElementById(`leer_${submenuId}`).checked = true;
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
        document.getElementById(`count_${menuId}`).textContent = checkedCount;
        
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
    }

    function updateAllMenuCounters() {
        const menuCheckboxes = document.querySelectorAll('.menu-checkbox');
        menuCheckboxes.forEach(checkbox => {
            const menuId = checkbox.id.replace('menu_', '');
            updateMenuCounter(menuId);
        });
    }

    function selectAllPermissions() {
        document.querySelectorAll('.submenu-checkbox').forEach(checkbox => {
            checkbox.checked = true;
            toggleSubmenu(checkbox.id.replace('submenu_', ''));
        });
        
        // Seleccionar todos los permisos CRUD
        document.querySelectorAll('.crud-permissions input[type="checkbox"]').forEach(cb => {
            cb.checked = true;
        });
        
        updateAllMenuCounters();
    }

    function clearAllPermissions() {
        document.querySelectorAll('.submenu-checkbox').forEach(checkbox => {
            checkbox.checked = false;
            toggleSubmenu(checkbox.id.replace('submenu_', ''));
        });
        
        updateAllMenuCounters();
    }
</script>

</body>
</html>