<?php

require_once 'config/database.php';
require_once 'includes/password-generator.php';

class User {

    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    // Método de login existente
    public function login($username, $password) {
        $sql = "SELECT u.*, r.nombre_rol 
                FROM usuarios u 
                INNER JOIN roles r ON u.id_rol = r.id_rol 
                WHERE (u.username = :username OR u.email = :username) 
                AND u.activo = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && base64_decode($user['password']) === $password) {
            $this->updateLastAccess($user['id_usuario']);
            return $user;
        }

        return false;
    }

    // Método cambio de contraseña existente
    public function changePassword($userId, $newPassword) {
        $sql = "UPDATE usuarios SET 
                password = :password, 
                requiere_cambio_contrasena = 0,
                clave_temporal = NULL
                WHERE id_usuario = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
                    'password' => base64_encode($newPassword),
                    'id' => $userId
        ]);
    }

    // Agregar este método a models/User.php

    public function getPacientes() {
        $sql = "SELECT id_usuario, username, email, cedula, nombre, apellido, 
                   fecha_nacimiento, genero, telefono, activo, fecha_registro
            FROM usuarios 
            WHERE id_rol = 4 
            ORDER BY nombre, apellido";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Buscar usuario por cédula
    public function getUserByCedula($cedula) {
        $sql = "SELECT * FROM usuarios WHERE cedula = :cedula AND activo = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cedula' => $cedula]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function toggleUserStatus($userId) {
        $sql = "UPDATE usuarios SET activo = CASE WHEN activo = 1 THEN 0 ELSE 1 END WHERE id_usuario = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $userId]);
    }

    // CRUD - Obtener todos los usuarios activos con paginación
    public function getAllUsers($page = 1, $limit = 10, $search = '', $roleFilter = '') {
        $offset = ($page - 1) * $limit;

        $whereConditions = ["u.activo = 1"];
        $params = [];

        if (!empty($search)) {
            $whereConditions[] = "(u.nombre LIKE :search OR u.apellido LIKE :search OR u.username LIKE :search OR u.email LIKE :search OR u.cedula LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        if (!empty($roleFilter)) {
            $whereConditions[] = "u.id_rol = :role_filter";
            $params['role_filter'] = $roleFilter;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT u.*, r.nombre_rol, s.nombre_sucursal
                FROM usuarios u 
                INNER JOIN roles r ON u.id_rol = r.id_rol 
                LEFT JOIN sucursales s ON u.id_sucursal = s.id_sucursal
                WHERE {$whereClause}
                ORDER BY u.fecha_registro DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);

        // Bind parámetros
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Contar total de usuarios para paginación
    public function countUsers($search = '', $roleFilter = '') {
        $whereConditions = ["u.activo = 1"];
        $params = [];

        if (!empty($search)) {
            $whereConditions[] = "(u.nombre LIKE :search OR u.apellido LIKE :search OR u.username LIKE :search OR u.email LIKE :search OR u.cedula LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        if (!empty($roleFilter)) {
            $whereConditions[] = "u.id_rol = :role_filter";
            $params['role_filter'] = $roleFilter;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $sql = "SELECT COUNT(*) as total 
                FROM usuarios u 
                INNER JOIN roles r ON u.id_rol = r.id_rol 
                WHERE {$whereClause}";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'];
    }

    // Obtener usuario por ID
    public function getUserById($id) {
        $sql = "SELECT u.*, r.nombre_rol 
                FROM usuarios u 
                INNER JOIN roles r ON u.id_rol = r.id_rol 
                WHERE u.id_usuario = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    // Crear nuevo usuario
    // Crear nuevo usuario
    public function createUser($data) {
        try {
            $this->db->beginTransaction();

            // Verificar si username, email o cédula ya existen
            if ($this->usernameExists($data['username'])) {
                throw new Exception("El nombre de usuario ya existe");
            }

            if ($this->emailExists($data['email'])) {
                throw new Exception("El email ya existe");
            }

            if (!empty($data['cedula']) && $this->cedulaExists($data['cedula'])) {
                throw new Exception("La cédula ya existe");
            }

            // Generar contraseña temporal automática
            $passwordTemporal = generarPasswordTemporal(8);

            // Insertar usuario
            $sql = "INSERT INTO usuarios (
                    username, email, password, cedula, nombre, apellido, 
                    fecha_nacimiento, genero, telefono, direccion, 
                    id_rol, id_sucursal, requiere_cambio_contrasena, clave_temporal
                ) VALUES (
                    :username, :email, :password, :cedula, :nombre, :apellido, 
                    :fecha_nacimiento, :genero, :telefono, :direccion, 
                    :id_rol, :id_sucursal, 1, :clave_temporal
                )";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => base64_encode($passwordTemporal),
                'cedula' => $data['cedula'] ?? null,
                'nombre' => $data['nombre'],
                'apellido' => $data['apellido'],
                'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
                'genero' => $data['genero'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'id_rol' => $data['id_rol'],
                'id_sucursal' => $data['id_sucursal'] ?? null,
                'clave_temporal' => $passwordTemporal
            ]);

            $userId = $this->db->lastInsertId();

            // Si es médico, agregar especialidades
            if ($data['id_rol'] == 3 && !empty($data['especialidades'])) {
                $this->addMedicoEspecialidades($userId, $data['especialidades']);
            }

            // Enviar email con credenciales
            $nombreCompleto = $data['nombre'] . ' ' . $data['apellido'];
            $emailEnviado = enviarCredencialesPorEmail(
                    $data['email'],
                    $data['username'],
                    $passwordTemporal,
                    $nombreCompleto
            );

            $this->db->commit();

            return [
                'user_id' => $userId,
                'password_temporal' => $passwordTemporal,
                'email_enviado' => $emailEnviado
            ];
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // Actualizar usuario
    public function updateUser($id, $data) {
        try {
            $this->db->beginTransaction();

            // Verificar unicidad (excluyendo el usuario actual)
            if ($this->usernameExists($data['username'], $id)) {
                throw new Exception("El nombre de usuario ya existe");
            }

            if ($this->emailExists($data['email'], $id)) {
                throw new Exception("El email ya existe");
            }

            if (!empty($data['cedula']) && $this->cedulaExists($data['cedula'], $id)) {
                throw new Exception("La cédula ya existe");
            }

            // Actualizar datos básicos
            $sql = "UPDATE usuarios SET 
            username = :username, email = :email, cedula = :cedula, 
            nombre = :nombre, apellido = :apellido, 
            fecha_nacimiento = :fecha_nacimiento, genero = :genero, 
            telefono = :telefono, direccion = :direccion, 
            id_rol = :id_rol, id_sucursal = :id_sucursal
        WHERE id_usuario = :id";

            $params = [
                'username' => $data['username'],
                'email' => $data['email'],
                'cedula' => $data['cedula'] ?? null,
                'nombre' => $data['nombre'],
                'apellido' => $data['apellido'],
                'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
                'genero' => $data['genero'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'id_rol' => $data['id_rol'],
                'id_sucursal' => $data['id_sucursal'] ?? null,
                'id' => $id
            ];

            // Actualizar contraseña solo si se proporciona
            if (!empty($data['password'])) {
                $sql = str_replace('id_sucursal = :id_sucursal', 'id_sucursal = :id_sucursal, password = :password, requiere_cambio_contrasena = 1', $sql);
                $params['password'] = base64_encode($data['password']);
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Si es médico, actualizar especialidades
            if ($data['id_rol'] == 3) {
                $this->updateMedicoEspecialidades($id, $data['especialidades'] ?? []);
            } else {
                // Si cambió de rol y ya no es médico, eliminar especialidades
                $this->removeMedicoEspecialidades($id);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // Eliminar usuario (lógico)
    public function deleteUser($id) {
        $sql = "UPDATE usuarios SET activo = 0 WHERE id_usuario = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    // Métodos auxiliares para validación
    private function usernameExists($username, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE username = :username";
        $params = ['username' => $username];

        if ($excludeId) {
            $sql .= " AND id_usuario != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    private function emailExists($email, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE email = :email";
        $params = ['email' => $email];

        if ($excludeId) {
            $sql .= " AND id_usuario != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    private function cedulaExists($cedula, $excludeId = null) {
        $sql = "SELECT COUNT(*) FROM usuarios WHERE cedula = :cedula";
        $params = ['cedula' => $cedula];

        if ($excludeId) {
            $sql .= " AND id_usuario != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    }

    // Métodos para manejo de especialidades de médicos
    private function addMedicoEspecialidades($medicoId, $especialidades) {
        foreach ($especialidades as $especialidadId) {
            $sql = "INSERT INTO medico_especialidades (id_medico, id_especialidad) 
                    VALUES (:medico_id, :especialidad_id)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'medico_id' => $medicoId,
                'especialidad_id' => $especialidadId
            ]);
        }
    }

    private function updateMedicoEspecialidades($medicoId, $especialidades) {
        // Eliminar especialidades existentes
        $this->removeMedicoEspecialidades($medicoId);

        // Agregar nuevas especialidades
        if (!empty($especialidades)) {
            $this->addMedicoEspecialidades($medicoId, $especialidades);
        }
    }

    private function removeMedicoEspecialidades($medicoId) {
        $sql = "DELETE FROM medico_especialidades WHERE id_medico = :medico_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['medico_id' => $medicoId]);
    }

    // Obtener especialidades de un médico
    public function getMedicoEspecialidades($medicoId) {
        $sql = "SELECT e.id_especialidad, e.nombre_especialidad 
                FROM medico_especialidades me
                INNER JOIN especialidades e ON me.id_especialidad = e.id_especialidad
                WHERE me.id_medico = :medico_id AND me.activo = 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['medico_id' => $medicoId]);
        return $stmt->fetchAll();
    }

    // Método existente
    private function updateLastAccess($userId) {
        $sql = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $userId]);
    }

    // Actualizar solo datos de perfil (sin rol ni sucursal)
    public function updateUserProfile($id, $data) {
        try {
            $this->db->beginTransaction();

            // Verificar unicidad (excluyendo el usuario actual)
            if ($this->usernameExists($data['username'], $id)) {
                throw new Exception("El nombre de usuario ya existe");
            }

            if ($this->emailExists($data['email'], $id)) {
                throw new Exception("El email ya existe");
            }

            if (!empty($data['cedula']) && $this->cedulaExists($data['cedula'], $id)) {
                throw new Exception("La cédula ya existe");
            }

            $sql = "UPDATE usuarios SET 
                    username = :username, 
                    email = :email, 
                    nombre = :nombre, 
                    apellido = :apellido, 
                    fecha_nacimiento = :fecha_nacimiento, 
                    genero = :genero, 
                    telefono = :telefono, 
                    direccion = :direccion,
                    cedula = :cedula
                    WHERE id_usuario = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'username' => $data['username'],
                'email' => $data['email'],
                'nombre' => $data['nombre'],
                'apellido' => $data['apellido'],
                'fecha_nacimiento' => $data['fecha_nacimiento'],
                'genero' => $data['genero'],
                'telefono' => $data['telefono'],
                'direccion' => $data['direccion'],
                'cedula' => $data['cedula'],
                'id' => $id
            ]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // Cambiar contraseña sin requerir cambio forzado
    public function changeUserPassword($userId, $newPassword) {
        $sql = "UPDATE usuarios SET password = :password WHERE id_usuario = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
                    'password' => base64_encode($newPassword),
                    'id' => $userId
        ]);
    }

    public function getHistorialPaciente($pacienteId) {
        $sql = "SELECT 
                c.id_cita,
                c.fecha_cita,
                c.hora_cita,
                c.tipo_cita,
                c.motivo_consulta,
                c.estado_cita,
                CONCAT(m.nombre, ' ', m.apellido) as medico_nombre,
                e.nombre_especialidad,
                s.nombre_sucursal,
                
                -- Datos de la consulta médica
                con.id_consulta,
                con.diagnostico_principal,
                con.diagnosticos_secundarios,
                con.tratamiento,
                con.sintomas,
                con.examen_fisico,
                con.recomendaciones,
                con.observaciones_medicas,
                con.fecha_consulta,
                
                -- Contar recetas asociadas
                (SELECT COUNT(*) 
                 FROM recetas r 
                 WHERE r.id_consulta = con.id_consulta) as total_recetas
                
            FROM citas c
            INNER JOIN usuarios m ON c.id_medico = m.id_usuario
            INNER JOIN especialidades e ON c.id_especialidad = e.id_especialidad  
            INNER JOIN sucursales s ON c.id_sucursal = s.id_sucursal
            LEFT JOIN consultas con ON c.id_cita = con.id_cita
            WHERE c.id_paciente = :paciente_id
            ORDER BY c.fecha_cita DESC, c.hora_cita DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['paciente_id' => $pacienteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Agregar este método al archivo models/User.php
    public function getHistorialPacienteCompleto($pacienteId) {
        $sql = "SELECT 
            c.id_cita,
            c.fecha_cita,
            c.hora_cita,
            c.tipo_cita,
            c.estado_cita,
            c.motivo_consulta,
            CONCAT(m.nombre, ' ', m.apellido) as nombre_medico,
            e.nombre_especialidad,
            s.nombre_sucursal,
            con.diagnostico_principal,
            con.tratamiento,
            con.observaciones_medicas,
            con.fecha_consulta
        FROM citas c
        INNER JOIN usuarios m ON c.id_medico = m.id_usuario
        INNER JOIN especialidades e ON c.id_especialidad = e.id_especialidad
        INNER JOIN sucursales s ON c.id_sucursal = s.id_sucursal
        LEFT JOIN consultas con ON c.id_cita = con.id_cita
        WHERE c.id_paciente = :paciente_id
        ORDER BY c.fecha_cita DESC, c.hora_cita DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['paciente_id' => $pacienteId]);
        return $stmt->fetchAll();
    }

    public function getRecetasConsulta($consultaId) {
        $sql = "SELECT 
                r.id_receta,
                r.codigo_receta,
                r.medicamento,
                r.concentracion,
                r.forma_farmaceutica,
                r.dosis,
                r.frecuencia,
                r.duracion,
                r.cantidad,
                r.indicaciones_especiales,
                r.fecha_emision,
                r.fecha_vencimiento,
                r.estado
            FROM recetas r
            WHERE r.id_consulta = :consulta_id
            ORDER BY r.fecha_emision DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['consulta_id' => $consultaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener lista de pacientes para historial médico
     * @param string $search Término de búsqueda opcional
     * @return array Lista de pacientes
     */
    public function getPacientesList($search = '') {
        try {
            $sql = "SELECT 
                    u.id_usuario,
                    u.nombre,
                    u.apellido,
                    u.cedula,
                    u.email,
                    u.telefono,
                    u.fecha_registro,
                    u.activo,
                    COUNT(c.id_cita) as total_citas,
                    MAX(c.fecha_cita) as ultima_cita
                FROM usuarios u 
                LEFT JOIN citas c ON u.id_usuario = c.id_paciente
                WHERE u.id_rol = 4 
                AND u.activo = 1";

            $params = [];

            if (!empty($search)) {
                $sql .= " AND (
                u.nombre LIKE :search OR 
                u.apellido LIKE :search OR 
                u.cedula LIKE :search OR
                CONCAT(u.nombre, ' ', u.apellido) LIKE :search
            )";
                $params['search'] = "%{$search}%";
            }

            $sql .= " GROUP BY u.id_usuario
                  ORDER BY u.nombre ASC, u.apellido ASC 
                  LIMIT 50";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en getPacientesList: " . $e->getMessage());
            return [];
        }
    }
}

?>