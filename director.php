<?php

declare(strict_types=1);

session_start();

/*
 * Comprobar que el usuario haya iniciado sesión.
 */
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

/*
 * Comprobar que el usuario sea director.
 */
if (($_SESSION["rol"] ?? "") !== "director") {
    http_response_code(403);

    exit(
        "<h2>Acceso denegado</h2>
         <p>No tienes permisos para acceder al panel de director.</p>
         <a href='foro.php'>Volver al foro</a>"
    );
}

/*
 * Conexión a la base de datos.
 */
require_once "conexion.php";


/*
 * Obtener carreras.
 */
$carreras = mysqli_query(
    $conex,
    "SELECT id, nombre
     FROM carreras
     ORDER BY nombre ASC"
);

if (!$carreras) {
    die(
        "Error al consultar carreras: " .
        htmlspecialchars(mysqli_error($conex))
    );
}


/*
 * Obtener comunidades junto con su carrera.
 */
$comunidades = mysqli_query(
    $conex,
    "SELECT
        c.id,
        c.nombre,
        c.creador,
        c.carrera_id,
        ca.nombre AS carrera
     FROM comunidades c
     LEFT JOIN carreras ca
        ON c.carrera_id = ca.id
     ORDER BY c.id DESC"
);

if (!$comunidades) {
    die(
        "Error al consultar comunidades: " .
        htmlspecialchars(mysqli_error($conex))
    );
}

?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Director - Foro</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            background: #f2f2f2;
            margin: 0;
            padding: 30px;
        }

        .contenedor {
            max-width: 1000px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,.1);
        }

        h1,
        h2 {
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        th {
            background: #eee;
        }

        .general {
            color: #777;
        }

    </style>

</head>

<body>

<div class="contenedor">

    <h1>Panel de Director</h1>

    <h2>Carreras</h2>

    <table>

        <tr>
            <th>ID</th>
            <th>Nombre</th>
        </tr>

        <?php while ($carrera = mysqli_fetch_assoc($carreras)): ?>

            <tr>

                <td>
                    <?= htmlspecialchars((string) $carrera["id"]) ?>
                </td>

                <td>
                    <?= htmlspecialchars($carrera["nombre"]) ?>
                </td>

            </tr>

        <?php endwhile; ?>

    </table>


    <h2>Comunidades</h2>

    <table>

        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Creador</th>
            <th>Carrera</th>
        </tr>

        <?php while ($comunidad = mysqli_fetch_assoc($comunidades)): ?>

            <tr>

                <td>
                    <?= htmlspecialchars((string) $comunidad["id"]) ?>
                </td>

                <td>
                    <?= htmlspecialchars($comunidad["nombre"]) ?>
                </td>

                <td>
                    <?= htmlspecialchars((string) $comunidad["creador"]) ?>
                </td>

                <td>

                    <?php if ($comunidad["carrera"] !== null): ?>

                        <?= htmlspecialchars($comunidad["carrera"]) ?>

                    <?php else: ?>

                        <span class="general">
                            General
                        </span>

                    <?php endif; ?>

                </td>

            </tr>

        <?php endwhile; ?>

    </table>

</div>

</body>

</html>