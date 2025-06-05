<?php

require_once 'config/database.php';

class Role {

    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // CRUD BÁSICO DE ROLES
    // Obtener todos los roles activos
    public function getAllRoles() {
        $sql = "SELECT * FROM roles WHERE activo = 1 ORDER BY nombre_rol";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Obtener todos los roles (incluyendo inactivos) para administración
    public function getAllRolesForAdmin($search = '') {
        $whereClause = "1=1";
        $params = [];

        if (!empty($search)) {
            $whereClause .= " AND (nombre_rol LIKE :search OR descripcion LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        $sql = "SELECT r.*, 
                       (SELECT COUNT(*) FROM usuarios u WHERE u.id_rol = r.id_rol AND u.activo = 1) as usuarios_activos
                FROM roles r 
                WHERE {$whereClause}
                ORDER BY r.activo DESC, r.nombre_rol";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // Obtener rol por ID
    public function getRoleById($id) {
        $sql = "SELECT * FROM roles WHERE id_rol = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    // Crear nuevo rol
    public function createRole($nombre, $descripcion) {
        try {
            $this->db->beginTransaction();

            // Verificar que el nombre no exista
            if ($this->roleNameExists($nombre)) {
                throw new Exception("Ya existe un rol con ese nombre");
            }

            $sql = "INSERT INTO roles (nombre_rol, descripcion, activo) VALUES (:nombre, :descripcion, 1)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nombre' => trim($nombre),
                'descripcion' => trim($descripcion)
            ]);

            $roleId = $this->db->lastInsertId();
            $this->db->commit();

            return $roleId;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // Actualizar rol
    public function updateRole($id, $nombre, $descripcion) {
        try {
            $this->db->beginTransaction();

            // Verificar que el nombre no exista (excluyendo el rol actual)
            if ($this->roleNameExists($nombre, $id)) {
                throw new Exception("Ya existe un rol con ese nombre");
            }

            $sql = "UPDATE roles SET nombre_rol = :nombre, descripcion = :descripcion WHERE id_rol = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nombre' => trim($nombre),
                'descripcion' => trim($descripcion),
                'id' => $id
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // Eliminar rol (lógico)
    public function deleteRole($id) {
        try {
            $this->db->beginTransaction();

            // Verificar que no tenga usuarios activos asignados
            $sql = "SELECT COUNT(*) as total FROM usuarios WHERE id_rol = :id AND activo = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetch();

            if ($result['total'] > 0) {
                throw new Exception("No se puede eliminar el rol porque tiene usuarios asignados");
            }

            // Eliminar lógicamente el rol
            $sql = "UPDATE roles SET activo = 0 WHERE id_rol = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);

            // Desactivar todos los permisos del rol
            $sql = "UPDATE permisos SET estado = '0' WHERE id_rol = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // GESTIÓN DE PERMISOS
    // Obtener estructura completa de menús y submenús con permisos de un rol
    public function getMenuStructureWithPermissions($roleId = null) {
        $sql = "SELECT 
                    m.id_menu, m.nombre_menu, m.icono as menu_icono, m.orden as menu_orden,
                    sm.id_submenu, sm.nombre_submenu, sm.uri_submenu, sm.icono as submenu_icono, sm.orden as submenu_orden,
                    p.id_permiso, p.permiso_crear, p.permiso_leer, p.permiso_editar, p.permiso_eliminar, p.estado
                FROM menus m
                INNER JOIN submenus sm ON m.id_menu = sm.id_menu
                LEFT JOIN permisos p ON (sm.id_submenu = p.id_submenu AND p.id_rol = :role_id)
                WHERE m.estado = '1' AND sm.estado = '1'
                ORDER BY m.orden, sm.orden";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['role_id' => $roleId]);
        $results = $stmt->fetchAll();

        // Organizar en estructura jerárquica
        $structure = [];
        foreach ($results as $row) {
            $menuId = $row['id_menu'];

            // Crear menú si no existe
            if (!isset($structure[$menuId])) {
                $structure[$menuId] = [
                    'id_menu' => $row['id_menu'],
                    'nombre_menu' => $row['nombre_menu'],
                    'icono' => $row['menu_icono'],
                    'orden' => $row['menu_orden'],
                    'submenus' => []
                ];
            }

            // Agregar submenú con permisos
            $structure[$menuId]['submenus'][] = [
                'id_submenu' => $row['id_submenu'],
                'nombre_submenu' => $row['nombre_submenu'],
                'uri_submenu' => $row['uri_submenu'],
                'icono' => $row['submenu_icono'],
                'orden' => $row['submenu_orden'],
                'permisos' => [
                    'id_permiso' => $row['id_permiso'],
                    'crear' => $row['permiso_crear'] == 1,
                    'leer' => $row['permiso_leer'] == 1,
                    'editar' => $row['permiso_editar'] == 1,
                    'eliminar' => $row['permiso_eliminar'] == 1,
                    'activo' => $row['estado'] == '1',
                    'tiene_permiso' => !is_null($row['id_permiso']) && $row['estado'] == '1'
                ]
            ];
        }

        return array_values($structure);
    }

    // Guardar todos los permisos de un rol
    public function saveRolePermissions($roleId, $permissions) {
        try {
            $this->db->beginTransaction();

            // Primero, desactivar todos los permisos existentes del rol
            $sql = "UPDATE permisos SET estado = '0' WHERE id_rol = :role_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['role_id' => $roleId]);

            // Luego, procesar los nuevos permisos
            // Luego, procesar los nuevos permisos
            foreach ($permissions as $submenuId => $permisos) {
                // Asegurar que todas las claves existan (los checkboxes no marcados no se envían)
                $permisos = array_merge([
                    'crear' => false,
                    'leer' => false,
                    'editar' => false,
                    'eliminar' => false
                        ], $permisos);

                // Verificar si ya existe un registro de permiso para este rol y submenú
                $sql = "SELECT id_permiso FROM permisos WHERE id_rol = :role_id AND id_submenu = :submenu_id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['role_id' => $roleId, 'submenu_id' => $submenuId]);
                $existingPermission = $stmt->fetch();

                if ($existingPermission) {
                    // Actualizar permiso existente
                    $sql = "UPDATE permisos SET 
                    permiso_crear = :crear,
                    permiso_leer = :leer,
                    permiso_editar = :editar,
                    permiso_eliminar = :eliminar,
                    estado = '1'
                WHERE id_permiso = :id_permiso";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        'crear' => $permisos['crear'] ? 1 : 0,
                        'leer' => $permisos['leer'] ? 1 : 0,
                        'editar' => $permisos['editar'] ? 1 : 0,
                        'eliminar' => $permisos['eliminar'] ? 1 : 0,
                        'id_permiso' => $existingPermission['id_permiso']
                    ]);
                } else {
                    // Crear nuevo permiso
                    $sql = "INSERT INTO permisos (id_rol, id_submenu, permiso_crear, permiso_leer, permiso_editar, permiso_eliminar, estado)
                VALUES (:role_id, :submenu_id, :crear, :leer, :editar, :eliminar, '1')";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        'role_id' => $roleId,
                        'submenu_id' => $submenuId,
                        'crear' => $permisos['crear'] ? 1 : 0,
                        'leer' => $permisos['leer'] ? 1 : 0,
                        'editar' => $permisos['editar'] ? 1 : 0,
                        'eliminar' => $permisos['eliminar'] ? 1 : 0
                    ]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // Clonar permisos de un rol a otro
    public function clonePermissions($fromRoleId, $toRoleId) {
        try {
            $this->db->beginTransaction();

            // Obtener permisos del rol origen
            $sql = "SELECT id_submenu, permiso_crear, permiso_leer, permiso_editar, permiso_eliminar
                    FROM permisos 
                    WHERE id_rol = :from_role AND estado = '1'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['from_role' => $fromRoleId]);
            $sourcePermissions = $stmt->fetchAll();

            // Aplicar permisos al rol destino
            $permissions = [];
            foreach ($sourcePermissions as $perm) {
                $permissions[$perm['id_submenu']] = [
                    'crear' => $perm['permiso_crear'] == 1,
                    'leer' => $perm['permiso_leer'] == 1,
                    'editar' => $perm['permiso_editar'] == 1,
                    'eliminar' => $perm['permiso_eliminar'] == 1
                ];
            }

            $this->saveRolePermissions($toRoleId, $permissions);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // MÉTODOS AUXILIARES
    // Verificar si existe un rol con el mismo nombre
    private function roleNameExists($nombre, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM roles WHERE nombre_rol = :nombre";
        $params = ['nombre' => $nombre];

        if ($excludeId) {
            $sql .= " AND id_rol != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    // Obtener resumen de permisos de un rol
    public function getRolePermissionsSummary($roleId) {
        $sql = "SELECT 
                    COUNT(*) as total_permisos,
                    SUM(CASE WHEN permiso_crear = 1 THEN 1 ELSE 0 END) as puede_crear,
                    SUM(CASE WHEN permiso_leer = 1 THEN 1 ELSE 0 END) as puede_leer,
                    SUM(CASE WHEN permiso_editar = 1 THEN 1 ELSE 0 END) as puede_editar,
                    SUM(CASE WHEN permiso_eliminar = 1 THEN 1 ELSE 0 END) as puede_eliminar
                FROM permisos 
                WHERE id_rol = :role_id AND estado = '1'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['role_id' => $roleId]);
        return $stmt->fetch();
    }
}

?>