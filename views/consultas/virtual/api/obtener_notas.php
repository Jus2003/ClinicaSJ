<?php
// views/consultas/virtual/api/obtener_notas.php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    echo json_encode([]);
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
    
    // Verificar que es el médico de la cita
    $sqlVerificar = "SELECT COUNT(*) FROM citas WHERE id_cita = :cita_id AND id_medico = :user_id";
    $stmtVerificar = $db->prepare($sqlVerificar);
    $stmtVerificar->bindParam(':cita_id', $citaId);
    $stmtVerificar->bindParam(':user_id', $_SESSION['user_id']);
    $stmtVerificar->execute();
    
    if ($stmtVerificar->fetchColumn() == 0) {
        echo json_encode([]);
        exit;
    }
    
    // Obtener notas
    $sql = "SELECT contenido, fecha_nota as fecha
            FROM notas_consulta_virtual
            WHERE id_cita = :cita_id AND id_medico = :medico_id
            ORDER BY fecha_nota DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':cita_id', $citaId);
    $stmt->bindParam(':medico_id', $_SESSION['user_id']);
    $stmt->execute();
    
    $notas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($notas);
    
} catch (Exception $e) {
    echo json_encode([]);
}
?>