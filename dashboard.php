<?php
// ============================================================
//  dashboard.php — Panel Principal
// ============================================================
require_once 'includes/auth.php';
require_once 'config/database.php';

// Datos para las tarjetas
$total_productos  = dbQuery("SELECT COUNT(*) as n FROM productos WHERE activo=1")[0]['n'] ?? 0;
$total_clientes   = dbQuery("SELECT COUNT(*) as n FROM clientes")[0]['n'] ?? 0;
$stock_bajo       = dbQuery("SELECT COUNT(*) as n FROM productos WHERE stock_actual<=stock_minimo AND activo=1")[0]['n'] ?? 0;
$ordenes_abiertas = dbQuery("SELECT COUNT(*) as n FROM ordenes_trabajo WHERE estado IN ('abierta','en_proceso')")[0]['n'] ?? 0;
$ventas_hoy       = dbQuery("SELECT COALESCE(SUM(total),0) as n FROM comprobantes WHERE DATE(created_at)=CURDATE() AND estado='emitida'")[0]['n'] ?? 0;
$ordenes_listas   = dbQuery("SELECT COUNT(*) as n FROM ordenes_trabajo WHERE estado='lista'")[0]['n'] ?? 0;
$total_iot_hoy    = dbQuery("SELECT COUNT(*) as n FROM movimientos_stock WHERE fuente='iot' AND DATE(fecha)=CURDATE()")[0]['n'] ?? 0;

