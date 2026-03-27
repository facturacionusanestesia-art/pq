<?php header("Content-type: text/css"); ?>
/* === COMPLETE STYLES FOR PQ2 ENGLISH VERSION === */

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

* { box-sizing: border-box; margin:0; padding:0; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }

body { background: var(--bg); color: var(--text-main); padding: 12px; }
.app { max-width: 960px; margin: 0 auto; }

header { margin-bottom: 12px; }
header h1 { font-size: 1.4rem; font-weight: 700; }
header p { font-size: 0.9rem; color: var(--text-soft); }

.role-bar { margin-top: 10px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

.day { background: var(--card-bg); border-radius: var(--radius); padding: 10px; margin-bottom: 12px; box-shadow: 0 1px 3px rgba(15,23,42,0.08); }
.day-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.shifts { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }

.shift { border-radius: 8px; border: 1px solid var(--border); padding: 8px; background: #f9fafb; }
.shift-title { font-size: 0.8rem; font-weight: 600; color: var(--text-soft); text-transform: uppercase; }

.clinic-card { border-radius: 6px; padding: 8px; margin-bottom: 6px; cursor: pointer; }
.state-empty  { background: var(--empty);  border: 1px dashed var(--danger); }
.state-partial{ background: var(--partial); border: 1px dashed #f59e0b; }
.state-full   { background: var(--full);    border: 1px solid var(--success); }

.modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,0.5); display: none; align-items: center; justify-content: center; z-index: 1000; }
.modal { background: #fff; border-radius: 12px; padding: 20px; width: 90%; max-width: 460px; box-shadow: 0 10px 25px rgba(0,0,0,0.25); }

/* ... rest of your original styles remain unchanged ... */