<?php
// db.php - Surgical planning database functions with debug logging

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ---------- Debug logging function ----------
function debug_log($message, $data = null) {
    $logEntry = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $logEntry .= " - " . print_r($data, true);
    }
    $logEntry .= PHP_EOL;
    file_put_contents(__DIR__ . '/debug.log', $logEntry, FILE_APPEND);
}
// -------------------------------------------

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $config = require 'config.php';
    debug_log("Loading DB config", [
        'host' => $config['db_host'],
        'dbname' => $config['db_name'],
        'user' => $config['db_user'],
    ]);
    
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset={$config['charset']}";

    try {
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        debug_log("PDO connection established");
        return $pdo;
    } catch (PDOException $e) {
        debug_log("DB connection ERROR", $e->getMessage());
        die("Database connection failed: " . $e->getMessage());
    }
}

function fetchShiftsForWeek(string $startDate): array {
    debug_log("fetchShiftsForWeek - START", ['startDate' => $startDate]);
    
    $pdo = getPDO();
    $sql = "SELECT 
            s.id AS shift_id, 
            s.shift_date,
            s.slot,
            c.id AS clinic_id, 
            c.name AS clinic,
            r.id AS reservation_id, 
            r.status,
            p.name AS professional_name, 
            p.role AS professional_role
        FROM shifts s
        CROSS JOIN clinics c
        LEFT JOIN reservations r ON r.shift_id = s.id AND r.clinic_id = c.id
        LEFT JOIN reservation_professionals rp ON rp.reservation_id = r.id
        LEFT JOIN professionals p ON p.id = rp.professional_id
        WHERE s.shift_date BETWEEN ? AND DATE_ADD(?, INTERVAL 6 DAY)
        ORDER BY s.shift_date, FIELD(s.slot, 'morning','afternoon'), c.name";

    $endDate = date('Y-m-d', strtotime($startDate . ' +6 days'));
    debug_log("SQL query", $sql);
    debug_log("Parameters: startDate = $startDate, calculated endDate = $endDate");

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$startDate, $startDate]);
    
    $rows = $stmt->fetchAll();
    $rowCount = count($rows);
    debug_log("Number of rows returned by query", $rowCount);
    
    if ($rowCount == 0) {
        debug_log("WARNING: Query returned 0 rows. Checking tables...");
        $shiftCount = $pdo->query("SELECT COUNT(*) FROM shifts")->fetchColumn();
        $clinicCount = $pdo->query("SELECT COUNT(*) FROM clinics")->fetchColumn();
        debug_log("Total shifts in table", $shiftCount);
        debug_log("Total clinics in table", $clinicCount);
        
        // Check shifts in the requested week
        $countSql = "SELECT COUNT(*) FROM shifts WHERE shift_date BETWEEN ? AND DATE_ADD(?, INTERVAL 6 DAY)";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute([$startDate, $startDate]);
        $shiftCountWeek = $countStmt->fetchColumn();
        debug_log("Shifts found in the requested week", $shiftCountWeek);
        
        // Show first 5 shifts in DB to see what dates exist
        $sampleShifts = $pdo->query("SELECT shift_date, slot FROM shifts LIMIT 5")->fetchAll();
        debug_log("Sample shifts in DB (first 5)", $sampleShifts);
    }

    // Build the data structure
    $data = [];
    foreach ($rows as $row) {
        $dateStr = $row['shift_date'];
        $slot = $row['slot'];
        $key = $row['shift_id'] . '_' . $row['clinic_id'];

        if (!isset($data[$dateStr][$slot][$key])) {
            $data[$dateStr][$slot][$key] = [
                'shift_id' => $row['shift_id'],
                'shift_date' => $dateStr,
                'slot' => $slot,
                'clinic_id' => $row['clinic_id'],
                'clinic' => $row['clinic'],
                'reservation_id' => $row['reservation_id'],
                'status' => $row['status'] ?? 'empty',
                'surgeons' => [],
                'anesthetists' => []
            ];
        }
        if (!empty($row['professional_name'])) {
            $roleKey = ($row['professional_role'] === 'surgeon') ? 'surgeons' : 'anesthetists';
            $data[$dateStr][$slot][$key][$roleKey][] = $row['professional_name'];
        }
    }

    debug_log("Final \$weekData structure", [
        'dates_with_data' => array_keys($data),
        'total_slots_processed' => count($rows)
    ]);
    
    return $data;
}

function getProfessionalsByType(string $type): array {
    debug_log("getProfessionalsByType called with type = $type");
    $stmt = getPDO()->prepare("SELECT id, name FROM professionals WHERE role = ? ORDER BY name");
    $stmt->execute([$type]);
    $result = $stmt->fetchAll();
    debug_log("Professionals found for $type", count($result));
    return $result;
}

function saveReservation(array $data): void {
    debug_log("saveReservation - START", $data);
    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id FROM reservations WHERE shift_id = ? AND clinic_id = ?");
        $stmt->execute([$data['shift_id'], $data['clinic_id']]);
        $existing = $stmt->fetch();
        debug_log("Existing reservation?", $existing);

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

        // Max 2 per role
        $surgeons = array_slice(array_unique((array)($data['surgeons'] ?? [])), 0, 2);
        $anesthetists = array_slice(array_unique((array)($data['anesthetists'] ?? [])), 0, 2);

        $insert = $pdo->prepare("INSERT INTO reservation_professionals (reservation_id, professional_id) VALUES (?, ?)");
        foreach ($surgeons as $pid) if ($pid) $insert->execute([$reservationId, $pid]);
        foreach ($anesthetists as $pid) if ($pid) $insert->execute([$reservationId, $pid]);

        $pdo->commit();
        debug_log("saveReservation - COMMIT successful", ['reservation_id' => $reservationId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        debug_log("saveReservation - ERROR", $e->getMessage());
        throw $e;
    }
}

function deleteReservation(int $id): void {
    debug_log("deleteReservation called with id = $id");
    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM reservation_professionals WHERE reservation_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM reservations WHERE id = ?")->execute([$id]);
        $pdo->commit();
        debug_log("deleteReservation - successfully deleted");
    } catch (Exception $e) {
        $pdo->rollBack();
        debug_log("deleteReservation - ERROR", $e->getMessage());
        throw $e;
    }
}
?>