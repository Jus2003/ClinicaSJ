<?php
require_once 'models/Cita.php';
require_once 'config/database.php';
require_once 'includes/pdf_generator.php';

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
$mostrarPdf = false;
$pdfContent = '';
$nombreArchivo = '';
$idPago = null;

// Información bancaria para transferencias
$cuentasBancarias = [
    'Banco Pichincha' => '2100123456',
    'Banco Guayaquil' => '0123456789',
    'Banco Pacífico' => '4567890123'
];

// Procesar pago
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $metodoPago = $_POST['metodo_pago'] ?? '';
        $numeroTransaccion = $_POST['numero_transaccion'] ?? '';
        $datosTarjeta = [];
        
        if (empty($metodoPago)) {
            throw new Exception('Debe seleccionar un método de pago');
        }

        // Procesar datos de tarjeta si es necesario
        if ($metodoPago === 'tarjeta') {
            $datosTarjeta = [
                'numero' => $_POST['numero_tarjeta'] ?? '',
                'nombre' => $_POST['nombre_tarjeta'] ?? '',
                'expiracion' => $_POST['expiracion_tarjeta'] ?? '',
                'cvv' => $_POST['cvv_tarjeta'] ?? ''
            ];
            
            // Validar datos de tarjeta
            if (empty($datosTarjeta['numero']) || empty($datosTarjeta['nombre']) || 
                empty($datosTarjeta['expiracion']) || empty($datosTarjeta['cvv'])) {
                throw new Exception('Todos los campos de la tarjeta son obligatorios');
            }
            
            // Generar número de transacción simulado
            $numeroTransaccion = 'TXN-' . date('Ymd') . '-' . rand(100000, 999999);
        }

        // Verificar que la cita pertenece al paciente
        $sqlVerificar = "SELECT c.*, e.precio_consulta, CONCAT(m.nombre, ' ', m.apellido) as medico_nombre, 
                                e.nombre_especialidad, s.nombre_sucursal, CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                                p.cedula as paciente_cedula
                         FROM citas c 
                         JOIN especialidades e ON c.id_especialidad = e.id_especialidad
                         JOIN usuarios m ON c.id_medico = m.id_usuario
                         JOIN sucursales s ON c.id_sucursal = s.id_sucursal
                         JOIN usuarios p ON c.id_paciente = p.id_usuario
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

        // Determinar estado del pago
        $estadoPago = ($metodoPago === 'efectivo') ? 'pendiente' : 'pagado';

        // Insertar pago
        $sqlPago = "INSERT INTO pagos (id_cita, monto, metodo_pago, estado_pago, 
                                     numero_transaccion, fecha_pago, id_usuario_registro, fecha_registro)
                    VALUES (:cita_id, :monto, :metodo_pago, :estado_pago, :numero_transaccion, 
                            :fecha_pago, :usuario_id, NOW())";

        $stmtPago = $db->prepare($sqlPago);
        $stmtPago->execute([
            'cita_id' => $citaId,
            'monto' => $citaData['precio_consulta'] ?? 35.00,
            'metodo_pago' => $metodoPago,
            'estado_pago' => $estadoPago,
            'numero_transaccion' => $numeroTransaccion,
            'fecha_pago' => ($metodoPago === 'efectivo') ? null : date('Y-m-d H:i:s'),
            'usuario_id' => $_SESSION['user_id']
        ]);

        $idPago = $db->lastInsertId();

        // Generar PDF
        $pdfGenerator = new PDFGenerator();
        $datosPago = [
            'id_pago' => $idPago,
            'monto' => $citaData['precio_consulta'] ?? 35.00,
            'metodo_pago' => $metodoPago,
            'numero_transaccion' => $numeroTransaccion,
            'estado_pago' => $estadoPago
        ];

        $datosCitaPdf = [
            'paciente_nombre' => $citaData['paciente_nombre'],
            'paciente_cedula' => $citaData['paciente_cedula'],
            'medico_nombre' => $citaData['medico_nombre'],
            'especialidad' => $citaData['nombre_especialidad'],
            'fecha_cita' => $citaData['fecha_cita'],
            'sucursal' => $citaData['nombre_sucursal']
        ];

        if ($metodoPago === 'efectivo') {
            $pdfContent = $pdfGenerator->generarOrdenPago($datosCitaPdf, $datosPago);
            $nombreArchivo = 'orden_pago_' . $idPago . '.pdf';
            $tipoPdf = 'orden_pago';
        } else {
            $pdfContent = $pdfGenerator->generarComprobantePago($datosCitaPdf, $datosPago);
            $nombreArchivo = 'comprobante_pago_' . $idPago . '.pdf';
            $tipoPdf = 'comprobante';
        }

        // Convertir PDF a base64 y guardar en BD
        $pdfBase64 = base64_encode($pdfContent);
        
        $sqlUpdatePdf = "UPDATE pagos SET archivo_pdf = :pdf_base64, tipo_pdf = :tipo_pdf, 
                         nombre_archivo = :nombre_archivo WHERE id_pago = :id_pago";
        $stmtUpdatePdf = $db->prepare($sqlUpdatePdf);
        $stmtUpdatePdf->execute([
            'pdf_base64' => $pdfBase64,
            'tipo_pdf' => $tipoPdf,
            'nombre_archivo' => $nombreArchivo,
            'id_pago' => $idPago
        ]);

        $mostrarPdf = true;
        
        if ($metodoPago === 'efectivo') {
            $success = "Orden de pago generada exitosamente. Presente este documento en recepción para realizar el pago.";
        } else {
            $success = "Pago procesado exitosamente. Su comprobante ha sido generado.";
        }
        
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
        <div class="col-lg-10">
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
                    
                    <?php if ($mostrarPdf && $idPago): ?>
                        <div class="mt-3">
                            <a href="index.php?action=descargar_pdf&pago_id=<?php echo $idPago; ?>" 
                               class="btn btn-info me-2" target="_blank">
                                <i class="fas fa-download"></i> Descargar PDF
                            </a>
                            <a href="index.php?action=citas/agenda" class="btn btn-primary">
                                <i class="fas fa-calendar"></i> Volver a Mi Agenda
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="mt-3">
                            <a href="index.php?action=citas/agenda" class="btn btn-primary">
                                <i class="fas fa-calendar"></i> Volver a Mi Agenda
                            </a>
                        </div>
                    <?php endif; ?>
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
                                <p><strong>Médico:</strong> <?php echo $cita['medico_nombre']; ?></p>
                                <p><strong>Especialidad:</strong> <?php echo $cita['nombre_especialidad']; ?></p>
                                <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Hora:</strong> <?php echo date('H:i', strtotime($cita['fecha_cita'])); ?></p>
                                <p><strong>Sucursal:</strong> <?php echo $cita['nombre_sucursal']; ?></p>
                                <h4 class="text-success">
                                    <strong>Total a pagar: $<?php echo number_format($cita['precio_consulta'] ?? 35.00, 2); ?></strong>
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($cita['estado_pago'] === 'pagado'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Esta cita ya se encuentra pagada.
                    </div>
                <?php else: ?>
                    <!-- Formulario de pago mejorado -->
                    <div class="card shadow">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-credit-card"></i> Realizar Pago
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="formPago">
                                <!-- Selección de método de pago -->
                                <div class="mb-4">
                                    <label class="form-label h6">Método de Pago <span class="text-danger">*</span></label>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <div class="card metodo-pago" onclick="seleccionarMetodo('efectivo')" data-metodo="efectivo">
                                                <div class="card-body text-center">
                                                    <i class="fas fa-money-bill-wave fa-3x text-success mb-2"></i>
                                                    <h6>Efectivo</h6>
                                                    <small class="text-muted">Pago en recepción</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="card metodo-pago" onclick="seleccionarMetodo('transferencia')" data-metodo="transferencia">
                                                <div class="card-body text-center">
                                                    <i class="fas fa-university fa-3x text-primary mb-2"></i>
                                                    <h6>Transferencia</h6>
                                                    <small class="text-muted">Pago bancario</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <div class="card metodo-pago" onclick="seleccionarMetodo('tarjeta')" data-metodo="tarjeta">
                                                <div class="card-body text-center">
                                                    <i class="fas fa-credit-card fa-3x text-warning mb-2"></i>
                                                    <h6>Tarjeta</h6>
                                                    <small class="text-muted">Débito/Crédito</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="metodo_pago" id="metodo_pago" required>
                                </div>

                                <!-- Información específica por método de pago -->
                                
                                <!-- Efectivo -->
                                <div id="info-efectivo" class="metodo-info d-none">
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle"></i> Instrucciones para pago en efectivo:</h6>
                                        <ul class="mb-0">
                                            <li>Se generará una orden de pago que debe presentar en recepción</li>
                                            <li>Realice el pago antes de su cita médica</li>
                                            <li>Conserve el comprobante que le entreguen</li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Transferencia -->
                                <div id="info-transferencia" class="metodo-info d-none">
                                    <div class="alert alert-primary">
                                        <h6><i class="fas fa-university"></i> Datos para transferencia bancaria:</h6>
                                        <div class="row">
                                            <?php foreach ($cuentasBancarias as $banco => $cuenta): ?>
                                            <div class="col-md-4 mb-2">
                                                <strong><?php echo $banco; ?>:</strong><br>
                                                <code><?php echo $cuenta; ?></code>
                                                <button type="button" class="btn btn-sm btn-outline-primary ms-1" 
                                                        onclick="copiarCuenta('<?php echo $cuenta; ?>')">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <small class="text-muted">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            Después de realizar la transferencia, presente el comprobante el día de su cita.
                                        </small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Número de Transferencia (Opcional)</label>
                                        <input type="text" class="form-control" name="numero_transaccion" 
                                               placeholder="Ingrese el número de referencia de su transferencia">
                                    </div>
                                </div>

                                <!-- Tarjeta -->
                                <div id="info-tarjeta" class="metodo-info d-none">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-shield-alt"></i> 
                                        <strong>Pago seguro con tarjeta</strong> (Simulación para demostración)
                                        <button type="button" class="btn btn-sm btn-info float-end" onclick="llenarDatosRapido()">
                                            <i class="fas fa-magic"></i> Llenar Rápido
                                        </button>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-8 mb-3">
                                            <label class="form-label">Número de Tarjeta</label>
                                            <input type="text" class="form-control" name="numero_tarjeta" id="numero_tarjeta"
                                                   placeholder="1234 5678 9012 3456" maxlength="19">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">CVV</label>
                                            <input type="text" class="form-control" name="cvv_tarjeta" id="cvv_tarjeta"
                                                   placeholder="123" maxlength="4">
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-8 mb-3">
                                            <label class="form-label">Nombre en la Tarjeta</label>
                                            <input type="text" class="form-control" name="nombre_tarjeta" id="nombre_tarjeta"
                                                   placeholder="JUAN PEREZ" style="text-transform: uppercase;">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Vencimiento</label>
                                            <input type="text" class="form-control" name="expiracion_tarjeta" id="expiracion_tarjeta"
                                                   placeholder="MM/YY" maxlength="5">
                                        </div>
                                    </div>
                                </div>

                                <!-- Botones de acción -->
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-success btn-lg me-2" id="btnPagar" disabled>
                                        <i class="fas fa-credit-card"></i> <span id="textoPagar">Seleccione método de pago</span>
                                    </button>
                                    <a href="index.php?action=citas/agenda" class="btn btn-secondary btn-lg">
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

<!-- Script para manejo del formulario -->
<script>
let metodoSeleccionado = '';

function seleccionarMetodo(metodo) {
    // Limpiar selecciones anteriores
    document.querySelectorAll('.metodo-pago').forEach(card => {
        card.classList.remove('border-success', 'bg-light');
    });
    
    document.querySelectorAll('.metodo-info').forEach(info => {
        info.classList.add('d-none');
    });
    
    // Seleccionar nuevo método
    metodoSeleccionado = metodo;
    document.getElementById('metodo_pago').value = metodo;
    
    const cardSeleccionada = document.querySelector(`[data-metodo="${metodo}"]`);
    cardSeleccionada.classList.add('border-success', 'bg-light');
    
    document.getElementById(`info-${metodo}`).classList.remove('d-none');
    
    // Actualizar botón
    const btnPagar = document.getElementById('btnPagar');
    const textoPagar = document.getElementById('textoPagar');
    
    btnPagar.disabled = false;
    
    switch(metodo) {
        case 'efectivo':
            textoPagar.textContent = 'Generar Orden de Pago';
            btnPagar.className = 'btn btn-warning btn-lg me-2';
            break;
        case 'transferencia':
            textoPagar.textContent = 'Registrar Transferencia';
            btnPagar.className = 'btn btn-primary btn-lg me-2';
            break;
        case 'tarjeta':
            textoPagar.textContent = 'Procesar Pago';
            btnPagar.className = 'btn btn-success btn-lg me-2';
            break;
    }
}

function copiarCuenta(numeroCuenta) {
    navigator.clipboard.writeText(numeroCuenta).then(() => {
        // Mostrar mensaje de copiado
        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.innerHTML = `<i class="fas fa-check"></i> Cuenta copiada: ${numeroCuenta}`;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            z-index: 9999;
            animation: slideIn 0.3s ease;
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 3000);
    });
}

