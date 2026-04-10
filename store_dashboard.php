<?php 
require_once 'includes/config.php';

if(!isLoggedIn() || !hasRole('STORE')) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// 1. Fetch Store and Check Status
$stmt = $pdo->prepare("SELECT * FROM stores WHERE user_id = ?");
$stmt->execute([$user_id]);
$store = $stmt->fetch();

if (!$store) {
    header("Location: index.php");
    exit();
}

// Handler for Verification Details Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_verification'])) {
    $aadhar_number = $_POST['aadhar_number'];
    $location = $_POST['location'];
    $aadhar_image = $store['aadhar_image']; // Keep old one if not updated

    if (empty($aadhar_number) || empty($location)) {
        $error = "Aadhar number and location are required.";
    } elseif (strlen($aadhar_number) != 12 || !is_numeric($aadhar_number)) {
        $error = "Aadhar Number must be a 12-digit number.";
    } else {
        // Upload Aadhar Image if provided
        if (isset($_FILES['aadhar_image']) && $_FILES['aadhar_image']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "assets/uploads/aadhar/";
            $file_name = time() . "_aadhar_" . basename($_FILES["aadhar_image"]["name"]);
            if (move_uploaded_file($_FILES["aadhar_image"]["tmp_name"], $target_dir . $file_name)) {
                $aadhar_image = $target_dir . $file_name;
            } else {
                $error = "Failed to upload Aadhar image.";
            }
        } elseif (empty($aadhar_image)) {
            $error = "Aadhar Card photo is required.";
        }

        if (!$error) {
            $stmt = $pdo->prepare("UPDATE stores SET aadhar_number = ?, location = ?, aadhar_image = ?, status = 'PENDING' WHERE id = ?");
            if ($stmt->execute([$aadhar_number, $location, $aadhar_image, $store['id']])) {
                $success = "Verification details submitted successfully! Admin will review shortly.";
                // Refresh store data
                $stmt = $pdo->prepare("SELECT * FROM stores WHERE id = ?");
                $stmt->execute([$store['id']]);
                $store = $stmt->fetch();
            } else {
                $error = "Failed to update details.";
            }
        }
    }
}

if ($store['status'] !== 'APPROVED') {
    include_once 'includes/header.php';
    echo '<div class="container" style="padding-top: 5rem; text-align: center;">';
    
    if (empty($store['aadhar_number']) || empty($store['location']) || empty($store['aadhar_image'])) {
        // Show Form for Incomplete Profiles
        echo '<div class="glass" style="padding: 3rem; max-width: 600px; margin: 0 auto; text-align: left;">
                <h2 style="font-size: 2rem; margin-bottom: 1rem; color: var(--primary);">Complete Your Profile</h2>
                <p style="color: var(--text-muted); margin-bottom: 2rem;">Please provide your verification details to begin the admin approval process.</p>
                
                ' . ($error ? '<div class="badge badge-danger" style="display:block; margin-bottom:1rem; text-align:center;">' . $error . '</div>' : '') . '
                
                <form action="store_dashboard.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="update_verification" value="1">
                    <div class="form-group">
                        <label>Campus Location</label>
                        <input type="text" name="location" class="form-input" placeholder="e.g. Block C, Near Library" required>
                    </div>
                    <div class="form-group">
                        <label>Aadhar Number</label>
                        <input type="text" name="aadhar_number" class="form-input" placeholder="12-digit UID" required maxlength="12">
                    </div>
                    <div class="form-group">
                        <label>Aadhar Card Photo</label>
                        <input type="file" name="aadhar_image" class="form-input" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 1rem;">Submit for Review</button>
                </form>
              </div>';
    } else {
        // Show Standard Pending Message
        echo '<div class="glass" style="padding: 4rem; max-width: 600px; margin: 0 auto;">
                <i class="fas fa-clock" style="font-size: 4rem; color: var(--warning); margin-bottom: 1.5rem;"></i>
                <h2 style="font-size: 2.5rem; margin-bottom: 1rem;">Verification Pending</h2>
                <p style="color: var(--text-muted); font-size: 1.1rem; line-height: 1.6;">
                    Your store application is currently being reviewed by the University Administration. 
                    You will be able to manage your inventory and orders once approved.
                </p>
                <div style="margin-top: 2rem; padding: 1.5rem; background: rgba(0,0,0,0.2); border-radius: 12px; text-align: left; border: 1px solid rgba(255,255,255,0.05);">
                    <p style="margin-bottom:0.5rem;"><strong>Aadhar Number:</strong> <span style="color:var(--text-main);">' . $store['aadhar_number'] . '</span></p>
                    <p style="margin-bottom:0.5rem;"><strong>Location:</strong> <span style="color:var(--text-main);">' . $store['location'] . '</span></p>
                    <p><strong>Status:</strong> <span class="badge badge-warning" style="font-size:0.7rem;">Review in Progress</span></p>
                </div>
                <a href="index.php" class="btn btn-glass" style="margin-top: 2rem;">Back to Marketplace</a>
              </div>';
    }
    echo '</div>';
    exit();
}

$store_id = $store['id'];

