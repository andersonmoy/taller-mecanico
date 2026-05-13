<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

if (isset($_GET['eliminar']) && esRol('administrador')) {
    dbQuery("DELETE FROM clientes WHERE id=?", [(int)$_GET['eliminar']]);
    header('Location: index.php?msg=eliminado'); exit;
}

$buscar = trim($_GET['buscar'] ?? '');
$tipo   = $_GET['tipo'] ?? '';
$sql    = "SELECT c.*, COUNT(v.id) as total_vehiculos FROM clientes c LEFT JOIN vehiculos v ON c.id=v.cliente_id WHERE 1=1";
$params = [];
if ($buscar) { $sql.=" AND (c.nombre LIKE ? OR c.dni_ruc LIKE ? OR c.telefono LIKE ?)"; $params[]= "%$buscar%"; $params[]="%$buscar%"; $params[]="%$buscar%"; }
if ($tipo)   { $sql.=" AND c.tipo=?"; $params[]=$tipo; }
$sql .= " GROUP BY c.id ORDER BY c.nombre ASC";
$clientes = dbQuery($sql,$params) ?: [];
$msg = $_GET['msg'] ?? '';

$PAGE_TITLE     = 'Clientes y Vehículos';
$PAGE_ICON      = 'fa-users';
$ACTIVE_MENU    = 'clientes';
$TOPBAR_ACTIONS = '<a href="crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo Cliente</a>';
require_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/clientes.css">

<?php if ($msg==='creado'): ?><div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Cliente registrado correctamente.</div><?php endif; ?>
<?php if ($msg==='editado'): ?><div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Cliente actualizado.</div><?php endif; ?>
<?php if ($msg==='eliminado'): ?><div class="alert alert-error alert-auto"><i class="fas fa-trash"></i> Cliente eliminado.</div><?php endif; ?>

<form method="GET" action="index.php">
  <div class="filtros-bar">
    <div class="search-wrapper">
      <i class="fas fa-search"></i>
      <input type="text" name="buscar" class="search-input" placeholder="Buscar por nombre, DNI/RUC o teléfono..." value="<?= htmlspecialchars($buscar) ?>">
    </div>
    <select name="tipo" class="form-control" style="width:170px" onchange="this.form.submit()">
      <option value="">Todos los tipos</option>
      <option value="natural" <?= $tipo==='natural'?'selected':'' ?>>Persona Natural</option>
      <option value="empresa" <?= $tipo==='empresa'?'selected':'' ?>>Empresa</option>
    </select>
    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
    <?php if ($buscar||$tipo): ?><a href="index.php" class="btn btn-outline"><i class="fas fa-times"></i> Limpiar</a><?php endif; ?>
  </div>
</form>

<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-users"></i> Lista de Clientes <span class="count-badge"><?= count($clientes) ?> cliente<?= count($clientes)!==1?'s':'' ?></span></h3>
  </div>
  <?php if (empty($clientes)): ?>
    <div class="empty"><i class="fas fa-users"></i><p>No se encontraron clientes</p><a href="crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Registrar primer cliente</a></div>
  <?php else: ?>
  <div class="tabla-wrapper">
    <table>
      <thead><tr><th>Cliente</th><th>DNI/RUC</th><th>Teléfono</th><th>Correo</th><th>Tipo</th><th>Vehículos</th><th>Acciones</th></tr></thead>
      <tbody>
        <?php foreach ($clientes as $c): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="cliente-avatar <?= $c['tipo']==='empresa'?'avatar-empresa':'avatar-natural' ?>"><?= strtoupper(substr($c['nombre'],0,1)) ?></div>
              <div class="cliente-nombre"><?= htmlspecialchars($c['nombre']) ?></div>
            </div>
          </td>
          <td><code><?= htmlspecialchars($c['dni_ruc']) ?></code></td>
          <td><?= htmlspecialchars($c['telefono']??'—') ?></td>
          <td style="font-size:12px"><?= htmlspecialchars($c['correo']??'—') ?></td>
          <td><span class="badge <?= $c['tipo']==='empresa'?'badge-empresa':'badge-natural' ?>"><?= $c['tipo']==='empresa'?'Empresa':'Natural' ?></span></td>
          <td><a href="vehiculos.php?cliente_id=<?= $c['id'] ?>" style="font-weight:700;color:var(--azul-acento)"><i class="fas fa-car"></i> <?= $c['total_vehiculos'] ?></a></td>
          <td>
            <div class="acciones">
              <a href="vehiculos.php?cliente_id=<?= $c['id'] ?>" class="btn-accion ver" title="Vehículos"><i class="fas fa-car"></i></a>
              <a href="editar.php?id=<?= $c['id'] ?>" class="btn-accion editar" title="Editar"><i class="fas fa-pen"></i></a>
              <?php if (esRol('administrador')): ?>
              <a href="index.php?eliminar=<?= $c['id'] ?>" class="btn-accion eliminar" title="Eliminar" onclick="return confirm('¿Eliminar a <?= htmlspecialchars(addslashes($c['nombre'])) ?>?')"><i class="fas fa-trash"></i></a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>
<script src="../../assets/js/main.js"></script>
<script>document.querySelectorAll('.alert-auto').forEach(el=>{setTimeout(()=>{el.style.opacity='0';el.style.transition='opacity .5s';setTimeout(()=>el.remove(),500)},4000)});</script>
</body></html>