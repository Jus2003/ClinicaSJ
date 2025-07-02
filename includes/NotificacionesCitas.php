<?php
require_once 'config/database.php';
require_once 'includes/email-sender.php';

class NotificacionesCitas {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Enviar notificación cuando se agenda una nueva cita
     */
    public function notificarNuevaCita($citaId) {
        try {
            $datosCita = $this->obtenerDatosCita($citaId);
            if (!$datosCita) {
                throw new Exception("Cita no encontrada");
            }
            
            // Notificar al paciente
            $this->enviarNotificacionPaciente($datosCita, 'nueva_cita');
            
            // Notificar al médico
            $this->enviarNotificacionMedico($datosCita, 'nueva_cita');
            
            return true;
        } catch (Exception $e) {
            error_log("Error notificando nueva cita: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enviar notificación cuando cambia el estado de una cita
     */
    public function notificarCambioEstado($citaId, $estadoAnterior, $estadoNuevo, $motivo = '') {
        try {
            $datosCita = $this->obtenerDatosCita($citaId);
            if (!$datosCita) {
                throw new Exception("Cita no encontrada");
            }
            
            // Actualizar datos con el nuevo estado
            $datosCita['estado_anterior'] = $estadoAnterior;
            $datosCita['estado_nuevo'] = $estadoNuevo;
            $datosCita['motivo_cambio'] = $motivo;
            
            // Notificar al paciente
            $this->enviarNotificacionPaciente($datosCita, 'cambio_estado');
            
            // Notificar al médico
            $this->enviarNotificacionMedico($datosCita, 'cambio_estado');
            
            return true;
        } catch (Exception $e) {
            error_log("Error notificando cambio de estado: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener datos completos de la cita
     */
    private function obtenerDatosCita($citaId) {
        $sql = "SELECT 
                    c.id_cita, c.fecha_cita, c.hora_cita, c.estado_cita, c.tipo_cita, 
                    c.motivo_consulta, c.observaciones, c.motivo_cancelacion,
                    p.id_usuario as id_paciente, p.nombre as paciente_nombre, 
                    p.apellido as paciente_apellido, p.email as paciente_email,
                    p.telefono as paciente_telefono, p.cedula as paciente_cedula,
                    m.id_usuario as id_medico, m.nombre as medico_nombre, 
                    m.apellido as medico_apellido, m.email as medico_email,
                    e.nombre_especialidad,
                    s.nombre_sucursal, s.direccion as sucursal_direccion, s.telefono as sucursal_telefono
                FROM citas c
                JOIN usuarios p ON c.id_paciente = p.id_usuario
                JOIN usuarios m ON c.id_medico = m.id_usuario
                JOIN especialidades e ON c.id_especialidad = e.id_especialidad
                JOIN sucursales s ON c.id_sucursal = s.id_sucursal
                WHERE c.id_cita = :cita_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cita_id' => $citaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Enviar notificación al paciente
     */
    private function enviarNotificacionPaciente($datosCita, $tipoNotificacion) {
        if (empty($datosCita['paciente_email'])) {
            return false;
        }
        
        $nombrePaciente = $datosCita['paciente_nombre'] . ' ' . $datosCita['paciente_apellido'];
        
        switch ($tipoNotificacion) {
            case 'nueva_cita':
                $titulo = "Cita Médica Agendada - Clínica SJ";
                $mensaje = $this->generarMensajePacienteNuevaCita($datosCita);
                break;
                
            case 'cambio_estado':
                $titulo = "Actualización de su Cita Médica - Clínica SJ";
                $mensaje = $this->generarMensajePacienteCambioEstado($datosCita);
                break;
                
            default:
                return false;
        }
        
        return enviarEmailNotificacion(
            $datosCita['paciente_email'], 
            $nombrePaciente, 
            $titulo, 
            $mensaje
        );
    }
    
    /**
     * Enviar notificación al médico
     */
    private function enviarNotificacionMedico($datosCita, $tipoNotificacion) {
        if (empty($datosCita['medico_email'])) {
            return false;
        }
        
        $nombreMedico = 'Dr(a). ' . $datosCita['medico_nombre'] . ' ' . $datosCita['medico_apellido'];
        
        switch ($tipoNotificacion) {
            case 'nueva_cita':
                $titulo = "Nueva Cita Asignada - Clínica SJ";
                $mensaje = $this->generarMensajeMedicoNuevaCita($datosCita);
                break;
                
            case 'cambio_estado':
                $titulo = "Actualización de Cita - Clínica SJ";
                $mensaje = $this->generarMensajeMedicoCambioEstado($datosCita);
                break;
                
            default:
                return false;
        }
        
        return enviarEmailNotificacion(
            $datosCita['medico_email'], 
            $nombreMedico, 
            $titulo, 
            $mensaje
        );
    }
    
    /**
     * Generar mensaje para paciente - nueva cita
     */
    private function generarMensajePacienteNuevaCita($datos) {
        $fechaFormateada = date('d/m/Y', strtotime($datos['fecha_cita']));
        $horaFormateada = date('H:i', strtotime($datos['hora_cita']));
        $medico = 'Dr(a). ' . $datos['medico_nombre'] . ' ' . $datos['medico_apellido'];
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h2 style='margin: 0;'>¡Cita Médica Agendada!</h2>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px 20px; border-radius: 0 0 8px 8px;'>
                <p>Estimado(a) <strong>{$datos['paciente_nombre']} {$datos['paciente_apellido']}</strong>,</p>
                
                <p>Su cita médica ha sido agendada exitosamente. A continuación los detalles:</p>
                
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #007bff;'>
                    <h3 style='color: #007bff; margin-top: 0;'>Detalles de la Cita</h3>
                    <p><strong>📅 Fecha:</strong> {$fechaFormateada}</p>
                    <p><strong>🕐 Hora:</strong> {$horaFormateada}</p>
                    <p><strong>👨‍⚕️ Médico:</strong> {$medico}</p>
                    <p><strong>🏥 Especialidad:</strong> {$datos['nombre_especialidad']}</p>
                    <p><strong>🏢 Sucursal:</strong> {$datos['nombre_sucursal']}</p>
                    <p><strong>📍 Dirección:</strong> {$datos['sucursal_direccion']}</p>
                    <p><strong>📞 Teléfono:</strong> {$datos['sucursal_telefono']}</p>
                    <p><strong>💼 Tipo de Cita:</strong> " . ucfirst($datos['tipo_cita']) . "</p>
                </div>
                
                <div style='background: #e7f3ff; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='color: #0066cc; margin-top: 0;'>📋 Motivo de la Consulta</h4>
                    <p style='margin-bottom: 0;'>{$datos['motivo_consulta']}</p>
                </div>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                    <h4 style='color: #856404; margin-top: 0;'>⚠️ Recordatorios Importantes</h4>
                    <ul style='margin-bottom: 0; color: #856404;'>
                        <li>Llegue 15 minutos antes de su cita</li>
                        <li>Traiga su cédula de identidad</li>
                        <li>Traiga sus exámenes médicos previos (si los tiene)</li>
                        <li>Si necesita cancelar, hágalo con al menos 24 horas de anticipación</li>
                    </ul>
                </div>
                
                <p style='text-align: center; margin-top: 30px;'>
                    <strong>¡Esperamos verle pronto!</strong><br>
                    <small style='color: #666;'>Equipo Clínica SJ</small>
                </p>
            </div>
        </div>";
    }
    
    /**
     * Generar mensaje para médico - nueva cita
     */
    private function generarMensajeMedicoNuevaCita($datos) {
        $fechaFormateada = date('d/m/Y', strtotime($datos['fecha_cita']));
        $horaFormateada = date('H:i', strtotime($datos['hora_cita']));
        $paciente = $datos['paciente_nombre'] . ' ' . $datos['paciente_apellido'];
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h2 style='margin: 0;'>Nueva Cita Asignada</h2>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px 20px; border-radius: 0 0 8px 8px;'>
                <p>Estimado(a) <strong>Dr(a). {$datos['medico_nombre']} {$datos['medico_apellido']}</strong>,</p>
                
                <p>Se le ha asignado una nueva cita médica. A continuación los detalles:</p>
                
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;'>
                    <h3 style='color: #28a745; margin-top: 0;'>Información de la Cita</h3>
                    <p><strong>📅 Fecha:</strong> {$fechaFormateada}</p>
                    <p><strong>🕐 Hora:</strong> {$horaFormateada}</p>
                    <p><strong>👤 Paciente:</strong> {$paciente}</p>
                    <p><strong>🆔 Cédula:</strong> " . ($datos['paciente_cedula'] ?: 'No registrada') . "</p>
                    <p><strong>📞 Teléfono:</strong> " . ($datos['paciente_telefono'] ?: 'No registrado') . "</p>
                    <p><strong>🏥 Especialidad:</strong> {$datos['nombre_especialidad']}</p>
                    <p><strong>🏢 Sucursal:</strong> {$datos['nombre_sucursal']}</p>
                    <p><strong>💼 Tipo de Cita:</strong> " . ucfirst($datos['tipo_cita']) . "</p>
                </div>
                
                <div style='background: #e7f3ff; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='color: #0066cc; margin-top: 0;'>📋 Motivo de la Consulta</h4>
                    <p style='margin-bottom: 0;'>{$datos['motivo_consulta']}</p>
                </div>
                
                <p style='text-align: center; margin-top: 30px;'>
                    <strong>Sistema Clínica SJ</strong><br>
                    <small style='color: #666;'>Gestión de Citas Médicas</small>
                </p>
            </div>
        </div>";
    }
    
    /**
     * Generar mensaje para paciente - cambio de estado
     */
    private function generarMensajePacienteCambioEstado($datos) {
        $fechaFormateada = date('d/m/Y', strtotime($datos['fecha_cita']));
        $horaFormateada = date('H:i', strtotime($datos['hora_cita']));
        $estadoAnterior = ucwords(str_replace('_', ' ', $datos['estado_anterior']));
        $estadoNuevo = ucwords(str_replace('_', ' ', $datos['estado_nuevo']));
        
        // Color según el estado
        $colorEstado = [
            'confirmada' => '#28a745',
            'cancelada' => '#dc3545',
            'completada' => '#17a2b8',
            'no_asistio' => '#6c757d'
        ];
        $color = $colorEstado[$datos['estado_nuevo']] ?? '#007bff';
        
        $mensaje = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: {$color}; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h2 style='margin: 0;'>Actualización de Cita Médica</h2>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px 20px; border-radius: 0 0 8px 8px;'>
                <p>Estimado(a) <strong>{$datos['paciente_nombre']} {$datos['paciente_apellido']}</strong>,</p>
                
                <p>Le informamos que el estado de su cita médica ha sido actualizado:</p>
                
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid {$color};'>
                    <h3 style='color: {$color}; margin-top: 0;'>Estado de la Cita</h3>
                    <p><strong>Estado anterior:</strong> {$estadoAnterior}</p>
                    <p><strong>Estado actual:</strong> <span style='color: {$color}; font-weight: bold;'>{$estadoNuevo}</span></p>
                </div>
                
                <div style='background: #e9ecef; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <h4 style='margin-top: 0;'>📋 Detalles de la Cita</h4>
                    <p><strong>📅 Fecha:</strong> {$fechaFormateada}</p>
                    <p><strong>🕐 Hora:</strong> {$horaFormateada}</p>
                    <p><strong>👨‍⚕️ Médico:</strong> Dr(a). {$datos['medico_nombre']} {$datos['medico_apellido']}</p>
                    <p><strong>🏥 Especialidad:</strong> {$datos['nombre_especialidad']}</p>
                </div>";
        
        // Mensaje específico según el estado
        if ($datos['estado_nuevo'] === 'cancelada') {
            $mensaje .= "
                <div style='background: #f8d7da; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc3545;'>
                    <h4 style='color: #721c24; margin-top: 0;'>❌ Cita Cancelada</h4>
                    <p style='color: #721c24;'>Su cita ha sido cancelada.</p>";
            
            if (!empty($datos['motivo_cambio'])) {
                $mensaje .= "<p style='color: #721c24;'><strong>Motivo:</strong> {$datos['motivo_cambio']}</p>";
            }
            
            $mensaje .= "<p style='color: #721c24;'>Si desea reagendar, por favor contáctenos.</p></div>";
            
        } elseif ($datos['estado_nuevo'] === 'confirmada') {
            $mensaje .= "
                <div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745;'>
                    <h4 style='color: #155724; margin-top: 0;'>✅ Cita Confirmada</h4>
                    <p style='color: #155724;'>Su cita ha sido confirmada. No olvide asistir en la fecha y hora programada.</p>
                </div>";
                
        } elseif ($datos['estado_nuevo'] === 'completada') {
            $mensaje .= "
                <div style='background: #d1ecf1; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #17a2b8;'>
                    <h4 style='color: #0c5460; margin-top: 0;'>✅ Cita Completada</h4>
                    <p style='color: #0c5460;'>Su cita médica ha sido completada satisfactoriamente.</p>
                </div>";
        }
        
        $mensaje .= "
                <p style='text-align: center; margin-top: 30px;'>
                    <strong>Para más información, contáctenos:</strong><br>
                    📞 {$datos['sucursal_telefono']}<br>
                    <small style='color: #666;'>Equipo Clínica SJ</small>
                </p>
            </div>
        </div>";
        
        return $mensaje;
    }
    
    /**
     * Generar mensaje para médico - cambio de estado
     */
    private function generarMensajeMedicoCambioEstado($datos) {
        $fechaFormateada = date('d/m/Y', strtotime($datos['fecha_cita']));
        $horaFormateada = date('H:i', strtotime($datos['hora_cita']));
        $estadoAnterior = ucwords(str_replace('_', ' ', $datos['estado_anterior']));
        $estadoNuevo = ucwords(str_replace('_', ' ', $datos['estado_nuevo']));
        $paciente = $datos['paciente_nombre'] . ' ' . $datos['paciente_apellido'];
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: #6c757d; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;'>
                <h2 style='margin: 0;'>Actualización de Cita</h2>
            </div>
            
            <div style='background: #f8f9fa; padding: 30px 20px; border-radius: 0 0 8px 8px;'>
                <p>Estimado(a) <strong>Dr(a). {$datos['medico_nombre']} {$datos['medico_apellido']}</strong>,</p>
                
                <p>Le informamos que una de sus citas ha cambiado de estado:</p>
                
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #6c757d;'>
                    <h3 style='color: #6c757d; margin-top: 0;'>Información de la Cita</h3>
                    <p><strong>👤 Paciente:</strong> {$paciente}</p>
                    <p><strong>📅 Fecha:</strong> {$fechaFormateada}</p>
                    <p><strong>🕐 Hora:</strong> {$horaFormateada}</p>
                    <p><strong>🏥 Especialidad:</strong> {$datos['nombre_especialidad']}</p>
                    <p><strong>Estado anterior:</strong> {$estadoAnterior}</p>
                    <p><strong>Estado actual:</strong> <span style='font-weight: bold;'>{$estadoNuevo}</span></p>
                </div>
                
                <p style='text-align: center; margin-top: 30px;'>
                    <strong>Sistema Clínica SJ</strong><br>
                    <small style='color: #666;'>Gestión de Citas Médicas</small>
                </p>
            </div>
        </div>";
    }
}
?>