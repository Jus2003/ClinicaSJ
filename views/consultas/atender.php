<?php
// Verificar autenticación (ya se maneja en index.php pero por seguridad)
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Redirigir según el rol del usuario
switch ($_SESSION['role_id']) {
    case 1: // Administrador - Acceso completo
        header('Location: index.php?action=consultas/atender/index');
        break;
        
    case 2: // Recepcionista - Solo lectura/consulta
        header('Location: index.php?action=consultas/atender/index');
        break;
        
    case 3: // Médico - Funcionalidad completa
        header('Location: index.php?action=consultas/atender/index');
        break;
        
    case 4: // Paciente - No tiene acceso
        header('Location: index.php?action=dashboard');
        break;
        
    default:
        // Si no tiene rol válido, redirigir al dashboard
        header('Location: index.php?action=dashboard');
}
exit;
?>