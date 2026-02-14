<?php
require_once "../../session.php";
require_once "../../config.php";

/* Security */
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "user") {
    header("Location: ../../index.php");
    exit;
}

/* Status check */
$id = $_SESSION["user_id"];
$stmt = $db->prepare("SELECT status FROM users WHERE id=? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($user["status"] !== "approved") {
    header("Location: ../../index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard - Innoventory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../css/main.css">
</head>

<body>

<?php include '../../common/header.php'; ?>

<div class="app-grid">

    <!-- SIDEBAR -->
    <?php include '../../common/menu.php'; ?>

    <!-- MAIN -->
    <main>
        <div class="dashboard-card">

            <div class="dashboard-header">
                <h1>User Dashboard</h1>
                <span>Welcome, <?= htmlspecialchars($_SESSION['name']) ?></span>
            </div>

            <?php if (isset($_SESSION['status_change_message'])): ?>
                <div class="message success" style="padding: 12px 16px; margin-bottom: 20px; border-radius: 8px; border-left: 4px solid #22c55e; background: #dcfce7; color: #15803d;">
                    <?= htmlspecialchars($_SESSION['status_change_message']) ?>
                </div>
                <?php unset($_SESSION['status_change_message']); ?>
            <?php endif; ?>

            <?php if (isset($_GET['upload']) && $_GET['upload'] === 'success'): ?>
                <div class="message success">File uploaded successfully!</div>
            <?php endif; ?>

            <?php if (isset($_GET['upload']) && $_GET['upload'] === 'fail'): ?>
                <div class="message error">File upload failed </div>
            <?php endif; ?>

            <div class="empty-state">
                More features coming soon...
            </div>

        </div>
    </main>

</div>

</body>
</html>
