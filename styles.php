<?php
header("Content-Type: text/css; charset=utf-8");
?>

/* === ESTILOS COMPLETOS === */

:root {
  --bg: #f4f6f9;
  --card-bg: #ffffff;
  --primary: #2563eb;
  --primary-soft: #dbeafe;
  --danger: #ef4444;
  --success: #16a34a;
  --border: #e5e7eb;
  --text-main: #111827;
  --text-soft: #6b7280;
  --empty: #fee2e2;
  --partial: #fef3c7;
  --full: #dcfce7;
  --radius: 10px;
}

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
  font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

body {
  background: var(--bg);
  color: var(--text-main);
  padding: 12px;
}

.app {
  max-width: 960px;
  margin: 0 auto;
}

header {
  margin-bottom: 12px;
}

header h1 {
  font-size: 1.2rem;
  font-weight: 700;
  margin-bottom: 4px;
}

header p {
  font-size: 0.85rem;
  color: var(--text-soft);
}

.role-bar {
  margin-top: 10px;
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

.role-bar label {
  font-size: 0.8rem;
  color: var(--text-soft);
}

select, button {
  font-size: 0.85rem;
  padding: 6px 10px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: #fff;
  cursor: pointer;
}

button {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
}

main {
  margin-top: 10px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding-bottom: 60px;
}

.day {
  background: var(--card-bg);
  border-radius: var(--radius);
  padding: 10px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
}

.day-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

.day-header h2 {
  font-size: 0.95rem;
  font-weight: 600;
}

.day-header span {
  font-size: 0.75rem;
  color: var(--text-soft);
}

.shifts {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
}

.shift {
  border-radius: 8px;
  border: 1px solid var(--border);
  padding: 6px;
  background: #f9fafb;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.shift-title {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-soft);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.clinics {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.clinic-card {
  border-radius: 6px;
  padding: 6px;
  font-size: 0.75rem;
  cursor: pointer;
  position: relative;
}

.clinic-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2px;
}

.clinic-name {
  font-weight: 600;
  font-size: 0.8rem;
}

.clinic-status {
  font-size: 0.7rem;
  padding: 2px 6px;
  border-radius: 999px;
  background: #fff;
  border: 1px solid var(--border);
}

.clinic-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.role-line {
  display: flex;
  justify-content: space-between;
  font-size: 0.7rem;
  color: var(--text-soft);
}

.role-line strong {
  color: var(--text-main);
}

.state-empty {
  background: var(--empty);
  border: 1px dashed var(--danger);
}

.state-partial {
  background: var(--partial);
  border: 1px dashed #f59e0b;
}

.state-full {
  background: var(--full);
  border: 1px solid var(--success);
}

.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

.modal {
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  width: 90%;
  max-width: 420px;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.25);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.modal-header h3 {
  font-size: 0.95rem;
  font-weight: 600;
}

.modal-header button {
  background: transparent;
  color: var(--text-soft);
  border: none;
  padding: 0;
  font-size: 1rem;
  cursor: pointer;
}

.modal-body {
  font-size: 0.8rem;
  color: var(--text-soft);
  margin-bottom: 10px;
}

.modal-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  flex-wrap: wrap;
}

.modal-actions button {
  border-radius: 999px;
  padding: 6px 10px;
  font-size: 0.8rem;
}

.btn-secondary {
  background: #fff;
  color: var(--text-main);
  border: 1px solid var(--border);
}

.btn-danger {
  background: var(--danger);
  border-color: var(--danger);
  color: #fff;
}

footer {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 8px 12px;
  background: rgba(248, 250, 252, 0.96);
  border-top: 1px solid var(--border);
  font-size: 0.7rem;
  color: var(--text-soft);
  text-align: center;
}

@media (min-width: 640px) {
  body {
    padding: 20px;
  }
  header h1 {
    font-size: 1.4rem;
  }
}
