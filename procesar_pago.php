<?php
declare(strict_types=1);
session_start();
require __DIR__ . "/verificar_acceso.php";

if (!isset($_SESSION["usuario"]) || $_SESSION["rol"] !== "alumno") {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: pagos.php");
    exit();
}

require __DIR__ . "/conexion.php";
mysqli_set_charset($conex, "utf8mb4");

$usuario = (int) $_SESSION["usuario"];
$facturaId = filter_input(INPUT_POST, "factura_id", FILTER_VALIDATE_INT);
$metodoPago = trim((string) ($_POST["metodo_pago"] ?? ""));

// Validaciones básicas
if (!$facturaId || !in_array($metodoPago, ['tarjeta', 'spei', 'bbva', 'oxxo'])) {
    header("Location: pagos.php?error_pago=factura_invalida");
    exit();
}

// Verificar que la factura pertenece al usuario y está pendiente
$verificarFactura = mysqli_prepare(
    $conex,
    "SELECT id, monto, descuento, estado FROM facturas WHERE id = ? AND usuario = ? AND estado = 'pendiente'"
);

if (!$verificarFactura) {
    mysqli_close($conex);
    header("Location: pagos.php?error_pago=error_servidor");
    exit();
}

mysqli_stmt_bind_param($verificarFactura, "ii", $facturaId, $usuario);
mysqli_stmt_execute($verificarFactura);
mysqli_stmt_store_result($verificarFactura);

if (mysqli_stmt_num_rows($verificarFactura) === 0) {
    mysqli_stmt_close($verificarFactura);
    mysqli_close($conex);
    header("Location: pagos.php?error_pago=factura_invalida");
    exit();
}

mysqli_stmt_bind_result($verificarFactura, $fid, $monto, $descuento, $estado);
mysqli_stmt_fetch($verificarFactura);
mysqli_stmt_close($verificarFactura);

$montoAPagar = $monto - $descuento;

mysqli_begin_transaction($conex);

try {
    // Registrar el pago
    $insertarPago = mysqli_prepare(
        $conex,
        "INSERT INTO pagos (factura_id, usuario, metodo_pago, monto_pagado, estado) VALUES (?, ?, ?, ?, 'completado')"
    );
    
    if (!$insertarPago) {
        throw new RuntimeException("Error al preparar el registro de pago");
    }
    
    mysqli_stmt_bind_param($insertarPago, "iisd", $facturaId, $usuario, $metodoPago, $montoAPagar);
    
    if (!mysqli_stmt_execute($insertarPago)) {
        throw new RuntimeException("Error al registrar el pago");
    }
    
    mysqli_stmt_close($insertarPago);
    
    // Actualizar estado de la factura
    $actualizarFactura = mysqli_prepare(
        $conex,
        "UPDATE facturas SET estado = 'pagado' WHERE id = ?"
    );
    
    if (!$actualizarFactura) {
        throw new RuntimeException("Error al actualizar la factura");
    }
    
    mysqli_stmt_bind_param($actualizarFactura, "i", $facturaId);
    
    if (!mysqli_stmt_execute($actualizarFactura)) {
        throw new RuntimeException("Error al actualizar el estado de la factura");
    }
    
    mysqli_stmt_close($actualizarFactura);
    
    mysqli_commit($conex);
    
    header("Location: pagos.php?pago_ok=1");
    exit();
    
} catch (Throwable $e) {
    mysqli_rollback($conex);
    mysqli_close($conex);
    header("Location: pagos.php?error_pago=error_servidor");
    exit();
}