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
    
    // Function to calculate folder size
    function calculateFolderSize($path) {
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
    
    // Check if user is admin
    if ($role === 'admin') {
        // For admin: Show their usage out of total system storage
        // Calculate total system storage (sum of all users' allocated storage)
        $systemQuery = mysqli_query($db, "SELECT SUM(storage_gb) as total_system_storage FROM users WHERE status='approved'");
        $systemRow = mysqli_fetch_assoc($systemQuery);
        $totalStorage = ($systemRow && $systemRow['total_system_storage']) ? (int)$systemRow['total_system_storage'] : 1;
        
        // Calculate admin's actual used storage
        $adminFolder = "../../uploads/user_" . $userId;
        $usedBytes = calculateFolderSize($adminFolder);
        $usedStorage = round($usedBytes / (1024 * 1024 * 1024), 2); // Convert to GB
        
        $storagePercent = $totalStorage > 0 ? round(($usedStorage / $totalStorage) * 100) : 0;
    } else {
        // For regular users: Show their usage out of their allocated storage
        $stmt = $db->prepare("SELECT storage_gb FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $userRow = $result->fetch_assoc();
        $stmt->close();
        
        if ($userRow && isset($userRow['storage_gb'])) {
            $totalStorage = (int)$userRow['storage_gb'];
        }
        
        // Calculate actual used storage
        $userFolder = "../../uploads/user_" . $userId;
        $usedBytes = calculateFolderSize($userFolder);
        $usedStorage = round($usedBytes / (1024 * 1024 * 1024), 2); // Convert to GB
        
        $storagePercent = $totalStorage > 0 ? round(($usedStorage / $totalStorage) * 100) : 0;
    }
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
                <span><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2Z"/></svg></span> New Folder
            </button>
            <button class="sb-new-option" type="button" onclick="document.getElementById('fileUploadInput').click()">
                <span><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M5 20h14v-2H5v2Zm7-16-5 5h3v4h4v-4h3l-5-5Z"/></svg></span> File Upload
            </button>
            <button class="sb-new-option" type="button" onclick="document.getElementById('folderUploadInput').click()">
                <span><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M4 4h6l2 2h8c1.1 0 2 .9 2 2v2H2V6c0-1.1.9-2 2-2Zm-2 8h20v8c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2v-8Zm10-3-4 4h3v4h2v-4h3l-4-4Z"/></svg></span> Folder Upload
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
                <span class="sb-ico"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M16 11c1.657 0 3-1.567 3-3.5S17.657 4 16 4s-3 1.567-3 3.5S14.343 11 16 11Zm-8 0c1.657 0 3-1.567 3-3.5S9.657 4 8 4 5 5.567 5 7.5 6.343 11 8 11Zm0 2c-2.761 0-5 2.239-5 5v2h10v-2c0-2.761-2.239-5-5-5Zm8 0c-.526 0-1.03.078-1.5.22 1.79.98 3 2.872 3 5.03V20h5v-2c0-2.761-2.239-5-5-5Z"/></svg></span>
                <span>Users Drive</span>
            </a>

            <a class="sb-link <?= ($currentPage === 'users.php' ? 'active' : '') ?>"
               href="../../pkg/user-management/users.php">
                <span class="sb-ico"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 12c2.761 0 5-2.239 5-5S14.761 2 12 2 7 4.239 7 7s2.239 5 5 5Zm0 2c-4.418 0-8 2.239-8 5v3h16v-3c0-2.761-3.582-5-8-5Z"/></svg></span>
                <span>Users</span>
            </a>

            <a class="sb-link <?= ($currentPage === 'analysis.php' ? 'active' : '') ?>"
               href="../../pkg/user-management/analysis.php">
                <span class="sb-ico"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M4 19h16v2H2V3h2v16Zm3-2h2V10H7v7Zm4 0h2V6h-2v11Zm4 0h2V13h-2v4Z"/></svg></span>
                <span>Analysis</span>
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
            <span class="sb-ico"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 17.27 18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21 12 17.27Z"/></svg></span>
            <span>Starred</span>
        </a>

        <a class="sb-link <?= ($currentPage === 'bin.php' ? 'active' : '') ?>"
           href="../../pkg/file-management/bin.php">
            <span class="sb-ico"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M9 3h6l1 2h5v2H3V5h5l1-2Zm1 6h2v9h-2V9Zm4 0h2v9h-2V9ZM7 9h2v9H7V9Z"/></svg></span>
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
            <?php if ($role === 'admin'): ?>
                <span><?= round($usedStorage, 1) ?> GB of <?= $totalStorage ?> GB system storage</span>
            <?php else: ?>
                <span><?= round($usedStorage, 1) ?> GB of <?= $totalStorage ?> GB used</span>
            <?php endif; ?>
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
    const logo = document.getElementById("themeLogoMenu");

    if (!btn || !menu) return;

    btn.addEventListener("click", function (e) {
        e.stopPropagation();
        menu.classList.toggle("show");
    });

    document.addEventListener("click", function () {
        menu.classList.remove("show");
    });

    function syncLogoTheme() {
        if (!logo) return;
        const isDark = document.documentElement.getAttribute("data-theme") === "dark";
        const darkSrc = logo.getAttribute("data-dark");
        const lightSrc = logo.getAttribute("data-light");
        logo.src = isDark ? darkSrc : lightSrc;
    }

    syncLogoTheme();

    const observer = new MutationObserver(syncLogoTheme);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ["data-theme"] });
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
