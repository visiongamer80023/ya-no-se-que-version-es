<?php
declare(strict_types=1);
session_start();
require __DIR__ . "/verificar_acceso.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit();
}

require __DIR__ . "/conexion.php";
mysqli_set_charset($conex, "utf8mb4");

$usuarioActual = (int) $_SESSION["usuario"];
$rolActual = $_SESSION["rol"] ?? "alumno";
$esAdminODirector = ($rolActual === "admin" || $rolActual === "director");

/* ==========================================
FACTURAS PENDIENTES (TODAS, ordenadas por más reciente)
========================================== */
$facturasPendientes = [];
$consultaFacturas = mysqli_prepare(
    $conex,
    "SELECT id, folio, periodo, concepto, programa, monto, descuento, 
            fecha_vencimiento, estado
     FROM facturas 
     WHERE usuario = ? AND estado = 'pendiente'
     ORDER BY fecha_vencimiento DESC"
);

if ($consultaFacturas) {
    mysqli_stmt_bind_param($consultaFacturas, "i", $usuarioActual);
    mysqli_stmt_execute($consultaFacturas);
    mysqli_stmt_bind_result(
        $consultaFacturas,
        $facturaId, $folio, $periodo, $concepto, $programa,
        $monto, $descuento, $fechaVencimiento, $estado
    );
    
    while (mysqli_stmt_fetch($consultaFacturas)) {
        $facturasPendientes[] = [
            'id' => (int) $facturaId,
            'folio' => (string) $folio,
            'periodo' => (string) $periodo,
            'concepto' => (string) $concepto,
            'programa' => (string) $programa,
            'monto' => (float) $monto,
            'descuento' => (float) $descuento,
            'fecha_vencimiento' => (string) $fechaVencimiento,
            'estado' => (string) $estado
        ];
    }
    mysqli_stmt_close($consultaFacturas);
}

/* ==========================================
HISTORIAL DE PAGOS
========================================== */
$historialPagos = [];
$consultaHistorial = mysqli_prepare(
    $conex,
    "SELECT p.id, f.folio, f.periodo, f.concepto, p.metodo_pago, 
            p.monto_pagado, p.fecha_pago, p.estado as estado_pago
     FROM pagos p
     INNER JOIN facturas f ON p.factura_id = f.id
     WHERE p.usuario = ?
     ORDER BY p.fecha_pago DESC
     LIMIT 10"
);

if ($consultaHistorial) {
    mysqli_stmt_bind_param($consultaHistorial, "i", $usuarioActual);
    mysqli_stmt_execute($consultaHistorial);
    mysqli_stmt_bind_result(
        $consultaHistorial,
        $pagoId, $pFolio, $pPeriodo, $pConcepto, $pMetodo,
        $pMonto, $pFecha, $pEstado
    );
    
    while (mysqli_stmt_fetch($consultaHistorial)) {
        $historialPagos[] = [
            'id' => (int) $pagoId,
            'folio' => (string) $pFolio,
            'periodo' => (string) $pPeriodo,
            'concepto' => (string) $pConcepto,
            'metodo_pago' => (string) $pMetodo,
            'monto_pagado' => (float) $pMonto,
            'fecha_pago' => (string) $pFecha,
            'estado' => (string) $pEstado
        ];
    }
    mysqli_stmt_close($consultaHistorial);
}

$pagoExitoso = isset($_GET["pago_ok"]);
$errorPago = $_GET["error_pago"] ?? null;

mysqli_close($conex);

