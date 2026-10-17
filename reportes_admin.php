<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
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

// CAMBIO: Aceptar tanto admin como director
if (!$datos || !in_array(($datos["rol"] ?? ""), ["admin", "director"])) {
    http_response_code(403);
    exit("Acceso denegado.");
}

$filtro = (string) ($_GET["estado"] ?? "pendiente");

$permitidos = [
    "pendiente",
    "contenido_eliminado",
    "rechazado",
    "usuario_baneado"
];

if (!in_array($filtro, $permitidos, true)) {
    $filtro = "pendiente";
}

$stmt = mysqli_prepare(
    $conex,
    "SELECT
        r.id,
        r.publicacion_id,
        r.reportado_por,
        r.motivo,
        r.descripcion,
        r.estado,
        r.fecha,
        r.revisado_por,
        r.fecha_revision,
        m.usuario AS autor_publicacion,
        m.mensaje,
        m.imagen,
        c.nombre AS comunidad
     FROM reportes r
     LEFT JOIN mensajes m
        ON m.id = r.publicacion_id
     LEFT JOIN comunidades c
        ON c.id = m.comunidad_id
     WHERE r.estado = ?
     ORDER BY r.fecha ASC"
);

mysqli_stmt_bind_param($stmt, "s", $filtro);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$reportes = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $reportes[] = $fila;
}

mysqli_stmt_close($stmt);
mysqli_close($conex);

