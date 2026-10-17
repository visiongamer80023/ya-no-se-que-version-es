<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

require __DIR__ . "/conexion.php";
mysqli_set_charset($conex, "utf8mb4");

$usuarioAdmin = (string) $_SESSION["usuario"];

/* Verificar que el usuario tenga rol de administrador O director */
$consultaAdmin = mysqli_prepare(
    $conex,
    "SELECT rol
     FROM datos
     WHERE usuario = ?
     LIMIT 1"
);

if ($consultaAdmin === false) {
    exit("No se pudo comprobar el rol del usuario.");
}

mysqli_stmt_bind_param(
    $consultaAdmin,
    "s",
    $usuarioAdmin
);

mysqli_stmt_execute($consultaAdmin);

$resultadoAdmin = mysqli_stmt_get_result($consultaAdmin);
$datosAdmin = mysqli_fetch_assoc($resultadoAdmin);

mysqli_stmt_close($consultaAdmin);

// CAMBIO: Aceptar tanto admin como director
if (!$datosAdmin || !in_array(($datosAdmin["rol"] ?? ""), ["admin", "director"])) {
    http_response_code(403);
    mysqli_close($conex);
    exit("No tienes permisos para entrar al panel de administración.");
}

/* Obtener usuarios */
$resultadoUsuarios = mysqli_query(
    $conex,
    "SELECT
        usuario,
        rol,
        baneado,
        motivo_ban,
        ban_hasta,
        fecha_registro
     FROM datos
     ORDER BY fecha_registro DESC"
);

$usuarios = [];

if ($resultadoUsuarios) {
    while ($fila = mysqli_fetch_assoc($resultadoUsuarios)) {
        $usuarios[] = $fila;
    }

    mysqli_free_result($resultadoUsuarios);
}

/* Obtener comunidades */
$resultadoComunidades = mysqli_query(
    $conex,
    "SELECT
        id,
        nombre,
        descripcion,
        creador,
        fecha
     FROM comunidades
     ORDER BY fecha DESC"
);

$comunidades = [];

if ($resultadoComunidades) {
    while ($fila = mysqli_fetch_assoc($resultadoComunidades)) {
        $comunidades[] = $fila;
    }

    mysqli_free_result($resultadoComunidades);
}

/* Obtener configuración de mantenimiento */
$resultadoConfiguracion = mysqli_query(
    $conex,
    "SELECT
        mantenimiento,
        mensaje_mantenimiento,
        mantenimiento_hasta,
        actualizado_por,
        actualizado_en
     FROM configuracion_sitio
     WHERE id = 1
     LIMIT 1"
);

$configuracion = $resultadoConfiguracion
    ? mysqli_fetch_assoc($resultadoConfiguracion)
    : null;

if ($resultadoConfiguracion) {
    mysqli_free_result($resultadoConfiguracion);
}

/* Estadísticas */
$estadisticas = [
    "usuarios" => 0,
    "comunidades" => 0,
    "publicaciones" => 0,
    "baneados" => 0,
    "reportes_pendientes" => 0
];

$consultasEstadisticas = [
    "usuarios" => "SELECT COUNT(*) AS total FROM datos",
    "comunidades" => "SELECT COUNT(*) AS total FROM comunidades",
    "publicaciones" => "SELECT COUNT(*) AS total FROM mensajes",
    "baneados" => "SELECT COUNT(*) AS total FROM datos WHERE baneado = 1",
    "reportes_pendientes" =>
        "SELECT COUNT(*) AS total
         FROM reportes
         WHERE estado = 'pendiente'"
];

foreach ($consultasEstadisticas as $clave => $sql) {
    $resultado = mysqli_query($conex, $sql);

    if ($resultado) {
        $fila = mysqli_fetch_assoc($resultado);
        $estadisticas[$clave] = (int) ($fila["total"] ?? 0);
        mysqli_free_result($resultado);
    }
}

mysqli_close($conex);

