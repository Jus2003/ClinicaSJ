<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Verificar que sea admin o recepcionista
if (!in_array($_SESSION['role_id'], [1, 2])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $citaId = $_POST['cita_id'] ?? '';
    $nuevoEstado = $_POST['nuevo_estado'] ?? '';
    $motivo = $_POST['motivo'] ?? '';
    
    // Validar datos
    if (empty($citaId) || empty($nuevoEstado)) {
        throw new Exception('Datos incompletos');
    }
    
    $estadosPermitidos = ['agendada', 'confirmada', 'en_curso', 'completada', 'cancelada', 'no_asistio'];
    if (!in_array($nuevoEstado, $estadosPermitidos)) {
        throw new Exception('Estado no válido');
    }
    
    // OBTENER ESTADO ANTERIOR ANTES DE ACTUALIZAR
    $sqlVerificar = "SELECT estado_cita FROM citas WHERE id_cita = :cita_id";
    $stmtVerificar = $db->prepare($sqlVerificar);
    $stmtVerificar->execute(['cita_id' => $citaId]);
    $estadoAnterior = $stmtVerificar->fetchColumn();
    
    if (!$estadoAnterior) {
        throw new Exception('Cita no encontrada');
    }
    
    // Actualizar estado
    if ($nuevoEstado === 'cancelada') {
        $sql = "UPDATE citas SET estado_cita = :estado, fecha_cancelacion = NOW(), motivo_cancelacion = :motivo WHERE id_cita = :cita_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'estado' => $nuevoEstado,
            'motivo' => $motivo,
            'cita_id' => $citaId
        ]);
    } else {
        $sql = "UPDATE citas SET estado_cita = :estado WHERE id_cita = :cita_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'estado' => $nuevoEstado,
            'cita_id' => $citaId
        ]);
    }
    
    // AGREGAR NOTIFICACIONES POR CORREO
    try {
        require_once '../includes/notificaciones-citas.php';
        $notificador = new NotificacionesCitas($db);
        $notificador->notificarCambioEstado($citaId, $estadoAnterior, $nuevoEstado, $motivo);
        
        error_log("Notificaciones de cambio de estado enviadas para cita ID: " . $citaId);
    } catch (Exception $e) {
        error_log("Error enviando notificaciones: " . $e->getMessage());
        // No fallar la operación por error de notificaciones
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado exitosamente y notificaciones enviadas'
    ]);
    
} catch (Exception $e) {
    error_log("Error en cambiar-estado-cita.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>