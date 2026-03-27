-- ============================================================
-- DATABASE CREATION
-- ============================================================
-- DROP DATABASE IF EXISTS pq2;
-- CREATE DATABASE pq2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pq2;

-- ============================================================
-- TABLE: clinics
-- ============================================================
CREATE TABLE clinics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    code VARCHAR(10) NOT NULL UNIQUE
);

INSERT INTO clinics (name, code) VALUES
('MCAN', 'MCAN'),
('QUIR', 'QUIR'),
('MIR',  'MIR'),
('FLO',  'FLO'),
('MONT', 'MONT');

-- ============================================================
-- TABLE: shifts
-- ============================================================
CREATE TABLE shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day VARCHAR(20) NOT NULL,
    slot ENUM('morning','afternoon') NOT NULL
);

INSERT INTO shifts (day, slot) VALUES
('monday', 'morning'),   ('monday', 'afternoon'),
('tuesday', 'morning'),  ('tuesday', 'afternoon'),
('wednesday', 'morning'),('wednesday', 'afternoon'),
('thursday', 'morning'), ('thursday', 'afternoon'),
('friday', 'morning'),   ('friday', 'afternoon'),
('saturday', 'morning'), ('saturday', 'afternoon'),
('sunday', 'morning'),   ('sunday', 'afternoon');

-- ============================================================
-- TABLE: professionals
-- ============================================================
CREATE TABLE professionals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    role ENUM('surgeon','anesthetist') NOT NULL
);

INSERT INTO professionals (name, role) VALUES
('Sola', 'surgeon'),
('Garrido', 'surgeon'),
('Gros', 'surgeon'),
('Deus', 'surgeon'),
('Cuenca', 'surgeon'),
('Domingo', 'surgeon'),

('Arauzo', 'anesthetist'),
('Bes', 'anesthetist'),
('Arroyo', 'anesthetist'),
('Aspiroz', 'anesthetist'),
('Céspedes', 'anesthetist'),
('Consuegra', 'anesthetist'),
('Izuzquiza', 'anesthetist'),
('Lloreda', 'anesthetist'),
('Ortiz', 'anesthetist');

-- ============================================================
-- TABLE: reservations
-- ============================================================
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    clinic_id INT NOT NULL,
    status ENUM('empty','partial','full') NOT NULL DEFAULT 'empty',
    UNIQUE KEY unique_reservation (shift_id, clinic_id),
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (clinic_id) REFERENCES clinics(id) ON DELETE CASCADE
);

-- ============================================================
-- TABLE: reservation_professionals
-- ============================================================
CREATE TABLE reservation_professionals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    professional_id INT NOT NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE
);

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Create reservations for all shifts and clinics
INSERT INTO reservations (shift_id, clinic_id, status)
SELECT s.id, c.id,
       CASE WHEN RAND() < 0.8 THEN 'full' ELSE 'partial' END
FROM shifts s
CROSS JOIN clinics c;

-- Assign random surgeons
INSERT INTO reservation_professionals (reservation_id, professional_id)
SELECT r.id, p.id
FROM reservations r
JOIN professionals p
WHERE p.role = 'surgeon'
AND RAND() < 0.4;

-- Assign random anesthetists
INSERT INTO reservation_professionals (reservation_id, professional_id)
SELECT r.id, p.id
FROM reservations r
JOIN professionals p
WHERE p.role = 'anesthetist'
AND RAND() < 0.4;

-- Cleanup of duplicates and inconsistent data
DELETE rp FROM reservation_professionals rp
LEFT JOIN professionals p ON rp.professional_id = p.id
WHERE p.id IS NULL;

DELETE r1 FROM reservations r1
JOIN reservations r2
  ON r1.shift_id = r2.shift_id
 AND r1.clinic_id = r2.clinic_id
 AND r1.id > r2.id;

-- Safe cleanup: Remove excess professionals (more than 2 per role per reservation)
DELETE FROM reservation_professionals 
WHERE reservation_id IN (
    SELECT reservation_id 
    FROM (
        SELECT rp.reservation_id, p.role, COUNT(*) as cnt
        FROM reservation_professionals rp
        JOIN professionals p ON rp.professional_id = p.id
        GROUP BY rp.reservation_id, p.role
        HAVING cnt > 2
    ) AS excess
);