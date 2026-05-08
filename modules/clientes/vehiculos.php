<?php
// ============================================================
//  modules/clientes/vehiculos.php — Vehículos del Cliente
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }

$rol       = $_SESSION['usuario_rol'];
$nombre_us = $_SESSION['usuario_nombre'];
$error     = '';
$msg       = $_GET['msg'] ?? '';

$cliente_id = (int)($_GET['cliente_id'] ?? 0);
if (!$cliente_id) { header('Location: index.php'); exit; }

// Cargar cliente
$clientes = dbQuery("SELECT * FROM clientes WHERE id = ?", [$cliente_id]);
if (!$clientes) { header('Location: index.php'); exit; }
$cliente = $clientes[0];

// Eliminar vehículo
if (isset($_GET['eliminar_v'])) {
    dbQuery("DELETE FROM vehiculos WHERE id = ? AND cliente_id = ?", [(int)$_GET['eliminar_v'], $cliente_id]);
    header("Location: vehiculos.php?cliente_id=$cliente_id&msg=v_eliminado");
    exit;
}

// Registrar vehículo nuevo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $placa  = strtoupper(trim($_POST['placa']  ?? ''));
    $marca  = trim($_POST['marca']  ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $anio   = (int)($_POST['anio']  ?? date('Y'));
    $color  = trim($_POST['color']  ?? '');
    $km     = (int)($_POST['km_actual'] ?? 0);
    $obs    = trim($_POST['observaciones'] ?? '');

    if (empty($placa) || empty($marca) || empty($modelo)) {
        $error = 'Placa, marca y modelo son obligatorios.';
    } else {
        $existe = dbQuery("SELECT id FROM vehiculos WHERE placa = ?", [$placa]);
        if ($existe) {
            $error = "La placa $placa ya está registrada.";
        } else {
            dbQuery(
                "INSERT INTO vehiculos (cliente_id, placa, marca, modelo, anio, color, km_actual, observaciones) VALUES (?,?,?,?,?,?,?,?)",
                [$cliente_id, $placa, $marca, $modelo, $anio, $color, $km, $obs]
            );
            header("Location: vehiculos.php?cliente_id=$cliente_id&msg=v_creado");
            exit;
        }
    }
}

// Cargar vehículos del cliente
$vehiculos = dbQuery("SELECT * FROM vehiculos WHERE cliente_id = ? ORDER BY created_at DESC", [$cliente_id]) ?: [];

