<?php
declare(strict_types=1);

ini_set("display_errors", "0");
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");

session_start();
require __DIR__ . "/verificar_acceso.php";
function responder(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);

    echo json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit();
}

function normalizarTexto(string $texto): string
{
    $texto = strtolower($texto);

    return strtr($texto, [
        "Á" => "a", "É" => "e", "Í" => "i", "Ó" => "o", "Ú" => "u",
        "á" => "a", "é" => "e", "í" => "i", "ó" => "o", "ú" => "u",
        "Ü" => "u", "ü" => "u", "Ñ" => "n", "ñ" => "n"
    ]);
}

try {
    if (!isset($_SESSION["usuario"])) {
        responder([
            "ok" => false,
            "error" => "La sesión terminó. Inicia sesión nuevamente."
        ], 401);
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        responder([
            "ok" => false,
            "error" => "Método no permitido."
        ], 405);
    }

    require __DIR__ . "/conexion.php";
    mysqli_set_charset($conex, "utf8mb4");

    $usuario = (string) $_SESSION["usuario"];
    $mensaje = trim((string) ($_POST["mensaje"] ?? ""));
    $comunidadId = filter_input(INPUT_POST, "comunidad_id", FILTER_VALIDATE_INT);
    $rutaImagen = null;

    if (!$comunidadId) {
        responder([
            "ok" => false,
            "error" => "El foro seleccionado no es válido."
        ], 422);
    }

    $comprobarForo = mysqli_prepare(
        $conex,
        "SELECT COUNT(*) FROM comunidades WHERE id = ?"
    );

    mysqli_stmt_bind_param($comprobarForo, "i", $comunidadId);
    mysqli_stmt_execute($comprobarForo);
    mysqli_stmt_bind_result($comprobarForo, $cantidadForos);
    mysqli_stmt_fetch($comprobarForo);
    mysqli_stmt_close($comprobarForo);

    if ((int) $cantidadForos === 0) {
        responder([
            "ok" => false,
            "error" => "Ese foro ya no existe."
        ], 404);
    }

    if ($mensaje === "" && empty($_FILES["imagen"]["name"])) {
        responder([
            "ok" => false,
            "error" => "Debes escribir un mensaje o seleccionar una imagen."
        ], 422);
    }

    if (strlen($mensaje) > 8000) {
        responder([
            "ok" => false,
            "error" => "El mensaje es demasiado largo."
        ], 422);
    }

    $resultadoPalabras = mysqli_query(
        $conex,
        "SELECT palabras FROM palabras_prohibidas"
    );

    if ($resultadoPalabras === false) {
        responder([
            "ok" => false,
            "error" => "No se pudo consultar el filtro de palabras."
        ], 500);
    }

    $mensajeNormalizado = normalizarTexto($mensaje);

    while ($fila = mysqli_fetch_assoc($resultadoPalabras)) {
        $contenido = trim((string) ($fila["palabras"] ?? ""));
        $lista = preg_split('/\r\n|\r|\n/', $contenido) ?: [];

        foreach ($lista as $palabra) {
            $palabra = trim($palabra);

            if ($palabra === "") {
                continue;
            }

            $patron = '/(?<![a-z0-9])' .
                preg_quote(normalizarTexto($palabra), '/') .
                '(?![a-z0-9])/i';

            if (preg_match($patron, $mensajeNormalizado) === 1) {
                responder([
                    "ok" => false,
                    "error" => "Tu publicación contiene una palabra o frase prohibida."
                ], 422);
            }
        }
    }

    mysqli_free_result($resultadoPalabras);

    if (!empty($_FILES["imagen"]["name"])) {
        if ($_FILES["imagen"]["error"] !== UPLOAD_ERR_OK) {
            responder([
                "ok" => false,
                "error" => "No se pudo subir la imagen."
            ], 422);
        }

        if ($_FILES["imagen"]["size"] > 5 * 1024 * 1024) {
            responder([
                "ok" => false,
                "error" => "La imagen no puede superar los 5 MB."
            ], 422);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES["imagen"]["tmp_name"]);

        $permitidos = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp",
            "image/gif" => "gif"
        ];

        if (!isset($permitidos[$mime])) {
            responder([
                "ok" => false,
                "error" => "Solo se permiten imágenes JPG, PNG, WEBP o GIF."
            ], 422);
        }

        $carpeta = __DIR__ . "/uploads";

        if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true)) {
            responder([
                "ok" => false,
                "error" => "No se pudo crear la carpeta uploads."
            ], 500);
        }

        $archivo = bin2hex(random_bytes(16)) . "." . $permitidos[$mime];
        $destino = $carpeta . "/" . $archivo;

        if (!move_uploaded_file($_FILES["imagen"]["tmp_name"], $destino)) {
            responder([
                "ok" => false,
                "error" => "No se pudo guardar la imagen."
            ], 500);
        }

        $rutaImagen = "uploads/" . $archivo;
    }

    $consulta = mysqli_prepare(
        $conex,
        "INSERT INTO mensajes
            (comunidad_id, usuario, mensaje, imagen)
         VALUES (?, ?, ?, ?)"
    );

    if ($consulta === false) {
        responder([
            "ok" => false,
            "error" => "No se pudo preparar la publicación."
        ], 500);
    }

    mysqli_stmt_bind_param(
        $consulta,
        "isss",
        $comunidadId,
        $usuario,
        $mensaje,
        $rutaImagen
    );

    if (!mysqli_stmt_execute($consulta)) {
        responder([
            "ok" => false,
            "error" => "No se pudo guardar la publicación: " .
                       mysqli_stmt_error($consulta)
        ], 500);
    }

    mysqli_stmt_close($consulta);
    mysqli_close($conex);

    responder([
        "ok" => true,
        "imagen" => $rutaImagen
    ]);
} catch (Throwable $error) {
    responder([
        "ok" => false,
        "error" => "Error interno: " . $error->getMessage()
    ], 500);
}