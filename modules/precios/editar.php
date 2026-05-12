<?php
// ============================================================
//  modules/precios/editar.php — Editar Servicio
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }
if ($_SESSION['usuario_rol'] === 'mecanico') { header('Location: index.php'); exit; }

$rol    = $_SESSION['usuario_rol'];
$nombre = $_SESSION['usuario_nombre'];
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
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Editar Servicio — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/precios.css">
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
    <a href="../ordenes/index.php"      class="menu-item"><i class="fas fa-clipboard-list"></i> Órdenes de Trabajo</a>
    <a href="../clientes/index.php"     class="menu-item"><i class="fas fa-users"></i> Clientes y Vehículos</a>
    <a href="../comprobantes/index.php" class="menu-item"><i class="fas fa-file-invoice"></i> Boletas y Facturas</a>
    <div class="menu-section">Almacén</div>
    <a href="../inventario/index.php" class="menu-item"><i class="fas fa-boxes-stacked"></i> Inventario</a>
    <a href="index.php"               class="menu-item active"><i class="fas fa-tags"></i> Precios y Servicios</a>
    <?php if ($rol === 'administrador'): ?>
    <div class="menu-section">Administración</div>
    <a href="../reportes/index.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reportes</a>
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
    <h1><i class="fas fa-pen"></i> Editar Servicio</h1>
    <div class="topbar-right">
      <a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>
  </header>

  <div class="content">
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