$_SESSION["csrf_admin_reportes"] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Reportes</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background:#eef2f7;
        }
        
        .reporte h2{
            margin-top:0;
            color:#ff4500;
            font-size:28px;
        }

        header{
            background:#1a1a1b;
            color:#fff;
            padding:20px 30px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            box-shadow:0 3px 10px rgba(0,0,0,.25);
        }

        header strong{
            font-size:26px;
        }

        header a{
            color:white;
            text-decoration:none;
            background:#0079d3;
            padding:10px 18px;
            border-radius:8px;
            transition:.2s;
        }

        header a:hover{
            background:#1484d6;
        }

        main {
            width: 92%;
            max-width: 1100px;
            margin: 22px auto;
        }

        .filtros,
        .reporte{
            background:white;
            border-radius:12px;
            padding:25px;
            margin-bottom:25px;
            box-shadow:0 6px 18px rgba(0,0,0,.08);
            transition:.25s;
            border-left:6px solid #ff4500;
        }

        .reporte p{
            margin:8px 0;
            line-height:1.6;
        }

        .reporte strong{
            color:#444;
            width:150px;
            display:inline-block;
        }

        .reporte:hover{
            transform:translateY(-3px);
            box-shadow:0 12px 25px rgba(0,0,0,.12);
        }

        .filtros{
            display:flex;
            gap:15px;
            flex-wrap:wrap;
        }

        .filtros a{
            text-decoration:none;
            background:#0079d3;
            color:white;
            padding:12px 18px;
            border-radius:8px;
            transition:.2s;
        }

        .filtros a:hover{
            background:#1484d6;
        }

        .contenido{
            background:#f6f7f8;
            border-left:4px solid #0079d3;
            padding:18px;
            border-radius:8px;
            margin-top:15px;
            font-size:15px;
        }

       img{
            margin-top:15px;
            border-radius:10px;
            border:1px solid #ddd;
            box-shadow:0 3px 10px rgba(0,0,0,.15);
        }

        .acciones {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

       button{
            padding:12px 18px;
            font-size:15px;
            transition:.2s;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            color: white;
        }

        .eliminar {
            background: #dc2626;
            color: white;
        }

        .eliminar:hover{
            background:#920018;
        }

        .banear {
            background: #e67e00;
            color: white;
        }

        .banear:hover{
            background:#cc7000;
        }

        .rechazar {
            background: #666;
            color: white;
        }

        .rechazar:hover{
            background:#4d4d4d;
        }

        .info-rol {
            color: #94a3b8;
            font-size: 14px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
<header>
    <strong>
        Administración de reportes
        <span class="info-rol">(<?= htmlspecialchars($datos["rol"] === "director" ? "Director" : "Administrador") ?>)</span>
    </strong>
    &nbsp; | &nbsp;
    <a href="admin.php">Volver al panel</a>
</header>

<main>
    <section class="filtros">
        <a href="?estado=pendiente">Pendientes</a>
        <a href="?estado=contenido_eliminado">Contenido eliminado</a>
        <a href="?estado=usuario_baneado">Usuarios baneados</a>
        <a href="?estado=rechazado">Rechazados</a>
    </section>

    <?php if (!$reportes): ?>
        <section class="reporte">
            No hay reportes con este estado.
        </section>
    <?php endif; ?>

    <?php foreach ($reportes as $reporte): ?>
        <article class="reporte">
            <h2>Reporte #<?= (int) $reporte["id"] ?></h2>

            <p>
                <strong>Publicación:</strong>
                #<?= (int) $reporte["publicacion_id"] ?>
            </p>

            <p>
                <strong>Comunidad:</strong>
                r/<?= htmlspecialchars(
                    (string) ($reporte["comunidad"] ?? "Eliminada")
                ) ?>
            </p>

            <p>
                <strong>Autor:</strong>
                <?= htmlspecialchars(
                    (string) ($reporte["autor_publicacion"] ?? "Desconocido")
                ) ?>
            </p>

            <p>
                <strong>Reportado por:</strong>
                <?= htmlspecialchars((string) $reporte["reportado_por"]) ?>
            </p>

            <p>
                <strong>Motivo:</strong>
                <?= htmlspecialchars((string) $reporte["motivo"]) ?>
            </p>

            <p>
                <strong>Descripción:</strong>
                <?= htmlspecialchars((string) $reporte["descripcion"]) ?>
            </p>

            <?php if ($reporte["mensaje"] !== null): ?>
                <div class="contenido"><?= htmlspecialchars(
                    (string) $reporte["mensaje"]
                ) ?></div>
            <?php endif; ?>

            <?php if (!empty($reporte["imagen"])): ?>
                <img
                    src="<?= htmlspecialchars((string) $reporte["imagen"]) ?>"
                    alt="Imagen reportada"
                >
            <?php endif; ?>

            <?php if ($filtro === "pendiente"): ?>
                <div class="acciones">
                    <form action="resolver_reportes.php" method="POST">
                        <input
                            type="hidden"
                            name="csrf"
                            value="<?= htmlspecialchars(
                                $_SESSION["csrf_admin_reportes"]
                            ) ?>"
                        >
                        <input
                            type="hidden"
                            name="reporte_id"
                            value="<?= (int) $reporte["id"] ?>"
                        >
                        <input
                            type="hidden"
                            name="accion"
                            value="eliminar_contenido"
                        >
                        <button
                            class="eliminar"
                            type="submit"
                            onclick="return confirm('¿Seguro que deseas eliminar esta publicación? Esta acción no se puede deshacer.')">
                            Eliminar publicación
                        </button>
                    </form>

                    <form action="resolver_reportes.php" method="POST">
                        <input
                            type="hidden"
                            name="csrf"
                            value="<?= htmlspecialchars(
                                $_SESSION["csrf_admin_reportes"]
                            ) ?>"
                        >
                        <input
                            type="hidden"
                            name="reporte_id"
                            value="<?= (int) $reporte["id"] ?>"
                        >
                        <input
                            type="hidden"
                            name="accion"
                            value="banear_autor"
                        >
                        <button
                            class="banear"
                            type="submit"
                            onclick="return confirm('¿Seguro que deseas banear al autor de esta publicación?')">
                            Banear autor
                        </button>
                    </form>

                    <form action="resolver_reportes.php" method="POST">
                        <input
                            type="hidden"
                            name="csrf"
                            value="<?= htmlspecialchars(
                                $_SESSION["csrf_admin_reportes"]
                            ) ?>"
                        >
                        <input
                            type="hidden"
                            name="reporte_id"
                            value="<?= (int) $reporte["id"] ?>"
                        >
                        <input
                            type="hidden"
                            name="accion"
                            value="rechazar"
                        >
                        <button
                            class="rechazar"
                            type="submit"
                            onclick="return confirm('¿Seguro que deseas rechazar este reporte?')">
                            Rechazar reporte
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</main>
</body>
</html>