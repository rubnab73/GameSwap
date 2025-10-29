<?php require_once 'includes/init.php'; 
requireLogin();

// Get popular games
$stmt = $db->query("SELECT g.title, g.console_type, g.genre, COUNT(br.id) as borrow_count,
                   AVG(r.game_rating) as avg_rating, u.username
                   FROM games g 
                   LEFT JOIN borrow_requests br ON g.id = br.game_id AND br.status = 'approved'
                   LEFT JOIN ratings r ON g.id = r.game_id
                   LEFT JOIN users u ON g.user_id = u.id
                   GROUP BY g.id 
                   ORDER BY borrow_count DESC, avg_rating DESC 
                   LIMIT 10");
$popular_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top lenders
$stmt = $db->query("SELECT u.username, u.full_name, u.points,
                   COUNT(DISTINCT g.id) as games_shared,
                   COUNT(DISTINCT br.id) as times_lent,
                   AVG(r.lender_rating) as avg_lender_rating
                   FROM users u 
                   LEFT JOIN games g ON u.id = g.user_id 
                   LEFT JOIN borrow_requests br ON g.id = br.game_id AND br.status = 'approved'
                   LEFT JOIN ratings r ON u.id = r.lender_id
                   GROUP BY u.id 
                   HAVING games_shared > 0
                   ORDER BY times_lent DESC, avg_lender_rating DESC 
                   LIMIT 10");
$top_lenders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get game availability stats
$stmt = $db->query("SELECT 
                   COUNT(*) as total_games,
                   SUM(CASE WHEN availability_status = 'available' THEN 1 ELSE 0 END) as available_games,
                   SUM(CASE WHEN availability_status = 'borrowed' THEN 1 ELSE 0 END) as borrowed_games,
                   SUM(CASE WHEN availability_status = 'unavailable' THEN 1 ELSE 0 END) as unavailable_games
                   FROM games");
$availability_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get console distribution
$stmt = $db->query("SELECT console_type, COUNT(*) as count FROM games GROUP BY console_type");
$console_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get genre distribution
$stmt = $db->query("SELECT genre, COUNT(*) as count FROM games GROUP BY genre ORDER BY count DESC");
$genre_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly activity
$stmt = $db->query("SELECT 
                   DATE_FORMAT(created_at, '%Y-%m') as month,
                   COUNT(*) as games_added
                   FROM games 
                   WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                   GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                   ORDER BY month DESC");
$monthly_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent borrows
$stmt = $db->query("SELECT br.*, g.title, g.console_type, 
                   u1.username as borrower_username, u1.full_name as borrower_name,
                   u2.username as lender_username, u2.full_name as lender_name
                   FROM borrow_requests br 
                   JOIN games g ON br.game_id = g.id 
                   JOIN users u1 ON br.borrower_id = u1.id 
                   JOIN users u2 ON g.user_id = u2.id 
                   WHERE br.status = 'approved'
                   ORDER BY br.request_date DESC 
                   LIMIT 10");
$recent_borrows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Insights - GameSwap</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <li class="nav-item">
                        <a class="nav-link active" href="reports.php">
                            <i class="fas fa-chart-bar me-1"></i>Reports
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-chart-bar me-2"></i>Reports & Insights</h2>
            <small class="text-muted">Community analytics and trends</small>
        </div>

        <!-- Stats Overview -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-gamepad fa-2x text-primary mb-3"></i>
                        <h4><?php echo $availability_stats['total_games']; ?></h4>
                        <p class="text-muted mb-0">Total Games</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-success mb-3"></i>
                        <h4><?php echo $availability_stats['available_games']; ?></h4>
                        <p class="text-muted mb-0">Available</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-hand-holding fa-2x text-warning mb-3"></i>
                        <h4><?php echo $availability_stats['borrowed_games']; ?></h4>
                        <p class="text-muted mb-0">Currently Borrowed</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-info mb-3"></i>
                        <h4><?php echo $db->query("SELECT COUNT(*) FROM users")->fetchColumn(); ?></h4>
                        <p class="text-muted mb-0">Community Members</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Popular Games -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-fire me-2"></i>Most Popular Games</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($popular_games)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-chart-line fa-2x text-muted mb-3"></i>
                            <p class="text-muted">No data available</p>
                        </div>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($popular_games as $index => $game): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo $game['title']; ?></h6>
                                    <small class="text-muted">
                                        <?php echo $game['console_type']; ?> • <?php echo $game['genre']; ?> • 
                                        Shared by <?php echo $game['username']; ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary"><?php echo $game['borrow_count']; ?> borrows</span>
                                    <?php if ($game['avg_rating']): ?>
                                    <br><small class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $game['avg_rating'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                        <?php echo number_format($game['avg_rating'], 1); ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top Lenders -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Lenders</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($top_lenders)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-users fa-2x text-muted mb-3"></i>
                            <p class="text-muted">No data available</p>
                        </div>
                        <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($top_lenders as $index => $lender): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?php echo $lender['full_name']; ?></h6>
                                    <small class="text-muted">@<?php echo $lender['username']; ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success"><?php echo $lender['times_lent']; ?> lent</span>
                                    <br><small class="text-muted"><?php echo $lender['games_shared']; ?> games shared</small>
                                    <?php if ($lender['avg_lender_rating']): ?>
                                    <br><small class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $lender['avg_lender_rating'] ? '' : '-o'; ?>"></i>
                                        <?php endfor; ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Console Distribution -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Console Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="consoleChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Genre Distribution -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Genre Distribution</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="genreChart" width="400" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Borrow Activity</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_borrows)): ?>
                <div class="text-center py-3">
                    <i class="fas fa-hand-holding fa-2x text-muted mb-3"></i>
                    <p class="text-muted">No recent activity</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Game</th>
                                <th>Borrower</th>
                                <th>Lender</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_borrows as $borrow): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $borrow['title']; ?></strong><br>
                                    <small class="text-muted"><?php echo $borrow['console_type']; ?></small>
                                </td>
                                <td><?php echo $borrow['borrower_name']; ?></td>
                                <td><?php echo $borrow['lender_name']; ?></td>
                                <td><?php echo formatDate($borrow['request_date']); ?></td>
                                <td>
                                    <span class="badge bg-success">Active</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Console Distribution Chart
        const consoleCtx = document.getElementById('consoleChart').getContext('2d');
        new Chart(consoleCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($console_distribution, 'console_type')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($console_distribution, 'count')); ?>,
                    backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Genre Distribution Chart
        const genreCtx = document.getElementById('genreChart').getContext('2d');
        new Chart(genreCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($genre_distribution, 'genre')); ?>,
                datasets: [{
                    label: 'Games',
                    data: <?php echo json_encode(array_column($genre_distribution, 'count')); ?>,
                    backgroundColor: '#007bff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
