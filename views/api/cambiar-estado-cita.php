<?php
session_start();
require_once '../../config/database.php';

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
    
    // Verificar que la cita existe
    $sqlVerificar = "SELECT estado_cita FROM citas WHERE id_cita = :cita_id";
    $stmtVerificar = $db->prepare($sqlVerificar);
    $stmtVerificar->execute(['cita_id' => $citaId]);
    
    if (!$stmtVerificar->fetch()) {
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
    
    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>