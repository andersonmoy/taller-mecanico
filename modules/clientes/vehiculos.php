<?php
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$cliente_id=(int)($_GET['cliente_id']??0);
if(!$cliente_id){header('Location: index.php');exit;}
$cli=dbQuery("SELECT * FROM clientes WHERE id=?",[$cliente_id]);
if(!$cli){header('Location: index.php');exit;}
$cliente=$cli[0];
$error=''; $msg=$_GET['msg']??'';

if(isset($_GET['eliminar_v'])){
    dbQuery("DELETE FROM vehiculos WHERE id=? AND cliente_id=?",[(int)$_GET['eliminar_v'],$cliente_id]);
    header("Location: vehiculos.php?cliente_id=$cliente_id&msg=v_eliminado"); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $placa=strtoupper(trim($_POST['placa']??'')); $marca=trim($_POST['marca']??'');
    $modelo=trim($_POST['modelo']??''); $anio=(int)($_POST['anio']??date('Y'));
    $color=trim($_POST['color']??''); $km=(int)($_POST['km_actual']??0);
    $obs=trim($_POST['observaciones']??'');
    if(!$placa||!$marca||!$modelo){$error='Placa, marca y modelo son obligatorios.';}
    else{
        $existe=dbQuery("SELECT id FROM vehiculos WHERE placa=?",[$placa]);
        if($existe){$error="La placa $placa ya está registrada.";}
        else{
            dbQuery("INSERT INTO vehiculos (cliente_id,placa,marca,modelo,anio,color,km_actual,observaciones) VALUES (?,?,?,?,?,?,?,?)",
                [$cliente_id,$placa,$marca,$modelo,$anio,$color,$km,$obs]);
            header("Location: vehiculos.php?cliente_id=$cliente_id&msg=v_creado"); exit;
        }
    }
}
$vehiculos=dbQuery("SELECT * FROM vehiculos WHERE cliente_id=? ORDER BY created_at DESC",[$cliente_id])?: [];
$total_ordenes=dbQuery("SELECT COUNT(*) as t FROM ordenes_trabajo WHERE cliente_id=?",[$cliente_id])[0]['t']??0;
$total_gastado=dbQuery("SELECT COALESCE(SUM(total),0) as t FROM ordenes_trabajo WHERE cliente_id=? AND estado='cobrada'",[$cliente_id])[0]['t']??0;

$PAGE_TITLE='Vehículos — '.htmlspecialchars($cliente['nombre']);
$PAGE_ICON='fa-car'; $ACTIVE_MENU='clientes';
$TOPBAR_ACTIONS='<a href="editar.php?id='.$cliente_id.'" class="btn btn-outline"><i class="fas fa-pen"></i> Editar Cliente</a><a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>';
require_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/clientes.css">

<?php if($msg==='v_creado'): ?><div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Vehículo registrado.</div><?php endif; ?>
<?php if($msg==='v_eliminado'): ?><div class="alert alert-error alert-auto"><i class="fas fa-trash"></i> Vehículo eliminado.</div><?php endif; ?>

