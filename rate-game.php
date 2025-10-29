<?php require_once 'includes/init.php'; 
requireLogin();

$request_id = (int)$_GET['id'];

// Get borrow request details
$stmt = $db->prepare("SELECT br.*, g.title, g.id as game_id, g.user_id as lender_id, u.username, u.full_name
                     FROM borrow_requests br 
                     JOIN games g ON br.game_id = g.id 
                     JOIN users u ON g.user_id = u.id 
                     WHERE br.id = ? AND br.borrower_id = ? AND br.status = 'returned'");
$stmt->execute([$request_id, $_SESSION['user_id']]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    redirect('borrowed.php');
}

$success = '';
$error = '';

// Handle rating submission
if ($_POST) {
    $game_rating = (int)$_POST['game_rating'];
    $lender_rating = (int)$_POST['lender_rating'];
    $review_text = sanitize($_POST['review_text']);
    
    if ($game_rating < 1 || $game_rating > 5 || $lender_rating < 1 || $lender_rating > 5) {
        $error = 'Please provide valid ratings (1-5 stars).';
    } else {
        // Check if already rated
        $stmt = $db->prepare("SELECT id FROM ratings WHERE borrower_id = ? AND game_id = ?");
        $stmt->execute([$_SESSION['user_id'], $request['game_id']]);
        if ($stmt->fetch()) {
            $error = 'You have already rated this game.';
        } else {
            // Insert rating
            $stmt = $db->prepare("INSERT INTO ratings (borrower_id, lender_id, game_id, game_rating, lender_rating, review_text) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$_SESSION['user_id'], $request['lender_id'], $request['game_id'], $game_rating, $lender_rating, $review_text])) {
                $success = 'Thank you for your feedback!';
                
                // No points awarded for rating
                
                // Check for rating badge
                $stmt = $db->prepare("SELECT COUNT(*) FROM ratings WHERE borrower_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $rating_count = $stmt->fetchColumn();
                
                if ($rating_count >= 5) {
                    // Award "Timely Return" badge if not already earned
                    $stmt = $db->prepare("SELECT COUNT(*) FROM user_badges WHERE user_id = ? AND badge_id = 4");
                    $stmt->execute([$_SESSION['user_id']]);
                    if ($stmt->fetchColumn() == 0) {
                        $stmt = $db->prepare("INSERT INTO user_badges (user_id, badge_id) VALUES (?, 4)");
                        $stmt->execute([$_SESSION['user_id']]);
                    }
                }
            } else {
                $error = 'Failed to submit rating.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Game - GameSwap</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-gamepad me-2"></i>GameSwap
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="browse.php">
                            <i class="fas fa-search me-1"></i>Browse Games
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my-games.php">
                            <i class="fas fa-gamepad me-1"></i>My Games
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="borrowed.php">
                            <i class="fas fa-hand-holding me-1"></i>Borrowed
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="leaderboard.php">
                            <i class="fas fa-trophy me-1"></i>Leaderboard
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo $_SESSION['username']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="notifications.php">Notifications</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0"><i class="fas fa-star me-2"></i>Rate Your Experience</h3>
                        <p class="text-muted mb-0">Help the community by sharing your feedback</p>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                            <div class="mt-2">
                                <a href="borrowed.php" class="btn btn-success">Back to Borrowed Games</a>
                            </div>
                        </div>
                        <?php else: ?>
                        
                        <div class="mb-4">
                            <h5><?php echo $request['title']; ?></h5>
                            <p class="text-muted">Lent by <?php echo $request['full_name']; ?> (<?php echo $request['username']; ?>)</p>
                        </div>
                        
                        <form method="POST">
                            <div class="row">
                                <!-- Game Rating -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Rate the Game</label>
                                    <select class="form-select" name="game_rating" required>
                                        <option value="">Select Rating</option>
                                        <option value="1">1 - Poor</option>
                                        <option value="2">2 - Fair</option>
                                        <option value="3">3 - Good</option>
                                        <option value="4">4 - Very Good</option>
                                        <option value="5">5 - Excellent</option>
                                    </select>
                                    <small class="text-muted">How was the game experience?</small>
                                </div>
                                
                                <!-- Lender Rating -->
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Rate the Lender</label>
                                    <select class="form-select" name="lender_rating" required>
                                        <option value="">Select Rating</option>
                                        <option value="1">1 - Poor</option>
                                        <option value="2">2 - Fair</option>
                                        <option value="3">3 - Good</option>
                                        <option value="4">4 - Very Good</option>
                                        <option value="5">5 - Excellent</option>
                                    </select>
                                    <small class="text-muted">How was the lending experience?</small>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="review_text" class="form-label">Write a Review (Optional)</label>
                                <textarea class="form-control" id="review_text" name="review_text" rows="4" 
                                          placeholder="Share your thoughts about the game and lending experience..."></textarea>
                            </div>
                            
                            <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-star me-2"></i>Submit Rating
                                </button>
                                <a href="borrowed.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                        
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
