<?php
// index.php - Main Surgical Planning Dashboard (pq2 English version)
require 'db.php';

$diagnostics = runSystemDiagnostics();
$errors   = $diagnostics['errors'];
$warnings = $diagnostics['warnings'];
$info     = $diagnostics['info'];

// Handle CRUD actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        saveReservation([
            'shift_id'     => (int)$_POST['shift_id'],
            'clinic_id'    => (int)$_POST['clinic_id'],
            'status'       => $_POST['status'],
            'surgeons'     => $_POST['surgeons'] ?? [],
            'anesthetists' => $_POST['anesthetists'] ?? [],
        ]);
        header('Location: index.php');
        exit;
    }
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        deleteReservation((int)$_POST['reservation_id']);
        header('Location: index.php');
        exit;
    }
}

$weekData = fetchAllShiftsWithReservations();
$weekDays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
$slotLabels = ['morning' => 'Morning', 'afternoon' => 'Afternoon'];

// Helper functions (English)
function isAnesthetistProfile(string $profile): bool {
    return str_starts_with($profile, 'anesthetist_');
}

function getAnesthetistNameFromProfile(string $profile, array $anesthetists): ?string {
    if (!isAnesthetistProfile($profile)) return null;
    $id = (int) str_replace('anesthetist_', '', $profile);
    foreach ($anesthetists as $a) {
        if ($a['id'] == $id) return $a['name'];
    }
    return null;
}

function canSeeNames(array $reservation, string $profile, array $anesthetists): bool {
    if ($profile === 'admin') return true;
    if (isAnesthetistProfile($profile)) {
        if ($reservation['status'] === 'empty') return true;
        $name = getAnesthetistNameFromProfile($profile, $anesthetists);
        return $name && in_array($name, $reservation['anesthetists'] ?? [], true);
    }
    return false;
}

function canEdit(array $reservation, string $profile, array $anesthetists): bool {
    if ($profile === 'admin') return true;
    if (isAnesthetistProfile($profile)) {
        if ($reservation['status'] === 'empty') return true;
        $name = getAnesthetistNameFromProfile($profile, $anesthetists);
        return $name && in_array($name, $reservation['anesthetists'] ?? [], true);
    }
    return false;
}

function getStatusLabel(string $status): string {
    return [
        'full'    => 'Full',
        'partial' => 'Partially Occupied',
        'empty'   => 'Empty',
    ][$status] ?? 'Empty';
}

function getStatusClass(string $status): string {
    return [
        'full'    => 'state-full',
        'partial' => 'state-partial',
        'empty'   => 'state-empty',
    ][$status] ?? 'state-empty';
}

// Current profile (demo - change as needed)
$profile = 'admin'; // or 'anesthetist_3' etc.
$anesthetists = getProfessionalsByType('anesthetist');
$surgeons     = getProfessionalsByType('surgeon');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Surgical Planning System - pq2</title>
    <link rel="stylesheet" href="styles.php">
</head>
<body>
<div class="app">
    <header>
        <h1>Surgical Planning System</h1>
        <p>Weekly shift planning • Contextual visibility • Full CRUD support</p>
        
        <div class="role-bar">
            <label>Current role:</label>
            <select onchange="location=this.value">
                <option value="index.php" <?= $profile==='admin'?'selected':'' ?>>Admin</option>
                <?php foreach ($anesthetists as $a): ?>
                <option value="index.php" <?= $profile==="anesthetist_{$a['id']}"?'selected':'' ?>>
                    Anesthetist: <?= htmlspecialchars($a['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </header>

    <?php if (!empty($errors) || !empty($warnings)): ?>
    <div style="background:#fee2e2;padding:12px;border-radius:8px;margin-bottom:16px;">
        <strong>Diagnostics:</strong><br>
        <?php foreach ($errors as $e) echo "• Error: $e<br>"; ?>
        <?php foreach ($warnings as $w) echo "• Warning: $w<br>"; ?>
    </div>
    <?php endif; ?>

    <main>
        <?php foreach ($weekDays as $day): 
            $dayData = $weekData[$day] ?? ['morning'=>[], 'afternoon'=>[]];
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
                            <div style="padding:12px;background:#fee2e2;border-radius:6px;font-size:0.8rem;">No data</div>
                        <?php else: ?>
                            <?php foreach ($slots as $slot): ?>
                            <div class="clinic-card <?= getStatusClass($slot['status']) ?>" 
                                 onclick="openBookingModal(<?= $slot['shift_id'] ?>, <?= $slot['clinic_id'] ?>, '<?= $slot['clinic'] ?>', '<?= $slot['status'] ?>', <?= htmlspecialchars(json_encode($slot)) ?>)">
                                <div class="clinic-header">
                                    <div class="clinic-name"><?= htmlspecialchars($slot['clinic']) ?></div>
                                    <span class="clinic-status"><?= getStatusLabel($slot['status']) ?></span>
                                </div>
                                <div class="clinic-body">
                                    <div class="role-line"><strong>Surgeons:</strong> <?= implode(', ', $slot['surgeons'] ?: ['—']) ?></div>
                                    <div class="role-line"><strong>Anesthetists:</strong> <?= implode(', ', $slot['anesthetists'] ?: ['—']) ?></div>
                                </div>
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
<div id="bookingModal" class="modal-backdrop" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Book Shift</h3>
            <button onclick="closeModal()">✕</button>
        </div>
        <form id="bookingForm" method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" id="modalShiftId" name="shift_id">
            <input type="hidden" id="modalClinicId" name="clinic_id">
            
            <div class="modal-body">
                <p><strong id="modalClinicName"></strong> — <span id="modalSlot"></span></p>
                
                <label>Status</label>
                <select name="status" id="modalStatus" style="width:100%;margin-bottom:12px;">
                    <option value="empty">Empty</option>
                    <option value="partial">Partially Occupied</option>
                    <option value="full">Full</option>
                </select>

                <label>Surgeons</label>
                <select name="surgeons[]" id="modalSurgeons" multiple style="width:100%;height:80px;margin-bottom:12px;">
                    <?php foreach ($surgeons as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Anesthetists</label>
                <select name="anesthetists[]" id="modalAnesthetists" multiple style="width:100%;height:80px;">
                    <?php foreach ($anesthetists as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Reservation</button>
                <button type="button" class="btn-danger" onclick="deleteCurrentReservation()" id="deleteBtn" style="display:none;">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
// Modal handling
let currentReservationId = null;

function openBookingModal(shiftId, clinicId, clinicName, status, slotData) {
    currentReservationId = slotData.reservation_id || null;
    document.getElementById('modalShiftId').value = shiftId;
    document.getElementById('modalClinicId').value = clinicId;
    document.getElementById('modalClinicName').textContent = clinicName;
    document.getElementById('modalSlot').textContent = slotData.slot ? slotData.slot.toUpperCase() : '';
    document.getElementById('modalStatus').value = status;

    // Pre-select assigned professionals
    const surgeonSelect = document.getElementById('modalSurgeons');
    const anesthetistSelect = document.getElementById('modalAnesthetists');
    Array.from(surgeonSelect.options).forEach(opt => opt.selected = slotData.surgeons.includes(opt.text));
    Array.from(anesthetistSelect.options).forEach(opt => opt.selected = slotData.anesthetists.includes(opt.text));

    document.getElementById('deleteBtn').style.display = currentReservationId ? 'inline-block' : 'none';
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
        
        const resId = document.createElement('input');
        resId.type = 'hidden';
        resId.name = 'reservation_id';
        resId.value = currentReservationId;
        form.appendChild(resId);
        
        form.submit();
    }
}
</script>
</body>
</html>