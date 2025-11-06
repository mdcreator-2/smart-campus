<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$action = isset($_POST['action']) ? $_POST['action'] : null;
$issue_id = isset($_POST['issue_id']) ? (int)$_POST['issue_id'] : 0;
$user_id = $_SESSION['user_id'];

if (!$issue_id || !in_array($action, ['upvote','downvote','clear'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $conn->beginTransaction();

    // Check existing vote
    $stmt = $conn->prepare("SELECT id, vote FROM issue_votes WHERE user_id = ? AND issue_id = ? FOR UPDATE");
    $stmt->execute([$user_id, $issue_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($action === 'clear') {
        if (!$existing) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => 'No existing vote to clear']);
            exit();
        }
        // Remove vote
        $stmt = $conn->prepare("DELETE FROM issue_votes WHERE id = ?");
        $stmt->execute([$existing['id']]);

        if ($existing['vote'] === 'upvote') {
            $stmt = $conn->prepare("UPDATE issues SET upvotes = GREATEST(0, upvotes - 1) WHERE id = ?");
            $stmt->execute([$issue_id]);
        } else {
            $stmt = $conn->prepare("UPDATE issues SET downvotes = GREATEST(0, downvotes - 1) WHERE id = ?");
            $stmt->execute([$issue_id]);
        }

        // fetch updated counts
        $stmt = $conn->prepare("SELECT upvotes, downvotes FROM issues WHERE id = ?");
        $stmt->execute([$issue_id]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        $conn->commit();

        echo json_encode(['success' => true, 'cleared' => true, 'upvotes' => (int)$counts['upvotes'], 'downvotes' => (int)$counts['downvotes']]);
        exit();
    }

    // If trying to vote (upvote/downvote)
    if ($existing) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'You have already voted on this issue']);
        exit();
    }

    // Insert vote
    $stmt = $conn->prepare("INSERT INTO issue_votes (user_id, issue_id, vote) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $issue_id, $action]);

    if ($action === 'upvote') {
        $stmt = $conn->prepare("UPDATE issues SET upvotes = upvotes + 1 WHERE id = ?");
        $stmt->execute([$issue_id]);
    } else {
        $stmt = $conn->prepare("UPDATE issues SET downvotes = downvotes + 1 WHERE id = ?");
        $stmt->execute([$issue_id]);
    }

    // fetch updated counts
    $stmt = $conn->prepare("SELECT upvotes, downvotes FROM issues WHERE id = ?");
    $stmt->execute([$issue_id]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    $conn->commit();

    echo json_encode(['success' => true, 'upvotes' => (int)$counts['upvotes'], 'downvotes' => (int)$counts['downvotes'], 'vote' => $action]);
    exit();

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}
