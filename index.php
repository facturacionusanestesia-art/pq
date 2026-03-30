<?php
// index.php - Surgical planning main interface (Spanish UI, English code/logs)

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

// ====================== INITIAL LOGGING ======================
debug_log("========== NEW INDEX REQUEST ==========");
debug_log("HTTP method: " . $_SERVER['REQUEST_METHOD']);
debug_log("GET params", $_GET);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    debug_log("POST params", $_POST);
}

// ====================== WEEK NAVIGATION ======================
$offset = isset($_GET['week_offset']) ? (int)$_GET['week_offset'] : 0;

// Use a fixed timezone to avoid DST issues
date_default_timezone_set('Europe/Madrid');
$mondayThisWeek = strtotime('monday this week');
if ($mondayThisWeek === false) {
    $today = new DateTime('today');
    $today->modify('monday this week');
    $mondayThisWeek = $today->getTimestamp();
}
$weekStartStr = date('Y-m-d', $mondayThisWeek + ($offset * 7 * 86400));
debug_log("Calculated week", ['offset' => $offset, 'weekStartStr' => $weekStartStr]);

// ====================== CURRENT PROFILE ======================
$currentProfile = $_GET['profile'] ?? 'admin';
$isAdmin = $currentProfile === 'admin';
debug_log("Current profile", ['profile' => $currentProfile, 'isAdmin' => $isAdmin]);

$currentUserId = null;
$currentRole = null;
$currentName = '';

if (preg_match('/^(surgeon|anesthetist)_(\d+)$/', $currentProfile, $matches)) {
    $currentRole = $matches[1];
    $currentUserId = (int)$matches[2];
    $stmt = getPDO()->prepare("SELECT name FROM professionals WHERE id = ?");
    $stmt->execute([$currentUserId]);
    $currentName = $stmt->fetchColumn() ?: '';
    debug_log("Authenticated professional", ['role' => $currentRole, 'id' => $currentUserId, 'name' => $currentName]);
}

// ====================== POST HANDLING ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($_POST['action'] === 'save') {
            $shiftId  = (int)$_POST['shift_id'];
            $clinicId = (int)$_POST['clinic_id'];
            debug_log("Processing 'save' action", ['shift_id' => $shiftId, 'clinic_id' => $clinicId]);

            if ($isAdmin) {
                $data = [
                    'shift_id'     => $shiftId,
                    'clinic_id'    => $clinicId,
                    'surgeons'     => $_POST['surgeons'] ?? [],
                    'anesthetists' => $_POST['anesthetists'] ?? [],
                ];
                debug_log("Admin save data", $data);
            } else if ($currentUserId && $currentRole) {
                // Get current assignment
                $pdo = getPDO();
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
                debug_log("Current assignment before change", ['surgeons' => $surgeonsIds, 'anesthetists' => $anestsIds]);

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
                debug_log("Data after professional change", $data);
            } else {
                $data = ['shift_id' => $shiftId, 'clinic_id' => $clinicId, 'surgeons' => [], 'anesthetists' => []];
            }

            $hasS = !empty($data['surgeons']);
            $hasA = !empty($data['anesthetists']);
            $data['status'] = ($hasS && $hasA) ? 'full' : ($hasS || $hasA ? 'partial' : 'empty');
            debug_log("Calculated status", $data['status']);

            saveReservation($data);
        } 
        elseif ($_POST['action'] === 'delete' && $isAdmin && function_exists('deleteReservation')) {
            debug_log("Deleting reservation", ['reservation_id' => $_POST['reservation_id']]);
            deleteReservation((int)$_POST['reservation_id']);
        }
    } catch (Exception $e) {
        debug_log("POST ERROR", $e->getMessage());
        echo "<div style='background:#fee2e2;padding:15px;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    header("Location: index.php?profile=" . urlencode($currentProfile) . "&week_offset=" . $offset);
    exit;
}

// Export to Excel (Admin only)
if ($isAdmin && isset($_GET['export']) && $_GET['export'] === 'excel') {
    debug_log("Exporting to Excel for week", $weekStartStr);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="planificacion_quirurgica_' . $weekStartStr . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Fecha', 'Turno', 'Clínica', 'Estado', 'Nº Cirujanos', 'Nº Anestesistas']);

    $weekData = fetchShiftsForWeek($weekStartStr);
    foreach ($weekData as $date => $slots) {
        foreach ($slots as $slotKey => $slotList) {
            foreach ($slotList as $slot) {
                fputcsv($out, [
                    $date,
                    ucfirst($slotKey) == 'Morning' ? 'Mañana' : 'Tarde',
                    $slot['clinic'],
                    strtoupper($slot['status'] ?? 'empty') == 'FULL' ? 'Completo' : (strtoupper($slot['status'] ?? 'empty') == 'PARTIAL' ? 'Parcial' : 'Vacío'),
                    count($slot['surgeons'] ?? []),
                    count($slot['anesthetists'] ?? [])
                ]);
            }
        }
    }
    fclose($out);
    exit;
}

// ====================== LOAD WEEK DATA ======================
debug_log("Calling fetchShiftsForWeek with startDate = $weekStartStr");
$weekData = fetchShiftsForWeek($weekStartStr);

