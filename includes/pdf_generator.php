<?php
require_once __DIR__ . '/fpdf/fpdf.php';

class PDFGenerator extends FPDF {
    private $clinicaInfo;
    
    public function __construct() {
        parent::__construct();
        $this->clinicaInfo = $this->getClinicaInfo();
    }
    
    private function getClinicaInfo() {
        return [
            'nombre' => 'Clínica Médica Integral',
            'direccion' => 'Av. 10 de Agosto y Patria, Quito',
            'telefono' => '+593-2-2234567',
            'email' => 'clinicasj.sistema@gmail.com',
            'ruc' => '1792146739001'
        ];
    }
    
    // Header del PDF
    function Header() {
        $this->SetFont('Times', 'B', 16);
        $this->Cell(0, 10, $this->convertirTexto(strtoupper($this->clinicaInfo['nombre'])), 0, 1, 'C');
        $this->SetFont('Times', '', 10);
        $this->Cell(0, 5, $this->convertirTexto($this->clinicaInfo['direccion']), 0, 1, 'C');
        $this->Cell(0, 5, $this->convertirTexto('Tel: ' . $this->clinicaInfo['telefono'] . ' | Email: ' . $this->clinicaInfo['email']), 0, 1, 'C');
        $this->Cell(0, 5, $this->convertirTexto('RUC: ' . $this->clinicaInfo['ruc']), 0, 1, 'C');
        $this->Ln(10);
    }
    
