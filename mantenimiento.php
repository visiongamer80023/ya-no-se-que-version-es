<?php
declare(strict_types=1);

session_start();

require __DIR__ . "/conexion.php";
mysqli_set_charset($conex, "utf8mb4");

/*
 * Evitar que alguien permanezca atrapado aquí
 * cuando el mantenimiento ya terminó.
 */
$resultado = mysqli_query(
    $conex,
    "SELECT
        mantenimiento,
        mensaje_mantenimiento,
        mantenimiento_hasta
     FROM configuracion_sitio
     WHERE id = 1
     LIMIT 1"
);

$configuracion = $resultado
    ? mysqli_fetch_assoc($resultado)
    : null;

if ($resultado) {
    mysqli_free_result($resultado);
}

$activo =
    (int) ($configuracion["mantenimiento"] ?? 0) === 1;

$hasta =
    $configuracion["mantenimiento_hasta"] ?? null;

if (
    $activo &&
    $hasta !== null &&
    strtotime((string) $hasta) <= time()
) {
    mysqli_query(
        $conex,
        "UPDATE configuracion_sitio
         SET mantenimiento = 0,
             mantenimiento_hasta = NULL
         WHERE id = 1"
    );

    $activo = false;
}

mysqli_close($conex);

if (!$activo) {
    header("Location: index.php");
    exit();
}

$mensaje =
    (string) (
        $configuracion["mensaje_mantenimiento"]
        ?? "La página se encuentra temporalmente en mantenimiento."
    );
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Página en mantenimiento</title>

    <style>
        body {
            margin: 0;
            min-height: 100vh;

            display: flex;
            justify-content: center;
            align-items: center;

            font-family: Arial, sans-serif;
            background: #dae0e6;
        }

        .panel {
            width: 90%;
            max-width: 560px;

            background: white;
            padding: 36px;
            border-radius: 10px;
            text-align: center;

            box-shadow:
                0 2px 12px rgba(0, 0, 0, .18);
        }

        h1 {
            color: #ff4500;
        }

        .fecha {
            margin-top: 20px;
            color: #555;
        }

        a {
            color: #0079d3;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="panel">
    <h1>Sitio en mantenimiento</h1>

    <p>
        <?= htmlspecialchars($mensaje) ?>
    </p>

    <?php if ($hasta !== null): ?>
        <p class="fecha">
            Finalización estimada:

            <strong>
                <?= htmlspecialchars((string) $hasta) ?>
            </strong>
        </p>
    <?php endif; ?>

    <p>
        <a href="login.php">
            Acceso para administradores
        </a>
    </p>
</div>

</body>
</html>
