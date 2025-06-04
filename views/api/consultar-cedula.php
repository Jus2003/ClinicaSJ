<?php
require_once '../../includes/cedula-api.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$cedula = trim($input['cedula'] ?? '');

if (empty($cedula)) {
    echo json_encode(['error' => 'Número de cédula requerido']);
    exit;
}

$resultado = consultarCedulaAPI($cedula);
echo json_encode($resultado);
?>