<?php
// index.php - pq2 FINAL VERSION
// Backend + variables + functions + status values = ENGLISH
// Frontend/UI + comments in modal/footer = SPANISH
// Profile-aware highlighting + restricted editing for surgeons/anesthetists

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

// ====================== CURRENT PROFILE ======================
$currentProfile = $_GET['profile'] ?? 'admin';
$isAdmin = $currentProfile === 'admin';

$currentUserId = null;
$currentRole = null;
$currentName = '';

if (preg_match('/^(surgeon|anesthetist)_(\d+)$/', $currentProfile, $matches)) {
    $currentRole = $matches[1];
    $currentUserId = (int)$matches[2];
    $stmt = getPDO()->prepare("SELECT name FROM professionals WHERE id = ?");
    $stmt->execute([$currentUserId]);
    $currentName = $stmt->fetchColumn() ?: '';
}

// ====================== POST HANDLING ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($_POST['action'] === 'save') {
            $shiftId  = (int)$_POST['shift_id'];
            $clinicId = (int)$_POST['clinic_id'];

            if ($isAdmin) {
                // Admin can edit everyone freely
                $data = [
                    'shift_id'     => $shiftId,
                    'clinic_id'    => $clinicId,
                    'surgeons'     => $_POST['surgeons'] ?? [],
                    'anesthetists' => $_POST['anesthetists'] ?? [],
                ];
            } else if ($currentUserId && $currentRole) {
                // Surgeon or Anesthetist can ONLY toggle their own attendance
                $pdo = getPDO();

                // Get current professionals assigned to this shift+clinic
                $stmt = $pdo->prepare("
                    SELECT p.id, p.role 
                    FROM reservation_professionals rp
                    JOIN reservations r ON rp.reservation_id = r.id
                    JOIN professionals p ON rp.professional_id = p.id
                    WHERE r.shift_id = ? AND r.clinic_id = ?
                ");
                $stmt->execute([$shiftId, $clinicId]);
                $currentPros = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $surgeonsIds = [];
                $anestsIds   = [];
                foreach ($currentPros as $p) {
                    if ($p['role'] === 'surgeon') $surgeonsIds[] = $p['id'];
                    if ($p['role'] === 'anesthetist') $anestsIds[] = $p['id'];
                }

                $selfAttendance = !empty($_POST['self_attendance']);

                if ($currentRole === 'surgeon') {
                    if ($selfAttendance) {
                        if (!in_array($currentUserId, $surgeonsIds)) $surgeonsIds[] = $currentUserId;
                    } else {
                        $surgeonsIds = array_filter($surgeonsIds, fn($id) => $id != $currentUserId);
                    }
                } else {
                    if ($selfAttendance) {
                        if (!in_array($currentUserId, $anestsIds)) $anestsIds[] = $currentUserId;
                    } else {
                        $anestsIds = array_filter($anestsIds, fn($id) => $id != $currentUserId);
                    }
                }

                $data = [
                    'shift_id'     => $shiftId,
                    'clinic_id'    => $clinicId,
                    'surgeons'     => array_values($surgeonsIds),
                    'anesthetists' => array_values($anestsIds),
                ];
            } else {
                $data = ['shift_id' => $shiftId, 'clinic_id' => $clinicId, 'surgeons' => [], 'anesthetists' => []];
            }

            // Auto-calculate status (English values)
            $hasSurgeon = !empty($data['surgeons']);
            $hasAnesthetist = !empty($data['anesthetists']);
            $data['status'] = ($hasSurgeon && $hasAnesthetist) ? 'full' : ($hasSurgeon || $hasAnesthetist ? 'partial' : 'empty');

            saveReservation($data);
        } 
        elseif ($_POST['action'] === 'delete' && $isAdmin && function_exists('deleteReservation')) {
            deleteReservation((int)$_POST['reservation_id']);
        }
    } catch (Exception $e) {
        echo "<div style='background:#fee2e2;padding:15px;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    header("Location: index.php?profile=" . urlencode($currentProfile));
    exit;
}

// Load data
$weekData = fetchAllShiftsWithReservations();

