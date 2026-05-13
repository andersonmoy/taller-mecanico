<?php
// ============================================================
//  modules/usuarios/editar.php — Editar Usuario
// ============================================================
require_once '../../includes/auth.php';
require_once '../../config/database.php';
soloAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$usuario = dbQuery("SELECT * FROM usuarios WHERE id=?", [$id]);
if (!$usuario) { header('Location: index.php'); exit; }
$usuario = $usuario[0];

$error = '';
$es_yo = $id == $SESSION_ID;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom    = trim($_POST['nombre']   ?? '');
    $ape    = trim($_POST['apellido'] ?? '');
    $correo = trim($_POST['correo']   ?? '');
    $rol    = $_POST['rol']           ?? '';
    $pass   = $_POST['password']      ?? '';
    $pass2  = $_POST['password2']     ?? '';

    if (!$nom || !$ape)   $error = 'Nombre y apellido son obligatorios.';
    elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) $error = 'Correo inválido.';
    elseif ($pass && strlen($pass) < 8) $error = 'La contraseña debe tener al menos 8 caracteres.';
    elseif ($pass && $pass !== $pass2)  $error = 'Las contraseñas no coinciden.';
    else {
        // Verificar correo único (excepto el propio)
        $existe = dbQuery("SELECT id FROM usuarios WHERE correo=? AND id!=?", [$correo, $id]);
        if ($existe) {
            $error = 'Ya existe otro usuario con ese correo.';
        } else {
            if ($pass) {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                dbQuery(
                    "UPDATE usuarios SET nombre=?,apellido=?,correo=?,rol=?,password=? WHERE id=?",
                    [$nom, $ape, $correo, $rol, $hash, $id]
                );
            } else {
                dbQuery(
                    "UPDATE usuarios SET nombre=?,apellido=?,correo=?,rol=? WHERE id=?",
                    [$nom, $ape, $correo, $rol, $id]
                );
            }
            header('Location: index.php?msg=editado'); exit;
        }
    }
}

$v = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $usuario;

$PAGE_TITLE  = 'Editar Usuario';
$PAGE_ICON   = 'fa-user-pen';
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
    <h3><i class="fas fa-user-pen"></i>
      <?= htmlspecialchars($usuario['nombre'].' '.$usuario['apellido']) ?>
      <?php if ($es_yo): ?>
        <span class="badge badge-lista" style="font-size:11px">Tu cuenta</span>
      <?php endif; ?>
    </h3>
  </div>
  <div class="card-body">
    <form method="POST" action="editar.php?id=<?= $id ?>">

      <p class="form-seccion">Información personal</p>
      <div class="form-grid">
        <div class="form-group">
          <label>Nombre *</label>
          <input type="text" name="nombre" class="form-control"
                 value="<?= htmlspecialchars($v['nombre']) ?>" required autofocus>
        </div>
        <div class="form-group">
          <label>Apellido *</label>
          <input type="text" name="apellido" class="form-control"
                 value="<?= htmlspecialchars($v['apellido']) ?>" required>
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Correo electrónico *</label>
          <input type="email" name="correo" class="form-control"
                 value="<?= htmlspecialchars($v['correo']) ?>" required>
        </div>
      </div>

      <?php if (!$es_yo): ?>
      <p class="form-seccion">Rol</p>
      <div class="form-group">
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
                   <?= $v['rol'] === $r ? 'checked' : '' ?>>
            <i class="fas <?= $ico ?>"></i>
            <span><?= $label ?></span>
            <small><?= $desc ?></small>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
        <input type="hidden" name="rol" value="<?= $usuario['rol'] ?>">
      <?php endif; ?>

      <p class="form-seccion">Cambiar Contraseña <small style="font-weight:400;text-transform:none;letter-spacing:0">(dejar vacío para no cambiar)</small></p>
      <div class="form-grid">
        <div class="form-group">
          <label>Nueva contraseña</label>
          <div class="pass-wrapper">
            <input type="password" name="password" id="pass1" class="form-control"
                   placeholder="••••••••" minlength="8">
            <button type="button" class="pass-toggle" onclick="togglePass('pass1',this)">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>
        <div class="form-group">
          <label>Confirmar nueva contraseña</label>
          <div class="pass-wrapper">
            <input type="password" name="password2" id="pass2" class="form-control"
                   placeholder="••••••••" minlength="8">
            <button type="button" class="pass-toggle" onclick="togglePass('pass2',this)">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>
      </div>

      <div style="display:flex;gap:12px;margin-top:8px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
        <a href="index.php" class="btn btn-outline">Cancelar</a>
      </div>
    </form>
  </div>
</div>

  </div></div>
<script>
function togglePass(id,btn){
  const i=document.getElementById(id);
  const isP=i.type==='password';
  i.type=isP?'text':'password';
  btn.querySelector('i').className='fas fa-eye'+(isP?'-slash':'');
}
</script>
<script src="../../assets/js/main.js"></script>