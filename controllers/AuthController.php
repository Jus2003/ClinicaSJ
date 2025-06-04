<?php
require_once 'models/User.php';

class AuthController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User();
    }
    
    public function showLogin() {
        // Si ya está logueado, redirigir al dashboard
        if (isset($_SESSION['user_id'])) {
            header('Location: index.php?action=dashboard');
            exit;
        }
        
        include 'views/auth/login.php';
    }
    
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            
            if (empty($username) || empty($password)) {
                $error = "Por favor complete todos los campos";
                include 'views/auth/login.php';
                return;
            }
            
            $user = $this->userModel->login($username, $password);
            
            if ($user) {
                // Iniciar sesión
                $_SESSION['user_id'] = $user['id_usuario'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nombre_completo'] = $user['nombre'] . ' ' . $user['apellido'];
                $_SESSION['role_id'] = $user['id_rol'];
                $_SESSION['role_name'] = $user['nombre_rol'];
                $_SESSION['email'] = $user['email'];
                
                header('Location: index.php?action=dashboard');
                exit;
            } else {
                $error = "Credenciales incorrectas";
                include 'views/auth/login.php';
            }
        }
    }
    
    public function logout() {
        session_destroy();
        header('Location: index.php');
        exit;
    }
}
?>