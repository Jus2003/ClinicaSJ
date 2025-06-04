<?php
require_once 'config/database.php';

class Sucursal {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Obtener todas las sucursales activas
    public function getAllSucursales() {
        $sql = "SELECT * FROM sucursales WHERE activo = 1 ORDER BY nombre_sucursal";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Obtener sucursal por ID
    public function getSucursalById($id) {
        $sql = "SELECT * FROM sucursales WHERE id_sucursal = :id AND activo = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
}
?>