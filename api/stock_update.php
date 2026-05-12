<?php
// ============================================================
//  api/stock_update.php — Consulta de stock para el ESP32
//
//  El ESP32 puede consultar el stock actual de un producto:
//  GET api/stock_update.php?api_key=TM_IOT_2025_SECRET&producto_id=3
//
//  También sirve para que la interfaz web consulte
//  el estado de los sensores en tiempo real (AJAX).
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

define('IOT_API_KEY', 'TM_IOT_2025_SECRET');

// ── Validar API Key ──
$api_key = $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($api_key !== IOT_API_KEY) {
    http_response_code(401);
    echo json_encode(['error' => 'API Key inválida.']);
    exit;
}

$accion = $_GET['accion'] ?? 'stock';

// ══════════════════════════
// ACCIÓN: stock de un producto
// GET ?accion=stock&producto_id=3
// ══════════════════════════
if ($accion === 'stock') {
    $producto_id = (int)($_GET['producto_id'] ?? 0);

    if (!$producto_id) {
        // Devolver todos los productos con info IoT
        $productos = dbQuery("
            SELECT p.id, p.nombre, p.stock_actual, p.stock_minimo,
                   p.peso_referencia, p.unidad_medida,
                   c.nombre AS categoria,
                   (p.stock_actual <= p.stock_minimo) AS stock_bajo
            FROM productos p
            LEFT JOIN categorias c ON p.categoria_id = c.id
            WHERE p.activo = 1
            ORDER BY p.nombre") ?: [];

        echo json_encode(['status' => 'ok', 'productos' => $productos]);
        exit;
    }

    $p = dbQuery(
        "SELECT id, nombre, stock_actual, stock_minimo, peso_referencia, unidad_medida
         FROM productos WHERE id = ? AND activo = 1",
        [$producto_id]
    );

    if (!$p) {
        http_response_code(404);
        echo json_encode(['error' => 'Producto no encontrado.']);
        exit;
    }

    echo json_encode(['status' => 'ok', 'producto' => $p[0]]);
    exit;
}

// ══════════════════════════
// ACCIÓN: últimos movimientos IoT
// GET ?accion=movimientos&limite=10
// ══════════════════════════
if ($accion === 'movimientos') {
    $limite = min((int)($_GET['limite'] ?? 20), 100);

    $movs = dbQuery("
        SELECT m.id, m.tipo, m.cantidad, m.peso_registrado,
               m.observacion, m.fecha,
               p.nombre AS producto, p.unidad_medida
        FROM movimientos_stock m
        LEFT JOIN productos p ON m.producto_id = p.id
        WHERE m.fuente = 'iot'
        ORDER BY m.fecha DESC
        LIMIT ?", [$limite]) ?: [];

    echo json_encode(['status' => 'ok', 'movimientos' => $movs]);
    exit;
}

// ══════════════════════════
// ACCIÓN: alertas no leídas
// GET ?accion=alertas
// ══════════════════════════
if ($accion === 'alertas') {
    $alertas = dbQuery("
        SELECT id, tipo, mensaje, created_at
        FROM alertas
        WHERE leida = 0
        ORDER BY created_at DESC
        LIMIT 10") ?: [];

    echo json_encode(['status' => 'ok', 'alertas' => $alertas, 'total' => count($alertas)]);
    exit;
}

// ══════════════════════════
// ACCIÓN: ping (el ESP32 verifica conexión)
// GET ?accion=ping
// ══════════════════════════
if ($accion === 'ping') {
    echo json_encode([
        'status'    => 'ok',
        'mensaje'   => 'Servidor Taller Mecánico en línea.',
        'timestamp' => date('Y-m-d H:i:s'),
        'version'   => '1.0'
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Acción no reconocida. Usa: stock, movimientos, alertas, ping']);