$weekDaysES = [
    'monday' => 'Lunes', 'tuesday' => 'Martes', 'wednesday' => 'Miércoles',
    'thursday' => 'Jueves', 'friday' => 'Viernes', 'saturday' => 'Sábado', 'sunday' => 'Domingo'
];

$slotLabels = ['morning' => 'MAÑANA', 'afternoon' => 'TARDE'];

$surgeonsList     = getProfessionalsByType('surgeon');
$anesthetistsList = getProfessionalsByType('anesthetist');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planificación Quirúrgica - CiruCall</title>
    <link rel="stylesheet" href="styles.php">
    <style>
        .clinic-card { cursor: pointer; padding: 12px; margin: 8px 0; border-radius: 8px; border: 1px solid #cbd5e1; }
        .clinic-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
        .state-empty   { background: #fee2e2; border-color: #ef4444; }   /* rojo = vacío */
        .state-partial { background: #fef3c7; border-color: #f59e0b; }   /* amarillo = parcial */
        .state-full    { background: #dcfce7; border-color: #16a34a; }   /* verde = completo */
        .my-shift      { border: 3px solid #2563eb !important; box-shadow: 0 0 12px rgba(37,99,235,0.6) !important; }
        .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.65); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal { background: white; padding: 25px; border-radius: 12px; width: 90%; max-width: 520px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        footer { margin-top: 30px; padding: 15px; text-align: center; font-size: 0.9rem; color: #64748b; background: #f8fafc; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="app">

    <header>
        <h1>Planificación Quirúrgica</h1>
        <p>Sistema de asignación de turnos semanales</p>
        
        <div style="margin:15px 0;">
            <label>Perfil actual: </label>
            <select onchange="window.location='index.php?profile=' + this.value">
                <option value="admin" <?= $isAdmin ? 'selected' : '' ?>>Administrador</option>
                <?php foreach ($surgeonsList as $s): ?>
                <option value="surgeon_<?= $s['id'] ?>" <?= $currentProfile === "surgeon_{$s['id']}" ? 'selected' : '' ?>>
                    Cirujano: <?= htmlspecialchars($s['name']) ?>
                </option>
                <?php endforeach; ?>
                <?php foreach ($anesthetistsList as $a): ?>
                <option value="anesthetist_<?= $a['id'] ?>" <?= $currentProfile === "anesthetist_{$a['id']}" ? 'selected' : '' ?>>
                    Anestesista: <?= htmlspecialchars($a['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </header>

    <main>
        <?php foreach (array_keys($weekDaysES) as $dayKey): 
            $dayData = $weekData[$dayKey] ?? [];
            $dayNameES = $weekDaysES[$dayKey];
        ?>
        <div class="day">
            <div class="day-header">
                <h2><?= $dayNameES ?></h2>
                <span><?= date('d M Y', strtotime("this $dayKey")) ?></span>
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
                            <div style="padding:20px;background:#fee2e2;border:2px dashed #ef4444;">Sin datos para este turno.</div>
                        <?php else: ?>
                            <?php foreach ($slots as $slot): 
                                $hasSurgeon = !empty($slot['surgeons']);
                                $hasAnesthetist = !empty($slot['anesthetists']);
                                $status = ($hasSurgeon && $hasAnesthetist) ? 'full' : ($hasSurgeon || $hasAnesthetist ? 'partial' : 'empty');

                                // Highlight if this professional is assigned (catches attention in one glimpse)
                                $selfAssigned = $currentName && (
                                    in_array($currentName, $slot['surgeons'] ?? []) ||
                                    in_array($currentName, $slot['anesthetists'] ?? [])
                                );
                                $highlightClass = (!$isAdmin && $selfAssigned) ? ' my-shift' : '';
                            ?>
                            <div class="clinic-card state-<?= $status ?><?= $highlightClass ?>" 
                                 onclick="openBookingModal(
                                    <?= (int)$slot['shift_id'] ?>, 
                                    <?= (int)$slot['clinic_id'] ?>, 
                                    '<?= addslashes(htmlspecialchars($slot['clinic'])) ?>', 
                                    <?= htmlspecialchars(json_encode($slot)) ?>, 
                                    <?= $selfAssigned ? 'true' : 'false' ?>
                                 )">
                                <strong><?= htmlspecialchars($slot['clinic']) ?></strong><br>
                                Cirujanos: <?= $hasSurgeon ? implode(', ', $slot['surgeons']) : '—' ?><br>
                                Anestesistas: <?= $hasAnesthetist ? implode(', ', $slot['anesthetists']) : '—' ?>
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

    <!-- Footer exactly as requested -->
    <footer>
        Estados: verde = completo · amarillo = parcial · rojo = vacío · La información se adapta al perfil seleccionado.<br>
        © 2026 CiruCall - Planificación quirúrgica 
        <a href="doc-planificacion.php" style="color:#2563eb;text-decoration:underline;">Documentación técnica del sistema</a>
    </footer>

</div>

<!-- Modal (role-aware) -->
<div id="bookingModal" class="modal-backdrop">
    <div class="modal">
        <h3>Gestionar Reserva</h3>
        <form id="bookingForm" method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" id="modalShiftId" name="shift_id">
            <input type="hidden" id="modalClinicId" name="clinic_id">

            <p><strong id="modalClinicName"></strong> — <span id="modalSlot"></span></p>

            <?php if ($isAdmin): ?>
                <!-- Admin: full editing allowed -->
                <label>Cirujanos</label><br>
                <select name="surgeons[]" id="modalSurgeons" multiple style="width:100%;height:110px;margin:8px 0;">
                    <?php foreach ($surgeonsList as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select><br><br>

                <label>Anestesistas</label><br>
                <select name="anesthetists[]" id="modalAnesthetists" multiple style="width:100%;height:110px;margin:8px 0;">
                    <?php foreach ($anesthetistsList as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <!-- Surgeon / Anesthetist: only self attendance -->
                <label style="font-size:1.1rem;display:block;margin:15px 0;">
                    <input type="checkbox" name="self_attendance" value="1" id="selfAttendance">
                    Asistiré a este turno
                </label>
            <?php endif; ?>

            <button type="submit" style="padding:10px 20px;background:#2563eb;color:white;border:none;border-radius:6px;">Guardar</button>
            <button type="button" onclick="closeModal()" style="padding:10px 20px;margin-left:10px;">Cancelar</button>
            
            <?php if ($isAdmin): ?>
            <button type="button" id="deleteBtn" onclick="deleteCurrentReservation()" style="padding:10px 20px;margin-left:10px;color:#ef4444;">Eliminar Reserva</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
let currentReservationId = null;

function openBookingModal(shiftId, clinicId, clinicName, slotData, isSelfAssigned) {
    currentReservationId = slotData.reservation_id || null;

    document.getElementById('modalShiftId').value = shiftId;
    document.getElementById('modalClinicId').value = clinicId;
    document.getElementById('modalClinicName').textContent = clinicName;
    document.getElementById('modalSlot').textContent = (slotData.slot || '').toUpperCase();

    if (document.getElementById('selfAttendance')) {
        // Non-admin: checkbox for self attendance
        document.getElementById('selfAttendance').checked = !!isSelfAssigned;
    } else {
        // Admin: pre-select full lists
        const sSel = document.getElementById('modalSurgeons');
        const aSel = document.getElementById('modalAnesthetists');
        Array.from(sSel.options).forEach(opt => opt.selected = (slotData.surgeons || []).includes(opt.text));
        Array.from(aSel.options).forEach(opt => opt.selected = (slotData.anesthetists || []).includes(opt.text));
    }

    document.getElementById('bookingModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('bookingModal').style.display = 'none';
}

function deleteCurrentReservation() {
    if (confirm('¿Eliminar esta reserva?')) {
        const form = document.getElementById('bookingForm');
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'action'; input.value = 'delete';
        form.appendChild(input);

        const rid = document.createElement('input');
        rid.type = 'hidden'; rid.name = 'reservation_id'; rid.value = currentReservationId;
        form.appendChild(rid);

        form.submit();
    }
}
</script>
</body>
</html>