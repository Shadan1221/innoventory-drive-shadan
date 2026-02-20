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

// Function to calculate folder size
function getFolderSize($path) {
    if (!is_dir($path)) return 0;
    $size = 0;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($files as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    return $size;
}

// Function to format bytes to human readable
function formatBytes($bytes) {
    if ($bytes == 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log(1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

// Queries
$pending = mysqli_query($db, "SELECT * FROM users WHERE status='pending' ORDER BY role, name");
$approved = mysqli_query($db, "SELECT * FROM users WHERE status='approved' ORDER BY name");
$denied = mysqli_query($db, "SELECT * FROM users WHERE status='denied' ORDER BY name");
$totalUsedBytes = 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Innoventory</title>
    <link rel="stylesheet" href="../../css/main.css">
    <style>
        body { background: var(--bg); margin: 0; }
        .dashboard-container { max-width: 1200px; margin: 0 auto; background: var(--panel); padding: 30px; border-radius: 18px; box-shadow: var(--shadow); }
        .header-section { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
        .header-right { color: var(--muted); font-size: 14px; }
        h1 { color: var(--text); margin-bottom: 10px; }
        .subtitle { color: var(--muted); margin-bottom: 30px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; color: var(--text); }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        table th { background: #f3f4f6; font-weight: 600; color: var(--text); position: relative; }
        table th.sortable { cursor: pointer; user-select: none; padding-right: 30px; }
        table th.sortable:hover { background: #e5e7eb; }
        table th.sortable::after { content: '⇅'; position: absolute; right: 10px; opacity: 0.3; font-size: 12px; }
        table th.sortable.asc::after { content: '↑'; opacity: 1; color: var(--accent); }
        table th.sortable.desc::after { content: '↓'; opacity: 1; color: var(--accent); }
        .role-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; background: var(--accent-soft); color: var(--accent); }
        .no-requests { text-align: center; padding: 40px; color: var(--muted); }
        .message { padding: 12px 16px; margin-bottom: 20px; border-radius: 8px; border-left: 4px solid; }
        .message.success { background: #dcfce7; border-color: #22c55e; color: #15803d; }
        .message.error { background: #fee2e2; border-color: #ef4444; color: #991b1b; }
        .action-links { display: flex; gap: 12px; }
        .action-links a { padding: 6px 12px; border-radius: 6px; background: var(--accent-soft); color: var(--accent); text-decoration: none; font-size: 13px; font-weight: 500; }
        .action-links a:hover { background: var(--accent); color: white; }
        .action-links a.deny:hover { background: #ef4444; }
        .controls-container { margin: 20px 0; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .search-input { flex: 1; max-width: 400px; padding: 10px 16px; border-radius: 10px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 14px; }
        .search-input:focus { outline: none; border-color: var(--accent); }
        .search-icon { color: var(--muted); font-size: 18px; }
        .sort-btn, .reset-btn { padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
        .sort-btn:hover, .reset-btn:hover { background: var(--accent); color: white; border-color: var(--accent); }
        .sort-btn.active { background: var(--accent); color: white; border-color: var(--accent); }
        .reset-btn { background: #ef4444; color: white; border-color: #ef4444; }
        .reset-btn:hover { background: #dc2626; }
        .no-results { text-align: center; padding: 20px; color: var(--muted); font-style: italic; }
        [data-theme="dark"] table th { background: #111827; color: #e5e7eb; }
        [data-theme="dark"] table th.sortable:hover { background: #1f2937; }
        [data-theme="dark"] .message.success { background: rgba(34, 197, 94, 0.1); border-color: #4ade80; color: #4ade80; }
        [data-theme="dark"] .message.error { background: rgba(239, 68, 68, 0.1); border-color: #f87171; color: #f87171; }
        
        /* Reason Modal Styles */
        .reason-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 10000; display: flex; align-items: center; justify-content: center; }
        .reason-modal-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); }
        .reason-modal-content { position: relative; background: var(--panel); border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3); max-width: 500px; width: 90%; max-height: 80vh; overflow: hidden; z-index: 10001; }
        .reason-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid var(--border); }
        .reason-modal-header h3 { margin: 0; color: var(--text); font-size: 18px; }
        .reason-modal-close { background: none; border: none; font-size: 28px; color: var(--muted); cursor: pointer; line-height: 1; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: all 0.2s; }
        .reason-modal-close:hover { background: var(--accent-soft); color: var(--text); }
        .reason-modal-body { padding: 24px; color: var(--text); line-height: 1.6; max-height: calc(80vh - 80px); overflow-y: auto; white-space: pre-wrap; word-wrap: break-word; }
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

                <div class="controls-container">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="userSearch" class="search-input" placeholder="Search users by name..." autocomplete="off">
                    <button class="reset-btn" onclick="resetView()">Reset</button>
                </div>

                <h2 style="margin-top:3px; margin-bottom:8px;">Pending Requests</h2>
                <table id="pendingTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th class="sortable" data-column="email">Email</th>
                            <th>Role</th>
                            <th>Requested Storage</th>
                            <th class="sortable" data-column="storage">Used Storage</th>
                            <th>Reason</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hasRequests = false;
                        while($row = mysqli_fetch_assoc($pending)): 
                            $hasRequests = true;
                            // Calculate used storage
                            $userFolder = "../../uploads/user_" . $row['id'];
                            $usedBytes = getFolderSize($userFolder);
                            $usedStorage = formatBytes($usedBytes);
                            $totalUsedBytes += $usedBytes;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($row["name"]); ?></td>
                            <td data-email="<?= htmlspecialchars($row["email"]); ?>"><?= htmlspecialchars($row["email"]); ?></td>
                            <td>
                                <span class="role-badge <?= $row["role"]; ?>">
                                    <?= ucfirst($row["role"]); ?>
                                </span>
                            </td>
                            <td>
                                <?= isset($row['storage_gb']) ? intval($row['storage_gb']) . ' GB' : 'N/A'; ?>
                            </td>
                            <td data-bytes="<?= $usedBytes; ?>"><?= $usedStorage; ?></td>
                            <td>
                                <?php if (isset($row['reason']) && $row['reason'] !== ''): ?>
                                    <a href="#" class="reason-link" onclick="showReasonModal(event, '<?= htmlspecialchars(addslashes($row['reason']), ENT_QUOTES); ?>')" style="color: var(--accent); text-decoration: underline; cursor: pointer;">View</a>
                                <?php else: ?>
                                    <span style="color: var(--muted);">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-links">
                                <a href="approve.php?id=<?= $row['id'] ?>">Approve</a>
                                <a href="deny.php?id=<?= $row['id'] ?>" class="deny">Deny</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if (!$hasRequests): ?>
                        <tr>
                            <td colspan="7" class="no-requests">No pending requests at this time.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <h2 style="margin-top:40px;">Approved Users</h2>
                <table id="approvedTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th class="sortable" data-column="email">Email</th>
                            <th>Role</th>
                            <th>Requested Storage</th>
                            <th class="sortable" data-column="storage">Used Storage</th>
                            <th>Reason</th>
                            <th>Location</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $hasApproved = false;
                        while ($u = mysqli_fetch_assoc($approved)):
                            $hasApproved = true;
                            // Calculate used storage
                            $userFolder = "../../uploads/user_" . $u['id'];
                            $usedBytes = getFolderSize($userFolder);
                            $usedStorage = formatBytes($usedBytes);
                            $totalUsedBytes += $usedBytes;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($u['name']); ?></td>
                            <td data-email="<?= htmlspecialchars($u['email']); ?>"><?= htmlspecialchars($u['email']); ?></td>
                            <td><span class="role-badge <?= htmlspecialchars($u['role'] ?? 'user'); ?>"><?= ucfirst($u['role'] ?? 'user'); ?></span></td>
                            <td><?= isset($u['storage_gb']) ? intval($u['storage_gb']) . ' GB' : 'N/A'; ?></td>
                            <td data-bytes="<?= $usedBytes; ?>"><?= $usedStorage; ?></td>
                            <td>
                                <?php if (isset($u['reason']) && $u['reason'] !== ''): ?>
                                    <a href="#" class="reason-link" onclick="showReasonModal(event, '<?= htmlspecialchars(addslashes($u['reason']), ENT_QUOTES); ?>')" style="color: var(--accent); text-decoration: underline; cursor: pointer;">View</a>
                                <?php else: ?>
                                    <span style="color: var(--muted);">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= isset($u['location']) && $u['location'] !== '' ? htmlspecialchars($u['location']) : 'N/A'; ?></td>
                            <td class="action-links">
                                <a href="deny.php?id=<?= $u['id'] ?>" class="deny">Deny</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>

                        <?php if (!$hasApproved): ?>
                        <tr>
                            <td colspan="8" class="no-requests">No approved users yet.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <h2 style="margin-top:40px;">Denied Users</h2>
                <table id="deniedTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th class="sortable" data-column="email">Email</th>
                            <th>Role</th>
                            <th>Requested Storage</th>
                            <th class="sortable" data-column="storage">Used Storage</th>
                            <th>Reason</th>
                            <th>Location</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $hasDenied = false;
                        while ($d = mysqli_fetch_assoc($denied)):
                            $hasDenied = true;
                            // Calculate used storage
                            $userFolder = "../../uploads/user_" . $d['id'];
                            $usedBytes = getFolderSize($userFolder);
                            $usedStorage = formatBytes($usedBytes);
                            $totalUsedBytes += $usedBytes;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($d['name']); ?></td>
                            <td data-email="<?= htmlspecialchars($d['email']); ?>"><?= htmlspecialchars($d['email']); ?></td>
                            <td><span class="role-badge <?= htmlspecialchars($d['role'] ?? 'user'); ?>"><?= ucfirst($d['role'] ?? 'user'); ?></span></td>
                            <td><?= isset($d['storage_gb']) ? intval($d['storage_gb']) . ' GB' : 'N/A'; ?></td>
                            <td data-bytes="<?= $usedBytes; ?>"><?= $usedStorage; ?></td>
                            <td>
                                <?php if (isset($d['reason']) && $d['reason'] !== ''): ?>
                                    <a href="#" class="reason-link" onclick="showReasonModal(event, '<?= htmlspecialchars(addslashes($d['reason']), ENT_QUOTES); ?>')" style="color: var(--accent); text-decoration: underline; cursor: pointer;">View</a>
                                <?php else: ?>
                                    <span style="color: var(--muted);">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><?= isset($d['location']) && $d['location'] !== '' ? htmlspecialchars($d['location']) : 'N/A'; ?></td>
                            <td class="action-links">
                                <a href="approve.php?id=<?= $d['id'] ?>">Approve</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>

                        <?php if (!$hasDenied): ?>
                        <tr>
                            <td colspan="8" class="no-requests">No denied users.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <table style="margin-top: 18px;">
                    <tbody>
                        <tr>
                            <td style="font-weight: 600;">Total storage used (all users)</td>
                            <td style="text-align: right; font-weight: 600;"><?= formatBytes($totalUsedBytes); ?></td>
                        </tr>
                    </tbody>
                </table>

            </div>
        </main>
    </div>

    <!-- Reason Modal -->
    <div id="reasonModal" class="reason-modal" style="display: none;">
        <div class="reason-modal-overlay" onclick="closeReasonModal()"></div>
        <div class="reason-modal-content">
            <div class="reason-modal-header">
                <h3>User's Reason for Access</h3>
                <button class="reason-modal-close" onclick="closeReasonModal()">&times;</button>
            </div>
            <div class="reason-modal-body" id="reasonModalBody">
                <!-- Reason text will be inserted here -->
            </div>
        </div>
    </div>

    <script>
    let currentSort = { column: null, direction: 'asc' };
    
    // Reason Modal Functions
    function showReasonModal(event, reason) {
        event.preventDefault();
        const modal = document.getElementById('reasonModal');
        const modalBody = document.getElementById('reasonModalBody');
        modalBody.textContent = reason;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeReasonModal() {
        const modal = document.getElementById('reasonModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeReasonModal();
        }
    });
    
    // User search functionality
    document.getElementById('userSearch').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase().trim();
        
        // Search in all three tables
        searchTable('pendingTable', searchTerm);
        searchTable('approvedTable', searchTerm);
        searchTable('deniedTable', searchTerm);
    });

    function searchTable(tableId, searchTerm) {
        const table = document.getElementById(tableId);
        const tbody = table.querySelector('tbody');
        const rows = tbody.querySelectorAll('tr');
        let visibleCount = 0;

        rows.forEach(row => {
            // Skip "no requests" or "no users" rows
            if (row.querySelector('.no-requests') || row.querySelector('.no-results')) {
                row.style.display = 'none';
                return;
            }

            const nameCell = row.cells[0];
            if (!nameCell) return;

            const name = nameCell.textContent.toLowerCase();
            
            if (searchTerm === '' || name.includes(searchTerm)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Show "no results" message if no rows are visible and search term is not empty
        if (visibleCount === 0 && searchTerm !== '') {
            let noResultsRow = tbody.querySelector('.no-results-row');
            if (!noResultsRow) {
                noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-row';
                const colspan = table.querySelector('thead tr').cells.length;
                noResultsRow.innerHTML = `<td colspan="${colspan}" class="no-results">No users found matching "${searchTerm}"</td>`;
                tbody.appendChild(noResultsRow);
            } else {
                noResultsRow.style.display = '';
                noResultsRow.querySelector('td').textContent = `No users found matching "${searchTerm}"`;
            }
        } else {
            const noResultsRow = tbody.querySelector('.no-results-row');
            if (noResultsRow) {
                noResultsRow.style.display = 'none';
            }
        }

        // Show original "no requests/users" message if search is empty and no data rows exist
        if (searchTerm === '') {
            const noDataRow = tbody.querySelector('.no-requests');
            if (noDataRow && visibleCount === 0) {
                noDataRow.parentElement.style.display = '';
            }
        }
    }

    // Sort by clicking table headers
    document.querySelectorAll('th.sortable').forEach(th => {
        th.addEventListener('click', () => {
            const column = th.getAttribute('data-column');
            if (!column) return;

            let direction = 'asc';
            if (currentSort.column === column) {
                direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            } else if (column === 'storage') {
                direction = 'desc';
            }

            currentSort = { column, direction };
            updateSortIndicators(column, direction);

            sortTable('pendingTable', column, direction);
            sortTable('approvedTable', column, direction);
            sortTable('deniedTable', column, direction);
        });
    });

    function updateSortIndicators(column, direction) {
        document.querySelectorAll('th.sortable').forEach(th => {
            th.classList.remove('asc', 'desc');
            if (th.getAttribute('data-column') === column) {
                th.classList.add(direction);
            }
        });
    }

    // Generic sort function
    function sortTable(tableId, column, direction) {
        const table = document.getElementById(tableId);
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr')).filter(row => 
            !row.querySelector('.no-requests') && !row.querySelector('.no-results')
        );
        
        rows.sort((a, b) => {
            let valA, valB;
            
            if (column === 'email') {
                // Email is in column index 1
                valA = a.cells[1].getAttribute('data-email').toLowerCase();
                valB = b.cells[1].getAttribute('data-email').toLowerCase();
                
                if (direction === 'asc') {
                    return valA.localeCompare(valB);
                } else {
                    return valB.localeCompare(valA);
                }
            } else if (column === 'storage') {
                // Storage is in column index 4, use data-bytes attribute
                valA = parseInt(a.cells[4].getAttribute('data-bytes')) || 0;
                valB = parseInt(b.cells[4].getAttribute('data-bytes')) || 0;
                
                if (direction === 'asc') {
                    return valA - valB;
                } else {
                    return valB - valA;
                }
            }
        });
        
        // Reorder rows
        rows.forEach(row => tbody.appendChild(row));
    }

    // Reset view functionality
    function resetView() {
        // Clear search
        document.getElementById('userSearch').value = '';
        
        // Reset sort state
        currentSort = { column: null, direction: 'asc' };
        updateSortIndicators('', 'asc');
        
        // Reload the page to reset everything
        window.location.href = window.location.pathname;
    }
    </script>
</body>
</html>
