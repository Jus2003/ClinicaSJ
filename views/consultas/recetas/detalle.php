<?php
// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Verificar permisos (admin, médicos, recepcionistas y pacientes pueden ver detalles)
if (!in_array($_SESSION['role_id'], [1, 2, 3, 4])) {
    header('Location: index.php?action=dashboard');
    exit;
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Obtener ID de la receta
$id_receta = (int)($_GET['id'] ?? 0);

if (!$id_receta) {
    header('Location: index.php?action=consultas/recetas');
    exit;
}

// Variables para mensajes
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Manejar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['cambiar_estado']) && isset($_POST['nuevo_estado'])) {
            $nuevo_estado = $_POST['nuevo_estado'];
            
            // Verificar permisos según rol
            $wherePermiso = '';
            $paramsPermiso = ['id_receta' => $id_receta];
            
            if ($_SESSION['role_id'] == 3) { // Médico solo sus recetas
                $wherePermiso = " AND cit.id_medico = :id_medico";
                $paramsPermiso['id_medico'] = $_SESSION['user_id'];
            } elseif ($_SESSION['role_id'] == 4) { // Paciente no puede cambiar estado
                throw new Exception("No tienes permisos para cambiar el estado de la receta");
            }
            
            // Verificar que la receta existe y el usuario tiene permisos
            $sqlVerificar = "SELECT r.id_receta FROM recetas r 
                           INNER JOIN consultas c ON r.id_consulta = c.id_consulta 
                           INNER JOIN citas cit ON c.id_cita = cit.id_cita
                           WHERE r.id_receta = :id_receta" . $wherePermiso;
            $stmtVerificar = $db->prepare($sqlVerificar);
            $stmtVerificar->execute($paramsPermiso);
            
            if ($stmtVerificar->rowCount() > 0) {
                $sqlUpdate = "UPDATE recetas SET estado = :estado WHERE id_receta = :id_receta";
                $stmtUpdate = $db->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    'estado' => $nuevo_estado,
                    'id_receta' => $id_receta
                ]);
                
                $success = "Estado de la receta actualizado exitosamente";
            } else {
                $error = "No tienes permisos para modificar esta receta";
            }
        }
    } catch (Exception $e) {
        $error = "Error al procesar la solicitud: " . $e->getMessage();
    }
}

// Construir consulta con permisos según rol
$wherePermiso = '';
$paramsPermiso = ['id_receta' => $id_receta];

if ($_SESSION['role_id'] == 3) { // Médico solo sus recetas
    $wherePermiso = " AND cit.id_medico = :id_medico";
    $paramsPermiso['id_medico'] = $_SESSION['user_id'];
} elseif ($_SESSION['role_id'] == 4) { // Paciente solo sus recetas
    $wherePermiso = " AND cit.id_paciente = :id_paciente";
    $paramsPermiso['id_paciente'] = $_SESSION['user_id'];
} elseif ($_SESSION['role_id'] == 2) { // Recepcionista solo de su sucursal
    $wherePermiso = " AND cit.id_sucursal = (SELECT id_sucursal FROM usuarios WHERE id_usuario = :user_id)";
    $paramsPermiso['user_id'] = $_SESSION['user_id'];
}

// Consulta principal para obtener todos los detalles
$sql = "SELECT r.*, 
               CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
               p.cedula as paciente_cedula,
               p.telefono as paciente_telefono,
               p.email as paciente_email,
               p.fecha_nacimiento,
               CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
               m.telefono as medico_telefono,
               m.email as medico_email,
               e.nombre_especialidad,
               s.nombre_sucursal,
               s.direccion as sucursal_direccion,
               s.telefono as sucursal_telefono,
               cit.fecha_cita,
               cit.hora_cita,
               cit.motivo_consulta,
               c.diagnostico_principal,
               c.tratamiento,
               c.observaciones_medicas
        FROM recetas r
        INNER JOIN consultas c ON r.id_consulta = c.id_consulta
        INNER JOIN citas cit ON c.id_cita = cit.id_cita
        INNER JOIN usuarios p ON cit.id_paciente = p.id_usuario
        INNER JOIN usuarios m ON cit.id_medico = m.id_usuario
        INNER JOIN especialidades e ON cit.id_especialidad = e.id_especialidad
        INNER JOIN sucursales s ON cit.id_sucursal = s.id_sucursal
        WHERE r.id_receta = :id_receta" . $wherePermiso;

$stmt = $db->prepare($sql);
$stmt->execute($paramsPermiso);
$receta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receta) {
    header('Location: index.php?action=consultas/recetas');
    exit;
}

