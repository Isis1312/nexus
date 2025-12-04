<?php
session_start();
require_once 'conexion.php';
require_once 'menu.php';

// Verificar si hay mensajes de sesión
$mensaje = $_SESSION['mensaje'] ?? '';
$error = $_SESSION['error'] ?? '';

// Limpiar los mensajes de sesión después de mostrarlos
if (isset($_SESSION['mensaje'])) unset($_SESSION['mensaje']);
if (isset($_SESSION['error'])) unset($_SESSION['error']);

// Verificar si hay un cliente recién creado para seleccionar automáticamente
$cliente_reciente = $_SESSION['cliente_reciente'] ?? null;
if (isset($_SESSION['cliente_reciente'])) unset($_SESSION['cliente_reciente']);

$busqueda = $_GET['busqueda'] ?? '';
$sql = "SELECT * FROM clientes";
if (!empty($busqueda)) {
    $sql .= " WHERE cedula LIKE :busqueda";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':busqueda' => "%$busqueda%"]);
    $clientes = $stmt->fetchAll();
} else {
    $stmt = $pdo->query($sql);
    $clientes = $stmt->fetchAll();
}
$totalClientes = count($clientes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Facturación</title>
    <link rel="stylesheet" href="css/clientes.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<main class="main-content">
    <div class="content-wrapper">

        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">Gestión de Facturación</h1>
        </div>

        <!-- Mostrar mensajes -->
        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-success" id="mensaje-exito">
                <?= htmlspecialchars($mensaje) ?>
                <?php if ($cliente_reciente): ?>
                    <br><small>Cliente disponible para facturación</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error" id="mensaje-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Controles superiores -->
        <div class="controls-container">
            <button class="btn-agregar" onclick="abrirModalAgregar()">
                <span>+</span> Agregar Cliente
            </button>

            <div class="filtros-container">
                <form method="GET" class="filtros-form">
                    <div class="search-container">
                        <input type="text" name="busqueda" class="search-input" placeholder="Buscar por cédula..." value="<?= htmlspecialchars($busqueda) ?>">
                        <button type="submit" class="btn-buscar">Buscar</button>
                        <?php if (!empty($busqueda)): ?>
                            <a href="clientes_factura.php" class="clear-search">Limpiar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de clientes -->
        <div class="users-table">
            <div class="table-header">
                <h3>Lista de Clientes (<?= $totalClientes ?> encontrados)</h3>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Cédula</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clientes)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">No se encontraron clientes</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clientes as $cliente): ?>
                            <tr id="cliente-<?= $cliente['id'] ?>" class="<?= ($cliente_reciente == $cliente['id']) ? 'cliente-reciente' : '' ?>">
                                <td><?= htmlspecialchars($cliente['nombre']) ?></td>
                                <td><?= htmlspecialchars($cliente['cedula']) ?></td>
                                <td><?= htmlspecialchars($cliente['telefono'] ?? '') ?></td>
                                <td><?= htmlspecialchars($cliente['direccion'] ?? '') ?></td>
                                <td>
                                    <div class="acciones-container">
                                        <a href="crear_factura.php?cliente_id=<?= $cliente['id'] ?>" class="btn-action btn-factura">
                                            Factura
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

<!-- Modal Agregar Cliente -->
<div id="modalAgregar" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Agregar Nuevo Cliente</h3>
            <span class="close-modal" onclick="cerrarModalAgregar()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="formAgregar" method="POST" action="guardar_cliente.php">
                <!-- Campo oculto para identificar el origen -->
                <input type="hidden" name="origen" value="facturacion">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nombre:</label>
                        <input type="text" name="nombre" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Cédula:</label>
                        <input type="text" name="cedula" class="form-input" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Teléfono:</label>
                        <input type="text" name="telefono" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Dirección:</label>
                        <input type="text" name="direccion" class="form-input" required>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-volver" onclick="cerrarModalAgregar()">Volver</button>
                    <button type="submit" class="btn-guardar">Guardar Cliente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Funciones para modales
    function abrirModalAgregar() {
        document.getElementById('modalAgregar').style.display = 'block';
    }

    function cerrarModalAgregar() {
        document.getElementById('modalAgregar').style.display = 'none';
        // Limpiar el formulario al cerrar
        document.getElementById('formAgregar').reset();
    }

    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        const modal = document.getElementById('modalAgregar');
        if (event.target == modal) {
            cerrarModalAgregar();
        }
    }

    // Auto-ocultar mensajes después de 5 segundos
    $(document).ready(function() {
        setTimeout(function() {
            $('#mensaje-exito, #mensaje-error').fadeOut('slow');
        }, 5000);
        
        // Si hay un cliente recién creado, resaltarlo
        <?php if ($cliente_reciente): ?>
            $('#cliente-<?= $cliente_reciente ?>').css({
                'background-color': 'rgba(0, 139, 139, 0.1)',
                'border-left': '4px solid #008B8B'
            });
        <?php endif; ?>
    });
</script>
</body>
</html>