<?php
// Solo obtener menús si aún no se han obtenido
if (!isset($menus) && isset($_SESSION['role_id'])) {
    require_once 'models/Menu.php';
    $menuModel = new Menu();
    $menus = $menuModel->getMenusByRole($_SESSION['role_id']);
}
?>