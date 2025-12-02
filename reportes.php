<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

// Incluir la conexión a la base de datos
require_once 'conexion.php';

// Inicializar sistema de permisos
require_once 'permisos.php';
$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);

// Verificar si puede ver este módulo 
if (!$sistemaPermisos->puedeVer('reportes')) {
    header('Location: inicio.php');
    exit();
}

// Datos estáticos para reportes
$reportes_data = [
    'productos_mas_vendidos' => [
        'titulo' => 'Productos Más Vendidos del Mes',
        'icono' => '🏆',
        'color' => '#FF6B6B',
        'datos' => [
            ['producto' => 'Leche Entera 1L', 'ventas' => 245, 'cambio' => '+12%'],
            ['producto' => 'Arroz 1kg', 'ventas' => 189, 'cambio' => '+5%'],
            ['producto' => 'Aceite Vegetal', 'ventas' => 167, 'cambio' => '+8%'],
            ['producto' => 'Harina PAN', 'ventas' => 156, 'cambio' => '+3%'],
            ['producto' => 'Azúcar 1kg', 'ventas' => 142, 'cambio' => '+7%']
        ]
    ],
    'productos_menos_vendidos' => [
        'titulo' => 'Productos Menos Vendidos del Mes',
        'icono' => '📉',
        'color' => '#4ECDC4',
        'datos' => [
            ['producto' => 'Salsa de Soja', 'ventas' => 12, 'cambio' => '-15%'],
            ['producto' => 'Aceitunas', 'ventas' => 15, 'cambio' => '-8%'],
            ['producto' => 'Mostaza', 'ventas' => 18, 'cambio' => '-5%'],
            ['producto' => 'Encurtidos', 'ventas' => 21, 'cambio' => '-12%'],
            ['producto' => 'Salsa Picante', 'ventas' => 23, 'cambio' => '-3%']
        ]
    ],
    'ventas_dia' => [
        'titulo' => 'Ventas del Día',
        'icono' => '📅',
        'color' => '#45B7D1',
        'datos' => [
            'total' => 12560.75,
            'transacciones' => 89,
            'ticket_promedio' => 141.13,
            'cambio' => '+8.5%'
        ]
    ],
    'ventas_semana' => [
        'titulo' => 'Ventas de la Semana',
        'icono' => '📊',
        'color' => '#96CEB4',
        'datos' => [
            'total' => 84520.30,
            'transacciones' => 623,
            'ticket_promedio' => 135.67,
            'cambio' => '+12.3%'
        ]
    ],
    'ventas_mes' => [
        'titulo' => 'Ventas del Mes',
        'icono' => '📈',
        'color' => '#FECA57',
        'datos' => [
            'total' => 325840.50,
            'transacciones' => 2450,
            'ticket_promedio' => 133.00,
            'cambio' => '+15.8%'
        ]
    ],
    'tasa_semanal' => [
        'titulo' => 'Tasa Cambiaria Semanal',
        'icono' => '🔄',
        'color' => '#FF9FF3',
        'datos' => [
            'actual' => 36.50,
            'anterior' => 35.20,
            'cambio_porcentaje' => '+3.7%',
            'cambio_valor' => 1.30
        ]
    ],
    'tasa_mensual' => [
        'titulo' => 'Tasa Cambiaria Mensual',
        'icono' => '💰',
        'color' => '#54A0FF',
        'datos' => [
            'actual' => 36.50,
            'anterior' => 32.80,
            'cambio_porcentaje' => '+11.3%',
            'cambio_valor' => 3.70
        ]
    ]
];

// Colores para los botones
$botones_colores = [
    '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4',
    '#FECA57', '#FF9FF3', '#54A0FF', '#5F27CD'
];

