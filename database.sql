CREATE DATABASE IF NOT EXISTS planificacion_quirurgica
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE planificacion_quirurgica;

-- Roles
CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL
);

INSERT INTO roles (nombre) VALUES
('admin'),
('anestesista');

-- Usuarios (simplificado, sin login real)
CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  rol_id INT NOT NULL,
  FOREIGN KEY (rol_id) REFERENCES roles(id)
);

INSERT INTO usuarios (nombre, rol_id) VALUES
('Administrador', 1),
('Arroyo', 2),
('Otro anestesista', 2);

-- Clínicas
CREATE TABLE clinicas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(10) NOT NULL,
  nombre VARCHAR(100) NOT NULL
);

INSERT INTO clinicas (codigo, nombre) VALUES
('MCAN', 'MCAN'),
('QUIR', 'QUIR'),
('MIR', 'MIR'),
('FLO', 'FLO'),
('MONT', 'MONT');

-- Turnos (día + franja)
CREATE TABLE turnos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  dia VARCHAR(20) NOT NULL,          -- Lunes, Martes, ...
  franja ENUM('manana','tarde') NOT NULL
);

-- Crea semana base
INSERT INTO turnos (dia, franja) VALUES
('Lunes','manana'), ('Lunes','tarde'),
('Martes','manana'), ('Martes','tarde'),
('Miércoles','manana'), ('Miércoles','tarde'),
('Jueves','manana'), ('Jueves','tarde'),
('Viernes','manana'), ('Viernes','tarde'),
('Sábado','manana'), ('Sábado','tarde'),
('Domingo','manana'), ('Domingo','tarde');

-- Reservas por turno y clínica
CREATE TABLE reservas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  turno_id INT NOT NULL,
  clinica_id INT NOT NULL,
  estado ENUM('empty','partial','full') NOT NULL DEFAULT 'empty',
  FOREIGN KEY (turno_id) REFERENCES turnos(id),
  FOREIGN KEY (clinica_id) REFERENCES clinicas(id)
);

-- Profesionales (solo nombre + tipo)
CREATE TABLE profesionales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  tipo ENUM('cirujano','anestesista') NOT NULL
);

INSERT INTO profesionales (nombre, tipo) VALUES
('Gros','cirujano'),
('Garrido','cirujano'),
('Domingo','cirujano'),
('Cuenca','cirujano'),
('Sola','cirujano'),
('Deus','cirujano'),
('Lloreda','anestesista'),
('Consuegra','anestesista'),
('Céspedes','anestesista'),
('Arauzo','anestesista'),
('Bes','anestesista'),
('Ortiz','anestesista'),
('Aspiroz','anestesista'),
('Izuzquiza','anestesista'),
('Arroyo','anestesista');

-- Equipo por reserva (relación N:N)
CREATE TABLE reserva_profesionales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reserva_id INT NOT NULL,
  profesional_id INT NOT NULL,
  FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE CASCADE,
  FOREIGN KEY (profesional_id) REFERENCES profesionales(id)
);

-- Ejemplo: Lunes mañana, MCAN, con Arroyo y un cirujano
INSERT INTO reservas (turno_id, clinica_id, estado)
SELECT t.id, c.id, 'full'
FROM turnos t
JOIN clinicas c ON c.codigo = 'MCAN'
WHERE t.dia = 'Lunes' AND t.franja = 'manana'
LIMIT 1;

SET @reserva_id := LAST_INSERT_ID();

INSERT INTO reserva_profesionales (reserva_id, profesional_id)
SELECT @reserva_id, p.id FROM profesionales p
WHERE p.nombre IN ('Arroyo','Gros');


ALTER TABLE turnos CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE clinicas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE reservas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE profesionales CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE reserva_profesionales CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER DATABASE planificacion_quirurgica CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
