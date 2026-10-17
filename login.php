<?php
session_start();

// Si ya hay sesión, ir al foro directamente
if (isset($_SESSION["usuario"]) && $_SESSION["usuario"] !== "") {
    header("Location: foro.php");
    exit();
}

require __DIR__ . "/conexion.php";
mysqli_set_charset($conex, "utf8mb4");

$mensaje_error = "";

// Procesar login
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"]) && $_POST["accion"] === "login") {
    $usuario = trim($_POST["usuario"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";
    
    if (strlen($usuario) !== 4 || !ctype_digit($usuario)) {
        $mensaje_error = "El usuario debe tener exactamente 4 números.";
    } elseif (empty($contrasena)) {
        $mensaje_error = "La contraseña es obligatoria.";
    } else {
        $consulta = mysqli_prepare(
            $conex,
            "SELECT usuario, contraseña, rol, baneado FROM datos WHERE usuario = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($consulta, "s", $usuario);
        mysqli_stmt_execute($consulta);
        mysqli_stmt_bind_result($consulta, $usuarioBD, $contrasenaBD, $rolBD, $baneado);
        
        if (mysqli_stmt_fetch($consulta)) {
            if ($contrasena === $contrasenaBD) {
                if ($baneado) {
                    $mensaje_error = "Tu cuenta ha sido suspendida.";
                } else {
                    // Limpiar sesión anterior y crear nueva
                    session_destroy();
                    session_start();
                    $_SESSION["usuario"] = $usuarioBD;
                    $_SESSION["rol"] = $rolBD;
                    header("Location: foro.php");
                    exit();
                }
            } else {
                $mensaje_error = "Usuario o contraseña incorrectos.";
            }
        } else {
            $mensaje_error = "Usuario o contraseña incorrectos.";
        }
        mysqli_stmt_close($consulta);
    }
}

if (isset($conex)) mysqli_close($conex);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - GTU Foro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; }
        body { min-height: 100vh; display: flex; }
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, rgba(15,23,42,0.88), rgba(30,58,138,0.88)),
                        url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=1920');
            background-size: cover; background-position: center;
            color: white; padding: 4rem;
            display: flex; flex-direction: column; justify-content: center;
        }
        .login-left h1 { font-size: 3.5rem; font-weight: 800; margin-bottom: 0.5rem; }
        .login-left h1 span { color: #60a5fa; }
        .login-left .subtitle { font-size: 1.1rem; opacity: 0.9; margin-bottom: 3rem; }
        .features { list-style: none; }
        .features li { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; font-size: 1rem; }
        .features li i { width: 36px; height: 36px; background: rgba(255,255,255,0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .login-right { flex: 1; background: white; padding: 4rem; display: flex; flex-direction: column; justify-content: center; align-items: center; }
        .login-form-container { width: 100%; max-width: 420px; }
        .login-form-container h2 { font-size: 2rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem; text-align: center; }
        .login-form-container > p { color: #64748b; text-align: center; margin-bottom: 2rem; font-size: 0.95rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem; }
        .input-wrapper { position: relative; }
        .input-wrapper input { width: 100%; padding: 0.875rem 1rem 0.875rem 2.75rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; background: #f8fafc; }
        .input-wrapper input:focus { outline: none; border-color: #2563eb; background: white; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .input-wrapper i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .btn-login { width: 100%; padding: 0.875rem; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s; margin-top: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .btn-login:hover { background: #1d4ed8; transform: translateY(-1px); }
        .alert { padding: 0.875rem 1rem; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .links { text-align: center; margin-top: 1.5rem; font-size: 0.9rem; color: #64748b; }
        .links a { color: #2563eb; text-decoration: none; font-weight: 500; }
        .links a:hover { text-decoration: underline; }
        @media (max-width: 900px) { .login-left { display: none; } .login-right { padding: 2rem; } }
    </style>
</head>
<body>
    <div class="login-left">
        <div>
            <h1>GTU <span>Foro</span></h1>
            <p class="subtitle">Plataforma académica institucional</p>
            <ul class="features">
                <li><i class="fa-solid fa-users"></i> Gestión de estudiantes y docentes</li>
                <li><i class="fa-solid fa-calendar-days"></i> Organización de eventos</li>
                <li><i class="fa-solid fa-file-lines"></i> Foros y materiales por carrera</li>
                <li><i class="fa-solid fa-chart-simple"></i> Reportes y estadísticas</li>
            </ul>
        </div>
    </div>
    <div class="login-right">
        <div class="login-form-container">
            <h2>Iniciar sesión</h2>
            <p>Accede a la plataforma como alumno o coordinador.</p>
            <?php if ($mensaje_error): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?= htmlspecialchars($mensaje_error) ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="login">
                <div class="form-group">
                    <label>Usuario o Matrícula</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" name="usuario" placeholder="Ej: 1234" maxlength="4" pattern="\d{4}" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" name="contrasena" placeholder="Ingresa tu contraseña" required>
                    </div>
                </div>
                <button type="submit" class="btn-login">Entrar al panel <i class="fa-solid fa-arrow-right"></i></button>
            </form>
            <div class="links">¿No tienes cuenta? <a href="index.php">Volver al registro</a></div>
        </div>
    </div>
    <script>
        document.querySelector('input[name="usuario"]').addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '');
        });
    </script>
</body>
</html>