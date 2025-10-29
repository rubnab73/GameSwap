<?php require_once 'includes/init.php'; 

// Get leaderboard data
$stmt = $db->query("SELECT u.username, u.full_name, u.points, 
                   COUNT(DISTINCT g.id) as games_shared,
                   COUNT(DISTINCT br.id) as games_borrowed,
                   AVG(r.game_rating) as avg_rating,
                   AVG(r2.lender_rating) as avg_lender_rating,
                   COUNT(DISTINCT r.id) as total_ratings_given,
                   COUNT(DISTINCT r2.id) as total_ratings_received
                   FROM users u 
                   LEFT JOIN games g ON u.id = g.user_id 
                   LEFT JOIN borrow_requests br ON u.id = br.borrower_id AND br.status = 'approved'
                   LEFT JOIN ratings r ON u.id = r.borrower_id
                   LEFT JOIN ratings r2 ON u.id = r2.lender_id
                   GROUP BY u.id 
                   ORDER BY u.points DESC, avg_lender_rating DESC, games_shared DESC 
                   LIMIT 50");
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current user's rank
$user_rank = 0;
if (isLoggedIn()) {
    $stmt = $db->prepare("SELECT COUNT(*) + 1 as rank FROM users WHERE points > (SELECT points FROM users WHERE id = ?)");
    $stmt->execute([$_SESSION['user_id']]);
    $user_rank = $stmt->fetchColumn();
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - GameSwap</title>
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
                        <a class="nav-link active" href="leaderboard.php">
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
        <div class="row">
            <!-- Leaderboard -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="mb-0"><i class="fas fa-trophy me-2"></i>Community Leaderboard</h3>
                        <p class="text-muted mb-0">Top contributors in the GameSwap community</p>
                    </div>
                    <div class="card-body">
                        <?php if (isLoggedIn() && $user_rank > 0): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            You are currently ranked <strong>#<?php echo $user_rank; ?></strong> with <strong><?php echo $_SESSION['points']; ?> points</strong>
                        </div>
                        <?php endif; ?>

                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Player</th>
                                        <th>Points</th>
                                        <th>Games Shared</th>
                                        <th>Games Borrowed</th>
                                        <th>As Lender</th>
                                        <th>Reviews</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($leaderboard as $index => $player): ?>
                                    <tr class="<?php echo isLoggedIn() && $player['username'] == $_SESSION['username'] ? 'table-primary' : ''; ?>">
                                        <td>
                                            <?php if ($index < 3): ?>
                                            <i class="fas fa-trophy text-<?php echo $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'danger'); ?>"></i>
                                            <?php else: ?>
                                            <strong><?php echo $index + 1; ?></strong>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-2">
                                                    <i class="fas fa-user-circle fa-2x text-muted"></i>
                                                </div>
                                                <div>
                                                    <strong><?php echo $player['full_name']; ?></strong><br>
                                                    <small class="text-muted">@<?php echo $player['username']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary fs-6"><?php echo $player['points']; ?></span>
                                        </td>
                                        <td><?php echo $player['games_shared']; ?></td>
                                        <td><?php echo $player['games_borrowed']; ?></td>
                                        <td>
                                            <?php if ($player['avg_lender_rating']): ?>
                                            <div class="text-warning">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $player['avg_lender_rating'] ? '' : '-o'; ?>"></i>
                                                <?php endfor; ?>
                                                <small class="text-muted ms-1"><?php echo number_format($player['avg_lender_rating'], 1); ?></small>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                Given: <?php echo $player['total_ratings_given']; ?><br>
                                                Received: <?php echo $player['total_ratings_received']; ?>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Badges and Stats -->
            <div class="col-lg-4">
                <!-- Community Stats -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Community Stats</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stats = [
                            ['icon' => 'fas fa-users', 'label' => 'Total Members', 'value' => $db->query("SELECT COUNT(*) FROM users")->fetchColumn()],
                            ['icon' => 'fas fa-gamepad', 'label' => 'Games Shared', 'value' => $db->query("SELECT COUNT(*) FROM games")->fetchColumn()],
                            ['icon' => 'fas fa-hand-holding', 'label' => 'Active Borrows', 'value' => $db->query("SELECT COUNT(*) FROM borrow_requests WHERE status = 'approved'")->fetchColumn()],
                            ['icon' => 'fas fa-star', 'label' => 'Total Reviews', 'value' => $db->query("SELECT COUNT(*) FROM ratings")->fetchColumn()]
                        ];
                        ?>
                        <?php foreach ($stats as $stat): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="d-flex align-items-center">
                                <i class="<?php echo $stat['icon']; ?> me-2 text-primary"></i>
                                <span><?php echo $stat['label']; ?></span>
                            </div>
                            <strong><?php echo $stat['value']; ?></strong>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
