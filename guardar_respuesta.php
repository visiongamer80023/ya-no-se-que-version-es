<?php
declare(strict_types=1);
session_start();

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

$usuario = (string) $_SESSION["usuario"];
$publicacionId = filter_input(INPUT_POST, "publicacion_id", FILTER_VALIDATE_INT);
$contenido = trim((string) ($_POST["contenido"] ?? ""));
$comunidadId = filter_input(INPUT_POST, "comunidad_id", FILTER_VALIDATE_INT);

if (!$publicacionId || !$comunidadId) {
    header("Location: foro.php?error_respuesta=publicacion_invalida");
    exit();
}

if ($contenido === "" || strlen($contenido) > 2000) {
    header("Location: foro.php?foro=$comunidadId&error_respuesta=contenido_invalido");
    exit();
}

// Verificar que la publicación existe
$verificar = mysqli_prepare($conex, "SELECT id FROM mensajes WHERE id = ?");
mysqli_stmt_bind_param($verificar, "i", $publicacionId);
mysqli_stmt_execute($verificar);
mysqli_stmt_store_result($verificar);

if (mysqli_stmt_num_rows($verificar) === 0) {
    mysqli_stmt_close($verificar);
    mysqli_close($conex);
    header("Location: foro.php?foro=$comunidadId&error_respuesta=no_existe");
    exit();
}
mysqli_stmt_close($verificar);

// Insertar respuesta
$insertar = mysqli_prepare(
    $conex,
    "INSERT INTO respuestas (publicacion_id, usuario, contenido) VALUES (?, ?, ?)"
);

if (!$insertar) {
    mysqli_close($conex);
    header("Location: foro.php?foro=$comunidadId&error_respuesta=error_servidor");
    exit();
}

mysqli_stmt_bind_param($insertar, "iss", $publicacionId, $usuario, $contenido);

if (!mysqli_stmt_execute($insertar)) {
    mysqli_stmt_close($insertar);
    mysqli_close($conex);
    header("Location: foro.php?foro=$comunidadId&error_respuesta=error_guardar");
    exit();
}

mysqli_stmt_close($insertar);
mysqli_close($conex);

header("Location: foro.php?foro=$comunidadId&respuesta_ok=1");
exit();