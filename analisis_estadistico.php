<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';
require_once 'permisos.php';
$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);

if (!$sistemaPermisos->puedeVer('reportes')) {
    header('Location: inicio.php');
    exit();
}

// Inicializar arrays para evitar errores
$datosReales = [
    'inventario' => [],
    'ventas' => [],
    'meses' => []
];

function obtenerDatosReales($pdo) {
    $datos = [
        'inventario' => [],
        'ventas' => [],
        'meses' => []
    ];
    
    try {
        // Inventario por mes
        $sql_inventario = "
            SELECT 
                MONTH(created_at) as mes,
                SUM(cantidad) as total_inventario
            FROM productos 
            WHERE estado = 'active'
            GROUP BY MONTH(created_at)
            ORDER BY mes
        ";
        
        $stmt = $pdo->prepare($sql_inventario);
        $stmt->execute();
        
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $datos['inventario'][] = $row['total_inventario'];
            $datos['meses'][] = obtenerNombreMes($row['mes']);
        }
        
        // Ventas por mes (ajustado según tu estructura)
        $sql_ventas = "
            SELECT 
                MONTH(fecha_venta) as mes,
                SUM(total_venta_eur) as total_ventas
            FROM ventas 
            WHERE estado = 'completada'
            GROUP BY MONTH(fecha_venta)
            ORDER BY mes
        ";
        
        $stmt = $pdo->prepare($sql_ventas);
        $stmt->execute();
        
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $datos['ventas'][] = $row['total_ventas'];
        }
        
    } catch (PDOException $e) {
        // Log del error
        error_log("Error en análisis estadístico: " . $e->getMessage());
    }
    
    return $datos;
}

function obtenerNombreMes($numero) {
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    return $meses[$numero] ?? 'Mes ' . $numero;
}

