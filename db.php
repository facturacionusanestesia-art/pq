<?php
// db.php - pq2-dev: Maximum verbosity for development

ini_set('display_errors', 1);
error_reporting(E_ALL);

function getPDO() {
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
        throw $e;
    }
}

function runVerboseDiagnostics(): array {
    $output = [];
    try {
        $pdo = getPDO();
        $output[] = "PHP Version: " . PHP_VERSION;
        $output[] = "Database: {$pdo->query('SELECT DATABASE()')->fetchColumn()}";
        
        // Check tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $output[] = "Tables found: " . implode(', ', $tables);
        
        if (!in_array('shifts', $tables)) $output[] = "ERROR: 'shifts' table missing!";
        if (!in_array('clinics', $tables)) $output[] = "ERROR: 'clinics' table missing!";
        if (!in_array('reservations', $tables)) $output[] = "ERROR: 'reservations' table missing!";
        
        // Check columns in shifts
        $cols = $pdo->query("DESCRIBE shifts")->fetchAll(PDO::FETCH_COLUMN);
        $output[] = "shifts columns: " . implode(', ', $cols);
        
        // Sample query to see if morning shifts exist
        $countMorning = $pdo->query("SELECT COUNT(*) FROM shifts WHERE slot = 'morning'")->fetchColumn();
        $output[] = "Morning shifts in DB: " . $countMorning;
        
        $countAfternoon = $pdo->query("SELECT COUNT(*) FROM shifts WHERE slot = 'afternoon'")->fetchColumn();
        $output[] = "Afternoon shifts in DB: " . $countAfternoon;
        
    } catch (Exception $e) {
        $output[] = "DIAGNOSTICS ERROR: " . $e->getMessage();
    }
    return $output;
}

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
    
    echo "SQL: <pre>" . htmlspecialchars($sql) . "</pre>";
    
    try {
        $rows = $pdo->query($sql)->fetchAll();
        echo "Rows returned from query: " . count($rows) . "<br>";
        
        if (count($rows) === 0) {
            echo "<span style='color:orange'>Warning: Query returned 0 rows. Check if shifts and clinics tables have data.</span><br>";
        }
        
        // Build the grouped structure
        $temp = [];
        foreach ($rows as $row) {
            $day = $row['day'];
            $slot = $row['slot'];
            $uid = $row['shift_id'] . '_' . $row['clinic_id'];
            
            if (!isset($temp[$day][$slot][$uid])) {
                $temp[$day][$slot][$uid] = [
                    'shift_id' => $row['shift_id'],
                    'clinic_id' => $row['clinic_id'],
                    'clinic' => $row['clinic_label'],
                    'reservation_id' => $row['reservation_id'],
                    'status' => $row['status'] ?? 'empty',
                    'surgeons' => [],
                    'anesthetists' => []
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
        
        echo "Final structured data days: " . count($final) . "<br>";
        echo "</div>";
        return $final;
        
    } catch (Exception $e) {
        echo "<span style='color:red'>Query failed: " . htmlspecialchars($e->getMessage()) . "</span><br>";
        echo "File: " . $e->getFile() . " line " . $e->getLine() . "<br>";
        echo "</div>";
        return [];
    }
}

// Other functions (saveReservation, deleteReservation, getProfessionalsByType) remain the same as previous version but wrapped with similar verbose blocks if needed.
// For brevity they are kept minimal here – add echo blocks inside them the same way if you see errors there.

function getProfessionalsByType(string $type): array {
    try {
        $stmt = getPDO()->prepare("SELECT id, name FROM professionals WHERE role = ? ORDER BY name");
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        echo "<div style='color:red'>getProfessionalsByType failed for role '{$type}': " . $e->getMessage() . "</div>";
        return [];
    }
}