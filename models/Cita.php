<?php

require_once 'config/database.php';

class Cita {

    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // Crear nueva cita
    public function createCita($data) {
        try {
            $sql = "INSERT INTO citas (
                        id_paciente, id_medico, id_especialidad, id_sucursal,
                        fecha_cita, hora_cita, tipo_cita, estado_cita,
                        motivo_consulta, observaciones, id_usuario_registro
                    ) VALUES (
                        :id_paciente, :id_medico, :id_especialidad, :id_sucursal,
                        :fecha_cita, :hora_cita, :tipo_cita, :estado_cita,
                        :motivo_consulta, :observaciones, :id_usuario_registro
                    )";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($data);

            if ($result) {
                return $this->db->lastInsertId();
            }

            return false;
        } catch (Exception $e) {
            error_log("Error al crear cita: " . $e->getMessage());
            throw $e;
        }
    }

    // Verificar disponibilidad de horario
    public function verificarDisponibilidad($medicoId, $fecha, $hora) {
        $sql = "SELECT COUNT(*) as total 
                FROM citas 
                WHERE id_medico = :medico_id 
                AND fecha_cita = :fecha 
                AND hora_cita = :hora 
                AND estado_cita NOT IN ('cancelada', 'no_asistio')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'medico_id' => $medicoId,
            'fecha' => $fecha,
            'hora' => $hora
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] == 0;
    }

    // Obtener horarios disponibles de un médico
    public function getHorariosDisponibles($medicoId, $fecha) {
        $diaSemana = date('w', strtotime($fecha)) == 0 ? 7 : date('w', strtotime($fecha));

        $sql = "SELECT h.hora_inicio, h.hora_fin, h.dia_semana
                FROM horarios_medicos h
                WHERE h.id_medico = :medico_id 
                AND h.dia_semana = :dia_semana 
                AND h.activo = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'medico_id' => $medicoId,
            'dia_semana' => $diaSemana
        ]);

        $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generar slots de tiempo disponibles
        $slotsDisponibles = [];
        foreach ($horarios as $horario) {
            $horaInicio = new DateTime($horario['hora_inicio']);
            $horaFin = new DateTime($horario['hora_fin']);

            while ($horaInicio < $horaFin) {
                $horaSlot = $horaInicio->format('H:i:s');

                // Verificar si el slot está disponible
                if ($this->verificarDisponibilidad($medicoId, $fecha, $horaSlot)) {
                    $slotsDisponibles[] = $horaSlot;
                }

                $horaInicio->add(new DateInterval('PT30M')); // Incrementar 30 minutos
            }
        }

        return $slotsDisponibles;
    }

    // Obtener médicos por especialidad y sucursal
    public function getMedicosPorEspecialidadYSucursal($especialidadId, $sucursalId) {
        $sql = "SELECT DISTINCT u.id_usuario, u.nombre, u.apellido, u.email
                FROM usuarios u
                JOIN medico_especialidades me ON u.id_usuario = me.id_medico
                WHERE u.id_rol = 3 
                AND u.activo = 1
                AND u.id_sucursal = :sucursal_id
                AND me.id_especialidad = :especialidad_id
                AND me.activo = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'sucursal_id' => $sucursalId,
            'especialidad_id' => $especialidadId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener citas de un paciente
    public function getCitasPaciente($pacienteId, $limite = null) {
        $sql = "SELECT c.*, 
                       CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
                       e.nombre_especialidad,
                       s.nombre_sucursal
                FROM citas c
                JOIN usuarios m ON c.id_medico = m.id_usuario
                JOIN especialidades e ON c.id_especialidad = e.id_especialidad
                JOIN sucursales s ON c.id_sucursal = s.id_sucursal
                WHERE c.id_paciente = :paciente_id
                ORDER BY c.fecha_cita DESC, c.hora_cita DESC";

        if ($limite) {
            $sql .= " LIMIT " . (int) $limite;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['paciente_id' => $pacienteId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener citas de un médico
    public function getCitasMedico($medicoId, $fecha = null) {
        $where = "c.id_medico = :medico_id";
        $params = ['medico_id' => $medicoId];

        if ($fecha) {
            $where .= " AND c.fecha_cita = :fecha";
            $params['fecha'] = $fecha;
        }

        $sql = "SELECT c.*, 
                       CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                       p.cedula as paciente_cedula,
                       p.telefono as paciente_telefono,
                       e.nombre_especialidad
                FROM citas c
                JOIN usuarios p ON c.id_paciente = p.id_usuario
                JOIN especialidades e ON c.id_especialidad = e.id_especialidad
                WHERE {$where}
                ORDER BY c.fecha_cita ASC, c.hora_cita ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Actualizar estado de cita
    public function updateEstadoCita($citaId, $nuevoEstado, $observaciones = null) {
        $sql = "UPDATE citas 
                SET estado_cita = :estado";

        $params = [
            'cita_id' => $citaId,
            'estado' => $nuevoEstado
        ];

        if ($observaciones) {
            $sql .= ", observaciones = :observaciones";
            $params['observaciones'] = $observaciones;
        }

        if ($nuevoEstado === 'cancelada') {
            $sql .= ", fecha_cancelacion = NOW(), motivo_cancelacion = :motivo";
            $params['motivo'] = $observaciones ?? 'Cancelada por el usuario';
        } elseif ($nuevoEstado === 'confirmada') {
            $sql .= ", fecha_confirmacion = NOW()";
        }

        $sql .= " WHERE id_cita = :cita_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    // Obtener citas de médico con filtros
    public function getCitasMedicoConFiltros($medicoId, $fechaInicio, $fechaFin, $estadoFilter = 'todas') {
        $where = "c.id_medico = :medico_id AND c.fecha_cita BETWEEN :fecha_inicio AND :fecha_fin";
        $params = [
            'medico_id' => $medicoId,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin
        ];

        if ($estadoFilter !== 'todas') {
            $where .= " AND c.estado_cita = :estado";
            $params['estado'] = $estadoFilter;
        }

        $sql = "SELECT c.*, 
                   CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                   p.cedula as paciente_cedula,
                   p.telefono as paciente_telefono,
                   e.nombre_especialidad,
                   s.nombre_sucursal
            FROM citas c
            JOIN usuarios p ON c.id_paciente = p.id_usuario
            JOIN especialidades e ON c.id_especialidad = e.id_especialidad
            JOIN sucursales s ON c.id_sucursal = s.id_sucursal
            WHERE {$where}
            ORDER BY c.fecha_cita ASC, c.hora_cita ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

// Obtener citas de paciente con filtros
    public function getCitasPacienteConFiltros($pacienteId, $fechaInicio, $fechaFin, $estadoFilter = 'todas') {
        $where = "c.id_paciente = :paciente_id AND c.fecha_cita BETWEEN :fecha_inicio AND :fecha_fin";
        $params = [
            'paciente_id' => $pacienteId,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin
        ];

        if ($estadoFilter !== 'todas') {
            $where .= " AND c.estado_cita = :estado";
            $params['estado'] = $estadoFilter;
        }

        $sql = "SELECT c.*, 
                  CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
                  e.nombre_especialidad,
                  s.nombre_sucursal
           FROM citas c
           JOIN usuarios m ON c.id_medico = m.id_usuario
           JOIN especialidades e ON c.id_especialidad = e.id_especialidad
           JOIN sucursales s ON c.id_sucursal = s.id_sucursal
           WHERE {$where}
           ORDER BY c.fecha_cita ASC, c.hora_cita ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

// Obtener citas globales con filtros (para admin/recepcionista)
    public function getCitasGlobalesConFiltros($fechaInicio, $fechaFin, $estadoFilter = 'todas', $usuarioId = null) {
        $where = "c.fecha_cita BETWEEN :fecha_inicio AND :fecha_fin";
        $params = [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin
        ];

        // Si es recepcionista, filtrar por sucursal
        if ($usuarioId) {
            $sqlRol = "SELECT id_rol, id_sucursal FROM usuarios WHERE id_usuario = :user_id";
            $stmtRol = $this->db->prepare($sqlRol);
            $stmtRol->execute(['user_id' => $usuarioId]);
            $usuario = $stmtRol->fetch(PDO::FETCH_ASSOC);

            if ($usuario && $usuario['id_rol'] == 2 && $usuario['id_sucursal']) {
                $where .= " AND c.id_sucursal = :sucursal_id";
                $params['sucursal_id'] = $usuario['id_sucursal'];
            }
        }

        if ($estadoFilter !== 'todas') {
            $where .= " AND c.estado_cita = :estado";
            $params['estado'] = $estadoFilter;
        }

        $sql = "SELECT c.*, 
                  CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                  p.cedula as paciente_cedula,
                  p.telefono as paciente_telefono,
                  CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
                  e.nombre_especialidad,
                  s.nombre_sucursal
           FROM citas c
           JOIN usuarios p ON c.id_paciente = p.id_usuario
           JOIN usuarios m ON c.id_medico = m.id_usuario
           JOIN especialidades e ON c.id_especialidad = e.id_especialidad
           JOIN sucursales s ON c.id_sucursal = s.id_sucursal
           WHERE {$where}
           ORDER BY c.fecha_cita ASC, c.hora_cita ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Verificar si una cita tiene triaje completado
    public function tieneTriajeCompletado($citaId) {
        $sql = "SELECT COUNT(*) as total FROM triaje_respuestas 
            WHERE id_cita = :cita_id AND tipo_triaje = 'digital'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cita_id' => $citaId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['total'] > 0;
    }

// Obtener precios por especialidad
    public function getPrecioPorEspecialidad($especialidadId) {
        $sql = "SELECT precio_consulta FROM especialidades WHERE id_especialidad = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $especialidadId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? $result['precio_consulta'] : 35.00; // Precio por defecto
    }

    public function getCitasVirtualesMedico($medicoId) {
        $sql = "SELECT c.*, 
                       CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                       CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
                       e.nombre_especialidad,
                       s.nombre_sucursal
                FROM citas c
                INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
                INNER JOIN usuarios m ON c.id_medico = m.id_usuario
                INNER JOIN especialidades e ON c.id_especialidad = e.id_especialidad
                INNER JOIN sucursales s ON c.id_sucursal = s.id_sucursal
                WHERE c.id_medico = :medico_id 
                AND c.tipo_cita = 'virtual'
                AND c.fecha_cita >= CURDATE() - INTERVAL 7 DAY
                ORDER BY c.fecha_cita ASC, c.hora_cita ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':medico_id', $medicoId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener citas virtuales de un paciente
     */
    public function getCitasVirtualesPaciente($pacienteId) {
        $sql = "SELECT c.*, 
                       CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                       CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
                       e.nombre_especialidad,
                       s.nombre_sucursal
                FROM citas c
                INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
                INNER JOIN usuarios m ON c.id_medico = m.id_usuario
                INNER JOIN especialidades e ON c.id_especialidad = e.id_especialidad
                INNER JOIN sucursales s ON c.id_sucursal = s.id_sucursal
                WHERE c.id_paciente = :paciente_id 
                AND c.tipo_cita = 'virtual'
                ORDER BY c.fecha_cita DESC, c.hora_cita DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':paciente_id', $pacienteId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generar enlace único para videollamada
     */
    public function generarEnlaceVirtual($citaId) {
        // Verificar que la cita existe y es virtual
        $sqlVerificar = "SELECT id_cita FROM citas WHERE id_cita = :cita_id AND tipo_cita = 'virtual'";
        $stmtVerificar = $this->conn->prepare($sqlVerificar);
        $stmtVerificar->bindParam(':cita_id', $citaId);
        $stmtVerificar->execute();

        if ($stmtVerificar->rowCount() == 0) {
            throw new Exception("Cita no encontrada o no es virtual");
        }

        // Generar ID único para la sala
        $salaId = 'clinica_virtual_' . $citaId . '_' . time();
        $enlaceCompleto = 'https://meet.jit.si/' . $salaId;

        // Actualizar la cita con el enlace
        $sqlActualizar = "UPDATE citas SET enlace_virtual = :enlace WHERE id_cita = :cita_id";
        $stmtActualizar = $this->conn->prepare($sqlActualizar);
        $stmtActualizar->bindParam(':enlace', $enlaceCompleto);
        $stmtActualizar->bindParam(':cita_id', $citaId);

        if (!$stmtActualizar->execute()) {
            throw new Exception("Error al generar enlace virtual");
        }

        return $enlaceCompleto;
    }

    /**
     * Verificar acceso a consulta virtual
     */
    public function verificarAccesoConsultaVirtual($citaId, $userId, $userRole) {
        $whereCondition = '';

        if ($userRole == 3) { // Médico
            $whereCondition = "AND c.id_medico = :user_id";
        } elseif ($userRole == 4) { // Paciente
            $whereCondition = "AND c.id_paciente = :user_id";
        } else {
            return false;
        }

        $sql = "SELECT c.*, 
                       CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                       CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
                       e.nombre_especialidad
                FROM citas c
                INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
                INNER JOIN usuarios m ON c.id_medico = m.id_usuario
                INNER JOIN especialidades e ON c.id_especialidad = e.id_especialidad
                WHERE c.id_cita = :cita_id 
                AND c.tipo_cita = 'virtual' 
                {$whereCondition}";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':cita_id', $citaId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Iniciar consulta virtual
     */
    public function iniciarConsultaVirtual($citaId) {
        $sql = "UPDATE citas SET estado_cita = 'en_curso' WHERE id_cita = :cita_id AND estado_cita = 'confirmada'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':cita_id', $citaId);

        if (!$stmt->execute()) {
            throw new Exception("Error al iniciar consulta virtual");
        }

        return true;
    }

    /**
     * Finalizar consulta virtual
     */
    public function finalizarConsultaVirtual($citaId) {
        $sql = "UPDATE citas SET estado_cita = 'completada' WHERE id_cita = :cita_id AND estado_cita = 'en_curso'";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':cita_id', $citaId);

        if (!$stmt->execute()) {
            throw new Exception("Error al finalizar consulta virtual");
        }

        return true;
    }

    public function getHistorialVirtualMedico($medicoId, $buscar = '', $estado = 'todas', $fechaDesde = '', $fechaHasta = '', $limit = 15, $offset = 0) {
        $whereConditions = ["c.id_medico = :medico_id", "c.tipo_cita = 'virtual'"];
        $params = ['medico_id' => $medicoId];

        if ($buscar) {
            $whereConditions[] = "(CONCAT(p.nombre, ' ', p.apellido) LIKE :buscar OR p.cedula LIKE :buscar)";
            $params['buscar'] = "%{$buscar}%";
        }

        if ($estado !== 'todas') {
            $whereConditions[] = "c.estado_cita = :estado";
            $params['estado'] = $estado;
        }

        if ($fechaDesde) {
            $whereConditions[] = "c.fecha_cita >= :fecha_desde";
            $params['fecha_desde'] = $fechaDesde;
        }

        if ($fechaHasta) {
            $whereConditions[] = "c.fecha_cita <= :fecha_hasta";
            $params['fecha_hasta'] = $fechaHasta;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT c.*, 
                   CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                   p.cedula as paciente_cedula,
                   CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
                   e.nombre_especialidad
            FROM citas c
            INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
            INNER JOIN usuarios m ON c.id_medico = m.id_usuario
            INNER JOIN especialidades e ON c.id_especialidad = e.id_especialidad
            WHERE {$whereClause}
            ORDER BY c.fecha_cita DESC, c.hora_cita DESC
            LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contar historial de consultas virtuales para médico
     */
    public function countHistorialVirtualMedico($medicoId, $buscar = '', $estado = 'todas', $fechaDesde = '', $fechaHasta = '') {
        $whereConditions = ["c.id_medico = :medico_id", "c.tipo_cita = 'virtual'"];
        $params = ['medico_id' => $medicoId];

        if ($buscar) {
            $whereConditions[] = "(CONCAT(p.nombre, ' ', p.apellido) LIKE :buscar OR p.cedula LIKE :buscar)";
            $params['buscar'] = "%{$buscar}%";
        }

        if ($estado !== 'todas') {
            $whereConditions[] = "c.estado_cita = :estado";
            $params['estado'] = $estado;
        }

        if ($fechaDesde) {
            $whereConditions[] = "c.fecha_cita >= :fecha_desde";
            $params['fecha_desde'] = $fechaDesde;
        }

        if ($fechaHasta) {
            $whereConditions[] = "c.fecha_cita <= :fecha_hasta";
            $params['fecha_hasta'] = $fechaHasta;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT COUNT(*) FROM citas c
            INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
            WHERE {$whereClause}";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    /**
     * Obtener historial de consultas virtuales para paciente
     */
    public function getHistorialVirtualPaciente($pacienteId, $buscar = '', $estado = 'todas', $fechaDesde = '', $fechaHasta = '', $limit = 15, $offset = 0) {
        $whereConditions = ["c.id_paciente = :paciente_id", "c.tipo_cita = 'virtual'"];
        $params = ['paciente_id' => $pacienteId];

        if ($buscar) {
            $whereConditions[] = "(CONCAT(m.nombre, ' ', m.apellido) LIKE :buscar OR e.nombre_especialidad LIKE :buscar)";
            $params['buscar'] = "%{$buscar}%";
        }

        if ($estado !== 'todas') {
            $whereConditions[] = "c.estado_cita = :estado";
            $params['estado'] = $estado;
        }

        if ($fechaDesde) {
            $whereConditions[] = "c.fecha_cita >= :fecha_desde";
            $params['fecha_desde'] = $fechaDesde;
        }

        if ($fechaHasta) {
            $whereConditions[] = "c.fecha_cita <= :fecha_hasta";
            $params['fecha_hasta'] = $fechaHasta;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT c.*, 
                   CONCAT(p.nombre, ' ', p.apellido) as paciente_nombre,
                   CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
                   e.nombre_especialidad
            FROM citas c
            INNER JOIN usuarios p ON c.id_paciente = p.id_usuario
            INNER JOIN usuarios m ON c.id_medico = m.id_usuario
            INNER JOIN especialidades e ON c.id_especialidad = e.id_especialidad
            WHERE {$whereClause}
            ORDER BY c.fecha_cita DESC, c.hora_cita DESC
            LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contar historial de consultas virtuales para paciente
     */
    public function countHistorialVirtualPaciente($pacienteId, $buscar = '', $estado = 'todas', $fechaDesde = '', $fechaHasta = '') {
        $whereConditions = ["c.id_paciente = :paciente_id", "c.tipo_cita = 'virtual'"];
        $params = ['paciente_id' => $pacienteId];

        if ($buscar) {
            $whereConditions[] = "(CONCAT(m.nombre, ' ', m.apellido) LIKE :buscar OR e.nombre_especialidad LIKE :buscar)";
            $params['buscar'] = "%{$buscar}%";
        }

        if ($estado !== 'todas') {
            $whereConditions[] = "c.estado_cita = :estado";
            $params['estado'] = $estado;
        }

        if ($fechaDesde) {
            $whereConditions[] = "c.fecha_cita >= :fecha_desde";
            $params['fecha_desde'] = $fechaDesde;
        }

        if ($fechaHasta) {
            $whereConditions[] = "c.fecha_cita <= :fecha_hasta";
            $params['fecha_hasta'] = $fechaHasta;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT COUNT(*) FROM citas c
            INNER JOIN usuarios m ON c.id_medico = m.id_usuario
            INNER JOIN especialidades e ON c.id_especialidad = e.id_especialidad
            WHERE {$whereClause}";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchColumn();
    }
}

?>