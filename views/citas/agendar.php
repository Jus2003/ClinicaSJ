<?php
require_once 'models/Especialidad.php';
require_once 'models/Sucursal.php';
require_once 'models/User.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
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
            <!-- Header mejorado -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="text-primary mb-2">
                                <i class="fas fa-calendar-plus me-2"></i> Agendar Nueva Cita
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                Seleccione una fecha disponible en el calendario para comenzar el proceso de agendamiento
                            </p>
                        </div>
                        <div>
                            <a href="index.php?action=citas/agenda" class="btn btn-outline-primary me-2">
                                <i class="fas fa-calendar"></i> Mi Agenda
                            </a>
                            <a href="index.php?action=citas/gestionar" class="btn btn-outline-secondary">
                                <i class="fas fa-list"></i> Gestionar Citas
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mensajes -->
            <div id="mensajes-container"></div>

            <!-- Calendario Principal Mejorado -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-lg">
                        <div class="card-header bg-gradient-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar-alt me-2"></i> Calendario de Disponibilidad
                                </h5>
                                <div class="d-flex align-items-center">
                                    <small class="opacity-75 me-2">
                                        <i class="fas fa-clock me-1"></i> Tiempo real
                                    </small>
                                    <div class="badge bg-light text-dark">
                                        <?php echo date('M Y'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <!-- Navegación del calendario -->
                            <div class="calendar-nav bg-light p-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnMesAnterior">
                                        <i class="fas fa-chevron-left"></i> Anterior
                                    </button>
                                    <h4 id="mesAnio" class="mb-0 text-primary fw-bold"></h4>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnMesSiguiente">
                                        Siguiente <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Tabla del calendario -->
                            <div class="p-3">
                                <div class="table-responsive">
                                    <table class="table table-bordered calendar-table mb-0">
                                        <thead class="bg-gradient-light">
                                            <tr>
                                                <th class="text-center fw-semibold">DOM</th>
                                                <th class="text-center fw-semibold">LUN</th>
                                                <th class="text-center fw-semibold">MAR</th>
                                                <th class="text-center fw-semibold">MIÉ</th>
                                                <th class="text-center fw-semibold">JUE</th>
                                                <th class="text-center fw-semibold">VIE</th>
                                                <th class="text-center fw-semibold">SÁB</th>
                                            </tr>
                                        </thead>
                                        <tbody id="calendar-body">
                                            <!-- Días del calendario se generan dinámicamente -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Indicador de carga -->
                            <div id="calendar-loading" class="text-center p-4 d-none">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p class="mt-2 text-muted">Cargando calendario...</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel de información mejorado -->
                <div class="col-lg-4">
                    <div class="row">
                        <!-- Card de instrucciones -->
                        <div class="col-12 mb-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-gradient-info text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-lightbulb me-2"></i> Guía de Agendamiento
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="steps-guide">
                                        <div class="step-guide-item mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="step-number bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    1
                                                </div>
                                                <div>
                                                    <h6 class="mb-1">Seleccionar Fecha</h6>
                                                    <small class="text-muted">Haga clic en una fecha disponible del calendario</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="step-guide-item mb-3">
                                            <div class="d-flex align-items-start">
                                                <div class="step-number bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    2
                                                </div>
                                                <div>
                                                    <h6 class="mb-1">Completar Datos</h6>
                                                    <small class="text-muted">Siga los 6 pasos del formulario</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="step-guide-item">
                                            <div class="d-flex align-items-start">
                                                <div class="step-number bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    3
                                                </div>
                                                <div>
                                                    <h6 class="mb-1">Confirmar Cita</h6>
                                                    <small class="text-muted">Revise y confirme los datos</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-3">

                                    <!-- Leyenda del calendario -->
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-palette me-1"></i> Leyenda
                                    </h6>
                                    <div class="legend-items">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="legend-box available me-2"></div>
                                            <small class="fw-medium">Fecha disponible</small>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="legend-box today me-2"></div>
                                            <small class="fw-medium">Día actual</small>
                                        </div>
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="legend-box selected me-2"></div>
                                            <small class="fw-medium">Fecha seleccionada</small>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <div class="legend-box disabled me-2"></div>
                                            <small class="fw-medium">No disponible</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Datos del usuario si es paciente -->
                        <?php if ($_SESSION['role_id'] == 4 && $datosUsuario): ?>
                            <div class="col-12">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-gradient-success text-white">
                                        <h6 class="mb-0">
                                            <i class="fas fa-user-check me-2"></i> Sus Datos
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="user-info-card">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="avatar-md bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    <?php echo strtoupper(substr($datosUsuario['nombre'], 0, 1) . substr($datosUsuario['apellido'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($datosUsuario['nombre'] . ' ' . $datosUsuario['apellido']); ?></h6>
                                                    <small class="text-muted">Paciente registrado</small>
                                                </div>
                                            </div>

                                            <div class="info-items">
                                                <div class="info-item mb-2">
                                                    <i class="fas fa-envelope text-primary me-2"></i>
                                                    <small><?php echo htmlspecialchars($datosUsuario['email']); ?></small>
                                                </div>

                                                <?php if ($datosUsuario['telefono']): ?>
                                                    <div class="info-item mb-2">
                                                        <i class="fas fa-phone text-success me-2"></i>
                                                        <small><?php echo htmlspecialchars($datosUsuario['telefono']); ?></small>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($datosUsuario['cedula']): ?>
                                                    <div class="info-item">
                                                        <i class="fas fa-id-card text-info me-2"></i>
                                                        <small>CI: <?php echo htmlspecialchars($datosUsuario['cedula']); ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Agendamiento Mejorado -->
    <div class="modal fade" id="modalAgendamiento" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl">
            <div class="modal-content border-0 shadow-lg">
                <!-- Header del modal -->
                <div class="modal-header bg-gradient-primary text-white border-0">
                    <div class="d-flex align-items-center">
                        <div class="modal-icon bg-white bg-opacity-20 rounded-circle p-2 me-3">
                            <i class="fas fa-calendar-plus text-white"></i>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">Agendar Nueva Cita Médica</h5>
                            <small class="opacity-75">
                                Fecha seleccionada: <span id="fechaModalTitulo" class="fw-bold"></span>
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <!-- Indicador de pasos mejorado -->
                <div class="steps-indicator bg-light border-bottom">
                    <div class="container-fluid py-3">
                        <div class="row text-center">
                            <div class="col step-item active" id="step1">
                                <div class="step-circle">
                                    <i class="fas fa-desktop"></i>
                                </div>
                                <small class="step-label">Tipo de Cita</small>
                                <div class="step-line"></div>
                            </div>
                            <div class="col step-item" id="step2">
                                <div class="step-circle">
                                    <i class="fas fa-user-md"></i>
                                </div>
                                <small class="step-label">Especialidad</small>
                                <div class="step-line"></div>
                            </div>
                            <div class="col step-item" id="step3">
                                <div class="step-circle">
                                    <i class="fas fa-building"></i>
                                </div>
                                <small class="step-label">Sucursal</small>
                                <div class="step-line"></div>
                            </div>
                            <div class="col step-item" id="step4">
                                <div class="step-circle">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <small class="step-label">Médico y Hora</small>
                                <div class="step-line"></div>
                            </div>
                            <div class="col step-item" id="step5">
                                <div class="step-circle">
                                    <i class="fas fa-user"></i>
                                </div>
                                <small class="step-label">Datos Paciente</small>
                                <div class="step-line"></div>
                            </div>
                            <div class="col step-item" id="step6">
                                <div class="step-circle">
                                    <i class="fas fa-check"></i>
                                </div>
                                <small class="step-label">Confirmación</small>
                            </div>
                        </div>

                        <!-- Barra de progreso -->
                        <div class="progress mt-3" style="height: 4px;">
                            <div class="progress-bar bg-success" id="progressBar" role="progressbar" 
                                 style="width: 16.67%" aria-valuenow="16.67" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenido del modal -->
                <div class="modal-body p-0">
                    <div class="container-fluid p-4">
                        <form id="formAgendamiento" novalidate>
                            <!-- Paso 1: Tipo de Cita -->
                            <div class="paso-container" id="paso1">
                                <div class="step-header mb-4">
                                    <h5 class="text-primary mb-2">
                                        <i class="fas fa-desktop me-2"></i> 
                                        Seleccione el tipo de cita médica
                                    </h5>
                                    <p class="text-muted mb-0">Elija la modalidad de atención que prefiere para su consulta</p>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="card tipo-cita-card h-100" data-tipo="presencial">
                                            <div class="card-body text-center p-4">
                                                <div class="tipo-icon mb-3">
                                                    <i class="fas fa-hospital fa-3x text-primary"></i>
                                                </div>
                                                <h5 class="card-title">Cita Presencial</h5>
                                                <p class="card-text text-muted">
                                                    Atención médica en nuestras instalaciones físicas. 
                                                    Incluye examen físico completo y uso de equipos médicos.
                                                </p>
                                                <div class="mt-3">
                                                    <span class="badge bg-primary">Recomendado</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card tipo-cita-card h-100" data-tipo="virtual">
                                            <div class="card-body text-center p-4">
                                                <div class="tipo-icon mb-3">
                                                    <i class="fas fa-video fa-3x text-success"></i>
                                                </div>
                                                <h5 class="card-title">Cita Virtual</h5>
                                                <p class="card-text text-muted">
                                                    Consulta médica por videollamada desde la comodidad de su hogar. 
                                                    Disponible solo para ciertas especialidades.
                                                </p>
                                                <div class="mt-3">
                                                    <span class="badge bg-success">Desde casa</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="tipoCita" name="tipo_cita" required>
                            </div>

                            <!-- Paso 2: Especialidad -->
                            <div class="paso-container d-none" id="paso2">
                                <div class="step-header mb-4">
                                    <h5 class="text-primary mb-2">
                                        <i class="fas fa-user-md me-2"></i> 
                                        Seleccione la especialidad médica
                                    </h5>
                                    <p class="text-muted mb-0">Elija la especialidad según el tipo de atención que necesita</p>
                                </div>

                                <div class="row g-3" id="especialidadesContainer">
                                    <!-- Las especialidades se cargan dinámicamente -->
                                </div>

                                <!-- Loading especialidades -->
                                <div id="especialidadesLoading" class="text-center py-4 d-none">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Cargando especialidades...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Cargando especialidades disponibles...</p>
                                </div>

                                <input type="hidden" id="especialidadSeleccionada" name="id_especialidad" required>
                            </div>

                            <!-- Paso 3: Sucursal -->
                            <div class="paso-container d-none" id="paso3">
                                <div class="step-header mb-4">
                                    <h5 class="text-primary mb-2">
                                        <i class="fas fa-building me-2"></i> 
                                        Seleccione la sucursal
                                    </h5>
                                    <p class="text-muted mb-0">Elija la sucursal donde desea recibir su atención médica</p>
                                </div>

                                <div class="row g-3" id="sucursalesContainer">
                                    <!-- Las sucursales se cargan dinámicamente -->
                                </div>

                                <!-- Loading sucursales -->
                                <div id="sucursalesLoading" class="text-center py-4 d-none">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Cargando sucursales...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Cargando sucursales disponibles...</p>
                                </div>

                                <input type="hidden" id="sucursalSeleccionada" name="id_sucursal" required>
                            </div>

                            <!-- Paso 4: Médico y Hora -->
                            <div class="paso-container d-none" id="paso4">
                                <div class="step-header mb-4">
                                    <h5 class="text-primary mb-2">
                                        <i class="fas fa-clock me-2"></i> 
                                        Seleccione médico y horario disponible
                                    </h5>
                                    <p class="text-muted mb-0">Elija el médico especialista y el horario que mejor se adapte a sus necesidades</p>
                                </div>

                                <div class="row">
                                    <!-- Selección de médico -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-user-md text-primary me-1"></i>
                                            Médico Especialista
                                        </label>
                                        <select class="form-select form-select-lg" id="medicoSeleccionado" name="id_medico" required>
                                            <option value="">Seleccione un médico...</option>
                                        </select>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle"></i> Solo se muestran médicos disponibles para la fecha seleccionada
                                        </div>
                                    </div>

                                    <!-- Selección de horario -->
                                    <div class="col-md-6 mb-4">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-clock text-success me-1"></i>
                                            Horario Disponible
                                        </label>
                                        <select class="form-select form-select-lg" id="horaSeleccionada" name="hora_cita" required>
                                            <option value="">Primero seleccione un médico</option>
                                        </select>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle"></i> Los horarios se actualizan según el médico seleccionado
                                        </div>
                                    </div>
                                </div>

                                <!-- Información del médico seleccionado -->
                                <div id="medicoInfo" class="alert alert-info border-0 d-none">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-info-circle fa-lg"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="alert-heading mb-1">Información del médico</h6>
                                            <p class="mb-0" id="medicoInfoContent">
                                                Los horarios disponibles se están cargando...
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Loading médicos y horarios -->
                                <div id="medicosLoading" class="text-center py-4 d-none">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Cargando médicos...</span>
                                    </div>
                                    <p class="mt-2 text-muted">Buscando médicos disponibles...</p>
                                </div>
                            </div>