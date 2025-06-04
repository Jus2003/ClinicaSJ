<?php
session_start();

// Obtener la acción de la URL
$action = $_GET['action'] ?? 'login';

// Rutas públicas que no requieren login
$publicRoutes = ['login', 'logout'];

// Verificar autenticación
$isPublic = in_array($action, $publicRoutes);
if (!$isPublic && !isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Convertir la acción en ruta de archivo
$viewPath = "views/{$action}.php";

// Si la vista no existe, ir a la página apropiada por defecto
if (!file_exists($viewPath)) {
    if (!isset($_SESSION['user_id'])) {
        $viewPath = "views/auth/login.php";
    } else {
        $viewPath = "views/dashboard/index.php";
    }
}

// Incluir la vista (que manejará toda su lógica)
include $viewPath;
?>