<?php
// ============================================================
//  modules/inventario/crear.php — Nuevo Producto
// ============================================================
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$error  = '';

// ── Categorías para el select ──
$categorias = dbQuery("SELECT * FROM categorias WHERE tipo = 'producto' ORDER BY nombre") ?: [];

// ── Procesar formulario ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom        = trim($_POST['nombre']        ?? '');
    $desc       = trim($_POST['descripcion']   ?? '');
    $cat_id     = (int)($_POST['categoria_id'] ?? 0);
    $precio_igv = (float)str_replace(',', '.', $_POST['precio_con_igv'] ?? 0);
    $precio_sin = round($precio_igv / 1.18, 2);
    $unidad     = trim($_POST['unidad_medida'] ?? 'unidad');
    $stock_act  = (float)str_replace(',', '.', $_POST['stock_actual']  ?? 0);
    $stock_min  = (float)str_replace(',', '.', $_POST['stock_minimo']  ?? 5);

    if (!$nom)           $error = 'El nombre del producto es obligatorio.';
    elseif (!$cat_id)    $error = 'Selecciona una categoría.';
    elseif ($precio_igv <= 0) $error = 'El precio debe ser mayor a 0.';
    else {
        dbQuery(
            "INSERT INTO productos
             (nombre, descripcion, categoria_id, precio_sin_igv, precio_con_igv,
              unidad_medida, stock_actual, stock_minimo, activo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [$nom, $desc, $cat_id, $precio_sin, $precio_igv,
             $unidad, $stock_act, $stock_min]
        );

        // Registrar movimiento inicial si hay stock
        if ($stock_act > 0) {
            $nuevo_id = dbLastId();
            dbQuery(
                "INSERT INTO movimientos_stock
                 (producto_id, tipo, cantidad, usuario_id, fuente, observacion)
                 VALUES (?, 'entrada', ?, ?, 'manual', 'Stock inicial al crear producto')",
                [$nuevo_id, $stock_act, $_SESSION['usuario_id']]
            );
        }

        header('Location: index.php?msg=creado'); exit;
    }
}
?>

$PAGE_TITLE  = 'Nuevo Producto';
$PAGE_ICON   = 'fa-plus';
$ACTIVE_MENU = 'inventario';
$TOPBAR_ACTIONS = '<a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>';
require_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/inventario.css">

    <?php if ($error): ?>
      <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="inv-form-card card">
      <div class="card-header">
        <h3><i class="fas fa-box"></i> Datos del Producto</h3>
      </div>
      <div class="card-body">
        <form method="POST" action="crear.php">

          <p class="form-seccion">Información general</p>
          <div class="form-grid">
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-group label">Nombre del producto *</label>
              <input type="text" name="nombre" class="form-control"
                     placeholder="Ej: Aceite Motor 10W-40 (1L)"
                     value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
              <label class="form-group label">Categoría *</label>
              <select name="categoria_id" class="form-control" required>
                <option value="">— Seleccionar —</option>
                <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($_POST['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['nombre']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-group label">Unidad de medida</label>
              <select name="unidad_medida" class="form-control">
                <?php foreach (['unidad','litro','frasco','juego','par','kg','caja'] as $u): ?>
                <option value="<?= $u ?>" <?= ($_POST['unidad_medida'] ?? 'unidad') === $u ? 'selected' : '' ?>>
                  <?= ucfirst($u) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-group label">Descripción</label>
              <textarea name="descripcion" class="form-control" rows="2"
                        placeholder="Descripción breve del producto..."><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
            </div>
          </div>

          <p class="form-seccion">Precios</p>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-group label">Precio con IGV (S/) *</label>
              <input type="number" name="precio_con_igv" id="precio_con_igv"
                     class="form-control" step="0.01" min="0.01"
                     placeholder="0.00"
                     value="<?= htmlspecialchars($_POST['precio_con_igv'] ?? '') ?>"
                     oninput="calcSinIgv()" required>
            </div>
            <div class="form-group">
              <label class="form-group label">Precio sin IGV (S/) — calculado</label>
              <input type="text" id="precio_sin_igv_display"
                     class="form-control" readonly
                     style="background:#f8f9fa;color:var(--texto-muted)"
                     placeholder="0.00">
            </div>
          </div>

          <p class="form-seccion">Control de stock</p>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-group label">Stock inicial</label>
              <input type="number" name="stock_actual" class="form-control"
                     step="0.001" min="0" placeholder="0"
                     value="<?= htmlspecialchars($_POST['stock_actual'] ?? '0') ?>">
            </div>
            <div class="form-group">
              <label class="form-group label">Stock mínimo (alerta)</label>
              <input type="number" name="stock_minimo" class="form-control"
                     step="0.001" min="0" placeholder="5"
                     value="<?= htmlspecialchars($_POST['stock_minimo'] ?? '5') ?>">
              <small style="color:var(--texto-muted);font-size:11px">
                El sistema alertará cuando el stock baje de este valor.
              </small>
            </div>
          </div>

          <div style="display:flex;gap:12px;margin-top:8px">
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> Guardar Producto
            </button>
            <a href="index.php" class="btn btn-outline">Cancelar</a>
          </div>

        </form>
      </div>
    </div>

<?php require_once '../../includes/footer.php'; ?>
<script>
function calcSinIgv() {
  const con = parseFloat(document.getElementById('precio_con_igv').value) || 0;
  const sin = con > 0 ? (con / 1.18).toFixed(2) : '';
  document.getElementById('precio_sin_igv_display').value = sin ? 'S/ ' + sin : '';
}
// Calcular al cargar si hay valor previo (error de validación)
calcSinIgv();
</script>
<script src="../../assets/js/main.js"></script>
</body>
</html>