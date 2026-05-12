<?php
// ============================================================
//  includes/header.php — Sidebar reutilizable
//  Uso: require_once '../../includes/header.php';
//
//  Variables requeridas ANTES de incluir:
//    $PAGE_TITLE  = 'Inventario';     (título en topbar)
//    $PAGE_ICON   = 'fa-boxes-stacked'; (ícono FontAwesome)
//    $ACTIVE_MENU = 'inventario';      (qué ítem resaltar)
//
//  Ítems de $ACTIVE_MENU disponibles:
//    dashboard | ordenes | clientes | comprobantes |
//    inventario | precios | reportes | usuarios
// ============================================================

// Detectar profundidad para construir rutas relativas
$_script   = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);
$_base     = str_replace('\\', '/', realpath(__DIR__ . '/..')) . '/';
$_depth    = substr_count(str_replace($_base, '', $_script), '/');
$_prefix   = str_repeat('../', $_depth);  // ej: '../../'

$_rol    = $_SESSION['usuario_rol']    ?? '';
$_nombre = $_SESSION['usuario_nombre'] ?? '';
$_active = $ACTIVE_MENU ?? '';
$_title  = $PAGE_TITLE  ?? APP_NAME;
$_icon   = $PAGE_ICON   ?? 'fa-circle';

// Helper: clase CSS activa
function menuActivo(string $item): string {
    global $_active;
    return $_active === $item ? 'active' : '';
}
?>
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
    <a href="<?= $_prefix ?>dashboard.php" class="menu-item <?= menuActivo('dashboard') ?>">
      <i class="fas fa-gauge-high"></i> Dashboard
    </a>

    <div class="menu-section">Operaciones</div>
    <a href="<?= $_prefix ?>modules/ordenes/index.php" class="menu-item <?= menuActivo('ordenes') ?>">
      <i class="fas fa-clipboard-list"></i> Órdenes de Trabajo
    </a>
    <a href="<?= $_prefix ?>modules/clientes/index.php" class="menu-item <?= menuActivo('clientes') ?>">
      <i class="fas fa-users"></i> Clientes y Vehículos
    </a>
    <a href="<?= $_prefix ?>modules/comprobantes/index.php" class="menu-item <?= menuActivo('comprobantes') ?>">
      <i class="fas fa-file-invoice"></i> Boletas y Facturas
    </a>

    <div class="menu-section">Almacén</div>
    <a href="<?= $_prefix ?>modules/inventario/index.php" class="menu-item <?= menuActivo('inventario') ?>">
      <i class="fas fa-boxes-stacked"></i> Inventario
      <?php
      // Badge de stock bajo
      $stock_bajo_n = dbQuery("SELECT COUNT(*) AS n FROM productos WHERE activo=1 AND stock_actual <= stock_minimo")[0]['n'] ?? 0;
      if ($stock_bajo_n > 0): ?>
        <span class="menu-badge"><?= $stock_bajo_n ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= $_prefix ?>modules/precios/index.php" class="menu-item <?= menuActivo('precios') ?>">
      <i class="fas fa-tags"></i> Precios y Servicios
    </a>

    <?php if (esRol('administrador', 'cajero')): ?>
    <div class="menu-section">Administración</div>
    <?php endif; ?>

    <?php if (esRol('administrador')): ?>
    <a href="<?= $_prefix ?>modules/reportes/index.php" class="menu-item <?= menuActivo('reportes') ?>">
      <i class="fas fa-chart-bar"></i> Reportes
    </a>
    <a href="<?= $_prefix ?>modules/usuarios/index.php" class="menu-item <?= menuActivo('usuarios') ?>">
      <i class="fas fa-user-gear"></i> Usuarios
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($_nombre, 0, 1)) ?></div>
    <div class="user-info">
      <strong><?= htmlspecialchars($_nombre) ?></strong>
      <span><?= ucfirst($_rol) ?></span>
    </div>
    <a href="<?= $_prefix ?>logout.php" class="btn-logout" title="Cerrar sesión">
      <i class="fas fa-right-from-bracket"></i>
    </a>
  </div>
</aside>

<!-- ══ TOPBAR ══ -->
<div class="main">
  <header class="topbar">
    <h1><i class="fas <?= htmlspecialchars($_icon) ?>"></i> <?= htmlspecialchars($_title) ?></h1>
    <div class="topbar-right">
      <span class="topbar-date">
        <i class="fas fa-calendar-day"></i> <?= date('d/m/Y') ?>
      </span>
      <?php if (isset($TOPBAR_ACTIONS)) echo $TOPBAR_ACTIONS; ?>
    </div>
  </header>
  <div class="content">
<?php
// NOTA: el archivo que incluye header.php debe cerrar
// </div> (content) y </div> (main) al final de la página.
?>