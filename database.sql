-- ============================================================
-- DATABASE CREATION
-- ============================================================
-- DROP DATABASE IF EXISTS pq2;
-- CREATE DATABASE pq2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pq2;

-- Drop old tables if they exist (backup first!)
DROP TABLE IF EXISTS reservation_professionals;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS shifts;
DROP TABLE IF EXISTS clinics;
DROP TABLE IF EXISTS professionals;

-- Professionals
CREATE TABLE professionals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role ENUM('surgeon', 'anesthetist') NOT NULL
);

-- Clinics
CREATE TABLE clinics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

-- Shifts (now date-specific instead of weekday-only)
CREATE TABLE shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_date DATE NOT NULL,
    slot ENUM('morning', 'afternoon') NOT NULL,
    UNIQUE KEY unique_shift (shift_date, slot)
);

-- Reservations
CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    clinic_id INT NOT NULL,
    status ENUM('empty', 'partial', 'full') DEFAULT 'empty',
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (clinic_id) REFERENCES clinics(id) ON DELETE CASCADE,
    UNIQUE KEY unique_reservation (shift_id, clinic_id)
);

-- Reservation professionals (max 2 per role enforced in code)
CREATE TABLE reservation_professionals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    professional_id INT NOT NULL,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (professional_id) REFERENCES professionals(id) ON DELETE CASCADE
);

-- Sample data
INSERT INTO professionals (name, role) VALUES 
('Sola', 'surgeon'), ('Deus', 'surgeon'), ('Cuenca', 'surgeon'), ('Garrido', 'surgeon'), ('Domingo', 'surgeon'),
('Arauzo', 'anesthetist'), ('Lloreda', 'anesthetist'), ('Aspiroz', 'anesthetist'), ('Arroyo', 'anesthetist');

INSERT INTO clinics (name) VALUES 
('MCAN'), ('QUIR'), ('MIR'), ('FLO'), ('MONT');

-- Generate shifts for the next 8 weeks (example)
INSERT INTO shifts (shift_date, slot)
SELECT 
    DATE_ADD('2026-03-23', INTERVAL d DAY) AS shift_date,
    slot
FROM (
    SELECT 0 AS d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
) days
CROSS JOIN (SELECT 'morning' AS slot UNION SELECT 'afternoon') slots
WHERE DAYOFWEEK(DATE_ADD('2026-03-23', INTERVAL d DAY)) BETWEEN 2 AND 7;  -- Monday to Saturday
