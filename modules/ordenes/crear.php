<?php
// ============================================================
//  modules/ordenes/crear.php — Nueva Orden de Trabajo
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }

$rol    = $_SESSION['usuario_rol'];
$nombre = $_SESSION['usuario_nombre'];
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
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nueva Orden — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/ordenes.css">
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
    <a href="index.php"                class="menu-item active"><i class="fas fa-clipboard-list"></i> Órdenes de Trabajo</a>
    <a href="../clientes/index.php"    class="menu-item"><i class="fas fa-users"></i> Clientes y Vehículos</a>
    <a href="../comprobantes/index.php"class="menu-item"><i class="fas fa-file-invoice"></i> Boletas y Facturas</a>
    <div class="menu-section">Almacén</div>
    <a href="../inventario/index.php"  class="menu-item"><i class="fas fa-boxes-stacked"></i> Inventario</a>
    <a href="../precios/index.php"     class="menu-item"><i class="fas fa-tags"></i> Precios y Servicios</a>
    <?php if ($rol === 'administrador'): ?>
    <div class="menu-section">Administración</div>
    <a href="../reportes/index.php"    class="menu-item"><i class="fas fa-chart-bar"></i> Reportes</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($nombre, 0, 1)) ?></div>
    <div class="user-info"><strong><?= htmlspecialchars($nombre) ?></strong><span><?= $rol ?></span></div>
    <a href="../../logout.php" class="btn-logout"><i class="fas fa-right-from-bracket"></i></a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <h1><i class="fas fa-plus"></i> Nueva Orden de Trabajo</h1>
    <div class="topbar-right">
      <a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
  </header>

  <div class="content">
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
  </div>
</div>

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