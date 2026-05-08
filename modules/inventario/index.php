<?php
// ============================================================
//  modules/inventario/index.php — Lista de Productos
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }

$rol    = $_SESSION['usuario_rol'];
$nombre = $_SESSION['usuario_nombre'];

// ── Desactivar producto (soft delete) ──
if (isset($_GET['desactivar']) && $rol === 'administrador') {
    $id = (int)$_GET['desactivar'];
    dbQuery("UPDATE productos SET activo = 0 WHERE id = ?", [$id]);
    header('Location: index.php?msg=desactivado'); exit;
}

// ── Filtros de búsqueda ──
$buscar    = trim($_GET['buscar']    ?? '');
$categoria = (int)($_GET['categoria'] ?? 0);
$stock_bajo = isset($_GET['stock_bajo']);

$sql    = "SELECT p.*, c.nombre AS categoria_nombre
           FROM productos p
           LEFT JOIN categorias c ON p.categoria_id = c.id
           WHERE p.activo = 1";
$params = [];

if ($buscar) {
    $sql .= " AND (p.nombre LIKE ? OR p.descripcion LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}
if ($categoria) {
    $sql .= " AND p.categoria_id = ?";
    $params[] = $categoria;
}
if ($stock_bajo) {
    $sql .= " AND p.stock_actual <= p.stock_minimo";
}

$sql .= " ORDER BY p.nombre ASC";
$productos = dbQuery($sql, $params) ?: [];

// ── Categorías para el filtro ──
$categorias = dbQuery("SELECT * FROM categorias WHERE tipo = 'producto' ORDER BY nombre") ?: [];

// ── Resumen de tarjetas ──
$resumen = dbQuery("
    SELECT
        COUNT(*)                              AS total,
        SUM(stock_actual <= stock_minimo)     AS stock_bajo,
        SUM(stock_actual = 0)                 AS sin_stock,
        SUM(precio_con_igv * stock_actual)    AS valor_total
    FROM productos WHERE activo = 1
")[0] ?? [];

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventario — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/inventario.css">
</head>
<body>

<!-- ══ SIDEBAR ══ -->
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
    <a href="index.php"                 class="menu-item active"><i class="fas fa-boxes-stacked"></i> Inventario</a>
    <a href="../precios/index.php"      class="menu-item"><i class="fas fa-tags"></i> Precios y Servicios</a>
    <?php if ($rol === 'administrador'): ?>
    <div class="menu-section">Administración</div>
    <a href="../reportes/index.php"     class="menu-item"><i class="fas fa-chart-bar"></i> Reportes</a>
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

<!-- ══ CONTENIDO PRINCIPAL ══ -->
<div class="main">
  <header class="topbar">
    <h1><i class="fas fa-boxes-stacked"></i> Inventario de Productos</h1>
    <div class="topbar-right">
      <span class="topbar-date"><i class="fas fa-calendar-day"></i> <?= date('d/m/Y') ?></span>
      <a href="movimientos.php" class="btn btn-outline"><i class="fas fa-clock-rotate-left"></i> Movimientos</a>
      <?php if ($rol !== 'mecanico'): ?>
      <a href="crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo Producto</a>
      <?php endif; ?>
    </div>
  </header>

  <div class="content">

    <!-- Alertas de mensaje -->
    <?php if ($msg === 'creado'):   ?><div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Producto registrado correctamente.</div><?php endif; ?>
    <?php if ($msg === 'editado'):  ?><div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Producto actualizado correctamente.</div><?php endif; ?>
    <?php if ($msg === 'ajuste'):   ?><div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Ajuste de stock registrado.</div><?php endif; ?>
    <?php if ($msg === 'desactivado'): ?><div class="alert alert-error alert-auto"><i class="fas fa-trash"></i> Producto desactivado.</div><?php endif; ?>

    <!-- ── Tarjetas de resumen ── -->
    <div class="inv-resumen">
      <div class="inv-card">
        <div class="inv-card-icon bg-azul"><i class="fas fa-cubes"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num"><?= $resumen['total'] ?? 0 ?></span>
          <span class="inv-card-label">Productos activos</span>
        </div>
      </div>
      <div class="inv-card">
        <div class="inv-card-icon bg-amarillo"><i class="fas fa-triangle-exclamation"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num"><?= $resumen['stock_bajo'] ?? 0 ?></span>
          <span class="inv-card-label">Stock bajo mínimo</span>
        </div>
      </div>
      <div class="inv-card">
        <div class="inv-card-icon bg-rojo"><i class="fas fa-circle-xmark"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num"><?= $resumen['sin_stock'] ?? 0 ?></span>
          <span class="inv-card-label">Sin stock</span>
        </div>
      </div>
      <div class="inv-card">
        <div class="inv-card-icon bg-verde"><i class="fas fa-dollar-sign"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num">S/ <?= number_format($resumen['valor_total'] ?? 0, 2) ?></span>
          <span class="inv-card-label">Valor total en almacén</span>
        </div>
      </div>
    </div>

    <!-- ── Filtros ── -->
    <form method="GET" action="index.php">
      <div class="filtros-bar">
        <div class="search-wrapper">
          <i class="fas fa-search"></i>
          <input type="text" name="buscar" class="search-input"
                 placeholder="Buscar producto..."
                 value="<?= htmlspecialchars($buscar) ?>">
        </div>
        <select name="categoria" class="form-control" style="width:200px" onchange="this.form.submit()">
          <option value="">Todas las categorías</option>
          <?php foreach ($categorias as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= $categoria == $cat['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <label class="filtro-check">
          <input type="checkbox" name="stock_bajo" <?= $stock_bajo ? 'checked' : '' ?> onchange="this.form.submit()">
          <i class="fas fa-triangle-exclamation"></i> Solo stock bajo
        </label>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
        <?php if ($buscar || $categoria || $stock_bajo): ?>
          <a href="index.php" class="btn btn-outline"><i class="fas fa-times"></i> Limpiar</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- ── Tabla de productos ── -->
    <div class="card">
      <div class="card-header">
        <h3><i class="fas fa-boxes-stacked"></i> Lista de Productos
          <span class="count-badge"><?= count($productos) ?> producto<?= count($productos) !== 1 ? 's' : '' ?></span>
        </h3>
      </div>

      <?php if (empty($productos)): ?>
        <div class="empty">
          <i class="fas fa-boxes-stacked"></i>
          <p>No se encontraron productos</p>
          <a href="crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Registrar primer producto</a>
        </div>
      <?php else: ?>
      <div class="tabla-wrapper">
        <table>
          <thead>
            <tr>
              <th>Producto</th>
              <th>Categoría</th>
              <th>Stock actual</th>
              <th>Stock mín.</th>
              <th>Estado</th>
              <th>Precio c/IGV</th>
              <th>Valor almacén</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($productos as $p):
              // Determinar estado de stock
              if ($p['stock_actual'] == 0) {
                $estado = 'sin_stock'; $estado_label = 'Sin stock';
              } elseif ($p['stock_actual'] <= $p['stock_minimo']) {
                $estado = 'stock_bajo'; $estado_label = 'Stock bajo';
              } else {
                $estado = 'ok'; $estado_label = 'Normal';
              }
              $valor = $p['precio_con_igv'] * $p['stock_actual'];
              $pct   = $p['stock_minimo'] > 0
                       ? min(100, round(($p['stock_actual'] / $p['stock_minimo']) * 100))
                       : 100;
            ?>
            <tr class="fila-<?= $estado ?>">
              <td>
                <div class="prod-nombre"><?= htmlspecialchars($p['nombre']) ?></div>
                <?php if ($p['descripcion']): ?>
                <div class="prod-desc"><?= htmlspecialchars(substr($p['descripcion'], 0, 55)) ?>...</div>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge-categoria"><?= htmlspecialchars($p['categoria_nombre'] ?? '—') ?></span>
              </td>
              <td>
                <div class="stock-info">
                  <span class="stock-num <?= $estado ?>"><?= number_format($p['stock_actual'], 0) ?></span>
                  <span class="stock-um"><?= htmlspecialchars($p['unidad_medida']) ?></span>
                </div>
                <!-- Barra de progreso de stock -->
                <div class="stock-bar">
                  <div class="stock-bar-fill <?= $estado ?>" style="width:<?= $pct ?>%"></div>
                </div>
              </td>
              <td>
                <span style="color:var(--texto-muted);font-size:13px">
                  <?= number_format($p['stock_minimo'], 0) ?> <?= $p['unidad_medida'] ?>
                </span>
              </td>
              <td>
                <?php if ($estado === 'sin_stock'): ?>
                  <span class="badge badge-anulada"><i class="fas fa-circle-xmark"></i> Sin stock</span>
                <?php elseif ($estado === 'stock_bajo'): ?>
                  <span class="badge badge-proceso"><i class="fas fa-triangle-exclamation"></i> Stock bajo</span>
                <?php else: ?>
                  <span class="badge badge-lista"><i class="fas fa-check"></i> Normal</span>
                <?php endif; ?>
              </td>
              <td class="td-precio">S/ <?= number_format($p['precio_con_igv'], 2) ?></td>
              <td class="td-precio">S/ <?= number_format($valor, 2) ?></td>
              <td>
                <div class="acciones">
                  <a href="ajuste.php?id=<?= $p['id'] ?>" class="btn-accion ver" title="Ajustar stock">
                    <i class="fas fa-sliders"></i>
                  </a>
                  <?php if ($rol !== 'mecanico'): ?>
                  <a href="editar.php?id=<?= $p['id'] ?>" class="btn-accion editar" title="Editar">
                    <i class="fas fa-pen"></i>
                  </a>
                  <?php endif; ?>
                  <?php if ($rol === 'administrador'): ?>
                  <a href="index.php?desactivar=<?= $p['id'] ?>"
                     class="btn-accion eliminar" title="Desactivar"
                     onclick="return confirm('¿Desactivar el producto «<?= htmlspecialchars(addslashes($p['nombre'])) ?>»?')">
                    <i class="fas fa-ban"></i>
                  </a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<script src="../../assets/js/main.js"></script>
<script>
// Auto-ocultar alertas después de 4 segundos
document.querySelectorAll('.alert-auto').forEach(el => {
  setTimeout(() => { el.style.opacity='0'; el.style.transition='opacity .5s';
    setTimeout(()=>el.remove(), 500); }, 4000);
});
</script>
</body>
</html>