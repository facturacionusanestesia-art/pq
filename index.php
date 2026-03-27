<?php
// ========================================================
// index.php - pq2-dev FIXED VERSION (March 2026)
// Fatal error fixed + Full/Partial validation enforced
// ========================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<div style='background:#1e3a8a;color:white;padding:15px;margin-bottom:20px;border-radius:8px;'>";
echo "<h1>Surgical Planning System - pq2-dev (Fixed)</h1>";
echo "<p>Debug mode • Delete function fixed • Full status requires 1 surgeon + 1 anesthetist</p>";
echo "</div>";

require_once 'db.php';

// ====================== POST HANDLING ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<div style='background:#fef3c7;padding:12px;margin:10px 0;border:2px solid #f59e0b;'>";
    echo "<strong>POST Action:</strong> " . htmlspecialchars($_POST['action'] ?? 'unknown') . "<br>";

    try {
        if ($_POST['action'] === 'save') {
            $data = [
                'shift_id'     => (int)$_POST['shift_id'],
                'clinic_id'    => (int)$_POST['clinic_id'],
                'status'       => $_POST['status'] ?? 'empty',
                'surgeons'     => $_POST['surgeons'] ?? [],
                'anesthetists' => $_POST['anesthetists'] ?? [],
            ];

            // ENFORCED RULE: Full status requires at least 1 surgeon + 1 anesthetist
            $hasSurgeon = !empty($data['surgeons']);
            $hasAnesthetist = !empty($data['anesthetists']);

            if ($data['status'] === 'full' && (!$hasSurgeon || !$hasAnesthetist)) {
                $data['status'] = 'partial';
                echo "<span style='color:orange'>⚠ Full status blocked. Changed to Partial because missing surgeon or anesthetist.</span><br>";
            }

            echo "Saving for shift {$data['shift_id']} | clinic {$data['clinic_id']} | status: {$data['status']}<br>";
            saveReservation($data);
            echo "<span style='color:green'>✓ Reservation saved successfully</span>";
        } 
        elseif ($_POST['action'] === 'delete') {
            if (function_exists('deleteReservation')) {
                echo "Deleting reservation ID: " . (int)$_POST['reservation_id'] . "<br>";
                deleteReservation((int)$_POST['reservation_id']);
                echo "<span style='color:green'>✓ Reservation deleted</span>";
            } else {
                echo "<span style='color:red'>deleteReservation() function is missing in db.php</span>";
            }
        }
    } catch (Exception $e) {
        echo "<span style='color:red'>ERROR: " . htmlspecialchars($e->getMessage()) . "</span><br>";
        echo "File: " . $e->getFile() . " (Line " . $e->getLine() . ")";
    }
    echo "</div>";

    header("Location: index.php?saved=1");
    exit;
}

// ====================== DIAGNOSTICS ======================
echo "<h2>Diagnostics</h2>";
$diagnostics = runVerboseDiagnostics();
echo "<div style='background:#f1f5f9;padding:12px;border:1px solid #64748b;border-radius:6px;margin-bottom:20px;'>";
echo "<ul>";
foreach ($diagnostics as $line) echo "<li>" . htmlspecialchars($line) . "</li>";
echo "</ul></div>";

// Load data
$weekData = fetchAllShiftsWithReservations();

$weekDays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
$slotLabels = ['morning' => 'Morning', 'afternoon' => 'Afternoon'];

