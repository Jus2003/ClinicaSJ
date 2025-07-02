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
$medicoId = $_POST['id_medico'] ?? '';
$diaSemana = $_POST['dia_semana'] ?? '';
$horaInicio = $_POST['hora_inicio'] ?? '';
$horaFin = $_POST['hora_fin'] ?? '';
$sucursalId = $_POST['id_sucursal'] ?? '';

// Validaciones básicas
if (empty($medicoId) || empty($diaSemana) || empty($horaInicio) || empty($horaFin) || empty($sucursalId)) {
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

    // Verificar que el médico existe y está activo
    $sqlMedico = "SELECT id_usuario FROM usuarios WHERE id_usuario = :medico_id AND id_rol = 3 AND activo = 1";
    $stmtMedico = $db->prepare($sqlMedico);
    $stmtMedico->execute(['medico_id' => $medicoId]);

    if (!$stmtMedico->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Médico no válido']);
        exit;
    }

    // Verificar que la sucursal existe y está activa
    $sqlSucursal = "SELECT id_sucursal FROM sucursales WHERE id_sucursal = :sucursal_id AND activo = 1";
    $stmtSucursal = $db->prepare($sqlSucursal);
    $stmtSucursal->execute(['sucursal_id' => $sucursalId]);

    if (!$stmtSucursal->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Sucursal no válida']);
        exit;
    }

    // Verificar que no exista conflicto de horarios
    $sqlConflicto = "SELECT id_horario FROM horarios_medicos 
                     WHERE id_medico = :medico_id 
                     AND id_sucursal = :sucursal_id 
                     AND dia_semana = :dia_semana 
                     AND activo = 1 
                     AND (
                         (hora_inicio < :hora_fin AND hora_fin > :hora_inicio)
                     )";

    $stmtConflicto = $db->prepare($sqlConflicto);
    $stmtConflicto->execute([
        'medico_id' => $medicoId,
        'sucursal_id' => $sucursalId,
        'dia_semana' => $diaSemana,
        'hora_inicio' => $horaInicio,
        'hora_fin' => $horaFin
    ]);

    if ($stmtConflicto->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Ya existe un horario que se superpone con el ingresado']);
        exit;
    }

    // Insertar nuevo horario
    $sqlInsert = "INSERT INTO horarios_medicos (id_medico, dia_semana, hora_inicio, hora_fin, id_sucursal, activo, fecha_creacion) 
              VALUES (:medico_id, :dia_semana, :hora_inicio, :hora_fin, :sucursal_id, 1, NOW())";

    $stmtInsert = $db->prepare($sqlInsert);
    $stmtInsert->execute([
        'medico_id' => $medicoId,
        'dia_semana' => $diaSemana,
        'hora_inicio' => $horaInicio,
        'hora_fin' => $horaFin,
        'sucursal_id' => $sucursalId
    ]);

    $horarioId = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Horario creado exitosamente',
        'horario_id' => $horarioId
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>