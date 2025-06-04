<?php
require_once 'config/database.php';

class Menu {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function getMenusByRole($roleId) {
        $sql = "SELECT DISTINCT m.*, sm.id_submenu, sm.nombre_submenu, sm.uri_submenu, sm.icono as icono_submenu
                FROM menus m
                INNER JOIN submenus sm ON m.id_menu = sm.id_menu
                INNER JOIN permisos p ON sm.id_submenu = p.id_submenu
                WHERE p.id_rol = :role_id 
                AND p.estado = '1' 
                AND p.permiso_leer = 1
                AND m.estado = '1'
                AND sm.estado = '1'
                ORDER BY m.orden, sm.orden";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['role_id' => $roleId]);
        $results = $stmt->fetchAll();
        
        // Organizar menús y submenús
        $menus = [];
        foreach ($results as $row) {
            $menuId = $row['id_menu'];
            
            if (!isset($menus[$menuId])) {
                $menus[$menuId] = [
                    'id_menu' => $row['id_menu'],
                    'nombre_menu' => $row['nombre_menu'],
                    'icono' => $row['icono'],
                    'orden' => $row['orden'],
                    'submenus' => []
                ];
            }
            
            $menus[$menuId]['submenus'][] = [
                'id_submenu' => $row['id_submenu'],
                'nombre_submenu' => $row['nombre_submenu'],
                'uri_submenu' => $row['uri_submenu'],
                'icono' => $row['icono_submenu']
            ];
        }
        
        return array_values($menus);
    }
}
?>