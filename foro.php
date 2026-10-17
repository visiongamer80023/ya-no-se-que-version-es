<?php
session_start();
require __DIR__ . "/verificar_acceso.php";
if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}
require __DIR__ . "/conexion.php";
mysqli_set_charset($conex, "utf8mb4");

$usuarioActual = (string) $_SESSION["usuario"];
$rolActual = "alumno";
$carreraActual = "Sin asignar";
$carreraIdActual = null;

$consultaUsuario = mysqli_prepare(
    $conex,
    "SELECT datos.rol, datos.carrera_id, carreras.nombre
     FROM datos
     LEFT JOIN carreras ON datos.carrera_id = carreras.id
     WHERE datos.usuario = ?
     LIMIT 1"
);
if ($consultaUsuario) {
    mysqli_stmt_bind_param($consultaUsuario, "s", $usuarioActual);
    mysqli_stmt_execute($consultaUsuario);
    mysqli_stmt_bind_result($consultaUsuario, $rolBaseDatos, $carreraIdBaseDatos, $nombreCarreraBaseDatos);
    if (mysqli_stmt_fetch($consultaUsuario)) {
        $rolActual = (string) $rolBaseDatos;
        if ($carreraIdBaseDatos !== null) {
            $carreraIdActual = (int) $carreraIdBaseDatos;
        }
        if ($nombreCarreraBaseDatos !== null && $nombreCarreraBaseDatos !== "") {
            $carreraActual = (string) $nombreCarreraBaseDatos;
        }
    }
    mysqli_stmt_close($consultaUsuario);
}

$_SESSION["rol"] = $rolActual;
$_SESSION["carrera_id"] = $carreraIdActual;

$nombresRoles = [
    "director" => "Director",
    "subdirector" => "Subdirector",
    "maestro" => "Maestro",
    "alumno" => "Alumno",
    "admin" => "Administrador"
];
$rolMostrado = $nombresRoles[$rolActual] ?? "Alumno";

$foroSeleccionadoId = filter_input(INPUT_GET, "foro", FILTER_VALIDATE_INT);

$erroresComunidad = [
    "formato" => "El nombre debe tener de 3 a 50 caracteres y usar solo letras, números o guion bajo.",
    "descripcion" => "La descripción no puede superar los 300 caracteres.",
    "existe" => "Ya existe un foro con ese nombre.",
    "sin_permiso" => "No tienes permiso para crear foros."
];
$errorComunidad = $_GET["error_comunidad"] ?? null;

$foros = [];
$sqlForos = "SELECT id, nombre, descripcion, creador, carrera_id FROM comunidades";

if ($rolActual === "alumno") {
    if ($carreraIdActual !== null) {
        $sqlForos .= " WHERE carrera_id IS NULL OR carrera_id = ?";
    } else {
        $sqlForos .= " WHERE carrera_id IS NULL";
    }
} 
$sqlForos .= " ORDER BY id DESC";

$consultaForos = mysqli_prepare($conex, $sqlForos);
if ($consultaForos) {
    if ($rolActual === "alumno" && $carreraIdActual !== null) {
        mysqli_stmt_bind_param($consultaForos, "i", $carreraIdActual);
    }
    
    mysqli_stmt_execute($consultaForos);
    mysqli_stmt_bind_result($consultaForos, $foroId, $foroNombre, $foroDescripcion, $foroCreador, $foroCarreraId);
    
    while (mysqli_stmt_fetch($consultaForos)) {
        $foros[] = [
            "id" => (int) $foroId,
            "nombre" => (string) $foroNombre,
            "descripcion" => (string) $foroDescripcion,
            "creador" => (string) $foroCreador,
            "carrera_id" => $foroCarreraId
        ];
    }
    mysqli_stmt_close($consultaForos);
}

