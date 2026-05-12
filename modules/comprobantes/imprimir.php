<?php
// ============================================================
//  modules/comprobantes/imprimir.php — Impresión/PDF
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$comp = dbQuery("
    SELECT c.*, cl.nombre AS cliente_nombre, cl.dni_ruc, cl.direccion, cl.telefono,
           ot.numero AS orden_numero, ot.vehiculo_id,
           v.placa, v.marca, v.modelo
    FROM comprobantes c
    LEFT JOIN clientes        cl ON c.cliente_id = cl.id
    LEFT JOIN ordenes_trabajo ot ON c.orden_id   = ot.id
    LEFT JOIN vehiculos       v  ON ot.vehiculo_id = v.id
    WHERE c.id = ?", [$id]);

if (!$comp) { header('Location: index.php'); exit; }
$comp = $comp[0];

$items = dbQuery("SELECT * FROM detalle_orden WHERE orden_id = ?", [$comp['orden_id']]) ?: [];
$num_comp = $comp['serie'].'-'.str_pad($comp['numero'], 8, '0', STR_PAD_LEFT);
$nuevo = isset($_GET['nuevo']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $num_comp ?> — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: #f5f5f5; color: #1a1a2e; font-size: 13px; }

    .no-print {
      background: #1a3a5c; padding: 14px 24px; display: flex;
      justify-content: space-between; align-items: center;
    }
    .no-print span { color: #fff; font-size: 14px; }
    .no-print .btns { display: flex; gap: 10px; }
    .btn-imp { padding: 9px 20px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; font-weight: 600; }
    .btn-imp.primary { background: #e8820c; color: #fff; }
    .btn-imp.outline { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,0.3); }

    .comprobante {
      max-width: 700px; margin: 24px auto; background: #fff;
      border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);
      overflow: hidden;
    }

    /* Encabezado */
    .comp-header {
      background: linear-gradient(135deg, #0f1e36, #1a3a5c);
      color: #fff; padding: 28px 32px;
      display: flex; justify-content: space-between; align-items: center;
    }
    .comp-empresa h1 { font-family: 'Rajdhani', sans-serif; font-size: 24px; font-weight: 700; }
    .comp-empresa p  { font-size: 11px; color: rgba(255,255,255,0.55); margin-top: 4px; }
    .comp-num-box {
      text-align: right;
      border: 1px solid rgba(255,255,255,0.2);
      padding: 12px 18px; border-radius: 8px;
    }
    .comp-num-tipo { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,0.5); }
    .comp-num-val  { font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; color: #e8820c; }
    .comp-fecha    { font-size: 11px; color: rgba(255,255,255,0.4); margin-top: 4px; }

    /* Datos */
    .comp-datos {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 0; border-bottom: 1px solid #eee;
    }
    .comp-datos-col {
      padding: 20px 32px;
    }
    .comp-datos-col:first-child { border-right: 1px solid #eee; }
    .datos-label { font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
                   text-transform: uppercase; color: #999; margin-bottom: 8px; }
    .datos-val   { font-weight: 600; color: #1a1a2e; font-size: 14px; }
    .datos-sub   { font-size: 12px; color: #888; margin-top: 2px; }

    /* Tabla de items */
    .comp-tabla { padding: 0 32px; }
    .comp-tabla table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    .comp-tabla th {
      background: #f8f9fa; font-size: 11px; font-weight: 700;
      letter-spacing: 1px; text-transform: uppercase; color: #888;
      padding: 10px 12px; text-align: left; border-bottom: 2px solid #eee;
    }
    .comp-tabla td { padding: 11px 12px; border-bottom: 1px solid #f0f0f0; }
    .comp-tabla tr:last-child td { border-bottom: none; }
    .item-nombre { font-weight: 600; color: #1a1a2e; }
    .item-tipo   { font-size: 10px; color: #aaa; margin-top: 2px; }
    .text-right  { text-align: right; }
    .text-center { text-align: center; }

    /* Totales */
    .comp-totales {
      padding: 16px 32px 24px;
      display: flex; justify-content: flex-end;
      border-top: 1px solid #eee;
    }
    .totales-box { min-width: 240px; }
    .tot-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 13px; }
    .tot-row span:first-child { color: #888; }
    .tot-final {
      display: flex; justify-content: space-between;
      padding: 12px 0 0; margin-top: 8px;
      border-top: 2px solid #1a1a2e;
      font-family: 'Rajdhani', sans-serif; font-size: 18px; font-weight: 700;
    }
    .tot-final span:last-child { color: #e8820c; }

    /* Pie */
    .comp-footer {
      background: #f8f9fa; padding: 16px 32px;
      text-align: center; font-size: 11px; color: #aaa;
      border-top: 1px solid #eee;
    }

    @media print {
      .no-print { display: none !important; }
      body { background: #fff; }
      .comprobante { box-shadow: none; margin: 0; border-radius: 0; }
    }
  </style>
</head>
<body>

<!-- Barra de acciones (no imprime) -->
<div class="no-print">
  <span>
    <?php if ($nuevo): ?>✅ Comprobante emitido correctamente — <?php endif; ?>
    <?= $num_comp ?>
  </span>
  <div class="btns">
    <button class="btn-imp outline" onclick="window.location.href='index.php?msg=emitido'">
      ← Ir a Comprobantes
    </button>
    <button class="btn-imp primary" onclick="window.print()">
      🖨 Imprimir
    </button>
  </div>
</div>

<!-- Comprobante -->
<div class="comprobante">

  <!-- Encabezado -->
  <div class="comp-header">
    <div class="comp-empresa">
      <h1><?= APP_NAME ?></h1>
      <p>RUC: 20123456789 · Jr. Los Mecánicos 123, Cusco</p>
      <p>Tel: 084-123456 · taller@correo.com</p>
    </div>
    <div class="comp-num-box">
      <div class="comp-num-tipo"><?= strtoupper($comp['tipo']) ?> DE VENTA</div>
      <div class="comp-num-val"><?= $num_comp ?></div>
      <div class="comp-fecha">Fecha: <?= date('d/m/Y H:i', strtotime($comp['created_at'])) ?></div>
    </div>
  </div>

  <!-- Datos cliente y vehículo -->
  <div class="comp-datos">
    <div class="comp-datos-col">
      <div class="datos-label">Cliente</div>
      <div class="datos-val"><?= htmlspecialchars($comp['cliente_nombre']) ?></div>
      <div class="datos-sub">DNI/RUC: <?= htmlspecialchars($comp['dni_ruc']) ?></div>
      <?php if ($comp['direccion']): ?>
      <div class="datos-sub"><?= htmlspecialchars($comp['direccion']) ?></div>
      <?php endif; ?>
      <?php if ($comp['telefono']): ?>
      <div class="datos-sub">Tel: <?= htmlspecialchars($comp['telefono']) ?></div>
      <?php endif; ?>
    </div>
    <div class="comp-datos-col">
      <div class="datos-label">Vehículo · Orden</div>
      <div class="datos-val"><?= htmlspecialchars($comp['placa']) ?></div>
      <div class="datos-sub"><?= htmlspecialchars($comp['marca'].' '.$comp['modelo']) ?></div>
      <div class="datos-sub" style="margin-top:8px;color:#1a3a5c;font-weight:600">
        Orden: <?= htmlspecialchars($comp['orden_numero']) ?>
      </div>
    </div>
  </div>

  <!-- Items -->
  <div class="comp-tabla">
    <table>
      <thead>
        <tr>
          <th>Descripción</th>
          <th class="text-center">Cant.</th>
          <th class="text-right">P. Unit. (S/)</th>
          <th class="text-right">Total (S/)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
        <tr>
          <td>
            <div class="item-nombre"><?= htmlspecialchars($it['nombre_item']) ?></div>
            <div class="item-tipo"><?= ucfirst($it['tipo']) ?></div>
          </td>
          <td class="text-center"><?= number_format($it['cantidad'], 2) ?></td>
          <td class="text-right">S/ <?= number_format($it['precio_unitario'], 2) ?></td>
          <td class="text-right"><strong>S/ <?= number_format($it['subtotal'], 2) ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Totales -->
  <div class="comp-totales">
    <div class="totales-box">
      <div class="tot-row"><span>Subtotal (sin IGV)</span><span>S/ <?= number_format($comp['subtotal'], 2) ?></span></div>
      <div class="tot-row"><span>IGV (18%)</span><span>S/ <?= number_format($comp['igv'], 2) ?></span></div>
      <div class="tot-final"><span>TOTAL</span><span>S/ <?= number_format($comp['total'], 2) ?></span></div>
    </div>
  </div>

  <!-- Pie -->
  <div class="comp-footer">
    <p>Gracias por confiar en <?= APP_NAME ?> · Este documento es válido como comprobante de pago</p>
    <p style="margin-top:4px">Representación impresa del comprobante electrónico</p>
  </div>

</div>

</body>
</html>