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

$especialidad = $_GET['especialidad'] ?? '';
$sucursal = $_GET['sucursal'] ?? '';
$fecha = $_GET['fecha'] ?? '';

if (empty($especialidad) || empty($sucursal) || empty($fecha)) {
    echo json_encode(['success' => false, 'error' => 'Parámetros incompletos']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // CORRECCIÓN: Calcular día de la semana correctamente
    // En tu BD: 1=Lunes, 2=Martes, ..., 7=Domingo
    $timestamp = strtotime($fecha);
    $diaSemana = date('w', $timestamp) + 1; // 1=Lunes, 7=Domingo

    
    $sql = "SELECT DISTINCT u.id_usuario, u.nombre, u.apellido, u.email
            FROM usuarios u
            INNER JOIN medico_especialidades me ON u.id_usuario = me.id_medico
            INNER JOIN horarios_medicos hm ON u.id_usuario = hm.id_medico
            WHERE u.id_rol = 3 
            AND u.activo = 1
            AND me.id_especialidad = :especialidad
            AND me.activo = 1
            AND hm.id_sucursal = :sucursal
            AND hm.dia_semana = :dia_semana
            AND hm.activo = 1
            ORDER BY u.nombre, u.apellido";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'especialidad' => $especialidad,
        'sucursal' => $sucursal,
        'dia_semana' => $diaSemana
    ]);
    
    $medicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'medicos' => $medicos,
        'debug' => [
            'fecha' => $fecha,
            'dia_semana_calculado' => $diaSemana,
            'dia_nombre' => date('l', $timestamp),
            'especialidad' => $especialidad,
            'sucursal' => $sucursal,
            'sql_usado' => $sql
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>