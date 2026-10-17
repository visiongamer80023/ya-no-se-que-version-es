<?php
declare(strict_types=1);

session_start();
require __DIR__ . "/verificar_acceso.php";
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

require __DIR__ . "/conexion.php";
mysqli_set_charset($conex, "utf8mb4");

$usuarioSesion = (string) $_SESSION["usuario"];
$usuarioPerfil = trim((string) ($_GET["usuario"] ?? $usuarioSesion));

if (!preg_match('/^[0-9]{4}$/', $usuarioPerfil)) {
    exit("Usuario no válido.");
}

/* Obtener información del usuario */
$consultaUsuario = mysqli_prepare(
    $conex,
    "SELECT usuario, foto, descripcion, fecha_registro, rol
     FROM datos
     WHERE usuario = ?
     LIMIT 1"
);

mysqli_stmt_bind_param(
    $consultaUsuario,
    "s",
    $usuarioPerfil
);

mysqli_stmt_execute($consultaUsuario);

$resultadoUsuario = mysqli_stmt_get_result($consultaUsuario);
$datosUsuario = mysqli_fetch_assoc($resultadoUsuario);

mysqli_stmt_close($consultaUsuario);

if (!$datosUsuario) {
    mysqli_close($conex);
    exit("El usuario no existe.");
}

/* Comprobar si el usuario que inició sesión es administrador */
$consultaAdmin = mysqli_prepare(
    $conex,
    "SELECT rol
     FROM datos
     WHERE usuario = ?
     LIMIT 1"
);

if ($consultaAdmin === false) {
    mysqli_close($conex);
    exit("No se pudo comprobar el rol del usuario.");
}

mysqli_stmt_bind_param(
    $consultaAdmin,
    "s",
    $usuarioSesion
);

mysqli_stmt_execute($consultaAdmin);

$resultadoAdmin = mysqli_stmt_get_result($consultaAdmin);
$datosAdmin = mysqli_fetch_assoc($resultadoAdmin);

mysqli_stmt_close($consultaAdmin);

$esAdministrador =
    ($datosAdmin["rol"] ?? "usuario") === "admin";

/* Cantidad de publicaciones */
$consultaPosts = mysqli_prepare(
    $conex,
    "SELECT COUNT(*) AS total
     FROM mensajes
     WHERE usuario = ?"
);

mysqli_stmt_bind_param(
    $consultaPosts,
    "s",
    $usuarioPerfil
);

mysqli_stmt_execute($consultaPosts);

$resultadoCantidadPosts = mysqli_stmt_get_result($consultaPosts);
$filaCantidadPosts = mysqli_fetch_assoc($resultadoCantidadPosts);

$cantidadPosts = (int) ($filaCantidadPosts["total"] ?? 0);

mysqli_stmt_close($consultaPosts);

/* Cantidad de comunidades creadas */
$consultaComunidades = mysqli_prepare(
    $conex,
    "SELECT COUNT(*) AS total
     FROM comunidades
     WHERE creador = ?"
);

mysqli_stmt_bind_param(
    $consultaComunidades,
    "s",
    $usuarioPerfil
);

mysqli_stmt_execute($consultaComunidades);

$resultadoCantidadComunidades =
    mysqli_stmt_get_result($consultaComunidades);

$filaCantidadComunidades =
    mysqli_fetch_assoc($resultadoCantidadComunidades);

$cantidadComunidades =
    (int) ($filaCantidadComunidades["total"] ?? 0);

mysqli_stmt_close($consultaComunidades);

/* Publicaciones recientes */
$consultaPublicaciones = mysqli_prepare(
    $conex,
    "SELECT
        mensajes.mensaje,
        mensajes.imagen,
        mensajes.fecha,
        comunidades.id AS comunidad_id,
        comunidades.nombre AS comunidad_nombre
     FROM mensajes
     LEFT JOIN comunidades
        ON comunidades.id = mensajes.comunidad_id
     WHERE mensajes.usuario = ?
     ORDER BY mensajes.id DESC
     LIMIT 20"
);

mysqli_stmt_bind_param(
    $consultaPublicaciones,
    "s",
    $usuarioPerfil
);

mysqli_stmt_execute($consultaPublicaciones);

$resultadoPublicaciones =
    mysqli_stmt_get_result($consultaPublicaciones);

$publicaciones = [];

while (
    $publicacion =
    mysqli_fetch_assoc($resultadoPublicaciones)
) {
    $publicaciones[] = $publicacion;
}

