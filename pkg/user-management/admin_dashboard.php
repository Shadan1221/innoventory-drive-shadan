<?php
// 1. Start Session
require_once "../../session.php";

// 2. PREVENT BROWSER CACHING (Must be before any output)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// 3. Database Config
require_once "../../config.php";

// 4. Security Check (Fixed Logic)
// If not logged in OR not an admin -> Redirect to Login
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("Location: ../../index.php");
    exit;
}

// Get all pending requests
$result = mysqli_query($db, "SELECT * FROM users WHERE status='pending' ORDER BY role, name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Innoventory</title>
    <link rel="stylesheet" href="../../css/main.css">
    <style>
        body {
            background: var(--bg);
            padding: 18px;
        }
        .dashboard-container {
            max-width: 1700px;
            margin: 0 auto;
            background: var(--panel);
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--soft-shadow);
        }
        h1 {
            color: var(--text);
            margin-bottom: 10px;
        }
        .subtitle {
            color: var(--muted);
            margin-bottom: 30px;
            font-size: 14px;
        }
        .message {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .message.success {
            background: rgba(16, 185, 129, 0.08);
            color: #059669;
            border: 1px solid rgba(167,243,208,0.25);
        }
        .message.error {
            background: rgba(254, 226, 226, 0.06);
            color: #d13212;
            border: 1px solid rgba(254,202,202,0.18);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        table th {
            background: var(--table-head-bg);
            font-weight: 600;
            color: var(--text);
        }
        table tr:hover {
            background: rgba(0,0,0,0.02);
        }
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .role-badge.admin {
            background: rgba(255,243,199,0.12);
            color: #92400e;
        }
        .role-badge.user {
            background: rgba(219,234,254,0.06);
            color: #1e40af;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.pending {
            background: rgba(255,243,199,0.08);
            color: #92400e;
        }
        .action-links a {
            color: #0073bb;
            text-decoration: none;
            margin-right: 10px;
        }
        .action-links a:hover {
            text-decoration: underline;
        }
        .action-links a.deny {
            color: #d13212;
        }
        .no-requests {
            text-align: center;
            padding: 40px;
            color: var(--muted);
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        .header-left h1 {
            margin: 0;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
            .user-info {
                color: var(--muted);
            font-size: 14px;
        }
        .btn-logout {
            padding: 8px 16px;
            background: #d13212;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .btn-logout:hover {
            background: #b0280f;
        }
    </style>
</head>
<body>
    <?php include '../../common/header.php'; ?>

    <div class="app-grid">
        <?php include "../../common/menu.php"; ?>

        <main>
            <div class="dashboard-card">
                <div class="header-section">
                <div class="header-right">
                    <span class="user-info">Welcome, <?= htmlspecialchars($_SESSION["name"] ?? "Admin"); ?></span>
                </div>
            </div>

                <?php if (isset($_GET["msg"])): ?>
                    <div class="message <?php echo strpos($_GET["msg"], "Error") !== false ? "error" : "success"; ?>">
                        <?php echo htmlspecialchars($_GET["msg"]); ?>
                    </div>
                <?php endif; ?>


                <div id="users-section">
                <h2 style="margin-top:30px; margin-bottom:8px;">Pending Requests</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Requested Storage</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hasRequests = false;
                        while($row = mysqli_fetch_assoc($result)): 
                            $hasRequests = true;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row["name"]); ?></td>
                            <td><?= htmlspecialchars($row["email"]); ?></td>
                            <td>
                                <span class="role-badge <?= $row["role"]; ?>">
                                    <?= ucfirst($row["role"]); ?>
                                </span>
                            </td>
                            <td>
                                <?= isset($row['storage_gb']) ? intval($row['storage_gb']) . ' GB' : 'N/A'; ?>
                            </td>
                            <td>
                                <span class="status-badge pending">
                                    <?= ucfirst($row["status"]); ?>
                                </span>
                            </td>
                            <td class="action-links">
                                <a href="approve.php?id=<?= $row['id'] ?>">Approve</a>
                                <a href="deny.php?id=<?= $row['id'] ?>" class="deny">Deny</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if (!$hasRequests): ?>
                        <tr>
                            <td colspan="6" class="no-requests">
                                No pending requests at this time.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- Approved/Denied infograph (Dashboard only) -->
                <?php
                $counts = ['approved' => 0, 'denied' => 0];
                $res = mysqli_query($db, "SELECT status, COUNT(*) AS cnt FROM users WHERE status IN ('approved','denied') GROUP BY status");
                if ($res) {
                    while ($r = mysqli_fetch_assoc($res)) {
                        $st = $r['status'];
                        if (isset($counts[$st])) $counts[$st] = intval($r['cnt']);
                    }
                    mysqli_free_result($res);
                }
                $approvedCount = $counts['approved'];
                $deniedCount = $counts['denied'];
                $total = $approvedCount + $deniedCount;
                $approvedPct = $total ? round(($approvedCount / $total) * 100, 1) : 0;
                $deniedPct = $total ? round(($deniedCount / $total) * 100, 1) : 0;
                ?>
                <div class="infograph-row" style="display:flex; align-items:center; gap:20px; margin-top:22px;">
                    <div class="status-infograph">
                        <svg class="donut" viewBox="0 0 42 42" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <?php $r=16; $circ = 2*M_PI*$r; $dashA = ($approvedPct/100)*$circ; $dashB = ($deniedPct/100)*$circ; ?>
                            <circle cx="18" cy="18" r="<?php echo $r; ?>" stroke="rgba(0,0,0,0.06)" stroke-width="6" fill="none" />
                            <circle cx="18" cy="18" r="<?php echo $r; ?>" stroke="#10B981" stroke-width="6" fill="none" stroke-dasharray="<?php echo $dashA; ?> <?php echo $circ; ?>" transform="rotate(-90 18 18)" stroke-linecap="round" />
                            <circle cx="18" cy="18" r="<?php echo $r; ?>" stroke="#ef4444" stroke-width="6" fill="none" stroke-dasharray="<?php echo $dashB; ?> <?php echo $circ; ?>" transform="rotate(-90 18 18)" stroke-linecap="round" stroke-dashoffset="-<?php echo $dashA; ?>" />
                            <text x="18" y="20" text-anchor="middle" font-size="8" fill="var(--text)"><?php echo intval($approvedPct); ?>%</text>
                        </svg>
                    </div>
                    <div class="legend" style="display:flex; flex-direction:column; gap:8px; color:var(--muted);">
                        <div style="display:flex; align-items:center; gap:8px;"><span class="swatch" style="width:12px;height:12px;background:#10B981;border-radius:3px"></span><strong style="margin-right:6px;color:var(--text);"><?php echo $approvedCount; ?></strong> Approved</div>
                        <div style="display:flex; align-items:center; gap:8px;"><span class="swatch" style="width:12px;height:12px;background:#ef4444;border-radius:3px"></span><strong style="margin-right:6px;color:var(--text);"><?php echo $deniedCount; ?></strong> Denied</div>
                    </div>
                </div>

                </div>
            </div>
        </main>
    </div>
    <script>
        // Smooth scroll to anchors and mark menu active
        (function(){
            function setActiveLink(hash) {
                document.querySelectorAll('.sidebar .menu li').forEach(function(li){ li.classList.remove('active'); });
                var links = document.querySelectorAll('.sidebar .menu a');
                links.forEach(function(a){
                    if (a.getAttribute('href') === hash) {
                        a.parentElement.classList.add('active');
                    }
                });
            }

            document.querySelectorAll('.sidebar .menu a').forEach(function(a){
                a.addEventListener('click', function(e){
                    var href = a.getAttribute('href');
                    if (href && href.startsWith('#')) {
                        e.preventDefault();
                        var target = document.querySelector(href);
                        if (target) {
                            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            history.replaceState(null, '', href);
                            setActiveLink(href);
                        }
                    }
                });
            });

            // If page loaded with hash, set active and scroll
            if (location.hash) {
                setTimeout(function(){
                    var target = document.querySelector(location.hash);
                    if (target) target.scrollIntoView();
                    setActiveLink(location.hash);
                }, 120);
            }
        })();
    </script>
</body>
</html>