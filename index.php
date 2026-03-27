<?php
// index.php - pq2 (Backend English / UI Spanish - Mobile Responsive + Restricted Booking)
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

            $pdo = getPDO();

            if ($isAdmin) {
                $data = [
                    'shift_id'     => $shiftId,
                    'clinic_id'    => $clinicId,
                    'surgeons'     => $_POST['surgeons'] ?? [],
                    'anesthetists' => $_POST['anesthetists'] ?? [],
                ];
            } else if ($currentUserId && $currentRole) {
                // Get current assignment
                $stmt = $pdo->prepare("
                    SELECT p.id, p.role 
                    FROM reservation_professionals rp
                    JOIN reservations r ON rp.reservation_id = r.id
                    JOIN professionals p ON rp.professional_id = p.id
                    WHERE r.shift_id = ? AND r.clinic_id = ?
                ");
                $stmt->execute([$shiftId, $clinicId]);
                $current = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $surgeonsIds = array_column(array_filter($current, fn($p) => $p['role']==='surgeon'), 'id');
                $anestsIds   = array_column(array_filter($current, fn($p) => $p['role']==='anesthetist'), 'id');

                $willAttend = ($_POST['will_attend'] ?? 'no') === 'yes';

                if ($currentRole === 'surgeon') {
                    if ($willAttend && count($surgeonsIds) < 2) {
                        if (!in_array($currentUserId, $surgeonsIds)) $surgeonsIds[] = $currentUserId;
                    } else {
                        $surgeonsIds = array_filter($surgeonsIds, fn($id) => $id != $currentUserId);
                    }
                } else {
                    if ($willAttend && count($anestsIds) < 2) {
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

            // Auto status + enforce max 2+2
            $hasS = !empty($data['surgeons']);
            $hasA = !empty($data['anesthetists']);
            $data['status'] = ($hasS && $hasA) ? 'full' : ($hasS || $hasA ? 'partial' : 'empty');

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

$weekDaysES = ['monday'=>'Lunes','tuesday'=>'Martes','wednesday'=>'Miércoles','thursday'=>'Jueves','friday'=>'Viernes','saturday'=>'Sábado','sunday'=>'Domingo'];
$slotLabels = ['morning' => 'MAÑANA', 'afternoon' => 'TARDE'];

$surgeonsList     = getProfessionalsByType('surgeon');
$anesthetistsList = getProfessionalsByType('anesthetist');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planificación Quirúrgica - CiruCall</title>
    <link rel="stylesheet" href="styles.php">
    <style>
        body { font-family: system-ui, sans-serif; margin:0; padding:0; background:#f8fafc; }
        .app { max-width: 1000px; margin: 0 auto; padding: 12px; }
        .day { background: white; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); overflow: hidden; }
        .day-header { display: flex; justify-content: space-between; padding: 12px 16px; background: #f1f5f9; font-weight: 600; }
        .shifts { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 12px; }
        .shift-title { font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 8px; text-align: center; }
        .clinic-card { padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0; cursor: pointer; transition: all 0.2s; }
        .clinic-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.12); }
        .state-empty   { background: #fee2e2; border-color: #ef4444; }
        .state-partial { background: #fef3c7; border-color: #f59e0b; }
        .state-full    { background: #dcfce7; border-color: #16a34a; }
        .my-shift      { border: 3px solid #2563eb !important; box-shadow: 0 0 0 4px rgba(37,99,235,0.2) !important; }
        
        /* Mobile optimizations */
        @media (max-width: 640px) {
            .shifts { grid-template-columns: 1fr; gap: 10px; }
            .clinic-card { padding: 10px; font-size: 0.95rem; }
            .day-header { flex-direction: column; gap: 4px; text-align: center; }
        }

        .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.7); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal { background: white; padding: 24px; border-radius: 16px; width: 90%; max-width: 420px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .yes-no { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 20px; }
        .yes-no button { padding: 14px; font-size: 1.1rem; border: none; border-radius: 10px; cursor: pointer; }
        .yes-btn { background: #16a34a; color: white; }
        .no-btn  { background: #ef4444; color: white; }
        footer { margin-top: 40px; padding: 20px; text-align: center; font-size: 0.9rem; color: #64748b; background: #f8fafc; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="app">

    <header style="margin-bottom:20px;">
        <h1 style="margin:0;">Planificación Quirúrgica</h1>
        <p style="margin:4px 0 0 0;color:#64748b;">Sistema de asignación de turnos semanales</p>
        
        <div style="margin-top:16px;">
            <label>Perfil actual: </label>
            <select onchange="window.location='index.php?profile=' + this.value" style="padding:8px;font-size:1rem;">
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
                        <?php foreach ($slots as $slot): 
                            $hasS = !empty($slot['surgeons']);
                            $hasA = !empty($slot['anesthetists']);
                            $status = ($hasS && $hasA) ? 'full' : ($hasS || $hasA ? 'partial' : 'empty');

                            $selfAssigned = $currentName && (
                                in_array($currentName, $slot['surgeons'] ?? []) || 
                                in_array($currentName, $slot['anesthetists'] ?? [])
                            );
                            $highlight = (!$isAdmin && $selfAssigned) ? ' my-shift' : '';
                        ?>
                        <div class="clinic-card state-<?= $status ?><?= $highlight ?>" 
                             onclick="openBookingModal(<?= (int)$slot['shift_id'] ?>, <?= (int)$slot['clinic_id'] ?>, '<?= addslashes(htmlspecialchars($slot['clinic'])) ?>', <?= htmlspecialchars(json_encode($slot)) ?>)">
                            <strong><?= htmlspecialchars($slot['clinic']) ?></strong><br>
                            Cirujanos: <?= $hasS ? implode(', ', $slot['surgeons']) : '—' ?><br>
                            Anestesistas: <?= $hasA ? implode(', ', $slot['anesthetists']) : '—' ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </main>

    <footer>
        Estados: verde = completo · amarillo = parcial · rojo = vacío · La información se adapta al perfil seleccionado.<br>
        © 2026 CiruCall - Planificación quirúrgica 
        <a href="doc-planificacion.php" style="color:#2563eb;text-decoration:underline;">Documentación técnica del sistema</a>
    </footer>

</div>

<!-- Modal -->
<div id="bookingModal" class="modal-backdrop">
    <div class="modal">
        <h3>Gestionar Reserva</h3>
        <form id="bookingForm" method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" id="modalShiftId" name="shift_id">
            <input type="hidden" id="modalClinicId" name="clinic_id">

            <p><strong id="modalClinicName"></strong> — <span id="modalSlot"></span></p>

            <?php if ($isAdmin): ?>
                <!-- Admin full control -->
                <label>Cirujanos (máx 2)</label><br>
                <select name="surgeons[]" id="modalSurgeons" multiple style="width:100%;height:100px;margin:8px 0;">
                    <?php foreach ($surgeonsList as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select><br><br>

                <label>Anestesistas (máx 2)</label><br>
                <select name="anesthetists[]" id="modalAnesthetists" multiple style="width:100%;height:100px;">
                    <?php foreach ($anesthetistsList as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <!-- Surgeon / Anesthetist restricted booking -->
                <p style="font-size:1.15rem;margin:20px 0 10px 0;">¿Asistiré a este turno?</p>
                <div class="yes-no">
                    <button type="button" class="yes-btn" onclick="setAttendance('yes')">Sí</button>
                    <button type="button" class="no-btn" onclick="setAttendance('no')">No</button>
                </div>
                <input type="hidden" name="will_attend" id="willAttend" value="">
            <?php endif; ?>

            <div style="margin-top:24px;">
                <button type="submit" style="padding:12px 24px;background:#2563eb;color:white;border:none;border-radius:8px;width:100%;font-size:1.05rem;">Guardar</button>
                <button type="button" onclick="closeModal()" style="padding:12px 24px;margin-top:8px;width:100%;background:#e2e8f0;border:none;border-radius:8px;">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentReservationId = null;

function openBookingModal(shiftId, clinicId, clinicName, slotData) {
    currentReservationId = slotData.reservation_id || null;
    document.getElementById('modalShiftId').value = shiftId;
    document.getElementById('modalClinicId').value = clinicId;
    document.getElementById('modalClinicName').textContent = clinicName;
    document.getElementById('modalSlot').textContent = (slotData.slot || '').toUpperCase();

    if (!document.getElementById('willAttend')) {
        // Admin mode - preselect
        const sSel = document.getElementById('modalSurgeons');
        const aSel = document.getElementById('modalAnesthetists');
        Array.from(sSel.options).forEach(opt => opt.selected = (slotData.surgeons || []).includes(opt.text));
        Array.from(aSel.options).forEach(opt => opt.selected = (slotData.anesthetists || []).includes(opt.text));
    }

    document.getElementById('bookingModal').style.display = 'flex';
}

function setAttendance(answer) {
    document.getElementById('willAttend').value = answer;
    document.getElementById('bookingForm').submit();
}

function closeModal() {
    document.getElementById('bookingModal').style.display = 'none';
}
</script>
</body>
</html>