// Funciones para modales
function abrirModalAgregar() {
    document.getElementById('modalAgregar').style.display = 'block';
}

function cerrarModalAgregar() {
    document.getElementById('modalAgregar').style.display = 'none';
}

function abrirModalEditar(id) {
    // Aquí deberías hacer una petición AJAX para obtener los datos del producto
    // Por ahora, solo abrimos el modal
    document.getElementById('edit_id').value = id;
    document.getElementById('modalEditar').style.display = 'block';
}

function cerrarModalEditar() {
    document.getElementById('modalEditar').style.display = 'none';
}

// Mostrar/ocultar subcategoría
function mostrarSubcategoria(categoria) {
    const subcategoriaGroup = document.getElementById('subcategoria-group');
    if (categoria === 'bebidas') {
        subcategoriaGroup.style.display = 'block';
    } else {
        subcategoriaGroup.style.display = 'none';
    }
}

// Calcular precio de venta (42% de ganancia)
function calcularPrecioVenta(precioBase) {
    if (precioBase) {
        const precioVenta = parseFloat(precioBase) * 1.42;
        document.getElementById('precio_venta').value = precioVenta.toFixed(2);
    } else {
        document.getElementById('precio_venta').value = '';
    }
}

function calcularPrecioVentaEditar(precioBase) {
    if (precioBase) {
        const precioVenta = parseFloat(precioBase) * 1.42;
        document.getElementById('edit_precio_venta').value = precioVenta.toFixed(2);
    } else {
        document.getElementById('edit_precio_venta').value = '';
    }
}

// Confirmar eliminación
function confirmarEliminar(id) {
    if (confirm('¿Estás seguro de que deseas eliminar este producto?')) {
        window.location.href = 'productos.php?eliminar=' + id;
    }
}

// Mostrar notificación
function mostrarNotificacion(mensaje, tipo = 'success') {
    const notificacion = document.getElementById('notificacion');
    notificacion.textContent = mensaje;
    notificacion.className = 'notificacion ' + tipo;
    
    setTimeout(() => {
        notificacion.className = 'notificacion';
    }, 3000);
}

// Cerrar modales al hacer clic fuera
window.onclick = function(event) {
    const modalAgregar = document.getElementById('modalAgregar');
    const modalEditar = document.getElementById('modalEditar');
    
    if (event.target === modalAgregar) {
        cerrarModalAgregar();
    }
    if (event.target === modalEditar) {
        cerrarModalEditar();
    }
}

// Verificar si hay alertas de stock bajo al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    const cantidadesBajas = document.querySelectorAll('.cantidad-baja');
    if (cantidadesBajas.length > 0) {
        mostrarNotificacion(`Hay ${cantidadesBajas.length} productos con stock bajo`, 'warning');
    }
    
    // Mostrar mensaje de éxito si existe
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        mostrarNotificacion('Producto guardado con éxito', 'success');
    }
});