<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';
require_once 'menu.php';
require_once 'permisos.php';

$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);

if (!$sistemaPermisos->puedeVer('ventas')) {
    header('Location: inicio.php');
    exit();
}

// Obtener todas las facturas ordenadas por fecha y número
try {
    $stmt = $pdo->query("SELECT * FROM ventas ORDER BY id_venta DESC");
    $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $_SESSION['error'] = "❌ Error al cargar las facturas: " . $e->getMessage();
    $facturas = [];
}

// Función para obtener la clase CSS del método de pago
function get_badge_class($metodo) {
    $metodo = strtolower(str_replace(' ', '-', $metodo));
    // Los nombres deben coincidir con las clases definidas en facturas.css
    if ($metodo === 'pago-móvil' || $metodo === 'transferencia' || $metodo === 'débito') {
        return 'pago-móvil'; // Usamos la clase general para todos los no-efectivo
    }
    return $metodo;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Facturas</title>
    <link rel="stylesheet" href="css/facturas.css"> 
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<main class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Facturas de Venta</h1>
            <?php if ($sistemaPermisos->puedeVer('ventas')): ?>
            <a href="facturacion.php" class="btn-facturas-lista">
                ➕ Nueva Factura
            </a>
            <?php endif; ?>
        </div>
        
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="users-table">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nro Factura</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Total ($)</th>
                            <th>Total (Bs)</th>
                            <th>Tasa Usada</th>
                            <th>Método Pago</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($facturas)): ?>
                            <tr>
                                <td colspan="8" class="empty-state">No hay facturas registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($facturas as $factura): ?>
                                <tr>
                                    <td><?= htmlspecialchars($factura['nro_factura']) ?></td>
                                    <td><?= htmlspecialchars($factura['cliente']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($factura['fecha'])) ?></td>
                                    
                                    <td style="text-align: right;">$. <?= number_format($factura['total_usd'], 2, ',', '.') ?></td>
                                    <td style="text-align: right;">Bs. <?= number_format($factura['total_bs'], 2, ',', '.') ?></td>
                                    
                                    <td style="text-align: right;"><?= number_format($factura['tasa_usd'], 2, ',', '.') ?></td>
                                    
                                    <td>
                                        <span class="badge-metodo <?= get_badge_class($factura['metodo_pago']) ?>">
                                            <?= htmlspecialchars($factura['metodo_pago']) ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <div class="acciones-container">
                                            <a href="ver_factura.php?id=<?= $factura['id_venta'] ?>" class="btn-action btn-ver">
                                                👁 Ver
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
<script>
    // Script para desaparecer alertas
    $(document).ready(function() {
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    });
</script>
</body>
</html>