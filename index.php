<?php 
require_once 'includes/config.php';

if(!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// RESTRICTION: Only UniMembers can access the marketplace
if (hasRole('RIDER')) {
    header("Location: rider_dashboard.php");
    exit();
} elseif (hasRole('STORE')) {
    header("Location: store_dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// 1. Fetch Categories
$categories = ['Food', 'Stationery', 'Sports'];

// 2. Fetch Products (Only from APPROVED stores)
$query = "SELECT p.*, s.store_name FROM products p JOIN stores s ON p.store_id = s.id WHERE p.is_available = 1 AND s.status = 'APPROVED'";
$params = [];

if (isset($_GET['cat']) && in_array($_GET['cat'], $categories)) {
    $query .= " AND p.category = ?";
    $params[] = $_GET['cat'];
}

$query .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();

// 2.5 Calculate Live ETD based on UniRiders
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'RIDER' AND is_online = 1");
$stmt->execute();
$rider_count = $stmt->fetchColumn();

if ($rider_count == 0) {
    $etd_message = "30-45 mins";
} elseif ($rider_count <= 2) {
    $etd_message = "15-25 mins";
} else {
    $etd_message = "10-15 mins";
}

// 3. Handle Order Initializing (Create Order & Redirect to Payment)
if (isset($_GET['buy_now'])) {
    // SECURITY: Only UniMembers can order
    if ($_SESSION['role'] !== 'MEMBER') {
        $error = "Only UniMembers (Students) are permitted to place orders.";
    } else {
        $product_id = $_GET['buy_now'];
        // Fetch product to get price/store
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if ($product) {
            $store_id = $product['store_id'];
            $total_price = $product['price'];

            $stmt = $pdo->prepare("INSERT INTO orders (member_id, store_id, total_price, status) VALUES (?, ?, ?, 'AWAITING_PAYMENT')");
            if($stmt->execute([$user_id, $store_id, $total_price])) {
                $order_id = $pdo->lastInsertId();
                // Add to items
                $pdo->prepare("INSERT INTO order_items (order_id, product_id, price) VALUES (?, ?, ?)")->execute([$order_id, $product_id, $total_price]);
                
                header("Location: pay_order.php?order_id=" . $order_id);
                exit();
            }
        }
    }
}

include_once 'includes/header.php';
?>

<div class="container" style="padding-top: 3rem;">
    <!-- Hero Section -->
    <div class="glass flex-mobile-col text-center" style="padding: 4rem 2rem; margin-bottom: 3rem; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: space-between;">
        <div style="z-index: 2;">
            <div style="display: inline-block; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); padding: 8px 16px; border-radius: 20px; font-weight: 600; color: var(--accent); margin-bottom: 20px;">
                <i class="fas fa-motorcycle"></i> Live ETD: <?php echo $etd_message; ?>
            </div>
            <h1 style="font-size: 3.5rem; line-height: 1.1; margin-bottom: 1rem;">Campus Shopping<br><span style="color: var(--primary);">Redefined.</span></h1>
            <p style="color: var(--text-muted); font-size: 1.1rem; max-width: 500px;">Hyper-local delivery within minutes. Order from your favorite campus stores and help fellow students earn rewards.</p>
        </div>
        <div class="hero-icon-mobile-hide" style="z-index: 1; opacity: 0.2; position: absolute; right: -50px; font-size: 10rem; color: var(--primary);">
            <i class="fas fa-shipping-fast"></i>
        </div>
    </div>

    <!-- Category Filter -->
    <div style="display: flex; gap: 15px; margin-bottom: 2rem; flex-wrap: wrap;">
        <a href="index.php" class="btn btn-primary">All Stores</a>
        <?php foreach($categories as $cat): ?>
            <a href="index.php?cat=<?php echo $cat; ?>" class="btn btn-glass"><?php echo $cat; ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Product Grid -->
    <h2 style="margin-bottom: 1.5rem;">Recommended for You</h2>
    <div class="grid grid-3">
        <?php foreach($products as $p): ?>
            <div class="glass" style="padding: 1.5rem; display: flex; flex-direction: column; height: 100%;">
                <div style="height: 180px; background: rgba(255,255,255,0.05); border-radius: 12px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                    <?php if(!empty($p['image_url']) && strpos($p['image_url'], 'default_product.jpg') === false): ?>
                        <img src="<?php echo $p['image_url']; ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="<?php echo htmlspecialchars($p['name']); ?>">
                    <?php else: ?>
                        <i class="fas fa-shopping-bag" style="font-size: 3rem; color: var(--text-muted);"></i>
                    <?php endif; ?>
                </div>
                <div style="flex-grow: 1;">
                    <span class="badge badge-success" style="font-size: 0.6rem; margin-bottom: 1rem; display: inline-block;"><?php echo $p['category']; ?></span>
                    <h3 style="font-size: 1.2rem; margin-bottom: 5px;"><?php echo $p['name']; ?></h3>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 10px;">By: <span style="color: var(--text-main);"><?php echo $p['store_name']; ?></span></p>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                    <span style="font-size: 1.4rem; font-weight: 700; color: var(--primary);">₹<?php echo $p['price']; ?></span>
                    <a href="index.php?buy_now=<?php echo $p['id']; ?>" class="btn btn-primary" style="padding: 10px 15px;">Order Now</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>
