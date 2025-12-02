// Variables globales
let datosX = [];
let datosY = [];
let contadorX = 0;
let contadorY = 0;
let dispersionChart = null;

// Inicializar cuando el DOM esté cargado
document.addEventListener('DOMContentLoaded', function() {
    inicializarTablas();
    actualizarContadores();
    
    // Event listeners
    document.getElementById('btnAgregarX').addEventListener('click', () => agregarFila('x'));
    document.getElementById('btnAgregarY').addEventListener('click', () => agregarFila('y'));
    document.getElementById('btnCalcular').addEventListener('click', calcularCorrelacion);
    document.getElementById('btnLimpiar').addEventListener('click', limpiarDatos);
    document.getElementById('btnCargarReales').addEventListener('click', cargarDatosReales);
    
    // Agregar filas iniciales
    for (let i = 0; i < 3; i++) {
        agregarFila('x');
        agregarFila('y');
    }
});

function inicializarTablas() {
    // Limpiar tablas
    document.getElementById('datosX').innerHTML = '';
    document.getElementById('datosY').innerHTML = '';
    datosX = [];
    datosY = [];
    contadorX = 0;
    contadorY = 0;
}

function agregarFila(tipo) {
    const tabla = document.getElementById(`datos${tipo.toUpperCase()}`);
    const contador = tipo === 'x' ? ++contadorX : ++contadorY;
    
    const fila = document.createElement('tr');
    
    // Configurar placeholder según el tipo
    const placeholderValor = tipo === 'x' ? '0 (unidades)' : '0.00 ($)';
    const tipoInput = tipo === 'x' ? 'number' : 'number';
    const step = tipo === 'x' ? '1' : '0.01';
    
    fila.innerHTML = `
        <td>${contador}</td>
        <td>
            <input type="${tipoInput}" 
                   id="${tipo}_${contador}" 
                   class="datos-input" 
                   step="${step}" 
                   placeholder="${placeholderValor}" 
                   oninput="actualizarDato('${tipo}', ${contador})">
        </td>
        <td>
            <input type="text" 
                   id="${tipo}_desc_${contador}" 
                   class="datos-input" 
                   placeholder="Ej: Enero 2024">
        </td>
        <td>
            <button type="button" class="btn-eliminar-fila" onclick="eliminarFila('${tipo}', ${contador})">
                🗑️
            </button>
        </td>
    `;
    
    tabla.appendChild(fila);
    
    // Inicializar arrays
    if (tipo === 'x') {
        datosX[contador - 1] = 0;
    } else {
        datosY[contador - 1] = 0;
    }
    
    actualizarContadores();
}

function actualizarDato(tipo, id) {
    const input = document.getElementById(`${tipo}_${id}`);
    const valor = parseFloat(input.value) || 0;
    const index = id - 1;
    
    if (tipo === 'x') {
        datosX[index] = valor;
    } else {
        datosY[index] = valor;
    }
}

function eliminarFila(tipo, id) {
    const fila = document.getElementById(`${tipo}_${id}`).closest('tr');
    if (fila) {
        const index = id - 1;
        
        if (tipo === 'x') {
            datosX.splice(index, 1);
            contadorX--;
            // Renumerar filas
            renumerarFilas('x');
        } else {
            datosY.splice(index, 1);
            contadorY--;
            renumerarFilas('y');
        }
        
        fila.remove();
        actualizarContadores();
    }
}

function renumerarFilas(tipo) {
    const tabla = document.getElementById(`datos${tipo.toUpperCase()}`);
    const filas = tabla.querySelectorAll('tr');
    
    filas.forEach((fila, index) => {
        const celdaNumero = fila.querySelector('td:first-child');
        celdaNumero.textContent = index + 1;
        
        const inputs = fila.querySelectorAll('input');
        inputs.forEach(input => {
            const oldId = input.id;
            const newId = oldId.replace(/_(\d+)/, `_${index + 1}`);
            input.id = newId;
            
            if (input.type === 'number') {
                input.oninput = () => actualizarDato(tipo, index + 1);
            }
        });
    });
}

function actualizarContadores() {
    const totalX = datosX.filter(d => d !== 0 && d !== undefined).length;
    const totalY = datosY.filter(d => d !== 0 && d !== undefined).length;
    
    document.getElementById('totalX').textContent = totalX;
    document.getElementById('totalY').textContent = totalY;
}

