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
                
                <a href="reportes/reporte_inventario.php" class="reporte-button">
                   Valor del inventario actual
                </a>

                <a href="reportes/reportes_ventas.php" class="reporte-button">
                    Ventas por período
                </a>

                <a href="reportes/reporte_rentabilidad.php" class="reporte-button">
                    Analisís de rentabilidad
                </a>
                
                <a href="reportes/reporte_cliente.php" class="reporte-button">
                    Top de clientes
                </a>
                
                <a href="reportes/reporte_utilidad_mensual.php" class="reporte-button">
                    Utilidad mensual
                </a>
                
                <a href="reportes/reporte_utilidad_anual.php" class="reporte-button">
                    Utilidad anual 
                </a>

                <a href="reportes/reporte_rotacion_inventario.php" class="reporte-button">
                    Rotación de inventario
                </a>

                <a href="reportes/reporte_margen_prod.php" class="reporte-button">
                    Margen por producto
                </a>
                
                <a href="reportes/reporte9.php" class="reporte-button">
                    Reporte 9
                </a>
                
           </div> </div>
        </div>
     </main>
</body>
</html>