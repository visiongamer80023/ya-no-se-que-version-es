<?php
declare(strict_types=1);
session_start();
require __DIR__ . "/verificar_acceso.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

$rolActual = $_SESSION["rol"] ?? "";
if ($rolActual !== "admin" && $rolActual !== "director") {
    http_response_code(403);
    exit("Acceso denegado.");
}

require __DIR__ . "/conexion.php";
mysqli_set_charset($conex, "utf8mb4");

$mensaje_exito = "";
$mensaje_error = "";

// Obtener lista de alumnos
$alumnos = [];
$consultaAlumnos = mysqli_query($conex, "SELECT usuario, rol FROM datos WHERE rol = 'alumno' ORDER BY usuario ASC");
if ($consultaAlumnos) {
    while ($fila = mysqli_fetch_assoc($consultaAlumnos)) {
        $alumnos[] = $fila;
    }
}

// Procesar creación de factura
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"]) && $_POST["accion"] === "crear_factura") {
    $usuario_factura = filter_input(INPUT_POST, "usuario", FILTER_VALIDATE_INT);
    $periodo = trim($_POST["periodo"] ?? "");
    $concepto = trim($_POST["concepto"] ?? "");
    $programa = trim($_POST["programa"] ?? "");
    $monto = filter_input(INPUT_POST, "monto", FILTER_VALIDATE_FLOAT);
    $descuento = filter_input(INPUT_POST, "descuento", FILTER_VALIDATE_FLOAT) ?? 0.00;
    $fecha_vencimiento = $_POST["fecha_vencimiento"] ?? "";
    
    if (!$usuario_factura || !$periodo || !$concepto || !$monto || !$fecha_vencimiento) {
        $mensaje_error = "Todos los campos son obligatorios.";
    } else {
        // Generar folio único (CORREGIDO: convertir a string)
        $numero_aleatorio = (string) rand(1, 999999);
        $folio = "FAC-" . date("Y") . "-" . str_pad($numero_aleatorio, 6, "0", STR_PAD_LEFT);
        
        $insertar = mysqli_prepare(
            $conex,
            "INSERT INTO facturas (usuario, periodo, concepto, programa, monto, descuento, fecha_vencimiento, folio, estado) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')"
        );
        
        if ($insertar) {
            mysqli_stmt_bind_param($insertar, "isssddss", 
                $usuario_factura, $periodo, $concepto, $programa, 
                $monto, $descuento, $fecha_vencimiento, $folio
            );
            
            if (mysqli_stmt_execute($insertar)) {
                $mensaje_exito = "Factura $folio creada exitosamente para el usuario $usuario_factura.";
            } else {
                $mensaje_error = "Error al crear la factura: " . mysqli_error($conex);
            }
            mysqli_stmt_close($insertar);
        }
    }
}

