<?php
// Verificar autenticación (ya se maneja en index.php pero por seguridad)
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Redirigir según el rol del usuario
switch ($_SESSION['role_id']) {
    case 1: // Administrador - Acceso completo
        header('Location: index.php?action=consultas/recetas/index');
        break;
        
    case 2: // Recepcionista - Gestión básica (solo lectura)
        header('Location: index.php?action=consultas/recetas/gestionar');
        break;
        
    case 3: // Médico - Puede crear y gestionar sus recetas
        header('Location: index.php?action=consultas/recetas/index');
        break;
        
    case 4: // Paciente - Solo ver sus propias recetas
        header('Location: index.php?action=consultas/recetas/ver');
        break;
        
    default:
        // Si no tiene rol válido, redirigir al dashboard
        header('Location: index.php?action=dashboard');
}
exit;
?>