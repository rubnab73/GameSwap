<?php require_once 'includes/init.php'; 

$success = '';
$error = '';

// Handle borrow request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'borrow_request') {
    if (!isLoggedIn()) {
        $error = 'You must be logged in to request games.';
    } else {
        $game_id = (int)$_POST['game_id'];
        
        // Check if game exists and is available
        $stmt = $db->prepare("SELECT g.*, u.id as owner_id FROM games g JOIN users u ON g.user_id = u.id WHERE g.id = ? AND g.availability_status = 'available'");
        $stmt->execute([$game_id]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$game) {
            $error = 'Game not found or not available.';
        } elseif ($game['owner_id'] == $_SESSION['user_id']) {
            $error = 'You cannot borrow your own game.';
        } else {
            // Check for existing requests
            $stmt = $db->prepare("SELECT COUNT(*) FROM borrow_requests WHERE game_id = ? AND borrower_id = ? AND status != 'rejected'");
            $stmt->execute([$game_id, $_SESSION['user_id']]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'You already have a pending or approved request for this game.';
            } else {
                // Create borrow request
                $stmt = $db->prepare("INSERT INTO borrow_requests (game_id, borrower_id, request_date, status) VALUES (?, ?, NOW(), 'pending')");
                if ($stmt->execute([$game_id, $_SESSION['user_id']])) {
                    $success = 'Borrow request sent successfully!';
                    
                    // Create notification for game owner
                    $notify_stmt = $db->prepare("INSERT INTO notifications (user_id, type, related_id, message, created_at) 
                                                VALUES (?, 'borrow_request', ?, ?, NOW())");
                    $notify_msg = "New borrow request for {$game['title']} from {$_SESSION['username']}";
                    $notify_stmt->execute([$game['owner_id'], $game_id, $notify_msg]);
                } else {
                    $error = 'Failed to create borrow request.';
                }
            }
        }
    }
}

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$console_filter = isset($_GET['console']) ? sanitize($_GET['console']) : '';
$genre_filter = isset($_GET['genre']) ? sanitize($_GET['genre']) : '';
$sort_by = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'newest';

// Build query
$where_conditions = ["g.availability_status = 'available'"];
$params = [];

if ($search) {
    $where_conditions[] = "(g.title LIKE ? OR g.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($console_filter) {
    $where_conditions[] = "g.console_type = ?";
    $params[] = $console_filter;
}

