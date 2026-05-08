<?php
// ============================================================
//  modules/inventario/movimientos.php — Historial de Stock
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }

$rol    = $_SESSION['usuario_rol'];
$nombre = $_SESSION['usuario_nombre'];

$producto_id = (int)($_GET['producto_id'] ?? 0);
$tipo_filtro = $_GET['tipo'] ?? '';

$sql    = "SELECT m.*, p.nombre AS producto, p.unidad_medida,
                  CONCAT(u.nombre,' ',u.apellido) AS usuario
           FROM movimientos_stock m
           LEFT JOIN productos p ON m.producto_id = p.id
           LEFT JOIN usuarios  u ON m.usuario_id  = u.id
           WHERE 1=1";
$params = [];

if ($producto_id) {
    $sql .= " AND m.producto_id = ?"; $params[] = $producto_id;
}
if ($tipo_filtro) {
    $sql .= " AND m.tipo = ?"; $params[] = $tipo_filtro;
}
$sql .= " ORDER BY m.fecha DESC LIMIT 200";
$movimientos = dbQuery($sql, $params) ?: [];

$productos = dbQuery("SELECT id, nombre FROM productos WHERE activo=1 ORDER BY nombre") ?: [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Movimientos — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/inventario.css">
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="icon"><i class="fas fa-wrench"></i></div>
    <div><h2><?= APP_NAME ?></h2><span>v<?= APP_VERSION ?></span></div>
  </div>
  <nav class="sidebar-menu">
    <div class="menu-section">Principal</div>
    <a href="../../dashboard.php" class="menu-item"><i class="fas fa-gauge-high"></i> Dashboard</a>
    <div class="menu-section">Operaciones</div>
    <a href="../ordenes/index.php"      class="menu-item"><i class="fas fa-clipboard-list"></i> Órdenes de Trabajo</a>
    <a href="../clientes/index.php"     class="menu-item"><i class="fas fa-users"></i> Clientes y Vehículos</a>
    <a href="../comprobantes/index.php" class="menu-item"><i class="fas fa-file-invoice"></i> Boletas y Facturas</a>
    <div class="menu-section">Almacén</div>
    <a href="index.php"            class="menu-item active"><i class="fas fa-boxes-stacked"></i> Inventario</a>
    <a href="../precios/index.php" class="menu-item"><i class="fas fa-tags"></i> Precios y Servicios</a>
    <?php if ($rol === 'administrador'): ?>
    <div class="menu-section">Administración</div>
    <a href="../reportes/index.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reportes</a>
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
    <h1><i class="fas fa-clock-rotate-left"></i> Historial de Movimientos</h1>
    <div class="topbar-right">
      <a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
  </header>

  <div class="content">
    <!-- Filtros -->
    <form method="GET" action="movimientos.php">
      <div class="filtros-bar">
        <select name="producto_id" class="form-control" style="width:260px" onchange="this.form.submit()">
          <option value="">Todos los productos</option>
          <?php foreach ($productos as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $producto_id == $p['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <select name="tipo" class="form-control" style="width:160px" onchange="this.form.submit()">
          <option value="">Entradas y Salidas</option>
          <option value="entrada" <?= $tipo_filtro==='entrada' ? 'selected':'' ?>>Solo Entradas</option>
          <option value="salida"  <?= $tipo_filtro==='salida'  ? 'selected':'' ?>>Solo Salidas</option>
        </select>
        <?php if ($producto_id || $tipo_filtro): ?>
          <a href="movimientos.php" class="btn btn-outline"><i class="fas fa-times"></i> Limpiar</a>
        <?php endif; ?>
      </div>
    </form>

    <div class="card">
      <div class="card-header">
        <h3><i class="fas fa-clock-rotate-left"></i> Movimientos
          <span class="count-badge"><?= count($movimientos) ?> registro<?= count($movimientos)!==1?'s':'' ?></span>
        </h3>
      </div>

      <?php if (empty($movimientos)): ?>
        <div class="empty">
          <i class="fas fa-clock-rotate-left"></i>
          <p>No hay movimientos registrados</p>
        </div>
      <?php else: ?>
      <div class="tabla-wrapper">
        <table>
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Producto</th>
              <th>Tipo</th>
              <th>Cantidad</th>
              <th>Fuente</th>
              <th>Usuario</th>
              <th>Observación</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($movimientos as $m): ?>
            <tr>
              <td style="white-space:nowrap;font-size:12px;color:var(--texto-muted)">
                <?= date('d/m/Y H:i', strtotime($m['fecha'])) ?>
              </td>
              <td class="prod-nombre"><?= htmlspecialchars($m['producto']) ?></td>
              <td>
                <span class="mov-tipo <?= $m['tipo'] === 'entrada' ? 'mov-entrada' : 'mov-salida' ?>">
                  <i class="fas fa-arrow-<?= $m['tipo']==='entrada'?'down':'up' ?>"></i>
                  <?= ucfirst($m['tipo']) ?>
                </span>
              </td>
              <td>
                <strong><?= $m['tipo']==='entrada' ? '+' : '-' ?><?= number_format($m['cantidad'],2) ?></strong>
                <span style="font-size:11px;color:var(--texto-muted)"><?= $m['unidad_medida'] ?></span>
              </td>
              <td>
                <span class="badge <?= $m['fuente']==='iot' ? 'badge-cobrada' : 'badge-natural' ?>">
                  <?= strtoupper($m['fuente']) ?>
                </span>
              </td>
              <td style="font-size:13px"><?= htmlspecialchars($m['usuario'] ?? '—') ?></td>
              <td style="font-size:12px;color:var(--texto-muted)"><?= htmlspecialchars($m['observacion'] ?? '—') ?></td>
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
</body>
</html>