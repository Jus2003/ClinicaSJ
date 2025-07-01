<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Obtener datos JSON
$input = json_decode(file_get_contents('php://input'), true);
$horarioId = $input['id_horario'] ?? '';

if (empty($horarioId)) {
    echo json_encode(['success' => false, 'error' => 'ID de horario requerido']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar que el horario existe
    $sqlExiste = "SELECT id_horario FROM horarios_medicos WHERE id_horario = :horario_id AND activo = 1";
    $stmtExiste = $db->prepare($sqlExiste);
    $stmtExiste->execute(['horario_id' => $horarioId]);
    
    if (!$stmtExiste->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Horario no encontrado']);
        exit;
    }
    
    // Verificar si hay citas futuras que dependan de este horario
    $sqlCitas = "SELECT COUNT(*) as total FROM citas c
                 INNER JOIN horarios_medicos h ON c.id_medico = h.id_medico
                 WHERE h.id_horario = :horario_id 
                 AND c.fecha_cita >= CURDATE()
                 AND c.estado_cita IN ('agendada', 'confirmada')";
    
    $stmtCitas = $db->prepare($sqlCitas);
    $stmtCitas->execute(['horario_id' => $horarioId]);
    $citasFuturas = $stmtCitas->fetch(PDO::FETCH_ASSOC)['total'];
    
    if ($citasFuturas > 0) {
        echo json_encode([
            'success' => false, 
            'error' => "No se puede eliminar el horario porque hay {$citasFuturas} cita(s) programada(s) que dependen de él"
        ]);
        exit;
    }
    
    // Eliminar horario (soft delete)
    $sqlDelete = "UPDATE horarios_medicos SET activo = 0 WHERE id_horario = :horario_id";
    $stmtDelete = $db->prepare($sqlDelete);
    $stmtDelete->execute(['horario_id' => $horarioId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Horario eliminado exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>