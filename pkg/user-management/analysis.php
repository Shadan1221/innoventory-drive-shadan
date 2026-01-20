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
$stmt1 = $db->prepare("SELECT status, COUNT(*) as count FROM users GROUP BY status");
$stmt1->execute();
$status_data = $stmt1->fetchAll(PDO::FETCH_ASSOC);

// 2. Role Stats
$stmt2 = $db->prepare("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$stmt2->execute();
$role_data = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// 3. Registrations Over Time
$stmt3 = $db->prepare("SELECT DATE(created_at) as reg_date, COUNT(*) as count FROM users GROUP BY DATE(created_at) ORDER BY reg_date ASC LIMIT 30");
$stmt3->execute();
$line_data = $stmt3->fetchAll(PDO::FETCH_ASSOC);

// 4. Detailed User List
$stmt4 = $db->prepare("SELECT id, name, email, role, status FROM users ORDER BY created_at DESC");
$stmt4->execute();
$all_users = $stmt4->fetchAll(PDO::FETCH_ASSOC);

// Total Count for Quick Stats
$total_users = count($all_users);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Analysis - Innoventory</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #64748b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #f8fafc;
            --card: #ffffff;
        }

        body, html { margin:0; padding:0; font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: #1e293b; height: 100%; }
        
        .app-grid { display:flex; height:100vh; overflow:hidden; }
        
        /* Sidebar Styles */
        .sidebar { width:260px; background:var(--card); border-right:1px solid #e2e8f0; display:flex; flex-direction:column; padding:24px; box-sizing:border-box; }
        .sidebar .site-logo img { width:150px; margin-bottom:30px; }
        .sidebar .menu { list-style:none; padding:0; margin:0; flex:1; }
        .sidebar .menu li { margin-bottom:8px; }
        .sidebar .menu li a { text-decoration:none; color:var(--secondary); font-weight:500; padding:10px 14px; display:block; border-radius:8px; transition:0.3s; }
        .sidebar .menu li.active a, .sidebar .menu li a:hover { background:var(--primary); color:#fff; }
        .btn-logout { padding:10px; text-align:center; border-radius:8px; background:#fee2e2; color:var(--danger); text-decoration:none; font-weight:600; }

        /* Main Content */
        main { flex:1; overflow-y:auto; padding:40px; box-sizing:border-box; }
        
        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--card); padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
        .stat-card h3 { margin: 0; font-size: 0.875rem; color: var(--secondary); text-transform: uppercase; }
        .stat-card p { margin: 10px 0 0 0; font-size: 1.5rem; font-weight: 700; color: var(--primary); }

        /* Charts Layout */
        .charts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 25px; margin-bottom: 40px; }
        .chart-card { background: var(--card); padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
        .chart-card.full-width { grid-column: span 2; }
        h2 { font-size: 1.25rem; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }

        /* Table Styles */
        .table-container { background: var(--card); border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        table { width:100%; border-collapse:collapse; text-align: left; }
        th { background:#f1f5f9; padding:14px; font-weight:600; font-size: 0.875rem; color: var(--secondary); }
        td { padding:14px; border-top: 1px solid #e2e8f0; font-size: 0.9rem; }
        tr:hover { background:#f8fafc; }

        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-denied { background: #fee2e2; color: #991b1b; }

        #filterIndicator { font-size: 0.8rem; background: var(--primary); color: white; padding: 4px 12px; border-radius: 4px; display: none; }
        #resetBtn { background: #e2e8f0; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: 500; }
        #resetBtn:hover { background: #cbd5e1; }
    </style>
</head>
<body>

<div class="app-grid">
    <aside class="sidebar">
        <div class="site-logo">
            <a href="/innoventory/index.php"><img src="/innoventory/logo/logo.png" alt="Logo"></a>
        </div>
        <ul class="menu">
            <li><a href="admin_dashboard.php">Dashboard</a></li>
            <li><a href="users.php">Users List</a></li>
            <li class="active"><a href="analysis.php">Data Analysis</a></li>
        </ul>
        <a href="logout.php" class="btn-logout">Logout</a>
    </aside>

    <main>
        <header style="margin-bottom: 30px;">
            <h1 style="margin:0;">System Analysis</h1>
            <p style="color: var(--secondary);">Real-time user metrics and distribution</p>
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
            <div class="chart-card">
                <h2>User Status <small style="font-weight:400; color:var(--secondary)">(Click segments to filter)</small></h2>
                <canvas id="statusChart"></canvas>
            </div>

            <div class="chart-card">
                <h2>Role Distribution</h2>
                <canvas id="roleChart"></canvas>
            </div>

            <div class="chart-card full-width">
                <h2>Registration Trends (Last 30 Days)</h2>
                <canvas id="lineChart" style="max-height: 300px;"></canvas>
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
    </main>
</div>

<script>
// Data from PHP
const allUsers = <?= json_encode($all_users) ?>;
const statusData = <?= json_encode($status_data) ?>;
const roleData = <?= json_encode($role_data) ?>;
const lineData = <?= json_encode($line_data) ?>;

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
const statusChart = new Chart(ctxStatus, {
    type: 'doughnut', // Using doughnut for a modern look
    data: {
        labels: statusData.map(d => d.status),
        datasets: [{
            data: statusData.map(d => d.count),
            backgroundColor: ['#3b82f6', '#fbbf24', '#ef4444', '#10b981'],
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
        scales: { y: { beginAtZero: true, grid: { display: false } } },
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
            x: { grid: { display: false } },
            y: { beginAtZero: true }
        }
    }
});

// --- 5. Initializations ---
document.getElementById("resetBtn").addEventListener("click", () => renderTable());
renderTable(); // First load
</script>

</body>
</html>





