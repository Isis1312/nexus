<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';

// AQUÍ VAN LAS BÚSQUEDAS AJAX PRIMERO - IMPORTANTE
if (isset($_GET['buscar_cliente'])) {
    $cedula = trim($_GET['buscar_cliente']);
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE cedula = ?");
    $stmt->execute([$cedula]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cliente) {
        echo json_encode(['success' => true, 'cliente' => $cliente]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
    }
    exit();
}

if (isset($_GET['buscar_producto'])) {
    $codigo = trim($_GET['buscar_producto']);
    $stmt = $pdo->prepare("SELECT p.*, c.nombre_categoria 
                          FROM productos p 
                          LEFT JOIN categoria_prod c ON p.categoria_id = c.id
                          WHERE p.codigo = ? AND p.cantidad > 0 AND p.estado = 'active'");
    $stmt->execute([$codigo]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($producto) {
        echo json_encode(['success' => true, 'producto' => $producto]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Producto no disponible']);
    }
    exit();
}

// Solo si no es una búsqueda AJAX, continuar con el HTML
require_once 'menu.php';

// Inicializar sistema de permisos
require_once 'permisos.php';
$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);

// Verificar si puede ver este módulo 
if (!$sistemaPermisos->puedeVer('ventas')) {
    header('Location: inicio.php');
    exit();
}

// Obtener usuario actual
$usuario_id = $_SESSION['id_usuario'] ?? null;

// Obtener tasa del dólar desde JSON (CORREGIDO)
$tasas_file = 'js/tasas_cache.json';
$tasa_usd = 36.50; // Valor por defecto

if (file_exists($tasas_file)) {
    $tasas_data = json_decode(file_get_contents($tasas_file), true);
    if (isset($tasas_data['dolar'])) {
        $tasa_usd = floatval($tasas_data['dolar']);
    }
}

// Obtener último número de factura (EMPIEZA EN 000001)
try {
    $stmt = $pdo->query("SELECT nro_factura FROM ventas ORDER BY id_venta DESC LIMIT 1");
    $ultima_factura = $stmt->fetchColumn();
    
    if ($ultima_factura && preg_match('/FAC-(\d+)/', $ultima_factura, $match)) {
        $numero = intval($match[1]) + 1;
    } else {
        $numero = 1; // Empieza en 1
    }
    
    $nro_factura = 'FAC-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
} catch (Exception $e) {
    $nro_factura = 'FAC-' . str_pad(1, 6, '0', STR_PAD_LEFT);
}

// Procesar facturación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['facturar'])) {
    try {
        $pdo->beginTransaction();
        
        // Datos del cliente
        $id_cliente = $_POST['id_cliente'];
        $cliente_nombre = $_POST['cliente_nombre'];
        
        // Datos de la factura
        $fecha = date('Y-m-d');
        $metodo_pago = $_POST['metodo_pago'];
        $subtotal_bs = floatval($_POST['subtotal_bs']);
        
        // Calcular impuestos
        $iva_porcentaje = 16; // IVA fijo
        $iva_bs = $subtotal_bs * ($iva_porcentaje / 100);
        
        $igtf_porcentaje = ($metodo_pago === 'Efectivo') ? 3 : 0; // IGTF solo para efectivo
        $igtf_bs = $subtotal_bs * ($igtf_porcentaje / 100);
        
        $total_bs = $subtotal_bs + $iva_bs + $igtf_bs;
        $total_usd = $total_bs / $tasa_usd;
        
        // Insertar venta
        $stmt = $pdo->prepare("INSERT INTO ventas 
            (cliente, fecha, metodo_pago, total_bs, total_usd, tasa_usd, 
             nro_factura, id_cliente, usuario_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $cliente_nombre,
            $fecha,
            $metodo_pago,
            $total_bs,
            $total_usd,
            $tasa_usd,
            $nro_factura,
            $id_cliente,
            $usuario_id
        ]);
        
        $id_venta = $pdo->lastInsertId();
        
        // Procesar productos
        $productos = json_decode($_POST['productos_json'], true);
        
        foreach ($productos as $producto) {
            $id_producto = $producto['id'];
            $cantidad = $producto['cantidad'];
            $precio_unitario_bs = $producto['precio_venta'];
            $subtotal_bs_producto = $precio_unitario_bs * $cantidad;
            
            // Insertar detalle
            $stmt_detalle = $pdo->prepare("INSERT INTO detalle_venta 
                (id_venta, id_producto, codigo_producto, nombre_producto, cantidad, 
                 precio_unitario_bs, subtotal_bs) 
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $stmt_detalle->execute([
                $id_venta,
                $id_producto,
                $producto['codigo'],
                $producto['nombre'],
                $cantidad,
                $precio_unitario_bs,
                $subtotal_bs_producto
            ]);
            
            // Actualizar stock
            $stmt_stock = $pdo->prepare("UPDATE productos SET cantidad = cantidad - ? WHERE id = ?");
            $stmt_stock->execute([$cantidad, $id_producto]);
        }
        
        $pdo->commit();
        $_SESSION['mensaje'] = "✅ Factura #$nro_factura registrada correctamente";
        header('Location: facturas.php');
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "❌ Error al facturar: " . $e->getMessage();
        header('Location: facturacion.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Facturación</title>
    <link rel="stylesheet" href="css/facturacion.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<main class="main-content">
    <div class="content-wrapper">
        <!-- Header -->
        <div class="page-header">
            <h1 class="page-title">Nueva Factura</h1>
            <a href="facturas.php" class="btn-facturas-lista">
                📋 Ver Facturas
            </a>
        </div>

        <!-- Mostrar mensajes -->
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert alert-success" id="mensaje-exito">
                <?= $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error" id="mensaje-error">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Controles superiores -->
        <div class="controls-container-factura">
            <div class="info-factura">
                <div class="info-item">
                    <span class="info-label">N° Factura:</span>
                    <span class="info-value factura-numero"><?= $nro_factura ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Fecha:</span>
                    <span class="info-value" id="fecha-actual"><?= date('d/m/Y') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tasa $:</span>
                    <span class="info-value">Bs. <?= number_format($tasa_usd, 2, ',', '.') ?></span>
                </div>
            </div>
        </div>

        <!-- Formulario de facturación -->
        <div class="factura-container">
            <!-- Datos del cliente -->
            <div class="seccion-cliente">
                <h3>Datos del Cliente</h3>
                <div class="busqueda-cliente">
                    <div class="input-group">
                        <input type="text" id="cedula-cliente" class="form-input" 
                               placeholder="Buscar por cédula (presione Enter)" 
                               onkeypress="if(event.keyCode==13) buscarCliente()">
                        <button type="button" class="btn-buscar" onclick="buscarCliente()">
                            🔍 Buscar
                        </button>
                    </div>
                    <button type="button" class="btn-agregar-cliente" onclick="abrirModalAgregar()">
                        + Nuevo Cliente
                    </button>
                </div>
                
                <div id="datos-cliente" class="datos-cliente" style="display: none;">
                    <input type="hidden" id="id_cliente" name="id_cliente">
                    <div class="cliente-info">
                        <div class="info-row">
                            <span class="info-label">Nombre:</span>
                            <span id="cliente-nombre" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Teléfono:</span>
                            <span id="cliente-telefono" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Dirección:</span>
                            <span id="cliente-direccion" class="info-value"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Agregar productos -->
            <div class="seccion-productos">
                <h3>Productos</h3>
                <div class="busqueda-producto">
                    <div class="input-group">
                        <input type="text" id="codigo-producto" class="form-input" 
                               placeholder="Código del producto (presione Enter)" 
                               onkeypress="if(event.keyCode==13) buscarProducto()">
                        <button type="button" class="btn-buscar" onclick="buscarProducto()">
                            🔍 Agregar
                        </button>
                    </div>
                </div>
                
                <!-- Tabla de productos -->
                <div class="tabla-productos-container">
                    <table id="tabla-productos" class="tabla-productos">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario (Bs)</th>
                                <th>Subtotal (Bs)</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Los productos se agregarán aquí dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Totales -->
            <div class="seccion-totales">
                <div class="totales-container">
                    <div class="total-row">
                        <span class="total-label">Subtotal:</span>
                        <span id="subtotal-bs" class="total-value">Bs. 0,00</span>
                    </div>
                    <div class="total-row">
                        <span class="total-label">IVA (16%):</span>
                        <span id="iva-bs" class="total-value">Bs. 0,00</span>
                    </div>
                    <div id="igtf-row" class="total-row" style="display: none;">
                        <span class="total-label">IGTF (3%):</span>
                        <span id="igtf-bs" class="total-value">Bs. 0,00</span>
                    </div>
                    <div class="total-row total-final">
                        <span class="total-label">TOTAL:</span>
                        <span id="total-bs" class="total-value">Bs. 0,00</span>
                        <span id="total-usd" class="total-value-usd">$ 0,00</span>
                    </div>
                </div>
            </div>

            <!-- Método de pago -->
            <div class="seccion-pago">
                <h3>Método de Pago</h3>
                <div class="metodos-pago">
                    <label class="radio-pago">
                        <input type="radio" name="metodo_pago" value="Efectivo" onchange="mostrarIGTF()">
                        <span>Efectivo</span>
                    </label>
                    <label class="radio-pago">
                        <input type="radio" name="metodo_pago" value="Pago Móvil" onchange="mostrarIGTF()">
                        <span>Pago Móvil</span>
                    </label>
                    <label class="radio-pago">
                        <input type="radio" name="metodo_pago" value="Transferencia" onchange="mostrarIGTF()">
                        <span>Transferencia</span>
                    </label>
                    <label class="radio-pago">
                        <input type="radio" name="metodo_pago" value="Débito" onchange="mostrarIGTF()">
                        <span>Débito</span>
                    </label>
                </div>
            </div>

            <!-- Botones de acción -->
            <div class="seccion-acciones">
                <form id="form-facturar" method="POST">
                    <input type="hidden" id="id_cliente_form" name="id_cliente">
                    <input type="hidden" id="cliente_nombre_form" name="cliente_nombre">
                    <input type="hidden" id="productos_json" name="productos_json" value="[]">
                    <input type="hidden" id="metodo_pago_form" name="metodo_pago">
                    <input type="hidden" id="subtotal_bs_form" name="subtotal_bs" value="0">
                    
                    <button type="button" class="btn-cancelar" onclick="cancelarFactura()">
                        ❌ Cancelar
                    </button>
                    <button type="submit" class="btn-facturar" name="facturar" id="btn-facturar" disabled>
                        ✅ Facturar
                    </button>
                </form>
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
            <form id="formAgregarCliente">
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
let productos = [];
let tasa_usd = <?= $tasa_usd ?>;
let subtotal_bs = 0;

function buscarCliente() {
    const cedula = $('#cedula-cliente').val().trim();
    if (!cedula) {
        mostrarAlerta('error', 'Ingrese una cédula');
        return;
    }
    
    $.ajax({
        url: 'facturacion.php',
        type: 'GET',
        data: { buscar_cliente: cedula },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                const cliente = data.cliente;
                $('#id_cliente').val(cliente.id);
                $('#cliente-nombre').text(cliente.nombre);
                $('#cliente-telefono').text(cliente.telefono);
                $('#cliente-direccion').text(cliente.direccion);
                $('#datos-cliente').show();
                
                $('#id_cliente_form').val(cliente.id);
                $('#cliente_nombre_form').val(cliente.nombre);
                
                verificarEstadoFactura();
            } else {
                mostrarAlerta('error', data.message);
                $('#datos-cliente').hide();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            mostrarAlerta('error', 'Error de conexión con el servidor: ' + error);
        }
    });
}

function buscarProducto() {
    const codigo = $('#codigo-producto').val().trim();
    
    if (!codigo) {
        mostrarAlerta('error', 'Ingrese un código de producto');
        return;
    }
    
    if (!$('#id_cliente').val()) {
        mostrarAlerta('error', 'Primero debe seleccionar un cliente');
        return;
    }
    
    $.ajax({
        url: 'facturacion.php',
        type: 'GET',
        data: { buscar_producto: codigo },
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                const producto = data.producto;
                
                if (parseInt(producto.cantidad) <= 0) {
                    mostrarAlerta('error', 'Producto sin stock disponible');
                    return;
                }
                
                const productoObj = {
                    id: producto.id,
                    codigo: producto.codigo,
                    nombre: producto.nombre,
                    cantidad: 1,
                    precio_venta: parseFloat(producto.precio_venta),
                    stock: parseInt(producto.cantidad)
                };
                
                const index = productos.findIndex(p => p.id === productoObj.id);
                if (index !== -1) {
                    if (productos[index].cantidad < productoObj.stock) {
                        productos[index].cantidad++;
                    } else {
                        mostrarAlerta('error', 'Stock insuficiente');
                        return;
                    }
                } else {
                    productos.push(productoObj);
                }
                
                actualizarTablaProductos();
                $('#codigo-producto').val('');
                mostrarAlerta('success', 'Producto agregado');
                
            } else {
                mostrarAlerta('error', data.message, 10000);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            mostrarAlerta('error', 'Error de conexión con el servidor: ' + error);
        }
    });
}

function actualizarTablaProductos() {
    const tbody = $('#tabla-productos tbody');
    tbody.empty();
    
    subtotal_bs = 0;
    
    productos.forEach((producto, index) => {
        const subtotal = producto.precio_venta * producto.cantidad;
        subtotal_bs += subtotal;
        
        const row = `
            <tr id="producto-${index}">
                <td>${producto.codigo}</td>
                <td>${producto.nombre}</td>
                <td>
                    <div class="cantidad-control">
                        <button type="button" class="btn-cantidad" onclick="cambiarCantidad(${index}, -1)">-</button>
                        <input type="number" class="cantidad-input" value="${producto.cantidad}" 
                               min="1" max="${producto.stock}" 
                               onchange="actualizarCantidad(${index}, this.value)">
                        <button type="button" class="btn-cantidad" onclick="cambiarCantidad(${index}, 1)">+</button>
                    </div>
                </td>
                <td>Bs. ${producto.precio_venta.toFixed(2).replace('.', ',')}</td>
                <td>Bs. ${subtotal.toFixed(2).replace('.', ',')}</td>
                <td>
                    <button type="button" class="btn-eliminar-producto" onclick="eliminarProducto(${index})">
                        🗑️ Eliminar
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
    
    actualizarTotales();
    verificarEstadoFactura();
}

function cambiarCantidad(index, cambio) {
    const nuevoValor = productos[index].cantidad + cambio;
    
    if (nuevoValor >= 1 && nuevoValor <= productos[index].stock) {
        productos[index].cantidad = nuevoValor;
        actualizarTablaProductos();
    }
}

function actualizarCantidad(index, valor) {
    const nuevoValor = parseInt(valor);
    
    if (!isNaN(nuevoValor) && nuevoValor >= 1 && nuevoValor <= productos[index].stock) {
        productos[index].cantidad = nuevoValor;
        actualizarTablaProductos();
    } else {
        $(`#producto-${index} .cantidad-input`).val(productos[index].cantidad);
    }
}

function eliminarProducto(index) {
    productos.splice(index, 1);
    actualizarTablaProductos();
    mostrarAlerta('info', 'Producto eliminado');
}

function actualizarTotales() {
    const iva_porcentaje = 16;
    const iva_bs = subtotal_bs * (iva_porcentaje / 100);
    
    let igtf_porcentaje = 0;
    if ($('input[name="metodo_pago"]:checked').val() === 'Efectivo') {
        igtf_porcentaje = 3;
    }
    const igtf_bs = subtotal_bs * (igtf_porcentaje / 100);
    
    const total_bs = subtotal_bs + iva_bs + igtf_bs;
    const total_usd = total_bs / tasa_usd;
    
    $('#subtotal-bs').text('Bs. ' + subtotal_bs.toFixed(2).replace('.', ','));
    $('#iva-bs').text('Bs. ' + iva_bs.toFixed(2).replace('.', ','));
    $('#igtf-bs').text('Bs. ' + igtf_bs.toFixed(2).replace('.', ','));
    $('#total-bs').text('Bs. ' + total_bs.toFixed(2).replace('.', ','));
    $('#total-usd').text('$ ' + total_usd.toFixed(2).replace('.', ','));
    
    $('#subtotal_bs_form').val(subtotal_bs);
    $('#productos_json').val(JSON.stringify(productos));
}

function mostrarIGTF() {
    const metodo = $('input[name="metodo_pago"]:checked').val();
    if (metodo === 'Efectivo') {
        $('#igtf-row').show();
    } else {
        $('#igtf-row').hide();
    }
    
    $('#metodo_pago_form').val(metodo);
    
    actualizarTotales();
    verificarEstadoFactura();
}

function verificarEstadoFactura() {
    const tieneCliente = $('#id_cliente').val() !== '';
    const tieneProductos = productos.length > 0;
    const tieneMetodoPago = $('input[name="metodo_pago"]:checked').val() !== undefined;
    
    if (tieneCliente && tieneProductos && tieneMetodoPago) {
        $('#btn-facturar').prop('disabled', false);
    } else {
        $('#btn-facturar').prop('disabled', true);
    }
}

function cancelarFactura() {
    Swal.fire({
        title: '¿Cancelar factura?',
        text: 'Se perderán todos los datos ingresados',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'No, continuar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'facturacion.php';
        }
    });
}

function mostrarAlerta(icon, message, timer = 3000) {
    Swal.fire({
        icon: icon,
        title: message,
        showConfirmButton: false,
        timer: timer,
        position: 'top-end',
        toast: true,
        timerProgressBar: true
    });
}

function abrirModalAgregar() {
    $('#modalAgregar').show();
}

function cerrarModalAgregar() {
    $('#modalAgregar').hide();
    $('#formAgregarCliente')[0].reset();
}

$('#formAgregarCliente').submit(function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize();
    
    $.ajax({
        url: 'guardar_cliente.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(data) {
            if (data.success) {
                cerrarModalAgregar();
                mostrarAlerta('success', data.message);
                
                $('#cedula-cliente').val(data.cedula);
                setTimeout(function() {
                    buscarCliente();
                }, 500);
            } else {
                mostrarAlerta('error', data.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', error);
            mostrarAlerta('error', 'Error de conexión con el servidor: ' + error);
        }
    });
});

$(document).ready(function() {
    const fecha = new Date();
    const fechaFormateada = fecha.toLocaleDateString('es-ES', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
    $('#fecha-actual').text(fechaFormateada);
    
    $(window).click(function(e) {
        if (e.target.id === 'modalAgregar') {
            cerrarModalAgregar();
        }
    });
    
    setTimeout(function() {
        $('#mensaje-exito, #mensaje-error').fadeOut('slow');
    }, 5000);
    
    $('#cedula-cliente').keypress(function(e) {
        if (e.which == 13) {
            buscarCliente();
            return false;
        }
    });
    
    $('#codigo-producto').keypress(function(e) {
        if (e.which == 13) {
            buscarProducto();
            return false;
        }
    });
});
</script>
</body>
</html>