<?php
// ============================================================
//  modules/ordenes/ver.php — Detalle de Orden de Trabajo
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }

$rol    = $_SESSION['usuario_rol'];
$nombre = $_SESSION['usuario_nombre'];
$error  = '';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

// Cargar orden completa
$orden = dbQuery("
    SELECT ot.*,
           cl.nombre AS cliente_nombre, cl.dni_ruc, cl.telefono, cl.correo,
           v.placa, v.marca, v.modelo, v.anio, v.color, v.km_actual,
           CONCAT(u.nombre,' ',u.apellido) AS mecanico_nombre
    FROM ordenes_trabajo ot
    LEFT JOIN clientes cl ON ot.cliente_id  = cl.id
    LEFT JOIN vehiculos v  ON ot.vehiculo_id = v.id
    LEFT JOIN usuarios  u  ON ot.mecanico_id = u.id
    WHERE ot.id = ?", [$id]);

if (!$orden) { header('Location: index.php'); exit; }
$orden = $orden[0];

// Cargar items de la orden
$items = dbQuery("SELECT * FROM detalle_orden WHERE orden_id = ? ORDER BY id", [$id]) ?: [];

// ── Cambio de estado ──
if (isset($_POST['cambiar_estado'])) {
    $nuevo = $_POST['nuevo_estado'] ?? '';
    $permitidos = ['abierta','en_proceso','lista','cobrada','anulada'];
    if (in_array($nuevo, $permitidos)) {
        $cierre = ($nuevo === 'cobrada' || $nuevo === 'anulada') ? date('Y-m-d H:i:s') : null;
        dbQuery("UPDATE ordenes_trabajo SET estado=?, fecha_cierre=? WHERE id=?", [$nuevo, $cierre, $id]);
    }
    header('Location: ver.php?id='.$id); exit;
}

// ── Agregar ítem (producto o servicio) ──
if (isset($_POST['agregar_item'])) {
    $tipo    = $_POST['tipo_item']  ?? '';
    $ref_id  = (int)($_POST['ref_id'] ?? 0);
    $cant    = (float)str_replace(',','.',($_POST['cantidad'] ?? 1));
    $precio  = (float)str_replace(',','.',($_POST['precio']  ?? 0));

    if ($tipo && $ref_id && $cant > 0 && $precio > 0) {
        // Obtener nombre del ítem
        if ($tipo === 'producto') {
            $item_data = dbQuery("SELECT nombre FROM productos WHERE id=?", [$ref_id]);
            // Descontar stock
            dbQuery("UPDATE productos SET stock_actual = stock_actual - ? WHERE id=?", [$cant, $ref_id]);
            dbQuery("INSERT INTO movimientos_stock (producto_id,tipo,cantidad,usuario_id,orden_id,fuente,observacion)
                     VALUES (?,  'salida', ?, ?, ?, 'manual', ?)",
                    [$ref_id, $cant, $_SESSION['usuario_id'], $id, 'Usado en orden '.$orden['numero']]);
        } else {
            $item_data = dbQuery("SELECT nombre FROM servicios WHERE id=?", [$ref_id]);
        }
        $nombre_item = $item_data[0]['nombre'] ?? 'Item';
        $subtotal    = $cant * $precio;

        dbQuery("INSERT INTO detalle_orden (orden_id,tipo,referencia_id,nombre_item,cantidad,precio_unitario,subtotal)
                 VALUES (?,?,?,?,?,?,?)",
                [$id, $tipo, $ref_id, $nombre_item, $cant, $precio, $subtotal]);

        // Recalcular totales de la orden
        $tot = dbQuery("SELECT SUM(subtotal) AS s FROM detalle_orden WHERE orden_id=?", [$id])[0]['s'] ?? 0;
        $igv = round($tot * 0.18, 2);
        dbQuery("UPDATE ordenes_trabajo SET subtotal=?, igv=?, total=? WHERE id=?",
                [round($tot, 2), $igv, round($tot + $igv, 2), $id]);
    }
    header('Location: ver.php?id='.$id); exit;
}

// ── Eliminar ítem ──
if (isset($_GET['del_item'])) {
    $item_id = (int)$_GET['del_item'];
    $item = dbQuery("SELECT * FROM detalle_orden WHERE id=? AND orden_id=?", [$item_id, $id]);
    if ($item) {
        $item = $item[0];
        // Devolver stock si era producto
        if ($item['tipo'] === 'producto') {
            dbQuery("UPDATE productos SET stock_actual = stock_actual + ? WHERE id=?",
                    [$item['cantidad'], $item['referencia_id']]);
        }
        dbQuery("DELETE FROM detalle_orden WHERE id=?", [$item_id]);
        // Recalcular totales
        $tot = dbQuery("SELECT SUM(subtotal) AS s FROM detalle_orden WHERE orden_id=?", [$id])[0]['s'] ?? 0;
        $igv = round($tot * 0.18, 2);
        dbQuery("UPDATE ordenes_trabajo SET subtotal=?, igv=?, total=? WHERE id=?",
                [round($tot,2), $igv, round($tot+$igv,2), $id]);
    }
    header('Location: ver.php?id='.$id); exit;
}

// Recargar orden con totales actualizados
$orden = dbQuery("SELECT ot.*, cl.nombre AS cliente_nombre, cl.dni_ruc, cl.telefono, cl.correo,
           v.placa, v.marca, v.modelo, v.anio, v.color, v.km_actual,
           CONCAT(u.nombre,' ',u.apellido) AS mecanico_nombre
    FROM ordenes_trabajo ot
    LEFT JOIN clientes cl ON ot.cliente_id=cl.id
    LEFT JOIN vehiculos v  ON ot.vehiculo_id=v.id
    LEFT JOIN usuarios  u  ON ot.mecanico_id=u.id
    WHERE ot.id=?", [$id])[0];
$items = dbQuery("SELECT * FROM detalle_orden WHERE orden_id=? ORDER BY id", [$id]) ?: [];

// Productos y servicios para el formulario de agregar
$productos_disp = dbQuery("SELECT id, nombre, precio_con_igv AS precio FROM productos WHERE activo=1 AND stock_actual>0 ORDER BY nombre") ?: [];
$servicios_disp = dbQuery("SELECT id, nombre, precio_base AS precio FROM servicios WHERE activo=1 ORDER BY nombre") ?: [];

// Stepper de estados
$estados_orden = ['abierta','en_proceso','lista','cobrada'];
$estado_actual = $orden['estado'];
$idx_actual    = array_search($estado_actual, $estados_orden);

$msg = $_GET['msg'] ?? '';
?>

$PAGE_TITLE  = ''; // Set per order
$PAGE_ICON   = 'fa-clipboard-list';
$ACTIVE_MENU = 'ordenes';
$TOPBAR_ACTIONS = (isset($orden) && $orden['estado']==='lista')
    ? '<a href="../comprobantes/crear.php?orden_id='.$id.'" class="btn btn-naranja"><i class="fas fa-file-invoice"></i> Emitir Comprobante</a><a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>'
    : '<a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>';
// Override title after loading order
require_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/ordenes.css">

    <?php if ($msg === 'creada'): ?>
      <div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Orden creada. Ahora agrega productos y servicios.</div>
    <?php endif; ?>

    <div class="orden-layout">

      <!-- ══ COLUMNA IZQUIERDA ══ -->
      <div>
        <!-- Header de la orden -->
        <div class="orden-header" style="margin-bottom:20px">
          <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
              <div class="ord-numero" style="font-size:22px"><?= htmlspecialchars($orden['numero']) ?></div>
              <div style="font-size:13px;color:var(--texto-muted);margin-top:4px">
                Ingreso: <?= date('d/m/Y H:i', strtotime($orden['fecha_ingreso'])) ?>
                <?php if ($orden['fecha_estimada']): ?>
                  · Entrega estimada: <strong><?= date('d/m/Y', strtotime($orden['fecha_estimada'])) ?></strong>
                <?php endif; ?>
              </div>
            </div>
            <?php
            $badges = ['abierta'=>'badge-abierta','en_proceso'=>'badge-proceso','lista'=>'badge-lista','cobrada'=>'badge-cobrada','anulada'=>'badge-anulada'];
            $labels = ['abierta'=>'Abierta','en_proceso'=>'En Proceso','lista'=>'Lista','cobrada'=>'Cobrada','anulada'=>'Anulada'];
            ?>
            <span class="badge <?= $badges[$estado_actual] ?? '' ?>" style="font-size:13px;padding:6px 14px">
              <?= $labels[$estado_actual] ?? $estado_actual ?>
            </span>
          </div>

          <!-- Stepper -->
          <?php if ($estado_actual !== 'anulada'): ?>
          <div class="estado-stepper">
            <?php foreach ($estados_orden as $i => $est): ?>
              <?php if ($i > 0): ?>
                <div class="step-line <?= $i <= $idx_actual ? 'done' : '' ?>"></div>
              <?php endif; ?>
              <div class="step <?= $i < $idx_actual ? 'done' : ($i === $idx_actual ? 'active' : '') ?>">
                <div class="step-circle">
                  <?= $i < $idx_actual ? '<i class="fas fa-check"></i>' : ($i + 1) ?>
                </div>
                <div class="step-label"><?= ucfirst(str_replace('_',' ',$est)) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <div class="orden-meta">
            <div class="orden-meta-item"><i class="fas fa-user"></i><span><?= htmlspecialchars($orden['cliente_nombre']) ?></span></div>
            <div class="orden-meta-item"><i class="fas fa-car"></i><strong><?= htmlspecialchars($orden['placa']) ?></strong><span><?= htmlspecialchars($orden['marca'].' '.$orden['modelo']) ?></span></div>
            <div class="orden-meta-item"><i class="fas fa-gauge-high"></i><span><?= number_format($orden['km_ingreso']) ?> km</span></div>
            <?php if ($orden['mecanico_nombre']): ?>
            <div class="orden-meta-item"><i class="fas fa-screwdriver-wrench"></i><span><?= htmlspecialchars($orden['mecanico_nombre']) ?></span></div>
            <?php endif; ?>
          </div>

          <?php if ($orden['diagnostico']): ?>
          <div style="margin-top:14px;padding:12px;background:var(--fondo);border-radius:8px;font-size:13px;color:var(--texto)">
            <strong><i class="fas fa-stethoscope" style="color:var(--azul-acento)"></i> Diagnóstico:</strong>
            <?= nl2br(htmlspecialchars($orden['diagnostico'])) ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Items de la orden -->
        <div class="items-section">
          <div class="items-header">
            <h3><i class="fas fa-list-check"></i> Productos y Servicios</h3>
            <?php if (!in_array($estado_actual, ['cobrada','anulada'])): ?>
            <button class="btn btn-primary btn-sm" onclick="toggleForm()">
              <i class="fas fa-plus"></i> Agregar Item
            </button>
            <?php endif; ?>
          </div>

          <!-- Formulario agregar ítem -->
          <?php if (!in_array($estado_actual, ['cobrada','anulada'])): ?>
          <div class="add-item-form hidden" id="addItemForm">
            <form method="POST" action="ver.php?id=<?= $id ?>" style="display:contents">
              <div>
                <label style="font-size:11px;font-weight:600;color:var(--texto-muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;display:block">Tipo</label>
                <select name="tipo_item" id="tipo_item" class="form-control" onchange="cargarItems()">
                  <option value="servicio">Servicio</option>
                  <option value="producto">Producto</option>
                </select>
              </div>
              <div>
                <label style="font-size:11px;font-weight:600;color:var(--texto-muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;display:block">Item</label>
                <select name="ref_id" id="ref_id" class="form-control" onchange="setPrecio()">
                  <option value="">— Seleccionar —</option>
                  <?php foreach ($servicios_disp as $s): ?>
                  <option value="<?= $s['id'] ?>" data-precio="<?= $s['precio'] ?>"><?= htmlspecialchars($s['nombre']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label style="font-size:11px;font-weight:600;color:var(--texto-muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;display:block">Cantidad</label>
                <input type="number" name="cantidad" id="cantidad" class="form-control" step="0.01" min="0.01" value="1">
              </div>
              <div>
                <label style="font-size:11px;font-weight:600;color:var(--texto-muted);letter-spacing:1px;text-transform:uppercase;margin-bottom:6px;display:block">Precio unit. (S/)</label>
                <input type="number" name="precio" id="precio" class="form-control" step="0.01" min="0.01" placeholder="0.00">
              </div>
              <div>
                <label style="font-size:11px;color:transparent;display:block;margin-bottom:6px">.</label>
                <button type="submit" name="agregar_item" class="btn btn-primary"><i class="fas fa-plus"></i></button>
              </div>
            </form>
          </div>
          <?php endif; ?>

          <!-- Tabla de items -->
          <?php if (empty($items)): ?>
            <div class="empty"><i class="fas fa-list-check"></i><p>Aún no hay productos ni servicios en esta orden</p></div>
          <?php else: ?>
          <div class="tabla-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Tipo</th>
                  <th>Descripción</th>
                  <th>Cantidad</th>
                  <th>Precio unit.</th>
                  <th>Subtotal</th>
                  <?php if (!in_array($estado_actual, ['cobrada','anulada'])): ?><th></th><?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $it): ?>
                <tr>
                  <td>
                    <span class="badge <?= $it['tipo']==='producto' ? 'tipo-prod' : 'tipo-serv' ?>">
                      <i class="fas <?= $it['tipo']==='producto' ? 'fa-box' : 'fa-screwdriver-wrench' ?>"></i>
                      <?= ucfirst($it['tipo']) ?>
                    </span>
                  </td>
                  <td class="prod-nombre"><?= htmlspecialchars($it['nombre_item']) ?></td>
                  <td><?= number_format($it['cantidad'], 2) ?></td>
                  <td>S/ <?= number_format($it['precio_unitario'], 2) ?></td>
                  <td class="td-precio">S/ <?= number_format($it['subtotal'], 2) ?></td>
                  <?php if (!in_array($estado_actual, ['cobrada','anulada'])): ?>
                  <td>
                    <a href="ver.php?id=<?= $id ?>&del_item=<?= $it['id'] ?>"
                       class="btn-accion eliminar"
                       onclick="return confirm('¿Quitar este ítem?')" title="Quitar">
                      <i class="fas fa-times"></i>
                    </a>
                  </td>
                  <?php endif; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <!-- Totales -->
          <div class="orden-totales">
            <div class="total-row"><span>Subtotal (sin IGV)</span><span>S/ <?= number_format($orden['subtotal'], 2) ?></span></div>
            <div class="total-row"><span>IGV (18%)</span><span>S/ <?= number_format($orden['igv'], 2) ?></span></div>
            <div class="total-row total-final"><span>TOTAL</span><span>S/ <?= number_format($orden['total'], 2) ?></span></div>
          </div>
          <?php endif; ?>
        </div>
      </div><!-- /col izquierda -->

      <!-- ══ COLUMNA DERECHA ══ -->
      <div class="orden-sidebar-panel">

        <!-- Cambiar estado -->
        <?php if (!in_array($estado_actual, ['cobrada','anulada'])): ?>
        <div class="info-panel">
          <h4><i class="fas fa-gears"></i> Cambiar Estado</h4>
          <form method="POST" action="ver.php?id=<?= $id ?>">
            <?php if ($estado_actual === 'abierta'): ?>
              <button type="submit" name="cambiar_estado" class="btn-estado btn-en-proceso"
                      value="1" onclick="this.form.nuevo_estado.value='en_proceso'">
                <i class="fas fa-gears"></i> Iniciar Proceso
              </button>
            <?php elseif ($estado_actual === 'en_proceso'): ?>
              <button type="submit" name="cambiar_estado" class="btn-estado btn-lista"
                      value="1" onclick="this.form.nuevo_estado.value='lista'">
                <i class="fas fa-check-circle"></i> Marcar como Lista
              </button>
            <?php endif; ?>
            <button type="submit" name="cambiar_estado" class="btn-estado btn-anular"
                    value="1" onclick="return confirm('¿Anular esta orden?')?this.form.nuevo_estado.value='anulada':false">
              <i class="fas fa-ban"></i> Anular Orden
            </button>
            <input type="hidden" name="nuevo_estado" value="">
          </form>
        </div>
        <?php endif; ?>

        <!-- Info cliente -->
        <div class="info-panel">
          <h4><i class="fas fa-user"></i> Cliente</h4>
          <div class="info-row"><span>Nombre</span><span><?= htmlspecialchars($orden['cliente_nombre']) ?></span></div>
          <div class="info-row"><span>DNI/RUC</span><span><?= htmlspecialchars($orden['dni_ruc']) ?></span></div>
          <div class="info-row"><span>Teléfono</span><span><?= htmlspecialchars($orden['telefono'] ?? '—') ?></span></div>
          <div class="info-row"><span>Correo</span><span style="font-size:11px"><?= htmlspecialchars($orden['correo'] ?? '—') ?></span></div>
        </div>

        <!-- Info vehículo -->
        <div class="info-panel">
          <h4><i class="fas fa-car"></i> Vehículo</h4>
          <div class="info-row"><span>Placa</span><span class="veh-placa"><?= htmlspecialchars($orden['placa']) ?></span></div>
          <div class="info-row"><span>Vehículo</span><span><?= htmlspecialchars($orden['marca'].' '.$orden['modelo']) ?></span></div>
          <div class="info-row"><span>Año</span><span><?= $orden['anio'] ?></span></div>
          <div class="info-row"><span>Color</span><span><?= htmlspecialchars($orden['color'] ?? '—') ?></span></div>
          <div class="info-row"><span>KM ingreso</span><span><?= number_format($orden['km_ingreso']) ?> km</span></div>
        </div>
<?php require_once '../../includes/footer.php'; ?>

<!-- Datos para JS -->
<script>
const productos = <?= json_encode(array_map(fn($p)=>['id'=>$p['id'],'nombre'=>$p['nombre'],'precio'=>$p['precio']], $productos_disp)) ?>;
const servicios = <?= json_encode(array_map(fn($s)=>['id'=>$s['id'],'nombre'=>$s['nombre'],'precio'=>$s['precio']], $servicios_disp)) ?>;

function toggleForm() {
  const f = document.getElementById('addItemForm');
  f.classList.toggle('hidden');
}

function cargarItems() {
  const tipo   = document.getElementById('tipo_item').value;
  const select = document.getElementById('ref_id');
  const lista  = tipo === 'producto' ? productos : servicios;
  select.innerHTML = '<option value="">— Seleccionar —</option>';
  lista.forEach(item => {
    const opt = document.createElement('option');
    opt.value = item.id;
    opt.dataset.precio = item.precio;
    opt.textContent = item.nombre;
    select.appendChild(opt);
  });
  document.getElementById('precio').value = '';
}

function setPrecio() {
  const sel = document.getElementById('ref_id');
  const opt = sel.options[sel.selectedIndex];
  if (opt && opt.dataset.precio)
    document.getElementById('precio').value = parseFloat(opt.dataset.precio).toFixed(2);
}
</script>
<script src="../../assets/js/main.js"></script>
<script>
document.querySelectorAll('.alert-auto').forEach(el => {
  setTimeout(() => { el.style.opacity='0'; el.style.transition='opacity .5s';
    setTimeout(()=>el.remove(),500); }, 4000);
});
</script>
</body>
</html>