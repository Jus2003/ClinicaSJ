<?php
require_once 'models/User.php';

// Redirigir si ya está logueado
if (isset($_SESSION['user_id'])) {
    header('Location: index.php?action=dashboard');
    exit;
}

$error = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = "Por favor complete todos los campos";
    } else {
        $userModel = new User();
        $user = $userModel->login($username, $password);
        
        if ($user) {
            // Iniciar sesión
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nombre_completo'] = $user['nombre'] . ' ' . $user['apellido'];
            $_SESSION['role_id'] = $user['id_rol'];
            $_SESSION['role_name'] = $user['nombre_rol'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['requiere_cambio_contrasena'] = $user['requiere_cambio_contrasena'];
            
            // Verificar si requiere cambio de contraseña
            if ($user['requiere_cambio_contrasena'] == 1) {
                header('Location: index.php?action=auth/change-password');
            } else {
                header('Location: index.php?action=dashboard');
            }
            exit;
        } else {
            $error = "Credenciales incorrectas";
        }
    }
}

include 'views/includes/header.php';
?>

<div class="container-fluid vh-100 d-flex align-items-center justify-content-center bg-light">
    <div class="row w-100">
        <div class="col-md-4 offset-md-4">
            <div class="card shadow">
                <div class="card-header bg-primary text-white text-center">
                    <h4><i class="fas fa-hospital"></i> Clínica SJ</h4>
                    <p class="mb-0">Sistema de Gestión Médica</p>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">
                                <i class="fas fa-user"></i> Usuario o Email
                            </label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Ingrese su usuario o email" required 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock"></i> Contraseña
                            </label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Ingrese su contraseña" required>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-center text-muted">
                    <small>&copy; 2025 Clínica SJ - Sistema de Gestión Médica</small>
                </div>
            </div>
            
            <!-- Panel de usuarios de prueba -->
            <div class="card mt-3 shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Usuarios de Prueba</h6>
                </div>
                <div class="card-body p-3">
                    <div class="row text-center">
                        <div class="col-6">
                            <small class="text-muted">
                                <strong>Admin:</strong><br>
                                admin / admin123
                            </small>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">
                                <strong>Médico:</strong><br>
                                dr_martinez / medic123
                            </small>
                        </div>
                    </div>
                    <div class="row text-center mt-2">
                        <div class="col-6">
                            <small class="text-muted">
                                <strong>Recepcionista:</strong><br>
                                recep_central / recep123
                            </small>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">
                                <strong>Paciente:</strong><br>
                                paciente_juan / pac123
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    .card {
        border: none;
        border-radius: 15px;
    }
    .card-header {
        border-radius: 15px 15px 0 0 !important;
    }
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
</style>

</body>
</html>