<?php
require_once 'config/database.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

$pagoId = $_GET['pago_id'] ?? '';

if (empty($pagoId)) {
    header('HTTP/1.0 400 Bad Request');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Verificar que el pago pertenece al usuario (si es paciente)
$sql = "SELECT p.archivo_pdf, p.nombre_archivo, p.tipo_pdf, c.id_paciente 
        FROM pagos p 
        JOIN citas c ON p.id_cita = c.id_cita 
        WHERE p.id_pago = :pago_id";

$stmt = $db->prepare($sql);
$stmt->execute(['pago_id' => $pagoId]);
$pago = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pago) {
    header('HTTP/1.0 404 Not Found');
    echo "PDF no encontrado";
    exit;
}

// Verificar permisos (pacientes solo pueden ver sus propios PDFs)
if ($_SESSION['role_id'] == 4 && $pago['id_paciente'] != $_SESSION['user_id']) {
    header('HTTP/1.0 403 Forbidden');
    echo "No tiene permisos para acceder a este archivo";
    exit;
}

if (empty($pago['archivo_pdf'])) {
    header('HTTP/1.0 404 Not Found');
    echo "El archivo PDF no está disponible";
    exit;
}

// Enviar PDF
$pdfContent = base64_decode($pago['archivo_pdf']);
$nombreArchivo = $pago['nombre_archivo'] ?: 'documento.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Content-Length: ' . strlen($pdfContent));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $pdfContent;
exit;
?>