// Estadísticas del cliente
$total_ordenes = dbQuery("SELECT COUNT(*) as t FROM ordenes_trabajo WHERE cliente_id = ?", [$cliente_id])[0]['t'] ?? 0;
$total_gastado = dbQuery("SELECT COALESCE(SUM(total),0) as t FROM comprobantes WHERE cliente_id = ? AND estado='emitida'", [$cliente_id])[0]['t'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vehículos — <?= htmlspecialchars($cliente['nombre']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/clientes.css">
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
    <a href="../ordenes/index.php" class="menu-item"><i class="fas fa-clipboard-list"></i> Órdenes de Trabajo</a>
    <a href="index.php" class="menu-item active"><i class="fas fa-users"></i> Clientes y Vehículos</a>
    <a href="../comprobantes/index.php" class="menu-item"><i class="fas fa-file-invoice"></i> Boletas y Facturas</a>
    <div class="menu-section">Almacén</div>
    <a href="../inventario/index.php" class="menu-item"><i class="fas fa-boxes-stacked"></i> Inventario</a>
    <a href="../precios/index.php" class="menu-item"><i class="fas fa-tags"></i> Precios y Servicios</a>
  </nav>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($nombre_us, 0, 1)) ?></div>
    <div class="user-info">
      <strong><?= htmlspecialchars($nombre_us) ?></strong>
      <span><?= $rol ?></span>
    </div>
    <a href="../../logout.php" class="btn-logout"><i class="fas fa-right-from-bracket"></i></a>
  </div>
</aside>

<!-- CONTENIDO -->
<div class="main">
  <header class="topbar">
    <h1><i class="fas fa-car"></i> Vehículos del Cliente</h1>
    <div class="topbar-right">
      <a href="editar.php?id=<?= $cliente_id ?>" class="btn btn-outline">
        <i class="fas fa-pen"></i> Editar Cliente
      </a>
      <a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
  </header>

  <div class="content">

    <?php if ($msg === 'v_creado'): ?>
      <div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Vehículo registrado correctamente.</div>
    <?php elseif ($msg === 'v_eliminado'): ?>
      <div class="alert alert-error alert-auto"><i class="fas fa-trash"></i> Vehículo eliminado.</div>
    <?php endif; ?>

    <!-- Perfil del cliente -->
    <div class="card" style="margin-bottom:20px">
      <div class="card-body">
        <div class="cliente-header">
          <div class="cliente-avatar-lg <?= $cliente['tipo']==='empresa' ? 'avatar-empresa' : 'avatar-natural' ?>"
               style="background:<?= $cliente['tipo']==='empresa' ? 'var(--morado)' : 'var(--azul-acento)' ?>">
            <?= strtoupper(substr($cliente['nombre'], 0, 1)) ?>
          </div>
          <div class="cliente-header-info">
            <h2><?= htmlspecialchars($cliente['nombre']) ?></h2>
            <span class="badge <?= $cliente['tipo']==='empresa' ? 'badge-empresa' : 'badge-natural' ?>">
              <?= $cliente['tipo'] === 'empresa' ? 'Empresa' : 'Persona Natural' ?>
            </span>
            &nbsp;
            <span style="font-size:13px;color:var(--texto-muted)">
              <i class="fas fa-id-card"></i> <?= htmlspecialchars($cliente['dni_ruc']) ?>
              &nbsp;|&nbsp;
              <i class="fas fa-phone"></i> <?= htmlspecialchars($cliente['telefono'] ?? '—') ?>
              &nbsp;|&nbsp;
              <i class="fas fa-envelope"></i> <?= htmlspecialchars($cliente['correo'] ?? '—') ?>
            </span>
          </div>
        </div>

        <!-- Stats -->
        <div class="cliente-stats">
          <div class="cliente-stat">
            <div class="num"><?= count($vehiculos) ?></div>
            <div class="lbl">Vehículos</div>
          </div>
          <div class="cliente-stat">
            <div class="num"><?= $total_ordenes ?></div>
            <div class="lbl">Órdenes de Trabajo</div>
          </div>
          <div class="cliente-stat">
            <div class="num">S/ <?= number_format($total_gastado, 0) ?></div>
            <div class="lbl">Total Gastado</div>
          </div>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

      <!-- Lista de vehículos -->
      <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
          <h3 style="font-size:16px;font-weight:700"><i class="fas fa-car" style="color:var(--azul-acento)"></i> Vehículos registrados</h3>
        </div>

        <?php if (empty($vehiculos)): ?>
          <div class="card">
            <div class="empty">
              <i class="fas fa-car"></i>
              <p>Este cliente no tiene vehículos registrados</p>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($vehiculos as $v): ?>
          <div class="vehiculo-card">
            <div class="vehiculo-icon"><i class="fas fa-car"></i></div>
            <div class="vehiculo-info">
              <div class="vehiculo-placa"><?= htmlspecialchars($v['placa']) ?></div>
              <div class="vehiculo-detalle">
                <span><?= htmlspecialchars($v['marca']) ?> <?= htmlspecialchars($v['modelo']) ?></span>
                · <?= $v['anio'] ?> · <?= htmlspecialchars($v['color'] ?? '') ?>
              </div>
              <div class="vehiculo-detalle" style="margin-top:4px">
                <i class="fas fa-gauge-high"></i> <span><?= number_format($v['km_actual']) ?> km</span>
                <?php if ($v['observaciones']): ?>
                  · <i class="fas fa-note-sticky"></i> <?= htmlspecialchars($v['observaciones']) ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="acciones">
              <a href="../ordenes/index.php?vehiculo=<?= $v['id'] ?>" class="btn-accion ver" title="Ver órdenes">
                <i class="fas fa-clipboard-list"></i>
              </a>
              <?php if ($rol === 'administrador'): ?>
              <a href="vehiculos.php?cliente_id=<?= $cliente_id ?>&eliminar_v=<?= $v['id'] ?>"
                 class="btn-accion eliminar" title="Eliminar"
                 onclick="return confirm('¿Eliminar el vehículo <?= htmlspecialchars(addslashes($v['placa'])) ?>?')">
                <i class="fas fa-trash"></i>
              </a>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Formulario nuevo vehículo -->
      <div class="card">
        <div class="card-header">
          <h3><i class="fas fa-plus-circle"></i> Agregar Vehículo</h3>
        </div>
        <div class="card-body">
          <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <form method="POST">
            <div class="form-group">
              <label>Placa *</label>
              <input type="text" name="placa" class="form-control"
                     placeholder="Ej: ABC-123" style="text-transform:uppercase"
                     value="<?= htmlspecialchars($_POST['placa'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label>Marca *</label>
              <input type="text" name="marca" class="form-control"
                     placeholder="Ej: Toyota"
                     value="<?= htmlspecialchars($_POST['marca'] ?? '') ?>" required>
            </div>
            <div class="form-group">
              <label>Modelo *</label>
              <input type="text" name="modelo" class="form-control"
                     placeholder="Ej: Corolla"
                     value="<?= htmlspecialchars($_POST['modelo'] ?? '') ?>" required>
            </div>
            <div class="form-grid">
              <div class="form-group">
                <label>Año</label>
                <input type="number" name="anio" class="form-control"
                       min="1980" max="<?= date('Y')+1 ?>"
                       value="<?= htmlspecialchars($_POST['anio'] ?? date('Y')) ?>">
              </div>
              <div class="form-group">
                <label>Color</label>
                <input type="text" name="color" class="form-control"
                       placeholder="Ej: Blanco"
                       value="<?= htmlspecialchars($_POST['color'] ?? '') ?>">
              </div>
            </div>
            <div class="form-group">
              <label>Kilometraje actual</label>
              <input type="number" name="km_actual" class="form-control"
                     min="0" placeholder="Ej: 85000"
                     value="<?= htmlspecialchars($_POST['km_actual'] ?? '0') ?>">
            </div>
            <div class="form-group">
              <label>Observaciones</label>
              <input type="text" name="observaciones" class="form-control"
                     placeholder="Notas adicionales..."
                     value="<?= htmlspecialchars($_POST['observaciones'] ?? '') ?>">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">
              <i class="fas fa-plus"></i> Registrar Vehículo
            </button>
          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="../../assets/js/main.js"></script>
</body>
</html>