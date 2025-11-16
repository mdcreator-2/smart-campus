<?php
require_once '../config/config.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $issue_id = isset($_POST['issue_id']) ? (int)$_POST['issue_id'] : 0;
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';
    $admin_id = $_SESSION['user_id'];

    $valid_actions = ['in_progress','resolved','rejected','archive'];
    if (!$issue_id || !in_array($action, $valid_actions)) {
        header('Location: admin.php?tab=issues&error=Invalid+action');
        exit();
    }

    try {
        $conn->beginTransaction();
        
        // Update issue status
        $update_sql = "UPDATE issues SET status = ?, admin_id = ?, updated_at = NOW()";
        if ($action === 'resolved') {
            $update_sql .= ", resolved_at = NOW()";
        } elseif ($action === 'rejected') {
            $update_sql .= ", rejected_at = NOW()";
        }
        $update_sql .= " WHERE id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->execute([$action, $admin_id, $issue_id]);

        // Log action in points_log (for audit)
        $log_reason = 'Admin action: ' . $action . ($note ? ' | Note: ' . $note : '');
        $stmt = $conn->prepare("INSERT INTO points_log (user_id, issue_id, points_change, reason) VALUES (?, ?, 0, ?)");
        $stmt->execute([$admin_id, $issue_id, $log_reason]);

        $conn->commit();
        $msg = 'Issue ' . $action . ' successfully!';
        header('Location: admin.php?tab=issues&success=' . urlencode($msg));
        exit();
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        header('Location: admin.php?tab=issues&error=Database+error');
        exit();
    }
} else {
    header('Location: admin.php?tab=issues');
    exit();
}

