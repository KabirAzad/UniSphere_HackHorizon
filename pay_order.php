<?php 
require_once 'includes/config.php';

if(!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if(!isset($_GET['order_id'])) {
    header("Location: index.php");
    exit();
}

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'];

// Fetch order
$stmt = $pdo->prepare("SELECT o.*, s.store_name FROM orders o JOIN stores s ON o.store_id = s.id WHERE o.id = ? AND o.member_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if(!$order) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

// Calculate Live ETD based on UniRiders
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'RIDER' AND is_online = 1");
$stmt->execute();
$rider_count = $stmt->fetchColumn();

if ($rider_count == 0) {
    $etd_message = "30-45 mins (Limited Riders)";
} elseif ($rider_count <= 2) {
    $etd_message = "15-25 mins";
} else {
    $etd_message = "10-15 mins";
}

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_payment'])) {
    $trn_id = $_POST['trn_id'];
    
    // File Upload (Mocking for now, but setting path)
    $target_dir = "uploads/payments/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = "pay_" . $order_id . "_" . time() . ".jpg";
    $target_file = $target_dir . $file_name;

    // For Demo: we'll just "mock" the upload success if trn_id is provided
    if(!empty($trn_id)) {
        // In real production: move_uploaded_file($_FILES["ss"]["tmp_name"], $target_file);
        $screenshot_url = $target_file; 
        
        $stmt = $pdo->prepare("INSERT INTO payments (order_id, transaction_id, screenshot_url) VALUES (?, ?, ?)");
        if($stmt->execute([$order_id, $trn_id, $screenshot_url])) {
            // Update order status
            $pdo->prepare("UPDATE orders SET status = 'PENDING_VERIFICATION' WHERE id = ?")->execute([$order_id]);
            $success = "Payment submitted! UniStore will verify it within 2 minutes.";
            
            // Redirect after 2 seconds
            header("refresh:2;url=my_orders.php");
        }
    } else {
        $error = "Transaction ID is required.";
    }
}

include_once 'includes/header.php';
?>

<div class="container auth-wrapper" style="align-items: start; padding-top: 5rem;">
    <div class="auth-card glass" style="max-width: 600px;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <h2 style="font-size: 2.2rem; margin-bottom: 0.5rem;">Complete Payment</h2>
            <p style="color: var(--text-muted); margin-bottom: 10px;">Order #<?php echo $order_id; ?> | Total: <span style="color: var(--primary); font-weight: 700;">₹<?php echo $order['total_price']; ?></span></p>
            <div style="display: inline-block; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 0.85rem; color: var(--accent);">
                <i class="fas fa-clock"></i> Estimated Delivery: <?php echo $etd_message; ?>
            </div>
        </div>

        <?php if($error): ?><div class="badge badge-danger" style="display: block; margin-bottom: 1rem; text-align: center;"><?php echo $error; ?></div><?php endif; ?>
        <?php if($success): ?><div class="badge badge-success" style="display: block; margin-bottom: 1rem; text-align: center;"><?php echo $success; ?></div><?php endif; ?>

        <div class="grid grid-2" style="gap: 30px;">
            <!-- QR Section -->
            <div style="text-align: center;">
                <div style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 20px; border: 1px solid var(--glass-border); margin-bottom: 1rem;">
                    <img src="uploads/images/payment_QR.jpeg" alt="Payment QR Code" style="max-width: 100%; border-radius: 10px; width: 200px; height: auto;">
                    <p style="margin-top: 1rem; font-size: 0.8rem; color: var(--text-muted);">Scan to pay via UPI</p>
                </div>
                <p style="font-weight: 600; color: var(--accent);">UniSphere Verified Vendor</p>
                <p style="font-size: 0.85rem; color: var(--text-muted);"><?php echo $order['store_name']; ?></p>
            </div>

            <!-- Form Section -->
            <form action="pay_order.php?order_id=<?php echo $order_id; ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="submit_payment" value="1">
                <div class="form-group">
                    <label>Transaction ID (TRN)</label>
                    <input type="text" name="trn_id" class="form-input" placeholder="Enter 12-digit UPI TRN" required>
                </div>
                <div class="form-group">
                    <label>Payment Screenshot</label>
                    <div style="position: relative; overflow: hidden; display: inline-block; width: 100%;">
                        <button type="button" class="btn btn-glass" style="width: 100%; justify-content: center;">
                            <i class="fas fa-upload"></i> Upload Screenshot
                        </button>
                        <input type="file" name="ss" style="position: absolute; left: 0; top: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer;">
                    </div>
                    <p style="font-size: 0.7rem; color: var(--text-muted); margin-top: 5px;">Format: JPG, PNG (Max 5MB)</p>
                </div>
                
                <p style="font-size: 0.75rem; color: var(--danger); margin-bottom: 1.5rem; line-height: 1.4;">
                    <i class="fas fa-info-circle"></i> No refunds on cancellation once payment is submitted.
                </p>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    Submit Payment
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
