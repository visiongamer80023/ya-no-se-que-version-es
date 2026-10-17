<?php
declare(strict_types=1);
session_start();
require __DIR__ . "/verificar_acceso.php";
require __DIR__ . "/conexion.php";
mysqli_set_charset($conex, "utf8mb4");

$usuarioActual = (string) $_SESSION["usuario"];
$rolActual = (string) ($_SESSION["rol"] ?? "");

// Si ya tiene carrera asignada o es staff, redirigir al foro
if ($rolActual !== "alumno" || !empty($_SESSION["carrera_id"])) {
    header("Location: foro.php");
    exit();
}

$carreras = [];
$resultado = mysqli_query($conex, "SELECT id, nombre FROM carreras ORDER BY nombre ASC");
while ($fila = mysqli_fetch_assoc($resultado)) {
    $carreras[] = $fila;
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $carreraId = filter_input(INPUT_POST, "carrera_id", FILTER_VALIDATE_INT);
    
    if (!$carreraId) {
        $error = "Selecciona una carrera válida.";
    } else {
        // Verificar que la carrera exista
        $check = mysqli_prepare($conex, "SELECT COUNT(*) FROM carreras WHERE id = ?");
        mysqli_stmt_bind_param($check, "i", $carreraId);
        mysqli_stmt_execute($check);
        mysqli_stmt_bind_result($check, $existe);
        mysqli_stmt_fetch($check);
        mysqli_stmt_close($check);
        
        if ((int)$existe === 0) {
            $error = "La carrera seleccionada no existe.";
        } else {
            // Actualizar usuario y sesión
            $update = mysqli_prepare($conex, "UPDATE datos SET carrera_id = ? WHERE usuario = ?");
            mysqli_stmt_bind_param($update, "is", $carreraId, $usuarioActual);
            
            if (mysqli_stmt_execute($update)) {
                $_SESSION["carrera_id"] = $carreraId;
                mysqli_stmt_close($update);
                mysqli_close($conex);
                header("Location: foro.php?bienvenida=1");
                exit();
            } else {
                $error = "Error al guardar tu selección. Intenta de nuevo.";
            }
            mysqli_stmt_close($update);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Carrera - GTU Foro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #1e3a8a; --accent: #2563eb; --bg: #f8fafc; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--bg); padding: 2rem; }
        .card { background: white; padding: 2.5rem; border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.08); width: 100%; max-width: 450px; }
        h1 { color: var(--primary); margin-bottom: 0.5rem; font-size: 1.75rem; }
        p { color: #64748b; margin-bottom: 1.5rem; line-height: 1.5; }
        select { width: 100%; padding: 0.9rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1rem; margin-bottom: 1rem; outline: none; transition: 0.3s; }
        select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        button { width: 100%; padding: 0.9rem; background: var(--accent); color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: 0.2s; }
        button:hover { background: var(--primary); }
        .error { background: #fee2e2; color: #991b1b; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; text-align: center; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Bienvenido, <?= htmlspecialchars($usuarioActual) ?></h1>
        <p>Para personalizar tu experiencia en GTU Foro, necesitamos saber a qué carrera perteneces. Esta información nos ayuda a mostrarte los foros relevantes.</p>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <select name="carrera_id" required>
                <option value="">-- Selecciona tu carrera --</option>
                <?php foreach ($carreras as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Continuar al Foro</button>
        </form>
    </div>
</body>
</html>