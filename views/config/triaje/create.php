<?php
require_once 'models/PreguntaTriaje.php';

// Verificar autenticación y permisos (roles 1, 2 o 3)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2, 3])) {
    header('Location: index.php?action=dashboard');
    exit;
}

$preguntaModel = new PreguntaTriaje();

$error = '';
$success = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'pregunta' => trim($_POST['pregunta']),
            'tipo_pregunta' => $_POST['tipo_pregunta'],
            'opciones_json' => null,
            'obligatoria' => isset($_POST['obligatoria']) ? 1 : 0,
            'orden' => (int) ($_POST['orden'] ?? 0)
        ];

        // Validaciones básicas
        if (empty($data['pregunta'])) {
            throw new Exception("El texto de la pregunta es obligatorio");
        }

        if (empty($data['tipo_pregunta'])) {
            throw new Exception("Debe seleccionar un tipo de pregunta");
        }

        // Procesar opciones según el tipo
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

                // Procesar etiquetas opcionales
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

        // Si no se especificó orden, usar el siguiente disponible
        if ($data['orden'] == 0) {
            $data['orden'] = $preguntaModel->getNextOrden();
        }

        $preguntaId = $preguntaModel->createPregunta($data);
        $success = "Pregunta creada exitosamente";

        // Limpiar formulario
        $_POST = [];
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$nextOrden = $preguntaModel->getNextOrden();

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
                        <i class="fas fa-plus-circle"></i> Nueva Pregunta de Triaje
                    </h2>
                    <p class="text-muted mb-0">Crear una nueva pregunta para el triaje digital</p>
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
                    <!-- Formulario principal -->
                    <div class="col-lg-8">
                        <!-- Información básica -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-question-circle"></i> Información Básica
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Texto de la Pregunta <span class="text-danger">*</span></label>
                                            <textarea class="form-control" name="pregunta" rows="3" 
                                                      placeholder="Escriba la pregunta que se mostrará al paciente..."
                                                      required><?php echo $_POST['pregunta'] ?? ''; ?></textarea>
                                            <div class="form-text">Sea claro y específico. Esta pregunta será visible para todos los pacientes.</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Tipo de Pregunta <span class="text-danger">*</span></label>
                                            <select class="form-select" name="tipo_pregunta" required onchange="updateTipoPregunta()">
                                                <option value="">Seleccionar tipo...</option>
                                                <option value="texto" <?php echo (($_POST
['tipo_pregunta'] ?? '') == 'texto') ? 'selected' : '';
?>>Texto Libre</option>
                                                <option value="numero" <?php echo (($_POST['tipo_pregunta'] ?? '') == 'numero') ? 'selected' : ''; ?>>Número</option>
                                                <option value="escala" <?php echo (($_POST['tipo_pregunta'] ?? '') == 'escala') ? 'selected' : ''; ?>>Escala (1-10)</option>
                                                <option value="multiple" <?php echo (($_POST['tipo_pregunta'] ?? '') == 'multiple') ? 'selected' : ''; ?>>Opción Múltiple</option>
                                                <option value="sino" <?php echo (($_POST['tipo_pregunta'] ?? '') == 'sino') ? 'selected' : ''; ?>>Sí/No</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Orden</label>
                                            <input type="number" class="form-control" name="orden" 
                                                   value="<?php echo $_POST['orden'] ?? $nextOrden; ?>" 
                                                   min="1" max="100">
                                            <div class="form-text">Posición en el formulario</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Configuración</label>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="obligatoria" 
                                                       id="obligatoria" value="1"
<?php echo (($_POST['obligatoria'] ?? '1') == '1') ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="obligatoria">
                                                    Pregunta Obligatoria
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
$opciones = $_POST['opciones_multiple'] ?? ['', ''];
foreach ($opciones as $i => $opcion):
    ?>
                                            <div class="input-group mb-2 opcion-item">
                                                <span class="input-group-text"><?php echo $i + 1; ?></span>
                                                <input type="text" class="form-control" name="opciones_multiple[]" 
                                                       value="<?php echo htmlspecialchars($opcion); ?>" 
                                                       placeholder="Escriba una opción...">
                                                <button type="button" class="btn btn-outline-danger" onclick="removeOpcion(this)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
