<?php
// views/pacientes/historial.php
// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

require_once 'models/User.php';
$userModel = new User();

// Determinar qué paciente mostrar
$pacienteId = null;

if ($_SESSION['role_id'] == 4) {
    // Si es paciente, mostrar su propio historial
    $pacienteId = $_SESSION['user_id'];
} else {
    // Si es staff, verificar parámetros
    if (isset($_GET['id'])) {
        $pacienteId = (int) $_GET['id'];
    } else {
        // Mostrar lista de pacientes
        $search = $_GET['search'] ?? '';
        $pacientes = $userModel->getPacientesList($search);
        
        include 'views/includes/header.php';
        include 'views/includes/navbar.php';
        ?>

        <div class="container-fluid mt-4">
            <div class="row">
                <div class="col-12">
                    <!-- Header de selección de pacientes -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="text-primary">
                                <i class="fas fa-file-medical"></i> Historiales Médicos
                            </h2>
                            <p class="text-muted mb-0">Seleccione un paciente para ver su historial médico completo</p>
                        </div>
                    </div>

                    <!-- Búsqueda -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <form method="GET" action="index.php">
                                <input type="hidden" name="action" value="pacientes/historial">
                                <div class="row g-3">
                                    <div class="col-md-10">
                                        <input type="text" class="form-control" name="search" 
                                               placeholder="Buscar por nombre, apellido o cédula..." 
                                               value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-search"></i> Buscar
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Lista de pacientes -->
                    <div class="row" id="pacientesList">
                        <?php foreach ($pacientes as $paciente): ?>
                            <div class="col-md-6 col-lg-4 mb-4 paciente-item">
                                <div class="card h-100 shadow-sm border-0">
                                    <div class="card-body d-flex flex-column">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="avatar-lg bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                <?php echo strtoupper(substr($paciente['nombre'], 0, 1) . substr($paciente['apellido'], 0, 1)); ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($paciente['nombre'] . ' ' . $paciente['apellido']); ?></h6>
                                                <small class="text-muted">CI: <?php echo htmlspecialchars($paciente['cedula']); ?></small>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-auto">
                                            <a href="index.php?action=pacientes/historial&id=<?php echo $paciente['id_usuario']; ?>" 
                                               class="btn btn-primary w-100">
                                                <i class="fas fa-file-medical"></i> Ver Historial
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($pacientes)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No se encontraron pacientes</h5>
                            <p class="text-muted">Intente con otros términos de búsqueda</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php
        include 'views/includes/footer.php';
        exit;
    }
}

// Obtener datos del paciente
$paciente = $userModel->getUserById($pacienteId);
if (!$paciente || $paciente['id_rol'] != 4) {
    if ($_SESSION['role_id'] == 4) {
        header('Location: index.php?action=dashboard');
    } else {
        header('Location: index.php?action=pacientes/historial');
    }
    exit;
}

// Verificar permisos
if ($_SESSION['role_id'] == 4 && $_SESSION['user_id'] != $pacienteId) {
    header('Location: index.php?action=dashboard');
    exit;
}

