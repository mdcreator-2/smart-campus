<?php
require_once '../config/config.php';

// Require login for this page
requireLogin();

// Fetch current user's hostel
$stmt = $conn->prepare("SELECT hostel FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$user_hostel = $user_data['hostel'] ?? null;

// Fetch issues ordered by votes and recency, filtered by user's hostel
$query = "SELECT i.*, (i.upvotes - i.downvotes) AS score,
  (SELECT photo_url FROM issue_photos p WHERE p.issue_id = i.id LIMIT 1) AS photo_url,
  (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM issue_categories ic JOIN categories c ON ic.category_id = c.id WHERE ic.issue_id = i.id) AS categories
  FROM issues i";

if ($user_hostel) {
  $query .= " WHERE i.hostel = ?";
  $stmt = $conn->prepare($query . " ORDER BY (i.upvotes - i.downvotes) DESC, i.created_at DESC");
  $stmt->execute([$user_hostel]);
} else {
  $stmt = $conn->query($query . " ORDER BY (i.upvotes - i.downvotes) DESC, i.created_at DESC");
}

$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch current user's votes
$stmt = $conn->prepare("SELECT issue_id, vote FROM issue_votes WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_votes = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $v) {
  $user_votes[$v['issue_id']] = $v['vote'];
}
?>
<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

    <style>
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

    <title>Dashboard | Smart Campus</title>
  </head>
  <body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
      <div class="container">
        <a class="navbar-brand" href="#">Smart Campus</a>
        <div class="ms-auto d-flex align-items-center gap-2">
          <a href="submit_issue.php" class="btn btn-primary me-2">Submit New Issue</a>
          <a href="profile.php" class="btn btn-outline-secondary me-2" title="Profile">
            <i class="bi bi-person-circle">👤</i> Profile
          </a>
          <form action="logout.php" method="post" class="d-inline">
            <button type="submit" class="btn btn-outline-danger">Logout</button>
          </form>
        </div>
      </div>
    </nav>
    <?php if (isset($_GET['success']) && $_GET['success'] === 'issue_submitted'): ?>
    <div class="container mt-3">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Your issue has been submitted successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="container mt-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h4 class="mb-0">Welcome to Smart Campus</h4>
          <small class="text-muted">
            You are logged in as: <?php echo htmlspecialchars($_SESSION['email']); ?> 
            <?php if ($user_hostel): ?>
              | Viewing issues from: <strong><?php echo htmlspecialchars($user_hostel); ?></strong>
            <?php endif; ?>
          </small>
        </div>
      </div>

      <div class="row g-3" id="issuesList">
        <?php if (empty($issues)): ?>
          <div class="col-12">
            <div class="alert alert-info">No issues found. Be the first to <a href="submit_issue.php">submit an issue</a>.</div>
          </div>
        <?php endif; ?>

        <?php foreach ($issues as $issue): ?>
          <div class="col-md-6 col-lg-4" id="issue-card-<?php echo $issue['id']; ?>">
            <div class="card h-100">
              <!-- Issue Images with Navigation -->
              <?php 
              // Fetch images for this issue
              $img_stmt = $conn->prepare('SELECT photo_url FROM issue_photos WHERE issue_id = ? ORDER BY uploaded_at ASC');
              $img_stmt->execute([$issue['id']]);
              $images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
              ?>
              <?php if (!empty($images)): ?>
                <div class="issue-image-container" data-issue-id="<?php echo $issue['id']; ?>">
                  <?php foreach ($images as $idx => $img): ?>
                    <div class="issue-image-wrapper <?php echo $idx === 0 ? 'active' : ''; ?>" data-index="<?php echo $idx; ?>">
                      <img src="/SBM/smart-campus/<?php echo htmlspecialchars($img['photo_url']); ?>" alt="Issue photo">
                    </div>
                  <?php endforeach; ?>
                  
                  <?php if (count($images) > 1): ?>
                    <button class="image-nav-arrow left" data-issue-id="<?php echo $issue['id']; ?>" data-direction="prev">&larr;</button>
                    <button class="image-nav-arrow right" data-issue-id="<?php echo $issue['id']; ?>" data-direction="next">&rarr;</button>
                  <?php endif; ?>
                  
                  <div class="image-counter"><?php echo count($images) > 1 ? '1 / ' . count($images) : '1 / 1'; ?></div>
                </div>
              <?php else: ?>
                <div class="issue-image-container" style="display: flex; align-items: center; justify-content: center;">
                  <span class="text-muted">No images</span>
                </div>
              <?php endif; ?>

              <div class="card-body d-flex flex-column">
                <h5 class="card-title"><?php echo htmlspecialchars($issue['title']); ?></h5>
                <?php if (!empty($issue['categories'])): ?>
                  <p><small class="text-muted"><?php echo htmlspecialchars($issue['categories']); ?></small></p>
                <?php endif; ?>
                <p class="card-text" style="flex:1"><?php echo nl2br(htmlspecialchars(strlen($issue['description'])>200?substr($issue['description'],0,200).'...':$issue['description'])); ?></p>
                <p class="mb-1"><small class="text-muted">Hostel: <?php echo htmlspecialchars($issue['hostel']); ?> <?php if(!empty($issue['room_number'])) echo ' | Room: '.htmlspecialchars($issue['room_number']); ?></small></p>
                <p class="mb-2"><small class="text-muted">Posted: <?php echo htmlspecialchars($issue['created_at']); ?></small></p>

                <div class="d-flex align-items-center justify-content-between mt-2">
                  <div>
                    <span class="badge bg-success" id="upcount-<?php echo $issue['id']; ?>"><?php echo (int)$issue['upvotes']; ?></span>
                    <small class="text-muted ms-1">Upvotes</small>
                    <span class="badge bg-danger ms-3" id="downcount-<?php echo $issue['id']; ?>"><?php echo (int)$issue['downvotes']; ?></span>
                    <small class="text-muted ms-1">Downvotes</small>
                  </div>

                  <div id="actions-<?php echo $issue['id']; ?>">
                    <?php if (isset($user_votes[$issue['id']])): ?>
                      <?php if ($user_votes[$issue['id']] === 'upvote'): ?>
                        <span class="badge bg-success">You upvoted</span>
                      <?php else: ?>
                        <span class="badge bg-danger">You downvoted</span>
                      <?php endif; ?>
                      <button class="btn btn-sm btn-warning ms-2 clear-vote-btn" data-issue-id="<?php echo $issue['id']; ?>">Clear Vote</button>
                    <?php else: ?>
                      <button class="btn btn-sm btn-success vote-btn" data-action="upvote" data-issue-id="<?php echo $issue['id']; ?>">Upvote</button>
                      <button class="btn btn-sm btn-danger vote-btn ms-2" data-action="downvote" data-issue-id="<?php echo $issue['id']; ?>">Downvote</button>
                    <?php endif; ?>
                  </div>
                </div>

              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </div>

    <!-- Optional JavaScript; choose one of the two! -->

    <!-- Option 1: Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

    <script>
      // Image carousel navigation
      document.querySelectorAll('.image-nav-arrow').forEach(arrow => {
        arrow.addEventListener('click', function() {
          const issueId = this.getAttribute('data-issue-id');
          const direction = this.getAttribute('data-direction');
          const container = document.querySelector(`[data-issue-id="${issueId}"].issue-image-container`);
          
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

      // Voting JS
      async function postVote(issueId, action, buttonEl) {
        try {
          if (buttonEl) buttonEl.disabled = true;
          const form = new FormData();
          form.append('issue_id', issueId);
          form.append('action', action);

          const res = await fetch('vote.php', { method: 'POST', body: form });
          const data = await res.json();
          if (!data.success) {
            alert(data.message || 'Action failed');
            if (buttonEl) buttonEl.disabled = false;
            return;
          }

          // Update counts
          if (typeof data.upvotes !== 'undefined') document.getElementById('upcount-' + issueId).textContent = data.upvotes;
          if (typeof data.downvotes !== 'undefined') document.getElementById('downcount-' + issueId).textContent = data.downvotes;

          const actionsEl = document.getElementById('actions-' + issueId);
          if (action === 'clear') {
            // remove vote, show buttons again
            actionsEl.innerHTML = `
              <button class="btn btn-sm btn-success vote-btn" data-action="upvote" data-issue-id="${issueId}">Upvote</button>
              <button class="btn btn-sm btn-danger vote-btn ms-2" data-action="downvote" data-issue-id="${issueId}">Downvote</button>
            `;
          } else {
            // show vote summary and clear
            const voteLabel = action === 'upvote' ? '<span class="badge bg-success">You upvoted</span>' : '<span class="badge bg-danger">You downvoted</span>';
            actionsEl.innerHTML = voteLabel + `<button class="btn btn-sm btn-warning ms-2 clear-vote-btn" data-issue-id="${issueId}">Clear Vote</button>`;
          }

        } catch (err) {
          console.error(err);
          alert('An error occurred');
        }
      }

      // Delegate click events
      document.addEventListener('click', function(e) {
        const voteBtn = e.target.closest('.vote-btn');
        if (voteBtn) {
          const issueId = voteBtn.getAttribute('data-issue-id');
          const action = voteBtn.getAttribute('data-action');
          postVote(issueId, action, voteBtn);
          return;
        }

        const clearBtn = e.target.closest('.clear-vote-btn');
        if (clearBtn) {
          const issueId = clearBtn.getAttribute('data-issue-id');
          postVote(issueId, 'clear', clearBtn);
          return;
        }
      });
    </script>
  </body>
</html>
