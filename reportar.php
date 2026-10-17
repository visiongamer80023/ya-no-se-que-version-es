<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

require __DIR__ . "/verificar_acceso.php";
require_once __DIR__ . "/notificar_reporte.php";

$usuario = (string) $_SESSION["usuario"];

$publicacionId = filter_input(
    INPUT_GET,
    "publicacion",
    FILTER_VALIDATE_INT
);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $publicacionId = filter_input(
        INPUT_POST,
        "publicacion_id",
        FILTER_VALIDATE_INT
    );

    $token = (string) ($_POST["csrf"] ?? "");

    if (
        !isset($_SESSION["csrf_reportes"]) ||
        !hash_equals($_SESSION["csrf_reportes"], $token)
    ) {
        exit("La sesión del formulario venció.");
    }

    $permitidos = [
        "spam",
        "acoso",
        "insultos",
        "contenido_inapropiado",
        "violencia",
        "informacion_falsa",
        "otro"
    ];

    $motivo = (string) ($_POST["motivo"] ?? "");
    $descripcion = trim((string) ($_POST["descripcion"] ?? ""));

    if (!$publicacionId) {
        exit("La publicación indicada no es válida.");
    }

    if (!in_array($motivo, $permitidos, true)) {
        exit("Selecciona un motivo válido.");
    }

    if (strlen($descripcion) > 1000) {
        exit("La descripción no puede superar 1000 caracteres.");
    }

    $stmt = mysqli_prepare(
        $conex,
        "SELECT m.usuario, c.nombre
         FROM mensajes m
         LEFT JOIN comunidades c
            ON c.id = m.comunidad_id
         WHERE m.id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, "i", $publicacionId);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $post = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    if (!$post) {
        exit("La publicación ya no existe.");
    }

    if ((string) $post["usuario"] === $usuario) {
        exit("No puedes reportar tu propia publicación.");
    }

    $stmt = mysqli_prepare(
        $conex,
        "INSERT INTO reportes
            (publicacion_id, reportado_por, motivo, descripcion)
         VALUES (?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "isss",
        $publicacionId,
        $usuario,
        $motivo,
        $descripcion
    );

    try {
        mysqli_stmt_execute($stmt);
    } catch (mysqli_sql_exception $error) {
        mysqli_stmt_close($stmt);

        if ((int) $error->getCode() === 1062) {
            header(
                "Location: reportar.php?publicacion=" .
                $publicacionId .
                "&duplicado=1"
            );
            exit();
        }

        throw $error;
    }

    $reporteId = mysqli_insert_id($conex);
    mysqli_stmt_close($stmt);

    notificarReporteAdministradores(
        $conex,
        [
            "id" => $reporteId,
            "publicacion_id" => $publicacionId,
            "reportado_por" => $usuario,
            "motivo" => $motivo,
            "descripcion" => $descripcion,
            "comunidad" => (string) ($post["nombre"] ?? "Desconocida")
        ]
    );

    unset($_SESSION["csrf_reportes"]);

    header(
        "Location: reportar.php?publicacion=" .
        $publicacionId .
        "&enviado=1"
    );
    exit();
}

if (!$publicacionId) {
    exit("La publicación indicada no es válida.");
}

$_SESSION["csrf_reportes"] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >
    <title>Reportar publicación</title>
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
            width: 92%;
            max-width: 520px;
            padding: 24px;
            background: white;
            border-radius: 7px;
        }

        select,
        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 12px;
            box-sizing: border-box;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        button {
            background: #b00020;
            color: white;
            border: 0;
            border-radius: 20px;
            padding: 9px 16px;
            cursor: pointer;
            font-weight: bold;
        }

        a {
            color: #0079d3;
        }

        .aviso {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 14px;
        }

        .correcto {
            background: #e5f6e8;
            color: #176528;
        }

        .error {
            background: #ffe7eb;
            color: #b00020;
        }
    </style>
</head>
<body>
<div class="panel">
    <a href="javascript:history.back()">← Volver</a>

    <h1>Reportar publicación #<?= (int) $publicacionId ?></h1>

    <?php if (isset($_GET["enviado"])): ?>
        <div class="aviso correcto">
            El reporte fue enviado a los administradores.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET["duplicado"])): ?>
        <div class="aviso error">
            Ya habías reportado esta publicación.
        </div>
    <?php endif; ?>

    <form
    method="POST"
    action="reportar.php?publicacion=<?= (int) $publicacionId ?>"
>
        <input
            type="hidden"
            name="publicacion_id"
            value="<?= (int) $publicacionId ?>"
        >

        <input
            type="hidden"
            name="csrf"
            value="<?= htmlspecialchars($_SESSION["csrf_reportes"]) ?>"
        >

        <label for="motivo">Motivo:</label>

        <select id="motivo" name="motivo" required>
            <option value="">Selecciona una opción</option>
            <option value="spam">Spam</option>
            <option value="acoso">Acoso</option>
            <option value="insultos">Insultos</option>
            <option value="contenido_inapropiado">
                Contenido inapropiado
            </option>
            <option value="violencia">Violencia</option>
            <option value="informacion_falsa">
                Información falsa
            </option>
            <option value="otro">Otro</option>
        </select>

        <label for="descripcion">Detalles:</label>

        <textarea
            id="descripcion"
            name="descripcion"
            maxlength="1000"
            placeholder="Explica el problema."
        ></textarea>

        <button type="submit">
            Enviar reporte
        </button>
    </form>
</div>
</body>
</html>
