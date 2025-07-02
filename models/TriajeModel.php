<?php
require_once 'config/database.php';

class TriajeModel {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Obtener citas pendientes de triaje para un paciente
    public function getCitasPendientesTriaje($pacienteId) {
        $sql = "SELECT c.id_cita, c.fecha_cita, c.hora_cita, 
                       e.nombre_especialidad, 
                       CONCAT(u.nombre, ' ', u.apellido) as medico_nombre,
                       s.nombre_sucursal
                FROM citas c 
                JOIN especialidades e ON c.id_especialidad = e.id_especialidad
                JOIN usuarios u ON c.id_medico = u.id_usuario
                JOIN sucursales s ON c.id_sucursal = s.id_sucursal
                WHERE c.id_paciente = :paciente_id 
                AND c.estado_cita = 'confirmada'
                AND c.fecha_cita >= CURDATE()
                AND NOT EXISTS (
                    SELECT 1 FROM triaje_respuestas tr 
                    WHERE tr.id_cita = c.id_cita 
                    AND tr.tipo_triaje = 'digital'
                )
                ORDER BY c.fecha_cita, c.hora_cita";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['paciente_id' => $pacienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener citas con triaje para un médico
    public function getCitasConTriaje($medicoId) {
        $sql = "SELECT c.id_cita, c.fecha_cita, c.hora_cita,
                       CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                       p.cedula as paciente_cedula,
                       MIN(tr.fecha_respuesta) as fecha_triaje_completado,
                       COUNT(tr.id_respuesta) as total_respuestas
                FROM citas c 
                JOIN usuarios p ON c.id_paciente = p.id_usuario
                JOIN triaje_respuestas tr ON c.id_cita = tr.id_cita
                WHERE c.id_medico = :medico_id 
                AND c.estado_cita = 'confirmada'
                AND c.fecha_cita >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY c.id_cita
                ORDER BY c.fecha_cita DESC, c.hora_cita DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['medico_id' => $medicoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener preguntas activas del triaje
    public function getPreguntasActivas() {
        $sql = "SELECT * FROM preguntas_triaje 
                WHERE activa = 1 
                ORDER BY orden ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Guardar respuestas del triaje
    public function guardarRespuestas($citaId, $respuestas, $usuarioId, $tipoTriaje = 'digital') {
        try {
            $this->db->beginTransaction();
            
            foreach ($respuestas as $preguntaId => $respuesta) {
                // Verificar si ya existe una respuesta para esta pregunta y cita
                $sqlCheck = "SELECT id_respuesta FROM triaje_respuestas 
                            WHERE id_cita = :cita_id AND id_pregunta = :pregunta_id";
                $stmtCheck = $this->db->prepare($sqlCheck);
                $stmtCheck->execute([
                    'cita_id' => $citaId,
                    'pregunta_id' => $preguntaId
                ]);
                
                $existe = $stmtCheck->fetch();
                
                if ($existe) {
                    // Actualizar respuesta existente
                    $sqlUpdate = "UPDATE triaje_respuestas 
                                 SET respuesta = :respuesta, 
                                     valor_numerico = :valor_numerico,
                                     fecha_respuesta = NOW()
                                 WHERE id_cita = :cita_id AND id_pregunta = :pregunta_id";
                    $stmtUpdate = $this->db->prepare($sqlUpdate);
                    
                    $valorNumerico = is_numeric($respuesta) ? $respuesta : null;
                    
                    $stmtUpdate->execute([
                        'cita_id' => $citaId,
                        'pregunta_id' => $preguntaId,
                        'respuesta' => $respuesta,
                        'valor_numerico' => $valorNumerico
                    ]);
                } else {
                    // Insertar nueva respuesta
                    $sqlInsert = "INSERT INTO triaje_respuestas 
                                 (id_cita, id_pregunta, respuesta, valor_numerico, tipo_triaje, id_usuario_registro) 
                                 VALUES (:cita_id, :pregunta_id, :respuesta, :valor_numerico, :tipo_triaje, :usuario_id)";
                    $stmtInsert = $this->db->prepare($sqlInsert);
                    
                    $valorNumerico = is_numeric($respuesta) ? $respuesta : null;
                    
                    $stmtInsert->execute([
                        'cita_id' => $citaId,
                        'pregunta_id' => $preguntaId,
                        'respuesta' => $respuesta,
                        'valor_numerico' => $valorNumerico,
                        'tipo_triaje' => $tipoTriaje,
                        'usuario_id' => $usuarioId
                    ]);
                }
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    // Obtener respuestas de triaje para una cita
    public function getRespuestasTriaje($citaId) {
        $sql = "SELECT tr.*, pt.pregunta, pt.tipo_pregunta, pt.opciones 
                FROM triaje_respuestas tr
                JOIN preguntas_triaje pt ON tr.id_pregunta = pt.id_pregunta
                WHERE tr.id_cita = :cita_id
                ORDER BY pt.orden ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cita_id' => $citaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Verificar si una cita tiene triaje completado
    public function tieneTriajeCompletado($citaId) {
        $sql = "SELECT COUNT(*) as total FROM triaje_respuestas WHERE id_cita = :cita_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cita_id' => $citaId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] > 0;
    }
    
    // Verificar permisos de acceso a una cita
    public function verificarPermisosCita($citaId, $usuarioId, $rolId) {
        switch ($rolId) {
            case 1: // Administrador - acceso total
                return true;
                
            case 2: // Recepcionista - solo citas de su sucursal
                $sql = "SELECT c.id_cita FROM citas c 
                        JOIN usuarios u ON u.id_usuario = :usuario_id
                        WHERE c.id_cita = :cita_id AND c.id_sucursal = u.id_sucursal";
                break;
                
            case 3: // Médico - solo sus citas
                $sql = "SELECT id_cita FROM citas 
                        WHERE id_cita = :cita_id AND id_medico = :usuario_id";
                break;
                
            case 4: // Paciente - solo sus citas
                $sql = "SELECT id_cita FROM citas 
                        WHERE id_cita = :cita_id AND id_paciente = :usuario_id";
                break;
                
            default:
                return false;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cita_id' => $citaId, 'usuario_id' => $usuarioId]);
        return $stmt->fetch() !== false;
    }
}
?>