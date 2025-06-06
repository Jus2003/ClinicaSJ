<?php
require_once 'models/User.php';

// Redirigir si ya está logueado
if (isset($_SESSION['user_id'])) {
    header('Location: index.php?action=dashboard');
    exit;
}

$error = '';
$success = '';

// Verificar si viene de logout
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = "Sesión cerrada correctamente";
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);
    
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
            
            if ($remember) {
                setcookie('remember_user', $user['username'], time() + (30 * 24 * 60 * 60), '/');
            }
            
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

$rememberedUser = $_COOKIE['remember_user'] ?? '';

include 'views/includes/header.php';
?>

<div class="login-page">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100 py-4">
            <div class="col-md-6 col-lg-4">
                
                <!-- Logo y título -->
                <div class="text-center mb-4">
                    <div class="logo mb-3">
                        <i class="fas fa-hospital fa-3x text-white"></i>
                    </div>
                    <h2 class="text-white fw-bold">Clínica SJ</h2>
                    <p class="text-white-50">Sistema de Gestión Médica</p>
                </div>

                <!-- Tarjeta de login -->
                <div class="card login-card shadow mb-4">
                    <div class="card-body p-4">
                        <h4 class="text-center mb-4">Iniciar Sesión</h4>
                        
                        <!-- Mensajes -->
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Usuario o Email</label>
                                <input type="text" 
                                       class="form-control" 
                                       name="username" 
                                       placeholder="Ingrese su usuario o email" 
                                       required 
                                       value="<?php echo htmlspecialchars($rememberedUser ?: ($_POST['username'] ?? '')); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <input type="password" 
                                       class="form-control" 
                                       name="password" 
                                       placeholder="Ingrese su contraseña" 
                                       required>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="remember" 
                                       <?php echo $rememberedUser ? 'checked' : ''; ?>>
                                <label class="form-check-label">
                                    Recordar sesión
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Usuarios de prueba -->
                <div class="card">
                    <div class="card-header text-center">
                        <h6 class="mb-0">Usuarios de Prueba</h6>
                    </div>
                    <div class="card-body p-3">
                        <div class="row text-center">
                            <div class="col-6 mb-2">
                                <button class="btn btn-outline-secondary btn-sm w-100 test-user" 
                                        data-username="admin" data-password="admin123">
                                    <i class="fas fa-user-shield text-danger"></i><br>
                                    <small>Admin</small>
                                </button>
                            </div>
                            <div class="col-6 mb-2">
                                <button class="btn btn-outline-secondary btn-sm w-100 test-user" 
                                        data-username="dr_martinez" data-password="medic123">
                                    <i class="fas fa-user-md text-success"></i><br>
                                    <small>Médico</small>
                                </button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-outline-secondary btn-sm w-100 test-user" 
                                        data-username="recep_central" data-password="recep123">
                                    <i class="fas fa-user-tie text-info"></i><br>
                                    <small>Recepcionista</small>
                                </button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-outline-secondary btn-sm w-100 test-user" 
                                        data-username="paciente_juan" data-password="pac123">
                                    <i class="fas fa-user text-warning"></i><br>
                                    <small>Paciente</small>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center mt-3">
                    <small class="text-white-50">
                        &copy; 2025 Clínica SJ
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Fondo con gradiente animado */
    .login-page {
        background: linear-gradient(-45deg, #667eea, #764ba2, #f093fb, #f5576c);
        background-size: 400% 400%;
        animation: gradientShift 15s ease infinite;
        min-height: 100vh;
    }
    
    @keyframes gradientShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }
    
    /* Logo simple */
    .logo {
        width: 80px;
        height: 80px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
    }
    
    /* Tarjetas */
    .card {
        border: none;
        border-radius: 15px;
        background: rgba(255, 255, 255, 0.95);
    }
    
    /* Formulario */
    .form-control {
        border-radius: 8px;
        padding: 12px;
    }
    
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 8px;
        padding: 12px;
    }
    
    .btn-primary:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }
    
    /* Usuarios de prueba */
    .test-user {
        border-radius: 8px;
        padding: 8px;
    }
    
    .test-user:hover {
        transform: translateY(-2px);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Usuarios de prueba clickeables
        const testUsers = document.querySelectorAll('.test-user');
        testUsers.forEach(user => {
            user.addEventListener('click', function() {
                const username = this.getAttribute('data-username');
                const password = this.getAttribute('data-password');
                
                document.querySelector('input[name="username"]').value = username;
                document.querySelector('input[name="password"]').value = password;
            });
        });
    });
</script>

</body>
</html>