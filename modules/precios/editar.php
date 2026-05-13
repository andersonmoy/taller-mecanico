<?php
// ============================================================
//  modules/precios/editar.php — Editar Servicio
// ============================================================
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$error  = '';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$serv = dbQuery("SELECT * FROM servicios WHERE id = ? AND activo = 1", [$id]);
if (!$serv) { header('Location: index.php'); exit; }
$serv = $serv[0];

$categorias = dbQuery("SELECT * FROM categorias WHERE tipo = 'servicio' ORDER BY nombre") ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom     = trim($_POST['nombre']      ?? '');
    $desc    = trim($_POST['descripcion'] ?? '');
    $cat_id  = (int)($_POST['categoria_id'] ?? 0);
    $precio  = (float)str_replace(',', '.', $_POST['precio_base'] ?? 0);
    $horas   = (int)($_POST['horas']   ?? 0);
    $minutos = (int)($_POST['minutos'] ?? 0);
    $duracion = ($horas * 60) + $minutos;

    if (!$nom)          $error = 'El nombre es obligatorio.';
    elseif (!$cat_id)   $error = 'Selecciona una categoría.';
    elseif ($precio <= 0) $error = 'El precio debe ser mayor a 0.';
    elseif ($duracion <= 0) $error = 'La duración debe ser mayor a 0 minutos.';
    else {
        dbQuery(
            "UPDATE servicios SET nombre=?, descripcion=?, categoria_id=?, precio_base=?, duracion_estimada=? WHERE id=?",
            [$nom, $desc, $cat_id, $precio, $duracion, $id]
        );
        header('Location: index.php?msg=editado'); exit;
    }
}

$horas_act  = intdiv($serv['duracion_estimada'], 60);
$mins_act   = $serv['duracion_estimada'] % 60;
$v = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : array_merge($serv, ['horas'=>$horas_act,'minutos'=>$mins_act]);
?>

$PAGE_TITLE  = 'Editar Servicio';
$PAGE_ICON   = 'fa-pen';
$ACTIVE_MENU = 'precios';
$TOPBAR_ACTIONS = '<a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>';
require_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/precios.css">

    <?php if ($error): ?>
      <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="inv-form-card card">
      <div class="card-header">
        <h3><i class="fas fa-screwdriver-wrench"></i> <?= htmlspecialchars($serv['nombre']) ?></h3>
      </div>
      <div class="card-body">
        <form method="POST" action="editar.php?id=<?= $id ?>">

          <p class="form-seccion">Información general</p>
          <div class="form-grid">
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-group label">Nombre del servicio *</label>
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
              <label class="form-group label">Precio base (S/) *</label>
              <input type="number" name="precio_base" class="form-control"
                     step="0.01" min="0.01"
                     value="<?= htmlspecialchars($v['precio_base']) ?>" required>
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label class="form-group label">Descripción</label>
              <textarea name="descripcion" class="form-control" rows="2"><?= htmlspecialchars($v['descripcion'] ?? '') ?></textarea>
            </div>
          </div>

          <p class="form-seccion">Duración estimada</p>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-group label">Horas</label>
              <input type="number" name="horas" class="form-control"
                     min="0" max="24" value="<?= $v['horas'] ?>">
            </div>
            <div class="form-group">
              <label class="form-group label">Minutos</label>
              <select name="minutos" class="form-control">
                <?php foreach ([0,15,30,45] as $m): ?>
                <option value="<?= $m ?>" <?= $v['minutos'] == $m ? 'selected' : '' ?>><?= $m ?> min</option>
                <?php endforeach; ?>
              </select>
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
<script src="../../assets/js/main.js"></script>
</body>
</html>