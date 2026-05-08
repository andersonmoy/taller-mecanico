<?php
// ============================================================
//  modules/clientes/index.php — Lista de Clientes
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id'])) { header('Location: ../../index.php'); exit; }

$rol    = $_SESSION['usuario_rol'];
$nombre = $_SESSION['usuario_nombre'];

// ── Eliminar cliente ──
if (isset($_GET['eliminar']) && $rol === 'administrador') {
    $id = (int)$_GET['eliminar'];
    dbQuery("DELETE FROM clientes WHERE id = ?", [$id]);
    header('Location: index.php?msg=eliminado');
    exit;
}

// ── Búsqueda y filtro ──
$buscar = trim($_GET['buscar'] ?? '');
$tipo   = $_GET['tipo'] ?? '';

$sql    = "SELECT c.*, COUNT(v.id) as total_vehiculos FROM clientes c LEFT JOIN vehiculos v ON c.id = v.cliente_id WHERE 1=1";
$params = [];

if ($buscar) {
    $sql .= " AND (c.nombre LIKE ? OR c.dni_ruc LIKE ? OR c.telefono LIKE ?)";
    $params[] = "%$buscar%"; $params[] = "%$buscar%"; $params[] = "%$buscar%";
}
if ($tipo) {
    $sql .= " AND c.tipo = ?";
    $params[] = $tipo;
}

$sql .= " GROUP BY c.id ORDER BY c.nombre ASC";
$clientes = dbQuery($sql, $params) ?: [];

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Clientes — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/style.css">
  <link rel="stylesheet" href="../../assets/css/clientes.css">
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="icon"><i class="fas fa-wrench"></i></div>
    <div><h2><?= APP_NAME ?></h2><span>v<?= APP_VERSION ?></span></div>
  </div>
  <nav class="sidebar-menu">
    <div class="menu-section">Principal</div>
    <a href="../../dashboard.php" class="menu-item"><i class="fas fa-gauge-high"></i> Dashboard</a>
    <div class="menu-section">Operaciones</div>
    <a href="../ordenes/index.php" class="menu-item"><i class="fas fa-clipboard-list"></i> Órdenes de Trabajo</a>
    <a href="index.php" class="menu-item active"><i class="fas fa-users"></i> Clientes y Vehículos</a>
    <a href="../comprobantes/index.php" class="menu-item"><i class="fas fa-file-invoice"></i> Boletas y Facturas</a>
    <div class="menu-section">Almacén</div>
    <a href="../inventario/index.php" class="menu-item"><i class="fas fa-boxes-stacked"></i> Inventario</a>
    <a href="../precios/index.php" class="menu-item"><i class="fas fa-tags"></i> Precios y Servicios</a>
    <?php if ($rol === 'administrador'): ?>
    <div class="menu-section">Administración</div>
    <a href="../reportes/index.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reportes</a>
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

<!-- CONTENIDO -->
<div class="main">
  <header class="topbar">
    <h1><i class="fas fa-users"></i> Clientes y Vehículos</h1>
    <div class="topbar-right">
      <span class="topbar-date"><i class="fas fa-calendar-day"></i> <?= date('d/m/Y') ?></span>
      <a href="crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nuevo Cliente</a>
    </div>
  </header>

  <div class="content">

    <?php if ($msg === 'creado'): ?>
      <div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Cliente registrado correctamente.</div>
    <?php elseif ($msg === 'editado'): ?>
      <div class="alert alert-success alert-auto"><i class="fas fa-check-circle"></i> Cliente actualizado correctamente.</div>
    <?php elseif ($msg === 'eliminado'): ?>
      <div class="alert alert-error alert-auto"><i class="fas fa-trash"></i> Cliente eliminado.</div>
    <?php endif; ?>

    <!-- Filtros -->
    <form method="GET" action="index.php">
      <div class="filtros-bar">
        <div class="search-wrapper">
          <i class="fas fa-search"></i>
          <input type="text" name="buscar" class="search-input"
                 placeholder="Buscar por nombre, DNI/RUC o teléfono..."
                 value="<?= htmlspecialchars($buscar) ?>">
        </div>
        <select name="tipo" class="form-control" style="width:160px" onchange="this.form.submit()">
          <option value="">Todos los tipos</option>
          <option value="natural"  <?= $tipo==='natural'  ? 'selected':'' ?>>Persona Natural</option>
          <option value="empresa"  <?= $tipo==='empresa'  ? 'selected':'' ?>>Empresa</option>
        </select>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
        <?php if ($buscar || $tipo): ?>
          <a href="index.php" class="btn btn-outline"><i class="fas fa-times"></i> Limpiar</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Tabla -->
    <div class="card">
      <div class="card-header">
        <h3><i class="fas fa-users"></i> Lista de Clientes
          <span style="font-size:13px;font-weight:400;color:var(--texto-muted);margin-left:8px">
            (<?= count($clientes) ?> <?= count($clientes) === 1 ? 'cliente' : 'clientes' ?>)
          </span>
        </h3>
      </div>

      <?php if (empty($clientes)): ?>
        <div class="empty">
          <i class="fas fa-users"></i>
          <p>No se encontraron clientes</p>
          <a href="crear.php" class="btn btn-primary"><i class="fas fa-plus"></i> Registrar primer cliente</a>
        </div>
      <?php else: ?>
      <div class="tabla-wrapper">
        <table>
          <thead>
            <tr>
              <th>Cliente</th>
              <th>DNI / RUC</th>
              <th>Teléfono</th>
              <th>Correo</th>
              <th>Tipo</th>
              <th>Vehículos</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($clientes as $c): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <div class="cliente-avatar <?= $c['tipo']==='empresa' ? 'avatar-empresa' : 'avatar-natural' ?>">
                    <?= strtoupper(substr($c['nombre'], 0, 1)) ?>
                  </div>
                  <div>
                    <div class="cliente-nombre"><?= htmlspecialchars($c['nombre']) ?></div>
                  </div>
                </div>
              </td>
              <td><code><?= htmlspecialchars($c['dni_ruc']) ?></code></td>
              <td><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
              <td style="font-size:12px"><?= htmlspecialchars($c['correo'] ?? '—') ?></td>
              <td>
                <span class="badge <?= $c['tipo']==='empresa' ? 'badge-empresa' : 'badge-natural' ?>">
                  <?= $c['tipo'] === 'empresa' ? 'Empresa' : 'Natural' ?>
                </span>
              </td>
              <td>
                <a href="vehiculos.php?cliente_id=<?= $c['id'] ?>" style="font-weight:700;color:var(--azul-acento)">
                  <i class="fas fa-car"></i> <?= $c['total_vehiculos'] ?>
                </a>
              </td>
              <td>
                <div class="acciones">
                  <a href="vehiculos.php?cliente_id=<?= $c['id'] ?>" class="btn-accion ver" title="Ver vehículos">
                    <i class="fas fa-car"></i>
                  </a>
                  <a href="editar.php?id=<?= $c['id'] ?>" class="btn-accion editar" title="Editar">
                    <i class="fas fa-pen"></i>
                  </a>
                  <?php if ($rol === 'administrador'): ?>
                  <a href="index.php?eliminar=<?= $c['id'] ?>"
                     class="btn-accion eliminar" title="Eliminar"
                     onclick="return confirm('¿Eliminar a <?= htmlspecialchars(addslashes($c['nombre'])) ?>? Se eliminarán también sus vehículos.')">
                    <i class="fas fa-trash"></i>
                  </a>
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

  </div>
</div>

<script src="../../assets/js/main.js"></script>
</body>
</html>