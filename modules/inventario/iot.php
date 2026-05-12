<?php
// ============================================================
//  modules/inventario/iot.php — Panel IoT en tiempo real
// ============================================================
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$PAGE_TITLE  = 'Monitor IoT — Sensores';
$PAGE_ICON   = 'fa-microchip';
$ACTIVE_MENU = 'inventario';
$TOPBAR_ACTIONS = '<a href="index.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Inventario</a>';

// Últimos movimientos IoT
$movimientos_iot = dbQuery("
    SELECT m.*, p.nombre AS producto, p.unidad_medida, p.stock_actual,
           p.stock_minimo, p.peso_referencia
    FROM movimientos_stock m
    LEFT JOIN productos p ON m.producto_id = p.id
    WHERE m.fuente = 'iot'
    ORDER BY m.fecha DESC
    LIMIT 50") ?: [];

// Productos con sensor (tienen peso_referencia > 0)
$productos_sensor = dbQuery("
    SELECT p.*, c.nombre AS categoria,
           (SELECT peso_registrado FROM movimientos_stock
            WHERE producto_id = p.id AND fuente = 'iot'
            ORDER BY fecha DESC LIMIT 1) AS ultimo_peso,
           (SELECT fecha FROM movimientos_stock
            WHERE producto_id = p.id AND fuente = 'iot'
            ORDER BY fecha DESC LIMIT 1) AS ultima_lectura
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.activo = 1 AND p.peso_referencia > 0
    ORDER BY p.nombre") ?: [];

// Alertas IoT no leídas
$alertas = dbQuery("
    SELECT * FROM alertas
    WHERE tipo = 'stock_bajo' AND leida = 0
    ORDER BY created_at DESC") ?: [];

// URL base de la API
$api_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
         . dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))) . '/api/iot_sensor.php';

require_once '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/inventario.css">
<link rel="stylesheet" href="../../assets/css/iot.css">

<!-- Alertas IoT -->
<?php if (!empty($alertas)): ?>
<div class="alert alert-warning">
  <i class="fas fa-triangle-exclamation"></i>
  <strong><?= count($alertas) ?> alerta<?= count($alertas)>1?'s':'' ?> de stock bajo</strong>
  — Los sensores detectaron productos bajo el mínimo.
  <a href="#alertas" style="margin-left:auto;color:inherit;font-weight:700">Ver alertas ↓</a>
</div>
<?php endif; ?>

<!-- Estado de conexión -->
<div class="iot-conexion-bar">
  <div class="iot-conexion-info">
    <span class="iot-dot" id="iotDot"></span>
    <span id="iotEstado" style="font-size:13px;font-weight:600;color:var(--verde)">Conectando...</span>
  </div>
  <div style="display:flex;align-items:center;gap:16px">
    <span style="font-size:12px;color:var(--texto-muted)">
      Última actualización: <strong id="ultimaActualizacion">—</strong>
    </span>
    <button class="btn btn-sm btn-outline" onclick="actualizarDatos()">
      <i class="fas fa-sync" id="iconoSync"></i> Actualizar
    </button>
  </div>
</div>

<!-- Tarjetas de sensores -->
<h3 style="font-family:var(--font-title);font-size:16px;margin-bottom:14px;color:var(--texto)">
  <i class="fas fa-weight-scale" style="color:var(--azul-acento)"></i>
  Productos con Sensor de Peso (<?= count($productos_sensor) ?>)
</h3>

<?php if (empty($productos_sensor)): ?>
<div class="alert alert-info">
  <i class="fas fa-info-circle"></i>
  No hay productos con sensor configurado. Ve a
  <a href="crear.php" style="font-weight:700">Nuevo Producto</a>
  y establece el <strong>peso de referencia</strong> (gramos por unidad).
