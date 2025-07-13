<?php
// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Verificar permisos (todos los roles pueden imprimir recetas según sus permisos)
if (!in_array($_SESSION['role_id'], [1, 2, 3, 4])) {
    header('Location: index.php?action=dashboard');
    exit;
}

// CORREGIR LAS RUTAS
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/fpdf/fpdf.php';

$database = new Database();
$db = $database->getConnection();

// Obtener ID de la receta
$id_receta = (int)($_GET['id'] ?? 0);

if (!$id_receta) {
    header('Location: index.php?action=consultas/recetas');
    exit;
}

// Construir consulta con permisos según rol
$wherePermiso = '';
$paramsPermiso = ['id_receta' => $id_receta];

if ($_SESSION['role_id'] == 3) { // Médico solo sus recetas
    $wherePermiso = " AND cit.id_medico = :id_medico";
    $paramsPermiso['id_medico'] = $_SESSION['user_id'];
} elseif ($_SESSION['role_id'] == 4) { // Paciente solo sus recetas
    $wherePermiso = " AND cit.id_paciente = :id_paciente";
    $paramsPermiso['id_paciente'] = $_SESSION['user_id'];
} elseif ($_SESSION['role_id'] == 2) { // Recepcionista solo de su sucursal
    $wherePermiso = " AND cit.id_sucursal = (SELECT id_sucursal FROM usuarios WHERE id_usuario = :user_id)";
    $paramsPermiso['user_id'] = $_SESSION['user_id'];
}

// Consulta principal para obtener todos los detalles
$sql = "SELECT r.*, 
               CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
               p.cedula as paciente_cedula,
               p.telefono as paciente_telefono,
               p.email as paciente_email,
               p.fecha_nacimiento,
               CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
               m.telefono as medico_telefono,
               m.email as medico_email,
               e.nombre_especialidad,
               s.nombre_sucursal,
               s.direccion as sucursal_direccion,
               s.telefono as sucursal_telefono,
               s.email as sucursal_email,
               cit.fecha_cita,
               cit.hora_cita,
               cit.motivo_consulta,
               c.diagnostico_principal,
               c.tratamiento,
               c.observaciones_medicas
        FROM recetas r
        INNER JOIN consultas c ON r.id_consulta = c.id_consulta
        INNER JOIN citas cit ON c.id_cita = cit.id_cita
        INNER JOIN usuarios p ON cit.id_paciente = p.id_usuario
        INNER JOIN usuarios m ON cit.id_medico = m.id_usuario
        INNER JOIN especialidades e ON cit.id_especialidad = e.id_especialidad
        INNER JOIN sucursales s ON cit.id_sucursal = s.id_sucursal
        WHERE r.id_receta = :id_receta" . $wherePermiso;

$stmt = $db->prepare($sql);
$stmt->execute($paramsPermiso);
$receta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$receta) {
    header('Location: index.php?action=consultas/recetas');
    exit;
}

// FUNCIÓN PARA CONVERTIR TEXTO SIN utf8_decode (REEMPLAZA utf8_decode)
function convertirTexto($texto) {
    // En lugar de utf8_decode, usar iconv que es más moderno
    if (!$texto) return '';
    return iconv('UTF-8', 'ISO-8859-1//IGNORE', $texto);
}

// Función para calcular edad
function calcularEdad($fechaNacimiento) {
    if (!$fechaNacimiento) return 'N/A';
    $nacimiento = new DateTime($fechaNacimiento);
    $hoy = new DateTime();
    return $nacimiento->diff($hoy)->y . ' años';
}

// Clase personalizada para la receta
class RecetaPDF extends FPDF {
    private $receta;
    
    function __construct($receta) {
        parent::__construct();
        $this->receta = $receta;
    }
    
    // Encabezado de página
    function Header() {
        // Logo o nombre de la clínica
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(0, 102, 204);
        $this->Cell(0, 15, convertirTexto('CLÍNICA SJ'), 0, 1, 'C');
        
        $this->SetFont('Arial', '', 12);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 8, convertirTexto($this->receta['nombre_sucursal']), 0, 1, 'C');
        $this->Cell(0, 6, convertirTexto($this->receta['sucursal_direccion']), 0, 1, 'C');
        $this->Cell(0, 6, convertirTexto('Teléfono: ' . $this->receta['sucursal_telefono']), 0, 1, 'C');
        
        $this->Ln(10);
        
        // Título de receta
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 12, convertirTexto('RECETA MÉDICA'), 0, 1, 'C');
        
        $this->Ln(5);
    }
    
    // Pie de página
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, convertirTexto('Receta médica - Generado el ' . date('d/m/Y H:i')), 0, 0, 'C');
    }
    
    // Función para agregar información en formato de tabla
    function addInfoSection($title, $data) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(240, 248, 255);
        $this->SetTextColor(0, 102, 204);
        $this->Cell(0, 8, convertirTexto($title), 1, 1, 'L', true);
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        
        foreach ($data as $label => $value) {
            if ($value) {
                $this->Cell(50, 6, convertirTexto($label . ':'), 0, 0, 'L');
                $this->Cell(0, 6, convertirTexto($value), 0, 1, 'L');
            }
        }
        $this->Ln(3);
    }
    
    // Función para texto multilínea
    function addMultilineText($title, $text, $maxWidth = 0) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(240, 248, 255);
        $this->SetTextColor(0, 102, 204);
        $this->Cell(0, 8, convertirTexto($title), 1, 1, 'L', true);
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        
        // Dividir el texto en líneas si es muy largo
        $words = explode(' ', $text);
        $line = '';
        
        foreach ($words as $word) {
            $testLine = $line . $word . ' ';
            if ($this->GetStringWidth(convertirTexto($testLine)) > 180) {
                $this->Cell(0, 6, convertirTexto(trim($line)), 1, 1, 'L');
                $line = $word . ' ';
            } else {
                $line = $testLine;
            }
        }
        if ($line) {
            $this->Cell(0, 6, convertirTexto(trim($line)), 1, 1, 'L');
        }
        $this->Ln(3);
    }
}

