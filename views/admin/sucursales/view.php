<?php
require_once 'models/Sucursal.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php?action=dashboard');
    exit;
}

$sucursalModel = new Sucursal();

$sucursalId = $_GET['id'] ?? 0;

// Obtener datos de la sucursal
$sucursal = $sucursalModel->getSucursalById($sucursalId);
if (!$sucursal) {
    header('Location: index.php?action=admin/sucursales');
    exit;
}

// Obtener datos relacionados
$especialidades = $sucursalModel->getSucursalEspecialidades($sucursalId);
$medicos = $sucursalModel->getMedicosBySucursal($sucursalId);
$estadisticas = $sucursalModel->getEstadisticasSucursal($sucursalId);

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
                        <i class="fas fa-building"></i> Detalles de Sucursal
                    </h2>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($sucursal['nombre_sucursal']); ?></p>
                </div>
                <div>
                    <a href="index.php?action=admin/sucursales/edit&id=<?php echo $sucursal['id_sucursal']; ?>" class="btn btn-primary me-2">
                        <i class="fas fa-edit"></i> Editar
                    </a>
                    <a href="index.php?action=admin/sucursales" class="btn btn-outline-secondary">
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
                                    <i class="fas fa-building fa-3x text-white"></i>
                                </div>
                                <h4><?php echo htmlspecialchars($sucursal['nombre_sucursal']); ?></h4>
                                <span class="badge bg-<?php echo $sucursal['activo'] == 1 ? 'success' : 'danger'; ?> fs-6">
                                    <?php echo $sucursal['activo'] == 1 ? 'Activa' : 'Inactiva'; ?>
                                </span>
                            </div>

                            <div class="info-item mb-3">
                                <strong><i class="fas fa-map-marker-alt text-muted me-2"></i>Dirección:</strong>
                                <p class="mb-0"><?php echo htmlspecialchars($sucursal['direccion']); ?></p>
                                <small class="text-muted">
                                    <?php echo $sucursal['ciudad']; ?>
                                    <?php if ($sucursal['provincia']): ?>
                                        , <?php echo $sucursal['provincia']; ?>
                                    <?php endif; ?>
                                    <?php if ($sucursal['codigo_postal']): ?>
                                        <br>CP: <?php echo $sucursal['codigo_postal']; ?>
                                    <?php endif; ?>
                                </small>
                            </div>

                            <?php if ($sucursal['telefono']): ?>
                                <div class="info-item mb-3">
                                    <strong><i class="fas fa-phone text-muted me-2"></i>Teléfono:</strong>
                                    <p class="mb-0"><?php echo htmlspecialchars($sucursal['telefono']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($sucursal['email']): ?>
                                <div class="info-item mb-3">
                                    <strong><i class="fas fa-envelope text-muted me-2"></i>Email:</strong>
                                    <p class="mb-0">
                                        <a href="mailto:<?php echo $sucursal['email']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($sucursal['email']); ?>
                                        </a>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <div class="info-item">
                                <strong><i class="fas fa-calendar text-muted me-2"></i>Fecha de Registro:</strong>
                                <p class="mb-0"><?php echo date('d/m/Y H:i', strtotime($sucursal['fecha_creacion'])); ?></p>
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
                                        <div class="h4 text-primary mb-1"><?php echo count($especialidades); ?></div>
                                        <small class="text-muted">Especialidades</small>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="border rounded p-3">
                                        <div class="h4 text-success mb-1"><?php echo count($medicos); ?></div>
                                        <small class="text-muted">Médicos</small>
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
                                        <div class="h4 text-info mb-1"><?php echo $estadisticas['citas_hoy'] ?? 0; ?></div>
                                        <small class="text-muted">Citas hoy</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Especialidades y Médicos -->
                <div class="col-md-8">
                    <!-- Especialidades -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-user-md"></i> Especialidades Disponibles
                                <span class="badge bg-light text-dark"><?php echo count($especialidades); ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($especialidades)): ?>
                                <div class="row">
                                    <?php foreach ($especialidades as $especialidad): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="border rounded p-3 h-100">
                                                <h6 class="text-primary mb-2">
                                                    <i class="fas fa-stethoscope me-2"></i>
                                                    <?php echo htmlspecialchars($especialidad['nombre_especialidad']); ?>
                                                </h6>
                                                <?php if ($especialidad['descripcion']): ?>
                                                    <p class="text-muted small mb-2"><?php echo htmlspecialchars($especialidad['descripcion']); ?></p>
                                                <?php endif; ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-info">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo $especialidad['duracion_cita_minutos']; ?> min por cita
                                                    </small>
                                                    <div>
                                                        <?php if ($especialidad['permite_presencial']): ?>
                                                            <span class="badge bg-primary">Presencial</span>
                                                        <?php endif; ?>
                                                        <?php if ($especialidad['permite_virtual']): ?>
                                                            <span class="badge bg-info">Virtual</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No hay especialidades asignadas</h6>
                                <p class="text-muted">Esta sucursal no tiene especialidades configuradas.</p>
                                <a href="index.php?action=admin/sucursales/edit&id=<?php echo $sucursal['id_sucursal']; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-plus"></i> Agregar Especialidades
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Médicos -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-user-nurse"></i> Médicos Asignados
                            <span class="badge bg-dark"><?php echo count($medicos); ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($medicos)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Médico</th>
                                            <th>Especialidades</th>
                                            <th>Contacto</th>
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
                                                    <?php if (!empty($medico['especialidades'])): ?>
                                                        <?php foreach (explode(', ', $medico['especialidades']) as $esp): ?>
                                                            <span class="badge bg-light text-dark me-1 mb-1"><?php echo $esp; ?></span>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin especialidades</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($medico['telefono']): ?>
                                                        <div><i class="fas fa-phone text-muted me-1"></i><?php echo $medico['telefono']; ?></div>
                                                    <?php endif; ?>
                                                    <div><i class="fas fa-envelope text-muted me-1"></i><?php echo $medico['email']; ?></div>
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
                                <i class="fas fa-user-nurse fa-3x text-muted mb-3"></i>
                                <h6 class="text-muted">No hay médicos asignados</h6>
                                <p class="text-muted">Esta sucursal no tiene médicos trabajando actualmente.</p>
                                <a href="index.php?action=admin/usuarios/create?role=3" class="btn btn-outline-warning">
                                    <i class="fas fa-plus"></i> Agregar Médico
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
</style>

</body>
</html>