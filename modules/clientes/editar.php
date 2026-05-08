<?php
// ============================================================
//  modules/clientes/editar.php — Editar Cliente
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }

$rol    = $_SESSION['usuario_rol'];
$nombre_us = $_SESSION['usuario_nombre'];
$error  = '';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

// Cargar datos del cliente
$clientes = dbQuery("SELECT * FROM clientes WHERE id = ?", [$id]);
if (!$clientes) { header('Location: index.php'); exit; }
$c = $clientes[0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom      = trim($_POST['nombre']    ?? '');
    $dni_ruc  = trim($_POST['dni_ruc']   ?? '');
    $telefono = trim($_POST['telefono']  ?? '');
    $correo   = trim($_POST['correo']    ?? '');
    $direccion= trim($_POST['direccion'] ?? '');
    $tipo     = $_POST['tipo'] ?? 'natural';

    if (empty($nom) || empty($dni_ruc)) {
        $error = 'El nombre y DNI/RUC son obligatorios.';
    } else {
        $existe = dbQuery("SELECT id FROM clientes WHERE dni_ruc = ? AND id != ?", [$dni_ruc, $id]);
        if ($existe) {
            $error = 'Ya existe otro cliente con ese DNI/RUC.';
        } else {
            dbQuery(
                "UPDATE clientes SET nombre=?, dni_ruc=?, telefono=?, correo=?, direccion=?, tipo=? WHERE id=?",
                [$nom, $dni_ruc, $telefono, $correo, $direccion, $tipo, $id]
            );
            header("Location: index.php?msg=editado");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Cliente — <?= APP_NAME ?></title>
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
    <h1><i class="fas fa-pen"></i> Editar Cliente</h1>
    <div class="topbar-right">
      <a href="vehiculos.php?cliente_id=<?= $id ?>" class="btn btn-outline">
        <i class="fas fa-car"></i> Ver Vehículos
      </a>
      <a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
  </header>

  <div class="content">
    <div class="card" style="max-width:700px;margin:0 auto">
      <div class="card-header">
        <h3>
          <div class="cliente-avatar <?= $c['tipo']==='empresa' ? 'avatar-empresa' : 'avatar-natural' ?>"
               style="width:32px;height:32px;font-size:13px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:700;margin-right:8px;background:<?= $c['tipo']==='empresa' ? 'var(--morado)' : 'var(--azul-acento)' ?>">
            <?= strtoupper(substr($c['nombre'], 0, 1)) ?>
          </div>
          <?= htmlspecialchars($c['nombre']) ?>
        </h3>
      </div>
      <div class="card-body">

        <?php if ($error): ?>
          <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="editar.php?id=<?= $id ?>">

          <div class="form-section">
            <div class="form-section-title"><i class="fas fa-id-card"></i> Tipo de Cliente</div>
            <div style="display:flex;gap:16px">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
                <input type="radio" name="tipo" value="natural"
                       <?= $c['tipo']==='natural' ? 'checked' : '' ?>
                       onchange="toggleTipo('natural')">
                <i class="fas fa-user" style="color:var(--azul-acento)"></i> Persona Natural
              </label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
                <input type="radio" name="tipo" value="empresa"
                       <?= $c['tipo']==='empresa' ? 'checked' : '' ?>
                       onchange="toggleTipo('empresa')">
                <i class="fas fa-building" style="color:var(--morado)"></i> Empresa
              </label>
            </div>
          </div>

          <div class="form-section">
            <div class="form-section-title"><i class="fas fa-circle-info"></i> Datos del Cliente</div>
            <div class="form-grid">
              <div class="form-group" style="grid-column:1/-1">
                <label>Nombre completo / Razón social *</label>
                <input type="text" name="nombre" class="form-control"
                       value="<?= htmlspecialchars($_POST['nombre'] ?? $c['nombre']) ?>" required>
              </div>
              <div class="form-group">
                <label id="label-dni"><?= $c['tipo']==='empresa' ? 'RUC' : 'DNI' ?> *</label>
                <input type="text" name="dni_ruc" id="dni_ruc" class="form-control"
                       maxlength="<?= $c['tipo']==='empresa' ? '11' : '8' ?>"
                       value="<?= htmlspecialchars($_POST['dni_ruc'] ?? $c['dni_ruc']) ?>" required>
              </div>
              <div class="form-group">
                <label>Teléfono</label>
                <input type="text" name="telefono" class="form-control"
                       value="<?= htmlspecialchars($_POST['telefono'] ?? $c['telefono']) ?>">
              </div>
              <div class="form-group">
                <label>Correo electrónico</label>
                <input type="email" name="correo" class="form-control"
                       value="<?= htmlspecialchars($_POST['correo'] ?? $c['correo']) ?>">
              </div>
              <div class="form-group">
                <label>Dirección</label>
                <input type="text" name="direccion" class="form-control"
                       value="<?= htmlspecialchars($_POST['direccion'] ?? $c['direccion']) ?>">
              </div>
            </div>
          </div>

          <div style="display:flex;gap:12px;justify-content:flex-end">
            <a href="index.php" class="btn btn-outline"><i class="fas fa-times"></i> Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="../../assets/js/main.js"></script>
<script>
function toggleTipo(tipo) {
  const label = document.getElementById('label-dni');
  const input = document.getElementById('dni_ruc');
  if (tipo === 'empresa') {
    label.textContent = 'RUC *';
    input.maxLength = 11;
  } else {
    label.textContent = 'DNI *';
    input.maxLength = 8;
  }
}
</script>
</body>
</html>