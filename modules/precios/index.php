<?php
// ============================================================
//  modules/precios/index.php — Precios y Servicios
// ============================================================
require_once '../../includes/auth.php';
require_once '../../config/database.php';


// ── Desactivar servicio ──
if (isset($_GET['desactivar']) && esRol('administrador')) {
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

$PAGE_TITLE  = 'Precios y Servicios';
$PAGE_ICON   = 'fa-tags';
$ACTIVE_MENU = 'precios';
$TOPBAR_ACTIONS = '<a href="crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo Servicio</a>';
require_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/precios.css">

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
                  <?php if (!esRol('mecanico')): ?>
                  <a href="editar.php?id=<?= $s['id'] ?>" class="btn-accion editar" title="Editar">
                    <i class="fas fa-pen"></i>
                  </a>
                  <?php endif; ?>
                  <?php if (esRol('administrador')): ?>
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

<?php require_once '../../includes/footer.php'; ?>
<script src="../../assets/js/main.js"></script>
<script>
document.querySelectorAll('.alert-auto').forEach(el => {
  setTimeout(() => { el.style.opacity='0'; el.style.transition='opacity .5s';
    setTimeout(()=>el.remove(),500); }, 4000);
});
</script>
</body>
</html>