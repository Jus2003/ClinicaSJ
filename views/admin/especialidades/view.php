<?php
require_once 'models/Especialidad.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php?action=dashboard');
    exit;
}

$especialidadModel = new Especialidad();

$especialidadId = $_GET['id'] ?? 0;

// Obtener datos de la especialidad
$especialidad = $especialidadModel->getEspecialidadById($especialidadId);
if (!$especialidad) {
    header('Location: index.php?action=admin/especialidades');
    exit;
}

// Obtener datos relacionados
$medicos = $especialidadModel->getMedicosByEspecialidad($especialidadId);
$sucursales = $especialidadModel->getSucursalesByEspecialidad($especialidadId);
$estadisticas = $especialidadModel->getEstadisticasEspecialidad($especialidadId);

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
                        <i class="fas fa-stethoscope"></i> Detalles de Especialidad
                    </h2>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($especialidad['nombre_especialidad']); ?></p>
                </div>
                <div>
                    <a href="index.php?action=admin/especialidades/edit&id=<?php echo $especialidad['id_especialidad']; ?>" class="btn btn-primary me-2">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <a href="index.php?action=admin/especialidades" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>

            <div class="row">
                <!-- Información General -->
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle"></i> Información General
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div class="avatar-xl bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                                    <i class="fas fa-stethoscope fa-3x text-white"></i>
                                </div>
                                <h4><?php echo htmlspecialchars($especialidad['nombre_especialidad']); ?></h4>
                                <span class="badge bg-<?php echo $especialidad['activo'] == 1 ? 'success' : 'danger'; ?> fs-6">
                                    <?php echo $especialidad['activo'] == 1 ? 'Activa' : 'Inactiva'; ?>
                                </span>
                            </div>

                            <?php if ($especialidad['descripcion']): ?>
                                <div class="info-item mb-3">
                                    <strong><i class="fas fa-align-left text-muted me-2"></i>Descripción:</strong>
                                    <p class="mb-0"><?php echo htmlspecialchars($especialidad['descripcion']); ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="info-item mb-3">
                                <strong><i class="fas fa-clock text-muted me-2"></i>Duración por Cita:</strong>
                                <p class="mb-0"><?php echo $especialidad['duracion_cita_minutos']; ?> minutos</p>
                            </div>

                            <div class="info-item mb-3">
                                <strong><i class="fas fa-video text-muted me-2"></i>Modalidades:</strong>
                                <div class="mt-2">
                                    <?php if ($especialidad['permite_presencial']): ?>
                                        <span class="badge bg-primary me-1">
                                            <i class="fas fa-hospital me-1"></i>Presencial
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($especialidad['permite_virtual']): ?>
                                        <span class="badge bg-info">
                                            <i class="fas fa-video me-1"></i>Virtual
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="info-item">
                                <strong><i class="fas fa-calendar text-muted me-2"></i>Fecha de Registro:</strong>
                                <p class="mb-0"><?php echo date('d/m/Y H:i', strtotime($especialidad['fecha_creacion'])); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Estadísticas rápidas -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-bar"></i> Estadísticas
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="h4 text-success mb-1"><?php echo count($medicos); ?></div>
                                        <small class="text-muted">Médicos</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="h4 text-info mb-1"><?php echo count($sucursales); ?></div>
                                        <small class="text-muted">Sucursales</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-3">
                                        <div class="h4 text-warning mb-1"><?php echo $estadisticas['citas_mes'] ?? 0; ?></div>
                                        <small class="text-muted">Citas este mes</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border rounded p-3">
                                        <div class="h4 text-danger mb-1"><?php echo $estadisticas['citas_hoy'] ?? 0; ?></div>
                                        <small class="text-muted">Citas hoy</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Médicos y Sucursales -->
                <div class="col-md-8">
                    <!-- Médicos -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-user-md"></i> Médicos Especializados
                                <span class="badge bg-light text-dark"><?php echo count($medicos); ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($medicos)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Médico</th>
                                                <th>Sucursal</th>
                                                <th>Contacto</th>
                                                <th>Licencia</th>
                                                <th>Estado</th>
                                                <th class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($medicos as $medico): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="avatar-sm bg-success rounded-circle d-flex align-items-center justify-content-center me-3">
                                                                <i class="fas fa-user-md text-white"></i>
                                                            </div>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($medico['nombre'] . ' ' . $medico['apellido']); ?></strong>
                                                                <br><small class="text-muted">@<?php echo $medico['username']; ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($medico['nombre_sucursal'] ?? 'Sin asignar'); ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($medico['telefono']): ?>
                                                            <div><i class="fas fa-phone text-muted me-1"></i><?php echo $medico['telefono']; ?></div>
                                                        <?php endif; ?>
                                                        <div><i class="fas fa-envelope text-muted me-1"></i><?php echo $medico['email']; ?></div>
                                                    </td>
                                                    <td>
                                                        <?php if ($medico['numero_licencia']): ?>
                                                            <small class="text-muted"><?php echo $medico['numero_licencia']; ?></small>
                                                        <?php else: ?>
                                                            <span class="text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $medico['activo'] == 1 ? 'success' : 'danger'; ?>">
                                                            <?php echo $medico['activo'] == 1 ? 'Activo' : 'Inactivo'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="index.php?action=admin/usuarios/edit&id=<?php echo $medico['id_usuario']; ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="Ver perfil">
                                                            <i class="fas fa-user"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                                    <h6 class="text-muted">No hay médicos especializados</h6>
                                    <p class="text-muted">Esta especialidad no tiene médicos asignados actualmente.</p>
                                    <a href="index.php?action=admin/usuarios/create?role=3" class="btn btn-outline-success">
                                        <i class="fas fa-plus"></i> Agregar Médico
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sucursales -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-building"></i> Sucursales Disponibles
                                <span class="badge bg-dark"><?php echo count($sucursales); ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($sucursales)): ?>
                                <div class="row">
                                    <?php foreach ($sucursales as $sucursal): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card border h-100">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-start">
                                                        <div class="avatar-sm bg-warning rounded-circle d-flex align-items-center justify-content-center me-3">
                                                            <i class="fas fa-building text-white"></i>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($sucursal['nombre_sucursal']); ?></h6>
                                                            <p class="text-muted small mb-2">
                                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                                <?php echo htmlspecialchars($sucursal['direccion']); ?>
                                                            </p>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <small class="text-muted">
                                                                    <?php echo $sucursal['ciudad']; ?>
                                                                    <?php if ($sucursal['provincia']): ?>
                                                                        , <?php echo $sucursal['provincia']; ?>
                                                                    <?php endif; ?>
                                                                </small>
                                                                <span class="badge bg-<?php echo $sucursal['activo'] == 1 ? 'success' : 'danger'; ?>">
                                                                    <?php echo $sucursal['activo'] == 1 ? 'Activa' : 'Inactiva'; ?>
                                                                </span>
                                                            </div>
                                                            <?php if ($sucursal['telefono'] || $sucursal['email']): ?>
                                                                <div class="mt-2 pt-2 border-top">
                                                                    <?php if ($sucursal['telefono']): ?>
                                                                        <small class="text-muted d-block">
                                                                            <i class="fas fa-phone me-1"></i><?php echo $sucursal['telefono']; ?>
                                                                        </small>
                                                                    <?php endif; ?>
                                                                    <?php if ($sucursal['email']): ?>
                                                                        <small class="text-muted d-block">
                                                                            <i class="fas fa-envelope me-1"></i><?php echo $sucursal['email']; ?>
                                                                        </small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-footer bg-light">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            Desde: <?php echo date('d/m/Y', strtotime($sucursal['fecha_asignacion'])); ?>
                                                        </small>
                                                        <a href="index.php?action=admin/sucursales/view&id=<?php echo $sucursal['id_sucursal']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i> Ver
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                    <h6 class="text-muted">No hay sucursales asignadas</h6>
                                    <p class="text-muted">Esta especialidad no está disponible en ninguna sucursal actualmente.</p>
                                    <a href="index.php?action=admin/sucursales" class="btn btn-outline-warning">
                                        <i class="fas fa-building"></i> Gestionar Sucursales
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .avatar-xl {
        width: 100px;
        height: 100px;
    }

    .avatar-sm {
        width: 35px;
        height: 35px;
        font-size: 14px;
    }

    .info-item {
        border-left: 3px solid #e9ecef;
        padding-left: 15px;
    }

    .info-item:hover {
        border-left-color: #007bff;
        background-color: #f8f9fa;
        padding: 10px 15px;
        margin: -10px -15px 1rem -15px;
        border-radius: 5px;
        transition: all 0.3s ease;
    }

    .card.border:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
    }
</style>

</body>
</html>