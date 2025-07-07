<?php
// views/consultas/virtual/api/obtener_detalles.php

session_start();
header('Content-Type: text/html; charset=UTF-8');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [3, 4])) {
    echo '<div class="alert alert-danger">No autorizado</div>';
    exit;
}

require_once '../../../../models/Cita.php';

$citaId = $_GET['cita_id'] ?? 0;

if (!$citaId) {
    echo '<div class="alert alert-danger">ID de cita requerido</div>';
    exit;
}

try {
    $citaModel = new Cita();
    $cita = $citaModel->verificarAccesoConsultaVirtual($citaId, $_SESSION['user_id'], $_SESSION['role_id']);
    
    if (!$cita) {
        echo '<div class="alert alert-danger">Sin acceso a esta consulta</div>';
        exit;
    }
    
    // Obtener notas si es médico
    $notas = [];
    if ($_SESSION['role_id'] == 3) {
        require_once '../../../../config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        
        $sqlNotas = "SELECT contenido, fecha_nota FROM notas_consulta_virtual 
                     WHERE id_cita = :cita_id ORDER BY fecha_nota DESC";
        $stmtNotas = $db->prepare($sqlNotas);
        $stmtNotas->bindParam(':cita_id', $citaId);
        $stmtNotas->execute();
        $notas = $stmtNotas->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener mensajes del chat
    $sqlMensajes = "SELECT m.mensaje, m.fecha_mensaje, 
                           CONCAT(u.nombre, ' ', u.apellido) as autor,
                           CASE WHEN u.id_rol = 3 THEN 'Médico' ELSE 'Paciente' END as tipo_usuario
                    FROM mensajes_consulta_virtual m
                    INNER JOIN usuarios u ON m.id_usuario = u.id_usuario
                    WHERE m.id_cita = :cita_id
                    ORDER BY m.fecha_mensaje ASC
                    LIMIT 10";
    $stmtMensajes = $db->prepare($sqlMensajes);
    $stmtMensajes->bindParam(':cita_id', $citaId);
    $stmtMensajes->execute();
    $mensajes = $stmtMensajes->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="text-primary mb-3">
            <i class="fas fa-info-circle"></i> Información de la Consulta
        </h6>
        
        <table class="table table-borderless table-sm">
            <tr>
                <td><strong>Fecha:</strong></td>
                <td><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></td>
            </tr>
            <tr>
                <td><strong>Hora:</strong></td>
                <td><?php echo date('H:i', strtotime($cita['hora_cita'])); ?></td>
            </tr>
            <tr>
                <td><strong>Estado:</strong></td>
                <td>
                    <span class="badge badge-estado-<?php echo $cita['estado_cita']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $cita['estado_cita'])); ?>
                    </span>
                </td>
            </tr>
            <?php if ($_SESSION['role_id'] == 3): ?>
                <tr>
                    <td><strong>Paciente:</strong></td>
                    <td><?php echo htmlspecialchars($cita['paciente_nombre']); ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td><strong>Médico:</strong></td>
                    <td>Dr. <?php echo htmlspecialchars($cita['medico_nombre']); ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <td><strong>Especialidad:</strong></td>
                <td><?php echo htmlspecialchars($cita['nombre_especialidad']); ?></td>
            </tr>
            <tr>
                <td><strong>Motivo:</strong></td>
                <td><?php echo htmlspecialchars($cita['motivo_consulta']); ?></td>
            </tr>
            <?php if ($cita['enlace_virtual']): ?>
                <tr>
                    <td><strong>Enlace:</strong></td>
                    <td>
                        <span class="badge bg-success">
                            <i class="fas fa-check"></i> Generado
                        </span>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="text-primary mb-3">
            <i class="fas fa-comments"></i> Últimos Mensajes del Chat
        </h6>
        
        <?php if (empty($mensajes)): ?>
            <p class="text-muted">No hay mensajes en esta consulta.</p>
        <?php else: ?>
            <div style="max-height: 200px; overflow-y: auto;">
                <?php foreach ($mensajes as $mensaje): ?>
                    <div class="mb-2 p-2 border-start border-3 <?php echo $mensaje['tipo_usuario'] == 'Médico' ? 'border-primary' : 'border-info'; ?> bg-light">
                        <div class="d-flex justify-content-between">
                            <small class="fw-bold"><?php echo htmlspecialchars($mensaje['autor']); ?></small>
                            <small class="text-muted"><?php echo date('H:i', strtotime($mensaje['fecha_mensaje'])); ?></small>
                        </div>
                        <div class="mt-1"><?php echo htmlspecialchars($mensaje['mensaje']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($_SESSION['role_id'] == 3 && !empty($notas)): ?>
    <hr class="my-4">
    <div class="row">
        <div class="col-12">
            <h6 class="text-primary mb-3">
                <i class="fas fa-sticky-note"></i> Notas Médicas
            </h6>
            
            <?php foreach ($notas as $nota): ?>
                <div class="card border-0 bg-light mb-2">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo date('d/m/Y H:i', strtotime($nota['fecha_nota'])); ?>
                            </small>
                        </div>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($nota['contenido'])); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="modal-footer">
    <?php if ($cita['estado_cita'] == 'confirmada' && $cita['enlace_virtual']): ?>
        <a href="index.php?action=consultas/virtual/sala&cita=<?php echo $cita['id_cita']; ?>" 
           class="btn btn-success" target="_blank">
            <i class="fas fa-video"></i> Ingresar a Consulta
        </a>
    <?php endif; ?>
    
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
        <i class="fas fa-times"></i> Cerrar
    </button>
</div>

<style>
.badge-estado-confirmada { background-color: #28a745; }
.badge-estado-en_curso { background-color: #ffc107; color: #000; }
.badge-estado-completada { background-color: #007bff; }
.badge-estado-agendada { background-color: #6c757d; }
.badge-estado-cancelada { background-color: #dc3545; }
.badge-estado-no_asistio { background-color: #6c757d; }
</style>