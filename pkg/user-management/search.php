<?php
require_once "../../session.php";
require_once "../../config.php";

// SECURITY: only logged in users
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("Location: ../../index.php");
    exit;
}

// OPTIONAL: Only admin can search users (recommended)
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../../index.php");
    exit;
}

$q = trim($_GET["q"] ?? "");
$results = [];

if ($q !== "") {
    $search = "%" . $q . "%";

    // ✅ FIXED QUERY: removed `location`
    $stmt = $db->prepare("
        SELECT id, name, email, role, status
        FROM users
        WHERE name LIKE ? OR email LIKE ? OR role LIKE ? OR status LIKE ?
        ORDER BY role, name
    ");
    $stmt->bind_param("ssss", $search, $search, $search, $search);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $results[] = $row;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Search - Innoventory</title>
    <link rel="stylesheet" href="../../css/main.css" />
</head>

<body>

<?php include "../../common/header.php"; ?>

<div class="app-grid">

    <!-- SIDEBAR -->
    <?php include "../../common/menu.php"; ?>

    <!-- MAIN -->
    <main>
        <div class="dashboard-card">

            <div class="dashboard-header">
                <h1>Search</h1>
                <span>Results for: <b><?= htmlspecialchars($q) ?></b></span>
            </div>

            <!-- Search box -->
            <form method="GET" action="search.php" style="margin-bottom: 20px;">
                <div class="global-search">
                    <input type="text" name="q"
                           placeholder="Search by name/email/role/status..."
                           value="<?= htmlspecialchars($q) ?>" />
                    <button type="submit">Search</button>
                </div>
            </form>

            <?php if ($q === ""): ?>
                <div class="empty-state">
                    Type something in the search box to find users.
                </div>

            <?php elseif (empty($results)): ?>
                <div class="empty-state">
                    No results found.
                </div>

            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead>
                        <tr>
                            <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border);">Name</th>
                            <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border);">Email</th>
                            <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border);">Role</th>
                            <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border);">Status</th>
                            <th style="text-align:left; padding:12px; border-bottom:1px solid var(--border);">Action</th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($results as $u): ?>
                            <tr>
                                <td style="padding:12px; border-bottom:1px solid var(--border);">
                                    <?= htmlspecialchars($u["name"]) ?>
                                </td>
                                <td style="padding:12px; border-bottom:1px solid var(--border);">
                                    <?= htmlspecialchars($u["email"]) ?>
                                </td>
                                <td style="padding:12px; border-bottom:1px solid var(--border);">
                                    <?= htmlspecialchars(ucfirst($u["role"])) ?>
                                </td>
                                <td style="padding:12px; border-bottom:1px solid var(--border);">
                                    <?= htmlspecialchars(ucfirst($u["status"])) ?>
                                </td>
                                <td style="padding:12px; border-bottom:1px solid var(--border);">
                                    <?php if ($u["role"] === "user"): ?>
                                        <a href="../../pkg/file-management/users_drive.php?user_id=<?= (int)$u["id"] ?>"
                                           style="color:var(--accent); text-decoration:none; font-weight:600;">
                                            View Drive
                                        </a>
                                    <?php else: ?>
                                        <span style="color:var(--muted);">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </main>

</div>

</body>
</html>