<?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addOpcion()">
                                        <i class="fas fa-plus"></i> Agregar Opción
                                    </button>
                                    <div class="form-text">Mínimo 2 opciones requeridas</div>
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
                                                   value="<?php echo $_POST['escala_min'] ?? '1'; ?>" 
                                                   min="0" max="10">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="mb-3">
                                            <label class="form-label">Valor Máximo</label>
                                            <input type="number" class="form-control" name="escala_max" 
                                                   value="<?php echo $_POST['escala_max'] ?? '10'; ?>" 
                                                   min="1" max="20">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Etiquetas (Opcionales)</label>
                                        <div class="row">
                                            <div class="col-4">
                                                <input type="text" class="form-control form-control-sm" name="etiqueta_min" 
                                                       value="<?php echo $_POST['etiqueta_min'] ?? ''; ?>" 
                                                       placeholder="Etiqueta mín.">
                                            </div>
                                            <div class="col-4">
                                                <input type="text" class="form-control form-control-sm" name="etiqueta_medio" 
                                                       value="<?php echo $_POST['etiqueta_medio'] ?? ''; ?>" 
                                                       placeholder="Etiqueta medio">
                                            </div>
                                            <div class="col-4">
                                                <input type="text" class="form-control form-control-sm" name="etiqueta_max" 
                                                       value="<?php echo $_POST['etiqueta_max'] ?? ''; ?>" 
                                                       placeholder="Etiqueta máx.">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-text">Ejemplo: 1=Muy bajo, 5=Moderado, 10=Muy alto</div>
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
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Valor Mínimo (Opcional)</label>
                                            <input type="number" class="form-control" name="numero_min" 
                                                   value="<?php echo $_POST['numero_min'] ?? ''; ?>" 
                                                   step="0.1" placeholder="Ej: 0">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Valor Máximo (Opcional)</label>
                                            <input type="number" class="form-control" name="numero_max" 
                                                   value="<?php echo $_POST['numero_max'] ?? ''; ?>" 
                                                   step="0.1" placeholder="Ej: 100">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label class="form-label">Unidad (Opcional)</label>
                                            <input type="text" class="form-control" name="numero_unidad" 
                                                   value="<?php echo $_POST['numero_unidad'] ?? ''; ?>" 
                                                   placeholder="Ej: °C, kg, cm">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-text">Configure límites y unidad para validar la respuesta numérica</div>
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
                                    Las preguntas de texto libre permiten al paciente escribir su respuesta en un campo de texto.
                                    No requieren configuración adicional.
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
                                    Las preguntas de Sí/No presentan dos opciones simples al paciente.
                                    No requieren configuración adicional.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Panel de vista previa -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm mb-4 sticky-top" style="top: 20px;">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="fas fa-eye"></i> Vista Previa
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="preview_container">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <span id="preview_numero">1</span>. 
                                            <span id="preview_pregunta">Escriba su pregunta aquí...</span>
                                            <span id="preview_obligatoria" class="text-danger">*</span>
                                        </label>
                                        <div id="preview_campo">
                                            <input type="text" class="form-control" placeholder="Campo de respuesta..." disabled>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-light">
                                    <small class="text-muted">
                                        <i class="fas fa-lightbulb me-1"></i>
                                        Esta es la vista previa de cómo se verá la pregunta en el formulario de triaje.
                                    </small>
                                </div>
                            </div>
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
                                <i class="fas fa-save"></i> Crear Pregunta
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .opcion-item .input-group-text {
        min-width: 40px;
        justify-content: center;
    }

    .sticky-top {
        z-index: 1020;
    }

    .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }
</style>

