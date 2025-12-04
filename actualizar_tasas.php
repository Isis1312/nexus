<?php
function obtenerTasasBCV() {
    $tasas = [
        'dolar' => 'No disponible',
        'euro' => 'No disponible',
        'fecha' => date('d/m/Y'),
        'actualizado' => false
    ];
    
    try {
        // Headers más realistas
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n" .
                           "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\n" .
                           "Accept-Language: es-ES,es;q=0.9,en;q=0.8\r\n" .
                           "Connection: keep-alive\r\n" .
                           "Upgrade-Insecure-Requests: 1\r\n",
                'timeout' => 15,
                'follow_location' => true,
                'max_redirects' => 3
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        
        $context = stream_context_create($options);
        
        // Intentar con diferentes URLs del BCV
        $urls = [
            'https://www.bcv.org.ve/',
            'http://www.bcv.org.ve/',
            'https://bcv.org.ve/'
        ];
        
        $html = false;
        foreach ($urls as $url) {
            $html = @file_get_contents($url, false, $context);
            if ($html !== false) break;
        }
        
        if ($html !== false) {
            // Buscar patrones alternativos del BCV
            $patrones_dolar = [
                '/USD<\/span>.*?<strong>([0-9,\.]+)<\/strong>/s',
                '/Dólar.*?([0-9,\.]+).*?Bs/si',
                '/USD.*?([0-9,\.]+).*?Bs/si'
            ];
            
            $patrones_euro = [
                '/EUR<\/span>.*?<strong>([0-9,\.]+)<\/strong>/s',
                '/Euro.*?([0-9,\.]+).*?Bs/si',
                '/EUR.*?([0-9,\.]+).*?Bs/si'
            ];
            
            // Buscar dólar
            foreach ($patrones_dolar as $patron) {
                if (preg_match($patron, $html, $matches)) {
                    $valor = str_replace(['.', ','], ['', '.'], $matches[1]);
                    $tasas['dolar'] = number_format(floatval($valor), 2, ',', '.');
                    $tasas['actualizado'] = true;
                    break;
                }
            }
            
            // Buscar euro
            foreach ($patrones_euro as $patron) {
                if (preg_match($patron, $html, $matches)) {
                    $valor = str_replace(['.', ','], ['', '.'], $matches[1]);
                    $tasas['euro'] = number_format(floatval($valor), 2, ',', '.');
                    $tasas['actualizado'] = true;
                    break;
                }
            }
            
            // Si no se encontraron tasas, mostrar el HTML para debugging
            if (!$tasas['actualizado']) {
                error_log("No se pudieron extraer tasas del HTML del BCV");
                // Guardar una copia del HTML para análisis
                file_put_contents('bcv_debug.html', $html);
            }
            
        } else {
            error_log("No se pudo conectar al BCV");
        }
        
    } catch (Exception $e) {
        error_log("Error obteniendo tasas BCV: " . $e->getMessage());
    }
    
    return $tasas;
}
?>