<?php
// ============================================================
//  modules/inventario/editar.php — Editar Producto
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }
if ($_SESSION['usuario_rol'] === 'mecanico') { header('Location: index.php'); exit; }

$rol    = $_SESSION['usuario_rol'];
$nombre = $_SESSION['usuario_nombre'];
$error  = '';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

// Cargar producto
$prod = dbQuery("SELECT * FROM productos WHERE id = ? AND activo = 1", [$id]);
if (!$prod) { header('Location: index.php'); exit; }
$prod = $prod[0];

$categorias = dbQuery("SELECT * FROM categorias WHERE tipo = 'producto' ORDER BY nombre") ?: [];

// ── Procesar formulario ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom        = trim($_POST['nombre']        ?? '');
    $desc       = trim($_POST['descripcion']   ?? '');
    $cat_id     = (int)($_POST['categoria_id'] ?? 0);
    $precio_igv = (float)str_replace(',', '.', $_POST['precio_con_igv'] ?? 0);
    $precio_sin = round($precio_igv / 1.18, 2);
    $unidad     = trim($_POST['unidad_medida'] ?? 'unidad');
    $stock_min  = (float)str_replace(',', '.', $_POST['stock_minimo'] ?? 5);

    if (!$nom)            $error = 'El nombre es obligatorio.';
    elseif (!$cat_id)     $error = 'Selecciona una categoría.';
    elseif ($precio_igv <= 0) $error = 'El precio debe ser mayor a 0.';
    else {
        dbQuery(
            "UPDATE productos SET
               nombre = ?, descripcion = ?, categoria_id = ?,
               precio_sin_igv = ?, precio_con_igv = ?,
               unidad_medida = ?, stock_minimo = ?
             WHERE id = ?",
            [$nom, $desc, $cat_id, $precio_sin, $precio_igv, $unidad, $stock_min, $id]
        );
        header('Location: index.php?msg=editado'); exit;
    }
}

// Usar valores POST si hubo error, sino los del producto
$v = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $prod;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Producto — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/inventario.css">
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
    <div class="user-info">
      <strong><?= htmlspecialchars($nombre) ?></strong>
      <span><?= $rol ?></span>
    </div>
    <a href="../../logout.php" class="btn-logout"><i class="fas fa-right-from-bracket"></i></a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <h1><i class="fas fa-pen"></i> Editar Producto</h1>
    <div class="topbar-right">
      <a href="ajuste.php?id=<?= $id ?>" class="btn btn-outline"><i class="fas fa-sliders"></i> Ajustar Stock</a>
      <a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
  </header>

  <div class="content">
    <?php if ($error): ?>
      <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Info actual del stock (solo lectura) -->
    <div style="display:flex;gap:12px;margin-bottom:20px">
      <div class="inv-card" style="flex:1">
        <div class="inv-card-icon bg-azul"><i class="fas fa-cubes"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num"><?= number_format($prod['stock_actual'], 2) ?> <?= $prod['unidad_medida'] ?></span>
          <span class="inv-card-label">Stock actual</span>
        </div>
      </div>
      <div class="inv-card" style="flex:1">
        <div class="inv-card-icon bg-naranja" style="background:linear-gradient(135deg,var(--naranja),#c0620a)"><i class="fas fa-tag"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num">S/ <?= number_format($prod['precio_con_igv'], 2) ?></span>
          <span class="inv-card-label">Precio actual c/IGV</span>
        </div>
      </div>
      <div class="inv-card" style="flex:1">
        <div class="inv-card-icon bg-verde"><i class="fas fa-dollar-sign"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num">S/ <?= number_format($prod['precio_con_igv'] * $prod['stock_actual'], 2) ?></span>
          <span class="inv-card-label">Valor en almacén</span>
        </div>
      </div>
    </div>

    <div class="inv-form-card card">
      <div class="card-header">
        <h3><i class="fas fa-box"></i> <?= htmlspecialchars($prod['nombre']) ?></h3>
      </div>
      <div class="card-body">
        <form method="POST" action="editar.php?id=<?= $id ?>">

          <p class="form-seccion">Información general</p>
          <div class="form-grid">
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-group label">Nombre del producto *</label>
              <input type="text" name="nombre" class="form-control"
                     value="<?= htmlspecialchars($v['nombre']) ?>" required autofocus>
            </div>
            <div class="form-group">
              <label class="form-group label">Categoría *</label>
              <select name="categoria_id" class="form-control" required>
                <option value="">— Seleccionar —</option>
                <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $v['categoria_id'] == $cat['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['nombre']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-group label">Unidad de medida</label>
              <select name="unidad_medida" class="form-control">
                <?php foreach (['unidad','litro','frasco','juego','par','kg','caja'] as $u): ?>
                <option value="<?= $u ?>" <?= $v['unidad_medida'] === $u ? 'selected' : '' ?>><?= ucfirst($u) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-group label">Descripción</label>
              <textarea name="descripcion" class="form-control" rows="2"><?= htmlspecialchars($v['descripcion'] ?? '') ?></textarea>
            </div>
          </div>

          <p class="form-seccion">Precios</p>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-group label">Precio con IGV (S/) *</label>
              <input type="number" name="precio_con_igv" id="precio_con_igv"
                     class="form-control" step="0.01" min="0.01"
                     value="<?= htmlspecialchars($v['precio_con_igv']) ?>"
                     oninput="calcSinIgv()" required>
            </div>
            <div class="form-group">
              <label class="form-group label">Precio sin IGV — calculado</label>
              <input type="text" id="precio_sin_display" class="form-control" readonly
                     style="background:#f8f9fa;color:var(--texto-muted)"
                     value="S/ <?= number_format($v['precio_sin_igv'] ?? ($v['precio_con_igv']/1.18), 2) ?>">
            </div>
          </div>

          <p class="form-seccion">Stock mínimo</p>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-group label">Stock mínimo (alerta)</label>
              <input type="number" name="stock_minimo" class="form-control"
                     step="0.001" min="0"
                     value="<?= htmlspecialchars($v['stock_minimo']) ?>">
              <small style="color:var(--texto-muted);font-size:11px">
                Para cambiar el stock actual usa <a href="ajuste.php?id=<?= $id ?>">Ajustar Stock</a>.
              </small>
            </div>
          </div>

          <div style="display:flex;gap:12px;margin-top:8px">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
            <a href="index.php" class="btn btn-outline">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function calcSinIgv() {
  const con = parseFloat(document.getElementById('precio_con_igv').value) || 0;
  document.getElementById('precio_sin_display').value = con > 0 ? 'S/ ' + (con/1.18).toFixed(2) : '';
}
</script>
<script src="../../assets/js/main.js"></script>
</body>
</html>