<?php
require_once '../config/config.php';

// Admin authentication
function requireAdmin() {
    if (!isLoggedIn() || empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    global $conn;
    $stmt = $conn->prepare('SELECT is_admin FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !$row['is_admin']) {
        header('Location: index.php');
        exit();
    }
}
requireAdmin();

// Filters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$user = isset($_GET['user']) ? $_GET['user'] : '';

// Build query
$query = "SELECT i.*, u.name AS user_name, u.email AS user_email,
    (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM issue_categories ic JOIN categories c ON ic.category_id = c.id WHERE ic.issue_id = i.id) AS categories
    FROM issues i
    JOIN users u ON i.user_id = u.id
    WHERE 1=1";
$params = [];
if ($status) {
    $query .= " AND i.status = ?";
    $params[] = $status;
}
if ($keyword) {
    $query .= " AND (i.title LIKE ? OR i.description LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}
if ($category) {
    $query .= " AND EXISTS (SELECT 1 FROM issue_categories ic WHERE ic.issue_id = i.id AND ic.category_id = ?)";
    $params[] = $category;
}
if ($user) {
    $query .= " AND u.email LIKE ?";
    $params[] = "%$user%";
}
$query .= " ORDER BY i.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for filter
$cat_stmt = $conn->query('SELECT id, name FROM categories');
$categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Admin Panel | Smart Campus</title>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="#">Admin Panel</a>
        <div class="ms-auto">
            <a href="index.php" class="btn btn-outline-light me-2">Dashboard</a>
            <form action="logout.php" method="post" class="d-inline">
                <button type="submit" class="btn btn-outline-danger">Logout</button>
            </form>
        </div>
    </div>
</nav>
<div class="container">
    <h2 class="mb-4">Issue Management</h2>
    <form class="row g-2 mb-4" method="get">
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">All Status</option>
                <option value="open"<?= $status=="open"?" selected":'' ?>>Open</option>
                <option value="in_progress"<?= $status=="in_progress"?" selected":'' ?>>In Progress</option>
                <option value="resolved"<?= $status=="resolved"?" selected":'' ?>>Resolved</option>
                <option value="rejected"<?= $status=="rejected"?" selected":'' ?>>Rejected</option>
                <option value="archive"<?= $status=="archive"?" selected":'' ?>>Archived</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="category" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"<?= $category==$cat['id']?" selected":'' ?>><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" name="keyword" class="form-control" placeholder="Keyword" value="<?= htmlspecialchars($keyword) ?>">
        </div>
        <div class="col-md-3">
            <input type="text" name="user" class="form-control" placeholder="User Email" value="<?= htmlspecialchars($user) ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </form>
    <div class="row g-3">
        <?php if (empty($issues)): ?>
            <div class="col-12"><div class="alert alert-info">No issues found.</div></div>
        <?php endif; ?>
        <?php foreach ($issues as $issue): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">#<?= $issue['id'] ?> <?= htmlspecialchars($issue['title']) ?></h5>
                        <p><strong>Status:</strong> <span class="badge bg-secondary"><?= htmlspecialchars($issue['status']) ?></span></p>
                        <p><strong>User:</strong> <?= htmlspecialchars($issue['user_name']) ?> (<?= htmlspecialchars($issue['user_email']) ?>)</p>
                        <p><strong>Categories:</strong> <?= htmlspecialchars($issue['categories']) ?></p>
                        <p><?= nl2br(htmlspecialchars(strlen($issue['description'])>200?substr($issue['description'],0,200).'...':$issue['description'])) ?></p>
                        <form method="post" action="admin_action.php" class="mt-3">
                            <input type="hidden" name="issue_id" value="<?= $issue['id'] ?>">
                            <div class="mb-2">
                                <select name="action" class="form-select form-select-sm" required>
                                    <option value="">Change Status</option>
                                    <option value="resolved">Resolve</option>
                                    <option value="rejected">Reject</option>
                                    <option value="archive">Archive</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <input type="text" name="note" class="form-control form-control-sm" placeholder="Feedback/Note (optional)">
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Update</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
