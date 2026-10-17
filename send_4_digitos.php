<?php

declare(strict_types=1);

include __DIR__ . "/conexion.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

$usuario = trim($_POST["usuario"] ?? "");
$password = trim($_POST["password"] ?? "");

if ($usuario === "" || $password === "") {
    exit("<h3>Llena todos los campos.</h3><a href='index.php'>Volver</a>");
}

// Validación obligatoria del servidor: exactamente 4 dígitos.
if (!preg_match('/^[0-9]{4}$/', $usuario)) {
    exit("<h3>El usuario debe contener exactamente 4 números, sin letras ni símbolos.</h3><a href='index.php'>Volver</a>");
}

$fecha = date("Y-m-d");

// Se usa una consulta preparada para evitar inyección SQL.
$consulta = mysqli_prepare(
    $conex,
    "INSERT INTO datos (usuario, contraseña, fecha) VALUES (?, ?, ?)"
);

if (!$consulta) {
    exit("<h3>Error al preparar la consulta: " . htmlspecialchars(mysqli_error($conex)) . "</h3>");
}

mysqli_stmt_bind_param($consulta, "sss", $usuario, $password, $fecha);
$resultado = mysqli_stmt_execute($consulta);

if (!$resultado) {
    exit("<h3>Error al registrar: " . htmlspecialchars(mysqli_stmt_error($consulta)) . "</h3><a href='index.php'>Volver</a>");
}

mysqli_stmt_close($consulta);
mysqli_close($conex);

header("Location: foro.html");
exit();