// Crear instancia del PDF
$pdf = new RecetaPDF($receta);
$pdf->AddPage();

// Información del documento
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 8, convertirTexto('Receta N°: ' . $receta['codigo_receta']), 0, 0, 'L');
$pdf->Cell(0, 8, convertirTexto('Fecha: ' . date('d/m/Y', strtotime($receta['fecha_emision']))), 0, 1, 'R');
$pdf->Ln(3);

// Información del Paciente
$edadPaciente = calcularEdad($receta['fecha_nacimiento']);
$pdf->addInfoSection('INFORMACIÓN DEL PACIENTE', [
    'Nombre' => $receta['paciente_nombre'],
    'Cédula' => $receta['paciente_cedula'],
    'Edad' => $edadPaciente,
    'Teléfono' => $receta['paciente_telefono']
]);

// Información del Médico
$pdf->addInfoSection('MÉDICO PRESCRIPTOR', [
    'Nombre' => $receta['medico_nombre'],
    'Especialidad' => $receta['nombre_especialidad'],
    'Teléfono' => $receta['medico_telefono']
]);

// Información del Medicamento (destacada)
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetFillColor(255, 248, 220);
$pdf->SetTextColor(204, 102, 0);
$pdf->Cell(0, 10, convertirTexto('MEDICAMENTO PRESCRITO'), 1, 1, 'C', true);

$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, convertirTexto($receta['medicamento']), 0, 1, 'C');

// Detalles del medicamento en tabla
$pdf->SetFont('Arial', '', 11);
$pdf->SetFillColor(248, 248, 248);

// Fila 1
$pdf->Cell(63, 8, convertirTexto('Concentración'), 1, 0, 'C', true);
$pdf->Cell(64, 8, convertirTexto('Forma Farmacéutica'), 1, 0, 'C', true);
$pdf->Cell(63, 8, convertirTexto('Cantidad'), 1, 1, 'C', true);

$pdf->Cell(63, 8, convertirTexto($receta['concentracion'] ?: 'N/E'), 1, 0, 'C');
$pdf->Cell(64, 8, convertirTexto($receta['forma_farmaceutica'] ?: 'N/E'), 1, 0, 'C');
$pdf->Cell(63, 8, convertirTexto($receta['cantidad'] ?: 'N/E'), 1, 1, 'C');

// Fila 2
$pdf->Cell(63, 8, convertirTexto('Dosis'), 1, 0, 'C', true);
$pdf->Cell(64, 8, convertirTexto('Frecuencia'), 1, 0, 'C', true);
$pdf->Cell(63, 8, convertirTexto('Duración'), 1, 1, 'C', true);

$pdf->Cell(63, 8, convertirTexto($receta['dosis'] ?: 'N/E'), 1, 0, 'C');
$pdf->Cell(64, 8, convertirTexto($receta['frecuencia'] ?: 'N/E'), 1, 0, 'C');
$pdf->Cell(63, 8, convertirTexto($receta['duracion'] ?: 'N/E'), 1, 1, 'C');

$pdf->Ln(5);

// Indicaciones especiales
if ($receta['indicaciones_especiales']) {
    $pdf->addMultilineText('INDICACIONES ESPECIALES:', $receta['indicaciones_especiales']);
}

// Información de la consulta
$pdf->addInfoSection('INFORMACIÓN DE LA CONSULTA', [
    'Fecha de Consulta' => date('d/m/Y', strtotime($receta['fecha_cita'])),
    'Diagnóstico' => $receta['diagnostico_principal'],
    'Motivo' => $receta['motivo_consulta']
]);

// Información adicional
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(204, 0, 0);
$pdf->Cell(0, 6, convertirTexto('INFORMACIÓN IMPORTANTE:'), 0, 1, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 5, convertirTexto('• Esta receta es válida hasta: ' . date('d/m/Y', strtotime($receta['fecha_vencimiento']))), 0, 1, 'L');
$pdf->Cell(0, 5, convertirTexto('• Estado actual: ' . strtoupper($receta['estado'])), 0, 1, 'L');
$pdf->Cell(0, 5, convertirTexto('• Siga las indicaciones médicas al pie de la letra'), 0, 1, 'L');
$pdf->Cell(0, 5, convertirTexto('• En caso de reacciones adversas, suspenda el medicamento y consulte'), 0, 1, 'L');

$pdf->Ln(10);

// Línea de firma
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, convertirTexto('_________________________________'), 0, 1, 'R');
$pdf->Cell(0, 5, convertirTexto('Firma y sello del médico'), 0, 1, 'R');
$pdf->Cell(0, 5, convertirTexto('Dr(a). ' . $receta['medico_nombre']), 0, 1, 'R');

// Configurar headers para descarga del PDF
$filename = 'Receta_' . $receta['codigo_receta'] . '_' . date('Ymd') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Generar y mostrar el PDF
$pdf->Output('I', $filename);
?>