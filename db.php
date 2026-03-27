<?php
// db.php - Database functions for pq2 Surgical Planning System

function getPDO() {
    $config = require 'config.php';
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset={$config['charset']}";
    try {
        return new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        die("Connection error: " . $e->getMessage());
    }
}

function runSystemDiagnostics(): array {
    $errors = []; $info = []; $warnings = [];
    try {
        $pdo = getPDO();
        $info[] = "PHP Version: " . PHP_VERSION;
        $info[] = "Database Connection: OK";
        
        $colsClinics = $pdo->query("DESCRIBE clinics")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('name', $colsClinics)) $warnings[] = "Missing 'name' column in clinics table.";
        
        $colsReservations = $pdo->query("DESCRIBE reservations")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('status', $colsReservations)) $errors[] = "Missing 'status' column in reservations table.";
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
    return ['errors' => $errors, 'info' => $info, 'warnings' => $warnings];
}

function fetchAllShiftsWithReservations(): array {
    $pdo = getPDO();
    $sql = "SELECT 
            s.id AS shift_id, 
            s.day, 
            s.slot,
            c.id AS clinic_id, 
            c.name AS clinic_label,
            r.id AS reservation_id, 
            r.status,
            p.name AS professional_name, 
            p.role AS professional_role
        FROM shifts s
        CROSS JOIN clinics c
        LEFT JOIN reservations r ON r.shift_id = s.id AND r.clinic_id = c.id
        LEFT JOIN reservation_professionals rp ON rp.reservation_id = r.id
        LEFT JOIN professionals p ON p.id = rp.professional_id
        ORDER BY FIELD(s.day, 'monday','tuesday','wednesday','thursday','friday','saturday','sunday'), 
                 FIELD(s.slot, 'morning','afternoon'), c.id";

    $rows = $pdo->query($sql)->fetchAll();
    $temp = [];

    foreach ($rows as $row) {
        $day = $row['day'];
        $slot = $row['slot'];
        $uid = $row['shift_id'] . '_' . $row['clinic_id'];

        if (!isset($temp[$day][$slot][$uid])) {
            $temp[$day][$slot][$uid] = [
                'shift_id'     => $row['shift_id'],
                'clinic_id'    => $row['clinic_id'],
                'clinic'       => $row['clinic_label'],
                'reservation_id' => $row['reservation_id'],
                'status'       => $row['status'] ?? 'empty',
                'surgeons'     => [],
                'anesthetists' => []
            ];
        }
        if ($row['professional_name']) {
            $key = ($row['professional_role'] === 'surgeon') ? 'surgeons' : 'anesthetists';
            $temp[$day][$slot][$uid][$key][] = $row['professional_name'];
        }
    }

    $final = [];
    foreach ($temp as $d => $slots) {
        foreach ($slots as $f => $data) {
            $final[$d][$f] = array_values($data);
        }
    }
    return $final;
}

function getProfessionalsByType(string $type): array {
    $stmt = getPDO()->prepare("SELECT id, name FROM professionals WHERE role = ? ORDER BY name");
    $stmt->execute([$type]);
    return $stmt->fetchAll();
}

function saveReservation(array $data): void {
    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id FROM reservations WHERE shift_id = ? AND clinic_id = ?");
        $stmt->execute([$data['shift_id'], $data['clinic_id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $reservationId = $existing['id'];
            $pdo->prepare("UPDATE reservations SET status = ? WHERE id = ?")
                ->execute([$data['status'], $reservationId]);
            $pdo->prepare("DELETE FROM reservation_professionals WHERE reservation_id = ?")->execute([$reservationId]);
        } else {
            $pdo->prepare("INSERT INTO reservations (shift_id, clinic_id, status) VALUES (?, ?, ?)")
                ->execute([$data['shift_id'], $data['clinic_id'], $data['status']]);
            $reservationId = $pdo->lastInsertId();
        }

        $insert = $pdo->prepare("INSERT INTO reservation_professionals (reservation_id, professional_id) VALUES (?, ?)");
        $professionalIds = array_unique(array_merge(
            (array)($data['surgeons'] ?? []),
            (array)($data['anesthetists'] ?? [])
        ));
        foreach ($professionalIds as $pid) {
            if (!empty($pid)) $insert->execute([$reservationId, $pid]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function deleteReservation(int $id): void {
    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM reservation_professionals WHERE reservation_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM reservations WHERE id = ?")->execute([$id]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}