$mensaje = (string) ($_GET["mensaje"] ?? "");
$error = (string) ($_GET["error"] ?? "");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Panel de administración</title>

    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --fondo: #eef2f7;
            --superficie: #ffffff;
            --superficie-secundaria: #f8fafc;
            --texto: #1f2937;
            --texto-suave: #64748b;
            --borde: #dbe3ed;
            --azul: #2563eb;
            --azul-hover: #1d4ed8;
            --rojo: #dc2626;
            --rojo-hover: #b91c1c;
            --verde: #15803d;
            --verde-hover: #166534;
            --naranja: #d97706;
            --naranja-hover: #b45309;
            --sombra: 0 10px 28px rgba(15, 23, 42, .08);
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
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
                    var(--fondo) 280px
                );
            color: var(--texto);
        }

        .header {
            min-height: 76px;
            padding: 16px 4%;
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

            border-bottom: 3px solid #f97316;
            box-shadow: 0 5px 18px rgba(15, 23, 42, .24);
        }

        .header strong {
            font-size: clamp(22px, 3vw, 29px);
            letter-spacing: -.4px;
        }

        .header div {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            color: #e2e8f0;
        }

        .header a {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 40px;
            padding: 9px 15px;

            color: white;
            background: var(--azul);
            border: 1px solid rgba(255, 255, 255, .14);
            border-radius: 9px;

            text-decoration: none;
            font-weight: 700;

            transition:
                background .18s ease,
                transform .18s ease,
                box-shadow .18s ease;
        }

        .header a:hover {
            background: var(--azul-hover);
            transform: translateY(-1px);
            box-shadow: 0 7px 16px rgba(37, 99, 235, .28);
        }

        .layout {
            width: min(1380px, 95%);
            margin: 28px auto 48px;

            display: grid;
            grid-template-columns: 250px minmax(0, 1fr);
            gap: 22px;
        }

        .sidebar,
        .panel {
            background: rgba(255, 255, 255, .96);
            border: 1px solid var(--borde);
            border-radius: 14px;
            box-shadow: var(--sombra);
        }

        .sidebar {
            padding: 18px;
            align-self: start;
            position: sticky;
            top: 20px;
        }

        .sidebar h2 {
            margin: 0 0 14px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 21px;
            color: #0f172a;
        }

        .sidebar a {
            display: block;
            padding: 11px 12px;
            margin-bottom: 7px;

            color: #334155;
            background: transparent;
            border-radius: 9px;

            text-decoration: none;
            font-weight: 650;

            transition:
                color .18s ease,
                background .18s ease,
                transform .18s ease;
        }

        .sidebar a:hover {
            background: #eff6ff;
            color: var(--azul);
            transform: translateX(3px);
        }

        .contenido {
            min-width: 0;
        }

        .panel {
            padding: 24px;
            margin-bottom: 22px;
        }

        .panel h1,
        .panel h2 {
            margin-top: 0;
            color: #0f172a;
            letter-spacing: -.35px;
        }

        .panel h1 {
            font-size: clamp(25px, 3vw, 32px);
        }

        .panel h2 {
            font-size: clamp(21px, 2.5vw, 27px);
        }

        .estadisticas {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
        }

        .tarjeta {
            position: relative;
            overflow: hidden;

            background:
                linear-gradient(
                    180deg,
                    #ffffff,
                    #f8fafc
                );
            border: 1px solid #dbeafe;
            border-radius: 12px;
            padding: 20px 16px;
            text-align: center;

            transition:
                transform .2s ease,
                box-shadow .2s ease,
                border-color .2s ease;
        }

        .tarjeta::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--azul);
        }

        .tarjeta:hover {
            transform: translateY(-3px);
            border-color: #93c5fd;
            box-shadow: 0 10px 22px rgba(37, 99, 235, .11);
        }

        .tarjeta strong {
            display: block;
            margin-bottom: 5px;
            font-size: clamp(29px, 4vw, 38px);
            color: var(--azul);
            line-height: 1;
        }

        .aviso {
            padding: 13px 15px;
            margin-bottom: 18px;
            border-radius: 9px;
            border: 1px solid transparent;
            font-weight: 650;
        }

        .correcto {
            background: #ecfdf3;
            color: #166534;
            border-color: #bbf7d0;
        }

        .error {
            background: #fff1f2;
            color: #b91c1c;
            border-color: #fecdd3;
        }

        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            overflow: hidden;
            border: 1px solid var(--borde);
            border-radius: 10px;
            background: white;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            vertical-align: top;
            border-bottom: 1px solid #e5e7eb;
        }

        th {
            background: #f1f5f9;
            color: #334155;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        tbody tr {
            transition: background .16s ease;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        td a {
            color: var(--azul);
            font-weight: 700;
            text-decoration: none;
        }

        td a:hover {
            text-decoration: underline;
        }

        label {
            display: inline-block;
            margin-bottom: 6px;
            color: #334155;
            font-weight: 700;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 10px 11px;
            margin-bottom: 9px;

            color: var(--texto);
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 8px;

            font: inherit;
            outline: none;

            transition:
                border-color .18s ease,
                box-shadow .18s ease,
                background .18s ease;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .13);
            background: #fff;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        button {
            min-height: 40px;
            padding: 9px 13px;

            border: none;
            border-radius: 8px;
            cursor: pointer;

            font: inherit;
            font-weight: 750;

            box-shadow: 0 4px 10px rgba(15, 23, 42, .10);

            transition:
                background .18s ease,
                transform .18s ease,
                box-shadow .18s ease;
        }

        button:hover {
            transform: translateY(-1px);
            box-shadow: 0 7px 14px rgba(15, 23, 42, .15);
        }

        button:active {
            transform: translateY(0);
        }

        button:focus-visible,
        a:focus-visible {
            outline: 3px solid rgba(37, 99, 235, .25);
            outline-offset: 3px;
        }

        .boton-normal {
            background: var(--azul);
            color: white;
        }

        .boton-normal:hover {
            background: var(--azul-hover);
        }

        .boton-peligro {
            background: var(--rojo);
            color: white;
        }

        .boton-peligro:hover {
            background: var(--rojo-hover);
        }

        .boton-seguro {
            background: var(--verde);
            color: white;
        }

        .boton-seguro:hover {
            background: var(--verde-hover);
        }

        .boton-advertencia {
            background: var(--naranja);
            color: white;
        }

        .boton-advertencia:hover {
            background: var(--naranja-hover);
        }

        .acciones {
            display: grid;
            gap: 9px;
        }

        .acciones form {
            margin: 0;
        }

        .acciones button {
            width: 100%;
        }

        .estado-activo {
            color: #b91c1c;
            font-weight: 800;
        }

        .estado-inactivo {
            color: #15803d;
            font-weight: 800;
        }

        .insignia {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 5px 9px;
            border-radius: 999px;

            font-size: 12px;
            font-weight: 800;
            letter-spacing: .02em;
        }

        .admin {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .usuario {
            background: #e2e8f0;
            color: #334155;
            border: 1px solid #cbd5e1;
        }

        @media (max-width: 1100px) {
            .estadisticas {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 950px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .sidebar {
                position: static;
            }

            .sidebar a {
                display: inline-block;
                margin-right: 6px;
            }

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            td form {
                min-width: 190px;
            }
        }

        @media (max-width: 680px) {
            .header {
                align-items: flex-start;
                flex-direction: column;
            }

            .header div {
                width: 100%;
                flex-direction: column;
                align-items: flex-start;
            }

            .header a {
                width: 100%;
            }

            .layout {
                width: 94%;
                margin-top: 20px;
            }

            .panel {
                padding: 19px;
            }

            .estadisticas {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 450px) {
            .estadisticas {
                grid-template-columns: 1fr;
            }

            .sidebar a {
                display: block;
                margin-right: 0;
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

<header class="header">
    <strong>
        Panel de administración
    </strong>

    <div>
        <?= htmlspecialchars($datosAdmin["rol"] === "director" ? "Director" : "Administrador") ?>:
        <?= htmlspecialchars($usuarioAdmin) ?>

        &nbsp; | &nbsp;

        <a href="foro.php">
            Volver al foro
        </a>
    </div>
</header>

<div class="layout">

    <aside class="sidebar">
        <h2>Administración</h2>

        <a href="#inicio">Resumen</a>
        <a href="reportes_admin.php">
            Reportes
            <?php if ($estadisticas["reportes_pendientes"] > 0): ?>
                (<?= $estadisticas["reportes_pendientes"] ?>)
            <?php endif; ?>
        </a>
        <a href="#mantenimiento">Mantenimiento</a>
        <a href="#usuarios">Usuarios</a>
        <a href="#comunidades">Comunidades</a>
    </aside>

    <main class="contenido">

        <?php if ($mensaje !== ""): ?>
            <div class="aviso correcto">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="aviso error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <section class="panel" id="inicio">
            <h1>Resumen del sitio</h1>

            <div class="estadisticas">
                <div class="tarjeta">
                    <strong>
                        <?= $estadisticas["usuarios"] ?>
                    </strong>
                    Usuarios
                </div>

                <div class="tarjeta">
                    <strong>
                        <?= $estadisticas["comunidades"] ?>
                    </strong>
                    Comunidades
                </div>

                <div class="tarjeta">
                    <strong>
                        <?= $estadisticas["publicaciones"] ?>
                    </strong>
                    Publicaciones
                </div>

                <div class="tarjeta">
                    <strong>
                        <?= $estadisticas["baneados"] ?>
                    </strong>
                    Baneados
                </div>

                <div class="tarjeta">
                    <strong>
                        <?= $estadisticas["reportes_pendientes"] ?>
                    </strong>
                    Reportes pendientes
                </div>
            </div>
        </section>

        <section class="panel" id="mantenimiento">
            <h2>Modo mantenimiento</h2>

            <?php if ($configuracion): ?>
                <p>
                    Estado:

                    <?php if ((int) $configuracion["mantenimiento"] === 1): ?>
                        <span class="estado-activo">
                            ACTIVADO
                        </span>
                    <?php else: ?>
                        <span class="estado-inactivo">
                            DESACTIVADO
                        </span>
                    <?php endif; ?>
                </p>

                <p>
                    Última actualización:
                    <?= htmlspecialchars(
                        (string) ($configuracion["actualizado_en"] ?? "")
                    ) ?>
                </p>

                <form
                    action="admin_acciones.php"
                    method="POST"
                >
                    <input
                        type="hidden"
                        name="accion"
                        value="mantenimiento"
                    >

                    <label for="mensaje_mantenimiento">
                        Mensaje:
                    </label>

                    <textarea
                        id="mensaje_mantenimiento"
                        name="mensaje"
                        maxlength="500"
                        rows="4"
                    ><?= htmlspecialchars(
                        (string) $configuracion["mensaje_mantenimiento"]
                    ) ?></textarea>

                    <label for="mantenimiento_hasta">
                        Terminar automáticamente:
                    </label>

                    <input
                        id="mantenimiento_hasta"
                        type="datetime-local"
                        name="mantenimiento_hasta"
                    >

                    <label for="estado">
                        Estado:
                    </label>

                    <select id="estado" name="estado">
                        <option value="1">
                            Activar mantenimiento
                        </option>

                        <option value="0">
                            Desactivar mantenimiento
                        </option>
                    </select>

                    <button
                        class="boton-advertencia"
                        type="submit"
                    >
                        Guardar estado
                    </button>
                </form>
            <?php else: ?>
                <p class="error">
                    No existe la configuración del sitio.
                </p>
            <?php endif; ?>
        </section>

        <section class="panel" id="usuarios">
            <h2>Usuarios</h2>

            <table>
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Banear</th>
                        <th>Rol administrativo</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td>
                                <a
                                    href="perfil.php?usuario=<?= urlencode(
                                        (string) $usuario["usuario"]
                                    ) ?>"
                                >
                                    <?= htmlspecialchars(
                                        (string) $usuario["usuario"]
                                    ) ?>
                                </a>
                            </td>

                            <td>
                                <span class="insignia <?= $usuario["rol"] === "admin"
                                    ? "admin"
                                    : "usuario" ?>">
                                    <?= htmlspecialchars(
                                        (string) $usuario["rol"]
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?php if ((int) $usuario["baneado"] === 1): ?>
                                    <strong class="estado-activo">
                                        Baneado
                                    </strong>

                                    <?php if (!empty($usuario["motivo_ban"])): ?>
                                        <br>
                                        <?= htmlspecialchars(
                                            (string) $usuario["motivo_ban"]
                                        ) ?>
                                    <?php endif; ?>

                                    <?php if (!empty($usuario["ban_hasta"])): ?>
                                        <br>
                                        Hasta:
                                        <?= htmlspecialchars(
                                            (string) $usuario["ban_hasta"]
                                        ) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="estado-inactivo">
                                        Activo
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (
                                    (string) $usuario["usuario"] !==
                                    $usuarioAdmin
                                ): ?>
                                    <div class="acciones">
                                        <form
                                            action="admin_acciones.php"
                                            method="POST"
                                        >
                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="banear"
                                            >

                                            <input
                                                type="hidden"
                                                name="usuario"
                                                value="<?= htmlspecialchars(
                                                    (string) $usuario["usuario"]
                                                ) ?>"
                                            >

                                            <input
                                                type="text"
                                                name="motivo"
                                                maxlength="255"
                                                placeholder="Motivo del ban"
                                                required
                                            >

                                            <input
                                                type="datetime-local"
                                                name="ban_hasta"
                                            >

                                            <button
                                                class="boton-peligro"
                                                type="submit"
                                            >
                                                Banear
                                            </button>
                                        </form>

                                        <form
                                            action="admin_acciones.php"
                                            method="POST"
                                        >
                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="desbanear"
                                            >

                                            <input
                                                type="hidden"
                                                name="usuario"
                                                value="<?= htmlspecialchars(
                                                    (string) $usuario["usuario"]
                                                ) ?>"
                                            >

                                            <button
                                                class="boton-seguro"
                                                type="submit"
                                            >
                                                Quitar ban
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    No puedes banear tu propia cuenta.
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if (
                                    (string) $usuario["usuario"] !==
                                    $usuarioAdmin
                                ): ?>
                                    <div class="acciones">
                                        <form
                                            action="admin_acciones.php"
                                            method="POST"
                                        >
                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="hacer_admin"
                                            >

                                            <input
                                                type="hidden"
                                                name="usuario"
                                                value="<?= htmlspecialchars(
                                                    (string) $usuario["usuario"]
                                                ) ?>"
                                            >

                                            <button
                                                class="boton-normal"
                                                type="submit"
                                            >
                                                Hacer admin
                                            </button>
                                        </form>

                                        <form
                                            action="admin_acciones.php"
                                            method="POST"
                                        >
                                            <input
                                                type="hidden"
                                                name="accion"
                                                value="quitar_admin"
                                            >

                                            <input
                                                type="hidden"
                                                name="usuario"
                                                value="<?= htmlspecialchars(
                                                    (string) $usuario["usuario"]
                                                ) ?>"
                                            >

                                            <button
                                                class="boton-advertencia"
                                                type="submit"
                                            >
                                                Quitar admin
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    Cuenta actual
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="panel" id="comunidades">
            <h2>Comunidades</h2>

            <table>
                <thead>
                    <tr>
                        <th>Comunidad</th>
                        <th>Descripción</th>
                        <th>Creador</th>
                        <th>Fecha</th>
                        <th>Acción</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($comunidades as $comunidad): ?>
                        <tr>
                            <td>
                                <a
                                    href="foro.php?foro=<?= (int) $comunidad["id"] ?>"
                                >
                                    r/<?= htmlspecialchars(
                                        (string) $comunidad["nombre"]
                                    ) ?>
                                </a>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) $comunidad["descripcion"]
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) $comunidad["creador"]
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    (string) $comunidad["fecha"]
                                ) ?>
                            </td>

                            <td>
                                <form
                                    action="admin_acciones.php"
                                    method="POST"
                                    onsubmit="
                                        return confirm(
                                            '¿Eliminar esta comunidad y todas sus publicaciones?'
                                        );
                                    "
                                >
                                    <input
                                        type="hidden"
                                        name="accion"
                                        value="eliminar_comunidad"
                                    >

                                    <input
                                        type="hidden"
                                        name="comunidad_id"
                                        value="<?= (int) $comunidad["id"] ?>"
                                    >

                                    <button
                                        class="boton-peligro"
                                        type="submit"
                                    >
                                        Eliminar comunidad
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

    </main>
</div>

</body>
</html>