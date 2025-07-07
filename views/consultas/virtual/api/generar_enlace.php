<?php
// views/consultas/virtual/api/generar_enlace.php

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once '../../../../models/Cita.php';

$input = json_decode(file_get_contents('php://input'), true);
$citaId = $input['cita_id'] ?? 0;

if (!$citaId) {
    echo json_encode(['success' => false, 'error' => 'ID de cita requerido']);
    exit;
}

try {
    $citaModel = new Cita();
    $enlace = $citaModel->generarEnlaceVirtual($citaId);
    
    echo json_encode([
        'success' => true,
        'enlace' => $enlace,
        'message' => 'Enlace generado exitosamente'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>