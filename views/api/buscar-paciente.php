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

// Verificar permisos (solo admin, recepcionista o médico pueden buscar otros pacientes)
if (!in_array($_SESSION['role_id'], [1, 2, 3])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sin permisos']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$criterio = trim($input['criterio'] ?? '');

if (empty($criterio)) {
    echo json_encode(['success' => false, 'error' => 'Criterio de búsqueda requerido']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Buscar por cédula, nombre, apellido o email
    $sql = "SELECT id_usuario, username, email, cedula, nombre, apellido, 
                   fecha_nacimiento, genero, telefono, direccion
            FROM usuarios 
            WHERE id_rol = 4 
            AND activo = 1
            AND (
                cedula LIKE :criterio OR
                nombre LIKE :criterio OR
                apellido LIKE :criterio OR
                email LIKE :criterio OR
                CONCAT(nombre, ' ', apellido) LIKE :criterio
            )
            ORDER BY nombre, apellido
            LIMIT 10";
    
    $stmt = $db->prepare($sql);
    $criterioLike = "%{$criterio}%";
    $stmt->execute(['criterio' => $criterioLike]);
    
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear resultados
    $resultados = [];
    foreach ($pacientes as $paciente) {
        $resultados[] = [
            'id_usuario' => $paciente['id_usuario'],
            'nombre_completo' => $paciente['nombre'] . ' ' . $paciente['apellido'],
            'cedula' => $paciente['cedula'],
            'email' => $paciente['email'],
            'telefono' => $paciente['telefono'],
            'fecha_nacimiento' => $paciente['fecha_nacimiento'],
            'genero' => $paciente['genero'],
            'direccion' => $paciente['direccion'],
            'datos_completos' => [
                'nombre' => $paciente['nombre'],
                'apellido' => $paciente['apellido'],
                'email' => $paciente['email'],
                'telefono' => $paciente['telefono'],
                'cedula' => $paciente['cedula'],
                'fecha_nacimiento' => $paciente['fecha_nacimiento'],
                'genero' => $paciente['genero'],
                'direccion' => $paciente['direccion']
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'pacientes' => $resultados,
        'total' => count($resultados)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>