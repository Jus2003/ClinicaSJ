<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$horarioId = $_GET['id'] ?? '';

if (empty($horarioId)) {
    echo json_encode(['success' => false, 'error' => 'ID de horario requerido']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $sql = "SELECT h.id_horario, h.id_medico, h.dia_semana, h.hora_inicio, h.hora_fin, 
                   h.id_sucursal, s.nombre_sucursal,
                   CASE h.dia_semana 
                       WHEN 1 THEN 'Lunes'
                       WHEN 2 THEN 'Martes'
                       WHEN 3 THEN 'Miércoles'
                       WHEN 4 THEN 'Jueves'
                       WHEN 5 THEN 'Viernes'
                       WHEN 6 THEN 'Sábado'
                       WHEN 7 THEN 'Domingo'
                   END as nombre_dia
            FROM horarios_medicos h
            INNER JOIN sucursales s ON h.id_sucursal = s.id_sucursal
            WHERE h.id_horario = :horario_id AND h.activo = 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['horario_id' => $horarioId]);
    $horario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$horario) {
        echo json_encode(['success' => false, 'error' => 'Horario no encontrado']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'horario' => $horario
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>