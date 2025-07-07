<?php
require_once 'config/database.php';
require_once 'models/UserModel.php';

class AjaxController {
    private $db;
    private $userModel;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->userModel = new UserModel($this->db);
    }

    public function obtenerRecetas() {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'No autenticado']);
            return;
        }

        $citaId = $_GET['cita_id'] ?? '';
        
        if (empty($citaId)) {
            echo json_encode(['success' => false, 'message' => 'ID de cita requerido']);
            return;
        }

        try {
            // Verificar permisos según el rol
            $wherePermiso = '';
            $params = ['cita_id' => $citaId];

            if ($_SESSION['role_id'] == 3) { // Médico
                $wherePermiso = " AND cit.id_medico = :id_medico";
                $params['id_medico'] = $_SESSION['user_id'];
            } elseif ($_SESSION['role_id'] == 4) { // Paciente
                $wherePermiso = " AND cit.id_paciente = :id_paciente";
                $params['id_paciente'] = $_SESSION['user_id'];
            }

            $sql = "SELECT r.*
                    FROM recetas r
                    INNER JOIN consultas c ON r.id_consulta = c.id_consulta
                    INNER JOIN citas cit ON c.id_cita = cit.id_cita
                    WHERE cit.id_cita = :cita_id" . $wherePermiso . "
                    ORDER BY r.fecha_emision DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $recetas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'recetas' => $recetas]);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al obtener recetas: ' . $e->getMessage()]);
        }
    }

    public function obtenerDetallesCita() {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'No autenticado']);
            return;
        }

        $citaId = $_GET['cita_id'] ?? '';
        
        if (empty($citaId)) {
            echo json_encode(['success' => false, 'message' => 'ID de cita requerido']);
            return;
        }

        try {
            // Verificar permisos según el rol
            $wherePermiso = '';
            $params = ['cita_id' => $citaId];

            if ($_SESSION['role_id'] == 3) { // Médico
                $wherePermiso = " AND cit.id_medico = :id_medico";
                $params['id_medico'] = $_SESSION['user_id'];
            } elseif ($_SESSION['role_id'] == 4) { // Paciente
                $wherePermiso = " AND cit.id_paciente = :id_paciente";
                $params['id_paciente'] = $_SESSION['user_id'];
            }

            // Obtener detalles completos de la cita
            $sql = "SELECT cit.*, 
                           CONCAT(p.nombre, ' ', p.apellido) as nombre_paciente,
                           CONCAT(m.nombre, ' ', m.apellido) as nombre_medico,
                           m.telefono as medico_telefono,
                           e.nombre_especialidad,
                           s.nombre_sucursal,
                           c.diagnostico_principal,
                           c.tratamiento,
                           c.observaciones_medicas
                    FROM citas cit
                    INNER JOIN usuarios p ON cit.id_paciente = p.id_usuario
                    INNER JOIN usuarios m ON cit.id_medico = m.id_usuario
                    INNER JOIN especialidades e ON cit.id_especialidad = e.id_especialidad
                    INNER JOIN sucursales s ON cit.id_sucursal = s.id_sucursal
                    LEFT JOIN consultas c ON cit.id_cita = c.id_cita
                    WHERE cit.id_cita = :cita_id" . $wherePermiso;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $detalles = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$detalles) {
                echo json_encode(['success' => false, 'message' => 'Cita no encontrada o sin permisos']);
                return;
            }

            // Obtener signos vitales si existen
            $sqlSignos = "SELECT * FROM signos_vitales WHERE id_cita = :cita_id ORDER BY fecha_registro DESC LIMIT 1";
            $stmtSignos = $this->db->prepare($sqlSignos);
            $stmtSignos->execute(['cita_id' => $citaId]);
            $signosVitales = $stmtSignos->fetch(PDO::FETCH_ASSOC);

            if ($signosVitales) {
                $detalles['signos_vitales'] = $signosVitales;
            }

            echo json_encode(['success' => true, 'detalles' => $detalles]);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error al obtener detalles: ' . $e->getMessage()]);
        }
    }
}

// Manejo de peticiones AJAX
if (isset($_GET['action'])) {
    $controller = new AjaxController();
    
    switch ($_GET['action']) {
        case 'ajax/obtener_recetas':
            $controller->obtenerRecetas();
            break;
        case 'ajax/obtener_detalles_cita':
            $controller->obtenerDetallesCita();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
            break;
    }
}
?>