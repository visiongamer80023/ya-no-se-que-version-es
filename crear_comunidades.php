<?php
declare(strict_types=1);

session_start();
require __DIR__ . "/verificar_acceso.php";
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: foro.php");
    exit();
}

require __DIR__ . "/conexion.php";
mysqli_set_charset($conex, "utf8mb4");

$nombre = trim((string) ($_POST["nombre"] ?? ""));
$descripcion = trim((string) ($_POST["descripcion"] ?? ""));
$creador = (string) $_SESSION["usuario"];

if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $nombre)) {
    header("Location: foro.php?error_comunidad=formato");
    exit();
}

if (strlen($descripcion) > 300) {
    header("Location: foro.php?error_comunidad=descripcion");
    exit();
}

$comprobar = mysqli_prepare(
    $conex,
    "SELECT COUNT(*) FROM comunidades WHERE nombre = ?"
);

mysqli_stmt_bind_param($comprobar, "s", $nombre);
mysqli_stmt_execute($comprobar);
mysqli_stmt_bind_result($comprobar, $cantidad);
mysqli_stmt_fetch($comprobar);
mysqli_stmt_close($comprobar);

if ((int) $cantidad > 0) {
    mysqli_close($conex);
    header("Location: foro.php?error_comunidad=existe");
    exit();
}

$consulta = mysqli_prepare(
    $conex,
    "INSERT INTO comunidades (nombre, descripcion, creador)
     VALUES (?, ?, ?)"
);

if ($consulta === false) {
    exit("Error al preparar la creación del foro.");
}

mysqli_stmt_bind_param(
    $consulta,
    "sss",
    $nombre,
    $descripcion,
    $creador
);

if (!mysqli_stmt_execute($consulta)) {
    $error = mysqli_stmt_error($consulta);
    mysqli_stmt_close($consulta);
    mysqli_close($conex);
    exit("No se pudo crear el foro: " . htmlspecialchars($error));
}

$nuevoId = mysqli_insert_id($conex);

mysqli_stmt_close($consulta);
mysqli_close($conex);

header("Location: foro.php?foro=" . $nuevoId);
exit();