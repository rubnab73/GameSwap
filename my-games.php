<?php require_once 'includes/init.php'; 
requireLogin();

$success = '';
$error = '';

// Handle game deletion
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'delete_game') {
    $game_id = (int)$_POST['game_id'];
    
    // Check if game exists and belongs to user
    $stmt = $db->prepare("SELECT availability_status FROM games WHERE id = ? AND user_id = ?");
    $stmt->execute([$game_id, $_SESSION['user_id']]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        $error = 'Game not found.';
    } elseif ($game['availability_status'] == 'borrowed') {
        $error = 'Cannot delete a borrowed game.';
    } else {
        $stmt = $db->prepare("DELETE FROM games WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$game_id, $_SESSION['user_id']])) {
            $success = 'Game deleted successfully!';
        } else {
            $error = 'Failed to delete game.';
        }
    }
}

// Handle game edit
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'edit_game') {
    $game_id = (int)$_POST['game_id'];
    $title = sanitize($_POST['title']);
    $console_type = sanitize($_POST['console_type']);
    $genre = sanitize($_POST['genre']);
    $description = sanitize($_POST['description']);
    
    if (empty($title) || empty($console_type) || empty($genre)) {
        $error = 'Title, console type, and genre are required.';
    } else {
        // Check if game exists and belongs to user
        $stmt = $db->prepare("SELECT * FROM games WHERE id = ? AND user_id = ?");
        $stmt->execute([$game_id, $_SESSION['user_id']]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$game) {
            $error = 'Game not found.';
        } else {
            $image_path = $game['image_path'];
            
            // Handle new image upload
            if (isset($_FILES['game_image']) && $_FILES['game_image']['error'] == 0) {
                $upload_dir = 'uploads/games/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['game_image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['game_image']['tmp_name'], $upload_path)) {
                        // Delete old image if exists
                        if ($game['image_path'] && file_exists($game['image_path'])) {
                            unlink($game['image_path']);
                        }
                        $image_path = $upload_path;
                    } else {
                        $error = 'Failed to upload new image.';
                    }
                } else {
                    $error = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
                }
            }
            
            if (!$error) {
                $stmt = $db->prepare("UPDATE games SET title = ?, console_type = ?, genre = ?, description = ?, image_path = ? WHERE id = ? AND user_id = ?");
                if ($stmt->execute([$title, $console_type, $genre, $description, $image_path, $game_id, $_SESSION['user_id']])) {
                    $success = 'Game updated successfully!';
                } else {
                    $error = 'Failed to update game.';
                }
            }
        }
    }
}

// Handle game addition
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'add_game') {
    $title = sanitize($_POST['title']);
    $console_type = sanitize($_POST['console_type']);
    $genre = sanitize($_POST['genre']);
    $description = sanitize($_POST['description']);
    
    if (empty($title) || empty($console_type) || empty($genre)) {
        $error = 'Title, console type, and genre are required.';
    } else {
        $image_path = '';
        
        // Handle image upload
        if (isset($_FILES['game_image']) && $_FILES['game_image']['error'] == 0) {
            $upload_dir = 'uploads/games/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['game_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = uniqid() . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['game_image']['tmp_name'], $upload_path)) {
                    $image_path = $upload_path;
                } else {
                    $error = 'Failed to upload image.';
                }
            } else {
                $error = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
            }
        }
        
        if (!$error) {
            $stmt = $db->prepare("INSERT INTO games (user_id, title, console_type, genre, description, image_path) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$_SESSION['user_id'], $title, $console_type, $genre, $description, $image_path])) {
                $success = 'Game added successfully!';
                // Award points for adding game
                $stmt = $db->prepare("UPDATE users SET points = points + 10 WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $_SESSION['points'] += 10;
                
                // Points already awarded above
            } else {
                $error = 'Failed to add game.';
            }
        }
    }
}

