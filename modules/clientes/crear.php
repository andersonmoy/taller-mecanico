<?php
// ============================================================
//  modules/clientes/crear.php — Registrar Nuevo Cliente
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }

$rol    = $_SESSION['usuario_rol'];
$nombre = $_SESSION['usuario_nombre'];
$error  = '';

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
        // Verificar si ya existe ese DNI/RUC
        $existe = dbQuery("SELECT id FROM clientes WHERE dni_ruc = ?", [$dni_ruc]);
        if ($existe) {
            $error = 'Ya existe un cliente con ese DNI/RUC.';
        } else {
            dbQuery(
                "INSERT INTO clientes (nombre, dni_ruc, telefono, correo, direccion, tipo) VALUES (?,?,?,?,?,?)",
                [$nom, $dni_ruc, $telefono, $correo, $direccion, $tipo]
            );
            $nuevo_id = dbLastId();
            header("Location: index.php?msg=creado");
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
  <title>Nuevo Cliente — <?= APP_NAME ?></title>
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
    <div class="user-avatar"><?= strtoupper(substr($nombre, 0, 1)) ?></div>
    <div class="user-info">
      <strong><?= htmlspecialchars($nombre) ?></strong>
      <span><?= $rol ?></span>
    </div>
    <a href="../../logout.php" class="btn-logout"><i class="fas fa-right-from-bracket"></i></a>
  </div>
</aside>

<!-- CONTENIDO -->
<div class="main">
  <header class="topbar">
    <h1><i class="fas fa-user-plus"></i> Nuevo Cliente</h1>
    <div class="topbar-right">
      <a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
  </header>

  <div class="content">
    <div class="card" style="max-width:700px;margin:0 auto">
      <div class="card-header">
        <h3><i class="fas fa-user-plus"></i> Registrar Nuevo Cliente</h3>
      </div>
      <div class="card-body">

        <?php if ($error): ?>
          <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="crear.php">

          <!-- Tipo de cliente -->
          <div class="form-section">
            <div class="form-section-title"><i class="fas fa-id-card"></i> Tipo de Cliente</div>
            <div style="display:flex;gap:16px">
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
                <input type="radio" name="tipo" value="natural"
                       <?= ($_POST['tipo'] ?? 'natural') === 'natural' ? 'checked' : '' ?>
                       onchange="toggleTipo('natural')">
                <i class="fas fa-user" style="color:var(--azul-acento)"></i> Persona Natural (DNI)
              </label>
              <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px">
                <input type="radio" name="tipo" value="empresa"
                       <?= ($_POST['tipo'] ?? '') === 'empresa' ? 'checked' : '' ?>
                       onchange="toggleTipo('empresa')">
                <i class="fas fa-building" style="color:var(--morado)"></i> Empresa (RUC)
              </label>
            </div>
          </div>

          <!-- Datos personales -->
          <div class="form-section">
            <div class="form-section-title"><i class="fas fa-circle-info"></i> Datos del Cliente</div>
            <div class="form-grid">
              <div class="form-group" style="grid-column:1/-1">
                <label>Nombre completo / Razón social *</label>
                <input type="text" name="nombre" class="form-control"
                       placeholder="Ej: Juan Pérez López"
                       value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label id="label-dni">DNI / RUC *</label>
                <input type="text" name="dni_ruc" id="dni_ruc" class="form-control"
                       placeholder="Ej: 12345678"
                       maxlength="11"
                       value="<?= htmlspecialchars($_POST['dni_ruc'] ?? '') ?>" required>
              </div>
              <div class="form-group">
                <label>Teléfono</label>
                <input type="text" name="telefono" class="form-control"
                       placeholder="Ej: 987654321"
                       value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Correo electrónico</label>
                <input type="email" name="correo" class="form-control"
                       placeholder="correo@ejemplo.com"
                       value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label>Dirección</label>
                <input type="text" name="direccion" class="form-control"
                       placeholder="Ej: Av. Sol 123, Cusco"
                       value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>">
              </div>
            </div>
          </div>

          <div style="display:flex;gap:12px;justify-content:flex-end">
            <a href="index.php" class="btn btn-outline"><i class="fas fa-times"></i> Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cliente</button>
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
    input.placeholder = 'Ej: 20123456789';
    input.maxLength = 11;
  } else {
    label.textContent = 'DNI *';
    input.placeholder = 'Ej: 12345678';
    input.maxLength = 8;
  }
}
</script>
</body>
</html>