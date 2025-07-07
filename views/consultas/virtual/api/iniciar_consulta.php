<?php
// views/consultas/virtual/api/iniciar_consulta.php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [3, 4])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once '../../../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$citaId = $input['cita_id'] ?? 0;

if (!$citaId) {
    echo json_encode(['success' => false, 'error' => 'ID de cita requerido']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar acceso a la cita
    $sqlVerificar = "SELECT COUNT(*) FROM citas WHERE id_cita = :cita_id AND 
                     (id_medico = :user_id OR id_paciente = :user_id) AND
                     estado_cita IN ('confirmada', 'en_curso')";
    $stmtVerificar = $db->prepare($sqlVerificar);
    $stmtVerificar->bindParam(':cita_id', $citaId);
    $stmtVerificar->bindParam(':user_id', $_SESSION['user_id']);
    $stmtVerificar->execute();
    
    if ($stmtVerificar->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'error' => 'Sin acceso a esta cita']);
        exit;
    }
    
    // Actualizar estado a "en_curso"
    $sqlActualizar = "UPDATE citas SET estado_cita = 'en_curso' WHERE id_cita = :cita_id";
    $stmtActualizar = $db->prepare($sqlActualizar);
    $stmtActualizar->bindParam(':cita_id', $citaId);
    $stmtActualizar->execute();
    
    echo json_encode(['success' => true, 'message' => 'Consulta iniciada']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>