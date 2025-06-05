<?php
require_once 'models/User.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
   header('Location: index.php?action=login');
   exit;
}

$userModel = new User();
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
       
       // Validar que la nueva contraseña tenga mínimo 6 caracteres
       if (strlen($newPassword) < 6) {
           throw new Exception("La nueva contraseña debe tener al menos 6 caracteres");
       }
       
       if ($newPassword !== $confirmPassword) {
           throw new Exception("Las contraseñas no coinciden");
       }
       
       // Verificar contraseña actual
       $usuario = $userModel->getUserById($_SESSION['user_id']);
       if (base64_decode($usuario['password']) !== $currentPassword) {
           throw new Exception("La contraseña actual es incorrecta");
       }
       
       // Cambiar contraseña
       if ($userModel->changeUserPassword($_SESSION['user_id'], $newPassword)) {
           $success = "Contraseña cambiada exitosamente";
       } else {
           throw new Exception("Error al cambiar la contraseña");
       }
       
   } catch (Exception $e) {
       $error = $e->getMessage();
   }
}

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container-fluid mt-4">
   <div class="row">
       <div class="col-12">
           <!-- Header -->
           <div class="d-flex justify-content-between align-items-center mb-4">
               <div>
                   <h2 class="text-primary">
                       <i class="fas fa-key"></i> Mi Perfil - Cambiar Contraseña
                   </h2>
                   <p class="text-muted mb-0">Actualizar contraseña de acceso</p>
               </div>
               <div>
                   <a href="index.php?action=dashboard" class="btn btn-outline-secondary">
                       <i class="fas fa-arrow-left"></i> Volver al Dashboard
                   </a>
               </div>
           </div>

           <!-- Mensajes -->
           <?php if (!empty($error)): ?>
               <div class="alert alert-danger alert-dismissible fade show" role="alert">
                   <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                   <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
               </div>
           <?php endif; ?>

           <?php if (!empty($success)): ?>
               <div class="alert alert-success alert-dismissible fade show" role="alert">
                   <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                   <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
               </div>
           <?php endif; ?>

           <div class="row justify-content-center">
               <!-- Enlaces rápidos -->
               <div class="col-lg-3">
                   <div class="card border-0 shadow-sm">
                       <div class="card-header bg-white border-bottom">
                           <h6 class="mb-0">
                               <i class="fas fa-cogs"></i> Configuración de Perfil
                           </h6>
                       </div>
                       <div class="card-body p-0">
                           <div class="list-group list-group-flush">
                               <a href="index.php?action=perfil/datos" class="list-group-item list-group-item-action">
                                   <i class="fas fa-id-card me-2"></i>
                                   Datos Personales
                               </a>
                               <a href="index.php?action=perfil/password" class="list-group-item list-group-item-action active">
                                   <i class="fas fa-key me-2"></i>
                                   Cambiar Contraseña
                               </a>
                               <a href="index.php?action=perfil/notificaciones" class="list-group-item list-group-item-action">
                                   <i class="fas fa-bell me-2"></i>
                                   Notificaciones
                               </a>
                           </div>
                       </div>
                   </div>
               </div>

               <!-- Formulario de Cambio de Contraseña -->
               <div class="col-lg-6">
                   <div class="card border-0 shadow-sm">
                       <div class="card-header bg-warning text-dark">
                           <h5 class="mb-0">
                               <i class="fas fa-shield-alt"></i> Cambiar Contraseña
                           </h5>
                       </div>
                       <div class="card-body">
                           <div class="alert alert-info">
                               <i class="fas fa-info-circle"></i>
                               <strong>Requisito de contraseña:</strong><br>
                               • Debe tener al menos 6 caracteres
                           </div>

                           <form method="POST" id="passwordForm">
                               <div class="mb-3">
                                   <label for="current_password" class="form-label">
                                       <i class="fas fa-lock"></i> Contraseña Actual <span class="text-danger">*</span>
                                   </label>
                                   <input type="password" class="form-control" id="current_password" name="current_password" 
                                          placeholder="Ingrese su contraseña actual" required>
                               </div>

                               <div class="mb-3">
                                   <label for="new_password" class="form-label">
                                       <i class="fas fa-key"></i> Nueva Contraseña <span class="text-danger">*</span>
                                   </label>
                                   <input type="password" class="form-control" id="new_password" name="new_password" 
                                          placeholder="Al menos 6 caracteres" minlength="6" required
                                          oninput="validatePassword()">
                                   <div class="form-text">
                                       <span id="passwordHelp">Ingrese al menos 6 caracteres</span>
                                   </div>
                               </div>

                               <div class="mb-3">
                                   <label for="confirm_password" class="form-label">
                                       <i class="fas fa-check"></i> Confirmar Nueva Contraseña <span class="text-danger">*</span>
                                   </label>
                                   <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                          placeholder="Repita la nueva contraseña" required
                                          oninput="validatePasswordMatch()">
                                   <div class="form-text">
                                       <span id="matchHelp"></span>
                                   </div>
                               </div>

                               <div class="d-grid gap-2">
                                   <button type="submit" class="btn btn-warning" id="submitBtn" disabled>
                                       <i class="fas fa-save"></i> Cambiar Contraseña
                                   </button>
                                   <a href="index.php?action=perfil/datos" class="btn btn-outline-secondary">
                                       <i class="fas fa-times"></i> Cancelar
                                   </a>
                               </div>
                           </form>
                       </div>
                   </div>
               </div>
           </div>
       </div>
   </div>