</div>
<?php else: ?>
<div class="iot-sensores-grid">
  <?php foreach ($productos_sensor as $p):
    $con_sensor = $p['ultima_lectura'] !== null;
    $hace_cuanto = $con_sensor
        ? human_time_diff($p['ultima_lectura'])
        : 'Sin lecturas';
    $estado_stock = $p['stock_actual'] == 0 ? 'sin_stock'
        : ($p['stock_actual'] <= $p['stock_minimo'] ? 'stock_bajo' : 'ok');
  ?>
  <div class="iot-sensor-card <?= $estado_stock ?>">
    <div class="iot-sensor-header">
      <div>
        <div class="iot-sensor-nombre"><?= htmlspecialchars($p['nombre']) ?></div>
        <div class="iot-sensor-cat"><?= htmlspecialchars($p['categoria'] ?? '') ?></div>
      </div>
      <div class="iot-dot <?= $con_sensor ? '' : 'offline' ?>" title="<?= $con_sensor ? 'Sensor activo' : 'Sin lecturas' ?>"></div>
    </div>

    <div class="iot-sensor-body">
      <!-- Stock actual -->
      <div class="iot-stat">
        <span class="iot-stat-val <?= $estado_stock ?>">
          <?= number_format($p['stock_actual'], 1) ?>
        </span>
        <span class="iot-stat-label"><?= $p['unidad_medida'] ?> en stock</span>
      </div>

      <!-- Barra de stock -->
      <?php
        $pct = $p['stock_minimo'] > 0
            ? min(100, round(($p['stock_actual'] / $p['stock_minimo']) * 100))
            : 100;
      ?>
      <div class="stock-bar" style="margin:10px 0">
        <div class="stock-bar-fill <?= $estado_stock ?>" style="width:<?= $pct ?>%"></div>
      </div>

      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--texto-muted)">
        <span>Mín: <?= $p['stock_minimo'] ?> <?= $p['unidad_medida'] ?></span>
        <span><?= $pct ?>% del mínimo</span>
      </div>
    </div>

    <div class="iot-sensor-footer">
      <span style="font-size:11px;color:var(--texto-muted)">
        <i class="fas fa-clock"></i> <?= $hace_cuanto ?>
      </span>
      <?php if ($p['ultimo_peso']): ?>
      <span style="font-size:11px;color:var(--texto-muted)">
        <i class="fas fa-weight-scale"></i>
        <?= number_format($p['ultimo_peso'], 1) ?>g
      </span>
      <?php endif; ?>
      <span style="font-size:11px;color:var(--texto-muted)">
        Ref: <?= $p['peso_referencia'] ?>g/u
      </span>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Últimos movimientos IoT -->
<div class="card" style="margin-top:24px">
  <div class="card-header">
    <h3><i class="fas fa-clock-rotate-left"></i> Últimos movimientos del sensor</h3>
    <span style="font-size:12px;color:var(--texto-muted)">Últimos 50 registros IoT</span>
  </div>
  <?php if (empty($movimientos_iot)): ?>
    <div class="empty">
      <i class="fas fa-microchip"></i>
      <p>El sensor aún no ha registrado movimientos</p>
      <p style="font-size:12px">Cuando el ESP32 envíe datos, aparecerán aquí automáticamente.</p>
    </div>
  <?php else: ?>
  <div class="tabla-wrapper">
    <table>
      <thead>
        <tr>
          <th>Fecha y hora</th>
          <th>Producto</th>
          <th>Movimiento</th>
          <th>Cantidad</th>
          <th>Peso leído</th>
          <th>Detalle</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($movimientos_iot as $m): ?>
        <tr>
          <td style="white-space:nowrap;font-size:12px;color:var(--texto-muted)">
            <?= date('d/m/Y H:i:s', strtotime($m['fecha'])) ?>
          </td>
          <td class="prod-nombre"><?= htmlspecialchars($m['producto']) ?></td>
          <td>
            <span class="mov-tipo <?= $m['tipo']==='entrada' ? 'mov-entrada' : 'mov-salida' ?>">
              <i class="fas fa-arrow-<?= $m['tipo']==='entrada' ? 'down':'up' ?>"></i>
              <?= ucfirst($m['tipo']) ?>
            </span>
          </td>
          <td>
            <strong><?= $m['tipo']==='entrada' ? '+':'-' ?><?= number_format($m['cantidad'], 3) ?></strong>
            <span style="font-size:11px;color:var(--texto-muted)"><?= $m['unidad_medida'] ?></span>
          </td>
          <td style="font-size:12px;color:var(--texto-muted)">
            <?= $m['peso_registrado'] ? number_format($m['peso_registrado'],1).'g' : '—' ?>
          </td>
          <td style="font-size:11px;color:var(--texto-muted)">
            <?= htmlspecialchars($m['observacion'] ?? '—') ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Alertas -->
