<?php
session_start();
// Verificar si el usuario está logueado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

// Obtener datos del usuario desde la sesión
$nombre_usuario = $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Usuario';
$rol_usuario = $_SESSION['rol'] ?? 'Usuario';

// Función para obtener tasas del BCV
function obtenerTasasBCV() {
    $cache_file = 'tasas_cache.json';
    $cache_time = 3600; // 1 hora en segundos
    
    // Verificar si existe cache válido
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        if ($cached_data) {
            return $cached_data;
        }
    }
    
    // Si no hay cache válido, obtener tasas actuales
    return actualizarTasasDesdeAPI();
}

// Función para actualizar tasas desde API
function actualizarTasasDesdeAPI() {
    $urls = [
        'https://api.exchangerate-api.com/v4/latest/USD',
        'https://open.er-api.com/v6/latest/USD'
    ];
    
    foreach ($urls as $url) {
        $tasas = obtenerTasasDesdeURL($url);
        if ($tasas !== false) {
            guardarTasasEnCache($tasas);
            return $tasas;
        }
    }
    
    // Si todas las APIs fallan, usar el último cache disponible
    return obtenerUltimoCache();
}

// Función para obtener tasas desde una URL específica
function obtenerTasasDesdeURL($url) {
    try {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ],
            'http' => [
                'timeout' => 10,
                'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return false;
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['rates']['VES'])) {
            $dolar_actual = number_format($data['rates']['VES'], 2, '.', '');
            $euro_actual = isset($data['rates']['EUR']) ? 
                number_format($data['rates']['VES'] / $data['rates']['EUR'], 2, '.', '') : 
                number_format($data['rates']['VES'] * 0.92, 2, '.', '');
            
            return procesarTasasConPorcentaje($dolar_actual, $euro_actual, 'BCV');
        }
        
    } catch (Exception $e) {
        return false;
    }
    
    return false;
}

// Función para obtener último cache disponible
function obtenerUltimoCache() {
    $cache_file = 'tasas_cache.json';
    
    if (file_exists($cache_file)) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        if ($cached_data) {
            return $cached_data;
        }
    }
    
    // Si no hay cache, usar valores por defecto
    return [
        'dolar' => '0.00',
        'euro' => '0.00'
        
    ];
}

// Función para guardar tasas en cache
function guardarTasasEnCache($tasas) {
    $cache_file = 'js/tasas_cache.json';
    file_put_contents($cache_file, json_encode($tasas, JSON_PRETTY_PRINT));
}

// Función para calcular porcentajes y procesar tasas
function procesarTasasConPorcentaje($dolar_actual, $euro_actual, $fuente) {
    // Obtener tasas anteriores del historial
    $tasas_anteriores = obtenerTasasAnteriores();
    $dolar_anterior = $tasas_anteriores['dolar'] ?? $dolar_actual;
    $euro_anterior = $tasas_anteriores['euro'] ?? $euro_actual;
    
    // Calcular porcentajes de cambio
    $porcentaje_dolar = calcularPorcentajeCambio($dolar_anterior, $dolar_actual);
    $porcentaje_euro = calcularPorcentajeCambio($euro_anterior, $euro_actual);
    
    // Guardar tasas actuales para el próximo cálculo
    guardarTasasActuales($dolar_actual, $euro_actual);
    
    return [
        'dolar' => $dolar_actual,
        'euro' => $euro_actual,
        'dolar_anterior' => $dolar_anterior,
        'euro_anterior' => $euro_anterior,
        'porcentaje_dolar' => $porcentaje_dolar,
        'porcentaje_euro' => $porcentaje_euro,
        'fecha_actualizacion' => date('Y-m-d H:i:s'),
        'fuente' => $fuente,
        'tendencia_dolar' => $porcentaje_dolar >= 0 ? 'sube' : 'baja',
        'tendencia_euro' => $porcentaje_euro >= 0 ? 'sube' : 'baja'
    ];
}

// Función para calcular porcentaje de cambio
function calcularPorcentajeCambio($valor_anterior, $valor_actual) {
    $anterior = floatval($valor_anterior);
    $actual = floatval($valor_actual);
    
    if ($anterior == 0 || $anterior == $actual) {
        return 0;
    }
    
    $cambio = (($actual - $anterior) / $anterior) * 100;
    return round($cambio, 2);
}

// Función para obtener tasas anteriores del historial
function obtenerTasasAnteriores() {
    $historial_file = 'js//historial_tasas.json';
    
    if (file_exists($historial_file)) {
        $historial = json_decode(file_get_contents($historial_file), true);
        if (!empty($historial) && is_array($historial)) {
            return end($historial);
        }
    }
    
    return [
        'dolar' => '0.00',
        'euro' => '0.00'
    ];
}

