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
                WHERE activo = 1 
                ORDER BY orden ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Guardar respuestas del triaje
    public function guardarRespuestas($citaId, $respuestas, $userId, $tipoTriaje) {
        try {
            $this->db->beginTransaction();

            // Primero eliminar respuestas existentes (por si acaso)
            $sqlDelete = "DELETE FROM triaje_respuestas WHERE id_cita = :cita_id";
            $stmtDelete = $this->db->prepare($sqlDelete);
            $stmtDelete->execute(['cita_id' => $citaId]);

            // Insertar nuevas respuestas
            // CAMBIAR A:
            $sqlInsert = "INSERT INTO triaje_respuestas (id_cita, id_pregunta, respuesta, tipo_triaje, fecha_respuesta, id_usuario_registro) 
              VALUES (:cita_id, :pregunta_id, :respuesta, :tipo_triaje, NOW(), :user_id)";
            $stmtInsert = $this->db->prepare($sqlInsert);

            foreach ($respuestas as $preguntaId => $respuesta) {
                if (!empty($respuesta) || $respuesta === '0') {
                    $stmtInsert->execute([
                        'cita_id' => $citaId,
                        'pregunta_id' => $preguntaId,
                        'respuesta' => $respuesta,
                        'tipo_triaje' => $tipoTriaje,
                        'user_id' => $userId
                    ]);
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // Obtener respuestas de triaje para una cita
    public function getRespuestasTriaje($citaId) {
        $sql = "SELECT tr.*, pt.pregunta, pt.tipo_pregunta, pt.obligatoria, pt.orden
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
        $sql = "SELECT COUNT(*) as count FROM triaje_respuestas WHERE id_cita = :cita_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cita_id' => $citaId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }

    // Verificar permisos de acceso a una cita
    public function verificarPermisosCita($citaId, $userId, $roleId) {
        if ($roleId == 4) { // Paciente
            $sql = "SELECT COUNT(*) as count FROM citas WHERE id_cita = :cita_id AND id_paciente = :user_id";
        } elseif ($roleId == 2) { // Médico
            $sql = "SELECT COUNT(*) as count FROM citas WHERE id_cita = :cita_id AND id_medico = :user_id";
        } else {
            return false;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cita_id' => $citaId, 'user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }

    public function getInfoCita($citaId) {
        $sql = "SELECT c.*, 
                   CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                   p.cedula as paciente_cedula,
                   p.fecha_nacimiento,
                   p.genero,
                   e.nombre_especialidad,
                   c.motivo_consulta
            FROM citas c
            JOIN usuarios p ON c.id_paciente = p.id_usuario
            JOIN especialidades e ON c.id_especialidad = e.id_especialidad
            WHERE c.id_cita = :cita_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cita_id' => $citaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

?>