<?php if (!empty($alertas)): ?>
<div class="card" id="alertas" style="margin-top:20px">
  <div class="card-header">
    <h3 style="color:var(--amarillo)"><i class="fas fa-triangle-exclamation"></i> Alertas activas</h3>
    <form method="POST">
      <button type="submit" name="marcar_leidas" class="btn btn-sm btn-outline">
        <i class="fas fa-check-double"></i> Marcar todas como leídas
      </button>
    </form>
  </div>
  <div style="padding:16px">
    <?php foreach ($alertas as $a): ?>
    <div class="alert alert-warning" style="margin-bottom:10px">
      <i class="fas fa-triangle-exclamation"></i>
      <?= htmlspecialchars($a['mensaje']) ?>
      <span style="margin-left:auto;font-size:11px;color:var(--texto-muted)">
        <?= date('d/m/Y H:i', strtotime($a['created_at'])) ?>
      </span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Configuración del ESP32 -->
<div class="card" style="margin-top:20px">
  <div class="card-header">
    <h3><i class="fas fa-microchip"></i> Configuración del ESP32</h3>
  </div>
  <div class="card-body">
    <p style="font-size:13px;color:var(--texto-muted);margin-bottom:16px">
      Usa estos datos para programar tu ESP32. El endpoint HTTP recibe los datos del sensor.
    </p>
    <div class="iot-config-grid">
      <div class="iot-config-item">
        <span class="iot-config-label">Endpoint POST (sensor → servidor)</span>
        <code class="iot-config-val"><?= htmlspecialchars($api_url) ?></code>
      </div>
      <div class="iot-config-item">
        <span class="iot-config-label">API Key</span>
        <code class="iot-config-val">TM_IOT_2025_SECRET</code>
      </div>
      <div class="iot-config-item">
        <span class="iot-config-label">Formato JSON a enviar</span>
        <code class="iot-config-val">{"api_key":"TM_IOT_2025_SECRET","sensor_id":"sensor_01","producto_id":1,"peso_actual":850.5}</code>
      </div>
      <div class="iot-config-item">
        <span class="iot-config-label">Endpoint consulta stock</span>
        <code class="iot-config-val"><?= str_replace('iot_sensor','stock_update', htmlspecialchars($api_url)) ?>?api_key=TM_IOT_2025_SECRET&accion=ping</code>
      </div>
    </div>

    <!-- Prueba manual -->
    <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--borde)">
      <h4 style="font-size:14px;font-weight:600;color:var(--texto);margin-bottom:12px">
        <i class="fas fa-flask" style="color:var(--naranja)"></i> Prueba manual del sensor
      </h4>
      <form id="formPrueba" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end">
        <div>
          <label style="font-size:11px;font-weight:600;color:var(--texto-muted);letter-spacing:1px;display:block;margin-bottom:6px">PRODUCTO</label>
          <select id="prb_producto" class="form-control">
            <?php foreach ($productos_sensor as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:var(--texto-muted);letter-spacing:1px;display:block;margin-bottom:6px">PESO ACTUAL (g)</label>
          <input type="number" id="prb_peso" class="form-control" step="0.1" min="0" placeholder="Ej: 1850.5">
        </div>
        <div>
          <label style="font-size:11px;font-weight:600;color:var(--texto-muted);letter-spacing:1px;display:block;margin-bottom:6px">SENSOR ID</label>
          <input type="text" id="prb_sensor" class="form-control" value="sensor_test">
        </div>
        <div>
          <button type="button" class="btn btn-naranja" onclick="probarSensor()">
            <i class="fas fa-paper-plane"></i> Enviar
          </button>
        </div>
      </form>
      <div id="prb_resultado" style="margin-top:12px;display:none"></div>
    </div>
  </div>
</div>

  </div><!-- /content -->
</div><!-- /main -->

<script src="../../assets/js/main.js"></script>
<script>
const API_KEY = 'TM_IOT_2025_SECRET';
const API_URL = '<?= addslashes($api_url) ?>';
const UPDATE_URL = '<?= addslashes(str_replace('iot_sensor','stock_update',$api_url)) ?>';

// ── Verificar conexión con el servidor ──
async function verificarConexion() {
  try {
    const r = await fetch(UPDATE_URL + '?api_key=' + API_KEY + '&accion=ping');
    const d = await r.json();
    if (d.status === 'ok') {
      document.getElementById('iotDot').classList.remove('offline');
      document.getElementById('iotEstado').textContent = 'Servidor en línea';
      document.getElementById('iotEstado').style.color = 'var(--verde)';
      document.getElementById('ultimaActualizacion').textContent = new Date().toLocaleTimeString('es-PE');
    }
  } catch(e) {
    document.getElementById('iotDot').classList.add('offline');
    document.getElementById('iotEstado').textContent = 'Sin conexión';
    document.getElementById('iotEstado').style.color = 'var(--rojo)';
  }
}

function actualizarDatos() {
  const icon = document.getElementById('iconoSync');
  icon.classList.add('fa-spin');
  setTimeout(() => { icon.classList.remove('fa-spin'); location.reload(); }, 800);
}

// ── Prueba manual del sensor ──
async function probarSensor() {
  const producto_id = parseInt(document.getElementById('prb_producto').value);
  const peso_actual = parseFloat(document.getElementById('prb_peso').value);
  const sensor_id   = document.getElementById('prb_sensor').value.trim();
  const div         = document.getElementById('prb_resultado');

  if (!peso_actual || peso_actual < 0) {
    div.style.display = 'block';
    div.innerHTML = '<div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> Ingresa un peso válido.</div>';
    return;
  }

  div.style.display = 'block';
  div.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Enviando datos al servidor...</div>';

  try {
    const resp = await fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        api_key: API_KEY,
        sensor_id,
        producto_id,
        peso_actual
      })
    });

    const data = await resp.json();

    if (data.status === 'ok') {
      div.innerHTML = `
        <div class="alert alert-success">
          <i class="fas fa-check-circle"></i>
          <div>
            <strong>${data.tipo === 'salida' ? 'Salida' : 'Entrada'} registrada:</strong>
            ${data.cantidad} ${data.unidad} de <em>${data.producto}</em>.
            Stock nuevo: <strong>${data.stock_nuevo} ${data.unidad}</strong>
            ${data.alerta ? '<br><span style="color:var(--amarillo)">⚠ ' + data.alerta + '</span>' : ''}
          </div>
        </div>`;
      setTimeout(() => location.reload(), 2500);
    } else if (data.status === 'calibrado') {
      div.innerHTML = `<div class="alert alert-info"><i class="fas fa-info-circle"></i> ${data.mensaje}</div>`;
    } else if (data.status === 'sin_cambio') {
      div.innerHTML = `<div class="alert alert-warning"><i class="fas fa-minus-circle"></i> Sin cambio: variación de ${data.diferencia.toFixed(1)}g dentro de la tolerancia.</div>`;
    } else {
      div.innerHTML = `<div class="alert alert-error"><i class="fas fa-times-circle"></i> ${data.error || 'Error desconocido.'}</div>`;
    }
  } catch(e) {
    div.innerHTML = `<div class="alert alert-error"><i class="fas fa-times-circle"></i> No se pudo conectar: ${e.message}</div>`;
  }
}

// Verificar conexión al cargar y cada 30 segundos
verificarConexion();
setInterval(verificarConexion, 30000);
</script>

<?php
// Helper: tiempo humano
function human_time_diff(string $fecha): string {
    $diff = time() - strtotime($fecha);
    if ($diff < 60)  return 'Hace ' . $diff . 's';
    if ($diff < 3600) return 'Hace ' . round($diff/60) . 'min';
    if ($diff < 86400) return 'Hace ' . round($diff/3600) . 'h';
    return 'Hace ' . round($diff/86400) . 'd';
}
?>