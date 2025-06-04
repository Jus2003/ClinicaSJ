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
                'timeout' => 10,
                'user_agent' => 'ClinicaSJ/1.0'
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
            return ['error' => 'Respuesta inválida del servicio'];
        }
        
        // Verificar si se encontraron datos
        if (empty($data) || !isset($data['nombres'])) {
            return ['error' => 'No se encontraron datos para esta cédula'];
        }
        
        // Retornar datos procesados
        return [
            'success' => true,
            'nombres' => trim($data['nombres'] ?? ''),
            'apellidos' => trim($data['apellidos'] ?? ''),
            'cedula' => $cedula
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