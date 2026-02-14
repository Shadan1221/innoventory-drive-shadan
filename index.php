<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Load config
require_once "config.php";

$error = "";

/* ---------------- HANDLE LOGIN ---------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login_btn'])) {

    $login_type = ($_POST['login_type'] === 'admin') ? 'admin' : 'user';
    $password   = trim($_POST['password']);

    if ($login_type === 'admin') {
        $username = trim($_POST['username']);
        $stmt = $db->prepare("
            SELECT id, name, password, role, status, status_notified
            FROM users 
            WHERE (name = ? OR email = ?) AND role = 'admin'
            LIMIT 1
        ");
        $stmt->bind_param("ss", $username, $username);
    } else {
        $email = trim($_POST['email']);
        $stmt = $db->prepare("
            SELECT id, name, password, role, status, status_notified
            FROM users 
            WHERE email = ? AND role = 'user'
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {

        // Check for status change notification
        $statusChanged = false;
        $statusMessage = '';
        
        if ($user['status_notified'] !== $user['status']) {
            $statusChanged = true;
            
            // User was denied but now approved
            if ($user['status_notified'] === 'denied' && $user['status'] === 'approved') {
                $statusMessage = "Good news! Your account has been approved. Welcome back!";
            }
            // User was approved but now denied
            elseif ($user['status_notified'] === 'approved' && $user['status'] === 'denied') {
                $statusMessage = "Your account access has been revoked. Please contact the administrator.";
            }
        }

        if ($user['status'] === 'pending') {
            $error = "Your account is still pending approval.";
        } elseif ($user['status'] === 'denied' && !$statusChanged) {
            $error = "Your access has been denied.";
        } elseif ($user['status'] === 'denied' && $statusChanged) {
            // Just denied - show message and don't allow login
            $error = $statusMessage;
            
            // Update the notified status
            $updateStmt = $db->prepare("UPDATE users SET status_notified = ? WHERE id = ?");
            $updateStmt->bind_param("si", $user['status'], $user['id']);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            // ✅ CORRECT SESSION KEYS
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['name']     = $user['name'];
            $_SESSION['role']     = $user['role'];
            
            // If there's a status change message, store it in session
            if ($statusChanged && $statusMessage) {
                $_SESSION['status_change_message'] = $statusMessage;
            }
            
            // Update the notified status
            $updateStmt = $db->prepare("UPDATE users SET status_notified = ? WHERE id = ?");
            $updateStmt->bind_param("si", $user['status'], $user['id']);
            $updateStmt->execute();
            $updateStmt->close();

            // Redirect correctly
            if ($user['role'] === 'admin') {
                header("Location: pkg/user-management/admin_dashboard.php");
            } else {
                header("Location: pkg/user-management/user_dashboard.php");
            }
            exit;
        }
    } else {
        $error = "Invalid credentials.";
    }
}

/* ---------------- ALREADY LOGGED IN ---------------- */
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {

    $id = $_SESSION['user_id'];

    $stmt = $db->prepare("SELECT role, status FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user['status'] === 'approved') {
        if ($user['role'] === 'admin') {
            header("Location: pkg/user-management/admin_dashboard.php");
        } else {
            header("Location: pkg/user-management/user_dashboard.php");
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign In - Innoventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/main.css">
</head>
<body class="auth-page">

<?php include "common/header.php"; ?>

<div class="auth-container">

    <div class="form-logo">
        <img id="themeLogoAuth" src="logo/logo (light).png" alt="Innoventory logo" data-light="logo/logo (light).png" data-dark="logo/logo (dark).png">
    </div>

    <h1 style="text-align:center;">Sign in</h1>

    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php">
        <input type="hidden" name="login_type" id="login_type" value="user">

        <!-- USER LOGIN -->
        <div id="userFields">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="email">
            </div>
        </div>

        <!-- ADMIN LOGIN -->
        <div id="adminFields" style="display:none;">
            <div class="form-group">
                <label>Username or Email</label>
                <input type="text" name="username" id="username">
            </div>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" id="password" required>
        </div>

        <div class="checkbox-group">
            <input type="checkbox" id="showPassword" onchange="togglePassword()">
            <label for="showPassword">Show Password</label>
        </div>

        <button type="submit" name="login_btn" class="btn-primary">
            Sign in
        </button>
    </form>

    <div class="actions-vertical">
        <button id="btnAdmin" class="btn-ghost">Sign in as Admin</button>
        <a href="pkg/user-management/register.php" class="btn-ghost">
            Request access as user
        </a>
    </div>
</div>

<script>
function togglePassword() {
    const p = document.getElementById('password');
    p.type = (p.type === 'password') ? 'text' : 'password';
}

const btnAdmin    = document.getElementById('btnAdmin');
const userFields  = document.getElementById('userFields');
const adminFields = document.getElementById('adminFields');
const loginType   = document.getElementById('login_type');
const emailInput  = document.getElementById('email');
const userInput   = document.getElementById('username');

let adminMode = false;

// Theme-aware logo switching for auth page
function updateAuthLogo() {
    const logo = document.getElementById('themeLogoAuth');
    if (logo) {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const lightSrc = logo.getAttribute('data-light');
        const darkSrc = logo.getAttribute('data-dark');
        logo.src = isDark ? darkSrc : lightSrc;
    }
}

// Update logo on page load
updateAuthLogo();

// Listen for theme changes
const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.attributeName === 'data-theme') {
            updateAuthLogo();
        }
    });
});
observer.observe(document.documentElement, { attributes: true });

btnAdmin.addEventListener('click', function (e) {
    e.preventDefault();
    adminMode = !adminMode;

    if (adminMode) {
        userFields.style.display = 'none';
        adminFields.style.display = 'block';
        loginType.value = 'admin';

        emailInput.required = false;
        userInput.required  = true;

        btnAdmin.textContent = 'Back to User Sign in';
    } else {
        userFields.style.display = 'block';
        adminFields.style.display = 'none';
        loginType.value = 'user';

        emailInput.required = true;
        userInput.required  = false;

        btnAdmin.textContent = 'Sign in as Admin';
    }
});
</script>

</body>
</html>