function cargarDatosReales() {
    if (!datosReales || !datosReales.inventario || datosReales.inventario.length === 0) {
        alert('⚠️ No hay datos reales disponibles en el sistema.\nPor favor, ingresa datos manualmente o verifica que haya registros en la base de datos.');
        return;
    }

    // Limpiar datos existentes
    inicializarTablas();
    
    // Cargar datos reales
    const meses = datosReales.meses;
    const inventario = datosReales.inventario;
    const ventas = datosReales.ventas;
    
    const cantidad = Math.min(meses.length, inventario.length, ventas.length);
    
    if (cantidad === 0) {
        alert('⚠️ No se encontraron datos suficientes para cargar.');
        return;
    }
    
    // Mostrar mensaje informativo
    alert(`📊 Cargando ${cantidad} meses de datos reales del sistema:\n${meses.slice(0, cantidad).join(', ')}`);
    
    for (let i = 0; i < cantidad; i++) {
        agregarFila('x');
        agregarFila('y');
        
        // Asignar valores
        setTimeout(() => {
            const inputX = document.getElementById(`x_${i + 1}`);
            const inputY = document.getElementById(`y_${i + 1}`);
            const descX = document.getElementById(`x_desc_${i + 1}`);
            const descY = document.getElementById(`y_desc_${i + 1}`);
            
            if (inputX && inputY && descX && descY) {
                inputX.value = inventario[i];
                inputY.value = ventas[i];
                descX.value = `${meses[i]} (${inventario[i]} unidades)`;
                descY.value = `${meses[i]} ($${ventas[i].toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})})`;
                
                // Actualizar arrays
                datosX[i] = inventario[i];
                datosY[i] = ventas[i];
            }
        }, 10);
    }
    
    // Calcular automáticamente
    setTimeout(() => {
        actualizarContadores();
        calcularCorrelacion();
        
        // Mostrar mensaje de éxito
        const totalVentas = ventas.slice(0, cantidad).reduce((a, b) => a + b, 0);
        const totalInventario = inventario.slice(0, cantidad).reduce((a, b) => a + b, 0);
        
        console.log(`✅ Datos cargados exitosamente:\n- Meses: ${cantidad}\n- Inventario total: ${totalInventario.toLocaleString()} unidades\n- Ventas totales: $${totalVentas.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`);
    }, 100);
}

function calcularCorrelacion() {
    // Filtrar datos válidos
    const xVals = datosX.filter((d, i) => 
        d !== undefined && d !== 0 && 
        datosY[i] !== undefined && datosY[i] !== 0
    );
    
    const yVals = datosY.filter((d, i) => 
        d !== undefined && d !== 0 && 
        datosX[i] !== undefined && datosX[i] !== 0
    );
    
    if (xVals.length < 2 || yVals.length < 2) {
        alert('⚠️ Se necesitan al menos 2 pares de datos válidos para calcular la correlación\n\nPor favor, ingresa valores en ambas columnas (Inventario y Ventas) para al menos 2 filas.');
        return;
    }
    
    if (xVals.length !== yVals.length) {
        alert('⚠️ El número de datos en X e Y debe ser igual\n\nPor favor, verifica que todas las filas tengan valores en ambas columnas.');
        return;
    }
    
    // Calcular coeficiente de correlación de Pearson
    const n = xVals.length;
    
    const sumX = xVals.reduce((a, b) => a + b, 0);
    const sumY = yVals.reduce((a, b) => a + b, 0);
    const sumXY = xVals.reduce((sum, x, i) => sum + x * yVals[i], 0);
    const sumX2 = xVals.reduce((sum, x) => sum + x * x, 0);
    const sumY2 = yVals.reduce((sum, y) => sum + y * y, 0);
    
    const numerador = n * sumXY - sumX * sumY;
    const denominador = Math.sqrt((n * sumX2 - sumX * sumX) * (n * sumY2 - sumY * sumY));
    
    const r = denominador !== 0 ? numerador / denominador : 0;
    
    // Mostrar resultados
    mostrarResultados(r, n, xVals, yVals);
    generarGraficoDispersion(xVals, yVals);
}

