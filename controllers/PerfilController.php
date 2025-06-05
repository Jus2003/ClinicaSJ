<?php
require_once 'models/User.php';

class PerfilController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User();
    }
    
    public function datos() {
        // Verificar autenticación
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        
        $error = '';
        $success = '';
        
        // Obtener datos actuales del usuario
        $usuario = $this->userModel->getUserById($_SESSION['user_id']);
        
        // Procesar actualización de datos
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $data = [
                    'username' => trim($_POST['username']),
                    'email' => trim($_POST['email']),
                    'nombre' => trim($_POST['nombre']),
                    'apellido' => trim($_POST['apellido']),
                    'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?: null,
                    'genero' => $_POST['genero'] ?: null,
                    'telefono' => trim($_POST['telefono'] ?? '') ?: null,
                    'direccion' => trim($_POST['direccion'] ?? '') ?: null,
                    'cedula' => trim($_POST['cedula'] ?? '') ?: null
                ];
                
                // Validaciones básicas
                if (empty($data['username']) || empty($data['email']) || 
                    empty($data['nombre']) || empty($data['apellido'])) {
                    throw new Exception("Por favor complete todos los campos obligatorios");
                }
                
                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("El email no tiene un formato válido");
                }
                
                // Actualizar datos personales (sin cambiar rol ni sucursal)
                $this->userModel->updateUserProfile($_SESSION['user_id'], $data);
                
                // Actualizar datos en sesión
                $_SESSION['nombre_completo'] = $data['nombre'] . ' ' . $data['apellido'];
                $_SESSION['username'] = $data['username'];
                $_SESSION['email'] = $data['email'];
                
                $success = "Datos actualizados exitosamente";
                
                // Recargar datos del usuario
                $usuario = $this->userModel->getUserById($_SESSION['user_id']);
                
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        include 'views/perfil/datos.php';
    }
    
    public function password() {
        // Verificar autenticación
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        
        $error = '';
        $success = '';
        
        // Procesar cambio de contraseña
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentPassword = trim($_POST['current_password'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');
            
            try {
                // Validaciones
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    throw new Exception("Por favor complete todos los campos");
                }
                
                // Validar que la nueva contraseña tenga exactamente 6 dígitos
                if (!preg_match('/^\d{6}$/', $newPassword)) {
                    throw new Exception("La nueva contraseña debe contener exactamente 6 números");
                }
                
                if ($newPassword !== $confirmPassword) {
                    throw new Exception("Las contraseñas no coinciden");
                }
                
                // Verificar contraseña actual
                $usuario = $this->userModel->getUserById($_SESSION['user_id']);
                if (base64_decode($usuario['password']) !== $currentPassword) {
                    throw new Exception("La contraseña actual es incorrecta");
                }
                
                // Cambiar contraseña
                if ($this->userModel->changeUserPassword($_SESSION['user_id'], $newPassword)) {
                    $success = "Contraseña cambiada exitosamente";
                } else {
                    throw new Exception("Error al cambiar la contraseña");
                }
                
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
        
        include 'views/perfil/password.php';
    }
    
    public function notificaciones() {
        // Verificar autenticación
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        
        // Por ahora solo incluimos la vista básica
        // Aquí puedes agregar la lógica para obtener notificaciones del usuario
        include 'views/perfil/notificaciones.php';
    }
}
?>