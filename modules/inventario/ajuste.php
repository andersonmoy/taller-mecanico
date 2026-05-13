<?php
// ============================================================
//  modules/inventario/ajuste.php — Ajuste de Stock
// ============================================================
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$error  = '';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$prod = dbQuery("SELECT p.*, c.nombre AS cat FROM productos p LEFT JOIN categorias c ON p.categoria_id=c.id WHERE p.id=? AND p.activo=1", [$id]);
if (!$prod) { header('Location: index.php'); exit; }
$prod = $prod[0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo       = $_POST['tipo']       ?? '';
    $cantidad   = (float)str_replace(',', '.', $_POST['cantidad'] ?? 0);
    $observacion= trim($_POST['observacion'] ?? '');

    if (!in_array($tipo, ['entrada','salida'])) $error = 'Tipo de movimiento inválido.';
    elseif ($cantidad <= 0)  $error = 'La cantidad debe ser mayor a 0.';
    elseif ($tipo === 'salida' && $cantidad > $prod['stock_actual'])
        $error = 'No hay suficiente stock. Stock actual: ' . $prod['stock_actual'] . ' ' . $prod['unidad_medida'];
    else {
        // Actualizar stock
        $nuevo_stock = $tipo === 'entrada'
            ? $prod['stock_actual'] + $cantidad
            : $prod['stock_actual'] - $cantidad;

        dbQuery("UPDATE productos SET stock_actual = ? WHERE id = ?", [$nuevo_stock, $id]);

        // Registrar movimiento
        dbQuery(
            "INSERT INTO movimientos_stock (producto_id, tipo, cantidad, usuario_id, fuente, observacion)
             VALUES (?, ?, ?, ?, 'manual', ?)",
            [$id, $tipo, $cantidad, $_SESSION['usuario_id'], $observacion ?: null]
        );

        header('Location: index.php?msg=ajuste'); exit;
    }
}
?>

$PAGE_TITLE  = 'Ajuste de Stock';
$PAGE_ICON   = 'fa-sliders';
$ACTIVE_MENU = 'inventario';
$TOPBAR_ACTIONS = '<a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>';
require_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/inventario.css">

    <?php if ($error): ?>
      <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Info del producto -->
    <div style="display:flex;gap:12px;margin-bottom:24px">
      <div class="inv-card" style="flex:2">
        <div class="inv-card-icon bg-azul"><i class="fas fa-box"></i></div>
        <div class="inv-card-info">
          <span class="inv-card-num"><?= htmlspecialchars($prod['nombre']) ?></span>
          <span class="inv-card-label"><?= htmlspecialchars($prod['cat'] ?? '') ?></span>
        </div>
      </div>
      <div class="inv-card" style="flex:1">
        <div class="inv-card-icon <?= $prod['stock_actual'] <= $prod['stock_minimo'] ? 'bg-amarillo' : 'bg-verde' ?>">
          <i class="fas fa-cubes"></i>
        </div>
        <div class="inv-card-info">
          <span class="inv-card-num"><?= number_format($prod['stock_actual'], 2) ?> <small style="font-size:14px"><?= $prod['unidad_medida'] ?></small></span>
          <span class="inv-card-label">Stock actual · mínimo: <?= $prod['stock_minimo'] ?></span>
        </div>
      </div>
    </div>

    <div class="inv-form-card card" style="max-width:500px">
      <div class="card-header">
        <h3><i class="fas fa-sliders"></i> Registrar Movimiento</h3>
      </div>
      <div class="card-body">
        <form method="POST" action="ajuste.php?id=<?= $id ?>">

          <!-- Tipo de ajuste -->
          <div class="ajuste-tipo">
            <label class="tipo-btn entrada">
              <input type="radio" name="tipo" value="entrada"
                     <?= ($_POST['tipo'] ?? 'entrada') === 'entrada' ? 'checked' : '' ?>>
              <i class="fas fa-arrow-down"></i>
              <span>Entrada</span>
            </label>
            <label class="tipo-btn salida">
              <input type="radio" name="tipo" value="salida"
                     <?= ($_POST['tipo'] ?? '') === 'salida' ? 'checked' : '' ?>>
              <i class="fas fa-arrow-up"></i>
              <span>Salida</span>
            </label>
          </div>

          <div class="form-group">
            <label class="form-group label">Cantidad *</label>
            <input type="number" name="cantidad" class="form-control"
                   step="0.001" min="0.001" placeholder="0.00"
                   value="<?= htmlspecialchars($_POST['cantidad'] ?? '') ?>"
                   autofocus required>
          </div>

          <div class="form-group">
            <label class="form-group label">Observación (opcional)</label>
            <input type="text" name="observacion" class="form-control"
                   placeholder="Ej: Compra a proveedor, ajuste por inventario..."
                   value="<?= htmlspecialchars($_POST['observacion'] ?? '') ?>">
          </div>

          <div style="display:flex;gap:12px;margin-top:8px">
            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Registrar Ajuste</button>
            <a href="index.php" class="btn btn-outline">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="../../assets/js/main.js"></script>
</body>
</html>