<?php
// ============================================================
//  dashboard.php  —  Panel Principal
//  Sistema de Gestión — Taller Mecánico
// ============================================================
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$nombre = $_SESSION['usuario_nombre'];
$rol    = $_SESSION['usuario_rol'];

// ── Datos para las tarjetas del dashboard ──
$total_productos  = dbQuery("SELECT COUNT(*) as total FROM productos WHERE activo = 1")[0]['total'] ?? 0;
$total_clientes   = dbQuery("SELECT COUNT(*) as total FROM clientes")[0]['total'] ?? 0;
$stock_bajo       = dbQuery("SELECT COUNT(*) as total FROM productos WHERE stock_actual <= stock_minimo AND activo = 1")[0]['total'] ?? 0;
$ordenes_abiertas = dbQuery("SELECT COUNT(*) as total FROM ordenes_trabajo WHERE estado IN ('abierta','en_proceso')")[0]['total'] ?? 0;
$ventas_hoy       = dbQuery("SELECT COALESCE(SUM(total),0) as total FROM comprobantes WHERE DATE(created_at) = CURDATE() AND estado = 'emitida'")[0]['total'] ?? 0;
$ordenes_listas   = dbQuery("SELECT COUNT(*) as total FROM ordenes_trabajo WHERE estado = 'lista'")[0]['total'] ?? 0;

