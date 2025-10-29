<?php require_once 'includes/init.php'; 
requireLogin();

$success = '';
$error = '';

// Handle return game
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'return_game') {
    $request_id = (int)$_POST['request_id'];
    
    // Start transaction
    $db->beginTransaction();
    try {
        // Update borrow request
        $stmt = $db->prepare("UPDATE borrow_requests SET status = 'returned', return_date = NOW() WHERE id = ? AND borrower_id = ?");
        $stmt->execute([$request_id, $_SESSION['user_id']]);
        
        // Get game and request details
        $stmt = $db->prepare("SELECT g.id, g.title, u.id as owner_id, br.due_date FROM borrow_requests br 
                             JOIN games g ON br.game_id = g.id 
                             JOIN users u ON g.user_id = u.id 
                             WHERE br.id = ?");
        $stmt->execute([$request_id]);
        $game_info = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if return is on time
        $return_date = new DateTime();
        $due_date = new DateTime($game_info['due_date']);
        if ($return_date <= $due_date) {
            // Award 10 points for on-time return
            $stmt = $db->prepare("UPDATE users SET points = points + 10 WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $_SESSION['points'] += 10;
            $success = 'Game returned successfully! +10 points for returning on time!';
        }
        
        // Update game status back to available
        $stmt = $db->prepare("UPDATE games SET availability_status = 'available' WHERE id = ?");
        $stmt->execute([$game_info['id']]);
        
        // Notify game owner
        $notif_stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'return')");
        $notif_title = 'Game Returned';
        $notif_message = "Your game '{$game_info['title']}' has been returned.";
        $notif_stmt->execute([$game_info['owner_id'], $notif_title, $notif_message]);
        
        $db->commit();
        $success = 'Game returned successfully!';
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Failed to return game.';
    }
}

// Handle approve/reject requests for user's games
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'approve_request') {
    $request_id = (int)$_POST['request_id'];
    $due_date = date('Y-m-d', strtotime('+14 days')); // 14 days from now
    
    
    
    
    // $due_date = date('Y-m-d H:i:s', strtotime('+2 minutes'));

    // Start transaction
    $db->beginTransaction();
    try {
        // Get game_id and borrower details for the approved request
        $stmt = $db->prepare("SELECT br.game_id, br.borrower_id, g.title FROM borrow_requests br JOIN games g ON br.game_id = g.id WHERE br.id = ?");
        $stmt->execute([$request_id]);
        $request_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Approve the selected request
        $stmt = $db->prepare("UPDATE borrow_requests SET status = 'approved', due_date = ? WHERE id = ?");
        $stmt->execute([$due_date, $request_id]);
        
        // Update game status to borrowed
        $stmt = $db->prepare("UPDATE games SET availability_status = 'borrowed' WHERE id = ?");
        $stmt->execute([$request_details['game_id']]);
        
        // Get all other pending requests for this game
        $stmt = $db->prepare("SELECT br.id, br.borrower_id, g.title FROM borrow_requests br JOIN games g ON br.game_id = g.id WHERE br.game_id = ? AND br.status = 'pending' AND br.id != ?");
        $stmt->execute([$request_details['game_id'], $request_id]);
        $other_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Reject other pending requests and send notifications
        if (!empty($other_requests)) {
            $stmt = $db->prepare("UPDATE borrow_requests SET status = 'rejected' WHERE id = ?");
            $notif_stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'request')");
            
            foreach ($other_requests as $other_request) {
                // Reject request
                $stmt->execute([$other_request['id']]);
                
                // Send notification to rejected borrower
                $notif_title = 'Game Request Rejected';
                $notif_message = "Your request to borrow '{$other_request['title']}' was automatically rejected as another request was approved.";
                $notif_stmt->execute([$other_request['borrower_id'], $notif_title, $notif_message]);
            }
        }
        
        // Send notification to approved borrower
        $notif_stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'approval')");
        $notif_title = 'Game Request Approved';
        $notif_message = "Your request to borrow '{$request_details['title']}' has been approved!";
        $notif_stmt->execute([$request_details['borrower_id'], $notif_title, $notif_message]);
        
        // Award points to lender
        $stmt = $db->prepare("UPDATE users SET points = points + 20 WHERE id = (SELECT user_id FROM games WHERE id = ?)");
        $stmt->execute([$request_details['game_id']]);
        
        $db->commit();
        $success = 'Borrow request approved!';
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Failed to approve request.';
    }
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'reject_request') {
    $request_id = (int)$_POST['request_id'];
    
    $stmt = $db->prepare("UPDATE borrow_requests SET status = 'rejected' WHERE id = ?");
    if ($stmt->execute([$request_id])) {
        $success = 'Borrow request rejected.';
    } else {
        $error = 'Failed to reject request.';
    }
}

