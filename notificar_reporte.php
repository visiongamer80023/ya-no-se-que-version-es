<?php
declare(strict_types=1);

function notificarReporteAdministradores(
    mysqli $conex,
    array $reporte
): bool {
    $autoload = __DIR__ . "/vendor/autoload.php";
    $configPath = __DIR__ . "/correo_config.php";

    if (!is_file($autoload) || !is_file($configPath)) {
        return false;
    }

    require_once $autoload;
    $config = require $configPath;

    $resultado = mysqli_query(
        $conex,
        "SELECT correo
         FROM datos
         WHERE rol = 'admin'
           AND recibir_reportes_correo = 1
           AND correo IS NOT NULL
           AND correo <> ''"
    );

    if (!$resultado) {
        return false;
    }

    $correos = [];

    while ($fila = mysqli_fetch_assoc($resultado)) {
        $correo = trim((string) ($fila["correo"] ?? ""));

        if (filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $correos[] = $correo;
        }
    }

    mysqli_free_result($resultado);

    if (!$correos) {
        return false;
    }

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) $config["host"];
        $mail->SMTPAuth = true;
        $mail->Username = (string) $config["usuario"];
        $mail->Password = (string) $config["password"];
        $mail->Port = (int) $config["puerto"];
        $mail->CharSet = "UTF-8";

        $mail->SMTPSecure =
            (($config["seguridad"] ?? "tls") === "ssl")
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $mail->setFrom(
            (string) $config["remitente_correo"],
            (string) $config["remitente_nombre"]
        );

        foreach ($correos as $correo) {
            $mail->addAddress($correo);
        }

        $id = (int) $reporte["id"];
        $publicacion = (int) $reporte["publicacion_id"];
        $usuario = htmlspecialchars((string) $reporte["reportado_por"]);
        $motivo = htmlspecialchars((string) $reporte["motivo"]);
        $descripcion = nl2br(
            htmlspecialchars((string) $reporte["descripcion"])
        );
        $comunidad = htmlspecialchars((string) $reporte["comunidad"]);

        $mail->isHTML(true);
        $mail->Subject = "Nuevo reporte #{$id}";

        $mail->Body = <<<HTML
<div style="font-family: Arial, sans-serif; max-width: 650px;">
    <h2 style="color: #b00020;">
        Nuevo reporte recibido
    </h2>

    <p>
        <strong>Reporte:</strong> #{$id}
    </p>

    <p>
        <strong>Publicación:</strong> #{$publicacion}
    </p>

    <p>
        <strong>Comunidad:</strong> r/{$comunidad}
    </p>

    <p>
        <strong>Autor reportado:</strong> {$autor}
    </p>

    <p>
        <strong>Reportado por:</strong> {$usuario}
    </p>

    <p>
        <strong>Motivo:</strong> {$motivo}
    </p>

    <p>
        <strong>Descripción:</strong>
        <br>
        {$descripcion}
    </p>

    <hr>

    <p>
        <a href="http://localhost/foro/reportes_admin.php">
            Abrir panel de reportes
        </a>
    </p>
</div>
HTML;

        $mail->send();
        return true;
    } catch (Throwable $error) {
        error_log(
            "No se pudo enviar correo de reporte: " .
            $error->getMessage()
        );
        return false;
    }
}