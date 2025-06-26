<?php
require_once 'models/Especialidad.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php?action=dashboard');
    exit;
}

$especialidadModel = new Especialidad();

$especialidadId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Obtener especialidad a editar
$especialidad = $especialidadModel->getEspecialidadById($especialidadId);
if (!$especialidad) {
    header('Location: index.php?action=admin/especialidades');
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'nombre_especialidad' => trim($_POST['nombre_especialidad']),
            'descripcion' => trim($_POST['descripcion']) ?: null,
            'permite_virtual' => isset($_POST['permite_virtual']) ? 1 : 0,
            'permite_presencial' => isset($_POST['permite_presencial']) ? 1 : 0,
            'duracion_cita_minutos' => (int)$_POST['duracion_cita_minutos']
        ];

        // Validaciones básicas
        if (empty($data['nombre_especialidad'])) {
            throw new Exception("El nombre de la especialidad es obligatorio");
        }

        if ($data['duracion_cita_minutos'] < 15 || $data['duracion_cita_minutos'] > 180) {
            throw new Exception("La duración debe estar entre 15 y 180 minutos");
        }

        if (!$data['permite_virtual'] && !$data['permite_presencial']) {
            throw new Exception("Debe permitir al menos una modalidad de consulta");
        }

        $especialidadModel->updateEspecialidad($especialidadId, $data);
        $success = "Especialidad actualizada exitosamente";

        // Recargar datos de la especialidad
        $especialidad = $especialidadModel->getEspecialidadById($especialidadId);

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
                        <i class="fas fa-user-md"></i> Editar Especialidad
                    </h2>
                    <p class="text-muted mb-0">Modificar datos de: <strong><?php echo htmlspecialchars($especialidad['nombre_especialidad']); ?></strong></p>
                </div>
                <div>
                    <a href="index.php?action=admin/especialidades" class="btn btn-outline-secondary">
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
            <form method="POST" id="especialidadForm">
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
                                        <i class="fas fa-stethoscope fa-2x text-white"></i>
                                    </div>
                                    <h6><?php echo htmlspecialchars($especialidad['nombre_especialidad']); ?></h6>
                                    <span class="badge bg-<?php echo $especialidad['activo'] == 1 ? 'success' : 'danger'; ?>">
                                        <?php echo $especialidad['activo'] == 1 ? 'Activa' : 'Inactiva'; ?>
                                    </span>
                                </div>

                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <strong>Descripción:</strong><br>
                                        <small><?php echo htmlspecialchars($especialidad['descripcion'] ?? 'Sin descripción'); ?></small>
                                    </li>
                                    <li class="mb-2">
                                        <strong>Duración:</strong> <?php echo $especialidad['duracion_cita_minutos']; ?> minutos
                                    </li>
                                    <li class="mb-2">
                                        <strong>Modalidades:</strong><br>
                                        <?php if ($especialidad['permite_presencial']): ?>
                                            <span class="badge bg-primary me-1">Presencial</span>
                                        <?php endif; ?>
                                        <?php if ($especialidad['permite_virtual']): ?>
                                            <span class="badge bg-info">Virtual</span>
                                        <?php endif; ?>
                                    </li>
                                    <li class="mb-2">
                                        <strong>Registro:</strong> <?php echo date('d/m/Y', strtotime($especialidad['fecha_creacion'])); ?>
                                    </li>
                                </ul>

                                <!-- Estadísticas -->
                                <div class="mt-3">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="border rounded p-2">
                                                <div class="h5 text-success mb-0">
                                                    <?php echo $especialidadModel->countMedicosByEspecialidad($especialidadId); ?>
                                                </div>
                                                <small class="text-muted">Médicos</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="border rounded p-2">
                                                <div class="h5 text-info mb-0">
                                                    <?php echo $especialidadModel->countSucursalesByEspecialidad($especialidadId); ?>
                                                </div>
                                                <small class="text-muted">Sucursales</small>
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
                                            <label class="form-label">Nombre de la Especialidad <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="nombre_especialidad" 
                                                   value="<?php echo htmlspecialchars($especialidad['nombre_especialidad']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Descripción</label>
                                            <textarea class="form-control" name="descripcion" rows="3"><?php echo htmlspecialchars($especialidad['descripcion'] ?? ''); ?></textarea>
                                            <div class="form-text">Opcional: Descripción que será visible para los pacientes</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Configuración de citas -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-clock"></i> Configuración de Citas
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Duración por Cita <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" name="duracion_cita_minutos" 
                                                       value="<?php echo $especialidad['duracion_cita_minutos']; ?>" 
                                                       min="15" max="180" required>
                                                <span class="input-group-text">minutos</span>
                                            </div>
                                            <div class="form-text">Entre 15 y 180 minutos</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Duración Sugerida</label>
                                        <div class="btn-group w-100" role="group">
                                            <input type="radio" class="btn-check" name="duracion_preset" id="preset_30" value="30">
                                            <label class="btn btn-outline-primary" for="preset_30" onclick="setDuration(30)">30 min</label>
                                            
                                            <input type="radio" class="btn-check" name="duracion_preset" id="preset_45" value="45">
                                            <label class="btn btn-outline-primary" for="preset_45" onclick="setDuration(45)">45 min</label>
                                            
                                            <input type="radio" class="btn-check" name="duracion_preset" id="preset_60" value="60">
                                            <label class="btn btn-outline-primary" for="preset_60" onclick="setDuration(60)">60 min</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modalidades de consulta -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-video"></i> Modalidades de Consulta
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Seleccione las modalidades de consulta que estarán disponibles para esta especialidad:</p>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card border">
                                            <div class="card-body text-center">
                                                <div class="form-check form-switch d-flex justify-content-center mb-3">
                                                    <input class="form-check-input" type="checkbox" name="permite_presencial" 
                                                           id="permite_presencial" value="1" 
                                                           <?php echo $especialidad['permite_presencial'] ? 'checked' : ''; ?>>
                                                </div>
                                                <i class="fas fa-hospital fa-3x text-primary mb-3"></i>
                                                <h6>Consulta Presencial</h6>
                                                <p class="text-muted small">
                                                    El paciente debe asistir físicamente a la sucursal para la consulta médica.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card border">
                                            <div class="card-body text-center">
                                                <div class="form-check form-switch d-flex justify-content-center mb-3">
                                                    <input class="form-check-input" type="checkbox" name="permite_virtual" 
                                                           id="permite_virtual" value="1"
                                                           <?php echo $especialidad['permite_virtual'] ? 'checked' : ''; ?>>
                                                </div>
                                                <i class="fas fa-video fa-3x text-info mb-3"></i>
                                                <h6>Consulta Virtual</h6>
                                                <p class="text-muted small">
                                                    Consulta realizada a través de videollamada desde cualquier ubicación.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Nota:</strong> Debe seleccionar al menos una modalidad de consulta.
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="card border-0 shadow-sm mt-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="index.php?action=admin/especialidades" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Actualizar Especialidad
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
    
    .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
    
    .card.border:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
</style>

<script>
    function setDuration(minutes) {
        document.querySelector('input[name="duracion_cita_minutos"]').value = minutes;
    }
</script>

</body>
</html>