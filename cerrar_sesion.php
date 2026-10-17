<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . "/conexion.php";
mysqli_set_charset($conex, "utf8mb4");

$usuario = isset($_SESSION["usuario"]) ? (string) $_SESSION["usuario"] : "";
$token = isset($_SESSION["token_sesion"]) ? (string) $_SESSION["token_sesion"] : "";

if ($usuario !== "" && $token !== "") {
    $consulta = mysqli_prepare(
        $conex,
        "UPDATE datos
         SET token_sesion = NULL,
             ultima_actividad = NULL
         WHERE usuario = ? AND token_sesion = ?"
    );

    if ($consulta) {
        mysqli_stmt_bind_param($consulta, "ss", $usuario, $token);
        mysqli_stmt_execute($consulta);
        mysqli_stmt_close($consulta);
    }
}

session_unset();
session_destroy();

header("Location: login.php");
exit();
