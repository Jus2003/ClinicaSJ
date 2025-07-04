<?php
// Debug mode - eliminar después
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Debug info
error_log("=== DEBUG AJAX ===");
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NO DEFINIDO'));
error_log("Session role_id: " . ($_SESSION['role_id'] ?? 'NO DEFINIDO'));
error_log("Cita ID recibido: " . ($_GET['cita_id'] ?? 'NO DEFINIDO'));
error_log("Ruta actual: " . __DIR__);

try {
    // Verificar si los archivos existen
    $triajeModelPath = __DIR__ . '/../models/TriajeModel.php';
    $databasePath = __DIR__ . '/../config/database.php';
    
    error_log("Buscando TriajeModel en: " . $triajeModelPath);
    error_log("Archivo TriajeModel existe: " . (file_exists($triajeModelPath) ? 'SÍ' : 'NO'));
    
    error_log("Buscando Database en: " . $databasePath);
    error_log("Archivo Database existe: " . (file_exists($databasePath) ? 'SÍ' : 'NO'));
    
    require_once $triajeModelPath;
    require_once $databasePath;
    
    error_log("Archivos incluidos exitosamente");
    
} catch (Exception $e) {
    error_log("Error incluyendo archivos: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Error incluyendo archivos: ' . $e->getMessage()]);
    exit;
}

// Set header for JSON response
header('Content-Type: application/json');

// Verificar que sea médico o recepcionista  
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [2, 3])) {
    error_log("Error de autorización - User ID: " . ($_SESSION['user_id'] ?? 'NO') . ", Role: " . ($_SESSION['role_id'] ?? 'NO'));
    echo json_encode(['success' => false, 'error' => 'No autorizado - Role: ' . ($_SESSION['role_id'] ?? 'undefined')]);
    exit;
}

$citaId = $_GET['cita_id'] ?? null;

if (!$citaId) {
    error_log("No se recibió cita_id");
    echo json_encode(['success' => false, 'error' => 'ID de cita requerido']);
    exit;
}

try {
    error_log("Creando instancia de TriajeModel");
    $triajeModel = new TriajeModel();
    
    error_log("Verificando permisos para cita: " . $citaId);
    // Verificar permisos
    if (!$triajeModel->verificarPermisosCita($citaId, $_SESSION['user_id'], $_SESSION['role_id'])) {
        error_log("Sin permisos para la cita");
        echo json_encode(['success' => false, 'error' => 'Sin permisos para esta cita']);
        exit;
    }
    
    error_log("Obteniendo información de la cita");
    // Obtener información de la cita
    $infoCita = $triajeModel->getInfoCita($citaId);
    error_log("Info cita obtenida: " . ($infoCita ? 'SÍ' : 'NO'));
    
    error_log("Obteniendo respuestas del triaje");
    // Obtener respuestas del triaje
    $respuestasTriaje = $triajeModel->getRespuestasTriaje($citaId);
    error_log("Respuestas obtenidas: " . count($respuestasTriaje));
    
    if (empty($respuestasTriaje)) {
        error_log("No hay respuestas de triaje");
        echo json_encode(['success' => false, 'error' => 'No hay triaje completado para esta cita']);
        exit;
    }
    
    // Calcular edad si hay fecha de nacimiento
    $edad = null;
    if (isset($infoCita['fecha_nacimiento']) && $infoCita['fecha_nacimiento']) {
        $fechaNac = new DateTime($infoCita['fecha_nacimiento']);
        $hoy = new DateTime();
        $edad = $hoy->diff($fechaNac)->y;
    }
    
    error_log("Enviando respuesta exitosa");
    echo json_encode([
        'success' => true,
        'cita' => $infoCita,
        'edad' => $edad,
        'respuestas' => $respuestasTriaje
    ]);
    
} catch (Exception $e) {
    error_log("Error en el try-catch: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>