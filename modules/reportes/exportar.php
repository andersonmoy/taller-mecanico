<?php
// ============================================================
//  modules/reportes/exportar.php — Exportar reportes a Excel
// ============================================================
require_once '../../includes/auth.php';
require_once '../../config/database.php';
soloAdmin();

$tipo = $_GET['tipo'] ?? '';
$anio = (int)($_GET['anio'] ?? date('Y'));

// ── Función: generar CSV limpio ──
function exportarCSV(string $nombre, array $cabeceras, array $filas): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF"; // BOM para que Excel reconozca UTF-8
    $out = fopen('php://output', 'w');
    fputcsv($out, $cabeceras, ';');
    foreach ($filas as $fila) fputcsv($out, $fila, ';');
    fclose($out);
    exit;
}

// ══════════════════════════════════════
// REPORTE: Órdenes del año
// ══════════════════════════════════════
if ($tipo === 'ordenes') {
    $filas = dbQuery("
        SELECT ot.numero, ot.estado,
               DATE_FORMAT(ot.fecha_ingreso,'%d/%m/%Y %H:%i') AS fecha_ingreso,
               DATE_FORMAT(ot.fecha_cierre,'%d/%m/%Y %H:%i')  AS fecha_cierre,
               cl.nombre AS cliente, cl.dni_ruc,
               v.placa, CONCAT(v.marca,' ',v.modelo) AS vehiculo,
               CONCAT(u.nombre,' ',u.apellido) AS mecanico,
               ot.km_ingreso, ot.subtotal, ot.igv, ot.total
        FROM ordenes_trabajo ot
        LEFT JOIN clientes cl ON ot.cliente_id  = cl.id
        LEFT JOIN vehiculos v  ON ot.vehiculo_id = v.id
        LEFT JOIN usuarios  u  ON ot.mecanico_id = u.id
        WHERE YEAR(ot.fecha_ingreso) = ?
        ORDER BY ot.fecha_ingreso DESC", [$anio]) ?: [];

    exportarCSV("ordenes_$anio", [
        'N° Orden','Estado','Fecha Ingreso','Fecha Cierre',
        'Cliente','DNI/RUC','Placa','Vehículo',
        'Mecánico','KM Ingreso','Subtotal (S/)','IGV (S/)','Total (S/)'
    ], array_map(fn($r) => [
        $r['numero'], $r['estado'], $r['fecha_ingreso'], $r['fecha_cierre'] ?? '',
        $r['cliente'], $r['dni_ruc'], $r['placa'], $r['vehiculo'],
        $r['mecanico'] ?? '', $r['km_ingreso'],
        number_format($r['subtotal'],2,'.',''),
        number_format($r['igv'],2,'.',''),
        number_format($r['total'],2,'.','')
    ], $filas));
}

// ══════════════════════════════════════
// REPORTE: Comprobantes del año
// ══════════════════════════════════════
if ($tipo === 'comprobantes') {
    $filas = dbQuery("
        SELECT CONCAT(c.serie,'-',LPAD(c.numero,8,'0')) AS numero,
               c.tipo,
               DATE_FORMAT(c.created_at,'%d/%m/%Y %H:%i') AS fecha,
               cl.nombre AS cliente, cl.dni_ruc,
               ot.numero AS orden,
               c.subtotal, c.igv, c.total, c.estado
        FROM comprobantes c
        LEFT JOIN clientes        cl ON c.cliente_id = cl.id
        LEFT JOIN ordenes_trabajo ot ON c.orden_id   = ot.id
        WHERE YEAR(c.created_at) = ?
        ORDER BY c.created_at DESC", [$anio]) ?: [];

    exportarCSV("comprobantes_$anio", [
        'N° Comprobante','Tipo','Fecha','Cliente','DNI/RUC',
        'Orden','Subtotal (S/)','IGV (S/)','Total (S/)','Estado'
    ], array_map(fn($r) => [
        $r['numero'], ucfirst($r['tipo']), $r['fecha'],
        $r['cliente'], $r['dni_ruc'], $r['orden'] ?? '',
        number_format($r['subtotal'],2,'.',''),
        number_format($r['igv'],2,'.',''),
        number_format($r['total'],2,'.',''),
        $r['estado']
    ], $filas));
}

// ══════════════════════════════════════
// REPORTE: Inventario actual
// ══════════════════════════════════════
if ($tipo === 'inventario') {
    $filas = dbQuery("
        SELECT p.nombre, c.nombre AS categoria,
               p.stock_actual, p.stock_minimo, p.unidad_medida,
               p.precio_sin_igv, p.precio_con_igv,
               ROUND(p.precio_con_igv * p.stock_actual, 2) AS valor_total,
               CASE WHEN p.stock_actual = 0 THEN 'Sin stock'
                    WHEN p.stock_actual <= p.stock_minimo THEN 'Stock bajo'
                    ELSE 'Normal' END AS estado_stock
        FROM productos p
        LEFT JOIN categorias c ON p.categoria_id = c.id
        WHERE p.activo = 1
        ORDER BY c.nombre, p.nombre") ?: [];

    exportarCSV('inventario_' . date('Ymd'), [
        'Producto','Categoría','Stock Actual','Stock Mínimo','Unidad',
        'Precio sin IGV (S/)','Precio con IGV (S/)','Valor en Almacén (S/)','Estado'
    ], array_map(fn($r) => [
        $r['nombre'], $r['categoria'],
        number_format($r['stock_actual'],3,'.',''),
        number_format($r['stock_minimo'],3,'.',''),
        $r['unidad_medida'],
        number_format($r['precio_sin_igv'],2,'.',''),
        number_format($r['precio_con_igv'],2,'.',''),
        number_format($r['valor_total'],2,'.',''),
        $r['estado_stock']
    ], $filas));
}

// ══════════════════════════════════════
// REPORTE: Movimientos de stock
// ══════════════════════════════════════
if ($tipo === 'movimientos') {
    $filas = dbQuery("
        SELECT DATE_FORMAT(m.fecha,'%d/%m/%Y %H:%i') AS fecha,
               p.nombre AS producto,
               m.tipo, m.cantidad, p.unidad_medida,
               m.fuente,
               CONCAT(u.nombre,' ',u.apellido) AS usuario,
               ot.numero AS orden,
               m.observacion
        FROM movimientos_stock m
        LEFT JOIN productos       p  ON m.producto_id = p.id
        LEFT JOIN usuarios        u  ON m.usuario_id  = u.id
        LEFT JOIN ordenes_trabajo ot ON m.orden_id    = ot.id
        WHERE YEAR(m.fecha) = ?
        ORDER BY m.fecha DESC", [$anio]) ?: [];

    exportarCSV("movimientos_stock_$anio", [
        'Fecha','Producto','Tipo','Cantidad','Unidad',
        'Fuente','Usuario','Orden','Observación'
    ], array_map(fn($r) => [
        $r['fecha'], $r['producto'],
        ucfirst($r['tipo']),
        number_format($r['cantidad'],3,'.',''),
        $r['unidad_medida'], strtoupper($r['fuente']),
        $r['usuario'] ?? '', $r['orden'] ?? '',
        $r['observacion'] ?? ''
    ], $filas));
}

// ══════════════════════════════════════
// REPORTE: Clientes
// ══════════════════════════════════════
if ($tipo === 'clientes') {
    $filas = dbQuery("
        SELECT cl.nombre, cl.dni_ruc, cl.telefono, cl.correo,
               cl.direccion, cl.tipo,
               COUNT(DISTINCT v.id)  AS total_vehiculos,
               COUNT(DISTINCT ot.id) AS total_ordenes,
               SUM(ot.total)         AS total_gastado,
               DATE_FORMAT(cl.created_at,'%d/%m/%Y') AS registrado
        FROM clientes cl
        LEFT JOIN vehiculos       v  ON v.cliente_id  = cl.id
        LEFT JOIN ordenes_trabajo ot ON ot.cliente_id = cl.id
        GROUP BY cl.id
        ORDER BY cl.nombre") ?: [];

    exportarCSV('clientes_' . date('Ymd'), [
        'Cliente','DNI/RUC','Teléfono','Correo','Dirección','Tipo',
        'Vehículos','Órdenes','Total Gastado (S/)','Registrado'
    ], array_map(fn($r) => [
        $r['nombre'], $r['dni_ruc'], $r['telefono'] ?? '',
        $r['correo'] ?? '', $r['direccion'] ?? '', ucfirst($r['tipo']),
        $r['total_vehiculos'], $r['total_ordenes'],
        number_format($r['total_gastado'] ?? 0, 2, '.', ''),
        $r['registrado']
    ], $filas));
}

// ── Si no coincide ningún tipo, redirigir ──
header('Location: index.php?anio=' . $anio);
exit;