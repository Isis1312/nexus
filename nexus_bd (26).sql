-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 15-12-2025 a las 14:09:08
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `nexus_bd`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_insertar_producto_desde_proveedor` (IN `p_id_producto_proveedor` INT, IN `p_cantidad_comprada` INT, IN `p_fecha_vencimiento` DATE)   BEGIN
    DECLARE v_codigo_num VARCHAR(50);
    DECLARE v_codigo_original VARCHAR(50);
    DECLARE v_nombre VARCHAR(255);
    DECLARE v_descripcion TEXT;
    DECLARE v_id_categoria INT;
    DECLARE v_id_subcategoria INT;
    DECLARE v_id_proveedor INT;
    DECLARE v_precio_compra DECIMAL(10,2);
    DECLARE v_unidad_medida VARCHAR(20);
    DECLARE v_es_perecedero TINYINT(1);
    DECLARE v_existe_producto INT;
    DECLARE v_producto_id INT;
    DECLARE v_stock_actual INT;
    DECLARE v_mensaje_error VARCHAR(500);

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    -- Verificar si existe el producto del proveedor
    SELECT COUNT(*) INTO v_existe_producto
    FROM productos_proveedor
    WHERE id_producto_proveedor = p_id_producto_proveedor;

    IF v_existe_producto = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Producto del proveedor no existe';
    END IF;

    -- Obtener datos del producto
    SELECT 
        codigo_producto,
        nombre, 
        descripcion, 
        id_categoria, 
        id_subcategoria,
        id_proveedor, 
        precio_compra, 
        unidad_medida, 
        es_perecedero
    INTO 
        v_codigo_original,
        v_nombre, 
        v_descripcion, 
        v_id_categoria, 
        v_id_subcategoria,
        v_id_proveedor, 
        v_precio_compra, 
        v_unidad_medida, 
        v_es_perecedero
    FROM productos_proveedor
    WHERE id_producto_proveedor = p_id_producto_proveedor;

    -- Extraer solo números del código (versión compatible)
    IF v_codigo_original LIKE 'PROD-%' THEN
        -- Si empieza con PROD-, extraer solo la parte numérica
        SET v_codigo_num = SUBSTRING(v_codigo_original, LOCATE('-', v_codigo_original) + 1);
    ELSE
        -- Intentar extraer números manualmente
        SET v_codigo_num = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(v_codigo_original, 
            'A', ''), 'B', ''), 'C', ''), 'D', ''), 'E', ''), 
            'F', ''), 'G', ''), 'H', ''), 'I', ''), 'J', '');
        SET v_codigo_num = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(v_codigo_num, 
            'K', ''), 'L', ''), 'M', ''), 'N', ''), 'O', ''), 
            'P', ''), 'Q', ''), 'R', ''), 'S', ''), 'T', '');
        SET v_codigo_num = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(v_codigo_num, 
            'U', ''), 'V', ''), 'W', ''), 'X', ''), 'Y', ''), 
            'Z', ''), 'a', ''), 'b', ''), 'c', ''), 'd', '');
        SET v_codigo_num = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(v_codigo_num, 
            'e', ''), 'f', ''), 'g', ''), 'h', ''), 'i', ''), 
            'j', ''), 'k', ''), 'l', ''), 'm', ''), 'n', '');
        SET v_codigo_num = REPLACE(REPLACE(REPLACE(REPLACE(v_codigo_num, 
            'o', ''), 'p', ''), 'q', ''), 'r', '');
        SET v_codigo_num = REPLACE(REPLACE(REPLACE(REPLACE(v_codigo_num, 
            's', ''), 't', ''), 'u', ''), 'v', '');
        SET v_codigo_num = REPLACE(REPLACE(REPLACE(REPLACE(v_codigo_num, 
            'w', ''), 'x', ''), 'y', ''), 'z', '');
        SET v_codigo_num = REPLACE(REPLACE(REPLACE(v_codigo_num, 
            '-', ''), '_', ''), ' ', '');
    END IF;
    
    -- Si no hay números, usar el ID del producto
    IF v_codigo_num = '' OR v_codigo_num IS NULL THEN
        SET v_codigo_num = CAST(p_id_producto_proveedor AS CHAR);
    END IF;

    -- Verificar si ya existe el producto en inventario
    SELECT COUNT(*), COALESCE(id, 0), COALESCE(cantidad, 0) 
    INTO v_existe_producto, v_producto_id, v_stock_actual
    FROM productos
    WHERE codigo = v_codigo_num
       OR id_producto_proveedor = p_id_producto_proveedor;

    -- Validar límite de stock si el producto ya existe
    IF v_existe_producto > 0 THEN
        -- Validar límite de stock (200 unidades máximo)
        IF (v_stock_actual + p_cantidad_comprada) > 200 THEN
            SET v_mensaje_error = CONCAT('No se puede exceder el límite de 200 unidades. Stock actual: ', 
                                        v_stock_actual, ', Se intenta agregar: ', p_cantidad_comprada);
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje_error;
        END IF;
        
        -- Actualizar producto existente
        UPDATE productos
        SET 
            cantidad = cantidad + p_cantidad_comprada,
            precio_costo = v_precio_compra,
            precio_venta = ROUND(v_precio_compra * 1.30, 2),
            subcategoria_id = COALESCE(v_id_subcategoria, subcategoria_id),
            fecha_vencimiento = CASE 
                WHEN p_fecha_vencimiento IS NOT NULL THEN p_fecha_vencimiento
                ELSE fecha_vencimiento
            END,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = v_producto_id;
        
    ELSE
        -- Para nuevo producto, validar límite inicial
        IF p_cantidad_comprada > 200 THEN
            SET v_mensaje_error = CONCAT('No se puede exceder el límite de 200 unidades para nuevo producto. Cantidad: ', p_cantidad_comprada);
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje_error;
        END IF;
        
        -- Insertar nuevo producto
        INSERT INTO productos (
            codigo, 
            nombre, 
            descripcion, 
            categoria_id, 
            subcategoria_id,
            proveedor_id, 
            id_producto_proveedor, 
            precio_costo, 
            precio_venta,
            fecha_vencimiento, 
            cantidad, 
            created_at, 
            updated_at, 
            estado
        ) VALUES (
            v_codigo_num, 
            v_nombre, 
            v_descripcion, 
            v_id_categoria, 
            v_id_subcategoria,
            v_id_proveedor, 
            p_id_producto_proveedor, 
            v_precio_compra,
            ROUND(v_precio_compra * 1.30, 2),
            p_fecha_vencimiento,
            p_cantidad_comprada, 
            CURRENT_TIMESTAMP, 
            CURRENT_TIMESTAMP,
            'active'
        );
    END IF;

    COMMIT;
    
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria_prod`
--

