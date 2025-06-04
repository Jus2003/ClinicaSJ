<?php
require_once 'config/database.php';

class Especialidad {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Obtener todas las especialidades activas
    public function getAllEspecialidades() {
        $sql = "SELECT * FROM especialidades WHERE activo = 1 ORDER BY nombre_especialidad";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Obtener especialidad por ID
    public function getEspecialidadById($id) {
        $sql = "SELECT * FROM especialidades WHERE id_especialidad = :id AND activo = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
}
?>