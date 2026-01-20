<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
$name = $_SESSION['name'] ?? 'User';

/* Active helper */
function isActive($needle, $currentPage) {
    return strpos($currentPage, $needle) !== false ? 'active' : '';
}
?>

<aside class="sidebar-v2">

    <!-- LOGO -->
    <div class="sb-logo">
        <img src="../../logo/logo.png" alt="Innoventory Logo">
    </div>

    <!-- NEW BUTTON -->
    <div class="sb-new">
        <button class="sb-new-btn" type="button" onclick="document.getElementById('uploadInput').click()">
            <span class="sb-plus">+</span> New
        </button>

        <form action="../../pkg/file-management/upload.php" method="POST" enctype="multipart/form-data">
            <input type="file" id="uploadInput" name="upload" hidden onchange="this.form.submit()">
        </form>
    </div>

    <!-- MENU -->
    <nav class="sb-nav">

        <a class="sb-link <?= isActive('dashboard', $currentPage) ?>"
           href="../../pkg/user-management/<?= htmlspecialchars($role) ?>_dashboard.php">
            <span class="sb-ico">🏠</span>
            <span>Dashboard</span>
        </a>

        <?php if ($role === 'admin'): ?>

            <a class="sb-link <?= ($currentPage === 'admin_drive.php' ? 'active' : '') ?>"
               href="../../pkg/file-management/admin_drive.php">
                <span class="sb-ico">💾</span>
                <span>My Drive</span>
            </a>

            <a class="sb-link <?= ($currentPage === 'users_drive.php' ? 'active' : '') ?>"
               href="../../pkg/file-management/users_drive.php">
                <span class="sb-ico">👥</span>
                <span>Users Drive</span>
            </a>

            <a class="sb-link <?= ($currentPage === 'users.php' ? 'active' : '') ?>"
               href="../../pkg/user-management/users.php">
                <span class="sb-ico">🧑‍💼</span>
                <span>Users</span>
            </a>

        <?php else: ?>

            <a class="sb-link <?= ($currentPage === 'my_drive.php' ? 'active' : '') ?>"
               href="../../pkg/file-management/my_drive.php">
                <span class="sb-ico">💾</span>
                <span>My Drive</span>
            </a>

        <?php endif; ?>

        <div class="sb-divider"></div>

        <a class="sb-link <?= ($currentPage === 'shared.php' ? 'active' : '') ?>" href="#">
            <span class="sb-ico">👫</span>
            <span>Shared</span>
        </a>

        <a class="sb-link <?= ($currentPage === 'starred.php' ? 'active' : '') ?>"
           href="../../pkg/file-management/starred.php">
            <span class="sb-ico">⭐</span>
            <span>Starred</span>
        </a>

        <a class="sb-link <?= ($currentPage === 'bin.php' ? 'active' : '') ?>"
           href="../../pkg/file-management/bin.php">
            <span class="sb-ico">🗑️</span>
            <span>Bin</span>
        </a>

    </nav>

    <!-- STORAGE (UI ONLY) -->
    <div class="sb-storage">
        <div class="sb-storage-title">
            <span class="sb-ico">☁️</span> Storage
        </div>

        <div class="sb-storage-bar">
            <div class="sb-storage-fill" style="width:35%"></div>
        </div>

        <div class="sb-storage-meta">
            <span>176 GB used</span>
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
</script>
