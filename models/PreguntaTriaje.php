<?php
require_once 'config/database.php';

class PreguntaTriaje {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Obtener todas las preguntas para administración con filtros
    public function getAllPreguntasForAdmin($search = '', $tipo_filter = '', $estado_filter = '') {
        $whereConditions = ["1=1"];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "pregunta LIKE :search";
            $params['search'] = "%{$search}%";
        }
        
        if (!empty($tipo_filter)) {
            $whereConditions[] = "tipo_pregunta = :tipo_filter";
            $params['tipo_filter'] = $tipo_filter;
        }
        
        if ($estado_filter !== '') {
            $whereConditions[] = "activo = :estado_filter";
            $params['estado_filter'] = $estado_filter;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "SELECT p.*,
                       (SELECT COUNT(*) FROM triaje_respuestas tr WHERE tr.id_pregunta = p.id_pregunta) as total_respuestas
                FROM preguntas_triaje p 
                WHERE {$whereClause}
                ORDER BY p.orden ASC, p.id_pregunta ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // Obtener estadísticas del triaje
    public function getEstadisticasTriaje() {
        $sql = "SELECT 
                    COUNT(*) as total_preguntas,
                    COUNT(CASE WHEN activo = 1 THEN 1 END) as preguntas_activas,
                    COUNT(CASE WHEN obligatoria = 1 AND activo = 1 THEN 1 END) as preguntas_obligatorias,
                    (SELECT COUNT(*) FROM triaje_respuestas tr 
                     WHERE YEAR(tr.fecha_respuesta) = YEAR(CURDATE()) 
                     AND MONTH(tr.fecha_respuesta) = MONTH(CURDATE())) as respuestas_mes
                FROM preguntas_triaje";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    // Obtener pregunta por ID
    public function getPreguntaById($id) {
        $sql = "SELECT * FROM preguntas_triaje WHERE id_pregunta = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
    
    // Obtener todas las preguntas activas (para vista previa)
    public function getPreguntasActivas() {
        $sql = "SELECT * FROM preguntas_triaje WHERE activo = 1 ORDER BY orden ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Crear nueva pregunta
    public function createPregunta($data) {
        try {
            $sql = "INSERT INTO preguntas_triaje (
                        pregunta, tipo_pregunta, opciones_json, 
                        obligatoria, orden, activo
                    ) VALUES (
                        :pregunta, :tipo_pregunta, :opciones_json,
                        :obligatoria, :orden, 1
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'pregunta' => $data['pregunta'],
                'tipo_pregunta' => $data['tipo_pregunta'],
                'opciones_json' => $data['opciones_json'],
                'obligatoria' => $data['obligatoria'],
                'orden' => $data['orden']
            ]);
            
            return $this->db->lastInsertId();
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    // Actualizar pregunta
    public function updatePregunta($id, $data) {
        try {
            $sql = "UPDATE preguntas_triaje SET 
                        pregunta = :pregunta,
                        tipo_pregunta = :tipo_pregunta,
                        opciones_json = :opciones_json,
                        obligatoria = :obligatoria,
                        orden = :orden
                    WHERE id_pregunta = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'pregunta' => $data['pregunta'],
                'tipo_pregunta' => $data['tipo_pregunta'],
                'opciones_json' => $data['opciones_json'],
                'obligatoria' => $data['obligatoria'],
                'orden' => $data['orden'],
                'id' => $id
            ]);
            
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    // Eliminar pregunta
    public function deletePregunta($id) {
        try {
            $this->db->beginTransaction();
            
            // Verificar si tiene respuestas
            $sql = "SELECT COUNT(*) as total FROM triaje_respuestas WHERE id_pregunta = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                throw new Exception("No se puede eliminar la pregunta porque ya tiene respuestas registradas");
            }
            
            // Eliminar pregunta
            $sql = "DELETE FROM preguntas_triaje WHERE id_pregunta = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    // Cambiar estado (activo/inactivo)
    public function toggleEstado($id, $estado) {
        try {
            $sql = "UPDATE preguntas_triaje SET activo = :estado WHERE id_pregunta = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'estado' => $estado ? 1 : 0,
                'id' => $id
            ]);
            
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    // Actualizar órdenes de preguntas
    public function updateOrdenes($ordenes) {
        try {
            $this->db->beginTransaction();
            
            foreach ($ordenes as $id => $orden) {
                $sql = "UPDATE preguntas_triaje SET orden = :orden WHERE id_pregunta = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'orden' => (int)$orden,
                    'id' => (int)$id
                ]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    // Obtener siguiente orden disponible
    public function getNextOrden() {
        $sql = "SELECT COALESCE(MAX(orden), 0) + 1 as next_orden FROM preguntas_triaje";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['next_orden'];
    }
    
    // Contar respuestas por pregunta
    public function countRespuestasByPregunta($preguntaId) {
        $sql = "SELECT COUNT(*) as total FROM triaje_respuestas WHERE id_pregunta = :pregunta_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['pregunta_id' => $preguntaId]);
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    // MÉTODOS PARA EL TRIAJE FUNCIONAL
    
    // Guardar respuestas de triaje
    public function guardarRespuestasTriaje($citaId, $respuestas, $tipoTriaje = 'digital', $usuarioRegistro = null) {
        try {
            $this->db->beginTransaction();
            
            foreach ($respuestas as $preguntaId => $respuesta) {
                // Obtener información de la pregunta para determinar valor numérico
                $pregunta = $this->getPreguntaById($preguntaId);
                $valorNumerico = null;
                
                if ($pregunta['tipo_pregunta'] == 'numero' || $pregunta['tipo_pregunta'] == 'escala') {
                    $valorNumerico = is_numeric($respuesta) ? (float)$respuesta : null;
                }
                
                $sql = "INSERT INTO triaje_respuestas (
                            id_cita, id_pregunta, respuesta, valor_numerico,
                            tipo_triaje, id_usuario_registro
                        ) VALUES (
                            :id_cita, :id_pregunta, :respuesta, :valor_numerico,
                            :tipo_triaje, :id_usuario_registro
                        )";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'id_cita' => $citaId,
                    'id_pregunta' => $preguntaId,
                    'respuesta' => $respuesta,
                    'valor_numerico' => $valorNumerico,
                    'tipo_triaje' => $tipoTriaje,
                    'id_usuario_registro' => $usuarioRegistro
                ]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    // Obtener respuestas de triaje por cita
    public function getRespuestasByCita($citaId) {
        $sql = "SELECT tr.*, pt.pregunta, pt.tipo_pregunta, pt.opciones_json
                FROM triaje_respuestas tr
                INNER JOIN preguntas_triaje pt ON tr.id_pregunta = pt.id_pregunta
                WHERE tr.id_cita = :cita_id
                ORDER BY pt.orden ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cita_id' => $citaId]);
        return $stmt->fetchAll();
    }
    
    // Verificar si una cita ya tiene triaje completado
    public function citaTieneTriaje($citaId) {
        $sql = "SELECT COUNT(*) as total FROM triaje_respuestas WHERE id_cita = :cita_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cita_id' => $citaId]);
        $result = $stmt->fetch();
        return $result['total'] > 0;
    }
    
    // Obtener estadísticas de respuestas por pregunta
    public function getEstadisticasRespuestasPregunta($preguntaId, $fechaInicio = null, $fechaFin = null) {
        $whereClause = "id_pregunta = :pregunta_id";
        $params = ['pregunta_id' => $preguntaId];
        
        if ($fechaInicio && $fechaFin) {
            $whereClause .= " AND DATE(fecha_respuesta) BETWEEN :fecha_inicio AND :fecha_fin";
            $params['fecha_inicio'] = $fechaInicio;
            $params['fecha_fin'] = $fechaFin;
        }
        
        $sql = "SELECT 
                    COUNT(*) as total_respuestas,
                    COUNT(DISTINCT id_cita) as citas_unicas,
                    respuesta,
                    COUNT(*) as frecuencia
                FROM triaje_respuestas 
                WHERE {$whereClause}
                GROUP BY respuesta
                ORDER BY frecuencia DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
?>