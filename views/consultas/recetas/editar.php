<?php
// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Verificar permisos (solo admin y médicos pueden editar recetas)
if (!in_array($_SESSION['role_id'], [1, 3])) {
    header('Location: index.php?action=consultas/recetas');
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
$success = '';
$error = '';

// Construir consulta con permisos según rol
$wherePermiso = '';
$paramsPermiso = ['id_receta' => $id_receta];

if ($_SESSION['role_id'] == 3) { // Médico solo sus recetas
    $wherePermiso = " AND cit.id_medico = :id_medico";
    $paramsPermiso['id_medico'] = $_SESSION['user_id'];
}

// Obtener datos de la receta
$sql = "SELECT r.*, 
               CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
               p.cedula as paciente_cedula,
               p.fecha_nacimiento,
               CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
               e.nombre_especialidad,
               s.nombre_sucursal,
               cit.fecha_cita,
               cit.hora_cita,
               c.diagnostico_principal
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

// Verificar que la receta se pueda editar
if (!in_array($receta['estado'], ['activa', 'dispensada'])) {
    $error = "Solo se pueden editar recetas activas o dispensadas.";
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    try {
        // Validar datos requeridos
        $medicamento = trim($_POST['medicamento'] ?? '');
        $concentracion = trim($_POST['concentracion'] ?? '');
        $forma_farmaceutica = trim($_POST['forma_farmaceutica'] ?? '');
        $dosis = trim($_POST['dosis'] ?? '');
        $frecuencia = trim($_POST['frecuencia'] ?? '');
        $duracion = trim($_POST['duracion'] ?? '');
        $cantidad = trim($_POST['cantidad'] ?? '');
        $indicaciones_especiales = trim($_POST['indicaciones_especiales'] ?? '');
        $nuevo_estado = $_POST['estado'] ?? $receta['estado'];
        
        if (empty($medicamento) || empty($dosis) || empty($frecuencia) || empty($duracion) || empty($cantidad)) {
            throw new Exception("Todos los campos obligatorios deben ser completados.");
        }
        
        // Actualizar receta
        $sqlUpdate = "UPDATE recetas SET 
                        medicamento = :medicamento,
                        concentracion = :concentracion,
                        forma_farmaceutica = :forma_farmaceutica,
                        dosis = :dosis,
                        frecuencia = :frecuencia,
                        duracion = :duracion,
                        cantidad = :cantidad,
                        indicaciones_especiales = :indicaciones_especiales,
                        estado = :estado
                      WHERE id_receta = :id_receta";
        
        $stmtUpdate = $db->prepare($sqlUpdate);
        $stmtUpdate->execute([
            'medicamento' => $medicamento,
            'concentracion' => $concentracion,
            'forma_farmaceutica' => $forma_farmaceutica,
            'dosis' => $dosis,
            'frecuencia' => $frecuencia,
            'duracion' => $duracion,
            'cantidad' => $cantidad,
            'indicaciones_especiales' => $indicaciones_especiales,
            'estado' => $nuevo_estado,
            'id_receta' => $id_receta
        ]);
        
        // Redirigir al detalle con mensaje de éxito
        header("Location: index.php?action=consultas/recetas/detalle&id={$id_receta}&success=" . urlencode("Receta actualizada exitosamente"));
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Función para calcular edad
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
                    <i class="fas fa-edit text-warning"></i> 
                    Editar Receta Médica
                    <span class="badge bg-info ms-2"><?php echo htmlspecialchars($receta['codigo_receta']); ?></span>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="index.php?action=consultas/recetas/detalle&id=<?php echo $id_receta; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Volver al Detalle
                        </a>
                        <a href="index.php?action=consultas/recetas/imprimir&id=<?php echo $id_receta; ?>" 
                           class="btn btn-outline-success" target="_blank">
                            <i class="fas fa-print"></i> Imprimir
                        </a>
                    </div>
                </div>
            </div>

            <!-- Mensajes -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Alerta si la receta está vencida -->
            <?php if (strtotime($receta['fecha_vencimiento']) < time()): ?>
                <div class="alert alert-warning border-0 shadow-sm mb-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                        <div>
                            <h6 class="alert-heading mb-1">¡Atención! Receta Vencida</h6>
                            <p class="mb-0">
                                Esta receta venció el <strong><?php echo date('d/m/Y', strtotime($receta['fecha_vencimiento'])); ?></strong>.
                                Los cambios se guardarán, pero considera generar una nueva receta.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Información del Paciente (no editable) -->
                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm">
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

                            <div class="info-item mb-2">
                                <strong><i class="fas fa-calendar text-muted me-2"></i>Fecha Consulta:</strong>
                                <span><?php echo date('d/m/Y', strtotime($receta['fecha_cita'])); ?></span>
                            </div>

                            <div class="info-item mb-2">
                                <strong><i class="fas fa-user-md text-muted me-2"></i>Médico:</strong>
                                <span><?php echo htmlspecialchars($receta['medico_nombre']); ?></span>
                            </div>

                            <div class="info-item mb-2">
                                <strong><i class="fas fa-building text-muted me-2"></i>Sucursal:</strong>
                                <span><?php echo htmlspecialchars($receta['nombre_sucursal']); ?></span>
                            </div>

                            <?php if ($receta['diagnostico_principal']): ?>
                                <div class="info-item">
                                    <strong><i class="fas fa-stethoscope text-muted me-2"></i>Diagnóstico:</strong>
                                    <div class="small mt-1"><?php echo nl2br(htmlspecialchars($receta['diagnostico_principal'])); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Información de la receta actual -->
                    <div class="card border-0 shadow-sm mt-3">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Información de la Receta
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="info-item mb-2">
                                <strong>Código:</strong>
                                <span><?php echo htmlspecialchars($receta['codigo_receta']); ?></span>
                            </div>
                            <div class="info-item mb-2">
                                <strong>Fecha Emisión:</strong>
                                <span><?php echo date('d/m/Y H:i', strtotime($receta['fecha_emision'])); ?></span>
                            </div>
                            <div class="info-item mb-2">
                                <strong>Fecha Vencimiento:</strong>
                                <span class="<?php echo strtotime($receta['fecha_vencimiento']) < time() ? 'text-danger fw-bold' : ''; ?>">
                                    <?php echo date('d/m/Y', strtotime($receta['fecha_vencimiento'])); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <strong>Estado Actual:</strong>
                                <span class="badge <?php 
                                    switch($receta['estado']) {
                                        case 'activa': echo 'bg-success'; break;
                                        case 'dispensada': echo 'bg-info'; break;
                                        case 'vencida': echo 'bg-warning'; break;
                                        case 'cancelada': echo 'bg-danger'; break;
                                        default: echo 'bg-secondary';
                                    }
                                ?>">
                                    <?php echo ucfirst($receta['estado']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario de Edición -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0">
                                <i class="fas fa-edit me-2"></i>
                                Editar Datos de la Receta
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="formEditarReceta">
                                <!-- Información del Medicamento -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-warning border-bottom pb-2 mb-3">
                                            <i class="fas fa-pills me-2"></i>Información del Medicamento
                                        </h6>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="medicamento" class="form-label">
                                            Medicamento <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="medicamento" name="medicamento" 
                                               value="<?php echo htmlspecialchars($receta['medicamento']); ?>"
                                               placeholder="Nombre del medicamento" required>
                                        <div class="form-text">Nombre genérico o comercial del medicamento</div>
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label for="concentracion" class="form-label">Concentración</label>
                                        <input type="text" class="form-control" id="concentracion" name="concentracion" 
                                               value="<?php echo htmlspecialchars($receta['concentracion'] ?? ''); ?>"
                                               placeholder="ej: 500mg, 10ml">
                                        <div class="form-text">Concentración por unidad</div>
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label for="forma_farmaceutica" class="form-label">Forma Farmacéutica</label>
                                        <select class="form-select" id="forma_farmaceutica" name="forma_farmaceutica">
                                            <option value="">Seleccionar...</option>
                                            <?php 
                                            $formas = ['Tabletas', 'Cápsulas', 'Jarabe', 'Suspensión', 'Gotas', 'Crema', 'Pomada', 'Gel', 'Solución', 'Inyectable', 'Supositorios', 'Óvulos', 'Parches', 'Inhalador', 'Otra'];
                                            foreach ($formas as $forma): ?>
                                                <option value="<?php echo $forma; ?>" 
                                                        <?php echo ($receta['forma_farmaceutica'] === $forma) ? 'selected' : ''; ?>>
                                                    <?php echo $forma; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Posología -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-warning border-bottom pb-2 mb-3">
                                            <i class="fas fa-clock me-2"></i>Posología
                                        </h6>
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label for="dosis" class="form-label">
                                            Dosis <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="dosis" name="dosis" 
                                               value="<?php echo htmlspecialchars($receta['dosis']); ?>"
                                               placeholder="ej: 1 tableta, 5ml" required>
                                        <div class="form-text">Cantidad por toma</div>
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label for="frecuencia" class="form-label">
                                            Frecuencia <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="frecuencia" name="frecuencia" required>
                                            <option value="">Seleccionar...</option>
                                            <?php 
                                            $frecuencias = [
                                                'Cada 4 horas', 'Cada 6 horas', 'Cada 8 horas', 'Cada 12 horas', 'Cada 24 horas',
                                                '2 veces al día', '3 veces al día', 'Antes de comidas', 'Después de comidas',
                                                'Con comidas', 'Antes de dormir', 'En ayunas', 'Según necesidad'
                                            ];
                                            foreach ($frecuencias as $freq): ?>
                                                <option value="<?php echo $freq; ?>" 
                                                        <?php echo ($receta['frecuencia'] === $freq) ? 'selected' : ''; ?>>
                                                    <?php echo $freq; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label for="duracion" class="form-label">
                                            Duración <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="duracion" name="duracion" 
                                               value="<?php echo htmlspecialchars($receta['duracion']); ?>"
                                               placeholder="ej: 7 días, 2 semanas" required>
                                        <div class="form-text">Duración del tratamiento</div>
                                    </div>

                                    <div class="col-md-3 mb-3">
                                        <label for="cantidad" class="form-label">
                                            Cantidad <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control" id="cantidad" name="cantidad" 
                                               value="<?php echo htmlspecialchars($receta['cantidad']); ?>"
                                               placeholder="ej: 30 tabletas, 1 frasco" required>
                                        <div class="form-text">Cantidad total a dispensar</div>
                                    </div>
                                </div>

                                <!-- Estado de la receta -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-warning border-bottom pb-2 mb-3">
                                            <i class="fas fa-info-circle me-2"></i>Estado de la Receta
                                        </h6>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="estado" class="form-label">Estado</label>
                                        <select class="form-select" id="estado" name="estado">
                                            <option value="activa" <?php echo ($receta['estado'] === 'activa') ? 'selected' : ''; ?>>Activa</option>
                                            <option value="dispensada" <?php echo ($receta['estado'] === 'dispensada') ? 'selected' : ''; ?>>Dispensada</option>
                                            <option value="vencida" <?php echo ($receta['estado'] === 'vencida') ? 'selected' : ''; ?>>Vencida</option>
                                            <option value="cancelada" <?php echo ($receta['estado'] === 'cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                                        </select>
                                        <div class="form-text">Cambiar el estado según corresponda</div>
                                    </div>
                                </div>

                                <!-- Indicaciones Especiales -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="text-warning border-bottom pb-2 mb-3">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Indicaciones Especiales
                                        </h6>
                                        
                                        <div class="mb-3">
                                            <label for="indicaciones_especiales" class="form-label">Instrucciones Adicionales</label>
                                            <textarea class="form-control" id="indicaciones_especiales" name="indicaciones_especiales" 
                                                      rows="4" placeholder="Instrucciones especiales para el paciente..."><?php echo htmlspecialchars($receta['indicaciones_especiales'] ?? ''); ?></textarea>
                                            <div class="form-text">Indicaciones especiales, precauciones, efectos secundarios a vigilar, etc.</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Botones de acción -->
                                <div class="row">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="index.php?action=consultas/recetas/detalle&id=<?php echo $id_receta; ?>" class="btn btn-outline-secondary">
                                                <i class="fas fa-times"></i> Cancelar
                                            </a>
                                            <button type="submit" class="btn btn-warning">
                                                <i class="fas fa-save"></i> Guardar Cambios
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validación del formulario
document.getElementById('formEditarReceta').addEventListener('submit', function(e) {
    const medicamento = document.getElementById('medicamento').value.trim();
    const dosis = document.getElementById('dosis').value.trim();
    const frecuencia = document.getElementById('frecuencia').value;
    const duracion = document.getElementById('duracion').value.trim();
    const cantidad = document.getElementById('cantidad').value.trim();
    
    if (!medicamento || !dosis || !frecuencia || !duracion || !cantidad) {
        e.preventDefault();
        alert('Por favor complete todos los campos obligatorios marcados con *');
        return false;
    }
    
    // Confirmación antes de guardar
    if (!confirm('¿Está seguro de que desea guardar los cambios en esta receta?')) {
        e.preventDefault();
        return false;
    }
});

// Alertar sobre cambios de estado importantes
document.getElementById('estado').addEventListener('change', function() {
    const nuevoEstado = this.value;
    const estadoAnterior = '<?php echo $receta['estado']; ?>';
    
    if (nuevoEstado === 'cancelada' && estadoAnterior !== 'cancelada') {
        alert('ATENCIÓN: Al cancelar la receta, no se podrá dispensar el medicamento.');
    } else if (nuevoEstado === 'vencida' && estadoAnterior !== 'vencida') {
        alert('ATENCIÓN: Al marcar como vencida, la receta no tendrá validez para dispensación.');
    } else if (nuevoEstado === 'dispensada' && estadoAnterior !== 'dispensada') {
        alert('INFORMACIÓN: Al marcar como dispensada, se registra que el medicamento ya fue entregado.');
    }
});
</script>

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