mysqli_stmt_close($consultaPublicaciones);
mysqli_close($conex);

$esPropietario = $usuarioSesion === $usuarioPerfil;

$foto = trim((string) ($datosUsuario["foto"] ?? ""));

$tieneFoto =
    $foto !== "" &&
    $foto !== "default.png" &&
    file_exists(__DIR__ . "/" . $foto);

$descripcion =
    trim((string) ($datosUsuario["descripcion"] ?? ""));

$mensajeError = "";

$errores = [
    "descripcion" => "La descripción no es válida.",
    "imagen" => "La imagen seleccionada no es válida.",
    "tamano" => "La imagen no puede superar los 5 MB.",
    "subida" => "No se pudo guardar la imagen.",
    "actualizacion" => "No se pudo actualizar el perfil."
];

$codigoError = (string) ($_GET["error"] ?? "");

if (isset($errores[$codigoError])) {
    $mensajeError = $errores[$codigoError];
}

$actualizado = isset($_GET["actualizado"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Perfil de <?= htmlspecialchars($usuarioPerfil) ?>
    </title>

    <style>
*{box-sizing:border-box}
:root{--bg:#eef2f7;--card:#fff;--line:#dbe3ed;--text:#1f2937;--muted:#64748b;--blue:#2563eb;--blue2:#1d4ed8;--red:#dc2626;--shadow:0 10px 28px rgba(15,23,42,.08)}
body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;background:linear-gradient(180deg,#e8eef6,var(--bg));color:var(--text)}
.header{background:linear-gradient(135deg,#111827,#1e293b);color:#fff;padding:16px 4%;display:flex;justify-content:space-between;align-items:center;gap:20px;box-shadow:0 4px 18px rgba(0,0,0,.2)}
.header a{color:#fff;text-decoration:none;font-weight:700}
.admin-enlace{color:#fca5a5!important}
.contenedor{width:min(1200px,94%);margin:24px auto;display:grid;grid-template-columns:330px 1fr;gap:24px}
.panel{background:var(--card);padding:24px;border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow)}
.perfil{text-align:center}
.avatar,.avatar-vacio{width:160px;height:160px;margin:0 auto 18px;border-radius:50%;border:5px solid var(--blue);background:#edf4ff}
.avatar{display:block;object-fit:cover}
.avatar-vacio{display:flex;justify-content:center;align-items:center;font-size:70px}
.insignia-admin{display:inline-block;background:var(--red);color:#fff;padding:7px 14px;border-radius:999px;font-size:12px;font-weight:700;margin:8px 0 14px}
.descripcion{background:#f8fafc;border:1px solid var(--line);padding:14px;border-radius:10px;text-align:left;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.6;min-height:70px}
.estadisticas{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-top:18px}
.estadistica{background:#f8fafc;border:1px solid var(--line);border-radius:10px;padding:14px}
.estadistica strong{display:block;color:var(--blue);font-size:28px}
.editar{margin-top:22px;padding-top:20px;border-top:1px solid #e5e7eb;text-align:left}
textarea,input[type=file]{width:100%;margin-bottom:12px}
textarea{padding:11px;min-height:110px;border:1px solid #cbd5e1;border-radius:10px;resize:vertical}
textarea:focus{outline:none;border-color:#60a5fa;box-shadow:0 0 0 3px rgba(37,99,235,.15)}
button{background:var(--blue);color:#fff;border:none;border-radius:10px;padding:11px 18px;font-weight:700;cursor:pointer}
button:hover{background:var(--blue2)}
.aviso{padding:12px;border-radius:10px;margin-bottom:14px}.correcto{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}
.post{border:1px solid var(--line);border-radius:12px;padding:16px;margin-bottom:16px;transition:.2s}
.post:hover{box-shadow:var(--shadow)}
.post p{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.6}
.post img{display:block;max-width:100%;max-height:450px;object-fit:contain;border-radius:10px;border:1px solid var(--line);margin-top:12px}
.post a{color:var(--blue);text-decoration:none;font-weight:700}
.meta{color:var(--muted);font-size:13px;margin-top:10px}
@media(max-width:850px){.contenedor{grid-template-columns:1fr}.header{flex-direction:column;align-items:flex-start}}
    </style>
</head>

<body>

<header class="header">

    <a href="foro.php">
        ← Volver a las comunidades
    </a>

    <div>
        <a
            href="perfil.php?usuario=<?= urlencode($usuarioSesion) ?>"
        >
            Mi perfil
        </a>

        <?php if ($esAdministrador): ?>

            &nbsp; | &nbsp;

            <a
                class="admin-enlace"
                href="admin.php"
            >
                Administración
            </a>

        <?php endif; ?>

        &nbsp; | &nbsp;

        <a href="cerrar_sesion.php">
            Cerrar sesión
        </a>
    </div>

</header>

<div class="contenedor">

    <aside class="panel perfil">

        <?php if ($tieneFoto): ?>

            <img
                class="avatar"
                src="<?= htmlspecialchars($foto) ?>"
                alt="Foto de perfil"
            >

        <?php else: ?>

            <div class="avatar-vacio">
                👤
            </div>

        <?php endif; ?>

        <h1>
            Usuario <?= htmlspecialchars($usuarioPerfil) ?>
        </h1>

        <?php if (($datosUsuario["rol"] ?? "usuario") === "admin"): ?>

            <div class="insignia-admin">
                ADMINISTRADOR
            </div>

        <?php endif; ?>

        <p>
            Miembro desde:
            <br>

            <strong>
                <?= htmlspecialchars(
                    (string) $datosUsuario["fecha_registro"]
                ) ?>
            </strong>
        </p>

        <h3>Descripción</h3>

        <div class="descripcion"><?=
            htmlspecialchars(
                $descripcion !== ""
                    ? $descripcion
                    : "Este usuario todavía no ha escrito una descripción."
            )
        ?></div>

        <div class="estadisticas">

            <div class="estadistica">
                <strong>
                    <?= $cantidadPosts ?>
                </strong>

                Publicaciones
            </div>

            <div class="estadistica">
                <strong>
                    <?= $cantidadComunidades ?>
                </strong>

                Comunidades
            </div>

        </div>

        <?php if ($esPropietario): ?>

            <section class="editar">

                <h2>Editar perfil</h2>

                <?php if ($actualizado): ?>

                    <div class="aviso correcto">
                        Perfil actualizado correctamente.
                    </div>

                <?php endif; ?>

                <?php if ($mensajeError !== ""): ?>

                    <div class="aviso error">
                        <?= htmlspecialchars($mensajeError) ?>
                    </div>

                <?php endif; ?>

                <form
                    action="actualizar_perfil.php"
                    method="POST"
                    enctype="multipart/form-data"
                >

                    <label for="descripcion">
                        Descripción:
                    </label>

                    <textarea
                        id="descripcion"
                        name="descripcion"
                        maxlength="500"
                        placeholder="Escribe algo sobre ti..."
                    ><?= htmlspecialchars($descripcion) ?></textarea>

                    <label for="foto">
                        Foto de perfil:
                    </label>

                    <input
                        id="foto"
                        type="file"
                        name="foto"
                        accept="image/jpeg,image/png,image/webp"
                    >

                    <button type="submit">
                        Guardar cambios
                    </button>

                </form>

            </section>

        <?php endif; ?>

    </aside>

    <main class="panel">

        <h2>Publicaciones recientes</h2>

        <?php if (!$publicaciones): ?>

            <p>
                Este usuario todavía no ha publicado nada.
            </p>

        <?php endif; ?>

        <?php foreach ($publicaciones as $post): ?>

            <article class="post">

                <?php if (!empty($post["comunidad_id"])): ?>

                    <a
                        href="foro.php?foro=<?= (int) $post["comunidad_id"] ?>"
                    >
                        r/<?= htmlspecialchars(
                            (string) $post["comunidad_nombre"]
                        ) ?>
                    </a>

                <?php endif; ?>

                <?php if (
                    trim((string) $post["mensaje"]) !== ""
                ): ?>

                    <p>
                        <?= htmlspecialchars(
                            (string) $post["mensaje"]
                        ) ?>
                    </p>

                <?php endif; ?>

                <?php if (!empty($post["imagen"])): ?>

                    <img
                        src="<?= htmlspecialchars(
                            (string) $post["imagen"]
                        ) ?>"
                        alt="Imagen publicada"
                    >

                <?php endif; ?>

                <div class="meta">
                    Publicado el
                    <?= htmlspecialchars(
                        (string) $post["fecha"]
                    ) ?>
                </div>

            </article>

        <?php endforeach; ?>

    </main>

</div>

</body>
</html>