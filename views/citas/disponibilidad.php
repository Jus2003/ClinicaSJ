<?php
// NO session_start() aquí porque ya está iniciado en index.php
// Verificar autenticación (ya se maneja en index.php pero por seguridad)
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?action=login');
    exit;
}

// Verificar permisos (admin y recepcionistas pueden gestionar todos los horarios, médicos solo los suyos)
if ($_SESSION['role_id'] == 3) {
    // Médicos solo pueden ver su propio horario
    $medicoSeleccionado = $_SESSION['user_id'];
} elseif (!in_array($_SESSION['role_id'], [1, 2])) {
    // Si no es admin (1), recepcionista (2) o médico (3), redirigir
    header('Location: index.php');
    exit;
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Construir la consulta SQL según el rol
if ($_SESSION['role_id'] == 3) {
    // Médico solo ve su propia información
    $sqlMedicos = "SELECT u.id_usuario, u.nombre, u.apellido, u.id_sucursal, s.nombre_sucursal,
                          GROUP_CONCAT(e.nombre_especialidad SEPARATOR ', ') as especialidades
                   FROM usuarios u 
                   INNER JOIN medico_especialidades me ON u.id_usuario = me.id_medico
                   INNER JOIN especialidades e ON me.id_especialidad = e.id_especialidad
                   LEFT JOIN sucursales s ON u.id_sucursal = s.id_sucursal
                   WHERE u.id_usuario = :id_usuario AND u.id_rol = 3 AND u.activo = 1 AND me.activo = 1
                   GROUP BY u.id_usuario";

    $stmtMedicos = $db->prepare($sqlMedicos);
    $stmtMedicos->bindParam(':id_usuario', $_SESSION['user_id']);
    $stmtMedicos->execute();
    $medicos = $stmtMedicos->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Admin y recepcionistas ven todos los médicos
    $sqlMedicos = "SELECT u.id_usuario, u.nombre, u.apellido, u.id_sucursal, s.nombre_sucursal,
                          GROUP_CONCAT(e.nombre_especialidad SEPARATOR ', ') as especialidades
                   FROM usuarios u 
                   INNER JOIN medico_especialidades me ON u.id_usuario = me.id_medico
                   INNER JOIN especialidades e ON me.id_especialidad = e.id_especialidad
                   LEFT JOIN sucursales s ON u.id_sucursal = s.id_sucursal
                   WHERE u.id_rol = 3 AND u.activo = 1 AND me.activo = 1
                   GROUP BY u.id_usuario
                   ORDER BY u.nombre, u.apellido";

    $stmtMedicos = $db->prepare($sqlMedicos);
    $stmtMedicos->execute();
    $medicos = $stmtMedicos->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener sucursales activas (para el modal)
$sqlSucursales = "SELECT * FROM sucursales WHERE activo = 1 ORDER BY nombre_sucursal";
$stmtSucursales = $db->prepare($sqlSucursales);
$stmtSucursales->execute();
$sucursales = $stmtSucursales->fetchAll(PDO::FETCH_ASSOC);

// Para el menú de navegación - cargar usando el helper existente
include 'views/includes/menu-helper.php';

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-clock text-primary"></i> 
                    Disponibilidad de Médicos
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button class="btn btn-success btn-horario" id="btnNuevoHorario" <?php echo ($_SESSION['role_id'] == 3) ? '' : 'disabled'; ?>>
                        <i class="fas fa-plus"></i> Nuevo Horario
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <label class="form-label">Seleccionar Médico</label>
                            <select class="form-select" id="selectMedico" <?php echo ($_SESSION['role_id'] == 3) ? 'disabled' : ''; ?>>
                                <?php if ($_SESSION['role_id'] == 3): ?>
                                    <!-- Si es médico, mostrar solo su opción -->
                                    <?php foreach ($medicos as $medico): ?>
                                        <option value="<?php echo $medico['id_usuario']; ?>" 
                                                data-sucursal="<?php echo $medico['id_sucursal']; ?>"
                                                data-sucursal-nombre="<?php echo htmlspecialchars($medico['nombre_sucursal']); ?>"
                                                selected>
                                            Dr. <?php echo htmlspecialchars($medico['nombre'] . ' ' . $medico['apellido']); ?>
                                            (<?php echo htmlspecialchars($medico['especialidades']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Si es admin o recepcionista, mostrar todos los médicos -->
                                    <option value="">Seleccione un médico...</option>
                                    <?php foreach ($medicos as $medico): ?>
                                        <option value="<?php echo $medico['id_usuario']; ?>" 
                                                data-sucursal="<?php echo $medico['id_sucursal']; ?>"
                                                data-sucursal-nombre="<?php echo htmlspecialchars($medico['nombre_sucursal']); ?>">
                                            Dr. <?php echo htmlspecialchars($medico['nombre'] . ' ' . $medico['apellido']); ?>
                                            (<?php echo htmlspecialchars($medico['especialidades']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Sucursal Asignada</label>
                            <input type="text" class="form-control" id="sucursalMedico" readonly placeholder="Seleccione un médico">
                            <input type="hidden" id="sucursalIdMedico">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información del Médico -->
            <div id="medicoInfo" class="d-none">
                <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4 id="medicoNombre" class="mb-1"></h4>
                                <p class="mb-0" id="medicoEspecialidades"></p>
                            </div>
                            <div class="col-md-4 text-end">
                                <i class="fas fa-user-md fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Horarios por día -->
            <div id="horariosContainer" class="d-none">
                <div class="row">
                    <?php
                    $dias = [
                        1 => 'Lunes',
                        2 => 'Martes',
                        3 => 'Miércoles',
                        4 => 'Jueves',
                        5 => 'Viernes',
                        6 => 'Sábado',
                        7 => 'Domingo'
                    ];

                    foreach ($dias as $numero => $nombre):
                        ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card border-0 shadow-sm h-100 horario-card">
                                <div class="card-body">
                                    <div class="text-center mb-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; padding: 12px;">
                                        <i class="fas fa-calendar-day me-2"></i>
                                        <strong><?php echo $nombre; ?></strong>
                                    </div>

                                    <div id="horarios-dia-<?php echo $numero; ?>">
                                        <!-- Los horarios se cargan dinámicamente aquí -->
                                    </div>

                                    <?php if ($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2): ?>
                                        <div class="text-center mt-3">
                                            <button class="btn btn-outline-primary btn-sm btn-agregar-horario" data-dia="<?php echo $numero; ?>">
                                                <i class="fas fa-plus"></i> Agregar Horario
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Estado cuando no hay médico seleccionado -->
            <div id="estadoInicial" class="text-center py-5" <?php echo ($_SESSION['role_id'] == 3) ? 'style="display: none;"' : ''; ?>>
                <i class="fas fa-user-clock fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">Gestión de Horarios Médicos</h4>
                <p class="text-muted">Seleccione un médico para ver y gestionar sus horarios de disponibilidad</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Agregar/Editar Horario -->
<div class="modal fade" id="modalHorario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-clock me-2"></i>
                    <span id="modalTitulo">Nuevo Horario</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formHorario">
                    <input type="hidden" id="horarioId" name="id_horario">
                    <input type="hidden" id="medicoId" name="id_medico">
                    <input type="hidden" id="diaSemana" name="dia_semana">
                    <input type="hidden" id="sucursalId" name="id_sucursal">

                    <div class="mb-3">
                        <label class="form-label">Día de la Semana</label>
                        <input type="text" class="form-control" id="diaNombre" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Sucursal</label>
                        <input type="text" class="form-control" id="sucursalNombre" readonly>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Hora Inicio <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="horaInicio" name="hora_inicio" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Hora Fin <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="horaFin" name="hora_fin" required>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Nota:</strong> Los horarios se dividirán automáticamente según la duración de cada especialidad del médico.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarHorario">
                    <i class="fas fa-save me-2"></i>Guardar Horario
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .horario-card {
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .horario-card:hover {
        border-color: var(--bs-primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .horario-item {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 8px;
        border-left: 4px solid #28a745;
        transition: all 0.2s ease;
    }

    .horario-item:hover {
        background: #e9ecef;
        transform: translateX(5px);
    }

    .sin-horarios {
        background: #fff3cd;
        border: 1px dashed #ffc107;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        color: #856404;
    }

    .btn-horario {
        border-radius: 20px;
        padding: 8px 20px;
        font-weight: 500;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Variables globales
    let medicoSeleccionado = null;
    let sucursalSeleccionada = null;
    let modalHorario = null;

    const dias = {
        1: 'Lunes',
        2: 'Martes',
        3: 'Miércoles',
        4: 'Jueves',
        5: 'Viernes',
        6: 'Sábado',
        7: 'Domingo'
    };

    document.addEventListener('DOMContentLoaded', function () {
        // Inicializar modal
        modalHorario = new bootstrap.Modal(document.getElementById('modalHorario'));

        // Si es médico, cargar automáticamente sus horarios
<?php if ($_SESSION['role_id'] == 3): ?>
            if (document.getElementById('selectMedico').value) {
                handleMedicoChange.call(document.getElementById('selectMedico'));
            }
<?php endif; ?>

        // Event listeners
        document.getElementById('selectMedico').addEventListener('change', handleMedicoChange);
        document.getElementById('btnNuevoHorario').addEventListener('click', mostrarModalNuevo);
        document.getElementById('btnGuardarHorario').addEventListener('click', guardarHorario);

        // Event listener para botones de agregar horario por día
        document.querySelectorAll('.btn-agregar-horario').forEach(btn => {
            btn.addEventListener('click', function () {
                const dia = this.dataset.dia;
                mostrarModalParaDia(dia);
            });
        });
    });

    // Manejar cambio de médico
    function handleMedicoChange() {
        const medicoId = this.value;
        const selectedOption = this.options[this.selectedIndex];

        medicoSeleccionado = medicoId;

        if (medicoId) {
            // Obtener datos de la sucursal del médico
            const sucursalId = selectedOption.getAttribute('data-sucursal');
            const sucursalNombre = selectedOption.getAttribute('data-sucursal-nombre');

            sucursalSeleccionada = sucursalId;

            // Mostrar sucursal del médico
            document.getElementById('sucursalMedico').value = sucursalNombre || 'Sin sucursal asignada';
            document.getElementById('sucursalIdMedico').value = sucursalId;

            // Mostrar información del médico
            mostrarInfoMedico(medicoId);

            // Habilitar controles
            document.querySelectorAll('.btn-agregar-horario').forEach(btn => {
                btn.disabled = false;
            });
            document.getElementById('btnNuevoHorario').disabled = false;

            // Cargar horarios existentes
            cargarHorariosMedico(medicoId);

        } else {
            // Ocultar información y resetear
            ocultarInfoMedico();
            resetearControles();
        }
    }

    // Mostrar información del médico
    function mostrarInfoMedico(medicoId) {
        const select = document.getElementById('selectMedico');
        const option = select.options[select.selectedIndex];
        const texto = option.text;

        // Extraer nombre y especialidades
        const partes = texto.split('(');
        const nombre = partes[0].trim();
        const especialidades = partes[1] ? partes[1].replace(')', '') : '';

        document.getElementById('medicoNombre').textContent = nombre;
        document.getElementById('medicoEspecialidades').textContent = especialidades;

        document.getElementById('medicoInfo').classList.remove('d-none');
        document.getElementById('horariosContainer').classList.remove('d-none');
        document.getElementById('estadoInicial').classList.add('d-none');
    }

    // Ocultar información del médico
    function ocultarInfoMedico() {
        document.getElementById('medicoInfo').classList.add('d-none');
        document.getElementById('horariosContainer').classList.add('d-none');
        document.getElementById('estadoInicial').classList.remove('d-none');
    }

    // Resetear controles
    function resetearControles() {
        document.getElementById('sucursalMedico').value = '';
        document.getElementById('sucursalIdMedico').value = '';
        document.getElementById('btnNuevoHorario').disabled = true;

        document.querySelectorAll('.btn-agregar-horario').forEach(btn => {
            btn.disabled = true;
        });

        // Limpiar horarios mostrados
        for (let i = 1; i <= 7; i++) {
            document.getElementById(`horarios-dia-${i}`).innerHTML = '';
        }

        medicoSeleccionado = null;
        sucursalSeleccionada = null;
    }

    // Cargar horarios del médico
    function cargarHorariosMedico(medicoId) {
        let url = `views/api/obtener-horarios-medico.php?medico_id=${medicoId}`;

        fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarHorarios(data.horarios);
                    } else {
                        console.error('Error al cargar horarios:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
    }

    // Mostrar horarios en la interfaz
    function mostrarHorarios(horarios) {
        // Limpiar horarios existentes
        for (let i = 1; i <= 7; i++) {
            const container = document.getElementById(`horarios-dia-${i}`);
            container.innerHTML = '';
        }

        // Agrupar horarios por día
        const horariosPorDia = {};
        horarios.forEach(horario => {
            if (!horariosPorDia[horario.dia_semana]) {
                horariosPorDia[horario.dia_semana] = [];
            }
            horariosPorDia[horario.dia_semana].push(horario);
        });

        // Mostrar horarios para cada día
        for (let dia = 1; dia <= 7; dia++) {
            const container = document.getElementById(`horarios-dia-${dia}`);

            if (horariosPorDia[dia] && horariosPorDia[dia].length > 0) {
                horariosPorDia[dia].forEach(horario => {
                    const horarioElement = crearElementoHorario(horario);
                    container.appendChild(horarioElement);
                });
            } else {
                container.innerHTML = `
                    <div class="sin-horarios">
                        <i class="fas fa-calendar-times mb-2"></i>
                        <p class="mb-0">Sin horarios configurados</p>
                    </div>
                `;
            }
        }
    }

    // Crear elemento HTML para un horario
    function crearElementoHorario(horario) {
        const div = document.createElement('div');
        div.className = 'horario-item';
        div.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>${horario.hora_inicio} - ${horario.hora_fin}</strong>
                    <br>
                    <small class="text-muted">
                        <i class="fas fa-building me-1"></i>
                        ${horario.nombre_sucursal}
                    </small>
                </div>
<?php if ($_SESSION['role_id'] != 3): // Mostrar solo si NO es médico (rol 3)   ?>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" onclick="editarHorario(${horario.id_horario})" title="Editar">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-outline-danger" onclick="eliminarHorario(${horario.id_horario})" title="Eliminar">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
<?php endif; ?>
            </div>
        `;
        return div;
    }

    // Mostrar modal para nuevo horario
    function mostrarModalNuevo() {
        if (!medicoSeleccionado) {
            Swal.fire('Atención', 'Primero seleccione un médico', 'warning');
            return;
        }

        // Resetear formulario
        document.getElementById('formHorario').reset();
        document.getElementById('horarioId').value = '';
        document.getElementById('medicoId').value = medicoSeleccionado;
        document.getElementById('sucursalId').value = sucursalSeleccionada;
        document.getElementById('modalTitulo').textContent = 'Nuevo Horario';

        // Mostrar nombre de sucursal
        const sucursalNombre = document.getElementById('sucursalMedico').value;
        document.getElementById('sucursalNombre').value = sucursalNombre;

        modalHorario.show();
    }

    // Mostrar modal para día específico
    function mostrarModalParaDia(dia) {
        if (!medicoSeleccionado) {
            Swal.fire('Atención', 'Primero seleccione un médico', 'warning');
            return;
        }

        // Resetear y configurar formulario
        document.getElementById('formHorario').reset();
        document.getElementById('horarioId').value = '';
        document.getElementById('medicoId').value = medicoSeleccionado;
        document.getElementById('sucursalId').value = sucursalSeleccionada;
        document.getElementById('diaSemana').value = dia;
        document.getElementById('diaNombre').value = dias[dia];
        document.getElementById('modalTitulo').textContent = `Nuevo Horario - ${dias[dia]}`;

        // Mostrar nombre de sucursal
        const sucursalNombre = document.getElementById('sucursalMedico').value;
        document.getElementById('sucursalNombre').value = sucursalNombre;

        modalHorario.show();
    }

    // Editar horario
    function editarHorario(horarioId) {
        fetch(`views/api/obtener-horario.php?id=${horarioId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const horario = data.horario;

                        // Validar que el médico solo pueda editar sus propios horarios
<?php if ($_SESSION['role_id'] == 3): ?>
                            if (horario.id_medico != <?php echo $_SESSION['user_id']; ?>) {
                                Swal.fire('Error', 'No tiene permisos para editar este horario', 'error');
                                return;
                            }
<?php endif; ?>

                        document.getElementById('horarioId').value = horario.id_horario;
                        document.getElementById('medicoId').value = horario.id_medico;
                        document.getElementById('sucursalId').value = horario.id_sucursal;
                        document.getElementById('diaSemana').value = horario.dia_semana;
                        document.getElementById('diaNombre').value = dias[horario.dia_semana];
                        document.getElementById('sucursalNombre').value = horario.nombre_sucursal;
                        document.getElementById('horaInicio').value = horario.hora_inicio;
                        document.getElementById('horaFin').value = horario.hora_fin;
                        document.getElementById('modalTitulo').textContent = `Editar Horario - ${dias[horario.dia_semana]}`;

                        modalHorario.show();
                    } else {
                        Swal.fire('Error', 'No se pudo cargar el horario', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Error al cargar el horario', 'error');
                });
    }

    // Eliminar horario
    function eliminarHorario(horarioId) {
        Swal.fire({
            title: '¿Está seguro?',
            text: 'Esta acción eliminará el horario permanentemente',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('views/api/eliminar-horario.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id_horario: horarioId,
                        id_medico: <?php echo ($_SESSION['role_id'] == 3) ? $_SESSION['user_id'] : 'null'; ?>
                    })
                })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Eliminado', 'El horario ha sido eliminado', 'success');
                                cargarHorariosMedico(medicoSeleccionado);
                            } else {
                                Swal.fire('Error', data.error || 'No se pudo eliminar el horario', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire('Error', 'Error al eliminar el horario', 'error');
                        });
            }
        });
    }

    // Guardar horario
    function guardarHorario() {
        const formData = new FormData(document.getElementById('formHorario'));
        const horarioId = document.getElementById('horarioId').value;

        // Validar que el médico solo pueda editar sus propios horarios
<?php if ($_SESSION['role_id'] == 3): ?>
            const medicoIdForm = formData.get('id_medico');
            if (medicoIdForm != <?php echo $_SESSION['user_id']; ?>) {
                Swal.fire('Error', 'No tiene permisos para editar este horario', 'error');
                return;
            }
<?php endif; ?>

        const url = horarioId ? 'views/api/actualizar-horario.php' : 'views/api/crear-horario.php';

        fetch(url, {
            method: 'POST',
            body: formData
        })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Éxito', 'Horario guardado correctamente', 'success');
                        modalHorario.hide();
                        cargarHorariosMedico(medicoSeleccionado);
                    } else {
                        Swal.fire('Error', data.error || 'No se pudo guardar el horario', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Error al guardar el horario', 'error');
                });
    }
</script>

</body>
</html>