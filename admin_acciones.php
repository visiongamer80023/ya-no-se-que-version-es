<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: admin.php");
    exit();
}

require __DIR__ . "/conexion.php";
mysqli_set_charset($conex, "utf8mb4");

$adminActual = (string) $_SESSION["usuario"];

/* Comprobar que quien ejecuta la acción sea administrador */
$consultaAdmin = mysqli_prepare(
    $conex,
    "SELECT rol
     FROM datos
     WHERE usuario = ?
     LIMIT 1"
);
/*


 POR QUE NO FUNCIONA
if ($consultaAdmin === false) {
    mysqli_close($conex);
    //si no se puede comprar el admin se cierra la conexion
    exit("No se pudo comprobar el rol del usuario.");
}else{
     // Si la consulta no es falsa, decides a dónde redirigir
    // Nota: Aquí debes agregar tu condición real para saber si es admin o no

 header("Location: foro.php");
}
if($esAdmin){
    header("Location: mantenimiento.php");
    exit();
}else{
    header("Location: foro.php");
    exit();
}
   
*/
mysqli_stmt_bind_param(
    $consultaAdmin,
    "s",
    $adminActual
);

mysqli_stmt_execute($consultaAdmin);

$resultadoAdmin = mysqli_stmt_get_result($consultaAdmin);
$datosAdmin = mysqli_fetch_assoc($resultadoAdmin);

mysqli_stmt_close($consultaAdmin);

if (!$datosAdmin || ($datosAdmin["rol"] ?? "usuario") !== "admin") {
    mysqli_close($conex);
    http_response_code(403);
    exit("Acceso denegado.");
}

$accion = trim((string) ($_POST["accion"] ?? ""));

function volverAdmin(string $mensaje = "", string $error = ""): never
{
    $parametros = [];

    if ($mensaje !== "") {
        $parametros["mensaje"] = $mensaje;
    }

    if ($error !== "") {
        $parametros["error"] = $error;
    }

    $url = "admin.php";

    if ($parametros) {
        $url .= "?" . http_build_query($parametros);
    }

    header("Location: " . $url);
    exit();
}

/* Activar o desactivar mantenimiento */
if ($accion === "mantenimiento") {
    $estado = ((int) ($_POST["estado"] ?? 0) === 1) ? 1 : 0;

    $mensaje = trim((string) ($_POST["mensaje"] ?? ""));

    if ($mensaje === "") {
        $mensaje = "La página se encuentra temporalmente en mantenimiento.";
    }

    if (strlen($mensaje) > 500) {
        mysqli_close($conex);
        volverAdmin("", "El mensaje de mantenimiento es demasiado largo.");
    }

    $hastaEntrada = trim(
        (string) ($_POST["mantenimiento_hasta"] ?? "")
    );

    $mantenimientoHasta = null;

    if ($estado === 1 && $hastaEntrada !== "") {
        $timestamp = strtotime($hastaEntrada);

        if ($timestamp === false) {
            mysqli_close($conex);
            volverAdmin("", "La fecha de mantenimiento no es válida.");
        }

        if ($timestamp <= time()) {
            mysqli_close($conex);
            volverAdmin("", "La fecha de finalización debe ser futura.");
        }

        $mantenimientoHasta = date("Y-m-d H:i:s", $timestamp);
    }

    if ($estado === 0) {
        $mantenimientoHasta = null;
    }

    $consulta = mysqli_prepare(
        $conex,
        "UPDATE configuracion_sitio
         SET mantenimiento = ?,
             mensaje_mantenimiento = ?,
             mantenimiento_hasta = ?,
             actualizado_por = ?
         WHERE id = 1"
    );

    if ($consulta === false) {
        mysqli_close($conex);
        volverAdmin("", "No se pudo preparar el cambio de mantenimiento.");
    }

    mysqli_stmt_bind_param(
        $consulta,
        "isss",
        $estado,
        $mensaje,
        $mantenimientoHasta,
        $adminActual
    );

    if (!mysqli_stmt_execute($consulta)) {
        $error = mysqli_stmt_error($consulta);

        mysqli_stmt_close($consulta);
        mysqli_close($conex);

        volverAdmin("", "No se pudo actualizar mantenimiento: " . $error);
    }

    mysqli_stmt_close($consulta);
    mysqli_close($conex);

    volverAdmin(
        $estado === 1
            ? "Modo mantenimiento activado."
            : "Modo mantenimiento desactivado."
    );
}

