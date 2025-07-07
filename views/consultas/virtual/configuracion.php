<?php
// views/consultas/virtual/configuracion.php

// Verificar autenticación y permisos
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [3, 4])) {
    header('Location: index.php?action=dashboard');
    exit;
}

include 'views/includes/header.php';
include 'views/includes/navbar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="text-primary">
                        <i class="fas fa-cog"></i> Configuración de Telemedicina
                    </h2>
                    <p class="text-muted mb-0">Verifique su equipo y conexión para consultas virtuales</p>
                </div>
                <div>
                    <a href="index.php?action=consultas/virtual" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>

            <!-- Test de Conectividad -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-wifi"></i> Test de Conectividad
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4 mb-3">
                                    <div id="test-internet" class="test-item">
                                        <i class="fas fa-globe fa-3x text-muted mb-3"></i>
                                        <h6>Conexión a Internet</h6>
                                        <p class="text-muted mb-3">Verificando...</p>
                                        <div class="spinner-border spinner-border-sm" role="status"></div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div id="test-camera" class="test-item">
                                        <i class="fas fa-video fa-3x text-muted mb-3"></i>
                                        <h6>Cámara</h6>
                                        <p class="text-muted mb-3">No probado</p>
                                        <button class="btn btn-sm btn-outline-primary" onclick="probarCamera()">
                                            Probar
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div id="test-microfono" class="test-item">
                                        <i class="fas fa-microphone fa-3x text-muted mb-3"></i>
                                        <h6>Micrófono</h6>
                                        <p class="text-muted mb-3">No probado</p>
                                        <button class="btn btn-sm btn-outline-primary" onclick="probarMicrofono()">
                                            Probar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vista Previa de Cámara -->
                    <div class="card border-0 shadow-sm" id="camera-preview" style="display: none;">
                        <div class="card-header bg-info text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-eye"></i> Vista Previa de Cámara
                            </h6>
                        </div>
                        <div class="card-body text-center">
                            <video id="preview-video" autoplay muted 
                                   style="max-width: 100%; height: 300px; background: #000; border-radius: 8px;">
                            </video>
                            <div class="mt-3">
                                <button class="btn btn-danger" onclick="detenerCamera()">
                                    <i class="fas fa-stop"></i> Detener
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel de Información -->
                <div class="col-lg-4">
                    <!-- Requisitos del Sistema -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-check-circle"></i> Requisitos del Sistema
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="requirements-list">
                                <div class="requirement-item mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-wifi text-success me-2"></i>
                                        <div>
                                            <strong>Internet</strong>
                                            <br><small class="text-muted">Mínimo 1 Mbps</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="requirement-item mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-video text-success me-2"></i>
                                        <div>
                                            <strong>Cámara Web</strong>
                                            <br><small class="text-muted">HD recomendado</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="requirement-item mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-microphone text-success me-2"></i>
                                        <div>
                                            <strong>Micrófono</strong>
                                            <br><small class="text-muted">Con cancelación de ruido</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="requirement-item mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-volume-up text-success me-2"></i>
                                        <div>
                                            <strong>Altavoces/Auriculares</strong>
                                            <br><small class="text-muted">Auriculares recomendados</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navegadores Compatibles -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-info text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-browser"></i> Navegadores Compatibles
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="browser-list">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fab fa-chrome text-warning me-2"></i>
                                    <span>Google Chrome 70+</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fab fa-firefox text-orange me-2"></i>
                                    <span>Firefox 65+</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fab fa-safari text-info me-2"></i>
                                    <span>Safari 12+</span>
                                </div>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fab fa-edge text-primary me-2"></i>
                                    <span>Edge 79+</span>
                                </div>
                            </div>
                            <div class="alert alert-warning alert-sm mt-3">
                                <small>
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    Actualice su navegador para mejor experiencia
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.test-item {
    padding: 1rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.test-item.success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
}

.test-item.success i {
    color: #28a745 !important;
}

