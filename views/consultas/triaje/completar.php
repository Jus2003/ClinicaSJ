<?php
require_once 'models/TriajeModel.php';

// Verificar que sea paciente
if ($_SESSION['role_id'] != 4) {
    header('Location: index.php?action=dashboard');
    exit;
}

$triajeModel = new TriajeModel();
$error = '';
$success = '';
$citaSeleccionada = null;

// Si se especifica una cita
$citaId = $_GET['cita_id'] ?? null;

if ($citaId) {
    // Verificar permisos y que la cita exista
    if (!$triajeModel->verificarPermisosCita($citaId, $_SESSION['user_id'], $_SESSION['role_id'])) {
        header('Location: index.php?action=consultas/triaje/completar');
        exit;
    }

    // Verificar si ya tiene triaje completado
    if ($triajeModel->tieneTriajeCompletado($citaId)) {
        $success = "El triaje para esta cita ya ha sido completado.";
        $citaSeleccionada = $citaId;
    } else {
        $citaSeleccionada = $citaId;
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$success) {
    try {
        $citaId = $_POST['cita_id'];
        $respuestas = $_POST['respuestas'] ?? [];

        if (empty($respuestas)) {
            throw new Exception("Debe responder al menos una pregunta");
        }

        // Verificar permisos nuevamente
        if (!$triajeModel->verificarPermisosCita($citaId, $_SESSION['user_id'], $_SESSION['role_id'])) {
            throw new Exception("No tiene permisos para esta cita");
        }

        $triajeModel->guardarRespuestas($citaId, $respuestas, $_SESSION['user_id'], 'digital');
        $success = "Triaje completado exitosamente. Gracias por completar la información.";
        $citaSeleccionada = $citaId;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener citas pendientes
$citasPendientes = $triajeModel->getCitasPendientesTriaje($_SESSION['user_id']);

// Si hay una cita seleccionada, obtener preguntas
$preguntas = [];
$respuestasExistentes = [];
if ($citaSeleccionada) {
    $preguntas = $triajeModel->getPreguntasActivas();
    $respuestasExistentes = $triajeModel->getRespuestasTriaje($citaSeleccionada);

    // Convertir respuestas a array asociativo
    $respuestasArray = [];
    foreach ($respuestasExistentes as $resp) {
        $respuestasArray[$resp['id_pregunta']] = $resp['respuesta'];
    }
    $respuestasExistentes = $respuestasArray;
}

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<style>
    .pain-scale-container {
        position: relative;
        margin: 20px 0;
    }

    .pain-scale {
        width: 100%;
        height: 8px;
        background: linear-gradient(to right, #28a745, #ffc107, #dc3545);
        border-radius: 4px;
        position: relative;
        cursor: pointer;
    }

    .pain-scale-track {
        position: relative;
        width: 100%;
        height: 40px;
        cursor: pointer;
    }

    .pain-scale-thumb {
        width: 20px;
        height: 20px;
        background: #fff;
        border: 3px solid #007bff;
        border-radius: 50%;
        position: absolute;
        top: 50%;
        transform: translate(-50%, -50%);
        cursor: pointer;
        box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        transition: all 0.2s ease;
    }

    .pain-scale-thumb:hover {
        transform: translate(-50%, -50%) scale(1.1);
        box-shadow: 0 4px 12px rgba(0,0,0,0.4);
    }

    .pain-scale-labels {
        display: flex;
        justify-content: space-between;
        margin-top: 10px;
        font-size: 0.85rem;
    }

    .pain-value-display {
        text-align: center;
        font-size: 1.5rem;
        font-weight: bold;
        margin: 15px 0;
        color: #007bff;
    }

    .pain-description {
        text-align: center;
        font-style: italic;
        color: #6c757d;
        margin-bottom: 15px;
    }

    .triaje-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .question-card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        margin-bottom: 25px;
    }

    .question-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .question-number {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        margin-right: 15px;
        flex-shrink: 0;
    }

    .form-control, .form-select {
        border-radius: 8px;
        border: 2px solid #e9ecef;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    .btn-gradient {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 8px;
        padding: 12px 30px;
        color: white;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-gradient:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        color: white;
    }

    .date-input-wrapper {
        position: relative;
    }

    .date-input-wrapper::before {
        content: '\f073';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        pointer-events: none;
        z-index: 10;
    }

    .radio-option {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .radio-option:hover {
        border-color: #667eea;
        background-color: #f8f9ff;
    }

    .radio-option.selected {
        border-color: #667eea;
        background-color: #f8f9ff;
    }

    .multiple-choice-option {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        padding: 12px 20px;
        margin-bottom: 8px;
        transition: all 0.3s ease;
        cursor: pointer;
        display: block;
        width: 100%;
        text-align: left;
        background: white;
    }

    .multiple-choice-option:hover {
        border-color: #667eea;
        background-color: #f8f9ff;
        transform: translateX(5px);
    }

    .multiple-choice-option.selected {
        border-color: #667eea;
        background-color: #667eea;
        color: white;
    }
</style>

<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- Header -->
            <div class="text-center mb-5">
                <div class="triaje-card text-white p-4">
                    <h1 class="display-6 mb-3">
                        <i class="fas fa-clipboard-list"></i> Triaje Digital
                    </h1>
                    <p class="lead mb-0">Complete la información médica antes de su consulta</p>
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

            <?php if (!$citaSeleccionada): ?>
                <!-- Selección de cita -->
                <div class="card question-card">
                    <div class="card-header bg-light">
                        <h4 class="mb-0">
                            <i class="fas fa-calendar-check"></i> Seleccione una Cita
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($citasPendientes)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times text-muted" style="font-size: 3rem;"></i>
                                <h5 class="text-muted mt-3">No tiene citas pendientes de triaje</h5>
                                <p class="text-muted">Las citas aparecerán aquí cuando sean confirmadas por el personal médico.</p>
                                <a href="index.php?action=dashboard" class="btn btn-gradient">
                                    <i class="fas fa-home"></i> Ir al Dashboard
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($citasPendientes as $cita): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 border-2">
                                            <div class="card-body">
                                                <h6 class="card-title text-primary">
                                                    <i class="fas fa-user-md"></i> <?php echo $cita['medico_nombre']; ?>
                                                </h6>
                                                <p class="card-text">
                                                    <strong>Especialidad:</strong> <?php echo $cita['nombre_especialidad']; ?><br>
                                                    <strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?><br>
                                                    <strong>Hora:</strong> <?php echo date('H:i', strtotime($cita['hora_cita'])); ?><br>
                                                    <strong>Sucursal:</strong> <?php echo $cita['nombre_sucursal']; ?>
                                                </p>
                                                <a href="index.php?action=consultas/triaje/completar&cita_id=<?php echo $cita['id_cita']; ?>" 
                                                   class="btn btn-gradient w-100">
                                                    <i class="fas fa-clipboard-list"></i> Completar Triaje
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- Formulario de triaje -->
                <div class="card question-card">
                    <?php if ($success): ?>
                        <!-- Mostrar respuestas completadas -->
                        <div class="card-header bg-success text-white">
                            <h4 class="mb-0">
                                <i class="fas fa-check-circle"></i> Triaje Completado
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                A continuación puede ver sus respuestas.
                            </div>

                            <?php foreach ($preguntas as $pregunta): ?>
                                <div class="question-card">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start">
                                            <div class="question-number">
                                                <i class="fas fa-check"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <label class="form-label fw-bold mb-3"><?php echo $pregunta['pregunta']; ?></label>
                                                <div class="alert alert-light mb-0">
                                                    <?php echo $respuestasExistentes[$pregunta['id_pregunta']] ?? 'Sin respuesta'; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="text-center mt-4">
                                <a href="index.php?action=consultas/triaje/completar" class="btn btn-gradient">
                                    <i class="fas fa-arrow-left"></i> Volver a Mis Citas
                                </a>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Formulario para completar triaje -->
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0">
                                <i class="fas fa-clipboard-list"></i> Complete su Triaje Médico
                            </h4>
                        </div>

                        <form method="POST" id="triajeForm">
                            <input type="hidden" name="cita_id" value="<?php echo $citaSeleccionada; ?>">

                            <div class="card-body">
                                <?php if (!empty($preguntas)): ?>
                                    <?php foreach ($preguntas as $index => $pregunta): ?>
                                        <div class="question-card">
                                            <div class="card-body">
                                                <div class="d-flex align-items-start">
                                                    <div class="question-number">
                                                        <?php echo $index + 1; ?>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <label class="form-label fw-bold mb-3">
                                                            <?php echo $pregunta['pregunta']; ?>
                                                            <?php if ($pregunta['obligatoria']): ?>
                                                                <span class="text-danger">*</span>
                                                            <?php endif; ?>
                                                        </label>

                                                        <?php
                                                        $opciones = null;
                                                        if ($pregunta['opciones_json']) {
                                                            $opciones = json_decode($pregunta['opciones_json'], true);
                                                        }
                                                        ?>

                                                        <?php if ($pregunta['tipo_pregunta'] === 'texto'): ?>
                                                            <textarea name="respuestas[<?php echo $pregunta['id_pregunta']; ?>]" 
                                                                      class="form-control" 
                                                                      rows="4" 
                                                                      placeholder="Escriba su respuesta aquí..."
                                                                      <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>></textarea>

                                                        <?php elseif ($pregunta['tipo_pregunta'] === 'numero'): ?>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <input type="number" 
                                                                           name="respuestas[<?php echo $pregunta['id_pregunta']; ?>]" 
                                                                           class="form-control" 
                                                                           placeholder="Ingrese el número"
                                                                           <?php
                                                                           if ($opciones) {
                                                                               if (isset($opciones['min']))
                                                                                   echo 'min="' . $opciones['min'] . '"';
                                                                               if (isset($opciones['max']))
                                                                                   echo 'max="' . $opciones['max'] . '"';
                                                                           }
                                                                           ?>
                                                                           <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                                                </div>
                                                                <?php if ($opciones && isset($opciones['unidad'])): ?>
                                                                    <div class="col-md-6">
                                                                        <span class="form-text text-muted">
                                                                            <strong>Unidad:</strong> <?php echo $opciones['unidad']; ?>
                                                                        </span>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>

                                                        <?php elseif ($pregunta['tipo_pregunta'] === 'escala'): ?>
                                                            <div class="pain-scale-container">
                                                                <div class="pain-value-display">
                                                                    <span id="scale-value-<?php echo $pregunta['id_pregunta']; ?>">5</span>
                                                                    <?php if ($opciones && isset($opciones['max'])): ?>
                                                                        / <?php echo $opciones['max']; ?>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="pain-description" id="scale-description-<?php echo $pregunta['id_pregunta']; ?>">
                                                                    Moderado
                                                                </div>
                                                                <div class="pain-scale-track">
                                                                    <div class="pain-scale"></div>
                                                                    <div class="pain-scale-thumb" 
                                                                         id="thumb-<?php echo $pregunta['id_pregunta']; ?>"
                                                                         style="left: 50%;"></div>
                                                                </div>
                                                                <div class="pain-scale-labels">
                                                                    <?php if ($opciones && isset($opciones['etiquetas'])): ?>
                                                                        <?php foreach ($opciones['etiquetas'] as $valor => $etiqueta): ?>
                                                                            <span><?php echo $valor; ?><br><small><?php echo $etiqueta; ?></small></span>
                                                                        <?php endforeach; ?>
                                                                    <?php else: ?>
                                                                        <span>1<br><small>Mínimo</small></span>
                                                                        <span>5<br><small>Moderado</small></span>
                                                                        <span>10<br><small>Máximo</small></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <input type="hidden" 
                                                                       name="respuestas[<?php echo $pregunta['id_pregunta']; ?>]" 
                                                                       id="scale-input-<?php echo $pregunta['id_pregunta']; ?>"
                                                                       value="5"
                                                                       <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                                            </div>

                                                        <?php elseif ($pregunta['tipo_pregunta'] === 'sino'): ?>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="radio-option" onclick="selectRadio('<?php echo $pregunta['id_pregunta']; ?>', 'Sí', this)">
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" 
                                                                                   type="radio" 
                                                                                   name="respuestas[<?php echo $pregunta['id_pregunta']; ?>]" 
                                                                                   value="Sí" 
                                                                                   id="si_<?php echo $pregunta['id_pregunta']; ?>"
                                                                                   <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                                                            <label class="form-check-label fw-bold" for="si_<?php echo $pregunta['id_pregunta']; ?>">
                                                                                <i class="fas fa-check text-success"></i> Sí
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="radio-option" onclick="selectRadio('<?php echo $pregunta['id_pregunta']; ?>', 'No', this)">
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" 
                                                                                   type="radio" 
                                                                                   name="respuestas[<?php echo $pregunta['id_pregunta']; ?>]" 
                                                                                   value="No" 
                                                                                   id="no_<?php echo $pregunta['id_pregunta']; ?>"
                                                                                   <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                                                            <label class="form-check-label fw-bold" for="no_<?php echo $pregunta['id_pregunta']; ?>">
                                                                                <i class="fas fa-times text-danger"></i> No
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                        <?php elseif ($pregunta['tipo_pregunta'] === 'multiple' && $opciones): ?>
                                                            <div class="multiple-choice-container">
                                                                <?php foreach ($opciones as $opcion): ?>
                                                                    <button type="button" 
                                                                            class="multiple-choice-option"
                                                                            onclick="selectMultiple('<?php echo $pregunta['id_pregunta']; ?>', '<?php echo htmlspecialchars($opcion); ?>', this)">
                                                                                <?php echo htmlspecialchars($opcion); ?>
                                                                    </button>
                                                                <?php endforeach; ?>
                                                                <input type="hidden" 
                                                                       name="respuestas[<?php echo $pregunta['id_pregunta']; ?>]" 
                                                                       id="multiple-input-<?php echo $pregunta['id_pregunta']; ?>"
                                                                       <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                                            </div>

                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <div class="text-center mt-4">
                                        <button type="submit" class="btn btn-gradient btn-lg">
                                            <i class="fas fa-save"></i> Completar Triaje
                                        </button>
                                        <a href="index.php?action=consultas/triaje/completar" class="btn btn-outline-secondary btn-lg ms-3">
                                            <i class="fas fa-arrow-left"></i> Cancelar
                                        </a>
                                    </div>

                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                                        <h5 class="text-muted mt-3">No hay preguntas configuradas</h5>
                                        <p class="text-muted">Contacte al personal médico para resolver este problema.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Variable para controlar si el formulario se está enviando
    let formSubmitting = false;

// Funciones para manejar la escala de dolor
    function initPainScale(preguntaId, min = 1, max = 10) {
        const track = document.querySelector(`#thumb-${preguntaId}`).parentElement;
        const thumb = document.querySelector(`#thumb-${preguntaId}`);
        const valueDisplay = document.querySelector(`#scale-value-${preguntaId}`);
        const descriptionDisplay = document.querySelector(`#scale-description-${preguntaId}`);
        const hiddenInput = document.querySelector(`#scale-input-${preguntaId}`);

        function updateValue(clientX) {
            const rect = track.getBoundingClientRect();
            const percentage = Math.max(0, Math.min(1, (clientX - rect.left) / rect.width));
            const value = Math.round(min + (max - min) * percentage);

            thumb.style.left = (percentage * 100) + '%';
            valueDisplay.textContent = value;
            hiddenInput.value = value;

            // Actualizar descripción basada en el valor
            let description = '';
            if (value <= 2)
                description = 'Muy leve';
            else if (value <= 4)
                description = 'Leve';
            else if (value <= 6)
                description = 'Moderado';
            else if (value <= 8)
                description = 'Intenso';
            else
                description = 'Muy intenso';

            descriptionDisplay.textContent = description;
        }

        let isDragging = false;

        thumb.addEventListener('mousedown', (e) => {
            isDragging = true;
            e.preventDefault();
        });

        track.addEventListener('click', (e) => {
            updateValue(e.clientX);
        });

        document.addEventListener('mousemove', (e) => {
            if (isDragging) {
                updateValue(e.clientX);
            }
        });

        document.addEventListener('mouseup', () => {
            isDragging = false;
        });

        // Touch events para móviles
        thumb.addEventListener('touchstart', (e) => {
            isDragging = true;
            e.preventDefault();
        });

        document.addEventListener('touchmove', (e) => {
            if (isDragging) {
                const touch = e.touches[0];
                updateValue(touch.clientX);
            }
        });

        document.addEventListener('touchend', () => {
            isDragging = false;
        });
    }

// Función para seleccionar radio buttons
    function selectRadio(preguntaId, value, element) {
        // Remover selección anterior
        const container = element.parentElement;
        container.querySelectorAll('.radio-option').forEach(option => {
            option.classList.remove('selected');
        });

        // Marcar como seleccionado
        element.classList.add('selected');

        // Marcar el radio button
        const radio = element.querySelector('input[type="radio"]');
        radio.checked = true;
    }

// Función para seleccionar opciones múltiples
    function selectMultiple(preguntaId, value, element) {
        // Remover selección anterior
        const container = element.parentElement;
        container.querySelectorAll('.multiple-choice-option').forEach(option => {
            option.classList.remove('selected');
        });

        // Marcar como seleccionado
        element.classList.add('selected');

        // Actualizar input hidden
        document.querySelector(`#multiple-input-${preguntaId}`).value = value;
    }

// Inicializar componentes cuando el DOM esté listo
    document.addEventListener('DOMContentLoaded', function () {
        // Inicializar todas las escalas de dolor
<?php foreach ($preguntas as $pregunta): ?>
    <?php if ($pregunta['tipo_pregunta'] === 'escala'): ?>
        <?php
        $opciones = $pregunta['opciones_json'] ? json_decode($pregunta['opciones_json'], true) : null;
        $min = $opciones['min'] ?? 1;
        $max = $opciones['max'] ?? 10;
        ?>
                initPainScale(<?php echo $pregunta['id_pregunta']; ?>, <?php echo $min; ?>, <?php echo $max; ?>);
    <?php endif; ?>
<?php endforeach; ?>

        // Manejar el envío del formulario
        const form = document.getElementById('triajeForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                // Marcar que el formulario se está enviando
                formSubmitting = true;

                // Validar campos requeridos
                let isValid = true;
                let firstInvalidField = null;

                this.querySelectorAll('[required]').forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        if (!firstInvalidField) {
                            firstInvalidField = field;
                        }
                        field.style.borderColor = '#dc3545';
                    } else {
                        field.style.borderColor = '#e9ecef';
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    formSubmitting = false; // Reset flag
                    alert('Por favor complete todos los campos obligatorios marcados con *');
                    if (firstInvalidField) {
                        firstInvalidField.scrollIntoView({behavior: 'smooth', block: 'center'});
                        firstInvalidField.focus();
                    }
                    return;
                }

                // Cambiar el botón a estado de carga
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
                    submitBtn.disabled = true;
                }

                // Limpiar progreso guardado
                localStorage.removeItem('triaje_progress_<?php echo $citaSeleccionada ?? 0; ?>');
            });

            // Guardar progreso automáticamente
            form.addEventListener('input', function () {
                if (!formSubmitting) {
                    const formData = new FormData(form);
                    const data = {};
                    for (let [key, value] of formData.entries()) {
                        data[key] = value;
                    }
                    localStorage.setItem('triaje_progress_<?php echo $citaSeleccionada ?? 0; ?>', JSON.stringify(data));
                }
            });

            // Cargar datos guardados al iniciar
            const savedData = localStorage.getItem('triaje_progress_<?php echo $citaSeleccionada ?? 0; ?>');
            if (savedData) {
                try {
                    const data = JSON.parse(savedData);
                    Object.keys(data).forEach(name => {
                        const field = form.querySelector(`[name="${name}"]`);
                        if (field) {
                            field.value = data[name];
                            // Trigger events para actualizar UI
                            if (field.type === 'radio' && field.value === data[name]) {
                                field.checked = true;
                                const radioOption = field.closest('.radio-option');
                                if (radioOption)
                                    radioOption.classList.add('selected');
                            }
                        }
                    });
                } catch (e) {
                    console.log('Error loading saved data:', e);
                }
            }
        }

        // Auto-resize para textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function () {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
    });

// Prevenir pérdida de datos accidental SOLO si no se está enviando el formulario
    window.addEventListener('beforeunload', function (e) {
        // NO mostrar advertencia si el formulario se está enviando
        if (formSubmitting) {
            return;
        }

        const form = document.getElementById('triajeForm');
        if (form) {
            const formData = new FormData(form);
            let hasData = false;
            for (let [key, value] of formData.entries()) {
                if (value.trim() !== '' && key !== 'cita_id') {
                    hasData = true;
                    break;
                }
            }

            if (hasData) {
                e.preventDefault();
                e.returnValue = '¿Está seguro de que desea salir? Los datos no guardados se perderán.';
            }
        }
    });

// Detectar si el usuario está en móvil para ajustar la UI
    function isMobile() {
        return window.innerWidth <= 768;
    }

// Ajustar elementos para móvil
    if (isMobile()) {
        document.querySelectorAll('.question-number').forEach(num => {
            num.style.width = '30px';
            num.style.height = '30px';
            num.style.fontSize = '0.9rem';
        });

        document.querySelectorAll('.pain-scale-thumb').forEach(thumb => {
            thumb.style.width = '25px';
            thumb.style.height = '25px';
        });
    }
</script>