if ($genre_filter) {
    $where_conditions[] = "g.genre = ?";
    $params[] = $genre_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Sort options
$order_by = "g.created_at DESC";
switch ($sort_by) {
    case 'title':
        $order_by = "g.title ASC";
        break;
    case 'popular':
        $order_by = "borrow_count DESC, g.created_at DESC";
        break;
    case 'rating':
        $order_by = "avg_rating DESC, g.created_at DESC";
        break;
}

// Query for available games
$sql = "SELECT g.*, u.username, u.full_name, 
        COUNT(br.id) as borrow_count,
        AVG(r.game_rating) as avg_rating
        FROM games g 
        JOIN users u ON g.user_id = u.id 
        LEFT JOIN borrow_requests br ON g.id = br.game_id AND br.status = 'approved'
        LEFT JOIN ratings r ON g.id = r.game_id
        WHERE $where_clause
        GROUP BY g.id
        ORDER BY $order_by";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Query for borrowed games
$where_conditions_borrowed = array_map(function($condition) {
    return str_replace("g.availability_status = 'available'", "g.availability_status = 'borrowed'", $condition);
}, $where_conditions);
$where_clause_borrowed = implode(' AND ', $where_conditions_borrowed);

$sql_borrowed = "SELECT g.*, u.username, u.full_name,
                 COUNT(br.id) as borrow_count,
                 AVG(r.game_rating) as avg_rating,
                 br.borrower_id,
                 br.request_date as borrow_date,
                 br.due_date,
                 u2.username as borrower_name
                 FROM games g
                 JOIN users u ON g.user_id = u.id
                 LEFT JOIN borrow_requests br ON g.id = br.game_id AND br.status = 'approved'
                 LEFT JOIN ratings r ON g.id = r.game_id
                 LEFT JOIN users u2 ON br.borrower_id = u2.id
                 WHERE $where_clause_borrowed
                 GROUP BY g.id
                 ORDER BY br.request_date DESC";

$stmt = $db->prepare($sql_borrowed);
$stmt->execute($params);
$borrowed_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique genres for filter
$stmt = $db->query("SELECT DISTINCT genre FROM games ORDER BY genre");
$genres = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Games - GameSwap</title>
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
                        <a class="nav-link active" href="browse.php">
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

        <!-- Search and Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="search" class="form-label">Search Games</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo $search; ?>" placeholder="Search by title or description...">
                    </div>
                    <div class="col-md-2">
                        <label for="console" class="form-label">Console</label>
                        <select class="form-select" id="console" name="console">
                            <option value="">All Consoles</option>
                            <option value="PS5" <?php echo $console_filter == 'PS5' ? 'selected' : ''; ?>>PlayStation 5</option>
                            <option value="Xbox" <?php echo $console_filter == 'Xbox' ? 'selected' : ''; ?>>Xbox Series X/S</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="genre" class="form-label">Genre</label>
                        <select class="form-select" id="genre" name="genre">
                            <option value="">All Genres</option>
                            <?php foreach ($genres as $genre): ?>
                            <option value="<?php echo $genre; ?>" <?php echo $genre_filter == $genre ? 'selected' : ''; ?>>
                                <?php echo $genre; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="sort" class="form-label">Sort By</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest</option>
                            <option value="title" <?php echo $sort_by == 'title' ? 'selected' : ''; ?>>Title A-Z</option>
                            <option value="popular" <?php echo $sort_by == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                            <option value="rating" <?php echo $sort_by == 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="available-tab" data-bs-toggle="tab" data-bs-target="#available" type="button" role="tab">
                    <i class="fas fa-gamepad me-2"></i>Available Games
                    <span class="badge bg-primary ms-2"><?php echo count($games); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="borrowed-tab" data-bs-toggle="tab" data-bs-target="#borrowed" type="button" role="tab">
                    <i class="fas fa-hand-holding me-2"></i>Borrowed Games
                    <span class="badge bg-warning ms-2"><?php echo count($borrowed_games); ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Available Games Tab -->
            <div class="tab-pane fade show active" id="available" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="fas fa-gamepad me-2"></i>Available Games
                    </h2>
                    <?php if (isLoggedIn()): ?>
                    <a href="my-games.php" class="btn btn-outline-primary">
                        <i class="fas fa-plus me-1"></i>Add Your Games
                    </a>
                    <?php endif; ?>
                </div>

        <!-- Games Grid -->
        <div class="row">
            <?php if (empty($games)): ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No games found</h4>
                    <p class="text-muted">Try adjusting your search criteria or check back later for new games.</p>
                    <?php if (!isLoggedIn()): ?>
                    <a href="register.php" class="btn btn-primary">Join GameSwap</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($games as $game): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card game-card h-100">
                    <?php if ($game['image_path']): ?>
                    <img src="<?php echo $game['image_path']; ?>" class="card-img-top" alt="<?php echo $game['title']; ?>">
                    <?php else: ?>
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center">
                        <i class="fas fa-gamepad fa-3x text-muted"></i>
                    </div>
                    <?php endif; ?>
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?php echo $game['title']; ?></h5>
                        <p class="card-text">
                            <span class="badge bg-primary"><?php echo $game['console_type']; ?></span>
                            <span class="badge bg-secondary"><?php echo $game['genre']; ?></span>
                        </p>
                        <?php if ($game['description']): ?>
                        <p class="card-text text-muted"><?php echo substr($game['description'], 0, 100) . (strlen($game['description']) > 100 ? '...' : ''); ?></p>
                        <?php endif; ?>
                        
                        <!-- Game Stats -->
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <small class="text-muted">Borrowed</small><br>
                                <strong><?php echo $game['borrow_count']; ?></strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Rating</small><br>
                                <strong>
                                    <?php 
                                    if ($game['avg_rating']) {
                                        echo number_format($game['avg_rating'], 1);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </strong>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Shared by</small><br>
                                <strong><?php echo $game['username']; ?></strong>
                            </div>
                        </div>
                        
                        <div class="mt-auto">
                            <p class="card-text">
                                <small class="text-muted">Added <?php echo timeAgo($game['created_at']); ?></small>
                            </p>
                            <div class="d-flex gap-2">
                                <a href="game-details.php?id=<?php echo $game['id']; ?>" class="btn btn-primary flex-fill">
                                    <i class="fas fa-eye me-1"></i>View Details
                                </a>
                                <?php if (isLoggedIn() && $game['user_id'] != $_SESSION['user_id']): ?>
                                <button class="btn btn-success" onclick="">
                                    <i class="fas fa-hand-holding me-1"></i>Available to Borrow
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
                </div>
            </div>

        <!-- borrowed section -->
            <div class="tab-pane fade" id="borrowed" role="tabpanel">
                <div class="row">
                    <?php if (empty($borrowed_games)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-hand-holding fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No borrowed games</h4>
                            <p class="text-muted">All games are currently available for borrowing!</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($borrowed_games as $game): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card game-card h-100">
                            <?php if ($game['image_path']): ?>
                            <img src="<?php echo $game['image_path']; ?>" class="card-img-top" alt="<?php echo $game['title']; ?>">
                            <?php else: ?>
                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center">
                                <i class="fas fa-gamepad fa-3x text-muted"></i>
                            </div>
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <div class="position-absolute top-0 end-0 m-2">
                                    <span class="badge bg-warning">Borrowed</span>
                                </div>
                                <h5 class="card-title"><?php echo $game['title']; ?></h5>
                                <p class="card-text">
                                    <span class="badge bg-primary"><?php echo $game['console_type']; ?></span>
                                    <span class="badge bg-secondary"><?php echo $game['genre']; ?></span>
                                </p>
                                <?php if ($game['description']): ?>
                                <p class="card-text text-muted"><?php echo substr($game['description'], 0, 100) . (strlen($game['description']) > 100 ? '...' : ''); ?></p>
                                <?php endif; ?>
                                
                                <div class="alert alert-warning mb-3">
                                    <small>
                                        <i class="fas fa-user me-1"></i>Borrowed by: <?php echo $game['borrower_name']; ?><br>
                                        <i class="fas fa-calendar me-1"></i>Since: <?php echo formatDate($game['borrow_date']); ?><br>
                                        <i class="fas fa-clock me-1"></i>Due: <?php echo formatDate($game['due_date']); ?>
                                    </small>
                                </div>
                                
                                <div class="mt-auto">
                                    <p class="card-text">
                                        <small class="text-muted">Owned by <?php echo $game['username']; ?></small>
                                    </p>
                                    <a href="" class="btn btn-primary w-100">
                                        <i class="fas fa-eye me-1"></i>View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Borrow Request Modal -->
    <div class="modal fade" id="borrowModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-hand-holding me-2"></i>Request to Borrow</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="borrowForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="game_id" name="game_id">
                        <input type="hidden" name="action" value="borrow_request">
                        <p>Are you sure you want to request to borrow this game?</p>
                        <p class="text-muted small">
                            <i class="fas fa-info-circle me-1"></i>
                            The game owner will be notified of your request and can approve or reject it.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane me-1"></i>Send Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function requestBorrow(gameId) {
            document.getElementById('game_id').value = gameId;
            new bootstrap.Modal(document.getElementById('borrowModal')).show();
        }
        
        <?php if (!isLoggedIn()): ?>
        function requestBorrow(gameId) {
            window.location.href = 'login.php';
        }
        <?php endif; ?>
    </script>
</body>
</html>
