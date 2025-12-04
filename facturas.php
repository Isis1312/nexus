<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';
require_once 'menu.php';

// Inicializar sistema de permisos
require_once 'permisos.php';
$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);

// Verificar si puede ver este módulo 
if (!$sistemaPermisos->puedeVer('ventas')) {
    header('Location: inicio.php');
    exit();
}

// Procesar búsqueda
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$where = '';
$params = [];

if (!empty($busqueda)) {
    $where = "WHERE v.nro_factura LIKE ? OR c.nombre LIKE ? OR c.cedula LIKE ? OR v.cliente LIKE ?";
    $searchTerm = "%$busqueda%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
}

// Obtener facturas - MODIFICADO: Sin JOIN con usuario
try {
    $sql = "SELECT v.*, c.cedula 
            FROM ventas v 
            LEFT JOIN clientes c ON v.id_cliente = c.id 
            $where 
            ORDER BY v.fecha DESC, v.id_venta DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalFacturas = count($facturas);
} catch (PDOException $e) {
    $facturas = [];
    $totalFacturas = 0;
    $error = "Error al cargar las facturas: " . $e->getMessage();
}

// Verificar mensajes
$mensaje = $_SESSION['mensaje'] ?? '';
$error_msg = $_SESSION['error'] ?? '';
unset($_SESSION['mensaje']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Facturas</title>
    <link rel="stylesheet" href="css/facturas.css">
</head>
<body>

<main class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">Facturas</h1>
            <a href="facturacion.php" class="btn-facturas-lista">
                ➕ Nueva Factura
            </a>
        </div>

        <!-- Mostrar mensajes -->
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-success" id="mensaje-exito">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-error" id="mensaje-error">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <!-- Controles superiores -->
        <div class="controls-container-facturas">
            <div class="filtros-container">
                <form method="GET" class="filtros-form">
                    <div class="search-container">
                        <input type="text" name="busqueda" class="search-input" 
                               placeholder="Buscar por factura, cliente o cédula..." 
                               value="<?= htmlspecialchars($busqueda) ?>">
                        <button type="submit" class="btn-buscar">Buscar</button>
                        <?php if (!empty($busqueda)): ?>
                            <a href="facturas.php" class="clear-search">Limpiar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de facturas -->
        <div class="users-table">
            <div class="table-header">
                <h3>Lista de Facturas (<?= $totalFacturas ?> encontradas)</h3>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>N° Factura</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Cédula</th>
                            <th>Método Pago</th>
                            <th>Total Bs</th>
                            <th>Total $</th>
                            <th>Usuario</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($facturas)): ?>
                            <tr>
                                <td colspan="9" class="empty-state">No se encontraron facturas</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($facturas as $factura): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($factura['nro_factura']) ?></strong></td>
                                <td><?= date('d/m/Y', strtotime($factura['fecha'])) ?></td>
                                <td><?= htmlspecialchars($factura['cliente']) ?></td>
                                <td><?= htmlspecialchars($factura['cedula'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge-metodo <?= strtolower(str_replace(' ', '-', $factura['metodo_pago'])) ?>">
                                        <?= htmlspecialchars($factura['metodo_pago']) ?>
                                    </span>
                                </td>
                                <td><strong>Bs. <?= number_format($factura['total_bs'], 2, ',', '.') ?></strong></td>
                                <td><strong>$ <?= number_format($factura['total_usd'], 2, ',', '.') ?></strong></td>
                                <!-- MODIFICADO: Usa el nombre de la sesión en lugar de usuario_nombre -->
                                <td><?= htmlspecialchars($_SESSION['nombre'] ?? 'Sistema') ?></td>
                                <td>
                                    <div class="acciones-container">
                                        <a href="ver_factura.php?id=<?= $factura['id_venta'] ?>" 
                                           class="btn-action btn-ver" target="_blank">
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
// Auto-ocultar mensajes después de 5 segundos
setTimeout(function() {
    const mensajes = document.querySelectorAll('.alert');
    mensajes.forEach(function(mensaje) {
        mensaje.style.transition = 'opacity 0.5s';
        mensaje.style.opacity = '0';
        setTimeout(function() {
            mensaje.style.display = 'none';
        }, 500);
    });
}, 5000);
</script>

</body>
</html>