<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
$name = $_SESSION['name'] ?? 'User';
$userId = $_SESSION['user_id'] ?? 0;

/* Get user storage info from database */
$totalStorage = 1; // Default 1 GB
$usedStorage = 0;  // Mock value
$storagePercent = 0;

if ($userId > 0) {
    require_once "../../config.php";
    
    $stmt = $db->prepare("SELECT storage_gb FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userRow = $result->fetch_assoc();
    $stmt->close();
    
    if ($userRow && isset($userRow['storage_gb'])) {
        $totalStorage = (int)$userRow['storage_gb'];
    }
    
    // Mock used storage calculation (consistent based on user ID using a seed)
    // This ensures the value doesn't change when navigating between pages
    mt_srand($userId); // Seed random with user ID for consistency
    $usedStorage = round($totalStorage * (60 + mt_rand(0, 20)) / 100, 2);
    $storagePercent = round(($usedStorage / $totalStorage) * 100);
    mt_srand(); // Reset to default seed
}

/* Active helper */
function isActive($needle, $currentPage) {
    return strpos($currentPage, $needle) !== false ? 'active' : '';
}
?>

<aside class="sidebar-v2">

    <!-- LOGO -->
    <div class="sb-logo">
        <img id="themeLogoMenu" src="../../logo/logo (light).png" alt="Innoventory Logo" data-light="../../logo/logo (light).png" data-dark="../../logo/logo (dark).png">
    </div>

    <!-- NEW BUTTON WITH DROPDOWN -->
    <div class="sb-new">
        <button class="sb-new-btn" type="button" id="newBtn" onclick="toggleNewMenu(event)">
            <span class="sb-plus">+</span> New
        </button>

        <!-- Dropdown Menu -->
        <div class="sb-new-dropdown" id="newDropdown">
            <button class="sb-new-option" type="button" onclick="showCreateFolderDialog()">
                <span>📁</span> New Folder
            </button>
            <button class="sb-new-option" type="button" onclick="document.getElementById('fileUploadInput').click()">
                <span>📤</span> File Upload
            </button>
            <button class="sb-new-option" type="button" onclick="document.getElementById('folderUploadInput').click()">
                <span>📦</span> Folder Upload
            </button>
        </div>

        <!-- File Upload Form -->
        <form action="../../pkg/file-management/upload.php" method="POST" enctype="multipart/form-data" id="fileUploadForm">
            <input type="file" id="fileUploadInput" name="upload" hidden onchange="document.getElementById('fileUploadForm').submit()">
        </form>

        <!-- Folder Upload Form -->
        <form action="../../pkg/file-management/folder_upload.php" method="POST" enctype="multipart/form-data" id="folderUploadForm">
            <input type="file" id="folderUploadInput" name="folder[]" hidden multiple webkitdirectory directory onchange="document.getElementById('folderUploadForm').submit()">
        </form>
    </div>

    <!-- MENU -->
    <nav class="sb-nav">

        <a class="sb-link <?= isActive('dashboard', $currentPage) ?>"
           href="../../pkg/user-management/<?= htmlspecialchars($role) ?>_dashboard.php">
            <span class="sb-ico"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><polygon points="12,3 4,9 4,21 10,21 10,14 14,14 14,21 20,21 20,9"></polygon></svg></span>
            <span>Dashboard</span>
        </a>

        <?php if ($role === 'admin'): ?>

            <a class="sb-link <?= ($currentPage === 'admin_drive.php' ? 'active' : '') ?>"
               href="../../pkg/file-management/admin_drive.php">
                <span class="sb-ico"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M9.05 15H15q.275 0 .5-.137.225-.138.35-.363l1.1-1.9q.125-.225.1-.5-.025-.275-.15-.5l-2.95-5.1q-.125-.225-.35-.363Q13.375 6 13.1 6h-2.2q-.275 0-.5.137-.225.138-.35.363L7.1 11.6q-.125.225-.125.5t.125.5l1.05 1.9q.125.25.375.375T9.05 15Zm1.2-3L12 9l1.75 3ZM3 17V4q0-.825.587-1.413Q4.175 2 5 2h14q.825 0 1.413.587Q21 3.175 21 4v13Zm2 5q-.825 0-1.413-.587Q3 20.825 3 20v-1h18v1q0 .825-.587 1.413Q19.825 22 19 22Z"></path></svg></span>
                <span>My Drive</span>
            </a>

            <a class="sb-link <?= ($currentPage === 'users_drive.php' ? 'active' : '') ?>"
               href="../../pkg/file-management/users_drive.php">
                <span class="sb-ico">👥︎</span>
                <span>Users Drive</span>
            </a>

            <a class="sb-link <?= ($currentPage === 'users.php' ? 'active' : '') ?>"
               href="../../pkg/user-management/users.php">
                <span class="sb-ico">👤</span>
                <span>Users</span>
            </a>

        <?php else: ?>

            <a class="sb-link <?= ($currentPage === 'my_drive.php' ? 'active' : '') ?>"
               href="../../pkg/file-management/my_drive.php">
                <span class="sb-ico"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M9.05 15H15q.275 0 .5-.137.225-.138.35-.363l1.1-1.9q.125-.225.1-.5-.025-.275-.15-.5l-2.95-5.1q-.125-.225-.35-.363Q13.375 6 13.1 6h-2.2q-.275 0-.5.137-.225.138-.35.363L7.1 11.6q-.125.225-.125.5t.125.5l1.05 1.9q.125.25.375.375T9.05 15Zm1.2-3L12 9l1.75 3ZM3 17V4q0-.825.587-1.413Q4.175 2 5 2h14q.825 0 1.413.587Q21 3.175 21 4v13Zm2 5q-.825 0-1.413-.587Q3 20.825 3 20v-1h18v1q0 .825-.587 1.413Q19.825 22 19 22Z"></path></svg></span>
                <span>My Drive</span>
            </a>

        <?php endif; ?>

        <div class="sb-divider"></div>

        <a class="sb-link <?= ($currentPage === 'shared.php' ? 'active' : '') ?>" href="#">
            <span class="sb-ico"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M15,8c0-1.42-0.5-2.73-1.33-3.76C14.09,4.1,14.53,4,15,4c2.21,0,4,1.79,4,4s-1.79,4-4,4c-0.43,0-0.84-0.09-1.23-0.21 c-0.03-0.01-0.06-0.02-0.1-0.03C14.5,10.73,15,9.42,15,8z M16.66,13.13C18.03,14.06,19,15.32,19,17v3h4v-3 C23,14.82,19.42,13.53,16.66,13.13z M9,4c2.21,0,4,1.79,4,4s-1.79,4-4,4s-4-1.79-4-4S6.79,4,9,4z M9,13c2.67,0,8,1.34,8,4v3H1v-3 C1,14.34,6.33,13,9,13z"></path></svg></span>
            <span>Shared</span>
        </a>

        <a class="sb-link <?= ($currentPage === 'starred.php' ? 'active' : '') ?>"
           href="../../pkg/file-management/starred.php">
            <span class="sb-ico">☆</span>
            <span>Starred</span>
        </a>

        <a class="sb-link <?= ($currentPage === 'bin.php' ? 'active' : '') ?>"
           href="../../pkg/file-management/bin.php">
            <span class="sb-ico">🗑︎</span>
            <span>Bin</span>
        </a>

    </nav>

    <!-- STORAGE (UI ONLY) -->
    <div class="sb-storage">
        <div class="sb-storage-title">
            <span class="sb-ico">☁︎</span> Storage
        </div>

        <div class="sb-storage-bar">
            <div class="sb-storage-fill" style="width:<?= $storagePercent ?>%"></div>
        </div>

        <div class="sb-storage-meta">
            <span><?= round($usedStorage, 1) ?> GB of <?= $totalStorage ?> GB used</span>
        </div>
    </div>

    <!-- PROFILE -->
    <div class="sb-profile">
        <div class="sb-profile-info">
            <div class="sb-profile-name"><?= htmlspecialchars($name) ?></div>
            <div class="sb-profile-role"><?= ucfirst(htmlspecialchars($role)) ?></div>
        </div>

        <button class="sb-kebab" id="sbKebabBtn" type="button">⋮</button>

        <div class="sb-kebab-menu" id="sbKebabMenu">
            <a href="../../pkg/user-management/update_user.php">⚙️ Settings</a>
            <a href="../../pkg/user-management/logout.php">🚪 Logout</a>
        </div>
    </div>

</aside>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const btn  = document.getElementById("sbKebabBtn");
    const menu = document.getElementById("sbKebabMenu");

    if (!btn || !menu) return;

    btn.addEventListener("click", function (e) {
        e.stopPropagation();
        menu.classList.toggle("show");
    });

    document.addEventListener("click", function () {
        menu.classList.remove("show");
    });
});

// Toggle New Menu Dropdown
function toggleNewMenu(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('newDropdown');
    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const newBtn = document.getElementById('newBtn');
    const dropdown = document.getElementById('newDropdown');
    
    if (newBtn && dropdown && !newBtn.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

// Show Create Folder Dialog
function showCreateFolderDialog() {
    const folderName = prompt('Enter folder name:');
    if (folderName && folderName.trim() !== '') {
        createFolder(folderName.trim());
    }
    // Close dropdown after
    document.getElementById('newDropdown').classList.remove('show');
}

// Create Folder via AJAX
async function createFolder(folderName) {
    try {
        const formData = new FormData();
        formData.append('folder_name', folderName);

        const response = await fetch('../../pkg/file-management/create_folder.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            alert('Folder created successfully!');
            location.reload();
        } else {
            alert(data.message || 'Failed to create folder');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while creating the folder');
    }
}
</script>
