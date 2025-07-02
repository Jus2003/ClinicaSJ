<?php
require_once 'models/TriajeModel.php';

// Verificar que sea paciente
if ($_SESSION['role_id'] != 4) {
    header('Location: index.php?action=dashboard');
    exit;
}

$triajeModel = new TriajeModel();
$error = '';
$success = '';
$citaSeleccionada = null;

// Si se especifica una cita
$citaId = $_GET['cita_id'] ?? null;

if ($citaId) {
    // Verificar permisos y que la cita exista
    if (!$triajeModel->verificarPermisosCita($citaId, $_SESSION['user_id'], $_SESSION['role_id'])) {
        header('Location: index.php?action=consultas/triaje/completar');
        exit;
    }
    
    // Verificar si ya tiene triaje completado
    if ($triajeModel->tieneTriajeCompletado($citaId)) {
        $success = "El triaje para esta cita ya ha sido completado.";
        $citaSeleccionada = $citaId;
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$success) {
    try {
        $citaId = $_POST['cita_id'];
        $respuestas = $_POST['respuestas'] ?? [];
        
        if (empty($respuestas)) {
            throw new Exception("Debe responder al menos una pregunta");
        }
        
        // Verificar permisos nuevamente
        if (!$triajeModel->verificarPermisosCita($citaId, $_SESSION['user_id'], $_SESSION['role_id'])) {
            throw new Exception("No tiene permisos para esta cita");
        }
        
        $triajeModel->guardarRespuestas($citaId, $respuestas, $_SESSION['user_id'], 'digital');
        $success = "Triaje completado exitosamente. Gracias por completar la información.";
        $citaSeleccionada = $citaId;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener citas pendientes
$citasPendientes = $triajeModel->getCitasPendientesTriaje($_SESSION['user_id']);

// Si hay una cita seleccionada, obtener preguntas
$preguntas = [];
$respuestasExistentes = [];
if ($citaSeleccionada) {
    $preguntas = $triajeModel->getPreguntasActivas();
    $respuestasExistentes = $triajeModel->getRespuestasTriaje($citaSeleccionada);
    
    // Convertir respuestas a array asociativo
    $respuestasArray = [];
    foreach ($respuestasExistentes as $resp) {
        $respuestasArray[$resp['id_pregunta']] = $resp['respuesta'];
    }
    $respuestasExistentes = $respuestasArray;
}

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Header -->
            <div class="text-center mb-4">
                <h2 class="text-primary">
                    <i class="fas fa-clipboard-list"></i> Triaje Digital
                </h2>
                <p class="text-muted">Complete la información médica antes de su consulta</p>
            </div>

            <!-- Mensajes -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($citasPendientes) && !$citaSeleccionada): ?>
                <!-- Sin citas pendientes -->
                <div class="card border-0 shadow">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-calendar-check text-muted" style="font-size: 4rem;"></i>
                        <h4 class="mt-3 text-muted">No hay citas pendientes de triaje</h4>
                        <p class="text-muted">Cuando tenga citas confirmadas, podrá completar el triaje digital aquí.</p>
                        <a href="index.php?action=citas/agendar" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Agendar Nueva Cita
                        </a>
                    </div>
                </div>
                
            <?php elseif (!$citaSeleccionada): ?>
                <!-- Selección de cita -->
                <div class="card border-0 shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt"></i> Seleccione la cita para completar el triaje
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($citasPendientes as $cita): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border-primary">
                                        <div class="card-body">
                                            <h6 class="text-primary"><?php echo $cita['nombre_especialidad']; ?></h6>
                                            <p class="mb-1"><strong>Médico:</strong> <?php echo $cita['medico_nombre']; ?></p>
                                            <p class="mb-1"><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></p>
                                            <p class="mb-1"><strong>Hora:</strong> <?php echo date('H:i', strtotime($cita['hora_cita'])); ?></p>
                                            <p class="mb-3"><strong>Sucursal:</strong> <?php echo $cita['nombre_sucursal']; ?></p>
                                            
                                            <a href="index.php?action=consultas/triaje/completar&cita_id=<?php echo $cita['id_cita']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-clipboard-list"></i> Completar Triaje
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Formulario de triaje -->
                <div class="card border-0 shadow">
                    <div class="card-header bg-gradient-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-list"></i> Formulario de Triaje Digital
                        </h5>
                    </div>
                    
                    <?php if (!empty($respuestasExistentes)): ?>
                        <!-- Mostrar respuestas ya completadas -->
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Este triaje ya ha sido completado. A continuación puede ver sus respuestas.
                            </div>
                            
                            <?php foreach ($preguntas as $pregunta): ?>
                                <div class="mb-4">
                                    <label class="form-label fw-bold"><?php echo $pregunta['pregunta']; ?></label>
                                    <div class="form-control-plaintext bg-light p-2 rounded">
                                        <?php echo $respuestasExistentes[$pregunta['id_pregunta']] ?? 'Sin respuesta'; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="text-center">
                                <a href="index.php?action=consultas/triaje/completar" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver a Mis Citas
                                </a>
                            </div>
                        </div>
                        
                    <?php else: ?>
                        <!-- Formulario para completar -->
                        <form method="POST" id="triajeForm">
                            <input type="hidden" name="cita_id" value="<?php echo $citaSeleccionada; ?>">
                            
                            <div class="card-body">
                                <?php foreach ($preguntas as $index => $pregunta): ?>
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">
                                            <?php echo ($index + 1) . '. ' . $pregunta['pregunta']; ?>
                                            <?php if ($pregunta['requerida']): ?>
                                                <span class="text-danger">*</span>
                                            <?php endif; ?>
                                        </label>
                                        
                                        <?php
                                        $fieldName = "respuestas[{$pregunta['id_pregunta']}]";
                                        $isRequired = $pregunta['requerida'] ? 'required' : '';
                                        
                                        switch ($pregunta['tipo_pregunta']):
                                            case 'texto':
                                        ?>
                                                <input type="text" 
                                                       class="form-control" 
                                                       name="<?php echo $fieldName; ?>"
                                                       <?php echo $isRequired; ?>
                                                       placeholder="Escriba su respuesta...">
                                        <?php
                                                break;
                                            case 'textarea':
                                        ?>
                                                <textarea class="form-control" 
                                                         name="<?php echo $fieldName; ?>"
                                                         rows="3"
                                                         <?php echo $isRequired; ?>
                                                         placeholder="Describa detalladamente..."></textarea>
                                        <?php
                                                break;
                                            case 'numero':
                                        ?>
                                                <input type="number" 
                                                       class="form-control" 
                                                       name="<?php echo $fieldName; ?>"
                                                       <?php echo $isRequired; ?>
                                                       min="0"
                                                       step="0.1"
                                                       placeholder="Ingrese un número...">
                                        <?php
                                                break;
                                            case 'select':
                                                $opciones = json_decode($pregunta['opciones'], true) ?: [];
                                        ?>
                                                <select class="form-select" name="<?php echo $fieldName; ?>" <?php echo $isRequired; ?>>
                                                    <option value="">Seleccione una opción...</option>
                                                    <?php foreach ($opciones as $opcion): ?>
                                                        <option value="<?php echo htmlspecialchars($opcion); ?>">
                                                            <?php echo htmlspecialchars($opcion); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                        <?php
                                                break;
                                            case 'radio':
                                                $opciones = json_decode($pregunta['opciones'], true) ?: [];
                                        ?>
                                                <div class="row">
                                                    <?php foreach ($opciones as $i => $opcion): ?>
                                                        <div class="col-md-6 mb-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input" 
                                                                       type="radio" 
                                                                       name="<?php echo $fieldName; ?>" 
                                                                       id="<?php echo $pregunta['id_pregunta'] . '_' . $i; ?>"
                                                                       value="<?php echo htmlspecialchars($opcion); ?>"
                                                                       <?php echo $isRequired; ?>>
                                                                <label class="form-check-label" 
                                                                       for="<?php echo $pregunta['id_pregunta'] . '_' . $i; ?>">
                                                                    <?php echo htmlspecialchars($opcion); ?>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                        <?php
                                                break;
                                        endswitch;
                                        ?>
                                        
                                        <?php if ($pregunta['ayuda']): ?>
                                            <div class="form-text">
                                                <i class="fas fa-info-circle"></i> <?php echo $pregunta['ayuda']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="card-footer bg-light">
                                <div class="d-flex justify-content-between">
                                    <a href="index.php?action=consultas/triaje/completar" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Volver
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save"></i> Completar Triaje
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('triajeForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Validación adicional si es necesaria
            const requiredFields = form.querySelectorAll('[required]');
            let allValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    allValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!allValid) {
                e.preventDefault();
                alert('Por favor complete todos los campos requeridos');
            }
        });
    }
});
</script>

