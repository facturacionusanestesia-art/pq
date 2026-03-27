USE planificacion_quirurgica;

/* 1. Crear todas las reservas base (turno × clínica) */
INSERT INTO reservas (turno_id, clinica_id, estado)
SELECT t.id, c.id, 'empty'
FROM turnos t
CROSS JOIN clinicas c;

/* 2. Crear tabla temporal con ~80% de reservas elegidas aleatoriamente */
DROP TEMPORARY TABLE IF EXISTS reservas_ocupadas;

CREATE TEMPORARY TABLE reservas_ocupadas AS
SELECT id
FROM reservas
WHERE RAND() < 0.8;

/* 3. Marcar esas reservas como 'full' */
UPDATE reservas r
JOIN reservas_ocupadas o ON r.id = o.id
SET r.estado = 'full';

/* 4. Asignar un anestesista aleatorio a cada reserva ocupada */
INSERT INTO reserva_profesionales (reserva_id, profesional_id)
SELECT 
    o.id AS reserva_id,
    (
        SELECT p.id 
        FROM profesionales p 
        WHERE p.tipo = 'anestesista'
        ORDER BY RAND()
        LIMIT 1
    ) AS profesional_id
FROM reservas_ocupadas o;

/* 5. Asignar un cirujano aleatorio a cada reserva ocupada */
INSERT INTO reserva_profesionales (reserva_id, profesional_id)
SELECT 
    o.id AS reserva_id,
    (
        SELECT p.id 
        FROM profesionales p 
        WHERE p.tipo = 'cirujano'
        ORDER BY RAND()
        LIMIT 1
    ) AS profesional_id
FROM reservas_ocupadas o;
