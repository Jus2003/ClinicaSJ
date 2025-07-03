<?php
require_once 'models/Cita.php';
require_once 'config/database.php';

// Verificar autenticación y rol de paciente
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    header('Location: index.php?action=login');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$citaModel = new Cita();

$citaId = $_GET['cita_id'] ?? '';
$error = '';
$success = '';

// Procesar pago
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $metodoPago = $_POST['metodo_pago'] ?? '';
        $numeroTransaccion = $_POST['numero_transaccion'] ?? '';

        if (empty($metodoPago)) {
            throw new Exception('Debe seleccionar un método de pago');
        }

        // Verificar que la cita pertenece al paciente
        $sqlVerificar = "SELECT c.*, e.precio_consulta, CONCAT(m.nombre, ' ', m.apellido) as medico_nombre, 
                                e.nombre_especialidad, s.nombre_sucursal
                         FROM citas c 
                         JOIN especialidades e ON c.id_especialidad = e.id_especialidad
                         JOIN usuarios m ON c.id_medico = m.id_usuario
                         JOIN sucursales s ON c.id_sucursal = s.id_sucursal
                         WHERE c.id_cita = :cita_id AND c.id_paciente = :paciente_id";

        $stmtVerificar = $db->prepare($sqlVerificar);
        $stmtVerificar->execute(['cita_id' => $citaId, 'paciente_id' => $_SESSION['user_id']]);
        $citaData = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

        if (!$citaData) {
            throw new Exception('Cita no encontrada');
        }

        // Verificar que no exista ya un pago
        $sqlPagoExistente = "SELECT id_pago FROM pagos WHERE id_cita = :cita_id";
        $stmtPagoExistente = $db->prepare($sqlPagoExistente);
        $stmtPagoExistente->execute(['cita_id' => $citaId]);

        if ($stmtPagoExistente->fetch()) {
            throw new Exception('Esta cita ya tiene un pago registrado');
        }

        // Insertar pago
        $sqlPago = "INSERT INTO pagos (id_cita, monto, metodo_pago, estado_pago, 
                                     numero_transaccion, fecha_pago, id_usuario_registro, fecha_registro)
                    VALUES (:cita_id, :monto, :metodo_pago, 'pagado', :numero_transaccion, 
                            NOW(), :usuario_id, NOW())";

        $stmtPago = $db->prepare($sqlPago);
        $stmtPago->execute([
            'cita_id' => $citaId,
            'monto' => $citaData['precio_consulta'] ?? 35.00,
            'metodo_pago' => $metodoPago,
            'numero_transaccion' => $numeroTransaccion,
            'usuario_id' => $_SESSION['user_id']
        ]);

        $success = "Pago registrado exitosamente. ¡Gracias por su pago!";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener datos de la cita
$sqlCita = "SELECT c.*, CONCAT(m.nombre, ' ', m.apellido) as medico_nombre, 
                   e.nombre_especialidad, e.precio_consulta, s.nombre_sucursal,
                   p.estado_pago
            FROM citas c 
            JOIN usuarios m ON c.id_medico = m.id_usuario
            JOIN especialidades e ON c.id_especialidad = e.id_especialidad
            JOIN sucursales s ON c.id_sucursal = s.id_sucursal
            LEFT JOIN pagos p ON c.id_cita = p.id_cita
            WHERE c.id_cita = :cita_id AND c.id_paciente = :paciente_id";

$stmtCita = $db->prepare($sqlCita);
$stmtCita->execute(['cita_id' => $citaId, 'paciente_id' => $_SESSION['user_id']]);
$cita = $stmtCita->fetch(PDO::FETCH_ASSOC);

if (!$cita) {
    header('Location: index.php?action=citas/agenda');
    exit;
}

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Header -->
            <div class="text-center mb-4">
                <h2 class="text-primary">
                    <i class="fas fa-credit-card"></i> Pago de Cita Médica
                </h2>
                <p class="text-muted">Complete el pago de su consulta médica</p>
            </div>

            <!-- Mensajes -->
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <div class="mt-3">
                        <a href="index.php?action=citas/agenda" class="btn btn-primary">
                            <i class="fas fa-calendar"></i> Volver a Mi Agenda
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <!-- Información de la cita -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-alt"></i> Detalles de la Cita
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></p>
                                <p><strong>Hora:</strong> <?php echo date('H:i', strtotime($cita['hora_cita'])); ?></p>
                                <p><strong>Médico:</strong> <?php echo $cita['medico_nombre']; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Especialidad:</strong> <?php echo $cita['nombre_especialidad']; ?></p>
                                <p><strong>Sucursal:</strong> <?php echo $cita['nombre_sucursal']; ?></p>
                                <p><strong>Tipo:</strong> <?php echo ucfirst($cita['tipo_cita']); ?></p>
                            </div>
                        </div>

                        <div class="text-center mt-3">
                            <h4 class="text-success">
                                Monto a pagar: $<?php echo number_format($cita['precio_consulta'] ?? 35.00, 2); ?>
                            </h4>
                        </div>
                    </div>
                </div>

                <?php if ($cita['estado_pago'] === 'pagado'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Esta cita ya se encuentra pagada.
                    </div>
                <?php else: ?>
                    <!-- Formulario de pago -->
                    <div class="card shadow">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-credit-card"></i> Realizar Pago
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Método de Pago <span class="text-danger">*</span></label>
                                            <select class="form-select" name="metodo_pago" required>
                                                <option value="">Seleccione...</option>
                                                <option value="efectivo">Efectivo</option>
                                                <option value="tarjeta">Tarjeta de Crédito/Débito</option>
                                                <option value="transferencia">Transferencia Bancaria</option>
                                                <option value="seguro">Seguro Médico</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Número de Transacción</label>
                                            <input type="text" class="form-control" name="numero_transaccion" 
                                                   placeholder="Opcional - Para tarjeta o transferencia">
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-credit-card"></i> Confirmar Pago
                                    </button>
                                    <a href="index.php?action=citas/agenda" class="btn btn-secondary btn-lg ms-2">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
</div>