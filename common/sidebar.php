<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<aside class="sidebar">

    <!-- LOGO -->
    <div class="site-logo">
        <img src="../../logo/logo.png" alt="Innoventory Logo">
    </div>

    <!-- PROFILE BOX -->
    <div class="profile-box" id="profileToggle">
        <div class="profile-name">
            <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>
        </div>
        <div class="profile-role">
            <?= ucfirst($_SESSION['role'] ?? '') ?>
        </div>

        <!-- DROPDOWN -->
        <div class="profile-menu" id="profileMenu">
            <a href="../../pkg/user-management/update_user.php">⚙️ Settings</a>
            <a href="../../pkg/user-management/logout.php">🚪 Logout</a>
        </div>
    </div>

    <!-- + NEW BUTTON -->
    <div class="new-btn-wrapper">
        <button class="btn-new" onclick="document.getElementById('uploadInput').click()">
            + New
        </button>
    </div>

    <!-- UPLOAD FORM -->
    <form action="../../pkg/file-management/upload.php"
          method="POST"
          enctype="multipart/form-data">
        <input type="file"
               id="uploadInput"
               name="upload"
               hidden
               onchange="this.form.submit()">
    </form>

    <!-- MENU -->
    <ul class="menu">
        <li class="active">
            <a href="../../pkg/user-management/<?= $_SESSION['role'] ?>_dashboard.php">
                Dashboard
            </a>
        </li>

        <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
            <li>
                <a href="../../pkg/user-management/users.php">Users</a>
            </li>
        <?php endif; ?>

        <li><a href="#">My Drive</a></li>
        <li><a href="#">Shared</a></li>
        <li><a href="#">Starred</a></li>
        <li><a href="#">Bin</a></li>
        <li><a href="#">Storage</a></li>
    </ul>

</aside>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const toggle = document.getElementById("profileToggle");
    const menu   = document.getElementById("profileMenu");

    if (toggle && menu) {
        toggle.addEventListener("click", function (e) {
            e.stopPropagation();
            menu.classList.toggle("show");
        });

        document.addEventListener("click", function () {
            menu.classList.remove("show");
        });
    }
});
</script>
