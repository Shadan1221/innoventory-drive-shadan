<?php
require_once "../../session.php";
require_once "../../config.php";

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Admin access only
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"]!==true || $_SESSION["role"]!=="admin"){
    header("Location: ../../index.php");
    exit;
}

/* ---------- DATA FETCHING ---------- */

// 1. Status Stats
$stmt1 = $db->prepare("SELECT status, COUNT(*) as count FROM users WHERE status IN ('pending', 'approved', 'denied') GROUP BY status");
$stmt1->execute();
$result1 = $stmt1->get_result();
$status_data = $result1 ? $result1->fetch_all(MYSQLI_ASSOC) : [];
$stmt1->close();

// 2. Role Stats
$stmt2 = $db->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$stmt2->execute();
$result2 = $stmt2->get_result();
$role_data = $result2 ? $result2->fetch_all(MYSQLI_ASSOC) : [];
$stmt2->close();

// 3. Registrations Over Time
$stmt3 = $db->prepare("SELECT DATE(created_at) as reg_date, COUNT(*) as count FROM users GROUP BY DATE(created_at) ORDER BY reg_date ASC LIMIT 30");
$stmt3->execute();
$result3 = $stmt3->get_result();
$line_data = $result3 ? $result3->fetch_all(MYSQLI_ASSOC) : [];
$stmt3->close();

// 4. Detailed User List
$stmt4 = $db->prepare("SELECT id, name, email, role, status FROM users ORDER BY created_at DESC");
$stmt4->execute();
$result4 = $stmt4->get_result();
$all_users = $result4 ? $result4->fetch_all(MYSQLI_ASSOC) : [];
$stmt4->close();

