<?php
// ============================================================
//  modules/precios/index.php — Precios y Servicios
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }

$rol    = $_SESSION['usuario_rol'];
$nombre = $_SESSION['usuario_nombre'];

// ── Desactivar servicio ──
if (isset($_GET['desactivar']) && $rol === 'administrador') {
    dbQuery("UPDATE servicios SET activo = 0 WHERE id = ?", [(int)$_GET['desactivar']]);
    header('Location: index.php?msg=desactivado'); exit;
}

// ── Filtros ──
$buscar   = trim($_GET['buscar']   ?? '');
$categoria = (int)($_GET['categoria'] ?? 0);

$sql    = "SELECT s.*, c.nombre AS categoria_nombre
           FROM servicios s
           LEFT JOIN categorias c ON s.categoria_id = c.id
           WHERE s.activo = 1";
$params = [];

if ($buscar) {
    $sql .= " AND (s.nombre LIKE ? OR s.descripcion LIKE ?)";
    $params[] = "%$buscar%"; $params[] = "%$buscar%";
}
if ($categoria) {
    $sql .= " AND s.categoria_id = ?";
    $params[] = $categoria;
}
$sql .= " ORDER BY c.nombre, s.nombre ASC";
$servicios = dbQuery($sql, $params) ?: [];

$categorias = dbQuery("SELECT * FROM categorias WHERE tipo = 'servicio' ORDER BY nombre") ?: [];

// Resumen
$resumen = dbQuery("SELECT COUNT(*) AS total, MIN(precio_base) AS precio_min, MAX(precio_base) AS precio_max, AVG(precio_base) AS precio_avg FROM servicios WHERE activo = 1")[0] ?? [];

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Precios y Servicios — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/precios.css">
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
    <a href="../inventario/index.php"  class="menu-item"><i class="fas fa-boxes-stacked"></i> Inventario</a>
    <a href="index.php"                class="menu-item active"><i class="fas fa-tags"></i> Precios y Servicios</a>
    <?php if ($rol === 'administrador'): ?>
    <div class="menu-section">Administración</div>
    <a href="../reportes/index.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reportes</a>
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
    <h1><i class="fas fa-tags"></i> Precios y Servicios</h1>
    <div class="topbar-right">
      <span class="topbar-date"><i class="fas fa-calendar-day"></i> <?= date('d/m/Y') ?></span>
      <?php if ($rol !== 'mecanico'): ?>
      <a href="crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo Servicio</a>
      <?php endif; ?>
    </div>
  </header>

  <div class="content">

    <?php if ($msg === 'creado'):    ?><div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Servicio registrado correctamente.</div><?php endif; ?>
    <?php if ($msg === 'editado'):   ?><div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Servicio actualizado correctamente.</div><?php endif; ?>
    <?php if ($msg === 'desactivado'): ?><div class="alert alert-error alert-auto"><i class="fas fa-ban"></i> Servicio desactivado.</div><?php endif; ?>

    <!-- Tarjetas resumen -->
    <div class="inv-resumen">
      <div class="inv-card">
        <div class="inv-card-icon bg-azul"><i class="fas fa-screwdriver-wrench"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num"><?= $resumen['total'] ?? 0 ?></span>
          <span class="inv-card-label">Servicios activos</span>
        </div>
      </div>
      <div class="inv-card">
        <div class="inv-card-icon bg-verde"><i class="fas fa-arrow-down"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num">S/ <?= number_format($resumen['precio_min'] ?? 0, 2) ?></span>
          <span class="inv-card-label">Precio mínimo</span>
        </div>
      </div>
      <div class="inv-card">
        <div class="inv-card-icon bg-amarillo"><i class="fas fa-chart-line"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num">S/ <?= number_format($resumen['precio_avg'] ?? 0, 2) ?></span>
          <span class="inv-card-label">Precio promedio</span>
        </div>
      </div>
      <div class="inv-card">
        <div class="inv-card-icon bg-rojo"><i class="fas fa-arrow-up"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num">S/ <?= number_format($resumen['precio_max'] ?? 0, 2) ?></span>
          <span class="inv-card-label">Precio máximo</span>
        </div>
      </div>
    </div>

    <!-- Filtros -->
    <form method="GET" action="index.php">
      <div class="filtros-bar">
        <div class="search-wrapper">
          <i class="fas fa-search"></i>
          <input type="text" name="buscar" class="search-input"
                 placeholder="Buscar servicio..."
                 value="<?= htmlspecialchars($buscar) ?>">
        </div>
        <select name="categoria" class="form-control" style="width:220px" onchange="this.form.submit()">
          <option value="">Todas las categorías</option>
          <?php foreach ($categorias as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= $categoria == $cat['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
        <?php if ($buscar || $categoria): ?>
          <a href="index.php" class="btn btn-outline"><i class="fas fa-times"></i> Limpiar</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Tabla -->
    <div class="card">
      <div class="card-header">
        <h3><i class="fas fa-tags"></i> Lista de Servicios
          <span class="count-badge"><?= count($servicios) ?> servicio<?= count($servicios) !== 1 ? 's' : '' ?></span>
        </h3>
      </div>

      <?php if (empty($servicios)): ?>
        <div class="empty">
          <i class="fas fa-tags"></i>
          <p>No se encontraron servicios</p>
          <a href="crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Registrar primer servicio</a>
        </div>
      <?php else: ?>
      <div class="tabla-wrapper">
        <table>
          <thead>
            <tr>
              <th>Servicio</th>
              <th>Categoría</th>
              <th>Precio base</th>
              <th>Duración estimada</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($servicios as $s):
              $horas = intdiv($s['duracion_estimada'], 60);
              $mins  = $s['duracion_estimada'] % 60;
              $dur   = $horas > 0 ? "{$horas}h {$mins}min" : "{$mins} min";
            ?>
            <tr>
              <td>
                <div class="prod-nombre"><?= htmlspecialchars($s['nombre']) ?></div>
                <?php if ($s['descripcion']): ?>
                <div class="prod-desc"><?= htmlspecialchars(substr($s['descripcion'], 0, 60)) ?>...</div>
                <?php endif; ?>
              </td>
              <td><span class="badge-categoria"><?= htmlspecialchars($s['categoria_nombre'] ?? '—') ?></span></td>
              <td class="td-precio">S/ <?= number_format($s['precio_base'], 2) ?></td>
              <td>
                <span style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--texto-muted)">
                  <i class="fas fa-clock" style="color:var(--azul-acento)"></i>
                  <?= $dur ?>
                </span>
              </td>
              <td>
                <div class="acciones">
                  <?php if ($rol !== 'mecanico'): ?>
                  <a href="editar.php?id=<?= $s['id'] ?>" class="btn-accion editar" title="Editar">
                    <i class="fas fa-pen"></i>
                  </a>
                  <?php endif; ?>
                  <?php if ($rol === 'administrador'): ?>
                  <a href="index.php?desactivar=<?= $s['id'] ?>"
                     class="btn-accion eliminar" title="Desactivar"
                     onclick="return confirm('¿Desactivar el servicio «<?= htmlspecialchars(addslashes($s['nombre'])) ?>»?')">
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