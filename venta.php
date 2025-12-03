<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

// Incluir la conexión a la base de datos
require_once 'conexion.php';
require_once 'menu.php';
require_once 'permisos.php';
$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);

// Verificar si puede ver este módulo 
if (!$sistemaPermisos->puedeVer('ventas')) {
    header('Location: inicio.php');
    exit();
}

// Leer tasas desde el archivo de cache
$tasas_file = 'js/tasas_cache.json';
if (file_exists($tasas_file)) {
    $tasas_data = json_decode(file_get_contents($tasas_file), true);
    $tasa_usd = isset($tasas_data['dolar']) ? floatval($tasas_data['dolar']) : 0;
    $tasa_eur = isset($tasas_data['euro']) ? floatval($tasas_data['euro']) : 0;
} else {
    // Valores por defecto si no existe cache
    $tasa_usd = 250.00;
    $tasa_eur = 270.00;
}

// Para compatibilidad con el modal (convierte USD → Bs)
$tasa_dia = $tasa_usd;
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de Ventas</title>

  <link rel="stylesheet" href="css/ventas.css">
</head>
<body>

<main class="main-content">
  <div class="content-wrapper">

<?php if (isset($_SESSION['mensaje'])): ?>
  <div class="alert alert-success"><?= $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
  <div class="alert alert-error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>


    <div class="page-header">
      <h1 class="page-title">Gestión de Ventas</h1>
    </div>

    <div class="controls-container">
      <button class="btn-agregar" onclick="abrirModalVenta()">
        <span>+</span> Registrar Venta
      </button>

      <div class="filtros-container">
        <form method="GET" class="filtros-form">
          <select name="estado" class="filtro-select">
          <option value="">Todos los estados</option>
          <option value="Pagado">Pagado</option>
          <option value="Pendiente">Pendiente</option>
          </select>

          <div class="search-container">
            <input type="text" name="busqueda" class="search-input" placeholder="Buscar ventas...">
            <button type="submit" class="btn-buscar">Buscar</button>
          </div>
        </form>
      </div>
    </div>

    <div class="users-table">
  <div class="table-header">
    <h3>Lista de Ventas</h3>
  </div>

  <div class="table-container">
    <table>
      <thead>
  <tr>
    <th>Factura</th>
    <th>Cliente</th>
    <th>Fecha</th>
    <th>Total (Bs)</th>
    <th>Total (USD)</th>
    <th>Total (EUR)</th>
    <th>Método de Pago</th>
    <th>Estado</th>
    <th>Acciones</th>
  </tr>
</thead>
<tbody>
  <?php
  $estado = $_GET['estado'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

$sql = "SELECT * FROM ventas WHERE 1=1";
$params = [];

if (!empty($estado)) {
    $sql .= " AND estado = ?";
    $params[] = $estado;
}

if (!empty($busqueda)) {
    $sql .= " AND (cliente LIKE ? OR nro_factura LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

$sql .= " ORDER BY fecha DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
  while ($venta = $stmt->fetch(PDO::FETCH_ASSOC)):
  ?>
  <tr>
  <td><?= htmlspecialchars($venta['nro_factura']) ?></td>
  <td><?= htmlspecialchars($venta['cliente']) ?></td>
  <td><?= date('d/m/Y', strtotime($venta['fecha'])) ?></td>
  <td><?= number_format($venta['total_bs'], 2) ?></td>
  <td><?= number_format($venta['total_usd'], 2) ?></td>
  <td><?= number_format($venta['total_eur'], 2) ?></td>
  <td><?= htmlspecialchars($venta['metodo_pago']) ?></td>
  <td><?= htmlspecialchars($venta['estado']) ?></td>
  <td>
    <div class="acciones-container">
      <button class="btn-action btn-editar">Ver</button>
      <button class="btn-action btn-eliminar">Eliminar</button>
    </div>
  </td>
</tr>
<?php endwhile; ?>
</tbody>
    </table>
  </div>
</div>

</div>
</div>

<!-- Modal Venta -->
<div id="modalVenta" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Registrar Nueva Venta</h3>
      <span class="close-modal" onclick="cerrarModalVenta()">&times;</span>
    </div>
    <div class="modal-body">
      <form id="formVenta" method="POST" action="procesar_venta.php">
        <div class="form-row">
          <div class="form-group">
            <label>Cliente:</label>
            <input type="text" name="cliente" class="form-input" required>
          </div>
          <div class="form-group">
            <label>Fecha:</label>
            <input type="date" name="fecha" class="form-input" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Método de Pago:</label>
            <select name="metodo" class="form-select" onchange="mostrarCamposPago(this.value)" required>
              <option value="">Seleccionar</option>
              <option value="Efectivo">Efectivo Bs</option>
              <option value="USD">Efectivo $</option>
              <option value="Pago Móvil">Pago Móvil</option>
              <option value="Transferencia">Transferencia</option>
              <option value="Débito">Débito</option>
            </select>
          </div>
          <div class="form-group">
            <label>Total (Bs):</label>
            <input type="number" name="total_bs" id="total_bs" class="form-input" step="0.01" required>
          </div>
        </div>

        <div id="camposPago"></div>

        <div class="form-row">
          <div class="form-group">
            <label>Estado:</label>
            <select name="estado" class="form-select" required>
              <option value="Pagado">Pagada</option>
              <option value="Pendiente">Pendiente</option>
            </select>
          </div>
        </div>

        <div class="modal-actions">
          <button type="button" class="btn-volver" onclick="cerrarModalVenta()">Volver</button>
          <button type="submit" class="btn-guardar">Guardar Venta</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
...
</script>

</body>
</html>


<script>
function abrirModalVenta() {
  document.getElementById('modalVenta').style.display = 'block';
}
function cerrarModalVenta() {
  document.getElementById('modalVenta').style.display = 'none';
}

function mostrarCamposPago(metodo) {
  const container = document.getElementById('camposPago');
  container.innerHTML = '';

  if (metodo === 'efectivo_usd') {
    container.innerHTML = `
      <div class="form-group">
        <label>Monto en $:</label>
        <input type="number" step="0.01" class="form-input" oninput="convertirUSD(this.value)">
      </div>
      <small class="tasa-info">Tasa del día: Bs <?= number_format($tasa_dia, 2) ?> por $1</small>
    `;
  }

  if (metodo === 'pago_movil') {
    container.innerHTML = `
      <div class="form-row">
        <div class="form-group">
          <label>Referencia:</label>
          <input type="text" class="form-input" required>
        </div>
        <div class="form-group">
          <label>Teléfono:</label>
          <input type="text" class="form-input" required>
        </div>
      </div>
    `;
  }

  if (metodo === 'transferencia') {
    container.innerHTML = `
      <div class="form-row">
        <div class="form-group">
          <label>N° Transacción:</label>
          <input type="text" class="form-input" required>
        </div>
        <div class="form-group">
          <label>Banco:</label>
          <input type="text" class="form-input" required>
        </div>
      </div>
    `;
  }

  if (metodo === 'debito') {
    container.innerHTML = `
      <div class="form-row">
        <div class="form-group">
          <label>Últimos 4 dígitos:</label>
          <input type="text" maxlength="4" class="form-input" required>
        </div>
        <div class="form-group">
          <label>Banco Emisor:</label>
          <input type="text" class="form-input" required>
        </div>
      </div>
    `;
  }
}

function convertirUSD(valor) {
  const tasa = <?= $tasa_dia ?>;
  const bs = parseFloat(valor) * tasa;
  document.getElementById('total_bs').value = bs.toFixed(2);
}
</script>

</body>
</html>