// Función para guardar tasas actuales en el historial
function guardarTasasActuales($dolar, $euro) {
    $historial_file = 'js/historial_tasas.json';
    $nueva_entrada = [
        'dolar' => $dolar,
        'euro' => $euro,
        'fecha' => date('Y-m-d H:i:s')
    ];
    
    $historial = [];
    if (file_exists($historial_file)) {
        $historial_data = file_get_contents($historial_file);
        $historial = json_decode($historial_data, true) ?? [];
    }
    
    // Mantener solo las últimas 48 horas de historial
    $historial = array_filter($historial, function($entrada) {
        return strtotime($entrada['fecha']) > strtotime('-48 hours');
    });
    
    // Agregar nueva entrada
    $historial[] = $nueva_entrada;
    
    // Guardar historial
    file_put_contents($historial_file, json_encode($historial, JSON_PRETTY_PRINT));
}

// Función para forzar actualización
function actualizarTasasForzado() {
    $cache_file = 'tasas_cache.json';
    if (file_exists($cache_file)) {
        unlink($cache_file);
    }
    return actualizarTasasDesdeAPI();
}

// Manejar actualización vía AJAX
if (isset($_POST['accion']) && $_POST['accion'] === 'actualizar_tasas') {
    $nuevas_tasas = actualizarTasasForzado();
    header('Content-Type: application/json');
    echo json_encode($nuevas_tasas);
    exit();
}

// Obtener tasas actuales
$tasas_bcv = obtenerTasasBCV();

$current_page = basename($_SERVER['PHP_SELF']);

require_once 'menu.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/inicio.css">
    <title>Inicio Nexus</title>
    <style>
        .trend-indicator.positive {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }
        
        .trend-indicator.negative {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }
        
        .previous-rate {
            font-size: 0.8em;
            opacity: 0.7;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        
        .update-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: Arial, sans-serif;
            transition: all 0.3s ease;
        }
        
        .update-notification.error {
            background: #f44336;
        }
    </style>
