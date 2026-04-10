<?php include_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniSphere | Campus Logistics & Rewards</title>
    <!-- Use base path for CSS if in subfolders -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Leaflet CSS for Maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
</head>
<body>

<header>
    <div class="container">
        <nav>
            <a href="index.php" class="logo" style="display: flex; align-items: center; text-decoration: none;">
                <img src="uploads/images/UniSphere_LOGO2_up.png" alt="UniSphere Logo" style="height: 80px; width: 300px; object-fit: contain; object-position: left center; transform: scale(2.5); transform-origin: left center;">
            </a>
            
            <input type="checkbox" id="nav-toggle" class="nav-toggle">
            <label for="nav-toggle" class="nav-toggle-label">
                <span></span>
                <span></span>
                <span></span>
            </label>
            
            <div class="nav-links">
                <?php if(isLoggedIn()): ?>
                    <?php if(hasRole('MEMBER')): ?>
                        <a href="index.php">Marketplace</a>
                        <a href="my_orders.php">My Orders</a>
                    <?php elseif(hasRole('RIDER')): ?>
                        <a href="rider_dashboard.php">Rider Panel</a>
                        <a href="rewards.php">Rewards (<?php echo number_get_format($_SESSION['points'] ?? 0, 0); ?> pts)</a>
                    <?php elseif(hasRole('STORE')): ?>
                        <a href="store_dashboard.php">Store Panel</a>
                    <?php elseif(hasRole('ADMIN')): ?>
                        <a href="admin_dashboard.php">Admin Panel</a>
                    <?php endif; ?>

                    <div class="user-profile" style="display: flex; align-items: center; gap: 10px;">
                        <span style="color: var(--text-main); font-weight: 600; margin-right: 5px;">
                            Hi, <?php echo explode(' ', $_SESSION['name'])[0] ?? 'User'; ?>
                        </span>
                        <?php if(!hasRole('ADMIN')): ?>
                            <a href="help.php" class="btn btn-glass" style="padding: 8px 16px;"><i class="fas fa-headset" style="margin-right: 5px;"></i> Help</a>
                        <?php endif; ?>
                        <a href="profile.php" class="btn btn-glass" style="padding: 8px 16px;"><i class="fas fa-user-edit" style="margin-right: 5px;"></i> Profile</a>
                        <a href="logout.php" class="btn btn-glass" style="padding: 8px 16px;">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-glass">Login</a>
                    <a href="register.php" class="btn btn-primary">Join UniSphere</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>
<?php
// PHP Formatting Helper
function number_get_format($num, $dec = 2) {
    return number_format((float)$num, $dec, '.', ',');
}
?>
