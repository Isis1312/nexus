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
if (!$sistemaPermisos->puedeVer('gestion_usuario')) {
    header('Location: inicio.php');
    exit();
}

// Procesar cambio de estado de usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cambiar_estado'])) {
    try {
        $id_usuario = $_POST['id_usuario'];
        $nuevo_estado = isset($_POST['nuevo_estado']) ? 1 : 0;
        
        // Verificar que el usuario no esté desactivándose a sí mismo
        if ($id_usuario == $_SESSION['id_usuario'] && $nuevo_estado == 0) {
            $_SESSION['error'] = "No puedes desactivar tu propio usuario";
            header('Location: gestion_usuarios.php');
            exit();
        }
        
        $sql = "UPDATE usuario SET activo = ? WHERE id_usuario = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nuevo_estado, $id_usuario]);
        
        $_SESSION['mensaje'] = "Estado del usuario actualizado correctamente";
        header('Location: gestion_usuarios.php');
        exit();
        
    } catch (PDOException $e) {
        $error_estado = "Error al cambiar el estado: " . $e->getMessage();
    }
}

// Procesar actualización de permisos desde el modal
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar_permisos'])) {
    try {
        $id_rol = $_POST['id_rol'];
        
        // Obtener todos los módulos
        $sqlModulos = "SELECT id_modulo, nombre_modulo FROM modulos ORDER BY id_modulo";
        $stmtModulos = $pdo->query($sqlModulos);
        $modulos = $stmtModulos->fetchAll(PDO::FETCH_ASSOC);
        
        // Actualizar cada permiso
        foreach ($modulos as $modulo) {
            $id_modulo = $modulo['id_modulo'];
            
            // Para cada módulo, obtener los permisos específicos que aplican
            $ver = isset($_POST['permisos'][$id_modulo]['ver']) ? 1 : 0;
            $agregar = isset($_POST['permisos'][$id_modulo]['agregar']) ? 1 : 0;
            $editar = isset($_POST['permisos'][$id_modulo]['editar']) ? 1 : 0;
            $eliminar = isset($_POST['permisos'][$id_modulo]['eliminar']) ? 1 : 0;
            $cambiar_estado = isset($_POST['permisos'][$id_modulo]['cambiar_estado']) ? 1 : 0;
            
            // Actualizar en la base de datos
            $sqlUpdate = "UPDATE permisos SET 
                         ver = ?, agregar = ?, editar = ?, eliminar = ?, cambiar_estado = ?
                         WHERE id_rol = ? AND id_modulo = ?";
            $stmtUpdate = $pdo->prepare($sqlUpdate);
            $stmtUpdate->execute([$ver, $agregar, $editar, $eliminar, $cambiar_estado, $id_rol, $id_modulo]);
        }
        
        $_SESSION['mensaje'] = "Permisos actualizados correctamente";
        header('Location: gestion_usuarios.php');
        exit();
        
    } catch (PDOException $e) {
        $error_permisos = "Error al actualizar permisos: " . $e->getMessage();
    }
}