// Get user's borrowed games
$stmt = $db->prepare("SELECT br.*, g.id as game_id, g.title, g.console_type, g.genre, g.image_path, u.username, u.full_name, u.phone
                     FROM borrow_requests br 
                     JOIN games g ON br.game_id = g.id 
                     JOIN users u ON g.user_id = u.id 
                     WHERE br.borrower_id = ? AND br.status IN ('approved', 'returned')
                     ORDER BY br.request_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$borrowed_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending requests for user's games
$stmt = $db->prepare("SELECT br.*, g.title, g.console_type, u.username, u.full_name, u.phone
                     FROM borrow_requests br 
                     JOIN games g ON br.game_id = g.id 
                     JOIN users u ON br.borrower_id = u.id 
                     WHERE g.user_id = ? AND br.status = 'pending'
                     ORDER BY br.request_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active borrows (games currently borrowed by user)
$active_borrows = array_filter($borrowed_games, function($game) {
    return $game['status'] == 'approved';
});

// Get overdue games
$overdue_games = array_filter($active_borrows, function($game) {
    return $game['due_date'] && strtotime($game['due_date']) < time();
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowed Games - GameSwap</title>
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

        <!-- Overdue Games Alert -->
        <?php if (!empty($overdue_games)): ?>
        <div class="alert alert-danger">
            <h5><i class="fas fa-exclamation-triangle me-2"></i>Overdue Games</h5>
            <p class="mb-0">You have <?php echo count($overdue_games); ?> overdue game(s). Please return them as soon as possible.</p>
        </div>
        <?php endif; ?>

        <!-- Pending Requests for My Games -->
        <?php if (!empty($pending_requests)): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Borrow Requests</h5>
            </div>
            <div class="card-body">
                <?php foreach ($pending_requests as $request): ?>
                <div class="d-flex justify-content-between align-items-center mb-3 p-3 border rounded">
                    <div>
                        <h6 class="mb-1"><?php echo $request['title']; ?></h6>
                        <p class="mb-1 text-muted">Requested by <?php echo $request['full_name']; ?> (<?php echo $request['username']; ?>)</p>
                        <p class="mb-1 text-muted">Phone: <?php echo $request['phone']; ?></p>
                        <small class="text-muted">Requested on <?php echo formatDate($request['request_date']); ?></small>
                    </div>
                    <div>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="approve_request">
                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                            <button type="submit" class="btn btn-success btn-sm me-2">Approve</button>
                        </form>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="action" value="reject_request">
                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Active Borrows -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-hand-holding me-2"></i>Currently Borrowed Games</h5>
            </div>
            <div class="card-body">
                <?php if (empty($active_borrows)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-hand-holding fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No active borrows</h5>
                    <p class="text-muted">You're not currently borrowing any games.</p>
                    <a href="browse.php" class="btn btn-primary">Browse Games</a>
                </div>
                <?php else: ?>
                <div class="row">
                    <?php foreach ($active_borrows as $game): ?>
                    <div class="col-lg-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title"><?php echo $game['title']; ?></h6>
                                        <p class="card-text">
                                            <span class="badge bg-primary"><?php echo $game['console_type']; ?></span>
                                            <span class="badge bg-secondary"><?php echo $game['genre']; ?></span>
                                        </p>
                                        <p class="card-text text-muted">
                                            <small>Lent by <?php echo $game['full_name']; ?> (<?php echo $game['username']; ?>)</small><br>
                                            <small>Phone: <?php echo $game['phone']; ?></small>
                                        </p>
                                    </div>
                                    <div class="text-end">
                                        <?php 
                                        $due_date = strtotime($game['due_date']);
                                        $days_left = ceil(($due_date - time()) / (60 * 60 * 24));
                                        $is_overdue = $days_left < 0;
                                        ?>
                                        <div class="mb-2">
                                            <strong>Due Date:</strong><br>
                                            <span class="badge bg-<?php echo $is_overdue ? 'danger' : ($days_left <= 3 ? 'warning' : 'success'); ?>">
                                                <?php echo formatDate($game['due_date']); ?>
                                            </span>
                                        </div>
                                        <?php if ($is_overdue): ?>
                                        <div class="text-danger">
                                            <i class="fas fa-exclamation-triangle"></i> Overdue by <?php echo abs($days_left); ?> days
                                        </div>
                                        <?php elseif ($days_left <= 3): ?>
                                        <div class="text-warning">
                                            <i class="fas fa-clock"></i> Due in <?php echo $days_left; ?> days
                                        </div>
                                        <?php else: ?>
                                        <div class="text-success">
                                            <i class="fas fa-check"></i> <?php echo $days_left; ?> days left
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="return_game">
                                        <input type="hidden" name="request_id" value="<?php echo $game['id']; ?>">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-undo me-1"></i>Return Game
                                        </button>
                                    </form>
                                    <?php
                                    // Check if game has been rated
                                    $rated_stmt = $db->prepare("SELECT id FROM ratings WHERE borrower_id = ? AND game_id = ?");
                                    $rated_stmt->execute([$_SESSION['user_id'], $game['game_id']]);
                                    $is_rated = $rated_stmt->fetch();
                                    if (!$is_rated): ?>
                                    <button class="btn btn-outline-primary btn-sm ms-2" onclick="rateGame(<?php echo $game['id']; ?>)">
                                        <i class="fas fa-star me-1"></i>Rate Game
                                    </button>
                                    <?php else: ?>
                                    <span class="badge bg-success ms-2"><i class="fas fa-check me-1"></i>Rated</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Borrow History -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Borrow History</h5>
            </div>
            <div class="card-body">
                <?php 
                $returned_games = array_filter($borrowed_games, function($game) {
                    return $game['status'] == 'returned';
                });
                ?>
                <?php if (empty($returned_games)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No borrow history</h5>
                    <p class="text-muted">Your borrow history will appear here once you return games.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Game</th>
                                <th>Console</th>
                                <th>Lent by</th>
                                <th>Borrowed Date</th>
                                <th>Returned Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($returned_games as $game): ?>
                            <tr>
                                <td>
                                    <strong><?php echo $game['title']; ?></strong>
                                    <br><small class="text-muted"><?php echo $game['genre']; ?></small>
                                </td>
                                <td><span class="badge bg-primary"><?php echo $game['console_type']; ?></span></td>
                                <td><?php echo $game['username']; ?></td>
                                <td><?php echo formatDate($game['request_date']); ?></td>
                                <td><?php echo $game['return_date'] ? formatDate($game['return_date']) : 'N/A'; ?></td>
                                <td>
                                    <?php
                                    $rated_stmt = $db->prepare("SELECT id FROM ratings WHERE borrower_id = ? AND game_id = ?");
                                    $rated_stmt->execute([$_SESSION['user_id'], $game['game_id']]);
                                    $is_rated = $rated_stmt->fetch();
                                    if (!$is_rated): ?>
                                    <div class="d-flex gap-2 align-items-center">
                                        <span class="badge bg-success">Returned</span>
                                        <button class="btn btn-outline-primary btn-sm" onclick="rateGame(<?php echo $game['id']; ?>)">
                                            <i class="fas fa-star me-1"></i>Rate
                                        </button>
                                    </div>
                                    <?php else: ?>
                                    <div class="d-flex gap-2 align-items-center">
                                        <span class="badge bg-success">Returned</span>
                                        <span class="badge bg-info"><i class="fas fa-check me-1"></i>Rated</span>
                                    </div>
                                    <?php endif; ?>
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
        function rateGame(requestId) {
            window.location.href = 'rate-game.php?id=' + requestId;
        }
    </script>
</body>
</html>
