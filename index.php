<?php
require_once 'includes/config.php';

if (!isLoggedIn()) {
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

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
    if (!hasRole('MEMBER')) {
        $error = "Only UniMembers can submit reviews.";
    } else {
        $r_store_id = $_POST['store_id'];
        $r_rating = (int) $_POST['rating'];
        $r_review = trim($_POST['review']);

        if ($r_rating >= 1 && $r_rating <= 5) {
            $stmt_chk = $pdo->prepare("SELECT id FROM store_reviews WHERE store_id = ? AND user_id = ?");
            $stmt_chk->execute([$r_store_id, $user_id]);
            if ($stmt_chk->fetch()) {
                $stmt = $pdo->prepare("UPDATE store_reviews SET rating = ?, review = ?, created_at = CURRENT_TIMESTAMP WHERE store_id = ? AND user_id = ?");
                $stmt->execute([$r_rating, $r_review, $r_store_id, $user_id]);
                $success = "Review updated successfully!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO store_reviews (store_id, user_id, rating, review) VALUES (?, ?, ?, ?)");
                $stmt->execute([$r_store_id, $user_id, $r_rating, $r_review]);
                $success = "Review submitted successfully!";
            }
            header("Location: index.php?store_id=" . $r_store_id . "#store-reviews-section");
            exit();
        } else {
            $error = "Please select a valid rating between 1 and 5.";
        }
    }
}

// 1. Fetch Categories
$categories = ['Food', 'Stationery', 'Sports'];

// 2. Fetch Products (Only from APPROVED stores)
$query = "SELECT p.*, s.store_name FROM products p JOIN stores s ON p.store_id = s.id WHERE p.is_available = 1 AND s.status = 'APPROVED'";
$params = [];

if (isset($_GET['cat']) && in_array($_GET['cat'], $categories)) {
    $query .= " AND p.category = ?";
    $params[] = $_GET['cat'];
}

