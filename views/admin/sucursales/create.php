<?php
require_once 'models/Sucursal.php';
require_once 'models/Especialidad.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php?action=dashboard');
    exit;
}

$sucursalModel = new Sucursal();
$especialidadModel = new Especialidad();

$error = '';
$success = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'nombre_sucursal' => trim($_POST['nombre_sucursal']),
            'direccion' => trim($_POST['direccion']),
            'telefono' => trim($_POST['telefono']) ?: null,
            'email' => trim($_POST['email']) ?: null,
            'ciudad' => trim($_POST['ciudad']) ?: null,
            'provincia' => trim($_POST['provincia']) ?: null,
            'codigo_postal' => trim($_POST['codigo_postal']) ?: null,
            'especialidades' => $_POST['especialidades'] ?? []
        ];

        // Validaciones básicas
        if (empty($data['nombre_sucursal']) || empty($data['direccion'])) {
            throw new Exception("Por favor complete todos los campos obligatorios");
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("El email no tiene un formato válido");
        }

        $sucursalId = $sucursalModel->createSucursal($data);
        $success = "Sucursal creada exitosamente";

        // Limpiar formulario
        $_POST = [];
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener datos para formulario
$especialidades = $especialidadModel->getAllEspecialidades();

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
                        <i class="fas fa-building"></i> Nueva Sucursal
                    </h2>
                    <p class="text-muted mb-0">Crear una nueva sucursal en el sistema</p>
                </div>
                <div>
                    <a href="index.php?action=admin/sucursales" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
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

            <!-- Formulario -->
            <form method="POST" id="sucursalForm">
                <div class="row">
                    <!-- Información básica -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle"></i> Información Básica
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre de la Sucursal <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="nombre_sucursal" 
                                                   value="<?php echo $_POST['nombre_sucursal'] ?? ''; ?>" 
                                                   placeholder="Ej: Clínica Central Norte" required>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Dirección <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="direccion" rows="3" 
                                                      placeholder="Dirección completa de la sucursal" required><?php echo $_POST['direccion'] ?? ''; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Ciudad</label>
                                            <input type="text" class="form-control" name="ciudad" 
                                                   value="<?php echo $_POST['ciudad'] ?? ''; ?>" 
                                                   placeholder="Ej: Quito">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Provincia</label>
                                            <select class="form-select" name="provincia">
                                                <option value="">Seleccionar provincia...</option>
                                                <option value="Azuay" <?php echo (($_POST['provincia'] ?? '') == 'Azuay') ? 'selected' : ''; ?>>Azuay</option>
                                                <option value="Bolívar" <?php echo (($_POST['provincia'] ?? '') == 'Bolívar') ? 'selected' : ''; ?>>Bolívar</option>
                                                <option value="Cañar" <?php echo (($_POST['provincia'] ?? '') == 'Cañar') ? 'selected' : ''; ?>>Cañar</option>
                                                <option value="Carchi" <?php echo (($_POST['provincia'] ?? '') == 'Carchi') ? 'selected' : ''; ?>>Carchi</option>
                                                <option value="Chimborazo" <?php echo (($_POST['provincia'] ?? '') == 'Chimborazo') ? 'selected' : ''; ?>>Chimborazo</option>
                                                <option value="Cotopaxi" <?php echo (($_POST['provincia'] ?? '') == 'Cotopaxi') ? 'selected' : ''; ?>>Cotopaxi</option>
                                                <option value="El Oro" <?php echo (($_POST['provincia'] ?? '') == 'El Oro') ? 'selected' : ''; ?>>El Oro</option>
                                                <option value="Esmeraldas" <?php echo (($_POST['provincia'] ?? '') == 'Esmeraldas') ? 'selected' : ''; ?>>Esmeraldas</option>
                                                <option value="Galápagos" <?php echo (($_POST['provincia'] ?? '') == 'Galápagos') ? 'selected' : ''; ?>>Galápagos</option>
                                                <option value="Guayas" <?php echo (($_POST['provincia'] ?? '') == 'Guayas') ? 'selected' : ''; ?>>Guayas</option>
                                                <option value="Imbabura" <?php echo (($_POST['provincia'] ?? '') == 'Imbabura') ? 'selected' : ''; ?>>Imbabura</option>
                                                <option value="Loja" <?php echo (($_POST['provincia'] ?? '') == 'Loja') ? 'selected' : ''; ?>>Loja</option>
                                                <option value="Los Ríos" <?php echo (($_POST['provincia'] ?? '') == 'Los Ríos') ? 'selected' : ''; ?>>Los Ríos</option>
                                                <option value="Manabí" <?php echo (($_POST['provincia'] ?? '') == 'Manabí') ? 'selected' : ''; ?>>Manabí</option>
                                                <option value="Morona Santiago" <?php echo (($_POST['provincia'] ?? '') == 'Morona Santiago') ? 'selected' : ''; ?>>Morona Santiago</option>
                                                <option value="Napo" <?php echo (($_POST['provincia'] ?? '') == 'Napo') ? 'selected' : ''; ?>>Napo</option>
                                                <option value="Orellana" <?php echo (($_POST['provincia'] ?? '') == 'Orellana') ? 'selected' : ''; ?>>Orellana</option>
                                                <option value="Pastaza" <?php echo (($_POST['provincia'] ?? '') == 'Pastaza') ? 'selected' : ''; ?>>Pastaza</option>
                                                <option value="Pichincha" <?php echo (($_POST['provincia'] ?? '') == 'Pichincha') ? 'selected' : ''; ?>>Pichincha</option>
                                                <option value="Santa Elena" <?php echo (($_POST['provincia'] ?? '') == 'Santa Elena') ? 'selected' : ''; ?>>Santa Elena</option>
                                                <option value="Santo Domingo de los Tsáchilas" <?php echo (($_POST['provincia'] ?? '') == 'Santo Domingo de los Tsáchilas') ? 'selected' : ''; ?>>Santo Domingo de los Tsáchilas</option>
                                                <option value="Sucumbíos" <?php echo (($_POST['provincia'] ?? '') == 'Sucumbíos') ? 'selected' : ''; ?>>Sucumbíos</option>
                                                <option value="Tungurahua" <?php echo (($_POST['provincia'] ?? '') == 'Tungurahua') ? 'selected' : ''; ?>>Tungurahua</option>
                                                <option value="Zamora Chinchipe" <?php echo (($_POST['provincia'] ?? '') == 'Zamora Chinchipe') ? 'selected' : ''; ?>>Zamora Chinchipe</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label">Código Postal</label>
                                            <input type="text" class="form-control" name="codigo_postal" 
                                                   value="<?php echo $_POST['codigo_postal'] ?? ''; ?>" 
                                                   placeholder="170135">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Información de contacto -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-phone"></i> Información de Contacto
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Teléfono</label>
                                            <input type="text" class="form-control" name="telefono" 
                                                   value="<?php echo $_POST['telefono'] ?? ''; ?>" 
                                                   placeholder="+593-2-2234567">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?php echo $_POST['email'] ?? ''; ?>" 
                                                   placeholder="sucursal@clinica.ec">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Especialidades disponibles -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-md"></i> Especialidades Disponibles
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Seleccione las especialidades que estarán disponibles en esta sucursal:</p>
                                <div class="specialties-container" style="max-height: 400px; overflow-y: auto;">
                                   <?php foreach ($especialidades as $especialidad): ?>
                                       <div class="form-check mb-2">
                                           <input class="form-check-input" type="checkbox" 
                                                  name="especialidades[]" 
                                                  value="<?php echo $especialidad['id_especialidad']; ?>"
                                                  id="esp_<?php echo $especialidad['id_especialidad']; ?>"
                                                  <?php echo in_array($especialidad['id_especialidad'], $_POST['especialidades'] ?? []) ? 'checked' : ''; ?>>
                                           <label class="form-check-label" for="esp_<?php echo $especialidad['id_especialidad']; ?>">
                                               <strong><?php echo $especialidad['nombre_especialidad']; ?></strong>
                                               <?php if ($especialidad['descripcion']): ?>
                                                   <br><small class="text-muted"><?php echo htmlspecialchars($especialidad['descripcion']); ?></small>
                                               <?php endif; ?>
                                               <br><small class="text-info">
                                                   Duración: <?php echo $especialidad['duracion_cita_minutos']; ?> min
                                                   <?php if ($especialidad['permite_virtual']): ?>
                                                       | Virtual: Sí
                                                   <?php endif; ?>
                                               </small>
                                           </label>
                                       </div>
                                       <hr class="my-2">
                                   <?php endforeach; ?>
                               </div>

                               <div class="mt-3">
                                   <button type="button" class="btn btn-sm btn-outline-success me-2" onclick="selectAllSpecialties()">
                                       <i class="fas fa-check-double"></i> Seleccionar Todo
                                   </button>
                                   <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllSpecialties()">
                                       <i class="fas fa-times"></i> Limpiar
                                   </button>
                               </div>
                           </div>
                       </div>
                   </div>
               </div>

               <!-- Botones de acción -->
               <div class="card border-0 shadow-sm">
                   <div class="card-body">
                       <div class="d-flex justify-content-end gap-2">
                           <a href="index.php?action=admin/sucursales" class="btn btn-secondary">
                               <i class="fas fa-times"></i> Cancelar
                           </a>
                           <button type="submit" class="btn btn-primary">
                               <i class="fas fa-save"></i> Crear Sucursal
                           </button>
                       </div>
                   </div>
               </div>
           </form>
       </div>
   </div>
</div>

<style>
   .specialties-container {
       scrollbar-width: thin;
       scrollbar-color: #dee2e6 #f8f9fa;
   }
   
   .specialties-container::-webkit-scrollbar {
       width: 6px;
   }
   
   .specialties-container::-webkit-scrollbar-track {
       background: #f8f9fa;
       border-radius: 3px;
   }
   
   .specialties-container::-webkit-scrollbar-thumb {
       background: #dee2e6;
       border-radius: 3px;
   }
   
   .form-check-label {
       cursor: pointer;
   }
</style>

<script>
   function selectAllSpecialties() {
       const checkboxes = document.querySelectorAll('input[name="especialidades[]"]');
       checkboxes.forEach(checkbox => checkbox.checked = true);
   }
   
   function clearAllSpecialties() {
       const checkboxes = document.querySelectorAll('input[name="especialidades[]"]');
       checkboxes.forEach(checkbox => checkbox.checked = false);
   }
</script>

</body>
</html>