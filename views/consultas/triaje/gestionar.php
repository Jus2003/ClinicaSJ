<?php
require_once 'models/TriajeModel.php';

// Verificar que sea recepcionista
if ($_SESSION['role_id'] != 2) {
    header('Location: index.php?action=dashboard');
    exit;
}

$triajeModel = new TriajeModel();
$error = '';
$success = '';

// Filtros
$fecha = $_GET['fecha'] ?? date('Y-m-d');
$estado = $_GET['estado'] ?? 'todos'; // pendiente, completado, todos
$buscar = $_GET['buscar'] ?? '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'completar_presencial':
                $citaId = $_POST['cita_id'];
                $respuestas = $_POST['respuestas'] ?? [];

                if (empty($respuestas)) {
                    throw new Exception("Debe completar al menos una respuesta");
                }

                $triajeModel->guardarRespuestas($citaId, $respuestas, $_SESSION['user_id'], 'presencial');
                $success = "Triaje presencial completado exitosamente";
                break;

            case 'enviar_recordatorio':
                // Aquí implementarías la lógica para enviar recordatorios
                $success = "Recordatorio enviado al paciente";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener datos según filtros
$database = new Database();
$db = $database->getConnection();

// Construir consulta base
$whereConditions = ["c.fecha_cita = :fecha"];
$params = ['fecha' => $fecha];

// Filtrar por sucursal del recepcionista
$whereConditions[] = "c.id_sucursal = (SELECT id_sucursal FROM usuarios WHERE id_usuario = :user_id)";
$params['user_id'] = $_SESSION['user_id'];

// Filtro por estado de triaje
if ($estado === 'pendiente') {
    $whereConditions[] = "NOT EXISTS (SELECT 1 FROM triaje_respuestas tr WHERE tr.id_cita = c.id_cita)";
} elseif ($estado === 'completado') {
    $whereConditions[] = "EXISTS (SELECT 1 FROM triaje_respuestas tr WHERE tr.id_cita = c.id_cita)";
}

// Filtro por búsqueda
if ($buscar) {
    $whereConditions[] = "(p.nombre LIKE :buscar OR p.apellido LIKE :buscar OR p.cedula LIKE :buscar)";
    $params['buscar'] = "%{$buscar}%";
}

$sql = "SELECT c.id_cita, c.fecha_cita, c.hora_cita, c.estado as estado_cita,
               CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
               p.cedula as paciente_cedula,
               p.telefono as paciente_telefono,
               CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
               e.nombre_especialidad,
               (SELECT COUNT(*) FROM triaje_respuestas tr WHERE tr.id_cita = c.id_cita) as tiene_triaje,
               (SELECT tipo_triaje FROM triaje_respuestas tr WHERE tr.id_cita = c.id_cita LIMIT 1) as tipo_triaje,
               (SELECT MIN(fecha_respuesta) FROM triaje_respuestas tr WHERE tr.id_cita = c.id_cita) as fecha_triaje
        FROM citas c
        JOIN usuarios p ON c.id_paciente = p.id_usuario
        JOIN usuarios m ON c.id_medico = m.id_usuario
        JOIN especialidades e ON c.id_especialidad = e.id_especialidad
        WHERE " . implode(' AND ', $whereConditions) . "
        ORDER BY c.hora_cita ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas del día
$sqlStats = "SELECT 
                COUNT(*) as total_citas,
                SUM(CASE WHEN EXISTS (SELECT 1 FROM triaje_respuestas tr WHERE tr.id_cita = c.id_cita) THEN 1 ELSE 0 END) as triajes_completados,
                SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM triaje_respuestas tr WHERE tr.id_cita = c.id_cita) THEN 1 ELSE 0 END) as triajes_pendientes
            FROM citas c
            WHERE c.fecha_cita = :fecha 
            AND c.id_sucursal = (SELECT id_sucursal FROM usuarios WHERE id_usuario = :user_id)
            AND c.estado = 'confirmada'";

$stmtStats = $db->prepare($sqlStats);
$stmtStats->execute(['fecha' => $fecha, 'user_id' => $_SESSION['user_id']]);
$estadisticas = $stmtStats->fetch(PDO::FETCH_ASSOC);

