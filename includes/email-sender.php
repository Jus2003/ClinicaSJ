<?php

require_once 'includes/phpmailer/PHPMailer.php';
require_once 'includes/phpmailer/SMTP.php';
require_once 'includes/phpmailer/Exception.php';
require_once 'includes/email-config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function enviarEmailNotificacion($email, $nombreCompleto, $titulo, $mensaje) {
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Remitente y destinatario
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $nombreCompleto);
        $mail->addReplyTo(SUPPORT_EMAIL, 'Soporte Clínica SJ');

        // Contenido del email
        $mail->isHTML(true);
        $mail->Subject = $titulo;

        // CAMBIO IMPORTANTE: Verificar si el mensaje ya viene formateado en HTML
        if (strpos($mensaje, '<div') !== false || strpos($mensaje, '<html') !== false) {
            // El mensaje ya viene con formato HTML completo (como los de NotificacionesCitas)
            $mail->Body = $mensaje;
        } else {
            // El mensaje es texto simple, usar plantilla genérica
            $mail->Body = generarPlantillaNotificacion($nombreCompleto, $titulo, $mensaje);
        }

        $mail->AltBody = strip_tags($mensaje);

        $mail->send();

        error_log("Email de notificación enviado exitosamente a: {$email}");
        return true;
    } catch (Exception $e) {
        error_log("Error enviando email de notificación a {$email}: {$mail->ErrorInfo}");
        return false;
    }
}

function generarPlantillaNotificacion($nombreCompleto, $titulo, $mensaje) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>{$titulo}</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 30px 20px; background: #f8f9fa; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; background: #f8f9fa; border-radius: 0 0 8px 8px; }
            .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 15px 0; }
            .notification-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 15px 0; }
            h1 { margin: 0; font-size: 24px; }
            h2 { color: #007bff; margin-top: 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏥 Clínica SJ</h1>
                <p>Sistema de Notificaciones</p>
            </div>
            <div class='content'>
                <div class='notification-box'>
                    <h2>Hola {$nombreCompleto},</h2>
                    <h3>📢 {$titulo}</h3>
                    <p style='font-size: 16px; line-height: 1.6;'>{$mensaje}</p>
                    <p style='text-align: center;'>
                        <a href='" . SYSTEM_URL . "' class='btn'>🔗 Acceder al Sistema</a>
                    </p>
                </div>
            </div>
            <div class='footer'>
                <p><strong>📧 Este es un mensaje automático del Sistema de Clínica SJ</strong></p>
                <p>Si tienes alguna pregunta, contacta con nosotros en " . SUPPORT_EMAIL . "</p>
                <p style='font-size: 10px; color: #999;'>Este email fue enviado automáticamente, por favor no responder a este mensaje.</p>
            </div>
        </div>
    </body>
    </html>";
}

// Función adicional para notificaciones simples (opcional)
function enviarEmailSimple($email, $nombreCompleto, $titulo, $mensaje) {
    return enviarEmailNotificacion($email, $nombreCompleto, $titulo, $mensaje);
}

// Agregar al final de includes/email-sender.php

function enviarRecetaPorEmail($datosReceta, $datosPaciente, $datosMedico) {
    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor (igual que las otras funciones)
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Remitente y destinatario
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($datosPaciente['email'], $datosPaciente['nombre']);
        $mail->addReplyTo(SUPPORT_EMAIL, 'Soporte Clínica SJ');

        // GENERAR EL PDF DE LA RECETA
        $pdfContent = generarPDFReceta($datosReceta, $datosPaciente, $datosMedico);

        // Adjuntar el PDF
        $nombreArchivo = 'Receta_' . $datosReceta['codigo_receta'] . '_' . date('Ymd') . '.pdf';
        $mail->addStringAttachment($pdfContent, $nombreArchivo, 'base64', 'application/pdf');

        // Contenido del email
        $mail->isHTML(true);
        $mail->Subject = '📄 Nueva Receta Médica - ' . $datosReceta['codigo_receta'];
        $mail->Body = generarPlantillaReceta($datosPaciente, $datosMedico, $datosReceta);
        $mail->AltBody = "Se ha generado una nueva receta médica. Consulte el archivo PDF adjunto para más detalles.";

        $mail->send();
        error_log("Receta enviada exitosamente por email a: {$datosPaciente['email']}");
        return true;
    } catch (Exception $e) {
        error_log("Error enviando receta por email a {$datosPaciente['email']}: {$mail->ErrorInfo}");
        return false;
    }
}

