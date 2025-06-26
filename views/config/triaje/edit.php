<?php
require_once 'models/PreguntaTriaje.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php?action=dashboard');
    exit;
}

$preguntaModel = new PreguntaTriaje();

$preguntaId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Obtener pregunta a editar
$pregunta = $preguntaModel->getPreguntaById($preguntaId);
if (!$pregunta) {
    header('Location: index.php?action=config/triaje');
    exit;
}

// Decodificar opciones JSON
$opciones_data = null;
if ($pregunta['opciones_json']) {
    $opciones_data = json_decode($pregunta['opciones_json'], true);
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'pregunta' => trim($_POST['pregunta']),
            'tipo_pregunta' => $_POST['tipo_pregunta'],
            'opciones_json' => null,
            'obligatoria' => isset($_POST['obligatoria']) ? 1 : 0,
            'orden' => (int) ($_POST['orden'] ?? $pregunta['orden'])
        ];

        // Validaciones básicas
        if (empty($data['pregunta'])) {
            throw new Exception("El texto de la pregunta es obligatorio");
        }

        if (empty($data['tipo_pregunta'])) {
            throw new Exception("Debe seleccionar un tipo de pregunta");
        }

        // Procesar opciones según el tipo (mismo código que en create.php)
        switch ($data['tipo_pregunta']) {
            case 'multiple':
                $opciones = array_filter(array_map('trim', $_POST['opciones_multiple'] ?? []));
                if (count($opciones) < 2) {
                    throw new Exception("Las preguntas de opción múltiple deben tener al menos 2 opciones");
                }
                $data['opciones_json'] = json_encode($opciones);
                break;

            case 'escala':
                $escala_data = [
                    'min' => (int) ($_POST['escala_min'] ?? 1),
                    'max' => (int) ($_POST['escala_max'] ?? 10),
                    'etiquetas' => []
                ];

                if ($escala_data['min'] >= $escala_data['max']) {
                    throw new Exception("El valor máximo debe ser mayor que el mínimo");
                }

                if (!empty($_POST['etiqueta_min'])) {
                    $escala_data['etiquetas'][$escala_data['min']] = trim($_POST['etiqueta_min']);
                }
                if (!empty($_POST['etiqueta_max'])) {
                    $escala_data['etiquetas'][$escala_data['max']] = trim($_POST['etiqueta_max']);
                }
                if (!empty($_POST['etiqueta_medio'])) {
                    $medio = round(($escala_data['min'] + $escala_data['max']) / 2);
                    $escala_data['etiquetas'][$medio] = trim($_POST['etiqueta_medio']);
                }

                $data['opciones_json'] = json_encode($escala_data);
                break;

            case 'numero':
                if (!empty($_POST['numero_min']) || !empty($_POST['numero_max']) || !empty($_POST['numero_unidad'])) {
                    $numero_data = [];
                    if (!empty($_POST['numero_min']))
                        $numero_data['min'] = (float) $_POST['numero_min'];
                    if (!empty($_POST['numero_max']))
                        $numero_data['max'] = (float) $_POST['numero_max'];
                    if (!empty($_POST['numero_unidad']))
                        $numero_data['unidad'] = trim($_POST['numero_unidad']);

                    $data['opciones_json'] = json_encode($numero_data);
                }
                break;
        }

        $preguntaModel->updatePregunta($preguntaId, $data);
        $success = "Pregunta actualizada exitosamente";

        // Recargar datos de la pregunta
        $pregunta = $preguntaModel->getPreguntaById($preguntaId);
        if ($pregunta['opciones_json']) {
            $opciones_data = json_decode($pregunta['opciones_json'], true);
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

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
                        <i class="fas fa-edit"></i> Editar Pregunta de Triaje
                    </h2>
                    <p class="text-muted mb-0">Modificar pregunta: <strong><?php echo htmlspecialchars(substr($pregunta['pregunta'], 0, 50)); ?>...</strong></p>
                </div>
                <div>
                    <a href="index.php?action=config/triaje" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>

            <!-- Mensajes -->
<?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

<?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
<?php endif; ?>

            <!-- Formulario -->
            <form method="POST" id="preguntaForm">
                <div class="row">
                    <!-- Información actual -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle"></i> Información Actual
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <div class="avatar-lg bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-2">
                                        <?php
                                        $iconos = [
                                            'texto' => 'fas fa-font',
                                            'numero' => 'fas fa-hashtag',
                                            'escala' => 'fas fa-slider-h',
                                            'multiple' => 'fas fa-list-ul',
                                            'sino' => 'fas fa-check-circle'
                                        ];
                                        ?>
                                        <i class="<?php echo $iconos[$pregunta['tipo_pregunta']]; ?> fa-2x text-white"></i>
                                    </div>
                                    <h6>Pregunta #<?php echo $pregunta['orden']; ?></h6>
                                    <span class="badge bg-<?php echo $pregunta['activo'] == 1 ? 'success' : 'danger'; ?>">
<?php echo $pregunta['activo'] == 1 ? 'Activa' : 'Inactiva'; ?>
                                    </span>
                                </div>

                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <strong>Tipo:</strong> <?php echo ucfirst($pregunta['tipo_pregunta']); ?>
                                    </li>
                                    <li class="mb-2">
                                        <strong>Obligatoria:</strong> 
<?php echo $pregunta['obligatoria'] ? 'Sí' : 'No'; ?>
                                    </li>
                                    <li class="mb-2">
                                        <strong>Creada:</strong> <?php echo date('d/m/Y', strtotime($pregunta['fecha_creacion'])); ?>
                                    </li>
                                    <li class="mb-2">
                                        <strong>Respuestas:</strong> 
<?php echo $preguntaModel->countRespuestasByPregunta($preguntaId); ?>
                                    </li>
                                </ul>

<?php if ($pregunta['opciones_json']): ?>
                                    <div class="mt-3">
                                        <strong>Configuración actual:</strong>
                                        <div class="mt-2 p-2 bg-light rounded">
                                            <small class="text-muted">
                                                <?php
                                                switch ($pregunta['tipo_pregunta']) {
                                                    case 'multiple':
                                                        echo count($opciones_data) . " opciones configuradas";
                                                        break;
                                                    case 'escala':
                                                        echo "Escala de {$opciones_data['min']} a {$opciones_data['max']}";
                                                        break;
                                                    case 'numero':
                                                        $config = [];
                                                        if (isset($opciones_data['min']))
                                                            $config[] = "Mín: {$opciones_data['min']}";
                                                        if (isset($opciones_data['max']))
                                                            $config[] = "Máx: {$opciones_data['max']}";
                                                        if (isset($opciones_data['unidad']))
                                                            $config[] = "Unidad: {$opciones_data['unidad']}";
                                                        echo implode(', ', $config);
                                                        break;
                                                }
                                                ?>
                                            </small>
                                        </div>
                                    </div>
<?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario de edición -->
                    <div class="col-lg-8">
                        <!-- Información básica -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-question-circle"></i> Información Básica </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Texto de la Pregunta <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="pregunta" rows="3" required><?php echo htmlspecialchars($pregunta['pregunta']); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tipo de Pregunta <span class="text-danger">*</span></label>
                                            <select class="form-select" name="tipo_pregunta" required onchange="updateTipoPregunta()">
                                                <option value="texto" <?php echo ($pregunta['tipo_pregunta'] == 'texto') ? 'selected' : ''; ?>>Texto Libre</option>
                                                <option value="numero" <?php echo ($pregunta['tipo_pregunta'] == 'numero') ? 'selected' : ''; ?>>Número</option>
                                                <option value="escala" <?php echo ($pregunta['tipo_pregunta'] == 'escala') ? 'selected' : ''; ?>>Escala (1-10)</option>
                                                <option value="multiple" <?php echo ($pregunta['tipo_pregunta'] == 'multiple') ? 'selected' : ''; ?>>Opción Múltiple</option>
                                                <option value="sino" <?php echo ($pregunta['tipo_pregunta'] == 'sino') ? 'selected' : ''; ?>>Sí/No</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Orden</label>
                                            <input type="number" class="form-control" name="orden" 
                                                   value="<?php echo $pregunta['orden']; ?>" 
                                                   min="1" max="100">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Configuración</label>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="obligatoria" 
                                                       id="obligatoria" value="1"
<?php echo $pregunta['obligatoria'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="obligatoria">
                                                    Obligatoria
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Configuración específica por tipo -->

                        <!-- Opción Múltiple -->
                        <div id="config_multiple" class="card border-0 shadow-sm mb-4" style="display: none;">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-list-ul"></i> Configuración - Opción Múltiple
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Opciones de Respuesta</label>
                                    <div id="opciones_container">
                                        <?php
                                        $opciones_actuales = [];
                                        if ($pregunta['tipo_pregunta'] == 'multiple' && $opciones_data) {
                                            $opciones_actuales = $opciones_data;
                                        }
                                        if (empty($opciones_actuales)) {
                                            $opciones_actuales = ['', ''];
                                        }

                                        foreach ($opciones_actuales as $i => $opcion):
                                            ?>
                                            <div class="input-group mb-2 opcion-item">
                                                <span class="input-group-text"><?php echo $i + 1; ?></span>
                                                <input type="text" class="form-control" name="opciones_multiple[]" 
                                                       value="<?php echo htmlspecialchars($opcion); ?>">
                                                <button type="button" class="btn btn-outline-danger" onclick="removeOpcion(this)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
<?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addOpcion()">
                                        <i class="fas fa-plus"></i> Agregar Opción
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Escala -->
                        <div id="config_escala" class="card border-0 shadow-sm mb-4" style="display: none;">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="fas fa-slider-h"></i> Configuración - Escala
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Valor Mínimo</label>
                                            <input type="number" class="form-control" name="escala_min" 
                                                   value="<?php echo ($pregunta['tipo_pregunta'] == 'escala' && $opciones_data) ? $opciones_data['min'] : '1'; ?>" 
                                                   min="0" max="10">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Valor Máximo</label>
                                            <input type="number" class="form-control" name="escala_max" 
                                                   value="<?php echo ($pregunta['tipo_pregunta'] == 'escala' && $opciones_data) ? $opciones_data['max'] : '10'; ?>" 
                                                   min="1" max="20">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Etiquetas (Opcionales)</label>
                                        <div class="row">
                                            <?php
                                            $etiquetas = ($pregunta['tipo_pregunta'] == 'escala' && $opciones_data && isset($opciones_data['etiquetas'])) ? $opciones_data['etiquetas'] : [];
                                            $min_val = ($pregunta['tipo_pregunta'] == 'escala' && $opciones_data) ? $opciones_data['min'] : 1;
                                            $max_val = ($pregunta['tipo_pregunta'] == 'escala' && $opciones_data) ? $opciones_data['max'] : 10;
                                            $medio_val = round(($min_val + $max_val) / 2);
                                            ?>
                                            <div class="col-4">
                                                <input type="text" class="form-control form-control-sm" name="etiqueta_min" 
                                                       value="<?php echo htmlspecialchars($etiquetas[$min_val] ?? ''); ?>" 
                                                       placeholder="Etiqueta mín.">
                                            </div>
                                            <div class="col-4">
                                                <input type="text" class="form-control form-control-sm" name="etiqueta_medio" 
                                                       value="<?php echo htmlspecialchars($etiquetas[$medio_val] ?? ''); ?>" 
                                                       placeholder="Etiqueta medio">
                                            </div>
                                            <div class="col-4">
                                                <input type="text" class="form-control form-control-sm" name="etiqueta_max" 
                                                       value="<?php echo htmlspecialchars($etiquetas[$max_val] ?? ''); ?>" 
                                                       placeholder="Etiqueta máx.">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Número -->
                        <div id="config_numero" class="card border-0 shadow-sm mb-4" style="display: none;">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-hashtag"></i> Configuración - Número
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
<?php
$numero_config = ($pregunta['tipo_pregunta'] == 'numero' && $opciones_data) ? $opciones_data : [];
?>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Valor Mínimo (Opcional)</label>
                                            <input type="number" class="form-control" name="numero_min" 
                                                   value="<?php echo htmlspecialchars($numero_config['min'] ?? ''); ?>" 
                                                   step="0.1">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Valor Máximo (Opcional)</label>
                                            <input type="number" class="form-control" name="numero_max" 
                                                   value="<?php echo htmlspecialchars($numero_config['max'] ?? ''); ?>" 
                                                   step="0.1">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Unidad (Opcional)</label>
                                            <input type="text" class="form-control" name="numero_unidad" 
                                                   value="<?php echo htmlspecialchars($numero_config['unidad'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Información sobre otros tipos -->
                        <div id="config_texto" class="card border-0 shadow-sm mb-4" style="display: none;">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-font"></i> Configuración - Texto Libre
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Las preguntas de texto libre no requieren configuración adicional.
                                </div>
                            </div>
                        </div>

                        <div id="config_sino" class="card border-0 shadow-sm mb-4" style="display: none;">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-check-circle"></i> Configuración - Sí/No
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Las preguntas de Sí/No no requieren configuración adicional.
                                </div>
                            </div>
                        </div>

                        <!-- Botones de acción -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="index.php?action=config/triaje" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Actualizar Pregunta
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .avatar-lg {
        width: 80px;
        height: 80px;
    }

    .opcion-item .input-group-text {
        min-width: 40px;
        justify-content: center;
    }
</style>

<script>
    let opcionCounter = <?php echo count($opciones_actuales ?? ['', '']); ?>;

    function updateTipoPregunta() {
        const tipo = document.querySelector('select[name="tipo_pregunta"]').value;

        // Ocultar todas las configuraciones
        document.querySelectorAll('[id^="config_"]').forEach(el => {
            el.style.display = 'none';
        });

        // Mostrar configuración específica
        if (tipo) {
            const configEl = document.getElementById(`config_${tipo}`);
            if (configEl) {
                configEl.style.display = 'block';
            }
        }
    }

    function addOpcion() {
        opcionCounter++;
        const container = document.getElementById('opciones_container');
        const div = document.createElement('div');
        div.className = 'input-group mb-2 opcion-item';
        div.innerHTML = `
           <span class="input-group-text">${opcionCounter}</span>
           <input type="text" class="form-control" name="opciones_multiple[]" placeholder="Escriba una opción...">
           <button type="button" class="btn btn-outline-danger" onclick="removeOpcion(this)">
               <i class="fas fa-times"></i>
           </button>
       `;
        container.appendChild(div);
        updateOpcionNumbers();
    }

    function removeOpcion(button) {
        if (document.querySelectorAll('.opcion-item').length > 2) {
            button.closest('.opcion-item').remove();
            updateOpcionNumbers();
        }
    }

    function updateOpcionNumbers() {
        document.querySelectorAll('.opcion-item').forEach((item, index) => {
            item.querySelector('.input-group-text').textContent = index + 1;
        });
    }

    // Inicializar vista
    document.addEventListener('DOMContentLoaded', function () {
        updateTipoPregunta();
    });
</script>

</body>
</html>