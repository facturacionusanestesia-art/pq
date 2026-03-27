-- ============================================================
-- CREACIÓN DE BASE DE DATOS
-- ============================================================
-- DROP DATABASE IF EXISTS pq2;
-- CREATE DATABASE pq2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pq2;

-- ============================================================
-- TABLA: clinicas
-- ============================================================
CREATE TABLE clinicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    codigo VARCHAR(10) NOT NULL UNIQUE
);

INSERT INTO clinicas (nombre, codigo) VALUES
('MCAN', 'MCAN'),
('QUIR', 'QUIR'),
('MIR',  'MIR'),
('FLO',  'FLO'),
('MONT', 'MONT');

-- ============================================================
-- TABLA: turnos
-- ============================================================
CREATE TABLE turnos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dia VARCHAR(20) NOT NULL,
    franja ENUM('mañana','tarde') NOT NULL
);

INSERT INTO turnos (dia, franja) VALUES
('Lunes', 'mañana'), ('Lunes', 'tarde'),
('Martes', 'mañana'), ('Martes', 'tarde'),
('Miércoles', 'mañana'), ('Miércoles', 'tarde'),
('Jueves', 'mañana'), ('Jueves', 'tarde'),
('Viernes', 'mañana'), ('Viernes', 'tarde'),
('Sábado', 'mañana'), ('Sábado', 'tarde'),
('Domingo', 'mañana'), ('Domingo', 'tarde');

-- ============================================================
-- TABLA: profesionales
-- ============================================================
CREATE TABLE profesionales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    tipo ENUM('cirujano','anestesista') NOT NULL
);

INSERT INTO profesionales (nombre, tipo) VALUES
('Sola', 'cirujano'),
('Garrido', 'cirujano'),
('Gros', 'cirujano'),
('Deus', 'cirujano'),
('Cuenca', 'cirujano'),
('Domingo', 'cirujano'),

('Arauzo', 'anestesista'),
('Bes', 'anestesista'),
('Arroyo', 'anestesista'),
('Aspiroz', 'anestesista'),
('Céspedes', 'anestesista'),
('Consuegra', 'anestesista'),
('Izuzquiza', 'anestesista'),
('Lloreda', 'anestesista'),
('Ortiz', 'anestesista');

-- ============================================================
-- TABLA: reservas
-- ============================================================
CREATE TABLE reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turno_id INT NOT NULL,
    clinica_id INT NOT NULL,
    estado ENUM('empty','partial','full') NOT NULL DEFAULT 'empty',
    UNIQUE KEY unique_reserva (turno_id, clinica_id),
    FOREIGN KEY (turno_id) REFERENCES turnos(id) ON DELETE CASCADE,
    FOREIGN KEY (clinica_id) REFERENCES clinicas(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLA: reserva_profesionales
-- ============================================================
CREATE TABLE reserva_profesionales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reserva_id INT NOT NULL,
    profesional_id INT NOT NULL,
    FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE CASCADE,
    FOREIGN KEY (profesional_id) REFERENCES profesionales(id) ON DELETE CASCADE
);

-- ============================================================
-- DATOS DE EJEMPLO (turnos-random.sql integrado)
-- ============================================================

-- Crear reservas para todos los turnos y clínicas
INSERT INTO reservas (turno_id, clinica_id, estado)
SELECT t.id, c.id,
       CASE WHEN RAND() < 0.8 THEN 'full' ELSE 'partial' END
FROM turnos t
CROSS JOIN clinicas c;

-- Asignar profesionales aleatorios
INSERT INTO reserva_profesionales (reserva_id, profesional_id)
SELECT r.id, p.id
FROM reservas r
JOIN profesionales p
WHERE p.tipo = 'cirujano'
AND RAND() < 0.4;

INSERT INTO reserva_profesionales (reserva_id, profesional_id)
SELECT r.id, p.id
FROM reservas r
JOIN profesionales p
WHERE p.tipo = 'anestesista'
AND RAND() < 0.4;

-- Limpieza de duplicados y datos inconsistentes
DELETE rp FROM reserva_profesionales rp
LEFT JOIN profesionales p ON rp.profesional_id = p.id
WHERE p.id IS NULL;

DELETE r1 FROM reservas r1
JOIN reservas r2
  ON r1.turno_id = r2.turno_id
 AND r1.clinica_id = r2.clinica_id
 AND r1.id > r2.id;
