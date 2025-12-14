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

// Mostrar/ocultar subcategoría (mantener por si se usa en agregar_producto)
function mostrarSubcategoria(categoria) {
    const subcategoriaGroup = document.getElementById('subcategoria-group');
    if (categoria === 'bebidas') {
        subcategoriaGroup.style.display = 'block';
    } else {
        subcategoriaGroup.style.display = 'none';
    }
}

function mostrarModalError(mensaje) {
    document.getElementById('mensajeErrorTexto').textContent = mensaje;
    document.getElementById('modalError').style.display = 'block';
}

function cerrarModalError() {
    document.getElementById('modalError').style.display = 'none';
}

// Confirmar eliminación
function confirmarEliminar(id) {
    if (confirm('¿Estás seguro de que deseas eliminar este producto?')) {
        // Asumimos que el id pasado a productos.php es el id_unico del grupo
        window.location.href = 'productos.php?eliminar=' + id; 
    }
}

// Mostrar notificación
function mostrarNotificacion(mensaje, tipo = 'success') {
    const notificacion = document.getElementById('notificacion');
    if (notificacion) {
        notificacion.textContent = mensaje;
        notificacion.className = 'notificacion ' + tipo;
        
        setTimeout(() => {
            notificacion.className = 'notificacion';
        }, 3000);
    } else {
        console.warn('Elemento de notificación no encontrado');
    }
}

// Cerrar modales al hacer clic fuera
window.onclick = function(event) {
    const modalAgregar = document.getElementById('modalAgregar');
    const modalEditar = document.getElementById('modalEditar');
    const modalStock = document.getElementById('modalStock'); // Agregado en el código de productos.php
    
    if (event.target === modalAgregar) {
        cerrarModalAgregar();
    }
    if (event.target === modalEditar) {
        cerrarModalEditar();
    }
    if (modalStock && event.target === modalStock) {
        // La función cerrarModalStock debe existir en productos.php
    }
}

// Verificar si hay alertas de stock bajo al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    const cantidadesBajas = document.querySelectorAll('.cantidad-baja');
    // Si bien la lógica de notificaciones de sesión en PHP es mejor, mantenemos el listener DOMContentLoaded 
    // por si otro código se basa en él.
});