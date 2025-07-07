<?php
// views/consultas/virtual/medico.php

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 3) {
    header('Location: index.php?action=dashboard');
    exit;
}

require_once 'models/Cita.php';
require_once 'models/User.php';

$citaModel = new Cita();
$userModel = new User();

$error = '';
$success = '';

// Obtener citas virtuales del médico
$citasVirtuales = $citaModel->getCitasVirtualesMedico($_SESSION['user_id']);

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generar_enlace':
                $citaId = $_POST['cita_id'] ?? 0;
                try {
                    $enlace = $citaModel->generarEnlaceVirtual($citaId);
                    $success = "Enlace de videollamada generado exitosamente.";
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;
                
            case 'iniciar_consulta':
                $citaId = $_POST['cita_id'] ?? 0;
                try {
                    $citaModel->iniciarConsultaVirtual($citaId);
                    $success = "Consulta virtual iniciada.";
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;
        }
        
        // Recargar datos después de la acción
        $citasVirtuales = $citaModel->getCitasVirtualesMedico($_SESSION['user_id']);
    }
}

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-primary">
                        <i class="fas fa-video"></i> Panel de Telemedicina
                    </h2>
                    <p class="text-muted mb-0">Gestione sus consultas virtuales y atienda pacientes remotamente</p>
                </div>
                <div>
                    <a href="index.php?action=consultas/virtual/configuracion" class="btn btn-outline-primary me-2">
                        <i class="fas fa-cog"></i> Configuración
                    </a>
                    <a href="index.php?action=consultas/virtual/historial" class="btn btn-outline-secondary">
                        <i class="fas fa-history"></i> Historial
                    </a>
                </div>
            </div>

            <!-- Alertas -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Estado de Conexión -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm bg-light">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <h6 class="mb-1">
                                        <i class="fas fa-wifi text-success me-2"></i>Estado de Conexión
                                    </h6>
                                    <p class="mb-0 text-muted">
                                        Conectado - Listo para consultas virtuales
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button class="btn btn-sm btn-success" onclick="verificarConexion()">
                                        <i class="fas fa-check-circle"></i> Verificar Conexión
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Citas Virtuales de Hoy -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar-day"></i> Consultas Virtuales de Hoy
                        </h5>
                        <span class="badge bg-light text-primary">
                            <?php echo count(array_filter($citasVirtuales, function($cita) { 
                                return $cita['fecha_cita'] == date('Y-m-d'); 
                            })); ?> citas
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($citasVirtuales)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-video fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No hay consultas virtuales programadas</h5>
                            <p class="text-muted">Las citas virtuales aparecerán aquí cuando sean agendadas.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($citasVirtuales as $cita): ?>
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="card border h-100 cita-card" data-estado="<?php echo $cita['estado_cita']; ?>">
                                        <div class="card-header bg-light">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-user-circle text-primary me-2"></i>
                                                    <?php echo htmlspecialchars($cita['paciente_nombre']); ?>
                                                </h6>
                                                <span class="badge badge-estado-<?php echo $cita['estado_cita']; ?>">
                                                    <?php echo ucfirst($cita['estado_cita']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-2">
                                                <i class="fas fa-calendar text-muted me-2"></i>
                                                <strong><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></strong>
                                            </p>
                                            <p class="mb-2">
                                                <i class="fas fa-clock text-muted me-2"></i>
                                                <strong><?php echo date('H:i', strtotime($cita['hora_cita'])); ?></strong>
                                            </p>
                                            <p class="mb-2">
                                                <i class="fas fa-stethoscope text-muted me-2"></i>
                                                <?php echo htmlspecialchars($cita['nombre_especialidad']); ?>
                                            </p>
                                            <p class="mb-3 text-muted small">
                                                <i class="fas fa-clipboard text-muted me-2"></i>
                                                <?php echo htmlspecialchars(substr($cita['motivo_consulta'], 0, 60)) . (strlen($cita['motivo_consulta']) > 60 ? '...' : ''); ?>
                                            </p>
                                            
                                            <?php if ($cita['enlace_virtual']): ?>
                                                <div class="alert alert-success alert-sm mb-3">
                                                    <i class="fas fa-link me-2"></i>
                                                    <small>Enlace generado</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer bg-light">
                                            <div class="d-flex flex-wrap gap-2">
                                                <?php if ($cita['estado_cita'] == 'confirmada' && !$cita['enlace_virtual']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="generar_enlace">
                                                        <input type="hidden" name="cita_id" value="<?php echo $cita['id_cita']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-link"></i> Generar Enlace
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($cita['enlace_virtual'] && $cita['estado_cita'] == 'confirmada'): ?>
                                                    <a href="index.php?action=consultas/virtual/sala&cita=<?php echo $cita['id_cita']; ?>" 
                                                       class="btn btn-sm btn-success" target="_blank">
                                                        <i class="fas fa-video"></i> Iniciar Consulta
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="verDetallesCita(<?php echo $cita['id_cita']; ?>)">
                                                    <i class="fas fa-eye"></i> Detalles
                                                </button>
                                                
                                                <a href="index.php?action=consultas/virtual/chat&cita=<?php echo $cita['id_cita']; ?>" 
                                                   class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-comments"></i> Chat
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Estadísticas Rápidas -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <i class="fas fa-video fa-2x text-primary mb-3"></i>
                            <h5 class="mb-1"><?php echo count($citasVirtuales); ?></h5>
                            <p class="text-muted mb-0 small">Consultas Virtuales</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <i class="fas fa-clock fa-2x text-warning mb-3"></i>
                            <h5 class="mb-1">
                                <?php echo count(array_filter($citasVirtuales, function($c) { 
                                    return $c['estado_cita'] == 'confirmada'; 
                                })); ?>
                            </h5>
                            <p class="text-muted mb-0 small">Pendientes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
                            <h5 class="mb-1">
                                <?php echo count(array_filter($citasVirtuales, function($c) { 
                                    return $c['estado_cita'] == 'completada'; 
                                })); ?>
                            </h5>
                            <p class="text-muted mb-0 small">Completadas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <i class="fas fa-users fa-2x text-info mb-3"></i>
                            <h5 class="mb-1">
                                <?php echo count(array_unique(array_column($citasVirtuales, 'id_paciente'))); ?>
                            </h5>
                            <p class="text-muted mb-0 small">Pacientes Virtuales</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Detalles de Cita -->
<div class="modal fade" id="modalDetallesCita" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye"></i> Detalles de la Consulta Virtual
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoDetallesCita">
                <!-- Se carga dinámicamente -->
            </div>
        </div>
    </div>
</div>

<style>
.cita-card[data-estado="confirmada"] {
    border-left: 4px solid #28a745;
}
.cita-card[data-estado="en_curso"] {
    border-left: 4px solid #ffc107;
}
.cita-card[data-estado="completada"] {
    border-left: 4px solid #007bff;
}

.badge-estado-confirmada { background-color: #28a745; }
.badge-estado-en_curso { background-color: #ffc107; color: #000; }
.badge-estado-completada { background-color: #007bff; }
.badge-estado-agendada { background-color: #6c757d; }

.alert-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}
</style>

<script>
function verificarConexion() {
    // Simular verificación de conexión
    Swal.fire({
        title: 'Verificando conexión...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    setTimeout(() => {
        Swal.fire({
            icon: 'success',
            title: 'Conexión Exitosa',
            text: 'Su conexión a internet es estable para videollamadas',
            timer: 2000,
            showConfirmButton: false
        });
    }, 2000);
}

function verDetallesCita(citaId) {
    // Cargar detalles de la cita
    fetch(`views/consultas/virtual/api/obtener_detalles.php?cita_id=${citaId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('contenidoDetallesCita').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetallesCita')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'No se pudieron cargar los detalles', 'error');
        });
}

// Auto-refresh cada 30 segundos
setInterval(() => {
    location.reload();
}, 30000);
</script>
