<?php
// index.php - FIXED pq2-dev version (March 2026)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<div style='background:#1e40af;color:white;padding:15px;margin-bottom:20px;border-radius:8px;'>";
echo "<h1>Surgical Planning System - pq2-dev (Fixed)</h1>";
echo "<p>Debug mode active • Click any clinic card to book/edit</p>";
echo "</div>";

require_once 'db.php';

// Handle POST (save or delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($_POST['action'] === 'save') {
            saveReservation([
                'shift_id'     => (int)$_POST['shift_id'],
                'clinic_id'    => (int)$_POST['clinic_id'],
                'status'       => $_POST['status'] ?? 'empty',
                'surgeons'     => $_POST['surgeons'] ?? [],
                'anesthetists' => $_POST['anesthetists'] ?? [],
            ]);
            echo "<div style='background:#86efac;padding:10px;'>✓ Reservation saved successfully</div>";
        } elseif ($_POST['action'] === 'delete') {
            deleteReservation((int)$_POST['reservation_id']);
            echo "<div style='background:#86efac;padding:10px;'>✓ Reservation deleted</div>";
        }
    } catch (Exception $e) {
        echo "<div style='background:#fca5a5;padding:15px;'>POST ERROR: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    header("Location: index.php");
    exit;
}

// Diagnostics
$diagnostics = runVerboseDiagnostics();
echo "<div style='background:#f1f5f9;padding:12px;border-radius:6px;margin-bottom:15px;'>";
echo "<strong>Diagnostics:</strong><ul>";
foreach ($diagnostics as $d) echo "<li>" . htmlspecialchars($d) . "</li>";
echo "</ul></div>";

// Load data
$weekData = fetchAllShiftsWithReservations();

$weekDays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
$slotLabels = ['morning' => 'Morning', 'afternoon' => 'Afternoon'];

$profile = 'admin';
$surgeons = getProfessionalsByType('surgeon');
$anesthetists = getProfessionalsByType('anesthetist');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Surgical Planning System</title>
    <link rel="stylesheet" href="styles.php">
    <style>
        .clinic-card { cursor: pointer; padding: 12px; margin: 6px 0; border-radius: 8px; border: 1px solid #e2e8f0; }
        .clinic-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .state-empty  { background: #fee2e2; }
        .state-partial{ background: #fef3c7; }
        .state-full   { background: #dcfce7; }
        .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.6); display:none; align-items:center; justify-content:center; z-index:1000; }
        .modal { background:white; padding:20px; border-radius:12px; width:90%; max-width:480px; }
    </style>
</head>
<body>
<div class="app">

    <header>
        <h1>Surgical Planning System</h1>
        <div class="role-bar">
            Current profile: 
            <strong>Admin (Full Access)</strong>
        </div>
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
                <?php foreach (['morning', 'afternoon'] as $slotKey): 
                    $slots = $dayData[$slotKey] ?? [];
                    $label = $slotLabels[$slotKey];
                ?>
                <div class="shift">
                    <div class="shift-title"><?= $label ?></div>
                    <div class="clinics">
                        <?php if (empty($slots)): ?>
                            <div style="padding:20px;background:#fee2e2;border:2px dashed #ef4444;border-radius:8px;">
                                <strong>No clinics loaded for <?= $label ?> on <?= ucfirst($day) ?></strong><br>
                                Check that your database has data in <strong>shifts</strong> and <strong>clinics</strong> tables.
                            </div>
                        <?php else: ?>
                            <?php foreach ($slots as $slot): 
                                $status = $slot['status'] ?? 'empty';
                            ?>
                            <div class="clinic-card state-<?= $status ?>" 
                                 onclick="openBookingModal(<?= (int)$slot['shift_id'] ?>, 
                                                           <?= (int)$slot['clinic_id'] ?>, 
                                                           '<?= addslashes(htmlspecialchars($slot['clinic'])) ?>', 
                                                           '<?= $status ?>', 
                                                           <?= htmlspecialchars(json_encode($slot)) ?>)">
                                <strong><?= htmlspecialchars($slot['clinic']) ?></strong><br>
                                Status: <span style="font-weight:bold;"><?= strtoupper($status) ?></span><br>
                                Surgeons: <?= implode(', ', $slot['surgeons'] ?: ['—']) ?><br>
                                Anesthetists: <?= implode(', ', $slot['anesthetists'] ?: ['—']) ?>
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

<!-- Modal -->
<div id="bookingModal" class="modal-backdrop">
    <div class="modal">
        <h3 id="modalTitle">Manage Reservation</h3>
        <form id="bookingForm" method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" id="modalShiftId" name="shift_id">
            <input type="hidden" id="modalClinicId" name="clinic_id">

            <p><strong id="modalClinicName"></strong> — <span id="modalSlot"></span></p>

            <label>Status</label><br>
            <select name="status" id="modalStatus">
                <option value="empty">Empty</option>
                <option value="partial">Partially Occupied</option>
                <option value="full">Full</option>
            </select><br><br>

            <label>Surgeons</label><br>
            <select name="surgeons[]" id="modalSurgeons" multiple style="width:100%;height:100px;">
                <?php foreach ($surgeons as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select><br><br>

            <label>Anesthetists</label><br>
            <select name="anesthetists[]" id="modalAnesthetists" multiple style="width:100%;height:100px;">
                <?php foreach ($anesthetists as $a): ?>
                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                <?php endforeach; ?>
            </select><br><br>

            <button type="submit">Save</button>
            <button type="button" onclick="closeModal()">Cancel</button>
            <button type="button" id="deleteBtn" onclick="deleteCurrentReservation()" style="display:none;color:red;">Delete Reservation</button>
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

    // Pre-select
    const sSelect = document.getElementById('modalSurgeons');
    const aSelect = document.getElementById('modalAnesthetists');
    Array.from(sSelect.options).forEach(opt => opt.selected = (slotData.surgeons || []).includes(opt.textContent));
    Array.from(aSelect.options).forEach(opt => opt.selected = (slotData.anesthetists || []).includes(opt.textContent));

    document.getElementById('deleteBtn').style.display = slotData.reservation_id ? 'inline-block' : 'none';
    document.getElementById('bookingModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('bookingModal').style.display = 'none';
}

function deleteCurrentReservation() {
    if (confirm('Delete this reservation?')) {
        const form = document.getElementById('bookingForm');
        const del = document.createElement('input');
        del.type = 'hidden';
        del.name = 'action';
        del.value = 'delete';
        form.appendChild(del);
        form.submit();
    }
}
</script>
</body>
</html>