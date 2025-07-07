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

require_once 'config/database.php';
require_once 'includes/fpdf/fpdf.php';

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

// Función para calcular edad
function calcularEdad($fechaNacimiento) {
    if (!$fechaNacimiento) return 'N/A';
    $nacimiento = new DateTime($fechaNacimiento);
    $hoy = new DateTime();
    return $nacimiento->diff($hoy)->y . ' años';
}

// Crear clase PDF personalizada
class RecetaPDF extends FPDF {
    private $receta;
    
    public function __construct($receta) {
        parent::__construct();
        $this->receta = $receta;
    }
    
    // Header de la página
    function Header() {
        // Logo o nombre de la clínica
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(0, 102, 204);
        $this->Cell(0, 10, utf8_decode('Clínica San José'), 0, 1, 'C');
        
        // Información de la sucursal
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 5, utf8_decode($this->receta['nombre_sucursal']), 0, 1, 'C');
        $this->Cell(0, 5, utf8_decode($this->receta['sucursal_direccion']), 0, 1, 'C');
        
        $contacto = '';
        if ($this->receta['sucursal_telefono']) {
            $contacto .= 'Tel: ' . $this->receta['sucursal_telefono'];
        }
        if ($this->receta['sucursal_email']) {
            $contacto .= ($contacto ? ' | ' : '') . 'Email: ' . $this->receta['sucursal_email'];
        }
        if ($contacto) {
            $this->Cell(0, 5, $contacto, 0, 1, 'C');
        }
        
        // Línea separadora
        $this->Ln(5);
        $this->SetDrawColor(0, 102, 204);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(10);
        
        // Título RECETA MÉDICA
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 10, utf8_decode('RECETA MÉDICA'), 0, 1, 'C');
        $this->Ln(5);
    }
    
    // Footer de la página
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . ' - Generado el ' . date('d/m/Y H:i'), 0, 0, 'C');
    }
    
    // Función para agregar información en formato de tabla
    function addInfoSection($title, $data) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(240, 248, 255);
        $this->SetTextColor(0, 102, 204);
        $this->Cell(0, 8, utf8_decode($title), 1, 1, 'L', true);
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        
        foreach ($data as $label => $value) {
            if ($value) {
                $this->Cell(50, 6, utf8_decode($label . ':'), 1, 0, 'L');
                $this->Cell(0, 6, utf8_decode($value), 1, 1, 'L');
            }
        }
        $this->Ln(3);
    }
    
    // Función para texto multilinea
    function addMultilineText($title, $text, $maxWidth = 180) {
        if (!$text) return;
        
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(0, 102, 204);
        $this->Cell(0, 7, utf8_decode($title), 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        
        // Dividir el texto en líneas
        $lines = explode("\n", $text);
        foreach ($lines as $line) {
            if (strlen($line) > 90) {
                // Dividir líneas muy largas
                $words = explode(' ', $line);
                $currentLine = '';
                foreach ($words as $word) {
                    if (strlen($currentLine . ' ' . $word) <= 90) {
                        $currentLine .= ($currentLine ? ' ' : '') . $word;
                    } else {
                        if ($currentLine) {
                            $this->Cell(0, 5, utf8_decode($currentLine), 0, 1, 'L');
                        }
                        $currentLine = $word;
                    }
                }
                if ($currentLine) {
                    $this->Cell(0, 5, utf8_decode($currentLine), 0, 1, 'L');
                }
            } else {
                $this->Cell(0, 5, utf8_decode($line), 0, 1, 'L');
            }
        }
        $this->Ln(3);
    }
}

// Crear PDF
$pdf = new RecetaPDF($receta);
$pdf->AddPage();

// Información del código y fecha
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(100, 8, utf8_decode('Código de Receta: ' . $receta['codigo_receta']), 0, 0, 'L');
$pdf->Cell(0, 8, utf8_decode('Fecha: ' . date('d/m/Y', strtotime($receta['fecha_emision']))), 0, 1, 'R');
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
$pdf->Cell(0, 10, utf8_decode('MEDICAMENTO PRESCRITO'), 1, 1, 'C', true);

