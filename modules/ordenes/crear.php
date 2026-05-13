<?php
// ============================================================
//  modules/ordenes/crear.php — Nueva Orden de Trabajo
// ============================================================
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$error  = '';

$clientes  = dbQuery("SELECT id, nombre, dni_ruc FROM clientes ORDER BY nombre") ?: [];
$mecanicos = dbQuery("SELECT id, nombre, apellido FROM usuarios WHERE rol='mecanico' AND activo=1 ORDER BY nombre") ?: [];

// Vehículos del cliente seleccionado (para el select dinámico)
$cliente_sel = (int)($_POST['cliente_id'] ?? $_GET['cliente_id'] ?? 0);
$vehiculos = $cliente_sel
    ? (dbQuery("SELECT * FROM vehiculos WHERE cliente_id = ? ORDER BY placa", [$cliente_sel]) ?: [])
    : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $cliente_id  = (int)($_POST['cliente_id']  ?? 0);
    $vehiculo_id = (int)($_POST['vehiculo_id'] ?? 0);
    $mecanico_id = (int)($_POST['mecanico_id'] ?? 0) ?: null;
    $km_ingreso  = (int)($_POST['km_ingreso']  ?? 0);
    $fecha_est   = $_POST['fecha_estimada'] ?? '';
    $diagnostico = trim($_POST['diagnostico'] ?? '');
    $obs         = trim($_POST['observaciones'] ?? '');

    if (!$cliente_id)  $error = 'Selecciona un cliente.';
    elseif (!$vehiculo_id) $error = 'Selecciona un vehículo.';
    else {
        // Generar número de orden: OT-YYYY-0001
        $anio  = date('Y');
        $count = dbQuery("SELECT COUNT(*) AS n FROM ordenes_trabajo WHERE YEAR(fecha_ingreso)=?", [$anio])[0]['n'] ?? 0;
        $numero = 'OT-'.$anio.'-'.str_pad($count + 1, 4, '0', STR_PAD_LEFT);

        dbQuery(
            "INSERT INTO ordenes_trabajo
             (numero, cliente_id, vehiculo_id, mecanico_id, fecha_estimada,
              diagnostico, observaciones, km_ingreso, estado)
             VALUES (?,?,?,?,?,?,?,?,'abierta')",
            [$numero, $cliente_id, $vehiculo_id, $mecanico_id,
             $fecha_est ?: null, $diagnostico, $obs, $km_ingreso]
        );

        // Actualizar km del vehículo
        if ($km_ingreso > 0) {
            dbQuery("UPDATE vehiculos SET km_actual=? WHERE id=?", [$km_ingreso, $vehiculo_id]);
        }

        $nueva_id = dbLastId();
        header('Location: ver.php?id='.$nueva_id.'&msg=creada'); exit;
    }
}
?>

$PAGE_TITLE  = 'Nueva Orden de Trabajo';
$PAGE_ICON   = 'fa-plus';
$ACTIVE_MENU = 'ordenes';
$TOPBAR_ACTIONS = '<a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>';
require_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/ordenes.css">

    <?php if ($error): ?>
      <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="inv-form-card card" style="max-width:800px">
      <div class="card-header">
        <h3><i class="fas fa-clipboard-list"></i> Datos de la Orden</h3>
      </div>
      <div class="card-body">
        <form method="POST" action="crear.php" id="formOrden">

          <p class="form-seccion">Cliente y Vehículo</p>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-group label">Cliente *</label>
              <select name="cliente_id" id="cliente_id" class="form-control" required onchange="cargarVehiculos()">
                <option value="">— Seleccionar cliente —</option>
                <?php foreach ($clientes as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $cliente_sel == $c['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['nombre']) ?> — <?= htmlspecialchars($c['dni_ruc']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-group label">Vehículo *</label>
              <select name="vehiculo_id" id="vehiculo_id" class="form-control" required>
                <option value="">— Seleccionar vehículo —</option>
                <?php foreach ($vehiculos as $v): ?>
                <option value="<?= $v['id'] ?>" <?= ($_POST['vehiculo_id'] ?? '') == $v['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($v['placa'].' — '.$v['marca'].' '.$v['modelo']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <p class="form-seccion">Mecánico y Fechas</p>
          <div class="form-grid form-grid-3">
            <div class="form-group">
              <label class="form-group label">Mecánico asignado</label>
              <select name="mecanico_id" class="form-control">
                <option value="">— Sin asignar —</option>
                <?php foreach ($mecanicos as $m): ?>
                <option value="<?= $m['id'] ?>" <?= ($_POST['mecanico_id'] ?? '') == $m['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($m['nombre'].' '.$m['apellido']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-group label">Fecha estimada entrega</label>
              <input type="date" name="fecha_estimada" class="form-control"
                     min="<?= date('Y-m-d') ?>"
                     value="<?= htmlspecialchars($_POST['fecha_estimada'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-group label">Kilometraje ingreso</label>
              <input type="number" name="km_ingreso" class="form-control"
                     min="0" placeholder="0 km"
                     value="<?= htmlspecialchars($_POST['km_ingreso'] ?? '') ?>">
            </div>
          </div>

          <p class="form-seccion">Diagnóstico</p>
          <div class="form-group">
            <label class="form-group label">Diagnóstico inicial</label>
            <textarea name="diagnostico" class="form-control" rows="3"
                      placeholder="Describe el problema o servicio requerido..."><?= htmlspecialchars($_POST['diagnostico'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-group label">Observaciones adicionales</label>
            <textarea name="observaciones" class="form-control" rows="2"
                      placeholder="Condición del vehículo, accesorios, etc..."><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
          </div>

          <div style="display:flex;gap:12px;margin-top:8px">
            <button type="submit" name="guardar" class="btn btn-primary">
              <i class="fas fa-save"></i> Crear Orden y Agregar Items
            </button>
            <a href="index.php" class="btn btn-outline">Cancelar</a>
          </div>
        </form>
      </div>
    </div>

<?php require_once '../../includes/footer.php'; ?>
<script>
// Carga dinámica de vehículos al cambiar cliente
function cargarVehiculos() {
  const clienteId = document.getElementById('cliente_id').value;
  const selVeh    = document.getElementById('vehiculo_id');
  if (!clienteId) { selVeh.innerHTML = '<option value="">— Seleccionar vehículo —</option>'; return; }
  // Recarga la página con el cliente seleccionado para cargar sus vehículos
  const form = document.getElementById('formOrden');
  const input = document.createElement('input');
  input.type = 'hidden'; input.name = 'cliente_id'; input.value = clienteId;
  window.location.href = 'crear.php?cliente_id=' + clienteId;
}
</script>
<script src="../../assets/js/main.js"></script>
</body>
</html>