function mostrarResultados(r, n, xVals, yVals) {
    const r2 = r * r;
    
    // Actualizar valores en la interfaz
    document.getElementById('valorR').textContent = r.toFixed(4);
    document.getElementById('valorR2').textContent = r2.toFixed(4);
    document.getElementById('totalPares').textContent = n;
    
    // Calcular estadísticas adicionales
    const promedioX = (xVals.reduce((a, b) => a + b, 0) / n).toFixed(2);
    const promedioY = (yVals.reduce((a, b) => a + b, 0) / n).toFixed(2);
    
    // Determinar interpretación
    const interpretacion = obtenerInterpretacion(r);
    const interpretacionElement = document.getElementById('interpretacion');
    interpretacionElement.textContent = interpretacion.texto;
    interpretacionElement.className = `nivel-correlacion nivel-${interpretacion.nivel}`;
    
    // Actualizar explicación con datos concretos
    const explicacionElement = document.getElementById('explicacion');
    explicacionElement.innerHTML = `
        <strong>📊 Resumen del análisis:</strong><br>
        • <strong>Pares de datos analizados:</strong> ${n}<br>
        • <strong>Inventario promedio:</strong> ${promedioX} unidades<br>
        • <strong>Ventas promedio:</strong> $${promedioY}<br>
        • <strong>Coeficiente de correlación (r):</strong> ${r.toFixed(4)}<br>
        • <strong>Coeficiente de determinación (r²):</strong> ${r2.toFixed(4)}<br><br>
        
        <strong>🔍 Interpretación del resultado:</strong><br>
        ${interpretacion.detalle}<br><br>
        
        <small>💡 <strong>Recomendación:</strong> ${interpretacion.recomendacion}</small>
    `;
    
    // Mostrar resultados
    document.getElementById('resultados').style.display = 'block';
    
    // Scroll suave a resultados
    document.getElementById('resultados').scrollIntoView({ 
        behavior: 'smooth',
        block: 'start'
    });
}

function obtenerInterpretacion(r) {
    const absR = Math.abs(r);
    
    if (absR >= 0.8) {
        return { 
            nivel: 'alta',
            texto: `Correlación ${r > 0 ? 'POSITIVA' : 'NEGATIVA'} FUERTE (r = ${r.toFixed(2)})`,
            detalle: r > 0 
                ? 'Existe una relación lineal fuerte y positiva entre inventario y ventas. Cuando el inventario aumenta, las ventas también tienden a aumentar significativamente.'
                : 'Existe una relación lineal fuerte y negativa entre inventario y ventas. Cuando el inventario aumenta, las ventas tienden a disminuir significativamente.',
            recomendacion: r > 0
                ? 'Considera aumentar el inventario estratégicamente para potenciar las ventas.'
                : 'Revisa la gestión de inventario, podría haber sobrestock afectando negativamente las ventas.'
        };
    } else if (absR >= 0.5) {
        return { 
            nivel: 'moderada',
            texto: `Correlación ${r > 0 ? 'POSITIVA' : 'NEGATIVA'} MODERADA (r = ${r.toFixed(2)})`,
            detalle: r > 0
                ? 'Existe una relación moderada entre inventario y ventas. Hay una tendencia positiva, pero otros factores también influyen.'
                : 'Existe una relación moderada e inversa. El inventario tiene algún impacto en las ventas, pero no es determinante.',
            recomendacion: 'Analiza otros factores que puedan estar influyendo en las ventas además del inventario.'
        };
    } else if (absR >= 0.3) {
        return { 
            nivel: 'baja',
            texto: `Correlación ${r > 0 ? 'POSITIVA' : 'NEGATIVA'} DÉBIL (r = ${r.toFixed(2)})`,
            detalle: 'La relación entre inventario y ventas es débil. Los cambios en inventario tienen poco efecto directo en las ventas.',
            recomendacion: 'Investiga otros factores como marketing, temporada, o competencia que puedan afectar más las ventas.'
        };
    } else if (absR > 0.1) {
        return { 
            nivel: 'baja',
            texto: `Correlación ${r > 0 ? 'POSITIVA' : 'NEGATIVA'} MUY DÉBIL (r = ${r.toFixed(2)})`,
            detalle: 'Prácticamente no hay relación lineal entre inventario y ventas. Son variables independientes.',
            recomendacion: 'El nivel de inventario no parece ser un factor clave para las ventas. Enfócate en otras estrategias comerciales.'
        };
    } else {
        return { 
            nivel: 'baja',
            texto: 'NO HAY CORRELACIÓN LINEAL SIGNIFICATIVA',
            detalle: 'No existe relación lineal discernible entre la cantidad de inventario y el monto de ventas.',
            recomendacion: 'Considera analizar la relación con otras variables o periodos de tiempo diferentes.'
        };
    }
}

