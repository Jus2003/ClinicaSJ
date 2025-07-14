<?php
require_once 'models/Especialidad.php';
require_once 'models/Sucursal.php';
require_once 'models/User.php';

// *** PROCESAMIENTO DE AGENDAMIENTO (ALTERNATIVA) ***
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'agendar_cita') {
    header('Content-Type: application/json');

    try {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();

        // Obtener datos del POST
        $fechaCita = $_POST['fecha_cita'] ?? '';
        $tipoCita = $_POST['tipo_cita'] ?? '';
        $idEspecialidad = $_POST['id_especialidad'] ?? '';
        $idSucursal = $_POST['id_sucursal'] ?? '';
        $idMedico = $_POST['id_medico'] ?? '';
        $horaCita = $_POST['hora_cita'] ?? '';
        $motivoConsulta = $_POST['motivo_consulta'] ?? '';
        $observaciones = $_POST['observaciones'] ?? '';
        $idPaciente = $_POST['id_paciente'] ?? $_SESSION['user_id'];

        // Validaciones básicas
        if (empty($fechaCita) || empty($tipoCita) || empty($idEspecialidad) ||
                empty($idSucursal) || empty($idMedico) || empty($horaCita) ||
                empty($motivoConsulta)) {
            throw new Exception('Todos los campos obligatorios deben estar completos');
        }

        // Verificar que la fecha no sea en el pasado
        if ($fechaCita < date('Y-m-d')) {
            throw new Exception('No se pueden agendar citas en fechas pasadas');
        }

        // Verificar que no haya conflicto de horarios
        $sqlConflicto = "SELECT COUNT(*) as total FROM citas 
                         WHERE id_medico = :medico 
                         AND fecha_cita = :fecha 
                         AND hora_cita = :hora
                         AND estado_cita NOT IN ('cancelada', 'no_asistio')";

        $stmtConflicto = $db->prepare($sqlConflicto);
        $stmtConflicto->execute([
            'medico' => $idMedico,
            'fecha' => $fechaCita,
            'hora' => $horaCita
        ]);

        if ($stmtConflicto->fetch()['total'] > 0) {
            throw new Exception('Ya existe una cita en ese horario');
        }

        // Insertar la cita
        $sqlInsert = "INSERT INTO citas (id_paciente, id_medico, id_especialidad, id_sucursal, 
                                       fecha_cita, hora_cita, tipo_cita, estado_cita, 
                                       motivo_consulta, observaciones, id_usuario_registro, fecha_registro) 
                      VALUES (:paciente, :medico, :especialidad, :sucursal, :fecha, :hora, 
                              :tipo, 'agendada', :motivo, :observaciones, :usuario_registro, NOW())";

        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->execute([
            'paciente' => $idPaciente,
            'medico' => $idMedico,
            'especialidad' => $idEspecialidad,
            'sucursal' => $idSucursal,
            'fecha' => $fechaCita,
            'hora' => $horaCita,
            'tipo' => $tipoCita,
            'motivo' => $motivoConsulta,
            'observaciones' => $observaciones,
            'usuario_registro' => $_SESSION['user_id']
        ]);

        $citaId = $db->lastInsertId();

        // *** ENVIAR NOTIFICACIONES ***
        try {
            require_once 'includes/notificaciones-citas.php';
            $notificador = new NotificacionesCitas($db);
            $notificador->notificarNuevaCita($citaId);

            error_log("✅ Cita ID {$citaId} creada y notificaciones enviadas (método alternativo)");

            echo json_encode([
                'success' => true,
                'message' => 'Cita agendada exitosamente. Notificaciones enviadas por correo.',
                'cita_id' => $citaId
            ]);
        } catch (Exception $notifError) {
            error_log("⚠️ Cita ID {$citaId} creada pero error en notificaciones: " . $notifError->getMessage());

            echo json_encode([
                'success' => true,
                'message' => 'Cita agendada exitosamente. (Error enviando notificaciones: ' . $notifError->getMessage() . ')',
                'cita_id' => $citaId
            ]);
        }
    } catch (Exception $e) {
        error_log("❌ Error en agendamiento alternativo: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }

    exit; // Importante: terminar el script aquí
}

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

                            <!-- Paso 5: Datos del Paciente -->
                            <div class="paso-container d-none" id="paso5">
                                <div class="step-header mb-4">
                                    <h5 class="text-primary mb-2">
                                        <i class="fas fa-user me-2"></i> 
                                        Información del paciente
                                    </h5>
                                    <p class="text-muted mb-0">Complete los datos del paciente para la cita médica</p>
                                </div>

                                <?php if ($_SESSION['role_id'] == 4): ?>
                                    <!-- MODO PACIENTE -->
                                    <div id="pacientePropio" class="paciente-mode active">
                                        <div class="alert alert-success border-0 mb-4">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-check fa-lg me-3"></i>
                                                <div class="flex-grow-1">
                                                    <h6 class="alert-heading mb-1">Cita para usted</h6>
                                                    <p class="mb-0">Sus datos ya están cargados automáticamente</p>
                                                </div>
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="cambiarAConocido()">
                                                    <i class="fas fa-user-friends"></i> Cita para un conocido
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="pacienteConocido" class="paciente-mode" style="display: none;">
                                        <div class="alert alert-info border-0 mb-4">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-friends fa-lg me-3"></i>
                                                <div class="flex-grow-1">
                                                    <h6 class="alert-heading mb-1">Cita para un conocido</h6>
                                                    <p class="mb-0">Complete los datos de la persona que recibirá la atención</p>
                                                </div>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cambiarAPropio()">
                                                    <i class="fas fa-user"></i> Mi cita
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- BÚSQUEDA INTELIGENTE DE CÉDULA -->
                                <div class="row mb-4">
                                    <div class="col-md-8">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-id-card text-primary me-1"></i>
                                            Número de Cédula
                                            <?php if ($_SESSION['role_id'] != 4): ?>
                                                <span class="text-danger">*</span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="input-group position-relative"> <!-- AGREGAR position-relative aquí -->
                                            <input type="text" class="form-control form-control-lg" 
                                                   name="cedula_paciente" id="cedulaPaciente" 
                                                   placeholder="Escriba el número de cédula..." 
                                                   maxlength="10" 
                                                   autocomplete="off"
                                                   <?php if ($_SESSION['role_id'] == 4): ?>
                                                       value="<?php echo htmlspecialchars($datosUsuarioActual['cedula'] ?? ''); ?>"
                                                   <?php else: ?>
                                                       required
                                                   <?php endif; ?>>
                                            <div class="input-group-text" id="cedulaStatus">
                                                <i class="fas fa-search text-muted"></i>
                                            </div>

                                            <!-- Dropdown de sugerencias -->
                                            <div id="cedulaSugerencias" class="sugerencias-dropdown" style="display: none;">
                                                <div id="listaSugerencias">
                                                    <!-- Sugerencias dinámicas -->
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Resultado de API -->
                                        <div id="cedulaApiResult" class="mt-2" style="display: none;"></div>

                                        <div class="form-text">
                                            <i class="fas fa-info-circle"></i> 
                                            <?php if ($_SESSION['role_id'] != 4): ?>
                                                Mientras escribe aparecerán sugerencias de pacientes registrados
                                            <?php else: ?>
                                                Si es para un conocido, ingrese su número de cédula
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">&nbsp;</label>
                                        <div class="d-grid">
                                            <button type="button" class="btn btn-outline-info btn-lg" 
                                                    id="btnConsultarApi" onclick="consultarCedulaApi()" disabled>
                                                <i class="fas fa-search"></i> Consultar API
                                            </button>
                                        </div>
                                        <div class="form-text text-center">
                                            <small>Datos del Registro Civil</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- DATOS DEL PACIENTE -->
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-user text-success me-1"></i>
                                            Nombre <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control form-control-lg" 
                                               name="nombre_paciente" id="nombrePaciente" required
                                               placeholder="Nombre del paciente"
                                               value="<?php echo $_SESSION['role_id'] == 4 ? htmlspecialchars($datosUsuario['nombre'] ?? '') : ''; ?>">
                                        <div class="invalid-feedback">Por favor ingrese el nombre</div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-user text-success me-1"></i>
                                            Apellido <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control form-control-lg" 
                                               name="apellido_paciente" id="apellidoPaciente" required
                                               placeholder="Apellido del paciente"
                                               value="<?php echo $_SESSION['role_id'] == 4 ? htmlspecialchars($datosUsuario['apellido'] ?? '') : ''; ?>">
                                        <div class="invalid-feedback">Por favor ingrese el apellido</div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-envelope text-info me-1"></i>
                                            Email <span class="text-danger">*</span>
                                        </label>
                                        <input type="email" class="form-control form-control-lg" 
                                               name="email_paciente" id="emailPaciente" required
                                               placeholder="ejemplo@correo.com"
                                               value="<?php echo $_SESSION['role_id'] == 4 ? htmlspecialchars($datosUsuario['email'] ?? '') : ''; ?>">
                                        <div class="invalid-feedback">Por favor ingrese un email válido</div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-phone text-warning me-1"></i>
                                            Teléfono
                                        </label>
                                        <input type="text" class="form-control form-control-lg" 
                                               name="telefono_paciente" id="telefonoPaciente"
                                               placeholder="0999123456"
                                               value="<?php echo $_SESSION['role_id'] == 4 ? htmlspecialchars($datosUsuario['telefono'] ?? '') : ''; ?>">
                                        <div class="form-text">Opcional - Para contacto y recordatorios</div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-calendar text-secondary me-1"></i>
                                            Fecha de Nacimiento
                                        </label>
                                        <input type="date" class="form-control form-control-lg" 
                                               name="fecha_nacimiento_paciente" id="fechaNacimientoPaciente"
                                               value="<?php echo $_SESSION['role_id'] == 4 ? htmlspecialchars($datosUsuario['fecha_nacimiento'] ?? '') : ''; ?>">
                                        <div class="form-text">Opcional - Para cálculo de edad</div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-venus-mars text-purple me-1"></i>
                                            Género
                                        </label>
                                        <select class="form-select form-select-lg" name="genero_paciente" id="generoPaciente">
                                            <option value="">Seleccione...</option>
                                            <option value="masculino" <?php echo ($_SESSION['role_id'] == 4 && ($datosUsuario['genero'] ?? '') === 'masculino') ? 'selected' : ''; ?>>Masculino</option>
                                            <option value="femenino" <?php echo ($_SESSION['role_id'] == 4 && ($datosUsuario['genero'] ?? '') === 'femenino') ? 'selected' : ''; ?>>Femenino</option>
                                            <option value="otro" <?php echo ($_SESSION['role_id'] == 4 && ($datosUsuario['genero'] ?? '') === 'otro') ? 'selected' : ''; ?>>Otro</option>
                                        </select>
                                        <div class="form-text">Opcional - Para estadísticas médicas</div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                            Dirección
                                        </label>
                                        <textarea class="form-control" name="direccion_paciente" id="direccionPaciente" 
                                                  rows="2" placeholder="Dirección completa del paciente"><?php echo $_SESSION['role_id'] == 4 ? htmlspecialchars($datosUsuario['direccion'] ?? '') : ''; ?></textarea>
                                        <div class="form-text">Opcional - Para ubicación y contacto</div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-stethoscope text-primary me-1"></i>
                                            Motivo de la Consulta <span class="text-danger">*</span>
                                        </label>
                                        <textarea class="form-control form-control-lg" name="motivo_consulta" id="motivoConsulta" 
                                                  rows="3" placeholder="Describa brevemente el motivo de la consulta médica..." required></textarea>
                                        <div class="invalid-feedback">Por favor describa el motivo de la consulta</div>
                                        <div class="form-text">Sea específico para ayudar al médico a prepararse mejor</div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-notes-medical text-secondary me-1"></i>
                                            Observaciones Adicionales
                                        </label>
                                        <textarea class="form-control" name="observaciones" id="observaciones" 
                                                  rows="2" placeholder="Información adicional relevante (alergias, medicamentos actuales, etc.)"></textarea>
                                        <div class="form-text">Opcional - Información adicional que considere importante</div>
                                    </div>
                                </div>

                                <!-- CAMPOS OCULTOS -->
                                <?php if ($_SESSION['role_id'] == 4): ?>
                                    <input type="hidden" name="id_paciente_original" value="<?php echo $_SESSION['user_id']; ?>">
                                    <input type="hidden" name="es_para_conocido" id="esParaConocido" value="0">
                                <?php endif; ?>
                                <input type="hidden" name="id_paciente_seleccionado" id="idPacienteSeleccionado">
                                <input type="hidden" name="paciente_desde_bd" id="pacienteDesdeBd" value="0">
                            </div>

                            <!-- Paso 6: Confirmación -->
                            <div class="paso-container d-none" id="paso6">
                                <div class="step-header mb-4">
                                    <h5 class="text-success mb-2">
                                        <i class="fas fa-check-circle me-2"></i> 
                                        Confirmar datos de la cita médica
                                    </h5>
                                    <p class="text-muted mb-0">Revise cuidadosamente todos los datos antes de confirmar la cita</p>
                                </div>

                                <!-- Resumen de la cita -->
                                <div id="resumenCita" class="mb-4">
                                    <!-- El resumen se genera dinámicamente -->
                                </div>

                                <!-- Advertencia importante sobre triaje -->
                                <div class="alert alert-warning border-0 shadow-sm">
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-clipboard-list fa-2x text-warning"></i>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="alert-heading mb-2">
                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                ¡Importante! - Triaje Digital
                                            </h6>
                                            <p class="mb-2">
                                                <strong>Una vez confirmada su cita, no olvide completar el triaje digital.</strong>
                                            </p>
                                            <ul class="mb-2 small">
                                                <li>El triaje digital debe completarse <strong>antes de su consulta</strong></li>
                                                <li>Le permitirá al médico prepararse mejor para su atención</li>
                                                <li>Optimiza el tiempo de consulta y mejora la calidad de atención</li>
                                                <li>Recibirá recordatorios automáticos por email</li>
                                            </ul>
                                            <div class="mt-2">
                                                <span class="badge bg-warning text-dark me-2">
                                                    <i class="fas fa-clock"></i> Se recomienda completarlo 24h antes
                                                </span>
                                                <span class="badge bg-info">
                                                    <i class="fas fa-mobile-alt"></i> Disponible desde cualquier dispositivo
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Términos y condiciones -->
                                <div class="card border-light bg-light">
                                    <div class="card-body py-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="aceptarTerminos" required>
                                            <label class="form-check-label small" for="aceptarTerminos">
                                                Acepto los <a href="#" class="text-primary" data-bs-toggle="modal" data-bs-target="#modalTerminos">términos y condiciones</a> 
                                                del servicio y autorizo el tratamiento de mis datos personales según la 
                                                <a href="#" class="text-primary">política de privacidad</a>.
                                            </label>
                                            <div class="invalid-feedback">Debe aceptar los términos y condiciones</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Campos ocultos del formulario -->
                            <input type="hidden" name="fecha_cita" id="fechaCita">
                            <input type="hidden" name="id_usuario_registro" value="<?php echo $_SESSION['user_id']; ?>">
                        </form>
                    </div>
                </div>

                <!-- Footer del modal con botones -->
                <div class="modal-footer bg-light border-0">
                    <div class="d-flex justify-content-between w-100 align-items-center">
                        <!-- Información del paso actual -->
                        <div class="step-info">
                            <small class="text-muted">
                                Paso <span id="pasoActualNumber">1</span> de 6
                            </small>
                        </div>

                        <!-- Botones de navegación -->
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-secondary" id="btnAnterior" style="display: none;">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                            <button type="button" class="btn btn-primary" id="btnSiguiente">
                                Siguiente <i class="fas fa-chevron-right"></i>
                            </button>
                            <button type="button" class="btn btn-success btn-lg d-none" id="btnConfirmar">
                                <i class="fas fa-calendar-check"></i> Confirmar Cita
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Términos y Condiciones -->
    <div class="modal fade" id="modalTerminos" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-contract"></i> Términos y Condiciones
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="terms-content">
                        <h6>1. Responsabilidades del Paciente</h6>
                        <ul>
                            <li>Llegar puntualmente a la cita médica</li>
                            <li>Completar el triaje digital antes de la consulta</li>
                            <li>Proporcionar información médica veraz y completa</li>
                            <li>Notificar cancelaciones con al menos 24 horas de anticipación</li>
                        </ul>

                        <h6>2. Política de Cancelaciones</h6>
                        <ul>
                            <li>Las cancelaciones deben realizarse con 24 horas de anticipación</li>
                            <li>Cancelaciones tardías pueden generar cargos</li>
                            <li>No presentarse a la cita sin justificación puede afectar futuras reservas</li>
                        </ul>

                        <h6>3. Protección de Datos</h6>
                        <ul>
                            <li>Sus datos médicos están protegidos bajo estricta confidencialidad</li>
                            <li>Solo personal autorizado tiene acceso a su información</li>
                            <li>Cumplimos con todas las normativas de protección de datos</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                        <i class="fas fa-check"></i> Entendido
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<style>
    /* ================================
       ESTILOS GENERALES MEJORADOS
    ================================ */
    .container-fluid {
        max-width: 1400px;
    }

    /* ================================
       CALENDARIO MEJORADO
    ================================ */
    .calendar-table {
        margin-bottom: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .calendar-table th {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        font-weight: 600;
        text-align: center;
        padding: 15px 8px;
        border: 1px solid #dee2e6;
        font-size: 0.875rem;
        color: #495057;
        position: relative;
    }

    .calendar-table td {
        height: 60px;
        width: 14.28%;
        text-align: center;
        vertical-align: middle;
        border: 1px solid #dee2e6;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        font-weight: 500;
        background: #fff;
    }

    .calendar-table td:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,123,255,0.15);
        z-index: 10;
    }

    .calendar-table td.available {
        background: linear-gradient(135deg, #fff 0%, #f8f9ff 100%);
        color: #333;
        border-color: #e3f2fd;
    }

    .calendar-table td.available:hover {
        background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
        border-color: #2196f3;
        color: #1976d2;
    }

    .calendar-table td.disabled {
        background: linear-gradient(135deg, #f5f5f5 0%, #eeeeee 100%);
        color: #999;
        cursor: not-allowed;
        opacity: 0.6;
    }

    .calendar-table td.disabled:hover {
        transform: none;
        box-shadow: none;
    }

    .calendar-table td.selected {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        color: white;
        font-weight: bold;
        border-color: #0056b3;
        transform: scale(1.05);
        box-shadow: 0 6px 20px rgba(0,123,255,0.3);
        z-index: 15;
    }

    .calendar-table td.other-month {
        color: #ccc;
        background: #fafafa;
        opacity: 0.5;
    }

    .calendar-table td.today {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        border: 2px solid #ffc107;
        font-weight: bold;
        color: #856404;
    }

    .calendar-table td.today:hover {
        background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
    }

    /* ================================
       LEYENDA Y GUÍAS
    ================================ */
    .legend-box {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        display: inline-block;
        border: 2px solid;
        position: relative;
    }

    .legend-box.available {
        background: linear-gradient(135deg, #fff 0%, #f8f9ff 100%);
        border-color: #e3f2fd;
    }

    .legend-box.today {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        border-color: #ffc107;
    }

    .legend-box.disabled {
        background: linear-gradient(135deg, #f5f5f5 0%, #eeeeee 100%);
        border-color: #999;
    }

    .legend-box.selected {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        border-color: #0056b3;
    }

    .step-number, .step-guide-item .step-number {
        width: 32px;
        height: 32px;
        font-size: 0.875rem;
        font-weight: 600;
        flex-shrink: 0;
    }

    /* ================================
       MODAL MEJORADO
    ================================ */
    .modal-xl {
        max-width: 1200px;
    }

    .modal-content {
        border-radius: 15px;
        overflow: hidden;
    }

    .modal-header {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        border: none;
        padding: 1.5rem;
    }

    .modal-icon {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* ================================
       INDICADOR DE PASOS AVANZADO
    ================================ */
    .steps-indicator {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        position: relative;
        overflow: hidden;
    }

    .steps-indicator::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, #007bff 0%, #28a745 100%);
    }

    .step-item {
        position: relative;
        opacity: 0.5;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        padding: 0 10px;
    }

    .step-item.active {
        opacity: 1;
        transform: scale(1.05);
    }

    .step-item.completed {
        opacity: 1;
    }

    .step-circle {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 8px;
        font-weight: bold;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        border: 3px solid transparent;
        font-size: 1rem;
    }

    .step-item.active .step-circle {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        transform: scale(1.1);
        border-color: rgba(0,123,255,0.3);
        box-shadow: 0 0 0 8px rgba(0,123,255,0.1);
    }

    .step-item.completed .step-circle {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        border-color: rgba(40,167,69,0.3);
    }

    .step-label {
        font-weight: 500;
        color: #6c757d;
        transition: color 0.3s ease;
    }

    .step-item.active .step-label,
    .step-item.completed .step-label {
        color: #495057;
        font-weight: 600;
    }

    .step-line {
        position: absolute;
        top: 22px;
        left: 70%;
        width: 60%;
        height: 2px;
        background: #dee2e6;
        transition: background 0.3s ease;
    }

    .step-item.completed .step-line {
        background: linear-gradient(90deg, #28a745 0%, #007bff 100%);
    }

    .step-item:last-child .step-line {
        display: none;
    }

    /* Barra de progreso */
    .progress {
        background: rgba(255,255,255,0.3);
        border-radius: 10px;
        overflow: hidden;
    }

    .progress-bar {
        background: linear-gradient(90deg, #28a745 0%, #007bff 50%, #6f42c1 100%);
        transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        border-radius: 10px;
    }

    /* ================================
       CARDS DE SELECCIÓN MEJORADAS
    ================================ */
    .tipo-cita-card, .especialidad-card, .sucursal-card {
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 2px solid #e9ecef;
        border-radius: 12px;
        height: 100%;
        overflow: hidden;
        position: relative;
    }

    .tipo-cita-card:hover, .especialidad-card:hover, .sucursal-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 35px rgba(0,0,0,0.1);
        border-color: #007bff;
    }

    .tipo-cita-card.selected, .especialidad-card.selected, .sucursal-card.selected {
        border-color: #007bff;
        background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(0,123,255,0.2);
    }

    .tipo-cita-card.selected::before,
    .especialidad-card.selected::before,
    .sucursal-card.selected::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #007bff 0%, #28a745 100%);
    }

    .especialidad-card.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f8f9fa;
    }

    .especialidad-card.disabled:hover {
        transform: none;
        box-shadow: none;
        border-color: #e9ecef;
    }

    .tipo-icon {
        transition: transform 0.3s ease;
    }

    .tipo-cita-card:hover .tipo-icon,
    .especialidad-card:hover .tipo-icon {
        transform: scale(1.1);
    }

    /* ================================
       FORMULARIOS MEJORADOS
    ================================ */
    .form-control-lg, .form-select-lg {
        border-radius: 10px;
        border: 2px solid #e9ecef;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
        font-size: 1rem;
    }

    .form-control-lg {
        border-radius: 10px;
        border: 2px solid #e9ecef;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
        font-size: 1rem;
    }

    .form-label.fw-semibold {
        color: #495057;
        margin-bottom: 0.75rem;
        font-size: 0.95rem;
    }

    .form-control-lg:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.15);
        transform: translateY(-1px);
    }
    .form-control-lg.is-valid {
        border-color: #28a745;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.04-.04L4.46 4.57 3.43 3.54a.75.75 0 1 0-1.06 1.06l.04.04-.04.04v.01.01l-.05.05v.01.01l-.05.05v.01.01l-.06.05v.01.01l-.06.05v.01.01l-.06.04v.01.01l-.07.04v.01.01l-.07.04v.01.01l-.07.03v.01.01l-.08.03v.01.01l-.08.02v.01.01l-.08.02v.01.01l-.09.01h-.01.01l-.09.01h-.01.01l-.09-.01h-.01.01l-.09-.01h-.01.01l-.08-.02v-.01.01l-.08-.02v-.01.01l-.08-.03v-.01.01l-.07-.03v-.01.01l-.07-.04v-.01.01l-.07-.04v-.01.01l-.06-.04v-.01.01l-.06-.05v-.01.01l-.06-.05v-.01.01l-.05-.05v-.01.01l-.05-.05v-.01.01l-.04-.04 1.4-1.4a.75.75 0 0 1 1.06 0z'/%3e%3c/svg%3e");
    }
    .form-control-lg.is-valid {
        border-color: #28a745;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.04-.04L4.46 4.57 3.43 3.54a.75.75 0 1 0-1.06 1.06l.04.04-.04.04v.01.01l-.05.05v.01.01l-.05.05v.01.01l-.06.05v.01.01l-.06.05v.01.01l-.06.04v.01.01l-.07.04v.01.01l-.07.04v.01.01l-.07.03v.01.01l-.08.03v.01.01l-.08.02v.01.01l-.08.02v.01.01l-.09.01h-.01.01l-.09.01h-.01.01l-.09-.01h-.01.01l-.09-.01h-.01.01l-.08-.02v-.01.01l-.08-.02v-.01.01l-.08-.03v-.01.01l-.07-.03v-.01.01l-.07-.04v-.01.01l-.07-.04v-.01.01l-.06-.04v-.01.01l-.06-.05v-.01.01l-.06-.05v-.01.01l-.05-.05v-.01.01l-.05-.05v-.01.01l-.04-.04 1.4-1.4a.75.75 0 0 1 1.06 0z'/%3e%3c/svg%3e");
    }


    /* ================================
       ALERTS Y NOTIFICACIONES
    ================================ */
    .alert {
        border-radius: 12px;
        border: none;
        padding: 1.25rem;
    }

    .alert-warning {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        color: #856404;
    }

    .alert-success {
        background: linear-gradient(135deg, #d4edda 0%, #a3e9a4 100%);
        color: #155724;
    }

    .alert-info {
        background: linear-gradient(135deg, #d1ecf1 0%, #a8e6f0 100%);
        color: #0c5460;
    }

    /* ================================
       BÚSQUEDA DE CÉDULA
    ================================ */
    #cedulaSugerencias {
        max-height: 300px;
        overflow-y: auto;
        border: 2px solid #007bff;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        z-index: 1050;
    }

    .sugerencia-item {
        padding: 12px 16px;
        cursor: pointer;
        transition: background 0.2s ease;
        border-bottom: 1px solid #f8f9fa;
    }

    .sugerencia-item:hover {
        background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
    }

    .sugerencia-item:last-child {
        border-bottom: none;
    }

    /* Estilos para dropdown de sugerencias - FORZAR VISIBILIDAD */
    .sugerencias-dropdown {
        position: absolute !important;
        top: calc(100% + 2px) !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        max-height: 400px !important;
        overflow-y: auto !important;
        background: white !important;
        border: 2px solid #007bff !important;
        border-radius: 10px !important;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;
        z-index: 9999 !important;
        opacity: 0;
        transform: translateY(-10px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: none;
        visibility: hidden;
    }
    /* Cuando se muestra */
    .sugerencias-dropdown.show {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
        transform: translateY(0) !important;
    }

    .sugerencias-dropdown[style*="display: block"] {
        display: block !important;
        visibility: visible !important;
        opacity: 1 !important;
    }

    .sugerencias-dropdown .dropdown-header {
        background: #f8f9fa !important;
        padding: 8px 16px !important;
        font-size: 0.875rem !important;
        font-weight: 600 !important;
        color: #495057 !important;
        border-bottom: 1px solid #dee2e6 !important;
        margin: 0 !important;
    }

    .sugerencias-dropdown .sugerencia-item {
        padding: 12px 16px !important;
        cursor: pointer !important;
        transition: background 0.2s ease !important;
        border-bottom: 1px solid #f8f9fa !important;
        display: block !important;
        margin: 0 !important;
        background: white !important;
    }

    .sugerencias-dropdown .sugerencia-item:hover {
        background: #e3f2fd !important;
    }

    .sugerencias-dropdown .sugerencia-item:last-child {
        border-bottom: none !important;
        border-radius: 0 0 8px 8px !important;
    }

    .sugerencias-dropdown .avatar-sm {
        width: 36px !important;
        height: 36px !important;
        font-size: 0.8rem !important;
        background: #007bff !important;
        color: white !important;
    }

    .sugerencias-dropdown mark {
        background-color: #fff3cd !important;
        padding: 1px 2px !important;
        border-radius: 2px !important;
    }

    .position-relative {
        position: relative !important;
    }

    /* Asegurar que el input tenga el contenedor relativo */
    .input-group {
        position: relative !important;
    }

    /* Debugging - temporal */
    .sugerencias-dropdown {
        border: 2px solid #007bff !important; /* Cambiar de rojo a azul */
    }

    /* ================================
       BOTONES MEJORADOS
    ================================ */
    .btn {
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border-width: 2px;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    .btn-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        border-color: #007bff;
    }

    .btn-success {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        border-color: #28a745;
    }

    .btn-lg {
        padding: 0.75rem 1.5rem;
        font-size: 1.1rem;
    }

    /* ================================
       ANIMACIONES Y TRANSICIONES
    ================================ */
    .paso-container {
        opacity: 0;
        transform: translateX(30px);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .paso-container:not(.d-none) {
        opacity: 1;
        transform: translateX(0);
    }

    .step-header {
        animation: slideInDown 0.6s ease-out;
    }

    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ================================
       RESPONSIVE DESIGN
    ================================ */
    @media (max-width: 768px) {
        .calendar-table td {
            height: 45px;
            font-size: 14px;
        }

        .calendar-table th {
            padding: 8px 4px;
            font-size: 12px;
        }

        .modal-xl {
            max-width: 95%;
            margin: 10px;
        }

        .step-circle {
            width: 35px;
            height: 35px;
            font-size: 0.875rem;
        }

        .step-item {
            margin-bottom: 15px;
        }

        .tipo-cita-card .fa-3x {
            font-size: 2rem !important;
        }

        .btn-group {
            flex-direction: column;
            width: 100%;
        }

        .btn-group .btn {
            margin-bottom: 0.5rem;
        }
    }

    /* ================================
       UTILIDADES ADICIONALES
    ================================ */
    .avatar-md {
        width: 48px;
        height: 48px;
        font-size: 1.2rem;
        font-weight: 600;
    }

    .paciente-mode {
        transition: all 0.3s ease;
    }

    .text-purple {
        color: #6f42c1 !important;
    }

    .bg-gradient-light {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }

    .border-light {
        border-color: #f8f9fa !important;
    }

    /* Loading states */
    .spinner-border {
        width: 2rem;
        height: 2rem;
    }

    /* Validación de formularios */
    .was-validated .form-control:valid,
    .form-control.is-valid {
        border-color: #28a745;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.04-.04L4.46 4.57 3.43 3.54a.75.75 0 1 0-1.06 1.06l.04.04-.04.04v.01.01l-.05.05v.01.01l-.05.05v.01.01l-.06.05v.01.01l-.06.05v.01.01l-.06.04v.01.01l-.07.04v.01.01l-.07.04v.01.01l-.07.03v.01.01l-.08.03v.01.01l-.08.02v.01.01l-.08.02v.01.01l-.09.01h-.01.01l-.09.01h-.01.01l-.09-.01h-.01.01l-.09-.01h-.01.01l-.08-.02v-.01.01l-.08-.02v-.01.01l-.08-.03v-.01.01l-.07-.03v-.01.01l-.07-.04v-.01.01l-.07-.04v-.01.01l-.06-.04v-.01.01l-.06-.05v-.01.01l-.06-.05v-.01.01l-.05-.05v-.01.01l-.05-.05v-.01.01l-.04-.04 1.4-1.4a.75.75 0 0 1 1.06 0z'/%3e%3c/svg%3e");
    }

    .was-validated .form-control:invalid,
    .form-control.is-invalid {
        border-color: #dc3545;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 4.6 2.4 2.8m-2.4 0 2.4-2.8'/%3e%3c/svg%3e");
    }
</style>

<script>
    /* ================================
     VARIABLES GLOBALES
     ================================ */

    // Variables del calendario
    let fechaActual = new Date();
    let fechaSeleccionada = null;
    let mesActual = fechaActual.getMonth();
    let anioActual = fechaActual.getFullYear();

    // Variables del modal y formulario
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

    // Variables de búsqueda de pacientes
    let timeoutBusqueda = null;
    let pacienteSeleccionadoBd = false;
    let modoConocido = false;

    // Datos de especialidades y sucursales desde PHP
    const especialidades = <?php echo json_encode($especialidades); ?>;
    const sucursales = <?php echo json_encode($sucursales); ?>;
    const esUsuarioPaciente = <?php echo $_SESSION['role_id'] == 4 ? 'true' : 'false'; ?>;
    const datosUsuarioActual = <?php echo $_SESSION['role_id'] == 4 ? json_encode($datosUsuario) : 'null'; ?>;

    // Nombres de meses en español
    const nombresMeses = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    // Configuración de estados de cita según rol
    const estadoCitaSegunRol = {
        1: 'confirmada', // Administrador
        2: 'confirmada', // Recepcionista  
        3: 'agendada', // Médico
        4: 'agendada'     // Paciente
    };

    /* ================================
     INICIALIZACIÓN
     ================================ */

    document.addEventListener('DOMContentLoaded', function () {
        console.log('🚀 Iniciando sistema de agendamiento...');

        // Generar calendario inicial
        generarCalendario(mesActual, anioActual);

        // Configurar event listeners
        inicializarEventListeners();

        // Configurar búsqueda de cédula para usuarios no pacientes
        if (!esUsuarioPaciente) {
            configurarBusquedaCedula();
        }

        // Configurar modo paciente si aplica
        if (esUsuarioPaciente && datosUsuarioActual) {
            configurarModoPaciente();
        }

        console.log('✅ Sistema inicializado correctamente');
    });

    /* ================================
     EVENT LISTENERS PRINCIPALES
     ================================ */

    function inicializarEventListeners() {
        // Navegación del calendario
        document.getElementById('btnMesAnterior').addEventListener('click', function () {
            navegarMes(-1);
        });

        document.getElementById('btnMesSiguiente').addEventListener('click', function () {
            navegarMes(1);
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

        // Validación en tiempo real del checkbox de términos
        document.getElementById('aceptarTerminos').addEventListener('change', function () {
            const btnConfirmar = document.getElementById('btnConfirmar');
            if (this.checked) {
                btnConfirmar.disabled = false;
                this.classList.remove('is-invalid');
            } else {
                btnConfirmar.disabled = true;
            }
        });

        // Event listeners para los selects de médico y hora
        document.getElementById('medicoSeleccionado').addEventListener('change', manejarCambioMedico);
        document.getElementById('horaSeleccionada').addEventListener('change', manejarCambioHora);
    }

    /* ================================
     CONFIGURACIÓN ESPECÍFICA
     ================================ */

    function configurarModoPaciente() {
        // Pre-cargar datos del paciente actual
        const campos = {
            'nombrePaciente': datosUsuarioActual.nombre || '',
            'apellidoPaciente': datosUsuarioActual.apellido || '',
            'emailPaciente': datosUsuarioActual.email || '',
            'telefonoPaciente': datosUsuarioActual.telefono || '',
            'fechaNacimientoPaciente': datosUsuarioActual.fecha_nacimiento || '',
            'generoPaciente': datosUsuarioActual.genero || '',
            'direccionPaciente': datosUsuarioActual.direccion || '',
            'cedulaPaciente': datosUsuarioActual.cedula || ''
        };

        Object.entries(campos).forEach(([id, valor]) => {
            const elemento = document.getElementById(id);
            if (elemento && valor) {
                elemento.value = valor;
        }
        });

        console.log('👤 Modo paciente configurado con datos pre-cargados');
    }

    function configurarBusquedaCedula() {
        const cedulaInput = document.getElementById('cedulaPaciente');
        if (!cedulaInput)
            return;

        // CORRECCIÓN: Remover estilos por defecto
        cedulaInput.classList.remove('is-valid', 'is-invalid');

        // Búsqueda en tiempo real mientras escribe
        cedulaInput.addEventListener('input', function () {
            const valor = this.value.replace(/\D/g, ''); // Solo números
            this.value = valor;

            // CORRECCIÓN: Limpiar clases de validación al escribir
            this.classList.remove('is-valid', 'is-invalid');

            // Validar y buscar
            validarYBuscarCedula(valor);
        });

        // Configurar dropdown de sugerencias
        document.addEventListener('click', function (e) {
            if (!document.getElementById('cedulaSugerencias').contains(e.target) &&
                    !e.target.closest('#cedulaPaciente')) {
                ocultarSugerencias();
            }
        });

        console.log('🔍 Búsqueda de cédula configurada');
    }

    /* ================================
     NAVEGACIÓN DE MESES
     ================================ */

    function navegarMes(direccion) {
        if (direccion === -1) {
            if (mesActual === 0) {
                mesActual = 11;
                anioActual--;
            } else {
                mesActual--;
            }
        } else {
            if (mesActual === 11) {
                mesActual = 0;
                anioActual++;
            } else {
                mesActual++;
            }
        }

        mostrarCargandoCalendario();
        setTimeout(() => {
            generarCalendario(mesActual, anioActual);
        }, 300);
    }

    function mostrarCargandoCalendario() {
        const calendarBody = document.getElementById('calendar-body');
        const loadingDiv = document.getElementById('calendar-loading');

        calendarBody.style.opacity = '0.5';
        loadingDiv.classList.remove('d-none');

        setTimeout(() => {
            loadingDiv.classList.add('d-none');
            calendarBody.style.opacity = '1';
        }, 500);
    }

    /* ================================
     FUNCIONES DE MODO PACIENTE
     ================================ */

    function cambiarAConocido() {
        modoConocido = true;

        // Mostrar/ocultar secciones
        document.getElementById('pacientePropio').style.display = 'none';
        document.getElementById('pacienteConocido').style.display = 'block';

        // Limpiar campos
        limpiarCamposPaciente();

        // Actualizar campo oculto
        document.getElementById('esParaConocido').value = '1';

        // Habilitar campo de cédula
        const cedulaInput = document.getElementById('cedulaPaciente');
        if (cedulaInput) {
            cedulaInput.disabled = false;
            cedulaInput.required = true;
            cedulaInput.value = '';
            cedulaInput.focus();
        }

        mostrarMensaje('info', 'Modo: Cita para un conocido activado');
        console.log('👥 Cambiado a modo conocido');
    }

    function cambiarAPropio() {
        modoConocido = false;

        // Mostrar/ocultar secciones
        document.getElementById('pacientePropio').style.display = 'block';
        document.getElementById('pacienteConocido').style.display = 'none';

        // Restaurar datos del usuario
        configurarModoPaciente();

        // Actualizar campo oculto
        document.getElementById('esParaConocido').value = '0';

        mostrarMensaje('success', 'Modo: Su propia cita restaurado');
        console.log('👤 Cambiado a modo propio');
    }

    function limpiarCamposPaciente() {
        const campos = [
            'cedulaPaciente', 'nombrePaciente', 'apellidoPaciente',
            'emailPaciente', 'telefonoPaciente', 'fechaNacimientoPaciente',
            'generoPaciente', 'direccionPaciente'
        ];

        campos.forEach(id => {
            const elemento = document.getElementById(id);
            if (elemento) {
                elemento.value = '';
                elemento.classList.remove('is-valid', 'is-invalid');
            }
        });

        // Limpiar estados de búsqueda
        pacienteSeleccionadoBd = false;
        document.getElementById('pacienteDesdeBd').value = '0';
        document.getElementById('idPacienteSeleccionado').value = '';

        // Resetear UI de cédula
        const cedulaStatus = document.getElementById('cedulaStatus');
        const btnConsultarApi = document.getElementById('btnConsultarApi');

        if (cedulaStatus) {
            cedulaStatus.innerHTML = '<i class="fas fa-search text-muted"></i>';
        }
        if (btnConsultarApi) {
            btnConsultarApi.disabled = true;
        }

        ocultarSugerencias();
        console.log('🧹 Campos de paciente limpiados');
    }

    /* ================================
     GENERACIÓN DEL CALENDARIO
     ================================ */

    function generarCalendario(mes, anio) {
        console.log(`📅 Generando calendario para ${nombresMeses[mes]} ${anio}`);

        const primerDia = new Date(anio, mes, 1);
        const ultimoDia = new Date(anio, mes + 1, 0);
        const primerDiaSemana = primerDia.getDay(); // 0 = Domingo
        const diasEnMes = ultimoDia.getDate();
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        // Actualizar header del calendario
        document.getElementById('mesAnio').textContent = `${nombresMeses[mes]} ${anio}`;

        // Limpiar calendario anterior
        const calendarBody = document.getElementById('calendar-body');
        calendarBody.innerHTML = '';

        let fecha = 1;
        let filasGeneradas = 0;

        // Generar máximo 6 semanas
        for (let semana = 0; semana < 6; semana++) {
            const fila = document.createElement('tr');
            let tieneDiasDelMes = false;

            // Generar 7 días por semana
            for (let dia = 0; dia < 7; dia++) {
                const celda = document.createElement('td');

                if (semana === 0 && dia < primerDiaSemana) {
                    // Días del mes anterior
                    const fechaAnterior = new Date(anio, mes - 1, 0).getDate() - (primerDiaSemana - dia - 1);
                    celda.textContent = fechaAnterior;
                    celda.classList.add('other-month', 'disabled');
                    configurarTooltip(celda, 'Mes anterior');

                } else if (fecha > diasEnMes) {
                    // Días del mes siguiente
                    const fechaSiguiente = fecha - diasEnMes;
                    celda.textContent = fechaSiguiente;
                    celda.classList.add('other-month', 'disabled');
                    configurarTooltip(celda, 'Mes siguiente');
                    fecha++;

                } else {
                    // Días del mes actual
                    celda.textContent = fecha;
                    tieneDiasDelMes = true;

                    const fechaCelda = new Date(anio, mes, fecha);
                    const esPasado = fechaCelda < hoy;
                    const esHoy = fechaCelda.getTime() === hoy.getTime();
                    const esFuturo = fechaCelda > hoy;

                    // Aplicar clases y configuraciones según el tipo de fecha
                    if (esPasado) {
                        celda.classList.add('disabled');
                        configurarTooltip(celda, 'Fecha no disponible');

                    } else if (esHoy) {
                        celda.classList.add('today', 'available');
                        configurarTooltip(celda, 'Hoy - Click para agendar');
                        configurarEventoClick(celda, anio, mes, fecha);

                    } else if (esFuturo) {
                        celda.classList.add('available');

                        // Verificar si es fin de semana (opcional: deshabilitarlo)
                        const diaSemana = fechaCelda.getDay();
                        if (diaSemana === 0 || diaSemana === 6) {
                            configurarTooltip(celda, 'Fin de semana - Click para agendar');
                        } else {
                            configurarTooltip(celda, 'Click para agendar cita');
                        }

                        configurarEventoClick(celda, anio, mes, fecha);
                    }

                    fecha++;
                }

                fila.appendChild(celda);
            }

            calendarBody.appendChild(fila);
            filasGeneradas++;

            // Optimización: si no hay más días del mes actual, salir del loop
            if (fecha > diasEnMes && !tieneDiasDelMes) {
                break;
            }
        }

        // Aplicar animación de entrada
        aplicarAnimacionCalendario();

        console.log(`✅ Calendario generado: ${filasGeneradas} filas`);
    }

    function configurarTooltip(elemento, texto) {
        elemento.setAttribute('title', texto);
        elemento.setAttribute('data-bs-toggle', 'tooltip');
        elemento.setAttribute('data-bs-placement', 'top');
    }

    function configurarEventoClick(celda, anio, mes, fecha) {
        // Crear closure para capturar correctamente las variables
        celda.addEventListener('click', function (event) {
            event.preventDefault();
            seleccionarFecha(anio, mes, fecha);
        });

        // Efecto hover mejorado
        celda.addEventListener('mouseenter', function () {
            if (!this.classList.contains('disabled')) {
                this.style.transform = 'translateY(-3px) scale(1.05)';
                this.style.transition = 'all 0.2s cubic-bezier(0.4, 0, 0.2, 1)';
            }
        });

        celda.addEventListener('mouseleave', function () {
            if (!this.classList.contains('selected')) {
                this.style.transform = '';
            }
        });
    }

    function aplicarAnimacionCalendario() {
        const celdas = document.querySelectorAll('.calendar-table td');
        celdas.forEach((celda, index) => {
            celda.style.opacity = '0';
            celda.style.transform = 'scale(0.8)';

            setTimeout(() => {
                celda.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                celda.style.opacity = '1';
                celda.style.transform = 'scale(1)';
            }, index * 20); // Efecto cascada
        });
    }

    /* ================================
     SELECCIÓN DE FECHA
     ================================ */

    function seleccionarFecha(anio, mes, dia) {
        console.log(`📅 Fecha seleccionada: ${dia}/${mes + 1}/${anio}`);

        // Validar que la fecha sea válida
        const fechaSeleccionadaTemp = new Date(anio, mes, dia);
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        if (fechaSeleccionadaTemp < hoy) {
            mostrarMensaje('warning', 'No puede seleccionar fechas pasadas');
            return;
        }

        // Remover selección anterior con animación
        const celdaAnterior = document.querySelector('.calendar-table td.selected');
        if (celdaAnterior) {
            celdaAnterior.classList.remove('selected');
            celdaAnterior.style.transform = '';
        }

        // Seleccionar nueva fecha
        const celdas = document.querySelectorAll('.calendar-table td.available');
        let celdaSeleccionada = null;

        celdas.forEach(celda => {
            if (celda.textContent == dia &&
                    !celda.classList.contains('other-month') &&
                    !celda.classList.contains('disabled')) {
                celda.classList.add('selected');
                celdaSeleccionada = celda;

                // Animación de selección
                celda.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    celda.style.transform = 'scale(1.05)';
                }, 200);
            }
        });

        if (!celdaSeleccionada) {
            mostrarMensaje('error', 'Error al seleccionar la fecha');
            return;
        }

        // Guardar fecha seleccionada
        fechaSeleccionada = new Date(anio, mes, dia);
        datosFormulario.fecha_cita = fechaSeleccionada;

        // Formatear fecha para mostrar
        const fechaFormateada = `${dia}/${mes + 1}/${anio}`;
        const fechaCompleta = fechaSeleccionada.toLocaleDateString('es-ES', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });

        // Actualizar título del modal
        document.getElementById('fechaModalTitulo').textContent = fechaFormateada;

        // Formatear fecha para MySQL (YYYY-MM-DD)
        const mesFormatted = String(mes + 1).padStart(2, '0');
        const diaFormatted = String(dia).padStart(2, '0');
        document.getElementById('fechaCita').value = `${anio}-${mesFormatted}-${diaFormatted}`;

        // Mostrar mensaje de confirmación
        mostrarMensaje('success', `Fecha seleccionada: ${fechaCompleta}`);

        // Preparar y abrir modal con animación
        setTimeout(() => {
            abrirModalAgendamiento();
        }, 500);
    }

    /* ================================
     GESTIÓN DEL MODAL
     ================================ */

    function abrirModalAgendamiento() {
        console.log('🔄 Abriendo modal de agendamiento...');

        // Resetear estado del modal
        pasoActual = 1;
        actualizarIndicadorPasos();
        actualizarBarraProgreso();
        mostrarPaso(1);

        // Cargar contenido del primer paso
        cargarTiposCita();

        // Abrir modal con configuración Bootstrap
        const modalElement = document.getElementById('modalAgendamiento');
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false,
            focus: true
        });

        // Animación de entrada
        modalElement.addEventListener('shown.bs.modal', function () {
            document.querySelector('.modal-content').style.animation = 'slideInDown 0.5s ease-out';
        }, {once: true});

        modal.show();

        console.log('✅ Modal abierto correctamente');
    }

    function resetearModal() {
        console.log('🔄 Reseteando modal...');

        // Resetear variables
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

        // Remover todas las selecciones
        document.querySelectorAll('.selected').forEach(elemento => {
            elemento.classList.remove('selected');
        });

        // Limpiar validaciones
        document.querySelectorAll('.is-valid, .is-invalid').forEach(elemento => {
            elemento.classList.remove('is-valid', 'is-invalid');
        });

        // Resetear campos específicos
        document.getElementById('aceptarTerminos').checked = false;
        document.getElementById('btnConfirmar').disabled = true;

        // Limpiar campos de paciente si es necesario
        if (esUsuarioPaciente && modoConocido) {
            cambiarAPropio();
        } else if (!esUsuarioPaciente) {
            limpiarCamposPaciente();
        }

        // Resetear indicadores visuales
        actualizarIndicadorPasos();
        actualizarBarraProgreso();
        mostrarPaso(1);

        console.log('✅ Modal reseteado completamente');
    }
    /* ================================
     NAVEGACIÓN DE PASOS
     ================================ */

    function siguientePaso() {
        if (pasoActual < totalPasos) {
            console.log(`➡️ Avanzando del paso ${pasoActual} al ${pasoActual + 1}`);

            // Marcar paso actual como completado
            document.getElementById(`step${pasoActual}`).classList.add('completed');

            pasoActual++;
            actualizarIndicadorPasos();
            actualizarBarraProgreso();
            mostrarPaso(pasoActual);

            // Scroll suave al inicio del modal
            document.querySelector('.modal-body').scrollTop = 0;
        }
    }

    function anteriorPaso() {
        if (pasoActual > 1) {
            console.log(`⬅️ Retrocediendo del paso ${pasoActual} al ${pasoActual - 1}`);

            // Remover completado del paso actual
            document.getElementById(`step${pasoActual}`).classList.remove('completed');

            pasoActual--;
            actualizarIndicadorPasos();
            actualizarBarraProgreso();
            mostrarPaso(pasoActual);

            // Scroll suave al inicio del modal
            document.querySelector('.modal-body').scrollTop = 0;
        }
    }

    function mostrarPaso(numeroPaso) {
        console.log(`👁️ Mostrando paso ${numeroPaso}`);

        // Ocultar todos los pasos con animación
        document.querySelectorAll('.paso-container').forEach((paso, index) => {
            if (!paso.classList.contains('d-none')) {
                paso.style.opacity = '0';
                paso.style.transform = 'translateX(-30px)';

                setTimeout(() => {
                    paso.classList.add('d-none');
                }, 200);
            }
        });

        // Mostrar paso actual con animación
        setTimeout(() => {
            const pasoActualElement = document.getElementById(`paso${numeroPaso}`);
            pasoActualElement.classList.remove('d-none');

            // Forzar reflow para la animación
            pasoActualElement.offsetHeight;

            pasoActualElement.style.opacity = '1';
            pasoActualElement.style.transform = 'translateX(0)';
        }, 250);

        // Actualizar botones
        actualizarBotones();

        // Actualizar número de paso
        document.getElementById('pasoActualNumber').textContent = numeroPaso;

        // Cargar contenido específico del paso
        setTimeout(() => {
            switch (numeroPaso) {
                case 1:
                    cargarTiposCita();
                    break;
                case 2:
                    cargarEspecialidades();
                    break;
                case 3:
                    cargarSucursales();
                    break;
                case 4:
                    cargarMedicosYHorarios();
                    break;
                case 5:
                    // Los datos del paciente ya están configurados
                    break;
                case 6:
                    generarResumen();
                    break;
            }
        }, 300);
    }

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

    function actualizarBarraProgreso() {
        const progreso = (pasoActual / totalPasos) * 100;
        const progressBar = document.getElementById('progressBar');

        progressBar.style.width = `${progreso}%`;
        progressBar.setAttribute('aria-valuenow', progreso);

        // Animación suave
        progressBar.style.transition = 'width 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
    }

    function actualizarBotones() {
        const btnAnterior = document.getElementById('btnAnterior');
        const btnSiguiente = document.getElementById('btnSiguiente');
        const btnConfirmar = document.getElementById('btnConfirmar');

        // Botón Anterior
        btnAnterior.style.display = pasoActual > 1 ? 'inline-block' : 'none';

        // Botones Siguiente/Confirmar
        if (pasoActual < totalPasos) {
            btnSiguiente.style.display = 'inline-block';
            btnConfirmar.classList.add('d-none');
        } else {
            btnSiguiente.style.display = 'none';
            btnConfirmar.classList.remove('d-none');

            // Verificar checkbox de términos
            const terminos = document.getElementById('aceptarTerminos');
            btnConfirmar.disabled = !terminos.checked;
        }
    }

    /* ================================
     VALIDACIONES POR PASO
     ================================ */

    function validarPasoActual() {
        console.log(`🔍 Validando paso ${pasoActual}...`);

        switch (pasoActual) {
            case 1:
                return validarTipoCita();
            case 2:
                return validarEspecialidad();
            case 3:
                return validarSucursal();
            case 4:
                return validarMedicoYHora();
            case 5:
                return validarDatosPaciente();
            case 6:
                return validarTerminos();
            default:
                return false;
        }
    }

    function validarTipoCita() {
        if (!datosFormulario.tipo_cita) {
            mostrarMensaje('warning', '⚠️ Por favor seleccione el tipo de cita (Presencial o Virtual)');
            resaltarError('.tipo-cita-card');
            return false;
        }

        console.log('✅ Tipo de cita válido:', datosFormulario.tipo_cita);
        return true;
    }

    function validarEspecialidad() {
        if (!datosFormulario.id_especialidad) {
            mostrarMensaje('warning', '⚠️ Por favor seleccione una especialidad médica');
            resaltarError('.especialidad-card');
            return false;
        }

        console.log('✅ Especialidad válida:', datosFormulario.id_especialidad);
        return true;
    }

    function validarSucursal() {
        if (!datosFormulario.id_sucursal) {
            mostrarMensaje('warning', '⚠️ Por favor seleccione una sucursal');
            resaltarError('.sucursal-card');
            return false;
        }

        console.log('✅ Sucursal válida:', datosFormulario.id_sucursal);
        return true;
    }

    function validarMedicoYHora() {
        if (!datosFormulario.id_medico) {
            mostrarMensaje('warning', '⚠️ Por favor seleccione un médico');
            document.getElementById('medicoSeleccionado').focus();
            return false;
        }

        if (!datosFormulario.hora_cita) {
            mostrarMensaje('warning', '⚠️ Por favor seleccione un horario disponible');
            document.getElementById('horaSeleccionada').focus();
            return false;
        }

        console.log('✅ Médico y hora válidos:', {
            medico: datosFormulario.id_medico,
            hora: datosFormulario.hora_cita
        });
        return true;
    }

    function validarDatosPaciente() {
        const form = document.getElementById('formAgendamiento');
        const formData = new FormData(form);

        // Campos obligatorios
        const camposObligatorios = [
            {campo: 'nombre_paciente', nombre: 'Nombre'},
            {campo: 'apellido_paciente', nombre: 'Apellido'},
            {campo: 'email_paciente', nombre: 'Email'},
            {campo: 'motivo_consulta', nombre: 'Motivo de consulta'}
        ];

        // Si no es paciente, también validar cédula
        if (!esUsuarioPaciente || modoConocido) {
            camposObligatorios.unshift({campo: 'cedula_paciente', nombre: 'Cédula'});
        }

        // Validar campos obligatorios
        for (const {campo, nombre} of camposObligatorios) {
            const valor = formData.get(campo);
            if (!valor || valor.trim() === '') {
                mostrarMensaje('warning', `⚠️ El campo "${nombre}" es obligatorio`);
                document.getElementById(campo.replace('_paciente', 'Paciente').replace('_consulta', 'Consulta')).focus();
                return false;
            }
        }

        // Validar formato de email
        const email = formData.get('email_paciente');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            mostrarMensaje('warning', '⚠️ Por favor ingrese un email válido');
            document.getElementById('emailPaciente').focus();
            return false;
        }

        // Validar cédula si aplica
        if (!esUsuarioPaciente || modoConocido) {
            const cedula = formData.get('cedula_paciente');
            if (cedula && !validarCedulaEcuatoriana(cedula)) {
                mostrarMensaje('warning', '⚠️ La cédula ingresada no es válida');
                document.getElementById('cedulaPaciente').focus();
                return false;
            }
        }

        console.log('✅ Datos del paciente válidos');
        return true;
    }

    function validarTerminos() {
        const terminos = document.getElementById('aceptarTerminos');
        if (!terminos.checked) {
            mostrarMensaje('warning', '⚠️ Debe aceptar los términos y condiciones para continuar');
            terminos.focus();
            terminos.classList.add('is-invalid');
            return false;
        }

        terminos.classList.remove('is-invalid');
        console.log('✅ Términos aceptados');
        return true;
    }

    /* ================================
     FUNCIONES DE APOYO PARA VALIDACIÓN
     ================================ */

    function resaltarError(selector) {
        const elementos = document.querySelectorAll(selector);
        elementos.forEach(elemento => {
            elemento.style.border = '2px solid #dc3545';
            elemento.style.animation = 'shake 0.5s ease-in-out';

            setTimeout(() => {
                elemento.style.border = '';
                elemento.style.animation = '';
            }, 2000);
        });
    }

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

    /* ================================
     FUNCIÓN DE MENSAJES MEJORADA
     ================================ */

    function mostrarMensaje(tipo, mensaje) {
        const container = document.getElementById('mensajes-container');
        const tiposIconos = {
            'success': 'check-circle',
            'warning': 'exclamation-triangle',
            'danger': 'times-circle',
            'info': 'info-circle'
        };

        const icono = tiposIconos[tipo] || 'info-circle';

        const alert = document.createElement('div');
        alert.className = `alert alert-${tipo} alert-dismissible fade show border-0 shadow-sm`;
        alert.style.animation = 'slideInDown 0.5s ease-out';
        alert.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${icono} fa-lg me-3"></i>
                <div class="flex-grow-1">
                    ${mensaje}
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        // Remover mensajes anteriores
        container.innerHTML = '';
        container.appendChild(alert);

        // Auto-remover después de 5 segundos
        setTimeout(() => {
            if (alert.parentNode) {
                alert.style.animation = 'slideOutUp 0.5s ease-in';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 500);
            }
        }, 5000);

        console.log(`📢 Mensaje mostrado [${tipo}]:`, mensaje);
    }

    /* ================================
     ANIMACIONES CSS ADICIONALES
     ================================ */

    // Agregar keyframes para animaciones
    const style = document.createElement('style');
    style.textContent = `
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideOutUp {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
    `;
    document.head.appendChild(style);

    /* ================================
     PASO 1: TIPOS DE CITA
     ================================ */

    function cargarTiposCita() {
        console.log('🔄 Cargando tipos de cita...');

        const cards = document.querySelectorAll('.tipo-cita-card');

        // Limpiar selecciones anteriores
        cards.forEach(card => {
            card.classList.remove('selected');
            card.style.transform = '';
        });

        // Configurar event listeners
        cards.forEach((card, index) => {
            // Animación de entrada escalonada
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';

            setTimeout(() => {
                card.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 150);

            // Event listener para selección
            card.addEventListener('click', function () {
                seleccionarTipoCita(this);
            });
        });

        console.log('✅ Tipos de cita cargados');
    }

    function seleccionarTipoCita(cardSeleccionada) {
        const tipo = cardSeleccionada.getAttribute('data-tipo');
        console.log(`🎯 Tipo de cita seleccionado: ${tipo}`);

        // Remover selección anterior
        document.querySelectorAll('.tipo-cita-card').forEach(card => {
            card.classList.remove('selected');
            card.style.transform = '';
        });

        // Seleccionar nueva opción con animación
        cardSeleccionada.classList.add('selected');
        cardSeleccionada.style.transform = 'translateY(-8px) scale(1.02)';

        // Guardar en datos del formulario
        datosFormulario.tipo_cita = tipo;
        document.getElementById('tipoCita').value = tipo;

        // Feedback visual
        const tipoTexto = tipo === 'virtual' ? 'Virtual' : 'Presencial';
        mostrarMensaje('success', `✅ Tipo de cita seleccionado: ${tipoTexto}`);
    }

    /* ================================
     PASO 2: ESPECIALIDADES
     ================================ */

    function cargarEspecialidades() {
        console.log('🔄 Cargando especialidades...');

        const container = document.getElementById('especialidadesContainer');
        const loadingDiv = document.getElementById('especialidadesLoading');

        // Mostrar loading
        container.innerHTML = '';
        loadingDiv.classList.remove('d-none');

        setTimeout(() => {
            loadingDiv.classList.add('d-none');
            generarEspecialidades();
        }, 500);
    }

    function generarEspecialidades() {
        const container = document.getElementById('especialidadesContainer');
        const tipoSeleccionado = datosFormulario.tipo_cita;

        if (!tipoSeleccionado) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Primero debe seleccionar el tipo de cita
                    </div>
                </div>
            `;
            return;
        }

        container.innerHTML = '';
        let especialidadesDisponibles = 0;

        especialidades.forEach((esp, index) => {
            // Verificar disponibilidad según tipo de cita
            const permiteVirtual = esp.permite_virtual == 1;
            const permitePresencial = esp.permite_presencial == 1;
            const disponible = (tipoSeleccionado === 'virtual' && permiteVirtual) ||
                    (tipoSeleccionado === 'presencial' && permitePresencial);

            if (disponible)
                especialidadesDisponibles++;

            const col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4 mb-3';

            const disponibilidadInfo = disponible ? '' :
                    `<div class="mt-2">
                    <span class="badge bg-danger">
                        <i class="fas fa-times"></i> No disponible para cita ${tipoSeleccionado}
                    </span>
                </div>`;

            col.innerHTML = `
                <div class="card especialidad-card h-100 ${disponible ? '' : 'disabled'}" 
                     data-especialidad="${esp.id_especialidad}">
                    <div class="card-body text-center p-3">
                        <div class="especialidad-icon mb-3">
                            <i class="fas fa-user-md fa-2x ${disponible ? 'text-primary' : 'text-muted'}"></i>
                        </div>
                        <h6 class="card-title">${esp.nombre_especialidad}</h6>
                        <p class="card-text text-muted small">
                            ${esp.descripcion || 'Atención médica especializada'}
                        </p>
                        ${esp.duracion_cita_minutos ?
                    `<small class="text-info">
                                <i class="fas fa-clock"></i> ${esp.duracion_cita_minutos} min
                            </small>` : ''
                    }
                        ${disponibilidadInfo}
                    </div>
                </div>
            `;

            container.appendChild(col);

            // Animación de entrada
            const card = col.querySelector('.especialidad-card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px) scale(0.9)';

            setTimeout(() => {
                card.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0) scale(1)';
            }, index * 100);

            // Event listener solo si está disponible
            if (disponible) {
                card.addEventListener('click', function () {
                    seleccionarEspecialidad(this);
                });
            }
        });

        // Mensaje si no hay especialidades disponibles
        if (especialidadesDisponibles === 0) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                        <h6>No hay especialidades disponibles</h6>
                        <p class="mb-0">No se encontraron especialidades que ofrezcan citas ${tipoSeleccionado}es.</p>
                        <button class="btn btn-outline-warning mt-2" onclick="anteriorPaso()">
                            <i class="fas fa-arrow-left"></i> Cambiar tipo de cita
                        </button>
                    </div>
                </div>
            `;
        }

        console.log(`✅ Especialidades generadas: ${especialidadesDisponibles} disponibles de ${especialidades.length} totales`);
    }

    function seleccionarEspecialidad(cardSeleccionada) {
        const idEspecialidad = cardSeleccionada.getAttribute('data-especialidad');
        const especialidadData = especialidades.find(e => e.id_especialidad == idEspecialidad);

        console.log(`🎯 Especialidad seleccionada: ${especialidadData.nombre_especialidad}`);

        // Remover selección anterior
        document.querySelectorAll('.especialidad-card').forEach(card => {
            card.classList.remove('selected');
            card.style.transform = '';
        });

        // Seleccionar nueva opción
        cardSeleccionada.classList.add('selected');
        cardSeleccionada.style.transform = 'translateY(-8px) scale(1.05)';

        // Guardar en datos del formulario
        datosFormulario.id_especialidad = idEspecialidad;
        document.getElementById('especialidadSeleccionada').value = idEspecialidad;

        // Feedback visual
        mostrarMensaje('success', `✅ Especialidad seleccionada: ${especialidadData.nombre_especialidad}`);
    }

    /* ================================
     PASO 3: SUCURSALES
     ================================ */

    function cargarSucursales() {
        console.log('🔄 Cargando sucursales...');

        const container = document.getElementById('sucursalesContainer');
        const loadingDiv = document.getElementById('sucursalesLoading');

        // Mostrar loading
        container.innerHTML = '';
        loadingDiv.classList.remove('d-none');

        setTimeout(() => {
            loadingDiv.classList.add('d-none');
            generarSucursales();
        }, 500);
    }

    function generarSucursales() {
        const container = document.getElementById('sucursalesContainer');

        if (!datosFormulario.id_especialidad) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Primero debe seleccionar una especialidad
                    </div>
                </div>
            `;
            return;
        }

        container.innerHTML = '';

        sucursales.forEach((suc, index) => {
            const col = document.createElement('div');
            col.className = 'col-md-6 col-lg-4 mb-3';

            col.innerHTML = `
                <div class="card sucursal-card h-100" data-sucursal="${suc.id_sucursal}">
                    <div class="card-body p-3">
                        <div class="sucursal-header mb-3">
                            <div class="d-flex align-items-center">
                                <div class="sucursal-icon me-3">
                                    <i class="fas fa-building fa-2x text-primary"></i>
                                </div>
                                <div>
                                    <h6 class="card-title mb-1">${suc.nombre_sucursal}</h6>
                                    <small class="text-muted">
                                        <i class="fas fa-map-marker-alt"></i> ${suc.ciudad || 'Ecuador'}
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="sucursal-info">
                            <p class="text-muted small mb-2">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                ${suc.direccion}
                            </p>
                            
                            ${suc.telefono ?
                    `<p class="mb-1 small">
                                    <i class="fas fa-phone text-success me-1"></i>
                                    <a href="tel:${suc.telefono}" class="text-decoration-none">${suc.telefono}</a>
                                </p>` : ''
                    }
                            
                            ${suc.email ?
                    `<p class="mb-0 small">
                                    <i class="fas fa-envelope text-info me-1"></i>
                                    <a href="mailto:${suc.email}" class="text-decoration-none">${suc.email}</a>
                                </p>` : ''
                    }
                        </div>
                        
                        <div class="sucursal-badge mt-3">
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-check-circle text-success"></i> Disponible
                            </span>
                        </div>
                    </div>
                </div>
            `;

            container.appendChild(col);

            // Animación de entrada
            const card = col.querySelector('.sucursal-card');
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px) scale(0.9)';

            setTimeout(() => {
                card.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0) scale(1)';
            }, index * 120);

            // Event listener
            card.addEventListener('click', function () {
                seleccionarSucursal(this);
            });
        });

        console.log(`✅ Sucursales generadas: ${sucursales.length} disponibles`);
    }

    function seleccionarSucursal(cardSeleccionada) {
        const idSucursal = cardSeleccionada.getAttribute('data-sucursal');
        const sucursalData = sucursales.find(s => s.id_sucursal == idSucursal);

        console.log(`🎯 Sucursal seleccionada: ${sucursalData.nombre_sucursal}`);

        // Remover selección anterior
        document.querySelectorAll('.sucursal-card').forEach(card => {
            card.classList.remove('selected');
            card.style.transform = '';
        });

        // Seleccionar nueva opción
        cardSeleccionada.classList.add('selected');
        cardSeleccionada.style.transform = 'translateY(-8px) scale(1.05)';

        // Guardar en datos del formulario
        datosFormulario.id_sucursal = idSucursal;
        document.getElementById('sucursalSeleccionada').value = idSucursal;

        // Feedback visual
        mostrarMensaje('success', `✅ Sucursal seleccionada: ${sucursalData.nombre_sucursal}`);
    }

    /* ================================
     FUNCIONES DE APOYO
     ================================ */

    function limpiarSelecciones(selector) {
        document.querySelectorAll(selector).forEach(elemento => {
            elemento.classList.remove('selected');
            elemento.style.transform = '';
            elemento.style.border = '';
        });
    }

    function aplicarEfectoHover(elementos) {
        elementos.forEach(elemento => {
            elemento.addEventListener('mouseenter', function () {
                if (!this.classList.contains('disabled') && !this.classList.contains('selected')) {
                    this.style.transform = 'translateY(-4px) scale(1.02)';
                    this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.1)';
                }
            });

            elemento.addEventListener('mouseleave', function () {
                if (!this.classList.contains('selected')) {
                    this.style.transform = '';
                    this.style.boxShadow = '';
                }
            });
        });
    }

    function mostrarCargando(containerId, mensaje = 'Cargando...') {
        const container = document.getElementById(containerId);
        container.innerHTML = `
            <div class="col-12">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">${mensaje}</span>
                    </div>
                    <p class="text-muted">${mensaje}</p>
                </div>
            </div>
        `;
    }

    /* ================================
     PASO 4: MÉDICOS Y HORARIOS
     ================================ */

    function cargarMedicosYHorarios() {
        console.log('🔄 Cargando médicos y horarios...');

        const selectMedico = document.getElementById('medicoSeleccionado');
        const selectHora = document.getElementById('horaSeleccionada');
        const medicoInfo = document.getElementById('medicoInfo');
        const loadingDiv = document.getElementById('medicosLoading');

        // Resetear estados
        selectMedico.innerHTML = '<option value="">Cargando médicos...</option>';
        selectHora.innerHTML = '<option value="">Primero seleccione un médico</option>';
        medicoInfo.classList.add('d-none');
        loadingDiv.classList.remove('d-none');

        // Validar datos previos
        if (!datosFormulario.id_especialidad || !datosFormulario.id_sucursal || !fechaSeleccionada) {
            mostrarMensaje('warning', '⚠️ Faltan datos previos para cargar médicos');
            loadingDiv.classList.add('d-none');
            selectMedico.innerHTML = '<option value="">Error: datos incompletos</option>';
            return;
        }

        // Preparar parámetros para la API
        const params = new URLSearchParams({
            especialidad: datosFormulario.id_especialidad,
            sucursal: datosFormulario.id_sucursal,
            fecha: document.getElementById('fechaCita').value
        });

        console.log('📡 Solicitando médicos con parámetros:', Object.fromEntries(params));

        // Hacer petición AJAX
        fetch(`views/api/obtener-medicos.php?${params}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    loadingDiv.classList.add('d-none');
                    procesarRespuestaMedicos(data);
                })
                .catch(error => {
                    console.error('❌ Error al cargar médicos:', error);
                    loadingDiv.classList.add('d-none');
                    selectMedico.innerHTML = '<option value="">Error al cargar médicos</option>';
                    mostrarMensaje('danger', `❌ Error de conexión: ${error.message}`);
                });
    }

    function procesarRespuestaMedicos(data) {
        const selectMedico = document.getElementById('medicoSeleccionado');

        console.log('📊 Respuesta médicos recibida:', data);

        if (data.success && data.medicos && data.medicos.length > 0) {
            // Limpiar y llenar select de médicos
            selectMedico.innerHTML = '<option value="">Seleccione un médico...</option>';

            data.medicos.forEach(medico => {
                const option = document.createElement('option');
                option.value = medico.id_usuario;
                option.textContent = `Dr. ${medico.nombre} ${medico.apellido}`;
                option.setAttribute('data-email', medico.email);
                selectMedico.appendChild(option);
            });

            // Aplicar animación al select
            selectMedico.style.animation = 'slideInRight 0.5s ease-out';

            mostrarMensaje('success', `✅ ${data.medicos.length} médico(s) disponible(s) encontrado(s)`);

        } else {
            selectMedico.innerHTML = '<option value="">No hay médicos disponibles</option>';

            const mensaje = data.debug ?
                    `No hay médicos disponibles para ${data.debug.dia_nombre || 'este día'}` :
                    'No se encontraron médicos disponibles';

            mostrarMensaje('warning', `⚠️ ${mensaje}`);

            if (data.debug) {
                console.log('🔍 Debug médicos:', data.debug);
            }
        }

        console.log('✅ Procesamiento de médicos completado');
    }

    function manejarCambioMedico() {
        const medicoId = this.value;
        const selectHora = document.getElementById('horaSeleccionada');
        const medicoInfo = document.getElementById('medicoInfo');

        console.log(`👨‍⚕️ Médico seleccionado: ${medicoId}`);

        // Guardar en datos del formulario
        datosFormulario.id_medico = medicoId;

        if (medicoId) {
            // Mostrar información del médico
            const medicoTexto = this.selectedOptions[0].textContent;
            const medicoEmail = this.selectedOptions[0].getAttribute('data-email');

            mostrarInfoMedico(medicoTexto, medicoEmail);

            // Cargar horarios disponibles
            cargarHorariosDisponibles(medicoId);

            mostrarMensaje('info', `👨‍⚕️ Médico seleccionado: ${medicoTexto}`);
        } else {
            // Reset si no hay médico seleccionado
            selectHora.innerHTML = '<option value="">Primero seleccione un médico</option>';
            medicoInfo.classList.add('d-none');
            datosFormulario.hora_cita = null;
        }
    }

    function mostrarInfoMedico(nombre, email) {
        const medicoInfo = document.getElementById('medicoInfo');
        const medicoInfoContent = document.getElementById('medicoInfoContent');

        medicoInfoContent.innerHTML = `
            <div class="row align-items-center">
                <div class="col-auto">
                    <div class="avatar-md bg-primary text-white rounded-circle d-flex align-items-center justify-content-center">
                        ${nombre.split(' ')[1]?.charAt(0) || 'D'}${nombre.split(' ')[2]?.charAt(0) || 'R'}
                    </div>
                </div>
                <div class="col">
                    <h6 class="mb-1">${nombre}</h6>
                    <p class="mb-0 small text-muted">
                        <i class="fas fa-envelope me-1"></i> ${email}
                    </p>
                    <p class="mb-0 small text-success">
                        <i class="fas fa-clock me-1"></i> Cargando horarios disponibles...
                    </p>
                </div>
            </div>
        `;

        medicoInfo.classList.remove('d-none');
        medicoInfo.style.animation = 'slideInUp 0.5s ease-out';
    }

    function cargarHorariosDisponibles(medicoId) {
        const selectHora = document.getElementById('horaSeleccionada');

        console.log('🕐 Cargando horarios para médico:', medicoId);

        // Mostrar loading en select de horas
        selectHora.innerHTML = '<option value="">Cargando horarios...</option>';
        selectHora.disabled = true;

        // Preparar parámetros
        const params = new URLSearchParams({
            medico: medicoId,
            fecha: document.getElementById('fechaCita').value,
            especialidad: datosFormulario.id_especialidad
        });

        console.log('📡 Solicitando horarios con parámetros:', Object.fromEntries(params));

        fetch(`views/api/obtener-horarios.php?${params}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    procesarRespuestaHorarios(data);
                })
                .catch(error => {
                    console.error('❌ Error al cargar horarios:', error);
                    selectHora.innerHTML = '<option value="">Error al cargar horarios</option>';
                    selectHora.disabled = false;
                    mostrarMensaje('danger', `❌ Error al cargar horarios: ${error.message}`);

                    // Actualizar info del médico
                    actualizarInfoMedico('Error al cargar horarios', 'danger');
                });
    }

    function procesarRespuestaHorarios(data) {
        const selectHora = document.getElementById('horaSeleccionada');

        console.log('🕐 Respuesta horarios recibida:', data);

        selectHora.disabled = false;

        if (data.success && data.horarios && data.horarios.length > 0) {
            // Limpiar y llenar select de horarios
            selectHora.innerHTML = '<option value="">Seleccione un horario...</option>';

            data.horarios.forEach(horario => {
                const option = document.createElement('option');
                option.value = horario.hora;
                option.textContent = horario.hora_formateada;
                selectHora.appendChild(option);
            });

            // Aplicar animación
            selectHora.style.animation = 'slideInRight 0.5s ease-out';

            // Actualizar info del médico
            actualizarInfoMedico(`${data.horarios.length} horario(s) disponible(s)`, 'success');

            mostrarMensaje('success', `✅ ${data.horarios.length} horario(s) disponible(s)`);

        } else {
            selectHora.innerHTML = '<option value="">No hay horarios disponibles</option>';

            const mensaje = data.debug ?
                    `No hay horarios disponibles para ${data.debug.dia_nombre || 'este día'}` :
                    'No se encontraron horarios disponibles';

            // Actualizar info del médico
            actualizarInfoMedico('Sin horarios disponibles', 'warning');

            mostrarMensaje('warning', `⚠️ ${mensaje}`);

            if (data.debug) {
                console.log('🔍 Debug horarios:', data.debug);
            }
        }

        console.log('✅ Procesamiento de horarios completado');
    }

    function actualizarInfoMedico(mensaje, tipo = 'info') {
        const medicoInfoContent = document.getElementById('medicoInfoContent');
        if (!medicoInfoContent)
            return;

        const iconos = {
            'success': 'check-circle text-success',
            'warning': 'exclamation-triangle text-warning',
            'danger': 'times-circle text-danger',
            'info': 'info-circle text-info'
        };

        const icono = iconos[tipo] || iconos.info;

        // Actualizar solo la última línea del contenido
        const contenidoActual = medicoInfoContent.innerHTML;
        const lineas = contenidoActual.split('</p>');

        if (lineas.length >= 3) {
            lineas[lineas.length - 2] = `
                    <p class="mb-0 small text-${tipo === 'success' ? 'success' : tipo === 'warning' ? 'warning' : tipo === 'danger' ? 'danger' : 'info'}">
                        <i class="fas fa-${icono.split(' ')[0]} me-1"></i> ${mensaje}
            `;
            medicoInfoContent.innerHTML = lineas.join('</p>');
    }
    }

    function manejarCambioHora() {
        const hora = this.value;
        const horaTexto = this.selectedOptions[0]?.textContent || '';

        console.log(`🕐 Hora seleccionada: ${hora} (${horaTexto})`);

        // Guardar en datos del formulario
        datosFormulario.hora_cita = hora;

        if (hora) {
            mostrarMensaje('success', `✅ Horario seleccionado: ${horaTexto}`);

            // Actualizar info del médico
            actualizarInfoMedico(`Horario confirmado: ${horaTexto}`, 'success');
        }
    }

    /* ================================
     FUNCIONES DE APOYO PARA MÉDICOS Y HORARIOS
     ================================ */

    function validarDisponibilidadMedico(medicoId, fecha, hora) {
        // Esta función podría hacer una validación adicional en tiempo real
        return new Promise((resolve, reject) => {
            const params = new URLSearchParams({
                medico: medicoId,
                fecha: fecha,
                hora: hora,
                accion: 'validar'
            });

            fetch(`views/api/validar-disponibilidad.php?${params}`)
                    .then(response => response.json())
                    .then(data => {
                        resolve(data.disponible);
                    })
                    .catch(error => {
                        console.warn('⚠️ No se pudo validar disponibilidad:', error);
                        resolve(true); // Asumir disponible si hay error
                    });
        });
    }

    function formatearHorario(hora) {
        // Convierte formato 24h a 12h con AM/PM
        const [horas, minutos] = hora.split(':');
        const horasNum = parseInt(horas);
        const periodo = horasNum >= 12 ? 'PM' : 'AM';
        const horas12 = horasNum > 12 ? horasNum - 12 : (horasNum === 0 ? 12 : horasNum);

        return `${horas12}:${minutos} ${periodo}`;
    }

    function limpiarSeleccionesMedicoHora() {
        datosFormulario.id_medico = null;
        datosFormulario.hora_cita = null;

        document.getElementById('medicoSeleccionado').value = '';
        document.getElementById('horaSeleccionada').innerHTML = '<option value="">Primero seleccione un médico</option>';
        document.getElementById('medicoInfo').classList.add('d-none');
    }

    // Agregar animaciones CSS para médicos y horarios
    const estilosMedicosHorarios = document.createElement('style');
    estilosMedicosHorarios.textContent = `
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-select:focus {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,123,255,0.15);
        }
        
        .avatar-md {
            font-size: 1rem;
            font-weight: 600;
        }
    `;
    document.head.appendChild(estilosMedicosHorarios);

    /* ================================
     BÚSQUEDA INTELIGENTE DE CÉDULA
     ================================ */

    function validarYBuscarCedula(cedula) {
        const cedulaStatus = document.getElementById('cedulaStatus');
        const btnConsultarApi = document.getElementById('btnConsultarApi');
        const cedulaInput = document.getElementById('cedulaPaciente');

        // Limpiar timeout anterior
        if (timeoutBusqueda) {
            clearTimeout(timeoutBusqueda);
        }

        // Resetear estado visual
        cedulaInput.classList.remove('is-valid', 'is-invalid');
        pacienteSeleccionadoBd = false;

        if (cedula.length === 0) {
            cedulaStatus.innerHTML = '<i class="fas fa-search text-muted"></i>';
            btnConsultarApi.disabled = true;
            ocultarSugerencias();
            limpiarCamposPacienteExceptoCedula();
            return;
        }

        // Validación en tiempo real
        if (cedula.length === 10) {
            const esValida = validarCedulaEcuatoriana(cedula);

            if (esValida) {
                cedulaStatus.innerHTML = '<i class="fas fa-check text-success"></i>';
                cedulaInput.classList.add('is-valid');
                btnConsultarApi.disabled = false;
            } else {
                cedulaStatus.innerHTML = '<i class="fas fa-times text-danger"></i>';
                cedulaInput.classList.add('is-invalid');
                btnConsultarApi.disabled = true;
            }
        } else {
            cedulaStatus.innerHTML = '<i class="fas fa-clock text-warning"></i>';
            btnConsultarApi.disabled = true;
        }

        // Búsqueda en BD solo para admin/recepcionista y si tiene al menos 3 dígitos
        if (!esUsuarioPaciente && cedula.length >= 3) {
            timeoutBusqueda = setTimeout(() => {
                buscarPacienteEnBd(cedula);
            }, 500); // Debounce de 500ms
        }
    }

    function buscarPacienteEnBd(criterio) {
        if (!criterio || criterio.length < 3)
            return;

        console.log('🔍 Buscando paciente en BD:', criterio);

        mostrarCargandoSugerencias();

        fetch('views/api/buscar-paciente.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({criterio: criterio})
        })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    procesarResultadosBusqueda(data, criterio);
                })
                .catch(error => {
                    console.error('❌ Error en búsqueda de pacientes:', error);
                    ocultarSugerencias();
                });
    }

    function procesarResultadosBusqueda(data, criterioOriginal) {
        console.log('📊 Resultados búsqueda BD:', data);

        if (data.success && data.pacientes && data.pacientes.length > 0) {
            mostrarSugerenciasPacientes(data.pacientes, criterioOriginal);
        } else {
            ocultarSugerencias();

            // Si es una cédula completa válida y no se encuentra, sugerir API
            if (criterioOriginal.length === 10 && validarCedulaEcuatoriana(criterioOriginal)) {
                mostrarSugerenciaApi(criterioOriginal);
            }
        }
    }

    function mostrarSugerenciasPacientes(pacientes, criterio) {
        console.log('📋 Mostrando sugerencias para:', {pacientes, criterio});

        const dropdown = document.getElementById('cedulaSugerencias');
        const lista = document.getElementById('listaSugerencias');

        if (!dropdown || !lista) {
            console.error('❌ Elementos del dropdown no encontrados');
            return;
        }

        // CORRECCIÓN: Limpiar estilos previos y forzar visibilidad
        dropdown.removeAttribute('style');
        dropdown.className = 'sugerencias-dropdown show';

        // Limpiar lista anterior
        lista.innerHTML = '';

        // Agregar header
        const header = document.createElement('div');
        header.className = 'dropdown-header';
        header.innerHTML = `
        <i class="fas fa-users text-primary"></i> Pacientes encontrados
        <small class="text-muted ms-2">${pacientes.length} resultado(s)</small>
    `;
        lista.appendChild(header);

        // Agregar pacientes
        pacientes.forEach((paciente, index) => {
            console.log('👤 Procesando paciente:', paciente);

            const item = document.createElement('div');
            item.className = 'sugerencia-item';

            const nombreCompleto = paciente.nombre_completo || 'Nombre no disponible';
            const nombreResaltado = resaltarCoincidencias(nombreCompleto, criterio);
            const cedulaResaltada = resaltarCoincidencias(paciente.cedula || '', criterio);

            item.innerHTML = `
            <div class="d-flex align-items-center">
                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                    ${nombreCompleto.split(' ')[0]?.charAt(0) || 'P'}${nombreCompleto.split(' ')[1]?.charAt(0) || ''}
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1 fs-6">${nombreResaltado}</h6>
                    <div class="small text-muted">
                        <span class="me-3">
                            <i class="fas fa-id-card me-1"></i>
                            CI: ${cedulaResaltada || 'N/A'}
                        </span>
                        <span class="me-3">
                            <i class="fas fa-envelope me-1"></i>
                            ${paciente.email || 'No especificado'}
                        </span>
                        ${paciente.telefono ? `
                            <span>
                                <i class="fas fa-phone me-1"></i>
                                ${paciente.telefono}
                            </span>
                        ` : ''}
                    </div>
                </div>
                <div>
                    <i class="fas fa-mouse-pointer text-primary"></i>
                </div>
            </div>
        `;

            // Event listeners
            item.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('🖱️ Click en paciente:', paciente);
                seleccionarPacienteDeBd(paciente);
            });

            item.addEventListener('mouseenter', function () {
                this.style.backgroundColor = '#e3f2fd';
            });

            item.addEventListener('mouseleave', function () {
                this.style.backgroundColor = '';
            });

            lista.appendChild(item);
        });

        // Mostrar dropdown
        dropdown.style.display = 'block';
        dropdown.style.visibility = 'visible';
        dropdown.style.opacity = '1';

        console.log(`✅ Mostrando ${pacientes.length} sugerencias correctamente`);
    }

    function mostrarSugerenciaApi(cedula) {
        const dropdown = document.getElementById('cedulaSugerencias');
        const lista = document.getElementById('listaSugerencias');

        lista.innerHTML = `
            <div class="dropdown-header">
                <i class="fas fa-search text-info"></i> Consultar Registro Civil
            </div>
            <div class="sugerencia-item text-center py-3" onclick="consultarCedulaApi()">
                <i class="fas fa-id-card text-info fa-2x mb-2"></i>
                <div>
                    <strong>Paciente no registrado</strong><br>
                    <small class="text-muted">Consultar datos en el Registro Civil</small><br>
                    <span class="badge bg-info mt-1">Cédula: ${cedula}</span>
                </div>
            </div>
        `;

        dropdown.style.display = 'block';
    }

    function mostrarCargandoSugerencias() {
        const dropdown = document.getElementById('cedulaSugerencias');
        const lista = document.getElementById('listaSugerencias');

        lista.innerHTML = `
            <div class="dropdown-header">
                <i class="fas fa-search text-primary"></i> Buscando...
            </div>
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Buscando...</span>
                </div>
                <div class="mt-2 small text-muted">Buscando pacientes registrados...</div>
            </div>
        `;

        dropdown.style.display = 'block';
    }

    function seleccionarPacienteDeBd(paciente) {
        console.log('👤 Paciente seleccionado de BD:', paciente);

        // Marcar como seleccionado de BD
        pacienteSeleccionadoBd = true;
        document.getElementById('pacienteDesdeBd').value = '1';
        document.getElementById('idPacienteSeleccionado').value = paciente.id_usuario;

        // Llenar campos del formulario
        rellenarCamposPaciente({
            cedula: paciente.cedula || '',
            nombre: paciente.datos_completos?.nombre || paciente.nombre_completo.split(' ')[0] || '',
            apellido: paciente.datos_completos?.apellido || paciente.nombre_completo.split(' ').slice(1).join(' ') || '',
            email: paciente.email || '',
            telefono: paciente.datos_completos?.telefono || paciente.telefono || '',
            fecha_nacimiento: paciente.datos_completos?.fecha_nacimiento || '',
            genero: paciente.datos_completos?.genero || '',
            direccion: paciente.datos_completos?.direccion || ''
        });

        // Actualizar UI
        const cedulaInput = document.getElementById('cedulaPaciente');
        cedulaInput.classList.remove('is-invalid'); // CORRECCIÓN: Solo remover invalid
        cedulaInput.classList.add('is-valid');
        cedulaInput.value = paciente.cedula || '';

        document.getElementById('cedulaStatus').innerHTML = '<i class="fas fa-check text-success"></i>';

        // CORRECCIÓN: Ocultar sugerencias inmediatamente
        ocultarSugerencias();

        // Mostrar mensaje de éxito
        mostrarMensaje('success', `✅ Paciente cargado: ${paciente.nombre_completo}`);
    }

    function consultarCedulaApi() {
        const cedulaInput = document.getElementById('cedulaPaciente');
        const cedula = cedulaInput.value.replace(/\D/g, '');
        const btnConsultarApi = document.getElementById('btnConsultarApi');
        const cedulaApiResult = document.getElementById('cedulaApiResult');

        if (!cedula || cedula.length !== 10 || !validarCedulaEcuatoriana(cedula)) {
            mostrarMensaje('warning', '⚠️ Ingrese una cédula válida de 10 dígitos');
            return;
        }

        console.log('🌐 Consultando API de cédula:', cedula);

        // Mostrar loading
        btnConsultarApi.disabled = true;
        btnConsultarApi.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Consultando...';

        // Ocultar sugerencias
        ocultarSugerencias();

        fetch('views/api/consultar-cedula.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({cedula: cedula})
        })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    procesarRespuestaApi(data, cedula);
                })
                .catch(error => {
                    console.error('❌ Error en consulta API:', error);
                    mostrarResultadoApi('danger', `❌ Error de conexión: ${error.message}`);
                })
                .finally(() => {
                    // Restaurar botón
                    btnConsultarApi.disabled = false;
                    btnConsultarApi.innerHTML = '<i class="fas fa-search"></i> Consultar API';
                });
    }

    function procesarRespuestaApi(data, cedula) {
        console.log('📊 Respuesta API cédula:', data);

        if (data.success && data.nombres && data.apellidos) {
            // Datos encontrados en API
            const nombres = data.nombres.trim();
            const apellidos = data.apellidos.trim();

            rellenarCamposPaciente({
                cedula: cedula,
                nombre: nombres,
                apellido: apellidos,
                email: '',
                telefono: '',
                fecha_nacimiento: '',
                genero: '',
                direccion: ''
            });

            // Marcar campos como válidos
            document.getElementById('nombrePaciente').classList.add('is-valid');
            document.getElementById('apellidoPaciente').classList.add('is-valid');
            document.getElementById('cedulaPaciente').classList.add('is-valid');

            // Mostrar resultado exitoso
            mostrarResultadoApi('success', `✅ Datos encontrados: ${nombres} ${apellidos}`);
            mostrarMensaje('success', `✅ Datos del Registro Civil cargados correctamente`);

            // Auto-remover validación visual después de 3 segundos
            setTimeout(() => {
                document.querySelectorAll('.is-valid').forEach(el => {
                    if (el.id !== 'cedulaPaciente')
                        el.classList.remove('is-valid');
                });
            }, 3000);

        } else {
            // No se encontraron datos
            const mensaje = data.error || 'No se encontraron datos para esta cédula en el Registro Civil';
            mostrarResultadoApi('warning', `⚠️ ${mensaje}`);
            mostrarMensaje('warning', `⚠️ ${mensaje}`);
        }
    }

    function mostrarResultadoApi(tipo, mensaje) {
        const cedulaApiResult = document.getElementById('cedulaApiResult');

        const tiposClases = {
            'success': 'alert-success',
            'warning': 'alert-warning',
            'danger': 'alert-danger',
            'info': 'alert-info'
        };

        const claseAlert = tiposClases[tipo] || 'alert-info';

        cedulaApiResult.innerHTML = `
            <div class="alert ${claseAlert} alert-sm mb-0 border-0">
                ${mensaje}
            </div>
        `;

        cedulaApiResult.style.display = 'block';
        cedulaApiResult.style.animation = 'slideInUp 0.5s ease-out';

        // Auto-ocultar después de 8 segundos
        setTimeout(() => {
            if (cedulaApiResult.style.display !== 'none') {
                cedulaApiResult.style.animation = 'slideOutDown 0.5s ease-in';
                setTimeout(() => {
                    cedulaApiResult.style.display = 'none';
                }, 500);
            }
        }, 8000);
    }

    /* ================================
     FUNCIONES DE APOYO PARA CÉDULA
     ================================ */

    function rellenarCamposPaciente(datos) {
        const campos = {
            'cedulaPaciente': datos.cedula,
            'nombrePaciente': datos.nombre,
            'apellidoPaciente': datos.apellido,
            'emailPaciente': datos.email,
            'telefonoPaciente': datos.telefono,
            'fechaNacimientoPaciente': datos.fecha_nacimiento,
            'generoPaciente': datos.genero,
            'direccionPaciente': datos.direccion
        };

        Object.entries(campos).forEach(([id, valor]) => {
            const elemento = document.getElementById(id);
            if (elemento && valor) {
                elemento.value = valor;
        }
        });
    }

    function limpiarCamposPacienteExceptoCedula() {
        if (pacienteSeleccionadoBd)
            return; // No limpiar si hay paciente seleccionado de BD

        const campos = [
            'nombrePaciente', 'apellidoPaciente', 'emailPaciente',
            'telefonoPaciente', 'fechaNacimientoPaciente', 'generoPaciente', 'direccionPaciente'
        ];

        campos.forEach(id => {
            const elemento = document.getElementById(id);
            if (elemento) {
                elemento.value = '';
                elemento.classList.remove('is-valid', 'is-invalid');
            }
        });

        // Limpiar resultado API
        const cedulaApiResult = document.getElementById('cedulaApiResult');
        if (cedulaApiResult) {
            cedulaApiResult.style.display = 'none';
        }
    }

    function resaltarCoincidencias(texto, criterio) {
        if (!texto || !criterio)
            return texto;

        const regex = new RegExp(`(${criterio})`, 'gi');
        return texto.replace(regex, '<mark>$1</mark>');
    }

    function ocultarSugerencias() {
        const dropdown = document.getElementById('cedulaSugerencias');
        if (dropdown) {
            // CORRECCIÓN: Ocultar completamente con animación
            dropdown.style.opacity = '0';
            dropdown.style.transform = 'translateY(-10px)';

            setTimeout(() => {
                dropdown.style.display = 'none';
                dropdown.style.visibility = 'hidden';

                // Limpiar contenido para evitar problemas
                const lista = document.getElementById('listaSugerencias');
                if (lista) {
                    lista.innerHTML = '';
                }
            }, 200);
        }
    }

    // Agregar estilos para las sugerencias
    const estilosSugerencias = document.createElement('style');
    estilosSugerencias.textContent = `
        .avatar-sm {
            width: 36px;
            height: 36px;
            font-size: 0.8rem;
        }
        
        mark {
            background-color: #fff3cd;
            padding: 1px 2px;
            border-radius: 2px;
        }
        
        @keyframes slideOutDown {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(20px);
            }
        }
    `;
    document.head.appendChild(estilosSugerencias);

    /* ================================
     PASO 6: RESUMEN Y CONFIRMACIÓN
     ================================ */

    function generarResumen() {
        console.log('📋 Generando resumen de la cita...');

        const resumenContainer = document.getElementById('resumenCita');
        const formData = new FormData(document.getElementById('formAgendamiento'));

        // Obtener datos descriptivos
        const datosResumen = obtenerDatosResumen(formData);

        // Generar HTML del resumen
        const htmlResumen = construirHtmlResumen(datosResumen);

        // Aplicar al contenedor con animación
        resumenContainer.style.opacity = '0';
        resumenContainer.innerHTML = htmlResumen;

        setTimeout(() => {
            resumenContainer.style.transition = 'opacity 0.5s ease-in-out';
            resumenContainer.style.opacity = '1';
        }, 100);

        console.log('✅ Resumen generado correctamente');
    }

    function obtenerDatosResumen(formData) {
        // Obtener textos descriptivos
        const tipoTexto = datosFormulario.tipo_cita === 'virtual' ? 'Virtual' : 'Presencial';
        const especialidadTexto = especialidades.find(e => e.id_especialidad == datosFormulario.id_especialidad)?.nombre_especialidad || 'No especificado';
        const sucursalData = sucursales.find(s => s.id_sucursal == datosFormulario.id_sucursal);
        const medicoTexto = document.getElementById('medicoSeleccionado').selectedOptions[0]?.textContent || 'No seleccionado';
        const horaTexto = document.getElementById('horaSeleccionada').selectedOptions[0]?.textContent || 'No seleccionada';

        // Datos del paciente
        const nombreCompleto = `${formData.get('nombre_paciente')} ${formData.get('apellido_paciente')}`;
        const esCitaParaConocido = esUsuarioPaciente && document.getElementById('esParaConocido').value === '1';

        return {
            // Datos de la cita
            fecha: document.getElementById('fechaModalTitulo').textContent,
            fechaCompleta: fechaSeleccionada.toLocaleDateString('es-ES', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }),
            hora: horaTexto,
            tipo: tipoTexto,
            especialidad: especialidadTexto,
            medico: medicoTexto,
            sucursal: {
                nombre: sucursalData?.nombre_sucursal || 'No especificada',
                direccion: sucursalData?.direccion || '',
                telefono: sucursalData?.telefono || ''
            },

            // Datos del paciente
            paciente: {
                nombre: nombreCompleto,
                cedula: formData.get('cedula_paciente') || (esUsuarioPaciente && !esCitaParaConocido ? datosUsuarioActual?.cedula : ''),
                email: formData.get('email_paciente'),
                telefono: formData.get('telefono_paciente') || '',
                esConocido: esCitaParaConocido
            },

            // Detalles médicos
            motivo: formData.get('motivo_consulta'),
            observaciones: formData.get('observaciones') || '',

            // Meta información
            estadoCita: estadoCitaSegunRol[<?php echo $_SESSION['role_id']; ?>] || 'agendada',
            rolUsuario: <?php echo $_SESSION['role_id']; ?>,
            fechaAgendamiento: new Date().toLocaleDateString('es-ES')
        };
    }

    function construirHtmlResumen(datos) {
        const badgeEstado = datos.estadoCita === 'confirmada'
                ? '<span class="badge bg-success"><i class="fas fa-check"></i> Se confirmará automáticamente</span>'
                : '<span class="badge bg-warning"><i class="fas fa-clock"></i> Quedará pendiente de confirmación</span>';

        const iconoTipo = datos.tipo === 'Virtual'
                ? '<i class="fas fa-video text-success"></i>'
                : '<i class="fas fa-hospital text-primary"></i>';

        return `
            <div class="row">
                <!-- Tarjeta principal de la cita -->
                <div class="col-lg-8 mb-4">
                    <div class="card border-success h-100">
                        <div class="card-header bg-gradient-success text-white">
                            <div class="d-flex align-items-center justify-content-between">
                                <h6 class="mb-0">
                                    <i class="fas fa-calendar-check me-2"></i>
                                    Resumen de la Cita Médica
                                </h6>
                                ${badgeEstado}
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <!-- Información de fecha y hora -->
                                <div class="col-md-6">
                                    <div class="info-group">
                                        <h6 class="text-primary mb-2">
                                            <i class="fas fa-calendar me-2"></i>Fecha y Hora
                                        </h6>
                                        <div class="info-details bg-light p-3 rounded">
                                            <p class="mb-1">
                                                <strong class="text-dark">${datos.fechaCompleta}</strong>
                                            </p>
                                            <p class="mb-1">
                                                <i class="fas fa-clock text-success me-1"></i>
                                                <span class="fw-bold">${datos.hora}</span>
                                            </p>
                                            <p class="mb-0">
                                                ${iconoTipo}
                                                <span class="ms-1">${datos.tipo}</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Información médica -->
                                <div class="col-md-6">
                                    <div class="info-group">
                                        <h6 class="text-primary mb-2">
                                            <i class="fas fa-user-md me-2"></i>Atención Médica
                                        </h6>
                                        <div class="info-details bg-light p-3 rounded">
                                            <p class="mb-1">
                                                <strong>${datos.medico}</strong>
                                            </p>
                                            <p class="mb-1">
                                                <i class="fas fa-stethoscope text-primary me-1"></i>
                                                ${datos.especialidad}
                                            </p>
                                            <p class="mb-0">
                                                <i class="fas fa-building text-info me-1"></i>
                                                ${datos.sucursal.nombre}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Información del paciente -->
                                <div class="col-12">
                                    <div class="info-group">
                                        <h6 class="text-primary mb-2">
                                            <i class="fas fa-user me-2"></i>Información del Paciente
                                            ${datos.paciente.esConocido ? '<span class="badge bg-info ms-2">Cita para conocido</span>' : ''}
                                        </h6>
                                        <div class="info-details bg-light p-3 rounded">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p class="mb-1">
                                                        <strong>${datos.paciente.nombre}</strong>
                                                    </p>
                                                    ${datos.paciente.cedula ?
                `<p class="mb-1">
                                                            <i class="fas fa-id-card text-secondary me-1"></i>
                                                            CI: ${datos.paciente.cedula}
                                                        </p>` : ''
                }
                                                </div>
                                                <div class="col-md-6">
                                                    <p class="mb-1">
                                                        <i class="fas fa-envelope text-primary me-1"></i>
                                                        ${datos.paciente.email}
                                                    </p>
                                                    ${datos.paciente.telefono ?
                `<p class="mb-1">
                                                            <i class="fas fa-phone text-success me-1"></i>
                                                            ${datos.paciente.telefono}
                                                        </p>` : ''
                }
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Motivo de consulta -->
                                <div class="col-12">
                                    <div class="info-group">
                                        <h6 class="text-primary mb-2">
                                            <i class="fas fa-notes-medical me-2"></i>Motivo de Consulta
                                        </h6>
                                        <div class="info-details bg-light p-3 rounded">
                                            <p class="mb-0">${datos.motivo}</p>
                                        </div>
                                    </div>
                                </div>
                                
                                ${datos.observaciones ? `
                                    <div class="col-12">
                                        <div class="info-group">
                                            <h6 class="text-primary mb-2">
                                                <i class="fas fa-comment-medical me-2"></i>Observaciones Adicionales
                                            </h6>
                                            <div class="info-details bg-light p-3 rounded">
                                                <p class="mb-0">${datos.observaciones}</p>
                                            </div>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Panel lateral con información adicional -->
                <div class="col-lg-4">
                    <div class="row g-3">
                        <!-- Información de la sucursal -->
                        <div class="col-12">
                            <div class="card border-info">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-map-marker-alt me-2"></i>Ubicación
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <h6 class="text-info">${datos.sucursal.nombre}</h6>
                                    <p class="text-muted small mb-2">${datos.sucursal.direccion}</p>
                                    ${datos.sucursal.telefono ?
                `<p class="mb-0">
                                            <i class="fas fa-phone text-success me-1"></i>
                                            <a href="tel:${datos.sucursal.telefono}" class="text-decoration-none">
                                                ${datos.sucursal.telefono}
                                            </a>
                                        </p>` : ''
                }
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recordatorios importantes -->
                        <div class="col-12">
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Recordatorios
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled mb-0 small">
                                        <li class="mb-2">
                                            <i class="fas fa-clock text-warning me-2"></i>
                                            Llegue 15 minutos antes de su cita
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-clipboard-list text-primary me-2"></i>
                                            Complete el triaje digital antes de su consulta
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-id-card text-info me-2"></i>
                                            Traiga su cédula de identidad
                                        </li>
                                        ${datos.tipo === 'Virtual' ?
                `<li class="mb-0">
                                                <i class="fas fa-wifi text-success me-2"></i>
                                                Verifique su conexión a internet
                                            </li>` :
                `<li class="mb-0">
                                                <i class="fas fa-mask text-secondary me-2"></i>
                                                Siga los protocolos de bioseguridad
                                            </li>`
                }
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Información del agendamiento -->
                        <div class="col-12">
                            <div class="card border-light">
                                <div class="card-body text-center">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Agendado el ${datos.fechaAgendamiento}
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /* ================================
     CONFIRMACIÓN FINAL DE LA CITA
     ================================ */

    // Función para confirmar cita (versión real)
    async function confirmarCita() {
        try {
            // Mostrar loading
            const btnConfirmar = document.getElementById('btnConfirmar');
            const textoOriginal = btnConfirmar.innerHTML;
            btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            btnConfirmar.disabled = true;

            // Recopilar datos del formulario
            const form = document.getElementById('formAgendamiento');
            const formData = new FormData(form);

            // Preparar datos para enviar
            const datosEnvio = {
                fecha_cita: datosFormulario.fecha_cita,
                tipo_cita: datosFormulario.tipo_cita,
                id_especialidad: datosFormulario.id_especialidad,
                id_sucursal: datosFormulario.id_sucursal,
                id_medico: datosFormulario.id_medico,
                hora_cita: datosFormulario.hora_cita,
                motivo_consulta: formData.get('motivo_consulta'),
                observaciones: formData.get('observaciones') || '',
                id_paciente: formData.get('id_paciente') || '<?php echo $_SESSION["user_id"]; ?>'
            };

            console.log('📤 Enviando datos:', datosEnvio);

            // Realizar petición al servidor
            const response = await fetch('api/agendar-cita.php', {// <-- Cambio aquí
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(datosEnvio)
            });

            console.log('📨 Respuesta del servidor:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            console.log('✅ Resultado:', result);

            if (result.success) {
                mostrarMensaje('success', result.message || 'Cita agendada exitosamente');

                // Cerrar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalAgendamiento'));
                modal.hide();

                // Opcional: recargar la página o redirigir
                setTimeout(() => {
                    window.location.href = 'index.php?action=citas/gestionar';
                }, 2000);
            } else {
                throw new Error(result.error || 'Error desconocido al agendar la cita');
            }

        } catch (error) {
            console.error('❌ Error al agendar cita:', error);
            manejarErrorConfirmacion(error);
        } finally {
            // Restaurar botón
            const btnConfirmar = document.getElementById('btnConfirmar');
            btnConfirmar.innerHTML = '<i class="fas fa-save"></i> Confirmar Cita';
            btnConfirmar.disabled = false;
        }
    }



    // Función corregida para preparar datos para envío
    function prepararDatosParaEnvio(formData) {
        const datos = {
            // Datos básicos de la cita
            fecha_cita: document.getElementById('fechaCita').value,
            hora_cita: datosFormulario.hora_cita,
            tipo_cita: datosFormulario.tipo_cita,
            id_especialidad: datosFormulario.id_especialidad,
            id_sucursal: datosFormulario.id_sucursal,
            id_medico: datosFormulario.id_medico,
            motivo_consulta: formData.get('motivo_consulta'),
            observaciones: formData.get('observaciones') || null,

            // Estado según el rol del usuario
            estado_cita: estadoCitaSegunRol[<?php echo $_SESSION['role_id']; ?>] || 'agendada'
        };

        // CORRECCIÓN: Lógica mejorada para determinar el paciente correcto
        if (esUsuarioPaciente) {
            const esCitaParaConocido = document.getElementById('esParaConocido').value === '1';

            if (esCitaParaConocido) {
                // Cita para conocido - crear/buscar paciente
                if (pacienteSeleccionadoBd) {
                    datos.id_paciente_existente = document.getElementById('idPacienteSeleccionado').value;
                    datos.paciente_desde_bd = true;
                } else {
                    // Nuevo paciente
                    datos.nombre_paciente = formData.get('nombre_paciente');
                    datos.apellido_paciente = formData.get('apellido_paciente');
                    datos.cedula_paciente = formData.get('cedula_paciente');
                    datos.email_paciente = formData.get('email_paciente');
                    datos.telefono_paciente = formData.get('telefono_paciente');
                    datos.fecha_nacimiento_paciente = formData.get('fecha_nacimiento_paciente');
                    datos.genero_paciente = formData.get('genero_paciente');
                    datos.direccion_paciente = formData.get('direccion_paciente');
                }
                datos.es_para_conocido = true;
                datos.id_paciente_solicitante = <?php echo $_SESSION['user_id']; ?>;
            } else {
                // Cita propia - usar ID del paciente actual
                datos.id_paciente_existente = <?php echo $_SESSION['user_id']; ?>;
            }
        } else {
            // CORRECCIÓN: Admin/Recepcionista agendando - NUNCA usar su propio ID
            if (pacienteSeleccionadoBd) {
                // Paciente existente seleccionado de BD
                datos.id_paciente_existente = document.getElementById('idPacienteSeleccionado').value;
                datos.paciente_desde_bd = true;
                console.log('📋 Usando paciente existente de BD:', datos.id_paciente_existente);
            } else {
                // Nuevo paciente o datos para crear/actualizar
                const cedula = formData.get('cedula_paciente');
                if (!cedula) {
                    throw new Error('La cédula del paciente es obligatoria');
                }

                datos.nombre_paciente = formData.get('nombre_paciente');
                datos.apellido_paciente = formData.get('apellido_paciente');
                datos.cedula_paciente = cedula;
                datos.email_paciente = formData.get('email_paciente');
                datos.telefono_paciente = formData.get('telefono_paciente');
                datos.fecha_nacimiento_paciente = formData.get('fecha_nacimiento_paciente');
                datos.genero_paciente = formData.get('genero_paciente');
                datos.direccion_paciente = formData.get('direccion_paciente');
                datos.crear_paciente_si_no_existe = true;
                console.log('📋 Creando/actualizando paciente con cédula:', cedula);
            }

            // IMPORTANTE: Nunca usar el ID del usuario que agenda (recepcionista/admin)
            datos.id_usuario_registro = <?php echo $_SESSION['user_id']; ?>;
        }

        console.log('📦 Datos preparados para envío:', datos);
        return datos;
    }

    function mostrarLoadingConfirmacion(btnConfirmar) {
        const textoOriginal = btnConfirmar.innerHTML;
        btnConfirmar.setAttribute('data-texto-original', textoOriginal);
        btnConfirmar.innerHTML = `
            <span class="spinner-border spinner-border-sm me-2" role="status"></span>
            Agendando cita...
        `;
        btnConfirmar.disabled = true;

        // Deshabilitar otros botones
        document.getElementById('btnAnterior').disabled = true;
        document.querySelector('[data-bs-dismiss="modal"]').disabled = true;
    }

    function enviarDatosAlServidor(datosCita, btnConfirmar) {
        console.log('📡 Enviando datos al servidor...');

        fetch('views/api/agendar-cita.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(datosCita)
        })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    procesarRespuestaServidor(data, btnConfirmar);
                })
                .catch(error => {
                    console.error('❌ Error al agendar cita:', error);
                    manejarErrorConfirmacion(error, btnConfirmar);
                });
    }

    function procesarRespuestaServidor(data, btnConfirmar) {
        console.log('📊 Respuesta del servidor:', data);

        if (data.success) {
            // Éxito al agendar
            mostrarExitoAgendamiento(data, btnConfirmar);
        } else {
            // Error en el agendamiento
            manejarErrorConfirmacion(new Error(data.error || 'Error desconocido'), btnConfirmar);
        }
    }

    function mostrarExitoAgendamiento(data, btnConfirmar) {
        // Cambiar botón a estado de éxito
        btnConfirmar.innerHTML = `
            <i class="fas fa-check me-2"></i>
            ¡Cita Agendada!
        `;
        btnConfirmar.className = 'btn btn-success btn-lg';

        // Mostrar mensaje de éxito principal
        const estadoTexto = data.cita?.estado_cita === 'confirmada' ? 'confirmada' : 'agendada';
        mostrarMensaje('success', `🎉 ¡Cita ${estadoTexto} exitosamente!`);

        // Preparar cierre automático del modal
        setTimeout(() => {
            cerrarModalConExito(data);
        }, 2000);

        console.log('✅ Cita agendada exitosamente');
    }

    function cerrarModalConExito(data) {
        const modal = bootstrap.Modal.getInstance(document.getElementById('modalAgendamiento'));

        // Cerrar modal
        modal.hide();

        // Mostrar mensaje final y opciones
        setTimeout(() => {
            mostrarOpcionesPostAgendamiento(data);
        }, 500);
    }

    function mostrarOpcionesPostAgendamiento(data) {
        const citaId = data.cita_id;
        const estadoCita = data.cita?.estado_cita || 'agendada';

        // Crear modal de opciones post-agendamiento
        const modalHtml = `
            <div class="modal fade" id="modalPostAgendamiento" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-check-circle me-2"></i>
                                ¡Cita Agendada Exitosamente!
                            </h5>
                        </div>
                        <div class="modal-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-calendar-check text-success" style="font-size: 3rem;"></i>
                            </div>
                            <h6>Su cita ha sido ${estadoCita}</h6>
                            <p class="text-muted">
                                ${estadoCita === 'confirmada'
                ? 'Su cita está confirmada y lista.'
                : 'Su cita está pendiente de confirmación.'
                }
                            </p>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-clipboard-list me-2"></i>
                                <strong>¡No olvide completar el triaje digital!</strong>
                            </div>
                        </div>
                        <div class="modal-footer justify-content-center">
                            <a href="index.php?action=citas/agenda" class="btn btn-primary">
                                <i class="fas fa-calendar"></i> Ver Mi Agenda
                            </a>
                            <a href="index.php?action=consultas/triaje/completar&cita_id=${citaId}" class="btn btn-success">
                                <i class="fas fa-clipboard-list"></i> Completar Triaje
                            </a>
                            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                                <i class="fas fa-plus"></i> Agendar Otra Cita
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Agregar y mostrar modal
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modalPost = new bootstrap.Modal(document.getElementById('modalPostAgendamiento'));
        modalPost.show();

        // Limpiar modal al cerrar
        document.getElementById('modalPostAgendamiento').addEventListener('hidden.bs.modal', function () {
            this.remove();
        });
    }

    function manejarErrorConfirmacion(error) {
        let mensaje = 'Error desconocido';

        if (error.message.includes('Unexpected token')) {
            mensaje = 'Error del servidor. Por favor, contacte al administrador.';
        } else {
            mensaje = error.message;
        }

        mostrarMensaje('danger', `❌ Error: ${mensaje}`);
        console.log('📢 Mensaje mostrado [danger]:', `❌ Error: ${mensaje}`);
    }

    function restaurarBotonConfirmacion(btnConfirmar) {
        const textoOriginal = btnConfirmar.getAttribute('data-texto-original');
        btnConfirmar.innerHTML = textoOriginal || '<i class="fas fa-calendar-check"></i> Confirmar Cita';
        btnConfirmar.className = 'btn btn-success btn-lg';
        btnConfirmar.disabled = false;
    }

    // Función alternativa para confirmar cita
    async function confirmarCitaAlternativa() {
        try {
            // Mostrar loading
            const btnConfirmar = document.getElementById('btnConfirmar');
            const textoOriginal = btnConfirmar.innerHTML;
            btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            btnConfirmar.disabled = true;

            // Recopilar datos del formulario
            const form = document.getElementById('formAgendamiento');
            const formData = new FormData(form);

            // Agregar datos adicionales de la cita
            formData.append('fecha_cita', document.getElementById('fechaCita').value);
            formData.append('tipo_cita', datosFormulario.tipo_cita);
            formData.append('id_especialidad', datosFormulario.id_especialidad);
            formData.append('id_sucursal', datosFormulario.id_sucursal);
            formData.append('id_medico', datosFormulario.id_medico);
            formData.append('hora_cita', datosFormulario.hora_cita);
            formData.append('action', 'agendar_cita');

            // CORRECCIÓN: Manejar correctamente el ID del paciente
            if (!esUsuarioPaciente) {
                // Para admin/recepcionista
                if (pacienteSeleccionadoBd) {
                    formData.append('id_paciente', document.getElementById('idPacienteSeleccionado').value);
                    formData.append('paciente_existente', '1');
                } else {
                    // Se creará un nuevo paciente con los datos del formulario
                    formData.append('crear_paciente', '1');
                }
            } else {
                // Para pacientes
                const esCitaParaConocido = document.getElementById('esParaConocido').value === '1';
                if (esCitaParaConocido) {
                    if (pacienteSeleccionadoBd) {
                        formData.append('id_paciente', document.getElementById('idPacienteSeleccionado').value);
                    } else {
                        formData.append('crear_paciente', '1');
                    }
                } else {
                    formData.append('id_paciente', <?php echo $_SESSION['user_id']; ?>);
                }
            }

            console.log('📤 Enviando datos via método alternativo');

            // Usar fetch con FormData
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const responseText = await response.text();
            console.log('📄 Respuesta:', responseText);

            // Intentar parsear como JSON
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (e) {
                // Si no es JSON, buscar indicadores de éxito
                if (responseText.includes('exitosamente') || responseText.includes('success')) {
                    result = {success: true, message: 'Cita agendada exitosamente'};
                } else {
                    throw new Error('Respuesta del servidor no válida');
                }
            }

            if (result.success) {
                mostrarMensaje('success', result.message || 'Cita agendada exitosamente');

                // Cerrar modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('modalAgendamiento'));
                modal.hide();

                // Recargar página
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                throw new Error(result.error || 'Error desconocido al agendar la cita');
            }

        } catch (error) {
            console.error('❌ Error en método alternativo:', error);
            manejarErrorConfirmacion(error);
        } finally {
            // Restaurar botón
            const btnConfirmar = document.getElementById('btnConfirmar');
            btnConfirmar.innerHTML = '<i class="fas fa-save"></i> Confirmar Cita';
            btnConfirmar.disabled = false;
        }
    }

// Modificar la función confirmarCita original para usar fallback
    async function confirmarCita() {
        try {
            // Intentar método original primero
            const response = await fetch('views/api/agendar-cita.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(datosEnvio)
            });

            if (!response.ok)
                throw new Error('Fetch failed');

            const result = await response.json();
            // ... resto del código original

        } catch (error) {
            console.warn('⚠️ Método principal falló, intentando método alternativo...', error);

            // Usar método alternativo como fallback
            return confirmarCitaAlternativa();
        }
    }

    /* ================================
     FINALIZACIÓN DEL SCRIPT
     ================================ */

    console.log('🎉 Sistema de agendamiento de citas cargado completamente');
    console.log('📋 Funcionalidades disponibles:', {
        'Calendario interactivo': '✅',
        'Validación por pasos': '✅',
        'Búsqueda inteligente de cédula': '✅',
        'Consulta API Registro Civil': '✅',
        'Selección de médicos y horarios': '✅',
        'Estados según rol de usuario': '✅',
        'Integración con triaje digital': '✅'
    });

</script>