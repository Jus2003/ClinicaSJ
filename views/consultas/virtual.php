<?php
// views/consultas/virtual.php

// Verificar autenticación (ya se maneja en index.php pero por seguridad)
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Verificar permisos - Solo médicos y pacientes pueden acceder a telemedicina
if (!in_array($_SESSION['role_id'], [3, 4])) {
    header('Location: index.php?action=dashboard');
    exit;
}

// Redirigir según el rol del usuario
switch ($_SESSION['role_id']) {
    case 3: // Médico - Panel completo de telemedicina
        header('Location: index.php?action=consultas/virtual/medico');
        break;
        
    case 4: // Paciente - Vista de consultas virtuales
        header('Location: index.php?action=consultas/virtual/paciente');
        break;
        
    default:
        // Si no tiene rol válido, redirigir al dashboard
        header('Location: index.php?action=dashboard');
}
exit;
?>