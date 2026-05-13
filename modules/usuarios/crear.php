<?php
// ============================================================
//  modules/usuarios/crear.php — Nuevo Usuario
// ============================================================
require_once '../../includes/auth.php';
require_once '../../config/database.php';
soloAdmin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom     = trim($_POST['nombre']   ?? '');
    $ape     = trim($_POST['apellido'] ?? '');
    $correo  = trim($_POST['correo']   ?? '');
    $pass    = $_POST['password']      ?? '';
    $pass2   = $_POST['password2']     ?? '';
    $rol     = $_POST['rol']           ?? '';

    $roles_validos = ['administrador','cajero','mecanico'];

    if (!$nom || !$ape)              $error = 'Nombre y apellido son obligatorios.';
    elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $error = 'Correo inválido.';
    elseif (strlen($pass) < 8)       $error = 'La contraseña debe tener al menos 8 caracteres.';
    elseif ($pass !== $pass2)        $error = 'Las contraseñas no coinciden.';
    elseif (!in_array($rol,$roles_validos)) $error = 'Rol inválido.';
    else {
        // Verificar correo único
        $existe = dbQuery("SELECT id FROM usuarios WHERE correo=?", [$correo]);
        if ($existe) {
            $error = 'Ya existe un usuario con ese correo.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            dbQuery(
                "INSERT INTO usuarios (nombre,apellido,correo,password,rol,activo)
                 VALUES (?,?,?,?,?,1)",
                [$nom, $ape, $correo, $hash, $rol]
            );
            header('Location: index.php?msg=creado'); exit;
        }
    }
}

$PAGE_TITLE  = 'Nuevo Usuario';
$PAGE_ICON   = 'fa-user-plus';
$ACTIVE_MENU = 'usuarios';
$TOPBAR_ACTIONS = '<a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>';
require_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/usuarios.css">

<?php if ($error): ?>
  <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="inv-form-card card" style="max-width:620px">
  <div class="card-header">
    <h3><i class="fas fa-user-plus"></i> Datos del nuevo usuario</h3>
  </div>
  <div class="card-body">
    <form method="POST" action="crear.php">

      <p class="form-seccion">Información personal</p>
      <div class="form-grid">
        <div class="form-group">
          <label>Nombre *</label>
          <input type="text" name="nombre" class="form-control"
                 value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                 placeholder="Ej: Carlos" required autofocus>
        </div>
        <div class="form-group">
          <label>Apellido *</label>
          <input type="text" name="apellido" class="form-control"
                 value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>"
                 placeholder="Ej: Quispe" required>
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Correo electrónico *</label>
          <input type="email" name="correo" class="form-control"
                 value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>"
                 placeholder="correo@taller.com" required>
        </div>
      </div>

      <p class="form-seccion">Rol y acceso</p>
      <div class="form-group">
        <label>Rol *</label>
        <div class="usu-rol-grid">
          <?php
          $roles_info = [
            'mecanico'      => ['fa-screwdriver-wrench','Mecánico',    'Puede ver y gestionar órdenes de trabajo.'],
            'cajero'        => ['fa-cash-register',     'Cajero',      'Puede emitir comprobantes y gestionar inventario.'],
            'administrador' => ['fa-shield-halved',     'Administrador','Acceso total al sistema.'],
          ];
          foreach ($roles_info as $r => [$ico, $label, $desc]):
          ?>
          <label class="usu-rol-btn">
            <input type="radio" name="rol" value="<?= $r ?>"
                   <?= ($_POST['rol'] ?? 'mecanico') === $r ? 'checked' : '' ?>>
            <i class="fas <?= $ico ?>"></i>
            <span><?= $label ?></span>
            <small><?= $desc ?></small>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <p class="form-seccion">Contraseña</p>
      <div class="form-grid">
        <div class="form-group">
          <label>Contraseña * (mín. 8 caracteres)</label>
          <div class="pass-wrapper">
            <input type="password" name="password" id="pass1" class="form-control"
                   placeholder="••••••••" required minlength="8">
            <button type="button" class="pass-toggle" onclick="togglePass('pass1',this)">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>
        <div class="form-group">
          <label>Confirmar contraseña *</label>
          <div class="pass-wrapper">
            <input type="password" name="password2" id="pass2" class="form-control"
                   placeholder="••••••••" required minlength="8">
            <button type="button" class="pass-toggle" onclick="togglePass('pass2',this)">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:12px;margin-top:8px">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save"></i> Crear Usuario
        </button>
        <a href="index.php" class="btn btn-outline">Cancelar</a>
      </div>
    </form>
  </div>
</div>

  </div></div>
<script>
function togglePass(id, btn) {
  const input = document.getElementById(id);
  const isPass = input.type === 'password';
  input.type = isPass ? 'text' : 'password';
  btn.querySelector('i').className = 'fas fa-eye' + (isPass ? '-slash' : '');
}
</script>
<script src="../../assets/js/main.js"></script>