</head>
<body>

    <main class="main-content">
        <div class="welcome-container">
            <h1>BIENVENIDO A NEXUS</h1>
            
            <div class="user-welcome">
                <p class="welcome-message">
                    Bienvenido de nuevo, <strong><?php echo htmlspecialchars($nombre_usuario); ?></strong>. 
                    Tu rol actual es: <span class="user-role"><?php echo htmlspecialchars($rol_usuario); ?></span>
                </p>
            </div>
            
            <div class="exchange-rates">
                <div class="rates-header">
                    <div class="header-content">
                        <h2>TASAS DE CAMBIO BCV</h2>
                        <p class="rates-subtitle">Tasas oficiales del Banco Central de Venezuela</p>
                    </div>
                </div>
                
                <div class="rates-grid">
                    <!-- Dólar Americano -->
                    <div class="rate-card usd">
                        <div class="currency-main">
                            <div class="currency-badge">
                                <span class="currency-flag">🇺🇸d</span>
                                <div class="currency-details">
                                    <span class="currency-code">USD</span>
                                    <span class="currency-name">Dólar Americano</span>
                                </div>
                            </div>
                            <div class="rate-value">
                                <span class="rate-amount">Bs. <?php echo $tasas_bcv['dolar']; ?></span>
                                <span class="currency-symbol">$</span>
                            </div>
                        </div>
                        <div class="trend-indicator <?php echo $tasas_bcv['tendencia_dolar'] === 'sube' ? 'positive' : 'negative'; ?>">
                            <span class="trend-arrow">
                                <?php echo $tasas_bcv['tendencia_dolar'] === 'sube' ? '↗' : '↘'; ?>
                            </span>
                            <span>
                                <?php 
                                echo ($tasas_bcv['porcentaje_dolar'] >= 0 ? '+' : '') . $tasas_bcv['porcentaje_dolar'] . '%';
                                ?>
                            </span>
                        </div>
                        <div class="previous-rate">
                            Anterior: Bs. <?php echo $tasas_bcv['dolar_anterior']; ?>
                        </div>
                    </div>
                    
                    <!-- Euro -->
                    <div class="rate-card eur">
                        <div class="currency-main">
                            <div class="currency-badge">
                                <span class="currency-flag">🇪🇺</span>
                                <div class="currency-details">
                                    <span class="currency-code">EUR</span>
                                    <span class="currency-name">Euro</span>
                                </div>
                            </div>
                            <div class="rate-value">
                                <span class="rate-amount">Bs. <?php echo $tasas_bcv['euro']; ?></span>
                                <span class="currency-symbol">€</span>
                            </div>
                        </div>
                        <div class="trend-indicator <?php echo $tasas_bcv['tendencia_euro'] === 'sube' ? 'positive' : 'negative'; ?>">
                            <span class="trend-arrow">
                                <?php echo $tasas_bcv['tendencia_euro'] === 'sube' ? '↗' : '↘'; ?>
                            </span>
                            <span>
                                <?php 
                                echo ($tasas_bcv['porcentaje_euro'] >= 0 ? '+' : '') . $tasas_bcv['porcentaje_euro'] . '%';
                                ?>
                            </span>
                        </div>
                        <div class="previous-rate">
                            Anterior: Bs. <?php echo $tasas_bcv['euro_anterior']; ?>
                        </div>
                    </div>
                </div>
                
                <div class="rates-footer">
                    <button class="refresh-btn" onclick="actualizarTasas()">
                        <span class="refresh-icon">🔄</span>
                        Actualizar Tasas
                    </button>
                </div>
            </div>
            
        </div>
    </main>
    
    <script>
        async function actualizarTasas() {
            const btn = document.querySelector('.refresh-btn');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<span class="refresh-icon">⏳</span> Actualizando...';
            btn.disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('accion', 'actualizar_tasas');
                
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor: ' + response.status);
                }
                
                const nuevasTasas = await response.json();
                
                if (!nuevasTasas || typeof nuevasTasas !== 'object') {
                    throw new Error('Datos de tasas inválidos');
                }
                
                // Actualizar los valores en la interfaz
                actualizarInterfazTasas(nuevasTasas);
                
                btn.innerHTML = '<span class="refresh-icon">✓</span> Actualizado';
                mostrarNotificacion('Tasas actualizadas correctamente');
                
            } catch (error) {
                console.error('Error al actualizar tasas:', error);
                btn.innerHTML = '<span class="refresh-icon">❌</span> Error';
                mostrarNotificacion('Error al actualizar tasas: ' + error.message, 'error');
            }
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 3000);
        }

        function actualizarInterfazTasas(tasas) {
            // Actualizar dólar
            if (document.querySelector('.usd .rate-amount')) {
                document.querySelector('.usd .rate-amount').textContent = `Bs. ${tasas.dolar}`;
            }
            if (document.querySelector('.usd .previous-rate')) {
                document.querySelector('.usd .previous-rate').textContent = `Anterior: Bs. ${tasas.dolar_anterior}`;
            }
            
            // Actualizar euro
            if (document.querySelector('.eur .rate-amount')) {
                document.querySelector('.eur .rate-amount').textContent = `Bs. ${tasas.euro}`;
            }
            if (document.querySelector('.eur .previous-rate')) {
                document.querySelector('.eur .previous-rate').textContent = `Anterior: Bs. ${tasas.euro_anterior}`;
            }
            // Actualizar tendencias dólar
            const trendDolar = document.querySelector('.usd .trend-indicator');
            if (trendDolar) {
                trendDolar.className = `trend-indicator ${tasas.tendencia_dolar === 'sube' ? 'positive' : 'negative'}`;
                trendDolar.innerHTML = `
                    <span class="trend-arrow">${tasas.tendencia_dolar === 'sube' ? '↗' : '↘'}</span>
                    <span>${(tasas.porcentaje_dolar >= 0 ? '+' : '') + tasas.porcentaje_dolar}%</span>
                `;
            }
            
            // Actualizar tendencias euro
            const trendEuro = document.querySelector('.eur .trend-indicator');
            if (trendEuro) {
                trendEuro.className = `trend-indicator ${tasas.tendencia_euro === 'sube' ? 'positive' : 'negative'}`;
                trendEuro.innerHTML = `
                    <span class="trend-arrow">${tasas.tendencia_euro === 'sube' ? '↗' : '↘'}</span>
                    <span>${(tasas.porcentaje_euro >= 0 ? '+' : '') + tasas.porcentaje_euro}%</span>
                `;
            }
        }

        function mostrarNotificacion(mensaje, tipo = 'success') {
            // Remover notificaciones anteriores
            const notificacionesAnteriores = document.querySelectorAll('.update-notification');
            notificacionesAnteriores.forEach(notif => notif.remove());
            
            const notificacion = document.createElement('div');
            notificacion.className = `update-notification ${tipo === 'error' ? 'error' : ''}`;
            notificacion.innerHTML = `
                <span class="notification-icon">${tipo === 'success' ? '✓' : '❌'}</span>
                <span>${mensaje}</span>
            `;
            
            document.body.appendChild(notificacion);
            
            setTimeout(() => {
                notificacion.style.opacity = '0';
                notificacion.style.transform = 'translateX(100px)';
                setTimeout(() => {
                    if (notificacion.parentNode) {
                        notificacion.remove();
                    }
                }, 300);
            }, 4000);
        }
    </script>
</body>
</html>