<?php
require_once 'config/database.php';

class Role {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Obtener todos los roles activos
    public function getAllRoles() {
        $sql = "SELECT * FROM roles WHERE activo = 1 ORDER BY nombre_rol";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Obtener rol por ID
    public function getRoleById($id) {
        $sql = "SELECT * FROM roles WHERE id_rol = :id AND activo = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
}
?>