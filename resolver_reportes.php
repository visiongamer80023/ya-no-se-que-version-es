<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: reportes_admin.php");
    exit();
}

require __DIR__ . "/conexion.php";
mysqli_set_charset($conex, "utf8mb4");

$admin = (string) $_SESSION["usuario"];

$stmt = mysqli_prepare(
    $conex,
    "SELECT rol FROM datos WHERE usuario = ? LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "s", $admin);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$datos = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if (!$datos || ($datos["rol"] ?? "") !== "admin") {
    http_response_code(403);
    exit("Acceso denegado.");
}

$token = (string) ($_POST["csrf"] ?? "");

if (
    !isset($_SESSION["csrf_admin_reportes"]) ||
    !hash_equals($_SESSION["csrf_admin_reportes"], $token)
) {
    exit("La sesión del formulario venció.");
}

$reporteId = filter_input(
    INPUT_POST,
    "reporte_id",
    FILTER_VALIDATE_INT
);

$accion = (string) ($_POST["accion"] ?? "");

if (!$reporteId) {
    exit("Reporte no válido.");
}

$stmt = mysqli_prepare(
    $conex,
    "SELECT
        r.publicacion_id,
        m.usuario AS autor,
        m.imagen
     FROM reportes r
     LEFT JOIN mensajes m
        ON m.id = r.publicacion_id
     WHERE r.id = ?
       AND r.estado = 'pendiente'
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $reporteId);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$reporte = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if (!$reporte) {
    exit("El reporte ya fue resuelto o no existe.");
}

mysqli_begin_transaction($conex);

try {
    $estado = "";

    if ($accion === "eliminar_contenido") {
        $publicacionId = (int) $reporte["publicacion_id"];

        $stmt = mysqli_prepare(
            $conex,
            "DELETE FROM mensajes WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $publicacionId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $estado = "contenido_eliminado";
    } elseif ($accion === "banear_autor") {
        $autor = (string) ($reporte["autor"] ?? "");

        if (!preg_match('/^[0-9]{4}$/', $autor)) {
            throw new RuntimeException("El autor no existe.");
        }

        $motivo = "Baneado por una publicación reportada.";

        $stmt = mysqli_prepare(
            $conex,
            "UPDATE datos
             SET baneado = 1,
                 motivo_ban = ?,
                 ban_hasta = NULL
             WHERE usuario = ?
               AND rol != 'admin'"
        );

        mysqli_stmt_bind_param($stmt, "ss", $motivo, $autor);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $estado = "usuario_baneado";
    } elseif ($accion === "rechazar") {
        $estado = "rechazado";
    } else {
        throw new RuntimeException("Acción no válida.");
    }

    $stmt = mysqli_prepare(
        $conex,
        "UPDATE reportes
         SET estado = ?,
             revisado_por = ?,
             fecha_revision = NOW()
         WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ssi",
        $estado,
        $admin,
        $reporteId
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($conex);

    if (
        $accion === "eliminar_contenido" &&
        !empty($reporte["imagen"]) &&
        str_starts_with((string) $reporte["imagen"], "uploads/")
    ) {
        $ruta = __DIR__ . "/" . $reporte["imagen"];

        if (is_file($ruta)) {
            @unlink($ruta);
        }
    }
} catch (Throwable $error) {
    mysqli_rollback($conex);
    mysqli_close($conex);

    exit(
        "No se pudo resolver el reporte: " .
        $error->getMessage()
    );
}

mysqli_close($conex);

header("Location: reportes_admin.php?estado=pendiente");
exit();