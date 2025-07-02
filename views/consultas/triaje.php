<?php
// Redirigir según el rol del usuario
switch ($_SESSION['role_id']) {
    case 1: // Administrador
        header('Location: index.php?action=consultas/triaje/index');
        break;
    case 2: // Recepcionista
        header('Location: index.php?action=consultas/triaje/gestionar');
        break;
    case 3: // Médico
        header('Location: index.php?action=consultas/triaje/ver');
        break;
    case 4: // Paciente
        header('Location: index.php?action=consultas/triaje/completar');
        break;
    default:
        header('Location: index.php?action=dashboard');
}
exit;
?>