// Obtener preguntas para triaje presencial
$preguntas = $triajeModel->getPreguntasActivas();

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
                        <i class="fas fa-clipboard-list"></i> Gestión de Triajes
                    </h2>
                    <p class="text-muted mb-0">Administrar triajes digitales y presenciales</p>
                </div>
            </div>

            <!-- Mensajes -->
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-calendar-day text-primary" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0"><?php echo $estadisticas['total_citas']; ?></h4>
                            <small class="text-muted">Citas del Día</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-check-circle text-success" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0"><?php echo $estadisticas['triajes_completados']; ?></h4>
                            <small class="text-muted">Triajes Completados</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-clock text-warning" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0"><?php echo $estadisticas['triajes_pendientes']; ?></h4>
                            <small class="text-muted">Triajes Pendientes</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-percentage text-info" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0">
                                <?php
                                echo $estadisticas['total_citas'] > 0 ? round(($estadisticas['triajes_completados'] / $estadisticas['total_citas']) * 100) : 0;
                                ?>%
                            </h4>
                            <small class="text-muted">Completitud</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="consultas/triaje/gestionar">

                        <div class="col-md-3">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" name="fecha" value="<?php echo $fecha; ?>">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Estado Triaje</label>
                            <select class="form-select" name="estado">
                                <option value="todos" <?php echo ($estado === 'todos') ? 'selected' : ''; ?>>Todos</option>
                                <option value="completado" <?php echo ($estado === 'completado') ? 'selected' : ''; ?>>Completados</option>
                                <option value="pendiente" <?php echo ($estado === 'pendiente') ? 'selected' : ''; ?>>Pendientes</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Buscar Paciente</label>
                            <input type="text" class="form-control" name="buscar" 
                                   value="<?php echo htmlspecialchars($buscar); ?>" 
                                   placeholder="Nombre, apellido o cédula...">
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de citas -->
            <div class="card border-0 shadow">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-list"></i> Citas del <?php echo date('d/m/Y', strtotime($fecha)); ?>
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($citas)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-muted">No hay citas para mostrar</h5>
                            <p class="text-muted">Ajuste los filtros o seleccione otra fecha</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Hora</th>
                                        <th>Paciente</th>
                                        <th>Contacto</th>
                                        <th>Médico</th>
                                        <th>Especialidad</th>
                                        <th>Estado Triaje</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($citas as $cita): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('H:i', strtotime($cita['hora_cita'])); ?></strong>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo $cita['paciente_nombre']; ?></strong>
                                                    <br>
                                                    <small class="text-muted">CI: <?php echo $cita['paciente_cedula']; ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($cita['paciente_telefono']): ?>
                                                    <a href="tel:<?php echo $cita['paciente_telefono']; ?>" class="text-decoration-none">
                                                        <i class="fas fa-phone text-primary"></i> <?php echo $cita['paciente_telefono']; ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">Sin teléfono</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $cita['medico_nombre']; ?></td>
                                            <td><?php echo $cita['nombre_especialidad']; ?></td>
                                            <td>
                                                <?php if ($cita['tiene_triaje'] > 0): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check"></i> Completado
                                                    </span>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo ucfirst($cita['tipo_triaje']); ?> - 
                                                        <?php echo date('H:i', strtotime($cita['fecha_triaje'])); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-clock"></i> Pendiente
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($cita['tiene_triaje'] > 0): ?>
                                                        <a href="index.php?action=consultas/triaje/ver&cita_id=<?php echo $cita['id_cita']; ?>" 
                                                           class="btn btn-outline-primary" title="Ver Triaje">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="btn btn-outline-success" 
                                                                onclick="abrirTriajePresencial(<?php echo $cita['id_cita']; ?>, '<?php echo addslashes($cita['paciente_nombre']); ?>')"
                                                                title="Triaje Presencial">
                                                            <i class="fas fa-clipboard-list"></i>
                                                        </button>
                                                        <button class="btn btn-outline-info" 
                                                                onclick="enviarRecordatorio(<?php echo $cita['id_cita']; ?>)"
                                                                title="Enviar Recordatorio">
                                                            <i class="fas fa-bell"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
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

