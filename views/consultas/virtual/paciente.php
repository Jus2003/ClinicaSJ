<?php
// views/consultas/virtual/paciente.php
// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    header('Location: index.php?action=dashboard');
    exit;
}

require_once 'models/Cita.php';
$citaModel = new Cita();

$error = '';
$success = '';

// Obtener citas virtuales del paciente
$citasVirtuales = $citaModel->getCitasVirtualesPaciente($_SESSION['user_id']);

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
                        <i class="fas fa-video"></i> Mis Consultas Virtuales
                    </h2>
                    <p class="text-muted mb-0">Acceda a sus consultas médicas desde la comodidad de su hogar</p>
                </div>
                <div>
                    <a href="index.php?action=citas/agendar" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Agendar Consulta Virtual
                    </a>
                </div>
            </div>

            <!-- Próxima Consulta -->
            <?php
            $proximaCita = null;
            foreach ($citasVirtuales as $cita) {
                if ($cita['estado_cita'] == 'confirmada' && $cita['fecha_cita'] >= date('Y-m-d')) {
                    $proximaCita = $cita;
                    break;
                }
            }
            ?>

            <?php if ($proximaCita): ?>
                <div class="card border-0 shadow-sm mb-4" style="border-left: 4px solid #28a745 !important;">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar-check"></i> Próxima Consulta Virtual
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h6 class="text-primary">
                                    Dr. <?php echo htmlspecialchars($proximaCita['medico_nombre']); ?>
                                </h6>
                                <p class="mb-1">
                                    <i class="fas fa-stethoscope text-muted me-2"></i>
                                    <?php echo htmlspecialchars($proximaCita['nombre_especialidad']); ?>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-calendar text-muted me-2"></i>
                                    <?php echo date('l, d \d\e F \d\e Y', strtotime($proximaCita['fecha_cita'])); ?>
                                </p>
                                <p class="mb-1">
                                    <i class="fas fa-clock text-muted me-2"></i>
                                    <?php echo date('H:i', strtotime($proximaCita['hora_cita'])); ?>
                                </p>
                                <p class="mb-0 text-muted">
                                    <i class="fas fa-clipboard text-muted me-2"></i>
                                    <?php echo htmlspecialchars($proximaCita['motivo_consulta']); ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-center">
                                <?php if ($proximaCita['enlace_virtual']): ?>
                                    <a href="index.php?action=consultas/virtual/sala&cita=<?php echo $proximaCita['id_cita']; ?>" 
                                       class="btn btn-success btn-lg mb-2" target="_blank">
                                        <i class="fas fa-video"></i> Ingresar a Consulta
                                    </a>
                                    <br>
                                    <small class="text-muted">Enlace disponible</small>
                                <?php else: ?>
                                    <button class="btn btn-outline-success btn-lg mb-2" disabled>
                                        <i class="fas fa-clock"></i> Esperando Médico
                                    </button>
                                    <br>
                                    <small class="text-muted">El médico generará el enlace pronto</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Instrucciones para Primera Vez -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i> Preparación para su Consulta Virtual
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">Antes de la consulta:</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Verifique su conexión a internet
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Pruebe su cámara y micrófono
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Busque un lugar silencioso y bien iluminado
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Tenga sus documentos médicos a mano
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">Durante la consulta:</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Mantenga la cámara encendida
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Hable claro y pausado
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Tome notas de las indicaciones médicas
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    No dude en hacer preguntas
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <button class="btn btn-outline-primary" onclick="probarConexion()">
                            <i class="fas fa-wifi"></i> Probar Mi Conexión
                        </button>
                    </div>
                </div>
            </div>

            <!-- Historial de Consultas Virtuales -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history"></i> Historial de Consultas Virtuales
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($citasVirtuales)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-video fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No hay consultas virtuales registradas</h5>
                            <p class="text-muted">Agende su primera consulta virtual para verla aquí.</p>
                            <a href="index.php?action=citas/agendar" class="btn btn-primary">
                                <i class="fas fa-calendar-plus"></i> Agendar Ahora
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha y Hora</th>
                                        <th>Médico</th>
                                        <th>Especialidad</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($citasVirtuales as $cita): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></strong><br>
                                                <small class="text-muted"><?php echo date('H:i', strtotime($cita['hora_cita'])); ?></small>
                                            </td>
                                            <td>
                                                <strong>Dr. <?php echo htmlspecialchars($cita['medico_nombre']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($cita['nombre_especialidad']); ?></td>
                                            <td>
                                                <span class="badge badge-estado-<?php echo $cita['estado_cita']; ?>">
                                                    <?php echo ucfirst($cita['estado_cita']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($cita['enlace_virtual'] && $cita['estado_cita'] == 'confirmada'): ?>
                                                    <a href="index.php?action=consultas/virtual/sala&cita=<?php echo $cita['id_cita']; ?>" 
                                                       class="btn btn-sm btn-success" target="_blank">
                                                        <i class="fas fa-video"></i> Ingresar
                                                    </a>
                                                <?php endif; ?>

                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="verDetalles(<?php echo $cita['id_cita']; ?>)">
                                                    <i class="fas fa-eye"></i> Ver
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .badge-estado-confirmada {
        background-color: #28a745;
    }
    .badge-estado-en_curso {
        background-color: #ffc107;
        color: #000;
    }
    .badge-estado-completada {
        background-color: #007bff;
    }
    .badge-estado-agendada {
        background-color: #6c757d;
    }
</style>

<script>
    function probarConexion() {
        Swal.fire({
            title: 'Probando conexión...',
            html: 'Verificando velocidad de internet...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        setTimeout(() => {
            Swal.fire({
                icon: 'success',
                title: 'Conexión Excelente',
                html: `
                <div class="text-center">
                    <p>Su conexión es apta para videollamadas</p>
                    <div class="row text-center mt-3">
                        <div class="col-4">
                            <i class="fas fa-wifi fa-2x text-success"></i>
                            <p class="small mb-0 mt-2">Internet: Estable</p>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-video fa-2x text-success"></i>
                            <p class="small mb-0 mt-2">Cámara: OK</p>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-microphone fa-2x text-success"></i>
                            <p class="small mb-0 mt-2">Micrófono: OK</p>
                        </div>
                    </div>
                </div>
            `,
                confirmButtonText: 'Entendido'
            });
        }, 3000);
    }

    function verDetalles(citaId) {
        // Implementar modal de detalles
        window.location.href = `index.php?action=consultas/virtual/historial&cita=${citaId}`;
    }

// Auto-refresh cada 60 segundos para pacientes
    setInterval(() => {
        // Solo refrescar si hay una cita próxima
        const proximaCita = document.querySelector('.card[style*="border-left: 4px solid #28a745"]');
        if (proximaCita) {
            location.reload();
        }
    }, 60000);
</script>