function generarGraficoDispersion(xVals, yVals) {
    const ctx = document.getElementById('dispersionChart').getContext('2d');
    
    // Destruir gráfico anterior si existe
    if (dispersionChart) {
        dispersionChart.destroy();
    }
    
    // Preparar datos para el gráfico
    const datosGrafico = xVals.map((x, i) => ({ 
        x: x, 
        y: yVals[i] 
    }));
    
    // Crear nuevo gráfico
    dispersionChart = new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: [{
                label: 'Datos (Inventario vs Ventas)',
                data: datosGrafico,
                backgroundColor: '#008B8B',
                borderColor: '#006666',
                pointRadius: 6,
                pointHoverRadius: 10,
                pointBorderWidth: 2,
                pointBorderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: '📈 Relación entre Inventario y Ventas',
                    font: {
                        size: 16,
                        weight: 'bold'
                    },
                    padding: {
                        top: 10,
                        bottom: 30
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return [
                                `📦 Inventario: ${context.parsed.x.toLocaleString()} unidades`,
                                `💰 Ventas: $${context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`,
                                `📊 Par de datos: ${context.dataIndex + 1}`
                            ];
                        }
                    },
                    backgroundColor: 'rgba(0, 139, 139, 0.9)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    padding: 12,
                    cornerRadius: 6
                },
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        color: '#008B8B',
                        font: {
                            size: 12,
                            weight: 'bold'
                        },
                        padding: 20
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Inventario (unidades)',
                        color: '#008B8B',
                        font: {
                            size: 14,
                            weight: 'bold'
                        },
                        padding: {top: 10, bottom: 10}
                    },
                    grid: {
                        color: 'rgba(0, 139, 139, 0.1)'
                    },
                    ticks: {
                        color: '#666',
                        callback: function(value) {
                            return value.toLocaleString() + ' uds';
                        }
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Ventas ($ USD)',
                        color: '#008B8B',
                        font: {
                            size: 14,
                            weight: 'bold'
                        },
                        padding: {top: 10, bottom: 10}
                    },
                    grid: {
                        color: 'rgba(0, 139, 139, 0.1)'
                    },
                    ticks: {
                        color: '#666',
                        callback: function(value) {
                            return '$' + value.toLocaleString('en-US', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            });
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'nearest'
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            }
        }
    });
}

function limpiarDatos() {
    if (confirm('⚠️ ¿Está seguro de que desea limpiar todos los datos?\n\nSe eliminarán todos los valores ingresados y los resultados del análisis.')) {
        inicializarTablas();
        actualizarContadores();
        
        // Ocultar resultados
        document.getElementById('resultados').style.display = 'none';
        
        // Resetear explicación
        document.getElementById('explicacion').innerHTML = `
            <strong>El coeficiente de correlación (r) mide la relación lineal entre dos variables:</strong><br>
            • <strong>r cercano a +1:</strong> A mayor inventario, mayores ventas (correlación positiva fuerte)<br>
            • <strong>r cercano a -1:</strong> A mayor inventario, menores ventas (correlación negativa fuerte)<br>
            • <strong>r cercano a 0:</strong> No hay relación lineal entre inventario y ventas<br>
            <br>
            <small>💡 <strong>Interpretación práctica:</strong> Si r es positivo y alto, aumentar el inventario podría incrementar las ventas. Si es negativo, podría indicar sobrestock.</small>
        `;
        
        // Destruir gráfico
        if (dispersionChart) {
            dispersionChart.destroy();
            dispersionChart = null;
        }
        
        // Resetear interpretación
        document.getElementById('interpretacion').textContent = 'Ingrese datos para ver la interpretación';
        document.getElementById('interpretacion').className = 'nivel-correlacion';
        
        // Agregar filas iniciales
        for (let i = 0; i < 3; i++) {
            agregarFila('x');
            agregarFila('y');
        }
        
        // Mostrar mensaje
        console.log('✅ Datos limpiados exitosamente');
    }
}