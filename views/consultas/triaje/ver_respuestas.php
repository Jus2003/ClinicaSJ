<?php
require_once 'models/TriajeModel.php';

// Verificar que sea médico
if ($_SESSION['role_id'] != 2) {
    header('Location: index.php?action=dashboard');
    exit;
}

$triajeModel = new TriajeModel();
$citaId = $_GET['cita_id'] ?? null;

if (!$citaId) {
    header('Location: index.php?action=citas/agenda');
    exit;
}

// Verificar que la cita pertenezca al médico
if (!$triajeModel->verificarPermisosCita($citaId, $_SESSION['user_id'], $_SESSION['role_id'])) {
    header('Location: index.php?action=citas/agenda');
    exit;
}

// Obtener información de la cita
$citaInfo = $triajeModel->getInfoCita($citaId);
$respuestasTriaje = $triajeModel->getRespuestasTriaje($citaId);

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-primary">
                        <i class="fas fa-clipboard-list"></i> Triaje Digital
                    </h2>
                    <p class="text-muted">Respuestas del paciente</p>
                </div>
                <a href="index.php?action=citas/agenda" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver a Agenda
                </a>
            </div>

            <!-- Información de la cita -->
            <?php if ($citaInfo): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-calendar-alt"></i> Información de la Cita
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Paciente:</strong> <?php echo $citaInfo['paciente_nombre']; ?></p>
                                <p><strong>Cédula:</strong> <?php echo $citaInfo['paciente_cedula']; ?></p>
                                <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($citaInfo['fecha_cita'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Hora:</strong> <?php echo date('H:i', strtotime($citaInfo['hora_cita'])); ?></p>
                                <p><strong>Especialidad:</strong> <?php echo $citaInfo['nombre_especialidad']; ?></p>
                                <p><strong>Motivo:</strong> <?php echo $citaInfo['motivo_consulta'] ?? 'No especificado'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Respuestas del triaje -->
            <div class="card border-0 shadow">
                <div class="card-header bg-gradient-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-clipboard-list"></i> Respuestas del Triaje Digital
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($respuestasTriaje)): ?>
                        <?php foreach ($respuestasTriaje as $index => $respuesta): ?>
                            <div class="mb-4 pb-3 <?php echo $index < count($respuestasTriaje) - 1 ? 'border-bottom' : ''; ?>">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold text-primary">
                                            <?php echo ($index + 1) . '. ' . $respuesta['pregunta']; ?>
                                        </label>
                                        <small class="text-muted d-block">
                                            Tipo: <?php echo ucfirst($respuesta['tipo_respuesta']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="bg-light p-3 rounded">
                                            <?php if ($respuesta['tipo_respuesta'] === 'sino'): ?>
                                                <span class="badge <?php echo $respuesta['respuesta'] === 'Sí' ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo $respuesta['respuesta']; ?>
                                                </span>
                                            <?php else: ?>
                                                <?php echo nl2br(htmlspecialchars($respuesta['respuesta'])); ?>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            Respondido el: <?php echo date('d/m/Y H:i', strtotime($respuesta['fecha_respuesta'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Triaje completado por el paciente de forma digital
                            </p>
                        </div>
                        
                    <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-clipboard-question fa-3x mb-3"></i>
                            <p>Este paciente no ha completado el triaje digital.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'views/includes/footer.php'; ?>