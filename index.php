<?php

// Compatibilidad para PHP inferior a 8.0
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

session_start();

require __DIR__ . '/db.php';

$weekDays = ["Lunes","Martes","Miércoles","Jueves","Viernes","Sábado","Domingo"];

// 1) Ejecutar diagnóstico de sistema
$diagnostics = run_system_diagnostics();
$diagErrors = $diagnostics['errors'] ?? [];
$diagWarnings = $diagnostics['warnings'] ?? [];
$diagInfo = $diagnostics['info'] ?? [];

$cirujanos = get_profesionales_por_tipo('cirujano');
$anestesistas = get_profesionales_por_tipo('anestesista');

if (isset($_POST['perfil'])) {
    $_SESSION['perfil'] = $_POST['perfil'];
}
$perfil = $_SESSION['perfil'] ?? 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'guardar') {
        guardar_reserva([
            'turno_id' => (int)$_POST['turno_id'],
            'clinica_id' => (int)$_POST['clinica_id'],
            'estado' => $_POST['estado'],
            'cirujanos' => $_POST['cirujanos'] ?? [],
            'anestesistas' => $_POST['anestesistas'] ?? [],
        ]);
        header('Location: index.php');
        exit;
    }

    if ($_POST['accion'] === 'eliminar') {
        eliminar_reserva((int)$_POST['reserva_id']);
        header('Location: index.php');
        exit;
    }
}

$weekData = fetch_all_turnos_with_reservas();

// 2) Test: ¿hay datos globales?
if (empty($weekData)) {
    $diagErrors[] = "No se ha recibido ningún dato de turnos/clinicas desde la BBDD (weekData está vacío). Revisa la consulta en fetch_all_turnos_with_reservas().";
}

function perfil_es_anestesista(string $perfil): bool {
    return str_starts_with($perfil, 'anest_');
}

function nombre_anestesista_desde_perfil(string $perfil, array $anestesistas): ?string {
    if (!perfil_es_anestesista($perfil)) return null;
    $id = (int) str_replace('anest_', '', $perfil);
    foreach ($anestesistas as $a) {
        if ($a['id'] == $id) return $a['nombre'];
    }
    return null;
}

function can_see_names(array $reserva, string $perfil, array $anestesistas): bool {
    if ($perfil === 'admin') return true;

    if (perfil_es_anestesista($perfil)) {
        if ($reserva['estado'] === 'empty') {
            return true;
        }
        $nombre = nombre_anestesista_desde_perfil($perfil, $anestesistas);
        return $nombre && in_array($nombre, $reserva['anestesistas'] ?? [], true);
    }

    return false;
}

function can_edit(array $reserva, string $perfil, array $anestesistas): bool {
    if ($perfil === 'admin') return true;

    if (perfil_es_anestesista($perfil)) {
        if ($reserva['estado'] === 'empty') {
            return true;
        }
        $nombre = nombre_anestesista_desde_perfil($perfil, $anestesistas);
        return $nombre && in_array($nombre, $reserva['anestesistas'] ?? [], true);
    }

    return false;
}

function estado_label(string $estado): string {
    return [
        'full' => 'Completo',
        'partial' => 'Ocupado parcial',
        'empty' => 'Vacío',
    ][$estado] ?? 'Vacío';
}

function estado_class(string $estado): string {
    return [
        'full' => 'state-full',
        'partial' => 'state-partial',
        'empty' => 'state-empty',
    ][$estado] ?? 'state-empty';
}

