<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    $fechaCita = $input['fecha_cita'] ?? '';
    $tipoCita = $input['tipo_cita'] ?? '';
    $idEspecialidad = $input['id_especialidad'] ?? '';
    $idSucursal = $input['id_sucursal'] ?? '';
    $idMedico = $input['id_medico'] ?? '';
    $horaCita = $input['hora_cita'] ?? '';
    $motivoConsulta = $input['motivo_consulta'] ?? '';
    $observaciones = $input['observaciones'] ?? '';
    $idPaciente = $input['id_paciente'] ?? $_SESSION['user_id'];
    
    // Validaciones
    if (empty($fechaCita) || empty($tipoCita) || empty($idEspecialidad) || 
        empty($idSucursal) || empty($idMedico) || empty($horaCita) || 
        empty($motivoConsulta)) {
        throw new Exception('Todos los campos obligatorios deben estar completos');
    }
    
    // Verificar que la fecha no sea en el pasado
    if ($fechaCita < date('Y-m-d')) {
        throw new Exception('No se pueden agendar citas en fechas pasadas');
    }
    
    // CORRECCIÓN: Verificar horario del médico usando DAYOFWEEK de MySQL
    $sqlHorario = "SELECT COUNT(*) as existe 
                   FROM horarios_medicos h
                   WHERE h.id_medico = :medico
                   AND h.dia_semana = DAYOFWEEK(:fecha)
                   AND :hora BETWEEN h.hora_inicio AND h.hora_fin
                   AND h.activo = 1";
    
    $stmtHorario = $db->prepare($sqlHorario);
    $stmtHorario->execute([
        'medico' => $idMedico,
        'fecha' => $fechaCita,
        'hora' => $horaCita
    ]);
    
    $horarioExiste = $stmtHorario->fetch()['existe'];
    
    if ($horarioExiste == 0) {
        // Obtener información de debug
        $sqlDebug = "SELECT 
                        DAYOFWEEK(:fecha) as dia_calculado,
                        DAYNAME(:fecha) as nombre_dia,
                        GROUP_CONCAT(CONCAT('Día ', h.dia_semana, ': ', h.hora_inicio, '-', h.hora_fin) SEPARATOR ', ') as horarios_medico
                     FROM horarios_medicos h 
                     WHERE h.id_medico = :medico AND h.activo = 1";
        
        $stmtDebug = $db->prepare($sqlDebug);
        $stmtDebug->execute([
            'fecha' => $fechaCita,
            'medico' => $idMedico
        ]);
        
        $debug = $stmtDebug->fetch();
        
        throw new Exception("El médico no tiene horario disponible en esa fecha/hora. " .
                          "Día calculado: {$debug['dia_calculado']} ({$debug['nombre_dia']}). " .
                          "Horarios del médico: {$debug['horarios_medico']}");
    }
    
    // Verificar que no haya conflicto de horarios
    $sqlConflicto = "SELECT COUNT(*) as total FROM citas 
                     WHERE id_medico = :medico 
                     AND fecha_cita = :fecha 
                     AND hora_cita = :hora
                     AND estado_cita NOT IN ('cancelada', 'no_asistio')";
    
    $stmtConflicto = $db->prepare($sqlConflicto);
    $stmtConflicto->execute([
        'medico' => $idMedico,
        'fecha' => $fechaCita,
        'hora' => $horaCita
    ]);
    
    if ($stmtConflicto->fetch()['total'] > 0) {
        throw new Exception('Ya existe una cita en ese horario');
    }
    
    // Insertar la cita
    $sqlInsert = "INSERT INTO citas (id_paciente, id_medico, id_especialidad, id_sucursal, 
                                   fecha_cita, hora_cita, tipo_cita, estado_cita, 
                                   motivo_consulta, observaciones, id_usuario_registro, fecha_registro) 
                  VALUES (:paciente, :medico, :especialidad, :sucursal, :fecha, :hora, 
                          :tipo, 'agendada', :motivo, :observaciones, :usuario_registro, NOW())";
    
    $stmtInsert = $db->prepare($sqlInsert);
    $stmtInsert->execute([
        'paciente' => $idPaciente,
        'medico' => $idMedico,
        'especialidad' => $idEspecialidad,
        'sucursal' => $idSucursal,
        'fecha' => $fechaCita,
        'hora' => $horaCita,
        'tipo' => $tipoCita,
        'motivo' => $motivoConsulta,
        'observaciones' => $observaciones,
        'usuario_registro' => $_SESSION['user_id']
    ]);
    
    $citaId = $db->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Cita agendada exitosamente',
        'cita_id' => $citaId
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>