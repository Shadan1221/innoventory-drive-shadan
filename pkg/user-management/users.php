<?php
require_once "../../session.php";
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
require_once "../../config.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("Location: ../../index.php");
    exit;
}

// Queries
$pending = mysqli_query($db, "SELECT * FROM users WHERE status='pending' ORDER BY role, name");
$approved = mysqli_query($db, "SELECT * FROM users WHERE status='approved' ORDER BY name");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Innoventory</title>
    <link rel="stylesheet" href="../../css/main.css">
    <style>
        body { background: var(--bg); margin: 0; margin-left: 280px; width: calc(100% - 280px); }
        .dashboard-container { max-width: 100%; margin: 0; background: var(--panel); padding: 30px; border-radius: 0; box-shadow: none; }
        h1 { color: var(--text); margin-bottom: 10px; }
        .subtitle { color: var(--muted); margin-bottom: 30px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; color: var(--text); }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        table th { background: #f3f4f6; font-weight: 600; color: var(--text); }
        .role-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: var(--accent-soft); color: var(--accent); }
        .no-requests { text-align: center; padding: 40px; color: var(--muted); }
        .message { padding: 12px 16px; margin-bottom: 20px; border-radius: 8px; border-left: 4px solid; }
        .message.success { background: #dcfce7; border-color: #22c55e; color: #15803d; }
        .message.error { background: #fee2e2; border-color: #ef4444; color: #991b1b; }
        .action-links { display: flex; gap: 12px; }
        .action-links a { padding: 6px 12px; border-radius: 6px; background: var(--accent-soft); color: var(--accent); text-decoration: none; font-size: 13px; font-weight: 500; }
        .action-links a:hover { background: var(--accent); color: white; }
        .action-links a.deny:hover { background: #ef4444; }
        [data-theme="dark"] table th { background: rgba(255, 255, 255, 0.05); }
        [data-theme="dark"] .message.success { background: rgba(34, 197, 94, 0.1); border-color: #4ade80; color: #4ade80; }
        [data-theme="dark"] .message.error { background: rgba(239, 68, 68, 0.1); border-color: #f87171; color: #f87171; }
    </style>
</head>
<body>
    <?php include '../../common/header.php'; ?>
    <div class="app-grid">
        <?php include '../../common/menu.php'; ?>

        <main>
            <div class="dashboard-container">
                <div class="header-section">
                    <div class="header-left">
                        <h1>Users</h1>
                        <p class="subtitle">Pending requests and approved users</p>
                    </div>
                    <div class="header-right">
                        <span class="user-info">Welcome, <?= htmlspecialchars($_SESSION["name"] ?? "Admin"); ?></span>
                    </div>
                </div>

                <?php if (isset($_GET["msg"])): ?>
                    <div class="message <?php echo strpos($_GET["msg"], "Error") !== false ? "error" : "success"; ?>">
                        <?php echo htmlspecialchars($_GET["msg"]); ?>
                    </div>
                <?php endif; ?>

                <h2 style="margin-top:3px; margin-bottom:8px;">Pending Requests</h2>
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
                        while($row = mysqli_fetch_assoc($pending)): 
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
                            <td colspan="6" class="no-requests">No pending requests at this time.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <h2 style="margin-top:40px;">Approved Users</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Requested Storage</th>
                            <th>Reason</th>
                            <th>Location</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $hasApproved = false;
                        while ($u = mysqli_fetch_assoc($approved)):
                            $hasApproved = true;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($u['name']); ?></td>
                            <td><?= htmlspecialchars($u['email']); ?></td>
                            <td><span class="role-badge <?= htmlspecialchars($u['role'] ?? 'user'); ?>"><?= ucfirst($u['role'] ?? 'user'); ?></span></td>
                            <td><?= isset($u['storage_gb']) ? intval($u['storage_gb']) . ' GB' : 'N/A'; ?></td>
                            <td><?= isset($u['reason']) && $u['reason'] !== '' ? htmlspecialchars($u['reason']) : 'N/A'; ?></td>
                            <td><?= isset($u['location']) && $u['location'] !== '' ? htmlspecialchars($u['location']) : 'N/A'; ?></td>
                        </tr>
                        <?php endwhile; ?>

                        <?php if (!$hasApproved): ?>
                        <tr>
                            <td colspan="6" class="no-requests">No approved users yet.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

            </div>
        </main>
    </div>
</body>
</html>
