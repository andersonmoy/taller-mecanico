<?php
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
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <!-- CSS separados -->
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

<div class="bg-pattern"></div>
<div class="gear gear-1"></div>
<div class="gear gear-2"></div>
<div class="gear gear-3"></div>

<div class="login-card">
  <div class="logo-area">
    <div class="logo-icon"><i class="fas fa-wrench"></i></div>
    <h1><?= APP_NAME ?></h1>
    <p>Sistema de Gestión Integral</p>
  </div>

  <?php if ($error): ?>
  <div class="alert-login">
    <i class="fas fa-circle-exclamation"></i>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="index.php" autocomplete="off">
    <div class="form-group">
      <label class="login-label" for="correo">Correo electrónico</label>
      <div class="input-wrapper">
        <input class="login-input" type="email" id="correo" name="correo"
               placeholder="tucorreo@taller.com"
               value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>" required autofocus>
        <i class="fas fa-envelope"></i>
      </div>
    </div>
    <div class="form-group">
      <label class="login-label" for="password">Contraseña</label>
      <div class="input-wrapper">
        <input class="login-input" type="password" id="password" name="password"
               placeholder="••••••••" required>
        <i class="fas fa-lock"></i>
      </div>
    </div>
    <button type="submit" class="btn-login">
      <i class="fas fa-right-to-bracket"></i> &nbsp;Ingresar al Sistema
    </button>
  </form>

  <div class="login-footer">
    <span class="rol-badge"><i class="fas fa-shield-halved"></i> Administrador</span>
    <span class="rol-badge"><i class="fas fa-screwdriver-wrench"></i> Mecánico</span>
    <span class="rol-badge"><i class="fas fa-cash-register"></i> Cajero</span>
  </div>

  <div class="dev-hint">
    <p>⚙️ Cuentas de prueba (contraseña: <span>password</span>)</p>
    <p>Admin: <span>admin@taller.com</span></p>
    <p>Mecánico: <span>mecanico@taller.com</span></p>
    <p>Cajero: <span>cajero@taller.com</span></p>
  </div>
</div>

<script src="assets/js/main.js"></script>
</body>
</html>