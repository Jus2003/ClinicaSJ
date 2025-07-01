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
        $mail->Body = generarPlantillaNotificacion($nombreCompleto, $titulo, $mensaje);
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
            .header { background: #007bff; color: white; padding: 20px; text-align: center; }
            .content { padding: 30px 20px; background: #f8f9fa; }
            .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
            .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Clínica SJ</h1>
                <p>Sistema de Notificaciones</p>
            </div>
            <div class='content'>
                <h2>Hola {$nombreCompleto},</h2>
                <h3>{$titulo}</h3>
                <p>{$mensaje}</p>
                <p>
                    <a href='" . SYSTEM_URL . "' class='btn'>Acceder al Sistema</a>
                </p>
            </div>
            <div class='footer'>
                <p>Este es un mensaje automático del Sistema de Clínica SJ</p>
                <p>Si tienes alguna pregunta, contacta con nosotros en " . SUPPORT_EMAIL . "</p>
            </div>
        </div>
    </body>
    </html>";
}
?>