// Obtener facturas existentes
$facturas = [];
$consultaFacturas = mysqli_query($conex, "
    SELECT f.id, f.folio, f.usuario, f.periodo, f.concepto, f.programa, 
           f.monto, f.descuento, f.fecha_vencimiento, f.estado
    FROM facturas f
    ORDER BY f.id DESC
    LIMIT 50
");
if ($consultaFacturas) {
    while ($fila = mysqli_fetch_assoc($consultaFacturas)) {
        $facturas[] = $fila;
    }
}

mysqli_close($conex);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Facturas - GTU</title>
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
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; }
        body { background: var(--gris-fondo); color: var(--texto-principal); min-height: 100vh; }
        
        .header {
            background: linear-gradient(135deg, var(--azul-institucional), var(--azul-oscuro));
            color: white; padding: 1rem 2rem;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .header h1 { font-size: 1.5rem; font-weight: 700; }
        .header a { color: white; text-decoration: none; padding: 0.5rem 1rem; background: rgba(255,255,255,0.2); border-radius: 6px; }
        .header a:hover { background: rgba(255,255,255,0.3); }
        
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        
        .card {
            background: white; border-radius: 12px; padding: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 2rem;
        }
        .card h2 {
            font-size: 1.5rem; font-weight: 700; color: var(--azul-oscuro);
            margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;
        }
        .card h2 i { color: var(--azul-medio); }
        
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.875rem; font-weight: 600; color: var(--texto-principal); margin-bottom: 0.5rem; }
        .form-group input, .form-group select {
            width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--gris-borde);
            border-radius: 8px; font-size: 0.95rem; outline: none; transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus { border-color: var(--azul-medio); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        
        .btn {
            padding: 0.75rem 1.5rem; border: none; border-radius: 8px;
            font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.2s;
        }
        .btn-primary { background: var(--azul-medio); color: white; }
        .btn-primary:hover { background: var(--azul-institucional); }
        
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid var(--gris-borde); font-size: 0.9rem; }
        th { background: var(--gris-fondo); font-weight: 600; color: var(--texto-secundario); }
        tr:hover { background: #f8fafc; }
        
        .badge {
            display: inline-block; padding: 0.25rem 0.75rem; border-radius: 999px;
            font-size: 0.75rem; font-weight: 600;
        }
        .badge-pendiente { background: #fef3c7; color: #92400e; }
        .badge-pagado { background: #dcfce7; color: #166534; }
        .badge-vencido { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fa-solid fa-file-invoice-dollar"></i> Administrar Facturas</h1>
        <a href="foro.php"><i class="fa-solid fa-arrow-left"></i> Volver al foro</a>
    </div>

    <div class="container">
        <?php if ($mensaje_exito): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($mensaje_exito) ?>
            </div>
        <?php endif; ?>

        <?php if ($mensaje_error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($mensaje_error) ?>
            </div>
        <?php endif; ?>

        <!-- FORMULARIO PARA CREAR FACTURA -->
        <div class="card">
            <h2><i class="fa-solid fa-plus-circle"></i> Asignar factura a alumno</h2>
            <form method="POST" action="">
                <input type="hidden" name="accion" value="crear_factura">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Alumno (usuario)</label>
                        <select name="usuario" required>
                            <option value="">Selecciona un alumno</option>
                            <?php foreach ($alumnos as $alumno): ?>
                                <option value="<?= $alumno['usuario'] ?>"><?= $alumno['usuario'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Periodo</label>
                        <input type="text" name="periodo" placeholder="Ej: 2025-10" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Concepto</label>
                        <input type="text" name="concepto" placeholder="Ej: Colegiatura Licenciatura" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Programa / Carrera</label>
                        <input type="text" name="programa" placeholder="Ej: Ingeniería en Sistemas">
                    </div>
                    
                    <div class="form-group">
                        <label>Monto ($)</label>
                        <input type="number" name="monto" step="0.01" min="0" placeholder="3450.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Descuento ($)</label>
                        <input type="number" name="descuento" step="0.01" min="0" value="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label>Fecha de vencimiento</label>
                        <input type="date" name="fecha_vencimiento" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fa-solid fa-plus"></i> Crear factura
                </button>
            </form>
        </div>

        <!-- LISTA DE FACTURAS EXISTENTES -->
        <div class="card">
            <h2><i class="fa-solid fa-list"></i> Facturas existentes</h2>
            <?php if (count($facturas) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Usuario</th>
                            <th>Periodo</th>
                            <th>Concepto</th>
                            <th>Monto</th>
                            <th>Vencimiento</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($facturas as $f): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($f['folio']) ?></strong></td>
                                <td><?= htmlspecialchars($f['usuario']) ?></td>
                                <td><?= htmlspecialchars($f['periodo']) ?></td>
                                <td><?= htmlspecialchars($f['concepto']) ?></td>
                                <td>$<?= number_format((float)$f['monto'], 2) ?></td>
                                <td><?= htmlspecialchars($f['fecha_vencimiento']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $f['estado'] ?>">
                                        <?= ucfirst($f['estado']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="color: var(--texto-secundario); text-align: center; padding: 2rem;">
                    No hay facturas registradas.
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>