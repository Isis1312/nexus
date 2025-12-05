<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'conexion.php';
require_once 'menu.php';

// Inicializar sistema de permisos
require_once 'permisos.php';
$sistemaPermisos = new SistemaPermisos($_SESSION['permisos']);

// Verificar si puede ver este módulo 
if (!$sistemaPermisos->puedeVer('reportes')) {
    header('Location: inicio.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú de Reportes</title>
    <link rel="stylesheet" href="css/reportes.css">
    </head>
<body>
    
     <main class="main-content">
        
        <div class="content-wrapper">
            
            <div class="page-header">
                <h1 class="page-title">Menú de Reportes</h1>
          </div>
            
           <div class="reportes-grid"> 
                
                <a href="reporte_inventario.php" class="reporte-button">
                    Reporte inventario
                </a>

                <a href="reportes_ventas.php" class="reporte-button">
                    Reporte de ventas
                </a>

                <a href="reporte_rentabilidad.php" class="reporte-button">
                    Reporte rentabilidad
                </a>
                
                <a href="reporte4.php" class="reporte-button">
                    Reporte 4
                </a>
                
                <a href="reporte5.php" class="reporte-button">
                    Reporte 5
                </a>
                
                <a href="reporte6.php" class="reporte-button">
                    Reporte 6
                </a>

                <a href="reporte7.php" class="reporte-button">
                    Reporte 7
                </a>

                <a href="reporte8.php" class="reporte-button">
                    Reporte 8
                </a>
                
                <a href="reporte9.php" class="reporte-button">
                    Reporte 9
                </a>
                
           </div> </div>
        </div>
     </main>
</body>
</html>