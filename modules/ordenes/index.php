<?php
// ============================================================
//  modules/ordenes/index.php — Lista de Órdenes de Trabajo
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }

$rol    = $_SESSION['usuario_rol'];
$nombre = $_SESSION['usuario_nombre'];

// ── Filtros ──
$estado  = $_GET['estado']  ?? '';
$buscar  = trim($_GET['buscar'] ?? '');

$sql = "SELECT ot.*,
               CONCAT(cl.nombre) AS cliente,
               v.placa, v.marca, v.modelo,
               CONCAT(u.nombre,' ',u.apellido) AS mecanico
        FROM ordenes_trabajo ot
        LEFT JOIN clientes cl  ON ot.cliente_id  = cl.id
        LEFT JOIN vehiculos v  ON ot.vehiculo_id = v.id
        LEFT JOIN usuarios  u  ON ot.mecanico_id = u.id
        WHERE 1=1";
$params = [];

if ($estado) { $sql .= " AND ot.estado = ?"; $params[] = $estado; }
if ($buscar) {
    $sql .= " AND (ot.numero LIKE ? OR cl.nombre LIKE ? OR v.placa LIKE ?)";
    $params[] = "%$buscar%"; $params[] = "%$buscar%"; $params[] = "%$buscar%";
}
$sql .= " ORDER BY ot.fecha_ingreso DESC";
$ordenes = dbQuery($sql, $params) ?: [];

