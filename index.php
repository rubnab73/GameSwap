<?php require_once 'includes/init.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameSwap - Neighborhood Console Game Sharing</title>
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

    <!-- Hero Section -->
    <div class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold text-white mb-4">Share Games, Build Community</h1>
                    <p class="lead text-white mb-4">Connect with your neighbors to borrow, lend, and swap PS5 and Xbox games. Earn points, build trust, and discover amazing games in your community.</p>
                    
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-gamepad hero-icon"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-exchange-alt feature-icon text-primary"></i>
                        <h5 class="card-title">Easy Sharing</h5>
                        <p class="card-text">Lend your games to neighbors and borrow theirs. Simple request system with automatic tracking.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-star feature-icon text-warning"></i>
                        <h5 class="card-title">Gamification</h5>
                        <p class="card-text">Earn points for lending games and climb the leaderboard.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="card h-100 text-center">
                    <div class="card-body">
                        <i class="fas fa-shield-alt feature-icon text-success"></i>
                        <h5 class="card-title">Trust & Safety</h5>
                        <p class="card-text">Rate games and lenders, build community trust, and enjoy safe neighborhood sharing.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Games Section -->
    <div class="container my-5">
        <h2 class="text-center mb-4">Recently Added Games</h2>
        <div class="row">
            <?php
            $stmt = $db->prepare("SELECT g.*, u.username, u.full_name FROM games g 
                                 JOIN users u ON g.user_id = u.id 
                                 WHERE g.availability_status = 'available' 
                                 ORDER BY g.created_at DESC LIMIT 6");
            $stmt->execute();
            $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($games as $game):
            ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card game-card">
                    <?php if ($game['image_path']): ?>
                    <img src="<?php echo $game['image_path']; ?>" class="card-img-top" alt="<?php echo $game['title']; ?>">
                    <?php else: ?>
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center">
                        <i class="fas fa-gamepad fa-3x text-muted"></i>
                    </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $game['title']; ?></h5>
                        <p class="card-text">
                            <span class="badge bg-primary"><?php echo $game['console_type']; ?></span>
                            <span class="badge bg-secondary"><?php echo $game['genre']; ?></span>
                        </p>
                        <p class="card-text"><small class="text-muted">Shared by <?php echo $game['username']; ?></small></p>
                        <?php if (isLoggedIn()): ?>
                        <a href="game-details.php?id=<?php echo $game['id']; ?>" class="btn btn-primary">View Details</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>GameSwap</h5>
                    <p>Building stronger communities through game sharing.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; 2024 GameSwap. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
