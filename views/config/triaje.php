<?php
require_once 'models/PreguntaTriaje.php';

// Verificar autenticación y permisos de administrador
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php?action=dashboard');
    exit;
}

$preguntaModel = new PreguntaTriaje();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'toggle_estado':
                $preguntaId = $_POST['pregunta_id'];
                $estado = $_POST['estado'];
                $preguntaModel->toggleEstado($preguntaId, $estado);
                $success = "Estado de la pregunta actualizado";
                break;
                
            case 'update_orden':
                $ordenes = $_POST['ordenes'] ?? [];
                $preguntaModel->updateOrdenes($ordenes);
                $success = "Orden de preguntas actualizado";
                break;
                
            case 'delete':
                $preguntaId = $_POST['pregunta_id'];
                $preguntaModel->deletePregunta($preguntaId);
                $success = "Pregunta eliminada correctamente";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Parámetros de búsqueda y filtros
$search = $_GET['search'] ?? '';
$tipo_filter = $_GET['tipo_filter'] ?? '';
$estado_filter = $_GET['estado_filter'] ?? '';

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
                        <i class="fas fa-clipboard-list"></i> Gestión de Preguntas de Triaje
                    </h2>
                    <p class="text-muted mb-0">Configurar preguntas del triaje digital</p>
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

            <!-- Estadísticas rápidas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="h3 text-primary mb-1"><?php echo $estadisticas['total_preguntas']; ?></div>
                            <small class="text-muted">Total Preguntas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="h3 text-success mb-1"><?php echo $estadisticas['preguntas_activas']; ?></div>
                            <small class="text-muted">Activas</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="h3 text-warning mb-1"><?php echo $estadisticas['preguntas_obligatorias']; ?></div>
                            <small class="text-muted">Obligatorias</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <div class="h3 text-info mb-1"><?php echo $estadisticas['respuestas_mes']; ?></div>
                            <small class="text-muted">Respuestas este mes</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mensajes -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" action="index.php">
                        <input type="hidden" name="action" value="config/triaje">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Buscar</label>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Texto de la pregunta..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tipo de Pregunta</label>
                                <select class="form-select" name="tipo_filter">
                                    <option value="">Todos los tipos</option>
                                    <option value="texto" <?php echo $tipo_filter == 'texto' ? 'selected' : ''; ?>>Texto</option>
                                    <option value="numero" <?php echo $tipo_filter == 'numero' ? 'selected' : ''; ?>>Número</option>
                                    <option value="escala" <?php echo $tipo_filter == 'escala' ? 'selected' : ''; ?>>Escala</option>
                                    <option value="multiple" <?php echo $tipo_filter == 'multiple' ? 'selected' : ''; ?>>Opción Múltiple</option>
                                    <option value="sino" <?php echo $tipo_filter == 'sino' ? 'selected' : ''; ?>>Sí/No</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="estado_filter">
                                    <option value="">Todos los estados</option>
                                    <option value="1" <?php echo $estado_filter == '1' ? 'selected' : ''; ?>>Activa</option>
                                    <option value="0" <?php echo $estado_filter == '0' ? 'selected' : ''; ?>>Inactiva</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i> Buscar
                                    </button>
                                    <a href="index.php?action=config/triaje" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i> Limpiar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de preguntas -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Lista de Preguntas 
                        <span class="badge bg-primary"><?php echo count($preguntas); ?></span>
                    </h5>
                    <div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleSortMode()">
                            <i class="fas fa-sort"></i> Reordenar
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($preguntas)): ?>
                        <form id="ordenForm" method="POST" style="display: none;">
                            <input type="hidden" name="action" value="update_orden">
                        </form>
                        
                        <div id="preguntasList" class="sortable-list">
                            <?php foreach ($preguntas as $pregunta): ?>
                                <div class="pregunta-item border-bottom p-3" data-id="<?php echo $pregunta['id_pregunta']; ?>">
                                    <div class="row align-items-center">
                                        <div class="col-md-1 text-center">
                                            <div class="orden-badge">
                                                <span class="badge bg-secondary"><?php echo $pregunta['orden']; ?></span>
                                            </div>
                                            <div class="drag-handle" style="display: none;">
                                                <i class="fas fa-grip-vertical text-muted"></i>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-start">
                                                <div class="me-3">
                                                    <div class="tipo-icon">
                                                        <?php
                                                        $iconos = [
                                                            'texto' => 'fas fa-font',
                                                            'numero' => 'fas fa-hashtag',
                                                            'escala' => 'fas fa-slider-h',
                                                            'multiple' => 'fas fa-list-ul',
                                                            'sino' => 'fas fa-check-circle'
                                                        ];
                                                        $colores = [
                                                            'texto' => 'primary',
                                                            'numero' => 'success',
                                                            'escala' => 'warning',
                                                            'multiple' => 'info',
                                                            'sino' => 'secondary'
                                                        ];
                                                        ?>
                                                        <div class="avatar-sm bg-<?php echo $colores[$pregunta['tipo_pregunta']]; ?> rounded-circle d-flex align-items-center justify-content-center">
                                                            <i class="<?php echo $iconos[$pregunta['tipo_pregunta']]; ?> text-white"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($pregunta['pregunta']); ?></h6>
                                                    <div class="d-flex gap-2 flex-wrap">
                                                        <span class="badge bg-<?php echo $colores[$pregunta['tipo_pregunta']]; ?>">
                                                            <?php echo ucfirst($pregunta['tipo_pregunta']); ?>
                                                        </span>
                                                        <?php if ($pregunta['obligatoria']): ?>
                                                            <span class="badge bg-danger">Obligatoria</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-dark">Opcional</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if ($pregunta['opciones_json']): ?>
                                                        <small class="text-muted d-block mt-1">
                                                            <i class="fas fa-cog me-1"></i>Con opciones configuradas
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <div class="form-check form-switch d-flex justify-content-center">
                                                <input class="form-check-input" type="checkbox" 
                                                       <?php echo $pregunta['activo'] ? 'checked' : ''; ?>
                                                       onchange="toggleEstado(<?php echo $pregunta['id_pregunta']; ?>, this.checked)">
                                            </div>
                                            <small class="text-muted">
                                                <?php echo $pregunta['activo'] ? 'Activa' : 'Inactiva'; ?>
                                            </small>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <small class="text-muted d-block">
                                                Respuestas: <?php echo $pregunta['total_respuestas'] ?? 0; ?>
                                            </small>
                                        </div>
                                        <div class="col-md-1 text-center">
                                            <div class="btn-group-vertical">
                                                <a href="index.php?action=config/triaje/edit&id=<?php echo $pregunta['id_pregunta']; ?>" 
                                                   class="btn btn-sm btn-outline-primary mb-1" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if (($pregunta['total_respuestas'] ?? 0) == 0): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="confirmDelete(<?php echo $pregunta['id_pregunta']; ?>, '<?php echo htmlspecialchars(substr($pregunta['pregunta'], 0, 30)); ?>...')"
                                                            title="Eliminar">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div id="sortActions" class="p-3 bg-light border-top" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Arrastra las preguntas para cambiar el orden
                                </small>
                                <div>
                                    <button type="button" class="btn btn-secondary me-2" onclick="cancelSort()">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                    <button type="button" class="btn btn-primary" onclick="saveOrder()">
                                        <i class="fas fa-save"></i> Guardar Orden
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-clipboard-question fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No hay preguntas configuradas</h5>
                            <p class="text-muted">Crea la primera pregunta para el triaje digital.</p>
                            <a href="index.php?action=config/triaje/create" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Crear Primera Pregunta
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación de eliminación -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Está seguro que desea eliminar la pregunta <strong id="preguntaText"></strong>?</p>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i>
                    <strong>Nota:</strong> Esta acción eliminará permanentemente la pregunta y no se podrá deshacer.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="pregunta_id" id="preguntaIdToDelete">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Eliminar Pregunta
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .avatar-sm {
        width: 35px;
        height: 35px;
        font-size: 14px;
    }
    
    .pregunta-item {
        transition: all 0.3s ease;
    }
    
    .pregunta-item:hover {
        background-color: #f8f9fa;
    }
    
    .sortable-list .pregunta-item.dragging {
        opacity: 0.5;
        transform: rotate(2deg);
    }
    
    .drag-handle {
        cursor: move;
    }
    
    .drag-handle:hover {
        color: #007bff !important;
    }
    
    .form-check-input:checked {
        background-color: #28a745;
        border-color: #28a745;
    }
    
    .btn-group-vertical .btn {
        border-radius: 0.375rem !important;
    }
    
    .hover-lift:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
    let sortable = null;
    let originalOrder = [];
    
    function toggleSortMode() {
        const sortActions = document.getElementById('sortActions');
        const dragHandles = document.querySelectorAll('.drag-handle');
        const ordenBadges = document.querySelectorAll('.orden-badge');
        
        if (sortActions.style.display === 'none') {
            // Activar modo ordenamiento
            sortActions.style.display = 'block';
            dragHandles.forEach(handle => handle.style.display = 'block');
            ordenBadges.forEach(badge => badge.style.display = 'none');
            
            // Guardar orden original
            originalOrder = Array.from(document.querySelectorAll('.pregunta-item')).map(item => item.dataset.id);
            
            // Inicializar Sortable
            const container = document.getElementById('preguntasList');
            sortable = Sortable.create(container, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'dragging'
            });
        }
    }
    
    function cancelSort() {
        // Restaurar orden original
        const container = document.getElementById('preguntasList');
        const items = Array.from(container.children);
        
        originalOrder.forEach((id, index) => {
            const item = items.find(item => item.dataset.id === id);
            if (item) {
                container.appendChild(item);
            }
        });
        
        exitSortMode();
    }
    
    function saveOrder() {
        const items = document.querySelectorAll('.pregunta-item');
        const ordenes = {};
        
        items.forEach((item, index) => {
            ordenes[item.dataset.id] = index + 1;
        });
        
        // Crear campos hidden y enviar
        const form = document.getElementById('ordenForm');
        form.innerHTML = '<input type="hidden" name="action" value="update_orden">';
        
        Object.keys(ordenes).forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `ordenes[${id}]`;
            input.value = ordenes[id];
            form.appendChild(input);
        });
        
        form.submit();
    }
    
    function exitSortMode() {
        const sortActions = document.getElementById('sortActions');
        const dragHandles = document.querySelectorAll('.drag-handle');
        const ordenBadges = document.querySelectorAll('.orden-badge');
        
        sortActions.style.display = 'none';
        dragHandles.forEach(handle => handle.style.display = 'none');
        ordenBadges.forEach(badge => badge.style.display = 'block');
        
        if (sortable) {
            sortable.destroy();
            sortable = null;
        }
    }
    
    function toggleEstado(preguntaId, estado) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        form.innerHTML = `
            <input type="hidden" name="action" value="toggle_estado">
            <input type="hidden" name="pregunta_id" value="${preguntaId}">
            <input type="hidden" name="estado" value="${estado ? 1 : 0}">
        `;
        
        document.body.appendChild(form);
        form.submit();
    }
    
    function confirmDelete(preguntaId, preguntaText) {
        document.getElementById('preguntaText').textContent = preguntaText;
        document.getElementById('preguntaIdToDelete').value = preguntaId;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>

</body>
</html>