<?php

require_once 'config/database.php';

class Notificacion {

    private $conn;
    private $table_name = "notificaciones";

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getAllNotificaciones() {
        $query = "SELECT n.*, 
                         CONCAT(u.nombre, ' ', u.apellido) as nombre_destinatario,
                         u.email as email_destinatario
                  FROM " . $this->table_name . " n
                  LEFT JOIN usuarios u ON n.id_usuario_destinatario = u.id_usuario
                  ORDER BY n.fecha_creacion DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNotificacionesByUsuario($user_id) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE id_usuario_destinatario = ?
                  ORDER BY fecha_creacion DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function marcarComoLeida($id_notificacion) {
        $query = "UPDATE " . $this->table_name . " 
                  SET leida = 1 
                  WHERE id_notificacion = ?";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id_notificacion]);
    }

    public function marcarTodasLeidasUsuario($user_id) {
        $query = "UPDATE " . $this->table_name . " 
                  SET leida = 1 
                  WHERE id_usuario_destinatario = ? AND leida = 0";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$user_id]);
    }

    public function marcarTodasLeidasAdmin() {
        $query = "UPDATE " . $this->table_name . " 
                  SET leida = 1 
                  WHERE leida = 0";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute();
    }

    public function eliminarNotificacion($id_notificacion) {
        $query = "DELETE FROM " . $this->table_name . " 
                  WHERE id_notificacion = ?";

        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$id_notificacion]);
    }

    public function contarNoLeidas($user_id) {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " 
                  WHERE id_usuario_destinatario = ? AND leida = 0";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }

    public function crearNotificacion($user_id, $tipo, $titulo, $mensaje, $id_referencia = null, $enviar_email = true) {
        $query = "INSERT INTO " . $this->table_name . " 
              (id_usuario_destinatario, tipo_notificacion, titulo, mensaje, id_referencia, enviada_email, fecha_creacion) 
              VALUES (?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $this->conn->prepare($query);
        $enviar_email_int = $enviar_email ? 1 : 0;
        $result = $stmt->execute([$user_id, $tipo, $titulo, $mensaje, $id_referencia, $enviar_email_int]);

        // Si se creó exitosamente y se debe enviar email, enviarlo
        if ($result && $enviar_email) {
            $this->enviarEmailNotificacion($user_id, $titulo, $mensaje);
        }

        return $result;
    }

    private function enviarEmailNotificacion($user_id, $titulo, $mensaje) {
        try {
            // Obtener datos del usuario
            $query = "SELECT email, CONCAT(nombre, ' ', apellido) as nombre_completo 
                      FROM usuarios WHERE id_usuario = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$user_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && !empty($usuario['email'])) {
                require_once 'includes/email-sender.php';
                enviarEmailNotificacion($usuario['email'], $usuario['nombre_completo'], $titulo, $mensaje);
            }
        } catch (Exception $e) {
            // Log del error pero no detener el proceso
            error_log("Error enviando email de notificación: " . $e->getMessage());
        }
    }

    public function getUsuariosParaNotificacion() {
        try {
            error_log("Ejecutando query para obtener usuarios...");

            $query = "SELECT u.id_usuario, CONCAT(u.nombre, ' ', u.apellido) as nombre_completo, 
                         r.nombre_rol
                  FROM usuarios u
                  JOIN roles r ON u.id_rol = r.id_rol
                  WHERE u.activo = 1
                  ORDER BY r.nombre_rol, u.nombre, u.apellido";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("Query ejecutada exitosamente. Usuarios encontrados: " . count($result));
            foreach ($result as $usuario) {
                error_log("Usuario: " . $usuario['nombre_completo'] . " - Rol: " . $usuario['nombre_rol']);
            }

            return $result;
        } catch (Exception $e) {
            error_log("Error en getUsuariosParaNotificacion: " . $e->getMessage());
            throw $e;
        }
    }
}

?>