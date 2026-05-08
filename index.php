<?php
// ============================================================
//  index.php  —  Login del Sistema
//  Sistema de Gestión — Taller Mecánico
// ============================================================
session_start();

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo   = trim($_POST['correo']   ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($correo) || empty($password)) {
        $error = 'Por favor completa todos los campos.';
    } else {
        $rows = dbQuery(
            "SELECT id, nombre, apellido, correo, password, rol FROM usuarios WHERE correo = ? AND activo = 1",
            [$correo]
        );

        if ($rows && password_verify($password, $rows[0]['password'])) {
            $u = $rows[0];
            $_SESSION['usuario_id']   = $u['id'];
            $_SESSION['usuario_nombre'] = $u['nombre'] . ' ' . $u['apellido'];
            $_SESSION['usuario_rol']  = $u['rol'];
            $_SESSION['usuario_correo'] = $u['correo'];
            header('Location: dashboard.php');
            exit;
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --azul-oscuro:  #0f1e36;
      --azul-medio:   #1a3a5c;
      --azul-acento:  #1d6fa4;
      --azul-claro:   #3b9fd1;
      --naranja:      #e8820c;
      --naranja-hover:#ff9a1f;
      --blanco:       #ffffff;
      --gris-claro:   #d0dce8;
      --texto-muted:  #7a9bb5;
    }

    body {
      min-height: 100vh;
      background: var(--azul-oscuro);
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Inter', sans-serif;
      overflow: hidden;
      position: relative;
    }

    /* ── Fondo animado con engranajes ── */
    .bg-pattern {
      position: fixed; inset: 0; z-index: 0;
      background:
        radial-gradient(ellipse at 20% 50%, rgba(29,111,164,0.15) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 20%, rgba(232,130,12,0.08) 0%, transparent 50%),
        var(--azul-oscuro);
    }

    .gear {
      position: fixed;
      border-radius: 50%;
      border: 3px solid rgba(29,111,164,0.12);
      animation: spin linear infinite;
    }
    .gear::before {
      content: '';
      position: absolute;
      inset: -12px;
      border-radius: 50%;
      border: 2px dashed rgba(29,111,164,0.08);
    }
    .gear-1 { width: 300px; height: 300px; top: -80px; left: -80px; animation-duration: 30s; }
    .gear-2 { width: 200px; height: 200px; bottom: -50px; right: 10%; animation-duration: 20s; animation-direction: reverse; }
    .gear-3 { width: 150px; height: 150px; top: 40%; right: -40px; animation-duration: 25s; }

    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

    /* ── Tarjeta principal ── */
    .card {
      position: relative; z-index: 10;
      width: 420px;
      background: rgba(26, 58, 92, 0.55);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(59,159,209,0.2);
      border-radius: 20px;
      padding: 48px 44px;
      box-shadow:
        0 30px 80px rgba(0,0,0,0.5),
        0 0 0 1px rgba(255,255,255,0.04) inset;
      animation: fadeUp .6s ease both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(28px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Logo / encabezado ── */
    .logo-area {
      text-align: center;
      margin-bottom: 36px;
    }

    .logo-icon {
      width: 68px; height: 68px;
      background: linear-gradient(135deg, var(--naranja), #c0620a);
      border-radius: 18px;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 30px; color: #fff;
      margin-bottom: 16px;
      box-shadow: 0 8px 25px rgba(232,130,12,0.35);
      animation: pulse 3s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { box-shadow: 0 8px 25px rgba(232,130,12,0.35); }
      50%       { box-shadow: 0 8px 35px rgba(232,130,12,0.55); }
    }

    .logo-area h1 {
      font-family: 'Rajdhani', sans-serif;
      font-size: 26px; font-weight: 700;
      color: var(--blanco);
      letter-spacing: 1px;
    }

    .logo-area p {
      font-size: 13px;
      color: var(--texto-muted);
      margin-top: 4px;
      letter-spacing: 0.5px;
    }

    /* ── Formulario ── */
    .form-group {
      margin-bottom: 20px;
    }

    label {
      display: block;
      font-size: 12px;
      font-weight: 500;
      color: var(--gris-claro);
      letter-spacing: 1px;
      text-transform: uppercase;
      margin-bottom: 8px;
    }

    .input-wrapper {
      position: relative;
    }

    .input-wrapper i {
      position: absolute;
      left: 14px; top: 50%;
      transform: translateY(-50%);
      color: var(--texto-muted);
      font-size: 15px;
      pointer-events: none;
      transition: color .2s;
    }

    input[type="email"],
    input[type="password"] {
      width: 100%;
      background: rgba(15,30,54,0.6);
      border: 1px solid rgba(59,159,209,0.2);
      border-radius: 10px;
      padding: 13px 14px 13px 42px;
      color: var(--blanco);
      font-size: 14px;
      font-family: 'Inter', sans-serif;
      outline: none;
      transition: border-color .25s, box-shadow .25s;
    }

    input[type="email"]:focus,
    input[type="password"]:focus {
      border-color: var(--azul-claro);
      box-shadow: 0 0 0 3px rgba(59,159,209,0.15);
    }

    input[type="email"]:focus + i,
    input[type="password"]:focus + i {
      color: var(--azul-claro);
    }

    input::placeholder { color: rgba(122,155,181,0.6); }

    /* ── Botón ── */
    .btn-login {
      width: 100%;
      margin-top: 8px;
      padding: 14px;
      background: linear-gradient(135deg, var(--naranja), #c0620a);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-family: 'Rajdhani', sans-serif;
      font-size: 17px;
      font-weight: 700;
      letter-spacing: 1.5px;
      text-transform: uppercase;
      cursor: pointer;
      transition: all .25s;
      box-shadow: 0 6px 20px rgba(232,130,12,0.3);
    }

    .btn-login:hover {
      background: linear-gradient(135deg, var(--naranja-hover), var(--naranja));
      transform: translateY(-2px);
      box-shadow: 0 10px 28px rgba(232,130,12,0.45);
    }

    .btn-login:active { transform: translateY(0); }

    /* ── Error ── */
    .alert-error {
      background: rgba(220,38,38,0.15);
      border: 1px solid rgba(220,38,38,0.35);
      border-radius: 10px;
      padding: 12px 16px;
      color: #fca5a5;
      font-size: 13px;
      display: flex; align-items: center; gap: 10px;
      margin-bottom: 20px;
      animation: shake .4s ease;
    }

    @keyframes shake {
      0%,100% { transform: translateX(0); }
      20%,60%  { transform: translateX(-6px); }
      40%,80%  { transform: translateX(6px); }
    }

    /* ── Footer de la card ── */
    .card-footer {
      margin-top: 28px;
      padding-top: 20px;
      border-top: 1px solid rgba(59,159,209,0.1);
      display: flex; justify-content: center; gap: 16px;
    }

    .rol-badge {
      font-size: 11px;
      background: rgba(29,111,164,0.2);
      border: 1px solid rgba(59,159,209,0.2);
      color: var(--texto-muted);
      padding: 4px 10px;
      border-radius: 20px;
      letter-spacing: 0.5px;
    }

    /* ── Hint de credenciales (solo desarrollo) ── */
    .dev-hint {
      margin-top: 16px;
      background: rgba(15,30,54,0.5);
      border: 1px dashed rgba(59,159,209,0.2);
      border-radius: 10px;
      padding: 12px 16px;
    }

    .dev-hint p {
      font-size: 11px;
      color: var(--texto-muted);
      margin-bottom: 4px;
    }

    .dev-hint span { color: var(--azul-claro); font-weight: 500; }
  </style>
</head>
<body>

<div class="bg-pattern"></div>
<div class="gear gear-1"></div>
<div class="gear gear-2"></div>
<div class="gear gear-3"></div>

<div class="card">

  <div class="logo-area">
    <div class="logo-icon"><i class="fas fa-wrench"></i></div>
    <h1><?= APP_NAME ?></h1>
    <p>Sistema de Gestión Integral</p>
  </div>

  <?php if ($error): ?>
  <div class="alert-error">
    <i class="fas fa-circle-exclamation"></i>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="index.php" autocomplete="off">
    <div class="form-group">
      <label for="correo">Correo electrónico</label>
      <div class="input-wrapper">
        <input
          type="email"
          id="correo"
          name="correo"
          placeholder="tucorreo@taller.com"
          value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>"
          required
          autofocus
        >
        <i class="fas fa-envelope"></i>
      </div>
    </div>

    <div class="form-group">
      <label for="password">Contraseña</label>
      <div class="input-wrapper">
        <input
          type="password"
          id="password"
          name="password"
          placeholder="••••••••"
          required
        >
        <i class="fas fa-lock"></i>
      </div>
    </div>

    <button type="submit" class="btn-login">
      <i class="fas fa-right-to-bracket"></i> &nbsp;Ingresar al Sistema
    </button>
  </form>

  <div class="card-footer">
    <span class="rol-badge"><i class="fas fa-shield-halved"></i> Administrador</span>
    <span class="rol-badge"><i class="fas fa-screwdriver-wrench"></i> Mecánico</span>
    <span class="rol-badge"><i class="fas fa-cash-register"></i> Cajero</span>
  </div>

  <!-- Eliminar este bloque en producción -->
  <div class="dev-hint">
    <p>⚙️ Cuentas de prueba (contraseña: <span>password</span>)</p>
    <p>Admin: <span>admin@taller.com</span></p>
    <p>Mecánico: <span>mecanico@taller.com</span></p>
    <p>Cajero: <span>cajero@taller.com</span></p>
  </div>

</div>

</body>
</html>