$ultimas_ordenes = dbQuery("
    SELECT ot.numero, ot.estado, ot.total,
           cl.nombre AS cliente, v.placa,
           CONCAT(u.nombre,' ',u.apellido) AS mecanico,
           ot.fecha_ingreso
    FROM ordenes_trabajo ot
    LEFT JOIN clientes cl ON ot.cliente_id  = cl.id
    LEFT JOIN vehiculos v  ON ot.vehiculo_id = v.id
    LEFT JOIN usuarios  u  ON ot.mecanico_id = u.id
    ORDER BY ot.fecha_ingreso DESC LIMIT 6") ?: [];

$productos_bajo = dbQuery("
    SELECT nombre, stock_actual, stock_minimo
    FROM productos
    WHERE stock_actual <= stock_minimo AND activo = 1
    ORDER BY stock_actual ASC LIMIT 5") ?: [];

$estado_color = ['abierta'=>'badge-abierta','en_proceso'=>'badge-proceso','lista'=>'badge-lista','cobrada'=>'badge-cobrada','anulada'=>'badge-anulada'];
$estado_texto = ['abierta'=>'Abierta','en_proceso'=>'En Proceso','lista'=>'Lista','cobrada'=>'Cobrada','anulada'=>'Anulada'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="icon"><i class="fas fa-wrench"></i></div>
    <div><h2><?= APP_NAME ?></h2><span>v<?= APP_VERSION ?></span></div>
  </div>
  <nav class="sidebar-menu">
    <div class="menu-section">Principal</div>
    <a href="dashboard.php" class="menu-item active">
      <i class="fas fa-gauge-high"></i> Dashboard
    </a>
    <div class="menu-section">Operaciones</div>
    <a href="modules/ordenes/index.php" class="menu-item">
      <i class="fas fa-clipboard-list"></i> Órdenes de Trabajo
      <?php if ($ordenes_abiertas > 0): ?>
        <span class="menu-badge"><?= $ordenes_abiertas ?></span>
      <?php endif; ?>
    </a>
    <a href="modules/clientes/index.php" class="menu-item">
      <i class="fas fa-users"></i> Clientes y Vehículos
    </a>
    <a href="modules/comprobantes/index.php" class="menu-item">
      <i class="fas fa-file-invoice"></i> Boletas y Facturas
    </a>
    <div class="menu-section">Almacén</div>
    <a href="modules/inventario/index.php" class="menu-item">
      <i class="fas fa-boxes-stacked"></i> Inventario
      <?php if ($stock_bajo > 0): ?>
        <span class="menu-badge"><?= $stock_bajo ?></span>
      <?php endif; ?>
    </a>
    <a href="modules/inventario/iot.php" class="menu-item">
      <i class="fas fa-microchip"></i> Monitor IoT
      <?php if ($total_iot_hoy > 0): ?>
        <span class="menu-badge" style="background:var(--verde)"><?= $total_iot_hoy ?></span>
      <?php endif; ?>
    </a>
    <a href="modules/precios/index.php" class="menu-item">
      <i class="fas fa-tags"></i> Precios y Servicios
    </a>
    <?php if ($SESSION_ROL === 'administrador'): ?>
    <div class="menu-section">Administración</div>
    <a href="modules/reportes/index.php" class="menu-item">
      <i class="fas fa-chart-bar"></i> Reportes
    </a>
    <a href="modules/usuarios/index.php" class="menu-item">
      <i class="fas fa-user-gear"></i> Usuarios
    </a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($SESSION_NOMBRE,0,1)) ?></div>
    <div class="user-info">
      <strong><?= htmlspecialchars($SESSION_NOMBRE) ?></strong>
      <span><?= ucfirst($SESSION_ROL) ?></span>
    </div>
    <a href="logout.php" class="btn-logout" title="Cerrar sesión">
      <i class="fas fa-right-from-bracket"></i>
    </a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <h1><i class="fas fa-gauge-high"></i> Dashboard</h1>
    <div class="topbar-right">
      <span class="topbar-date">
        <i class="fas fa-calendar-day"></i>
        <span id="reloj"><?= date('d/m/Y — H:i') ?></span>
      </span>
      <button class="btn-notif" onclick="window.location='modules/inventario/index.php?stock_bajo=1'">
        <i class="fas fa-bell"></i>
        <?php if ($stock_bajo > 0): ?><span class="notif-dot"></span><?php endif; ?>
      </button>
    </div>
  </header>

  <div class="content">

    <!-- Tarjetas de estadísticas -->
    <div class="stats-grid">
      <a href="modules/ordenes/index.php" class="stat-card" style="text-decoration:none">
        <div class="stat-icon naranja"><i class="fas fa-clipboard-list"></i></div>
        <div class="stat-info">
          <div class="valor"><?= $ordenes_abiertas ?></div>
          <div class="label">Órdenes Activas</div>
        </div>
      </a>
      <a href="modules/ordenes/index.php?estado=lista" class="stat-card" style="text-decoration:none">
        <div class="stat-icon verde"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
          <div class="valor"><?= $ordenes_listas ?></div>
          <div class="label">Listas para Cobrar</div>
        </div>
      </a>
      <a href="modules/inventario/index.php" class="stat-card" style="text-decoration:none">
        <div class="stat-icon azul"><i class="fas fa-boxes-stacked"></i></div>
        <div class="stat-info">
          <div class="valor"><?= $total_productos ?></div>
          <div class="label">Productos en Stock</div>
        </div>
      </a>
      <a href="modules/inventario/index.php?stock_bajo=1" class="stat-card" style="text-decoration:none">
        <div class="stat-icon rojo"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="stat-info">
          <div class="valor"><?= $stock_bajo ?></div>
          <div class="label">Stock Bajo</div>
        </div>
      </a>
      <a href="modules/clientes/index.php" class="stat-card" style="text-decoration:none">
        <div class="stat-icon morado"><i class="fas fa-users"></i></div>
        <div class="stat-info">
          <div class="valor"><?= $total_clientes ?></div>
          <div class="label">Clientes Registrados</div>
        </div>
      </a>
      <a href="modules/comprobantes/index.php" class="stat-card" style="text-decoration:none">
        <div class="stat-icon amarillo"><i class="fas fa-circle-dollar-to-slot"></i></div>
        <div class="stat-info">
          <div class="valor">S/ <?= number_format($ventas_hoy, 0) ?></div>
          <div class="label">Ventas de Hoy</div>
        </div>
      </a>
    </div>

    <div class="content-grid">

      <!-- Últimas órdenes -->
      <div class="card">
        <div class="card-header">
          <h3><i class="fas fa-clipboard-list"></i> Últimas Órdenes</h3>
          <a href="modules/ordenes/index.php" class="btn-ver">Ver todas →</a>
        </div>
        <?php if (empty($ultimas_ordenes)): ?>
          <div class="empty"><i class="fas fa-clipboard"></i><p>No hay órdenes aún</p></div>
        <?php else: ?>
        <div class="tabla-wrapper">
          <table>
            <thead>
              <tr><th>N° OT</th><th>Cliente</th><th>Placa</th><th>Estado</th><th>Total</th></tr>
            </thead>
            <tbody>
              <?php foreach ($ultimas_ordenes as $ot): ?>
              <tr onclick="window.location='modules/ordenes/ver.php?id=<?= $ot['id'] ?? '' ?>'" style="cursor:pointer">
                <td><strong><?= htmlspecialchars($ot['numero']) ?></strong></td>
                <td><?= htmlspecialchars($ot['cliente']) ?></td>
                <td><code><?= htmlspecialchars($ot['placa']) ?></code></td>
                <td><span class="badge <?= $estado_color[$ot['estado']] ?>"><?= $estado_texto[$ot['estado']] ?></span></td>
                <td><strong>S/ <?= number_format($ot['total'],2) ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <div class="side-panels">

        <!-- Accesos rápidos -->
        <div class="card">
          <div class="card-header"><h3><i class="fas fa-bolt"></i> Accesos Rápidos</h3></div>
          <div class="quick-grid">
            <a href="modules/ordenes/crear.php" class="quick-btn">
              <i class="fas fa-plus-circle qi-azul"></i> Nueva Orden
            </a>
            <a href="modules/clientes/crear.php" class="quick-btn">
              <i class="fas fa-user-plus qi-verde"></i> Nuevo Cliente
            </a>
            <a href="modules/inventario/ajuste.php" class="quick-btn">
              <i class="fas fa-sliders qi-naranja"></i> Ajustar Stock
            </a>
            <a href="modules/inventario/iot.php" class="quick-btn">
              <i class="fas fa-microchip qi-morado"></i> Monitor IoT
            </a>
          </div>
        </div>

        <!-- Stock bajo -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fas fa-triangle-exclamation"></i> Stock Bajo</h3>
            <a href="modules/inventario/index.php?stock_bajo=1" class="btn-ver">Ver →</a>
          </div>
          <?php if (empty($productos_bajo)): ?>
            <div class="empty">
              <i class="fas fa-check-circle" style="color:var(--verde);opacity:1"></i>
              <p>¡Todo el stock está bien!</p>
            </div>
          <?php else: ?>
            <?php foreach ($productos_bajo as $p):
              $pct = $p['stock_minimo'] > 0 ? min(100, ($p['stock_actual']/$p['stock_minimo'])*100) : 0;
            ?>
            <div class="stock-item">
              <div class="stock-bar-wrap">
                <div class="stock-nombre"><?= htmlspecialchars($p['nombre']) ?></div>
                <div class="stock-bar"><div class="stock-fill" style="width:<?= $pct ?>%"></div></div>
              </div>
              <div class="stock-num <?= $p['stock_actual']==0?'sin':'bajo' ?>"><?= $p['stock_actual'] ?></div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- IoT estado -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fas fa-microchip"></i> Sensores IoT — Hoy</h3>
            <a href="modules/inventario/iot.php" class="btn-ver">Ver →</a>
          </div>
          <div style="padding:16px;text-align:center">
            <div style="font-family:var(--font-title);font-size:36px;font-weight:700;color:var(--verde)">
              <?= $total_iot_hoy ?>
            </div>
            <div style="font-size:13px;color:var(--texto-muted)">movimientos detectados hoy</div>
            <div style="margin-top:12px">
              <span class="iot-status <?= $total_iot_hoy > 0 ? '' : 'offline' ?>">
                <span class="iot-dot <?= $total_iot_hoy > 0 ? '' : 'offline' ?>"></span>
                <?= $total_iot_hoy > 0 ? 'Sensores activos' : 'Sin actividad' ?>
              </span>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script src="assets/js/main.js"></script>
<script src="assets/js/dashboard.js"></script>
<script>
// Reloj en tiempo real
setInterval(() => {
  const now = new Date();
  document.getElementById('reloj').textContent =
    now.toLocaleDateString('es-PE') + ' — ' + now.toLocaleTimeString('es-PE',{hour:'2-digit',minute:'2-digit'});
}, 60000);
</script>
</body>
</html>