// Total Count for Quick Stats
$total_users = count($all_users);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Analysis - Innoventory</title>
    <link rel="stylesheet" href="../../css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        body, html { margin:0; padding:0; font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); height: 100%; }

        .analysis-wrap { max-width: 1200px; margin: 0 auto; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--panel); padding: 20px; border-radius: 12px; box-shadow: var(--shadow); border: 1px solid var(--border); }
        .stat-card h3 { margin: 0; font-size: 0.875rem; color: var(--muted); text-transform: uppercase; }
        .stat-card p { margin: 10px 0 0 0; font-size: 1.5rem; font-weight: 700; color: var(--accent); }

        /* Charts Layout */
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-bottom: 40px; }
        .chart-card { background: var(--panel); padding: 25px; border-radius: 12px; box-shadow: var(--shadow); border: 1px solid var(--border); }
        .chart-card.full-width { grid-column: span 2; }
        h2 { font-size: 1.25rem; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }

        /* Table Styles */
        .table-container { background: var(--panel); border-radius: 12px; border: 1px solid var(--border); overflow: hidden; }
        table { width:100%; border-collapse:collapse; text-align: left; }
        th { background: var(--accent-soft); padding:14px; font-weight:600; font-size: 0.875rem; color: var(--muted); }
        td { padding:14px; border-top: 1px solid var(--border); font-size: 0.9rem; }
        tr:hover { background: rgba(0,0,0,0.03); }

        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-denied { background: #fee2e2; color: #991b1b; }

        #filterIndicator { font-size: 0.8rem; background: var(--accent); color: white; padding: 4px 12px; border-radius: 4px; display: none; }
        #resetBtn { background: var(--accent-soft); border: 1px solid var(--border); padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: 500; color: var(--text); }
        #resetBtn:hover { background: rgba(37,99,235,0.12); }
    </style>
</head>
<body>

<div class="app-grid">
    <?php include "../../common/menu.php"; ?>
    <?php include "../../common/header.php"; ?>

    <main>
        <div class="dashboard-card analysis-wrap">
            <header style="margin-bottom: 30px;">
                <h1 style="margin:0;">System Analysis</h1>
                <p style="color: var(--muted);">Real-time user metrics and distribution</p>
            </header>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <p><?= $total_users ?></p>
                </div>
                <div class="stat-card">
                    <h3>System Health</h3>
                    <p style="color: var(--success);">Optimal</p>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-card full-width">
                    <h2>Registration Trends (Last 30 Days)</h2>
                    <canvas id="lineChart" style="max-height: 300px;"></canvas>
                </div>

                <div class="chart-card">
                    <h2>Role Distribution</h2>
                    <canvas id="roleChart"></canvas>
                </div>

                <div class="chart-card">
                    <h2>User Status <small style="font-weight:400; color:var(--muted)">(Click segments to filter)</small></h2>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <div class="table-container">
                <div style="padding: 20px; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="margin:0;">User Data <span id="filterIndicator"></span></h2>
                    <button id="resetBtn">Reset View</button>
                </div>
                <table id="userTable">
                    <thead>
                        <tr>
                            <th>ID</th><th>User Name</th><th>Email</th><th>Role</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
// Data from PHP
const allUsers = <?= json_encode($all_users) ?>;
const statusData = <?= json_encode($status_data) ?>;
const roleData = <?= json_encode($role_data) ?>;
const lineData = <?= json_encode($line_data) ?>;

const chartTextColor = getComputedStyle(document.documentElement).getPropertyValue('--text').trim() || '#0f172a';
const chartGridColor = getComputedStyle(document.documentElement).getPropertyValue('--border').trim() || 'rgba(0,0,0,0.1)';
Chart.defaults.color = chartTextColor;

// --- 1. Table Rendering Logic ---
function renderTable(filterType = null, filterValue = null) {
    const tbody = document.querySelector("#userTable tbody");
    const indicator = document.getElementById("filterIndicator");
    tbody.innerHTML = "";
    
    let filtered = allUsers;
    if(filterType && filterValue) {
        filtered = allUsers.filter(u => String(u[filterType]).toLowerCase() === String(filterValue).toLowerCase());
        indicator.innerText = `Filtering by ${filterType}: ${filterValue}`;
        indicator.style.display = "inline-block";
    } else {
        indicator.style.display = "none";
    }

    filtered.forEach(u => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
            <td>#${u.id}</td>
            <td style="font-weight:600;">${u.name}</td>
            <td>${u.email}</td>
            <td>${u.role}</td>
            <td><span class="badge badge-${u.status.toLowerCase()}">${u.status}</span></td>
        `;
        tbody.appendChild(tr);
    });
}

// --- 2. Status Pie Chart ---
const ctxStatus = document.getElementById('statusChart').getContext('2d');

// Color mapping for statuses
const statusColorMap = {
    'approved': '#10b981',  // green
    'denied': '#ef4444',    // red
    'pending': '#3b82f6'    // blue
};

// Map colors based on actual status values
const statusColors = statusData.map(d => statusColorMap[d.status.toLowerCase()] || '#9ca3af');

const statusChart = new Chart(ctxStatus, {
    type: 'doughnut', // Using doughnut for a modern look
    data: {
        labels: statusData.map(d => d.status),
        datasets: [{
            data: statusData.map(d => d.count),
            backgroundColor: statusColors,
            hoverOffset: 15,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        },
        onClick: (evt, elements) => {
            if (elements.length > 0) {
                const idx = elements[0].index;
                renderTable('status', statusChart.data.labels[idx]);
            }
        }
    }
});

// --- 3. Role Bar Chart ---
const ctxRole = document.getElementById('roleChart').getContext('2d');
new Chart(ctxRole, {
    type: 'bar',
    data: {
        labels: roleData.map(d => d.role),
        datasets: [{
            label: 'Users',
            data: roleData.map(d => d.count),
            backgroundColor: '#6366f1',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        scales: {
            x: { grid: { color: chartGridColor } },
            y: { beginAtZero: true, grid: { color: chartGridColor } }
        },
        plugins: { legend: { display: false } },
        onClick: (evt, elements) => {
            if (elements.length > 0) {
                const idx = elements[0].index;
                renderTable('role', roleData[idx].role);
            }
        }
    }
});

// --- 4. Line Chart with Gradient ---
const ctxLine = document.getElementById('lineChart').getContext('2d');
const gradient = ctxLine.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(37, 99, 235, 0.2)');
gradient.addColorStop(1, 'rgba(37, 99, 235, 0)');

new Chart(ctxLine, {
    type: 'line',
    data: {
        labels: lineData.map(d => d.reg_date),
        datasets: [{
            label: 'New Registrations',
            data: lineData.map(d => d.count),
            borderColor: '#2563eb',
            fill: true,
            backgroundColor: gradient,
            tension: 0.4,
            pointBackgroundColor: '#2563eb'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: chartGridColor } },
            y: { beginAtZero: true, grid: { color: chartGridColor } }
        }
    }
});

// --- 5. Initializations ---
document.getElementById("resetBtn").addEventListener("click", () => renderTable());
renderTable(); // First load
</script>

</body>
</html>





