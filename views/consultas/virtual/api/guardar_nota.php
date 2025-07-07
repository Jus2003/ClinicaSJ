<?php
// views/consultas/virtual/api/guardar_nota.php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once '../../../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$citaId = $input['cita_id'] ?? 0;
$nota = trim($input['nota'] ?? '');

if (!$citaId || !$nota) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
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
        echo json_encode(['success' => false, 'error' => 'Sin acceso a esta cita']);
        exit;
    }
    
    // Crear tabla de notas si no existe
    $sqlCrearTabla = "CREATE TABLE IF NOT EXISTS notas_consulta_virtual (
        id_nota INT AUTO_INCREMENT PRIMARY KEY,
        id_cita INT NOT NULL,
        id_medico INT NOT NULL,
        contenido TEXT NOT NULL,
        fecha_nota TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_cita) REFERENCES citas(id_cita) ON DELETE CASCADE,
        FOREIGN KEY (id_medico) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
    )";
    $db->exec($sqlCrearTabla);
    
    // Insertar nota
    $sqlInsertar = "INSERT INTO notas_consulta_virtual (id_cita, id_medico, contenido) 
                    VALUES (:cita_id, :medico_id, :contenido)";
    $stmtInsertar = $db->prepare($sqlInsertar);
    $stmtInsertar->bindParam(':cita_id', $citaId);
    $stmtInsertar->bindParam(':medico_id', $_SESSION['user_id']);
    $stmtInsertar->bindParam(':contenido', $nota);
    $stmtInsertar->execute();
    
    echo json_encode(['success' => true, 'message' => 'Nota guardada']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>