<?php
session_start();
require_once 'conexion.php';

// Obtener clientes
$stmt = $pdo->query("SELECT id, nombre, cedula, telefono, direccion FROM clientes ORDER BY nombre ASC");
$clientes = $stmt->fetchAll();
?>
<?php
session_start();
require_once 'conexion.php';

$busqueda = $_GET['busqueda'] ?? '';
$sql = "SELECT * FROM clientes";
if (!empty($busqueda)) {
  $sql .= " WHERE cedula LIKE '%$busqueda%'";
}
$stmt = $pdo->query($sql);
$clientes = $stmt->fetchAll();
$totalClientes = count($clientes);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de Facturación</title>
  <link rel="stylesheet" href="css/inventario.css">
  <link rel="stylesheet" href="css/clientes.css">
  <link rel="stylesheet" href="css/modales.css">
</head>
<body>

<main class="main-content">
  <div class="content-wrapper">

    <!-- Header -->
    <div class="page-header">
      <h1 class="page-title">Gestión de Facturación</h1>
    </div>

    <!-- Mostrar mensajes -->
    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="alert alert-success">
            <?= $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?= $error ?>
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
                <tr>
                    <td><?= htmlspecialchars($cliente['nombre']) ?></td>
                    <td><?= htmlspecialchars($cliente['cedula']) ?></td>
                    <td><?= htmlspecialchars($cliente['telefono']) ?></td>
                    <td><?= htmlspecialchars($cliente['direccion']) ?></td>
                    <td>
                        <div class="acciones-container">
                            <a href="crear_factura.php?cliente_id=<?= $cliente['id'] ?>" class="btn-action btn-factura">🧾 Factura</a>
                            <button class="btn-action btn-editar" onclick="abrirModalEditar(<?= $cliente['id'] ?>, '<?= addslashes($cliente['nombre']) ?>', '<?= addslashes($cliente['telefono']) ?>')">Editar</button>
                            <button class="btn-action btn-eliminar" onclick="confirmarEliminar(<?= $cliente['id'] ?>, '<?= addslashes($cliente['nombre']) ?>')">Eliminar</button>
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

<!-- Modales (idénticos a clientes.php) -->
<div id="modalAgregar" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Agregar Nuevo Cliente</h3>
      <span class="close-modal" onclick="cerrarModalAgregar()">&times;</span>
    </div>
    <div class="modal-body">
      <form id="formAgregar" method="POST" action="guardar_cliente.php">
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

<div id="modalEditar" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Editar Cliente</h3>
      <span class="close-modal" onclick="cerrarModalEditar()">&times;</span>
    </div>
    <div class="modal-body">
      <form id="formEditar" method="POST" action="actualizar_cliente.php">
        <input type="hidden" name="id" id="editar_id">
        <div class="form-row">
          <div class="form-group">
            <label>Nombre:</label>
            <input type="text" name="nombre" id="editar_nombre" class="form-input" required>
          </div>
          <div class="form-group">
            <label>Teléfono:</label>
            <input type="text" name="telefono" id="editar_telefono" class="form-input" required>
          </div>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-volver" onclick="cerrarModalEditar()">Volver</button>
          <button type="submit" class="btn-guardar">Actualizar Cliente</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="modalEliminar" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Confirmar Eliminación</h3>
      <span class="close-modal" onclick="cerrarModalEliminar()">&times;</span>
    </div>
    <div class="modal-body">
      <p>¿Está seguro que desea eliminar al cliente: <strong id="clienteEliminarNombre"></strong>?</p>
      <form id="formEliminar" method="POST">
        <input type="hidden" name="eliminar_id" id="eliminar_id">
        <div class="modal-actions">
          <button type="button" class="btn-volver" onclick="cerrarModalEliminar()">Cancelar</button>
          <button type="submit" class="btn-eliminar-confirmar">Eliminar</button>
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
}
function abrirModalEditar(id, nombre, telefono) {
  document.getElementById('editar_id').value = id;
  document.getElementById('editar_nombre').value = nombre;
  document.getElementById('editar_telefono').value = telefono;
  document.getElementById('modalEditar').style.display = 'block';
}
function cerrarModalEditar() {
  document.getElementById('modalEditar').style.display = 'none';
}
function confirmarEliminar(id, nombre) {
  document.getElementById('eliminar_id').value = id;
  document.getElementById('clienteEliminar