$ultimas_ordenes = dbQuery("
    SELECT ot.numero, ot.estado, ot.total,
           cl.nombre AS cliente, v.placa,
           CONCAT(u.nombre,' ',u.apellido) AS mecanico,
           ot.fecha_ingreso
    FROM ordenes_trabajo ot
    LEFT JOIN clientes cl  ON ot.cliente_id  = cl.id
    LEFT JOIN vehiculos v  ON ot.vehiculo_id = v.id
    LEFT JOIN usuarios  u  ON ot.mecanico_id = u.id
    ORDER BY ot.fecha_ingreso DESC LIMIT 5
") ?: [];

$productos_bajo = dbQuery("
    SELECT nombre, stock_actual, stock_minimo
    FROM productos
    WHERE stock_actual <= stock_minimo AND activo = 1
    LIMIT 5
") ?: [];

$estado_color = [
    'abierta'    => 'badge-abierta',
    'en_proceso' => 'badge-proceso',
    'lista'      => 'badge-lista',
    'cobrada'    => 'badge-cobrada',
    'anulada'    => 'badge-anulada',
];
$estado_texto = [
    'abierta'    => 'Abierta',
    'en_proceso' => 'En Proceso',
    'lista'      => 'Lista',
    'cobrada'    => 'Cobrada',
    'anulada'    => 'Anulada',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — <?= APP_NAME ?></title>
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- ✅ CSS SEPARADOS (antes era todo inline) -->
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>

<!-- ══ SIDEBAR ══ -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="icon"><i class="fas fa-wrench"></i></div>
    <div>
      <h2><?= APP_NAME ?></h2>
      <span>v<?= APP_VERSION ?></span>
    </div>
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
    <a href="modules/precios/index.php" class="menu-item">
      <i class="fas fa-tags"></i> Precios y Servicios
    </a>

    <?php if ($rol === 'administrador'): ?>
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
    <div class="user-avatar"><?= strtoupper(substr($nombre, 0, 1)) ?></div>
    <div class="user-info">
      <strong><?= htmlspecialchars($nombre) ?></strong>
      <span><?= $rol ?></span>
    </div>
    <a href="logout.php" class="btn-logout" title="Cerrar sesión">
      <i class="fas fa-right-from-bracket"></i>
    </a>
  </div>
</aside>

<!-- ══ CONTENIDO PRINCIPAL ══ -->
<div class="main">

  <header class="topbar">
    <h1><i class="fas fa-gauge-high"></i> Dashboard</h1>
    <div class="topbar-right">
      <span class="topbar-date">
        <i class="fas fa-calendar-day"></i>
        <span id="reloj"><?= date('d/m/Y — H:i') ?></span>
      </span>
      <button class="btn-notif">
        <i class="fas fa-bell"></i>
        <?php if ($stock_bajo > 0): ?><span class="notif-dot"></span><?php endif; ?>
      </button>
    </div>
  </header>

  <div class="content">

    <!-- Tarjetas de estadísticas -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon naranja"><i class="fas fa-clipboard-list"></i></div>
        <div class="stat-info">
          <div class="valor"><?= $ordenes_abiertas ?></div>
          <div class="label">Órdenes Activas</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon verde"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
          <div class="valor"><?= $ordenes_listas ?></div>
          <div class="label">Listas para Cobrar</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon azul"><i class="fas fa-boxes-stacked"></i></div>
        <div class="stat-info">
          <div class="valor"><?= $total_productos ?></div>
          <div class="label">Productos en Stock</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon rojo"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="stat-info">
          <div class="valor"><?= $stock_bajo ?></div>
          <div class="label">Stock Bajo</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon morado"><i class="fas fa-users"></i></div>
        <div class="stat-info">
          <div class="valor"><?= $total_clientes ?></div>
          <div class="label">Clientes Registrados</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon amarillo"><i class="fas fa-circle-dollar-to-slot"></i></div>
        <div class="stat-info">
          <div class="valor">S/ <?= number_format($ventas_hoy, 0) ?></div>
          <div class="label">Ventas de Hoy</div>
        </div>
      </div>
    </div>

    <!-- Grid principal -->
    <div class="content-grid">

      <!-- Tabla últimas órdenes -->
      <div class="card">
        <div class="card-header">
          <h3><i class="fas fa-clipboard-list"></i> Últimas Órdenes de Trabajo</h3>
          <a href="modules/ordenes/index.php" class="btn-ver">Ver todas →</a>
        </div>
        <?php if (empty($ultimas_ordenes)): ?>
          <div class="empty">
            <i class="fas fa-clipboard"></i>
            <p>No hay órdenes de trabajo aún</p>
          </div>
        <?php else: ?>
        <div class="tabla-wrapper">
          <table>
            <thead>
              <tr>
                <th>N° OT</th>
                <th>Cliente</th>
                <th>Placa</th>
                <th>Mecánico</th>
                <th>Estado</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ultimas_ordenes as $ot): ?>
              <tr>
                <td><strong><?= htmlspecialchars($ot['numero']) ?></strong></td>
                <td><?= htmlspecialchars($ot['cliente']) ?></td>
                <td><code><?= htmlspecialchars($ot['placa']) ?></code></td>
                <td><?= htmlspecialchars($ot['mecanico'] ?? '—') ?></td>
                <td><span class="badge <?= $estado_color[$ot['estado']] ?>"><?= $estado_texto[$ot['estado']] ?></span></td>
                <td><strong>S/ <?= number_format($ot['total'], 2) ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- Panel lateral -->
      <div class="side-panels">

        <!-- Accesos rápidos -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fas fa-bolt"></i> Accesos Rápidos</h3>
          </div>
          <div class="quick-grid">
            <a href="modules/ordenes/crear.php" class="quick-btn">
              <i class="fas fa-plus-circle qi-azul"></i> Nueva Orden
            </a>
            <a href="modules/clientes/crear.php" class="quick-btn">
              <i class="fas fa-user-plus qi-verde"></i> Nuevo Cliente
            </a>
            <a href="modules/comprobantes/boleta.php" class="quick-btn">
              <i class="fas fa-receipt qi-naranja"></i> Nueva Boleta
            </a>
            <a href="modules/inventario/index.php" class="quick-btn">
              <i class="fas fa-boxes-stacked qi-morado"></i> Ver Stock
            </a>
          </div>
        </div>

        <!-- Stock bajo -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fas fa-triangle-exclamation"></i> Stock Bajo</h3>
            <a href="modules/inventario/index.php" class="btn-ver">Ver →</a>
          </div>
          <?php if (empty($productos_bajo)): ?>
            <div class="empty">
              <i class="fas fa-check-circle" style="color:var(--verde);opacity:1"></i>
              <p>Todo el stock está bien ✓</p>
            </div>
          <?php else: ?>
            <?php foreach ($productos_bajo as $p):
              $pct = $p['stock_minimo'] > 0 ? min(100, ($p['stock_actual'] / $p['stock_minimo']) * 100) : 0;
            ?>
            <div class="stock-item">
              <div class="stock-bar-wrap">
                <div class="stock-nombre"><?= htmlspecialchars($p['nombre']) ?></div>
                <div class="stock-bar">
                  <div class="stock-fill" style="width:<?= $pct ?>%"></div>
                </div>
              </div>
              <div class="stock-num"><?= $p['stock_actual'] ?></div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Gráfica -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Servicios del Mes</h3>
          </div>
          <div class="chart-wrap">
            <canvas id="chartServicios" height="180"></canvas>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- ✅ JS SEPARADOS -->
<script src="assets/js/main.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>