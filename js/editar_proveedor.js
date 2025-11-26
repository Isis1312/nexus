// Validación de RIF en tiempo real
document.getElementById('rif').addEventListener('input', function(e) {
    let value = e.target.value.toUpperCase();
    // Permitir solo letras, números y guiones
    value = value.replace(/[^A-Z0-9-]/g, '');
    e.target.value = value;
    
    // Validar formato básico
    if (value.length > 0 && !/^[JVGEP]-\d{1,9}$/.test(value)) {
        this.style.borderColor = '#f44336';
    } else {
        this.style.borderColor = '';
    }
});

// Validación de teléfono
document.getElementById('telefono').addEventListener('input', function(e) {
    let value = e.target.value;
    // Permitir solo números, espacios, guiones y paréntesis
    value = value.replace(/[^0-9\s\-()]/g, '');
    e.target.value = value;
});

// Validación de email
document.getElementById('email').addEventListener('blur', function(e) {
    const email = e.target.value;
    if (email && !isValidEmail(email)) {
        this.style.borderColor = '#f44336';
    } else {
        this.style.borderColor = '';
    }
});

function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Validación antes de enviar
document.getElementById('formProveedor').addEventListener('submit', function(e) {
    const rif = document.getElementById('rif').value;
    const email = document.getElementById('email').value;
    
    // Validar formato de RIF
    if (!/^[JVGEP]-\d{9}$/.test(rif)) {
        e.preventDefault();
        alert('El RIF debe tener el formato correcto: J-123456789');
        document.getElementById('rif').focus();
        return;
    }
    
    // Validar email si se proporciona
    if (email && !isValidEmail(email)) {
        e.preventDefault();
        alert('Por favor ingrese un correo electrónico válido');
        document.getElementById('email').focus();
        return;
    }
    
    // Mostrar loading
    const btnGuardar = document.getElementById('btnGuardar');
    btnGuardar.innerHTML = '⏳ Actualizando...';
    btnGuardar.disabled = true;
});

// Contador de caracteres para textarea
document.getElementById('direccion').addEventListener('input', function(e) {
    const maxLength = 500;
    const currentLength = e.target.value.length;
    const counter = document.getElementById('contadorDireccion') || crearContador();
    
    counter.textContent = `${currentLength}/${maxLength} caracteres`;
    
    if (currentLength > maxLength * 0.8) {
        counter.style.color = '#ff9800';
    } else {
        counter.style.color = '#666';
    }
});

function crearContador() {
    const contador = document.createElement('small');
    contador.id = 'contadorDireccion';
    contador.className = 'form-text';
    contador.style.display = 'block';
    contador.style.textAlign = 'right';
    contador.style.marginTop = '5px';
    document.getElementById('direccion').parentNode.appendChild(contador);
    return contador;
}

// Inicializar contador
document.addEventListener('DOMContentLoaded', function() {
    crearContador();
    document.getElementById('direccion').dispatchEvent(new Event('input'));
});