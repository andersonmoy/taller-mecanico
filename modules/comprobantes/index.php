<?php
// ============================================================
//  modules/comprobantes/index.php — Boletas y Facturas
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }

$rol    = $_SESSION['usuario_rol'];
$nombre = $_SESSION['usuario_nombre'];

// ── Filtros ──
$tipo   = $_GET['tipo']   ?? '';
$buscar = trim($_GET['buscar'] ?? '');

$sql = "SELECT c.*, 
               cl.nombre AS cliente_nombre, cl.dni_ruc,
               ot.numero AS orden_numero
        FROM comprobantes c
        LEFT JOIN clientes         cl ON c.cliente_id = cl.id
        LEFT JOIN ordenes_trabajo  ot ON c.orden_id   = ot.id
        WHERE c.estado = 'emitida'";
$params = [];

if ($tipo) { $sql .= " AND c.tipo = ?"; $params[] = $tipo; }
if ($buscar) {
    $sql .= " AND (cl.nombre LIKE ? OR cl.dni_ruc LIKE ? OR CONCAT(c.serie,'-',LPAD(c.numero,8,'0')) LIKE ?)";
    $params[] = "%$buscar%"; $params[] = "%$buscar%"; $params[] = "%$buscar%";
}
$sql .= " ORDER BY c.created_at DESC";
$comprobantes = dbQuery($sql, $params) ?: [];

// Resumen
$resumen = dbQuery("
    SELECT
        COUNT(*) AS total,
        SUM(tipo='boleta')  AS boletas,
        SUM(tipo='factura') AS facturas,
        SUM(total)          AS monto_total
    FROM comprobantes WHERE estado='emitida'
")[0] ?? [];

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Comprobantes — <?= APP_NAME ?></title>
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
    <div class="user-info">
      <strong><?= htmlspecialchars($nombre) ?></strong>
      <span><?= $rol ?></span>
    </div>
    <a href="../../logout.php" class="btn-logout"><i class="fas fa-right-from-bracket"></i></a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <h1><i class="fas fa-file-invoice"></i> Boletas y Facturas</h1>
    <div class="topbar-right">
      <span class="topbar-date"><i class="fas fa-calendar-day"></i> <?= date('d/m/Y') ?></span>
    </div>
  </header>

  <div class="content">

    <?php if ($msg === 'emitido'): ?>
      <div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Comprobante emitido correctamente.</div>
    <?php endif; ?>

    <!-- Tarjetas resumen -->
    <div class="inv-resumen">
      <div class="inv-card">
        <div class="inv-card-icon bg-azul"><i class="fas fa-file-invoice"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num"><?= $resumen['total'] ?? 0 ?></span>
          <span class="inv-card-label">Total emitidos</span>
        </div>
      </div>
      <div class="inv-card">
        <div class="inv-card-icon bg-verde"><i class="fas fa-receipt"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num"><?= $resumen['boletas'] ?? 0 ?></span>
          <span class="inv-card-label">Boletas</span>
        </div>
      </div>
      <div class="inv-card">
        <div class="inv-card-icon bg-amarillo"><i class="fas fa-file-lines"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num"><?= $resumen['facturas'] ?? 0 ?></span>
          <span class="inv-card-label">Facturas</span>
        </div>
      </div>
      <div class="inv-card">
        <div class="inv-card-icon bg-naranja" style="background:linear-gradient(135deg,var(--naranja),#c0620a)"><i class="fas fa-sack-dollar"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num">S/ <?= number_format($resumen['monto_total'] ?? 0, 2) ?></span>
          <span class="inv-card-label">Monto total facturado</span>
        </div>
      </div>
    </div>

    <!-- Filtros -->
    <form method="GET" action="index.php">
      <div class="filtros-bar">
        <div class="search-wrapper">
          <i class="fas fa-search"></i>
          <input type="text" name="buscar" class="search-input"
                 placeholder="Buscar por cliente, RUC o N° comprobante..."
                 value="<?= htmlspecialchars($buscar) ?>">
        </div>
        <select name="tipo" class="form-control" style="width:180px" onchange="this.form.submit()">
          <option value="">Boletas y Facturas</option>
          <option value="boleta"  <?= $tipo==='boleta'  ? 'selected':'' ?>>Solo Boletas</option>
          <option value="factura" <?= $tipo==='factura' ? 'selected':'' ?>>Solo Facturas</option>
        </select>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
        <?php if ($buscar || $tipo): ?>
          <a href="index.php" class="btn btn-outline"><i class="fas fa-times"></i> Limpiar</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Tabla -->
    <div class="card">
      <div class="card-header">
        <h3><i class="fas fa-file-invoice"></i> Comprobantes emitidos
          <span class="count-badge"><?= count($comprobantes) ?> registro<?= count($comprobantes)!==1?'s':'' ?></span>
        </h3>
      </div>

      <?php if (empty($comprobantes)): ?>
        <div class="empty">
          <i class="fas fa-file-invoice"></i>
          <p>No hay comprobantes emitidos aún</p>
          <a href="../ordenes/index.php?estado=lista" class="btn btn-primary">
            <i class="fas fa-clipboard-list"></i> Ver órdenes listas para cobrar
          </a>
        </div>
      <?php else: ?>
      <div class="tabla-wrapper">
        <table>
          <thead>
            <tr>
              <th>N° Comprobante</th>
              <th>Tipo</th>
              <th>Cliente</th>
              <th>DNI/RUC</th>
              <th>Orden</th>
              <th>Subtotal</th>
              <th>IGV</th>
              <th>Total</th>
              <th>Fecha</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($comprobantes as $c):
              $num_comp = $c['serie'].'-'.str_pad($c['numero'], 8, '0', STR_PAD_LEFT);
            ?>
            <tr>
              <td><span class="ord-numero"><?= htmlspecialchars($num_comp) ?></span></td>
              <td>
                <span class="badge <?= $c['tipo']==='boleta' ? 'badge-lista' : 'badge-cobrada' ?>">
                  <i class="fas <?= $c['tipo']==='boleta' ? 'fa-receipt' : 'fa-file-lines' ?>"></i>
                  <?= ucfirst($c['tipo']) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($c['cliente_nombre']) ?></td>
              <td style="font-size:12px;color:var(--texto-muted)"><?= htmlspecialchars($c['dni_ruc']) ?></td>
              <td>
                <?php if ($c['orden_numero']): ?>
                  <a href="../ordenes/ver.php?id=<?= $c['orden_id'] ?>" style="color:var(--azul-acento);font-size:13px">
                    <?= htmlspecialchars($c['orden_numero']) ?>
                  </a>
                <?php else: ?> — <?php endif; ?>
              </td>
              <td>S/ <?= number_format($c['subtotal'], 2) ?></td>
              <td>S/ <?= number_format($c['igv'], 2) ?></td>
              <td class="td-precio">S/ <?= number_format($c['total'], 2) ?></td>
              <td style="font-size:12px;color:var(--texto-muted);white-space:nowrap">
                <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?>
              </td>
              <td>
                <div class="acciones">
                  <a href="imprimir.php?id=<?= $c['id'] ?>" target="_blank"
                     class="btn-accion ver" title="Imprimir">
                    <i class="fas fa-print"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script src="../../assets/js/main.js"></script>
<script>
document.querySelectorAll('.alert-auto').forEach(el => {
  setTimeout(() => { el.style.opacity='0'; el.style.transition='opacity .5s';
    setTimeout(()=>el.remove(),500); }, 4000);
});
</script>
</body>
</html>