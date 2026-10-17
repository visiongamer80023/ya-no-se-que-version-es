
<?php

session_start();

require __DIR__ . "/verificar_acceso.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

/* SOLO EL DIRECTOR PUEDE CREAR FOROS */
if (($_SESSION["rol"] ?? "") !== "director") {
    header("Location: foro.php?error_comunidad=sin_permiso");
    exit();
}

/* SOLO SE PERMITE ENTRAR MEDIANTE POST */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: foro.php");
    exit();
}

require __DIR__ . "/conexion.php";

mysqli_set_charset($conex, "utf8mb4");

$nombre = trim((string) ($_POST["nombre"] ?? ""));
$descripcion = trim((string) ($_POST["descripcion"] ?? ""));
$carreraRecibida = $_POST["carrera_id"] ?? null;

$creador = (string) $_SESSION["usuario"];

/* VALIDAR NOMBRE */
if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $nombre)) {
    header("Location: foro.php?error_comunidad=formato");
    exit();
}

/* VALIDAR DESCRIPCIÓN */
if (strlen($descripcion) > 300) {
    header("Location: foro.php?error_comunidad=descripcion");
    exit();
}

/* VALIDAR CARRERA */
if (
    !is_string($carreraRecibida)
    || !preg_match('/^(0|[1-3])$/', $carreraRecibida)
) {
    header("Location: foro.php?error_comunidad=carrera");
    exit();
}

/*
 * 0 = foro general: carrera_id NULL
 * 1, 2 y 3 = carreras existentes
 */
$carreraId = (int) $carreraRecibida;

if ($carreraId === 0) {
    $carreraId = null;
} else {
    $verificarCarrera = mysqli_prepare(
        $conex,
        "SELECT id FROM carreras WHERE id = ? LIMIT 1"
    );

    if (!$verificarCarrera) {
        mysqli_close($conex);
        exit("No se pudo verificar la carrera.");
    }

    mysqli_stmt_bind_param(
        $verificarCarrera,
        "i",
        $carreraId
    );

    mysqli_stmt_execute($verificarCarrera);
    mysqli_stmt_store_result($verificarCarrera);

    $carreraExiste = mysqli_stmt_num_rows($verificarCarrera) > 0;

    mysqli_stmt_close($verificarCarrera);

    if (!$carreraExiste) {
        mysqli_close($conex);
        header("Location: foro.php?error_comunidad=carrera");
        exit();
    }
}

/* COMPROBAR SI EL NOMBRE YA EXISTE */
$comprobar = mysqli_prepare(
    $conex,
    "SELECT COUNT(*) FROM comunidades WHERE nombre = ?"
);

if (!$comprobar) {
    mysqli_close($conex);
    exit("No se pudo comprobar el nombre del foro.");
}

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

/* CREAR FORO CON SU CARRERA */
$consulta = mysqli_prepare(
    $conex,
    "INSERT INTO comunidades
        (nombre, descripcion, creador, carrera_id)
     VALUES (?, ?, ?, ?)"
);

if (!$consulta) {
    mysqli_close($conex);
    exit("No se pudo preparar la creación del foro.");
}

mysqli_stmt_bind_param(
    $consulta,
    "sssi",
    $nombre,
    $descripcion,
    $creador,
    $carreraId
);

if (!mysqli_stmt_execute($consulta)) {
    $error = mysqli_stmt_error($consulta);

    mysqli_stmt_close($consulta);
    mysqli_close($conex);

    exit(
        "No se pudo crear el foro: " .
        htmlspecialchars($error, ENT_QUOTES, "UTF-8")
    );
}

$nuevoId = mysqli_insert_id($conex);

mysqli_stmt_close($consulta);
mysqli_close($conex);

/* REGRESAR AL FORO NUEVO */
header("Location: foro.php?foro=" . $nuevoId);
exit();
