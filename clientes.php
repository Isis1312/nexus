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
if (!$sistemaPermisos->puedeVer('clientes')) {
    header('Location: inicio.php');
    exit();
}

// Procesar búsqueda 
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';
$where = '';
$params = []; 

if (!empty($busqueda)) {
    $where = "WHERE cedula LIKE ? OR nombre LIKE ?";
    $searchTerm = "%$busqueda%";
    $params = [$searchTerm, $searchTerm];
}

// Obtener clientes de la base de datos
try {
    $sql = "SELECT id, nombre, cedula, telefono, direccion FROM clientes $where ORDER BY nombre";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalClientes = count($clientes);
} catch (PDOException $e) {
    $clientes = [];
    $totalClientes = 0;
    $error = "Error al cargar los clientes: " . $e->getMessage();
}

// Procesar eliminación de cliente
if (isset($_POST['eliminar_id'])) {
    $id_eliminar = $_POST['eliminar_id'];
    try {
        $sql = "DELETE FROM clientes WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_eliminar]);
        $_SESSION['mensaje'] = "Cliente eliminado correctamente";
        header('Location: clientes.php');
        exit();
    } catch (PDOException $e) {
        $error = "Error al eliminar el cliente: " . $e->getMessage();
    }
}

require_once 'menu.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de Clientes</title>
  <link rel="stylesheet" href="css/clientes.css">
</head>
<body>

<main class="main-content">
  <div class="content-wrapper">

    <!-- Header -->
    <div class="page-header">
      <h1 class="page-title">Gestión de Clientes</h1>
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
                <a href="clientes.php" class="clear-search">Limpiar</a>
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

<!-- Modal Agregar Cliente -->
<div id="modalAgregar" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Agregar Nuevo Cliente</h3>
      <span class="close-modal" onclick="cerrarModalAgregar()">&times;</span>
    </div>
    <div class="modal-body">
      <form id="formAgregar" method="POST" action="guardar_cliente.php">
        <input type="hidden" name="origen" value="clientes">
        
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

<!-- Modal Editar Cliente -->
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

<!-- Modal Confirmar Eliminación -->
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
  document.getElementById('clienteEliminarNombre').textContent = nombre;
  document.getElementById('modalEliminar').style.display = 'block';
}

function cerrarModalEliminar() {
  document.getElementById('modalEliminar').style.display = 'none';
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
  const modals = document.getElementsByClassName('modal');
  for (let modal of modals) {
    if (event.target == modal) {
      modal.style.display = 'none';
    }
  }
}
</script>

</body>
</html>