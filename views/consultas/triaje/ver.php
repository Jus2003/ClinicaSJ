<?php
require_once 'models/TriajeModel.php';

// Verificar que sea médico
if ($_SESSION['role_id'] != 3) {
    header('Location: index.php?action=dashboard');
    exit;
}

$triajeModel = new TriajeModel();
$citaSeleccionada = $_GET['cita_id'] ?? null;
$respuestasTriaje = [];
$infoCita = null;

// Si se selecciona una cita específica
if ($citaSeleccionada) {
    // Verificar permisos
    if (!$triajeModel->verificarPermisosCita($citaSeleccionada, $_SESSION['user_id'], $_SESSION['role_id'])) {
        header('Location: index.php?action=consultas/triaje/ver');
        exit;
    }
    
    $respuestasTriaje = $triajeModel->getRespuestasTriaje($citaSeleccionada);
    
    // Obtener información de la cita
    $database = new Database();
    $db = $database->getConnection();
    
    $sql = "SELECT c.*, 
                   CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                   p.cedula as paciente_cedula,
                   p.fecha_nacimiento,
                   p.genero,
                   e.nombre_especialidad
            FROM citas c
            JOIN usuarios p ON c.id_paciente = p.id_usuario
            JOIN especialidades e ON c.id_especialidad = e.id_especialidad
            WHERE c.id_cita = :cita_id AND c.id_medico = :medico_id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        'cita_id' => $citaSeleccionada,
        'medico_id' => $_SESSION['user_id']
    ]);
    $infoCita = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$infoCita) {
        header('Location: index.php?action=consultas/triaje/ver');
        exit;
    }
}

