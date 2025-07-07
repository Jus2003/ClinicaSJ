<?php
// views/consultas/virtual/sala.php

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [3, 4])) {
    header('Location: index.php?action=dashboard');
    exit;
}

require_once 'models/Cita.php';
$citaModel = new Cita();

$citaId = $_GET['cita'] ?? 0;
if (!$citaId) {
    header('Location: index.php?action=consultas/virtual');
    exit;
}

// Verificar acceso a la cita
$cita = $citaModel->verificarAccesoConsultaVirtual($citaId, $_SESSION['user_id'], $_SESSION['role_id']);
if (!$cita) {
    header('Location: index.php?action=consultas/virtual');
    exit;
}

// Generar sala única si no existe
if (!$cita['enlace_virtual']) {
    $enlace = $citaModel->generarEnlaceVirtual($citaId);
    $cita['enlace_virtual'] = $enlace;
}

// Extraer ID de sala de Jitsi del enlace
$salaId = basename(parse_url($cita['enlace_virtual'], PHP_URL_PATH));

include 'views/includes/header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta Virtual - <?php echo htmlspecialchars($cita['paciente_nombre']); ?></title>
    <script src="https://meet.jit.si/external_api.js"></script>
    <style>
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        .container-sala { height: 100vh; display: flex; flex-direction: column; }
        .header-sala { background: #0d6efd; color: white; padding: 1rem; display: flex; justify-content: between; align-items: center; }
        .info-consulta { flex: 1; }
        .controles-sala { display: flex; gap: 1rem; align-items: center; }
        .video-container { flex: 1; background: #000; position: relative; }
        .panel-lateral { width: 300px; background: #f8f9fa; border-left: 1px solid #dee2e6; display: flex; flex-direction: column; }
        .chat-container { flex: 1; padding: 1rem; overflow-y: auto; }
        .notas-container { border-top: 1px solid #dee2e6; padding: 1rem; }
        .main-content { display: flex; flex: 1; }
        .btn-control { padding: 0.5rem 1rem; border: none; border-radius: 0.375rem; cursor: pointer; font-size: 0.875rem; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #000; }
        .btn-primary { background: #0d6efd; color: white; }
        .message { margin-bottom: 1rem; padding: 0.5rem; border-radius: 0.375rem; }
        .message.medico { background: #e3f2fd; border-left: 3px solid #2196f3; }
        .message.paciente { background: #f3e5f5; border-left: 3px solid #9c27b0; }
        .nota-item { background: white; border: 1px solid #dee2e6; border-radius: 0.375rem; padding: 0.75rem; margin-bottom: 0.5rem; }
        @media (max-width: 768px) {
            .main-content { flex-direction: column; }
            .panel-lateral { width: 100%; max-height: 300px; }
        }
    </style>
</head>
<body>
    <div class="container-sala">
        <!-- Header de la Sala -->
        <div class="header-sala">
            <div class="info-consulta">
                <h5 style="margin: 0; margin-bottom: 0.25rem;">
                    Consulta Virtual - <?php echo htmlspecialchars($cita['nombre_especialidad']); ?>
                </h5>
                <div style="font-size: 0.875rem; opacity: 0.9;">
                    <?php if ($_SESSION['role_id'] == 3): ?>
                        Paciente: <?php echo htmlspecialchars($cita['paciente_nombre']); ?>
                    <?php else: ?>
                        Dr. <?php echo htmlspecialchars($cita['medico_nombre']); ?>
                    <?php endif; ?>
                    | <?php echo date('d/m/Y H:i', strtotime($cita['fecha_cita'] . ' ' . $cita['hora_cita'])); ?>
                </div>
            </div>
            <div class="controles-sala">
                <button class="btn-control btn-warning" onclick="togglePanel()">
                    <i class="fas fa-comments"></i> Chat
                </button>
                <button class="btn-control btn-primary" onclick="toggleNotas()">
                    <i class="fas fa-sticky-note"></i> Notas
                </button>
                <button class="btn-control btn-danger" onclick="finalizarConsulta()">
                    <i class="fas fa-phone-slash"></i> Finalizar
                </button>
            </div>
        </div>

        <!-- Contenido Principal -->
        <div class="main-content">
            <!-- Contenedor de Video -->
            <div class="video-container" id="jitsi-container">
                <!-- Aquí se incrustará Jitsi Meet -->
            </div>

            <!-- Panel Lateral -->
            <div class="panel-lateral" id="panel-lateral">
                <!-- Chat -->
                <div class="chat-container" id="chat-container">
                    <h6 style="margin-bottom: 1rem; color: #495057;">
                        <i class="fas fa-comments"></i> Chat de la Consulta
                    </h6>
                    <div id="mensajes-chat">
                        <!-- Los mensajes se cargarán aquí -->
                    </div>
                    <div style="margin-top: 1rem;">
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" id="mensaje-input" 
                                   style="flex: 1; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 0.375rem;" 
                                   placeholder="Escribir mensaje...">
                            <button onclick="enviarMensaje()" 
                                    style="padding: 0.5rem 1rem; background: #0d6efd; color: white; border: none; border-radius: 0.375rem;">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Notas de la Consulta (Solo para Médico) -->
                <?php if ($_SESSION['role_id'] == 3): ?>
                <div class="notas-container" id="notas-container" style="display: none;">
                    <h6 style="margin-bottom: 1rem; color: #495057;">
                        <i class="fas fa-sticky-note"></i> Notas de la Consulta
                    </h6>
                    <div id="notas-list">
                        <!-- Las notas se cargarán aquí -->
                    </div>
                    <div style="margin-top: 1rem;">
                        <textarea id="nueva-nota" 
                                  style="width: 100%; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 0.375rem; resize: vertical;"
                                  rows="3" placeholder="Agregar nota médica..."></textarea>
                        <button onclick="guardarNota()" 
                                style="margin-top: 0.5rem; width: 100%; padding: 0.5rem; background: #28a745; color: white; border: none; border-radius: 0.375rem;">
                            <i class="fas fa-save"></i> Guardar Nota
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Configuración de Jitsi Meet
        const domain = 'meet.jit.si';
        const options = {
            roomName: '<?php echo $salaId; ?>',
            width: '100%',
            height: '100%',
            parentNode: document.querySelector('#jitsi-container'),
            userInfo: {
                displayName: '<?php echo $_SESSION['role_id'] == 3 ? 'Dr. ' . $_SESSION['nombre'] : $_SESSION['nombre']; ?>',
                email: '<?php echo $_SESSION['email']; ?>'
            },
            configOverwrite: {
                prejoinPageEnabled: false,
                disableInviteFunctions: true,
                startWithAudioMuted: false,
                startWithVideoMuted: false,
                enableWelcomePage: false,
                enableClosePage: false,
                disableDeepLinking: true,
                defaultLocalDisplayName: '<?php echo $_SESSION['role_id'] == 3 ? 'Dr. ' . $_SESSION['nombre'] : $_SESSION['nombre']; ?>'
            },
            interfaceConfigOverwrite: {
                TOOLBAR_BUTTONS: [
                    'microphone', 'camera', 'desktop', 'fullscreen',
                    'fodeviceselection', 'hangup', 'profile', 'chat',
                    'recording', 'livestreaming', 'etherpad', 'sharedvideo',
                    'settings', 'raisehand', 'videoquality', 'filmstrip',
                    'feedback', 'stats', 'shortcuts', 'tileview', 'videobackgroundblur',
                    'download', 'help', 'mute-everyone'
                ],
                SETTINGS_SECTIONS: ['devices', 'language', 'moderator', 'profile', 'calendar'],
                SHOW_JITSI_WATERMARK: false,
                SHOW_WATERMARK_FOR_GUESTS: false,
                SHOW_BRAND_WATERMARK: false,
                BRAND_WATERMARK_LINK: '',
                SHOW_POWERED_BY: false,
                DEFAULT_BACKGROUND: '#474747',
                DISABLE_VIDEO_BACKGROUND: false,
                INITIAL_TOOLBAR_TIMEOUT: 20000,
                TOOLBAR_TIMEOUT: 4000
            }
        };

        // Inicializar Jitsi Meet
        const api = new JitsiMeetExternalAPI(domain, options);

        // Variables globales
        const citaId = <?php echo $citaId; ?>;
        const userRole = <?php echo $_SESSION['role_id']; ?>;
        let panelVisible = true;
        let notasVisible = false;

        // Event listeners de Jitsi
        api.addEventListener('participantJoined', (participant) => {
            console.log('Participante se unió:', participant);
            agregarMensajeChat('Sistema', `${participant.displayName} se unió a la consulta`, 'sistema');
        });

        api.addEventListener('participantLeft', (participant) => {
            console.log('Participante salió:', participant);
            agregarMensajeChat('Sistema', `${participant.displayName} salió de la consulta`, 'sistema');
        });

        api.addEventListener('videoConferenceJoined', () => {
            console.log('Usuario se unió a la videollamada');
            // Marcar consulta como en curso
            fetch('views/consultas/virtual/api/iniciar_consulta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cita_id: citaId })
            });
        });

        api.addEventListener('videoConferenceLeft', () => {
            console.log('Usuario salió de la videollamada');
            // Opcional: manejar salida
        });

        // Funciones de la interfaz
        function togglePanel() {
            const panel = document.getElementById('panel-lateral');
            panelVisible = !panelVisible;
            panel.style.display = panelVisible ? 'flex' : 'none';
        }

        function toggleNotas() {
            if (userRole !== 3) return; // Solo médicos
            
            const chat = document.getElementById('chat-container');
            const notas = document.getElementById('notas-container');
            
            notasVisible = !notasVisible;
            
            if (notasVisible) {
                chat.style.display = 'none';
                notas.style.display = 'block';
                cargarNotas();
            } else {
                chat.style.display = 'block';
                notas.style.display = 'none';
            }
        }

        // Funciones de Chat
        function enviarMensaje() {
            const input = document.getElementById('mensaje-input');
            const mensaje = input.value.trim();
            
            if (!mensaje) return;
            
            fetch('views/consultas/virtual/api/enviar_mensaje.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    cita_id: citaId,
                    mensaje: mensaje
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    cargarMensajes();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function cargarMensajes() {
            fetch(`views/consultas/virtual/api/obtener_mensajes.php?cita_id=${citaId}`)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('mensajes-chat');
                    container.innerHTML = '';
                    
                    data.forEach(mensaje => {
                        agregarMensajeChat(mensaje.autor, mensaje.mensaje, mensaje.tipo, mensaje.fecha);
                    });
                    
                    container.scrollTop = container.scrollHeight;
                })
                .catch(error => console.error('Error:', error));
        }

        function agregarMensajeChat(autor, mensaje, tipo, fecha = null) {
            const container = document.getElementById('mensajes-chat');
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${tipo}`;
            
            const fechaStr = fecha ? new Date(fecha).toLocaleTimeString() : new Date().toLocaleTimeString();
            
            messageDiv.innerHTML = `
                <div style="font-weight: bold; font-size: 0.875rem; margin-bottom: 0.25rem;">
                    ${autor} <span style="font-weight: normal; opacity: 0.7;">${fechaStr}</span>
                </div>
                <div>${mensaje}</div>
            `;
            
            container.appendChild(messageDiv);
            container.scrollTop = container.scrollHeight;
        }

        // Funciones de Notas (Solo Médicos)
        function guardarNota() {
            if (userRole !== 3) return;
            
            const textarea = document.getElementById('nueva-nota');
            const nota = textarea.value.trim();
            
            if (!nota) return;
            
            fetch('views/consultas/virtual/api/guardar_nota.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    cita_id: citaId,
                    nota: nota
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    textarea.value = '';
                    cargarNotas();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function cargarNotas() {
            if (userRole !== 3) return;
            
            fetch(`views/consultas/virtual/api/obtener_notas.php?cita_id=${citaId}`)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('notas-list');
                    container.innerHTML = '';
                    
                    data.forEach(nota => {
                        const notaDiv = document.createElement('div');
                        notaDiv.className = 'nota-item';
                        notaDiv.innerHTML = `
                            <div style="font-size: 0.875rem; color: #6c757d; margin-bottom: 0.5rem;">
                                ${new Date(nota.fecha).toLocaleString()}
                            </div>
                            <div>${nota.contenido}</div>
                        `;
                        container.appendChild(notaDiv);
                    });
                })
                .catch(error => console.error('Error:', error));
        }

        function finalizarConsulta() {
            if (confirm('¿Está seguro de que desea finalizar la consulta?')) {
                // Finalizar consulta en el sistema
                fetch('views/consultas/virtual/api/finalizar_consulta.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ cita_id: citaId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        api.dispose();
                        window.close();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    api.dispose();
                    window.close();
                });
            }
        }

        // Eventos de teclado
        document.getElementById('mensaje-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                enviarMensaje();
            }
        });

        // Cargar datos iniciales
        document.addEventListener('DOMContentLoaded', function() {
            cargarMensajes();
            if (userRole === 3) {
                cargarNotas();
            }
            
            // Actualizar chat cada 5 segundos
            setInterval(cargarMensajes, 5000);
        });

        // Prevenir salida accidental
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = '';
        });
    </script>
</body>
</html>