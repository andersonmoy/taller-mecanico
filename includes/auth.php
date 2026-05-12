<?php
// ============================================================
//  includes/auth.php — Verificación de sesión y roles
//  Uso: require_once '../../includes/auth.php';
//       require_once '../includes/auth.php';   (desde raíz)
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Detectar la ruta base según dónde se incluya ──
// Busca index.php subiendo niveles
function getBasePath() {
    $dir = __DIR__;
    for ($i = 0; $i < 4; $i++) {
        if (file_exists($dir . '/index.php') && file_exists($dir . '/config')) {
            return $dir . '/';
        }
        $dir = dirname($dir);
    }
    return '/';
}

$BASE_PATH = getBasePath();

// ── Si no hay sesión → redirigir al login ──
if (!isset($_SESSION['usuario_id'])) {
    // Calcular ruta relativa al index.php
    $depth = substr_count(str_replace($BASE_PATH, '', $_SERVER['SCRIPT_FILENAME']), '/');
    $prefix = str_repeat('../', $depth);
    header('Location: ' . $prefix . 'index.php');
    exit;
}

// ── Variables globales disponibles en toda la vista ──
$SESSION_ID     = $_SESSION['usuario_id'];
$SESSION_NOMBRE = $_SESSION['usuario_nombre'];
$SESSION_ROL    = $_SESSION['usuario_rol'];

// ── Función helper: verificar rol mínimo requerido ──
// Uso: requireRol('administrador');  → solo admins
//      requireRol('cajero');          → admin y cajero
function requireRol(string $rol_minimo): void {
    $jerarquia = ['mecanico' => 1, 'cajero' => 2, 'administrador' => 3];
    $rol_usuario = $_SESSION['usuario_rol'] ?? 'mecanico';

    if (($jerarquia[$rol_usuario] ?? 0) < ($jerarquia[$rol_minimo] ?? 99)) {
        header('Location: ../../dashboard.php?error=sin_permiso');
        exit;
    }
}

// ── Función helper: verificar si tiene un rol específico ──
function esRol(string ...$roles): bool {
    return in_array($_SESSION['usuario_rol'] ?? '', $roles, true);
}

// ── Función helper: solo administrador ──
function soloAdmin(): void {
    requireRol('administrador');
}