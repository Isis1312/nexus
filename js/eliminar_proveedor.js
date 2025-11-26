// Confirmación de eliminación
document.getElementById('formEliminar').addEventListener('submit', function(e) {
    const confirmacion = document.querySelector('input[name="confirmacion"]').value;
    
    // Validar que se escribió exactamente "ELIMINAR"
    if (confirmacion.toUpperCase() !== 'ELIMINAR') {
        e.preventDefault();
        alert('❌ Debe escribir exactamente "ELIMINAR" para confirmar la eliminación');
        document.querySelector('input[name="confirmacion"]').focus();
        return false;
    }
    
    // Confirmación final
    const confirmacionFinal = confirm('🚨 ¿ESTÁ ABSOLUTAMENTE SEGURO?\n\nEsta acción NO se puede deshacer.\nEl proveedor será marcado como inactivo y todos sus productos serán desactivados.');
    
    if (!confirmacionFinal) {
        e.preventDefault();
        return false;
    }
    
    // Mostrar loading
    const btnEliminar = document.getElementById('btnEliminar');
    btnEliminar.innerHTML = '⏳ Eliminando...';
    btnEliminar.disabled = true;
    
    return true;
});

// Control del botón de eliminar
document.addEventListener('DOMContentLoaded', function() {
    const btnEliminar = document.getElementById('btnEliminar');
    const inputConfirmacion = document.querySelector('input[name="confirmacion"]');
    
    // Inicialmente deshabilitado
    if (btnEliminar) {
        btnEliminar.disabled = true;
    }
    
    // Validar input en tiempo real
    if (inputConfirmacion) {
        inputConfirmacion.addEventListener('input', function() {
            const valor = this.value.toUpperCase();
            const esValido = valor === 'ELIMINAR';
            
            // Habilitar/deshabilitar botón
            if (btnEliminar) {
                btnEliminar.disabled = !esValido;
            }
            
            // Cambiar estilos visuales
            if (esValido) {
                this.style.borderColor = '#4CAF50';
                this.style.backgroundColor = 'rgba(76, 175, 80, 0.05)';
                this.style.color = '#2e7d32';
            } else {
                this.style.borderColor = '#f44336';
                this.style.backgroundColor = '#ffffff';
                this.style.color = '#334155';
            }
        });
        
        // Focus en el input
        inputConfirmacion.focus();
        
        // También validar al cargar la página por si hay valor
        inputConfirmacion.dispatchEvent(new Event('input'));
    }
    
    // Debug: Verificar que los elementos existen
    console.log('Botón eliminar:', btnEliminar);
    console.log('Input confirmación:', inputConfirmacion);
});