// Handle game status update
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $game_id = (int)$_POST['game_id'];
    $status = sanitize($_POST['status']);
    
    // Check if game is borrowed and validate status
    $stmt = $db->prepare("SELECT availability_status FROM games WHERE id = ? AND user_id = ?");
    $stmt->execute([$game_id, $_SESSION['user_id']]);
    $current_status = $stmt->fetchColumn();
    
    if ($current_status == 'borrowed') {
        $error = 'Cannot change status of a borrowed game.';
    } elseif ($status == 'borrowed') {
        $error = 'Game status cannot be manually set to borrowed.';
    } elseif (!in_array($status, ['available', 'unavailable'])) {
        $error = 'Invalid game status.';
    } else {
        $stmt = $db->prepare("UPDATE games SET availability_status = ? WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$status, $game_id, $_SESSION['user_id']])) {
            $success = 'Game status updated successfully!';
        } else {
            $error = 'Failed to update game status.';
        }
    }
}

// Get user's games
$stmt = $db->prepare("SELECT * FROM games WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's borrowed games
$stmt = $db->prepare("SELECT br.*, g.title, g.console_type, g.genre, g.image_path, u.username, u.full_name 
                     FROM borrow_requests br 
                     JOIN games g ON br.game_id = g.id 
                     JOIN users u ON g.user_id = u.id 
                     WHERE br.borrower_id = ? AND br.status = 'approved'");
$stmt->execute([$_SESSION['user_id']]);
$borrowed_games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// // Get pending requests for user's games
// $stmt = $db->prepare("SELECT br.*, g.title, g.console_type, u.username, u.full_name 
//                      FROM borrow_requests br 
//                      JOIN games g ON br.game_id = g.id 
//                      JOIN users u ON br.borrower_id = u.id 
//                      WHERE g.user_id = ? AND br.status = 'pending'");
// $stmt->execute([$_SESSION['user_id']]);
// $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
// ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Games - GameSwap</title>
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
                        <a class="nav-link active" href="my-games.php">
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

        <!-- Add Game Button -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-gamepad me-2"></i>My Games</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGameModal">
                <i class="fas fa-plus me-2"></i>Add Game
            </button>
        </div>

        <!-- Pending Requests -->
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

        <!-- My Games Grid -->
        <div class="row">
            <?php if (empty($games)): ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-gamepad fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No games added yet</h4>
                    <p class="text-muted">Start building your game collection by adding your first game!</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGameModal">
                        <i class="fas fa-plus me-2"></i>Add Your First Game
                    </button>
                </div>
            </div>
            <?php else: ?>
            <?php foreach ($games as $game): ?>
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
                            <span class="badge bg-<?php echo $game['availability_status'] == 'available' ? 'success' : ($game['availability_status'] == 'borrowed' ? 'warning' : 'danger'); ?>">
                                <?php echo ucfirst($game['availability_status']); ?>
                            </span>
                        </p>
                        <?php if ($game['description']): ?>
                        <p class="card-text"><?php echo $game['description']; ?></p>
                        <?php endif; ?>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editGameModal<?php echo $game['id']; ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($game['availability_status'] != 'borrowed'): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this game?');">
                                <input type="hidden" name="action" value="delete_game">
                                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                <div class="position-relative">
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" <?php echo $game['availability_status'] == 'borrowed' ? 'disabled' : ''; ?>>
                                        <option value="available" <?php echo $game['availability_status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                                        <option value="unavailable" <?php echo $game['availability_status'] == 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                                    </select>
                                    <?php if ($game['availability_status'] == 'borrowed'): ?>
                                    <small class="text-muted d-block mt-1">
                                        <i class="fas fa-info-circle"></i> Status cannot be changed while game is borrowed
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Game Modal -->
    <div class="modal fade" id="addGameModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add New Game</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_game">
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Game Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="console_type" class="form-label">Console</label>
                                    <select class="form-select" id="console_type" name="console_type" required>
                                        <option value="">Select Console</option>
                                        <option value="PS5">PlayStation 5</option>
                                        <option value="Xbox">Xbox Series X/S</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="genre" class="form-label">Genre</label>
                                    <select class="form-select" id="genre" name="genre" required>
                                        <option value="">Select Genre</option>
                                        <option value="Action">Action</option>
                                        <option value="Adventure">Adventure</option>
                                        <option value="RPG">RPG</option>
                                        <option value="FPS">FPS</option>
                                        <option value="Racing">Racing</option>
                                        <option value="Sports">Sports</option>
                                        <option value="Strategy">Strategy</option>
                                        <option value="Puzzle">Puzzle</option>
                                        <option value="Horror">Horror</option>
                                        <option value="Fighting">Fighting</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="game_image" class="form-label">Game Image</label>
                            <input type="file" class="form-control" id="game_image" name="game_image" accept="image/*">
                            <div class="form-text">Upload a photo of your game disk/case (JPG, PNG, GIF)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Add Game
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Edit Game Modals -->
    <?php foreach ($games as $game): ?>
    <div class="modal fade" id="editGameModal<?php echo $game['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Game</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_game">
                        <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                        
                        <div class="mb-3">
                            <label for="title<?php echo $game['id']; ?>" class="form-label">Game Title</label>
                            <input type="text" class="form-control" id="title<?php echo $game['id']; ?>" name="title" 
                                   value="<?php echo htmlspecialchars($game['title']); ?>" required>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="console_type<?php echo $game['id']; ?>" class="form-label">Console</label>
                                    <select class="form-select" id="console_type<?php echo $game['id']; ?>" name="console_type" required>
                                        <option value="">Select Console</option>
                                        <option value="PS5" <?php echo $game['console_type'] == 'PS5' ? 'selected' : ''; ?>>PlayStation 5</option>
                                        <option value="Xbox" <?php echo $game['console_type'] == 'Xbox' ? 'selected' : ''; ?>>Xbox Series X/S</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="genre<?php echo $game['id']; ?>" class="form-label">Genre</label>
                                    <select class="form-select" id="genre<?php echo $game['id']; ?>" name="genre" required>
                                        <option value="">Select Genre</option>
                                        <option value="Action" <?php echo $game['genre'] == 'Action' ? 'selected' : ''; ?>>Action</option>
                                        <option value="Adventure" <?php echo $game['genre'] == 'Adventure' ? 'selected' : ''; ?>>Adventure</option>
                                        <option value="RPG" <?php echo $game['genre'] == 'RPG' ? 'selected' : ''; ?>>RPG</option>
                                        <option value="FPS" <?php echo $game['genre'] == 'FPS' ? 'selected' : ''; ?>>FPS</option>
                                        <option value="Racing" <?php echo $game['genre'] == 'Racing' ? 'selected' : ''; ?>>Racing</option>
                                        <option value="Sports" <?php echo $game['genre'] == 'Sports' ? 'selected' : ''; ?>>Sports</option>
                                        <option value="Strategy" <?php echo $game['genre'] == 'Strategy' ? 'selected' : ''; ?>>Strategy</option>
                                        <option value="Puzzle" <?php echo $game['genre'] == 'Puzzle' ? 'selected' : ''; ?>>Puzzle</option>
                                        <option value="Horror" <?php echo $game['genre'] == 'Horror' ? 'selected' : ''; ?>>Horror</option>
                                        <option value="Fighting" <?php echo $game['genre'] == 'Fighting' ? 'selected' : ''; ?>>Fighting</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description<?php echo $game['id']; ?>" class="form-label">Description</label>
                            <textarea class="form-control" id="description<?php echo $game['id']; ?>" name="description" rows="3"><?php echo htmlspecialchars($game['description']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Current Image</label>
                            <?php if ($game['image_path']): ?>
                            <div class="mb-2">
                                <img src="<?php echo $game['image_path']; ?>" alt="Current game image" style="max-width: 200px;" class="img-thumbnail">
                            </div>
                            <?php endif; ?>
                            <label for="game_image<?php echo $game['id']; ?>" class="form-label">New Image (Optional)</label>
                            <input type="file" class="form-control" id="game_image<?php echo $game['id']; ?>" name="game_image" accept="image/*">
                            <div class="form-text">Leave empty to keep current image. Upload a new photo to replace it.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</body>
</html>