    // Footer del PDF
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Times', 'I', 8);
        $this->Cell(0, 10, $this->convertirTexto('Documento generado automáticamente el ' . date('d/m/Y H:i:s')), 0, 0, 'C');
    }
    
    // Función para convertir texto con tildes
    private function convertirTexto($texto) {
        return iconv('UTF-8', 'windows-1252//IGNORE', $texto);
    }
    
    public function generarOrdenPago($datosCita, $datosPago) {
        $this->AddPage();
        
        // Título
        $this->SetFont('Times', 'B', 14);
        $this->Cell(0, 10, $this->convertirTexto('ORDEN DE PAGO'), 0, 1, 'C');
        $this->Ln(5);
        
        // Información del pago
        $this->SetFont('Times', '', 10);
        $this->Cell(40, 8, $this->convertirTexto('Orden N°:'), 1, 0, 'L');
        $this->Cell(60, 8, 'OP-' . str_pad($datosPago['id_pago'], 6, '0', STR_PAD_LEFT), 1, 0, 'L');
        $this->Cell(40, 8, 'Fecha:', 1, 0, 'L');
        $this->Cell(50, 8, date('d/m/Y H:i'), 1, 1, 'L');
        $this->Ln(5);
        
        // Información del paciente
        $this->SetFont('Times', 'B', 12);
        $this->Cell(0, 8, $this->convertirTexto('DATOS DEL PACIENTE'), 0, 1, 'L');
        $this->SetFont('Times', '', 10);
        $this->Cell(40, 6, $this->convertirTexto('Paciente:'), 0, 0, 'L');
        $this->Cell(0, 6, $this->convertirTexto($datosCita['paciente_nombre']), 0, 1, 'L');
        $this->Cell(40, 6, $this->convertirTexto('Cédula:'), 0, 0, 'L');
        $this->Cell(0, 6, $this->convertirTexto($datosCita['paciente_cedula']), 0, 1, 'L');
        $this->Ln(5);
        
        // Información de la cita
        $this->SetFont('Times', 'B', 12);
        $this->Cell(0, 8, $this->convertirTexto('DATOS DE LA CITA'), 0, 1, 'L');
        $this->SetFont('Times', '', 10);
        $this->Cell(40, 6, $this->convertirTexto('Médico:'), 0, 0, 'L');
        $this->Cell(0, 6, $this->convertirTexto($datosCita['medico_nombre']), 0, 1, 'L');
        $this->Cell(40, 6, 'Especialidad:', 0, 0, 'L');
        $this->Cell(0, 6, $this->convertirTexto($datosCita['especialidad']), 0, 1, 'L');
        $this->Cell(40, 6, 'Fecha y Hora:', 0, 0, 'L');
        $this->Cell(0, 6, date('d/m/Y H:i', strtotime($datosCita['fecha_cita'])), 0, 1, 'L');
        $this->Cell(40, 6, 'Sucursal:', 0, 0, 'L');
        $this->Cell(0, 6, $this->convertirTexto($datosCita['sucursal']), 0, 1, 'L');
        $this->Ln(10);
        
        // Detalle del pago
        $this->SetFont('Times', 'B', 12);
        $this->Cell(0, 8, $this->convertirTexto('DETALLE DEL PAGO'), 0, 1, 'L');
        
        // Tabla de conceptos
        $this->SetFont('Times', 'B', 10);
        $this->Cell(100, 8, 'CONCEPTO', 1, 0, 'C');
        $this->Cell(40, 8, 'CANTIDAD', 1, 0, 'C');
        $this->Cell(50, 8, 'VALOR', 1, 1, 'C');
        
        $this->SetFont('Times', '', 10);
        $this->Cell(100, 8, $this->convertirTexto('Consulta ' . $datosCita['especialidad']), 1, 0, 'L');
        $this->Cell(40, 8, '1', 1, 0, 'C');
        $this->Cell(50, 8, '$' . number_format($datosPago['monto'], 2), 1, 1, 'R');
        
        $this->SetFont('Times', 'B', 10);
        $this->Cell(140, 8, 'TOTAL A PAGAR:', 1, 0, 'R');
        $this->Cell(50, 8, '$' . number_format($datosPago['monto'], 2), 1, 1, 'R');
        $this->Ln(10);
        
        // Instrucciones
        $this->SetFont('Times', 'B', 12);
        $this->Cell(0, 8, $this->convertirTexto('INSTRUCCIONES PARA EL PAGO'), 0, 1, 'L');
        $this->SetFont('Times', '', 10);
        $this->Cell(0, 6, $this->convertirTexto('1. Presente esta orden de pago en recepción antes de su cita.'), 0, 1, 'L');
        $this->Cell(0, 6, $this->convertirTexto('2. El pago debe realizarse en efectivo exacto.'), 0, 1, 'L');
        $this->Cell(0, 6, $this->convertirTexto('3. Solicite su comprobante de pago al momento de cancelar.'), 0, 1, 'L');
        $this->Cell(0, 6, $this->convertirTexto('4. Esta orden es válida únicamente para la fecha y hora indicadas.'), 0, 1, 'L');
        $this->Ln(10);
        
        // Nota importante
        $this->SetFont('Times', 'B', 10);
        $this->Cell(0, 8, $this->convertirTexto('IMPORTANTE:'), 0, 1, 'L');
        $this->SetFont('Times', '', 9);
        $this->Cell(0, 5, $this->convertirTexto('- El pago debe realizarse 15 minutos antes de la cita.'), 0, 1, 'L');
        $this->Cell(0, 5, $this->convertirTexto('- En caso de no asistir, deberá reagendar su cita.'), 0, 1, 'L');
        
        return $this->Output('S');
    }
    
    public function generarComprobantePago($datosCita, $datosPago) {
        $this->AddPage();
        
        // Título
        $this->SetFont('Times', 'B', 14);
        $this->Cell(0, 10, $this->convertirTexto('COMPROBANTE DE PAGO'), 0, 1, 'C');
        $this->Ln(5);
        
        // Información del comprobante
        $this->SetFont('Times', '', 10);
        $this->Cell(40, 8, $this->convertirTexto('Comprobante N°:'), 1, 0, 'L');
        $this->Cell(60, 8, 'CP-' . str_pad($datosPago['id_pago'], 6, '0', STR_PAD_LEFT), 1, 0, 'L');
        $this->Cell(40, 8, 'Fecha Pago:', 1, 0, 'L');
        $this->Cell(50, 8, date('d/m/Y H:i'), 1, 1, 'L');
        $this->Ln(5);
        
        // Información del paciente
        $this->SetFont('Times', 'B', 12);
        $this->Cell(0, 8, $this->convertirTexto('DATOS DEL PACIENTE'), 0, 1, 'L');
        $this->SetFont('Times', '', 10);
        $this->Cell(40, 6, $this->convertirTexto('Paciente:'), 0, 0, 'L');
        $this->Cell(0, 6, $this->convertirTexto($datosCita['paciente_nombre']), 0, 1, 'L');
        $this->Cell(40, 6, $this->convertirTexto('Cédula:'), 0, 0, 'L');
        $this->Cell(0, 6, $this->convertirTexto($datosCita['paciente_cedula']), 0, 1, 'L');
        $this->Ln(5);
        
        // Información del pago
        $this->SetFont('Times', 'B', 12);
        $this->Cell(0, 8, $this->convertirTexto('INFORMACIÓN DEL PAGO'), 0, 1, 'L');
        $this->SetFont('Times', '', 10);
        $this->Cell(40, 6, $this->convertirTexto('Método:'), 0, 0, 'L');
        $this->Cell(0, 6, $this->convertirTexto(ucfirst($datosPago['metodo_pago'])), 0, 1, 'L');
        
        if (!empty($datosPago['numero_transaccion'])) {
            $this->Cell(40, 6, $this->convertirTexto('Transacción:'), 0, 0, 'L');
            $this->Cell(0, 6, $this->convertirTexto($datosPago['numero_transaccion']), 0, 1, 'L');
        }
        
        $this->Cell(40, 6, 'Estado:', 0, 0, 'L');
        $this->Cell(0, 6, 'PAGADO', 0, 1, 'L');
        $this->Ln(5);
        
        // Información de la cita
        $this->SetFont('Times', 'B', 12);
        $this->Cell(0, 8, $this->convertirTexto('DATOS DE LA CITA'), 0, 1, 'L');
        $this->SetFont('Times', '', 10);
        $this->Cell(40, 6, $this->convertirTexto('Médico:'), 0, 0, 'L');
        $this->Cell(0, 6, $this->convertirTexto($datosCita['medico_nombre']), 0, 1, 'L');
        $this->Cell(40, 6, 'Especialidad:', 0, 0, 'L');
        $this->Cell(0, 6, $this->convertirTexto($datosCita['especialidad']), 0, 1, 'L');
        $this->Cell(40, 6, 'Fecha y Hora:', 0, 0, 'L');
        $this->Cell(0, 6, date('d/m/Y H:i', strtotime($datosCita['fecha_cita'])), 0, 1, 'L');
        $this->Cell(40, 6, 'Sucursal:', 0, 0, 'L');
        $this->Cell(0, 6, $this->convertirTexto($datosCita['sucursal']), 0, 1, 'L');
        $this->Ln(10);
        
        // Detalle del pago
        $this->SetFont('Times', 'B', 12);
        $this->Cell(0, 8, $this->convertirTexto('DETALLE DEL PAGO'), 0, 1, 'L');
        
        // Tabla de conceptos
        $this->SetFont('Times', 'B', 10);
        $this->Cell(100, 8, 'CONCEPTO', 1, 0, 'C');
        $this->Cell(40, 8, 'CANTIDAD', 1, 0, 'C');
        $this->Cell(50, 8, 'VALOR', 1, 1, 'C');
        
        $this->SetFont('Times', '', 10);
        $this->Cell(100, 8, $this->convertirTexto('Consulta ' . $datosCita['especialidad']), 1, 0, 'L');
        $this->Cell(40, 8, '1', 1, 0, 'C');
        $this->Cell(50, 8, '$' . number_format($datosPago['monto'], 2), 1, 1, 'R');
        
        $this->SetFont('Times', 'B', 10);
        $this->Cell(140, 8, 'TOTAL PAGADO:', 1, 0, 'R');
        $this->Cell(50, 8, '$' . number_format($datosPago['monto'], 2), 1, 1, 'R');
        $this->Ln(10);
        
        // Información adicional
        $this->SetFont('Times', 'B', 12);
        $this->Cell(0, 8, $this->convertirTexto('INFORMACIÓN ADICIONAL'), 0, 1, 'L');
        $this->SetFont('Times', '', 10);
        $this->Cell(0, 6, $this->convertirTexto('- Presente este comprobante el día de su cita.'), 0, 1, 'L');
        $this->Cell(0, 6, $this->convertirTexto('- Conserve este documento como respaldo del pago.'), 0, 1, 'L');
        $this->Cell(0, 6, $this->convertirTexto('- En caso de cancelación, presente este comprobante.'), 0, 1, 'L');
        
        return $this->Output('S');
    }
}
?>