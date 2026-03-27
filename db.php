<?php
// src/db.php

function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $config = require __DIR__ . '/config.php';
    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['db_host'], $config['db_name'], $config['charset']);
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

function run_system_diagnostics(): array {
    $errors = []; $info = []; $warnings = [];
    try {
        $pdo = get_pdo();
        $info[] = "PHP Version: " . PHP_VERSION;
        $info[] = "Conexión BD: OK";
        
        $colsC = $pdo->query("DESCRIBE clinicas")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('codigo', $colsC)) $warnings[] = "Usando 'nombre' como identificador de clínica.";
        
        $colsR = $pdo->query("DESCRIBE reservas")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('estado', $colsR)) $errors[] = "Falta columna 'estado' en 'reservas'.";
    } catch (Exception $e) { $errors[] = $e->getMessage(); }
    return ['errors' => $errors, 'info' => $info, 'warnings' => $warnings];
}

function normalizar_dia($dia) {
    $map = ['Miércoles'=>'Miércoles','Miercoles'=>'Miércoles','MiÃ©rcoles'=>'Miércoles','Sábado'=>'Sábado','Sabado'=>'Sábado','SÃ¡bado'=>'Sábado'];
    return $map[$dia] ?? $dia;
}

function fetch_all_turnos_with_reservas(): array {
    $pdo = get_pdo();
    
    // Detectar columnas existentes para evitar errores 500
    $colsClinica = $pdo->query("DESCRIBE clinicas")->fetchAll(PDO::FETCH_COLUMN);
    $colClinica = in_array('codigo', $colsClinica) ? "c.codigo" : "c.nombre";
    
    $colsReservas = $pdo->query("DESCRIBE reservas")->fetchAll(PDO::FETCH_COLUMN);
    $colEstado = in_array('estado', $colsReservas) ? "r.estado" : "'empty'";

    $sql = "SELECT t.id AS turno_id, t.dia, t.franja, 
                   c.id AS clinica_id, $colClinica AS clinica_label,
                   r.id AS reserva_id, $colEstado AS estado,
                   p.nombre AS profesional_nombre, p.tipo AS profesional_tipo
            FROM turnos t
            CROSS JOIN clinicas c
            LEFT JOIN reservas r ON r.turno_id = t.id AND r.clinica_id = c.id
            LEFT JOIN reserva_profesionales rp ON rp.reserva_id = r.id
            LEFT JOIN profesionales p ON p.id = rp.profesional_id
            ORDER BY FIELD(t.dia, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'), t.franja, c.id";

    $rows = $pdo->query($sql)->fetchAll();
    $temp = [];

    foreach ($rows as $row) {
        $dia = normalizar_dia($row['dia']);
        $franja = $row['franja'];
        $uid = $row['turno_id'] . '_' . $row['clinica_id'];

        if (!isset($temp[$dia][$franja][$uid])) {
            $temp[$dia][$franja][$uid] = [
                'turno_id' => $row['turno_id'],
                'clinica_id' => $row['clinica_id'],
                'clinica' => $row['clinica_label'],
                'reserva_id' => $row['reserva_id'],
                'estado' => $row['estado'],
                'cirujanos' => [],
                'anestesistas' => []
            ];
        }
        if ($row['profesional_nombre']) {
            $key = ($row['profesional_tipo'] === 'cirujano') ? 'cirujanos' : 'anestesistas';
            $temp[$dia][$franja][$uid][$key][] = $row['profesional_nombre'];
        }
    }

    $final = [];
    foreach ($temp as $d => $franjas) {
        foreach ($franjas as $f => $slots) {
            $final[$d][$f] = array_values($slots);
        }
    }
    return $final;
}

function get_profesionales_por_tipo($tipo) {
    $stmt = get_pdo()->prepare("SELECT id, nombre FROM profesionales WHERE tipo = ? ORDER BY nombre");
    $stmt->execute([$tipo]);
    return $stmt->fetchAll();
}

function guardar_reserva($data) {
    $pdo = get_pdo();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id FROM reservas WHERE turno_id = ? AND clinica_id = ?");
        $stmt->execute([$data['turno_id'], $data['clinica_id']]);
        $res = $stmt->fetch();

        $cols = $pdo->query("DESCRIBE reservas")->fetchAll(PDO::FETCH_COLUMN);
        $hasEstado = in_array('estado', $cols);

        if ($res) {
            $reserva_id = $res['id'];
            if ($hasEstado) {
                $pdo->prepare("UPDATE reservas SET estado = ? WHERE id = ?")->execute([$data['estado'], $reserva_id]);
            }
            $pdo->prepare("DELETE FROM reserva_profesionales WHERE reserva_id = ?")->execute([$reserva_id]);
        } else {
            if ($hasEstado) {
                $pdo->prepare("INSERT INTO reservas (turno_id, clinica_id, estado) VALUES (?, ?, ?)")
                    ->execute([$data['turno_id'], $data['clinica_id'], $data['estado']]);
            } else {
                $pdo->prepare("INSERT INTO reservas (turno_id, clinica_id) VALUES (?, ?)")
                    ->execute([$data['turno_id'], $data['clinica_id']]);
            }
            $reserva_id = $pdo->lastInsertId();
        }

        $ins = $pdo->prepare("INSERT INTO reserva_profesionales (reserva_id, profesional_id) VALUES (?, ?)");
        $pids = array_unique(array_merge((array)($data['cirujanos']??[]), (array)($data['anestesistas']??[])));
        foreach ($pids as $pid) {
            if (!empty($pid)) $ins->execute([$reserva_id, $pid]);
        }
        $pdo->commit();
    } catch (Exception $e) { $pdo->rollBack(); throw $e; }
}

function eliminar_reserva($id) {
    $pdo = get_pdo();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM reserva_profesionales WHERE reserva_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM reservas WHERE id = ?")->execute([$id]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}