<?php require_once 'includes/init.php'; 
requireLogin();

$user_id = isset($_GET['user']) ? (int)$_GET['user'] : $_SESSION['user_id'];
$is_own_profile = $user_id == $_SESSION['user_id'];

$success = '';
$error = '';

// Handle profile update
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'update_profile' && $is_own_profile) {
    $full_name = sanitize($_POST['full_name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    
    if (empty($full_name) || empty($phone)) {
        $error = 'Name and phone are required.';
    } else {
        $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?");
        if ($stmt->execute([$full_name, $phone, $address, $_SESSION['user_id']])) {
            $success = 'Profile updated successfully!';
        } else {
            $error = 'Failed to update profile.';
        }
    }
}

// Get user profile
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    redirect('index.php');
}

// Get user's games
$stmt = $db->prepare("SELECT * FROM games WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$user_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// // Get user's badges
// $stmt = $db->prepare("SELECT b.*, ub.earned_at 
//                      FROM user_badges ub 
//                      JOIN badges b ON ub.badge_id = b.id 
//                      WHERE ub.user_id = ? 
//                      ORDER BY ub.earned_at DESC");
// $stmt->execute([$user_id]);
// $user_badges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's stats
$stmt = $db->prepare("SELECT COUNT(*) FROM borrow_requests WHERE borrower_id = ? AND status = 'approved'");
$stmt->execute([$user_id]);
$games_borrowed = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM borrow_requests WHERE borrower_id = ?");
$stmt->execute([$user_id]);
$total_borrows = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT AVG(game_rating) FROM ratings WHERE borrower_id = ?");
$stmt->execute([$user_id]);
$avg_rating = $stmt->fetchColumn();

$stats = [
    'games_shared' => count($user_games),
    'games_borrowed' => $games_borrowed,
    'total_borrows' => $total_borrows,
    'avg_rating' => $avg_rating
];

// Get recent activity
$stmt = $db->prepare("SELECT 'game_added' as type, g.title, g.created_at as date, NULL as other_user
                     FROM games g WHERE g.user_id = ?
                     UNION ALL
                     SELECT 'borrow_request' as type, g.title, br.request_date as date, u.username as other_user
                     FROM borrow_requests br 
                     JOIN games g ON br.game_id = g.id 
                     JOIN users u ON br.borrower_id = u.id 
                     WHERE g.user_id = ?
                     ORDER BY date DESC LIMIT 10");
$stmt->execute([$user_id, $user_id]);
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $user['full_name']; ?> - GameSwap</title>
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
                        <a class="nav-link" href="borrowed.php">
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
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Profile Info -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-user-circle fa-5x text-primary"></i>
                        </div>
                        <h4><?php echo $user['full_name']; ?></h4>
                        <p class="text-muted">@<?php echo $user['username']; ?></p>
                        
                        <div class="mb-3">
                            <span class="badge bg-primary fs-6"><?php echo $user['points']; ?> Points</span>
                        </div>
                        
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <strong><?php echo $stats['games_shared']; ?></strong><br>
                                <small class="text-muted">Games Shared</small>
                            </div>
                            <div class="col-4">
                                <strong><?php echo $stats['games_borrowed']; ?></strong><br>
                                <small class="text-muted">Borrowed</small>
                            </div>
                            <div class="col-4">
                                <strong><?php echo $stats['total_borrows']; ?></strong><br>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                        
                        <?php if ($user['address']): ?>
                        <div class="text-start">
                            <strong>Location:</strong><br>
                            <small class="text-muted"><?php echo $user['address']; ?></small>
                        </div>
                        <?php endif; ?>
                        
                        <div class="text-start mt-3">
                            <strong>Member since:</strong><br>
                            <small class="text-muted"><?php echo formatDate($user['created_at']); ?></small>
                        </div>
                        
                        <?php if ($is_own_profile): ?>
                        <div class="mt-3">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                <i class="fas fa-edit me-1"></i>Edit Profile
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Badges -->
                <?php if (!empty($user_badges)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-medal me-2"></i>Badges</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($user_badges as $badge): ?>
                            <div class="col-6 mb-2">
                                <div class="text-center">
                                    <div class="fs-4 mb-1"><?php echo $badge['icon']; ?></div>
                                    <small class="text-muted"><?php echo $badge['name']; ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Games and Activity -->
            <div class="col-lg-8">
                <!-- User's Games -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-gamepad me-2"></i>
                            <?php echo $is_own_profile ? 'My Games' : $user['username'] . "'s Games"; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($user_games)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-gamepad fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No games shared yet</h5>
                            <?php if ($is_own_profile): ?>
                            <p class="text-muted">Start sharing your games with the community!</p>
                            <a href="my-games.php" class="btn btn-primary">Add Your First Game</a>
                            <?php else: ?>
                            <p class="text-muted">This user hasn't shared any games yet.</p>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($user_games as $game): ?>
                            <div class="col-lg-6 col-md-6 mb-3">
                                <div class="card">
                                    <?php if ($game['image_path']): ?>
                                    <img src="<?php echo $game['image_path']; ?>" class="card-img-top" alt="<?php echo $game['title']; ?>" style="height: 150px; object-fit: cover;">
                                    <?php else: ?>
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                                        <i class="fas fa-gamepad fa-2x text-muted"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo $game['title']; ?></h6>
                                        <p class="card-text">
                                            <span class="badge bg-primary"><?php echo $game['console_type']; ?></span>
                                            <span class="badge bg-secondary"><?php echo $game['genre']; ?></span>
                                            <span class="badge bg-<?php echo $game['availability_status'] == 'available' ? 'success' : ($game['availability_status'] == 'borrowed' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($game['availability_status']); ?>
                                            </span>
                                        </p>
                                        <a href="game-details.php?id=<?php echo $game['id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_activity)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-history fa-2x text-muted mb-3"></i>
                            <p class="text-muted">No recent activity</p>
                        </div>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_activity as $activity): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-start">
                                <div class="ms-2 me-auto">
                                    <div class="fw-bold">
                                        <?php if ($activity['type'] == 'game_added'): ?>
                                        <i class="fas fa-plus-circle text-success me-2"></i>Added game
                                        <?php else: ?>
                                        <i class="fas fa-hand-holding text-primary me-2"></i>Borrow request
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted">
                                        <?php echo $activity['title']; ?>
                                        <?php if ($activity['other_user']): ?>
                                        by <?php echo $activity['other_user']; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <small class="text-muted"><?php echo timeAgo($activity['date']); ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($is_own_profile): ?>
    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo $user['full_name']; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $user['phone']; ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo $user['address']; ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
