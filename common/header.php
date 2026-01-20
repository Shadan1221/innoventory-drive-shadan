<?php
// common/header.php
?>
<header class="site-header">
  <!-- Left empty (future logo/crumbs if needed) -->
  <div class="header-left"></div>

  <!-- Center Search -->
  <div class="header-center">
    <form class="global-search" action="/innoventory-drive/pkg/user-management/search.php" method="GET">
      <input type="text" name="q" placeholder="Search by name/email/status..." autocomplete="off">
      <button type="submit">Search</button>
    </form>
  </div>

  <!-- Right controls -->
  <div class="header-right">
    <button id="themeToggle" class="theme-btn" aria-label="Toggle theme" title="Toggle dark mode">🌙</button>
  </div>
</header>


<script>
document.addEventListener("DOMContentLoaded", () => {
  const btn = document.getElementById("themeToggle");
  if (!btn) return;

  function apply(theme) {
    if (theme === "dark") {
      document.documentElement.setAttribute("data-theme", "dark");
      btn.textContent = "☀️";
    } else {
      document.documentElement.removeAttribute("data-theme");
      btn.textContent = "🌙";
    }
  }

  // load saved theme
  const saved = localStorage.getItem("innoventory-theme") || "light";
  apply(saved);

  btn.addEventListener("click", () => {
    const current = document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light";
    const next = current === "dark" ? "light" : "dark";
    localStorage.setItem("innoventory-theme", next);
    apply(next);
  });
});
</script>