$busqueda = trim((string) ($_GET["buscar"] ?? ""));
$resultadosBusqueda = [];
if ($busqueda !== "") {
    $terminoBusqueda = "%" . $busqueda . "%";
    $sqlBusqueda = "SELECT id, nombre, descripcion, creador, carrera_id FROM comunidades";
    
    if ($rolActual === "alumno") {
        if ($carreraIdActual !== null) {
            $sqlBusqueda .= " WHERE (nombre LIKE ? OR descripcion LIKE ?) AND (carrera_id IS NULL OR carrera_id = ?)";
        } else {
            $sqlBusqueda .= " WHERE (nombre LIKE ? OR descripcion LIKE ?) AND carrera_id IS NULL";
        }
    } else {
        $sqlBusqueda .= " WHERE nombre LIKE ? OR descripcion LIKE ?";
    }
    $sqlBusqueda .= " ORDER BY id DESC";

    $consultaBusqueda = mysqli_prepare($conex, $sqlBusqueda);
    if ($consultaBusqueda) {
        if ($rolActual === "alumno" && $carreraIdActual !== null) {
            mysqli_stmt_bind_param($consultaBusqueda, "ssi", $terminoBusqueda, $terminoBusqueda, $carreraIdActual);
        } else {
            mysqli_stmt_bind_param($consultaBusqueda, "ss", $terminoBusqueda, $terminoBusqueda);
        }
        
        mysqli_stmt_execute($consultaBusqueda);
        mysqli_stmt_bind_result($consultaBusqueda, $resultadoId, $resultadoNombre, $resultadoDescripcion, $resultadoCreador, $resultadoCarreraId);
        while (mysqli_stmt_fetch($consultaBusqueda)) {
            $resultadosBusqueda[] = [
                "id" => (int) $resultadoId,
                "nombre" => (string) $resultadoNombre,
                "descripcion" => (string) $resultadoDescripcion,
                "creador" => (string) $resultadoCreador,
                "carrera_id" => $resultadoCarreraId
            ];
        }
        mysqli_stmt_close($consultaBusqueda);
    }
}

$foroActual = null;
$accesoDenegado = false;

if ($foroSeleccionadoId !== false && $foroSeleccionadoId !== null) {
    $sqlForoActual = "SELECT id, nombre, descripcion, creador, carrera_id FROM comunidades WHERE id = ?";
    
    if ($rolActual === "alumno") {
        if ($carreraIdActual !== null) {
            $sqlForoActual .= " AND (carrera_id IS NULL OR carrera_id = ?)";
        } else {
            $sqlForoActual .= " AND carrera_id IS NULL";
        }
    }
    $sqlForoActual .= " LIMIT 1";
    
    $consultaForo = mysqli_prepare($conex, $sqlForoActual);
    if ($consultaForo) {
        if ($rolActual === "alumno" && $carreraIdActual !== null) {
            mysqli_stmt_bind_param($consultaForo, "ii", $foroSeleccionadoId, $carreraIdActual);
        } else {
            mysqli_stmt_bind_param($consultaForo, "i", $foroSeleccionadoId);
        }
        
        mysqli_stmt_execute($consultaForo);
        mysqli_stmt_bind_result($consultaForo, $actualId, $actualNombre, $actualDescripcion, $actualCreador, $actualCarreraId);
        if (mysqli_stmt_fetch($consultaForo)) {
            $foroActual = [
                "id" => (int) $actualId,
                "nombre" => (string) $actualNombre,
                "descripcion" => (string) $actualDescripcion,
                "creador" => (string) $actualCreador,
                "carrera_id" => $actualCarreraId
            ];
        } else {
            $accesoDenegado = true;
        }
        mysqli_stmt_close($consultaForo);
    }
}

