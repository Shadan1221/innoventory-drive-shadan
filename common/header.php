<?php
// common/header.php
// Only show header with search bar if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
?>
<header class="site-header">
  <!-- Left empty (future logo/crumbs if needed) -->
  <div class="header-left"></div>

  <!-- Center Search -->
  <?php if ($isLoggedIn): ?>
  <div class="header-center">
    <form class="global-search" action="/innoventory/innoventory/pkg/user-management/search.php" method="GET">
      <input type="text" name="q" placeholder="Search files in My Drive..." autocomplete="off">
      <button type="submit">Search</button>
    </form>
  </div>
  <?php else: ?>
  <div class="header-center"></div>
  <?php endif; ?>

  <!-- Right controls -->
  <div class="header-right">
    <button id="themeToggle" class="theme-btn" aria-label="Toggle theme" title="Toggle dark mode">🌙</button>
  </div>
</header>


<script>
// Theme toggle functionality - works on all pages
(function() {
  function applyTheme(theme) {
    if (theme === "dark") {
      document.documentElement.setAttribute("data-theme", "dark");
    } else {
      document.documentElement.removeAttribute("data-theme");
    }
    
    // Update button if it exists
    const btn = document.getElementById("themeToggle");
    if (btn) {
      btn.textContent = theme === "dark" ? "☀️" : "🌙";
    }
  }

  // Load and apply saved theme immediately (before DOMContentLoaded)
  const saved = localStorage.getItem("innoventory-theme") || "light";
  applyTheme(saved);

  // Set up event listeners after DOM loads
  document.addEventListener("DOMContentLoaded", function() {
    const btn = document.getElementById("themeToggle");
    
    if (btn) {
      btn.addEventListener("click", function() {
        const current = document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light";
        const next = current === "dark" ? "light" : "dark";
        localStorage.setItem("innoventory-theme", next);
        applyTheme(next);
      });
    }
  });
})();
</script>