// Obtener datos reales
global $pdo;
if (isset($pdo)) {
    $datosReales = obtenerDatosReales($pdo);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis Estadístico - Sistema Nexus</title>
    <link rel="stylesheet" href="css/resportes.css">
    <link rel="stylesheet" href="css/analisis.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/analisis.js" defer></script>
</head>
<body>
    <?php require_once 'menu.php'; ?>
    
    <main class="main-content">
        <div class="content-wrapper">
            <!-- Header -->
            <div class="page-header">
                <h1 class="page-title">Análisis de Correlación Estadística</h1>
                <div class="reporte-fecha">
                </div>
            </div>

            <!-- Descripción del análisis -->
            <div class="descripcion-analisis">
                <div class="descripcion-titulo">
                    📋 ¿Qué vamos a analizar?
                </div>
                <div class="descripcion-texto">
                    Esta herramienta calcula la <strong>correlación estadística</strong> entre el inventario disponible y las ventas realizadas. 
                    La correlación nos ayuda a entender si existe una relación entre la cantidad de productos en inventario 
                    y el volumen de ventas, lo que permite tomar mejores decisiones de gestión y optimizar los recursos.
                </div>
                
                <div class="variables-explicacion">
                    <div class="variable-item">
                        <div class="variable-nombre">
                            📦 Variable X: Inventario
                        </div>
                        <div class="variable-descripcion">
                            Representa la <strong>cantidad total de productos disponibles en stock</strong> cada mes. 
                            Un valor más alto indica mayor disponibilidad de productos para la venta.
                        </div>
                    </div>
                    
                    <div class="variable-item">
                        <div class="variable-nombre">
                            💰 Variable Y: Ventas
                        </div>
                        <div class="variable-descripcion">
                            Representa el <strong>monto total en dólares ($)</strong> de ventas realizadas cada mes. 
                            Muestra el desempeño comercial del periodo en términos monetarios.
                        </div>
                    </div>
                    
                    <div class="variable-item">
                        <div class="variable-nombre">
                            📊 Coeficiente "r"
                        </div>
                        <div class="variable-descripcion">
                            Mide la <strong>fuerza y dirección</strong> de la relación lineal entre inventario y ventas. 
                            Valores cercanos a <strong>1</strong> indican fuerte correlación positiva.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de datos reales -->
            <div class="datos-contenedor">
                <div class="datos-reales">
                    <h3>📊 Datos Reales del Sistema</h3>
                    <div class="datos-grid">
                        <div class="dato-item">
                            <span class="dato-label">Meses Registrados:</span>
                            <span class="dato-valor"><?= count($datosReales['meses']) ?></span>
                        </div>
                        <div class="dato-item">
                            <span class="dato-label">Total Inventario:</span>
                            <span class="dato-valor"><?= number_format(array_sum($datosReales['inventario'])) ?> unidades</span>
                        </div>
                        <div class="dato-item">
                            <span class="dato-label">Total Ventas:</span>
                            <span class="dato-valor" data-tipo="moneda"><?= number_format(array_sum($datosReales['ventas']), 2) ?></span>
                        </div>
                    </div>
                    <button id="btnCargarReales" class="btn-cargar-reales">
                        📥 Cargar Datos del Sistema
                    </button>
                </div>
            </div>

            <!-- Contenedor principal de correlación -->
            <div class="correlacion-container">
                <div class="correlacion-header">
                    <h2>📊 Calculadora de Correlación</h2>
                </div>
                
                <div class="datos-tablas">
                    <!-- Tabla para variable X -->
                    <div class="tabla-contenedor">
                        <div class="tabla-titulo">
                            <span>
                                📦 Variable X: Inventario 
                                <span class="tabla-subtitulo">(Cantidad de productos en stock)</span>
                            </span>
                            <button id="btnAgregarX" class="btn-agregar-fila">+</button>
                        </div>
                        <table class="tabla-datos">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Valor (unidades)</th>
                                    <th>Descripción</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody id="datosX">
                                <!-- Las filas se generarán dinámicamente -->
                            </tbody>
                        </table>
                        <div class="tabla-total">
                            Total datos X: <span id="totalX">0</span>
                        </div>
                    </div>

                    <!-- Tabla para variable Y -->
                    <div class="tabla-contenedor">
                        <div class="tabla-titulo">
                            <span>
                                💰 Variable Y: Ventas 
                                <span class="tabla-subtitulo">(Monto en dólares $)</span>
                            </span>
                            <button id="btnAgregarY" class="btn-agregar-fila">+</button>
                        </div>
                        <table class="tabla-datos">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Valor ($)</th>
                                    <th>Descripción</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody id="datosY">
                                <!-- Las filas se generarán dinámicamente -->
                            </tbody>
                        </table>
                        <div class="tabla-total">
                            Total datos Y: <span id="totalY">0</span>
                        </div>
                    </div>
                </div>

                <!-- Resultados -->
                <div id="resultados" class="resultados-container" style="display: none;">
                    <h3>📈 Resultados del Análisis</h3>
                    
                    <div class="resultados-grid">
                        <div class="resultado-item">
                            <div class="resultado-valor" id="valorR">0.0000</div>
                            <div class="resultado-label">Coeficiente de Correlación (r)</div>
                        </div>
                        <div class="resultado-item">
                            <div class="resultado-valor" id="valorR2">0.0000</div>
                            <div class="resultado-label">Coeficiente de Determinación (r²)</div>
                        </div>
                        <div class="resultado-item">
                            <div class="resultado-valor" id="totalPares">0</div>
                            <div class="resultado-label">Pares de Datos Analizados</div>
                        </div>
                    </div>

                    <div class="interpretacion-container">
                        <div class="interpretacion-titulo">
                            📋 Interpretación
                        </div>
                        <div id="interpretacion" class="nivel-correlacion">
                            Ingrese datos para ver la interpretación
                        </div>
                        <div id="explicacion" class="interpretacion-descripcion" style="margin-top: 15px;">
                            <strong>El coeficiente de correlación (r) mide la relación lineal entre dos variables:</strong><br>
                            • <strong>r cercano a +1:</strong> A mayor inventario, mayores ventas (correlación positiva fuerte)<br>
                            • <strong>r cercano a -1:</strong> A mayor inventario, menores ventas (correlación negativa fuerte)<br>
                            • <strong>r cercano a 0:</strong> No hay relación lineal entre inventario y ventas<br>
                            <br>
                            <small>💡 <strong>Interpretación práctica:</strong> Si r es positivo y alto, aumentar el inventario podría incrementar las ventas. Si es negativo, podría indicar sobrestock.</small>
                        </div>
                    </div>

                    <!-- Gráfico de dispersión -->
                    <div class="grafico-dispersion">
                        <canvas id="dispersionChart"></canvas>
                        <div class="grafico-leyenda">
                            📈 Gráfico de dispersión: Relación entre Inventario (X) y Ventas en $ (Y)
                        </div>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="acciones-container">
                    <button id="btnCalcular" class="btn-calcular">
                        📊 Calcular Correlación
                    </button>
                    <button id="btnLimpiar" class="btn-limpiar">
                        🗑️ Limpiar Datos
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Pasar datos de PHP a JavaScript
        const datosReales = {
            inventario: <?= json_encode($datosReales['inventario']) ?>,
            ventas: <?= json_encode($datosReales['ventas']) ?>,
            meses: <?= json_encode($datosReales['meses']) ?>
        };
    </script>
</body>
</html>