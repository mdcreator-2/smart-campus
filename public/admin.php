<?php
require_once '../config/config.php';
require_once '../config/thingspeak.php';

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

// Get current tab
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'issues';
$success_msg = isset($_GET['success']) ? $_GET['success'] : '';
$error_msg = isset($_GET['error']) ? $_GET['error'] : '';

// ============ ISSUE MANAGEMENT DATA ============
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

// ============ RESOURCE MONITORING DATA ============
$water_tank = getWaterTankLevel();
$feedback_days = isset($_GET['feedback_days']) ? (int)$_GET['feedback_days'] : 1;
$clean_feedback = getCleanFeedback($feedback_days);
$grouped_feedback = groupFeedbackByDate($clean_feedback);
$today_feedback = countFeedbackByDate($clean_feedback, date('Y-m-d'));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .water-level { height: 200px; border: 2px solid #333; position: relative; background: linear-gradient(to top, #3498db 0%, transparent 100%); }
        .level-indicator { position: absolute; bottom: 0; left: 0; right: 0; background: #3498db; transition: height 0.3s ease; }
        .feedback-stat { text-align: center; padding: 15px; border-radius: 5px; }
        .feedback-bad { background: #ffebee; border-left: 4px solid #f44336; }
        .feedback-normal { background: #fff3e0; border-left: 4px solid #ff9800; }
        .feedback-good { background: #e8f5e9; border-left: 4px solid #4caf50; }
        .nav-tabs .nav-link { color: #495057; }
        .nav-tabs .nav-link.active { background-color: #007bff; color: white; }
        
        /* Image carousel styles */
        .issue-image-container {
            position: relative;
            width: 100%;
            height: 200px;
            background: #f0f0f0;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .issue-image-wrapper {
            width: 100%;
            height: 100%;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .issue-image-wrapper.active {
            display: flex;
        }
        .issue-image-wrapper img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        .image-nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 18px;
            border-radius: 3px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 10;
        }
        .issue-image-container:hover .image-nav-arrow {
            opacity: 1;
        }
        .image-nav-arrow:hover {
            background: rgba(0, 0, 0, 0.8);
        }
        .image-nav-arrow.left {
            left: 5px;
        }
        .image-nav-arrow.right {
            right: 5px;
        }
        .image-counter {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            z-index: 5;
        }
    </style>
    <title>Admin Panel | Smart Campus</title>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Smart Campus Admin</a>
        <div class="ms-auto">
            <a href="index.php" class="btn btn-outline-light me-2 btn-sm">Dashboard</a>
            <form action="logout.php" method="post" class="d-inline">
                <button type="submit" class="btn btn-outline-danger btn-sm">Logout</button>
            </form>
        </div>
    </div>
</nav>

<div class="container-fluid p-3">
    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabs Navigation -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'issues' ? 'active' : '' ?>" href="?tab=issues">Issue Management</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'resources' ? 'active' : '' ?>" href="?tab=resources">Resource Monitoring</a>
        </li>
    </ul>

    <!-- ========== ISSUE MANAGEMENT TAB ========== -->
    <div id="issues-tab" class="tab-content <?= $tab === 'issues' ? 'active' : '' ?>">
        <h2 class="mb-4">Issue Management</h2>
        <form class="row g-2 mb-4" method="get">
            <input type="hidden" name="tab" value="issues">
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <option value="open"<?= $status=="open"?" selected":'' ?>>Open</option>
                    <option value="in_progress"<?= $status=="in_progress"?" selected":'' ?>>In Progress</option>
                    <option value="resolved"<?= $status=="resolved"?" selected":'' ?>>Resolved</option>
                    <option value="rejected"<?= $status=="rejected"?" selected":'' ?>>Rejected</option>
                    <option value="archive"<?= $status=="archive"?" selected":'' ?>>Archived</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"<?= $category==$cat['id']?" selected":'' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="keyword" class="form-control form-control-sm" placeholder="Search keyword" value="<?= htmlspecialchars($keyword) ?>">
            </div>
            <div class="col-md-3">
                <input type="text" name="user" class="form-control form-control-sm" placeholder="User email" value="<?= htmlspecialchars($user) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
            </div>
        </form>

        <div class="row g-3">
            <?php if (empty($issues)): ?>
                <div class="col-12"><div class="alert alert-info">No issues found.</div></div>
            <?php else: ?>
                <?php foreach ($issues as $issue): ?>
                    <?php 
                    // Fetch images for this issue
                    $img_stmt = $conn->prepare('SELECT photo_url FROM issue_photos WHERE issue_id = ? ORDER BY uploaded_at ASC');
                    $img_stmt->execute([$issue['id']]);
                    $images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <!-- Issue Images with Navigation -->
                            <?php if (!empty($images)): ?>
                                <div class="issue-image-container" data-issue-id="<?= $issue['id'] ?>">
                                    <?php foreach ($images as $idx => $img): ?>
                                        <div class="issue-image-wrapper <?= $idx === 0 ? 'active' : '' ?>" data-index="<?= $idx ?>">
                                            <img src="/SBM/smart-campus/<?= htmlspecialchars($img['photo_url']) ?>" alt="Issue photo">
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($images) > 1): ?>
                                        <button class="image-nav-arrow left" data-issue-id="<?= $issue['id'] ?>" data-direction="prev">&larr;</button>
                                        <button class="image-nav-arrow right" data-issue-id="<?= $issue['id'] ?>" data-direction="next">&rarr;</button>
                                    <?php endif; ?>
                                    
                                    <div class="image-counter"><?= count($images) > 1 ? '1 / ' . count($images) : '1 / 1' ?></div>
                                </div>
                            <?php else: ?>
                                <div class="issue-image-container" style="display: flex; align-items: center; justify-content: center;">
                                    <span class="text-muted">No images</span>
                                </div>
                            <?php endif; ?>

                            <div class="card-body">
                                <h6 class="card-title">#<?= $issue['id'] ?> - <?= htmlspecialchars(substr($issue['title'], 0, 40)) ?></h6>
                                <p class="mb-1"><small>
                                    <strong>Status:</strong> 
                                    <span class="badge bg-info"><?= htmlspecialchars($issue['status']) ?></span>
                                </small></p>
                                <p class="mb-1"><small>
                                    <strong>User:</strong> <?= htmlspecialchars($issue['user_name']) ?>
                                </small></p>
                                <p class="mb-2"><small class="text-muted"><?= htmlspecialchars($issue['categories']) ?></small></p>
                                <p class="card-text"><small><?= nl2br(htmlspecialchars(strlen($issue['description'])>100?substr($issue['description'],0,100).'...':$issue['description'])) ?></small></p>
                                
                                <form method="post" action="admin_action.php" class="mt-2">
                                    <input type="hidden" name="issue_id" value="<?= $issue['id'] ?>">
                                    <div class="mb-2">
                                        <select name="action" class="form-select form-select-sm" required>
                                            <option value="">Update Status</option>
                                            <option value="in_progress">In Progress</option>
                                            <option value="resolved">Resolve</option>
                                            <option value="rejected">Reject</option>
                                            <option value="archive">Archive</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <input type="text" name="note" class="form-control form-control-sm" placeholder="Note (optional)">
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary w-100">Update</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========== RESOURCE MONITORING TAB ========== -->
    <div id="resources-tab" class="tab-content <?= $tab === 'resources' ? 'active' : '' ?>">
        <h2 class="mb-4">Resource Monitoring</h2>

        <!-- Water Tank Section -->
        <div class="row mb-5">
            <div class="col-12">
                <h4>Water Tank Level</h4>
                <?php if ($water_tank): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="water-level">
                                <div class="level-indicator" style="height: <?= ($water_tank['field2'] ?? 0) . '%' ?>"></div>
                                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold;">
                                    <?= number_format((float)$water_tank['field2'], 1) ?>%
                                </div>
                            </div>
                            <p class="mt-2 text-muted"><small>Last Updated: <?= htmlspecialchars($water_tank['created_at']) ?></small></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">Unable to fetch water tank data from ThingSpeak. Check API configuration.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Clean Feedback Section -->
        <div class="row">
            <div class="col-12">
                <h4>Cleanliness Feedback</h4>
                
                <!-- Filter by days -->
                <form method="get" class="mb-3">
                    <input type="hidden" name="tab" value="resources">
                    <div class="row g-2">
                        <div class="col-md-2">
                            <select name="feedback_days" class="form-select form-select-sm">
                                <option value="1"<?= $feedback_days==1?" selected":'' ?>>Today</option>
                                <option value="7"<?= $feedback_days==7?" selected":'' ?>>Last 7 Days</option>
                                <option value="30"<?= $feedback_days==30?" selected":'' ?>>Last 30 Days</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>
                        </div>
                    </div>
                </form>

                <!-- Today's Stats -->
                <h5>Today's Feedback Summary</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="feedback-stat feedback-good">
                            <h3><?= $today_feedback['good'] ?></h3>
                            <small>Good</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="feedback-stat feedback-normal">
                            <h3><?= $today_feedback['normal'] ?></h3>
                            <small>Normal</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="feedback-stat feedback-bad">
                            <h3><?= $today_feedback['bad'] ?></h3>
                            <small>Bad</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="feedback-stat" style="background: #f5f5f5; border-left: 4px solid #999;">
                            <h3><?= array_sum($today_feedback) ?></h3>
                            <small>Total</small>
                        </div>
                    </div>
                </div>

                <!-- Historical Data -->
                <h5>Feedback by Date</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th class="text-success">Good</th>
                                <th class="text-warning">Normal</th>
                                <th class="text-danger">Bad</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($grouped_feedback)): ?>
                                <tr><td colspan="5" class="text-center text-muted">No feedback data available</td></tr>
                            <?php else: ?>
                                <?php foreach (array_slice($grouped_feedback, 0, 30) as $date => $counts): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($date) ?></td>
                                        <td class="text-success"><strong><?= $counts['good'] ?></strong></td>
                                        <td class="text-warning"><strong><?= $counts['normal'] ?></strong></td>
                                        <td class="text-danger"><strong><?= $counts['bad'] ?></strong></td>
                                        <td><?= array_sum($counts) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Tab switching
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            window.location.href = this.getAttribute('href');
        });
    });

    // Image carousel navigation
    document.querySelectorAll('.image-nav-arrow').forEach(arrow => {
        arrow.addEventListener('click', function() {
            const issueId = this.getAttribute('data-issue-id');
            const direction = this.getAttribute('data-direction');
            const container = document.querySelector(`[data-issue-id="${issueId}"]`);
            
            if (!container) return;
            
            const wrappers = container.querySelectorAll('.issue-image-wrapper');
            const activeWrapper = container.querySelector('.issue-image-wrapper.active');
            const currentIndex = Array.from(wrappers).indexOf(activeWrapper);
            
            let nextIndex;
            if (direction === 'next') {
                nextIndex = (currentIndex + 1) % wrappers.length;
            } else {
                nextIndex = (currentIndex - 1 + wrappers.length) % wrappers.length;
            }
            
            // Update active wrapper
            wrappers.forEach(w => w.classList.remove('active'));
            wrappers[nextIndex].classList.add('active');
            
            // Update counter
            const counter = container.querySelector('.image-counter');
            if (counter) {
                counter.textContent = (nextIndex + 1) + ' / ' + wrappers.length;
            }
        });
    });

    // Keyboard navigation support (optional, for demo)
    document.addEventListener('keydown', function(e) {
        const activeContainer = document.querySelector('.issue-image-container:hover');
        if (activeContainer) {
            const issueId = activeContainer.getAttribute('data-issue-id');
            if (e.key === 'ArrowRight') {
                const rightArrow = document.querySelector(`.image-nav-arrow.right[data-issue-id="${issueId}"]`);
                if (rightArrow) rightArrow.click();
            } else if (e.key === 'ArrowLeft') {
                const leftArrow = document.querySelector(`.image-nav-arrow.left[data-issue-id="${issueId}"]`);
                if (leftArrow) leftArrow.click();
            }
        }
    });
</script>
</body>
</html>
