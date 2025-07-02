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

?>