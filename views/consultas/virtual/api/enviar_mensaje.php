<?php
// views/consultas/virtual/api/enviar_mensaje.php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [3, 4])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once '../../../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$citaId = $input['cita_id'] ?? 0;
$mensaje = trim($input['mensaje'] ?? '');

if (!$citaId || !$mensaje) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar acceso a la cita
    $sqlVerificar = "SELECT COUNT(*) FROM citas WHERE id_cita = :cita_id AND 
                     (id_medico = :user_id OR id_paciente = :user_id)";
    $stmtVerificar = $db->prepare($sqlVerificar);
    $stmtVerificar->bindParam(':cita_id', $citaId);
    $stmtVerificar->bindParam(':user_id', $_SESSION['user_id']);
    $stmtVerificar->execute();
    
    if ($stmtVerificar->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'error' => 'Sin acceso a esta cita']);
        exit;
    }
    
    // Crear tabla de mensajes si no existe
    $sqlCrearTabla = "CREATE TABLE IF NOT EXISTS mensajes_consulta_virtual (
        id_mensaje INT AUTO_INCREMENT PRIMARY KEY,
        id_cita INT NOT NULL,
        id_usuario INT NOT NULL,
        mensaje TEXT NOT NULL,
        fecha_mensaje TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_cita) REFERENCES citas(id_cita) ON DELETE CASCADE,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
    )";
    $db->exec($sqlCrearTabla);
    
    // Insertar mensaje
    $sqlInsertar = "INSERT INTO mensajes_consulta_virtual (id_cita, id_usuario, mensaje) 
                    VALUES (:cita_id, :user_id, :mensaje)";
    $stmtInsertar = $db->prepare($sqlInsertar);
    $stmtInsertar->bindParam(':cita_id', $citaId);
    $stmtInsertar->bindParam(':user_id', $_SESSION['user_id']);
    $stmtInsertar->bindParam(':mensaje', $mensaje);
    $stmtInsertar->execute();
    
    echo json_encode(['success' => true, 'message' => 'Mensaje enviado']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>