// Obtener lista de usuarios
try {
    $sql = "SELECT u.*, r.nombre_rol, r.id_rol 
            FROM usuario u 
            INNER JOIN roles r ON u.id_rol = r.id_rol 
            ORDER BY u.id_usuario";
    $stmt = $pdo->query($sql);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_usuarios = "Error al cargar los usuarios: " . $e->getMessage();
    $usuarios = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - NEXUS</title>
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/g_usuarios.css">
</head>
<body>
    <?php require_once 'menu.php'; ?>
    
    <main class="main-content">
        <div class="content-wrapper">
            <div class="page-header">
                <h1 class="page-title">Gestión de Usuarios</h1>
            </div>

            <?php if (isset($_SESSION['mensaje'])): ?>
                <div class="mensaje-exito"><?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="mensaje-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <?php if (isset($error_estado)): ?>
                <div class="mensaje-error"><?php echo $error_estado; ?></div>
            <?php endif; ?>

            <?php if (isset($error_permisos)): ?>
                <div class="mensaje-error"><?php echo $error_permisos; ?></div>
            <?php endif; ?>

            <!-- Controles superiores -->
            <div class="controls-container">
                <?php if ($sistemaPermisos->puedeAgregar('gestion_usuario')): ?>
                    <a href="agregar_usuario.php" class="btn-agregar">
                        <span>+</span>
                        Agregar nuevo usuario
                    </a>
                <?php endif; ?>
                
                <div class="search-container">
                    <input type="text" id="searchInput" class="search-input" placeholder="Buscar un usuario...">
                </div>
            </div>

            <!-- Tabla de usuarios -->
            <div class="users-table">
                <div class="table-header">
                    <h3>Usuarios de NEXUS</h3>
                </div>
                <div class="table-container">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>Nº</th>
                                <th>Usuario</th>
                                <th>Nombre completo</th>
                                <th>Contraseña</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($usuarios) > 0): ?>
                                <?php foreach ($usuarios as $index => $usuario): ?>
                                    <?php
                                    $nombreCompleto = $usuario['nombre'] . ' ' . ($usuario['apellido'] ?? '');
                                    $estado_texto = $usuario['activo'] ? 'Activo' : 'Inactivo';
                                    $esUsuarioActual = ($usuario['id_usuario'] == $_SESSION['id_usuario']);
                                    ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($usuario['usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($nombreCompleto); ?></td>
                                        <td>
                                            <div class="password-container">
                                                <span class="password-field" data-password="<?php echo htmlspecialchars($usuario['contraseña']); ?>">
                                                    ••••••••
                                                </span>
                                                <button class="btn-toggle-password" onmousedown="mostrarPassword(this)" onmouseup="ocultarPassword(this)" ontouchstart="mostrarPassword(this)" ontouchend="ocultarPassword(this)">
                                                    👁
                                                </button>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($usuario['nombre_rol']); ?></td>
                                        <td>
                                            <?php if ($sistemaPermisos->puedeCambiarEstado('gestion_usuario')): ?>
                                                <form method="POST" action="" class="toggle-form" id="formEstado<?php echo $usuario['id_usuario']; ?>">
                                                    <input type="hidden" name="cambiar_estado" value="1">
                                                    <input type="hidden" name="id_usuario" value="<?php echo $usuario['id_usuario']; ?>">
                                                    <div class="toggle-container">
                                                        <label class="toggle-switch">
                                                            <input type="checkbox" name="nuevo_estado" value="1" 
                                                                   <?php echo $usuario['activo'] ? 'checked' : ''; ?>
                                                                   <?php echo $esUsuarioActual ? 'onclick="return false;"' : ''; ?>
                                                                   onchange="confirmarCambioEstado(<?php echo $usuario['id_usuario']; ?>, <?php echo $usuario['activo'] ? 'true' : 'false'; ?>, <?php echo $esUsuarioActual ? 'true' : 'false'; ?>)">
                                                            <span class="toggle-slider"></span>
                                                        </label>
                                                        <span class="toggle-label <?php echo $esUsuarioActual ? 'current-user' : ''; ?>">
                                                            <?php echo $estado_texto; ?>
                                                            <?php if ($esUsuarioActual): ?>
                                                                <br><small>(Tú)</small>
                                                            <?php endif; ?>
                                                        </span>
                                                    </div>
                                                </form>
                                            <?php else: ?>
                                                <div class="toggle-container">
                                                    <span class="toggle-label-only <?php echo $usuario['activo'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $estado_texto; ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="acciones-container">
                                                <?php if ($sistemaPermisos->puedeEditar('gestion_usuario')): ?>
                                                    <a href="editar_usuario.php?id=<?php echo $usuario['id_usuario']; ?>" class="btn-action btn-editar">
                                                        <span>✎</span>
                                                        Editar
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($sistemaPermisos->puedeVer('gestion_usuario')): ?>
                                                    <button class="btn-action btn-detalles" onclick="mostrarModalPermisos(<?php echo $usuario['id_rol']; ?>, '<?php echo htmlspecialchars($usuario['nombre_rol']); ?>')">
                                                        <span>⚙️</span>
                                                        Permisos
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="no-users">
                                        No hay usuarios registrados en el sistema.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal de permisos -->
            <div id="modalPermisos" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Configurar Permisos del Rol</h3>
                        <span class="close-modal" onclick="cerrarModal()">&times;</span>
                    </div>
                    <form method="POST" action="" id="formPermisos">
                        <input type="hidden" name="actualizar_permisos" value="1">
                        <input type="hidden" name="id_rol" id="id_rol_modal">
                        
                        <div class="modal-body">
                            <div class="modal-scroll-content">
                                
                                <div class="modal-actions">
                                    <button type="submit" class="btn-guardar">
                                        Guardar Cambios
                                    </button>
                                    <button type="button" class="btn-cancelar" onclick="cerrarModal()">
                                        <span>✕</span>
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            // Función para mostrar/ocultar contraseña
            function mostrarPassword(boton) {
                const container = boton.parentElement;
                const passwordField = container.querySelector('.password-field');
                const passwordReal = passwordField.getAttribute('data-password');
                passwordField.textContent = passwordReal;
            }

            function ocultarPassword(boton) {
                const container = boton.parentElement;
                const passwordField = container.querySelector('.password-field');
                passwordField.textContent = '••••••••';
            }

            // Función para buscar en la tabla
            document.getElementById('searchInput').addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('#usersTable tbody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });

            // Función para confirmar cambio de estado
            function confirmarCambioEstado(idUsuario, estadoActual, esUsuarioActual) {
                if (esUsuarioActual) {
                    alert('No puedes cambiar el estado de tu propio usuario');
                    return false;
                }
                
                const nuevoEstado = !estadoActual;
                const accion = nuevoEstado ? 'activar' : 'desactivar';
                const mensaje = `¿Estás seguro de que deseas ${accion} este usuario?`;
                
                if (confirm(mensaje)) {
                    document.getElementById(`formEstado${idUsuario}`).submit();
                } else {
                    // Revertir el cambio visual del toggle
                    const checkbox = document.querySelector(`#formEstado${idUsuario} input[type="checkbox"]`);
                    checkbox.checked = estadoActual;
                    return false;
                }
            }

            // Funciones para el modal de permisos
            async function mostrarModalPermisos(idRol, nombreRol) {
                console.log("Mostrando modal para rol:", idRol, nombreRol);
                const modal = document.getElementById('modalPermisos');
                const contenido = document.getElementById('detallesContenido');
                const permisosGrid = document.getElementById('permisosGrid');
                const idRolInput = document.getElementById('id_rol_modal');
                
                // Mostrar información del rol
                contenido.innerHTML = `<h4>${nombreRol}</h4><p class="rol-description">Configura los permisos para el rol de ${nombreRol}</p>`;
                idRolInput.value = idRol;
                
                // Limpiar grid mientras carga
                permisosGrid.innerHTML = '<div class="cargando">Cargando permisos...</div>';
                
                // Cargar permisos desde el servidor
                await cargarPermisosRol(idRol);
                
                modal.style.display = 'block';
            }

            async function cargarPermisosRol(idRol) {
                try {
                    console.log("Cargando permisos para rol:", idRol);
                    const response = await fetch(`obtener_permisos.php?id_rol=${idRol}`);
                    
                    if (!response.ok) {
                        throw new Error(`Error HTTP: ${response.status}`);
                    }
                    
                    const permisos = await response.json();
                    console.log("Permisos recibidos:", permisos);
                    
                    const permisosGrid = document.getElementById('permisosGrid');
                    permisosGrid.innerHTML = '';
                    
                    if (permisos.error) {
                        permisosGrid.innerHTML = '<div class="error-carga">Error: ' + permisos.error + '</div>';
                        return;
                    }
                    
                    if (permisos.length === 0) {
                        permisosGrid.innerHTML = '<div class="error-carga">No se encontraron permisos para este rol</div>';
                        return;
                    }
                    
                    permisos.forEach(permiso => {
                        const moduloHTML = crearHTMLModulo(permiso);
                        permisosGrid.innerHTML += moduloHTML;
                    });
                    
                    // Inicializar el estado de todos los checkboxes después de cargar
                    permisos.forEach(permiso => {
                        inicializarEstadoCheckboxes(permiso.id_modulo);
                    });
                    
                } catch (error) {
                    console.error('Error al cargar permisos:', error);
                    const permisosGrid = document.getElementById('permisosGrid');
                    permisosGrid.innerHTML = '<div class="error-carga">Error al cargar los permisos: ' + error.message + '</div>';
                }
            }

            function crearHTMLModulo(permiso) {
                const id_modulo = permiso.id_modulo;
                const nombreModulo = obtenerNombreModulo(permiso.nombre_modulo);
                const permisosHTML = generarCheckboxesPermisos(permiso);
                
                return `
                    <div class="permiso-modulo" data-modulo="${id_modulo}">
                        <h4>${nombreModulo}</h4>
                        <div class="permiso-opciones">
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][ver]" ${permiso.ver ? 'checked' : ''} 
                                       onchange="manejarCambioVer(${id_modulo})">
                                Ver
                            </label>
                            ${permisosHTML}
                        </div>
                    </div>
                `;
            }

            function generarCheckboxesPermisos(permiso) {
                const id_modulo = permiso.id_modulo;
                let html = '';
                
                // Según el módulo, mostrar solo los permisos relevantes
                switch(permiso.nombre_modulo) {
                    case 'gestion_usuario':
                        html = `
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][agregar]" ${permiso.agregar ? 'checked' : ''}>
                                Agregar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][editar]" ${permiso.editar ? 'checked' : ''}>
                                Editar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][cambiar_estado]" ${permiso.cambiar_estado ? 'checked' : ''}>
                                Cambiar Estado
                            </label>
                        `;
                        break;
                        
                    case 'clientes':
                        html = `
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][agregar]" ${permiso.agregar ? 'checked' : ''}>
                                Agregar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][editar]" ${permiso.editar ? 'checked' : ''}>
                                Editar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][eliminar]" ${permiso.eliminar ? 'checked' : ''}>
                                Eliminar
                            </label>
                        `;
                        break;
                        
                  case 'inventario':
                    html = `
                        <label class="checkbox-label">
                            <input type="checkbox" name="permisos[${id_modulo}][agregar]" ${permiso.agregar ? 'checked' : ''}>
                            Agregar
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="permisos[${id_modulo}][editar]" ${permiso.editar ? 'checked' : ''}>
                            Editar
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="permisos[${id_modulo}][eliminar]" ${permiso.eliminar ? 'checked' : ''}>
                            Eliminar
                        </label>
                    `;
                    break;
                        
                    case 'proveedores':
                        html = `
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][agregar]" ${permiso.agregar ? 'checked' : ''}>
                                Agregar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][editar]" ${permiso.editar ? 'checked' : ''}>
                                Editar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][eliminar]" ${permiso.eliminar ? 'checked' : ''}>
                                Eliminar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][cambiar_estado]" ${permiso.cambiar_estado ? 'checked' : ''}>
                                Cambiar Estado
                            </label>
                        `;
                        break;
                        
                    default:
                        html = `
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][agregar]" ${permiso.agregar ? 'checked' : ''}>
                                Agregar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][editar]" ${permiso.editar ? 'checked' : ''}>
                                Editar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][eliminar]" ${permiso.eliminar ? 'checked' : ''}>
                                Eliminar
                            </label>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permisos[${id_modulo}][cambiar_estado]" ${permiso.cambiar_estado ? 'checked' : ''}>
                                Cambiar Estado
                            </label>
                        `;
                }
                
                return html;
            }

            function inicializarEstadoCheckboxes(idModulo) {
                const verCheckbox = document.querySelector(`input[name="permisos[${idModulo}][ver]"]`);
                if (verCheckbox) {
                    manejarCambioVer(idModulo);
                }
            }

            // Función para manejar cambios en el checkbox "Ver"
            function manejarCambioVer(idModulo) {
                const verCheckbox = document.querySelector(`input[name="permisos[${idModulo}][ver]"]`);
                const moduloDiv = document.querySelector(`.permiso-modulo[data-modulo="${idModulo}"]`);
                
                if (!verCheckbox || !moduloDiv) {
                    console.error(`No se encontró el módulo ${idModulo}`);
                    return;
                }
                
                const estaActivo = verCheckbox.checked;
                const otrosCheckboxes = moduloDiv.querySelectorAll('input[type="checkbox"]:not([name*="[ver]"])');
                
                console.log(`Módulo ${idModulo} - Ver: ${estaActivo}, otros checkboxes: ${otrosCheckboxes.length}`);
                
                // Habilitar/deshabilitar otros checkboxes basado en "Ver"
                otrosCheckboxes.forEach(checkbox => {
                    checkbox.disabled = !estaActivo;
                    if (!estaActivo) {
                        checkbox.checked = false;
                    }
                });
            }

            function obtenerNombreModulo(nombre) {
                const nombres = {
                    'gestion_usuario': 'Gestión de Usuarios',
                    'clientes': 'Clientes',
                    'inventario': 'Inventario',
                    'proveedores': 'Proveedores',
                    'reportes': 'Reportes',
                    'ventas': 'Ventas'
                };
                return nombres[nombre] || nombre;
            }

            function cerrarModal() {
                const modal = document.getElementById('modalPermisos');
                modal.style.display = 'none';
            }

            // Cerrar modal al hacer click fuera
            window.onclick = function(event) {
                const modal = document.getElementById('modalPermisos');
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }

            // Manejar envío del formulario de permisos
            document.getElementById('formPermisos').addEventListener('submit', function(e) {
                if (!confirm('¿Estás seguro de actualizar los permisos de este rol?')) {
                    e.preventDefault();
                } else {
                    console.log("Enviando formulario de permisos...");
                }
            });
        </script>
    </main>
</body>
</html>