<?php
// Deshabilitar mostrar errores para evitar HTML en respuesta JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start();

// Limpiar cualquier output previo
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Headers JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Función para enviar respuesta JSON limpia
function enviarRespuestaJSON($data) {
    // Limpiar cualquier output previo
    if (ob_get_level()) {
        ob_clean();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    enviarRespuestaJSON(['success' => false, 'error' => 'Método no permitido']);
}

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    enviarRespuestaJSON(['success' => false, 'error' => 'No autorizado']);
}

try {
    // Incluir modelos con manejo de errores
    $basePath = dirname(dirname(__DIR__));
    $citaModelPath = $basePath . '/models/Cita.php';
    $userModelPath = $basePath . '/models/User.php';
    
    if (!file_exists($citaModelPath)) {
        throw new Exception("Archivo Cita.php no encontrado en: " . $citaModelPath);
    }
    if (!file_exists($userModelPath)) {
        throw new Exception("Archivo User.php no encontrado en: " . $userModelPath);
    }
    
    require_once $citaModelPath;
    require_once $userModelPath;
    
    // Verificar que las clases existan
    if (!class_exists('Cita')) {
        throw new Exception("Clase Cita no encontrada");
    }
    if (!class_exists('User')) {
        throw new Exception("Clase User no encontrada");
    }
    
    // Obtener datos JSON
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        throw new Exception("No se recibieron datos JSON");
    }
    
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON inválido: " . json_last_error_msg());
    }
    
    // Log para debugging
    error_log("Datos recibidos: " . print_r($input, true));
    
    // Validar campos requeridos
    $camposRequeridos = [
        'fecha_cita', 'hora_cita', 'tipo_cita', 
        'id_especialidad', 'id_sucursal', 'id_medico', 
        'motivo_consulta'
    ];
    
    foreach ($camposRequeridos as $campo) {
        if (!isset($input[$campo]) || (is_string($input[$campo]) && trim($input[$campo]) === '')) {
            throw new Exception("El campo '{$campo}' es requerido");
        }
    }
    
    // Instanciar modelos
    $citaModel = new Cita();
    $userModel = new User();
    
    // Determinar el paciente
    $idPaciente = null;
    
    if ($_SESSION['role_id'] == 4) { // Paciente
        if (isset($input['es_para_conocido']) && $input['es_para_conocido']) {
            // Validar datos de conocido
            if (empty($input['nombre_paciente']) || empty($input['apellido_paciente']) || empty($input['email_paciente'])) {
                throw new Exception("Faltan datos del conocido");
            }
            
            // Crear nuevo paciente
            $datosNuevoPaciente = [
                'username' => $input['cedula_paciente'] ?? 'temp_' . time(),
                'email' => $input['email_paciente'],
                'password' => password_hash($input['cedula_paciente'] ?? 'temp123', PASSWORD_DEFAULT),
                'nombre' => $input['nombre_paciente'],
                'apellido' => $input['apellido_paciente'],
                'cedula' => $input['cedula_paciente'] ?? null,
                'telefono' => $input['telefono_paciente'] ?? null,
                'fecha_nacimiento' => $input['fecha_nacimiento_paciente'] ?? null,
                'genero' => $input['genero_paciente'] ?? null,
                'direccion' => $input['direccion_paciente'] ?? null,
                'id_rol' => 4,
                'activo' => 1
            ];
            
            $idPaciente = $userModel->createUser($datosNuevoPaciente);
            if (!$idPaciente) {
                throw new Exception("Error al registrar el conocido");
            }
        } else {
            // Cita propia
            $idPaciente = $_SESSION['user_id'];
        }
    } else {
        // Admin/Recepcionista
        if (isset($input['id_paciente_existente']) && !empty($input['id_paciente_existente'])) {
            // Paciente existente seleccionado
            $idPaciente = $input['id_paciente_existente'];
        } else {
            // Buscar o crear paciente por cédula
            if (!empty($input['cedula_paciente'])) {
                // Verificar si existe el método getUserByCedula
                if (!method_exists($userModel, 'getUserByCedula')) {
                    throw new Exception("Método getUserByCedula no existe en clase User");
                }
                
                $pacienteExistente = $userModel->getUserByCedula($input['cedula_paciente']);
                
                if ($pacienteExistente) {
                    $idPaciente = $pacienteExistente['id_usuario'];
                } else {
                    // Crear nuevo paciente
                    if (empty($input['nombre_paciente']) || empty($input['apellido_paciente']) || empty($input['email_paciente'])) {
                        throw new Exception("Faltan datos del paciente");
                    }
                    
                    $datosNuevoPaciente = [
                        'username' => $input['cedula_paciente'],
                        'email' => $input['email_paciente'],
                        'password' => password_hash($input['cedula_paciente'], PASSWORD_DEFAULT),
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
                        throw new Exception("Error al registrar el paciente");
                    }
                }
            } else {
                throw new Exception("Debe especificar la cédula del paciente");
            }
        }
    }
    
    if (!$idPaciente) {
        throw new Exception("No se pudo determinar el ID del paciente");
    }
    
    // Verificar disponibilidad del horario
    if (!method_exists($citaModel, 'verificarDisponibilidad')) {
        throw new Exception("Método verificarDisponibilidad no existe en clase Cita");
    }
    
    if (!$citaModel->verificarDisponibilidad($input['id_medico'], $input['fecha_cita'], $input['hora_cita'])) {
        throw new Exception("El horario seleccionado ya no está disponible");
    }
    
    // Determinar estado según rol
    $estadoCita = 'agendada'; // Por defecto
    if ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2) { // Admin o Recepcionista
        $estadoCita = 'confirmada';
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
        'estado_cita' => $estadoCita,
        'motivo_consulta' => $input['motivo_consulta'],
        'observaciones' => $input['observaciones'] ?? null,
        'id_usuario_registro' => $_SESSION['user_id']
    ];
    
    // Log para debugging
    error_log("Datos de cita a crear: " . print_r($datosCita, true));
    
    // Crear la cita
    if (!method_exists($citaModel, 'createCita')) {
        throw new Exception("Método createCita no existe en clase Cita");
    }
    
    $citaId = $citaModel->createCita($datosCita);
    
    if ($citaId) {
        // Log éxito
        error_log("Cita creada exitosamente con ID: " . $citaId);
        
        // Respuesta exitosa
        enviarRespuestaJSON([
            'success' => true,
            'message' => 'Cita agendada exitosamente',
            'cita_id' => $citaId,
            'cita' => [
                'id_cita' => $citaId,
                'estado_cita' => $estadoCita,
                'fecha_cita' => $input['fecha_cita'],
                'hora_cita' => $input['hora_cita'],
                'tipo_cita' => $input['tipo_cita']
            ]
        ]);
    } else {
        throw new Exception("Error al crear la cita en la base de datos");
    }
    
} catch (Exception $e) {
    // Log del error
    error_log("Error en agendar-cita.php: " . $e->getMessage() . " en línea " . $e->getLine());
    
    enviarRespuestaJSON([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'line' => $e->getLine(),
            'file' => basename($e->getFile()),
            'session_role' => $_SESSION['role_id'] ?? 'no_definido'
        ]
    ]);
}
?>