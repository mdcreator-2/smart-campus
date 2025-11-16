<?php
require_once '../config/config.php';

// Require login
requireLogin();

$user_id = $_SESSION['user_id'];
$edit_mode = isset($_GET['edit']) ? true : false;
$error = isset($_GET['error']) ? $_GET['error'] : '';
$success = isset($_GET['success']) ? $_GET['success'] : '';

// Define available hostels
$hostels = ['Sone A', 'Sone B', 'Koshi', 'Koshi Ext.', 'Baghmati', 'Ganga'];

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: login.php');
    exit();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .profile-pic-container {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
        }
        .profile-pic {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: #999;
        }
        .profile-pic-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #667eea;
            border: 3px solid white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 20px;
            transition: background 0.3s;
        }
        .profile-pic-upload:hover {
            background: #764ba2;
        }
        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
            flex: 0 0 150px;
        }
        .info-value {
            flex: 1;
            color: #212529;
        }
        .badge-custom {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .points-badge {
            background: #fff3cd;
            color: #856404;
        }
        .rank-badge {
            background: #d1ecf1;
            color: #0c5460;
        }
        .status-badge {
            background: #d4edda;
            color: #155724;
        }
        .form-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        .profile-pic-file-input {
            display: none;
        }
        .btn-group-custom {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
    <title>Profile | Smart Campus</title>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="index.php">Smart Campus</a>
        <div class="ms-auto">
            <a href="index.php" class="btn btn-outline-secondary me-2">Dashboard</a>
            <form action="logout.php" method="post" class="d-inline">
                <button type="submit" class="btn btn-outline-danger">Logout</button>
            </form>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="text-center">
                    <div class="profile-pic-container">
                        <?php if (!empty($user['profile_pic']) && file_exists('../' . $user['profile_pic'])): ?>
                            <img src="/SBM/smart-campus/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile" class="profile-pic">
                        <?php else: ?>
                            <div class="profile-pic">👤</div>
                        <?php endif; ?>
                        <?php if ($edit_mode): ?>
                            <div class="profile-pic-upload" onclick="document.querySelector('.profile-pic-file-input').click();" title="Change profile picture">
                                📷
                            </div>
                        <?php endif; ?>
                    </div>
                    <h2 class="mb-2"><?php echo htmlspecialchars($user['name'] ?? 'No Name'); ?></h2>
                    <p class="mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <!-- Stats Section -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="info-section text-center">
                        <div class="badge-custom points-badge">
                            <span style="font-size: 24px;">⭐</span><br>
                            <?php echo (int)$user['total_points']; ?> Points
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-section text-center">
                        <div class="badge-custom rank-badge">
                            <span style="font-size: 24px;">🏆</span><br>
                            <?php echo htmlspecialchars($user['rank'] ?? 'Beginner'); ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-section text-center">
                        <div class="badge-custom status-badge">
                            <span style="font-size: 24px;">👤</span><br>
                            <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Information -->
            <?php if (!$edit_mode): ?>
                <!-- View Mode -->
                <div class="info-section mb-4">
                    <h4 class="mb-3">Personal Information</h4>
                    
                    <div class="info-row">
                        <div class="info-label">Email:</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Full Name:</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['name'] ?? 'Not Set'); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Hostel:</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['hostel'] ?? 'Not Set'); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Roll Number:</div>
                        <div class="info-value"><?php echo htmlspecialchars($user['roll_no'] ?? 'Not Set'); ?></div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Member Since:</div>
                        <div class="info-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                    </div>
                </div>

                <div class="info-section mb-4">
                    <h4 class="mb-3">Points & Achievements</h4>
                    
                    <div class="info-row">
                        <div class="info-label">Total Points:</div>
                        <div class="info-value">
                            <span class="badge bg-warning text-dark"><?php echo (int)$user['total_points']; ?> ⭐</span>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Current Rank:</div>
                        <div class="info-value">
                            <span class="badge bg-info"><?php echo htmlspecialchars($user['rank'] ?? 'Beginner'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="profile.php?edit=1" class="btn btn-primary">Edit Profile</a>
                    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>

            <?php else: ?>
                <!-- Edit Mode -->
                <div class="form-section">
                    <h4 class="mb-4">Edit Profile</h4>
                    <form method="post" action="update_profile.php" enctype="multipart/form-data">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="hostel" class="form-label">Hostel</label>
                            <select class="form-control" id="hostel" name="hostel" required>
                                <option value="">-- Select Hostel --</option>
                                <?php foreach ($hostels as $hostel_option): ?>
                                    <option value="<?php echo htmlspecialchars($hostel_option); ?>" 
                                        <?php echo ($user['hostel'] === $hostel_option) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($hostel_option); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="roll_no" class="form-label">Roll Number</label>
                            <input type="text" class="form-control" id="roll_no" name="roll_no" value="<?php echo htmlspecialchars($user['roll_no'] ?? ''); ?>" placeholder="e.g., 19BCS001">
                        </div>

                        <div class="mb-3">
                            <label for="profile_pic" class="form-label">Profile Picture</label>
                            <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*">
                            <small class="text-muted">Max size: 2MB. Formats: JPG, PNG</small>
                        </div>

                        <hr>

                        <h5>Change Password</h5>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password (leave blank to skip)</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6">
                        </div>

                        <div class="btn-group-custom">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="profile.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Hidden file input for profile picture
    const fileInput = document.querySelector('.profile-pic-file-input');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = document.querySelector('.profile-pic');
                    if (img) {
                        img.src = event.target.result;
                        img.style.cssText = 'width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid white;';
                    }
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
</script>
</body>
</html>
