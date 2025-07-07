<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$citaId = $_GET['cita_id'] ?? '';

if (empty($citaId)) {
    echo json_encode(['success' => false, 'message' => 'ID de cita requerido']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar permisos según el rol
    $wherePermiso = '';
    $params = ['cita_id' => $citaId];

    if ($_SESSION['role_id'] == 3) { // Médico
        $wherePermiso = " AND cit.id_medico = :id_medico";
        $params['id_medico'] = $_SESSION['user_id'];
    } elseif ($_SESSION['role_id'] == 4) { // Paciente
        $wherePermiso = " AND cit.id_paciente = :id_paciente";
        $params['id_paciente'] = $_SESSION['user_id'];
    }

    $sql = "SELECT r.*
            FROM recetas r
            INNER JOIN consultas c ON r.id_consulta = c.id_consulta
            INNER JOIN citas cit ON c.id_cita = cit.id_cita
            WHERE cit.id_cita = :cita_id" . $wherePermiso . "
            ORDER BY r.fecha_emision DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $recetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'recetas' => $recetas]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al obtener recetas: ' . $e->getMessage()]);
}
?>