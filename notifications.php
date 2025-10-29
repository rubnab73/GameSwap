<?php require_once 'includes/init.php'; 
requireLogin();

// Mark notification as read
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'mark_read') {
    $notification_id = (int)$_POST['notification_id'];
    $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    $stmt->execute([$notification_id, $_SESSION['user_id']]);
}

// Mark all as read
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'mark_all_read') {
    $stmt = $db->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Get notifications
$stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt->execute([$_SESSION['user_id']]);
$unread_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - GameSwap</title>
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
                            <li><a class="dropdown-item active" href="notifications.php">
                                Notifications 
                                <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </a></li>
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
            <h2><i class="fas fa-bell me-2"></i>Notifications</h2>
            <?php if ($unread_count > 0): ?>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-check-double me-1"></i>Mark All as Read
                </button>
            </form>
            <?php endif; ?>
        </div>

        <?php if (empty($notifications)): ?>
        <div class="text-center py-5">
            <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">No notifications</h4>
            <p class="text-muted">You'll receive notifications about borrow requests, approvals, and other activities here.</p>
        </div>
        <?php else: ?>
        <div class="list-group">
            <?php foreach ($notifications as $notification): ?>
            <div class="list-group-item <?php echo !$notification['is_read'] ? 'list-group-item-light' : ''; ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-2">
                            <?php
                            $icon_class = 'fas fa-info-circle';
                            $icon_color = 'text-primary';
                            switch ($notification['type']) {
                                case 'request':
                                    $icon_class = 'fas fa-hand-holding';
                                    $icon_color = 'text-warning';
                                    break;
                                case 'approval':
                                    $icon_class = 'fas fa-check-circle';
                                    $icon_color = 'text-success';
                                    break;
                                case 'reminder':
                                    $icon_class = 'fas fa-clock';
                                    $icon_color = 'text-warning';
                                    break;
                                case 'return':
                                    $icon_class = 'fas fa-undo';
                                    $icon_color = 'text-info';
                                    break;
                            }
                            ?>
                            <i class="<?php echo $icon_class; ?> <?php echo $icon_color; ?> me-2"></i>
                            <h6 class="mb-0"><?php echo $notification['title']; ?></h6>
                            <?php if (!$notification['is_read']): ?>
                            <span class="badge bg-primary ms-2">New</span>
                            <?php endif; ?>
                        </div>
                        <p class="mb-2"><?php echo $notification['message']; ?></p>
                        <small class="text-muted"><?php echo timeAgo($notification['created_at']); ?></small>
                    </div>
                    <?php if (!$notification['is_read']): ?>
                    <form method="POST" class="ms-3">
                        <input type="hidden" name="action" value="mark_read">
                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-check"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