// Obtener historial médico del paciente
$historial = $userModel->getHistorialPaciente($pacienteId);

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Header del historial individual -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-primary">
                        <i class="fas fa-file-medical"></i> 
                        <?php echo ($_SESSION['role_id'] == 4) ? 'Mi Historial Médico' : 'Historial Médico'; ?>
                    </h2>
                    <p class="text-muted mb-0">
                        <?php if ($_SESSION['role_id'] == 4): ?>
                            Su historial clínico completo
                        <?php else: ?>
                            Historial clínico de: 
                            <strong><?php echo htmlspecialchars($paciente['nombre'] . ' ' . $paciente['apellido']); ?></strong>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <?php if ($_SESSION['role_id'] != 4): ?>
                        <a href="index.php?action=pacientes/historial" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-arrow-left"></i> Volver a Lista
                        </a>
                        <a href="index.php?action=pacientes/editar&id=<?php echo $paciente['id_usuario']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-user-edit"></i> Editar Paciente
                        </a>
                    <?php else: ?>
                        <a href="index.php?action=dashboard" class="btn btn-outline-secondary">
                            <i class="fas fa-home"></i> Ir al Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <!-- Panel lateral con información del paciente -->
                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-user"></i> 
                                <?php echo ($_SESSION['role_id'] == 4) ? 'Mi Información' : 'Información del Paciente'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div class="avatar-xl bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                                    <i class="fas fa-user fa-2x text-white"></i>
                                </div>
                                <h5><?php echo htmlspecialchars($paciente['nombre'] . ' ' . $paciente['apellido']); ?></h5>
                                <span class="badge bg-<?php echo $paciente['activo'] ? 'success' : 'danger'; ?> fs-6">
                                    <?php echo $paciente['activo'] ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </div>

                            <div class="patient-info">
                                <div class="info-item mb-3">
                                    <label class="form-label text-muted small mb-1">Cédula de Identidad</label>
                                    <div class="fw-bold"><?php echo htmlspecialchars($paciente['cedula']); ?></div>
                                </div>

                                <div class="info-item mb-3">
                                    <label class="form-label text-muted small mb-1">Email</label>
                                    <div><?php echo htmlspecialchars($paciente['email']); ?></div>
                                </div>

                                <?php if ($paciente['telefono']): ?>
                                    <div class="info-item mb-3">
                                        <label class="form-label text-muted small mb-1">Teléfono</label>
                                        <div><?php echo htmlspecialchars($paciente['telefono']); ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($paciente['fecha_nacimiento']): ?>
                                    <div class="info-item mb-3">
                                        <label class="form-label text-muted small mb-1">Fecha de Nacimiento</label>
                                        <div>
                                            <?php 
                                            $fechaNac = new DateTime($paciente['fecha_nacimiento']);
                                            $hoy = new DateTime();
                                            $edad = $hoy->diff($fechaNac)->y;
                                            echo $fechaNac->format('d/m/Y') . " ($edad años)";
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($paciente['genero']): ?>
                                    <div class="info-item mb-3">
                                        <label class="form-label text-muted small mb-1">Género</label>
                                        <div><?php echo ucfirst(htmlspecialchars($paciente['genero'])); ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($paciente['direccion']): ?>
                                    <div class="info-item mb-3">
                                        <label class="form-label text-muted small mb-1">Dirección</label>
                                        <div><?php echo htmlspecialchars($paciente['direccion']); ?></div>
                                    </div>
                                <?php endif; ?>

                                <div class="info-item">
                                    <label class="form-label text-muted small mb-1">Registrado</label>
                                    <div><?php echo date('d/m/Y', strtotime($paciente['fecha_registro'])); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Estadísticas rápidas -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-chart-pie"></i> Resumen Médico
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php
                            $totalCitas = count($historial);
                            $citasCompletadas = count(array_filter($historial, function($h) { return $h['estado_cita'] == 'completada'; }));
                            $ultimaCita = !empty($historial) ? $historial[0]['fecha_cita'] : null;
                            ?>
                            
                            <div class="stats-grid">
                                <div class="stat-item text-center mb-3">
                                    <div class="stat-value text-primary fs-2 fw-bold"><?php echo $totalCitas; ?></div>
                                    <div class="stat-label text-muted small">Total de Citas</div>
                                </div>
                                
                                <div class="stat-item text-center mb-3">
                                    <div class="stat-value text-success fs-2 fw-bold"><?php echo $citasCompletadas; ?></div>
                                    <div class="stat-label text-muted small">Consultas Completadas</div>
                                </div>
                                
                                <?php if ($ultimaCita): ?>
                                    <div class="stat-item text-center">
                                        <div class="stat-value text-info fs-6 fw-bold">
                                            <?php echo date('d/m/Y', strtotime($ultimaCita)); ?>
                                        </div>
                                        <div class="stat-label text-muted small">Última Consulta</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Columna principal con el historial -->
                <div class="col-xl-8">
                    
                    <!-- Timeline del historial médico -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history"></i> Historial de Consultas Médicas
                                </h5>
                                <span class="badge bg-light text-dark">
                                    <?php echo count($historial); ?> registro(s)
                                </span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($historial)): ?>
                                <div class="timeline-container p-4">
                                    <?php foreach ($historial as $index => $consulta): ?>
                                        <div class="timeline-item mb-4">
                                            <!-- Marcador de la timeline -->
                                            <div class="timeline-marker bg-<?php echo $consulta['estado_cita'] == 'completada' ? 'success' : ($consulta['estado_cita'] == 'en_curso' ? 'warning' : 'secondary'); ?>">
                                                <i class="fas fa-<?php echo $consulta['tipo_cita'] == 'virtual' ? 'video' : 'hospital'; ?>"></i>
                                            </div>
                                            
                                            <!-- Contenido de la consulta -->
                                            <div class="timeline-content">
                                                <div class="card border-0 shadow-sm">
                                                    <!-- Header de la consulta -->
                                                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <h6 class="mb-1 text-primary">
                                                                <i class="fas fa-calendar me-2"></i>
                                                                <?php echo date('d/m/Y', strtotime($consulta['fecha_cita'])); ?> 
                                                                - <?php echo date('H:i', strtotime($consulta['hora_cita'])); ?>
                                                            </h6>
                                                            <small class="text-muted">
                                                                <i class="fas fa-user-md me-1"></i>
                                                                Dr. <?php echo htmlspecialchars($consulta['medico_nombre']); ?> 
                                                                - <?php echo htmlspecialchars($consulta['nombre_especialidad']); ?>
                                                            </small>
                                                        </div>
                                                        <div class="d-flex gap-2">
                                                            <span class="badge bg-<?php 
                                                                echo $consulta['estado_cita'] == 'completada' ? 'success' : 
                                                                    ($consulta['estado_cita'] == 'en_curso' ? 'warning' : 
                                                                    ($consulta['estado_cita'] == 'agendada' ? 'info' : 'secondary')); 
                                                            ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $consulta['estado_cita'])); ?>
                                                            </span>
                                                            <span class="badge bg-<?php echo $consulta['tipo_cita'] == 'virtual' ? 'info' : 'primary'; ?>">
                                                                <i class="fas fa-<?php echo $consulta['tipo_cita'] == 'virtual' ? 'video' : 'hospital'; ?> me-1"></i>
                                                                <?php echo ucfirst($consulta['tipo_cita']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="card-body">
                                                        <!-- Motivo de Consulta -->
                                                        <div class="consultation-section mb-3">
                                                            <h6 class="section-title text-warning">
                                                                <i class="fas fa-stethoscope me-2"></i>Motivo de Consulta
                                                            </h6>
                                                            <p class="section-content text-muted mb-0">
                                                                <?php echo htmlspecialchars($consulta['motivo_consulta']); ?>
                                                            </p>
                                                        </div>

                                                        <?php if ($consulta['id_consulta'] && $consulta['estado_cita'] == 'completada'): ?>
                                                            <!-- Diagnóstico -->
                                                            <?php if (!empty($consulta['diagnostico_principal'])): ?>
                                                                <div class="consultation-section mb-3">
                                                                    <h6 class="section-title text-success">
                                                                        <i class="fas fa-diagnoses me-2"></i>Diagnóstico
                                                                    </h6>
                                                                    <div class="section-content">
                                                                        <p class="mb-1">
                                                                            <strong>Principal:</strong> 
                                                                            <?php echo htmlspecialchars($consulta['diagnostico_principal']); ?>
                                                                        </p>
                                                                        <?php if (!empty($consulta['diagnosticos_secundarios'])): ?>
                                                                            <p class="mb-0">
                                                                                <strong>Secundarios:</strong> 
                                                                                <?php echo htmlspecialchars($consulta['diagnosticos_secundarios']); ?>
                                                                            </p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- Tratamiento -->
                                                            <?php if (!empty($consulta['tratamiento'])): ?>
                                                                <div class="consultation-section mb-3">
                                                                    <h6 class="section-title text-info">
                                                                        <i class="fas fa-pills me-2"></i>Tratamiento
                                                                    </h6>
                                                                    <p class="section-content text-muted mb-0">
                                                                        <?php echo htmlspecialchars($consulta['tratamiento']); ?>
                                                                    </p>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- Recetas Médicas -->
                                                            <?php if ($consulta['total_recetas'] > 0): ?>
                                                                <div class="consultation-section mb-3">
                                                                    <h6 class="section-title text-primary">
                                                                        <i class="fas fa-prescription me-2"></i>
                                                                        Recetas Médicas 
                                                                        <span class="badge bg-primary ms-2"><?php echo $consulta['total_recetas']; ?></span>
                                                                    </h6>
                                                                    
                                                                    <div class="d-flex gap-2 flex-wrap">
                                                                        <button class="btn btn-outline-primary btn-sm" 
                                                                                onclick="cargarRecetas(<?php echo $consulta['id_consulta']; ?>)"
                                                                                data-bs-toggle="modal" 
                                                                                data-bs-target="#modalRecetas">
                                                                            <i class="fas fa-eye"></i> Ver Recetas
                                                                        </button>
                                                                        
                                                                        <?php if (in_array($_SESSION['role_id'], [1, 2, 3])): // Admin, recepcionista, médico ?>
                                                                            <a href="index.php?action=consultas/recetas/gestionar&consulta_id=<?php echo $consulta['id_consulta']; ?>" 
                                                                               class="btn btn-outline-success btn-sm">
                                                                                <i class="fas fa-prescription"></i> Gestionar
                                                                            </a>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- Síntomas (si existen) -->
                                                            <?php if (!empty($consulta['sintomas'])): ?>
                                                                <div class="consultation-section mb-3">
                                                                    <h6 class="section-title text-warning">
                                                                        <i class="fas fa-thermometer-half me-2"></i>Síntomas Reportados
                                                                    </h6>
                                                                    <p class="section-content text-muted mb-0">
                                                                        <?php echo htmlspecialchars($consulta['sintomas']); ?>
                                                                    </p>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- Examen Físico (si existe) -->
                                                            <?php if (!empty($consulta['examen_fisico'])): ?>
                                                                <div class="consultation-section mb-3">
                                                                    <h6 class="section-title text-secondary">
                                                                        <i class="fas fa-user-check me-2"></i>Examen Físico
                                                                    </h6>
                                                                    <p class="section-content text-muted mb-0">
                                                                        <?php echo htmlspecialchars($consulta['examen_fisico']); ?>
                                                                    </p>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- Recomendaciones -->
                                                            <?php if (!empty($consulta['recomendaciones'])): ?>
                                                                <div class="consultation-section mb-3">
                                                                    <h6 class="section-title text-info">
                                                                        <i class="fas fa-lightbulb me-2"></i>Recomendaciones
                                                                    </h6>
                                                                    <p class="section-content text-muted mb-0">
                                                                        <?php echo htmlspecialchars($consulta['recomendaciones']); ?>
                                                                    </p>
                                                                </div>
                                                            <?php endif; ?>

                                                            <!-- Observaciones Médicas -->
                                                            <?php if (!empty($consulta['observaciones_medicas'])): ?>
                                                                <div class="consultation-section mb-3">
                                                                    <h6 class="section-title text-secondary">
                                                                        <i class="fas fa-notes-medical me-2"></i>Observaciones Médicas
                                                                    </h6>
                                                                    <p class="section-content text-muted mb-0">
                                                                        <?php echo htmlspecialchars($consulta['observaciones_medicas']); ?>
                                                                    </p>
                                                                </div>
                                                            <?php endif; ?>

                                                        <?php else: ?>
                                                            <!-- Si no hay consulta completada -->
                                                            <div class="alert alert-info border-0 mb-0">
                                                                <div class="d-flex align-items-center">
                                                                    <i class="fas fa-info-circle fa-lg me-3"></i>
                                                                    <div>
                                                                        <?php if (in_array($consulta['estado_cita'], ['agendada', 'confirmada'])): ?>
                                                                            <strong>Cita pendiente</strong><br>
                                                                            <small class="text-muted">Los detalles médicos aparecerán después de completar la consulta</small>
                                                                        <?php elseif ($consulta['estado_cita'] == 'en_curso'): ?>
                                                                            <strong>Consulta en curso</strong><br>
                                                                            <small class="text-muted">La consulta está siendo realizada en este momento</small>
                                                                        <?php else: ?>
                                                                            <strong>Consulta no completada</strong><br>
                                                                            <small class="text-muted">Estado: <?php echo ucfirst(str_replace('_', ' ', $consulta['estado_cita'])); ?></small>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Footer con información adicional -->
                                                    <div class="card-footer bg-light text-muted small">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <i class="fas fa-building me-1"></i>
                                                                <?php echo htmlspecialchars($consulta['nombre_sucursal']); ?>
                                                            </div>
                                                            <?php if ($consulta['fecha_consulta']): ?>
                                                                <div>
                                                                    <i class="fas fa-clock me-1"></i>
                                                                    Registrado: <?php echo date('d/m/Y H:i', strtotime($consulta['fecha_consulta'])); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <!-- Mensaje si no hay historial -->
                                <div class="text-center py-5">
                                    <i class="fas fa-file-medical fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">No hay historial médico disponible</h5>
                                    <p class="text-muted mb-4">
                                        <?php if ($_SESSION['role_id'] == 4): ?>
                                            Aún no has tenido consultas médicas registradas en el sistema.
                                        <?php else: ?>
                                            Este paciente no tiene consultas médicas registradas en el sistema.
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($_SESSION['role_id'] == 4): ?>
                                        <a href="index.php?action=citas/agendar" class="btn btn-primary">
                                            <i class="fas fa-calendar-plus"></i> Agendar Primera Cita
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Ver Recetas -->
<div class="modal fade" id="modalRecetas" tabindex="-1" aria-labelledby="modalRecetasLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalRecetasLabel">
                    <i class="fas fa-prescription me-2"></i>Recetas Médicas
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="contenidoRecetas">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-3 text-muted">Cargando recetas médicas...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* ================================
   ESTILOS PARA EL HISTORIAL MÉDICO
================================ */

/* Timeline Styles */
.timeline-container {
    position: relative;
}

.timeline-container::before {
    content: '';
    position: absolute;
    left: 30px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #007bff, #28a745);
    z-index: 1;
}

.timeline-item {
    position: relative;
    padding-left: 80px;
}

.timeline-marker {
    position: absolute;
    left: 15px;
    top: 15px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.875rem;
    z-index: 2;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.timeline-content {
    margin-bottom: 2rem;
}

/* Card Enhancements */
.card {
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

/* Section Styles */
.consultation-section {
    border-left: 3px solid #e9ecef;
    padding-left: 1rem;
    margin-bottom: 1rem;
}

.section-title {
    font-size: 0.95rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.section-content {
    font-size: 0.9rem;
    line-height: 1.5;
}

/* Consultation Section Color Coding */
.consultation-section:has(.text-warning) {
    border-left-color: #ffc107;
}

.consultation-section:has(.text-success) {
    border-left-color: #28a745;
}

.consultation-section:has(.text-info) {
    border-left-color: #17a2b8;
}

.consultation-section:has(.text-primary) {
    border-left-color: #007bff;
}

.consultation-section:has(.text-secondary) {
    border-left-color: #6c757d;
}

/* Avatar Styles */
.avatar-xl {
    width: 80px;
    height: 80px;
    font-size: 1.5rem;
}

.avatar-lg {
    width: 60px;
    height: 60px;
    font-size: 1.25rem;
}

/* Patient Info Styles */
.patient-info .info-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid #f8f9fa;
}

.patient-info .info-item:last-child {
    border-bottom: none;
}

/* Stats Grid */
.stats-grid .stat-item {
    padding: 1rem;
    border-radius: 8px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    margin-bottom: 0.5rem;
}

.stat-value {
    line-height: 1;
}

/* Badge Enhancements */
.badge {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
}

/* Alert Improvements */
.alert {
    border: none;
    border-radius: 10px;
}

.alert-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #b8e6f0 100%);
    color: #0c5460;
}

/* Button Improvements */
.btn {
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn:hover {
    transform: translateY(-1px);
}

/* Modal Enhancements */
.modal-xl {
    max-width: 1200px;
}

.modal-content {
    border: none;
    border-radius: 12px;
    overflow: hidden;
}

.modal-header {
    border: none;
}

/* Responsive Design */
@media (max-width: 768px) {
    .timeline-container::before {
        left: 20px;
    }
    
    .timeline-marker {
        left: 5px;
        width: 25px;
        height: 25px;
        font-size: 0.75rem;
    }
    
    .timeline-item {
        padding-left: 60px;
    }
    
    .avatar-xl {
        width: 60px;
        height: 60px;
        font-size: 1.25rem;
    }
    
    .d-flex.gap-2 {
        flex-direction: column;
        gap: 0.5rem !important;
    }
    
    .btn-sm {
        font-size: 0.8rem;
        padding: 0.4rem 0.8rem;
    }
}

/* Loading Animation */
.spinner-border {
    width: 3rem;
    height: 3rem;
}

/* Receta Card Styles */
.receta-card {
    transition: all 0.3s ease;
    border: 1px solid #e9ecef;
}

.receta-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.receta-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

/* Estado de Recetas */
.estado-activa { background-color: #d4edda; color: #155724; }
.estado-dispensada { background-color: #d1ecf1; color: #0c5460; }
.estado-vencida { background-color: #f8d7da; color: #721c24; }
.estado-cancelada { background-color: #e2e3e5; color: #383d41; }

/* Print Styles */
@media print {
    .timeline-container::before {
        display: none;
    }
    
    .timeline-marker {
        position: relative;
        left: 0;
        margin-bottom: 1rem;
    }
    
    .timeline-item {
        padding-left: 0;
        page-break-inside: avoid;
    }
    
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
    }
    
    .btn {
        display: none;
    }
}

/* Smooth Animations */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Focus States for Accessibility */
.btn:focus,
.card:focus {
    outline: 2px solid #007bff;
    outline-offset: 2px;
}

/* Dark Mode Support (Optional) */
@media (prefers-color-scheme: dark) {
    .bg-light {
        background-color: #343a40 !important;
    }
    
    .text-muted {
        color: #adb5bd !important;
    }
    
    .card {
        background-color: #495057;
        color: #fff;
    }
}
</style>

<script>
/* ================================
   JAVASCRIPT PARA EL HISTORIAL
================================ */

document.addEventListener('DOMContentLoaded', function() {
    // Agregar animaciones fade-in a las cards
    const cards = document.querySelectorAll('.timeline-item .card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Mejorar tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

/**
 * Cargar recetas de una consulta específica
 */
function cargarRecetas(consultaId) {
    const contenido = document.getElementById('contenidoRecetas');
    
    // Mostrar loading
    contenido.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p class="mt-3 text-muted">Cargando recetas médicas...</p>
        </div>
    `;
    
    // Hacer petición AJAX
    fetch(`views/api/obtener-recetas.php?consulta_id=${consultaId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.recetas && data.recetas.length > 0) {
                mostrarRecetas(data.recetas);
            } else {
                mostrarMensajeVacio();
            }
        })
        .catch(error => {
            console.error('Error al cargar recetas:', error);
            mostrarError(error.message);
        });
}

/**
 * Mostrar las recetas en el modal
 */
function mostrarRecetas(recetas) {
    let html = '';
    
    recetas.forEach((receta, index) => {
        const estadoColor = {
            'activa': 'success',
            'dispensada': 'info', 
            'vencida': 'danger',
            'cancelada': 'secondary'
        };
        
        const estadoTexto = {
            'activa': 'Activa',
            'dispensada': 'Dispensada', 
            'vencida': 'Vencida',
            'cancelada': 'Cancelada'
        };
        
        html += `
            <div class="card receta-card border-0 shadow-sm mb-3 fade-in" style="animation-delay: ${index * 0.1}s">
                <div class="card-header receta-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-pills fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 text-primary fw-bold">${escapeHtml(receta.medicamento)}</h6>
                            <small class="text-muted">${escapeHtml(receta.codigo_receta)}</small>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-${estadoColor[receta.estado] || 'secondary'} fs-6">
                            <i class="fas fa-${receta.estado === 'activa' ? 'check' : receta.estado === 'dispensada' ? 'hand-holding-medical' : receta.estado === 'vencida' ? 'clock' : 'times'}"></i>
                            ${estadoTexto[receta.estado] || receta.estado.toUpperCase()}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="info-group">
                                <label class="form-label text-muted small mb-1">Concentración</label>
                                <div class="fw-semibold">${escapeHtml(receta.concentracion || 'No especificada')}</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-group">
                                <label class="form-label text-muted small mb-1">Forma Farmacéutica</label>
                                <div class="fw-semibold">${escapeHtml(receta.forma_farmaceutica || 'No especificada')}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group">
                                <label class="form-label text-muted small mb-1">Dosis</label>
                                <div class="fw-semibold text-primary">${escapeHtml(receta.dosis)}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group">
                                <label class="form-label text-muted small mb-1">Frecuencia</label>
                                <div class="fw-semibold text-success">${escapeHtml(receta.frecuencia)}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="info-group">
                                <label class="form-label text-muted small mb-1">Duración</label>
                                <div class="fw-semibold text-info">${escapeHtml(receta.duracion)}</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="info-group">
                                <label class="form-label text-muted small mb-1">Cantidad Prescrita</label>
                                <div class="fw-semibold">${escapeHtml(receta.cantidad)}</div>
                            </div>
                        </div>
                        
                        ${receta.indicaciones_especiales ? `
                            <div class="col-12">
                                <div class="info-group">
                                    <label class="form-label text-muted small mb-1">Indicaciones Especiales</label>
                                    <div class="alert alert-warning border-0 mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        ${escapeHtml(receta.indicaciones_especiales)}
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="mt-3 pt-3 border-top">
                        <div class="row text-muted small">
                            <div class="col-md-6">
                                <i class="fas fa-calendar me-1 text-primary"></i>
                                <strong>Emitida:</strong> ${formatearFecha(receta.fecha_emision)}
                            </div>
                            ${receta.fecha_vencimiento ? `
                                <div class="col-md-6">
                                    <i class="fas fa-calendar-times me-1 text-warning"></i>
                                    <strong>Vence:</strong> ${formatearFecha(receta.fecha_vencimiento)}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    document.getElementById('contenidoRecetas').innerHTML = html;
}

/**
 * Mostrar mensaje cuando no hay recetas
 */
function mostrarMensajeVacio() {
    document.getElementById('contenidoRecetas').innerHTML = `
        <div class="text-center py-5">
            <i class="fas fa-prescription fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">No hay recetas médicas</h5>
            <p class="text-muted mb-0">No se encontraron recetas para esta consulta.</p>
        </div>
    `;
}

/**
 * Mostrar mensaje de error
 */
function mostrarError(mensaje) {
    document.getElementById('contenidoRecetas').innerHTML = `
        <div class="alert alert-danger border-0" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-lg me-3"></i>
                <div>
                    <strong>Error al cargar recetas</strong><br>
                    <small>${escapeHtml(mensaje)}</small>
                </div>
            </div>
        </div>
    `;
}

/**
 * Formatear fecha para mostrar
 */
function formatearFecha(fechaString) {
    const fecha = new Date(fechaString);
    return fecha.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit', 
        year: 'numeric'
    });
}

/**
 * Escapar HTML para prevenir XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
}
</script>

<?php
include 'views/includes/footer.php';
?>