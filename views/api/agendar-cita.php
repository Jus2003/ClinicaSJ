<?php
session_start();
require_once '../../models/Cita.php';
require_once '../../models/User.php';

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
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validar datos requeridos
    $camposRequeridos = ['fecha_cita', 'hora_cita', 'tipo_cita', 'id_especialidad', 'id_sucursal', 'id_medico', 'motivo_consulta'];
    
    foreach ($camposRequeridos as $campo) {
        if (empty($input[$campo])) {
            throw new Exception("El campo {$campo} es requerido");
        }
    }
    
    $citaModel = new Cita();
    $userModel = new User();
    
    // Determinar el paciente
    $idPaciente = null;
    
    if ($_SESSION['role_id'] == 4) {
        // Si es paciente, usar su propio ID
        $idPaciente = $_SESSION['user_id'];
    } else {
        // Si es admin/recepcionista, buscar o crear paciente
        if (!empty($input['cedula_paciente'])) {
            // Buscar paciente existente por cédula
            $pacienteExistente = $userModel->getUserByCedula($input['cedula_paciente']);
            
            if ($pacienteExistente) {
                $idPaciente = $pacienteExistente['id_usuario'];
            } else {
                // Crear nuevo paciente
                $datosNuevoPaciente = [
                    'username' => $input['cedula_paciente'],
                    'email' => $input['email_paciente'],
                    'password' => password_hash($input['cedula_paciente'], PASSWORD_DEFAULT), // Usar cédula como password temporal
                    'nombre' => $input['nombre_paciente'],
                    'apellido' => $input['apellido_paciente'],
                    'cedula' => $input['cedula_paciente'],
                    'telefono' => $input['telefono_paciente'] ?? null,
                    'fecha_nacimiento' => $input['fecha_nacimiento_paciente'] ?? null,
                    'genero' => $input['genero_paciente'] ?? null,
                    'direccion' => $input['direccion_paciente'] ?? null,
                    'id_rol' => 4,
                    'activo' => 1
                ];
                
                $idPaciente = $userModel->createUser($datosNuevoPaciente);
                
                if (!$idPaciente) {
                    throw new Exception("Error al crear el paciente");
                }
            }
        } else {
            throw new Exception("Debe especificar los datos del paciente");
        }
    }
    
    // Verificar disponibilidad del horario
    if (!$citaModel->verificarDisponibilidad($input['id_medico'], $input['fecha_cita'], $input['hora_cita'])) {
        throw new Exception("El horario seleccionado ya no está disponible");
    }
    
    // Preparar datos de la cita
    $datosCita = [
        'id_paciente' => $idPaciente,
        'id_medico' => $input['id_medico'],
        'id_especialidad' => $input['id_especialidad'],
        'id_sucursal' => $input['id_sucursal'],
        'fecha_cita' => $input['fecha_cita'],
        'hora_cita' => $input['hora_cita'],
        'tipo_cita' => $input['tipo_cita'],
        'estado_cita' => 'agendada',
        'motivo_consulta' => $input['motivo_consulta'],
        'observaciones' => $input['observaciones'] ?? null,
        'id_usuario_registro' => $_SESSION['user_id']
    ];
    
    // Crear la cita
    $citaId = $citaModel->createCita($datosCita);
    
    if ($citaId) {
        // Obtener datos completos de la cita creada
        $citaCreada = $citaModel->getCitasPaciente($idPaciente, 1)[0] ?? null;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Cita agendada exitosamente',
            'cita_id' => $citaId,
            'cita' => $citaCreada
        ]);
    } else {
        throw new Exception("Error al crear la cita");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>