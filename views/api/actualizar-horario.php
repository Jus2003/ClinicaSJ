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

// Obtener datos del formulario
$horarioId = $_POST['id_horario'] ?? '';
$medicoId = $_POST['id_medico'] ?? '';
$diaSemana = $_POST['dia_semana'] ?? '';
$horaInicio = $_POST['hora_inicio'] ?? '';
$horaFin = $_POST['hora_fin'] ?? '';
$sucursalId = $_POST['id_sucursal'] ?? '';

// Validaciones básicas
if (empty($horarioId) || empty($medicoId) || empty($diaSemana) || empty($horaInicio) || empty($horaFin) || empty($sucursalId)) {
    echo json_encode(['success' => false, 'error' => 'Todos los campos son obligatorios']);
    exit;
}

if ($diaSemana < 1 || $diaSemana > 7) {
    echo json_encode(['success' => false, 'error' => 'Día de la semana inválido']);
    exit;
}

if ($horaInicio >= $horaFin) {
    echo json_encode(['success' => false, 'error' => 'La hora de inicio debe ser menor que la hora de fin']);
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
    
    // Verificar que no exista conflicto con otros horarios (excluyendo el actual)
    $sqlConflicto = "SELECT id_horario FROM horarios_medicos 
                     WHERE id_medico = :medico_id 
                     AND id_sucursal = :sucursal_id 
                     AND dia_semana = :dia_semana 
                     AND id_horario != :horario_id
                     AND activo = 1 
                     AND (
                         (hora_inicio < :hora_fin AND hora_fin > :hora_inicio)
                     )";
    
    $stmtConflicto = $db->prepare($sqlConflicto);
    $stmtConflicto->execute([
        'medico_id' => $medicoId,
        'sucursal_id' => $sucursalId,
        'dia_semana' => $diaSemana,
        'horario_id' => $horarioId,
        'hora_inicio' => $horaInicio,
        'hora_fin' => $horaFin
    ]);
    
    if ($stmtConflicto->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Ya existe un horario que se superpone con el ingresado']);
        exit;
    }
    
    // Actualizar horario
    $sqlUpdate = "UPDATE horarios_medicos 
                  SET dia_semana = :dia_semana, 
                      hora_inicio = :hora_inicio, 
                      hora_fin = :hora_fin, 
                      id_sucursal = :sucursal_id
                  WHERE id_horario = :horario_id";
    
    $stmtUpdate = $db->prepare($sqlUpdate);
    $stmtUpdate->execute([
        'dia_semana' => $diaSemana,
        'hora_inicio' => $horaInicio,
        'hora_fin' => $horaFin,
        'sucursal_id' => $sucursalId,
        'horario_id' => $horarioId
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Horario actualizado exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>