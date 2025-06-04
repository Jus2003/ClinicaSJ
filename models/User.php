<?php
require_once 'config/database.php';

class User {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function login($username, $password) {
        $sql = "SELECT u.*, r.nombre_rol 
                FROM usuarios u 
                INNER JOIN roles r ON u.id_rol = r.id_rol 
                WHERE (u.username = :username OR u.email = :username) 
                AND u.activo = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        
        if ($user && base64_decode($user['password']) === $password) {
            // Actualizar último acceso
            $this->updateLastAccess($user['id_usuario']);
            return $user;
        }
        
        return false;
    }
    
    public function changePassword($userId, $newPassword) {
        $sql = "UPDATE usuarios SET 
                password = :password, 
                requiere_cambio_contrasena = 0,
                clave_temporal = NULL
                WHERE id_usuario = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'password' => base64_encode($newPassword),
            'id' => $userId
        ]);
    }
    
    private function updateLastAccess($userId) {
        $sql = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $userId]);
    }
}
?>