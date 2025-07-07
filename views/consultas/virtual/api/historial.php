<?php
// views/consultas/virtual/historial.php

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [3, 4])) {
    header('Location: index.php?action=dashboard');
    exit;
}

require_once 'models/Cita.php';
$citaModel = new Cita();

$error = '';
$success = '';

// Filtros
$buscar = $_GET['buscar'] ?? '';
$estado_filter = $_GET['estado'] ?? 'todas';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Obtener historial según el rol
if ($_SESSION['role_id'] == 3) { // Médico
    $historial = $citaModel->getHistorialVirtualMedico($_SESSION['user_id'], $buscar, $estado_filter, $fecha_desde, $fecha_hasta, $limit, $offset);
    $total = $citaModel->countHistorialVirtualMedico($_SESSION['user_id'], $buscar, $estado_filter, $fecha_desde, $fecha_hasta);
} else { // Paciente
    $historial = $citaModel->getHistorialVirtualPaciente($_SESSION['user_id'], $buscar, $estado_filter, $fecha_desde, $fecha_hasta, $limit, $offset);
    $total = $citaModel->countHistorialVirtualPaciente($_SESSION['user_id'], $buscar, $estado_filter, $fecha_desde, $fecha_hasta);
}

$totalPages = ceil($total / $limit);

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
                        <i class="fas fa-history"></i> Historial de Telemedicina
                    </h2>
                    <p class="text-muted mb-0">
                        <?php if ($_SESSION['role_id'] == 3): ?>
                            Historial completo de sus consultas virtuales realizadas
                        <?php else: ?>
                            Historial de sus consultas médicas virtuales
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <a href="index.php?action=consultas/virtual" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-filter"></i> Filtros de Búsqueda
                    </h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <input type="hidden" name="action" value="consultas/virtual/historial">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Buscar</label>
                                <input type="text" class="form-control" name="buscar" 
                                       value="<?php echo htmlspecialchars($buscar); ?>"
                                       placeholder="<?php echo $_SESSION['role_id'] == 3 ? 'Paciente, cédula...' : 'Médico, especialidad...'; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="estado">
                                    <option value="todas" <?php echo $estado_filter == 'todas' ? 'selected' : ''; ?>>Todas</option>
                                    <option value="completada" <?php echo $estado_filter == 'completada' ? 'selected' : ''; ?>>Completadas</option>
                                    <option value="cancelada" <?php echo $estado_filter == 'cancelada' ? 'selected' : ''; ?>>Canceladas</option>
                                    <option value="no_asistio" <?php echo $estado_filter == 'no_asistio' ? 'selected' : ''; ?>>No asistió</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Desde</label>
                                <input type="date" class="form-control" name="fecha_desde" 
                                       value="<?php echo $fecha_desde; ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Hasta</label>
                                <input type="date" class="form-control" name="fecha_hasta" 
                                       value="<?php echo $fecha_hasta; ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                                <a href="index.php?action=consultas/virtual/historial" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Limpiar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Estadísticas Rápidas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <i class="fas fa-video fa-2x text-primary mb-2"></i>
                            <h5 class="mb-1"><?php echo $total; ?></h5>
                            <p class="text-muted mb-0 small">Total Consultas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h5 class="mb-1">
                                <?php echo count(array_filter($historial, function($h) { 
                                    return $h['estado_cita'] == 'completada'; 
                                })); ?>
                            </h5>
                            <p class="text-muted mb-0 small">Completadas</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <i class="fas fa-calendar-month fa-2x text-info mb-2"></i>
                            <h5 class="mb-1">
                                <?php 
                                $esteMes = count(array_filter($historial, function($h) { 
                                    return date('Y-m', strtotime($h['fecha_cita'])) == date('Y-m'); 
                                }));
                                echo $esteMes;
                                ?>
                            </h5>
                            <p class="text-muted mb-0 small">Este Mes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h5 class="mb-1">
                                <?php 
                                // Calcular duración promedio (estimada en 30 min por consulta completada)
                                $completadas = count(array_filter($historial, function($h) { 
                                    return $h['estado_cita'] == 'completada'; 
                                }));
                                echo $completadas * 30;
                                ?>
                            </h5>
                            <p class="text-muted mb-0 small">Min. Totales</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Historial -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list"></i> Historial de Consultas Virtuales
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($historial)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No se encontraron consultas virtuales</h5>
                            <p class="text-muted">Modifique los filtros o verifique las fechas seleccionadas.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha y Hora</th>
                                        <?php if ($_SESSION['role_id'] == 3): ?>
                                            <th>Paciente</th>
                                        <?php else: ?>
                                            <th>Médico</th>
                                        <?php endif; ?>
                                        <th>Especialidad</th>
                                        <th>Estado</th>
                                        <th>Duración</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historial as $consulta): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo date('d/m/Y', strtotime($consulta['fecha_cita'])); ?></strong>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('H:i', strtotime($consulta['hora_cita'])); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($_SESSION['role_id'] == 3): ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($consulta['paciente_nombre']); ?></strong>
                                                    </div>
                                                    <?php if (!empty($consulta['paciente_cedula'])): ?>
                                                        <small class="text-muted">
                                                            CI: <?php echo htmlspecialchars($consulta['paciente_cedula']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div>
                                                        <strong>Dr. <?php echo htmlspecialchars($consulta['medico_nombre']); ?></strong>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo htmlspecialchars($consulta['nombre_especialidad']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-estado-<?php echo $consulta['estado_cita']; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $consulta['estado_cita'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($consulta['estado_cita'] == 'completada'): ?>
                                                    <small class="text-success">
                                                        <i class="fas fa-clock me-1"></i>~30 min
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-muted">N/A</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="verDetallesConsulta(<?php echo $consulta['id_cita']; ?>)">
                                                        <i class="fas fa-eye"></i> Ver
                                                    </button>
                                                    
                                                    <?php if ($consulta['estado_cita'] == 'completada'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-success" 
                                                                onclick="verResumenConsulta(<?php echo $consulta['id_cita']; ?>)">
                                                            <i class="fas fa-file-medical"></i> Resumen
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($_SESSION['role_id'] == 3 && $consulta['estado_cita'] == 'completada'): ?>
                                                        <a href="index.php?action=consultas/recetas&cita=<?php echo $consulta['id_cita']; ?>" 
                                                           class="btn btn-sm btn-outline-warning">
                                                            <i class="fas fa-prescription"></i> Receta
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <?php if ($totalPages > 1): ?>
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <div class="text-muted">
                                    Mostrando <?php echo min($offset + 1, $total); ?> a 
                                    <?php echo min($offset + $limit, $total); ?> de <?php echo $total; ?> consultas
                                </div>
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?action=consultas/virtual/historial&page=<?php echo $page - 1; ?>&buscar=<?php echo urlencode($buscar); ?>&estado=<?php echo $estado_filter; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php
                                        $start = max(1, $page - 2);
                                        $end = min($totalPages, $page + 2);
                                        for ($i = $start; $i <= $end; $i++):
                                        ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?action=consultas/virtual/historial&page=<?php echo $i; ?>&buscar=<?php echo urlencode($buscar); ?>&estado=<?php echo $estado_filter; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?action=consultas/virtual/historial&page=<?php echo $page + 1; ?>&buscar=<?php echo urlencode($buscar); ?>&estado=<?php echo $estado_filter; ?>&fecha_desde=<?php echo $fecha_desde; ?>&fecha_hasta=<?php echo $fecha_hasta; ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Detalles -->
<div class="modal fade" id="modalDetallesConsulta" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye"></i> Detalles de Consulta Virtual
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoDetallesConsulta">
                <!-- Se carga dinámicamente -->
            </div>
        </div>
    </div>
</div>

<style>
.badge-estado-completada { background-color: #28a745; }
.badge-estado-cancelada { background-color: #dc3545; }
.badge-estado-no_asistio { background-color: #6c757d; }
</style>

<script>
function verDetallesConsulta(citaId) {
    fetch(`views/consultas/virtual/api/obtener_detalles.php?cita_id=${citaId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('contenidoDetallesConsulta').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetallesConsulta')).show();
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'No se pudieron cargar los detalles', 'error');
        });
}

function verResumenConsulta(citaId) {
    window.open(`index.php?action=consultas/virtual/resumen&cita=${citaId}`, '_blank');
}
</script>

<?php include 'views/includes/footer.php'; ?>