</div>

<style>
   .list-group-item.active {
       background-color: #667eea;
       border-color: #667eea;
   }
   
   .form-control:focus {
       border-color: #ffc107;
       box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
   }
   
   .is-valid {
       border-color: #198754;
   }
   
   .is-invalid {
       border-color: #dc3545;
   }
   
   .text-success {
       color: #198754 !important;
   }
   
   .text-danger {
       color: #dc3545 !important;
   }
</style>

<script>
   function validatePassword() {
       const newPassword = document.getElementById('new_password');
       const passwordHelp = document.getElementById('passwordHelp');
       const confirmPassword = document.getElementById('confirm_password');
       
       if (newPassword.value.length >= 6) {
           newPassword.classList.remove('is-invalid');
           newPassword.classList.add('is-valid');
           passwordHelp.innerHTML = '<i class="fas fa-check text-success"></i> Contraseña válida';
           passwordHelp.className = 'text-success';
       } else if (newPassword.value.length > 0) {
           newPassword.classList.remove('is-valid');
           newPassword.classList.add('is-invalid');
           passwordHelp.innerHTML = `<i class="fas fa-times text-danger"></i> ${newPassword.value.length}/6 caracteres mínimo`;
           passwordHelp.className = 'text-danger';
       } else {
           newPassword.classList.remove('is-valid', 'is-invalid');
           passwordHelp.innerHTML = 'Ingrese al menos 6 caracteres';
           passwordHelp.className = '';
       }
       
       // Limpiar confirmación si había algo
       if (confirmPassword.value) {
           validatePasswordMatch();
       }
       
       toggleSubmitButton();
   }
   
   function validatePasswordMatch() {
       const newPassword = document.getElementById('new_password');
       const confirmPassword = document.getElementById('confirm_password');
       const matchHelp = document.getElementById('matchHelp');
       
       if (confirmPassword.value && newPassword.value) {
           if (confirmPassword.value === newPassword.value) {
               confirmPassword.classList.remove('is-invalid');
               confirmPassword.classList.add('is-valid');
               matchHelp.innerHTML = '<i class="fas fa-check text-success"></i> Las contraseñas coinciden';
               matchHelp.className = 'text-success';
           } else {
               confirmPassword.classList.remove('is-valid');
               confirmPassword.classList.add('is-invalid');
               matchHelp.innerHTML = '<i class="fas fa-times text-danger"></i> Las contraseñas no coinciden';
               matchHelp.className = 'text-danger';
           }
       } else {
           confirmPassword.classList.remove('is-valid', 'is-invalid');
           matchHelp.innerHTML = '';
           matchHelp.className = '';
       }
       
       toggleSubmitButton();
   }
   
   function toggleSubmitButton() {
       const newPassword = document.getElementById('new_password');
       const confirmPassword = document.getElementById('confirm_password');
       const currentPassword = document.getElementById('current_password');
       const submitBtn = document.getElementById('submitBtn');
       
       const isNewPasswordValid = newPassword.value.length >= 6;
       const isPasswordMatch = confirmPassword.value === newPassword.value && confirmPassword.value !== '';
       const hasCurrentPassword = currentPassword.value.length > 0;
       
       if (isNewPasswordValid && isPasswordMatch && hasCurrentPassword) {
           submitBtn.disabled = false;
       } else {
           submitBtn.disabled = true;
       }
   }
   
   // Validar en tiempo real
   document.getElementById('current_password').addEventListener('input', toggleSubmitButton);
   
   // Prevenir envío si las validaciones no pasan
   document.getElementById('passwordForm').addEventListener('submit', function(e) {
       const newPassword = document.getElementById('new_password').value;
       const confirmPassword = document.getElementById('confirm_password').value;
       
       if (newPassword.length < 6) {
           e.preventDefault();
           alert('La nueva contraseña debe tener al menos 6 caracteres');
           return false;
       }
       
       if (newPassword !== confirmPassword) {
           e.preventDefault();
           alert('Las contraseñas no coinciden');
           return false;
       }
   });
</script>

</body>
</html>