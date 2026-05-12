<?php
// ============================================================
//  api/iot_sensor.php — Endpoint para el sensor ESP32
//  
//  El ESP32 envía un POST con JSON:
//  {
//    "api_key":    "TM_IOT_2025_SECRET",
//    "sensor_id":  "sensor_01",
//    "producto_id": 3,
//    "peso_actual": 1850.5
//  }
//
//  El servidor calcula la diferencia con el peso anterior,
//  divide entre peso_referencia y descuenta del stock.
// ============================================================

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Usa POST.']);
    exit;
}

// Headers para JSON y CORS (el ESP32 puede conectarse desde la red local)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

require_once '../config/database.php';

// ── API Key (debe coincidir con la que programas en el ESP32) ──
define('IOT_API_KEY', 'TM_IOT_2025_SECRET');

// ── Leer el JSON que envía el ESP32 ──
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// Validar que llegó JSON válido
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido o vacío.']);
    exit;
}

// ── Validar API Key ──
$api_key = $data['api_key'] ?? ($_SERVER['HTTP_X_API_KEY'] ?? '');
if ($api_key !== IOT_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'API Key inválida.']);
    exit;
}

// ── Validar campos obligatorios ──
$sensor_id   = trim($data['sensor_id']   ?? '');
$producto_id = (int)($data['producto_id'] ?? 0);
$peso_actual = (float)($data['peso_actual'] ?? -1);

if (!$sensor_id || !$producto_id || $peso_actual < 0) {
    http_response_code(400);
    echo json_encode([
        'error'   => 'Faltan campos: sensor_id, producto_id, peso_actual.',
        'recibido'=> $data
    ]);
    exit;
}

// ── Cargar el producto ──
$producto = dbQuery(
    "SELECT id, nombre, stock_actual, stock_minimo, peso_referencia, unidad_medida
     FROM productos WHERE id = ? AND activo = 1",
    [$producto_id]
);

if (!$producto) {
    http_response_code(404);
    echo json_encode(['error' => "Producto ID $producto_id no encontrado."]);
    exit;
}
$producto = $producto[0];

// Validar que el producto tiene peso de referencia configurado
if ($producto['peso_referencia'] <= 0) {
    http_response_code(422);
    echo json_encode([
        'error'   => 'El producto no tiene peso_referencia configurado.',
        'producto' => $producto['nombre']
    ]);
    exit;
}

// ── Obtener el último peso registrado por este sensor ──
$ultimo_mov = dbQuery(
    "SELECT peso_registrado FROM movimientos_stock
     WHERE producto_id = ? AND fuente = 'iot'
     ORDER BY fecha DESC LIMIT 1",
    [$producto_id]
);

// Si no hay registro previo, usamos el peso actual como referencia inicial
if (!$ultimo_mov) {
    // Primer registro — guardamos el peso como baseline sin cambiar stock
    dbQuery(
        "INSERT INTO movimientos_stock
         (producto_id, tipo, cantidad, peso_registrado, fuente, observacion)
         VALUES (?, 'entrada', 0, ?, 'iot', 'Calibración inicial del sensor')",
        [$producto_id, $peso_actual]
    );

    echo json_encode([
        'status'   => 'calibrado',
        'mensaje'  => 'Peso inicial registrado. El sistema está listo.',
        'producto' => $producto['nombre'],
        'peso'     => $peso_actual
    ]);
    exit;
}

$peso_anterior = (float)$ultimo_mov[0]['peso_registrado'];

// ── Calcular la diferencia de peso ──
$diferencia_peso = $peso_anterior - $peso_actual;

// Tolerancia: ignorar variaciones menores a 10g (ruido del sensor)
$TOLERANCIA_GRAMOS = 10.0;

if (abs($diferencia_peso) < $TOLERANCIA_GRAMOS) {
    echo json_encode([
        'status'      => 'sin_cambio',
        'mensaje'     => 'Variación dentro de la tolerancia del sensor.',
        'diferencia'  => $diferencia_peso,
        'tolerancia'  => $TOLERANCIA_GRAMOS
    ]);
    exit;
}

// ── Calcular cantidad en unidades ──
$cantidad_unidades = abs($diferencia_peso) / $producto['peso_referencia'];
$cantidad_unidades = round($cantidad_unidades, 3);

// Determinar tipo: salida (se retiró) o entrada (se agregó)
$tipo = $diferencia_peso > 0 ? 'salida' : 'entrada';

// Validar que no haya más salida que stock disponible
if ($tipo === 'salida' && $cantidad_unidades > $producto['stock_actual']) {
    // Ajustar al stock disponible (posible error del sensor)
    $cantidad_unidades = $producto['stock_actual'];
}

// ── Actualizar stock ──
if ($tipo === 'salida') {
    $nuevo_stock = $producto['stock_actual'] - $cantidad_unidades;
} else {
    $nuevo_stock = $producto['stock_actual'] + $cantidad_unidades;
}
$nuevo_stock = max(0, round($nuevo_stock, 3));

dbQuery(
    "UPDATE productos SET stock_actual = ? WHERE id = ?",
    [$nuevo_stock, $producto_id]
);

// ── Registrar movimiento ──
dbQuery(
    "INSERT INTO movimientos_stock
     (producto_id, tipo, cantidad, peso_registrado, fuente, observacion)
     VALUES (?, ?, ?, ?, 'iot', ?)",
    [
        $producto_id,
        $tipo,
        $cantidad_unidades,
        $peso_actual,
        "Sensor $sensor_id — Δpeso: {$diferencia_peso}g"
    ]
);

// ── Verificar si bajó del stock mínimo y crear alerta ──
$alerta_msg = null;
if ($nuevo_stock <= $producto['stock_minimo'] && $tipo === 'salida') {
    $alerta_msg = "Stock bajo: {$producto['nombre']} tiene $nuevo_stock {$producto['unidad_medida']} (mínimo: {$producto['stock_minimo']})";

    // Verificar si ya existe una alerta reciente (últimas 2 horas) para no duplicar
    $alerta_existe = dbQuery(
        "SELECT id FROM alertas
         WHERE tipo = 'stock_bajo'
         AND mensaje LIKE ?
         AND created_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)",
        ["%{$producto['nombre']}%"]
    );

    if (!$alerta_existe) {
        dbQuery(
            "INSERT INTO alertas (tipo, mensaje, leida)
             VALUES ('stock_bajo', ?, 0)",
            [$alerta_msg]
        );
    }
}

// ── Respuesta JSON al ESP32 ──
http_response_code(200);
echo json_encode([
    'status'       => 'ok',
    'tipo'         => $tipo,
    'producto'     => $producto['nombre'],
    'cantidad'     => $cantidad_unidades,
    'unidad'       => $producto['unidad_medida'],
    'stock_nuevo'  => $nuevo_stock,
    'stock_minimo' => $producto['stock_minimo'],
    'alerta'       => $alerta_msg,
    'timestamp'    => date('Y-m-d H:i:s')
]);