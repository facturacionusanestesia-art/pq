<?php
// db.php - pq2 (English + Maximum Verbosity + Protection: max 2 surgeons & 2 anesthetists per reservation)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ====================== DATABASE CONNECTION ======================
function getPDO(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    echo "<div style='background:#fef3c7;padding:10px;margin:10px 0;border:1px solid #f59e0b;'>";
    echo "<strong>DB Connection attempt...</strong><br>";

    $config = require 'config.php';
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset={$config['charset']}";

    echo "DSN: " . htmlspecialchars($dsn) . "<br>";
    echo "User: " . htmlspecialchars($config['db_user']) . "<br>";

    try {
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        echo "<span style='color:green'>✓ PDO Connection successful</span><br>";
        echo "</div>";
        return $pdo;
    } catch (PDOException $e) {
        echo "<span style='color:red'>✗ Connection failed: " . htmlspecialchars($e->getMessage()) . "</span><br>";
        echo "File: " . $e->getFile() . " (line " . $e->getLine() . ")<br>";
        echo "</div>";
        die("Database connection error. Check config.php");
    }
}

// ====================== DIAGNOSTICS ======================
function runVerboseDiagnostics(): array {
    $output = [];
    try {
        $pdo = getPDO();
        $output[] = "PHP Version: " . PHP_VERSION;
        $output[] = "Database: " . $pdo->query('SELECT DATABASE()')->fetchColumn();

        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $output[] = "Tables found: " . implode(', ', $tables);

        $morningCount = $pdo->query("SELECT COUNT(*) FROM shifts WHERE slot = 'morning'")->fetchColumn();
        $output[] = "Morning shifts in DB: " . $morningCount;

        $afternoonCount = $pdo->query("SELECT COUNT(*) FROM shifts WHERE slot = 'afternoon'")->fetchColumn();
        $output[] = "Afternoon shifts in DB: " . $afternoonCount;

    } catch (Exception $e) {
        $output[] = "DIAGNOSTICS ERROR: " . $e->getMessage();
    }
    return $output;
}

// ====================== FETCH SHIFTS ======================
function fetchAllShiftsWithReservations(): array {
    echo "<div style='background:#dbeafe;padding:10px;margin:10px 0;border:1px solid #2563eb;'>";
    echo "<strong>Executing fetchAllShiftsWithReservations()...</strong><br>";

    $pdo = getPDO();

    $sql = "SELECT 
            s.id AS shift_id, s.day, s.slot,
            c.id AS clinic_id, c.name AS clinic_label,
            r.id AS reservation_id, r.status,
            p.name AS professional_name, p.role AS professional_role
        FROM shifts s
        CROSS JOIN clinics c
        LEFT JOIN reservations r ON r.shift_id = s.id AND r.clinic_id = c.id
        LEFT JOIN reservation_professionals rp ON rp.reservation_id = r.id
        LEFT JOIN professionals p ON p.id = rp.professional_id
        ORDER BY FIELD(s.day, 'monday','tuesday','wednesday','thursday','friday','saturday','sunday'), 
                 FIELD(s.slot, 'morning','afternoon'), c.id";

    try {
        $rows = $pdo->query($sql)->fetchAll();
        echo "Rows returned: " . count($rows) . "<br>";

        $temp = [];
        foreach ($rows as $row) {
            $day = $row['day'];
            $slot = $row['slot'];
            $uid = $row['shift_id'] . '_' . $row['clinic_id'];

            if (!isset($temp[$day][$slot][$uid])) {
                $temp[$day][$slot][$uid] = [
                    'shift_id'       => $row['shift_id'],
                    'clinic_id'      => $row['clinic_id'],
                    'clinic'         => $row['clinic_label'],
                    'reservation_id' => $row['reservation_id'],
                    'status'         => $row['status'] ?? 'empty',
                    'surgeons'       => [],
                    'anesthetists'   => []
                ];
            }
            if (!empty($row['professional_name'])) {
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

        echo "Final structured days: " . count($final) . "<br>";
        echo "</div>";
        return $final;

    } catch (Exception $e) {
        echo "<span style='color:red'>Query failed: " . htmlspecialchars($e->getMessage()) . "</span><br>";
        echo "</div>";
        return [];
    }
}

// ====================== PROFESSIONALS ======================
function getProfessionalsByType(string $type): array {
    try {
        $stmt = getPDO()->prepare("SELECT id, name FROM professionals WHERE role = ? ORDER BY name");
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        echo "<div style='color:red'>getProfessionalsByType failed for '{$type}': " . htmlspecialchars($e->getMessage()) . "</div>";
        return [];
    }
}

// ====================== SAVE RESERVATION WITH PROTECTION ======================
function saveReservation(array $data): void {
    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        $shiftId  = $data['shift_id'];
        $clinicId = $data['clinic_id'];

        // Get current reservation or create new
        $stmt = $pdo->prepare("SELECT id FROM reservations WHERE shift_id = ? AND clinic_id = ?");
        $stmt->execute([$shiftId, $clinicId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $reservationId = $existing['id'];
            $pdo->prepare("UPDATE reservations SET status = ? WHERE id = ?")
                ->execute([$data['status'], $reservationId]);
            $pdo->prepare("DELETE FROM reservation_professionals WHERE reservation_id = ?")->execute([$reservationId]);
        } else {
            $pdo->prepare("INSERT INTO reservations (shift_id, clinic_id, status) VALUES (?, ?, ?)")
                ->execute([$shiftId, $clinicId, $data['status']]);
            $reservationId = $pdo->lastInsertId();
        }

        // === PROTECTION: MAX 2 SURGEONS AND 2 ANESTHETISTS ===
        $surgeonsToAdd     = array_slice((array)($data['surgeons'] ?? []), 0, 2);
        $anesthetistsToAdd = array_slice((array)($data['anesthetists'] ?? []), 0, 2);

        if (count($surgeonsToAdd) > 2 || count($anesthetistsToAdd) > 2) {
            throw new Exception("Cannot assign more than 2 surgeons or 2 anesthetists per shift.");
        }

        $insert = $pdo->prepare("INSERT INTO reservation_professionals (reservation_id, professional_id) VALUES (?, ?)");

        foreach ($surgeonsToAdd as $pid) {
            if (!empty($pid)) $insert->execute([$reservationId, $pid]);
        }
        foreach ($anesthetistsToAdd as $pid) {
            if (!empty($pid)) $insert->execute([$reservationId, $pid]);
        }

        $pdo->commit();
        echo "<div style='color:green'>✓ Reservation saved (max 2 per role enforced)</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div style='color:red'>Save failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        throw $e;
    }
}

// ====================== DELETE RESERVATION ======================
function deleteReservation(int $id): void {
    echo "<div style='background:#fee2e2;padding:8px;'>Deleting reservation ID: $id</div>";
    $pdo = getPDO();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM reservation_professionals WHERE reservation_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM reservations WHERE id = ?")->execute([$id]);
        $pdo->commit();
        echo "<div style='color:green'>✓ Reservation $id deleted successfully</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div style='color:red'>Delete failed: " . htmlspecialchars($e->getMessage()) . "</div>";
        throw $e;
    }
}