// Conteo por estado para las tarjetas
$conteos = [];
$rows = dbQuery("SELECT estado, COUNT(*) AS n FROM ordenes_trabajo GROUP BY estado") ?: [];
foreach ($rows as $r) $conteos[$r['estado']] = $r['n'];

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Órdenes de Trabajo — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/ordenes.css">
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
    <a href="index.php"                class="menu-item active"><i class="fas fa-clipboard-list"></i> Órdenes de Trabajo</a>
    <a href="../clientes/index.php"    class="menu-item"><i class="fas fa-users"></i> Clientes y Vehículos</a>
    <a href="../comprobantes/index.php"class="menu-item"><i class="fas fa-file-invoice"></i> Boletas y Facturas</a>
    <div class="menu-section">Almacén</div>
    <a href="../inventario/index.php"  class="menu-item"><i class="fas fa-boxes-stacked"></i> Inventario</a>
    <a href="../precios/index.php"     class="menu-item"><i class="fas fa-tags"></i> Precios y Servicios</a>
    <?php if ($rol === 'administrador'): ?>
    <div class="menu-section">Administración</div>
    <a href="../reportes/index.php"    class="menu-item"><i class="fas fa-chart-bar"></i> Reportes</a>
    <?php endif; ?>
  </nav>
  <div class="sidebar-user">
    <div class="user-avatar"><?= strtoupper(substr($nombre, 0, 1)) ?></div>
    <div class="user-info">
      <strong><?= htmlspecialchars($nombre) ?></strong>
      <span><?= $rol ?></span>
    </div>
    <a href="../../logout.php" class="btn-logout"><i class="fas fa-right-from-bracket"></i></a>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <h1><i class="fas fa-clipboard-list"></i> Órdenes de Trabajo</h1>
    <div class="topbar-right">
      <span class="topbar-date"><i class="fas fa-calendar-day"></i> <?= date('d/m/Y') ?></span>
      <a href="crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nueva Orden</a>
    </div>
  </header>

  <div class="content">

    <?php if ($msg === 'creada'): ?><div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Orden creada correctamente.</div><?php endif; ?>
    <?php if ($msg === 'anulada'): ?><div class="alert alert-error alert-auto"><i class="fas fa-ban"></i> Orden anulada.</div><?php endif; ?>

    <!-- Tarjetas por estado -->
    <div class="ord-resumen">
      <a href="index.php" class="ord-card">
        <div class="ord-card-icon bg-naranja"><i class="fas fa-list"></i></div>
        <div><span class="ord-card-num"><?= array_sum($conteos) ?></span><span class="ord-card-label">Total</span></div>
      </a>
      <a href="index.php?estado=abierta" class="ord-card">
        <div class="ord-card-icon bg-abierta"><i class="fas fa-folder-open"></i></div>
        <div><span class="ord-card-num"><?= $conteos['abierta'] ?? 0 ?></span><span class="ord-card-label">Abiertas</span></div>
      </a>
      <a href="index.php?estado=en_proceso" class="ord-card">
        <div class="ord-card-icon bg-proceso"><i class="fas fa-gears"></i></div>
        <div><span class="ord-card-num"><?= $conteos['en_proceso'] ?? 0 ?></span><span class="ord-card-label">En proceso</span></div>
      </a>
      <a href="index.php?estado=lista" class="ord-card">
        <div class="ord-card-icon bg-lista"><i class="fas fa-check-circle"></i></div>
        <div><span class="ord-card-num"><?= $conteos['lista'] ?? 0 ?></span><span class="ord-card-label">Listas</span></div>
      </a>
      <a href="index.php?estado=cobrada" class="ord-card">
        <div class="ord-card-icon bg-cobrada"><i class="fas fa-circle-dollar-to-slot"></i></div>
        <div><span class="ord-card-num"><?= $conteos['cobrada'] ?? 0 ?></span><span class="ord-card-label">Cobradas</span></div>
      </a>
    </div>

    <!-- Tabs de estado -->
    <div class="estado-tabs">
      <a href="index.php" class="estado-tab <?= !$estado ? 'active' : '' ?>">
        <i class="fas fa-list"></i> Todas
      </a>
      <a href="index.php?estado=abierta" class="estado-tab <?= $estado==='abierta' ? 'active' : '' ?>">
        <i class="fas fa-folder-open"></i> Abiertas
      </a>
      <a href="index.php?estado=en_proceso" class="estado-tab <?= $estado==='en_proceso' ? 'active-amarillo active' : '' ?>">
        <i class="fas fa-gears"></i> En proceso
      </a>
      <a href="index.php?estado=lista" class="estado-tab <?= $estado==='lista' ? 'active-verde active' : '' ?>">
        <i class="fas fa-check-circle"></i> Listas
      </a>
      <a href="index.php?estado=cobrada" class="estado-tab <?= $estado==='cobrada' ? 'active-morado active' : '' ?>">
        <i class="fas fa-circle-dollar-to-slot"></i> Cobradas
      </a>
      <a href="index.php?estado=anulada" class="estado-tab <?= $estado==='anulada' ? 'active' : '' ?>">
        <i class="fas fa-ban"></i> Anuladas
      </a>
    </div>

    <!-- Búsqueda -->
    <form method="GET" action="index.php">
      <?php if ($estado): ?><input type="hidden" name="estado" value="<?= $estado ?>"> <?php endif; ?>
      <div class="filtros-bar" style="margin-bottom:16px">
        <div class="search-wrapper">
          <i class="fas fa-search"></i>
          <input type="text" name="buscar" class="search-input"
                 placeholder="Buscar por N° orden, cliente o placa..."
                 value="<?= htmlspecialchars($buscar) ?>">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
        <?php if ($buscar): ?><a href="index.php<?= $estado ? '?estado='.$estado : '' ?>" class="btn btn-outline"><i class="fas fa-times"></i> Limpiar</a><?php endif; ?>
      </div>
    </form>

    <!-- Tabla -->
    <div class="card">
      <div class="card-header">
        <h3><i class="fas fa-clipboard-list"></i> Órdenes
          <span class="count-badge"><?= count($ordenes) ?> orden<?= count($ordenes)!==1?'es':'' ?></span>
        </h3>
      </div>

      <?php if (empty($ordenes)): ?>
        <div class="empty">
          <i class="fas fa-clipboard-list"></i>
          <p>No hay órdenes<?= $estado ? ' con estado "'.ucfirst(str_replace('_',' ',$estado)).'"' : '' ?></p>
          <a href="crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Crear primera orden</a>
        </div>
      <?php else: ?>
      <div class="tabla-wrapper">
        <table>
          <thead>
            <tr>
              <th>N° Orden</th>
              <th>Cliente</th>
              <th>Vehículo</th>
              <th>Mecánico</th>
              <th>Ingreso</th>
              <th>Est. entrega</th>
              <th>Estado</th>
              <th>Total</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($ordenes as $o): ?>
            <tr>
              <td><span class="ord-numero"><?= htmlspecialchars($o['numero']) ?></span></td>
              <td><?= htmlspecialchars($o['cliente']) ?></td>
              <td>
                <div class="veh-info">
                  <span class="veh-placa"><?= htmlspecialchars($o['placa']) ?></span>
                  <span class="veh-modelo"><?= htmlspecialchars($o['marca'].' '.$o['modelo']) ?></span>
                </div>
              </td>
              <td style="font-size:13px"><?= htmlspecialchars($o['mecanico'] ?? '—') ?></td>
              <td style="font-size:12px;color:var(--texto-muted);white-space:nowrap">
                <?= date('d/m/Y', strtotime($o['fecha_ingreso'])) ?>
              </td>
              <td style="font-size:12px;white-space:nowrap">
                <?= $o['fecha_estimada'] ? date('d/m/Y', strtotime($o['fecha_estimada'])) : '—' ?>
              </td>
              <td>
                <?php
                $badges = [
                  'abierta'   => ['badge-abierta',  'Abierta'],
                  'en_proceso'=> ['badge-proceso',   'En proceso'],
                  'lista'     => ['badge-lista',     'Lista'],
                  'cobrada'   => ['badge-cobrada',   'Cobrada'],
                  'anulada'   => ['badge-anulada',   'Anulada'],
                ];
                [$cls, $lbl] = $badges[$o['estado']] ?? ['badge-anulada','Desconocido'];
                ?>
                <span class="badge <?= $cls ?>"><?= $lbl ?></span>
              </td>
              <td class="td-precio">S/ <?= number_format($o['total'], 2) ?></td>
              <td>
                <div class="acciones">
                  <a href="ver.php?id=<?= $o['id'] ?>" class="btn-accion ver" title="Ver detalle">
                    <i class="fas fa-eye"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script src="../../assets/js/main.js"></script>
<script>
document.querySelectorAll('.alert-auto').forEach(el => {
  setTimeout(() => { el.style.opacity='0'; el.style.transition='opacity .5s';
    setTimeout(()=>el.remove(),500); }, 4000);
});
</script>
</body>
</html>