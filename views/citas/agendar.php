<?php
require_once 'models/Especialidad.php';
require_once 'models/Sucursal.php';
require_once 'models/User.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Verificar permisos según rol
$rolesPermitidos = [1, 2, 3, 4]; // Admin, Recepcionista, Médico, Paciente
if (!in_array($_SESSION['role_id'], $rolesPermitidos)) {
    header('Location: index.php?action=dashboard');
    exit;
}

// Obtener datos necesarios
$especialidadModel = new Especialidad();
$sucursalModel = new Sucursal();
$userModel = new User();

$especialidades = $especialidadModel->getAllEspecialidades();
$sucursales = $sucursalModel->getAllSucursales();

// Si es paciente, obtener sus datos
$datosUsuario = null;
if ($_SESSION['role_id'] == 4) { // Paciente
    $datosUsuario = $userModel->getUserById($_SESSION['user_id']);
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
                        <i class="fas fa-calendar-plus"></i> Agendar Nueva Cita
                    </h2>
                    <p class="text-muted mb-0">Seleccione una fecha en el calendario para agendar su cita médica</p>
                </div>
                <div>
                    <a href="index.php?action=citas/gestionar" class="btn btn-outline-secondary">
                        <i class="fas fa-list"></i> Ver Mis Citas
                    </a>
                </div>
            </div>

            <!-- Mensajes -->
            <div id="mensajes-container"></div>

            <!-- Calendario Principal -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-alt"></i> Seleccionar Fecha
                            </h5>
                        </div>
                        <div class="card-body p-4">
                            <div id="calendario-container">
                                <div class="calendar-header d-flex justify-content-between align-items-center mb-4">
                                    <button type="button" class="btn btn-outline-primary" id="btnMesAnterior">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <h4 id="mesAnio" class="mb-0 text-primary"></h4>
                                    <button type="button" class="btn btn-outline-primary" id="btnMesSiguiente">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered calendar-table">
                                        <thead class="bg-light">
                                            <tr>
                                                <th class="text-center">Dom</th>
                                                <th class="text-center">Lun</th>
                                                <th class="text-center">Mar</th>
                                                <th class="text-center">Mié</th>
                                                <th class="text-center">Jue</th>
                                                <th class="text-center">Vie</th>
                                                <th class="text-center">Sáb</th>
                                            </tr>
                                        </thead>
                                        <tbody id="calendar-body">
                                            <!-- Días del calendario se generan dinámicamente -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel de información -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-info-circle"></i> Información
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info border-0">
                                <h6><i class="fas fa-lightbulb"></i> Instrucciones:</h6>
                                <ul class="mb-0 small">
                                    <li>Seleccione una fecha disponible en el calendario</li>
                                    <li>Las fechas pasadas aparecen deshabilitadas</li>
                                    <li>Al seleccionar una fecha se abrirá el formulario</li>
                                    <li>Complete todos los datos requeridos</li>
                                </ul>
                            </div>

                            <div class="legend mt-3">
                                <h6>Leyenda:</h6>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="legend-box available me-2"></div>
                                    <small>Fecha disponible</small>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <div class="legend-box disabled me-2"></div>
                                    <small>Fecha no disponible</small>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div class="legend-box selected me-2"></div>
                                    <small>Fecha seleccionada</small>
                                </div>
                            </div>

                            <!-- Datos del usuario si es paciente -->
                            <?php if ($_SESSION['role_id'] == 4 && $datosUsuario): ?>
                                <div class="mt-4">
                                    <h6><i class="fas fa-user"></i> Sus Datos:</h6>
                                    <div class="bg-light p-3 rounded">
                                        <p class="mb-1"><strong><?php echo htmlspecialchars($datosUsuario['nombre'] . ' ' . $datosUsuario['apellido']); ?></strong></p>
                                        <p class="mb-1 small text-muted">Email: <?php echo htmlspecialchars($datosUsuario['email']); ?></p>
                                        <?php if ($datosUsuario['telefono']): ?>
                                            <p class="mb-0 small text-muted">Teléfono: <?php echo htmlspecialchars($datosUsuario['telefono']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal de Agendamiento -->
    <div class="modal fade" id="modalAgendamiento" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-calendar-plus"></i> 
                        Agendar Cita - <span id="fechaModalTitulo"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-0">
                    <!-- Indicador de pasos -->
                    <div class="steps-indicator bg-light p-3 border-bottom">
                        <div class="row text-center">
                            <div class="col step-item active" id="step1">
                                <div class="step-circle">1</div>
                                <small>Tipo de Cita</small>
                            </div>
                            <div class="col step-item" id="step2">
                                <div class="step-circle">2</div>
                                <small>Especialidad</small>
                            </div>
                            <div class="col step-item" id="step3">
                                <div class="step-circle">3</div>
                                <small>Sucursal</small>
                            </div>
                            <div class="col step-item" id="step4">
                                <div class="step-circle">4</div>
                                <small>Médico y Hora</small>
                            </div>
                            <div class="col step-item" id="step5">
                                <div class="step-circle">5</div>
                                <small>Datos Paciente</small>
                            </div>
                            <div class="col step-item" id="step6">
                                <div class="step-circle">6</div>
                                <small>Confirmación</small>
                            </div>
                        </div>
                    </div>

                    <!-- Contenido del formulario -->
                    <div class="p-4">
                        <form id="formAgendamiento">
                            <!-- Paso 1: Tipo de Cita -->
                            <div class="paso-container" id="paso1">
                                <h5 class="mb-4">
                                    <i class="fas fa-desktop text-primary"></i> 
                                    Seleccione el tipo de cita
                                </h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="card tipo-cita-card" data-tipo="presencial">
                                            <div class="card-body text-center p-4">
                                                <i class="fas fa-hospital fa-3x text-primary mb-3"></i>
                                                <h5>Cita Presencial</h5>
                                                <p class="text-muted mb-0">
                                                    Atención médica en nuestras instalaciones. 
                                                    Incluye examen físico completo.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="card tipo-cita-card" data-tipo="virtual">
                                            <div class="card-body text-center p-4">
                                                <i class="fas fa-video fa-3x text-success mb-3"></i>
                                                <h5>Cita Virtual</h5>
                                                <p class="text-muted mb-0">
                                                    Consulta médica por videollamada. 
                                                    Disponible solo para ciertas especialidades.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="tipoCita" name="tipo_cita" required>
                            </div>

                            <!-- Paso 2: Especialidad -->
                            <div class="paso-container d-none" id="paso2">
                                <h5 class="mb-4">
                                    <i class="fas fa-user-md text-primary"></i> 
                                    Seleccione la especialidad médica
                                </h5>
                                <div class="row" id="especialidadesContainer">
                                    <!-- Las especialidades se cargan dinámicamente -->
                                </div>
                                <input type="hidden" id="especialidadSeleccionada" name="id_especialidad" required>
                            </div>

                            <!-- Paso 3: Sucursal -->
                            <div class="paso-container d-none" id="paso3">
                                <h5 class="mb-4">
                                    <i class="fas fa-building text-primary"></i> 
                                    Seleccione la sucursal
                                </h5>
                                <div class="row" id="sucursalesContainer">
                                    <!-- Las sucursales se cargan dinámicamente -->
                                </div>
                                <input type="hidden" id="sucursalSeleccionada" name="id_sucursal" required>
                            </div>

                            <!-- Paso 4: Médico y Hora -->
                            <div class="paso-container d-none" id="paso4">
                                <h5 class="mb-4">
                                    <i class="fas fa-clock text-primary"></i> 
                                    Seleccione médico y horario
                                </h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Médico Disponible</label>
                                        <select class="form-select" id="medicoSeleccionado" name="id_medico" required>
                                            <option value="">Seleccione un médico...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Horario Disponible</label>
                                        <select class="form-select" id="horaSeleccionada" name="hora_cita" required>
                                            <option value="">Primero seleccione un médico</option>
                                        </select>
                                    </div>
                                </div>
                                <div id="medicoInfo" class="alert alert-info d-none">
                                    <!-- Información del médico seleccionado -->
                                </div>
                            </div>

                            <!-- Paso 5: Datos del Paciente -->
                            <div class="paso-container d-none" id="paso5">
                                <h5 class="mb-4">
                                    <i class="fas fa-user text-primary"></i> 
                                    Datos del paciente
                                </h5>

                                <?php if ($_SESSION['role_id'] != 4): ?>
                                    <!-- Solo mostrar si NO es paciente -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">
                                                Cédula del Paciente
                                                <button type="button" class="btn btn-sm btn-outline-info ms-1" 
                                                        onclick="consultarCedula()" id="btnConsultarCedula" disabled>
                                                    <i class="fas fa-search"></i> Consultar
                                                </button>
                                            </label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" name="cedula_paciente" 
                                                       id="cedulaPaciente" placeholder="Ingrese número de cédula" 
                                                       maxlength="10" oninput="validarCedulaInput()">
                                                <div class="input-group-text" id="cedulaStatus">
                                                    <i class="fas fa-question text-muted"></i>
                                                </div>
                                            </div>
                                            <div id="cedulaResult" class="mt-2" style="display: none;"></div>
                                            <div class="form-text">
                                                10 dígitos - Si el paciente ya existe, se cargarán sus datos automáticamente
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">&nbsp;</label>
                                            <div class="d-grid">
                                                <button type="button" class="btn btn-outline-secondary" 
                                                        onclick="buscarPacienteExistente()">
                                                    <i class="fas fa-search"></i> Buscar Paciente Existente
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Nombre <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="nombre_paciente" 
                                               id="nombrePaciente" required
                                               value="<?php echo $_SESSION['role_id'] == 4 ? htmlspecialchars($datosUsuario['nombre'] ?? '') : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Apellido <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="apellido_paciente" 
                                               id="apellidoPaciente" required
                                               value="<?php echo $_SESSION['role_id'] == 4 ? htmlspecialchars($datosUsuario['apellido'] ?? '') : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="email_paciente" 
                                               id="emailPaciente" required
                                               value="<?php echo $_SESSION['role_id'] == 4 ? htmlspecialchars($datosUsuario['email'] ?? '') : ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Teléfono</label>
                                        <input type="text" class="form-control" name="telefono_paciente" 
                                               id="telefonoPaciente"
                                               value="<?php echo $_SESSION['role_id'] == 4 ? htmlspecialchars($datosUsuario['telefono'] ?? '') : ''; ?>">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Motivo de la Consulta <span class="text-danger">*</span></label>
                                        <textarea class="form-control" name="motivo_consulta" id="motivoConsulta" 
                                                  rows="3" placeholder="Describa brevemente el motivo de su consulta..." required></textarea>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label">Observaciones Adicionales</label>
                                        <textarea class="form-control" name="observaciones" id="observaciones" 
                                                  rows="2" placeholder="Información adicional relevante (opcional)"></textarea>
                                    </div>
                                </div>

                                <?php if ($_SESSION['role_id'] == 4): ?>
                                    <input type="hidden" name="id_paciente" value="<?php echo $_SESSION['user_id']; ?>">
                                <?php else: ?>
                                    <input type="hidden" name="id_paciente" id="idPacienteSeleccionado">
                                <?php endif; ?>
                            </div>

                            <!-- Paso 6: Confirmación -->
                            <div class="paso-container d-none" id="paso6">
                                <h5 class="mb-4">
                                    <i class="fas fa-check-circle text-success"></i> 
                                    Confirmar datos de la cita
                                </h5>
                                <div id="resumenCita">
                                    <!-- El resumen se genera dinámicamente -->
                                </div>
                            </div>

                            <!-- Campos ocultos -->
                            <input type="hidden" name="fecha_cita" id="fechaCita">
                            <input type="hidden" name="id_usuario_registro" value="<?php echo $_SESSION['user_id']; ?>">
                        </form>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="btnAnterior" style="display: none;">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" id="btnSiguiente">
                        Siguiente <i class="fas fa-chevron-right"></i>
                    </button>
                    <button type="button" class="btn btn-success d-none" id="btnConfirmar">
                        <i class="fas fa-save"></i> Confirmar Cita
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
    /* Estilos del calendario */
    .calendar-table {
        margin-bottom: 0;
    }

    .calendar-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        text-align: center;
        padding: 15px 8px;
        border: 1px solid #dee2e6;
    }

    .calendar-table td {
        height: 60px;
        width: 14.28%;
        text-align: center;
        vertical-align: middle;
        border: 1px solid #dee2e6;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
    }

    .calendar-table td:hover {
        background-color: #e3f2fd;
    }

    .calendar-table td.available {
        background-color: #fff;
        color: #333;
    }

    .calendar-table td.available:hover {
        background-color: #e3f2fd;
        transform: scale(1.05);
    }

    .calendar-table td.disabled {
        background-color: #f5f5f5;
        color: #999;
        cursor: not-allowed;
    }

    .calendar-table td.disabled:hover {
        background-color: #f5f5f5;
        transform: none;
    }

    .calendar-table td.selected {
        background-color: #007bff;
        color: white;
        font-weight: bold;
    }

    .calendar-table td.other-month {
        color: #ccc;
        background-color: #fafafa;
    }

    .calendar-table td.today {
        background-color: #fff3cd;
        border: 2px solid #ffc107;
        font-weight: bold;
    }

    /* Leyenda */
    .legend-box {
        width: 20px;
        height: 20px;
        border-radius: 4px;
        display: inline-block;
    }

    .legend-box.available {
        background-color: #fff;
        border: 2px solid #007bff;
    }

    .legend-box.disabled {
        background-color: #f5f5f5;
        border: 2px solid #999;
    }

    .legend-box.selected {
        background-color: #007bff;
        border: 2px solid #007bff;
    }

    /* Estilos del modal */
    .modal-xl {
        max-width: 1200px;
    }

    /* Indicador de pasos */
    .steps-indicator {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .step-item {
        position: relative;
        opacity: 0.5;
        transition: all 0.3s ease;
    }

    .step-item.active {
        opacity: 1;
    }

    .step-item.completed {
        opacity: 1;
    }

    .step-circle {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        background-color: #6c757d;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 5px;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .step-item.active .step-circle {
        background-color: #007bff;
        transform: scale(1.1);
    }

    .step-item.completed .step-circle {
        background-color: #28a745;
    }

    /* Cards de tipo de cita */
    .tipo-cita-card {
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid #e9ecef;
    }

    .tipo-cita-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        border-color: #007bff;
    }

    .tipo-cita-card.selected {
        border-color: #007bff;
        background-color: #e3f2fd;
        transform: translateY(-2px);
    }

    /* Cards de especialidad y sucursal */
    .especialidad-card, .sucursal-card {
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid #e9ecef;
        height: 100%;
    }

    .especialidad-card:hover, .sucursal-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        border-color: #007bff;
    }

    .especialidad-card.selected, .sucursal-card.selected {
        border-color: #007bff;
        background-color: #e3f2fd;
    }

    .especialidad-card.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .especialidad-card.disabled:hover {
        transform: none;
        box-shadow: none;
        border-color: #e9ecef;
    }

    /* Input groups mejorados */
    .input-group-text {
        min-width: 40px;
        justify-content: center;
    }

    /* Alerts pequeños */
    .alert-sm {
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
    }

    /* Animaciones de paso */
    .paso-container {
        opacity: 0;
        transform: translateX(20px);
        transition: all 0.3s ease;
    }

    .paso-container:not(.d-none) {
        opacity: 1;
        transform: translateX(0);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .calendar-table td {
            height: 45px;
            font-size: 14px;
        }

        .calendar-table th {
            padding: 10px 4px;
            font-size: 12px;
        }

        .modal-xl {
            max-width: 95%;
            margin: 10px auto;
        }

        .step-item {
            margin-bottom: 10px;
        }

        .step-circle {
            width: 30px;
            height: 30px;
            font-size: 12px;
        }

        .tipo-cita-card .fa-3x {
            font-size: 2rem !important;
        }
    }