if (isset($_GET['store_id']) && is_numeric($_GET['store_id'])) {
    $query .= " AND p.store_id = ?";
    $params[] = $_GET['store_id'];
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

// 2.7 Fetch Featured Stores
$stmt_stores = $pdo->prepare("
    SELECT s.*, 
           COALESCE(AVG(sr.rating), 0) as avg_rating,
           COUNT(sr.id) as review_count
    FROM stores s 
    LEFT JOIN store_reviews sr ON s.id = sr.store_id
    WHERE s.status = 'APPROVED' 
    GROUP BY s.id
    ORDER BY s.id DESC 
    LIMIT 4
");
$stmt_stores->execute();
$featured_stores = $stmt_stores->fetchAll();

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
            if ($stmt->execute([$user_id, $store_id, $total_price])) {
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
    <div class="glass flex-mobile-col text-center hero-section"
        style="padding: 4rem 2rem; margin-bottom: 3rem; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: space-between;">
        <div style="z-index: 2;">
            <div
                style="display: inline-block; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); padding: 8px 16px; border-radius: 20px; font-weight: 600; color: var(--accent); margin-bottom: 20px;">
                <i class="fas fa-motorcycle"></i> Live ETD: <?php echo $etd_message; ?>
            </div>
            <h1 style="font-size: 3.5rem; line-height: 1.1; margin-bottom: 1rem;">Campus Shopping<br><span
                    style="color: var(--primary);">Redefined.</span></h1>
            <p style="color: var(--text-muted); font-size: 1.1rem; max-width: 500px;">Hyper-local delivery within
                minutes. Order from your favorite campus stores and help fellow students earn rewards.</p>
        </div>
        <div class="hero-icon-mobile-hide"
            style="z-index: 1; opacity: 0.2; position: absolute; right: -50px; font-size: 10rem; color: var(--primary);">
            <i class="fas fa-shipping-fast"></i>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="glass quick-stats"
        style="display: flex; justify-content: space-around; padding: 2rem 1rem; margin-bottom: 4rem; border-radius: 16px; flex-wrap: wrap; gap: 20px;">
        <div class="stat-box" style="text-align: center; flex: 1; min-width: 150px;">
            <div style="font-size: 2.5rem; font-weight: 800; color: var(--primary); line-height: 1;">100+</div>
            <div
                style="color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; margin-top: 8px;">
                <i class="fas fa-box-open" style="margin-right: 5px;"></i> Daily Deliveries
            </div>
        </div>
        <div class="stat-box stat-divider"
            style="text-align: center; flex: 1; min-width: 150px; border-left: 1px solid rgba(255,255,255,0.1); border-right: 1px solid rgba(255,255,255,0.1);">
            <div style="font-size: 2.5rem; font-weight: 800; color: var(--accent); line-height: 1;">
                <?php echo max(50, $rider_count * 10); ?>+
            </div>
            <div
                style="color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; margin-top: 8px;">
                <i class="fas fa-users" style="margin-right: 5px;"></i> Active Riders
            </div>
        </div>
        <div class="stat-box" style="text-align: center; flex: 1; min-width: 150px;">
            <div style="font-size: 2.5rem; font-weight: 800; color: #f59e0b; line-height: 1;">
                ~<?php echo $rider_count > 0 ? '15' : '25'; ?>m</div>
            <div
                style="color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; margin-top: 8px;">
                <i class="fas fa-bolt" style="margin-right: 5px;"></i> Avg ETA
            </div>
        </div>
    </div>

    <!-- Featured Campus Stores -->
    <h2 class="section-title"
        style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <span><i class="fas fa-store-alt" style="color: var(--primary); margin-right: 10px;"></i> Featured Stores</span>
        <a href="#" style="font-size: 0.9rem; color: var(--accent); text-decoration: none;">View All <i
                class="fas fa-arrow-right"></i></a>
    </h2>
    <div class="grid grid-4" style="margin-bottom: 4rem; gap: 20px;">
        <?php foreach ($featured_stores as $store): ?>
            <div class="glass"
                style="padding: 1.5rem; display: flex; align-items: center; gap: 15px; transition: transform 0.3s ease; height: 100%;"
                onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                <div
                    style="width: 60px; height: 60px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--primary); flex-shrink: 0;">
                    <i class="fas fa-store"></i>
                </div>
                <div>
                    <h3 style="margin-bottom: 5px; font-size: 1.1rem;">
                        <?php echo htmlspecialchars($store['store_name'] ?? 'Store'); ?>
                    </h3>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 10px;">
                        <i class="fas fa-star" style="color: #f59e0b;"></i> 
                        <?php if ($store['review_count'] > 0): ?>
                            <?php echo number_format($store['avg_rating'], 1); ?> (<?php echo $store['review_count']; ?> Reviews)
                        <?php else: ?>
                            New Store
                        <?php endif; ?>
                    </p>
                    <a href="index.php?store_id=<?php echo $store['id']; ?>#store-reviews-section" class="btn btn-glass" style="width: 100%; justify-content: center; font-size: 0.8rem; padding: 6px 10px;">View Store & Reviews</a>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($featured_stores)): ?>
            <div class="glass" style="padding: 1.5rem; text-align: center; color: var(--text-muted); grid-column: span 3;">
                More stores joining soon!
            </div>
        <?php endif; ?>
    </div>

    <!-- How It Works Section -->
    <h2 style="margin-bottom: 1.5rem; text-align: center;">How UniSphere Works <i class="fas fa-magic"
            style="color: #f59e0b; font-size: 1.5rem;"></i></h2>
    <div class="grid grid-3" style="margin-bottom: 4rem;">
        <div class="glass text-center" style="padding: 2rem;">
            <div
                style="width: 70px; height: 70px; background: rgba(16, 185, 129, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; color: var(--accent); font-size: 2rem; border: 1px solid rgba(16, 185, 129, 0.3);">
                <i class="fas fa-store"></i>
            </div>
            <h3 style="margin-bottom: 1rem;">1. Choose & Order</h3>
            <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5;">Browse products from local campus
                stores, canteens, and stationery shops. Place your order instantly.</p>
        </div>
        <div class="glass text-center" style="padding: 2rem;">
            <div
                style="width: 70px; height: 70px; background: rgba(59, 130, 246, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; color: var(--primary); font-size: 2rem; border: 1px solid rgba(59, 130, 246, 0.3);">
                <i class="fas fa-motorcycle"></i>
            </div>
            <h3 style="margin-bottom: 1rem;">2. Fast Delivery</h3>
            <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5;">A fellow student (UniRider) picks
                up and delivers your order straight to your hostel or class across the campus.</p>
        </div>
        <div class="glass text-center" style="padding: 2rem;">
            <div
                style="width: 70px; height: 70px; background: rgba(245, 158, 11, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; color: #f59e0b; font-size: 2rem; border: 1px solid rgba(245, 158, 11, 0.3);">
                <i class="fas fa-coins"></i>
            </div>
            <h3 style="margin-bottom: 1rem;">3. Earn Rewards</h3>
            <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5;">Riders earn UniCoins for every
                valid delivery. Members earn cashback. Redeem for awesome perks.</p>
        </div>
    </div>

    <!-- Category Filter -->
    <div id="products-section" style="display: flex; gap: 15px; margin-bottom: 2rem; flex-wrap: wrap;">
        <a href="index.php#products-section" class="btn <?php echo (!isset($_GET['cat']) && !isset($_GET['store_id'])) ? 'btn-primary' : 'btn-glass'; ?>">All Products</a>
        <?php foreach ($categories as $cat): ?>
            <a href="index.php?cat=<?php echo $cat; ?>#products-section" class="btn <?php echo (isset($_GET['cat']) && $_GET['cat'] == $cat) ? 'btn-primary' : 'btn-glass'; ?>"><?php echo $cat; ?></a>
        <?php endforeach; ?>
        <?php if(isset($_GET['store_id']) && is_numeric($_GET['store_id'])): ?>
            <a href="index.php?store_id=<?php echo $_GET['store_id']; ?>#products-section" class="btn btn-primary"><i class="fas fa-store" style="margin-right: 5px;"></i> Store Products</a>
        <?php endif; ?>
    </div>

    <!-- Product Grid -->
    <?php
    $product_section_title = "Recommended for You";
    if (isset($_GET['store_id']) && is_numeric($_GET['store_id'])) {
        $stmt_sname = $pdo->prepare("SELECT store_name FROM stores WHERE id = ?");
        $stmt_sname->execute([$_GET['store_id']]);
        $sname_res = $stmt_sname->fetch();
        if ($sname_res) {
            $product_section_title = "Products from " . htmlspecialchars($sname_res['store_name']);
        }
    } else if (isset($_GET['cat']) && in_array($_GET['cat'], $categories)) {
        $product_section_title = htmlspecialchars($_GET['cat']) . " Products";
    }
    ?>
    <h2 style="margin-bottom: 1.5rem;"><?php echo $product_section_title; ?></h2>
    <div class="grid grid-3">
        <?php if(empty($products)): ?>
            <div class="glass" style="padding: 3rem; text-align: center; grid-column: span 3;">
                <i class="fas fa-box-open" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                <h3 style="color: var(--text-main);">No products found</h3>
                <p style="color: var(--text-muted);">We couldn't find any items right now.</p>
                <a href="index.php#products-section" class="btn btn-glass" style="margin-top: 1rem; display: inline-flex;">View All Products</a>
            </div>
        <?php else: ?>
            <?php foreach ($products as $p): ?>
                <div class="glass" style="padding: 1.5rem; display: flex; flex-direction: column; height: 100%;">
                    <div
                        style="height: 180px; background: rgba(255,255,255,0.05); border-radius: 12px; margin-bottom: 1rem; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <?php if (!empty($p['image_url']) && strpos($p['image_url'], 'default_product.jpg') === false): ?>
                            <img src="<?php echo $p['image_url']; ?>" style="width: 100%; height: 100%; object-fit: cover;"
                                alt="<?php echo htmlspecialchars($p['name']); ?>">
                        <?php else: ?>
                            <i class="fas fa-shopping-bag" style="font-size: 3rem; color: var(--text-muted);"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex-grow: 1;">
                        <span class="badge badge-success"
                            style="font-size: 0.6rem; margin-bottom: 1rem; display: inline-block;"><?php echo $p['category']; ?></span>
                        <h3 style="font-size: 1.2rem; margin-bottom: 5px;"><?php echo $p['name']; ?></h3>
                        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 10px;">By: <span
                                style="color: var(--text-main);"><?php echo $p['store_name']; ?></span></p>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                        <span
                            style="font-size: 1.4rem; font-weight: 700; color: var(--primary);">₹<?php echo $p['price']; ?></span>
                        <a href="index.php?buy_now=<?php echo $p['id']; ?>" class="btn btn-primary"
                            style="padding: 10px 15px;">Order Now</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Store Reviews Section -->
    <?php if (isset($_GET['store_id']) && is_numeric($_GET['store_id'])): ?>
    <?php 
        $sid = $_GET['store_id'];
        $stmt_revs = $pdo->prepare("SELECT sr.*, u.name as reviewer_name FROM store_reviews sr JOIN users u ON sr.user_id = u.id WHERE sr.store_id = ? ORDER BY sr.created_at DESC");
        $stmt_revs->execute([$sid]);
        $store_reviews = $stmt_revs->fetchAll();
        
        $stmt_my_rev = $pdo->prepare("SELECT * FROM store_reviews WHERE store_id = ? AND user_id = ?");
        $stmt_my_rev->execute([$sid, $user_id]);
        $my_review = $stmt_my_rev->fetch();
    ?>
    <div id="store-reviews-section" style="margin-top: 4rem; margin-bottom: 4rem;">
        <h2 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-comments" style="color: var(--primary);"></i> Store Reviews
        </h2>
        
        <div class="grid grid-2" style="align-items: start; gap: 2rem;">
            <!-- Leave a Review Form -->
            <div class="glass" style="padding: 2rem;">
                <h3 style="margin-bottom: 1rem;"><?php echo $my_review ? 'Update your review' : 'Leave a Review'; ?></h3>
                
                <?php if(!hasRole('MEMBER')): ?>
                    <div style="background: rgba(245, 158, 11, 0.1); border-left: 3px solid #f59e0b; padding: 10px 15px; margin-bottom: 1rem; font-size: 0.85rem; color: var(--text-muted);">
                        <i class="fas fa-info-circle" style="color: #f59e0b; margin-right: 5px;"></i> You are viewing this form, but only <strong>UniMembers</strong> can submit reviews.
                    </div>
                <?php endif; ?>

                <form action="index.php" method="POST">
                    <input type="hidden" name="submit_review" value="1">
                    <input type="hidden" name="store_id" value="<?php echo $sid; ?>">
                    
                    <div class="form-group" style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 10px;">Your Rating</label>
                        <div class="star-rating" style="display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 5px; font-size: 2rem;">
                            <input type="radio" id="star5" name="rating" value="5" <?php echo ($my_review && $my_review['rating']==5) ? 'checked' : ''; ?> required /><label for="star5" title="5 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star4" name="rating" value="4" <?php echo ($my_review && $my_review['rating']==4) ? 'checked' : ''; ?> /><label for="star4" title="4 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star3" name="rating" value="3" <?php echo ($my_review && $my_review['rating']==3) ? 'checked' : ''; ?> /><label for="star3" title="3 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star2" name="rating" value="2" <?php echo ($my_review && $my_review['rating']==2) ? 'checked' : ''; ?> /><label for="star2" title="2 stars"><i class="fas fa-star"></i></label>
                            <input type="radio" id="star1" name="rating" value="1" <?php echo ($my_review && $my_review['rating']==1) ? 'checked' : ''; ?> /><label for="star1" title="1 star"><i class="fas fa-star"></i></label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Your Feedback</label>
                        <textarea name="review" class="form-input" rows="4" placeholder="What did you think about this store?" required><?php echo $my_review ? htmlspecialchars($my_review['review']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;" <?php echo !hasRole('MEMBER') ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''; ?>>
                        <?php echo $my_review ? 'Update Review' : 'Submit Review'; ?>
                    </button>
                </form>
            </div>
            
            <!-- List of Reviews -->
            <div style="display: flex; flex-direction: column; gap: 15px; max-height: 500px; overflow-y: auto; padding-right: 5px;">
                <?php if (empty($store_reviews)): ?>
                    <div class="glass" style="padding: 2rem; text-align: center; color: var(--text-muted);">
                        <i class="far fa-star" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No reviews yet. Be the first to review this store!</p>
                    </div>
                <?php else: ?>
                    <?php foreach($store_reviews as $rev): ?>
                        <div class="glass" style="padding: 1.5rem; background: rgba(0,0,0,0.2);">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                                <div>
                                    <span style="font-weight: bold; color: var(--text-main); display: block;"><?php echo htmlspecialchars($rev['reviewer_name']); ?></span>
                                    <div style="color: #f59e0b; font-size: 0.8rem; margin-top: 5px;">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <?php if($i <= $rev['rating']): ?>
                                                <i class="fas fa-star"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <span style="font-size: 0.7rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></span>
                            </div>
                            <p style="font-size: 0.95rem; line-height: 1.5; color: var(--text-muted); margin: 0;"><?php echo nl2br(htmlspecialchars($rev['review'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- CSS for Native Star Rating Interaction -->
    <style>
        .star-rating input[type="radio"] {
            display: none;
        }
        .star-rating label {
            color: rgba(255,255,255,0.1);
            cursor: pointer;
            transition: color 0.2s;
        }
        .star-rating input[type="radio"]:checked + label,
        .star-rating input[type="radio"]:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #f59e0b;
        }
    </style>
    <?php endif; ?>

    <!-- The UniCoin Ecosystem Section -->
    <div class="glass ecosystem-section"
        style="padding: 3rem 2rem; margin-top: 4rem; border-radius: 16px; background: linear-gradient(to right, rgba(0,0,0,0.3), rgba(245, 158, 11, 0.05)); border-left: 4px solid #f59e0b; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 2rem;">
        <div class="ecosystem-text" style="flex: 1; min-width: 250px;">
            <span class="badge"
                style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; margin-bottom: 15px; display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem;"><i
                    class="fas fa-coins"></i> UniSphere Economy</span>
            <h2 class="ecosystem-title" style="font-size: 2.2rem; margin-bottom: 1rem; line-height: 1.2;">Earn <span
                    style="color: #f59e0b;">UniCoins</span> with <br>every action.</h2>
            <p style="color: var(--text-muted); font-size: 1.05rem; line-height: 1.6; margin-bottom: 1.5rem;">The Campus
                Delivery Ecosystem rewards both buyers and riders. Earn virtual currency to unlock exclusive campus
                perks, free deliveries, and special meals at partnered canteens.</p>
            <ul style="list-style: none; padding: 0; margin-bottom: 0;">
                <li style="margin-bottom: 10px; color: var(--text-main); display: flex; align-items: center;"><i
                        class="fas fa-check-circle" style="color: #10b981; margin-right: 10px; font-size: 1.2rem;"></i>
                    Get 5 UniCoins per successful delivery (Riders)</li>
                <li style="margin-bottom: 10px; color: var(--text-main); display: flex; align-items: center;"><i
                        class="fas fa-check-circle" style="color: #10b981; margin-right: 10px; font-size: 1.2rem;"></i>
                    Up to 5% Cashback on Store Orders (Students)</li>
                <li style="margin-bottom: 10px; color: var(--text-main); display: flex; align-items: center;"><i
                        class="fas fa-check-circle" style="color: #10b981; margin-right: 10px; font-size: 1.2rem;"></i>
                    Redeem for discount vouchers & gifts</li>
            </ul>
        </div>
        <div class="ecosystem-animation" style="flex: 1; min-width: 250px; display: flex; justify-content: center;">
            <div
                style="width: 250px; height: 250px; background: rgba(245, 158, 11, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; position: relative; border: 2px dashed rgba(245, 158, 11, 0.4); animation: spin 20s linear infinite;">
                <div
                    style="width: 180px; height: 180px; background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 30px rgba(0,0,0,0.5); animation: spin-reverse 20s linear infinite;">
                    <i class="fas fa-gem"
                        style="font-size: 4rem; color: #f59e0b; margin-bottom: 10px; text-shadow: 0 0 20px rgba(245,158,11,0.5);"></i>
                    <span style="font-weight: 800; font-size: 1.2rem; color: #fff;">Rewards</span>
                </div>
            </div>
            <style>
                @keyframes spin {
                    100% {
                        transform: rotate(360deg);
                    }
                }

                @keyframes spin-reverse {
                    100% {
                        transform: rotate(-360deg);
                    }
                }
            </style>
        </div>
    </div>

    <!-- Campus Testimonials -->
    <h2 style="margin-top: 5rem; margin-bottom: 1.5rem; text-align: center;">Hear From The Campus <i
            class="fas fa-heart" style="color: #ef4444; font-size: 1.5rem;"></i></h2>
    <div class="grid grid-3" style="margin-bottom: 2rem;">
        <div class="glass" style="padding: 2rem; position: relative; overflow: hidden; transition: transform 0.3s ease;"
            onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
            <i class="fas fa-quote-right"
                style="position: absolute; right: 20px; top: 20px; font-size: 3rem; color: rgba(255,255,255,0.03);"></i>
            <div style="display: flex; gap: 2px; color: #f59e0b; margin-bottom: 1rem;">
                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                    class="fas fa-star"></i><i class="fas fa-star"></i>
            </div>
            <p style="color: var(--text-muted); font-style: italic; margin-bottom: 1.5rem; min-height: 80px;">"Life
                saver! Ordered a textbook right before my exam from the stationery shop, and a UniRider delivered it in
                10 mins."</p>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div
                    style="width: 40px; height: 40px; background: rgba(16, 185, 129, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--accent); font-weight: bold;">
                    R</div>
                <div>
                    <h4 style="margin: 0; font-size: 1rem;">Rahul S.</h4>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">CS Dept, 2nd Year</span>
                </div>
            </div>
        </div>
        <div class="glass" style="padding: 2rem; position: relative; overflow: hidden; transition: transform 0.3s ease;"
            onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
            <i class="fas fa-quote-right"
                style="position: absolute; right: 20px; top: 20px; font-size: 3rem; color: rgba(255,255,255,0.03);"></i>
            <div style="display: flex; gap: 2px; color: #f59e0b; margin-bottom: 1rem;">
                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                    class="fas fa-star"></i><i class="fas fa-star"></i>
            </div>
            <p style="color: var(--text-muted); font-style: italic; margin-bottom: 1.5rem; min-height: 80px;">"I earn
                UniCoins just by delivering food to the hostel next to mine. Super easy to pay for my own meals now!"
            </p>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div
                    style="width: 40px; height: 40px; background: rgba(59, 130, 246, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: bold;">
                    A</div>
                <div>
                    <h4 style="margin: 0; font-size: 1rem;">Aman K.</h4>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Active UniRider</span>
                </div>
            </div>
        </div>
        <div class="glass" style="padding: 2rem; position: relative; overflow: hidden; transition: transform 0.3s ease;"
            onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
            <i class="fas fa-quote-right"
                style="position: absolute; right: 20px; top: 20px; font-size: 3rem; color: rgba(255,255,255,0.03);"></i>
            <div style="display: flex; gap: 2px; color: #f59e0b; margin-bottom: 1rem;">
                <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i
                    class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
            </div>
            <p style="color: var(--text-muted); font-style: italic; margin-bottom: 1.5rem; min-height: 80px;">"Our
                canteen sales jumped by 30% since joining UniSphere. The students handle delivery, we just pack and hand
                over."</p>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div
                    style="width: 40px; height: 40px; background: rgba(239, 68, 68, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #ef4444; font-weight: bold;">
                    S</div>
                <div>
                    <h4 style="margin: 0; font-size: 1rem;">Sharma Ji</h4>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Campus Canteen</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Become a Rider CTA -->
    <div class="glass flex-mobile-col rider-cta"
        style="padding: 3rem; margin-top: 5rem; margin-bottom: 2rem; background: linear-gradient(135deg, rgba(59,130,246,0.1), rgba(16,185,129,0.1)); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; display: flex; align-items: center; justify-content: space-between; overflow: hidden; position: relative;">
        <div style="z-index: 2;">
            <span class="badge badge-success" style="margin-bottom: 15px; display: inline-block; font-size: 0.8rem;"><i
                    class="fas fa-star" style="color: #f59e0b;"></i> UniRider Program</span>
            <h2 class="cta-title" style="font-size: 2.2rem; margin-bottom: 1rem; line-height: 1.2;">Deliver & earn
                while<br>walking to class!</h2>
            <p style="color: var(--text-muted); font-size: 1.1rem; max-width: 500px; margin-bottom: 1.5rem;">Join our
                student delivery network. Deliver orders on your way to the hostel or classes, earn UniCoins, and redeem
                them at your favorite campus spots.</p>
            <a href="rider_dashboard.php" class="btn btn-primary"
                style="padding: 12px 24px; font-size: 1.1rem; border-radius: 30px;">
                <i class="fas fa-biking" style="margin-right: 8px;"></i> Join as a UniRider
            </a>
        </div>
        <div style="font-size: 12rem; color: rgba(255,255,255,0.03); line-height: 1; z-index: 1; position: absolute; right: -20px; bottom: -20px;"
            class="hero-icon-mobile-hide">
            <i class="fas fa-gift"></i>
        </div>
    </div>
</div>

<!-- Simple Footer -->
<footer
    style="text-align: center; padding: 2.5rem 0; color: var(--text-muted); border-top: 1px solid rgba(255,255,255,0.05); margin-top: 2rem; background: rgba(0,0,0,0.2);">
    <div style="margin-bottom: 1rem; font-size: 1.5rem; color: var(--primary); font-weight: bold;">
        <i class="fas fa-globe"></i> UniSphere
    </div>
    <p style="font-size: 0.9rem;">&copy; <?php echo date('Y'); ?> UniSphere. Crafted for Campus Hyperlocal Delivery.</p>
    <div style="margin-top: 10px;">
        <a href="#" style="color: var(--text-muted); margin: 0 10px;text-decoration: none;"><i
                class="fab fa-instagram"></i></a>
        <a href="#" style="color: var(--text-muted); margin: 0 10px;text-decoration: none;"><i
                class="fab fa-twitter"></i></a>
        <a href="#" style="color: var(--text-muted); margin: 0 10px;text-decoration: none;"><i
                class="fab fa-linkedin"></i></a>
    </div>
</footer>

</body>

</html>