<?php
function consultarCedulaAPI($cedula) {
    // Validar que la cédula tenga 10 dígitos
    if (!preg_match('/^\d{10}$/', $cedula)) {
        return ['error' => 'La cédula debe tener exactamente 10 dígitos'];
    }
    
    // Validar algoritmo de cédula ecuatoriana
    if (!validarCedulaEcuatoriana($cedula)) {
        return ['error' => 'Número de cédula inválido'];
    }
    
    try {
        $url = "https://sifae.agrocalidad.gob.ec/SIFAEBack/index.php?ruta=datos_demograficos/{$cedula}";
        
        // Configurar contexto para la petición
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'method' => 'GET',
                'header' => [
                    'Accept: application/json, text/plain, */*',
                    'Accept-Language: es-ES,es;q=0.9'
                ]
            ]
        ]);
        
        // Realizar petición
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            return ['error' => 'No se pudo conectar con el servicio de consulta'];
        }
        
        // Decodificar respuesta JSON
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Respuesta inválida del servicio: ' . json_last_error_msg()];
        }
        
        // Verificar estructura de respuesta de Agrocalidad
        if (empty($data) || $data['estado'] !== 'OK' || empty($data['resultado']) || !is_array($data['resultado'])) {
            return ['error' => 'No se encontraron datos para esta cédula'];
        }
        
        // Obtener el primer resultado
        $persona = $data['resultado'][0];
        
        // Extraer el nombre completo
        $nombreCompleto = trim($persona['nombre'] ?? '');
        
        if (empty($nombreCompleto)) {
            return ['error' => 'No se encontró información de nombres para esta cédula'];
        }
        
        // Separar nombres y apellidos
        // El formato parece ser: APELLIDOS NOMBRES
        // Ejemplo: "SANCHEZ LUJE JUSTIN SEBASTIAN"
        $partesNombre = explode(' ', $nombreCompleto);
        
        if (count($partesNombre) >= 3) {
            // Asumir que los primeros 2 son apellidos y el resto nombres
            $apellidos = $partesNombre[0] . ' ' . $partesNombre[1];
            $nombres = implode(' ', array_slice($partesNombre, 2));
        } elseif (count($partesNombre) == 2) {
            // Solo un apellido y un nombre
            $apellidos = $partesNombre[0];
            $nombres = $partesNombre[1];
        } else {
            // Solo una palabra, ponerla como nombre
            $apellidos = '';
            $nombres = $nombreCompleto;
        }
        
        // Limpiar y formatear
        $nombres = ucwords(strtolower($nombres));
        $apellidos = ucwords(strtolower($apellidos));
        
        // Retornar datos procesados
        return [
            'success' => true,
            'nombres' => trim($nombres),
            'apellidos' => trim($apellidos),
            'cedula' => $cedula,
            'fecha_nacimiento' => $persona['fechaNacimiento'] ?? null,
            'lugar_nacimiento' => $persona['lugarNacimiento'] ?? null,
            'nombre_completo_original' => $nombreCompleto
        ];
        
    } catch (Exception $e) {
        return ['error' => 'Error al consultar los datos: ' . $e->getMessage()];
    }
}

function validarCedulaEcuatoriana($cedula) {
    if (strlen($cedula) != 10) return false;
    
    $digitos = str_split($cedula);
    $suma = 0;
    
    // Validar los primeros 9 dígitos
    for ($i = 0; $i < 9; $i++) {
        $digito = intval($digitos[$i]);
        
        if ($i % 2 == 0) {
            // Posiciones pares (0, 2, 4, 6, 8)
            $digito *= 2;
            if ($digito > 9) $digito -= 9;
        }
        
        $suma += $digito;
    }
    
    $digitoVerificador = intval($digitos[9]);
    $residuo = $suma % 10;
    $resultado = $residuo == 0 ? 0 : 10 - $residuo;
    
    return $digitoVerificador == $resultado;
}
?>