<!-- Info del cliente -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px">
      <div style="display:flex;align-items:center;gap:14px">
        <div class="cliente-avatar <?= $cliente['tipo']==='empresa'?'avatar-empresa':'avatar-natural' ?>" style="width:52px;height:52px;font-size:22px">
          <?= strtoupper(substr($cliente['nombre'],0,1)) ?>
        </div>
        <div>
          <h2 style="font-family:var(--font-title);font-size:20px;font-weight:700"><?= htmlspecialchars($cliente['nombre']) ?></h2>
          <span style="font-size:13px;color:var(--texto-muted)">
            <i class="fas fa-id-card"></i> <?= htmlspecialchars($cliente['dni_ruc']) ?>
            &nbsp;|&nbsp;<i class="fas fa-phone"></i> <?= htmlspecialchars($cliente['telefono']??'—') ?>
          </span>
        </div>
      </div>
      <div style="display:flex;gap:20px">
        <div style="text-align:center">
          <div style="font-family:var(--font-title);font-size:24px;font-weight:700;color:var(--azul-acento)"><?= count($vehiculos) ?></div>
          <div style="font-size:12px;color:var(--texto-muted)">Vehículos</div>
        </div>
        <div style="text-align:center">
          <div style="font-family:var(--font-title);font-size:24px;font-weight:700;color:var(--naranja)"><?= $total_ordenes ?></div>
          <div style="font-size:12px;color:var(--texto-muted)">Órdenes</div>
        </div>
        <div style="text-align:center">
          <div style="font-family:var(--font-title);font-size:24px;font-weight:700;color:var(--verde)">S/ <?= number_format($total_gastado,0) ?></div>
          <div style="font-size:12px;color:var(--texto-muted)">Total gastado</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start">

  <!-- Lista de vehículos -->
  <div>
    <h3 style="font-size:16px;font-weight:700;margin-bottom:14px"><i class="fas fa-car" style="color:var(--azul-acento)"></i> Vehículos registrados</h3>
    <?php if(empty($vehiculos)): ?>
      <div class="card"><div class="empty"><i class="fas fa-car"></i><p>Sin vehículos registrados</p></div></div>
    <?php else: ?>
      <?php foreach($vehiculos as $v): ?>
      <div class="vehiculo-card">
        <div class="vehiculo-icon"><i class="fas fa-car"></i></div>
        <div class="vehiculo-info">
          <div class="vehiculo-placa"><?= htmlspecialchars($v['placa']) ?></div>
          <div class="vehiculo-detalle"><?= htmlspecialchars($v['marca'].' '.$v['modelo']) ?> · <?= $v['anio'] ?> · <?= htmlspecialchars($v['color']??'') ?></div>
          <div class="vehiculo-detalle" style="margin-top:4px">
            <i class="fas fa-gauge-high"></i> <?= number_format($v['km_actual']) ?> km
            <?php if($v['observaciones']): ?> · <i class="fas fa-note-sticky"></i> <?= htmlspecialchars($v['observaciones']) ?><?php endif; ?>
          </div>
        </div>
        <div class="acciones">
          <a href="../ordenes/index.php" class="btn-accion ver" title="Ver órdenes"><i class="fas fa-clipboard-list"></i></a>
          <?php if(esRol('administrador')): ?>
          <a href="vehiculos.php?cliente_id=<?= $cliente_id ?>&eliminar_v=<?= $v['id'] ?>" class="btn-accion eliminar" title="Eliminar" onclick="return confirm('¿Eliminar <?= htmlspecialchars(addslashes($v['placa'])) ?>?')"><i class="fas fa-trash"></i></a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Formulario nuevo vehículo -->
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-plus-circle"></i> Agregar Vehículo</h3></div>
    <div class="card-body">
      <?php if($error): ?><div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="POST">
        <div class="form-group"><label>Placa *</label><input type="text" name="placa" class="form-control" placeholder="ABC-123" style="text-transform:uppercase" value="<?= htmlspecialchars($_POST['placa']??'') ?>" required autofocus></div>
        <div class="form-group"><label>Marca *</label><input type="text" name="marca" class="form-control" placeholder="Toyota" value="<?= htmlspecialchars($_POST['marca']??'') ?>" required></div>
        <div class="form-group"><label>Modelo *</label><input type="text" name="modelo" class="form-control" placeholder="Corolla" value="<?= htmlspecialchars($_POST['modelo']??'') ?>" required></div>
        <div class="form-grid">
          <div class="form-group"><label>Año</label><input type="number" name="anio" class="form-control" min="1980" max="<?= date('Y')+1 ?>" value="<?= $_POST['anio']??date('Y') ?>"></div>
          <div class="form-group"><label>Color</label><input type="text" name="color" class="form-control" placeholder="Blanco" value="<?= htmlspecialchars($_POST['color']??'') ?>"></div>
        </div>
        <div class="form-group"><label>KM actual</label><input type="number" name="km_actual" class="form-control" min="0" value="<?= $_POST['km_actual']??'0' ?>"></div>
        <div class="form-group"><label>Observaciones</label><input type="text" name="observaciones" class="form-control" placeholder="Notas..." value="<?= htmlspecialchars($_POST['observaciones']??'') ?>"></div>
        <button type="submit" class="btn btn-primary" style="width:100%"><i class="fas fa-plus"></i> Registrar Vehículo</button>
      </form>
    </div>
  </div>

</div>

<?php require_once '../../includes/footer.php'; ?>
<script src="../../assets/js/main.js"></script>
<script>document.querySelectorAll('.alert-auto').forEach(el=>{setTimeout(()=>{el.style.opacity='0';el.style.transition='opacity .5s';setTimeout(()=>el.remove(),500)},4000)});</script>
</body></html>