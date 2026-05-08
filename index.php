<?php
// ============================================================
//  index.php — Login  |  Sistema Taller Mecánico
// ============================================================
session_start();
if (isset($_SESSION['usuario_id'])) { header('Location: dashboard.php'); exit; }
require_once 'config/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $correo   = trim($_POST['correo']   ?? '');
  $password = trim($_POST['password'] ?? '');
  if (empty($correo) || empty($password)) {
    $error = 'Por favor completa todos los campos.';
  } else {
    $rows = dbQuery("SELECT id, nombre, apellido, password, rol FROM usuarios WHERE correo = ? AND activo = 1", [$correo]);
    if ($rows && password_verify($password, $rows[0]['password'])) {
      $_SESSION['usuario_id']     = $rows[0]['id'];
      $_SESSION['usuario_nombre'] = $rows[0]['nombre'] . ' ' . $rows[0]['apellido'];
      $_SESSION['usuario_rol']    = $rows[0]['rol'];
      header('Location: dashboard.php'); exit;
    } else {
      $error = 'Correo o contraseña incorrectos.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

<!-- Fondo -->
<div class="login-bg"></div>

<!-- ═══════ TARJETA PRINCIPAL ═══════ -->
<div class="login-card">

  <!-- ── PANEL IZQUIERDO: Formulario ── -->
  <div class="panel-left">

    <!-- Logo -->
    <div class="login-logo">
      <div class="login-logo-icon"><i class="fas fa-wrench"></i></div>
      <div class="login-logo-text">
        <h2><?= APP_NAME ?></h2>
        <span>Sistema de Gestión</span>
      </div>
    </div>

    <!-- Encabezado -->
    <div class="form-heading">
      <h1>Bienvenido</h1>
      <p>Ingresa tus credenciales para continuar</p>
    </div>

    <div class="form-divider"></div>

    <!-- Alerta de error -->
    <?php if ($error): ?>
    <div class="alert-login">
      <i class="fas fa-circle-exclamation"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Formulario -->
    <form method="POST" action="index.php" autocomplete="off">

      <!-- Correo — input VA PRIMERO (selector :focus ~) -->
      <div class="form-group">
        <label class="login-label" for="correo">Correo electrónico</label>
        <div class="block-cube block-input">
          <input type="email" id="correo" name="correo"
            placeholder="tucorreo@taller.com"
            value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>"
            required autofocus>
          <div class="bg-top"><div class="bg-inner"></div></div>
          <div class="bg-right"><div class="bg-inner"></div></div>
          <div class="bg"><div class="bg-inner"></div></div>
          <i class="fas fa-envelope cube-icon"></i>
        </div>
      </div>

      <!-- Contraseña — input VA PRIMERO -->
      <div class="form-group">
        <label class="login-label" for="password">Contraseña</label>
        <div class="block-cube block-input">
          <input type="password" id="password" name="password"
            placeholder="••••••••" required>
          <div class="bg-top"><div class="bg-inner"></div></div>
          <div class="bg-right"><div class="bg-inner"></div></div>
          <div class="bg"><div class="bg-inner"></div></div>
          <i class="fas fa-lock cube-icon"></i>
        </div>
      </div>

      <!-- Botón — bg VAN PRIMERO, luego .text -->
      <div class="block-cube block-cube-hover"
           onclick="this.closest('form').submit()"
           tabindex="0"
           onkeydown="if(event.key==='Enter')this.closest('form').submit()">
        <div class="bg-top"><div class="bg-inner"></div></div>
        <div class="bg-right"><div class="bg-inner"></div></div>
        <div class="bg"><div class="bg-inner"></div></div>
        <span class="text btn-cube">
          <i class="fas fa-right-to-bracket"></i> &nbsp;Ingresar al Sistema
        </span>
      </div>
      <button type="submit" style="display:none"></button>

    </form>

    <!-- Roles -->
    <div class="form-footer">
      <span class="rol-badge"><i class="fas fa-shield-halved"></i> Administrador</span>
      <span class="rol-badge"><i class="fas fa-screwdriver-wrench"></i> Mecánico</span>
      <span class="rol-badge"><i class="fas fa-cash-register"></i> Cajero</span>
    </div>

    <!-- Dev hint -->
    <div class="dev-hint">
      <p>⚙ Cuentas de prueba &mdash; contraseña: <span>password</span></p>
      <p>Admin: <span>admin@taller.com</span> &nbsp;|&nbsp;
         Mecánico: <span>mecanico@taller.com</span> &nbsp;|&nbsp;
         Cajero: <span>cajero@taller.com</span></p>
    </div>

  </div><!-- /panel-left -->

  <!-- ── PANEL DERECHO: Decorativo ── -->
  <div class="panel-right">
    <div class="gear gear-1"></div>
    <div class="gear gear-2"></div>
    <div class="gear gear-3"></div>

    <div class="panel-right-content">
      <div class="panel-icon">
        <i class="fas fa-car-on"></i>
      </div>

      <h3>Taller Mecánico<br>Profesional</h3>
      <p>Gestión integral de servicios, inventario, clientes y facturación.</p>

      <div class="panel-stats">
        <div class="stat-row">
          <i class="fas fa-boxes-stacked"></i>
          <span>Control de inventario en tiempo real</span>
        </div>
        <div class="stat-row">
          <i class="fas fa-clipboard-list"></i>
          <span>Órdenes de trabajo digitales</span>
        </div>
        <div class="stat-row">
          <i class="fas fa-file-invoice"></i>
          <span>Boletas y facturas electrónicas</span>
        </div>
        <div class="stat-row">
          <i class="fas fa-chart-line"></i>
          <span>Reportes y estadísticas</span>
        </div>
      </div>
    </div>
  </div><!-- /panel-right -->

</div><!-- /login-card -->

<script src="assets/js/main.js"></script>
</body>
</html>