<?php 
require_once 'includes/config.php';

if(!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Remove incomplete payment orders (older than 1 minute)
$delete_stmt = $pdo->prepare("DELETE FROM orders WHERE status = 'AWAITING_PAYMENT' AND created_at < DATE_SUB(NOW(), INTERVAL 1 MINUTE) AND member_id = ?");
$delete_stmt->execute([$user_id]);

// 1. Fetch My Orders
$stmt = $pdo->prepare("SELECT o.*, s.store_name, r.name as rider_name 
                       FROM orders o 
                       JOIN stores s ON o.store_id = s.id 
                       LEFT JOIN users r ON o.rider_id = r.id 
                       WHERE o.member_id = ? 
                       ORDER BY o.id DESC");
$stmt->execute([$user_id]);
$my_orders = $stmt->fetchAll();

include_once 'includes/header.php';
?>

<div class="container" style="padding-top: 3rem;">
    <h1 style="margin-bottom: 2rem;">My UniSphere Orders</h1>

    <?php if(empty($my_orders)): ?>
        <div class="glass" style="padding: 4rem; text-align: center;">
            <i class="fas fa-box-open" style="font-size: 4rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
            <p style="color: var(--text-muted);">You haven't ordered anything yet.</p>
            <a href="index.php" class="btn btn-primary" style="margin-top: 1rem;">Start Shopping</a>
        </div>
    <?php else: ?>
        <div class="grid grid-2">
            <?php foreach($my_orders as $o): ?>
                <div class="glass" style="padding: 1.5rem; position: relative;">
                    <div class="flex-mobile-col" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem;">
                        <div>
                            <h3 style="font-size: 1.2rem;">Order #<?php echo $o['id']; ?></h3>
                            <p style="color: var(--text-muted); font-size: 0.85rem;"><?php echo date('M d, H:i', strtotime($o['created_at'])); ?></p>
                        </div>
                        <span class="badge badge-<?php echo ($o['status'] == 'DELIVERED') ? 'success' : (($o['status'] == 'CANCELLED') ? 'danger' : 'pending'); ?>">
                            <?php echo str_replace('_', ' ', $o['status']); ?>
                        </span>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <p style="font-size: 0.95rem; margin-bottom: 5px;">Store: <strong><?php echo $o['store_name']; ?></strong></p>
                        <p style="font-size: 0.95rem;">Total: <span style="color: var(--primary); font-weight: 700;">₹<?php echo $o['total_price']; ?></span></p>
                    </div>

                    <?php if($o['status'] == 'CANCELLED' && !empty($o['rejection_reason'])): ?>
                        <div style="background: rgba(239, 68, 68, 0.05); padding: 1.2rem; border-radius: 12px; border: 1px dashed var(--danger); margin-bottom: 1rem;">
                            <p style="font-size: 0.75rem; color: var(--danger); margin-bottom: 5px; text-transform: uppercase; font-weight: 600;">Order Declined</p>
                            <p style="font-size: 0.95rem; font-weight: 500; color: var(--text-main);">
                                Reason: <?php echo htmlspecialchars($o['rejection_reason']); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <?php if($o['status'] != 'DELIVERED' && $o['status'] != 'CANCELLED' && $o['status'] != 'AWAITING_PAYMENT'): ?>
                        <div style="background: rgba(99, 102, 241, 0.05); padding: 1.2rem; border-radius: 12px; border: 1px dashed var(--primary); margin-bottom: 1rem;">
                            <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 5px; text-transform: uppercase;">Delivery OTP (Share with UniRider)</p>
                            <span style="font-size: 1.8rem; font-weight: 700; color: var(--text-main); letter-spacing: 2px;">
                                <?php echo $o['otp'] ?? '----'; ?>
                            </span>
                        </div>
                        
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <?php if($o['status'] != 'DELIVERED' && $o['status'] != 'CANCELLED'): ?>
                                <a href="track_order.php?order_id=<?php echo $o['id']; ?>" class="btn btn-primary" style="flex: 1; justify-content: center;">
                                    <i class="fas fa-map-marker-alt"></i> Track Live
                                </a>
                            <?php else: ?>
                                <a href="track_order.php?order_id=<?php echo $o['id']; ?>" class="btn btn-glass" style="flex: 1; justify-content: center;">
                                    View Summary
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if($o['status'] == 'AWAITING_PAYMENT'): ?>
                        <a href="pay_order.php?order_id=<?php echo $o['id']; ?>" class="btn btn-warning" style="width: 100%; justify-content: center;">
                            Complete Payment
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
