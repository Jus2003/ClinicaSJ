<?php
require_once 'models/PreguntaTriaje.php';

// Verificar que sea administrador
if ($_SESSION['role_id'] != 1) {
    header('Location: index.php?action=dashboard');
    exit;
}

$preguntaModel = new PreguntaTriaje();
$error = '';
$success = '';

// Filtros
$search = $_GET['search'] ?? '';
$tipo_filter = $_GET['tipo_filter'] ?? '';
$estado_filter = $_GET['estado_filter'] ?? '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'toggle_estado':
                $id = $_POST['pregunta_id'];
                $preguntaModel->toggleEstado($id);
                $success = "Estado de la pregunta actualizado";
                break;

            case 'delete':
                $id = $_POST['pregunta_id'];
                $preguntaModel->deletePregunta($id);
                $success = "Pregunta eliminada exitosamente";
                break;

            case 'reorder':
                $orders = $_POST['orders'] ?? [];
                foreach ($orders as $id => $orden) {
                    $preguntaModel->updateOrden($id, $orden);
                }
                $success = "Orden de preguntas actualizado";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener preguntas
$preguntas = $preguntaModel->getAllPreguntasForAdmin($search, $tipo_filter, $estado_filter);
$estadisticas = $preguntaModel->getEstadisticasTriaje();

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
                        <i class="fas fa-clipboard-list"></i> Configuración de Triaje Digital
                    </h2>
                    <p class="text-muted mb-0">Gestionar preguntas y configuración del sistema de triaje</p>
                </div>
                <div>
                    <a href="index.php?action=config/triaje/preview" class="btn btn-info me-2">
                        <i class="fas fa-eye"></i> Vista Previa
                    </a>
                    <a href="index.php?action=config/triaje/create" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nueva Pregunta
                    </a>
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
                            <i class="fas fa-question-circle text-primary" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0"><?php echo $estadisticas['total_preguntas']; ?></h4>
                            <small class="text-muted">Total Preguntas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-toggle-on text-success" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0"><?php echo $estadisticas['preguntas_activas']; ?></h4>
                            <small class="text-muted">Preguntas Activas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-clipboard-check text-info" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0"><?php echo $estadisticas['triajes_completados_hoy'] ?? 0; ?></h4>
                            <small class="text-muted">Triajes Hoy</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line text-warning" style="font-size: 2rem;"></i>
                            <h4 class="mt-2 mb-0"><?php echo $estadisticas['promedio_respuestas'] ?? 0; ?></h4>
                            <small class="text-muted">Promedio Respuestas</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="consultas/triaje/index">

                        <div class="col-md-4">
                            <label class="form-label">Buscar</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Buscar por pregunta...">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" name="tipo_filter">
                                <option value="">Todos los tipos</option>
                                <option value="texto" <?php echo ($tipo_filter === 'texto') ? 'selected' : ''; ?>>Texto</option>
                                <option value="textarea" <?php echo ($tipo_filter === 'textarea') ? 'selected' : ''; ?>>Área de texto</option>
                                <option value="numero" <?php echo ($tipo_filter === 'numero') ? 'selected' : ''; ?>>Número</option>
                                <option value="select" <?php echo ($tipo_filter === 'select') ? 'selected' : ''; ?>>Selección</option>
                                <option value="radio" <?php echo ($tipo_filter === 'radio') ? 'selected' : ''; ?>>Opción múltiple</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado_filter">
                                <option value="">Todos</option>
                                <option value="1" <?php echo ($estado_filter === '1') ? 'selected' : ''; ?>>Activas</option>
                                <option value="0" <?php echo ($estado_filter === '0') ? 'selected' : ''; ?>>Inactivas</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de preguntas -->
            <div class="card border-0 shadow">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-list"></i> Preguntas del Triaje
                    </h6>
                    <button class="btn btn-sm btn-outline-primary" onclick="activarModoReorden()">
                        <i class="fas fa-sort"></i> Reordenar
                    </button>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($preguntas)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-question-circle text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3 text-muted">No hay preguntas configuradas</h5>
                            <p class="text-muted">Comience creando la primera pregunta del triaje</p>
                            <a href="index.php?action=config/triaje/create" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Crear Primera Pregunta
                            </a>
                        </div>
                    <?php else: ?>
                        <form method="POST" id="reorderForm" style="display: none;">
                            <input type="hidden" name="action" value="reorder">
                        </form>

                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="preguntasTable">
                                <thead class="table-light">
                                    <tr>
                                        <th width="60">Orden</th>
                                        <th>Pregunta</th>
                                        <th>Tipo</th>
                                        <th>Opciones</th>
                                        <th>Requerida</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="preguntasTableBody">
                                    <?php foreach ($preguntas as $pregunta): ?>
                                        <tr data-id="<?php echo $pregunta['id_pregunta']; ?>">
                                            <td>
                                                <span class="handle btn btn-sm btn-outline-secondary" style="display: none; cursor: move;">
                                                    <i class="fas fa-grip-vertical"></i>
                                                </span>
                                                <span class="orden-display"><?php echo $pregunta['orden'] ?? 0; ?></span>
                                                <input type="hidden" name="orders[<?php echo $pregunta['id_pregunta']; ?>]" 
                                                       value="<?php echo $pregunta['orden'] ?? 0; ?>" form="reorderForm">
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($pregunta['pregunta']); ?></strong>
                                                    <?php if (isset($pregunta['ayuda']) && $pregunta['ayuda']): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="fas fa-info-circle"></i> 
                                                            <?php echo htmlspecialchars($pregunta['ayuda']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucfirst($pregunta['tipo_pregunta'] ?? 'texto'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (in_array($pregunta['tipo_pregunta'] ?? '', ['select', 'radio']) && isset($pregunta['opciones']) && $pregunta['opciones']): ?>
                                                    <?php
                                                    $opciones = json_decode($pregunta['opciones'], true);
                                                    if ($opciones && count($opciones) > 0):
                                                        ?>
                                                        <small class="text-muted">
                                                            <?php echo count($opciones); ?> opciones:
                                                            <br>
                                                            <?php echo implode(', ', array_slice($opciones, 0, 2)); ?>
                                                            <?php if (count($opciones) > 2): ?>
                                                                <span class="text-muted">... (+<?php echo count($opciones) - 2; ?>)</span>
                                                            <?php endif; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (isset($pregunta['requerida']) && $pregunta['requerida']): ?>
                                                    <span class="badge bg-warning">
                                                        <i class="fas fa-asterisk"></i> Requerida
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Opcional</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_estado">
                                                    <input type="hidden" name="pregunta_id" value="<?php echo $pregunta['id_pregunta']; ?>">
                                                    <button type="submit" class="btn btn-sm <?php echo ($pregunta['activa'] ?? 0) ? 'btn-success' : 'btn-secondary'; ?>">
                                                        <i class="fas fa-<?php echo ($pregunta['activa'] ?? 0) ? 'toggle-on' : 'toggle-off'; ?>"></i>
                                                        <?php echo ($pregunta['activa'] ?? 0) ? 'Activa' : 'Inactiva'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="index.php?action=config/triaje/edit&id=<?php echo $pregunta['id_pregunta']; ?>" 
                                                       class="btn btn-outline-primary" title="Editar">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="eliminarPregunta(<?php echo $pregunta['id_pregunta']; ?>, '<?php echo addslashes(htmlspecialchars($pregunta['pregunta'])); ?>')"
                                                            title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="card-footer bg-light" id="reorderControls" style="display: none;">
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" onclick="cancelarReorden()">
                                    <i class="fas fa-times"></i> Cancelar
                                </button>
                                <button type="button" class="btn btn-primary" onclick="guardarReorden()">
                                    <i class="fas fa-save"></i> Guardar Orden
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
                                    let sortable = null;
                                    let modoReorden = false;

                                    function activarModoReorden() {
                                        modoReorden = true;

                                        // Mostrar controles de reorden
                                        document.getElementById('reorderControls').style.display = 'block';
                                        document.querySelectorAll('.handle').forEach(el => el.style.display = 'inline-block');

                                        // Inicializar Sortable
                                        const tbody = document.getElementById('preguntasTableBody');
                                        sortable = Sortable.create(tbody, {
                                            handle: '.handle',
                                            animation: 150,
                                            onEnd: function (evt) {
                                                actualizarOrden();
                                            }
                                        });
                                    }

                                    function cancelarReorden() {
                                        modoReorden = false;

                                        // Ocultar controles
                                        document.getElementById('reorderControls').style.display = 'none';
                                        document.querySelectorAll('.handle').forEach(el => el.style.display = 'none');

                                        // Destruir Sortable
                                        if (sortable) {
                                            sortable.destroy();
                                            sortable = null;
                                        }

                                        // Recargar página para restaurar orden original
                                        location.reload();
                                    }

                                    function guardarReorden() {
                                        if (confirm('¿Guardar el nuevo orden de las preguntas?')) {
                                            document.getElementById('reorderForm').submit();
                                        }
                                    }

                                    function actualizarOrden() {
                                        const filas = document.querySelectorAll('#preguntasTableBody tr');
                                        filas.forEach((fila, index) => {
                                            const id = fila.dataset.id;
                                            const orden = index + 1;

                                            // Actualizar display
                                            fila.querySelector('.orden-display').textContent = orden;

                                            // Actualizar input hidden
                                            fila.querySelector(`input[name="orders[${id}]"]`).value = orden;
                                        });
                                    }

                                    function eliminarPregunta(id, pregunta) {
                                        if (confirm(`¿Está seguro de eliminar la pregunta "${pregunta}"?\n\nEsta acción no se puede deshacer.`)) {
                                            const form = document.createElement('form');
                                            form.method = 'POST';
                                            form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="pregunta_id" value="${id}">
        `;
                                            document.body.appendChild(form);
                                            form.submit();
                                        }
                                    }
</script>
