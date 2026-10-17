<?php

declare(strict_types=1);

session_start();

include __DIR__ . "/conexion.php";
mysqli_set_charset($conex, "utf8mb4");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

$usuario = trim($_POST["usuario"] ?? "");
$password = trim($_POST["password"] ?? "");

if ($usuario === "" || $password === "") {
    header("Location: login.php?error=campos");
    exit();
}

if (!preg_match('/^[0-9]{4}$/', $usuario)) {
    header("Location: login.php?error=formato");
    exit();
}

$consulta = mysqli_prepare(
    $conex,
    "SELECT usuario, contraseña, rol, carrera_id, token_sesion, ultima_actividad
     FROM datos
     WHERE usuario = ?
     LIMIT 1"
);

if (!$consulta) {
    exit("Error al preparar la consulta.");
}

mysqli_stmt_bind_param($consulta, "s", $usuario);
mysqli_stmt_execute($consulta);

$resultado = mysqli_stmt_get_result($consulta);
$fila = mysqli_fetch_assoc($resultado);

if (!$fila) {
    mysqli_stmt_close($consulta);
    mysqli_close($conex);
    header("Location: login.php?error=usuario");
    exit();
}

if (!hash_equals((string) $fila["contraseña"], $password)) {
    mysqli_stmt_close($consulta);
    mysqli_close($conex);
    header("Location: login.php?error=password");
    exit();
}

/*
 * SESIÓN ÚNICA
 *
 * Si existe un token y hubo actividad durante los últimos 30 minutos,
 * consideramos que la cuenta sigue abierta en otro dispositivo.
 */
$tokenActivo = (string) ($fila["token_sesion"] ?? "");
$ultimaActividad = $fila["ultima_actividad"] ?? null;
$sesionVigente = false;

if ($tokenActivo !== "" && $ultimaActividad !== null) {
    $marcaTiempo = strtotime((string) $ultimaActividad);

    if ($marcaTiempo !== false) {
        $sesionVigente = $marcaTiempo > (time() - 1800);
    }
}

if ($sesionVigente) {
    mysqli_stmt_close($consulta);
    mysqli_close($conex);
    header("Location: login.php?error=sesion_activa");
    exit();
}

/*
 * No hay otra sesión vigente (o la anterior caducó).
 * Generamos un token criptográficamente aleatorio para este dispositivo.
 */
$tokenNuevo = bin2hex(random_bytes(32));

$guardarSesion = mysqli_prepare(
    $conex,
    "UPDATE datos
     SET token_sesion = ?,
         ultima_actividad = NOW()
     WHERE usuario = ?"
);

if (!$guardarSesion) {
    mysqli_stmt_close($consulta);
    mysqli_close($conex);
    exit("No se pudo registrar la sesión.");
}

mysqli_stmt_bind_param($guardarSesion, "ss", $tokenNuevo, $usuario);
mysqli_stmt_execute($guardarSesion);
mysqli_stmt_close($guardarSesion);

session_regenerate_id(true);

/*
 * DATOS DE LA SESIÓN
 *
 * Guardamos usuario, token, rol y carrera.
 */
$_SESSION["usuario"] = (string) $fila["usuario"];
$_SESSION["token_sesion"] = $tokenNuevo;
$_SESSION["rol"] = (string) $fila["rol"];

if ($fila["carrera_id"] !== null) {
    $_SESSION["carrera_id"] = (int) $fila["carrera_id"];
} else {
    $_SESSION["carrera_id"] = null;
}

mysqli_stmt_close($consulta);
mysqli_close($conex);

/*
 * REDIRECCIÓN INTELIGENTE
 *
 * Si el usuario es alumno y no tiene carrera asignada,
 * lo mandamos a seleccionar su carrera antes de entrar al foro.
 * Directores, subdirectores y maestros entran directo al foro.
 */
$rolUsuario = (string) $fila["rol"];
$carreraUsuario = $fila["carrera_id"];

if ($rolUsuario === "alumno" && $carreraUsuario === null) {
    header("Location: seleccionar_carrera.php");
} else {
    header("Location: foro.php");
}

exit();