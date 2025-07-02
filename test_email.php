<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/notificaciones-citas.php';
require_once __DIR__ . '/includes/email-sender.php';

try {
    $notificaciones = new NotificacionesCitas();
    
    // Datos de prueba COMPLETOS (ajusta con tus datos reales)
    $datosPrueba = [
        'id_cita' => 1,
        'id_paciente' => 9,
        'id_medico' => 5,
        'paciente_nombre' => 'Nombre Real',
        'paciente_apellido' => 'Apellido Real',
        'paciente_email' => 'email@real.com', // Usar un email real
        'medico_nombre' => 'Dr. Real',
        'medico_apellido' => 'Apellido Doctor',
        'medico_email' => 'doctor@real.com', // Usar un email real
        'fecha_cita' => date('Y-m-d'),
        'hora_cita' => '10:00:00',
        'nombre_especialidad' => 'Especialidad Real',
        'nombre_sucursal' => 'Sucursal Real',
        'sucursal_direccion' => 'Dirección real',
        'sucursal_telefono' => '123456789',
        'motivo_consulta' => 'Prueba del sistema',
        'tipo_cita' => 'presencial'
    ];
    
    // Llamada CORRECTA a métodos públicos
    echo "Probando notificación paciente:<br>";
    $resultPaciente = $notificaciones->enviarNotificacionPaciente($datosPrueba, 'nueva_cita');
    echo $resultPaciente ? "✅ Éxito" : "❌ Falló";
    
    echo "<br><br>Probando notificación médico:<br>";
    $resultMedico = $notificaciones->enviarNotificacionMedico($datosPrueba, 'nueva_cita');
    echo $resultMedico ? "✅ Éxito" : "❌ Falló";

} catch (Exception $e) {
    echo "<br><br>❌ Error: " . $e->getMessage();
}
?>