if (empty($weekData)) {
    debug_log("WARNING: weekData is empty. No shifts will be displayed.");
} else {
    $totalCards = 0;
    foreach ($weekData as $date => $slots) {
        foreach ($slots as $slotKey => $slotArray) {
            $totalCards += count($slotArray);
        }
    }
    debug_log("Total clinic cards generated", $totalCards);
}

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
</head>
<body>
<div class="app">

    <header>
        <h1>Planificación Quirúrgica</h1>
        <p>Sistema de asignación de turnos semanales</p>
        
        <div style="margin:15px 0;">
            <label>Perfil actual: </label>
            <select onchange="window.location='index.php?profile=' + this.value + '&week_offset=<?= $offset ?>'">
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

        <?php if ($isAdmin): ?>
        <a href="?export=excel&profile=admin&week_offset=<?= $offset ?>" style="background:#16a34a;color:white;padding:8px 16px;border-radius:6px;text-decoration:none;">Exportar a Excel</a>
        <?php endif; ?>
    </header>

    <div style="text-align:center;margin:15px 0 25px;">
        <a href="index.php?profile=<?= urlencode($currentProfile) ?>&week_offset=<?= $offset - 1 ?>">← Semana anterior</a>
        <strong style="margin:0 20px;"><?= date('d M Y', strtotime($weekStartStr)) ?> - <?= date('d M Y', strtotime($weekStartStr) + 6*86400) ?></strong>
        <a href="index.php?profile=<?= urlencode($currentProfile) ?>&week_offset=<?= $offset + 1 ?>">Semana siguiente →</a>
    </div>

    <main>
        <?php 
        $currentWeekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($weekStartStr . " +$i days"));
            $dayName = date('l', strtotime($date));
            $dayKey = strtolower($dayName);
            $currentWeekDays[$date] = $weekDaysES[$dayKey] ?? ucfirst($dayName);
        }

        foreach ($currentWeekDays as $date => $dayNameES): 
            $dayData = $weekData[$date] ?? [];
        ?>
        <div class="day">
            <div class="day-header">
                <h2><?= $dayNameES ?></h2>
                <span><?= date('d M', strtotime($date)) ?></span>
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
                            <div style="padding:20px;background:#fee2e2;border:2px dashed #ef4444;">Sin turnos generados para esta fecha.</div>
                        <?php else: ?>
                            <?php foreach ($slots as $slot): 
                                $hasS = !empty($slot['surgeons']);
                                $hasA = !empty($slot['anesthetists']);
                                $status = ($hasS && $hasA) ? 'full' : ($hasS || $hasA ? 'partial' : 'empty');

                                $selfAssigned = $currentName && (
                                    in_array($currentName, $slot['surgeons'] ?? []) || 
                                    in_array($currentName, $slot['anesthetists'] ?? [])
                                );
                                $highlight = (!$isAdmin && $selfAssigned) ? ' my-shift' : '';

                                $showNames = $isAdmin || $selfAssigned;
                            ?>
                            <div class="clinic-card state-<?= $status ?><?= $highlight ?>" 
                                 onclick="openBookingModal(<?= (int)$slot['shift_id'] ?>, <?= (int)$slot['clinic_id'] ?>, '<?= addslashes(htmlspecialchars($slot['clinic'])) ?>', <?= htmlspecialchars(json_encode($slot)) ?>)">
                                <strong><?= htmlspecialchars($slot['clinic']) ?></strong><br>
                                Cirujanos: 
                                <?php if ($showNames): ?>
                                    <?= $hasS ? implode(', ', array_map(fn($n) => ($n === $currentName ? "<strong><u>$n</u></strong>" : $n), $slot['surgeons'])) : '—' ?>
                                <?php else: ?>
                                    <?= count($slot['surgeons'] ?? []) ?>
                                <?php endif; ?><br>
                                Anestesistas: 
                                <?php if ($showNames): ?>
                                    <?= $hasA ? implode(', ', array_map(fn($n) => ($n === $currentName ? "<strong><u>$n</u></strong>" : $n), $slot['anesthetists'])) : '—' ?>
                                <?php else: ?>
                                    <?= count($slot['anesthetists'] ?? []) ?>
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

    <footer>
        Estados: verde = completo · amarillo = parcial · rojo = vacío · La información se adapta al perfil seleccionado.<br>
        © 2026 CiruCall - Planificación quirúrgica 
        <a href="doc-planificacion.php" style="color:#2563eb;text-decoration:underline;">Documentación técnica</a>
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
                <label>Cirujanos (máx 2)</label><br>
                <select name="surgeons[]" id="modalSurgeons" multiple style="width:100%;height:110px;margin:10px 0;">
                    <?php foreach ($surgeonsList as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select><br><br>

                <label>Anestesistas (máx 2)</label><br>
                <select name="anesthetists[]" id="modalAnesthetists" multiple style="width:100%;height:110px;">
                    <?php foreach ($anesthetistsList as $a): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <div style="margin-top:20px;">
                    <button type="submit" style="width:100%;padding:12px;background:#2563eb;color:white;border:none;border-radius:8px;">Guardar</button>
                    <button type="button" onclick="closeModal()" style="width:100%;padding:12px;margin-top:8px;background:#e2e8f0;border:none;border-radius:8px;">Cancelar</button>
                </div>
            <?php else: ?>
                <p style="font-size:1.2rem;margin:25px 0 15px 0;text-align:center;">¿Asistiré a este turno?</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <button type="button" onclick="setAttendance('yes')" style="padding:16px;font-size:1.1rem;background:#16a34a;color:white;border:none;border-radius:10px;">Sí</button>
                    <button type="button" onclick="setAttendance('no')"  style="padding:16px;font-size:1.1rem;background:#ef4444;color:white;border:none;border-radius:10px;">No</button>
                </div>
                <input type="hidden" name="will_attend" id="willAttend" value="">
            <?php endif; ?>
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

    if (document.getElementById('modalSurgeons')) {
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