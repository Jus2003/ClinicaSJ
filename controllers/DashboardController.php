<?php
require_once 'models/Menu.php';

class DashboardController {
    private $menuModel;
    
    public function __construct() {
        $this->menuModel = new Menu();
    }
    
    public function index() {
        // Verificar si está logueado
        if (!isset($_SESSION['user_id'])) {
            header('Location: index.php?action=login');
            exit;
        }
        
        // Obtener menús según el rol
        $menus = $this->menuModel->getMenusByRole($_SESSION['role_id']);
        
        include 'views/dashboard/index.php';
    }
}
?>