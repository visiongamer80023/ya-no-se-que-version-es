<?php
declare(strict_types=1);

/*
 * Temporalmente muestra errores para detectar cualquier problema.
 * Cuando todo funcione puedes cambiar display_errors a 0.
 */
ini_set("display_errors", "1");
error_reporting(E_ALL);

require __DIR__ . "/conexion.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

$usuario = trim((string) ($_POST["usuario"] ?? ""));
$password = trim((string) ($_POST["password"] ?? ""));

if (!preg_match('/^[0-9]{4}$/', $usuario)) {
    exit(
        "<h3>El usuario debe contener exactamente 4 números.</h3>" .
        "<a href='index.php'>Volver</a>"
    );
}

if ($password === "") {
    exit(
        "<h3>Debes escribir una contraseña.</h3>" .
        "<a href='index.php'>Volver</a>"
    );
}

/*
 * Se usa COUNT(*) y bind_result para evitar mysqli_stmt_get_result(),
 * que puede fallar en algunas instalaciones.
 */
$comprobar = mysqli_prepare(
    $conex,
    "SELECT COUNT(*) FROM datos WHERE usuario = ?"
);

if ($comprobar === false) {
    exit(
        "<h3>Error al preparar la comprobación:</h3><pre>" .
        htmlspecialchars(mysqli_error($conex)) .
        "</pre>"
    );
}

mysqli_stmt_bind_param($comprobar, "s", $usuario);

if (!mysqli_stmt_execute($comprobar)) {
    exit(
        "<h3>Error al ejecutar la comprobación:</h3><pre>" .
        htmlspecialchars(mysqli_stmt_error($comprobar)) .
        "</pre>"
    );
}

mysqli_stmt_bind_result($comprobar, $cantidad);
mysqli_stmt_fetch($comprobar);
mysqli_stmt_close($comprobar);

if ((int) $cantidad > 0) {
    mysqli_close($conex);

    exit(
        "<h3>Ese usuario ya está registrado.</h3>" .
        "<a href='index.php'>Volver</a>"
    );
}

$fecha = date("Y-m-d");

/*
 * Las comillas invertidas protegen el nombre contraseña,
 * que contiene la letra ñ.
 */
$insertar = mysqli_prepare(
    $conex,
    "INSERT INTO datos (`usuario`, `contraseña`, `fecha`) VALUES (?, ?, ?)"
);

if ($insertar === false) {
    exit(
        "<h3>Error al preparar el registro:</h3><pre>" .
        htmlspecialchars(mysqli_error($conex)) .
        "</pre>"
    );
}

mysqli_stmt_bind_param($insertar, "sss", $usuario, $password, $fecha);

if (!mysqli_stmt_execute($insertar)) {
    $mensaje = mysqli_stmt_error($insertar);

    mysqli_stmt_close($insertar);
    mysqli_close($conex);

    exit(
        "<h3>No se pudo registrar el usuario:</h3><pre>" .
        htmlspecialchars($mensaje) .
        "</pre><a href='index.php'>Volver</a>"
    );
}

mysqli_stmt_close($insertar);
mysqli_close($conex);

header("Location: login.php?registro=correcto");
exit();