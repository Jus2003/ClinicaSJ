<?php

require_once 'models/User.php';

class PerfilController {

    private $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    public function datos() {
        // Verificar autenticación
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $error = '';
        $success = '';

        // Obtener datos actuales del usuario
        $usuario = $this->userModel->getUserById($_SESSION['user_id']);

        // Procesar actualización de datos
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $data = [
                    'username' => trim($_POST['username']),
                    'email' => trim($_POST['email']),
                    'nombre' => trim($_POST['nombre']),
                    'apellido' => trim($_POST['apellido']),
                    'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?: null,
                    'genero' => $_POST['genero'] ?: null,
                    'telefono' => trim($_POST['telefono'] ?? '') ?: null,
                    'direccion' => trim($_POST['direccion'] ?? '') ?: null,
                    'cedula' => trim($_POST['cedula'] ?? '') ?: null
                ];

                // Validaciones básicas
                if (empty($data['username']) || empty($data['email']) ||
                        empty($data['nombre']) || empty($data['apellido'])) {
                    throw new Exception("Por favor complete todos los campos obligatorios");
                }

                if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("El email no tiene un formato válido");
                }

                // Actualizar datos personales (sin cambiar rol ni sucursal)
                $this->userModel->updateUserProfile($_SESSION['user_id'], $data);

                // Actualizar datos en sesión
                $_SESSION['nombre_completo'] = $data['nombre'] . ' ' . $data['apellido'];
                $_SESSION['username'] = $data['username'];
                $_SESSION['email'] = $data['email'];

                $success = "Datos actualizados exitosamente";

                // Recargar datos del usuario
                $usuario = $this->userModel->getUserById($_SESSION['user_id']);
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        include 'views/perfil/datos.php';
    }

    public function password() {
        // Verificar autenticación
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        $error = '';
        $success = '';

        // Procesar cambio de contraseña
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentPassword = trim($_POST['current_password'] ?? '');
            $newPassword = trim($_POST['new_password'] ?? '');
            $confirmPassword = trim($_POST['confirm_password'] ?? '');

            try {
                // Validaciones
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    throw new Exception("Por favor complete todos los campos");
                }

                // Validar que la nueva contraseña tenga exactamente 6 dígitos
                if (!preg_match('/^\d{6}$/', $newPassword)) {
                    throw new Exception("La nueva contraseña debe contener exactamente 6 números");
                }

                if ($newPassword !== $confirmPassword) {
                    throw new Exception("Las contraseñas no coinciden");
                }

                // Verificar contraseña actual
                $usuario = $this->userModel->getUserById($_SESSION['user_id']);
                if (base64_decode($usuario['password']) !== $currentPassword) {
                    throw new Exception("La contraseña actual es incorrecta");
                }

                // Cambiar contraseña
                if ($this->userModel->changeUserPassword($_SESSION['user_id'], $newPassword)) {
                    $success = "Contraseña cambiada exitosamente";
                } else {
                    throw new Exception("Error al cambiar la contraseña");
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        include 'views/perfil/password.php';
    }

    public function notificaciones() {
        // Verificar autenticación
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }

        // Manejar peticiones AJAX
        if (isset($_POST['ajax_action'])) {
            $this->handleNotificacionAjax();
            return;
        }

        // Cargar vista normal
        include 'views/perfil/notificaciones.php';
    }

    private function handleNotificacionAjax() {
        header('Content-Type: application/json');

        // Debug logging
        error_log("=== AJAX NOTIFICACIONES DEBUG ===");
        error_log("POST data: " . print_r($_POST, true));
        error_log("Session data: " . print_r($_SESSION, true));

        require_once 'models/Notificacion.php';
        $notificacionModel = new Notificacion();

        $action = $_POST['ajax_action'] ?? '';
        $response = ['success' => false, 'message' => ''];

        try {
            error_log("Procesando acción: " . $action);

            switch ($action) {
                case 'obtener_usuarios':
                    error_log("Verificando permisos - Role ID: " . ($_SESSION['role_id'] ?? 'no definido'));

                    // Solo admin puede ver lista de usuarios
                    if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
                        throw new Exception('No tiene permisos para esta acción. Role: ' . ($_SESSION['role_id'] ?? 'no definido'));
                    }

                    error_log("Obteniendo usuarios...");
                    $usuarios = $notificacionModel->getUsuariosParaNotificacion();
                    error_log("Usuarios obtenidos: " . count($usuarios));

                    $response['success'] = true;
                    $response['data'] = $usuarios;
                    break;

                case 'marcar_leida':
                    $id_notificacion = intval($_POST['id_notificacion'] ?? 0);
                    if ($notificacionModel->marcarComoLeida($id_notificacion)) {
                        $response['success'] = true;
                        $response['message'] = 'Notificación marcada como leída';
                    } else {
                        throw new Exception('Error al marcar como leída');
                    }
                    break;

                case 'marcar_todas_leidas':
                    $user_id = $_SESSION['user_id'];
                    $user_role = $_SESSION['role_id'];

                    if (in_array($user_role, [1, 2])) {
                        if ($notificacionModel->marcarTodasLeidasAdmin()) {
                            $response['success'] = true;
                            $response['message'] = 'Todas las notificaciones marcadas como leídas';
                        }
                    } else {
                        if ($notificacionModel->marcarTodasLeidasUsuario($user_id)) {
                            $response['success'] = true;
                            $response['message'] = 'Todas tus notificaciones marcadas como leídas';
                        }
                    }
                    break;

                case 'eliminar_notificacion':
                    $id_notificacion = intval($_POST['id_notificacion'] ?? 0);
                    if ($notificacionModel->eliminarNotificacion($id_notificacion)) {
                        $response['success'] = true;
                        $response['message'] = 'Notificación eliminada';
                    } else {
                        throw new Exception('Error al eliminar notificación');
                    }
                    break;

                case 'crear_notificacion_admin':
                    if ($_SESSION['role_id'] != 1) {
                        throw new Exception('No tiene permisos para esta acción');
                    }

                    $destinatarios = $_POST['destinatarios'] ?? [];
                    $titulo = trim($_POST['titulo'] ?? '');
                    $mensaje = trim($_POST['mensaje'] ?? '');
                    $enviar_email = ($_POST['enviar_email'] ?? '0') === '1';

                    if (empty($titulo) || empty($mensaje) || empty($destinatarios)) {
                        throw new Exception('Todos los campos son obligatorios');
                    }

                    $count = 0;
                    foreach ($destinatarios as $user_id) {
                        if ($notificacionModel->crearNotificacion($user_id, 'sistema', $titulo, $mensaje, null, $enviar_email)) {
                            $count++;
                        }
                    }

                    $response['success'] = true;
                    $response['message'] = "Notificación enviada a {$count} usuario(s)";
                    break;

                default:
                    throw new Exception('Acción no válida: ' . $action);
            }
        } catch (Exception $e) {
            error_log("Error en AJAX: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $response['message'] = $e->getMessage();
        }

        error_log("Response: " . json_encode($response));
        echo json_encode($response);
        exit;
    }
}

?>