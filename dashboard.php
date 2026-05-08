<?php
// ============================================================
//  dashboard.php  —  Panel Principal
//  Sistema de Gestión — Taller Mecánico
// ============================================================
session_start();
require_once 'config/database.php';

// Protección: si no está logueado, redirigir al login
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

// ── Últimas órdenes de trabajo ──
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

// ── Productos con stock bajo ──
$productos_bajo = dbQuery("
    SELECT nombre, stock_actual, stock_minimo
    FROM productos
    WHERE stock_actual <= stock_minimo AND activo = 1
    LIMIT 5
") ?: [];

// Colores por estado
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --azul-oscuro:  #0f1e36;
      --azul-medio:   #1a3a5c;
      --azul-acento:  #1d6fa4;
      --azul-claro:   #3b9fd1;
      --naranja:      #e8820c;
      --verde:        #27ae60;
      --rojo:         #e74c3c;
      --amarillo:     #f39c12;
      --morado:       #8e44ad;
      --blanco:       #ffffff;
      --fondo:        #f0f4f8;
      --sidebar-w:    260px;
      --texto:        #1a2e44;
      --texto-muted:  #6b8099;
      --borde:        #dde4ec;
      --sombra:       0 2px 12px rgba(0,0,0,0.08);
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--fondo);
      color: var(--texto);
      display: flex;
      min-height: 100vh;
    }

    /* ══════════════════════════════
       SIDEBAR
    ══════════════════════════════ */
    .sidebar {
      width: var(--sidebar-w);
      background: var(--azul-oscuro);
      min-height: 100vh;
      position: fixed;
      top: 0; left: 0;
      display: flex;
      flex-direction: column;
      z-index: 100;
      transition: transform .3s;
    }

    .sidebar-logo {
      padding: 24px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .sidebar-logo .icon {
      width: 42px; height: 42px;
      background: linear-gradient(135deg, var(--naranja), #c0620a);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; color: #fff;
      flex-shrink: 0;
    }

    .sidebar-logo h2 {
      font-family: 'Rajdhani', sans-serif;
      font-size: 17px; font-weight: 700;
      color: #fff;
      line-height: 1.2;
    }

    .sidebar-logo span {
      font-size: 11px;
      color: rgba(255,255,255,0.4);
      font-weight: 400;
    }

    .sidebar-menu {
      flex: 1;
      padding: 16px 0;
      overflow-y: auto;
    }

    .menu-section {
      padding: 8px 20px 4px;
      font-size: 10px;
      font-weight: 600;
      letter-spacing: 1.5px;
      color: rgba(255,255,255,0.25);
      text-transform: uppercase;
    }

    .menu-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 11px 20px;
      color: rgba(255,255,255,0.6);
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      transition: all .2s;
      position: relative;
      margin: 1px 8px;
      border-radius: 8px;
    }

    .menu-item:hover {
      background: rgba(255,255,255,0.07);
      color: #fff;
    }

    .menu-item.active {
      background: linear-gradient(90deg, rgba(29,111,164,0.5), rgba(29,111,164,0.1));
      color: #fff;
      border-left: 3px solid var(--azul-claro);
    }

    .menu-item i { width: 18px; text-align: center; font-size: 15px; }

    .menu-badge {
      margin-left: auto;
      background: var(--rojo);
      color: #fff;
      font-size: 10px;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: 10px;
    }

    .sidebar-user {
      padding: 16px 20px;
      border-top: 1px solid rgba(255,255,255,0.06);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .user-avatar {
      width: 38px; height: 38px;
      background: var(--azul-acento);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 16px; color: #fff;
      font-weight: 700;
      flex-shrink: 0;
    }

    .user-info { flex: 1; min-width: 0; }
    .user-info strong { display: block; font-size: 13px; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-info span   { font-size: 11px; color: rgba(255,255,255,0.4); text-transform: capitalize; }

    .btn-logout {
      color: rgba(255,255,255,0.3);
      font-size: 16px;
      cursor: pointer;
      transition: color .2s;
      background: none; border: none;
    }
    .btn-logout:hover { color: var(--rojo); }

    /* ══════════════════════════════
       CONTENIDO PRINCIPAL
    ══════════════════════════════ */
    .main {
      margin-left: var(--sidebar-w);
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    /* ── Topbar ── */
    .topbar {
      background: #fff;
      padding: 0 28px;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid var(--borde);
      position: sticky;
      top: 0; z-index: 50;
      box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    }

    .topbar h1 {
      font-family: 'Rajdhani', sans-serif;
      font-size: 22px;
      font-weight: 700;
      color: var(--texto);
    }

    .topbar-right {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .topbar-date {
      font-size: 13px;
      color: var(--texto-muted);
    }

    .btn-notif {
      position: relative;
      width: 38px; height: 38px;
      border-radius: 8px;
      border: 1px solid var(--borde);
      background: #fff;
      display: flex; align-items: center; justify-content: center;
      color: var(--texto-muted);
      cursor: pointer;
      font-size: 16px;
      transition: all .2s;
    }
    .btn-notif:hover { background: var(--fondo); color: var(--texto); }

    .notif-dot {
      position: absolute;
      top: 6px; right: 6px;
      width: 8px; height: 8px;
      background: var(--rojo);
      border-radius: 50%;
      border: 2px solid #fff;
    }

    /* ── Contenido ── */
    .content {
      padding: 28px;
      flex: 1;
    }

    /* ── Tarjetas de estadísticas ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 18px;
      margin-bottom: 28px;
    }

    .stat-card {
      background: #fff;
      border-radius: 14px;
      padding: 22px;
      box-shadow: var(--sombra);
      border: 1px solid var(--borde);
      display: flex;
      align-items: center;
      gap: 16px;
      transition: transform .2s, box-shadow .2s;
      cursor: default;
    }

    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }

    .stat-icon {
      width: 52px; height: 52px;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px;
      flex-shrink: 0;
    }

    .stat-icon.azul    { background: rgba(29,111,164,0.12); color: var(--azul-acento); }
    .stat-icon.naranja { background: rgba(232,130,12,0.12); color: var(--naranja); }
    .stat-icon.verde   { background: rgba(39,174,96,0.12);  color: var(--verde); }
    .stat-icon.rojo    { background: rgba(231,76,60,0.12);  color: var(--rojo); }
    .stat-icon.morado  { background: rgba(142,68,173,0.12); color: var(--morado); }
    .stat-icon.amarillo{ background: rgba(243,156,18,0.12); color: var(--amarillo); }

    .stat-info { flex: 1; min-width: 0; }
    .stat-info .valor {
      font-size: 28px;
      font-weight: 700;
      color: var(--texto);
      font-family: 'Rajdhani', sans-serif;
      line-height: 1;
    }
    .stat-info .label {
      font-size: 12px;
      color: var(--texto-muted);
      margin-top: 4px;
      font-weight: 500;
    }

    /* ── Grid de tablas y gráfica ── */
    .content-grid {
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: 20px;
    }

    .card {
      background: #fff;
      border-radius: 14px;
      border: 1px solid var(--borde);
      box-shadow: var(--sombra);
      overflow: hidden;
    }

    .card-header {
      padding: 18px 22px;
      border-bottom: 1px solid var(--borde);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .card-header h3 {
      font-size: 15px;
      font-weight: 600;
      color: var(--texto);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .card-header h3 i { color: var(--azul-acento); }

    .btn-ver {
      font-size: 12px;
      color: var(--azul-acento);
      text-decoration: none;
      font-weight: 600;
      padding: 4px 12px;
      border-radius: 6px;
      border: 1px solid rgba(29,111,164,0.2);
      transition: all .2s;
    }
    .btn-ver:hover { background: var(--azul-acento); color: #fff; }

    /* ── Tabla ── */
    table { width: 100%; border-collapse: collapse; }
    th {
      background: var(--fondo);
      padding: 10px 16px;
      font-size: 11px;
      font-weight: 600;
      color: var(--texto-muted);
      text-transform: uppercase;
      letter-spacing: 0.8px;
      text-align: left;
    }
    td {
      padding: 12px 16px;
      font-size: 13px;
      color: var(--texto);
      border-top: 1px solid var(--borde);
    }
    tr:hover td { background: #f8fafd; }

    /* ── Badges de estado ── */
    .badge {
      font-size: 11px;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 20px;
      white-space: nowrap;
    }
    .badge-abierta  { background: rgba(29,111,164,0.1); color: var(--azul-acento); }
    .badge-proceso  { background: rgba(243,156,18,0.1); color: var(--amarillo); }
    .badge-lista    { background: rgba(39,174,96,0.1);  color: var(--verde); }
    .badge-cobrada  { background: rgba(142,68,173,0.1); color: var(--morado); }
    .badge-anulada  { background: rgba(231,76,60,0.1);  color: var(--rojo); }

    /* ── Panel lateral derecho ── */
    .side-panels { display: flex; flex-direction: column; gap: 20px; }

    /* ── Stock bajo ── */
    .stock-item {
      padding: 12px 22px;
      border-top: 1px solid var(--borde);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .stock-bar-wrap { flex: 1; }
    .stock-nombre { font-size: 12px; font-weight: 600; color: var(--texto); margin-bottom: 4px; }
    .stock-bar {
      height: 6px;
      background: var(--borde);
      border-radius: 3px;
      overflow: hidden;
    }
    .stock-fill {
      height: 100%;
      border-radius: 3px;
      background: var(--rojo);
    }
    .stock-num {
      font-size: 12px;
      font-weight: 700;
      color: var(--rojo);
      white-space: nowrap;
    }

    /* ── Gráfica ── */
    .chart-wrap { padding: 20px; }

    /* ── Accesos rápidos ── */
    .quick-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      padding: 16px;
    }

    .quick-btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      padding: 16px 10px;
      background: var(--fondo);
      border-radius: 10px;
      text-decoration: none;
      color: var(--texto);
      font-size: 12px;
      font-weight: 600;
      transition: all .2s;
      border: 1px solid var(--borde);
      text-align: center;
    }
    .quick-btn:hover { background: var(--azul-acento); color: #fff; transform: translateY(-2px); }
    .quick-btn i { font-size: 22px; }
    .quick-btn:hover i { color: #fff; }
    .quick-btn .qi-azul    { color: var(--azul-acento); }
    .quick-btn .qi-naranja { color: var(--naranja); }
    .quick-btn .qi-verde   { color: var(--verde); }
    .quick-btn .qi-morado  { color: var(--morado); }

    /* ── Empty state ── */
    .empty {
      padding: 32px;
      text-align: center;
      color: var(--texto-muted);
      font-size: 13px;
    }
    .empty i { font-size: 32px; display: block; margin-bottom: 8px; opacity: .4; }

    /* ── Responsive ── */
    @media (max-width: 1024px) {
      .content-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .main { margin-left: 0; }
      .stats-grid { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

<!-- ══════════════════════════════
     SIDEBAR
══════════════════════════════ -->
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

<!-- ══════════════════════════════
     CONTENIDO PRINCIPAL
══════════════════════════════ -->
<div class="main">

  <!-- Topbar -->
  <header class="topbar">
    <h1><i class="fas fa-gauge-high" style="color:var(--azul-acento);margin-right:8px"></i>Dashboard</h1>
    <div class="topbar-right">
      <span class="topbar-date">
        <i class="fas fa-calendar-day" style="color:var(--azul-acento)"></i>
        <?= date('d/m/Y — H:i') ?>
      </span>
      <button class="btn-notif">
        <i class="fas fa-bell"></i>
        <?php if ($stock_bajo > 0): ?><span class="notif-dot"></span><?php endif; ?>
      </button>
    </div>
  </header>

  <div class="content">

    <!-- ── Tarjetas de estadísticas ── -->
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
        <div class="stat-icon amarillo"><i class="fas fa-soles-sign"></i></div>
        <div class="stat-info">
          <div class="valor">S/ <?= number_format($ventas_hoy, 0) ?></div>
          <div class="label">Ventas de Hoy</div>
        </div>
      </div>

    </div>

    <!-- ── Grid principal ── -->
    <div class="content-grid">

      <!-- Tabla de últimas órdenes -->
      <div class="card">
        <div class="card-header">
          <h3><i class="fas fa-clipboard-list"></i> Últimas Órdenes de Trabajo</h3>
          <a href="modules/ordenes/index.php" class="btn-ver">Ver todas →</a>
        </div>
        <?php if (empty($ultimas_ordenes)): ?>
          <div class="empty">
            <i class="fas fa-clipboard"></i>
            No hay órdenes de trabajo aún
          </div>
        <?php else: ?>
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
              <td><span style="font-family:monospace;font-weight:700"><?= htmlspecialchars($ot['placa']) ?></span></td>
              <td><?= htmlspecialchars($ot['mecanico'] ?? '—') ?></td>
              <td><span class="badge <?= $estado_color[$ot['estado']] ?>"><?= $estado_texto[$ot['estado']] ?></span></td>
              <td><strong>S/ <?= number_format($ot['total'], 2) ?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
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
              <i class="fas fa-check-circle" style="color:var(--verde)"></i>
              Todo el stock está bien ✓
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

        <!-- Mini gráfica -->
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

<script>
// Gráfica de dona
const ctx = document.getElementById('chartServicios').getContext('2d');
new Chart(ctx, {
  type: 'doughnut',
  data: {
    labels: ['Cambio Aceite', 'Frenos', 'Afinamiento', 'Diagnóstico', 'Otros'],
    datasets: [{
      data: [35, 25, 20, 12, 8],
      backgroundColor: ['#1d6fa4','#e8820c','#27ae60','#8e44ad','#f39c12'],
      borderWidth: 0,
      hoverOffset: 6
    }]
  },
  options: {
    cutout: '65%',
    plugins: {
      legend: {
        position: 'bottom',
        labels: { font: { size: 11 }, padding: 12 }
      }
    }
  }
});
</script>

</body>
</html>