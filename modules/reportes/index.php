<?php
// ============================================================
//  modules/reportes/index.php — Reportes y Estadísticas
//  Solo accesible para administrador
// ============================================================
session_start();
require_once '../../config/database.php';
if (!isset($_SESSION['usuario_id']))          { header('Location: ../../index.php'); exit; }
if ($_SESSION['usuario_rol'] !== 'administrador') { header('Location: ../../dashboard.php'); exit; }

$rol    = $_SESSION['usuario_rol'];
$nombre = $_SESSION['usuario_nombre'];

// ── Filtro de período ──
$anio = (int)($_GET['anio'] ?? date('Y'));
$anios_disp = [];
$anio_rows = dbQuery("SELECT DISTINCT YEAR(fecha_ingreso) AS a FROM ordenes_trabajo ORDER BY a DESC") ?: [];
foreach ($anio_rows as $r) $anios_disp[] = $r['a'];
if (empty($anios_disp)) $anios_disp = [date('Y')];

// ══════════════════════════════════
// KPIs generales
// ══════════════════════════════════
$kpi = dbQuery("
    SELECT
        COUNT(*)                                          AS total_ordenes,
        SUM(estado = 'cobrada')                          AS ordenes_cobradas,
        SUM(CASE WHEN estado='cobrada' THEN total END)   AS ingresos_total,
        COUNT(DISTINCT cliente_id)                       AS clientes_activos
    FROM ordenes_trabajo
    WHERE YEAR(fecha_ingreso) = ?", [$anio])[0] ?? [];

$total_productos = dbQuery("SELECT COUNT(*) AS n FROM productos WHERE activo=1")[0]['n'] ?? 0;
$stock_bajo      = dbQuery("SELECT COUNT(*) AS n FROM productos WHERE activo=1 AND stock_actual <= stock_minimo")[0]['n'] ?? 0;

// ══════════════════════════════════
// Ingresos por mes (para la gráfica de barras)
// ══════════════════════════════════
$ingresos_mes = [];
$rows = dbQuery("
    SELECT MONTH(fecha_ingreso) AS mes, SUM(total) AS total
    FROM ordenes_trabajo
    WHERE estado = 'cobrada' AND YEAR(fecha_ingreso) = ?
    GROUP BY mes ORDER BY mes", [$anio]) ?: [];

$meses_nombres = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$data_ingresos = array_fill(0, 12, 0);
foreach ($rows as $r) $data_ingresos[$r['mes'] - 1] = (float)$r['total'];

// ══════════════════════════════════
// Órdenes por estado (para la gráfica dona)
// ══════════════════════════════════
$estados_data = [];
$rows = dbQuery("
    SELECT estado, COUNT(*) AS n
    FROM ordenes_trabajo
    WHERE YEAR(fecha_ingreso) = ?
    GROUP BY estado", [$anio]) ?: [];
foreach ($rows as $r) $estados_data[$r['estado']] = $r['n'];

// ══════════════════════════════════
// Servicios más solicitados
// ══════════════════════════════════
$top_servicios = dbQuery("
    SELECT d.nombre_item, COUNT(*) AS veces, SUM(d.subtotal) AS total_generado
    FROM detalle_orden d
    JOIN ordenes_trabajo ot ON d.orden_id = ot.id
    WHERE d.tipo = 'servicio' AND YEAR(ot.fecha_ingreso) = ?
    GROUP BY d.nombre_item
    ORDER BY veces DESC LIMIT 8", [$anio]) ?: [];

$max_servicio = !empty($top_servicios) ? $top_servicios[0]['veces'] : 1;

// ══════════════════════════════════
// Productos más usados en órdenes
// ══════════════════════════════════
$top_productos = dbQuery("
    SELECT d.nombre_item, SUM(d.cantidad) AS total_usado, SUM(d.subtotal) AS total_generado
    FROM detalle_orden d
    JOIN ordenes_trabajo ot ON d.orden_id = ot.id
    WHERE d.tipo = 'producto' AND YEAR(ot.fecha_ingreso) = ?
    GROUP BY d.nombre_item
    ORDER BY total_usado DESC LIMIT 6", [$anio]) ?: [];

$max_producto = !empty($top_productos) ? $top_productos[0]['total_usado'] : 1;

// ══════════════════════════════════
// Rendimiento de mecánicos
// ══════════════════════════════════
$mecanicos_perf = dbQuery("
    SELECT CONCAT(u.nombre,' ',u.apellido) AS mecanico,
           COUNT(ot.id) AS ordenes,
           SUM(CASE WHEN ot.estado='cobrada' THEN ot.total ELSE 0 END) AS ingresos
    FROM usuarios u
    LEFT JOIN ordenes_trabajo ot ON ot.mecanico_id = u.id AND YEAR(ot.fecha_ingreso) = ?
    WHERE u.rol = 'mecanico' AND u.activo = 1
    GROUP BY u.id, u.nombre, u.apellido
    ORDER BY ordenes DESC", [$anio]) ?: [];

// ══════════════════════════════════
// Productos con stock bajo
// ══════════════════════════════════
$productos_bajos = dbQuery("
    SELECT p.nombre, p.stock_actual, p.stock_minimo, p.unidad_medida, c.nombre AS categoria
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.activo = 1 AND p.stock_actual <= p.stock_minimo
    ORDER BY p.stock_actual ASC LIMIT 8") ?: [];
?>

$PAGE_TITLE  = 'Reportes y Estadísticas';
$PAGE_ICON   = 'fa-chart-bar';
$ACTIVE_MENU = 'reportes';
$TOPBAR_ACTIONS = '';
require_once '../../includes/header.php';
?>
<link rel="stylesheet" href="../../assets/css/reportes.css">

    <!-- Filtro de año -->
    <div class="reporte-filtro">
      <i class="fas fa-filter" style="color:var(--azul-acento)"></i>
      <label>Período:</label>
      <form method="GET" action="index.php" style="display:flex;align-items:center;gap:10px">
        <select name="anio" class="form-control" style="width:120px" onchange="this.form.submit()">
          <?php foreach ($anios_disp as $a): ?>
          <option value="<?= $a ?>" <?= $a == $anio ? 'selected' : '' ?>><?= $a ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> Aplicar</button>
      </form>
      <span style="font-size:12px;color:var(--texto-muted);margin-left:auto">
        Mostrando datos del año <strong><?= $anio ?></strong>
      </span>

    <!-- Botones de exportar -->
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
      <a href="exportar.php?tipo=ordenes&anio=<?= $anio ?>" class="btn btn-verde">
        <i class="fas fa-file-csv"></i> Exportar Órdenes
      </a>
      <a href="exportar.php?tipo=comprobantes&anio=<?= $anio ?>" class="btn btn-verde">
        <i class="fas fa-file-csv"></i> Exportar Comprobantes
      </a>
      <a href="exportar.php?tipo=inventario" class="btn btn-verde">
        <i class="fas fa-file-csv"></i> Exportar Inventario
      </a>
      <a href="exportar.php?tipo=movimientos&anio=<?= $anio ?>" class="btn btn-verde">
        <i class="fas fa-file-csv"></i> Exportar Movimientos
      </a>
      <a href="exportar.php?tipo=clientes" class="btn btn-verde">
        <i class="fas fa-file-csv"></i> Exportar Clientes
      </a>
    </div>

    <!-- ══ KPIs ══ -->
    <div class="kpi-grid">
      <div class="kpi-card azul">
        <i class="fas fa-clipboard-list kpi-icon"></i>
        <div class="kpi-valor"><?= $kpi['total_ordenes'] ?? 0 ?></div>
        <div class="kpi-label">Órdenes totales <?= $anio ?></div>
      </div>
      <div class="kpi-card naranja">
        <i class="fas fa-sack-dollar kpi-icon"></i>
        <div class="kpi-valor">S/ <?= number_format($kpi['ingresos_total'] ?? 0, 0) ?></div>
        <div class="kpi-label">Ingresos <?= $anio ?></div>
      </div>
      <div class="kpi-card verde">
        <i class="fas fa-users kpi-icon"></i>
        <div class="kpi-valor"><?= $kpi['clientes_activos'] ?? 0 ?></div>
        <div class="kpi-label">Clientes atendidos</div>
      </div>
      <div class="kpi-card morado">
        <i class="fas fa-triangle-exclamation kpi-icon"></i>
        <div class="kpi-valor"><?= $stock_bajo ?></div>
        <div class="kpi-label">Productos con stock bajo</div>
      </div>
    </div>

    <!-- ══ Gráficas ══ -->
    <div class="reportes-grid">

      <!-- Ingresos por mes -->
      <div class="reporte-card">
        <div class="reporte-card-header">
          <h3><i class="fas fa-chart-bar"></i> Ingresos por Mes</h3>
          <span><?= $anio ?></span>
        </div>
        <div class="reporte-card-body">
          <div class="chart-wrapper">
            <canvas id="chartIngresos"></canvas>
          </div>
        </div>
      </div>

      <!-- Órdenes por estado -->
      <div class="reporte-card">
        <div class="reporte-card-header">
          <h3><i class="fas fa-chart-pie"></i> Órdenes por Estado</h3>
          <span><?= $anio ?></span>
        </div>
        <div class="reporte-card-body">
          <div class="chart-wrapper">
            <canvas id="chartEstados"></canvas>
          </div>
        </div>
      </div>

    </div>

    <!-- ══ Tablas ══ -->
    <div class="reportes-grid">

      <!-- Servicios más solicitados -->
      <div class="reporte-card">
        <div class="reporte-card-header">
          <h3><i class="fas fa-screwdriver-wrench"></i> Servicios más Solicitados</h3>
          <span>Top <?= count($top_servicios) ?></span>
        </div>
        <div class="reporte-card-body" style="padding:0">
          <?php if (empty($top_servicios)): ?>
            <div class="empty"><i class="fas fa-chart-bar"></i><p>Sin datos para este período</p></div>
          <?php else: ?>
          <div class="reporte-tabla">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Servicio</th>
                  <th>Veces</th>
                  <th>Generado</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($top_servicios as $i => $s):
                  $pct = round(($s['veces'] / $max_servicio) * 100);
                  $rankClass = $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : ''));
                ?>
                <tr>
                  <td><span class="rank-num <?= $rankClass ?>"><?= $i+1 ?></span></td>
                  <td style="max-width:180px">
                    <div style="font-weight:600;font-size:13px;color:var(--texto)">
                      <?= htmlspecialchars($s['nombre_item']) ?>
                    </div>
                    <div class="prog-wrap" style="margin-top:5px">
                      <div class="prog-bar">
                        <div class="prog-fill azul" style="width:<?= $pct ?>%"></div>
                      </div>
                    </div>
                  </td>
                  <td><strong><?= $s['veces'] ?></strong> veces</td>
                  <td style="color:var(--verde);font-weight:600">S/ <?= number_format($s['total_generado'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Productos más usados -->
      <div class="reporte-card">
        <div class="reporte-card-header">
          <h3><i class="fas fa-box"></i> Productos más Usados</h3>
          <span>Top <?= count($top_productos) ?></span>
        </div>
        <div class="reporte-card-body" style="padding:0">
          <?php if (empty($top_productos)): ?>
            <div class="empty"><i class="fas fa-chart-bar"></i><p>Sin datos para este período</p></div>
          <?php else: ?>
          <div class="reporte-tabla">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Producto</th>
                  <th>Cantidad</th>
                  <th>Generado</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($top_productos as $i => $p):
                  $pct = round(($p['total_usado'] / $max_producto) * 100);
                  $rankClass = $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : ''));
                ?>
                <tr>
                  <td><span class="rank-num <?= $rankClass ?>"><?= $i+1 ?></span></td>
                  <td style="max-width:180px">
                    <div style="font-weight:600;font-size:13px;color:var(--texto)">
                      <?= htmlspecialchars($p['nombre_item']) ?>
                    </div>
                    <div class="prog-wrap" style="margin-top:5px">
                      <div class="prog-bar">
                        <div class="prog-fill naranja" style="width:<?= $pct ?>%"></div>
                      </div>
                    </div>
                  </td>
                  <td><strong><?= number_format($p['total_usado'], 1) ?></strong> uds.</td>
                  <td style="color:var(--naranja);font-weight:600">S/ <?= number_format($p['total_generado'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- ══ Mecánicos + Stock bajo ══ -->
    <div class="reportes-grid">

      <!-- Rendimiento mecánicos -->
      <div class="reporte-card">
        <div class="reporte-card-header">
          <h3><i class="fas fa-screwdriver-wrench"></i> Rendimiento de Mecánicos</h3>
          <span><?= $anio ?></span>
        </div>
        <div class="reporte-card-body" style="padding:0">
          <?php if (empty($mecanicos_perf)): ?>
            <div class="empty"><i class="fas fa-users"></i><p>Sin mecánicos registrados</p></div>
          <?php else: ?>
          <div class="reporte-tabla">
            <table>
              <thead>
                <tr>
                  <th>Mecánico</th>
                  <th>Órdenes</th>
                  <th>Ingresos generados</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($mecanicos_perf as $m): ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px">
                      <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--azul-acento),#1860a0);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:13px;flex-shrink:0">
                        <?= strtoupper(substr($m['mecanico'],0,1)) ?>
                      </div>
                      <span style="font-weight:600"><?= htmlspecialchars($m['mecanico']) ?></span>
                    </div>
                  </td>
                  <td>
                    <span class="badge badge-lista"><?= $m['ordenes'] ?> órdenes</span>
                  </td>
                  <td style="font-weight:700;color:var(--verde)">
                    S/ <?= number_format($m['ingresos'], 2) ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Stock bajo -->
      <div class="reporte-card">
        <div class="reporte-card-header">
          <h3><i class="fas fa-triangle-exclamation" style="color:var(--amarillo)"></i> Alertas de Stock Bajo</h3>
          <a href="../inventario/index.php?stock_bajo=1" style="font-size:12px;color:var(--azul-acento)">Ver todos →</a>
        </div>
        <div class="reporte-card-body" style="padding:0">
          <?php if (empty($productos_bajos)): ?>
            <div class="empty">
              <i class="fas fa-check-circle" style="color:var(--verde)"></i>
              <p style="color:var(--verde)">¡Todo el stock está bien!</p>
            </div>
          <?php else: ?>
          <div class="reporte-tabla">
            <table>
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Stock actual</th>
                  <th>Mínimo</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($productos_bajos as $p): ?>
                <tr>
                  <td>
                    <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($p['nombre']) ?></div>
                    <div style="font-size:11px;color:var(--texto-muted)"><?= htmlspecialchars($p['categoria']) ?></div>
                  </td>
                  <td>
                    <strong style="color:<?= $p['stock_actual'] == 0 ? 'var(--rojo)' : 'var(--amarillo)' ?>">
                      <?= number_format($p['stock_actual'], 1) ?>
                    </strong>
                    <span style="font-size:11px;color:var(--texto-muted)"> <?= $p['unidad_medida'] ?></span>
                  </td>
                  <td style="color:var(--texto-muted);font-size:13px">
                    <?= number_format($p['stock_minimo'], 1) ?> <?= $p['unidad_medida'] ?>
                  </td>
                  <td>
                    <?php if ($p['stock_actual'] == 0): ?>
                      <span class="badge badge-anulada"><i class="fas fa-circle-xmark"></i> Sin stock</span>
                    <?php else: ?>
                      <span class="badge badge-proceso"><i class="fas fa-triangle-exclamation"></i> Bajo</span>
                    <?php endif; ?>
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

  </div>
</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
// Colores del sistema
const azul    = '#1d6fa4';
const naranja = '#e8820c';
const verde   = '#27ae60';
const amarillo= '#f39c12';
const rojo    = '#e74c3c';
const morado  = '#8e44ad';

// ── Gráfica de ingresos por mes ──
const ctxIngresos = document.getElementById('chartIngresos').getContext('2d');
new Chart(ctxIngresos, {
  type: 'bar',
  data: {
    labels: <?= json_encode($meses_nombres) ?>,
    datasets: [{
      label: 'Ingresos (S/)',
      data: <?= json_encode($data_ingresos) ?>,
      backgroundColor: 'rgba(232,130,12,0.15)',
      borderColor: naranja,
      borderWidth: 2,
      borderRadius: 6,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: ctx => ' S/ ' + ctx.parsed.y.toLocaleString('es-PE', {minimumFractionDigits:2})
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(0,0,0,0.05)' },
        ticks: {
          callback: val => 'S/ ' + val.toLocaleString('es-PE'),
          font: { size: 11 }
        }
      },
      x: {
        grid: { display: false },
        ticks: { font: { size: 11 } }
      }
    }
  }
});

// ── Gráfica de estados ──
const estadosData = <?= json_encode($estados_data) ?>;
const estadosLabels = {
  abierta: 'Abiertas', en_proceso: 'En Proceso',
  lista: 'Listas', cobrada: 'Cobradas', anulada: 'Anuladas'
};
const estadosColores = {
  abierta: azul, en_proceso: amarillo,
  lista: verde, cobrada: morado, anulada: rojo
};

const ctxEstados = document.getElementById('chartEstados').getContext('2d');
new Chart(ctxEstados, {
  type: 'doughnut',
  data: {
    labels: Object.keys(estadosData).map(k => estadosLabels[k] || k),
    datasets: [{
      data: Object.values(estadosData),
      backgroundColor: Object.keys(estadosData).map(k => estadosColores[k] || '#ccc'),
      borderWidth: 2,
      borderColor: '#fff',
      hoverBorderWidth: 3,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    cutout: '65%',
    plugins: {
      legend: {
        position: 'bottom',
        labels: { font: { size: 12 }, padding: 16, usePointStyle: true }
      }
    }
  }
});
</script>
<script src="../../assets/js/main.js"></script>
</body>
</html>