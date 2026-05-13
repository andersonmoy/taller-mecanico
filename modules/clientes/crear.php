<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom=trim($_POST['nombre']??''); $dni=trim($_POST['dni_ruc']??'');
    $tel=trim($_POST['telefono']??''); $cor=trim($_POST['correo']??'');
    $dir=trim($_POST['direccion']??''); $tipo=$_POST['tipo']??'natural';
    if (!$nom||!$dni) { $error='El nombre y DNI/RUC son obligatorios.'; }
    else {
        $existe=dbQuery("SELECT id FROM clientes WHERE dni_ruc=?",[$dni]);
        if ($existe) { $error='Ya existe un cliente con ese DNI/RUC.'; }
        else { dbQuery("INSERT INTO clientes (nombre,dni_ruc,telefono,correo,direccion,tipo) VALUES (?,?,?,?,?,?)",[$nom,$dni,$tel,$cor,$dir,$tipo]); header('Location: index.php?msg=creado'); exit; }
    }
}

$PAGE_TITLE='Nuevo Cliente'; $PAGE_ICON='fa-user-plus'; $ACTIVE_MENU='clientes';
$TOPBAR_ACTIONS='<a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>';
require_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/clientes.css">

<?php if ($error): ?><div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="card" style="max-width:700px">
  <div class="card-header"><h3><i class="fas fa-user-plus"></i> Datos del Cliente</h3></div>
  <div class="card-body">
    <form method="POST" action="crear.php">
      <p class="form-seccion">Tipo de cliente</p>
      <div class="tipo-cliente-grid">
        <label class="tipo-btn <?= ($_POST['tipo']??'natural')==='natural'?'active':'' ?>">
          <input type="radio" name="tipo" value="natural" <?= ($_POST['tipo']??'natural')==='natural'?'checked':'' ?> onchange="this.closest('form').querySelectorAll('.tipo-btn').forEach(b=>b.classList.remove('active'));this.closest('.tipo-btn').classList.add('active')">
          <i class="fas fa-user"></i> <span>Persona Natural</span>
        </label>
        <label class="tipo-btn <?= ($_POST['tipo']??'')==='empresa'?'active':'' ?>">
          <input type="radio" name="tipo" value="empresa" <?= ($_POST['tipo']??'')==='empresa'?'checked':'' ?> onchange="this.closest('form').querySelectorAll('.tipo-btn').forEach(b=>b.classList.remove('active'));this.closest('.tipo-btn').classList.add('active')">
          <i class="fas fa-building"></i> <span>Empresa</span>
        </label>
      </div>
      <p class="form-seccion">Información</p>
      <div class="form-grid">
        <div class="form-group" style="grid-column:1/-1">
          <label>Nombre / Razón Social *</label>
          <input type="text" name="nombre" class="form-control" placeholder="Nombre completo o razón social" value="<?= htmlspecialchars($_POST['nombre']??'') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label>DNI / RUC *</label>
          <input type="text" name="dni_ruc" class="form-control" placeholder="12345678 / 20123456789" value="<?= htmlspecialchars($_POST['dni_ruc']??'') ?>" required>
        </div>
        <div class="form-group">
          <label>Teléfono</label>
          <input type="text" name="telefono" class="form-control" placeholder="987654321" value="<?= htmlspecialchars($_POST['telefono']??'') ?>">
        </div>
        <div class="form-group">
          <label>Correo electrónico</label>
          <input type="email" name="correo" class="form-control" placeholder="correo@ejemplo.com" value="<?= htmlspecialchars($_POST['correo']??'') ?>">
        </div>
        <div class="form-group">
          <label>Dirección</label>
          <input type="text" name="direccion" class="form-control" placeholder="Jr. Ejemplo 123, Cusco" value="<?= htmlspecialchars($_POST['direccion']??'') ?>">
        </div>
      </div>
      <div style="display:flex;gap:12px;margin-top:8px">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cliente</button>
        <a href="index.php" class="btn btn-outline">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
<script src="../../assets/js/main.js"></script>
</body></html>