</style>

<script>
    // Variables globales
    let fechaActual = new Date();
    let fechaSeleccionada = null;
    let mesActual = fechaActual.getMonth();
    let anioActual = fechaActual.getFullYear();

    // Variables del modal
    let pasoActual = 1;
    const totalPasos = 6;
    let datosFormulario = {
        fecha_cita: null,
        tipo_cita: null,
        id_especialidad: null,
        id_sucursal: null,
        id_medico: null,
        hora_cita: null
    };

    // Datos de especialidades y sucursales (se cargan desde PHP)
    const especialidades = <?php echo json_encode($especialidades); ?>;
    const sucursales = <?php echo json_encode($sucursales); ?>;
    const esUsuarioPaciente = <?php echo $_SESSION['role_id'] == 4 ? 'true' : 'false'; ?>;

    // Nombres de meses
    const nombresMeses = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    // Inicializar al cargar la página
    document.addEventListener('DOMContentLoaded', function () {
        generarCalendario(mesActual, anioActual);
        inicializarEventListeners();
    });

    // Event listeners
    function inicializarEventListeners() {
        // Navegación del calendario
        document.getElementById('btnMesAnterior').addEventListener('click', function () {
            if (mesActual === 0) {
                mesActual = 11;
                anioActual--;
            } else {
                mesActual--;
            }
            generarCalendario(mesActual, anioActual);
        });

        document.getElementById('btnMesSiguiente').addEventListener('click', function () {
            if (mesActual === 11) {
                mesActual = 0;
                anioActual++;
            } else {
                mesActual++;
            }
            generarCalendario(mesActual, anioActual);
        });

        // Botones del modal
        document.getElementById('btnSiguiente').addEventListener('click', function () {
            if (validarPasoActual()) {
                siguientePaso();
            }
        });

        document.getElementById('btnAnterior').addEventListener('click', function () {
            anteriorPaso();
        });

        document.getElementById('btnConfirmar').addEventListener('click', function () {
            confirmarCita();
        });

        // Reset al cerrar modal
        document.getElementById('modalAgendamiento').addEventListener('hidden.bs.modal', function () {
            resetearModal();
        });
    }

    // Función para generar el calendario
    function generarCalendario(mes, anio) {
        const primerDia = new Date(anio, mes, 1);
        const ultimoDia = new Date(anio, mes + 1, 0);
        const primerDiaSemana = primerDia.getDay();
        const diasEnMes = ultimoDia.getDate();

        // Actualizar header
        document.getElementById('mesAnio').textContent = `${nombresMeses[mes]} ${anio}`;

        // Limpiar calendario
        const calendarBody = document.getElementById('calendar-body');
        calendarBody.innerHTML = '';

        let fecha = 1;

        // Generar 6 semanas máximo
        for (let i = 0; i < 6; i++) {
            const fila = document.createElement('tr');

            // Generar 7 días por semana
            for (let j = 0; j < 7; j++) {
                const celda = document.createElement('td');

                if (i === 0 && j < primerDiaSemana) {
                    // Días del mes anterior
                    const fechaAnterior = new Date(anio, mes, 0).getDate() - (primerDiaSemana - j - 1);
                    celda.textContent = fechaAnterior;
                    celda.classList.add('other-month', 'disabled');
                } else if (fecha > diasEnMes) {
                    // Días del mes siguiente
                    celda.textContent = fecha - diasEnMes;
                    celda.classList.add('other-month', 'disabled');
                    fecha++;
                } else {
                    // Días del mes actual
                    celda.textContent = fecha;

                    const fechaCelda = new Date(anio, mes, fecha);
                    const hoy = new Date();
                    hoy.setHours(0, 0, 0, 0);

                    // Validaciones de fecha
                    if (fechaCelda < hoy) {
                        // Fechas pasadas - deshabilitadas
                        celda.classList.add('disabled');
                    } else {
                        // Fechas futuras - disponibles
                        celda.classList.add('available');
                        // Crear closure para capturar la fecha correcta
                        (function (anio, mes, dia) {
                            celda.addEventListener('click', function () {
                                seleccionarFecha(anio, mes, dia);
                            });
                        })(anio, mes, fecha);
                    }

                    // Marcar día actual
                    if (fechaCelda.getTime() === hoy.getTime()) {
                        celda.classList.add('today');
                    }

                    fecha++;
                }

                fila.appendChild(celda);
            }

            calendarBody.appendChild(fila);

            // Si ya no hay más días del mes, salir del loop
            if (fecha > diasEnMes) {
                break;
            }
        }
    }

    // Función para seleccionar fecha (CORREGIDA)
    function seleccionarFecha(anio, mes, dia) {
        // Remover selección anterior
        const celdaAnterior = document.querySelector('.calendar-table td.selected');
        if (celdaAnterior) {
            celdaAnterior.classList.remove('selected');
        }

        // Seleccionar nueva fecha
        const celdas = document.querySelectorAll('.calendar-table td.available');
        celdas.forEach(celda => {
            if (celda.textContent == dia && !celda.classList.contains('other-month')) {
                celda.classList.add('selected');
            }
        });

        // Guardar fecha seleccionada (CORREGIDO)
        fechaSeleccionada = new Date(anio, mes, dia);
        datosFormulario.fecha_cita = fechaSeleccionada;

        // Formatear fecha para mostrar (CORREGIDO)
        const fechaFormateada = `${dia}/${mes + 1}/${anio}`;
        document.getElementById('fechaModalTitulo').textContent = fechaFormateada;

        // Formatear fecha para MySQL (CORREGIDO)
        const mesFormatted = String(mes + 1).padStart(2, '0');
        const diaFormatted = String(dia).padStart(2, '0');
        document.getElementById('fechaCita').value = `${anio}-${mesFormatted}-${diaFormatted}`;

        // Mostrar mensaje y abrir modal
        mostrarMensaje('success', `Fecha seleccionada: ${fechaFormateada}`);
        abrirModalAgendamiento();
    }

    // Función para mostrar mensajes
    function mostrarMensaje(tipo, mensaje) {
        const container = document.getElementById('mensajes-container');
        const alert = document.createElement('div');
        alert.className = `alert alert-${tipo} alert-dismissible fade show`;
        alert.innerHTML = `
            <i class="fas fa-${tipo === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
            ${mensaje}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        container.innerHTML = '';
        container.appendChild(alert);

        // Auto-remover después de 5 segundos
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

    // Función para abrir el modal
    function abrirModalAgendamiento() {
        pasoActual = 1;
        actualizarIndicadorPasos();
        mostrarPaso(1);
        cargarTiposCita();

        const modal = new bootstrap.Modal(document.getElementById('modalAgendamiento'));
        modal.show();
    }

    // Función para mostrar un paso específico
    function mostrarPaso(numeroPaso) {
        // Ocultar todos los pasos
        document.querySelectorAll('.paso-container').forEach(paso => {
            paso.classList.add('d-none');
        });

        // Mostrar paso actual
        document.getElementById(`paso${numeroPaso}`).classList.remove('d-none');

        // Actualizar botones
        actualizarBotones();

        // Cargar contenido específico del paso
        switch (numeroPaso) {
            case 2:
                cargarEspecialidades();
                break;
            case 3:
                cargarSucursales();
                break;
            case 4:
                cargarMedicosYHorarios();
                break;
            case 6:
                generarResumen();
                break;
        }
    }

    // Función para actualizar el indicador de pasos
    function actualizarIndicadorPasos() {
        for (let i = 1; i <= totalPasos; i++) {
            const step = document.getElementById(`step${i}`);
            step.classList.remove('active', 'completed');

            if (i < pasoActual) {
                step.classList.add('completed');
            } else if (i === pasoActual) {
                step.classList.add('active');
            }
        }
    }

    // Función para actualizar botones
    function actualizarBotones() {
        const btnAnterior = document.getElementById('btnAnterior');
        const btnSiguiente = document.getElementById('btnSiguiente');
        const btnConfirmar = document.getElementById('btnConfirmar');

        // Botón Anterior
        btnAnterior.style.display = pasoActual > 1 ? 'inline-block' : 'none';

        // Botón Siguiente/Confirmar
        if (pasoActual < totalPasos) {
            btnSiguiente.style.display = 'inline-block';
            btnConfirmar.classList.add('d-none');
        } else {
            btnSiguiente.style.display = 'none';
            btnConfirmar.classList.remove('d-none');
        }
    }

    // Función para ir al siguiente paso
    function siguientePaso() {
        if (pasoActual < totalPasos) {
            pasoActual++;
            actualizarIndicadorPasos();
            mostrarPaso(pasoActual);
        }
    }

    // Función para ir al paso anterior
    function anteriorPaso() {
        if (pasoActual > 1) {
            pasoActual--;
            actualizarIndicadorPasos();
            mostrarPaso(pasoActual);
        }
    }

    // Función para resetear el modal
    function resetearModal() {
        pasoActual = 1;
        datosFormulario = {
            fecha_cita: null,
            tipo_cita: null,
            id_especialidad: null,
            id_sucursal: null,
            id_medico: null,
            hora_cita: null
        };

        // Limpiar formulario
        document.getElementById('formAgendamiento').reset();

        // Remover selecciones
        document.querySelectorAll('.selected').forEach(el => el.classList.remove('selected'));

        // Resetear pasos
        actualizarIndicadorPasos();
        mostrarPaso(1);
    }

    // Función para cargar tipos de cita
    function cargarTiposCita() {
        const cards = document.querySelectorAll('.tipo-cita-card');

        cards.forEach(card => {
            card.addEventListener('click', function () {
                // Remover selección anterior
                cards.forEach(c => c.classList.remove('selected'));

                // Seleccionar nueva opción
                this.classList.add('selected');

                const tipo = this.getAttribute('data-tipo');
                datosFormulario.tipo_cita = tipo;
                document.getElementById('tipoCita').value = tipo;

                console.log('Tipo de cita seleccionado:', tipo);
            });
        });
    }

    // Función para cargar especialidades
    function cargarEspecialidades() {
        const container = document.getElementById('especialidadesContainer');
        container.innerHTML = '';

        especialidades.forEach(esp => {
            // Verificar si la especialidad permite el tipo de cita seleccionado
            const permiteVirtual = esp.permite_virtual == 1;
            const permitePresencial = esp.permite_presencial == 1;
            const tipoSeleccionado = datosFormulario.tipo_cita;

            const disponible = (tipoSeleccionado === 'virtual' && permiteVirtual) ||
                    (tipoSeleccionado === 'presencial' && permitePresencial);

            const col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4 mb-3';

            col.innerHTML = `
                <div class="card especialidad-card ${disponible ? '' : 'disabled'}" 
                     data-especialidad="${esp.id_especialidad}">
                    <div class="card-body text-center p-3">
                        <i class="fas fa-user-md fa-2x text-primary mb-2"></i>
                        <h6>${esp.nombre_especialidad}</h6>
                        <small class="text-muted">${esp.descripcion || 'Atención especializada'}</small>
                        ${!disponible ? '<br><small class="text-danger">No disponible para cita ' + tipoSeleccionado + '</small>' : ''}
                    </div>
                </div>
            `;

            container.appendChild(col);

            // Agregar event listener solo si está disponible
            if (disponible) {
                const card = col.querySelector('.especialidad-card');
                card.addEventListener('click', function () {
                    // Remover selección anterior
                    document.querySelectorAll('.especialidad-card').forEach(c =>
                        c.classList.remove('selected'));

                    // Seleccionar nueva opción
                    this.classList.add('selected');

                    const idEspecialidad = this.getAttribute('data-especialidad');
                    datosFormulario.id_especialidad = idEspecialidad;
                    document.getElementById('especialidadSeleccionada').value = idEspecialidad;

                    console.log('Especialidad seleccionada:', idEspecialidad);
                });
            }
        });
    }

    // Función para cargar sucursales
    function cargarSucursales() {
        const container = document.getElementById('sucursalesContainer');
        container.innerHTML = '';

        sucursales.forEach(suc => {
            const col = document.createElement('div');
            col.className = 'col-md-6 mb-3';

            col.innerHTML = `
                <div class="card sucursal-card" data-sucursal="${suc.id_sucursal}">
                    <div class="card-body p-3">
                        <h6><i class="fas fa-building text-primary me-2"></i>${suc.nombre_sucursal}</h6>
                        <p class="text-muted small mb-2">${suc.direccion}</p>
                        <p class="mb-0">
                            <small><i class="fas fa-phone me-1"></i>${suc.telefono || 'N/A'}</small>
                            ${suc.email ? `<br><small><i class="fas fa-envelope me-1"></i>${suc.email}</small>` : ''}
                        </p>
                    </div>
                </div>
            `;

            container.appendChild(col);

            const card = col.querySelector('.sucursal-card');
            card.addEventListener('click', function () {
                // Remover selección anterior
                document.querySelectorAll('.sucursal-card').forEach(c =>
                    c.classList.remove('selected'));

                // Seleccionar nueva opción
                this.classList.add('selected');

                const idSucursal = this.getAttribute('data-sucursal');
                datosFormulario.id_sucursal = idSucursal;
                document.getElementById('sucursalSeleccionada').value = idSucursal;

                console.log('Sucursal seleccionada:', idSucursal);
            });
        });
    }

    // Función para cargar médicos y horarios
    function cargarMedicosYHorarios() {
        const selectMedico = document.getElementById('medicoSeleccionado');
        const selectHora = document.getElementById('horaSeleccionada');

        // Limpiar selects
        selectMedico.innerHTML = '<option value="">Cargando médicos...</option>';
        selectHora.innerHTML = '<option value="">Primero seleccione un médico</option>';

        // Hacer petición AJAX para obtener médicos disponibles
        const params = new URLSearchParams({
            especialidad: datosFormulario.id_especialidad,
            sucursal: datosFormulario.id_sucursal,
            fecha: document.getElementById('fechaCita').value
        });

        fetch(`views/api/obtener-medicos.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Respuesta médicos:', data);
                    selectMedico.innerHTML = '<option value="">Seleccione un médico...</option>';

                    if (data.success && data.medicos.length > 0) {
                        data.medicos.forEach(medico => {
                            const option = document.createElement('option');
                            option.value = medico.id_usuario;
                            option.textContent = `Dr. ${medico.nombre} ${medico.apellido}`;
                            selectMedico.appendChild(option);
                        });
                    } else {
                        selectMedico.innerHTML = '<option value="">No hay médicos disponibles</option>';
                        console.log('Debug médicos:', data.debug);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    selectMedico.innerHTML = '<option value="">Error al cargar médicos</option>';
                });

        // Event listener para cambio de médico (solo agregar una vez)
        selectMedico.removeEventListener('change', manejarCambioMedico);
        selectMedico.addEventListener('change', manejarCambioMedico);

        // Event listener para cambio de hora (solo agregar una vez)
        selectHora.removeEventListener('change', manejarCambioHora);
        selectHora.addEventListener('change', manejarCambioHora);
    }

    // Función separada para manejar cambio de médico
    function manejarCambioMedico() {
        const medicoId = this.value;
        datosFormulario.id_medico = medicoId;

        if (medicoId) {
            cargarHorariosDisponibles(medicoId);
            mostrarInfoMedico(medicoId);
        } else {
            document.getElementById('horaSeleccionada').innerHTML = '<option value="">Primero seleccione un médico</option>';
            document.getElementById('medicoInfo').classList.add('d-none');
        }
    }

    // Función separada para manejar cambio de hora
    function manejarCambioHora() {
        datosFormulario.hora_cita = this.value;
        console.log('Hora seleccionada:', this.value);
    }

    // Función para cargar horarios disponibles
    function cargarHorariosDisponibles(medicoId) {
        const selectHora = document.getElementById('horaSeleccionada');
        selectHora.innerHTML = '<option value="">Cargando horarios...</option>';

        const params = new URLSearchParams({
            medico: medicoId,
            fecha: document.getElementById('fechaCita').value,
            especialidad: datosFormulario.id_especialidad
        });

        fetch(`views/api/obtener-horarios.php?${params}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Respuesta horarios:', data);
                    selectHora.innerHTML = '<option value="">Seleccione un horario...</option>';

                    if (data.success && data.horarios.length > 0) {
                        data.horarios.forEach(horario => {
                            const option = document.createElement('option');
                            option.value = horario.hora;
                            option.textContent = horario.hora_formateada;
                            selectHora.appendChild(option);
                        });
                    } else {
                        selectHora.innerHTML = '<option value="">No hay horarios disponibles</option>';
                        console.log('Debug horarios:', data.debug);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    selectHora.innerHTML = '<option value="">Error al cargar horarios</option>';
                });
    }

    // Función para mostrar información del médico
    function mostrarInfoMedico(medicoId) {
        const medicoInfo = document.getElementById('medicoInfo');
        medicoInfo.innerHTML = `
            <h6><i class="fas fa-info-circle"></i> Información del médico</h6>
            <p class="mb-0">Médico seleccionado. Los horarios disponibles se están cargando...</p>
        `;
        medicoInfo.classList.remove('d-none');
    }

    // Función para validar el paso actual
    function validarPasoActual() {
        switch (pasoActual) {
            case 1:
                if (!datosFormulario.tipo_cita) {
                    mostrarMensaje('warning', 'Por favor seleccione el tipo de cita');
                    return false;
                }
                break;
            case 2:
                if (!datosFormulario.id_especialidad) {
                    mostrarMensaje('warning', 'Por favor seleccione una especialidad');
                    return false;
                }
                break;
            case 3:
                if (!datosFormulario.id_sucursal) {
                    mostrarMensaje('warning', 'Por favor seleccione una sucursal');
                    return false;
                }
                break;
            case 4:
                if (!datosFormulario.id_medico || !datosFormulario.hora_cita) {
                    mostrarMensaje('warning', 'Por favor seleccione médico y horario');
                    return false;
                }
                break;
            case 5:
                // Validar campos del formulario de paciente
                const form = document.getElementById('formAgendamiento');
                const formData = new FormData(form);

                if (!formData.get('nombre_paciente') || !formData.get('apellido_paciente') ||
                        !formData.get('email_paciente') || !formData.get('motivo_consulta')) {
                    mostrarMensaje('warning', 'Por favor complete todos los campos obligatorios');
                    return false;
                }

                // Validar email
                const email = formData.get('email_paciente');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    mostrarMensaje('warning', 'Por favor ingrese un email válido');
                    return false;
                }
                break;
        }
        return true;
    }

    function buscarPacienteExistente() {
        const criterio = prompt('Ingrese cédula, nombre, apellido o email del paciente:');

        if (!criterio || criterio.trim() === '') {
            return;
        }

        // Mostrar loading
        const btnBuscar = event.target;
        const textoOriginal = btnBuscar.innerHTML;
        btnBuscar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
        btnBuscar.disabled = true;

        fetch('views/api/buscar-paciente.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({criterio: criterio.trim()})
        })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.pacientes.length > 0) {
                        mostrarModalSeleccionPaciente(data.pacientes);
                    } else {
                        mostrarMensaje('warning', 'No se encontraron pacientes con ese criterio');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensaje('danger', 'Error al buscar pacientes');
                })
                .finally(() => {
                    // Restaurar botón
                    btnBuscar.innerHTML = textoOriginal;
                    btnBuscar.disabled = false;
                });
    }

    // Función para mostrar modal de selección de paciente
    function mostrarModalSeleccionPaciente(pacientes) {
        // Crear modal dinámicamente
        const modalHtml = `
            <div class="modal fade" id="modalSeleccionPaciente" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-search"></i> Seleccionar Paciente
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nombre</th>
                                            <th>Cédula</th>
                                            <th>Email</th>
                                            <th>Teléfono</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${pacientes.map(paciente => `
                                            <tr>
                                                <td>${paciente.nombre_completo}</td>
                                                <td>${paciente.cedula || 'N/A'}</td>
                                                <td>${paciente.email}</td>
                                                <td>${paciente.telefono || 'N/A'}</td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            onclick="seleccionarPaciente(${paciente.id_usuario}, '${paciente.nombre_completo}', ${JSON.stringify(paciente.datos_completos).replace(/"/g, '&quot;')})">
                                                        <i class="fas fa-check"></i> Seleccionar
                                                    </button>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Remover modal anterior si existe
        const modalExistente = document.getElementById('modalSeleccionPaciente');
        if (modalExistente) {
            modalExistente.remove();
        }

        // Agregar nuevo modal
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('modalSeleccionPaciente'));
        modal.show();
    }

    // Función para seleccionar un paciente
    function seleccionarPaciente(idPaciente, nombreCompleto, datosCompletos) {
        // Llenar campos del formulario
        document.getElementById('nombrePaciente').value = datosCompletos.nombre || '';
        document.getElementById('apellidoPaciente').value = datosCompletos.apellido || '';
        document.getElementById('emailPaciente').value = datosCompletos.email || '';
        document.getElementById('telefonoPaciente').value = datosCompletos.telefono || '';

        // Solo llenar cédula si el campo existe (para roles que no son paciente)
        const cedulaField = document.getElementById('cedulaPaciente');
        if (cedulaField) {
            cedulaField.value = datosCompletos.cedula || '';
        }

        document.getElementById('idPacienteSeleccionado').value = idPaciente;

        // Cerrar modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalSeleccionPaciente'));
        modal.hide();

        // Mostrar mensaje
        mostrarMensaje('success', `Paciente seleccionado: ${nombreCompleto}`);

        console.log('Paciente seleccionado:', {idPaciente, datosCompletos});
    }

    // ===== FUNCIONES DE VALIDACIÓN DE CÉDULA =====

    // Función para validar entrada de cédula
    function validarCedulaInput() {
        const cedulaInput = document.getElementById('cedulaPaciente');
        const btnConsultar = document.getElementById('btnConsultarCedula');
        const cedulaStatus = document.getElementById('cedulaStatus');
        const cedulaResult = document.getElementById('cedulaResult');

        if (!cedulaInput || !btnConsultar || !cedulaStatus || !cedulaResult) {
            return; // Los elementos no existen (usuario paciente)
        }

        const cedula = cedulaInput.value.replace(/\D/g, ''); // Solo números

        // Actualizar el input solo con números
        cedulaInput.value = cedula;

        // Resetear resultado anterior
        cedulaResult.style.display = 'none';

        if (cedula.length === 10) {
            if (validarCedulaEcuatoriana(cedula)) {
                cedulaStatus.innerHTML = '<i class="fas fa-check text-success"></i>';
                btnConsultar.disabled = false;
                cedulaInput.classList.remove('is-invalid');
                cedulaInput.classList.add('is-valid');
            } else {
                cedulaStatus.innerHTML = '<i class="fas fa-times text-danger"></i>';
                btnConsultar.disabled = true;
                cedulaInput.classList.remove('is-valid');
                cedulaInput.classList.add('is-invalid');
            }
        } else if (cedula.length > 0) {
            cedulaStatus.innerHTML = '<i class="fas fa-clock text-warning"></i>';
            btnConsultar.disabled = true;
            cedulaInput.classList.remove('is-valid', 'is-invalid');
        } else {
            cedulaStatus.innerHTML = '<i class="fas fa-question text-muted"></i>';
            btnConsultar.disabled = true;
            cedulaInput.classList.remove('is-valid', 'is-invalid');
        }
    }

    // Función para validar cédula ecuatoriana
    function validarCedulaEcuatoriana(cedula) {
        if (cedula.length !== 10)
            return false;

        const digitos = cedula.split('').map(d => parseInt(d));
        let suma = 0;

        for (let i = 0; i < 9; i++) {
            let digito = digitos[i];
            if (i % 2 === 0) {
                digito *= 2;
                if (digito > 9)
                    digito -= 9;
            }
            suma += digito;
        }

        const digitoVerificador = digitos[9];
        const residuo = suma % 10;
        const resultado = residuo === 0 ? 0 : 10 - residuo;

        return digitoVerificador === resultado;
    }

    // Función para consultar cédula en API
    async function consultarCedula() {
        const cedulaInput = document.getElementById('cedulaPaciente');
        const btnConsultar = document.getElementById('btnConsultarCedula');
        const cedulaResult = document.getElementById('cedulaResult');
        const nombreInput = document.getElementById('nombrePaciente');
        const apellidoInput = document.getElementById('apellidoPaciente');

        if (!cedulaInput || !btnConsultar || !cedulaResult) {
            return; // Los elementos no existen (usuario paciente)
        }

        const cedula = cedulaInput.value;

        if (!cedula || cedula.length !== 10) {
            return;
        }

        // Mostrar loading
        btnConsultar.disabled = true;
        btnConsultar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando...';

        try {
            const response = await fetch('views/api/consultar-cedula.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({cedula: cedula})
            });

            const data = await response.json();

            if (data.success) {
                cedulaResult.innerHTML = `
                    <div class="alert alert-success alert-sm mb-0">
                        <i class="fas fa-check-circle"></i> 
                        <strong>Datos encontrados:</strong> ${data.nombres} ${data.apellidos}
                    </div>
                `;
                cedulaResult.style.display = 'block';

                // Completar campos automáticamente
                if (data.nombres && nombreInput) {
                    nombreInput.value = data.nombres;
                    nombreInput.classList.add('is-valid');
                }
                if (data.apellidos && apellidoInput) {
                    apellidoInput.value = data.apellidos;
                    apellidoInput.classList.add('is-valid');
                }

                // Highlight de los campos completados
                setTimeout(() => {
                    if (nombreInput)
                        nombreInput.classList.remove('is-valid');
                    if (apellidoInput)
                        apellidoInput.classList.remove('is-valid');
                }, 3000);

            } else {
                cedulaResult.innerHTML = `
                    <div class="alert alert-warning alert-sm mb-0">
                        <i class="fas fa-exclamation-triangle"></i> 
                        ${data.error || 'No se encontraron datos para esta cédula'}
                    </div>
                `;
                cedulaResult.style.display = 'block';
            }

        } catch (error) {
            console.error('Error:', error);
            cedulaResult.innerHTML = `
                <div class="alert alert-danger alert-sm mb-0">
                    <i class="fas fa-times-circle"></i> 
                    Error de conexión. Verifique su internet e inténtelo nuevamente.
                </div>
            `;
            cedulaResult.style.display = 'block';
        } finally {
            // Restaurar botón
            btnConsultar.disabled = false;
            btnConsultar.innerHTML = '<i class="fas fa-search"></i> Consultar';
        }
    }

    function generarResumen() {
        const resumenContainer = document.getElementById('resumenCita');

        // Obtener datos del formulario
        const formData = new FormData(document.getElementById('formAgendamiento'));

        // Obtener nombres descriptivos
        const tipoTexto = datosFormulario.tipo_cita === 'virtual' ? 'Virtual' : 'Presencial';
        const especialidadTexto = especialidades.find(e => e.id_especialidad == datosFormulario.id_especialidad)?.nombre_especialidad || '';
        const sucursalTexto = sucursales.find(s => s.id_sucursal == datosFormulario.id_sucursal)?.nombre_sucursal || '';
        const medicoTexto = document.getElementById('medicoSeleccionado').selectedOptions[0]?.text || '';
        const horaTexto = document.getElementById('horaSeleccionada').selectedOptions[0]?.text || '';

        resumenContainer.innerHTML = `
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-calendar-check"></i> Resumen de la Cita</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Fecha:</strong> ${document.getElementById('fechaModalTitulo').textContent}</p>
                            <p><strong>Hora:</strong> ${horaTexto}</p>
                            <p><strong>Tipo:</strong> ${tipoTexto}</p>
                            <p><strong>Especialidad:</strong> ${especialidadTexto}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Médico:</strong> ${medicoTexto}</p>
                            <p><strong>Sucursal:</strong> ${sucursalTexto}</p>
                            <p><strong>Paciente:</strong> ${formData.get('nombre_paciente')} ${formData.get('apellido_paciente')}</p>
                            <p><strong>Email:</strong> ${formData.get('email_paciente')}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <p><strong>Motivo:</strong> ${formData.get('motivo_consulta')}</p>
                            ${formData.get('observaciones') ? `<p><strong>Observaciones:</strong> ${formData.get('observaciones')}</p>` : ''}
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle"></i>
                <strong>Importante:</strong> Verifique que todos los datos sean correctos antes de confirmar la cita.
            </div>
        `;

        console.log('Resumen generado:', datosFormulario);
    }

    // Función para confirmar cita (placeholder)
    function confirmarCita() {
        // Mostrar loading
        const btnConfirmar = document.getElementById('btnConfirmar');
        const textoOriginal = btnConfirmar.innerHTML;
        btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btnConfirmar.disabled = true;

        // Simular guardado
        setTimeout(() => {
            mostrarMensaje('success', 'Cita agendada exitosamente');

            // Cerrar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalAgendamiento'));
            modal.hide();

            // Restaurar botón
            btnConfirmar.innerHTML = textoOriginal;
            btnConfirmar.disabled = false;

            console.log('Cita confirmada:', datosFormulario);
        }, 2000);
    }
</script>

</body>
</html>