/* Banear usuario */
if ($accion === "banear") {
    $usuario = trim((string) ($_POST["usuario"] ?? ""));
    $motivo = trim((string) ($_POST["motivo"] ?? ""));
    $banHastaEntrada = trim((string) ($_POST["ban_hasta"] ?? ""));

    if (!preg_match('/^[0-9]{4}$/', $usuario)) {
        mysqli_close($conex);
        volverAdmin("", "El usuario indicado no es válido.");
    }

    if ($usuario === $adminActual) {
        mysqli_close($conex);
        volverAdmin("", "No puedes banear tu propia cuenta.");
    }

    if ($motivo === "") {
        $motivo = "Suspensión aplicada por un administrador.";
    }

    if (strlen($motivo) > 255) {
        mysqli_close($conex);
        volverAdmin("", "El motivo del ban es demasiado largo.");
    }

    $banHasta = null;

    if ($banHastaEntrada !== "") {
        $timestamp = strtotime($banHastaEntrada);

        if ($timestamp === false || $timestamp <= time()) {
            mysqli_close($conex);
            volverAdmin("", "La fecha de finalización del ban no es válida.");
        }

        $banHasta = date("Y-m-d H:i:s", $timestamp);
    }

    $consulta = mysqli_prepare(
        $conex,
        "UPDATE datos
         SET baneado = 1,
             motivo_ban = ?,
             ban_hasta = ?
         WHERE usuario = ?
           AND rol != 'admin'"
    );

    mysqli_stmt_bind_param(
        $consulta,
        "sss",
        $motivo,
        $banHasta,
        $usuario
    );

    mysqli_stmt_execute($consulta);

    if (mysqli_stmt_affected_rows($consulta) === 0) {
        mysqli_stmt_close($consulta);
        mysqli_close($conex);

        volverAdmin(
            "",
            "No se pudo banear al usuario. Puede ser administrador o no existir."
        );
    }

    mysqli_stmt_close($consulta);
    mysqli_close($conex);

    volverAdmin("Usuario " . $usuario . " baneado correctamente.");
}

/* Quitar ban */
if ($accion === "desbanear") {
    $usuario = trim((string) ($_POST["usuario"] ?? ""));

    if (!preg_match('/^[0-9]{4}$/', $usuario)) {
        mysqli_close($conex);
        volverAdmin("", "El usuario indicado no es válido.");
    }

    $consulta = mysqli_prepare(
        $conex,
        "UPDATE datos
         SET baneado = 0,
             motivo_ban = NULL,
             ban_hasta = NULL
         WHERE usuario = ?"
    );

    mysqli_stmt_bind_param(
        $consulta,
        "s",
        $usuario
    );

    mysqli_stmt_execute($consulta);
    mysqli_stmt_close($consulta);
    mysqli_close($conex);

    volverAdmin("Se eliminó el ban del usuario " . $usuario . ".");
}

/* Convertir en administrador */
if ($accion === "hacer_admin") {
    $usuario = trim((string) ($_POST["usuario"] ?? ""));

    if (!preg_match('/^[0-9]{4}$/', $usuario)) {
        mysqli_close($conex);
        volverAdmin("", "El usuario indicado no es válido.");
    }

    $consulta = mysqli_prepare(
        $conex,
        "UPDATE datos
         SET rol = 'admin',
             baneado = 0,
             motivo_ban = NULL,
             ban_hasta = NULL
         WHERE usuario = ?"
    );

    mysqli_stmt_bind_param(
        $consulta,
        "s",
        $usuario
    );

    mysqli_stmt_execute($consulta);
    mysqli_stmt_close($consulta);
    mysqli_close($conex);

    volverAdmin("El usuario " . $usuario . " ahora es administrador.");
}