function llenarDatosRapido() {
    // Datos falsos para pruebas rápidas
    document.getElementById('numero_tarjeta').value = '4532 1234 5678 9012';
    document.getElementById('nombre_tarjeta').value = 'JUAN PEREZ GARCIA';
    document.getElementById('expiracion_tarjeta').value = '12/28';
    document.getElementById('cvv_tarjeta').value = '123';
    
    // Mostrar mensaje
    const alert = document.createElement('div');
    alert.className = 'alert alert-info alert-dismissible fade show mt-2';
    alert.innerHTML = `
        <i class="fas fa-magic"></i> Datos de prueba cargados automáticamente
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.getElementById('info-tarjeta').appendChild(alert);
}

// Formateo automático de campos de tarjeta
document.addEventListener('DOMContentLoaded', function() {
    // Formatear número de tarjeta
    const numeroTarjeta = document.getElementById('numero_tarjeta');
    if (numeroTarjeta) {
        numeroTarjeta.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });
    }
    
    // Formatear fecha de expiración
    const expiracion = document.getElementById('expiracion_tarjeta');
    if (expiracion) {
        expiracion.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
    }
    
    // Solo números en CVV
    const cvv = document.getElementById('cvv_tarjeta');
    if (cvv) {
        cvv.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
    }
});

// Validación del formulario antes del envío
document.getElementById('formPago').addEventListener('submit', function(e) {
    if (metodoSeleccionado === 'tarjeta') {
        const campos = ['numero_tarjeta', 'nombre_tarjeta', 'expiracion_tarjeta', 'cvv_tarjeta'];
        let error = false;
        
        campos.forEach(campo => {
            const input = document.getElementById(campo);
            if (!input.value.trim()) {
                input.classList.add('is-invalid');
                error = true;
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');
            }
        });
        
        if (error) {
            e.preventDefault();
            alert('Por favor complete todos los campos de la tarjeta');
            return false;
        }
    }
    
    // Mostrar loading en el botón
    const btnPagar = document.getElementById('btnPagar');
    const textoOriginal = btnPagar.innerHTML;
    btnPagar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
    btnPagar.disabled = true;
});
</script>

<style>
.metodo-pago {
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid #dee2e6;
}

.metodo-pago:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.metodo-pago.border-success {
    border-color: #28a745 !important;
    box-shadow: 0 0 10px rgba(40, 167, 69, 0.3);
}

.metodo-info {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideIn {
    from { transform: translateX(100%); }
    to { transform: translateX(0); }
}

.is-invalid {
    border-color: #dc3545 !important;
}

.is-valid {
    border-color: #28a745 !important;
}
</style>