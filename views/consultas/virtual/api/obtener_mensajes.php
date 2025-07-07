<?php
// views/consultas/virtual/api/obtener_mensajes.php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [3, 4])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once '../../../../config/database.php';

$citaId = $_GET['cita_id'] ?? 0;

if (!$citaId) {
    echo json_encode([]);
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
        echo json_encode([]);
        exit;
    }
    
    // Obtener mensajes
    $sql = "SELECT m.mensaje, m.fecha_mensaje as fecha,
                   CONCAT(u.nombre, ' ', u.apellido) as autor,
                   CASE WHEN u.id_rol = 3 THEN 'medico' ELSE 'paciente' END as tipo
            FROM mensajes_consulta_virtual m
            INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
            WHERE m.id_cita = :cita_id
            ORDER BY m.fecha_mensaje ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':cita_id', $citaId);
    $stmt->execute();
    
    $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($mensajes);
    
} catch (Exception $e) {
    echo json_encode([]);
}
?>