<?php
require_once "../../session.php";
require_once "../../config.php";

/* ---------------- AUTH CHECK ---------------- */
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

$is_admin = ($_SESSION['role'] === 'admin');
$msg = "";

/* ---------------- DETERMINE TARGET USER ---------------- */
if ($is_admin) {
    // Admin can load any user
    $user_id = $_GET['uid'] ?? $_SESSION['user_id'];
} else {
    // User can ONLY edit own profile
    $user_id = $_SESSION['user_id'];
}

/* ---------------- UPDATE USER ---------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Enforce server-side authority
    $id   = $user_id;
    $name = trim($_POST['name']);

    if ($is_admin) {
        $role   = $_POST['role'];
        $status = $_POST['status'];
    } else {
        // Users cannot change role or status
        $role   = $_SESSION['role'];
        $status = 'approved';
    }

    $stmt = $db->prepare("
        UPDATE users 
        SET name = ?, role = ?, status = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssi", $name, $role, $status, $id);

    if ($stmt->execute()) {
        $msg = "Profile updated successfully.";
    } else {
        $msg = "Error updating profile.";
    }

    $stmt->close();
}

/* ---------------- LOAD USER DATA ---------------- */
$stmt = $db->prepare("SELECT id, name, email, role, status FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ---------------- USERS LIST (ADMIN ONLY) ---------------- */
if ($is_admin) {
    $users = mysqli_query($db, "SELECT id, name, email FROM users ORDER BY id ASC");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Account Settings</title>
</head>
<body>

<h2>Account Settings</h2>

<?php if ($msg): ?>
    <p style="color:green;"><?php echo $msg; ?></p>
<?php endif; ?>

<!-- ADMIN: SELECT USER -->
<?php if ($is_admin): ?>
<form method="GET">
    <select name="uid" required>
        <option value="">Select user</option>
        <?php while ($u = mysqli_fetch_assoc($users)): ?>
            <option value="<?= $u['id']; ?>" <?= ($u['id'] == $user_id) ? "selected" : "" ?>>
                <?= htmlspecialchars($u['name']); ?> (<?= htmlspecialchars($u['email']); ?>)
            </option>
        <?php endwhile; ?>
    </select>
    <button type="submit">Load User</button>
</form>
<hr>
<?php endif; ?>

<!-- UPDATE FORM -->
<form method="POST">

    <label>Name:</label><br>
    <input type="text" name="name" value="<?= htmlspecialchars($user['name']); ?>" required><br><br>

    <?php if ($is_admin): ?>
        <label>Role:</label><br>
        <select name="role">
            <option value="user"  <?= ($user['role'] === "user")  ? "selected" : "" ?>>User</option>
            <option value="admin" <?= ($user['role'] === "admin") ? "selected" : "" ?>>Admin</option>
        </select><br><br>

        <label>Status:</label><br>
        <select name="status">
            <option value="pending"  <?= ($user['status'] === "pending")  ? "selected" : "" ?>>Pending</option>
            <option value="approved" <?= ($user['status'] === "approved") ? "selected" : "" ?>>Approved</option>
            <option value="denied"   <?= ($user['status'] === "denied")   ? "selected" : "" ?>>Denied</option>
        </select><br><br>
    <?php endif; ?>

    <button type="submit">Save Changes</button>
</form>

</body>
</html>