// 2. Handle Product Addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $p_name = $_POST['p_name'];
    $p_price = $_POST['p_price'];
    $p_category = $_POST['p_category'];

    if(!empty($p_name) && !empty($p_price)) {
        $image_url_val = 'assets/images/default_product.jpg';
        
        // Handle optional image upload
        if(isset($_FILES['p_image']) && $_FILES['p_image']['error'] == 0) {
            $target_dir = "uploads/products/";
            if(!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            $file_name = "prod_" . time() . "_" . basename($_FILES["p_image"]["name"]);
            $target_file = $target_dir . $file_name;
            if(move_uploaded_file($_FILES["p_image"]["tmp_name"], $target_file)) {
                $image_url_val = $target_file;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO products (store_id, name, price, category, image_url) VALUES (?, ?, ?, ?, ?)");
        if($stmt->execute([$store_id, $p_name, $p_price, $p_category, $image_url_val])) {
            $success = "Product added successfully!";
            header("Location: store_dashboard.php");
            exit();
        }
    } else {
        $error = "Product name and price are required.";
    }
}

// 3. Handle Payment Verification
if (isset($_GET['verify_order'])) {
    $order_id = $_GET['verify_order'];
    // Verify it belongs to this store
    $stmt = $pdo->prepare("UPDATE orders SET status = 'CONFIRMED' WHERE id = ? AND store_id = ?");
    if($stmt->execute([$order_id, $store_id])) {
        // Create OTP for the order
        $otp = sprintf("%06d", mt_rand(1, 999999));
        $pdo->prepare("UPDATE orders SET otp = ? WHERE id = ?")->execute([$otp, $order_id]);
        
        // Also update payment status
        $pdo->prepare("UPDATE payments SET status = 'VERIFIED' WHERE order_id = ?")->execute([$order_id]);
        
        $success = "Order confirmed! UniRiders can now pick it up.";
    }
}

// 4. Fetch Products
$stmt = $pdo->prepare("SELECT * FROM products WHERE store_id = ? ORDER BY id DESC");
$stmt->execute([$store_id]);
$products = $stmt->fetchAll();

// 5. Fetch Pending Orders
$stmt = $pdo->prepare("SELECT o.*, u.name as member_name, p.transaction_id, p.screenshot_url 
                       FROM orders o 
                       JOIN users u ON o.member_id = u.id 
                       JOIN payments p ON o.id = p.order_id 
                       WHERE o.store_id = ? AND o.status = 'PENDING_VERIFICATION'");
$stmt->execute([$store_id]);
$pending_orders = $stmt->fetchAll();

include_once 'includes/header.php';
?>

<div class="container" style="padding-top: 2rem;">
    <div class="flex-mobile-col" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 2.5rem;"><?php echo $store['store_name']; ?></h1>
            <p style="color: var(--text-muted);">Manage your UniStore Inventory and Orders</p>
        </div>
        <div class="badge badge-success">UniStore Verified</div>
    </div>

    <?php if($error): ?><div class="badge badge-danger"><?php echo $error; ?></div><?php endif; ?>
    <?php if($success): ?><div class="badge badge-success"><?php echo $success; ?></div><?php endif; ?>

    <div class="grid grid-2" style="align-items: start;">
        <!-- Column 1: Order Verification -->
        <div class="glass" style="padding: 2rem;">
            <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-receipt" style="color: var(--primary);"></i>
                Pending Verification (Check within 2 mins)
            </h3>
            
            <?php if(empty($pending_orders)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 2rem;">No orders awaiting verification.</p>
            <?php else: ?>
                <?php foreach($pending_orders as $o): ?>
                    <div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: 15px; border: 1px solid var(--glass-border); margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <strong>Order #<?php echo $o['id']; ?></strong>
                            <span style="color: var(--accent);">₹<?php echo $o['total_price']; ?></span>
                        </div>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 15px;">By: <?php echo $o['member_name']; ?></p>
                        
                        <div style="background: #000; padding: 10px; border-radius: 10px; margin-bottom: 15px;">
                            <p style="font-size: 0.8rem; color: #fff;">TRN ID: <code><?php echo $o['transaction_id']; ?></code></p>
                        </div>

                        <div style="display: flex; gap: 10px;">
                            <a href="<?php echo $o['screenshot_url']; ?>" target="_blank" class="btn btn-glass" style="flex: 1; font-size: 0.8rem;">View Receipt</a>
                            <a href="store_dashboard.php?verify_order=<?php echo $o['id']; ?>" class="btn btn-primary" style="flex: 1; font-size: 0.8rem;">Confirm Order</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Column 2: Inventory Management -->
        <div class="glass" style="padding: 2rem;">
            <h3 style="margin-bottom: 1.5rem;">Add New Product</h3>
            <form action="store_dashboard.php" method="POST" enctype="multipart/form-data" style="margin-bottom: 2rem;">
                <input type="hidden" name="add_product" value="1">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="p_name" class="form-input" placeholder="e.g. Chicken Burger" required>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label>Price (₹)</label>
                        <input type="number" name="p_price" class="form-input" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="p_category" class="form-input" style="background: #1e293b;">
                            <option value="Food">Food</option>
                            <option value="Stationery">Stationery</option>
                            <option value="Sports">Sports</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Product Image (Optional)</label>
                    <div style="position: relative; overflow: hidden; display: inline-block; width: 100%;">
                        <button type="button" class="btn btn-glass" style="width: 100%; justify-content: center; background: rgba(255,255,255,0.05); border: 1px dashed var(--text-muted);">
                            <i class="fas fa-image"></i> Click to Upload Image
                        </button>
                        <input type="file" name="p_image" accept="image/*" style="position: absolute; left: 0; top: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer;">
                    </div>
                    <p style="font-size: 0.7rem; color: var(--text-muted); margin-top: 5px;">Format: JPG, PNG (Max 5MB)</p>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Add Product</button>
            </form>

            <h3 style="margin-bottom: 1rem;">My Inventory</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($products as $p): ?>
                            <tr>
                                <td><?php echo $p['name']; ?></td>
                                <td><?php echo $p['category']; ?></td>
                                <td>₹<?php echo $p['price']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