/* Quitar administrador */
if ($accion === "quitar_admin") {
    $usuario = trim((string) ($_POST["usuario"] ?? ""));

    if (!preg_match('/^[0-9]{4}$/', $usuario)) {
        mysqli_close($conex);
        volverAdmin("", "El usuario indicado no es válido.");
    }

    if ($usuario === $adminActual) {
        mysqli_close($conex);
        volverAdmin("", "No puedes quitarte tu propio rol de administrador.");
    }

    $consulta = mysqli_prepare(
        $conex,
        "UPDATE datos
         SET rol = 'usuario'
         WHERE usuario = ?"
    );

    mysqli_stmt_bind_param(
        $consulta,
        "s",
        $usuario
    );

    mysqli_stmt_execute($consulta);
    mysqli_stmt_close($consulta);
    mysqli_close($conex);

    volverAdmin("Se quitó el rol administrativo al usuario " . $usuario . ".");
}

/* Eliminar comunidad y sus publicaciones */
if ($accion === "eliminar_comunidad") {
    $comunidadId = filter_input(
        INPUT_POST,
        "comunidad_id",
        FILTER_VALIDATE_INT
    );

    if (!$comunidadId) {
        mysqli_close($conex);
        volverAdmin("", "La comunidad indicada no es válida.");
    }

    mysqli_begin_transaction($conex);

    try {
        $consultaImagenes = mysqli_prepare(
            $conex,
            "SELECT imagen
             FROM mensajes
             WHERE comunidad_id = ?
               AND imagen IS NOT NULL"
        );

        mysqli_stmt_bind_param(
            $consultaImagenes,
            "i",
            $comunidadId
        );

        mysqli_stmt_execute($consultaImagenes);
        $resultadoImagenes = mysqli_stmt_get_result($consultaImagenes);

        $imagenes = [];

        while ($fila = mysqli_fetch_assoc($resultadoImagenes)) {
            if (!empty($fila["imagen"])) {
                $imagenes[] = (string) $fila["imagen"];
            }
        }

        mysqli_stmt_close($consultaImagenes);

        $eliminarMensajes = mysqli_prepare(
            $conex,
            "DELETE FROM mensajes
             WHERE comunidad_id = ?"
        );

        mysqli_stmt_bind_param(
            $eliminarMensajes,
            "i",
            $comunidadId
        );

        mysqli_stmt_execute($eliminarMensajes);
        mysqli_stmt_close($eliminarMensajes);

        $eliminarComunidad = mysqli_prepare(
            $conex,
            "DELETE FROM comunidades
             WHERE id = ?"
        );

        mysqli_stmt_bind_param(
            $eliminarComunidad,
            "i",
            $comunidadId
        );

        mysqli_stmt_execute($eliminarComunidad);

        if (mysqli_stmt_affected_rows($eliminarComunidad) === 0) {
            throw new RuntimeException("La comunidad no existe.");
        }

        mysqli_stmt_close($eliminarComunidad);

        mysqli_commit($conex);

        foreach ($imagenes as $rutaImagen) {
            if (
                str_starts_with($rutaImagen, "uploads/") &&
                is_file(__DIR__ . "/" . $rutaImagen)
            ) {
                @unlink(__DIR__ . "/" . $rutaImagen);
            }
        }

        mysqli_close($conex);

        volverAdmin("La comunidad fue eliminada correctamente.");
    } catch (Throwable $error) {
        mysqli_rollback($conex);
        mysqli_close($conex);

        volverAdmin(
            "",
            "No se pudo eliminar la comunidad: " . $error->getMessage()
        );
    }
}

mysqli_close($conex);

volverAdmin("", "La acción solicitada no es válida.");