// 3) Test: ¿hay al menos un slot visible para el perfil actual?
$visibleSlotsForProfile = 0;
foreach ($weekDays as $dia) {
    $dayData = $weekData[$dia] ?? ['manana' => [], 'tarde' => []];
    foreach (['manana','tarde'] as $key) {
        foreach ($dayData[$key] as $reserva) {
            if (can_see_names($reserva, $perfil, $anestesistas) || can_edit($reserva, $perfil, $anestesistas)) {
                $visibleSlotsForProfile++;
            }
        }
    }
}
if ($visibleSlotsForProfile === 0 && empty($diagErrors)) {
    $diagWarnings[] = "No hay ningún turno visible para el perfil seleccionado ('{$perfil}'). Puede deberse a que todos los turnos estén vacíos o a un problema de asignación de profesionales.";
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planificación personal quirúrgico</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="styles.php">
</head>
<body>
<div class="app">
    <header>
        <h1>Planificación personal quirúrgico</h1>
        <p>Semana completa · Visibilidad contextual y privacidad controlada</p>

        <?php if (!empty($diagErrors) || !empty($diagWarnings) || !empty($diagInfo)): ?>
            <div style="margin-top:8px;padding:8px;border-radius:8px;background:#fef3c7;border:1px solid #f59e0b;font-size:0.8rem;">
                <strong>Diagnóstico al cargar la página:</strong><br>
                <?php if (!empty($diagInfo)): ?>
                    <ul style="margin:4px 0 4px 16px;">
                        <?php foreach ($diagInfo as $msg): ?>
                            <li><?= htmlspecialchars($msg) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (!empty($diagWarnings)): ?>
                    <div style="margin-top:4px;color:#92400e;">
                        <strong>Advertencias:</strong>
                        <ul style="margin:4px 0 4px 16px;">
                            <?php foreach ($diagWarnings as $msg): ?>
                                <li><?= htmlspecialchars($msg) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (!empty($diagErrors)): ?>
                    <div style="margin-top:4px;color:#b91c1c;">
                        <strong>Errores:</strong>
                        <ul style="margin:4px 0 4px 16px;">
                            <?php foreach ($diagErrors as $msg): ?>
                                <li><?= htmlspecialchars($msg) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p>Mientras existan errores, la visualización puede ser incompleta o incorrecta.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" class="role-bar">
            <label><strong>Perfil:</strong></label>
            <select name="perfil" onchange="this.form.submit()">
                <option value="admin" <?= $perfil === 'admin' ? 'selected' : '' ?>>Administrador</option>
                <?php foreach ($anestesistas as $a): ?>
                    <option value="anest_<?= $a['id'] ?>" <?= $perfil === 'anest_'.$a['id'] ? 'selected' : '' ?>>
                        Anestesista: <?= htmlspecialchars($a['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </header>

    <main>
        <?php if (!empty($diagErrors)): ?>
            <p style="margin-top:12px;font-size:0.9rem;color:#b91c1c;">
                Se han detectado errores de datos. Corrígelos en la base de datos y recarga la página.
            </p>
        <?php else: ?>
            <?php foreach ($weekDays as $dia): ?>
                <?php $dayData = $weekData[$dia] ?? ['manana' => [], 'tarde' => []]; ?>
                <section class="day">
                    <div class="day-header">
                        <h2><?= htmlspecialchars($dia) ?></h2>
                        <span>Turnos: mañana / tarde</span>
                    </div>
                    <div class="shifts">
                        <?php foreach (['manana' => 'Mañana', 'tarde' => 'Tarde'] as $key => $label): ?>
                            <div class="shift">
                                <div class="shift-title"><?= $label ?></div>
                                <div class="clinics">
                                    <?php if (empty($dayData[$key])): ?>
                                        <div class="clinic-card state-empty">
                                            Sin datos para este turno (<?= htmlspecialchars($dia) ?> / <?= htmlspecialchars($key) ?>).
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($dayData[$key] as $reserva): ?>
                                            <?php
                                            $seeNames = can_see_names($reserva, $perfil, $anestesistas);
                                            $edit = can_edit($reserva, $perfil, $anestesistas);
                                            $cirCount = count($reserva['cirujanos']);
                                            $anesCount = count($reserva['anestesistas']);
                                            $cirText = $seeNames ? ($cirCount ? implode(', ', $reserva['cirujanos']) : '—') : $cirCount.' cirujano(s)';
                                            $anesText = $seeNames ? ($anesCount ? implode(', ', $reserva['anestesistas']) : '—') : $anesCount.' anestesista(s)';
                                            ?>
                                            <div class="clinic-card <?= estado_class($reserva['estado']) ?>"
                                                 data-dia="<?= htmlspecialchars($dia) ?>"
                                                 data-franja="<?= htmlspecialchars($key) ?>"
                                                 data-turno-id="<?= (int)$reserva['turno_id'] ?>"
                                                 data-clinica-id="<?= (int)$reserva['clinica_id'] ?>"
                                                 data-reserva-id="<?= (int)($reserva['reserva_id'] ?? 0) ?>"
                                                 data-cirujanos="<?= htmlspecialchars(implode('|', $reserva['cirujanos'])) ?>"
                                                 data-anestesistas="<?= htmlspecialchars(implode('|', $reserva['anestesistas'])) ?>"
                                                 onclick="openModalFromCard(this)">
                                                <div class="clinic-header">
                                                    <span class="clinic-name"><?= htmlspecialchars($reserva['clinica']) ?></span>
                                                    <span class="clinic-status"><?= estado_label($reserva['estado']) ?></span>
                                                </div>
                                                <div class="clinic-body">
                                                    <div class="role-line">
                                                        <strong>Cirujanos</strong>
                                                        <span><?= htmlspecialchars($cirText) ?></span>
                                                    </div>
                                                    <div class="role-line">
                                                        <strong>Anestesistas</strong>
                                                        <span><?= htmlspecialchars($anesText) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</div>

<footer style="margin-top:2rem; padding:1rem; text-align:center; font-size:0.9rem; color:#666; border-top:1px solid #ddd;">
    Estados: verde = completo · amarillo = parcial · rojo = vacío · La información se adapta al perfil seleccionado.
    <br>
    &copy; <?php echo date('Y'); ?> CiruCall · Planificación quirúrgica ·
    <a href="doc-planificacion.php">Documentación técnica del sistema</a>
</footer>

<div class="modal-backdrop" id="modalBackdrop">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h3 id="modalTitle">Turno</h3>
            <button type="button" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-actions" id="modalActions"></div>
    </div>
</div>

<script>
    const perfil = "<?= $perfil ?>";
    const anestesistas = <?= json_encode($anestesistas, JSON_UNESCAPED_UNICODE) ?>;
    const cirujanos = <?= json_encode($cirujanos, JSON_UNESCAPED_UNICODE) ?>;

    function closeModal() {
        document.getElementById('modalBackdrop').style.display = 'none';
    }

    function openModalFromCard(card) {
        const dia = card.dataset.dia;
        const franja = card.dataset.franja === 'manana' ? 'Mañana' : 'Tarde';
        const turnoId = card.dataset.turnoId;
        const clinicaId = card.dataset.clinicaId;
        const reservaId = parseInt(card.dataset.reservaId, 10) || 0;
        const clinicaNombre = card.querySelector('.clinic-name').textContent.trim();
        const estadoTexto = card.querySelector('.clinic-status').textContent.trim();

        const cir = card.dataset.cirujanos ? card.dataset.cirujanos.split('|').filter(Boolean) : [];
        const anes = card.dataset.anestesistas ? card.dataset.anestesistas.split('|').filter(Boolean) : [];

        const modalTitle = document.getElementById('modalTitle');
        const modalBody = document.getElementById('modalBody');
        const modalActions = document.getElementById('modalActions');

        modalTitle.textContent = `${dia} · ${franja} · ${clinicaNombre}`;
        modalActions.innerHTML = '';

        const esAnestesista = perfil.startsWith("anest_");
        let miNombre = null;

        if (esAnestesista) {
            const id = perfil.replace("anest_", "");
            const match = anestesistas.find(a => a.id == id);
            miNombre = match ? match.nombre : null;
        }

        if (estadoTexto === 'Vacío') {
            modalBody.innerHTML = `<p>Turno vacío. Puedes reservar este bloque.</p>`;
        } else {
            if (perfil === 'admin' || (esAnestesista && miNombre && anes.includes(miNombre))) {
                modalBody.innerHTML = `
                    <p><strong>Cirujanos:</strong> ${cir.join(', ') || '—'}</p>
                    <p><strong>Anestesistas:</strong> ${anes.join(', ') || '—'}</p>
                `;
            } else {
                modalBody.innerHTML = `
                    <p><strong>Cirujanos:</strong> ${cir.length}</p>
                    <p><strong>Anestesistas:</strong> ${anes.length}</p>
                    <p style="margin-top:4px;">Detalle restringido por privacidad.</p>
                `;
            }
        }

        if (perfil === 'admin' || (esAnestesista && miNombre && anes.includes(miNombre)) || estadoTexto === 'Vacío') {
            const form = document.createElement('form');
            form.method = 'post';

            form.innerHTML = `
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="turno_id" value="${turnoId}">
                <input type="hidden" name="clinica_id" value="${clinicaId}">
                <div style="margin-bottom:8px;">
                    <label><strong>Estado:</strong></label><br>
                    <select name="estado">
                        <option value="empty" ${estadoTexto === 'Vacío' ? 'selected' : ''}>Vacío</option>
                        <option value="partial" ${estadoTexto === 'Ocupado parcial' ? 'selected' : ''}>Ocupado parcial</option>
                        <option value="full" ${estadoTexto === 'Completo' ? 'selected' : ''}>Completo</option>
                    </select>
                </div>
                <div style="margin-bottom:8px;">
                    <label><strong>Cirujanos:</strong></label><br>
                    ${cirujanos.map(c => `
                        <label style="display:block;font-size:0.8rem;">
                            <input type="checkbox" name="cirujanos[]" value="${c.id}"
                                ${cir.includes(c.nombre) ? 'checked' : ''}>
                            ${c.nombre}
                        </label>
                    `).join('')}
                </div>
                <div style="margin-bottom:8px;">
                    <label><strong>Anestesistas:</strong></label><br>
                    ${anestesistas.map(a => `
                        <label style="display:block;font-size:0.8rem;">
                            <input type="checkbox" name="anestesistas[]" value="${a.id}"
                                ${anes.includes(a.nombre) ? 'checked' : ''}>
                            ${a.nombre}
                        </label>
                    `).join('')}
                </div>
            `;

            const btnGuardar = document.createElement('button');
            btnGuardar.type = 'submit';
            btnGuardar.textContent = reservaId ? 'Modificar reserva' : 'Crear reserva';
            btnGuardar.className = 'btn-secondary';
            form.appendChild(btnGuardar);

            modalActions.appendChild(form);

            if (reservaId) {
                const formDel = document.createElement('form');
                formDel.method = 'post';
                formDel.innerHTML = `
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="reserva_id" value="${reservaId}">
                `;
                const btnEliminar = document.createElement('button');
                btnEliminar.type = 'submit';
                btnEliminar.textContent = 'Eliminar reserva';
                btnEliminar.className = 'btn-danger';
                formDel.appendChild(btnEliminar);
                modalActions.appendChild(formDel);
            }
        }

        const btnCerrar = document.createElement('button');
        btnCerrar.type = 'button';
        btnCerrar.textContent = 'Cerrar';
        btnCerrar.className = 'btn-secondary';
        btnCerrar.onclick = closeModal;
        modalActions.appendChild(btnCerrar);

        document.getElementById('modalBackdrop').style.display = 'flex';
    }
</script>

</body>
</html>
