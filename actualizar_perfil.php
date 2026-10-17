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

if (!$datos || ($datos["rol"] ?? "") !== "admin") {
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
        * {
            box-sizing: border-box;
        }

        :root {
            --fondo: #eef2f7;
            --superficie: #ffffff;
            --superficie-secundaria: #f7f9fc;
            --texto: #1f2937;
            --texto-secundario: #64748b;
            --borde: #dbe3ed;
            --azul: #2563eb;
            --azul-hover: #1d4ed8;
            --naranja: #f97316;
            --rojo: #dc2626;
            --rojo-hover: #b91c1c;
            --amarillo: #d97706;
            --amarillo-hover: #b45309;
            --gris: #64748b;
            --gris-hover: #475569;
            --sombra:
                0 8px 24px rgba(15, 23, 42, 0.08);
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family:
                Inter,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            background:
                linear-gradient(
                    180deg,
                    #e8eef6 0,
                    var(--fondo) 260px
                );
            color: var(--texto);
        }

        header {
            min-height: 76px;
            padding: 16px 5%;
            background:
                linear-gradient(
                    135deg,
                    #111827,
                    #1e293b
                );
            color: white;

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;

            border-bottom:
                3px solid var(--naranja);
            box-shadow:
                0 5px 18px rgba(15, 23, 42, 0.24);
        }

        header strong {
            font-size: clamp(21px, 3vw, 28px);
            letter-spacing: -0.4px;
        }

        header a {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 42px;
            padding: 10px 18px;

            background: var(--azul);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 10px;

            text-decoration: none;
            font-weight: 700;

            transition:
                transform 0.18s ease,
                background 0.18s ease,
                box-shadow 0.18s ease;
        }

        header a:hover {
            background: var(--azul-hover);
            transform: translateY(-1px);
            box-shadow:
                0 7px 16px rgba(37, 99, 235, 0.28);
        }

        main {
            width: min(1120px, 92%);
            margin: 30px auto 50px;
        }

        .filtros {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;

            margin-bottom: 24px;
            padding: 16px;

            background: rgba(255, 255, 255, 0.9);
            border: 1px solid var(--borde);
            border-radius: 14px;
            box-shadow: var(--sombra);
            backdrop-filter: blur(8px);
        }

        .filtros a {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 42px;
            padding: 10px 15px;

            background: var(--superficie-secundaria);
            color: #334155;
            border: 1px solid var(--borde);
            border-radius: 9px;

            text-decoration: none;
            font-size: 14px;
            font-weight: 750;

            transition:
                color 0.18s ease,
                border-color 0.18s ease,
                background 0.18s ease,
                transform 0.18s ease;
        }

        .filtros a:hover {
            background: #eff6ff;
            color: var(--azul);
            border-color: #93c5fd;
            transform: translateY(-1px);
        }

        .reporte {
            position: relative;
            overflow: hidden;

            margin-bottom: 22px;
            padding: 25px 27px;

            background: var(--superficie);
            border: 1px solid var(--borde);
            border-left: 5px solid var(--naranja);
            border-radius: 14px;
            box-shadow: var(--sombra);

            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;
        }

        .reporte::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 110px;
            height: 110px;

            background:
                radial-gradient(
                    circle at top right,
                    rgba(249, 115, 22, 0.12),
                    transparent 68%
                );

            pointer-events: none;
        }

        .reporte:hover {
            transform: translateY(-2px);
            border-color: #cbd5e1;
            box-shadow:
                0 14px 34px rgba(15, 23, 42, 0.11);
        }

        .reporte h2 {
            position: relative;
            margin: 0 0 18px;

            color: #c2410c;
            font-size: clamp(22px, 3vw, 29px);
            letter-spacing: -0.4px;
        }

        .reporte p {
            position: relative;
            display: grid;
            grid-template-columns: 155px minmax(0, 1fr);
            gap: 12px;

            margin: 9px 0;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .reporte strong {
            color: #334155;
            font-weight: 750;
        }

        .contenido {
            position: relative;
            margin-top: 18px;
            padding: 17px 18px;

            background: #f8fafc;
            color: #334155;
            border: 1px solid #e2e8f0;
            border-left: 4px solid var(--azul);
            border-radius: 9px;

            line-height: 1.65;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        img {
            position: relative;
            display: block;

            max-width: 100%;
            max-height: 420px;
            margin-top: 18px;

            object-fit: contain;
            background: #f8fafc;
            border: 1px solid var(--borde);
            border-radius: 11px;
            box-shadow:
                0 6px 18px rgba(15, 23, 42, 0.12);
        }

        .acciones {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;

            margin-top: 22px;
            padding-top: 18px;
            border-top: 1px solid #e5e7eb;
        }

        .acciones form {
            margin: 0;
        }

        button {
            min-height: 42px;
            padding: 10px 16px;

            border: 0;
            border-radius: 9px;
            color: white;

            font-family: inherit;
            font-size: 14px;
            font-weight: 750;
            cursor: pointer;

            box-shadow:
                0 4px 10px rgba(15, 23, 42, 0.12);

            transition:
                transform 0.18s ease,
                filter 0.18s ease,
                box-shadow 0.18s ease;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow:
                0 7px 15px rgba(15, 23, 42, 0.17);
        }

        button:active {
            transform: translateY(0);
        }

        button:focus-visible,
        a:focus-visible {
            outline: 3px solid rgba(37, 99, 235, 0.28);
            outline-offset: 3px;
        }

        .eliminar {
            background: var(--rojo);
        }

        .eliminar:hover {
            background: var(--rojo-hover);
        }

        .banear {
            background: var(--amarillo);
        }

        .banear:hover {
            background: var(--amarillo-hover);
        }

        .rechazar {
            background: var(--gris);
        }

        .rechazar:hover {
            background: var(--gris-hover);
        }

        @media (max-width: 700px) {
            header {
                padding: 15px 4%;
                align-items: flex-start;
                flex-direction: column;
            }

            header a {
                width: 100%;
            }

            main {
                width: 94%;
                margin-top: 20px;
            }

            .filtros {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .filtros a {
                text-align: center;
            }

            .reporte {
                padding: 21px 18px;
            }

            .reporte p {
                grid-template-columns: 1fr;
                gap: 2px;
                padding-bottom: 8px;
                border-bottom: 1px dashed #e2e8f0;
            }

            .acciones {
                display: grid;
            }

            .acciones form,
            .acciones button {
                width: 100%;
            }
        }

        @media (max-width: 430px) {
            .filtros {
                grid-template-columns: 1fr;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition: none !important;
            }
        }
    </style>
</head>
<body>
<header>
    <strong>Administración de reportes</strong>
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
                    <?php foreach ([
                        "eliminar_contenido" => ["Eliminar publicación", "eliminar"],
                        "banear_autor" => ["Banear autor", "banear"],
                        "rechazar" => ["Rechazar reporte", "rechazar"]
                    ] as $accion => $boton): ?>
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
                                value="<?= htmlspecialchars($accion) ?>"
                            >
                            <button
                                class="<?= htmlspecialchars($boton[1]) ?>"
                                type="submit"
                            >
                                <?= htmlspecialchars($boton[0]) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</main>
</body>
</html>