// Funciones auxiliares
function getEstadoBadgeClass($estado) {
    switch ($estado) {
        case 'activa': return 'bg-success';
        case 'dispensada': return 'bg-info';
        case 'vencida': return 'bg-warning';
        case 'cancelada': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

function formatearEstado($estado) {
    switch ($estado) {
        case 'activa': return 'Activa';
        case 'dispensada': return 'Dispensada';
        case 'vencida': return 'Vencida';
        case 'cancelada': return 'Cancelada';
        default: return ucfirst($estado);
    }
}

function calcularEdad($fechaNacimiento) {
    if (!$fechaNacimiento) return 'N/A';
    $nacimiento = new DateTime($fechaNacimiento);
    $hoy = new DateTime();
    return $nacimiento->diff($hoy)->y . ' años';
}

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-prescription text-primary"></i> 
                    Detalle de Receta
                    <span class="badge <?php echo getEstadoBadgeClass($receta['estado']); ?> ms-2">
                        <?php echo formatearEstado($receta['estado']); ?>
                    </span>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="javascript:history.back()" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver
                        </a>
                        <a href="index.php?action=consultas/recetas/imprimir&id=<?php echo $receta['id_receta']; ?>" 
                           class="btn btn-success" target="_blank">
                            <i class="fas fa-print"></i> Imprimir
                        </a>
                        <?php if (in_array($_SESSION['role_id'], [1, 3]) && in_array($receta['estado'], ['activa', 'dispensada'])): ?>
                            <button type="button" class="btn btn-warning" 
                                    data-bs-toggle="modal" data-bs-target="#cambiarEstadoModal">
                                <i class="fas fa-edit"></i> Cambiar Estado
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Mensajes -->
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Información de la Receta -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-prescription me-2"></i>
                                Información de la Receta
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item mb-3">
                                        <strong><i class="fas fa-barcode text-muted me-2"></i>Código:</strong>
                                        <span class="fs-5 fw-bold text-primary"><?php echo htmlspecialchars($receta['codigo_receta']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item mb-3">
                                        <strong><i class="fas fa-calendar text-muted me-2"></i>Fecha de Emisión:</strong>
                                        <span><?php echo date('d/m/Y H:i', strtotime($receta['fecha_emision'])); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item mb-3">
                                        <strong><i class="fas fa-calendar-times text-muted me-2"></i>Fecha de Vencimiento:</strong>
                                        <span class="<?php echo strtotime($receta['fecha_vencimiento']) < time() ? 'text-danger fw-bold' : ''; ?>">
                                            <?php echo date('d/m/Y', strtotime($receta['fecha_vencimiento'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item mb-3">
                                        <strong><i class="fas fa-info-circle text-muted me-2"></i>Estado:</strong>
                                        <span class="badge <?php echo getEstadoBadgeClass($receta['estado']); ?>">
                                            <?php echo formatearEstado($receta['estado']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información del Medicamento -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-pills me-2"></i>
                                Información del Medicamento
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <h4 class="text-success mb-2"><?php echo htmlspecialchars($receta['medicamento']); ?></h4>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <strong>Concentración:</strong><br>
                                                <span class="fs-6"><?php echo htmlspecialchars($receta['concentracion']); ?></span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Forma Farmacéutica:</strong><br>
                                                <span class="fs-6"><?php echo htmlspecialchars($receta['forma_farmaceutica']); ?></span>
                                            </div>
                                            <div class="col-md-4">
                                                <strong>Cantidad:</strong><br>
                                                <span class="fs-6 fw-bold"><?php echo htmlspecialchars($receta['cantidad']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="info-item mb-3">
                                        <strong><i class="fas fa-weight text-muted me-2"></i>Dosis:</strong>
                                        <div class="mt-1">
                                            <span class="badge bg-light text-dark fs-6"><?php echo htmlspecialchars($receta['dosis']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-item mb-3">
                                        <strong><i class="fas fa-clock text-muted me-2"></i>Frecuencia:</strong>
                                        <div class="mt-1">
                                            <span class="badge bg-light text-dark fs-6"><?php echo htmlspecialchars($receta['frecuencia']); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-item mb-3">
                                        <strong><i class="fas fa-calendar-day text-muted me-2"></i>Duración:</strong>
                                        <div class="mt-1">
                                            <span class="badge bg-light text-dark fs-6"><?php echo htmlspecialchars($receta['duracion']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if ($receta['indicaciones_especiales']): ?>
                                <div class="alert alert-info border-0 mt-3">
                                    <h6 class="alert-heading">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Indicaciones Especiales
                                    </h6>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($receta['indicaciones_especiales'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Información del Paciente y Médico -->
                <div class="col-lg-4">
                    <!-- Información del Paciente -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-user me-2"></i>
                                Información del Paciente
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div class="avatar bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; font-size: 24px;">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                            
                            <div class="text-center mb-3">
                                <h6 class="mb-1"><?php echo htmlspecialchars($receta['paciente_nombre']); ?></h6>
                                <small class="text-muted">Cédula: <?php echo htmlspecialchars($receta['paciente_cedula']); ?></small>
                            </div>

                            <div class="info-item mb-2">
                                <strong><i class="fas fa-birthday-cake text-muted me-2"></i>Edad:</strong>
                                <span><?php echo calcularEdad($receta['fecha_nacimiento']); ?></span>
                            </div>

                            <?php if ($receta['paciente_telefono']): ?>
                                <div class="info-item mb-2">
                                    <strong><i class="fas fa-phone text-muted me-2"></i>Teléfono:</strong>
                                    <span><?php echo htmlspecialchars($receta['paciente_telefono']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($receta['paciente_email']): ?>
                                <div class="info-item mb-2">
                                    <strong><i class="fas fa-envelope text-muted me-2"></i>Email:</strong>
                                    <span class="small"><?php echo htmlspecialchars($receta['paciente_email']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Información del Médico -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0">
                                <i class="fas fa-user-md me-2"></i>
                                Médico Prescriptor
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <div class="avatar bg-warning text-dark rounded-circle d-inline-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px; font-size: 24px;">
                                    <i class="fas fa-user-md"></i>
                                </div>
                            </div>
                            
                            <div class="text-center mb-3">
                                <h6 class="mb-1"><?php echo htmlspecialchars($receta['medico_nombre']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($receta['nombre_especialidad']); ?></small>
                            </div>

                            <?php if ($receta['medico_telefono']): ?>
                                <div class="info-item mb-2">
                                    <strong><i class="fas fa-phone text-muted me-2"></i>Teléfono:</strong>
                                    <span><?php echo htmlspecialchars($receta['medico_telefono']); ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($receta['medico_email']): ?>
                                <div class="info-item mb-2">
                                    <strong><i class="fas fa-envelope text-muted me-2"></i>Email:</strong>
                                    <span class="small"><?php echo htmlspecialchars($receta['medico_email']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Información de la Consulta -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-stethoscope me-2"></i>
                                Información de la Consulta
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="info-item mb-3">
                                <strong><i class="fas fa-calendar text-muted me-2"></i>Fecha de Consulta:</strong>
                                <div><?php echo date('d/m/Y', strtotime($receta['fecha_cita'])); ?></div>
                                <small class="text-muted"><?php echo date('H:i', strtotime($receta['hora_cita'])); ?></small>
                            </div>

                            <div class="info-item mb-3">
                                <strong><i class="fas fa-building text-muted me-2"></i>Sucursal:</strong>
                                <div><?php echo htmlspecialchars($receta['nombre_sucursal']); ?></div>
                            </div>

                            <?php if ($receta['motivo_consulta']): ?>
                                <div class="info-item mb-3">
                                    <strong><i class="fas fa-comment text-muted me-2"></i>Motivo de Consulta:</strong>
                                    <div class="small"><?php echo nl2br(htmlspecialchars($receta['motivo_consulta'])); ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if ($receta['diagnostico_principal']): ?>
                                <div class="info-item mb-3">
                                    <strong><i class="fas fa-diagnosis text-muted me-2"></i>Diagnóstico:</strong>
                                    <div class="small"><?php echo nl2br(htmlspecialchars($receta['diagnostico_principal'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para cambiar estado -->
<?php if (in_array($_SESSION['role_id'], [1, 3]) && in_array($receta['estado'], ['activa', 'dispensada'])): ?>
<div class="modal fade" id="cambiarEstadoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit text-warning"></i> Cambiar Estado de Receta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas cambiar el estado de la receta <strong><?php echo htmlspecialchars($receta['codigo_receta']); ?></strong>?</p>
                    
                    <div class="mb-3">
                        <label for="nuevo_estado" class="form-label">Nuevo Estado</label>
                        <select class="form-select" id="nuevo_estado" name="nuevo_estado" required>
                            <option value="activa" <?php echo $receta['estado'] === 'activa' ? 'selected' : ''; ?>>Activa</option>
                            <option value="dispensada" <?php echo $receta['estado'] === 'dispensada' ? 'selected' : ''; ?>>Dispensada</option>
                            <option value="vencida" <?php echo $receta['estado'] === 'vencida' ? 'selected' : ''; ?>>Vencida</option>
                            <option value="cancelada" <?php echo $receta['estado'] === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                        </select>
                    </div>
                    
                    <input type="hidden" name="cambiar_estado" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Cambiar Estado
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.info-item {
    border-bottom: 1px solid #f8f9fa;
    padding-bottom: 8px;
}

.info-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.avatar {
    font-size: 1.5rem;
}
</style>