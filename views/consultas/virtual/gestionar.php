<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telemedicina - Recepcionista</title>
</head>
<body>
    <div class="container mt-4">
        <h1><i class="fas fa-headset"></i> Telemedicina - Vista Recepcionista</h1>
        <div class="alert alert-warning">
            <h4>¡Funciona correctamente!</h4>
            <p>Esta es la vista para <strong>Recepcionistas</strong></p>
            <p>Tu rol actual: <?php echo $_SESSION['role_id'] ?? 'No definido'; ?></p>
            <p>Aquí puedes monitorear las consultas virtuales</p>
        </div>
        
        <div class="card">
            <div class="card-body">
                <h5>Próximamente:</h5>
                <ul>
                    <li>Monitor de consultas en curso</li>
                    <li>Soporte técnico a pacientes</li>
                    <li>Agenda de citas virtuales</li>
                    <li>Reportes de conectividad</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>