// Obtener todas las citas con triaje
$citasConTriaje = $triajeModel->getCitasConTriaje($_SESSION['user_id']);

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
                        <i class="fas fa-clipboard-list"></i> Triajes de Pacientes
                    </h2>
                    <p class="text-muted mb-0">Revisar información de triaje completada por sus pacientes</p>
                </div>
                <?php if ($citaSeleccionada): ?>
                    <a href="index.php?action=consultas/triaje/ver" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver a Lista
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!$citaSeleccionada): ?>
                <!-- Lista de citas con triaje -->
                <?php if (empty($citasConTriaje)): ?>
                    <div class="card border-0 shadow">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-clipboard text-muted" style="font-size: 4rem;"></i>
                            <h4 class="mt-3 text-muted">No hay triajes disponibles</h4>
                            <p class="text-muted">Cuando sus pacientes completen el triaje digital, aparecerán aquí.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="fas fa-list"></i> Citas con Triaje Completado
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Paciente</th>
                                            <th>Cédula</th>
                                            <th>Fecha Cita</th>
                                            <th>Hora</th>
                                            <th>Triaje Completado</th>
                                            <th>Respuestas</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($citasConTriaje as $cita): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                            <?php echo strtoupper(substr($cita['paciente_nombre'], 0, 1)); ?>
                                                        </div>
                                                        <strong><?php echo $cita['paciente_nombre']; ?></strong>
                                                    </div>
                                                </td>
                                                <td><?php echo $cita['paciente_cedula']; ?></td>
                                                <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
                                                <td><?php echo date('H:i', strtotime($cita['hora_cita'])); ?></td>
                                                <td>
                                                    <span class="badge bg-success">
                                                        <?php echo date('d/m/Y H:i', strtotime($cita['fecha_triaje_completado'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?php echo $cita['total_respuestas']; ?> respuestas</span>
                                                </td>
                                                <td>
                                                    <a href="index.php?action=consultas/triaje/ver&cita_id=<?php echo $cita['id_cita']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i> Ver Triaje
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Detalle del triaje seleccionado -->
                <div class="row">
                    <!-- Información del paciente -->
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 shadow h-100">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-user"></i> Información del Paciente
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <div class="avatar-lg bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2">
                                        <?php echo strtoupper(substr($infoCita['paciente_nombre'], 0, 1)); ?>
                                    </div>
                                    <h5><?php echo $infoCita['paciente_nombre']; ?></h5>
                                    <p class="text-muted mb-0">Cédula: <?php echo $infoCita['paciente_cedula']; ?></p>
                                </div>
                                
                                <hr>
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <small class="text-muted">Fecha de Nacimiento</small>
                                        <div class="fw-bold">
                                            <?php 
                                            if ($infoCita['fecha_nacimiento']) {
                                                $fechaNac = new DateTime($infoCita['fecha_nacimiento']);
                                                $hoy = new DateTime();
                                                $edad = $hoy->diff($fechaNac)->y;
                                                echo date('d/m/Y', strtotime($infoCita['fecha_nacimiento'])) . " ({$edad} años)";
                                            } else {
                                                echo "No especificado";
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <small class="text-muted">Género</small>
                                        <div class="fw-bold"><?php echo ucfirst($infoCita['genero'] ?? 'No especificado'); ?></div>
                                    </div>
                                    <div class="col-12">
                                        <small class="text-muted">Especialidad</small>
                                        <div class="fw-bold"><?php echo $infoCita['nombre_especialidad']; ?></div>
                                    </div>
                                    <div class="col-12">
                                        <small class="text-muted">Fecha de Cita</small>
                                        <div class="fw-bold">
                                            <?php echo date('d/m/Y', strtotime($infoCita['fecha_cita'])); ?> a las 
                                            <?php echo date('H:i', strtotime($infoCita['hora_cita'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Respuestas del triaje -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-clipboard-list"></i> Respuestas del Triaje Digital
                                </h6>
                                <span class="badge bg-success">
                                    Completado: <?php echo date('d/m/Y H:i', strtotime($respuestasTriaje[0]['fecha_respuesta'] ?? '')); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <?php if (empty($respuestasTriaje)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2 text-muted">No hay respuestas de triaje disponibles</h6>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($respuestasTriaje as $index => $respuesta): ?>
                                        <div class="mb-4 <?php echo ($index < count($respuestasTriaje) - 1) ? 'border-bottom pb-3' : ''; ?>">
                                            <div class="d-flex align-items-start">
                                                <div class="flex-shrink-0">
                                                    <span class="badge bg-primary rounded-pill"><?php echo ($index + 1); ?></span>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h6 class="mb-2 text-dark"><?php echo $respuesta['pregunta']; ?></h6>
                                                    
                                                    <div class="response-content">
                                                        <?php if ($respuesta['tipo_pregunta'] === 'numero' && $respuesta['valor_numerico']): ?>
                                                            <div class="d-flex align-items-center">
                                                                <span class="fs-4 fw-bold text-primary me-2">
                                                                    <?php echo $respuesta['valor_numerico']; ?>
                                                                </span>
                                                                <small class="text-muted">
                                                                    <?php 
                                                                    // Añadir contexto para valores numéricos comunes
                                                                    if (stripos($respuesta['pregunta'], 'dolor') !== false) {
                                                                        $valor = (float)$respuesta['valor_numerico'];
                                                                        if ($valor <= 3) echo "(Leve)";
                                                                        elseif ($valor <= 6) echo "(Moderado)";
                                                                        else echo "(Severo)";
                                                                    }
                                                                    ?>
                                                                </small>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="bg-light p-3 rounded">
                                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($respuesta['respuesta'])); ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Indicadores visuales para respuestas importantes -->
                                                    <?php
                                                    $respuestaLower = strtolower($respuesta['respuesta']);
                                                    $alertas = [];
                                                    
                                                    // Detectar respuestas que requieren atención
                                                    if (stripos($respuesta['pregunta'], 'dolor') !== false && 
                                                        $respuesta['valor_numerico'] && $respuesta['valor_numerico'] >= 7) {
                                                        $alertas[] = ['tipo' => 'warning', 'texto' => 'Dolor severo reportado'];
                                                    }
                                                    
                                                    if (stripos($respuestaLower, 'sangre') !== false || 
                                                        stripos($respuestaLower, 'sangrado') !== false) {
                                                        $alertas[] = ['tipo' => 'danger', 'texto' => 'Menciona sangrado'];
                                                    }
                                                    
                                                    if (stripos($respuestaLower, 'dificultad para respirar') !== false || 
                                                        stripos($respuestaLower, 'falta de aire') !== false) {
                                                        $alertas[] = ['tipo' => 'danger', 'texto' => 'Problemas respiratorios'];
                                                    }
                                                    
                                                    foreach ($alertas as $alerta):
                                                    ?>
                                                        <div class="mt-2">
                                                            <span class="badge bg-<?php echo $alerta['tipo']; ?>">
                                                                <i class="fas fa-exclamation-triangle"></i> <?php echo $alerta['texto']; ?>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Resumen y recomendaciones -->
                                    <div class="mt-4 pt-3 border-top">
                                        <h6 class="text-primary">
                                            <i class="fas fa-lightbulb"></i> Notas para la Consulta
                                        </h6>
                                        <div class="bg-info bg-opacity-10 p-3 rounded">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle"></i> 
                                                Revise especialmente las respuestas marcadas con alertas. 
                                                Esta información fue proporcionada por el paciente antes de la consulta.
                                            </small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-clock"></i> 
                                        Tipo: <?php echo ucfirst($respuestasTriaje[0]['tipo_triaje'] ?? 'Digital'); ?>
                                    </small>
                                    <div>
                                        <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
                                            <i class="fas fa-print"></i> Imprimir
                                        </button>
                                        <a href="index.php?action=consultas/atender&cita_id=<?php echo $citaSeleccionada; ?>" 
                                           class="btn btn-sm btn-success">
                                            <i class="fas fa-stethoscope"></i> Iniciar Consulta
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .avatar-sm {
        width: 32px;
        height: 32px;
        font-size: 0.875rem;
    }
    
    .avatar-lg {
        width: 64px;
        height: 64px;
        font-size: 1.5rem;
    }
    
    .response-content {
        font-size: 0.95rem;
        line-height: 1.5;
    }
    
    @media print {
        .btn, .card-footer, .card-header, nav, .navbar {
            display: none !important;
        }
        
        .card {
            border: none !important;
            box-shadow: none !important;
        }
    }
</style>

<?php include 'views/includes/footer.php'; ?>