.test-item.error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
}

.test-item.error i {
    color: #dc3545 !important;
}

.alert-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

.requirement-item {
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #dee2e6;
}

.requirement-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
</style>

<script>
let stream = null;

// Test automático de internet al cargar
document.addEventListener('DOMContentLoaded', function() {
    probarInternet();
});

function probarInternet() {
    const testItem = document.getElementById('test-internet');
    
    fetch('https://httpbin.org/get', { mode: 'cors' })
        .then(response => {
            if (response.ok) {
                testItem.classList.add('success');
                testItem.querySelector('i').className = 'fas fa-globe fa-3x text-success mb-3';
                testItem.querySelector('p').textContent = 'Conexión estable';
                testItem.querySelector('.spinner-border').style.display = 'none';
            }
        })
        .catch(error => {
            testItem.classList.add('error');
            testItem.querySelector('i').className = 'fas fa-globe fa-3x text-danger mb-3';
            testItem.querySelector('p').textContent = 'Sin conexión';
            testItem.querySelector('.spinner-border').style.display = 'none';
        });
}

async function probarCamera() {
    const testItem = document.getElementById('test-camera');
    const previewContainer = document.getElementById('camera-preview');
    const video = document.getElementById('preview-video');
    
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        video.srcObject = stream;
        
        testItem.classList.add('success');
        testItem.querySelector('i').className = 'fas fa-video fa-3x text-success mb-3';
        testItem.querySelector('p').textContent = 'Funcionando';
        testItem.querySelector('button').textContent = 'Funcionando ✓';
       testItem.querySelector('button').className = 'btn btn-sm btn-success';
       testItem.querySelector('button').disabled = true;
       
       previewContainer.style.display = 'block';
       
   } catch (error) {
       testItem.classList.add('error');
       testItem.querySelector('i').className = 'fas fa-video fa-3x text-danger mb-3';
       testItem.querySelector('p').textContent = 'No disponible';
       testItem.querySelector('button').textContent = 'Error';
       testItem.querySelector('button').className = 'btn btn-sm btn-danger';
       
       console.error('Error accediendo a la cámara:', error);
       Swal.fire('Error', 'No se pudo acceder a la cámara. Verifique los permisos.', 'error');
   }
}

async function probarMicrofono() {
   const testItem = document.getElementById('test-microfono');
   
   try {
       const audioStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
       
       testItem.classList.add('success');
       testItem.querySelector('i').className = 'fas fa-microphone fa-3x text-success mb-3';
       testItem.querySelector('p').textContent = 'Funcionando';
       testItem.querySelector('button').textContent = 'Funcionando ✓';
       testItem.querySelector('button').className = 'btn btn-sm btn-success';
       testItem.querySelector('button').disabled = true;
       
       // Detener el stream de audio inmediatamente
       audioStream.getTracks().forEach(track => track.stop());
       
   } catch (error) {
       testItem.classList.add('error');
       testItem.querySelector('i').className = 'fas fa-microphone fa-3x text-danger mb-3';
       testItem.querySelector('p').textContent = 'No disponible';
       testItem.querySelector('button').textContent = 'Error';
       testItem.querySelector('button').className = 'btn btn-sm btn-danger';
       
       console.error('Error accediendo al micrófono:', error);
       Swal.fire('Error', 'No se pudo acceder al micrófono. Verifique los permisos.', 'error');
   }
}

function detenerCamera() {
   if (stream) {
       stream.getTracks().forEach(track => track.stop());
       stream = null;
   }
   
   document.getElementById('camera-preview').style.display = 'none';
   document.getElementById('preview-video').srcObject = null;
   
   // Resetear el botón de cámara
   const testItem = document.getElementById('test-camera');
   testItem.querySelector('button').textContent = 'Probar nuevamente';
   testItem.querySelector('button').className = 'btn btn-sm btn-outline-primary';
   testItem.querySelector('button').disabled = false;
}
</script>