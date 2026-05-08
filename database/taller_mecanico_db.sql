-- ============================================================
--  SISTEMA DE GESTIÓN — TALLER MECÁNICO
--  Base de Datos: taller_mecanico_db
--  Grupo: Hambrientos Extremos
-- ============================================================

CREATE DATABASE IF NOT EXISTS taller_mecanico_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE taller_mecanico_db;

-- ─────────────────────────────────────────────────────────────
-- TABLA: categorias
-- ─────────────────────────────────────────────────────────────
CREATE TABLE categorias (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(100) NOT NULL,
  tipo        ENUM('producto','servicio') NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO categorias (nombre, tipo) VALUES
  ('Aceites y Lubricantes',   'producto'),
  ('Filtros',                 'producto'),
  ('Frenos',                  'producto'),
  ('Eléctrico',               'producto'),
  ('Suspensión',              'producto'),
  ('Llantas y Ruedas',        'producto'),
  ('Mantenimiento General',   'servicio'),
  ('Frenos y Suspensión',     'servicio'),
  ('Motor',                   'servicio'),
  ('Eléctrico y Electrónico', 'servicio');

-- ─────────────────────────────────────────────────────────────
-- TABLA: usuarios
-- ─────────────────────────────────────────────────────────────
CREATE TABLE usuarios (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(100) NOT NULL,
  apellido    VARCHAR(100) NOT NULL,
  correo      VARCHAR(150) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  rol         ENUM('administrador','mecanico','cajero') NOT NULL DEFAULT 'mecanico',
  activo      TINYINT(1) DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Password para todos: Admin123! (hash bcrypt)
INSERT INTO usuarios (nombre, apellido, correo, password, rol) VALUES
  ('Admin',    'Sistema',   'admin@taller.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrador'),
  ('Carlos',   'Quispe',    'mecanico@taller.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mecanico'),
  ('Rosa',     'Mamani',    'cajero@taller.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cajero');

-- ─────────────────────────────────────────────────────────────
-- TABLA: clientes
-- ─────────────────────────────────────────────────────────────
CREATE TABLE clientes (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nombre       VARCHAR(150) NOT NULL,
  dni_ruc      VARCHAR(20)  NOT NULL UNIQUE,
  telefono     VARCHAR(20),
  correo       VARCHAR(150),
  direccion    VARCHAR(250),
  tipo         ENUM('natural','empresa') NOT NULL DEFAULT 'natural',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO clientes (nombre, dni_ruc, telefono, correo, tipo) VALUES
  ('Juan Pérez López',       '12345678', '987654321', 'juan@correo.com', 'natural'),
  ('María García Huanca',    '87654321', '912345678', 'maria@correo.com','natural'),
  ('Transportes Andinos SAC','20123456789','984123456','info@tandinos.com','empresa');

-- ─────────────────────────────────────────────────────────────
-- TABLA: vehiculos
-- ─────────────────────────────────────────────────────────────
CREATE TABLE vehiculos (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id   INT NOT NULL,
  placa        VARCHAR(10) NOT NULL UNIQUE,
  marca        VARCHAR(80) NOT NULL,
  modelo       VARCHAR(80) NOT NULL,
  anio         YEAR,
  color        VARCHAR(50),
  km_actual    INT DEFAULT 0,
  observaciones TEXT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO vehiculos (cliente_id, placa, marca, modelo, anio, color, km_actual) VALUES
  (1, 'ABC-123', 'Toyota',  'Corolla',  2018, 'Blanco', 85000),
  (2, 'XYZ-456', 'Hyundai', 'Tucson',   2020, 'Gris',   42000),
  (3, 'TRK-789', 'Volvo',   'FH16',     2015, 'Rojo',  320000);

-- ─────────────────────────────────────────────────────────────
-- TABLA: productos
-- ─────────────────────────────────────────────────────────────
CREATE TABLE productos (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  nombre           VARCHAR(150) NOT NULL,
  descripcion      TEXT,
  categoria_id     INT,
  precio_sin_igv   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  precio_con_igv   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  unidad_medida    VARCHAR(30)  DEFAULT 'unidad',
  stock_actual     DECIMAL(10,3) DEFAULT 0,
  stock_minimo     DECIMAL(10,3) DEFAULT 5,
  peso_referencia  DECIMAL(10,3) DEFAULT 0 COMMENT 'Peso en gramos de 1 unidad para IoT',
  activo           TINYINT(1)   DEFAULT 1,
  created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB;

INSERT INTO productos (nombre, categoria_id, precio_sin_igv, precio_con_igv, unidad_medida, stock_actual, stock_minimo, peso_referencia) VALUES
  ('Aceite Motor 10W-40 (1L)',     1, 16.95, 20.00, 'litro',   20,  5,  900),
  ('Aceite Motor 15W-40 (1L)',     1, 14.41, 17.00, 'litro',   15,  5,  900),
  ('Filtro de Aceite Toyota',      2, 12.71, 15.00, 'unidad',  30, 10,  250),
  ('Filtro de Aire Universal',     2, 18.64, 22.00, 'unidad',  20,  8,  350),
  ('Pastillas de Freno Delanteras',3, 42.37, 50.00, 'juego',   12,  4,  800),
  ('Líquido de Frenos DOT4 (500ml)',3,10.17, 12.00, 'frasco',  18,  6,  550),
  ('Bujías NGK (x4)',              4, 33.90, 40.00, 'juego',   25,  8,  120),
  ('Batería 12V 60Ah',             4,169.49,200.00, 'unidad',   8,  2, 15000),
  ('Amortiguador Delantero',       5, 84.75,100.00, 'unidad',  10,  4, 2500),
  ('Llanta 195/65 R15',            6,211.86,250.00, 'unidad',  16,  4, 8000);

-- ─────────────────────────────────────────────────────────────
-- TABLA: servicios
-- ─────────────────────────────────────────────────────────────
CREATE TABLE servicios (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  nombre              VARCHAR(150) NOT NULL,
  descripcion         TEXT,
  categoria_id        INT,
  precio_base         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  duracion_estimada   INT DEFAULT 60 COMMENT 'Minutos estimados',
  activo              TINYINT(1) DEFAULT 1,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (categoria_id) REFERENCES categorias(id)
) ENGINE=InnoDB;

INSERT INTO servicios (nombre, categoria_id, precio_base, duracion_estimada) VALUES
  ('Cambio de Aceite y Filtro',        7,  50.00,  45),
  ('Afinamiento General',              9, 150.00, 120),
  ('Cambio de Pastillas de Freno',     8,  80.00,  60),
  ('Mantenimiento de Frenos Completo', 8, 180.00, 180),
  ('Diagnóstico Electrónico',         10,  60.00,  30),
  ('Cambio de Batería',               10,  25.00,  20),
  ('Rotación de Llantas',              7,  40.00,  30),
  ('Alineación y Balanceo',            8,  70.00,  60),
  ('Revisión General (Pre-viaje)',      7,  80.00,  90),
  ('Cambio de Amortiguadores',         8, 120.00, 120);

-- ─────────────────────────────────────────────────────────────
-- TABLA: ordenes_trabajo
-- ─────────────────────────────────────────────────────────────
CREATE TABLE ordenes_trabajo (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  numero           VARCHAR(20) NOT NULL UNIQUE COMMENT 'Ej: OT-2025-0001',
  cliente_id       INT NOT NULL,
  vehiculo_id      INT NOT NULL,
  mecanico_id      INT,
  fecha_ingreso    DATETIME   DEFAULT CURRENT_TIMESTAMP,
  fecha_estimada   DATE,
  fecha_cierre     DATETIME,
  estado           ENUM('abierta','en_proceso','lista','cobrada','anulada') DEFAULT 'abierta',
  diagnostico      TEXT,
  observaciones    TEXT,
  km_ingreso       INT DEFAULT 0,
  subtotal         DECIMAL(10,2) DEFAULT 0.00,
  igv              DECIMAL(10,2) DEFAULT 0.00,
  total            DECIMAL(10,2) DEFAULT 0.00,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id)  REFERENCES clientes(id),
  FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id),
  FOREIGN KEY (mecanico_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- TABLA: detalle_orden
-- ─────────────────────────────────────────────────────────────
CREATE TABLE detalle_orden (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  orden_id        INT NOT NULL,
  tipo            ENUM('producto','servicio') NOT NULL,
  referencia_id   INT NOT NULL COMMENT 'ID del producto o servicio',
  nombre_item     VARCHAR(150),
  cantidad        DECIMAL(10,3) DEFAULT 1,
  precio_unitario DECIMAL(10,2) DEFAULT 0.00,
  subtotal        DECIMAL(10,2) DEFAULT 0.00,
  FOREIGN KEY (orden_id) REFERENCES ordenes_trabajo(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- TABLA: movimientos_stock
-- ─────────────────────────────────────────────────────────────
CREATE TABLE movimientos_stock (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  producto_id     INT NOT NULL,
  tipo            ENUM('entrada','salida') NOT NULL,
  cantidad        DECIMAL(10,3) NOT NULL,
  peso_registrado DECIMAL(10,3) DEFAULT 0 COMMENT 'Peso leído por sensor IoT',
  usuario_id      INT,
  orden_id        INT,
  fuente          ENUM('iot','manual') DEFAULT 'manual',
  observacion     VARCHAR(250),
  fecha           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (producto_id) REFERENCES productos(id),
  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id),
  FOREIGN KEY (orden_id)    REFERENCES ordenes_trabajo(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- TABLA: comprobantes
-- ─────────────────────────────────────────────────────────────
CREATE TABLE comprobantes (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  orden_id     INT,
  tipo         ENUM('boleta','factura') NOT NULL,
  serie        VARCHAR(5) NOT NULL COMMENT 'Ej: B001 o F001',
  numero       INT NOT NULL,
  cliente_id   INT NOT NULL,
  subtotal     DECIMAL(10,2) DEFAULT 0.00,
  igv          DECIMAL(10,2) DEFAULT 0.00,
  total        DECIMAL(10,2) DEFAULT 0.00,
  estado       ENUM('emitida','anulada') DEFAULT 'emitida',
  pdf_url      VARCHAR(255),
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_serie_numero (serie, numero),
  FOREIGN KEY (orden_id)   REFERENCES ordenes_trabajo(id),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- TABLA: alertas
-- ─────────────────────────────────────────────────────────────
CREATE TABLE alertas (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  tipo        ENUM('stock_bajo','orden_abierta','factura_pendiente','otro') DEFAULT 'otro',
  mensaje     TEXT NOT NULL,
  usuario_id  INT,
  leida       TINYINT(1) DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- VISTA: stock_bajo (productos bajo mínimo)
-- ─────────────────────────────────────────────────────────────
CREATE VIEW vista_stock_bajo AS
  SELECT p.id, p.nombre, p.stock_actual, p.stock_minimo,
         c.nombre AS categoria,
         (p.stock_minimo - p.stock_actual) AS cantidad_faltante
  FROM productos p
  LEFT JOIN categorias c ON p.categoria_id = c.id
  WHERE p.stock_actual <= p.stock_minimo AND p.activo = 1;

-- ─────────────────────────────────────────────────────────────
-- VISTA: resumen_ordenes
-- ─────────────────────────────────────────────────────────────
CREATE VIEW vista_ordenes_activas AS
  SELECT ot.id, ot.numero, ot.estado,
         CONCAT(cl.nombre) AS cliente,
         v.placa, v.marca, v.modelo,
         CONCAT(u.nombre, ' ', u.apellido) AS mecanico,
         ot.fecha_ingreso, ot.fecha_estimada, ot.total
  FROM ordenes_trabajo ot
  LEFT JOIN clientes cl  ON ot.cliente_id  = cl.id
  LEFT JOIN vehiculos v  ON ot.vehiculo_id = v.id
  LEFT JOIN usuarios  u  ON ot.mecanico_id = u.id
  WHERE ot.estado IN ('abierta','en_proceso','lista');

-- ============================================================
-- FIN DEL SCRIPT
-- Importar en phpMyAdmin: Base de datos > Importar > este archivo
-- ============================================================