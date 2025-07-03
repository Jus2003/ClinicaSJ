<?php
// Después de crear la cita exitosamente
if ($citaId) {
    // Incluir el sistema de notificaciones
    require_once 'includes/notificaciones-citas.php';
    
    // Crear instancia del notificador
    $notificador = new NotificacionesCitas($this->db);
    
    // Enviar notificaciones por nueva cita
    $notificador->notificarNuevaCita($citaId);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Cita agendada exitosamente. Se han enviado las notificaciones por correo.',
        'cita_id' => $citaId
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error al agendar la cita'
    ]);
}
?>