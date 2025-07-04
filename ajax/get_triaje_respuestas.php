<?php
session_start();
require_once '../models/TriajeModel.php';
require_once '../config/database.php';

// Verificar que sea médico o recepcionista
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [2, 3])) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$citaId = $_GET['cita_id'] ?? null;

if (!$citaId) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de cita requerido']);
    exit;
}

try {
    $triajeModel = new TriajeModel();
    
    // Verificar permisos
    if (!$triajeModel->verificarPermisosCita($citaId, $_SESSION['user_id'], $_SESSION['role_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Sin permisos para esta cita']);
        exit;
    }
    
    // Obtener información de la cita
    $infoCita = $triajeModel->getInfoCita($citaId);
    
    // Obtener respuestas del triaje
    $respuestasTriaje = $triajeModel->getRespuestasTriaje($citaId);
    
    if (empty($respuestasTriaje)) {
        echo json_encode(['error' => 'No hay triaje completado para esta cita']);
        exit;
    }
    
    // Calcular edad si hay fecha de nacimiento
    $edad = null;
    if (isset($infoCita['fecha_nacimiento']) && $infoCita['fecha_nacimiento']) {
        $fechaNac = new DateTime($infoCita['fecha_nacimiento']);
        $hoy = new DateTime();
        $edad = $hoy->diff($fechaNac)->y;
    }
    
    echo json_encode([
        'success' => true,
        'cita' => $infoCita,
        'edad' => $edad,
        'respuestas' => $respuestasTriaje
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>