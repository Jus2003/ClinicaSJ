<?php
require_once 'config/database.php';

class Especialidad {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Obtener todas las especialidades activas (método existente)
    public function getAllEspecialidades() {
        $sql = "SELECT * FROM especialidades WHERE activo = 1 ORDER BY nombre_especialidad";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Obtener especialidad por ID (método existente)
    public function getEspecialidadById($id) {
        $sql = "SELECT * FROM especialidades WHERE id_especialidad = :id AND activo = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
    
    // NUEVOS MÉTODOS PARA EL CRUD
    
    // Obtener todas las especialidades para administración con filtros y paginación
    public function getAllEspecialidadesForAdmin($page = 1, $limit = 10, $search = '', $tipo_filter = '', $duracion_filter = '') {
        $offset = ($page - 1) * $limit;
        
        $whereConditions = ["e.activo = 1"];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(e.nombre_especialidad LIKE :search OR e.descripcion LIKE :search)";
            $params['search'] = "%{$search}%";
        }
        
        if (!empty($tipo_filter)) {
            switch ($tipo_filter) {
                case 'presencial':
                    $whereConditions[] = "e.permite_presencial = 1 AND e.permite_virtual = 0";
                    break;
                case 'virtual':
                    $whereConditions[] = "e.permite_virtual = 1 AND e.permite_presencial = 0";
                    break;
                case 'ambos':
                    $whereConditions[] = "e.permite_presencial = 1 AND e.permite_virtual = 1";
                    break;
            }
        }
        
        if (!empty($duracion_filter)) {
            $whereConditions[] = "e.duracion_cita_minutos = :duracion_filter";
            $params['duracion_filter'] = $duracion_filter;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "SELECT e.*,
                       (SELECT COUNT(*) FROM medico_especialidades me WHERE me.id_especialidad = e.id_especialidad AND me.activo = 1) as total_medicos,
                       (SELECT COUNT(*) FROM sucursal_especialidades se WHERE se.id_especialidad = e.id_especialidad AND se.activo = 1) as total_sucursales
                FROM especialidades e 
                WHERE {$whereClause}
                ORDER BY e.nombre_especialidad 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Contar total de especialidades para paginación
    public function countEspecialidades($search = '', $tipo_filter = '', $duracion_filter = '') {
        $whereConditions = ["activo = 1"];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(nombre_especialidad LIKE :search OR descripcion LIKE :search)";
            $params['search'] = "%{$search}%";
        }
        
        if (!empty($tipo_filter)) {
            switch ($tipo_filter) {
                case 'presencial':
                    $whereConditions[] = "permite_presencial = 1 AND permite_virtual = 0";
                    break;
                case 'virtual':
                    $whereConditions[] = "permite_virtual = 1 AND permite_presencial = 0";
                    break;
                case 'ambos':
                    $whereConditions[] = "permite_presencial = 1 AND permite_virtual = 1";
                    break;
            }
        }
        
        if (!empty($duracion_filter)) {
            $whereConditions[] = "duracion_cita_minutos = :duracion_filter";
            $params['duracion_filter'] = $duracion_filter;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "SELECT COUNT(*) as total FROM especialidades WHERE {$whereClause}";
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    // Crear nueva especialidad
    public function createEspecialidad($data) {
        try {
            // Verificar que el nombre no exista
            if ($this->especialidadNameExists($data['nombre_especialidad'])) {
                throw new Exception("Ya existe una especialidad con ese nombre");
            }
            
            $sql = "INSERT INTO especialidades (
                        nombre_especialidad, descripcion, permite_virtual, 
                        permite_presencial, duracion_cita_minutos
                    ) VALUES (
                        :nombre_especialidad, :descripcion, :permite_virtual,
                        :permite_presencial, :duracion_cita_minutos
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nombre_especialidad' => $data['nombre_especialidad'],
                'descripcion' => $data['descripcion'],
                'permite_virtual' => $data['permite_virtual'],
                'permite_presencial' => $data['permite_presencial'],
                'duracion_cita_minutos' => $data['duracion_cita_minutos']
            ]);
            
            return $this->db->lastInsertId();
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    // Actualizar especialidad
    public function updateEspecialidad($id, $data) {
        try {
            // Verificar que el nombre no exista (excluyendo la especialidad actual)
            if ($this->especialidadNameExists($data['nombre_especialidad'], $id)) {
                throw new Exception("Ya existe una especialidad con ese nombre");
            }
            
            $sql = "UPDATE especialidades SET 
                        nombre_especialidad = :nombre_especialidad,
                        descripcion = :descripcion,
                        permite_virtual = :permite_virtual,
                        permite_presencial = :permite_presencial,
                        duracion_cita_minutos = :duracion_cita_minutos
                    WHERE id_especialidad = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nombre_especialidad' => $data['nombre_especialidad'],
                'descripcion' => $data['descripcion'],
                'permite_virtual' => $data['permite_virtual'],
                'permite_presencial' => $data['permite_presencial'],
                'duracion_cita_minutos' => $data['duracion_cita_minutos'],
                'id' => $id
            ]);
            
            return true;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    // Eliminar especialidad (lógico)
    public function deleteEspecialidad($id) {
        try {
            $this->db->beginTransaction();
            
            // Verificar que no tenga médicos activos asignados
            $sql = "SELECT COUNT(*) as total FROM medico_especialidades WHERE id_especialidad = :id AND activo = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                throw new Exception("No se puede eliminar la especialidad porque tiene médicos asignados");
            }
            
            // Desactivar especialidad
            $sql = "UPDATE especialidades SET activo = 0 WHERE id_especialidad = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            // Desactivar relaciones con sucursales
            $sql = "UPDATE sucursal_especialidades SET activo = 0 WHERE id_especialidad = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    // Obtener médicos por especialidad
    public function getMedicosByEspecialidad($especialidadId) {
        $sql = "SELECT u.*, s.nombre_sucursal, me.numero_licencia, me.fecha_obtencion
                FROM usuarios u
                INNER JOIN medico_especialidades me ON u.id_usuario = me.id_medico
                LEFT JOIN sucursales s ON u.id_sucursal = s.id_sucursal
                WHERE me.id_especialidad = :especialidad_id 
                AND me.activo = 1 AND u.activo = 1
                ORDER BY u.nombre, u.apellido";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['especialidad_id' => $especialidadId]);
        return $stmt->fetchAll();
    }
    
    // Obtener sucursales por especialidad
    public function getSucursalesByEspecialidad($especialidadId) {
        $sql = "SELECT s.*, se.fecha_asignacion
                FROM sucursales s
                INNER JOIN sucursal_especialidades se ON s.id_sucursal = se.id_sucursal
                WHERE se.id_especialidad = :especialidad_id 
                AND se.activo = 1 AND s.activo = 1
                ORDER BY s.nombre_sucursal";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['especialidad_id' => $especialidadId]);
        return $stmt->fetchAll();
    }
    
    // Obtener estadísticas de una especialidad
    public function getEstadisticasEspecialidad($especialidadId) {
        $sql = "SELECT 
                    COUNT(CASE WHEN DATE(c.fecha_cita) = CURDATE() THEN 1 END) as citas_hoy,
                    COUNT(CASE WHEN YEAR(c.fecha_cita) = YEAR(CURDATE()) AND MONTH(c.fecha_cita) = MONTH(CURDATE()) THEN 1 END) as citas_mes,
                    COUNT(CASE WHEN c.estado_cita = 'completada' THEN 1 END) as citas_completadas,
                    COUNT(*) as total_citas
                FROM citas c 
                WHERE c.id_especialidad = :especialidad_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['especialidad_id' => $especialidadId]);
        return $stmt->fetch();
    }
    
    // Contar médicos por especialidad
    public function countMedicosByEspecialidad($especialidadId) {
        $sql = "SELECT COUNT(*) as total 
                FROM medico_especialidades me
                INNER JOIN usuarios u ON me.id_medico = u.id_usuario
                WHERE me.id_especialidad = :especialidad_id 
                AND me.activo = 1 AND u.activo = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['especialidad_id' => $especialidadId]);
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    // Contar sucursales por especialidad
    public function countSucursalesByEspecialidad($especialidadId) {
        $sql = "SELECT COUNT(*) as total 
                FROM sucursal_especialidades se
                INNER JOIN sucursales s ON se.id_sucursal = s.id_sucursal
                WHERE se.id_especialidad = :especialidad_id 
                AND se.activo = 1 AND s.activo = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['especialidad_id' => $especialidadId]);
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    // MÉTODOS AUXILIARES
    
    // Verificar si existe una especialidad con el mismo nombre
    private function especialidadNameExists($nombre, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM especialidades WHERE nombre_especialidad = :nombre";
        $params = ['nombre' => $nombre];
        
        if ($excludeId) {
            $sql .= " AND id_especialidad != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }
}
?>