function generarPlantillaReceta($datosPaciente, $datosMedico, $datosReceta) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Nueva Receta Médica</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 30px 20px; background: #f8f9fa; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; background: #f8f9fa; border-radius: 0 0 8px 8px; }
            .receta-box { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 15px 0; }
            .medicamento-destacado { background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin: 15px 0; text-align: center; }
            .info-section { margin: 15px 0; padding: 15px; background: #e9ecef; border-radius: 5px; }
            .btn { display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 15px 0; }
            .warning-box { background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; padding: 15px; margin: 15px 0; }
            h1 { margin: 0; font-size: 24px; }
            h2 { color: #28a745; margin-top: 0; }
            h3 { color: #495057; }
            .highlight { color: #dc3545; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>💊 Nueva Receta Médica</h1>
                <p>Clínica Médica Integral</p>
            </div>
            <div class='content'>
                <div class='receta-box'>
                    <h2>Estimado/a {$datosPaciente['nombre']},</h2>
                    <p>Se ha generado una nueva receta médica para usted:</p>
                    
                    <div class='medicamento-destacado'>
                        <h3>💊 Medicamento Prescrito</h3>
                        <h2 style='margin: 10px 0; color: #856404;'>{$datosReceta['medicamento']}</h2>
                        <p><strong>Concentración:</strong> {$datosReceta['concentracion']}</p>
                        <p><strong>Forma:</strong> {$datosReceta['forma_farmaceutica']}</p>
                    </div>
                    
                    <div class='info-section'>
                        <h3>📋 Información de Dosificación</h3>
                        <p><strong>Dosis:</strong> {$datosReceta['dosis']}</p>
                        <p><strong>Frecuencia:</strong> {$datosReceta['frecuencia']}</p>
                        <p><strong>Duración:</strong> {$datosReceta['duracion']}</p>
                        <p><strong>Cantidad:</strong> {$datosReceta['cantidad']}</p>
                    </div>
                    
                    " . ($datosReceta['indicaciones_especiales'] ? "
                    <div class='info-section'>
                        <h3>⚠️ Indicaciones Especiales</h3>
                        <p>{$datosReceta['indicaciones_especiales']}</p>
                    </div>
                    " : "") . "
                    
                    <div class='info-section'>
                        <h3>👨‍⚕️ Médico Prescriptor</h3>
                        <p><strong>Dr(a). {$datosMedico['nombre']}</strong></p>
                        <p><strong>Especialidad:</strong> {$datosMedico['especialidad']}</p>
                        <p><strong>Fecha de emisión:</strong> " . date('d/m/Y') . "</p>
                    </div>
                    
                    <div class='warning-box'>
                        <h3 class='highlight'>📢 Instrucciones Importantes:</h3>
                        <ul>
                            <li><strong>Presente esta receta en la farmacia</strong> para obtener su medicamento</li>
                            <li><strong>Siga exactamente las indicaciones médicas</strong> - dosis, horarios y duración</li>
                            <li><strong>No suspenda el tratamiento</strong> sin consultar con su médico</li>
                            <li><strong>En caso de reacciones adversas,</strong> suspenda y contacte inmediatamente</li>
                            <li><strong>Validez:</strong> Esta receta es válida hasta " . date('d/m/Y', strtotime('+30 days')) . "</li>
                        </ul>
                    </div>
                    
                    <p style='text-align: center;'>
                        <a href='" . SYSTEM_URL . "' class='btn'>🔗 Acceder al Sistema</a>
                    </p>
                </div>
            </div>
            <div class='footer'>
                <p><strong>📧 Receta enviada automáticamente por el Sistema de Clínica SJ</strong></p>
                <p>📎 <strong>ADJUNTO:</strong> Encontrará el archivo PDF oficial de su receta adjunto a este email</p>
                <p>Si tiene alguna pregunta sobre su medicamento, contacte con nosotros en " . SUPPORT_EMAIL . "</p>
                <p style='font-size: 10px; color: #999;'>Este email fue enviado automáticamente, por favor no responder a este mensaje.</p>
            </div>
        </div>
    </body>
    </html>";
}

function generarPDFReceta($datosReceta, $datosPaciente, $datosMedico) {
    // Incluir la funcionalidad del PDF desde el archivo imprimir.php
    require_once __DIR__ . '/fpdf/fpdf.php';
    
    // Función para convertir texto (igual que en imprimir.php)
    function convertirTextoEmail($texto) {
        if (!$texto) return '';
        return iconv('UTF-8', 'ISO-8859-1//IGNORE', $texto);
    }
    
    // Usar la MISMA clase que funciona en imprimir.php
    class RecetaPDFEmail extends FPDF {
        private $receta;
        
        function __construct($receta) {
            parent::__construct();
            $this->receta = $receta;
        }
        
        // Encabezado de página (IGUAL que en imprimir.php)
        function Header() {
            // Logo o nombre de la clínica
            $this->SetFont('Arial', 'B', 20);
            $this->SetTextColor(0, 102, 204);
            $this->Cell(0, 15, convertirTextoEmail('CLÍNICA MÉDICA'), 0, 1, 'C');
            
            $this->SetFont('Arial', '', 12);
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 8, convertirTextoEmail('Sistema de Gestión Médica'), 0, 1, 'C');
            $this->Cell(0, 6, convertirTextoEmail('Quito, Ecuador'), 0, 1, 'C');
            $this->Cell(0, 6, convertirTextoEmail('Teléfono: +593-2-2234567'), 0, 1, 'C');
            
            $this->Ln(10);
            
            // Título de receta
            $this->SetFont('Arial', 'B', 18);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 12, convertirTextoEmail('RECETA MÉDICA'), 0, 1, 'C');
            
            $this->Ln(5);
        }
        
        // Pie de página
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(128, 128, 128);
            $this->Cell(0, 10, convertirTextoEmail('Receta médica - Generado el ' . date('d/m/Y H:i')), 0, 0, 'C');
        }
        
        // Función para agregar información en formato de tabla
        function addInfoSection($title, $data) {
            $this->SetFont('Arial', 'B', 12);
            $this->SetFillColor(240, 248, 255);
            $this->SetTextColor(0, 102, 204);
            $this->Cell(0, 8, convertirTextoEmail($title), 1, 1, 'L', true);
            
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(0, 0, 0);
            
            foreach ($data as $label => $value) {
                if ($value) {
                    $this->Cell(50, 6, convertirTextoEmail($label . ':'), 0, 0, 'L');
                    $this->Cell(0, 6, convertirTextoEmail($value), 0, 1, 'L');
                }
            }
            $this->Ln(3);
        }
        
        // Función para texto multilínea
        function addMultilineText($title, $text) {
            $this->SetFont('Arial', 'B', 12);
            $this->SetFillColor(240, 248, 255);
            $this->SetTextColor(0, 102, 204);
            $this->Cell(0, 8, convertirTextoEmail($title), 1, 1, 'L', true);
            
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(0, 0, 0);
            $this->MultiCell(0, 6, convertirTextoEmail($text), 1, 'L');
            $this->Ln(3);
        }
    }
    
    // Crear PDF CON TODOS LOS DATOS (igual que imprimir.php)
    $pdf = new RecetaPDFEmail($datosReceta);
    $pdf->AddPage();

    // Información del documento
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(95, 8, convertirTextoEmail('Receta N°: ' . $datosReceta['codigo_receta']), 0, 0, 'L');
    $pdf->Cell(0, 8, convertirTextoEmail('Fecha: ' . date('d/m/Y')), 0, 1, 'R');
    $pdf->Ln(3);

    // Información del Paciente
    $pdf->addInfoSection('INFORMACIÓN DEL PACIENTE', [
        'Nombre' => $datosPaciente['nombre'],
        'Cédula' => $datosPaciente['cedula'],
        'Email' => $datosPaciente['email']
    ]);

    // Información del Médico
    $pdf->addInfoSection('MÉDICO PRESCRIPTOR', [
        'Nombre' => $datosMedico['nombre'],
        'Especialidad' => $datosMedico['especialidad']
    ]);

    // Información del Medicamento (destacada) - IGUAL QUE IMPRIMIR.PHP
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetFillColor(255, 248, 220);
    $pdf->SetTextColor(204, 102, 0);
    $pdf->Cell(0, 10, convertirTextoEmail('MEDICAMENTO PRESCRITO'), 1, 1, 'C', true);

    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, convertirTextoEmail($datosReceta['medicamento']), 0, 1, 'C');

    // Detalles del medicamento en tabla
    $pdf->SetFont('Arial', '', 11);
    $pdf->SetFillColor(248, 248, 248);

    // Fila 1
    $pdf->Cell(63, 8, convertirTextoEmail('Concentración'), 1, 0, 'C', true);
    $pdf->Cell(64, 8, convertirTextoEmail('Forma Farmacéutica'), 1, 0, 'C', true);
    $pdf->Cell(63, 8, convertirTextoEmail('Cantidad'), 1, 1, 'C', true);

    $pdf->Cell(63, 8, convertirTextoEmail($datosReceta['concentracion'] ?: 'N/E'), 1, 0, 'C');
    $pdf->Cell(64, 8, convertirTextoEmail($datosReceta['forma_farmaceutica'] ?: 'N/E'), 1, 0, 'C');
    $pdf->Cell(63, 8, convertirTextoEmail($datosReceta['cantidad'] ?: 'N/E'), 1, 1, 'C');

    // Fila 2
    $pdf->Cell(63, 8, convertirTextoEmail('Dosis'), 1, 0, 'C', true);
    $pdf->Cell(64, 8, convertirTextoEmail('Frecuencia'), 1, 0, 'C', true);
    $pdf->Cell(63, 8, convertirTextoEmail('Duración'), 1, 1, 'C', true);

    $pdf->Cell(63, 8, convertirTextoEmail($datosReceta['dosis'] ?: 'N/E'), 1, 0, 'C');
    $pdf->Cell(64, 8, convertirTextoEmail($datosReceta['frecuencia'] ?: 'N/E'), 1, 0, 'C');
    $pdf->Cell(63, 8, convertirTextoEmail($datosReceta['duracion'] ?: 'N/E'), 1, 1, 'C');

    $pdf->Ln(5);

    // Indicaciones especiales
    if ($datosReceta['indicaciones_especiales']) {
        $pdf->addMultilineText('INDICACIONES ESPECIALES:', $datosReceta['indicaciones_especiales']);
    }

    // Información adicional
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(204, 0, 0);
    $pdf->Cell(0, 6, convertirTextoEmail('INFORMACIÓN IMPORTANTE:'), 0, 1, 'L');

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, convertirTextoEmail('• Esta receta es válida hasta: ' . date('d/m/Y', strtotime('+30 days'))), 0, 1, 'L');
    $pdf->Cell(0, 5, convertirTextoEmail('• Siga las indicaciones médicas al pie de la letra'), 0, 1, 'L');
    $pdf->Cell(0, 5, convertirTextoEmail('• En caso de reacciones adversas, suspenda el medicamento y consulte'), 0, 1, 'L');

    $pdf->Ln(10);

    // Línea de firma
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, convertirTextoEmail('_________________________________'), 0, 1, 'R');
    $pdf->Cell(0, 5, convertirTextoEmail('Firma y sello del médico'), 0, 1, 'R');
    $pdf->Cell(0, 5, convertirTextoEmail('Dr(a). ' . $datosMedico['nombre']), 0, 1, 'R');
    
    // Retornar el PDF como string
    return $pdf->Output('S');
}



?>