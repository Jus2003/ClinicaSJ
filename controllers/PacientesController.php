<?php
// controllers/PacientesController.php
require_once 'models/User.php';
require_once 'includes/cedula-api.php';

class PacientesController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User();
    }
    
    // Mostrar formulario de registro de paciente
    public function registrar() {
        // Verificar permisos (solo admin o recepcionista)
        if (!in_array($_SESSION['role_id'], [1, 2])) {
            header('Location: index.php?action=dashboard');
            exit;
        }
        
        $error = '';
        $success = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Validar campos obligatorios
                $requiredFields = ['username', 'email', 'nombre', 'apellido'];
                foreach ($requiredFields as $field) {
                    if (empty(trim($_POST[$field]))) {
                        throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
                    }
                }
                
                // Validar email
                if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("El email no tiene un formato válido");
                }
                
                // Validar cédula si se proporciona
                if (!empty($_POST['cedula']) && !validarCedulaEcuatoriana($_POST['cedula'])) {
                    throw new Exception("La cédula ingresada no es válida");
                }
                
                $data = [
                    'username' => trim($_POST['username']),
                    'email' => trim($_POST['email']),
                    'cedula' => trim($_POST['cedula']) ?: null,
                    'nombre' => trim($_POST['nombre']),
                    'apellido' => trim($_POST['apellido']),
                    'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?: null,
                    'genero' => $_POST['genero'] ?: null,
                    'telefono' => trim($_POST['telefono']) ?: null,
                    'direccion' => trim($_POST['direccion']) ?: null,
                    'id_rol' => 4, // Rol de paciente
                    'id_sucursal' => null // Los pacientes no están asignados a sucursales específicas
                ];
                
                $result = $this->userModel->createUser($data);
                
                $success = "Paciente registrado exitosamente. Se han enviado las credenciales al email: " . $data['email'];
                
                // Limpiar formulario después del éxito
                $_POST = [];
                
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        include 'views/pacientes/registrar.php';
    }
    
    // Gestionar pacientes existentes
    public function gestionar() {
        // Verificar permisos (solo admin o recepcionista)
        if (!in_array($_SESSION['role_id'], [1, 2])) {
            header('Location: index.php?action=dashboard');
            exit;
        }
        
        // Obtener todos los pacientes
        $pacientes = $this->userModel->getPacientes();
        
        include 'views/pacientes/gestionar.php';
    }
    
    // Ver/Editar paciente específico
    public function editar() {
        // Verificar permisos
        if (!in_array($_SESSION['role_id'], [1, 2])) {
            header('Location: index.php?action=dashboard');
            exit;
        }
        
        $id = $_GET['id'] ?? 0;
        if (!$id) {
            header('Location: index.php?action=pacientes_gestionar');
            exit;
        }
        
        $error = '';
        $success = '';
        
        // Obtener datos del paciente
        $paciente = $this->userModel->getUserById($id);
        
        if (!$paciente || $paciente['id_rol'] != 4) {
            header('Location: index.php?action=pacientes_gestionar');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                // Validar campos obligatorios
                $requiredFields = ['username', 'email', 'nombre', 'apellido'];
                foreach ($requiredFields as $field) {
                    if (empty(trim($_POST[$field]))) {
                        throw new Exception("El campo " . ucfirst($field) . " es obligatorio");
                    }
                }
                
                // Validar email
                if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("El email no tiene un formato válido");
                }
                
                // Validar cédula si se proporciona
                if (!empty($_POST['cedula']) && !validarCedulaEcuatoriana($_POST['cedula'])) {
                    throw new Exception("La cédula ingresada no es válida");
                }
                
                $data = [
                    'username' => trim($_POST['username']),
                    'email' => trim($_POST['email']),
                    'cedula' => trim($_POST['cedula']) ?: null,
                    'nombre' => trim($_POST['nombre']),
                    'apellido' => trim($_POST['apellido']),
                    'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?: null,
                    'genero' => $_POST['genero'] ?: null,
                    'telefono' => trim($_POST['telefono']) ?: null,
                    'direccion' => trim($_POST['direccion']) ?: null,
                    'id_rol' => 4, // Mantener rol de paciente
                    'id_sucursal' => null
                ];
                
                $this->userModel->updateUser($id, $data);
                $success = "Datos del paciente actualizados exitosamente";
                
                // Recargar datos actualizados
                $paciente = $this->userModel->getUserById($id);
                
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        include 'views/pacientes/editar.php';
    }
}
?>