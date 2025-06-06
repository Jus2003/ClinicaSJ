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

$medico = $_GET['medico'] ?? '';
$fecha = $_GET['fecha'] ?? '';
$especialidad = $_GET['especialidad'] ?? '';

if (empty($medico) || empty($fecha) || empty($especialidad)) {
    echo json_encode(['success' => false, 'error' => 'Parámetros incompletos']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // CORRECCIÓN: Calcular día de la semana correctamente
    $timestamp = strtotime($fecha);
    $diaSemana = date('N', $timestamp); // 1=Lunes, 7=Domingo
    
    // Obtener duración de la cita para esta especialidad
    $sqlDuracion = "SELECT duracion_cita_minutos FROM especialidades WHERE id_especialidad = :especialidad";
    $stmtDuracion = $db->prepare($sqlDuracion);
    $stmtDuracion->execute(['especialidad' => $especialidad]);
    $duracionCita = $stmtDuracion->fetchColumn() ?: 30;
    
    // Obtener horarios del médico para este día
    $sqlHorarios = "SELECT hora_inicio, hora_fin 
                    FROM horarios_medicos 
                    WHERE id_medico = :medico 
                    AND dia_semana = :dia_semana 
                    AND activo = 1
                    ORDER BY hora_inicio";
    
    $stmtHorarios = $db->prepare($sqlHorarios);
    $stmtHorarios->execute([
        'medico' => $medico,
        'dia_semana' => $diaSemana
    ]);
    
    $horariosDisponibles = $stmtHorarios->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($horariosDisponibles)) {
        echo json_encode([
            'success' => true,
            'horarios' => [],
            'mensaje' => 'El médico no tiene horarios configurados para este día',
            'debug' => [
                'fecha' => $fecha,
                'dia_semana' => $diaSemana,
                'dia_nombre' => date('l', $timestamp),
                'medico' => $medico
            ]
        ]);
        exit;
    }
    
    // Obtener citas ya agendadas
    $sqlCitas = "SELECT TIME(hora_cita) as hora_cita 
                 FROM citas 
                 WHERE id_medico = :medico 
                 AND fecha_cita = :fecha 
                 AND estado_cita NOT IN ('cancelada', 'no_asistio')";
    
    $stmtCitas = $db->prepare($sqlCitas);
    $stmtCitas->execute([
        'medico' => $medico,
        'fecha' => $fecha
    ]);
    
    $citasAgendadas = $stmtCitas->fetchAll(PDO::FETCH_COLUMN);
    
    // Generar slots de tiempo disponibles
    $slotsDisponibles = [];
    
    foreach ($horariosDisponibles as $horario) {
        $horaInicio = new DateTime($fecha . ' ' . $horario['hora_inicio']);
        $horaFin = new DateTime($fecha . ' ' . $horario['hora_fin']);
        
        while ($horaInicio < $horaFin) {
            $horaSlot = $horaInicio->format('H:i:s');
            
            // Verificar si ya hay una cita en este horario
            if (!in_array($horaSlot, $citasAgendadas)) {
                $slotsDisponibles[] = [
                    'hora' => $horaSlot,
                    'hora_formateada' => $horaInicio->format('g:i A')
                ];
            }
            
            // Avanzar por la duración de la cita
            $horaInicio->add(new DateInterval('PT' . $duracionCita . 'M'));
        }
    }
    
    echo json_encode([
        'success' => true,
        'horarios' => $slotsDisponibles,
        'debug' => [
            'fecha' => $fecha,
            'dia_semana' => $diaSemana,
            'dia_nombre' => date('l', $timestamp),
            'duracion_cita' => $duracionCita,
            'citas_agendadas' => $citasAgendadas,
            'horarios_medico' => $horariosDisponibles,
            'total_slots' => count($slotsDisponibles)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>