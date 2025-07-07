<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telemedicina - Administrador</title>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="fas fa-video"></i> Telemedicina - Vista Administrador</h1>
        <div class="alert alert-info">
            <h4>¡Funciona correctamente!</h4>
            <p>Esta es la vista para <strong>Administradores</strong></p>
            <p>Tu rol actual: <?php echo $_SESSION['role_id'] ?? 'No definido'; ?></p>
            <p>Aquí verás todas las consultas virtuales del sistema</p>
        </div>
        
        <div class="card">
            <div class="card-body">
                <h5>Próximamente:</h5>
                <ul>
                    <li>Lista de todas las consultas virtuales</li>
                    <li>Monitoreo en tiempo real</li>
                    <li>Reportes y estadísticas</li>
                    <li>Gestión de médicos en línea</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>