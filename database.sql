-- ============================================================
-- DATABASE CREATION FOR pq2 (Surgical Planning System)
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

-- ============================================================
-- TABLES
-- ============================================================

CREATE TABLE professionals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role ENUM('surgeon', 'anesthetist') NOT NULL
);

CREATE TABLE clinics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_date DATE NOT NULL,
    slot ENUM('morning', 'afternoon') NOT NULL,
    UNIQUE KEY unique_shift (shift_date, slot)
);

CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shift_id INT NOT NULL,
    clinic_id INT NOT NULL,
    status ENUM('empty', 'partial', 'full') DEFAULT 'empty',
    FOREIGN KEY (shift_id) REFERENCES shifts(id) ON DELETE CASCADE,
    FOREIGN KEY (clinic_id) REFERENCES clinics(id) ON DELETE CASCADE,
    UNIQUE KEY unique_reservation (shift_id, clinic_id)
);

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

INSERT INTO professionals (name, role) VALUES 
('Sola', 'surgeon'), ('Deus', 'surgeon'), ('Cuenca', 'surgeon'), ('Garrido', 'surgeon'), ('Domingo', 'surgeon'),
('Arauzo', 'anesthetist'), ('Lloreda', 'anesthetist'), ('Aspiroz', 'anesthetist'), ('Arroyo', 'anesthetist');

INSERT INTO clinics (name) VALUES 
('MCAN'), ('QUIR'), ('MIR'), ('FLO'), ('MONT');

-- ============================================================
-- GENERATE SHIFTS FOR THE ENTIRE YEAR 2026 (Monday to Saturday)
-- ============================================================

INSERT INTO shifts (shift_date, slot)
SELECT 
    DATE_ADD('2026-01-01', INTERVAL n DAY) AS shift_date,
    slot
FROM (
    SELECT 
        a0.d + a1.d*10 + a2.d*100 AS n
    FROM
        (SELECT 0 AS d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a0,
        (SELECT 0 AS d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a1,
        (SELECT 0 AS d UNION SELECT 1 UNION SELECT 2 UNION SELECT 3) a2
    WHERE a0.d + a1.d*10 + a2.d*100 <= 364
) numbers
CROSS JOIN (SELECT 'morning' AS slot UNION SELECT 'afternoon') slots
WHERE DAYOFWEEK(DATE_ADD('2026-01-01', INTERVAL n DAY)) BETWEEN 2 AND 7  -- Monday to Saturday
ORDER BY shift_date, slot;

-- ============================================================
-- BOOTSTRAP RANDOM RESERVATIONS AND ASSIGNMENTS
-- ============================================================
-- This procedure populates about 80% of all (shift, clinic) combinations
-- with random professionals (max 2 surgeons, 2 anesthetists).
-- It also sets the reservation status accordingly.

DROP PROCEDURE IF EXISTS bootstrap_random_reservations;
DELIMITER //

CREATE PROCEDURE bootstrap_random_reservations()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE current_shift_id INT;
    DECLARE current_clinic_id INT;
    DECLARE total_shifts INT;
    DECLARE total_clinics INT;
    DECLARE reservation_exists INT;
    
    -- Variables for random assignment
    DECLARE rand_val DECIMAL(5,4);
    DECLARE assign_surgeons BOOLEAN;
    DECLARE assign_anesthetists BOOLEAN;
    DECLARE num_surgeons INT;
    DECLARE num_anesthetists INT;
    DECLARE new_reservation_id INT;
    DECLARE status_val VARCHAR(10);
    
    -- Cursor over all (shift, clinic) combinations
    DECLARE cur CURSOR FOR
        SELECT s.id, c.id
        FROM shifts s
        CROSS JOIN clinics c
        ORDER BY s.id, c.id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO current_shift_id, current_clinic_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- 80% chance to create a reservation (occupied)
        SET rand_val = RAND();
        IF rand_val <= 0.80 THEN
            -- Determine if it will be full or partial
            -- 60% of occupied become full, 40% partial
            IF RAND() <= 0.60 THEN
                SET assign_surgeons = TRUE;
                SET assign_anesthetists = TRUE;
                SET status_val = 'full';
            ELSE
                -- Partial: randomly choose only surgeons OR only anesthetists
                IF RAND() <= 0.5 THEN
                    SET assign_surgeons = TRUE;
                    SET assign_anesthetists = FALSE;
                    SET status_val = 'partial';
                ELSE
                    SET assign_surgeons = FALSE;
                    SET assign_anesthetists = TRUE;
                    SET status_val = 'partial';
                END IF;
            END IF;
            
            -- Create the reservation
            INSERT INTO reservations (shift_id, clinic_id, status)
            VALUES (current_shift_id, current_clinic_id, status_val);
            SET new_reservation_id = LAST_INSERT_ID();
            
            -- Assign surgeons (1 or 2 randomly)
            IF assign_surgeons THEN
                SET num_surgeons = 1 + FLOOR(RAND() * 2);  -- 1 or 2
                -- Get random surgeon IDs without repetition
                INSERT INTO reservation_professionals (reservation_id, professional_id)
                SELECT new_reservation_id, id
                FROM (
                    SELECT id FROM professionals WHERE role = 'surgeon' ORDER BY RAND() LIMIT num_surgeons
                ) AS random_surgeons;
            END IF;
            
            -- Assign anesthetists (1 or 2 randomly)
            IF assign_anesthetists THEN
                SET num_anesthetists = 1 + FLOOR(RAND() * 2);  -- 1 or 2
                INSERT INTO reservation_professionals (reservation_id, professional_id)
                SELECT new_reservation_id, id
                FROM (
                    SELECT id FROM professionals WHERE role = 'anesthetist' ORDER BY RAND() LIMIT num_anesthetists
                ) AS random_anesthetists;
            END IF;
        END IF;
        -- If rand_val > 0.80, no reservation is created (empty)
    END LOOP;
    
    CLOSE cur;
END //

DELIMITER ;

-- Execute the bootstrap procedure
CALL bootstrap_random_reservations();

-- Clean up (drop the procedure after use)
DROP PROCEDURE IF EXISTS bootstrap_random_reservations;

-- ============================================================
-- FINAL COUNTS (for verification – can be removed in production)
-- ============================================================
SELECT 'Total shifts' AS description, COUNT(*) AS count FROM shifts;
SELECT 'Total clinics' AS description, COUNT(*) AS count FROM clinics;
SELECT 'Total reservations (occupied)' AS description, COUNT(*) AS count FROM reservations;
SELECT 'Empty reservations' AS description, 
       (SELECT COUNT(*) FROM shifts) * (SELECT COUNT(*) FROM clinics) - (SELECT COUNT(*) FROM reservations) AS count;
SELECT 'Reservation professionals assignments' AS description, COUNT(*) AS count FROM reservation_professionals;