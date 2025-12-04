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
if (!$sistemaPermisos->puedeVer('proveedores')) {
    header('Location: inicio.php');
    exit();
}
// Consultar proveedores
try {
    $sql = "SELECT * FROM proveedores ORDER BY nombre_comercial";
    $stmt = $pdo->query($sql);
    $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular estadísticas
    $total_proveedores = count($proveedores);
    $proveedores_activos = count(array_filter($proveedores, function($p) { return $p['estado'] == 'activo'; }));
} catch (PDOException $e) {
    die("Error al consultar proveedores: " . $e->getMessage());
}
 require_once 'menu.php';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Proveedores - NEXUS SYSTEM</title>
    <link rel="stylesheet" href="css/proveedores.css">
</head>
<body>
   <main class="main-content">
        <div class="content-wrapper">

            <div class="container">
                <div class="page-header">
                    <h1 class="page-title"> Gestión de Proveedores</h1>
                </div>


                <!-- Estadísticas -->
                <div class="stats-container">
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $total_proveedores; ?></span>
                        <span class="stat-label">Total Proveedores</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $proveedores_activos; ?></span>
                        <span class="stat-label">Proveedores Activos</span>
                    </div>
                </div>

                <?php if ($total_proveedores > 0): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nombre Comercial</th>
                                    <th>RIF</th>
                                    <th>Contacto</th>
                                    <th>Teléfono</th>
                                    <th>Email</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($proveedores as $row): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['nombre_comercial']); ?></strong>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($row['rif']); ?></code></td>
                                        <td><?php echo htmlspecialchars($row['nombres']); ?></td>
                                        <td>
                                            <?php if ($row['telefono']): ?>
                                                📞 <?php echo htmlspecialchars($row['telefono']); ?>
                                            <?php else: ?>
                                                <span style="color: #6c757d; font-style: italic;">No especificado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['email']): ?>
                                                ✉️ <?php echo htmlspecialchars($row['email']); ?>
                                            <?php else: ?>
                                                <span style="color: #6c757d; font-style: italic;">No especificado</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['estado'] == 'activo'): ?>
                                                <span class="estado-activo">● Activo</span>
                                            <?php else: ?>
                                                <span class="estado-inactivo">● Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="white-space: nowrap;">
                                            <?php
                                            if (isset($row['id_proveedor'])) {
                                                echo '<a href="editar_proveedor.php?id=' . $row['id_proveedor'] . '" class="btn btn-success btn-sm">✎ Editar</a>';
                                                echo '<a href="eliminar_proveedor.php?id=' . $row['id_proveedor'] . '" class="btn btn-danger btn-sm" onclick="return confirm(\'¿Estás seguro de eliminar este proveedor?\')">🗑️ Eliminar</a>';
                                            } else {
                                                echo '<span style="color: red; font-size: 0.8em;">Error: ID no encontrado</span>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <h3>📭 No hay proveedores registrados</h3>
                        <p>Comienza agregando tu primer proveedor al sistema.</p>
                    </div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <a href="agregar_proveedores.php" class="btn btn-primary">➕ Agregar proveedor</a>
                    <a href="productos_proveedores.php" class="btn btn-primary"> Ver productos</a>
                </div>
            </div>
        </div>
  </div>
</main>
</body>
</html>