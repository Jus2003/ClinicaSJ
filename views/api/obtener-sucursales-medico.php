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

$medicoId = $_GET['medico_id'] ?? '';

if (empty($medicoId)) {
    echo json_encode(['success' => false, 'error' => 'ID de médico requerido']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener sucursales donde el médico trabaja (basado en horarios existentes)
    $sql = "SELECT DISTINCT s.id_sucursal, s.nombre_sucursal, s.direccion, s.telefono
            FROM sucursales s
            INNER JOIN horarios_medicos h ON s.id_sucursal = h.id_sucursal
            WHERE h.id_medico = :medico_id 
            AND s.activo = 1 
            AND h.activo = 1
            ORDER BY s.nombre_sucursal";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['medico_id' => $medicoId]);
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Si no tiene sucursales asignadas por horarios, devolver todas las sucursales activas
    if (empty($sucursales)) {
        $sqlTodas = "SELECT id_sucursal, nombre_sucursal, direccion, telefono 
                     FROM sucursales 
                     WHERE activo = 1 
                     ORDER BY nombre_sucursal";
        $stmtTodas = $db->prepare($sqlTodas);
        $stmtTodas->execute();
        $sucursales = $stmtTodas->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'sucursales' => $sucursales,
        'total' => count($sucursales)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>