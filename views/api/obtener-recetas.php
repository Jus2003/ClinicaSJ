<?php
// views/api/obtener-recetas.php

header('Content-Type: application/json');

// Verificar autenticación
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// Verificar parámetros
$consultaId = (int) ($_GET['consulta_id'] ?? 0);
if (!$consultaId) {
    echo json_encode(['success' => false, 'error' => 'ID de consulta requerido']);
    exit;
}

try {
    require_once '../../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    // Verificar permisos - el paciente solo puede ver sus propias recetas
    if ($_SESSION['role_id'] == 4) {
        $sqlVerificar = "SELECT c.id_cita 
                        FROM consultas con
                        INNER JOIN citas c ON con.id_cita = c.id_cita
                        WHERE con.id_consulta = :consulta_id 
                        AND c.id_paciente = :user_id";
        
        $stmtVerificar = $db->prepare($sqlVerificar);
        $stmtVerificar->execute([
            'consulta_id' => $consultaId,
            'user_id' => $_SESSION['user_id']
        ]);
        
        if (!$stmtVerificar->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Sin permisos para ver estas recetas']);
            exit;
        }
    }

    // Obtener recetas
    $sql = "SELECT 
                r.id_receta,
                r.codigo_receta,
                r.medicamento,
                r.concentracion,
                r.forma_farmaceutica,
                r.dosis,
                r.frecuencia,
                r.duracion,
                r.cantidad,
                r.indicaciones_especiales,
                r.fecha_emision,
                r.fecha_vencimiento,
                r.estado
            FROM recetas r
            WHERE r.id_consulta = :consulta_id
            ORDER BY r.fecha_emision DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute(['consulta_id' => $consultaId]);
    $recetas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'recetas' => $recetas
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener recetas: ' . $e->getMessage()
    ]);
}
?>