$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, utf8_decode($receta['medicamento']), 0, 1, 'C');

// Detalles del medicamento en tabla
$pdf->SetFont('Arial', '', 11);
$pdf->SetFillColor(248, 248, 248);

// Fila 1
$pdf->Cell(63, 8, utf8_decode('Concentración'), 1, 0, 'C', true);
$pdf->Cell(64, 8, utf8_decode('Forma Farmacéutica'), 1, 0, 'C', true);
$pdf->Cell(63, 8, utf8_decode('Cantidad'), 1, 1, 'C', true);

$pdf->Cell(63, 8, utf8_decode($receta['concentracion'] ?: 'N/E'), 1, 0, 'C');
$pdf->Cell(64, 8, utf8_decode($receta['forma_farmaceutica'] ?: 'N/E'), 1, 0, 'C');
$pdf->Cell(63, 8, utf8_decode($receta['cantidad']), 1, 1, 'C');

$pdf->Ln(2);

// Fila 2
$pdf->Cell(63, 8, utf8_decode('Dosis'), 1, 0, 'C', true);
$pdf->Cell(64, 8, utf8_decode('Frecuencia'), 1, 0, 'C', true);
$pdf->Cell(63, 8, utf8_decode('Duración'), 1, 1, 'C', true);

$pdf->Cell(63, 8, utf8_decode($receta['dosis']), 1, 0, 'C');
$pdf->Cell(64, 8, utf8_decode($receta['frecuencia']), 1, 0, 'C');
$pdf->Cell(63, 8, utf8_decode($receta['duracion']), 1, 1, 'C');

$pdf->Ln(5);

// Indicaciones especiales
if ($receta['indicaciones_especiales']) {
    $pdf->addMultilineText('INDICACIONES ESPECIALES:', $receta['indicaciones_especiales']);
}

// Información de la consulta
if ($receta['diagnostico_principal']) {
    $pdf->addMultilineText('DIAGNÓSTICO:', $receta['diagnostico_principal']);
}

// Fechas importantes
$pdf->addInfoSection('INFORMACIÓN ADICIONAL', [
    'Fecha de Consulta' => date('d/m/Y', strtotime($receta['fecha_cita'])),
    'Fecha de Emisión' => date('d/m/Y H:i', strtotime($receta['fecha_emision'])),
    'Fecha de Vencimiento' => date('d/m/Y', strtotime($receta['fecha_vencimiento'])),
    'Estado' => ucfirst($receta['estado'])
]);

// Advertencias legales
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(220, 20, 20);
$pdf->Cell(0, 6, utf8_decode('ADVERTENCIAS IMPORTANTES:'), 0, 1, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0, 0, 0);
$advertencias = [
    '• Siga exactamente las indicaciones del médico',
    '• No suspenda el tratamiento sin consultar',
    '• Si presenta efectos adversos, consulte inmediatamente',
    '• Conserve en lugar fresco y seco',
    '• Mantenga fuera del alcance de los niños',
    '• Esta receta tiene validez hasta la fecha de vencimiento indicada'
];

foreach ($advertencias as $advertencia) {
    $pdf->Cell(0, 4, utf8_decode($advertencia), 0, 1, 'L');
}

// Línea de firma (solo si es receta activa)
if ($receta['estado'] === 'activa') {
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, utf8_decode('_______________________________'), 0, 1, 'R');
    $pdf->Cell(0, 6, utf8_decode('Firma del Médico'), 0, 1, 'R');
    $pdf->Cell(0, 6, utf8_decode($receta['medico_nombre']), 0, 1, 'R');
    $pdf->Cell(0, 6, utf8_decode($receta['nombre_especialidad']), 0, 1, 'R');
}

// Configurar headers para descarga
$filename = 'Receta_' . $receta['codigo_receta'] . '_' . date('Y-m-d') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Salida del PDF
$pdf->Output('D', $filename);
exit;
?>