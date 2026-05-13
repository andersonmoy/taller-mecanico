<?php
// ============================================================
//  modules/inventario/editar.php — Editar Producto
// ============================================================
require_once '../../includes/auth.php';
require_once '../../config/database.php';

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

$PAGE_TITLE  = 'Editar Producto';
$PAGE_ICON   = 'fa-pen';
$ACTIVE_MENU = 'inventario';
$TOPBAR_ACTIONS = '<a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>';
require_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/inventario.css">

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

<?php require_once '../../includes/footer.php'; ?>
<script>
function calcSinIgv() {
  const con = parseFloat(document.getElementById('precio_con_igv').value) || 0;
  document.getElementById('precio_sin_display').value = con > 0 ? 'S/ ' + (con/1.18).toFixed(2) : '';
}
</script>
<script src="../../assets/js/main.js"></script>
</body>
</html>