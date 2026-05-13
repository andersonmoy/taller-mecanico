<?php
// ============================================================
//  modules/usuarios/index.php — Gestión de Usuarios
// ============================================================
require_once '../../includes/auth.php';
require_once '../../config/database.php';
soloAdmin();

// Activar / desactivar usuario
if (isset($_GET['toggle']) && (int)$_GET['toggle'] !== $SESSION_ID) {
    $uid = (int)$_GET['toggle'];
    $u   = dbQuery("SELECT activo FROM usuarios WHERE id=?", [$uid]);
    if ($u) {
        $nuevo = $u[0]['activo'] ? 0 : 1;
        dbQuery("UPDATE usuarios SET activo=? WHERE id=?", [$nuevo, $uid]);
        header('Location: index.php?msg='.($nuevo?'activado':'desactivado')); exit;
    }
}

$usuarios = dbQuery("
    SELECT u.*,
           COUNT(ot.id) AS total_ordenes
    FROM usuarios u
    LEFT JOIN ordenes_trabajo ot ON ot.mecanico_id = u.id
    GROUP BY u.id
    ORDER BY u.rol, u.nombre") ?: [];

$msg = $_GET['msg'] ?? '';

$PAGE_TITLE  = 'Gestión de Usuarios';
$PAGE_ICON   = 'fa-user-gear';
$ACTIVE_MENU = 'usuarios';
$TOPBAR_ACTIONS = '<a href="crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo Usuario</a>';
require_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/usuarios.css">

<?php if ($msg === 'creado'):    ?><div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Usuario creado correctamente.</div><?php endif; ?>
<?php if ($msg === 'editado'):   ?><div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Usuario actualizado.</div><?php endif; ?>
<?php if ($msg === 'activado'):  ?><div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Usuario activado.</div><?php endif; ?>
<?php if ($msg === 'desactivado'): ?><div class="alert alert-warning alert-auto"><i class="fas fa-ban"></i> Usuario desactivado.</div><?php endif; ?>

<!-- Tarjetas resumen -->
<div class="usu-resumen">
  <?php
  $roles = ['administrador'=>['bg-azul','fa-shield-halved'],
            'cajero'       =>['bg-amarillo','fa-cash-register'],
            'mecanico'     =>['bg-verde','fa-screwdriver-wrench']];
  foreach ($roles as $r => [$cls,$ico]):
    $n = count(array_filter($usuarios, fn($u)=>$u['rol']===$r && $u['activo']));
  ?>
  <div class="inv-card">
    <div class="inv-card-icon <?= $cls ?>"><i class="fas <?= $ico ?>"></i></div>
    <div class="inv-card-info">
      <span class="inv-card-num"><?= $n ?></span>
      <span class="inv-card-label"><?= ucfirst($r).'s' ?></span>
    </div>
  </div>
  <?php endforeach; ?>
  <div class="inv-card">
    <div class="inv-card-icon bg-rojo"><i class="fas fa-ban"></i></div>
    <div class="inv-card-info">
      <span class="inv-card-num"><?= count(array_filter($usuarios, fn($u)=>!$u['activo'])) ?></span>
      <span class="inv-card-label">Desactivados</span>
    </div>
  </div>
</div>

<!-- Tabla -->
<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-users"></i> Usuarios del Sistema
      <span class="count-badge"><?= count($usuarios) ?> usuario<?= count($usuarios)!==1?'s':'' ?></span>
    </h3>
  </div>
  <div class="tabla-wrapper">
    <table>
      <thead>
        <tr>
          <th>Usuario</th>
          <th>Correo</th>
          <th>Rol</th>
          <th>Órdenes atendidas</th>
          <th>Estado</th>
          <th>Registrado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($usuarios as $u):
          $es_yo = $u['id'] == $SESSION_ID;
          $roles_badge = [
            'administrador' => 'badge-cobrada',
            'cajero'        => 'badge-proceso',
            'mecanico'      => 'badge-lista',
          ];
        ?>
        <tr class="<?= !$u['activo'] ? 'usu-inactivo' : '' ?>">
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="usu-avatar"><?= strtoupper(substr($u['nombre'],0,1)) ?></div>
              <div>
                <div style="font-weight:600;font-size:14px">
                  <?= htmlspecialchars($u['nombre'].' '.$u['apellido']) ?>
                  <?php if ($es_yo): ?>
                    <span class="badge badge-lista" style="font-size:10px;margin-left:6px">Tú</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </td>
          <td style="font-size:13px;color:var(--texto-muted)"><?= htmlspecialchars($u['correo']) ?></td>
          <td>
            <span class="badge <?= $roles_badge[$u['rol']] ?? '' ?>">
              <?= ucfirst($u['rol']) ?>
            </span>
          </td>
          <td style="text-align:center">
            <strong><?= $u['total_ordenes'] ?></strong>
          </td>
          <td>
            <span class="badge <?= $u['activo'] ? 'badge-activo' : 'badge-inactivo' ?>">
              <i class="fas <?= $u['activo'] ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
              <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
            </span>
          </td>
          <td style="font-size:12px;color:var(--texto-muted)">
            <?= date('d/m/Y', strtotime($u['created_at'])) ?>
          </td>
          <td>
            <div class="acciones">
              <a href="editar.php?id=<?= $u['id'] ?>" class="btn-accion editar" title="Editar">
                <i class="fas fa-pen"></i>
              </a>
              <?php if (!$es_yo): ?>
              <a href="index.php?toggle=<?= $u['id'] ?>"
                 class="btn-accion <?= $u['activo'] ? 'eliminar' : 'ver' ?>"
                 title="<?= $u['activo'] ? 'Desactivar' : 'Activar' ?>"
                 onclick="return confirm('¿<?= $u['activo']?'Desactivar':'Activar' ?> a <?= htmlspecialchars(addslashes($u['nombre'])) ?>?')">
                <i class="fas <?= $u['activo'] ? 'fa-ban' : 'fa-circle-check' ?>"></i>
              </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

  </div></div>
<script src="../../assets/js/main.js"></script>
<script>
document.querySelectorAll('.alert-auto').forEach(el => {
  setTimeout(()=>{el.style.opacity='0';el.style.transition='opacity .5s';setTimeout(()=>el.remove(),500);},4000);
});
</script>