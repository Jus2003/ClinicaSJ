<?php
// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

require_once 'models/Notificacion.php';
$notificacionModel = new Notificacion();

// Obtener notificaciones según el rol del usuario
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role_id'];

// Admin y recepcionista ven todas, otros solo las suyas
if (in_array($user_role, [1, 2])) {
    $notificaciones = $notificacionModel->getAllNotificaciones();
    $esAdmin = true;
} else {
    $notificaciones = $notificacionModel->getNotificacionesByUsuario($user_id);
    $esAdmin = false;
}

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Enlaces de navegación del perfil -->
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">
                        <i class="fas fa-cogs"></i> Configuración de Perfil
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="index.php?action=perfil/datos" class="list-group-item list-group-item-action">
                            <i class="fas fa-id-card me-2"></i>
                            Datos Personales
                        </a>
                        <a href="index.php?action=perfil/password" class="list-group-item list-group-item-action">
                            <i class="fas fa-key me-2"></i>
                            Cambiar Contraseña
                        </a>
                        <a href="index.php?action=perfil/notificaciones" class="list-group-item list-group-item-action active">
                            <i class="fas fa-bell me-2"></i>
                            Notificaciones
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenido principal de notificaciones -->
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-bell text-primary me-2"></i>
                            <?php echo $esAdmin ? 'Todas las Notificaciones' : 'Mis Notificaciones'; ?>
                        </h5>
                        <div class="d-flex gap-2">
                            <?php if ($esAdmin): ?>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevaNotificacion">
                                    <i class="fas fa-plus me-1"></i>Nueva Notificación
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-outline-secondary btn-sm" onclick="marcarTodasLeidas()">
                                <i class="fas fa-check-double me-1"></i>Marcar todas como leídas
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card-body border-bottom">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <select class="form-select" id="filtroTipo">
                                <option value="">Todos los tipos</option>
                                <option value="cita_agendada">Cita Agendada</option>
                                <option value="cita_recordatorio">Recordatorio</option>
                                <option value="cita_cancelada">Cita Cancelada</option>
                                <option value="receta_disponible">Receta Disponible</option>
                                <option value="resultado_laboratorio">Resultado Laboratorio</option>
                                <option value="sistema">Sistema</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" id="filtroEstado">
                                <option value="">Todas</option>
                                <option value="1">Solo leídas</option>
                                <option value="0">Solo no leídas</option>
                            </select>
                        </div>
                        <?php if ($esAdmin): ?>
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="filtroUsuario" placeholder="Buscar por usuario...">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lista de notificaciones -->
                <div class="card-body p-0">
                    <?php if (empty($notificaciones)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No hay notificaciones</h5>
                            <p class="text-muted">Cuando recibas notificaciones aparecerán aquí.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush" id="listaNotificaciones">
                            <?php foreach ($notificaciones as $notif): ?>
                                <div class="list-group-item border-0 notification-item <?php echo $notif['leida'] ? '' : 'notification-unread'; ?>" data-id="<?php echo $notif['id_notificacion']; ?>">
                                    <div class="d-flex w-100 justify-content-between align-items-start">
                                        <div class="flex-grow-1 me-3">
                                            <div class="d-flex align-items-center mb-2">
                                                <?php
                                                $iconos = [
                                                    'cita_agendada' => 'fas fa-calendar-plus text-success',
                                                    'cita_recordatorio' => 'fas fa-clock text-warning',
                                                    'cita_cancelada' => 'fas fa-calendar-times text-danger',
                                                    'receta_disponible' => 'fas fa-prescription text-info',
                                                    'resultado_laboratorio' => 'fas fa-flask text-primary',
                                                    'sistema' => 'fas fa-cog text-secondary'
                                                ];
                                                $icono = $iconos[$notif['tipo_notificacion']] ?? 'fas fa-bell text-muted';
                                                ?>
                                                <i class="<?php echo $icono; ?> me-2"></i>
                                                <h6 class="mb-0 <?php echo!$notif['leida'] ? 'fw-bold' : ''; ?>">
                                                    <?php echo htmlspecialchars($notif['titulo']); ?>
                                                </h6>
                                                <?php if (!$notif['leida']): ?>
                                                    <span class="badge bg-primary ms-2">Nueva</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-1 text-muted">
                                                <?php echo htmlspecialchars($notif['mensaje']); ?>
                                            </p>
                                            <?php if ($esAdmin): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>
                                                    Para: <?php echo htmlspecialchars($notif['nombre_destinatario'] ?? 'Usuario'); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted">
                                                <?php echo date('d/m/Y H:i', strtotime($notif['fecha_creacion'])); ?>
                                            </small>
                                            <div class="mt-2">
                                                <?php if (!$notif['leida']): ?>
                                                    <button class="btn btn-outline-primary btn-sm" onclick="marcarLeida(<?php echo $notif['id_notificacion']; ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-danger btn-sm" onclick="eliminarNotificacion(<?php echo $notif['id_notificacion']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($esAdmin): ?>
        <!-- Modal Nueva Notificación -->
        <div class="modal fade" id="modalNuevaNotificacion" tabindex="-1" aria-labelledby="modalNuevaNotificacionLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalNuevaNotificacionLabel">
                            <i class="fas fa-plus-circle text-primary me-2"></i>
                            Nueva Notificación del Sistema
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="formNuevaNotificacion">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="tituloNotificacion" class="form-label">
                                        <i class="fas fa-heading me-1"></i>Título *
                                    </label>
                                    <input type="text" class="form-control" id="tituloNotificacion" name="titulo" 
                                           placeholder="Ej: Mantenimiento programado del sistema" required maxlength="200">
                                    <div class="form-text">Máximo 200 caracteres</div>
                                </div>

                                <div class="col-12 mb-3">
                                    <label for="mensajeNotificacion" class="form-label">
                                        <i class="fas fa-envelope me-1"></i>Mensaje *
                                    </label>
                                    <textarea class="form-control" id="mensajeNotificacion" name="mensaje" rows="4" 
                                              placeholder="Escriba el mensaje detallado de la notificación..." required></textarea>
                                    <div class="form-text">Describa claramente la información que desea comunicar</div>
                                </div>

                                <div class="col-12 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-users me-1"></i>Destinatarios *
                                    </label>

                                    <!-- Opciones rápidas -->
                                    <div class="mb-3">
                                        <div class="btn-group" role="group" aria-label="Selección rápida">
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="seleccionarTodos()">
                                                <i class="fas fa-check-double me-1"></i>Todos
                                            </button>
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="seleccionarPorRol('Médico')">
                                                <i class="fas fa-user-md me-1"></i>Médicos
                                            </button>
                                            <button type="button" class="btn btn-outline-info btn-sm" onclick="seleccionarPorRol('Paciente')">
                                                <i class="fas fa-user me-1"></i>Pacientes
                                            </button>
                                            <button type="button" class="btn btn-outline-warning btn-sm" onclick="seleccionarPorRol('Recepcionista')">
                                                <i class="fas fa-user-tie me-1"></i>Recepcionistas
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="limpiarSeleccion()">
                                                <i class="fas fa-times me-1"></i>Limpiar
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Lista de usuarios -->
                                    <div class="border rounded p-3" style="max-height: 250px; overflow-y: auto;">
                                        <div id="listaDestinatarios">
                                            <div class="text-center py-3">
                                                <i class="fas fa-spinner fa-spin"></i> Cargando usuarios...
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-text">Seleccione uno o más destinatarios para la notificación</div>
                                </div>

                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="enviarEmail" name="enviar_email" checked>
                                        <label class="form-check-label" for="enviarEmail">
                                            <i class="fas fa-envelope me-1"></i>
                                            Enviar también por email
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i>Enviar Notificación
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<style>
    .notification-unread {
        background-color: #f8f9fa;
        border-left: 4px solid #007bff !important;
    }

    .notification-item:hover {
        background-color: #f8f9fa;
    }
</style>

<script>
    function marcarLeida(id) {
        fetch('index.php?action=perfil/notificaciones', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ajax_action=marcar_leida&id_notificacion=${id}`
        })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remover el badge "Nueva" y cambiar estilo
                        const item = document.querySelector(`[data-id="${id}"]`);
                        item.classList.remove('notification-unread');
                        const badge = item.querySelector('.badge');
                        if (badge)
                            badge.remove();
                        const button = item.querySelector('.btn-outline-primary');
                        if (button)
                            button.remove();

                        mostrarMensaje(data.message, 'success');
                    } else {
                        mostrarMensaje(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensaje('Error al marcar como leída', 'error');
                });
    }

    function marcarTodasLeidas() {
        if (!confirm('¿Marcar todas las notificaciones como leídas?'))
            return;

        fetch('index.php?action=perfil/notificaciones', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'ajax_action=marcar_todas_leidas'
        })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Actualizar todas las notificaciones visualmente
                        document.querySelectorAll('.notification-unread').forEach(item => {
                            item.classList.remove('notification-unread');
                        });
                        document.querySelectorAll('.badge').forEach(badge => badge.remove());
                        document.querySelectorAll('.btn-outline-primary').forEach(btn => btn.remove());

                        mostrarMensaje(data.message, 'success');
                    } else {
                        mostrarMensaje(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensaje('Error al marcar todas como leídas', 'error');
                });
    }

    function eliminarNotificacion(id) {
        if (!confirm('¿Está seguro de eliminar esta notificación?'))
            return;

        fetch('index.php?action=perfil/notificaciones', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ajax_action=eliminar_notificacion&id_notificacion=${id}`
        })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remover el elemento de la lista
                        const item = document.querySelector(`[data-id="${id}"]`);
                        item.style.transition = 'opacity 0.3s';
                        item.style.opacity = '0';
                        setTimeout(() => item.remove(), 300);

                        mostrarMensaje(data.message, 'success');
                    } else {
                        mostrarMensaje(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarMensaje('Error al eliminar notificación', 'error');
                });
    }

    function mostrarMensaje(mensaje, tipo) {
        const alertClass = tipo === 'success' ? 'alert-success' : 'alert-danger';
        const icono = tipo === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';

        const alert = document.createElement('div');
        alert.className = `alert ${alertClass} alert-dismissible fade show`;
        alert.innerHTML = `
        <i class="fas ${icono}"></i> ${mensaje}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

        const container = document.querySelector('.container-fluid');
        container.insertBefore(alert, container.firstChild);

        // Auto-dismiss después de 5 segundos
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

// Filtros en tiempo real
    document.getElementById('filtroTipo').addEventListener('change', filtrarNotificaciones);
    document.getElementById('filtroEstado').addEventListener('change', filtrarNotificaciones);
<?php if ($esAdmin): ?>
        document.getElementById('filtroUsuario').addEventListener('input', filtrarNotificaciones);
<?php endif; ?>

    function filtrarNotificaciones() {
        const tipo = document.getElementById('filtroTipo').value;
        const estado = document.getElementById('filtroEstado').value;
        const usuario = document.getElementById('filtroUsuario')?.value.toLowerCase() || '';

        document.querySelectorAll('.notification-item').forEach(item => {
            let mostrar = true;

            // Filtro por tipo
            if (tipo && !item.querySelector('.fas').className.includes(getIconClass(tipo))) {
                mostrar = false;
            }

            // Filtro por estado
            if (estado !== '') {
                const esNoLeida = item.classList.contains('notification-unread');
                if ((estado === '1' && esNoLeida) || (estado === '0' && !esNoLeida)) {
                    mostrar = false;
                }
            }

            // Filtro por usuario (solo admin)
            if (usuario && !item.textContent.toLowerCase().includes(usuario)) {
                mostrar = false;
            }

            item.style.display = mostrar ? 'block' : 'none';
        });
    }

    function getIconClass(tipo) {
        const iconos = {
            'cita_agendada': 'fa-calendar-plus',
            'cita_recordatorio': 'fa-clock',
            'cita_cancelada': 'fa-calendar-times',
            'receta_disponible': 'fa-prescription',
            'resultado_laboratorio': 'fa-flask',
            'sistema': 'fa-cog'
        };
        return iconos[tipo] || 'fa-bell';
    }


    // Funciones para el modal de nueva notificación
<?php if ($esAdmin): ?>
        let usuariosDisponibles = [];

        // Cargar usuarios cuando se abre el modal
        document.getElementById('modalNuevaNotificacion').addEventListener('shown.bs.modal', function () {
            cargarUsuariosDisponibles();
        });

        function cargarUsuariosDisponibles() {
            console.log('=== INICIANDO CARGA DE USUARIOS ===');
            console.log('URL actual:', window.location.href);

            const listaContainer = document.getElementById('listaDestinatarios');
            listaContainer.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Cargando usuarios...</div>';

            fetch('index.php?action=perfil/notificaciones', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'ajax_action=obtener_usuarios'
            })
                    .then(response => {
                        console.log('=== RESPUESTA RECIBIDA ===');
                        console.log('Status:', response.status);
                        console.log('StatusText:', response.statusText);
                        console.log('Headers:', [...response.headers.entries()]);

                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }

                        return response.text();
                    })
                    .then(text => {
                        console.log('=== TEXTO RAW RECIBIDO ===');
                        console.log('Length:', text.length);
                        console.log('Content:', text);

                        // Limpiar posible BOM o espacios
                        const cleanText = text.trim();

                        if (!cleanText) {
                            throw new Error('Respuesta vacía del servidor');
                        }

                        try {
                            const data = JSON.parse(cleanText);
                            console.log('=== JSON PARSEADO ===');
                            console.log('Success:', data.success);
                            console.log('Message:', data.message);
                            console.log('Data:', data.data);

                            if (data.success) {
                                if (data.data && data.data.length > 0) {
                                    usuariosDisponibles = data.data;
                                    mostrarListaUsuarios(usuariosDisponibles);
                                    console.log('✅ Usuarios cargados exitosamente:', data.data.length);
                                } else {
                                    listaContainer.innerHTML = '<div class="text-warning text-center py-3"><i class="fas fa-exclamation-triangle me-1"></i>No hay usuarios disponibles</div>';
                                }
                            } else {
                                console.error('❌ Error en respuesta:', data.message);
                                listaContainer.innerHTML = `<div class="text-danger text-center py-3"><i class="fas fa-exclamation-triangle me-1"></i>Error: ${data.message}</div>`;
                            }
                        } catch (parseError) {
                            console.error('❌ Error parsing JSON:', parseError);
                            console.error('Texto que falló:', cleanText.substring(0, 500));
                            listaContainer.innerHTML = '<div class="text-danger text-center py-3"><i class="fas fa-exclamation-triangle me-1"></i>Error: Respuesta inválida del servidor</div>';
                        }
                    })
                    .catch(error => {
                        console.error('❌ Error en fetch:', error);
                        listaContainer.innerHTML = `<div class="text-danger text-center py-3"><i class="fas fa-exclamation-triangle me-1"></i>Error: ${error.message}</div>`;
                    });
        }

        function mostrarListaUsuarios(usuarios) {
            let html = '';
            let rolActual = '';

            usuarios.forEach(usuario => {
                if (rolActual !== usuario.nombre_rol) {
                    if (rolActual !== '')
                        html += '</div><hr class="my-2">';
                    rolActual = usuario.nombre_rol;
                    html += `<div class="mb-2"><strong class="text-primary">${rolActual}s:</strong></div><div class="ps-3">`;
                }

                html += `
                            <div class="form-check mb-1">
                                <input class="form-check-input destinatario-check" type="checkbox" 
                                       value="${usuario.id_usuario}" id="user_${usuario.id_usuario}"
                                       data-rol="${usuario.nombre_rol}">
                                <label class="form-check-label" for="user_${usuario.id_usuario}">
                                    ${usuario.nombre_completo}
                                </label>
                            </div>
                        `;
            });

            if (rolActual !== '')
                html += '</div>';

            document.getElementById('listaDestinatarios').innerHTML = html;
        }

        function seleccionarTodos() {
            document.querySelectorAll('.destinatario-check').forEach(checkbox => {
                checkbox.checked = true;
            });
        }

        function seleccionarPorRol(rol) {
            document.querySelectorAll('.destinatario-check').forEach(checkbox => {
                checkbox.checked = checkbox.dataset.rol === rol;
            });
        }

        function limpiarSeleccion() {
            document.querySelectorAll('.destinatario-check').forEach(checkbox => {
                checkbox.checked = false;
            });
        }

        // Manejar envío del formulario
        document.getElementById('formNuevaNotificacion').addEventListener('submit', function (e) {
            e.preventDefault();

            const titulo = document.getElementById('tituloNotificacion').value.trim();
            const mensaje = document.getElementById('mensajeNotificacion').value.trim();
            const enviarEmail = document.getElementById('enviarEmail').checked;

            // Obtener destinatarios seleccionados
            const destinatarios = [];
            document.querySelectorAll('.destinatario-check:checked').forEach(checkbox => {
                destinatarios.push(checkbox.value);
            });

            // Validaciones
            if (!titulo || !mensaje) {
                mostrarMensaje('Por favor complete todos los campos obligatorios', 'error');
                return;
            }

            if (destinatarios.length === 0) {
                mostrarMensaje('Debe seleccionar al menos un destinatario', 'error');
                return;
            }

            // Enviar notificación
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Enviando...';
            submitBtn.disabled = true;

            const formData = new URLSearchParams();
            formData.append('ajax_action', 'crear_notificacion_admin');
            formData.append('titulo', titulo);
            formData.append('mensaje', mensaje);
            formData.append('enviar_email', enviarEmail ? '1' : '0');
            destinatarios.forEach(id => formData.append('destinatarios[]', id));

            fetch('index.php?action=perfil/notificaciones', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            mostrarMensaje(data.message, 'success');

                            // Cerrar modal y limpiar formulario
                            const modal = bootstrap.Modal.getInstance(document.getElementById('modalNuevaNotificacion'));
                            modal.hide();
                            this.reset();
                            limpiarSeleccion();

                            // Recargar la página para mostrar las nuevas notificaciones
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        } else {
                            mostrarMensaje(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        mostrarMensaje('Error al enviar la notificación', 'error');
                    })
                    .finally(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    });
        });
<?php endif; ?>
</script>

