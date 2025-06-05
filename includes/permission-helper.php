<?php
function hasPermission($userId, $uriSubmenu, $action = 'leer') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Obtener rol del usuario
        $sql = "SELECT id_rol FROM usuarios WHERE id_usuario = :user_id AND activo = 1";
        $stmt = $db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Verificar permiso específico
        $sql = "SELECT p.permiso_crear, p.permiso_leer, p.permiso_editar, p.permiso_eliminar 
                FROM permisos p
                INNER JOIN submenus sm ON p.id_submenu = sm.id_submenu
                WHERE p.id_rol = :role_id 
                AND sm.uri_submenu = :uri_submenu 
                AND p.estado = '1'
                AND sm.estado = '1'";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'role_id' => $user['id_rol'],
            'uri_submenu' => $uriSubmenu
        ]);
        $permission = $stmt->fetch();
        
        if (!$permission) {
            return false;
        }
        
        // Verificar el tipo de acción solicitada
        switch ($action) {
            case 'crear':
                return $permission['permiso_crear'] == 1;
            case 'leer':
                return $permission['permiso_leer'] == 1;
            case 'editar':
                return $permission['permiso_editar'] == 1;
            case 'eliminar':
                return $permission['permiso_eliminar'] == 1;
            default:
                return false;
        }
        
    } catch (Exception $e) {
        error_log("Error verificando permisos: " . $e->getMessage());
        return false;
    }
}

function requirePermission($userId, $uriSubmenu, $action = 'leer') {
    if (!hasPermission($userId, $uriSubmenu, $action)) {
        header('Location: index.php?action=dashboard');
        exit;
    }
}
?>