$posts = [];
if ($foroActual !== null) {
    $consultaPosts = mysqli_prepare(
        $conex,
        "SELECT id, usuario, mensaje as contenido, imagen as foto, fecha 
         FROM mensajes 
         WHERE comunidad_id = ? 
         ORDER BY id DESC"
    );
    if ($consultaPosts) {
        mysqli_stmt_bind_param($consultaPosts, "i", $foroActual["id"]);
        mysqli_stmt_execute($consultaPosts);
        mysqli_stmt_bind_result($consultaPosts, $postId, $postUsuario, $postContenido, $postFoto, $postFecha);
        while (mysqli_stmt_fetch($consultaPosts)) {
            $posts[] = [
                "id" => (int) $postId,
                "usuario" => (string) $postUsuario,
                "contenido" => (string) $postContenido,
                "foto" => $postFoto,
                "fecha" => $postFecha
            ];
        }
        mysqli_stmt_close($consultaPosts);
    }
}

$totalForos = count($foros);
$totalPosts = count($posts);
$totalComunidadesCreadas = 0;
if ($rolActual === "director") {
    foreach ($foros as $foro) {
        if ($foro["creador"] === $usuarioActual) {
            $totalComunidadesCreadas++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foros - GTU</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --azul-institucional: #1e3a8a;
            --azul-oscuro: #0f172a;
            --azul-medio: #2563eb;
            --naranja: #f97316;
            --verde: #10b981;
            --rojo: #ef4444;
            --gris-fondo: #f1f5f9;
            --gris-borde: #e2e8f0;
            --texto-principal: #1e293b;
            --texto-secundario: #64748b;
            --sombra: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --sombra-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--gris-fondo);
            color: var(--texto-principal);
            min-height: 100vh;
        }

        .header-gtu {
            background: linear-gradient(135deg, var(--azul-institucional) 0%, var(--azul-oscuro) 100%);
            color: white;
            padding: 0 2rem;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--sombra-lg);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left { display: flex; align-items: center; gap: 1rem; }
        .logo-gtu { display: flex; align-items: center; gap: 0.75rem; }
        .logo-icon {
            width: 40px; height: 40px; background: var(--naranja);
            border-radius: 8px; display: flex; align-items: center;
            justify-content: center; font-weight: 800; font-size: 1.2rem;
        }
        .logo-text h1 { font-size: 1.5rem; font-weight: 800; letter-spacing: -0.5px; }
        .logo-text span { font-size: 0.75rem; opacity: 0.8; display: block; margin-top: -2px; }

        .header-center { flex: 1; max-width: 600px; margin: 0 2rem; }
        .search-box { position: relative; width: 100%; }
        .search-box input {
            width: 100%; padding: 0.75rem 1rem 0.75rem 3rem;
            border: none; border-radius: 8px; background: rgba(255, 255, 255, 0.15);
            color: white; font-size: 0.95rem; outline: none; transition: all 0.3s;
        }
        .search-box input::placeholder { color: rgba(255, 255, 255, 0.7); }
        .search-box input:focus { background: rgba(255, 255, 255, 0.25); }
        .search-box i {
            position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.7);
        }

        .header-right { display: flex; align-items: center; gap: 1.5rem; }
        .header-icons { display: flex; gap: 1rem; }
        .header-icons a { color: white; font-size: 1.3rem; position: relative; transition: transform 0.2s; text-decoration: none; }
        .header-icons a:hover { transform: scale(1.1); }
        .badge {
            position: absolute; top: -8px; right: -8px; background: var(--rojo);
            color: white; font-size: 0.7rem; font-weight: 700; width: 18px; height: 18px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
        }
        .user-profile {
            display: flex; align-items: center; gap: 0.75rem; cursor: pointer;
            padding: 0.5rem; border-radius: 8px; transition: background 0.2s; text-decoration: none; color: white;
        }
        .user-profile:hover { background: rgba(255, 255, 255, 0.1); }
        .user-avatar {
            width: 40px; height: 40px; border-radius: 50%; background: var(--naranja);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1.1rem;
        }
        .user-info { line-height: 1.3; }
        .user-info strong { display: block; font-size: 0.9rem; }
        .user-info span { font-size: 0.75rem; opacity: 0.8; }

        .layout-gtu {
            display: grid; grid-template-columns: 260px 1fr 300px;
            gap: 1.5rem; padding: 1.5rem 2rem; max-width: 1600px; margin: 0 auto;
        }

        .sidebar-left {
            background: white; border-radius: 12px; padding: 1.5rem;
            box-shadow: var(--sombra); height: fit-content; position: sticky; top: 85px;
        }
        .nav-menu { list-style: none; }
        .nav-menu li { margin-bottom: 0.5rem; }
        .nav-menu a {
            display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem;
            color: var(--texto-principal); text-decoration: none; border-radius: 8px;
            font-weight: 500; transition: all 0.2s;
        }
        .nav-menu a:hover { background: var(--gris-fondo); color: var(--azul-medio); }
        .nav-menu a.active { background: var(--azul-medio); color: white; }
        .nav-menu a i { width: 20px; text-align: center; font-size: 1.1rem; }

        .nav-section { margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--gris-borde); }
        .nav-section h3 {
            font-size: 0.75rem; text-transform: uppercase; color: var(--texto-secundario);
            margin-bottom: 0.75rem; padding-left: 1rem; letter-spacing: 0.5px;
        }

        .main-content { min-width: 0; }
        .page-header { margin-bottom: 1.5rem; }
        .page-header h1 { font-size: 2rem; font-weight: 800; color: var(--azul-oscuro); margin-bottom: 0.5rem; }
        .page-header h1 span { color: var(--naranja); }
        .page-header p { color: var(--texto-secundario); font-size: 0.95rem; }

        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card {
            background: white; border-radius: 12px; padding: 1.25rem;
            box-shadow: var(--sombra); display: flex; align-items: center;
            gap: 1rem; transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--sombra-lg); }
        .stat-icon {
            width: 50px; height: 50px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: white;
        }
        .stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .stat-icon.orange { background: linear-gradient(135deg, #f97316, #ea580c); }
        .stat-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-icon.purple { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .stat-info strong { display: block; font-size: 1.75rem; font-weight: 800; color: var(--azul-oscuro); line-height: 1; }
        .stat-info span { font-size: 0.8rem; color: var(--texto-secundario); display: block; margin-top: 0.25rem; }

        .content-card {
            background: white; border-radius: 12px; padding: 1.5rem;
            box-shadow: var(--sombra); margin-bottom: 1.5rem;
        }
        .content-card h2 {
            font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem;
            color: var(--azul-oscuro); display: flex; align-items: center; gap: 0.5rem;
        }
        .content-card h2 i { color: var(--azul-medio); }

        .search-form { display: flex; gap: 0.75rem; }
        .search-form input {
            flex: 1; padding: 0.75rem 1rem; border: 1px solid var(--gris-borde);
            border-radius: 8px; font-size: 0.95rem; outline: none; transition: border-color 0.2s;
        }
        .search-form input:focus { border-color: var(--azul-medio); }
        .search-form button {
            padding: 0.75rem 1.5rem; background: var(--azul-medio); color: white;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s;
        }
        .search-form button:hover { background: var(--azul-institucional); }

        .foro-list { display: grid; gap: 0.75rem; }
        .foro-item {
            display: flex; align-items: center; gap: 1rem; padding: 1rem;
            background: var(--gris-fondo); border-radius: 10px; text-decoration: none;
            color: var(--texto-principal); transition: all 0.2s; border: 2px solid transparent;
        }
        .foro-item:hover { background: white; border-color: var(--azul-medio); transform: translateX(4px); }
        .foro-item.active { background: white; border-color: var(--azul-medio); }
        .foro-icon {
            width: 45px; height: 45px; background: linear-gradient(135deg, var(--azul-medio), var(--azul-institucional));
            border-radius: 10px; display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700; font-size: 1.2rem; flex-shrink: 0;
        }
        .foro-details { flex: 1; min-width: 0; }
        .foro-details strong { display: block; font-size: 1rem; margin-bottom: 0.25rem; }

        .tag-foro {
            display: inline-block; font-size: 0.7rem; font-weight: 600;
            padding: 2px 8px; border-radius: 4px; margin-top: 4px;
        }
        .tag-general { background: #dcfce7; color: #166534; }
        .tag-restringido { background: #fee2e2; color: #991b1b; }

        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: var(--texto-principal); }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--gris-borde);
            border-radius: 8px; font-family: inherit; font-size: 0.95rem;
            outline: none; transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--azul-medio); }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .btn-primary {
            padding: 0.75rem 1.5rem; background: var(--azul-medio); color: white;
            border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s;
        }
        .btn-primary:hover { background: var(--azul-institucional); }

        .post-item { border-bottom: 1px solid var(--gris-borde); padding: 1.25rem 0; }
        .post-item:last-child { border-bottom: none; }
        .post-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; }
        .post-avatar {
            width: 40px; height: 40px; border-radius: 50%; background: var(--naranja);
            display: flex; align-items: center; justify-content: center;
            color: white; font-weight: 700;
        }
        .post-meta strong { display: block; font-size: 0.95rem; }
        .post-meta span { font-size: 0.8rem; color: var(--texto-secundario); }
        .post-content { margin-left: 3.25rem; line-height: 1.6; }
        .post-content img { max-width: 100%; border-radius: 8px; margin-top: 0.75rem; }

        .sidebar-right { display: flex; flex-direction: column; gap: 1rem; }
        .info-card { background: white; border-radius: 12px; padding: 1.25rem; box-shadow: var(--sombra); }
        .info-card h3 { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; color: var(--azul-oscuro); }
        .info-item { display: flex; justify-content: space-between; padding: 0.5rem 0; font-size: 0.9rem; }
        .info-item strong { color: var(--texto-secundario); }
        .btn-director {
            display: block; width: 100%; padding: 0.75rem; background: var(--naranja);
            color: white; text-align: center; text-decoration: none; border-radius: 8px;
            font-weight: 600; margin-bottom: 0.5rem; transition: background 0.2s;
        }
        .btn-director:hover { background: #ea580c; }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; font-weight: 500; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-warning { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }

        @media (max-width: 1200px) {
            .layout-gtu { grid-template-columns: 240px 1fr; }
            .sidebar-right { display: none; }
        }
        @media (max-width: 900px) {
            .layout-gtu { grid-template-columns: 1fr; padding: 1rem; }
            .sidebar-left { position: static; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .header-center { display: none; }
        }
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: 1fr; }
            .header-gtu { padding: 0 1rem; }
            .user-info { display: none; }
        }

        /* ==========================================
        BOTÓN DE PAGOS EN SIDEBAR
        ========================================== */
        .btn-pagos {
            display: flex !important;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1rem !important;
            margin: 1rem 0;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white !important;
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
            border: 1px solid transparent;
        }
        .btn-pagos i {
            width: 20px;
            text-align: center;
            font-size: 1.2rem;
        }
        .btn-pagos:hover {
            background: linear-gradient(135deg, #ea580c, #c2410c);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(249, 115, 22, 0.4);
        }
    </style>
</head>
<body>

    <header class="header-gtu">
        <div class="header-left">
            <div class="logo-gtu">
                <div class="logo-icon">GTU</div>
                <div class="logo-text">
                    <h1>Foros</h1>
                    <span>Campus Hermosillo</span>
                </div>
            </div>
        </div>

        <div class="header-center">
            <form method="GET" action="foro.php" class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="buscar" placeholder="Buscar foros, publicaciones, recursos..." value="<?= htmlspecialchars($busqueda) ?>">
            </form>
        </div>

        <div class="header-right">
            <div class="header-icons">
                <a href="#" title="Notificaciones">
                    <i class="fa-solid fa-bell"></i>
                    <span class="badge">3</span>
                </a>
                <a href="#" title="Mensajes">
                    <i class="fa-solid fa-message"></i>
                </a>
            </div>
            <a href="perfil.php" class="user-profile">
                <div class="user-avatar"><?= substr($usuarioActual, 0, 1) ?></div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($usuarioActual) ?></strong>
                    <span><?= htmlspecialchars($rolMostrado) ?></span>
                </div>
            </a>
        </div>
    </header>

    <div class="layout-gtu">
        <aside class="sidebar-left">
            <ul class="nav-menu">
                <li>
                    <a href="foro.php" class="<?= ($foroActual === null && empty($busqueda) && !$accesoDenegado) ? 'active' : '' ?>">
                        <i class="fa-solid fa-house"></i>
                        Inicio / Foros
                    </a>
                </li>
                <li>
                    <a href="perfil.php">
                        <i class="fa-solid fa-user"></i>
                        Mi perfil
                    </a>
                </li>
            </ul>

            <!-- BOTÓN DE PAGOS -->
            <a href="pagos.php" class="btn-pagos">
                <i class="fa-solid fa-credit-card"></i>
                <span>Pagos</span>
            </a>

            <div class="nav-section">
                <h3>FOROS DISPONIBLES</h3>
                <?php if (count($foros) > 0): ?>
                    <?php foreach ($foros as $foro): ?>
                        <a href="foro.php?foro=<?= $foro['id'] ?>" 
                           class="foro-item <?= $foroSeleccionadoId === $foro['id'] ? 'active' : '' ?>"
                           style="margin-bottom: 0.5rem; padding: 0.6rem 0.8rem;">
                            <div class="foro-icon" style="width: 35px; height: 35px; font-size: 0.9rem;">
                                <?= strtoupper(substr($foro['nombre'], 0, 1)) ?>
                            </div>
                            <div class="foro-details">
                                <strong style="font-size: 0.9rem;"><?= htmlspecialchars($foro['nombre']) ?></strong>
                                <?php if ($foro['carrera_id'] === null): ?>
                                    <span class="tag-foro tag-general">General</span>
                                <?php else: ?>
                                    <span class="tag-foro tag-restringido">Restringido</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: var(--texto-secundario); font-size: 0.85rem; padding: 0 1rem;">
                        No hay foros disponibles para tu carrera.
                    </p>
                <?php endif; ?>
            </div>

            <!-- SECCIÓN DE ADMINISTRACIÓN CON FACTURAS CORREGIDA -->
            <?php if ($rolActual === "director" || $rolActual === "admin"): ?>
                <div class="nav-section">
                    <h3>ADMINISTRACIÓN</h3>
                    <?php if ($rolActual === "director"): ?>
                        <a href="director.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: var(--texto-principal); text-decoration: none; border-radius: 8px; font-weight: 500;">
                            <i class="fa-solid fa-user-tie"></i> Panel Director
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($rolActual === "admin"): ?>
                        <a href="admin.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: var(--texto-principal); text-decoration: none; border-radius: 8px; font-weight: 500;">
                            <i class="fa-solid fa-gear"></i> Administración
                        </a>
                    <?php endif; ?>
                    
                    <!-- ENLACE A FACTURAS (CORREGIDO) -->
                    <a href="admin_facturas.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: var(--texto-principal); text-decoration: none; border-radius: 8px; font-weight: 500;">
                        <i class="fa-solid fa-file-invoice-dollar"></i> Facturas
                    </a>
                </div>
            <?php endif; ?>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <h1>Expediente <span>académico</span></h1>
                <p>Consulta foros, publica contenido y participa en la comunidad académica.</p>
            </div>

            <?php if ($errorComunidad !== null && isset($erroresComunidad[$errorComunidad])): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($erroresComunidad[$errorComunidad]) ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fa-solid fa-users"></i></div>
                    <div class="stat-info"><strong><?= $totalForos ?></strong><span>Foros activos</span></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fa-solid fa-file-lines"></i></div>
                    <div class="stat-info"><strong><?= $totalPosts ?></strong><span>Publicaciones</span></div>
                </div>
                <?php if ($rolActual === "director"): ?>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-plus"></i></div>
                        <div class="stat-info"><strong><?= $totalComunidadesCreadas ?></strong><span>Creados por ti</span></div>
                    </div>
                <?php endif; ?>
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fa-solid fa-graduation-cap"></i></div>
                    <div class="stat-info"><strong><?= htmlspecialchars($carreraActual) ?></strong><span>Tu carrera</span></div>
                </div>
            </div>

            <div class="content-card">
                <h2><i class="fa-solid fa-magnifying-glass"></i> Buscar foros</h2>
                <form method="GET" action="foro.php" class="search-form">
                    <input type="text" name="buscar" placeholder="Buscar un foro..." value="<?= htmlspecialchars($busqueda) ?>">
                    <button type="submit">Buscar</button>
                </form>
            </div>

            <?php if ($busqueda !== ""): ?>
                <div class="content-card">
                    <h2><i class="fa-solid fa-filter"></i> Resultados de búsqueda</h2>
                    <?php if (count($resultadosBusqueda) > 0): ?>
                        <div class="foro-list">
                            <?php foreach ($resultadosBusqueda as $resultado): ?>
                                <a href="foro.php?foro=<?= $resultado['id'] ?>" class="foro-item">
                                    <div class="foro-icon"><?= strtoupper(substr($resultado['nombre'], 0, 1)) ?></div>
                                    <div class="foro-details">
                                        <strong><?= htmlspecialchars($resultado['nombre']) ?></strong>
                                        <small><?= htmlspecialchars($resultado['descripcion']) ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--texto-secundario); text-align: center; padding: 2rem;">No se encontraron foros con ese término.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($rolActual === "director"): ?>
                <div class="content-card">
                    <h2><i class="fa-solid fa-plus-circle"></i> Crear un foro</h2>
                    <form method="POST" action="crear_comunidad.php">
                        <div class="form-group">
                            <label for="nombre">Nombre del foro</label>
                            <input type="text" id="nombre" name="nombre" maxlength="50" minlength="3" pattern="[A-Za-z0-9_]{3,50}" required>
                        </div>
                        <div class="form-group">
                            <label for="descripcion">Descripción</label>
                            <textarea id="descripcion" name="descripcion" maxlength="300" placeholder="Describe de qué trata este foro..."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="carrera_id">Tipo de foro</label>
                            <select id="carrera_id" name="carrera_id" required>
                                <option value="">Selecciona una opción</option>
                                <option value="0">Foro general</option>
                                <option value="1">Ingeniería en Sistemas</option>
                                <option value="2">Ingeniería Mecánica</option>
                                <option value="3">Derecho</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-plus"></i> Crear foro</button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($accesoDenegado): ?>
                <div class="content-card">
                    <h2><i class="fa-solid fa-ban" style="color: var(--rojo);"></i> Acceso Restringido</h2>
                    <p style="color: var(--texto-secundario);">
                        No tienes permiso para acceder a este foro o no existe. 
                        <a href="foro.php" style="color: var(--azul-medio); font-weight: 600;">Volver al inicio</a>
                    </p>
                </div>
            <?php elseif ($foroActual !== null): ?>
                <div class="content-card">
                    <h2><i class="fa-solid fa-folder-open"></i> <?= htmlspecialchars($foroActual['nombre']) ?></h2>
                    <p style="color: var(--texto-secundario); margin-bottom: 0.5rem;">
                        <?= htmlspecialchars($foroActual['descripcion']) ?>
                    </p>
                    <small style="color: var(--texto-secundario);">
                        Creado por: <strong><?= htmlspecialchars($foroActual['creador']) ?></strong>
                    </small>
                </div>

                <div class="content-card">
                    <h2><i class="fa-solid fa-pen-to-square"></i> Crear publicación</h2>
                    <form method="POST" action="guardar_post.php" enctype="multipart/form-data">
                        <input type="hidden" name="comunidad_id" value="<?= $foroActual['id'] ?>">
                        <div class="form-group">
                            <textarea name="mensaje" placeholder="¿Qué quieres publicar?" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="imagen">Imagen (opcional)</label>
                            <input type="file" id="imagen" name="imagen" accept="image/*">
                        </div>
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-paper-plane"></i> Publicar
                        </button>
                    </form>
                </div>

                <div class="content-card">
                    <h2><i class="fa-solid fa-newspaper"></i> Publicaciones</h2>
                    <?php if (count($posts) > 0): ?>
                        <?php foreach ($posts as $post): ?>
                            <article class="post-item">
                                <div class="post-header">
                                    <div class="post-avatar"><?= substr($post['usuario'], 0, 1) ?></div>
                                    <div class="post-meta">
                                        <strong>Usuario: <?= htmlspecialchars($post['usuario']) ?></strong>
                                        <span><?= htmlspecialchars((string) $post['fecha']) ?></span>
                                    </div>
                                </div>
                                <div class="post-content">
                                    <p><?= nl2br(htmlspecialchars($post['contenido'])) ?></p>
                                    <?php if (!empty($post['foto'])): ?>
                                        <img src="<?= htmlspecialchars((string) $post['foto']) ?>" alt="Imagen de la publicación">
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="color: var(--texto-secundario); text-align: center; padding: 2rem;">
                            Todavía no hay publicaciones en este foro.
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="content-card">
                    <h2><i class="fa-solid fa-house"></i> Bienvenido a los foros</h2>
                    <p style="color: var(--texto-secundario);">
                        Selecciona un foro de la lista lateral para comenzar a explorar y participar.
                    </p>
                </div>
            <?php endif; ?>
        </main>

        <aside class="sidebar-right">
            <div class="info-card">
                <h3><i class="fa-solid fa-user"></i> Mi cuenta</h3>
                <div class="info-item">
                    <strong>Usuario:</strong>
                    <span><?= htmlspecialchars($usuarioActual) ?></span>
                </div>
                <div class="info-item">
                    <strong>Rol:</strong>
                    <span><?= htmlspecialchars($rolMostrado) ?></span>
                </div>
                <div class="info-item">
                    <strong>Carrera:</strong>
                    <span><?= htmlspecialchars($carreraActual) ?></span>
                </div>
            </div>

            <?php if ($rolActual === "director"): ?>
                <div class="info-card">
                    <h3><i class="fa-solid fa-crown"></i> Acciones rápidas</h3>
                    <a href="director.php" class="btn-director">
                        <i class="fa-solid fa-chart-line"></i> Panel Director
                    </a>
                    <a href="#crear-foro" class="btn-director" style="background: var(--azul-medio);">
                        <i class="fa-solid fa-plus"></i> Crear foro
                    </a>
                </div>
            <?php endif; ?>

            <div class="info-card" style="background: linear-gradient(135deg, var(--azul-institucional), var(--azul-oscuro)); color: white;">
                <h3 style="color: white;"><i class="fa-solid fa-building-columns"></i> GTU</h3>
                <p style="font-size: 0.85rem; opacity: 0.9; line-height: 1.5;">
                    Más que una universidad, una comunidad.
                </p>
                <p style="font-size: 0.75rem; opacity: 0.7; margin-top: 0.5rem;">#SomosGTU</p>
            </div>
        </aside>
    </div>

    <script>
        let contadorDoom = 0;
        document.querySelector('.logo-gtu').addEventListener('click', function () {
            contadorDoom++;
            if (contadorDoom >= 5) {
                window.location.href = "doom.php";
            }
        });
    </script>

</body>
</html>
<?php
mysqli_close($conex);
?>