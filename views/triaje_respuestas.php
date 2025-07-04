<?php
require_once 'models/TriajeModel.php';

// Verificar que sea médico o recepcionista
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [2, 3])) {
    header('Location: index.php?action=dashboard');
    exit;
}

$triajeModel = new TriajeModel();
$citaId = $_GET['cita_id'] ?? null;

if (!$citaId) {
    header('Location: index.php?action=citas/agenda');
    exit;
}

// Verificar que la cita pertenezca al usuario
if (!$triajeModel->verificarPermisosCita($citaId, $_SESSION['user_id'], $_SESSION['role_id'])) {
    header('Location: index.php?action=citas/agenda');
    exit;
}

// Obtener información de la cita
$infoCita = $triajeModel->getInfoCita($citaId);
$respuestasTriaje = $triajeModel->getRespuestasTriaje($citaId);

// Calcular edad
$edad = null;
if (isset($infoCita['fecha_nacimiento']) && $infoCita['fecha_nacimiento']) {
    $fechaNac = new DateTime($infoCita['fecha_nacimiento']);
    $hoy = new DateTime();
    $edad = $hoy->diff($fechaNac)->y;
}

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<style>
    .triaje-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 15px;
        color: white;
    }
    
    .patient-info-card {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border-radius: 12px;
        color: white;
    }
    
    .question-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        margin-bottom: 20px;
    }
    
    .question-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .question-number {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 20px;
        flex-shrink: 0;
    }
    
    .answer-box {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-left: 4px solid #007bff;
        border-radius: 8px;
        padding: 15px;
    }
</style>

<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <!-- Header -->
            <div class="text-center mb-4">
                <div class="triaje-card p-4">
                    <h1 class="display-6 mb-3">
                        <i class="fas fa-clipboard-list"></i> Triaje Digital del Paciente
                    </h1>
                    <p class="lead mb-0">Información médica pre-consulta</p>
                </div>
            </div>

            <!-- Botón volver -->
            <div class="mb-4">
                <a href="index.php?action=citas/agenda" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a la Agenda
                </a>
            </div>

            <div class="row">
                <!-- Información del paciente -->
                <div class="col-lg-4">
                    <div class="patient-info-card p-4 mb-4">
                        <h5 class="mb-4">
                            <i class="fas fa-user me-2"></i>
                            Información del Paciente
                        </h5>
                        
                        <div class="mb-3">
                            <small class="opacity-75">Nombre Completo</small>
                            <div class="fw-bold fs-6"><?php echo htmlspecialchars($infoCita['paciente_nombre'] ?? 'N/A'); ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="opacity-75">Cédula</small>
                            <div class="fw-bold"><?php echo htmlspecialchars($infoCita['paciente_cedula'] ?? 'N/A'); ?></div>
                        </div>
                        
                        <?php if ($edad): ?>
                        <div class="mb-3">
                            <small class="opacity-75">Edad</small>
                            <div class="fw-bold"><?php echo $edad; ?> años</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($infoCita['genero']) && $infoCita['genero']): ?>
                        <div class="mb-3">
                            <small class="opacity-75">Género</small>
                            <div class="fw-bold"><?php echo ucfirst($infoCita['genero']); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <small class="opacity-75">Especialidad</small>
                            <div class="fw-bold"><?php echo htmlspecialchars($infoCita['nombre_especialidad'] ?? 'N/A'); ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="opacity-75">Fecha de Cita</small>
                            <div class="fw-bold">
                                <?php echo date('d/m/Y', strtotime($infoCita['fecha_cita'])); ?>
                                <br>
                                <small><?php echo date('H:i', strtotime($infoCita['hora_cita'])); ?></small>
                            </div>
                        </div>
                        
                        <?php if (!empty($respuestasTriaje)): ?>
                        <div class="mt-4 p-3 bg-light rounded text-dark">
                            <small>
                                <i class="fas fa-clock me-1"></i>
                                <strong>Triaje completado:</strong><br>
                                <?php echo date('d/m/Y H:i', strtotime($respuestasTriaje[0]['fecha_respuesta'])); ?>
                            </small>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($infoCita['motivo_consulta']) && $infoCita['motivo_consulta']): ?>
                        <div class="mt-4 p-3 bg-light rounded text-dark">
                            <small class="fw-bold text-primary">Motivo de consulta:</small><br>
                            <small><?php echo htmlspecialchars($infoCita['motivo_consulta']); ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Respuestas del triaje -->
                <div class="col-lg-8">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4>
                            <i class="fas fa-clipboard-list text-primary me-2"></i>
                            Respuestas del Triaje
                        </h4>
                        <span class="badge bg-primary fs-6">
                            <?php echo count($respuestasTriaje); ?> preguntas respondidas
                        </span>
                    </div>
                    
                    <?php if (empty($respuestasTriaje)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-exclamation-circle text-warning" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-muted">Sin respuestas de triaje</h5>
                            <p class="text-muted">El paciente aún no ha completado el triaje digital para esta cita.</p>
                            <a href="index.php?action=citas/agenda" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Volver a la Agenda
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($respuestasTriaje as $index => $respuesta): ?>
                            <div class="question-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start">
                                        <div class="question-number">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="fw-bold text-dark mb-3">
                                                <?php echo htmlspecialchars($respuesta['pregunta']); ?>
                                            </h6>
                                            
                                            <div class="answer-box">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <strong class="text-primary">Respuesta:</strong>
                                                        <div class="mt-1 fs-5">
                                                            <?php echo htmlspecialchars($respuesta['respuesta']); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (isset($respuesta['valor_numerico']) && $respuesta['valor_numerico']): ?>
                                                        <div class="ms-3">
                                                            <span class="badge bg-secondary fs-6">
                                                                Valor: <?php echo $respuesta['valor_numerico']; ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Resumen final -->
                        <div class="card border-success mb-4">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Resumen del Triaje
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Total de preguntas respondidas:</strong></p>
                                        <span class="badge bg-primary"><?php echo count($respuestasTriaje); ?> preguntas</span>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Completado el:</strong></p>
                                        <span class="text-muted">
                                            <?php echo date('d/m/Y \a \l\a\s H:i', strtotime($respuestasTriaje[0]['fecha_respuesta'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botones de acción -->
                        <div class="text-center">
                            <a href="index.php?action=citas/agenda" class="btn btn-outline-secondary btn-lg me-3">
                                <i class="fas fa-arrow-left"></i> Volver a la Agenda
                            </a>
                            
                            <?php if ($_SESSION['role_id'] == 2): // Solo para médicos ?>
                                <a href="index.php?action=consultas/atender&cita_id=<?php echo $citaId; ?>" class="btn btn-success btn-lg">
                                    <i class="fas fa-user-md"></i> Atender Paciente
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>