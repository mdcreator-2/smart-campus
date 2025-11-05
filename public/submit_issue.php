<?php
require_once '../config/config.php';

// Require login for this page
requireLogin();

// Fetch categories for the dropdown
$stmt = $conn->query("SELECT id, name FROM categories");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $hostel = trim($_POST['hostel']);
    $room_number = trim($_POST['room_number']);
    $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
    $selected_categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    
    $error = null;
    
    // Validate inputs
    if (empty($title) || empty($description) || empty($hostel)) {
        $error = "Please fill in all required fields";
    }
    
    // Handle file uploads
    $photo_paths = [];
    if (!empty($_FILES['photos']['name'][0])) {
        $upload_dir = '../storage/uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Check number of files
        if (count($_FILES['photos']['name']) > 4) {
            $error = "Maximum 4 photos allowed";
        } else {
            foreach ($_FILES['photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_extension = strtolower(pathinfo($_FILES['photos']['name'][$key], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png'];
                    
                    if (!in_array($file_extension, $allowed_extensions)) {
                        $error = "Only JPG, JPEG, and PNG files are allowed";
                        break;
                    }
                    
                    $new_filename = uniqid() . '.' . $file_extension;
                    $destination = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($tmp_name, $destination)) {
                        $photo_paths[] = 'storage/uploads/' . $new_filename;
                    } else {
                        $error = "Failed to upload one or more photos";
                        break;
                    }
                }
            }
        }
    } else {
        $error = "At least one photo is required";
    }
    
    // If no errors, proceed with database insertion
    if (!$error) {
        try {
            $conn->beginTransaction();
            
            // Insert issue
            $stmt = $conn->prepare("INSERT INTO issues (user_id, title, description, hostel, room_number, is_anonymous) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $title, $description, $hostel, $room_number, $is_anonymous]);
            $issue_id = $conn->lastInsertId();
            
            // Insert photos
            $stmt = $conn->prepare("INSERT INTO issue_photos (issue_id, photo_url) VALUES (?, ?)");
            foreach ($photo_paths as $photo_url) {
                $stmt->execute([$issue_id, $photo_url]);
            }
            
            // Insert categories
            if (!empty($selected_categories)) {
                $stmt = $conn->prepare("INSERT INTO issue_categories (issue_id, category_id) VALUES (?, ?)");
                foreach ($selected_categories as $category_id) {
                    $stmt->execute([$issue_id, $category_id]);
                }
            }
            
            $conn->commit();
            $success = "Issue submitted successfully!";
            
            // Redirect after successful submission
            header("Location: index.php?success=issue_submitted");
            exit();
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Failed to submit issue. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Issue | Smart Campus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- File input styling -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .preview-images {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .preview-images img {
            max-width: 100px;
            height: auto;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">Smart Campus</a>
            <div class="ms-auto">
                <a href="index.php" class="btn btn-outline-primary me-2">Back to Dashboard</a>
                <form action="logout.php" method="post" class="d-inline">
                    <button type="submit" class="btn btn-outline-danger">Logout</button>
                </form>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Submit New Issue</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <form method="post" action="" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="title" class="form-label">Issue Title*</label>
                                <input type="text" class="form-control" id="title" name="title" required
                                    value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description*</label>
                                <textarea class="form-control" id="description" name="description" rows="4" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="hostel" class="form-label">Hostel*</label>
                                    <input type="text" class="form-control" id="hostel" name="hostel" required
                                        value="<?php echo isset($_POST['hostel']) ? htmlspecialchars($_POST['hostel']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="room_number" class="form-label">Room Number</label>
                                    <input type="text" class="form-control" id="room_number" name="room_number"
                                        value="<?php echo isset($_POST['room_number']) ? htmlspecialchars($_POST['room_number']) : ''; ?>">
                                </div>
                            </div>

                            <?php if (!empty($categories)): ?>
                            <div class="mb-3">
                                <label class="form-label">Categories</label>
                                <div class="row">
                                    <?php foreach ($categories as $category): ?>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="categories[]" 
                                                value="<?php echo $category['id']; ?>" id="category_<?php echo $category['id']; ?>"
                                                <?php echo (isset($_POST['categories']) && in_array($category['id'], $_POST['categories'])) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="category_<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="photos" class="form-label">Photos (1-4 required)*</label>
                                <input type="file" class="form-control" id="photos" name="photos[]" multiple accept="image/*" required
                                    onchange="previewImages(this)">
                                <div class="preview-images mt-2" id="imagePreview"></div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_anonymous" name="is_anonymous"
                                        <?php echo isset($_POST['is_anonymous']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_anonymous">
                                        Submit Anonymously
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Submit Issue</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewImages(input) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files.length > 0) {
                if (input.files.length > 4) {
                    alert('You can only upload up to 4 images');
                    input.value = '';
                    return;
                }
                
                Array.from(input.files).forEach(file => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'img-thumbnail';
                        preview.appendChild(img);
                    }
                    reader.readAsDataURL(file);
                });
            }
        }
    </script>
</body>
</html>