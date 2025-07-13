<?php
// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Verificar permisos (solo admin y médicos pueden crear recetas)
if (!in_array($_SESSION['role_id'], [1, 3])) {
    header('Location: index.php?action=consultas/recetas');
    exit;
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Variables para el formulario
$success = '';
$error = '';
$consulta_id = (int) ($_GET['consulta_id'] ?? 0);
$cita_id = (int) ($_GET['cita_id'] ?? 0);

// Información de la consulta/cita si se proporciona
$consultaInfo = null;
$pacienteInfo = null;

if ($consulta_id) {
    // Obtener información de la consulta
    $sqlConsulta = "SELECT c.*, cit.*, 
                           CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                           p.cedula as paciente_cedula,
                           p.fecha_nacimiento,
                           CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
                           e.nombre_especialidad,
                           s.nombre_sucursal
                    FROM consultas c
                    INNER JOIN citas cit ON c.id_cita = cit.id_cita
                    INNER JOIN usuarios p ON cit.id_paciente = p.id_usuario
                    INNER JOIN usuarios m ON cit.id_medico = m.id_usuario
                    INNER JOIN especialidades e ON cit.id_especialidad = e.id_especialidad
                    INNER JOIN sucursales s ON cit.id_sucursal = s.id_sucursal
                    WHERE c.id_consulta = :consulta_id";

    // Si es médico, verificar que sea su consulta
    if ($_SESSION['role_id'] == 3) {
        $sqlConsulta .= " AND cit.id_medico = :id_medico";
    }

    $stmtConsulta = $db->prepare($sqlConsulta);
    $params = ['consulta_id' => $consulta_id];
    if ($_SESSION['role_id'] == 3) {
        $params['id_medico'] = $_SESSION['user_id'];
    }
    $stmtConsulta->execute($params);
    $consultaInfo = $stmtConsulta->fetch(PDO::FETCH_ASSOC);

    if (!$consultaInfo) {
        $error = "No se encontró la consulta o no tienes permisos para crear recetas para esta consulta.";
    }
} elseif ($cita_id) {
    // Obtener información de la cita (para crear consulta y receta)
    $sqlCita = "SELECT cit.*, 
                       CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                       p.cedula as paciente_cedula,
                       p.fecha_nacimiento,
                       CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
                       e.nombre_especialidad,
                       s.nombre_sucursal
                FROM citas cit
                INNER JOIN usuarios p ON cit.id_paciente = p.id_usuario
                INNER JOIN usuarios m ON cit.id_medico = m.id_usuario
                INNER JOIN especialidades e ON cit.id_especialidad = e.id_especialidad
                INNER JOIN sucursales s ON cit.id_sucursal = s.id_sucursal
                WHERE cit.id_cita = :cita_id
                AND cit.estado_cita IN ('en_curso', 'completada')";

    // Si es médico, verificar que sea su cita
    if ($_SESSION['role_id'] == 3) {
        $sqlCita .= " AND cit.id_medico = :id_medico";
    }

    $stmtCita = $db->prepare($sqlCita);
    $params = ['cita_id' => $cita_id];
    if ($_SESSION['role_id'] == 3) {
        $params['id_medico'] = $_SESSION['user_id'];
    }
    $stmtCita->execute($params);
    $consultaInfo = $stmtCita->fetch(PDO::FETCH_ASSOC);

    if (!$consultaInfo) {
        $error = "No se encontró la cita o no tienes permisos para crear recetas para esta cita.";
    }
}

// Obtener consultas disponibles para crear recetas
$consultasDisponibles = [];
if (!$consultaInfo) {
    if ($_SESSION['role_id'] == 1) { // Admin ve todas las consultas
        $sqlConsultasDisponibles = "SELECT c.id_consulta, c.id_cita,
                                           CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                                           p.cedula as paciente_cedula,
                                           p.telefono as paciente_telefono,
                                           CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
                                           e.nombre_especialidad,
                                           s.nombre_sucursal,
                                           cit.fecha_cita,
                                           cit.hora_cita,
                                           cit.motivo_consulta,
                                           cit.estado_cita,
                                           c.diagnostico_principal,
                                           (SELECT COUNT(*) FROM recetas r WHERE r.id_consulta = c.id_consulta) as total_recetas
                                    FROM consultas c
                                    INNER JOIN citas cit ON c.id_cita = cit.id_cita
                                    INNER JOIN usuarios p ON cit.id_paciente = p.id_usuario
                                    INNER JOIN usuarios m ON cit.id_medico = m.id_usuario
                                    INNER JOIN especialidades e ON cit.id_especialidad = e.id_especialidad
                                    INNER JOIN sucursales s ON cit.id_sucursal = s.id_sucursal
                                    WHERE cit.estado_cita IN ('completada', 'en_curso')
                                    ORDER BY cit.fecha_cita DESC
                                    LIMIT 50";
        $stmtConsultasDisponibles = $db->prepare($sqlConsultasDisponibles);
        $stmtConsultasDisponibles->execute();
        $consultasDisponibles = $stmtConsultasDisponibles->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($_SESSION['role_id'] == 3) { // Médico solo ve sus consultas
        $sqlMisConsultas = "SELECT c.id_consulta, c.id_cita,
                                   CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                                   p.cedula as paciente_cedula,
                                   p.telefono as paciente_telefono,
                                   e.nombre_especialidad,
                                   s.nombre_sucursal,
                                   cit.fecha_cita,
                                   cit.hora_cita,
                                   cit.motivo_consulta,
                                   cit.estado_cita,
                                   c.diagnostico_principal,
                                   (SELECT COUNT(*) FROM recetas r WHERE r.id_consulta = c.id_consulta) as total_recetas
                            FROM consultas c
                            INNER JOIN citas cit ON c.id_cita = cit.id_cita
                            INNER JOIN usuarios p ON cit.id_paciente = p.id_usuario
                            INNER JOIN especialidades e ON cit.id_especialidad = e.id_especialidad
                            INNER JOIN sucursales s ON cit.id_sucursal = s.id_sucursal
                            WHERE cit.id_medico = :id_medico
                            AND cit.estado_cita IN ('completada', 'en_curso')
                            ORDER BY cit.fecha_cita DESC
                            LIMIT 30";
        $stmtMisConsultas = $db->prepare($sqlMisConsultas);
        $stmtMisConsultas->execute(['id_medico' => $_SESSION['user_id']]);
        $consultasDisponibles = $stmtMisConsultas->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    try {
        $db->beginTransaction();

        // Validar datos requeridos
        $medicamento = trim($_POST['medicamento'] ?? '');
        $concentracion = trim($_POST['concentracion'] ?? '');
        $forma_farmaceutica = trim($_POST['forma_farmaceutica'] ?? '');
        $dosis = trim($_POST['dosis'] ?? '');
        $frecuencia = trim($_POST['frecuencia'] ?? '');
        $duracion = trim($_POST['duracion'] ?? '');
        $cantidad = trim($_POST['cantidad'] ?? '');
        $indicaciones_especiales = trim($_POST['indicaciones_especiales'] ?? '');
        $id_consulta_final = (int) ($_POST['id_consulta'] ?? 0);

        if (empty($medicamento) || empty($dosis) || empty($frecuencia) || empty($duracion) || empty($cantidad)) {
            throw new Exception("Todos los campos obligatorios deben ser completados.");
        }

        // Si no hay consulta, crear una básica (solo para casos especiales)
        if (!$id_consulta_final && $cita_id) {
            $sqlInsertConsulta = "INSERT INTO consultas (id_cita, fecha_consulta) VALUES (:id_cita, NOW())";
            $stmtInsertConsulta = $db->prepare($sqlInsertConsulta);
            $stmtInsertConsulta->execute(['id_cita' => $cita_id]);
            $id_consulta_final = $db->lastInsertId();
        }

        if (!$id_consulta_final) {
            throw new Exception("No se pudo asociar la receta a una consulta válida.");
        }

        // Insertar receta
        $sqlInsert = "INSERT INTO recetas (
                        id_consulta, medicamento, concentracion, forma_farmaceutica, 
                        dosis, frecuencia, duracion, cantidad, indicaciones_especiales
                      ) VALUES (
                        :id_consulta, :medicamento, :concentracion, :forma_farmaceutica,
                        :dosis, :frecuencia, :duracion, :cantidad, :indicaciones_especiales
                      )";

        $stmtInsert = $db->prepare($sqlInsert);
        $stmtInsert->execute([
            'id_consulta' => $id_consulta_final,
            'medicamento' => $medicamento,
            'concentracion' => $concentracion,
            'forma_farmaceutica' => $forma_farmaceutica,
            'dosis' => $dosis,
            'frecuencia' => $frecuencia,
            'duracion' => $duracion,
            'cantidad' => $cantidad,
            'indicaciones_especiales' => $indicaciones_especiales
        ]);

        $id_receta = $db->lastInsertId();

        // LÍNEA 202: $id_receta = $db->lastInsertId();
// ========== NUEVO CÓDIGO: ENVÍO AUTOMÁTICO POR EMAIL ==========
        try {
            // Obtener datos completos de la receta recién creada para el email
            $sqlRecetaCompleta = "SELECT r.*, 
                                 CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                                 p.email as paciente_email,
                                 p.cedula as paciente_cedula,
                                 CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
                                 e.nombre_especialidad
                          FROM recetas r
                          INNER JOIN consultas c ON r.id_consulta = c.id_consulta
                          INNER JOIN citas cit ON c.id_cita = cit.id_cita
                          INNER JOIN usuarios p ON cit.id_paciente = p.id_usuario
                          INNER JOIN usuarios m ON cit.id_medico = m.id_usuario
                          INNER JOIN especialidades e ON cit.id_especialidad = e.id_especialidad
                          WHERE r.id_receta = :id_receta";

            $stmtRecetaCompleta = $db->prepare($sqlRecetaCompleta);
            $stmtRecetaCompleta->execute(['id_receta' => $id_receta]);
            $recetaCompleta = $stmtRecetaCompleta->fetch(PDO::FETCH_ASSOC);

            if ($recetaCompleta && $recetaCompleta['paciente_email']) {
                // Incluir el sistema de email
                require_once __DIR__ . '/../../../includes/email-sender.php';

                // Preparar datos para el email
                $datosPaciente = [
                    'nombre' => $recetaCompleta['paciente_nombre'],
                    'email' => $recetaCompleta['paciente_email'],
                    'cedula' => $recetaCompleta['paciente_cedula']
                ];

                $datosMedico = [
                    'nombre' => $recetaCompleta['medico_nombre'],
                    'especialidad' => $recetaCompleta['nombre_especialidad']
                ];

                $datosReceta = [
                    'codigo_receta' => $recetaCompleta['codigo_receta'],
                    'medicamento' => $recetaCompleta['medicamento'],
                    'concentracion' => $recetaCompleta['concentracion'] ?: 'No especificada',
                    'forma_farmaceutica' => $recetaCompleta['forma_farmaceutica'] ?: 'No especificada',
                    'dosis' => $recetaCompleta['dosis'],
                    'frecuencia' => $recetaCompleta['frecuencia'],
                    'duracion' => $recetaCompleta['duracion'],
                    'cantidad' => $recetaCompleta['cantidad'],
                    'indicaciones_especiales' => $recetaCompleta['indicaciones_especiales']
                ];

                // Enviar email con PDF adjunto
                $emailEnviado = enviarRecetaPorEmail($datosReceta, $datosPaciente, $datosMedico);

                if ($emailEnviado) {
                    error_log("Receta {$recetaCompleta['codigo_receta']} enviada por email a {$recetaCompleta['paciente_email']}");
                } else {
                    error_log("Error al enviar receta {$recetaCompleta['codigo_receta']} por email");
                }
            }
        } catch (Exception $emailError) {
            // Si hay error en el email, solo registrarlo pero no afectar el proceso principal
            error_log("Error enviando email de receta: " . $emailError->getMessage());
        }
// ========== FIN DEL NUEVO CÓDIGO ==========

        $db->commit();

// Redirigir al detalle de la receta creada
        header("Location: index.php?action=consultas/recetas/detalle&id={$id_receta}&success=" . urlencode("Receta creada exitosamente y enviada por email"));
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }

// Función para calcular edad
    function calcularEdad($fechaNacimiento) {
        if (!$fechaNacimiento)
            return 'N/A';
        $nacimiento = new DateTime($fechaNacimiento);
        $hoy = new DateTime();
        return $nacimiento->diff($hoy)->y . ' años';
    }

    include 'views/includes/header.php';
    include 'views/includes/navbar.php';
    ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-prescription text-primary"></i> 
                        Nueva Receta Médica
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="javascript:history.back()" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Volver
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Mensajes -->
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Información del Paciente -->
                    <?php if ($consultaInfo): ?>
                        <div class="col-lg-4 mb-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-user me-2"></i>
                                        Información del Paciente
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <div class="avatar bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                                             style="width: 60px; height: 60px; font-size: 24px;">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    </div>

                                    <div class="text-center mb-3">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($consultaInfo['paciente_nombre']); ?></h6>
                                        <small class="text-muted">Cédula: <?php echo htmlspecialchars($consultaInfo['paciente_cedula']); ?></small>
                                    </div>

                                    <div class="info-item mb-2">
                                        <strong><i class="fas fa-birthday-cake text-muted me-2"></i>Edad:</strong>
                                        <span><?php echo calcularEdad($consultaInfo['fecha_nacimiento']); ?></span>
                                    </div>

                                    <div class="info-item mb-2">
                                        <strong><i class="fas fa-calendar text-muted me-2"></i>Fecha Consulta:</strong>
                                        <span><?php echo date('d/m/Y', strtotime($consultaInfo['fecha_cita'])); ?></span>
                                    </div>

                                    <div class="info-item mb-2">
                                        <strong><i class="fas fa-user-md text-muted me-2"></i>Médico:</strong>
                                        <span><?php echo htmlspecialchars($consultaInfo['medico_nombre']); ?></span>
                                    </div>

                                    <div class="info-item">
                                        <strong><i class="fas fa-building text-muted me-2"></i>Sucursal:</strong>
                                        <span><?php echo htmlspecialchars($consultaInfo['nombre_sucursal']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Formulario de Receta -->
                    <div class="<?php echo $consultaInfo ? 'col-lg-8' : 'col-12'; ?>">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-pills me-2"></i>
                                    Datos de la Receta
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="formReceta">
                                    <?php if ($consultaInfo && isset($consultaInfo['id_consulta'])): ?>
                                        <input type="hidden" name="id_consulta" value="<?php echo $consultaInfo['id_consulta']; ?>">
                                    <?php endif; ?>

                                    <!-- Selector de Consulta (solo si no viene de consulta específica) -->
                                    <?php if (!$consultaInfo): ?>
                                        <div class="row mb-4">
                                            <div class="col-12">
                                                <h6 class="text-primary border-bottom pb-2 mb-3">
                                                    <i class="fas fa-search me-2"></i>Seleccionar Consulta
                                                </h6>

                                                <?php if (!empty($consultasDisponibles)): ?>
                                                    <div class="mb-3">
                                                        <label for="seleccionar_consulta" class="form-label">
                                                            Consulta Médica <span class="text-danger">*</span>
                                                        </label>
                                                        <select class="form-select" id="seleccionar_consulta" name="id_consulta" required onchange="mostrarDetallesConsulta(this.value)">
                                                            <option value="">Seleccionar consulta para crear receta...</option>
                                                            <?php foreach ($consultasDisponibles as $consulta): ?>
                                                                <option value="<?php echo $consulta['id_consulta']; ?>" 
                                                                        data-paciente="<?php echo htmlspecialchars($consulta['paciente_nombre']); ?>"
                                                                        data-cedula="<?php echo htmlspecialchars($consulta['paciente_cedula']); ?>"
                                                                        data-telefono="<?php echo htmlspecialchars($consulta['paciente_telefono'] ?? ''); ?>"
                                                                        data-fecha="<?php echo date('d/m/Y', strtotime($consulta['fecha_cita'])); ?>"
                                                                        data-hora="<?php echo date('H:i', strtotime($consulta['hora_cita'])); ?>"
                                                                        data-motivo="<?php echo htmlspecialchars($consulta['motivo_consulta'] ?? ''); ?>"
                                                                        data-diagnostico="<?php echo htmlspecialchars($consulta['diagnostico_principal'] ?? ''); ?>"
                                                                        data-especialidad="<?php echo htmlspecialchars($consulta['nombre_especialidad']); ?>"
                                                                        data-sucursal="<?php echo htmlspecialchars($consulta['nombre_sucursal']); ?>"
                                                                        data-recetas="<?php echo $consulta['total_recetas']; ?>"
                                                                        <?php if ($_SESSION['role_id'] == 1): ?>
                                                                            data-medico="<?php echo htmlspecialchars($consulta['medico_nombre']); ?>"
                                                                        <?php endif; ?>>

                                                                    <?php echo htmlspecialchars($consulta['paciente_nombre']); ?> - 
                                                                    <?php echo htmlspecialchars($consulta['paciente_cedula']); ?> - 
                                                                    <?php echo date('d/m/Y H:i', strtotime($consulta['fecha_cita'] . ' ' . $consulta['hora_cita'])); ?>

                                                                    <?php if ($_SESSION['role_id'] == 1): ?>
                                                                        (Dr. <?php echo htmlspecialchars($consulta['medico_nombre']); ?>)
                                                                    <?php endif; ?>

                                                                    <?php if ($consulta['total_recetas'] > 0): ?>
                                                                        [<?php echo $consulta['total_recetas']; ?> receta(s) existente(s)]
                                                                    <?php endif; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <div class="form-text">Seleccione la consulta médica para la cual desea crear esta receta</div>
                                                    </div>

                                                    <!-- Panel de detalles de la consulta seleccionada -->
                                                    <div id="detallesConsulta" class="card border-info d-none">
                                                        <div class="card-header bg-info text-white">
                                                            <h6 class="mb-0">
                                                                <i class="fas fa-info-circle me-2"></i>
                                                                Detalles de la Consulta Seleccionada
                                                            </h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-2">
                                                                        <strong><i class="fas fa-user text-muted me-2"></i>Paciente:</strong>
                                                                        <span id="detalle-paciente"></span>
                                                                    </div>
                                                                    <div class="mb-2">
                                                                        <strong><i class="fas fa-id-card text-muted me-2"></i>Cédula:</strong>
                                                                        <span id="detalle-cedula"></span>
                                                                    </div>
                                                                    <div class="mb-2">
                                                                        <strong><i class="fas fa-phone text-muted me-2"></i>Teléfono:</strong>
                                                                        <span id="detalle-telefono"></span>
                                                                    </div>
                                                                    <?php if ($_SESSION['role_id'] == 1): ?>
                                                                        <div class="mb-2">
                                                                            <strong><i class="fas fa-user-md text-muted me-2"></i>Médico:</strong>
                                                                            <span id="detalle-medico"></span>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-2">
                                                                        <strong><i class="fas fa-calendar text-muted me-2"></i>Fecha y Hora:</strong>
                                                                        <span id="detalle-fecha"></span> a las <span id="detalle-hora"></span>
                                                                    </div>
                                                                    <div class="mb-2">
                                                                        <strong><i class="fas fa-stethoscope text-muted me-2"></i>Especialidad:</strong>
                                                                        <span id="detalle-especialidad"></span>
                                                                    </div>
                                                                    <div class="mb-2">
                                                                        <strong><i class="fas fa-building text-muted me-2"></i>Sucursal:</strong>
                                                                        <span id="detalle-sucursal"></span>
                                                                    </div>
                                                                    <div class="mb-2">
                                                                        <strong><i class="fas fa-prescription text-muted me-2"></i>Recetas existentes:</strong>
                                                                        <span id="detalle-recetas" class="badge bg-secondary"></span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row mt-2">
                                                                <div class="col-12">
                                                                    <div class="mb-2" id="detalle-motivo-container">
                                                                        <strong><i class="fas fa-comment text-muted me-2"></i>Motivo de Consulta:</strong>
                                                                        <div class="mt-1 p-2 bg-light rounded">
                                                                            <span id="detalle-motivo"></span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="mb-2" id="detalle-diagnostico-container">
                                                                        <strong><i class="fas fa-diagnosis text-muted me-2"></i>Diagnóstico:</strong>
                                                                        <div class="mt-1 p-2 bg-light rounded">
                                                                            <span id="detalle-diagnostico"></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                <?php else: ?>
                                                    <div class="alert alert-warning border-0">
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                                                            <div>
                                                                <h6 class="alert-heading mb-1">No hay consultas disponibles</h6>
                                                                <p class="mb-0">
                                                                    <?php if ($_SESSION['role_id'] == 3): ?>
                                                                        No tienes consultas médicas completadas donde puedas crear recetas.
                                                                        <a href="index.php?action=consultas/atender" class="alert-link">Ir a Atender Pacientes</a>
                                                                    <?php else: ?>
                                                                        No hay consultas médicas completadas en el sistema.
                                                                        <a href="index.php?action=citas/gestionar" class="alert-link">Gestionar Citas</a>
                                                                    <?php endif; ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Información del Medicamento -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                                <i class="fas fa-pills me-2"></i>Información del Medicamento
                                            </h6>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label for="medicamento" class="form-label">
                                                Medicamento <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="medicamento" name="medicamento" 
                                                   placeholder="Nombre del medicamento" required>
                                            <div class="form-text">Nombre genérico o comercial del medicamento</div>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="concentracion" class="form-label">Concentración</label>
                                            <input type="text" class="form-control" id="concentracion" name="concentracion" 
                                                   placeholder="ej: 500mg, 10ml">
                                            <div class="form-text">Concentración por unidad</div>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="forma_farmaceutica" class="form-label">Forma Farmacéutica</label>
                                            <select class="form-select" id="forma_farmaceutica" name="forma_farmaceutica">
                                                <option value="">Seleccionar...</option>
                                                <option value="Tabletas">Tabletas</option>
                                                <option value="Cápsulas">Cápsulas</option>
                                                <option value="Jarabe">Jarabe</option>
                                                <option value="Suspensión">Suspensión</option>
                                                <option value="Gotas">Gotas</option>
                                                <option value="Crema">Crema</option>
                                                <option value="Pomada">Pomada</option>
                                                <option value="Gel">Gel</option>
                                                <option value="Solución">Solución</option>
                                                <option value="Inyectable">Inyectable</option>
                                                <option value="Supositorios">Supositorios</option>
                                                <option value="Óvulos">Óvulos</option>
                                                <option value="Parches">Parches</option>
                                                <option value="Inhalador">Inhalador</option>
                                                <option value="Otra">Otra</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Posología -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                                <i class="fas fa-clock me-2"></i>Posología
                                            </h6>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="dosis" class="form-label">
                                                Dosis <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="dosis" name="dosis" 
                                                   placeholder="ej: 1 tableta, 5ml" required>
                                            <div class="form-text">Cantidad por toma</div>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="frecuencia" class="form-label">
                                                Frecuencia <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-select" id="frecuencia" name="frecuencia" required>
                                                <option value="">Seleccionar...</option>
                                                <option value="Cada 4 horas">Cada 4 horas</option>
                                                <option value="Cada 6 horas">Cada 6 horas</option>
                                                <option value="Cada 8 horas">Cada 8 horas</option>
                                                <option value="Cada 12 horas">Cada 12 horas</option>
                                                <option value="Cada 24 horas">Cada 24 horas (Diario)</option>
                                                <option value="2 veces al día">2 veces al día</option>
                                                <option value="3 veces al día">3 veces al día</option>
                                                <option value="Antes de comidas">Antes de comidas</option>
                                                <option value="Después de comidas">Después de comidas</option>
                                                <option value="Con comidas">Con comidas</option>
                                                <option value="Antes de dormir">Antes de dormir</option>
                                                <option value="En ayunas">En ayunas</option>
                                                <option value="Según necesidad">Según necesidad</option>
                                            </select>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="duracion" class="form-label">
                                                Duración <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="duracion" name="duracion" 
                                                   placeholder="ej: 7 días, 2 semanas" required>
                                            <div class="form-text">Duración del tratamiento</div>
                                        </div>

                                        <div class="col-md-3 mb-3">
                                            <label for="cantidad" class="form-label">
                                                Cantidad <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control" id="cantidad" name="cantidad" 
                                                   placeholder="ej: 30 tabletas, 1 frasco" required>
                                            <div class="form-text">Cantidad total a dispensar</div>
                                        </div>
                                    </div>

                                    <!-- Indicaciones Especiales -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <h6 class="text-primary border-bottom pb-2 mb-3">
                                                <i class="fas fa-exclamation-triangle me-2"></i>Indicaciones Especiales
                                            </h6>

                                            <div class="mb-3">
                                                <label for="indicaciones_especiales" class="form-label">Instrucciones Adicionales</label>
                                                <textarea class="form-control" id="indicaciones_especiales" name="indicaciones_especiales" 
                                                          rows="4" placeholder="Instrucciones especiales para el paciente..."></textarea>
                                                <div class="form-text">Indicaciones especiales, precauciones, efectos secundarios a vigilar, etc.</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Plantillas rápidas -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <h6 class="text-secondary border-bottom pb-2 mb-3">
                                                <i class="fas fa-magic me-2"></i>Plantillas Rápidas
                                            </h6>

                                            <div class="row">
                                                <div class="col-md-4 mb-2">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                                            onclick="aplicarPlantilla('paracetamol')">
                                                        <i class="fas fa-thermometer-half me-1"></i>Paracetamol
                                                    </button>
                                                </div>
                                                <div class="col-md-4 mb-2">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                                            onclick="aplicarPlantilla('ibuprofeno')">
                                                        <i class="fas fa-hand-holding-medical me-1"></i>Ibuprofeno
                                                    </button>
                                                </div>
                                                <div class="col-md-4 mb-2">
                                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                                            onclick="aplicarPlantilla('amoxicilina')">
                                                        <i class="fas fa-capsules me-1"></i>Amoxicilina
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Botones de acción -->
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="d-flex justify-content-end gap-2">
                                                <a href="javascript:history.back()" class="btn btn-outline-secondary">
                                                    <i class="fas fa-times"></i> Cancelar
                                                </a>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Crear Receta
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Función para mostrar detalles de la consulta seleccionada
        function mostrarDetallesConsulta(consultaId) {
            const detallesDiv = document.getElementById('detallesConsulta');

            if (!consultaId) {
                detallesDiv.classList.add('d-none');
                return;
            }

            // Obtener la opción seleccionada
            const selectElement = document.getElementById('seleccionar_consulta');
            const selectedOption = selectElement.querySelector(`option[value="${consultaId}"]`);

            if (selectedOption) {
                // Llenar los detalles
                document.getElementById('detalle-paciente').textContent = selectedOption.dataset.paciente;
                document.getElementById('detalle-cedula').textContent = selectedOption.dataset.cedula;
                document.getElementById('detalle-telefono').textContent = selectedOption.dataset.telefono || 'No registrado';
                document.getElementById('detalle-fecha').textContent = selectedOption.dataset.fecha;
                document.getElementById('detalle-hora').textContent = selectedOption.dataset.hora;
                document.getElementById('detalle-especialidad').textContent = selectedOption.dataset.especialidad;
                document.getElementById('detalle-sucursal').textContent = selectedOption.dataset.sucursal;
                document.getElementById('detalle-recetas').textContent = selectedOption.dataset.recetas;

                // Solo mostrar médico si es admin
    <?php if ($_SESSION['role_id'] == 1): ?>
                    document.getElementById('detalle-medico').textContent = selectedOption.dataset.medico;
    <?php endif; ?>

            // Mostrar motivo y diagnóstico si existen
            const motivoContainer = document.getElementById('detalle-motivo-container');
            const diagnosticoContainer = document.getElementById('detalle-diagnostico-container');

            if (selectedOption.dataset.motivo) {
                document.getElementById('detalle-motivo').textContent = selectedOption.dataset.motivo;
                motivoContainer.style.display = 'block';
            } else {
                motivoContainer.style.display = 'none';
            }

            if (selectedOption.dataset.diagnostico) {
                document.getElementById('detalle-diagnostico').textContent = selectedOption.dataset.diagnostico;
                diagnosticoContainer.style.display = 'block';
            } else {
                diagnosticoContainer.style.display = 'none';
            }

            // Mostrar el panel de detalles
            detallesDiv.classList.remove('d-none');

            // Scroll suave hacia el panel
            detallesDiv.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        }
    }

// Plantillas de medicamentos comunes
    const plantillas = {
        paracetamol: {
            medicamento: 'Paracetamol',
            concentracion: '500mg',
            forma_farmaceutica: 'Tabletas',
            dosis: '1 tableta',
            frecuencia: 'Cada 8 horas',
            duracion: '3-5 días',
            cantidad: '15 tabletas',
            indicaciones_especiales: 'Tomar con abundante agua. No exceder 3 gramos por día. Suspender si persiste la fiebre después de 3 días.'
        },
        ibuprofeno: {
            medicamento: 'Ibuprofeno',
            concentracion: '400mg',
            forma_farmaceutica: 'Tabletas',
            dosis: '1 tableta',
            frecuencia: 'Cada 8 horas',
            duracion: '3-5 días',
            cantidad: '15 tabletas',
            indicaciones_especiales: 'Tomar después de los alimentos para evitar irritación gástrica. Suspender si hay molestias estomacales.'
        },
        amoxicilina: {
            medicamento: 'Amoxicilina',
            concentracion: '500mg',
            forma_farmaceutica: 'Cápsulas',
            dosis: '1 cápsula',
            frecuencia: 'Cada 8 horas',
            duracion: '7 días',
            cantidad: '21 cápsulas',
            indicaciones_especiales: 'Completar todo el tratamiento aunque se sienta mejor. Tomar a horas regulares para mantener niveles constantes.'
        }
    };

    function aplicarPlantilla(tipo) {
        if (plantillas[tipo]) {
            const plantilla = plantillas[tipo];

            // Llenar los campos del formulario
            document.getElementById('medicamento').value = plantilla.medicamento;
            document.getElementById('concentracion').value = plantilla.concentracion;
            document.getElementById('forma_farmaceutica').value = plantilla.forma_farmaceutica;
            document.getElementById('dosis').value = plantilla.dosis;
            document.getElementById('frecuencia').value = plantilla.frecuencia;
            document.getElementById('duracion').value = plantilla.duracion;
            document.getElementById('cantidad').value = plantilla.cantidad;
            document.getElementById('indicaciones_especiales').value = plantilla.indicaciones_especiales;

            // Mostrar mensaje de confirmación
            const toast = document.createElement('div');
            toast.className = 'toast align-items-center text-white bg-success border-0 position-fixed top-0 end-0 m-3';
            toast.style.zIndex = '1055';
            toast.innerHTML = `
           <div class="d-flex">
               <div class="toast-body">
                   <i class="fas fa-check-circle me-2"></i>Plantilla aplicada: ${plantilla.medicamento}
               </div>
               <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
           </div>
       `;

            document.body.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();

            // Remover el toast después de que se oculte
            toast.addEventListener('hidden.bs.toast', function () {
                document.body.removeChild(toast);
            });
        }
    }

// Validación del formulario
    document.getElementById('formReceta').addEventListener('submit', function (e) {
        const consultaSeleccionada = document.getElementById('seleccionar_consulta');
        const medicamento = document.getElementById('medicamento').value.trim();
        const dosis = document.getElementById('dosis').value.trim();
        const frecuencia = document.getElementById('frecuencia').value;
        const duracion = document.getElementById('duracion').value.trim();
        const cantidad = document.getElementById('cantidad').value.trim();

        // Validar consulta seleccionada solo si el selector existe
        if (consultaSeleccionada && !consultaSeleccionada.value) {
            e.preventDefault();
            alert('Por favor seleccione una consulta médica para crear la receta');
            consultaSeleccionada.focus();
            return false;
        }

        if (!medicamento || !dosis || !frecuencia || !duracion || !cantidad) {
            e.preventDefault();
            alert('Por favor complete todos los campos obligatorios marcados con *');
            return false;
        }
    });

// Autocompletar forma farmacéutica basado en keywords
    document.getElementById('medicamento').addEventListener('input', function () {
        const medicamento = this.value.toLowerCase();
        const formaSelect = document.getElementById('forma_farmaceutica');

        if (medicamento.includes('jarabe') || medicamento.includes('suspensión')) {
            formaSelect.value = 'Jarabe';
        } else if (medicamento.includes('crema') || medicamento.includes('pomada')) {
            formaSelect.value = 'Crema';
        } else if (medicamento.includes('gotas')) {
            formaSelect.value = 'Gotas';
        } else if (medicamento.includes('cápsula')) {
            formaSelect.value = 'Cápsulas';
        }
    });
</script>

<style>
    .info-item {
        border-bottom: 1px solid #f8f9fa;
        padding-bottom: 8px;
    }

    .info-item:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .avatar {
        font-size: 1.5rem;
    }

    .toast {
        min-width: 300px;
    }
</style>                                            