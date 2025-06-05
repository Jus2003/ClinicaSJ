<?php
require_once 'includes/phpmailer/PHPMailer.php';
require_once 'includes/phpmailer/SMTP.php';
require_once 'includes/phpmailer/Exception.php';
require_once 'includes/email-config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function generarPasswordTemporal($longitud = 8) {
    $caracteres = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    
    for ($i = 0; $i < $longitud; $i++) {
        $password .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
    
    return $password;
}

function enviarCredencialesPorEmail($email, $username, $passwordTemporal, $nombreCompleto) {
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
        $mail->Subject = 'Bienvenido a Clínica SJ - Credenciales de Acceso';
        $mail->Body = generarPlantillaEmail($nombreCompleto, $username, $passwordTemporal);
        $mail->AltBody = generarTextoPlano($nombreCompleto, $username, $passwordTemporal);
        
        $mail->send();
        
        // Log exitoso
        error_log("Email enviado exitosamente a: {$email}");
        return true;
        
    } catch (Exception $e) {
        // Log del error
        error_log("Error enviando email a {$email}: {$mail->ErrorInfo}");
        return false;
    }
}

function generarPlantillaEmail($nombreCompleto, $username, $passwordTemporal) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
                background-color: #f4f4f4;
            }
            .container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: white;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .header h1 { margin: 0; font-size: 28px; }
            .header p { margin: 5px 0 0 0; opacity: 0.9; }
            .content { padding: 40px 30px; }
            .credentials { 
                background: #f8f9fa; 
                padding: 25px; 
                margin: 25px 0; 
                border-radius: 8px; 
                border-left: 4px solid #667eea;
            }
            .credentials h3 { margin-top: 0; color: #667eea; }
            .password { 
                background: white; 
                padding: 10px 15px; 
                border-radius: 5px; 
                font-family: 'Courier New', monospace; 
                font-size: 16px; 
                font-weight: bold;
                color: #e74c3c;
                border: 2px dashed #e74c3c;
                display: inline-block;
                margin: 5px 0;
            }
            .warning { 
                background: #fff3cd; 
                color: #856404; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0;
                border-left: 4px solid #ffc107;
            }
            .steps { 
                background: #e8f5e8; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0;
            }
            .steps ol { margin: 0; padding-left: 20px; }
            .steps li { margin-bottom: 8px; }
            .footer { 
                background: #f8f9fa; 
                text-align: center; 
                padding: 20px; 
                color: #666; 
                font-size: 14px; 
                border-top: 1px solid #dee2e6;
            }
            .btn { 
                display: inline-block; 
                background: #667eea; 
                color: white; 
                padding: 12px 30px; 
                text-decoration: none; 
                border-radius: 5px; 
                margin: 15px 0;
                font-weight: bold;
            }
            .btn:hover { background: #5a6fd8; }
            @media (max-width: 600px) {
                .container { margin: 10px; }
                .content { padding: 20px; }
                .credentials { padding: 15px; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🏥 Clínica SJ</h1>
                <p>Sistema de Gestión Médica</p>
            </div>
            <div class='content'>
                <h2>¡Bienvenido/a, {$nombreCompleto}!</h2>
                <p>Se ha creado su cuenta en el Sistema de Gestión Médica de Clínica SJ. A continuación encontrará sus credenciales de acceso.</p>
                
                <div class='credentials'>
                    <h3>🔐 Sus credenciales de acceso</h3>
                    <p><strong>👤 Usuario:</strong> {$username}</p>
                    <p><strong>🔑 Contraseña temporal:</strong></p>
                    <div class='password'>{$passwordTemporal}</div>
                    <p><strong>🌐 Acceso al sistema:</strong></p>
                    <a href='" . SYSTEM_URL . "' class='btn'>Ingresar al Sistema</a>
                </div>
                
                <div class='warning'>
                    <strong>⚠️ Información importante:</strong>
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li>Esta es una contraseña temporal que <strong>debe cambiar</strong> en su primer acceso</li>
                        <li>Por seguridad, no comparta estas credenciales con terceros</li>
                        <li>La nueva contraseña debe tener al menos 6 caracteres</li>
                        <li>Mantenga sus credenciales en un lugar seguro</li>
                    </ul>
                </div>
                
                <div class='steps'>
                    <h3>📋 Pasos para acceder al sistema:</h3>
                    <ol>
                        <li>Haga clic en el botón 'Ingresar al Sistema' o copie el enlace en su navegador</li>
                        <li>Ingrese su usuario y contraseña temporal</li>
                        <li>El sistema le solicitará crear una nueva contraseña segura</li>
                        <li>¡Listo! Ya puede utilizar todas las funciones del sistema</li>
                    </ol>
                </div>
                
                <p>Si tiene algún problema para acceder al sistema, no dude en contactar al administrador.</p>
            </div>
            <div class='footer'>
                <p><strong>Sistema Clínica SJ</strong></p>
                <p>Este es un mensaje automático, por favor no responda a este correo.</p>
                <p>Para soporte técnico: <a href='mailto:" . SUPPORT_EMAIL . "'>" . SUPPORT_EMAIL . "</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
}

function generarTextoPlano($nombreCompleto, $username, $passwordTemporal) {
    return "
CLÍNICA SJ - SISTEMA DE GESTIÓN MÉDICA

¡Bienvenido/a, {$nombreCompleto}!

Sus credenciales de acceso:
- Usuario: {$username}
- Contraseña temporal: {$passwordTemporal}
- URL: " . SYSTEM_URL . "

IMPORTANTE:
- Esta es una contraseña temporal
- Debe cambiarla en su primer acceso
- No comparta estas credenciales

Pasos para acceder:
1. Ingrese al sistema con la URL proporcionada
2. Use su usuario y contraseña temporal
3. Cambie su contraseña cuando se le solicite
4. ¡Listo para usar el sistema!

Para soporte: " . SUPPORT_EMAIL . "

Sistema Clínica SJ
    ";
}
?>