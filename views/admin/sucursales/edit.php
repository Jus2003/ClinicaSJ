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

$sucursalId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Obtener sucursal a editar
$sucursal = $sucursalModel->getSucursalById($sucursalId);
if (!$sucursal) {
    header('Location: index.php?action=admin/sucursales');
    exit;
}

// Obtener especialidades de la sucursal
$sucursalEspecialidades = $sucursalModel->getSucursalEspecialidades($sucursalId);
$sucursalEspecialidadesIds = array_column($sucursalEspecialidades, 'id_especialidad');

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

        $sucursalModel->updateSucursal($sucursalId, $data);
        $success = "Sucursal actualizada exitosamente";

        // Recargar datos de la sucursal
        $sucursal = $sucursalModel->getSucursalById($sucursalId);
        $sucursalEspecialidades = $sucursalModel->getSucursalEspecialidades($sucursalId);
        $sucursalEspecialidadesIds = array_column($sucursalEspecialidades, 'id_especialidad');

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
                        <i class="fas fa-building"></i> Editar Sucursal
                    </h2>
                    <p class="text-muted mb-0">Modificar datos de: <strong><?php echo htmlspecialchars($sucursal['nombre_sucursal']); ?></strong></p>
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
                    <!-- Información actual -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle"></i> Información Actual
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <div class="avatar-lg bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2">
                                        <i class="fas fa-building fa-2x text-white"></i>
                                    </div>
                                    <h6><?php echo htmlspecialchars($sucursal['nombre_sucursal']); ?></h6>
                                    <span class="badge bg-<?php echo $sucursal['activo'] == 1 ? 'success' : 'danger'; ?>">
                                        <?php echo $sucursal['activo'] == 1 ? 'Activa' : 'Inactiva'; ?>
                                    </span>
                                </div>

                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <strong>Dirección:</strong><br>
                                        <small><?php echo htmlspecialchars($sucursal['direccion']); ?></small>
                                    </li>
                                    <?php if ($sucursal['telefono']): ?>
                                        <li class="mb-2">
                                            <strong>Teléfono:</strong> <?php echo htmlspecialchars($sucursal['telefono']); ?>
                                        </li>
                                    <?php endif; ?>
                                    <?php if ($sucursal['email']): ?>
                                        <li class="mb-2">
                                            <strong>Email:</strong> <?php echo htmlspecialchars($sucursal['email']); ?>
                                        </li>
                                    <?php endif; ?>
                                    <li class="mb-2">
                                        <strong>Ubicación:</strong> 
                                        <?php echo $sucursal['ciudad']; ?>
                                        <?php if ($sucursal['provincia']): ?>
                                            , <?php echo $sucursal['provincia']; ?>
                                        <?php endif; ?>
                                    </li>
                                    <li class="mb-2">
                                        <strong>Registro:</strong> <?php echo date('d/m/Y', strtotime($sucursal['fecha_creacion'])); ?>
                                    </li>
                                </ul>

                                <!-- Estadísticas -->
                                <div class="mt-3">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="border rounded p-2">
                                                <div class="h5 text-primary mb-0"><?php echo count($sucursalEspecialidades); ?></div>
                                                <small class="text-muted">Especialidades</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="border rounded p-2">
                                                <div class="h5 text-success mb-0">
                                                    <?php 
                                                    $medicos = $sucursalModel->getMedicosBySucursal($sucursalId);
                                                    echo count($medicos);
                                                    ?>
                                                </div>
                                                <small class="text-muted">Médicos</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario de edición -->
                    <div class="col-lg-8">
                        <!-- Información básica -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-edit"></i> Información Básica
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre de la Sucursal <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="nombre_sucursal" 
                                                   value="<?php echo htmlspecialchars($sucursal['nombre_sucursal']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Dirección <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="direccion" rows="3" required><?php echo htmlspecialchars($sucursal['direccion']); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Ciudad</label>
                                            <input type="text" class="form-control" name="ciudad" 
                                                   value="<?php echo htmlspecialchars($sucursal['ciudad'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Provincia</label>
                                            <select class="form-select" name="provincia">
                                                <option value="">Seleccionar provincia...</option>
                                                <?php 
                                                $provincias = ['Azuay', 'Bolívar', 'Cañar', 'Carchi', 'Chimborazo', 'Cotopaxi', 'El Oro', 'Esmeraldas', 'Galápagos', 'Guayas', 'Imbabura', 'Loja', 'Los Ríos', 'Manabí', 'Morona Santiago', 'Napo', 'Orellana', 'Pastaza', 'Pichincha', 'Santa Elena', 'Santo Domingo de los Tsáchilas', 'Sucumbíos', 'Tungurahua', 'Zamora Chinchipe'];
                                                foreach ($provincias as $provincia): 
                                                ?>
                                                    <option value="<?php echo $provincia; ?>" 
                                                            <?php echo ($sucursal['provincia'] == $provincia) ? 'selected' : ''; ?>>
                                                        <?php echo $provincia; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="mb-3">
                                            <label class="form-label">Código Postal</label>
                                            <input type="text" class="form-control" name="codigo_postal" 
                                                   value="<?php echo htmlspecialchars($sucursal['codigo_postal'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Teléfono</label>
                                            <input type="text" class="form-control" name="telefono" 
                                                   value="<?php echo htmlspecialchars($sucursal['telefono'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?php echo htmlspecialchars($sucursal['email'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Especialidades -->
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
                                                   <?php echo in_array($especialidad['id_especialidad'], $sucursalEspecialidadesIds) ? 'checked' : ''; ?>>
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

                        <!-- Botones de acción -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="index.php?action=admin/sucursales" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Actualizar Sucursal
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .avatar-lg {
        width: 80px;
        height: 80px;
    }
    
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