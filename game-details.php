<?php require_once 'includes/init.php'; 

$game_id = (int)$_GET['id'];

// Get game details
$stmt = $db->prepare("SELECT g.*, u.username, u.full_name, u.phone, u.points,
                     COUNT(br.id) as borrow_count,
                     AVG(r.game_rating) as avg_rating,
                     COUNT(r.id) as rating_count
                     FROM games g 
                     JOIN users u ON g.user_id = u.id 
                     LEFT JOIN borrow_requests br ON g.id = br.game_id AND br.status = 'approved'
                     LEFT JOIN ratings r ON g.id = r.game_id
                     WHERE g.id = ?
                     GROUP BY g.id");
$stmt->execute([$game_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    redirect('browse.php');
}

$success = '';
$error = '';

// Handle borrow request
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'request_borrow' && isLoggedIn()) {
    if ($game['user_id'] == $_SESSION['user_id']) {
        $error = 'You cannot borrow your own game.';
    } else {
        // Check if user already has a pending request for this game
        $stmt = $db->prepare("SELECT id FROM borrow_requests WHERE borrower_id = ? AND game_id = ? AND status = 'pending'");
        $stmt->execute([$_SESSION['user_id'], $game_id]);
        if ($stmt->fetch()) {
            $error = 'You already have a pending request for this game.';
        } else {
            $stmt = $db->prepare("INSERT INTO borrow_requests (borrower_id, game_id, notes) VALUES (?, ?, ?)");
            if ($stmt->execute([$_SESSION['user_id'], $game_id, sanitize($_POST['notes'])])) {
                $success = 'Borrow request sent successfully!';
            } else {
                $error = 'Failed to send borrow request.';
            }
        }
    }
}

// Get recent ratings for this game
$stmt = $db->prepare("SELECT r.*, u.username, u.full_name 
                     FROM ratings r 
                     JOIN users u ON r.borrower_id = u.id 
                     WHERE r.game_id = ? AND r.game_rating IS NOT NULL
                     ORDER BY r.created_at DESC LIMIT 5");
$stmt->execute([$game_id]);
$ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $game['title']; ?> - GameSwap</title>
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
                    <?php if (isLoggedIn()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="my-games.php">
                            <i class="fas fa-gamepad me-1"></i>My Games
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="borrowed.php">
                            <i class="fas fa-hand-holding me-1"></i>Borrowed
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="leaderboard.php">
                            <i class="fas fa-trophy me-1"></i>Leaderboard
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
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
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <!-- Alerts -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Game Image and Basic Info -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <?php if ($game['image_path']): ?>
                    <img src="<?php echo $game['image_path']; ?>" class="card-img-top" alt="<?php echo $game['title']; ?>">
                    <?php else: ?>
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 300px;">
                        <i class="fas fa-gamepad fa-3x text-muted"></i>
                    </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <h4 class="card-title"><?php echo $game['title']; ?></h4>
                        <p class="card-text">
                            <span class="badge bg-primary fs-6"><?php echo $game['console_type']; ?></span>
                            <span class="badge bg-secondary fs-6"><?php echo $game['genre']; ?></span>
                            <span class="badge bg-<?php echo $game['availability_status'] == 'available' ? 'success' : ($game['availability_status'] == 'borrowed' ? 'warning' : 'danger'); ?> fs-6">
                                <?php echo ucfirst($game['availability_status']); ?>
                            </span>
                        </p>
                        
                        <!-- Game Stats -->
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <strong><?php echo $game['borrow_count']; ?></strong><br>
                                    <small class="text-muted">Borrowed</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <strong>
                                        <?php 
                                        if ($game['avg_rating']) {
                                            echo number_format($game['avg_rating'], 1);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </strong><br>
                                    <small class="text-muted">Rating</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded p-2">
                                    <strong><?php echo $game['rating_count']; ?></strong><br>
                                    <small class="text-muted">Reviews</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Game Details and Actions -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Game Details</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($game['description']): ?>
                        <p class="card-text"><?php echo nl2br($game['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Shared by:</strong><br>
                                <a href="profile.php?user=<?php echo $game['username']; ?>" class="text-decoration-none">
                                    <?php echo $game['full_name']; ?> (<?php echo $game['username']; ?>)
                                </a><br>
                                <small class="text-muted"><?php echo $game['points']; ?> points</small>
                            </div>
                            <div class="col-md-6">
                                <strong>Contact:</strong><br>
                                <i class="fas fa-phone me-1"></i><?php echo $game['phone']; ?>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Added:</strong><br>
                                <?php echo formatDate($game['created_at']); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Last Updated:</strong><br>
                                <?php echo formatDate($game['updated_at']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Borrow Request Form -->
                <?php if (isLoggedIn() && $game['availability_status'] == 'available' && $game['user_id'] != $_SESSION['user_id']): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-hand-holding me-2"></i>Request to Borrow</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="request_borrow">
                            <div class="mb-3">
                                <label for="notes" class="form-label">Message (Optional)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="Add a message for the game owner..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-hand-holding me-2"></i>Send Borrow Request
                            </button>
                        </form>
                    </div>
                </div>
                <?php elseif (!isLoggedIn()): ?>
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <h5>Want to borrow this game?</h5>
                        <p class="text-muted">Join GameSwap to start borrowing and lending games in your neighborhood!</p>
                        <a href="register.php" class="btn btn-primary me-2">Join GameSwap</a>
                        <a href="login.php" class="btn btn-outline-primary">Login</a>
                    </div>
                </div>
                <?php elseif ($game['user_id'] == $_SESSION['user_id']): ?>
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <h5>This is your game</h5>
                        <p class="text-muted">You can manage this game from your <a href="my-games.php">My Games</a> page.</p>
                    </div>
                </div>
                <?php else: ?>
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <h5>Game not available</h5>
                        <p class="text-muted">This game is currently not available for borrowing.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Reviews -->
                <?php if (!empty($ratings)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-star me-2"></i>Recent Reviews</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($ratings as $rating): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo $rating['full_name']; ?></strong>
                                    <div class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $rating['game_rating'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo timeAgo($rating['created_at']); ?></small>
                            </div>
                            <?php if ($rating['review_text']): ?>
                            <p class="mt-2 mb-0"><?php echo nl2br($rating['review_text']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
