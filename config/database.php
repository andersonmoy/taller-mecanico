<?php
// ============================================================
//  config/database.php
//  Conexión a la base de datos MySQL
//  Sistema de Gestión — Taller Mecánico
// ============================================================

// ── Parámetros de conexión ────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_USER',     'root');       // Cambia si tienes otro usuario
define('DB_PASS',     '');           // Cambia si tienes contraseña
define('DB_NAME',     'taller_mecanico_db');
define('DB_CHARSET',  'utf8mb4');

// ── Función de conexión (Singleton) ──────────────────────
function getDB(): mysqli {
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            error_log("Error de conexión BD: " . $conn->connect_error);
            die(json_encode([
                'error' => true,
                'mensaje' => 'No se pudo conectar a la base de datos. Verifica que XAMPP esté activo.'
            ]));
        }

        $conn->set_charset(DB_CHARSET);
        $conn->query("SET time_zone = '-05:00'"); // Hora Perú (UTC-5)
    }

    return $conn;
}

// ── Función auxiliar: query segura con parámetros ────────
/**
 * Ejecuta una consulta preparada y retorna el resultado.
 *
 * Uso:
 *   $rows = dbQuery("SELECT * FROM productos WHERE id = ?", [5]);
 *   $rows = dbQuery("SELECT * FROM usuarios WHERE rol = ? AND activo = ?", ['mecanico', 1]);
 *
 * @param string $sql    Consulta con ? como placeholders
 * @param array  $params Valores a enlazar
 * @return array|bool    Array de filas (SELECT) o true/false (INSERT/UPDATE/DELETE)
 */
function dbQuery(string $sql, array $params = []): array|bool {
    $db   = getDB();
    $stmt = $db->prepare($sql);

    if (!$stmt) {
        error_log("Error preparando consulta: " . $db->error . " | SQL: $sql");
        return false;
    }

    if (!empty($params)) {
        // Detecta tipos automáticamente: i=int, d=double, s=string
        $types = '';
        foreach ($params as $p) {
            if (is_int($p))    $types .= 'i';
            elseif (is_float($p)) $types .= 'd';
            else               $types .= 's';
        }
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    // SELECT → retorna array de filas
    $result = $stmt->get_result();
    if ($result !== false) {
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    // INSERT / UPDATE / DELETE → retorna true/false
    $ok = ($stmt->affected_rows >= 0);
    $stmt->close();
    return $ok;
}

// ── Función auxiliar: obtener último ID insertado ────────
function dbLastId(): int {
    return (int) getDB()->insert_id;
}

// ── Constantes del sistema ───────────────────────────────
define('APP_NAME',    'Taller Mecánico');
define('APP_VERSION', '1.0.0');
define('IGV',         0.18);          // 18% IGV Perú
define('SERIE_BOLETA','B001');
define('SERIE_FACTURA','F001');
define('MONEDA',      'PEN');         // Soles peruanos