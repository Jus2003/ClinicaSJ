<?php
require_once 'config/database.php';

class Sucursal {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Obtener todas las sucursales activas (método existente)
    public function getAllSucursales() {
        $sql = "SELECT * FROM sucursales WHERE activo = 1 ORDER BY nombre_sucursal";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Obtener sucursal por ID (método existente)
    public function getSucursalById($id) {
        $sql = "SELECT * FROM sucursales WHERE id_sucursal = :id AND activo = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }
    
    // NUEVOS MÉTODOS PARA EL CRUD
    
    // Obtener todas las sucursales para administración con filtros y paginación
    public function getAllSucursalesForAdmin($page = 1, $limit = 10, $search = '', $ciudad_filter = '', $provincia_filter = '') {
        $offset = ($page - 1) * $limit;
        
        $whereConditions = ["s.activo = 1"];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(s.nombre_sucursal LIKE :search OR s.direccion LIKE :search OR s.telefono LIKE :search OR s.email LIKE :search)";
            $params['search'] = "%{$search}%";
        }
        
        if (!empty($ciudad_filter)) {
            $whereConditions[] = "s.ciudad = :ciudad_filter";
            $params['ciudad_filter'] = $ciudad_filter;
        }
        
        if (!empty($provincia_filter)) {
            $whereConditions[] = "s.provincia = :provincia_filter";
            $params['provincia_filter'] = $provincia_filter;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "SELECT s.*,
                       (SELECT COUNT(*) FROM sucursal_especialidades se WHERE se.id_sucursal = s.id_sucursal AND se.activo = 1) as total_especialidades,
                       (SELECT COUNT(*) FROM usuarios u WHERE u.id_sucursal = s.id_sucursal AND u.id_rol = 3 AND u.activo = 1) as total_medicos
                FROM sucursales s 
                WHERE {$whereClause}
                ORDER BY s.nombre_sucursal 
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
    
    // Contar total de sucursales para paginación
    public function countSucursales($search = '', $ciudad_filter = '', $provincia_filter = '') {
        $whereConditions = ["activo = 1"];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(nombre_sucursal LIKE :search OR direccion LIKE :search OR telefono LIKE :search OR email LIKE :search)";
            $params['search'] = "%{$search}%";
        }
        
        if (!empty($ciudad_filter)) {
            $whereConditions[] = "ciudad = :ciudad_filter";
            $params['ciudad_filter'] = $ciudad_filter;
        }
        
        if (!empty($provincia_filter)) {
            $whereConditions[] = "provincia = :provincia_filter";
            $params['provincia_filter'] = $provincia_filter;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "SELECT COUNT(*) as total FROM sucursales WHERE {$whereClause}";
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'];
    }
    
    // Crear nueva sucursal
    public function createSucursal($data) {
        try {
            $this->db->beginTransaction();
            
            // Insertar sucursal
            $sql = "INSERT INTO sucursales (
                        nombre_sucursal, direccion, telefono, email, 
                        ciudad, provincia, codigo_postal
                    ) VALUES (
                        :nombre_sucursal, :direccion, :telefono, :email,
                        :ciudad, :provincia, :codigo_postal
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nombre_sucursal' => $data['nombre_sucursal'],
                'direccion' => $data['direccion'],
                'telefono' => $data['telefono'],
                'email' => $data['email'],
                'ciudad' => $data['ciudad'],
                'provincia' => $data['provincia'],
                'codigo_postal' => $data['codigo_postal']
            ]);
            
            $sucursalId = $this->db->lastInsertId();
            
            // Asignar especialidades si se especificaron
            if (!empty($data['especialidades'])) {
                $this->addSucursalEspecialidades($sucursalId, $data['especialidades']);
            }
            
            $this->db->commit();
            return $sucursalId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    // Actualizar sucursal
    public function updateSucursal($id, $data) {
        try {
            $this->db->beginTransaction();
            
            // Actualizar datos básicos
            $sql = "UPDATE sucursales SET 
                        nombre_sucursal = :nombre_sucursal,
                        direccion = :direccion,
                        telefono = :telefono,
                        email = :email,
                        ciudad = :ciudad,
                        provincia = :provincia,
                        codigo_postal = :codigo_postal
                    WHERE id_sucursal = :id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nombre_sucursal' => $data['nombre_sucursal'],
                'direccion' => $data['direccion'],
                'telefono' => $data['telefono'],
                'email' => $data['email'],
                'ciudad' => $data['ciudad'],
                'provincia' => $data['provincia'],
                'codigo_postal' => $data['codigo_postal'],
                'id' => $id
            ]);
            
            // Actualizar especialidades
            $this->updateSucursalEspecialidades($id, $data['especialidades'] ?? []);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    // Eliminar sucursal (lógico)
    public function deleteSucursal($id) {
        try {
            $this->db->beginTransaction();
            
            // Verificar que no tenga médicos activos asignados
            $sql = "SELECT COUNT(*) as total FROM usuarios WHERE id_sucursal = :id AND id_rol = 3 AND activo = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetch();
            
            if ($result['total'] > 0) {
                throw new Exception("No se puede eliminar la sucursal porque tiene médicos asignados");
            }
            
            // Desactivar sucursal
            $sql = "UPDATE sucursales SET activo = 0 WHERE id_sucursal = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            // Desactivar especialidades asociadas
            $sql = "UPDATE sucursal_especialidades SET activo = 0 WHERE id_sucursal = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    // Obtener especialidades de una sucursal
    public function getSucursalEspecialidades($sucursalId) {
        $sql = "SELECT e.* 
                FROM especialidades e
                INNER JOIN sucursal_especialidades se ON e.id_especialidad = se.id_especialidad
                WHERE se.id_sucursal = :sucursal_id AND se.activo = 1 AND e.activo = 1
                ORDER BY e.nombre_especialidad";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sucursal_id' => $sucursalId]);
        return $stmt->fetchAll();
    }
    
    // Obtener médicos de una sucursal
    public function getMedicosBySucursal($sucursalId) {
        $sql = "SELECT u.*,
                       GROUP_CONCAT(e.nombre_especialidad SEPARATOR ', ') as especialidades
                FROM usuarios u
                LEFT JOIN medico_especialidades me ON u.id_usuario = me.id_medico AND me.activo = 1
                LEFT JOIN especialidades e ON me.id_especialidad = e.id_especialidad AND e.activo = 1
                WHERE u.id_sucursal = :sucursal_id AND u.id_rol = 3 AND u.activo = 1
                GROUP BY u.id_usuario
                ORDER BY u.nombre, u.apellido";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sucursal_id' => $sucursalId]);
        return $stmt->fetchAll();
    }
    
    // Obtener estadísticas de una sucursal
    public function getEstadisticasSucursal($sucursalId) {
        $sql = "SELECT 
                    COUNT(CASE WHEN DATE(c.fecha_cita) = CURDATE() THEN 1 END) as citas_hoy,
                    COUNT(CASE WHEN YEAR(c.fecha_cita) = YEAR(CURDATE()) AND MONTH(c.fecha_cita) = MONTH(CURDATE()) THEN 1 END) as citas_mes,
                    COUNT(CASE WHEN c.estado_cita = 'completada' THEN 1 END) as citas_completadas,
                    COUNT(*) as total_citas
                FROM citas c 
                WHERE c.id_sucursal = :sucursal_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sucursal_id' => $sucursalId]);
        return $stmt->fetch();
    }
    
    // Obtener ciudades únicas para filtros
    public function getCiudades() {
        $sql = "SELECT DISTINCT ciudad FROM sucursales WHERE ciudad IS NOT NULL AND ciudad != '' AND activo = 1 ORDER BY ciudad";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Obtener provincias únicas para filtros
    public function getProvincias() {
        $sql = "SELECT DISTINCT provincia FROM sucursales WHERE provincia IS NOT NULL AND provincia != '' AND activo = 1 ORDER BY provincia";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // MÉTODOS AUXILIARES PARA ESPECIALIDADES
    
    private function addSucursalEspecialidades($sucursalId, $especialidades) {
        foreach ($especialidades as $especialidadId) {
            $sql = "INSERT INTO sucursal_especialidades (id_sucursal, id_especialidad) 
                    VALUES (:sucursal_id, :especialidad_id)
                    ON DUPLICATE KEY UPDATE activo = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'sucursal_id' => $sucursalId,
                'especialidad_id' => $especialidadId
            ]);
        }
    }
    
    private function updateSucursalEspecialidades($sucursalId, $especialidades) {
        // Desactivar todas las especialidades existentes
        $sql = "UPDATE sucursal_especialidades SET activo = 0 WHERE id_sucursal = :sucursal_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['sucursal_id' => $sucursalId]);
        
        // Agregar nuevas especialidades
        if (!empty($especialidades)) {
            $this->addSucursalEspecialidades($sucursalId, $especialidades);
        }
    }
}
?>