// Determinar qué reporte mostrar
$reporte_actual = isset($_GET['reporte']) ? $_GET['reporte'] : 'productos_mas_vendidos';
$reporte = $reportes_data[$reporte_actual] ?? $reportes_data['productos_mas_vendidos'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes y Métricas</title>
    <link rel="stylesheet" href="css/reportes.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php require_once 'menu.php'; ?>
    
    <main class="main-content">
        <div class="content-wrapper">
            <!-- Header de la página -->
            <div class="page-header">
                <h1 class="page-title">Reportes y Métricas</h1>
                <div class="reporte-fecha">
                    <?= date('d/m/Y') ?>
                </div>
            </div>

            <!-- Submenú de Reportes - BOTONES HORIZONTALES -->
            <div class="submenu-reportes">
                <div class="submenu-botones">
                    <?php 
                    $i = 0;
                    foreach ($reportes_data as $key => $reporte_item): 
                        $color = $botones_colores[$i % count($botones_colores)];
                        $i++;
                    ?>
                    <a href="?reporte=<?= $key ?>" 
                       class="reporte-btn <?= $reporte_actual == $key ? 'active' : '' ?>" 
                       style="--btn-color: <?= $color ?>">
                        <span class="btn-icon"><?= $reporte_item['icono'] ?></span>
                        <span class="btn-text"><?= $reporte_item['titulo'] ?></span>
                    </a>
                    <?php endforeach; ?>
                    
                    <!-- Botón de Análisis Estadístico -->
                    <a href="analisis_estadistico.php" 
                       class="reporte-btn" 
                       style="--btn-color: #6c5ce7">
                        <span class="btn-icon">📈</span>
                        <span class="btn-text">Análisis Estadístico</span>
                    </a>
                </div>
            </div>

            <!-- Contenido del Reporte -->
            <div class="reporte-contenido">
                <div class="reporte-header">
                    <h2><?= $reporte['icono'] ?> <?= $reporte['titulo'] ?></h2>
                    <div class="reporte-acciones">
                        <button class="btn-exportar">📥 Exportar PDF</button>
                        <button class="btn-imprimir">🖨️ Imprimir</button>
                    </div>
                </div>

                <div class="reporte-body">
                    <?php if (in_array($reporte_actual, ['productos_mas_vendidos', 'productos_menos_vendidos'])): ?>
                        <!-- Reporte de Productos -->
                        <div class="productos-lista">
                            <?php foreach ($reporte['datos'] as $index => $producto): ?>
                            <div class="producto-item <?= $index < 3 && $reporte_actual == 'productos_mas_vendidos' ? 'destacado' : '' ?>">
                                <div class="producto-rank">#<?= $index + 1 ?></div>
                                <div class="producto-info">
                                    <div class="producto-nombre"><?= $producto['producto'] ?></div>
                                    <div class="producto-detalle"><?= $producto['ventas'] ?> unidades vendidas</div>
                                </div>
                                <div class="producto-cambio <?= strpos($producto['cambio'], '+') !== false ? 'positivo' : 'negativo' ?>">
                                    <?= $producto['cambio'] ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Gráfico de Productos -->
                        <div class="grafico-container">
                            <canvas id="productosChart"></canvas>
                        </div>

                    <?php elseif (in_array($reporte_actual, ['ventas_dia', 'ventas_semana', 'ventas_mes'])): ?>
                        <!-- Reporte de Ventas -->
                        <div class="metricas-ventas">
                            <div class="metrica-principal">
                                <div class="metrica-valor">$<?= number_format($reporte['datos']['total'], 2) ?></div>
                                <div class="metrica-titulo">Total Ventas</div>
                                <div class="metrica-cambio <?= strpos($reporte['datos']['cambio'], '+') !== false ? 'positivo' : 'negativo' ?>">
                                    <?= $reporte['datos']['cambio'] ?>
                                </div>
                            </div>
                            
                            <div class="metricas-secundarias">
                                <div class="metrica-secundaria">
                                    <div class="metrica-valor"><?= $reporte['datos']['transacciones'] ?></div>
                                    <div class="metrica-titulo">Transacciones</div>
                                </div>
                                <div class="metrica-secundaria">
                                    <div class="metrica-valor">$<?= number_format($reporte['datos']['ticket_promedio'], 2) ?></div>
                                    <div class="metrica-titulo">Ticket Promedio</div>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico de Ventas -->
                        <div class="grafico-container">
                            <canvas id="ventasChart"></canvas>
                        </div>

                    <?php else: ?>
                        <!-- Reporte de Tasa Cambiaria -->
                        <div class="metricas-tasa">
                            <div class="tasa-actual">
                                <div class="tasa-valor">Bs <?= number_format($reporte['datos']['actual'], 2) ?></div>
                                <div class="tasa-titulo">Tasa Actual</div>
                            </div>
                            
                            <div class="tasa-comparacion">
                                <div class="tasa-anterior">
                                    <span>Anterior: Bs <?= number_format($reporte['datos']['anterior'], 2) ?></span>
                                </div>
                                <div class="tasa-variacion">
                                    <span>Variación: Bs <?= number_format($reporte['datos']['cambio_valor'], 2) ?></span>
                                </div>
                                <div class="tasa-porcentaje <?= strpos($reporte['datos']['cambio_porcentaje'], '+') !== false ? 'positivo' : 'negativo' ?>">
                                    <?= $reporte['datos']['cambio_porcentaje'] ?>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico de Tasa -->
                        <div class="grafico-container">
                            <canvas id="tasaChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Gráficos según el reporte actual
        const reporteActual = '<?= $reporte_actual ?>';
        
        if (reporteActual.includes('productos')) {
            // Gráfico de productos
            const ctx = document.getElementById('productosChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($reporte['datos'], 'producto')) ?>,
                    datasets: [{
                        label: 'Unidades Vendidas',
                        data: <?= json_encode(array_column($reporte['datos'], 'ventas')) ?>,
                        backgroundColor: '#008B8B',
                        borderColor: '#006666',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Distribución de Ventas por Producto'
                        }
                    }
                }
            });
        } else if (reporteActual.includes('ventas')) {
            // Gráfico de ventas
            const ctx = document.getElementById('ventasChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                    datasets: [{
                        label: 'Ventas ($)',
                        data: [1850, 1920, 2100, 1980, 2250, 2450, 2100],
                        borderColor: '#008B8B',
                        backgroundColor: 'rgba(0, 139, 139, 0.1)',
                        borderWidth: 3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Evolución de Ventas Diarias'
                        }
                    }
                }
            });
        } else {
            // Gráfico de tasa cambiaria
            const ctx = document.getElementById('tasaChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Sem 5'],
                    datasets: [{
                        label: 'Tasa USD/BS',
                        data: [32.80, 33.50, 34.20, 35.20, 36.50],
                        borderColor: '#008B8B',
                        backgroundColor: 'rgba(0, 139, 139, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Evolución de la Tasa Cambiaria'
                        }
                    }
                }
            });
        }
    });
    </script>
</body>
</html>