<script>
    let opcionCounter = <?php echo count($_POST['opciones_multiple'] ?? ['', '']); ?>;

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

        updatePreview();
    }

    function addOpcion() {
        opcionCounter++;
        const container = document.getElementById('opciones_container');
        const div = document.createElement('div');
        div.className = 'input-group mb-2 opcion-item';
        div.innerHTML = `
           <span class="input-group-text">${opcionCounter}</span>
           <input type="text" class="form-control" name="opciones_multiple[]" 
                  placeholder="Escriba una opción..." onchange="updatePreview()">
           <button type="button" class="btn btn-outline-danger" onclick="removeOpcion(this)">
               <i class="fas fa-times"></i>
           </button>
       `;
        container.appendChild(div);
        updateOpcionNumbers();
        updatePreview();
    }

    function removeOpcion(button) {
        if (document.querySelectorAll('.opcion-item').length > 2) {
            button.closest('.opcion-item').remove();
            updateOpcionNumbers();
            updatePreview();
        }
    }

    function updateOpcionNumbers() {
        document.querySelectorAll('.opcion-item').forEach((item, index) => {
            item.querySelector('.input-group-text').textContent = index + 1;
        });
    }

    function updatePreview() {
        const pregunta = document.querySelector('textarea[name="pregunta"]').value || 'Escriba su pregunta aquí...';
        const tipo = document.querySelector('select[name="tipo_pregunta"]').value;
        const orden = document.querySelector('input[name="orden"]').value || '1';
        const obligatoria = document.querySelector('input[name="obligatoria"]').checked;

        // Actualizar texto
        document.getElementById('preview_numero').textContent = orden;
        document.getElementById('preview_pregunta').textContent = pregunta;
        document.getElementById('preview_obligatoria').style.display = obligatoria ? 'inline' : 'none';

        // Actualizar campo según tipo
        const campo = document.getElementById('preview_campo');

        switch (tipo) {
            case 'texto':
                campo.innerHTML = '<textarea class="form-control" rows="3" placeholder="Escriba su respuesta..." disabled></textarea>';
                break;

            case 'numero':
                const unidad = document.querySelector('input[name="numero_unidad"]').value;
                if (unidad) {
                    campo.innerHTML = `
                       <div class="input-group">
                           <input type="number" class="form-control" placeholder="0" disabled>
                           <span class="input-group-text">${unidad}</span>
                       </div>
                   `;
                } else {
                    campo.innerHTML = '<input type="number" class="form-control" placeholder="0" disabled>';
                }
                break;

            case 'escala':
                const min = document.querySelector('input[name="escala_min"]').value || 1;
                const max = document.querySelector('input[name="escala_max"]').value || 10;
                let html = '<div class="d-flex justify-content-between align-items-center">';
                html += `<span class="text-muted">${min}</span>`;
                html += '<input type="range" class="form-range mx-3" disabled>';
                html += `<span class="text-muted">${max}</span>`;
                html += '</div>';
                campo.innerHTML = html;
                break;

            case 'multiple':
                const opciones = Array.from(document.querySelectorAll('input[name="opciones_multiple[]"]'))
                        .map(input => input.value.trim())
                        .filter(val => val);

                if (opciones.length > 0) {
                    let html = '';
                    opciones.forEach((opcion, index) => {
                        html += `
                           <div class="form-check">
                               <input class="form-check-input" type="radio" name="preview_radio" disabled>
                               <label class="form-check-label">${opcion || `Opción ${index + 1}`}</label>
                           </div>
                       `;
                    });
                    campo.innerHTML = html;
                } else {
                    campo.innerHTML = '<p class="text-muted">Configure las opciones de respuesta</p>';
                }
                break;

            case 'sino':
                campo.innerHTML = `
                   <div class="form-check form-check-inline">
                       <input class="form-check-input" type="radio" name="preview_sino" disabled>
                       <label class="form-check-label">Sí</label>
                   </div>
                   <div class="form-check form-check-inline">
                       <input class="form-check-input" type="radio" name="preview_sino" disabled>
                       <label class="form-check-label">No</label>
                   </div>
               `;
                break;

            default:
                campo.innerHTML = '<input type="text" class="form-control" placeholder="Campo de respuesta..." disabled>';
        }
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function () {
        updateTipoPregunta();
        updatePreview();

        // Actualizar vista previa al cambiar campos
        document.querySelector('textarea[name="pregunta"]').addEventListener('input', updatePreview);
        document.querySelector('select[name="tipo_pregunta"]').addEventListener('change', updatePreview);
        document.querySelector('input[name="orden"]').addEventListener('input', updatePreview);
        document.querySelector('input[name="obligatoria"]').addEventListener('change', updatePreview);

        // Listeners específicos para cada tipo
        document.querySelectorAll('input[name^="escala_"], input[name^="numero_"]').forEach(input => {
            input.addEventListener('input', updatePreview);
        });

        document.querySelectorAll('input[name="opciones_multiple[]"]').forEach(input => {
            input.addEventListener('input', updatePreview);
        });
    });
</script>

</body>
</html>
