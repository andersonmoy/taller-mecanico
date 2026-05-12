<?php
// ============================================================
//  modules/comprobantes/crear.php — Emitir Comprobante
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }
if ($_SESSION['usuario_rol'] === 'mecanico') { header('Location: ../ordenes/index.php'); exit; }

$rol    = $_SESSION['usuario_rol'];
$nombre = $_SESSION['usuario_nombre'];
$error  = '';

$orden_id = (int)($_GET['orden_id'] ?? $_POST['orden_id'] ?? 0);
if (!$orden_id) { header('Location: ../ordenes/index.php'); exit; }

// Cargar la orden (debe estar en estado 'lista')
$orden = dbQuery("
    SELECT ot.*, cl.nombre AS cliente_nombre, cl.dni_ruc, cl.telefono, cl.tipo AS cliente_tipo,
           v.placa, v.marca, v.modelo,
           CONCAT(u.nombre,' ',u.apellido) AS mecanico
    FROM ordenes_trabajo ot
    LEFT JOIN clientes cl ON ot.cliente_id  = cl.id
    LEFT JOIN vehiculos v  ON ot.vehiculo_id = v.id
    LEFT JOIN usuarios  u  ON ot.mecanico_id = u.id
    WHERE ot.id = ?", [$orden_id]);

if (!$orden) { header('Location: ../ordenes/index.php'); exit; }
$orden = $orden[0];

if (!in_array($orden['estado'], ['lista'])) {
    header('Location: ../ordenes/ver.php?id='.$orden_id); exit;
}

// Items de la orden
$items = dbQuery("SELECT * FROM detalle_orden WHERE orden_id = ? ORDER BY id", [$orden_id]) ?: [];

// Siguiente número de serie
$serie_boleta  = 'B001';
$serie_factura = 'F001';

$ult_boleta  = dbQuery("SELECT MAX(numero) AS n FROM comprobantes WHERE serie=?", [$serie_boleta])[0]['n']  ?? 0;
$ult_factura = dbQuery("SELECT MAX(numero) AS n FROM comprobantes WHERE serie=?", [$serie_factura])[0]['n'] ?? 0;
$next_boleta  = ($ult_boleta  ?? 0) + 1;
$next_factura = ($ult_factura ?? 0) + 1;

// ── Emitir comprobante ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emitir'])) {
    $tipo_comp = $_POST['tipo_comp'] ?? 'boleta';

    if (!in_array($tipo_comp, ['boleta','factura'])) {
        $error = 'Tipo de comprobante inválido.';
    } else {
        $serie  = $tipo_comp === 'boleta' ? $serie_boleta : $serie_factura;
        $numero = $tipo_comp === 'boleta' ? $next_boleta  : $next_factura;

        // Insertar comprobante
        dbQuery(
            "INSERT INTO comprobantes
             (orden_id, tipo, serie, numero, cliente_id, subtotal, igv, total, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'emitida')",
            [$orden_id, $tipo_comp, $serie, $numero,
             $orden['cliente_id'], $orden['subtotal'], $orden['igv'], $orden['total']]
        );

        $comp_id = dbLastId();

        // Cambiar orden a 'cobrada'
        dbQuery("UPDATE ordenes_trabajo SET estado='cobrada', fecha_cierre=NOW() WHERE id=?", [$orden_id]);

        header('Location: imprimir.php?id='.$comp_id.'&nuevo=1'); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Emitir Comprobante — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/comprobantes.css">
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="icon"><i class="fas fa-wrench"></i></div>
    <div><h2><?= APP_NAME ?></h2><span>v<?= APP_VERSION ?></span></div>
  </div>
  <nav class="sidebar-menu">
    <div class="menu-section">Principal</div>
    <a href="../../dashboard.php"      class="menu-item"><i class="fas fa-gauge-high"></i> Dashboard</a>
    <div class="menu-section">Operaciones</div>
    <a href="../ordenes/index.php"     class="menu-item"><i class="fas fa-clipboard-list"></i> Órdenes de Trabajo</a>
    <a href="../clientes/index.php"    class="menu-item"><i class="fas fa-users"></i> Clientes y Vehículos</a>
    <a href="index.php"                class="menu-item active"><i class="fas fa-file-invoice"></i> Boletas y Facturas</a>
    <div class="menu-section">Almacén</div>
    <a href="../inventario/index.php"  class="menu-item"><i class="fas fa-boxes-stacked"></i> Inventario</a>
    <a href="../precios/index.php"     class="menu-item"><i class="fas fa-tags"></i> Precios y Servicios</a>
    <?php if ($rol === 'administrador'): ?>
    <div class="menu-section">Administración</div>
    <a href="../reportes/index.php"    class="menu-item"><i class="fas fa-chart-bar"></i> Reportes</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($nombre, 0, 1)) ?></div>
    <div class="user-info"><strong><?= htmlspecialchars($nombre) ?></strong><span><?= $rol ?></span></div>
    <a href="../../logout.php" class="btn-logout"><i class="fas fa-right-from-bracket"></i></a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <h1><i class="fas fa-file-invoice"></i> Emitir Comprobante</h1>
    <div class="topbar-right">
      <a href="../ordenes/ver.php?id=<?= $orden_id ?>" class="btn btn-outline">
        <i class="fas fa-arrow-left"></i> Volver a la Orden
      </a>
    </div>
  </header>

  <div class="content">
    <?php if ($error): ?>
      <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

      <!-- Vista previa del comprobante -->
      <div class="card">
        <div class="card-header">
          <h3><i class="fas fa-eye"></i> Vista previa — <span id="tipo_label">Boleta de Venta</span></h3>
        </div>
        <div class="card-body">

          <!-- Encabezado del comprobante -->
          <div style="text-align:center;padding:20px 0;border-bottom:2px solid var(--borde);margin-bottom:20px">
            <h2 style="font-family:var(--font-title);font-size:22px;color:var(--texto)"><?= APP_NAME ?></h2>
            <p style="font-size:12px;color:var(--texto-muted)">RUC: 20123456789 · Cusco, Perú</p>
            <div style="margin-top:12px;padding:10px 20px;background:var(--fondo);border-radius:8px;display:inline-block">
              <p style="font-family:var(--font-title);font-size:18px;font-weight:700;color:var(--azul-acento)" id="num_preview">
                B001 — <?= str_pad($next_boleta, 8, '0', STR_PAD_LEFT) ?>
              </p>
            </div>
          </div>

          <!-- Datos del cliente -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
            <div>
              <p style="font-size:11px;color:var(--texto-muted);font-weight:600;letter-spacing:1px;text-transform:uppercase;margin-bottom:6px">Cliente</p>
              <p style="font-weight:600;color:var(--texto)"><?= htmlspecialchars($orden['cliente_nombre']) ?></p>
              <p style="font-size:12px;color:var(--texto-muted)">DNI/RUC: <?= htmlspecialchars($orden['dni_ruc']) ?></p>
              <?php if ($orden['telefono']): ?>
              <p style="font-size:12px;color:var(--texto-muted)">Tel: <?= htmlspecialchars($orden['telefono']) ?></p>
              <?php endif; ?>
            </div>
            <div>
              <p style="font-size:11px;color:var(--texto-muted);font-weight:600;letter-spacing:1px;text-transform:uppercase;margin-bottom:6px">Vehículo</p>
              <p style="font-weight:700;font-family:var(--font-title);font-size:16px;color:var(--texto)"><?= htmlspecialchars($orden['placa']) ?></p>
              <p style="font-size:12px;color:var(--texto-muted)"><?= htmlspecialchars($orden['marca'].' '.$orden['modelo']) ?></p>
              <p style="font-size:12px;color:var(--texto-muted)">Orden: <?= htmlspecialchars($orden['numero']) ?></p>
            </div>
          </div>

          <!-- Tabla de items -->
          <div class="tabla-wrapper" style="margin-bottom:0">
            <table>
              <thead>
                <tr>
                  <th>Descripción</th>
                  <th style="text-align:center">Cant.</th>
                  <th style="text-align:right">P. Unit.</th>
                  <th style="text-align:right">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $it): ?>
                <tr>
                  <td>
                    <div style="font-weight:500;font-size:13px"><?= htmlspecialchars($it['nombre_item']) ?></div>
                    <div style="font-size:11px;color:var(--texto-muted)"><?= ucfirst($it['tipo']) ?></div>
                  </td>
                  <td style="text-align:center"><?= number_format($it['cantidad'], 2) ?></td>
                  <td style="text-align:right">S/ <?= number_format($it['precio_unitario'], 2) ?></td>
                  <td style="text-align:right;font-weight:600">S/ <?= number_format($it['subtotal'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Totales -->
          <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--borde)">
            <div style="display:flex;justify-content:flex-end;gap:40px;font-size:13px;margin-bottom:6px">
              <span style="color:var(--texto-muted)">Subtotal (sin IGV)</span>
              <span style="font-weight:600;min-width:90px;text-align:right">S/ <?= number_format($orden['subtotal'], 2) ?></span>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:40px;font-size:13px;margin-bottom:6px">
              <span style="color:var(--texto-muted)">IGV (18%)</span>
              <span style="font-weight:600;min-width:90px;text-align:right">S/ <?= number_format($orden['igv'], 2) ?></span>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:40px;font-size:17px;font-family:var(--font-title);font-weight:700;color:var(--texto);margin-top:8px;padding-top:8px;border-top:2px solid var(--borde)">
              <span>TOTAL</span>
              <span style="min-width:90px;text-align:right;color:var(--naranja)">S/ <?= number_format($orden['total'], 2) ?></span>
            </div>
          </div>

        </div>
      </div>

      <!-- Panel de emisión -->
      <div>
        <div class="inv-form-card card">
          <div class="card-header">
            <h3><i class="fas fa-paper-plane"></i> Emitir</h3>
          </div>
          <div class="card-body">
            <form method="POST" action="crear.php">
              <input type="hidden" name="orden_id" value="<?= $orden_id ?>">

              <p style="font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--texto-muted);margin-bottom:12px">
                Tipo de comprobante
              </p>

              <!-- Selector tipo -->
              <div class="ajuste-tipo" style="margin-bottom:20px">
                <label class="tipo-btn entrada" id="btn_boleta" onclick="setTipo('boleta')">
                  <input type="radio" name="tipo_comp" value="boleta" checked>
                  <i class="fas fa-receipt"></i>
                  <span>Boleta</span>
                  <small style="display:block;font-size:10px;color:var(--texto-muted);margin-top:4px">
                    <?= $serie_boleta ?>-<?= str_pad($next_boleta, 8,'0',STR_PAD_LEFT) ?>
                  </small>
                </label>
                <label class="tipo-btn salida" id="btn_factura" onclick="setTipo('factura')">
                  <input type="radio" name="tipo_comp" value="factura">
                  <i class="fas fa-file-lines"></i>
                  <span>Factura</span>
                  <small style="display:block;font-size:10px;color:var(--texto-muted);margin-top:4px">
                    <?= $serie_factura ?>-<?= str_pad($next_factura, 8,'0',STR_PAD_LEFT) ?>
                  </small>
                </label>
              </div>

              <!-- Resumen -->
              <div style="background:var(--fondo);border-radius:10px;padding:16px;margin-bottom:20px">
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px">
                  <span style="color:var(--texto-muted)">Cliente</span>
                  <span style="font-weight:600"><?= htmlspecialchars(substr($orden['cliente_nombre'],0,22)) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px">
                  <span style="color:var(--texto-muted)">Orden</span>
                  <span style="font-weight:600;color:var(--azul-acento)"><?= htmlspecialchars($orden['numero']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:16px;font-family:var(--font-title);font-weight:700;padding-top:8px;border-top:1px solid var(--borde)">
                  <span>TOTAL A COBRAR</span>
                  <span style="color:var(--naranja)">S/ <?= number_format($orden['total'], 2) ?></span>
                </div>
              </div>

              <button type="submit" name="emitir" class="btn btn-primary" style="width:100%;padding:14px;font-size:16px"
                      onclick="return confirm('¿Emitir el comprobante? Esta acción marcará la orden como COBRADA.')">
                <i class="fas fa-paper-plane"></i> Emitir y Cobrar
              </button>

              <p style="font-size:11px;color:var(--texto-muted);text-align:center;margin-top:10px">
                Al emitir, la orden pasará a estado <strong>Cobrada</strong> automáticamente.
              </p>
            </form>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
const nextBoleta  = '<?= $serie_boleta.'-'.str_pad($next_boleta,8,'0',STR_PAD_LEFT) ?>';
const nextFactura = '<?= $serie_factura.'-'.str_pad($next_factura,8,'0',STR_PAD_LEFT) ?>';

function setTipo(tipo) {
  document.getElementById('tipo_label').textContent =
    tipo === 'boleta' ? 'Boleta de Venta' : 'Factura Electrónica';
  document.getElementById('num_preview').textContent =
    tipo === 'boleta' ? nextBoleta : nextFactura;
}
</script>
<script src="../../assets/js/main.js"></script>
</body>
</html>