// Función para calcular días restantes
function diasRestantes($fechaVencimiento) {
    $hoy = new DateTime();
    $vencimiento = new DateTime($fechaVencimiento);
    $diferencia = $hoy->diff($vencimiento);
    return $diferencia->invert ? -$diferencia->days : $diferencia->days;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagos - GTU</title>
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
        }
        .header h1 { font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
        .header a { color: white; text-decoration: none; padding: 0.5rem 1rem; background: rgba(255,255,255,0.2); border-radius: 6px; }
        .header a:hover { background: rgba(255,255,255,0.3); }
        
        .container { max-width: 1000px; margin: 2rem auto; padding: 0 2rem; }
        
        .card {
            background: white; border-radius: 12px; padding: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 2rem;
        }
        .card h2 {
            font-size: 1.4rem; font-weight: 700; color: var(--azul-oscuro);
            margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;
        }
        .card h2 i { color: var(--azul-medio); }
        
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        /* Factura individual */
        .factura-card {
            border: 2px solid var(--gris-borde);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: border-color 0.2s;
        }
        .factura-card.vencida { border-color: var(--rojo); background: #fef2f2; }
        .factura-card.por-vencer { border-color: var(--naranja); background: #fff7ed; }
        .factura-card.al-dia { border-color: var(--verde); background: #f0fdf4; }
        
        .factura-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--gris-borde);
        }
        .factura-header h3 { font-size: 1.1rem; font-weight: 700; color: var(--azul-oscuro); }
        .factura-header .folio { font-size: 0.9rem; color: var(--texto-secundario); }
        
        .factura-detalles { display: grid; gap: 0.75rem; margin-bottom: 1rem; }
        .factura-detalle {
            display: flex; justify-content: space-between; padding: 0.5rem 0;
            border-bottom: 1px solid var(--gris-borde);
        }
        .factura-detalle:last-child { border-bottom: none; }
        .factura-detalle .label { color: var(--texto-secundario); font-weight: 500; }
        .factura-detalle .value { font-weight: 600; }
        
        .badge-vencimiento {
            display: inline-flex; align-items: center; gap: 0.4rem;
            padding: 0.3rem 0.7rem; border-radius: 6px;
            font-size: 0.8rem; font-weight: 600; margin-left: 0.5rem;
        }
        .badge-vencida { background: #fee2e2; color: #991b1b; }
        .badge-urgente { background: #fef3c7; color: #92400e; }
        .badge-ok { background: #dcfce7; color: #166534; }
        
        .total-factura {
            background: #f8fafc; border-radius: 8px; padding: 1rem;
            margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;
        }
        .total-factura .label { font-weight: 600; color: var(--texto-secundario); }
        .total-factura .amount { font-size: 1.3rem; font-weight: 800; color: var(--azul-oscuro); }
        
        /* Métodos de pago */
        .metodos-pago {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem; margin: 1.5rem 0;
        }
        .metodo-pago {
            border: 2px solid var(--gris-borde); border-radius: 10px;
            padding: 1.25rem; cursor: pointer; transition: all 0.2s; position: relative;
        }
        .metodo-pago:hover { border-color: var(--azul-medio); background: #eff6ff; }
        .metodo-pago.seleccionado { border-color: var(--naranja); background: #fff7ed; }
        .metodo-pago input[type="radio"] {
            position: absolute; top: 1rem; right: 1rem;
            width: 20px; height: 20px; accent-color: var(--naranja);
        }
        .metodo-icon { font-size: 2rem; color: var(--azul-medio); margin-bottom: 0.5rem; }
        .metodo-nombre { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.25rem; }
        .metodo-desc { font-size: 0.8rem; color: var(--texto-secundario); }
        
        .form-tarjeta {
            background: #f8fafc; border: 1px solid var(--gris-borde);
            border-radius: 10px; padding: 1.5rem; margin-top: 1rem;
        }
        .form-tarjeta h3 { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; }
        .form-group input {
            width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--gris-borde);
            border-radius: 8px; font-size: 0.95rem;
        }
        .form-group input:focus { outline: none; border-color: var(--azul-medio); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        
        .btn-pagar {
            width: 100%; padding: 1rem; background: var(--naranja); color: white;
            border: none; border-radius: 8px; font-size: 1.1rem; font-weight: 700;
            cursor: pointer; transition: background 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            margin-top: 1rem;
        }
        .btn-pagar:hover { background: #ea580c; }
        
        .resumen-pago {
            background: #f8fafc; border-radius: 10px; padding: 1rem; margin-top: 1rem;
        }
        .resumen-item {
            display: flex; justify-content: space-between; padding: 0.5rem 0;
            border-bottom: 1px solid var(--gris-borde);
        }
        .resumen-item:last-child { border-bottom: none; }
        .total-pagar {
            background: #dcfce7; border-radius: 8px; padding: 1rem; margin-top: 0.5rem;
            display: flex; justify-content: space-between; align-items: center;
        }
        .total-label { font-weight: 700; color: #166534; font-size: 1.1rem; }
        .total-amount { font-size: 1.5rem; font-weight: 800; color: #166534; }
        
        .historial-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem; border-bottom: 1px solid var(--gris-borde);
        }
        .historial-item:last-child { border-bottom: none; }
        .historial-info strong { display: block; font-size: 0.95rem; }
        .historial-info span { font-size: 0.8rem; color: var(--texto-secundario); }
        .historial-monto { text-align: right; }
        .historial-monto strong { display: block; font-size: 1rem; color: var(--verde); }
        
        .badge-estado {
            display: inline-block; padding: 0.25rem 0.6rem; border-radius: 999px;
            font-size: 0.75rem; font-weight: 600; margin-top: 0.25rem;
        }
        .badge-completado { background: #dcfce7; color: #166534; }
        .badge-pendiente { background: #fef3c7; color: #92400e; }
        
        .empty-state { text-align: center; padding: 3rem; }
        .empty-state i { font-size: 4rem; color: var(--verde); margin-bottom: 1rem; }
        .empty-state h3 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
        .empty-state p { color: var(--texto-secundario); }
        
        @media (max-width: 768px) {
            .metodos-pago { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fa-solid fa-credit-card"></i> Pagar Colegiatura</h1>
        <a href="foro.php"><i class="fa-solid fa-arrow-left"></i> Volver al foro</a>
    </div>

    <div class="container">
        <?php if ($pagoExitoso): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i> ¡Pago realizado con éxito!
            </div>
        <?php endif; ?>

        <?php if ($errorPago): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i> Error al procesar el pago.
            </div>
        <?php endif; ?>

        <?php if ($esAdminODirector): ?>
            <div class="card">
                <h2><i class="fa-solid fa-shield-halved"></i> Panel de Administración</h2>
                <p style="color: var(--texto-secundario); margin-bottom: 1rem;">
                    Los administradores y directores pueden gestionar facturas desde el panel de administración.
                </p>
                <a href="admin_facturas.php" class="btn-pagar" style="max-width: 300px; text-decoration: none; background: var(--azul-medio);">
                    <i class="fa-solid fa-file-invoice-dollar"></i> Administrar facturas
                </a>
            </div>
        <?php endif; ?>

        <?php if (count($facturasPendientes) > 0): ?>
            <!-- MOSTRAR TODAS LAS FACTURAS PENDIENTES -->
            <?php foreach ($facturasPendientes as $factura): 
                $dias = diasRestantes($factura['fecha_vencimiento']);
                $total = $factura['monto'] - $factura['descuento'];
                
                // Determinar clase según estado
                if ($dias < 0) {
                    $claseFactura = 'vencida';
                    $badgeClass = 'badge-vencida';
                    $badgeText = '<i class="fa-solid fa-triangle-exclamation"></i> Vencida hace ' . abs($dias) . ' días';
                } elseif ($dias <= 15) {
                    $claseFactura = 'por-vencer';
                    $badgeClass = 'badge-urgente';
                    $badgeText = '<i class="fa-solid fa-clock"></i> Vence en ' . $dias . ' días';
                } else {
                    $claseFactura = 'al-dia';
                    $badgeClass = 'badge-ok';
                    $badgeText = '<i class="fa-solid fa-check"></i> Vence en ' . $dias . ' días';
                }
            ?>
            <div class="factura-card <?= $claseFactura ?>">
                <div class="factura-header">
                    <h3><i class="fa-solid fa-file-invoice"></i> <?= htmlspecialchars($factura['concepto']) ?></h3>
                    <span class="folio"><?= htmlspecialchars($factura['folio']) ?></span>
                </div>
                
                <div class="factura-detalles">
                    <div class="factura-detalle">
                        <span class="label">Periodo</span>
                        <span class="value"><?= htmlspecialchars($factura['periodo']) ?></span>
                    </div>
                    <div class="factura-detalle">
                        <span class="label">Programa</span>
                        <span class="value"><?= htmlspecialchars($factura['programa']) ?></span>
                    </div>
                    <div class="factura-detalle">
                        <span class="label">Fecha de vencimiento</span>
                        <span class="value">
                            <?= htmlspecialchars($factura['fecha_vencimiento']) ?>
                            <span class="badge-vencimiento <?= $badgeClass ?>"><?= $badgeText ?></span>
                        </span>
                    </div>
                </div>

                <form method="POST" action="procesar_pago.php">
                    <input type="hidden" name="factura_id" value="<?= $factura['id'] ?>">
                    
                    <h4 style="margin: 1.5rem 0 1rem; font-size: 1.1rem; color: var(--azul-oscuro);">
                        <i class="fa-solid fa-wallet"></i> Selecciona un método de pago
                    </h4>
                    
                    <div class="metodos-pago">
                        <label class="metodo-pago seleccionado">
                            <input type="radio" name="metodo_pago" value="tarjeta" checked>
                            <div class="metodo-icon"><i class="fa-solid fa-credit-card"></i></div>
                            <div class="metodo-nombre">Tarjeta de crédito o débito</div>
                            <div class="metodo-desc">Visa, Mastercard, Amex</div>
                        </label>
                        <label class="metodo-pago">
                            <input type="radio" name="metodo_pago" value="spei">
                            <div class="metodo-icon"><i class="fa-solid fa-building-columns"></i></div>
                            <div class="metodo-nombre">Transferencia SPEI</div>
                            <div class="metodo-desc">Desde tu banca en línea</div>
                        </label>
                        <label class="metodo-pago">
                            <input type="radio" name="metodo_pago" value="oxxo">
                            <div class="metodo-icon"><i class="fa-solid fa-store"></i></div>
                            <div class="metodo-nombre">Pago en OXXO</div>
                            <div class="metodo-desc">Genera tu referencia</div>
                        </label>
                    </div>

                    <div class="form-tarjeta" id="form-tarjeta-<?= $factura['id'] ?>">
                        <h3><i class="fa-solid fa-lock"></i> Pago seguro con tarjeta</h3>
                        <div class="form-group">
                            <label>Número de tarjeta</label>
                            <input type="text" class="numero-tarjeta" placeholder="1234 5678 9012 3456" maxlength="19">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Fecha de expiración</label>
                                <input type="text" class="fecha-exp" placeholder="MM/AA" maxlength="5">
                            </div>
                            <div class="form-group">
                                <label>CVV</label>
                                <input type="text" name="cvv" placeholder="123" maxlength="4">
                            </div>
                            <div class="form-group">
                                <label>Nombre en la tarjeta</label>
                                <input type="text" name="nombre_tarjeta" placeholder="Como aparece en la tarjeta">
                            </div>
                        </div>
                    </div>

                    <div class="resumen-pago">
                        <div class="resumen-item">
                            <span><?= htmlspecialchars($factura['concepto']) ?> <?= htmlspecialchars($factura['periodo']) ?></span>
                            <span>$<?= number_format($factura['monto'], 2) ?></span>
                        </div>
                        <div class="resumen-item">
                            <span>Descuentos / Becas</span>
                            <span>- $<?= number_format($factura['descuento'], 2) ?></span>
                        </div>
                        <div class="total-pagar">
                            <span class="total-label">Total a pagar</span>
                            <span class="total-amount">$<?= number_format($total, 2) ?> MXN</span>
                        </div>
                    </div>

                    <button type="submit" class="btn-pagar">
                        <i class="fa-solid fa-lock"></i> Continuar pago - $<?= number_format($total, 2) ?> MXN
                    </button>
                </form>
            </div>
            <?php endforeach; ?>

        <?php else: ?>
            <!-- SIN FACTURAS PENDIENTES -->
            <div class="card">
                <div class="empty-state">
                    <i class="fa-solid fa-circle-check"></i>
                    <h3>¡No tienes pagos pendientes!</h3>
                    <p>Todas tus colegiaturas están al día. ¡Excelente!</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (count($historialPagos) > 0): ?>
            <div class="card">
                <h2><i class="fa-solid fa-clock-rotate-left"></i> Historial de pagos</h2>
                <?php foreach ($historialPagos as $pago): ?>
                    <div class="historial-item">
                        <div class="historial-info">
                            <strong><?= htmlspecialchars($pago['concepto']) ?> - <?= htmlspecialchars($pago['periodo']) ?></strong>
                            <span>Folio: <?= htmlspecialchars($pago['folio']) ?> | <?= htmlspecialchars($pago['fecha_pago']) ?></span>
                        </div>
                        <div class="historial-monto">
                            <strong>$<?= number_format($pago['monto_pagado'], 2) ?> MXN</strong>
                            <span class="badge-estado badge-<?= $pago['estado'] ?>"><?= ucfirst($pago['estado']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Selección de método de pago
        document.querySelectorAll('.metodo-pago').forEach(metodo => {
            metodo.addEventListener('click', function() {
                const form = this.closest('.factura-card');
                form.querySelectorAll('.metodo-pago').forEach(m => m.classList.remove('seleccionado'));
                this.classList.add('seleccionado');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });

        // Formatear número de tarjeta
        document.querySelectorAll('.numero-tarjeta').forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                value = value.match(/.{1,4}/g)?.join(' ') || value;
                e.target.value = value;
            });
        });

        // Formatear fecha de expiración
        document.querySelectorAll('.fecha-exp').forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length >= 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2, 4);
                }
                e.target.value = value;
            });
        });
    </script>
</body>
</html>