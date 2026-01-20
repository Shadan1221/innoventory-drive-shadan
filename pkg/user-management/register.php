<?php
require_once "../../config.php"; // Go up two levels to find config

$msg = "";
$error = "";

// Prevent admin registration via this page. Redirect admin requests to login.
if (isset($_GET['role']) && $_GET['role'] === 'admin') {
    header("Location: ../../index.php");
    exit;
}
$role = 'user';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $role = trim($_POST["role"]); // Get role from hidden field
    // Storage requested in GB (1-100)
    $storage_gb = isset($_POST['storage_gb']) ? intval($_POST['storage_gb']) : 1;
    if ($storage_gb < 1) $storage_gb = 1;
    if ($storage_gb > 100) $storage_gb = 100;
    
    // Check if email exists
    $check = $db->prepare("SELECT id FROM users WHERE email=?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error = "Email already exists.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Both admin and user requests start as 'pending'
        $status = 'pending';
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Ensure DB has storage_gb column; if not, attempt to add it
        $colCheck = mysqli_query($db, "SHOW COLUMNS FROM users LIKE 'storage_gb'");
        if ($colCheck && mysqli_num_rows($colCheck) == 0) {
            @mysqli_query($db, "ALTER TABLE users ADD COLUMN storage_gb INT NOT NULL DEFAULT 1");
        }

        $stmt = $db->prepare("INSERT INTO users (name, email, password, role, status, storage_gb) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssi", $name, $email, $hashed_password, $role, $status, $storage_gb);

        if ($stmt->execute()) {
            if ($role === 'admin') {
                $msg = "Admin registration request submitted! Please wait for approval from an existing admin.";
            } else {
                $msg = "Request submitted successfully! Please wait for Admin approval.";
            }
        } else {
            $error = "Error submitting request.";
        }
        $stmt->close();
    }
    $check->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Access - Innoventory</title>
    <link rel="stylesheet" href="../../css/main.css">
</head>
<body class="auth-page">
    <?php include '../../common/header.php'; ?>

    <div class="auth-container">
        <div class="form-logo">
            <img src="../logo/logo.png" alt="Innoventory logo">
        </div>
        <h1 style="text-align:center; margin-top:0;">Request Access</h1>
        <?php if($msg) echo "<div class='message success'>".htmlspecialchars($msg)."</div>"; ?>
        <?php if($error) echo "<div class='message error'>".htmlspecialchars($error)."</div>"; ?>

        <div class="message" style="background: rgba(59,130,246,0.06); color: var(--accent-strong); font-size:13px;">
            Submit a request for access. An administrator will review and approve your request.
        </div>

        <form method="POST">
            <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" placeholder="Full Name" required autocomplete="name">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Email" required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="password">Create Password</label>
                <input type="password" id="password" name="password" placeholder="Create Password" required autocomplete="new-password" minlength="6">
            </div>
            <div class="form-group">
                <label for="storage_gb">Requested Storage: <span id="storageValue">1</span> GB</label>
                <input type="range" id="storage_gb" name="storage_gb" min="1" max="100" step="1" value="1" oninput="document.getElementById('storageValue').innerText=this.value">
            </div>
            <div class="actions-vertical">
                <button type="submit" class="btn-primary">Submit Request</button>
                <a href="../../index.php" class="btn-ghost">Back to Login</a>
            </div>
        </form>
    </div>
</body>
</html>