<!-- Modal para Triaje Presencial -->
<div class="modal fade" id="triajePresencialModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-clipboard-list"></i> Triaje Presencial
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="triajePresencialForm">
                <input type="hidden" name="action" value="completar_presencial">
                <input type="hidden" name="cita_id" id="modalCitaId">

                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        Completando triaje presencial para: <strong id="modalPacienteNombre"></strong>
                    </div>

                    <div id="preguntasContainer">
                        <?php foreach ($preguntas as $index => $pregunta): ?>
                            <div class="mb-4">
                                <label class="form-label fw-bold">
                                    <?php echo ($index + 1) . '. ' . $pregunta['pregunta']; ?>
                                    <?php if (isset($pregunta['requerida']) && $pregunta['requerida']): ?>
                                        <span class="text-danger">*</span>
                                    <?php endif; ?>
                                </label>

                                <?php
                                $fieldName = "respuestas[{$pregunta['id_pregunta']}]";
                                $isRequired = $pregunta['requerida'] ? 'required' : '';

                                switch ($pregunta['tipo_pregunta']):
                                    case 'texto':
                                        ?>
                                        <input type="text" 
                                               class="form-control" 
                                               name="<?php echo $fieldName; ?>"
                                               <?php echo $isRequired; ?>
                                               placeholder="Escriba la respuesta del paciente...">
                                               <?php
                                               break;
                                           case 'textarea':
                                               ?>
                                        <textarea class="form-control" 
                                                  name="<?php echo $fieldName; ?>"
                                                  rows="3"
                                                  <?php echo $isRequired; ?>
                                                  placeholder="Describa detalladamente..."></textarea>
                                                  <?php
                                                  break;
                                              case 'numero':
                                                  ?>
                                        <input type="number" 
                                               class="form-control" 
                                               name="<?php echo $fieldName; ?>"
                                               <?php echo $isRequired; ?>
                                               min="0"
                                               step="0.1"
                                               placeholder="Ingrese un número...">
                                               <?php
                                               break;
                                           case 'select':
                                               $opciones = json_decode($pregunta['opciones'], true) ?: [];
                                               ?>
                                        <select class="form-select" name="<?php echo $fieldName; ?>" <?php echo $isRequired; ?>>
                                            <option value="">Seleccione una opción...</option>
                                            <?php foreach ($opciones as $opcion): ?>
                                                <option value="<?php echo htmlspecialchars($opcion); ?>">
                                                    <?php echo htmlspecialchars($opcion); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php
                                        break;
                                    case 'radio':
                                        $opciones = json_decode($pregunta['opciones'], true) ?: [];
                                        ?>
                                        <div class="row">
                                            <?php foreach ($opciones as $i => $opcion): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" 
                                                               type="radio" 
                                                               name="<?php echo $fieldName; ?>" 
                                                               id="modal_<?php echo $pregunta['id_pregunta'] . '_' . $i; ?>"
                                                               value="<?php echo htmlspecialchars($opcion); ?>"
                                                               <?php echo $isRequired; ?>>
                                                        <label class="form-check-label" 
                                                               for="modal_<?php echo $pregunta['id_pregunta'] . '_' . $i; ?>">
                                                                   <?php echo htmlspecialchars($opcion); ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php
                                        break;
                                endswitch;
                                ?>

                                <?php if (isset($pregunta['ayuda']) && $pregunta['ayuda']): ?>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle"></i> <?php echo $pregunta['ayuda']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Guardar Triaje
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function abrirTriajePresencial(citaId, pacienteNombre) {
        document.getElementById('modalCitaId').value = citaId;
        document.getElementById('modalPacienteNombre').textContent = pacienteNombre;

        // Limpiar formulario
        const form = document.getElementById('triajePresencialForm');
        const inputs = form.querySelectorAll('input[type="text"], input[type="number"], textarea, select');
        inputs.forEach(input => input.value = '');

        const radios = form.querySelectorAll('input[type="radio"]');
        radios.forEach(radio => radio.checked = false);

        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('triajePresencialModal'));
        modal.show();
    }

    function enviarRecordatorio(citaId) {
        if (confirm('¿Enviar recordatorio al paciente para completar el triaje digital?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
           <input type="hidden" name="action" value="enviar_recordatorio">
           <input type="hidden" name="cita_id" value="${citaId}">
       `;
            document.body.appendChild(form);
            form.submit();
        }
    }

// Validación del formulario
    document.getElementById('triajePresencialForm').addEventListener('submit', function (e) {
        const requiredFields = this.querySelectorAll('[required]');
        let allValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                allValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });

        if (!allValid) {
            e.preventDefault();
            alert('Por favor complete todos los campos requeridos');
        }
    });
</script>

<?php include 'views/includes/footer.php'; ?>