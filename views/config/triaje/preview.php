<?php
require_once 'models/PreguntaTriaje.php';

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header('Location: index.php?action=dashboard');
    exit;
}

$preguntaModel = new PreguntaTriaje();

// Obtener todas las preguntas activas
$preguntas = $preguntaModel->getPreguntasActivas();

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
                        <i class="fas fa-eye"></i> Vista Previa del Triaje Digital
                    </h2>
                    <p class="text-muted mb-0">Como lo verán los pacientes y recepcionistas</p>
                </div>
                <div>
                    <a href="index.php?action=config/triaje" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>

            <!-- Simulación del formulario -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0 shadow">
                        <div class="card-header bg-gradient-primary text-white">
                            <div class="row align-items-center">
                                <div class="col">
                                    <h4 class="mb-0">
                                        <i class="fas fa-clipboard-list me-2"></i>
                                        Triaje Digital - Evaluación Médica
                                    </h4>
                                    <p class="mb-0 opacity-75">Complete el siguiente formulario antes de su consulta</p>
                                </div>
                                <div class="col-auto">
                                    <div class="badge bg-light text-dark fs-6">
                                        <?php echo count($preguntas); ?> preguntas
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <?php if (!empty($preguntas)): ?>
                                <form id="triaje-preview">
                                    <?php foreach ($preguntas as $index => $pregunta): ?>
                                        <div class="pregunta-preview mb-4 p-3 border rounded" data-pregunta="<?php echo $index + 1; ?>">
                                            <label class="form-label fw-semibold">
                                                <span class="badge bg-primary me-2"><?php echo $pregunta['orden']; ?></span>
                                                <?php echo htmlspecialchars($pregunta['pregunta']); ?>
                                                <?php if ($pregunta['obligatoria']): ?>
                                                    <span class="text-danger">*</span>
                                                <?php endif; ?>
                                            </label>

                                            <div class="respuesta-container">
                                                <?php
                                                $opciones = $pregunta['opciones_json'] ? json_decode($pregunta['opciones_json'], true) : null;

                                                switch ($pregunta['tipo_pregunta']):
                                                    case 'texto':
                                                        ?>
                                                        <textarea class="form-control" rows="3" 
                                                                  placeholder="Escriba su respuesta aquí..."
                                                                  <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>></textarea>
                                                                  <?php
                                                                  break;

                                                              case 'numero':
                                                                  ?>
                                                        <div class="<?php echo $opciones && isset($opciones['unidad']) ? 'input-group' : ''; ?>">
                                                            <input type="number" class="form-control" 
                                                                   placeholder="Ingrese un número"
                                                                   <?php if ($opciones): ?>
                                                                       <?php if (isset($opciones['min'])): ?>min="<?php echo $opciones['min']; ?>"<?php endif; ?>
                                                                       <?php if (isset($opciones['max'])): ?>max="<?php echo $opciones['max']; ?>"<?php endif; ?>
                                                                   <?php endif; ?>
                                                                   <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                                                   <?php if ($opciones && isset($opciones['unidad'])): ?>
                                                                <span class="input-group-text"><?php echo htmlspecialchars($opciones['unidad']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($opciones && (isset($opciones['min']) || isset($opciones['max']))): ?>
                                                            <small class="form-text text-muted">
                                                                <?php if (isset($opciones['min']) && isset($opciones['max'])): ?>
                                                                    Valor entre <?php echo $opciones['min']; ?> y <?php echo $opciones['max']; ?>
                                                                <?php elseif (isset($opciones['min'])): ?>
                                                                    Valor mínimo: <?php echo $opciones['min']; ?>
                                                                <?php elseif (isset($opciones['max'])): ?>
                                                                    Valor máximo: <?php echo $opciones['max']; ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php
                                                        break;

                                                    case 'escala':
                                                        $min = $opciones['min'] ?? 1;
                                                        $max = $opciones['max'] ?? 10;
                                                        $etiquetas = $opciones['etiquetas'] ?? [];
                                                        ?>
                                                        <div class="escala-container">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <span class="text-muted">
                                                                    <?php echo $min; ?>
                                                                    <?php if (isset($etiquetas[$min])): ?>
                                                                        <br><small><?php echo htmlspecialchars($etiquetas[$min]); ?></small>
                                                                    <?php endif; ?>
                                                                </span>
                                                                <input type="range" class="form-range mx-3" 
                                                                       min="<?php echo $min; ?>" max="<?php echo $max; ?>" 
                                                                       value="<?php echo round(($min + $max) / 2); ?>"
                                                                       oninput="updateEscalaValue(this)"
                                                                       <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                                                <span class="text-muted">
                                                                    <?php echo $max; ?>
                                                                    <?php if (isset($etiquetas[$max])): ?>
                                                                        <br><small><?php echo htmlspecialchars($etiquetas[$max]); ?></small>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </div>
                                                            <div class="text-center">
                                                                <span class="badge bg-primary fs-6 escala-value"><?php echo round(($min + $max) / 2); ?></span>
                                                                <?php
                                                                $medio = round(($min + $max) / 2);
                                                                if (isset($etiquetas[$medio])):
                                                                    ?>
                                                                    <br><small class="text-muted escala-etiqueta"><?php echo htmlspecialchars($etiquetas[$medio]); ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        break;

                                                    case 'multiple':
                                                        ?>
                                                        <div class="opciones-multiple">
                                                            <?php foreach ($opciones as $i => $opcion): ?>
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="radio" 
                                                                           name="pregunta_<?php echo $pregunta['id_pregunta']; ?>" 
                                                                           value="<?php echo htmlspecialchars($opcion); ?>"
                                                                           id="opcion_<?php echo $pregunta['id_pregunta']; ?>_<?php echo $i; ?>"
                                                                           <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                                                    <label class="form-check-label" 
                                                                           for="opcion_<?php echo $pregunta['id_pregunta']; ?>_<?php echo $i; ?>">
                                                                               <?php echo htmlspecialchars($opcion); ?>
                                                                    </label>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <?php
                                                        break;

                                                    case 'sino':
                                                        ?>
                                                        <div class="opciones-sino">
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input" type="radio" 
                                                                       name="pregunta_<?php echo $pregunta['id_pregunta']; ?>" 
                                                                       value="Si" id="si_<?php echo $pregunta['id_pregunta']; ?>"
                                                                       <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                                                <label class="form-check-label" for="si_<?php echo $pregunta['id_pregunta']; ?>">
                                                                    <i class="fas fa-check text-success me-1"></i> Sí
                                                                </label>
                                                            </div>
                                                            <div class="form-check form-check-inline">
                                                                <input class="form-check-input" type="radio" 
                                                                       name="pregunta_<?php echo $pregunta['id_pregunta']; ?>" 
                                                                       value="No" id="no_<?php echo $pregunta['id_pregunta']; ?>"
                                                                       <?php echo $pregunta['obligatoria'] ? 'required' : ''; ?>>
                                                                <label class="form-check-label" for="no_<?php echo $pregunta['id_pregunta']; ?>">
                                                                    <i class="fas fa-times text-danger me-1"></i> No
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        break;
                                                endswitch;
                                                ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Los campos marcados con (*) son obligatorios
                                        </small>
                                        <button type="button" class="btn btn-primary" onclick="simularEnvio()">
                                            <i class="fas fa-paper-plane me-2"></i>
                                            Enviar Triaje
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-clipboard-question fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No hay preguntas configuradas</h5>
                                    <p class="text-muted">Configure las preguntas de triaje para ver la vista previa.</p>
                                    <a href="index.php?action=config/triaje/create" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Crear Primera Pregunta
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Información adicional -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h6 class="text-primary">
                                        <i class="fas fa-mobile-alt me-2"></i>
                                        Acceso Móvil
                                    </h6>
                                    <p class="text-muted small mb-0">
                                        Este formulario es completamente responsive y funciona perfectamente en dispositivos móviles.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h6 class="text-success">
                                        <i class="fas fa-save me-2"></i>
                                        Guardado Automático
                                    </h6>
                                    <p class="text-muted small mb-0">
                                        Las respuestas se guardan automáticamente cuando el paciente completa el triaje.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bg-gradient-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .pregunta-preview {
        transition: all 0.3s ease;
    }

    .pregunta-preview:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .escala-container .form-range {
        height: 8px;
    }

    .escala-container .form-range::-webkit-slider-thumb {
        width: 20px;
        height: 20px;
    }

    .form-check-input:checked {
        background-color: #667eea;
        border-color: #667eea;
    }

    .badge {
        font-size: 0.8em;
    }
</style>

<script>
    function updateEscalaValue(range) {
        const value = range.value;
        const container = range.closest('.escala-container');
        const valueSpan = container.querySelector('.escala-value');
        const etiquetaSpan = container.querySelector('.escala-etiqueta');

        valueSpan.textContent = value;

        // Aquí podrías agregar lógica para mostrar etiquetas específicas según el valor
        // Por simplicidad, solo actualizamos el número
    }

    function simularEnvio() {
        // Simular validación del formulario
        const form = document.getElementById('triaje-preview');
        const requiredFields = form.querySelectorAll('[required]');
        let valid = true;

        requiredFields.forEach(field => {
            if (!field.value && field.type !== 'radio') {
                valid = false;
                field.classList.add('is-invalid');
            } else if (field.type === 'radio') {
                const radioGroup = form.querySelectorAll(`[name="${field.name}"]`);
                const isChecked = Array.from(radioGroup).some(radio => radio.checked);
                if (!isChecked) {
                    valid = false;
                    radioGroup.forEach(radio => radio.classList.add('is-invalid'));
                }
            } else {
                field.classList.remove('is-invalid');
            }
        });

        if (valid) {
            // Mostrar mensaje de éxito
            Swal.fire({
                icon: 'success',
                title: '¡Triaje Completado!',
                text: 'Las respuestas han sido registradas correctamente.',
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#667eea'
            });
        } else {
            // Mostrar mensaje de error
            Swal.fire({
                icon: 'error',
                title: 'Campos Incompletos',
                text: 'Por favor complete todos los campos obligatorios.',
                confirmButtonText: 'Revisar',
                confirmButtonColor: '#dc3545'
            });
        }
    }

    // Limpiar validación al cambiar valores
    document.addEventListener('DOMContentLoaded', function () {
        const fields = document.querySelectorAll('input, textarea, select');
        fields.forEach(field => {
            field.addEventListener('change', function () {
                this.classList.remove('is-invalid');
            });
        });
    });
</script>

<!-- Incluir SweetAlert2 para los mensajes -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>
</html>