$surgeons = getProfessionalsByType('surgeon');
$anesthetists = getProfessionalsByType('anesthetist');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Surgical Planning System - pq2-dev</title>
    <link rel="stylesheet" href="styles.php">
    <style>
        .clinic-card { cursor: pointer; padding: 12px; margin: 8px 0; border-radius: 8px; border: 1px solid #cbd5e1; }
        .clinic-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
        .state-empty   { background: #fee2e2; }
        .state-partial { background: #fef3c7; }
        .state-full    { background: #dcfce7; }
        .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.65); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal { background: white; padding: 25px; border-radius: 12px; width: 90%; max-width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    </style>
</head>
<body>
<div class="app">

    <header>
        <h1>Surgical Planning System</h1>
        <p>Weekly shift planning • Full status requires surgeon + anesthetist</p>
    </header>

    <main>
        <?php foreach ($weekDays as $day): 
            $dayData = $weekData[$day] ?? [];
        ?>
        <div class="day">
            <div class="day-header">
                <h2><?= ucfirst($day) ?></h2>
                <span><?= date('M j, Y', strtotime("this $day")) ?></span>
            </div>

            <div class="shifts">
                <?php foreach (['morning','afternoon'] as $slotKey): 
                    $slots = $dayData[$slotKey] ?? [];
                    $label = $slotLabels[$slotKey];
                ?>
                <div class="shift">
                    <div class="shift-title"><?= $label ?></div>
                    <div class="clinics">
                        <?php if (empty($slots)): ?>
                            <div style="padding:20px;background:#fee2e2;border:2px dashed red;">
                                No data for this slot. Check database (shifts + clinics tables).
                            </div>
                        <?php else: ?>
                            <?php foreach ($slots as $slot): 
                                $status = $slot['status'] ?? 'empty';
                                $hasSurgeon = !empty($slot['surgeons']);
                                $hasAnesthetist = !empty($slot['anesthetists']);
                            ?>
                            <div class="clinic-card state-<?= htmlspecialchars($status) ?>" 
                                 onclick="openBookingModal(
                                    <?= (int)$slot['shift_id'] ?>, 
                                    <?= (int)$slot['clinic_id'] ?>, 
                                    '<?= addslashes(htmlspecialchars($slot['clinic'])) ?>', 
                                    '<?= $status ?>', 
                                    <?= htmlspecialchars(json_encode($slot)) ?>
                                 )">
                                <strong><?= htmlspecialchars($slot['clinic']) ?></strong><br>
                                Status: <b><?= strtoupper($status) ?></b><br>
                                Surgeons: <?= $hasSurgeon ? implode(', ', $slot['surgeons']) : '—' ?><br>
                                Anesthetists: <?= $hasAnesthetist ? implode(', ', $slot['anesthetists']) : '—' ?>
                                <?php if ($status === 'full' && (!$hasSurgeon || !$hasAnesthetist)): ?>
                                    <br><span style="color:red;">⚠ Invalid Full status (missing professional)</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </main>
</div>

<!-- Booking Modal -->
<div id="bookingModal" class="modal-backdrop">
    <div class="modal">
        <h3>Manage Reservation</h3>
        <form id="bookingForm" method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" id="modalShiftId" name="shift_id">
            <input type="hidden" id="modalClinicId" name="clinic_id">

            <p><strong id="modalClinicName"></strong> — <span id="modalSlot"></span></p>

            <label>Status</label><br>
            <select name="status" id="modalStatus">
                <option value="empty">Empty</option>
                <option value="partial">Partially Occupied</option>
                <option value="full">Full (requires surgeon + anesthetist)</option>
            </select><br><br>

            <label>Surgeons</label><br>
            <select name="surgeons[]" id="modalSurgeons" multiple style="width:100%;height:110px;">
                <?php foreach ($surgeons as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select><br><br>

            <label>Anesthetists</label><br>
            <select name="anesthetists[]" id="modalAnesthetists" multiple style="width:100%;height:110px;">
                <?php foreach ($anesthetists as $a): ?>
                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                <?php endforeach; ?>
            </select><br><br>

            <button type="submit">Save Reservation</button>
            <button type="button" onclick="closeModal()">Cancel</button>
            <button type="button" id="deleteBtn" onclick="deleteCurrentReservation()" style="color:red;display:none;">Delete Reservation</button>
        </form>
    </div>
</div>

<script>
function openBookingModal(shiftId, clinicId, clinicName, status, slotData) {
    document.getElementById('modalShiftId').value = shiftId;
    document.getElementById('modalClinicId').value = clinicId;
    document.getElementById('modalClinicName').textContent = clinicName;
    document.getElementById('modalSlot').textContent = (slotData.slot || '').toUpperCase();
    document.getElementById('modalStatus').value = status;

    // Pre-select professionals
    const sSel = document.getElementById('modalSurgeons');
    const aSel = document.getElementById('modalAnesthetists');
    Array.from(sSel.options).forEach(opt => opt.selected = (slotData.surgeons || []).includes(opt.text));
    Array.from(aSel.options).forEach(opt => opt.selected = (slotData.anesthetists || []).includes(opt.text));

    document.getElementById('deleteBtn').style.display = slotData.reservation_id ? 'inline-block' : 'none';
    document.getElementById('bookingModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('bookingModal').style.display = 'none';
}

function deleteCurrentReservation() {
    if (confirm('Delete this reservation?')) {
        const form = document.getElementById('bookingForm');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = 'delete';
        form.appendChild(input);

        const rid = document.createElement('input');
        rid.type = 'hidden';
        rid.name = 'reservation_id';
        rid.value = currentReservationId || '';
        form.appendChild(rid);

        form.submit();
    }
}
let currentReservationId = null; // Will be set in openBookingModal if needed
</script>
</body>
</html>