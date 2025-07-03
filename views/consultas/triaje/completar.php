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
    } else {
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

            <?php if (empty($citaSeleccionada)): ?>
                <!-- Mostrar citas pendientes -->
                <div class="card border-0 shadow">
                    <div class="card-header bg-gradient-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt"></i> Citas Pendientes de Triaje
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($citasPendientes)): ?>
                            <div class="row">
                                <?php foreach ($citasPendientes as $cita): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card border-start border-primary border-4">
                                            <div class="card-body">
                                                <p class="mb-1"><strong>Especialidad:</strong> <?php echo $cita['nombre_especialidad']; ?></p>
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
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <i class="fas fa-calendar-check fa-3x mb-3"></i>
                                <p>No tiene citas pendientes de triaje.</p>
                            </div>
                        <?php endif; ?>
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
                            
                            <div class="text-center mt-4">
                                <a href="index.php?action=consultas/triaje/completar" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Volver a Mis Citas
                                </a>
                            </div>
                        </div>
                        
                    <?php else: ?>
                        <!-- Formulario para completar triaje -->
                        <form method="POST" id="triajeForm">
                            <input type="hidden" name="cita_id" value="<?php echo $citaSeleccionada; ?>">
                            
                            <div class="card-body">
                                <?php if (!empty($preguntas)): ?>
                                    <?php foreach ($preguntas as $index => $pregunta): ?>
                                        <div class="mb-4">
                                            <label class="form-label fw-bold">
                                                <?php echo ($index + 1) . '. ' . $pregunta['pregunta']; ?>
                                                <?php if ($pregunta['obligatoria']): ?>
                                                    <span class="text-danger">*</span>
                                                <?php endif; ?>
                                            </label>
                                            
                                            <?php if ($pregunta['tipo_respuesta'] === 'texto'): ?>
                                                <textarea name="respuestas[<?php echo $pregunta['id_pregunta']; ?>]" 
                                                          class="form-control" 
                                                          rows="3" 
                                                          placeholder="Escriba su respuesta..."
                                                          <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>></textarea>
                                            
                                            <?php elseif ($pregunta['tipo_respuesta'] === 'numero'): ?>
                                                <input type="number" 
                                                       name="respuestas[<?php echo $pregunta['id_pregunta']; ?>]" 
                                                       class="form-control" 
                                                       placeholder="Ingrese un número"
                                                       <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                            
                                            <?php elseif ($pregunta['tipo_respuesta'] === 'fecha'): ?>
                                                <input type="date" 
                                                       name="respuestas[<?php echo $pregunta['id_pregunta']; ?>]" 
                                                       class="form-control"
                                                       <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                            
                                            <?php elseif ($pregunta['tipo_respuesta'] === 'sino'): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" 
                                                           type="radio" 
                                                           name="respuestas[<?php echo $pregunta['id_pregunta']; ?>]" 
                                                           value="Sí" 
                                                           id="si_<?php echo $pregunta['id_pregunta']; ?>"
                                                           <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                                    <label class="form-check-label" for="si_<?php echo $pregunta['id_pregunta']; ?>">
                                                        Sí
                                                    </label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" 
                                                           type="radio" 
                                                           name="respuestas[<?php echo $pregunta['id_pregunta']; ?>]" 
                                                           value="No" 
                                                           id="no_<?php echo $pregunta['id_pregunta']; ?>"
                                                           <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                                    <label class="form-check-label" for="no_<?php echo $pregunta['id_pregunta']; ?>">
                                                        No
                                                    </label>
                                                </div>
                                            
                                            <?php elseif ($pregunta['tipo_respuesta'] === 'seleccion' && !empty($pregunta['opciones'])): ?>
                                                <select name="respuestas[<?php echo $pregunta['id_pregunta']; ?>]" 
                                                        class="form-select"
                                                        <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                                    <option value="">Seleccione una opción</option>
                                                    <?php 
                                                    $opciones = explode(',', $pregunta['opciones']);
                                                    foreach ($opciones as $opcion): ?>
                                                        <option value="<?php echo trim($opcion); ?>"><?php echo trim($opcion); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted">
                                        <i class="fas fa-clipboard-question fa-3x mb-3"></i>
                                        <p>No hay preguntas de triaje configuradas.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($preguntas)): ?>
                                <div class="card-footer text-end">
                                    <a href="index.php?action=consultas/triaje/completar" class="btn btn-secondary me-2">
                                        <i class="fas fa-arrow-left"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Completar Triaje
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>