CREATE TABLE `categoria_prod` (
  `id` int(11) NOT NULL,
  `nombre_categoria` varchar(50) NOT NULL,
  `estado` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categoria_prod`
--

INSERT INTO `categoria_prod` (`id`, `nombre_categoria`, `estado`, `created_at`) VALUES
(1, 'Lácteos', 'active', '2025-11-12 15:01:55'),
(2, 'Enlatados', 'inactive', '2025-11-12 15:01:55'),
(5, 'Salsas', 'active', '2025-11-12 15:01:55'),
(7, 'Snack', 'active', '2025-11-12 15:01:55'),
(18, 'Bebidas', 'active', '2025-12-05 03:01:41');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `cedula` int(8) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `direccion` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `cedula`, `telefono`, `direccion`) VALUES
(1, 'Isis Sofia', 29604083, '04160588684', 'Av. libertador con calle 57'),
(5, 'jose pernalete', 30797057, '04122201285', 'avenida españa entre calle 6 y 7'),
(11, 'Daviana', 1111111, '2147483647', 'su casa'),
(12, 'marco', 888888, '04160588684', 'su casa');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras_proveedores`
--

CREATE TABLE `compras_proveedores` (
  `id_compra` int(11) NOT NULL,
  `fecha_compra` date NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `precio_compra_total` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `compras_proveedores`
--

INSERT INTO `compras_proveedores` (`id_compra`, `fecha_compra`, `usuario_id`, `fecha_registro`, `precio_compra_total`) VALUES
(1, '2025-11-03', 5, '2025-11-03 15:37:20', 0.00),
(2, '2025-11-03', 5, '2025-11-03 15:55:31', 0.00),
(3, '2025-11-09', 5, '2025-11-09 21:21:59', 0.00),
(4, '2025-11-12', 5, '2025-11-12 04:49:39', 0.00),
(5, '2025-11-12', 4, '2025-11-12 13:30:23', 0.00),
(6, '2025-11-12', 4, '2025-11-12 16:30:44', 0.00),
(7, '2025-11-12', 4, '2025-11-12 20:51:20', 0.00),
(8, '2025-11-12', 4, '2025-11-12 20:53:33', 0.00),
(9, '2025-11-12', 4, '2025-11-12 20:55:30', 0.00),
(10, '2025-11-12', 4, '2025-11-12 21:38:24', 0.00),
(11, '2025-11-12', 4, '2025-11-12 21:38:49', 0.00),
(12, '2025-11-14', 4, '2025-11-14 11:39:49', 0.00),
(13, '2025-11-14', 4, '2025-11-14 12:25:25', 0.00),
(14, '2025-12-03', 5, '2025-12-03 22:45:00', 0.00),
(15, '2025-12-04', 5, '2025-12-03 23:18:59', 0.00),
(16, '2025-12-04', 5, '2025-12-03 23:18:59', 0.00),
(23, '2025-12-03', 4, '2025-12-04 01:25:44', 0.00),
(29, '2025-12-04', 4, '2025-12-04 01:36:40', 0.00),
(30, '2025-12-04', 4, '2025-12-04 01:38:08', 0.00),
(31, '2025-12-04', 4, '2025-12-04 01:39:08', 0.00),
(32, '2025-12-04', 4, '2025-12-04 03:48:16', 0.00),
(33, '2025-12-04', 4, '2025-12-04 03:48:16', 0.00),
(38, '2025-12-04', 4, '2025-12-04 06:26:35', 0.00),
(39, '2025-12-04', 4, '2025-12-04 06:26:35', 0.00),
(40, '2025-12-04', 4, '2025-12-04 06:26:35', 0.00),
(41, '2025-12-04', 4, '2025-12-04 15:29:27', 0.00),
(42, '2025-12-04', 4, '2025-12-04 15:37:56', 0.00),
(43, '2025-12-04', 4, '2025-12-04 15:37:56', 0.00),
(44, '2025-12-04', 4, '2025-12-04 15:37:56', 0.00),
(45, '2025-12-04', 4, '2025-12-04 15:37:56', 0.00),
(46, '2025-12-04', 4, '2025-12-04 15:39:27', 0.00),
(47, '2025-12-04', 4, '2025-12-04 15:39:27', 0.00),
(48, '2025-12-04', 4, '2025-12-04 15:39:27', 0.00),
(49, '2025-12-04', 4, '2025-12-04 17:59:29', 0.00),
(50, '2025-12-04', 4, '2025-12-04 18:05:39', 0.00),
(51, '2025-12-08', 5, '2025-12-08 14:54:03', 0.00),
(53, '2025-12-08', 5, '2025-12-08 15:03:12', 0.00),
(54, '2025-12-08', 5, '2025-12-08 15:03:12', 0.00),
(55, '2025-12-08', 5, '2025-12-08 15:06:03', 0.00),
(56, '2025-12-08', 5, '2025-12-08 15:06:03', 0.00),
(57, '2025-12-08', 5, '2025-12-08 15:06:03', 0.00),
(58, '2025-12-08', 5, '2025-12-08 15:21:18', 0.00),
(59, '2025-12-08', 5, '2025-12-08 15:21:18', 0.00),
(60, '2025-12-08', 5, '2025-12-08 15:21:18', 0.00),
(61, '2025-12-08', 5, '2025-12-08 15:21:18', 0.00),
(62, '2025-12-08', 5, '2025-12-08 15:21:18', 0.00),
(63, '2025-12-08', 5, '2025-12-08 15:45:20', 0.00),
(64, '2025-12-08', 5, '2025-12-08 15:45:20', 0.00),
(65, '2025-12-08', 5, '2025-12-08 15:45:20', 0.00),
(66, '2025-12-09', 5, '2025-12-09 13:36:50', 0.00),
(67, '2025-12-09', 5, '2025-12-09 13:36:50', 0.00),
(68, '2025-12-09', 5, '2025-12-09 13:36:50', 0.00),
(69, '2025-12-09', 5, '2025-12-09 13:36:50', 0.00),
(70, '2025-12-09', 5, '2025-12-09 14:18:03', 0.00),
(71, '2025-12-09', 5, '2025-12-09 14:18:03', 0.00),
(72, '2025-12-09', 5, '2025-12-09 14:18:03', 0.00),
(73, '2025-12-09', 5, '2025-12-09 14:18:03', 0.00),
(74, '2025-12-09', 5, '2025-12-09 14:18:03', 0.00),
(75, '2025-12-09', 5, '2025-12-09 14:18:03', 0.00),
(76, '2025-12-09', 5, '2025-12-09 14:18:03', 0.00),
(77, '2025-12-09', 5, '2025-12-09 14:39:35', 0.00),
(78, '2025-12-09', 5, '2025-12-09 14:39:35', 0.00),
(79, '2025-12-09', 5, '2025-12-09 14:39:35', 0.00),
(80, '2025-12-09', 5, '2025-12-09 14:39:35', 0.00),
(81, '2025-12-09', 5, '2025-12-09 14:39:35', 0.00),
(82, '2025-12-09', 5, '2025-12-09 14:39:35', 0.00),
(83, '2025-12-09', 5, '2025-12-09 14:39:35', 0.00),
(84, '2025-12-09', 5, '2025-12-09 14:40:37', 0.00),
(85, '2025-12-09', 5, '2025-12-09 14:40:37', 0.00),
(86, '2025-12-09', 5, '2025-12-09 14:40:37', 0.00),
(87, '2025-12-09', 5, '2025-12-09 14:40:37', 0.00),
(88, '2025-12-09', 5, '2025-12-09 14:40:37', 0.00),
(89, '2025-12-09', 5, '2025-12-09 14:40:37', 0.00),
(90, '2025-12-09', 5, '2025-12-09 14:40:37', 0.00),
(100, '2025-12-15', 4, '2025-12-15 13:06:08', 500.00),
(101, '2025-12-15', 4, '2025-12-15 13:07:53', 500.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_venta`
--

CREATE TABLE `detalle_venta` (
  `id_detalle` int(11) NOT NULL,
  `id_venta` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `codigo_producto` varchar(50) DEFAULT NULL,
  `nombre_producto` varchar(120) DEFAULT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `precio_unitario_bs` decimal(12,2) NOT NULL,
  `precio_unitario_usd` decimal(10,2) NOT NULL,
  `subtotal_bs` decimal(12,2) NOT NULL,
  `subtotal_usd` decimal(10,2) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_venta`
--

INSERT INTO `detalle_venta` (`id_detalle`, `id_venta`, `id_producto`, `codigo_producto`, `nombre_producto`, `cantidad`, `precio_unitario_bs`, `precio_unitario_usd`, `subtotal_bs`, `subtotal_usd`, `fecha_registro`) VALUES
(9, 11, 71, '002', 'Leche Entera Pasteurizada', 2.00, 0.00, 3.25, 0.00, 6.50, '2025-12-15 13:08:15'),
(10, 12, 70, '007', 'queso manchego', 2.00, 0.00, 6.50, 0.00, 13.00, '2025-12-15 13:08:36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_compras`
--

CREATE TABLE `historial_compras` (
  `id_historial` int(11) NOT NULL,
  `id_compra` int(11) NOT NULL,
  `id_producto_proveedor` int(11) NOT NULL,
  `cantidad_empaques` int(11) NOT NULL,
  `unidades_empaque` int(11) NOT NULL,
  `total_unidades` int(11) NOT NULL,
  `precio_total` decimal(10,2) NOT NULL,
  `fecha_compra` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial_compras`
--

INSERT INTO `historial_compras` (`id_historial`, `id_compra`, `id_producto_proveedor`, `cantidad_empaques`, `unidades_empaque`, `total_unidades`, `precio_total`, `fecha_compra`, `fecha_vencimiento`, `usuario_id`, `fecha_registro`) VALUES
(1, 15, 9, 11, 10, 110, 250.00, '2025-12-04', '2026-01-03', 5, '2025-12-03 23:18:59'),
(2, 16, 11, 6, 10, 60, 520.00, '2025-12-04', '2026-01-03', 5, '2025-12-03 23:18:59'),
(7, 23, 8, 2, 10, 20, 100.00, '2025-12-03', '2026-01-08', 4, '2025-12-04 01:25:44'),
(8, 29, 8, 1, 10, 10, 50.00, '2025-12-04', '2026-01-03', 4, '2025-12-04 01:36:40'),
(9, 30, 8, 1, 100, 100, 4.00, '2025-12-04', '2026-06-04', 4, '2025-12-04 01:38:08'),
(10, 31, 8, 25, 100, 2500, 10.00, '2025-12-04', '2026-01-03', 4, '2025-12-04 01:39:08'),
(11, 32, 9, 5, 40, 200, 100.00, '2025-12-04', '2026-04-30', 4, '2025-12-04 03:48:16'),
(12, 33, 11, 5, 40, 200, 80.00, '2025-12-04', '2026-04-30', 4, '2025-12-04 03:48:16'),
(13, 38, 10, 10, 20, 200, 600.00, '2025-12-04', '2026-07-29', 4, '2025-12-04 06:26:35'),
(14, 39, 9, 8, 15, 120, 500.00, '2025-12-04', '2026-07-29', 4, '2025-12-04 06:26:35'),
(15, 40, 11, 20, 10, 200, 500.00, '2025-12-04', '2026-07-29', 4, '2025-12-04 06:26:35'),
(16, 41, 1, 10, 10, 100, 600.00, '2025-12-04', '2026-05-29', 4, '2025-12-04 15:29:27'),
(17, 42, 9, 10, 50, 500, 200.00, '2025-12-04', '2025-12-25', 4, '2025-12-04 15:37:56'),
(18, 43, 11, 10, 10, 100, 500.00, '2025-12-04', '2025-12-25', 4, '2025-12-04 15:37:56'),
(19, 44, 1, 10, 20, 200, 400.00, '2025-12-04', '2025-12-25', 4, '2025-12-04 15:37:56'),
(20, 45, 10, 10, 20, 200, 500.00, '2025-12-04', '2025-12-25', 4, '2025-12-04 15:37:56'),
(21, 46, 4, 10, 10, 100, 20.00, '2025-12-04', '2026-01-03', 4, '2025-12-04 15:39:27'),
(22, 47, 9, 10, 20, 200, 200.00, '2025-12-04', '2026-01-03', 4, '2025-12-04 15:39:27'),
(23, 48, 3, 5, 10, 50, 100.00, '2025-12-04', '2026-01-03', 4, '2025-12-04 15:39:27'),
(24, 49, 9, 20, 10, 200, 500.00, '2025-12-04', '2025-12-31', 4, '2025-12-04 17:59:29'),
(25, 50, 9, 1, 2, 2, 50.00, '2025-12-04', '2025-12-26', 4, '2025-12-04 18:05:39'),
(26, 51, 5, 1, 1, 1, 1.00, '2025-12-08', '2026-01-07', 5, '2025-12-08 14:54:03'),
(28, 53, 5, 1, 1, 1, 1.00, '2025-12-08', '2026-01-07', 5, '2025-12-08 15:03:12'),
(29, 54, 1, 1, 1, 1, 1.00, '2025-12-08', '2026-01-07', 5, '2025-12-08 15:03:12'),
(30, 55, 6, 1, 1, 1, 1.00, '2025-12-08', '2026-01-07', 5, '2025-12-08 15:06:03'),
(31, 56, 8, 1, 1, 1, 1.00, '2025-12-08', '2026-01-07', 5, '2025-12-08 15:06:03'),
(32, 57, 7, 1, 1, 1, 1.00, '2025-12-08', '2026-01-07', 5, '2025-12-08 15:06:03'),
(33, 58, 5, 1, 1, 1, 1.00, '2025-12-08', '2026-01-07', 5, '2025-12-08 15:21:18'),
(34, 59, 4, 1, 1, 1, 1.00, '2025-12-08', '2026-01-07', 5, '2025-12-08 15:21:18'),
(35, 60, 6, 1, 1, 1, 1.00, '2025-12-08', '2026-01-07', 5, '2025-12-08 15:21:18'),
(36, 61, 2, 1, 1, 1, 1.00, '2025-12-08', '2026-01-07', 5, '2025-12-08 15:21:18'),
(37, 62, 1, 1, 1, 1, 11.00, '2025-12-08', '2026-01-07', 5, '2025-12-08 15:21:18'),
(38, 63, 5, 1, 1, 1, 14.00, '2025-12-08', '2026-01-31', 5, '2025-12-08 15:45:20'),
(39, 64, 6, 1, 1, 1, 1.00, '2025-12-08', '2026-01-31', 5, '2025-12-08 15:45:20'),
(40, 65, 1, 1, 1, 1, 1.00, '2025-12-08', '2026-01-31', 5, '2025-12-08 15:45:20'),
(41, 66, 5, 1, 1, 1, 1.00, '2025-12-09', '2026-02-28', 5, '2025-12-09 13:36:50'),
(42, 67, 1, 1, 1, 1, 1.00, '2025-12-09', '2026-02-28', 5, '2025-12-09 13:36:50'),
(43, 68, 4, 44, 2, 88, 12.00, '2025-12-09', '2026-02-28', 5, '2025-12-09 13:36:50'),
(44, 69, 6, 1, 1, 1, 1.00, '2025-12-09', '2026-02-28', 5, '2025-12-09 13:36:50'),
(45, 70, 5, 1, 1, 1, 1.00, '2025-12-09', '2026-04-08', 5, '2025-12-09 14:18:03'),
(46, 71, 4, 1, 1, 1, 1.00, '2025-12-09', '2026-04-08', 5, '2025-12-09 14:18:03'),
(47, 72, 8, 1, 1, 1, 1.00, '2025-12-09', '2026-04-08', 5, '2025-12-09 14:18:03'),
(48, 73, 6, 1, 1, 1, 1.00, '2025-12-09', '2026-04-08', 5, '2025-12-09 14:18:03'),
(49, 74, 1, 1, 1, 1, 1.00, '2025-12-09', '2026-04-08', 5, '2025-12-09 14:18:03'),
(50, 75, 2, 1, 1, 1, 1.00, '2025-12-09', '2026-04-08', 5, '2025-12-09 14:18:03'),
(51, 76, 7, 1, 1, 1, 1.00, '2025-12-09', '2026-04-08', 5, '2025-12-09 14:18:03'),
(52, 77, 5, 1, 1, 1, 1.00, '2025-12-09', '2026-02-08', 5, '2025-12-09 14:39:35'),
(53, 78, 1, 1, 1, 1, 1.00, '2025-12-09', '2026-02-08', 5, '2025-12-09 14:39:35'),
(54, 79, 4, 1, 1, 1, 1.00, '2025-12-09', '2026-02-08', 5, '2025-12-09 14:39:35'),
(55, 80, 6, 1, 1, 1, 1.00, '2025-12-09', '2026-02-08', 5, '2025-12-09 14:39:35'),
(56, 81, 8, 1, 1, 1, 1.00, '2025-12-09', '2026-02-08', 5, '2025-12-09 14:39:35'),
(57, 82, 2, 1, 1, 1, 1.00, '2025-12-09', '2026-02-08', 5, '2025-12-09 14:39:35'),
(58, 83, 7, 1, 1, 1, 1.00, '2025-12-09', '2026-02-08', 5, '2025-12-09 14:39:35'),
(59, 84, 5, 1, 1, 1, 1.00, '2025-12-09', '2026-01-08', 5, '2025-12-09 14:40:37'),
(60, 85, 1, 1, 1, 1, 1.00, '2025-12-09', '2026-01-08', 5, '2025-12-09 14:40:37'),
(61, 86, 6, 1, 1, 1, 1.00, '2025-12-09', '2026-01-08', 5, '2025-12-09 14:40:37'),
(62, 87, 4, 1, 1, 1, 1.00, '2025-12-09', '2026-01-08', 5, '2025-12-09 14:40:37'),
(63, 88, 8, 1, 1, 1, 1.00, '2025-12-09', '2026-01-08', 5, '2025-12-09 14:40:37'),
(64, 89, 2, 1, 1, 1, 1.00, '2025-12-09', '2026-01-08', 5, '2025-12-09 14:40:37'),
(65, 90, 7, 1, 1, 1, 1.00, '2025-12-09', '2026-01-08', 5, '2025-12-09 14:40:37'),
(71, 100, 8, 10, 20, 200, 500.00, '0000-00-00', '2026-01-14', 4, '2025-12-15 13:06:08'),
(72, 101, 1, 20, 10, 200, 500.00, '0000-00-00', '2026-01-14', 4, '2025-12-15 13:07:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modulos`
--

CREATE TABLE `modulos` (
  `id_modulo` int(11) NOT NULL,
  `nombre_modulo` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `modulos`
--

INSERT INTO `modulos` (`id_modulo`, `nombre_modulo`) VALUES
(5, 'clientes'),
(1, 'gestion_usuario'),
(3, 'Inventario'),
(2, 'proveedores'),
(4, 'reportes'),
(6, 'ventas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `permisos`
--

CREATE TABLE `permisos` (
  `id_rol` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `id_modulo` int(11) NOT NULL,
  `agregar` tinyint(1) DEFAULT 0,
  `editar` tinyint(1) DEFAULT 0,
  `eliminar` tinyint(1) DEFAULT 0,
  `cambiar_estado` tinyint(1) DEFAULT 0,
  `ver` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `permisos`
--

INSERT INTO `permisos` (`id_rol`, `id_usuario`, `id_modulo`, `agregar`, `editar`, `eliminar`, `cambiar_estado`, `ver`) VALUES
(1, NULL, 1, 1, 1, 0, 1, 1),
(1, NULL, 2, 1, 1, 1, 1, 1),
(1, NULL, 3, 1, 1, 1, 0, 1),
(1, NULL, 4, 1, 1, 1, 0, 1),
(1, NULL, 5, 1, 1, 1, 0, 1),
(1, NULL, 6, 1, 1, 1, 0, 1),
(2, NULL, 1, 0, 0, 0, 0, 0),
(2, NULL, 2, 1, 1, 1, 1, 1),
(2, NULL, 3, 1, 1, 1, 1, 1),
(2, NULL, 4, 1, 1, 1, 1, 1),
(2, NULL, 5, 1, 1, 1, 0, 1),
(2, NULL, 6, 1, 1, 1, 1, 1),
(3, NULL, 1, 0, 0, 0, 0, 0),
(3, NULL, 2, 0, 0, 0, 0, 0),
(3, NULL, 3, 0, 0, 0, 0, 1),
(3, NULL, 4, 1, 1, 1, 1, 1),
(3, NULL, 5, 1, 1, 1, 0, 1),
(3, NULL, 6, 1, 1, 1, 0, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `categoria_id` int(11) NOT NULL,
  `subcategoria_id` int(11) DEFAULT NULL,
  `proveedor_id` int(11) NOT NULL,
  `id_producto_proveedor` int(11) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `precio_costo` decimal(10,2) NOT NULL,
  `precio_venta` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estado` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `codigo`, `nombre`, `descripcion`, `categoria_id`, `subcategoria_id`, `proveedor_id`, `id_producto_proveedor`, `fecha_vencimiento`, `cantidad`, `precio_costo`, `precio_venta`, `created_at`, `updated_at`, `estado`) VALUES
(66, '003', 'Mantequilla con Sal', NULL, 1, NULL, 1, 4, '2026-01-08', 2, 0.20, 0.26, '2025-12-09 14:39:35', '2025-12-09 14:40:37', 'active'),
(67, '006', 'natilla', NULL, 1, NULL, 1, 6, '2026-01-08', 2, 1.50, 1.95, '2025-12-09 14:39:35', '2025-12-09 14:40:37', 'active'),
(68, '008', 'Queso', NULL, 1, 2, 2, 8, '2026-01-14', 202, 2.50, 3.25, '2025-12-09 14:39:35', '2025-12-15 13:06:08', 'active'),
(69, '004', 'Queso Blanco Fresco', NULL, 1, 2, 1, 2, '2026-01-08', 2, 2.50, 3.25, '2025-12-09 14:39:35', '2025-12-09 14:40:37', 'active'),
(70, '007', 'queso manchego', NULL, 1, 2, 1, 7, '2026-01-08', 0, 5.00, 6.50, '2025-12-09 14:39:35', '2025-12-15 13:08:36', 'active'),
(71, '002', 'Leche Entera Pasteurizada', '', 1, 1, 1, 1, '2026-01-14', 198, 2.50, 3.25, '2025-12-15 13:07:53', '2025-12-15 13:08:15', 'active');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos_proveedor`
--

CREATE TABLE `productos_proveedor` (
  `id_producto_proveedor` int(11) NOT NULL,
  `codigo_producto` varchar(50) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_categoria` int(11) NOT NULL,
  `id_subcategoria` int(11) DEFAULT NULL,
  `id_proveedor` int(11) NOT NULL,
  `precio_compra` decimal(10,2) NOT NULL,
  `unidad_medida` varchar(20) DEFAULT 'unidad',
  `fecha_compra` date DEFAULT NULL,
  `es_perecedero` tinyint(1) DEFAULT 0,
  `registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos_proveedor`
--

INSERT INTO `productos_proveedor` (`id_producto_proveedor`, `codigo_producto`, `nombre`, `descripcion`, `id_categoria`, `id_subcategoria`, `id_proveedor`, `precio_compra`, `unidad_medida`, `fecha_compra`, `es_perecedero`, `registro`, `actualizacion`) VALUES
(1, '002', 'Leche Entera Pasteurizada', NULL, 1, 1, 1, 2.50, 'litro', '2025-12-15', 1, '2025-10-29 21:55:16', '2025-12-15 13:07:53'),
(2, '004', 'Queso Blanco Fresco', NULL, 1, 2, 1, 2.50, 'kilo', NULL, 1, '2025-10-29 21:55:16', '2025-12-09 14:11:30'),
(3, '005', 'Yogurt Natural', NULL, 3, 3, 1, 2.00, 'litro', '2025-12-04', 1, '2025-10-29 21:55:16', '2025-12-09 14:11:30'),
(4, '003', 'Mantequilla con Sal', NULL, 1, NULL, 1, 0.20, 'unidad', '2025-12-04', 1, '2025-10-29 21:55:16', '2025-12-09 14:11:30'),
(5, '001', 'Crema de Leche', NULL, 1, 1, 1, 0.95, 'litro', '2025-12-03', 1, '2025-10-29 21:55:16', '2025-12-09 14:11:30'),
(6, '006', 'natilla', NULL, 1, NULL, 1, 1.50, 'paquete', NULL, 1, '2025-11-03 14:55:54', '2025-12-09 14:11:30'),
(7, '007', 'queso manchego', NULL, 1, 2, 1, 5.00, 'kilo', NULL, 1, '2025-11-03 15:02:03', '2025-12-09 14:11:30'),
(8, '008', 'Queso', NULL, 1, 2, 2, 2.50, 'kilo', '2025-12-15', 1, '2025-11-12 13:42:00', '2025-12-15 13:06:08'),
(9, '009', 'Choclate con Leche', NULL, 6, NULL, 3, 25.00, 'unidad', '2025-12-04', 1, '2025-11-12 20:27:03', '2025-12-09 14:11:30'),
(10, '010', 'Samba', NULL, 6, NULL, 3, 2.50, 'unidad', '2025-12-04', 1, '2025-11-12 21:36:37', '2025-12-09 14:11:30'),
(11, '011', 'Cocosette', NULL, 6, NULL, 3, 5.00, 'unidad', '2025-12-04', 1, '2025-11-12 21:37:50', '2025-12-09 14:11:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id_proveedor` int(11) NOT NULL,
  `nombres` varchar(255) NOT NULL,
  `nombre_comercial` varchar(255) NOT NULL,
  `rif` varchar(20) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id_proveedor`, `nombres`, `nombre_comercial`, `rif`, `telefono`, `email`, `direccion`, `estado`, `registro`, `actualizacion`) VALUES
(1, 'jose andres pernalete', 'lacteos vaquita.C.A', 'J-307970578', '04122201285', 'jose00pg2@gmail.com', 'avenida españa entre calle 6 y 7', 'activo', '2022-10-19 19:39:05', '2025-11-03 03:37:45'),
(2, 'Juan Perez', 'Lacteos Los Andes', 'J-123456789', '1234-5678', 'contacto@economia.com', 'Av. Principal #123', 'activo', '2025-10-30 00:16:41', '2025-11-03 03:37:54'),
(3, 'juan jose rodriguez rivero', 'savoy', 'J-307970566', '04122201285', 'pernaletegimenezjose@gmail.com', 'avenida españa entre calle 6 y 7', 'activo', '2025-11-03 03:11:38', '2025-11-03 03:37:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre_rol` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre_rol`) VALUES
(1, 'administrador'),
(3, 'asistente'),
(2, 'superusuario');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `subcategorias`
--

CREATE TABLE `subcategorias` (
  `id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `nombre_subcategoria` varchar(50) NOT NULL,
  `estado` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `subcategorias`
--

INSERT INTO `subcategorias` (`id`, `categoria_id`, `nombre_subcategoria`, `estado`, `created_at`) VALUES
(1, 1, 'Leche', 'active', '2025-11-12 15:01:55'),
(2, 1, 'Quesos', 'active', '2025-11-12 15:01:55'),
(3, 1, 'Yogurt', 'active', '2025-11-12 15:01:55'),
(10, 18, 'Gaseosa', 'active', '2025-12-05 03:01:59');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `contraseña` varchar(255) NOT NULL,
  `id_rol` int(11) NOT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `usuario`, `nombre`, `apellido`, `contraseña`, `id_rol`, `activo`) VALUES
(4, 'isis01', 'Isis Sofia', 'Cedeño Bastidas', '1234', 1, 1),
(5, 'jose02', 'Jose', 'Pernalete', '1234', 1, 1),
(8, 'arturito01', 'Arturito', 'Riverito', '1234', 2, 1),
(9, 'daviana01', 'Daviana', 'Amaro', '1234', 3, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id_venta` int(11) NOT NULL,
  `cliente` varchar(100) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `total_bs` decimal(12,2) DEFAULT 0.00,
  `id_cliente` int(11) DEFAULT NULL,
  `total_usd` decimal(12,2) DEFAULT 0.00,
  `tasa_usd` decimal(12,4) DEFAULT 0.0000,
  `nro_factura` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id_venta`, `cliente`, `fecha`, `metodo_pago`, `total_bs`, `id_cliente`, `total_usd`, `tasa_usd`, `nro_factura`) VALUES
(9, 'Isis Sofia', '2025-12-05', 'Efectivo', 1349.12, 1, 5.36, 251.8900, 'FAC-004538'),
(10, 'Isis Sofia', '2025-12-15', 'Pago Móvil', 2079.67, 1, 7.68, 270.7900, 'FAC-004539'),
(11, 'Isis Sofia', '2025-12-15', 'Pago Móvil', 1760.14, 1, 6.50, 270.7900, 'FAC-004540'),
(12, 'Isis Sofia', '2025-12-15', 'Efectivo', 3625.88, 1, 13.39, 270.7900, 'FAC-004541');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_resumen_compras`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_resumen_compras` (
`id_historial` int(11)
,`id_compra` int(11)
,`id_producto_proveedor` int(11)
,`cantidad_empaques` int(11)
,`unidades_empaque` int(11)
,`total_unidades` int(11)
,`precio_total` decimal(10,2)
,`fecha_compra` date
,`fecha_vencimiento` date
,`usuario_id` int(11)
,`fecha_registro` timestamp
,`producto_nombre` varchar(255)
,`codigo_producto` varchar(50)
,`proveedor_nombre` varchar(255)
,`usuario_nombre` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_resumen_compras`
--
DROP TABLE IF EXISTS `vista_resumen_compras`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_resumen_compras`  AS SELECT `hc`.`id_historial` AS `id_historial`, `hc`.`id_compra` AS `id_compra`, `hc`.`id_producto_proveedor` AS `id_producto_proveedor`, `hc`.`cantidad_empaques` AS `cantidad_empaques`, `hc`.`unidades_empaque` AS `unidades_empaque`, `hc`.`total_unidades` AS `total_unidades`, `hc`.`precio_total` AS `precio_total`, `hc`.`fecha_compra` AS `fecha_compra`, `hc`.`fecha_vencimiento` AS `fecha_vencimiento`, `hc`.`usuario_id` AS `usuario_id`, `hc`.`fecha_registro` AS `fecha_registro`, `pp`.`nombre` AS `producto_nombre`, `pp`.`codigo_producto` AS `codigo_producto`, `p`.`nombre_comercial` AS `proveedor_nombre`, `u`.`nombre` AS `usuario_nombre` FROM (((`historial_compras` `hc` join `productos_proveedor` `pp` on(`hc`.`id_producto_proveedor` = `pp`.`id_producto_proveedor`)) join `proveedores` `p` on(`pp`.`id_proveedor` = `p`.`id_proveedor`)) join `usuario` `u` on(`hc`.`usuario_id` = `u`.`id_usuario`)) ORDER BY `hc`.`fecha_registro` DESC ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `categoria_prod`
--
ALTER TABLE `categoria_prod`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre_categoria`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula` (`cedula`);

--
-- Indices de la tabla `compras_proveedores`
--
ALTER TABLE `compras_proveedores`
  ADD PRIMARY KEY (`id_compra`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_compras_fecha` (`fecha_compra`),
  ADD KEY `idx_compras_usuario` (`usuario_id`);

--
-- Indices de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_venta` (`id_venta`),
  ADD KEY `id_producto` (`id_producto`),
  ADD KEY `idx_detalle_venta` (`id_venta`);

--
-- Indices de la tabla `historial_compras`
--
ALTER TABLE `historial_compras`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `id_compra` (`id_compra`),
  ADD KEY `id_producto_proveedor` (`id_producto_proveedor`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `modulos`
--
ALTER TABLE `modulos`
  ADD PRIMARY KEY (`id_modulo`),
  ADD UNIQUE KEY `nombre_modulo` (`nombre_modulo`);

--
-- Indices de la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD UNIQUE KEY `unique_rol_modulo` (`id_rol`,`id_modulo`),
  ADD UNIQUE KEY `unique_usuario_modulo` (`id_usuario`,`id_modulo`),
  ADD KEY `id_modulo` (`id_modulo`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `categoria_id` (`categoria_id`),
  ADD KEY `subcategoria_id` (`subcategoria_id`),
  ADD KEY `proveedor_id` (`proveedor_id`),
  ADD KEY `id_producto_proveedor` (`id_producto_proveedor`),
  ADD KEY `idx_productos_codigo` (`codigo`);

--
-- Indices de la tabla `productos_proveedor`
--
ALTER TABLE `productos_proveedor`
  ADD PRIMARY KEY (`id_producto_proveedor`),
  ADD UNIQUE KEY `codigo_proveedor_unique` (`codigo_producto`,`id_proveedor`),
  ADD KEY `id_categoria` (`id_categoria`),
  ADD KEY `id_proveedor` (`id_proveedor`),
  ADD KEY `idx_pp_codigo` (`codigo_producto`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id_proveedor`),
  ADD UNIQUE KEY `rif` (`rif`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre_rol` (`nombre_rol`);

--
-- Indices de la tabla `subcategorias`
--
ALTER TABLE `subcategorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categoria_id` (`categoria_id`,`nombre_subcategoria`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `id_rol` (`id_rol`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id_venta`),
  ADD KEY `fk_venta_cliente` (`id_cliente`),
  ADD KEY `idx_ventas_fecha` (`fecha`),
  ADD KEY `idx_ventas_nro_factura` (`nro_factura`),
  ADD KEY `idx_ventas_cliente` (`id_cliente`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `categoria_prod`
--
ALTER TABLE `categoria_prod`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `compras_proveedores`
--
ALTER TABLE `compras_proveedores`
  MODIFY `id_compra` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `historial_compras`
--
ALTER TABLE `historial_compras`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT de la tabla `modulos`
--
ALTER TABLE `modulos`
  MODIFY `id_modulo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT de la tabla `productos_proveedor`
--
ALTER TABLE `productos_proveedor`
  MODIFY `id_producto_proveedor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id_proveedor` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `subcategorias`
--
ALTER TABLE `subcategorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id_venta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `compras_proveedores`
--
ALTER TABLE `compras_proveedores`
  ADD CONSTRAINT `compras_proveedores_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD CONSTRAINT `detalle_venta_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`),
  ADD CONSTRAINT `detalle_venta_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `historial_compras`
--
ALTER TABLE `historial_compras`
  ADD CONSTRAINT `historial_compras_ibfk_1` FOREIGN KEY (`id_compra`) REFERENCES `compras_proveedores` (`id_compra`),
  ADD CONSTRAINT `historial_compras_ibfk_2` FOREIGN KEY (`id_producto_proveedor`) REFERENCES `productos_proveedor` (`id_producto_proveedor`),
  ADD CONSTRAINT `historial_compras_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `permisos`
--
ALTER TABLE `permisos`
  ADD CONSTRAINT `permisos_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`),
  ADD CONSTRAINT `permisos_ibfk_2` FOREIGN KEY (`id_modulo`) REFERENCES `modulos` (`id_modulo`);

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `fk_productos_producto_proveedor` FOREIGN KEY (`id_producto_proveedor`) REFERENCES `productos_proveedor` (`id_producto_proveedor`),
  ADD CONSTRAINT `productos_ibfk_2` FOREIGN KEY (`subcategoria_id`) REFERENCES `subcategorias` (`id`),
  ADD CONSTRAINT `productos_ibfk_4` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id_proveedor`);

--
-- Filtros para la tabla `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `roles_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `usuario` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `subcategorias`
--
ALTER TABLE `subcategorias`
  ADD CONSTRAINT `subcategorias_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categoria_prod` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `fk_venta_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
