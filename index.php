<?php
// ========================================================
// index.php - pq2 (Backend & Logic in English / UI in Spanish)
// Footer added + Full Spanish interface
// ========================================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db.php';

// ====================== POST HANDLING ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($_POST['action'] === 'save') {
            $data = [
                'shift_id'     => (int)$_POST['shift_id'],
                'clinic_id'    => (int)$_POST['clinic_id'],
                'status'       => $_POST['status'] ?? 'empty',
                'surgeons'     => $_POST['surgeons'] ?? [],
                'anesthetists' => $_POST['anesthetists'] ?? [],
            ];

            // Enforce business rule (backend still in English)
            if ($data['status'] === 'full' && (empty($data['surgeons']) || empty($data['anesthetists']))) {
                $data['status'] = 'partial';
            }

            saveReservation($data);
        } 
        elseif ($_POST['action'] === 'delete' && function_exists('deleteReservation')) {
            deleteReservation((int)$_POST['reservation_id']);
        }
    } catch (Exception $e) {
        echo "<div style='background:#fee2e2;padding:15px;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    header("Location: index.php");
    exit;
}

// Load data
$weekData = fetchAllShiftsWithReservations();

$weekDays = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
$slotLabels = ['morning' => 'Mañana', 'afternoon' => 'Tarde'];

$surgeons     = getProfessionalsByType('surgeon');
$anesthetists = getProfessionalsByType('anesthetist');
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
        .state-empty   { background: #fee2e2; border-color: #ef4444; }
        .state-partial { background: #fef3c7; border-color: #f59e0b; }
        .state-full    { background: #dcfce7; border-color: #16a34a; }
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
    </header>

    <main>
        <?php foreach ($weekDays as $day): 
            $dayData = $weekData[$day] ?? [];
        ?>
        <div class="day">
            <div class="day-header">
                <h2><?= ucfirst($day) ?></h2>
                <span><?= date('d M Y', strtotime("this $day")) ?></span>
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
                            <div style="padding:20px;background:#fee2e2;border:2px dashed #ef4444;border-radius:8px;">
                                Sin datos para este turno. Verifique la base de datos.
                            </div>
                        <?php else: ?>
                            <?php foreach ($slots as $slot): 
                                $status = $slot['status'] ?? 'empty';
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
                                Estado: <b><?= strtoupper($status === 'full' ? 'Completo' : ($status === 'partial' ? 'Parcial' : 'Vacío')) ?></b><br>
                                Cirujanos: <?= !empty($slot['surgeons']) ? implode(', ', $slot['surgeons']) : '—' ?><br>
                                Anestesistas: <?= !empty($slot['anesthetists']) ? implode(', ', $slot['anesthetists']) : '—' ?>
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

<!-- Modal (in Spanish) -->
<div id="bookingModal" class="modal-backdrop">
    <div class="modal">
        <h3 id="modalTitle">Gestionar Reserva</h3>
        <form id="bookingForm" method="post">
            <input type="hidden" name="action" value="save">
            <input type="hidden" id="modalShiftId" name="shift_id">
            <input type="hidden" id="modalClinicId" name="clinic_id">

            <p><strong id="modalClinicName"></strong> — <span id="modalSlot"></span></p>

            <label>Estado</label><br>
            <select name="status" id="modalStatus" style="width:100%;padding:8px;margin:8px 0;">
                <option value="empty">Vacío</option>
                <option value="partial">Parcial</option>
                <option value="full">Completo</option>
            </select><br><br>

            <label>Cirujanos</label><br>
            <select name="surgeons[]" id="modalSurgeons" multiple style="width:100%;height:110px;margin:8px 0;">
                <?php foreach ($surgeons as $s): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                <?php endforeach; ?>
            </select><br><br>

            <label>Anestesistas</label><br>
            <select name="anesthetists[]" id="modalAnesthetists" multiple style="width:100%;height:110px;margin:8px 0;">
                <?php foreach ($anesthetists as $a): ?>
                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
                <?php endforeach; ?>
            </select><br><br>

            <button type="submit" style="padding:10px 20px;background:#2563eb;color:white;border:none;border-radius:6px;">Guardar</button>
            <button type="button" onclick="closeModal()" style="padding:10px 20px;margin-left:10px;">Cancelar</button>
            <button type="button" id="deleteBtn" onclick="deleteCurrentReservation()" style="padding:10px 20px;margin-left:10px;color:#ef4444;display:none;">Eliminar Reserva</button>
        </form>
    </div>
</div>

<script>
let currentReservationId = null;

function openBookingModal(shiftId, clinicId, clinicName, status, slotData) {
    currentReservationId = slotData.reservation_id || null;

    document.getElementById('modalShiftId').value = shiftId;
    document.getElementById('modalClinicId').value = clinicId;
    document.getElementById('modalClinicName').textContent = clinicName;
    document.getElementById('modalSlot').textContent = (slotData.slot || '').toUpperCase();

    document.getElementById('modalStatus').value = status;

    // Pre-select
    const sSel = document.getElementById('modalSurgeons');
    const aSel = document.getElementById('modalAnesthetists');
    Array.from(sSel.options).forEach(opt => opt.selected = (slotData.surgeons || []).includes(opt.text));
    Array.from(aSel.options).forEach(opt => opt.selected = (slotData.anesthetists || []).includes(opt.text));

    document.getElementById('deleteBtn').style.display = currentReservationId ? 'inline-block' : 'none';
    document.getElementById('bookingModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('bookingModal').style.display = 'none';
}

function deleteCurrentReservation() {
    if (confirm('¿Eliminar esta reserva?')) {
        const form = document.getElementById('bookingForm');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = 'delete';
        form.appendChild(input);

        const rid = document.createElement('input');
        rid.type = 'hidden';
        rid.name = 'reservation_id';
        rid.value = currentReservationId;
        form.appendChild(rid);

        form.submit();
    }
}
</script>
</body>
</html>