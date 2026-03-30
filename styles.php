<?php
// styles.php - Complete Mobile-Responsive CSS for pq2 Surgical Planning System
header("Content-type: text/css");
?>

:root {
    --primary: #2563eb;
    --success: #16a34a;
    --warning: #f59e0b;
    --danger: #ef4444;
    --bg: #f8fafc;
    --card: #ffffff;
    --text: #111827;
    --text-light: #64748b;
    --border: #e2e8f0;
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.5;
    padding-bottom: 40px;
}

.app {
    max-width: 1100px;
    margin: 0 auto;
    padding: 12px;
}

/* Header */
header {
    margin-bottom: 20px;
    text-align: center;
}

header h1 {
    font-size: 1.85rem;
    font-weight: 700;
    color: #1e3a8a;
    margin-bottom: 8px;
}

header p {
    color: var(--text-light);
    font-size: 1.05rem;
}

/* Profile selector */
header select {
    padding: 10px 14px;
    font-size: 1rem;
    border: 2px solid #cbd5e1;
    border-radius: 8px;
    background: white;
    width: 100%;
    max-width: 320px;
}

/* Week navigation */
.week-nav {
    text-align: center;
    margin: 15px 0 25px;
    font-size: 1rem;
}

.week-nav a {
    color: var(--primary);
    text-decoration: underline;
    margin: 0 12px;
}

/* Day container */
.day {
    background: var(--card);
    border-radius: 14px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
    overflow: hidden;
}

.day-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 18px;
    background: #f1f5f9;
    font-weight: 600;
    font-size: 1.15rem;
    border-bottom: 1px solid var(--border);
}

/* Shifts grid */
.shifts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    padding: 16px;
}

.shift-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text-light);
    text-transform: uppercase;
    text-align: center;
    margin-bottom: 10px;
    letter-spacing: 0.5px;
}

/* Clinic cards */
.clinic-card {
    padding: 14px;
    border-radius: 12px;
    border: 2px solid transparent;
    cursor: pointer;
    transition: all 0.25s ease;
    min-height: 118px;
}

.clinic-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.clinic-card strong {
    font-size: 1.1rem;
    display: block;
    margin-bottom: 8px;
}

/* Status colors */
.state-empty   { background: #fee2e2; border-color: #ef4444; }
.state-partial { background: #fef3c7; border-color: #f59e0b; }
.state-full    { background: #dcfce7; border-color: #16a34a; }

/* Personal shift highlight */
.my-shift {
    border: 3px solid #2563eb !important;
    box-shadow: 0 0 0 5px rgba(37, 99, 235, 0.25) !important;
}

/* Modal */
.modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.75);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal {
    background: white;
    padding: 24px;
    border-radius: 16px;
    width: 92%;
    max-width: 460px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
}

.modal h3 {
    margin-bottom: 18px;
    color: #1e3a8a;
    text-align: center;
}

/* Yes/No buttons for non-admin */
.yes-no-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
    margin: 24px 0 10px 0;
}

.yes-no-container button {
    padding: 16px;
    font-size: 1.15rem;
    font-weight: 600;
    border: none;
    border-radius: 12px;
    cursor: pointer;
}

.yes-btn { background: #16a34a; color: white; }
.no-btn  { background: #ef4444; color: white; }

/* Footer */
footer {
    margin-top: 40px;
    padding: 20px 15px;
    text-align: center;
    font-size: 0.92rem;
    color: var(--text-light);
    background: #f1f5f9;
    border-top: 1px solid #cbd5e1;
    line-height: 1.6;
}

/* ==================== MOBILE RESPONSIVE IMPROVEMENTS ==================== */
@media (max-width: 680px) {
    .app {
        padding: 10px;
    }
    
    header h1 {
        font-size: 1.65rem;
    }
    
    .shifts {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .clinic-card {
        padding: 13px;
        min-height: 110px;
        font-size: 0.97rem;
    }
    
    .day-header {
        flex-direction: column;
        gap: 6px;
        text-align: center;
        padding: 14px;
    }
    
    .modal {
        padding: 22px 18px;
        width: 94%;
    }
    
    .yes-no-container button {
        padding: 15px;
        font-size: 1.1rem;
    }
    
    .week-nav {
        font-size: 0.95rem;
    }
}

/* Extra small screens */
@media (max-width: 480px) {
    header select {
        font-size: 0.95rem;
        padding: 9px 12px;
    }
    
    .clinic-card strong {
        font-size: 1.05rem;
    }
}

/* Button active state */
button:active {
    transform: scale(0.97);
}