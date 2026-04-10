<?php 
require_once 'includes/config.php';

if(!isLoggedIn() || !hasRole('RIDER')) {
    header("Location: login.php");
    exit();
}

$rider_id = $_SESSION['user_id'];
$success = '';
$error = '';

// 1. Handle Status Toggle
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_status'])) {
    $new_status = $_POST['new_status'] == '1' ? 1 : 0;
    $pdo->prepare("UPDATE users SET is_online = ? WHERE id = ?")->execute([$new_status, $rider_id]);
    $success = $new_status ? "You are now ONLINE and visible for deliveries." : "You are now OFFLINE.";
}

// Fetch Current Rider Status
$stmt = $pdo->prepare("SELECT is_online FROM users WHERE id = ?");
$stmt->execute([$rider_id]);
$is_online = $stmt->fetchColumn();

// 1.5 Handle Accepting Order
if (isset($_GET['accept_order'])) {
    if (!$is_online) {
        $error = "You must be online to accept incoming deliveries.";
    } else {
        $order_id = $_GET['accept_order'];
        $stmt = $pdo->prepare("UPDATE orders SET rider_id = ?, status = 'PICKED_UP' WHERE id = ? AND status = 'CONFIRMED'");
        if($stmt->execute([$rider_id, $order_id])) {
            $success = "Order accepted! Pick it up and update your coordinates.";
        }
    }
}

// 2. Handle OTP Verification (Delivered)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_otp'])) {
    $order_id = $_POST['order_id'];
    $input_otp = $_POST['otp'];

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND rider_id = ?");
    $stmt->execute([$order_id, $rider_id]);
    $order = $stmt->fetch();

    if ($order && $order['otp'] === $input_otp) {
        // Complete Order
        $pdo->prepare("UPDATE orders SET status = 'DELIVERED' WHERE id = ?")->execute([$order_id]);
        
        // Award Points
        $points = 50; // Constants
        $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$points, $rider_id]);
        $_SESSION['points'] += $points;
        
        $success = "Delivery verified! You earned 50 Reward Points.";
    } else {
        $error = "Invalid OTP. Please check with the UniMember.";
    }
}

// 3. Handle Manual Checkpoint Fallback
if (isset($_GET['update_checkpoint'])) {
    $order_id = $_GET['order_id'];
    $loc = $_GET['update_checkpoint'];
    $pdo->prepare("UPDATE orders SET checkpoint = ? WHERE id = ? AND rider_id = ?")->execute([$loc, $order_id, $rider_id]);
    $success = "Checkpoint updated to: " . $loc;
}

// 4. Fetch Available Orders (Confirmed by UniStore but no Rider yet)
$stmt = $pdo->prepare("SELECT o.*, s.store_name FROM orders o JOIN stores s ON o.store_id = s.id WHERE o.status = 'CONFIRMED' AND o.rider_id IS NULL");
$stmt->execute();
$available_orders = $stmt->fetchAll();

// 5. Fetch Ongoing Orders (Accepted by this Rider)
$stmt = $pdo->prepare("SELECT o.*, s.store_name, u.name as member_name 
                       FROM orders o 
                       JOIN stores s ON o.store_id = s.id 
                       JOIN users u ON o.member_id = u.id 
                       WHERE o.rider_id = ? AND o.status != 'DELIVERED' AND o.status != 'CANCELLED'");
$stmt->execute([$rider_id]);
$my_active_orders = $stmt->fetchAll();

// 6. Fetch Recently Completed Deliveries
$stmt = $pdo->prepare("SELECT o.*, s.store_name, u.name as member_name 
                       FROM orders o 
                       JOIN stores s ON o.store_id = s.id 
                       JOIN users u ON o.member_id = u.id 
                       WHERE o.rider_id = ? AND o.status = 'DELIVERED' 
                       ORDER BY o.updated_at DESC LIMIT 5");
$stmt->execute([$rider_id]);
$completed_orders = $stmt->fetchAll();

include_once 'includes/header.php';
?>

<div class="container" style="padding-top: 2rem;">
    <div class="flex-mobile-col" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1>UniRider Console</h1>
            <p style="color: var(--text-muted);">Fuel the campus ecosystem. Earn while you move.</p>
        </div>
        <div style="text-align: right; display: flex; gap: 20px; align-items: center;">
            <form action="rider_dashboard.php" method="POST" style="margin: 0;">
                <input type="hidden" name="toggle_status" value="1">
                <input type="hidden" name="new_status" value="<?php echo $is_online ? '0' : '1'; ?>">
                <button type="submit" class="btn" style="background: <?php echo $is_online ? 'var(--warning)' : 'var(--accent)'; ?>; color: #fff;">
                    <i class="fas fa-power-off"></i> <?php echo $is_online ? 'Go Offline' : 'Go Online'; ?>
                </button>
            </form>
            <div style="text-align: right;">
                <p style="font-size: 0.8rem; color: var(--text-muted);">My Points</p>
                <span style="font-size: 1.5rem; font-weight: 700; color: var(--primary);"><?php echo number_get_format($_SESSION['points'] ?? 0, 0); ?></span>
            </div>
        </div>
    </div>

    <?php if($error): ?><div class="badge badge-danger"><?php echo $error; ?></div><?php endif; ?>
    <?php if($success): ?><div class="badge badge-success"><?php echo $success; ?></div><?php endif; ?>

    <div class="grid grid-2">
        <!-- Active Tasks -->
        <div class="glass" style="padding: 2rem;">
            <h3 style="margin-bottom: 1.5rem;"><i class="fas fa-motorcycle" style="color: var(--primary);"></i> My Active Deliveries</h3>
            
            <?php if(empty($my_active_orders)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 2rem;">No active tasks. Accept one from the market!</p>
            <?php else: ?>
                <?php foreach($my_active_orders as $o): ?>
                    <div style="background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: 15px; border: 1px solid var(--glass-border); margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <strong>Order #<?php echo $o['id']; ?></strong>
                            <span class="badge badge-pending"><?php echo str_replace('_', ' ', $o['status']); ?></span>
                        </div>
                        <p style="font-size: 0.9rem; color: var(--text-muted);">From: <strong><?php echo $o['store_name']; ?></strong></p>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 15px;">To: <strong><?php echo $o['member_name']; ?></strong></p>
                        
                        <div style="background: #000; padding: 15px; border-radius: 12px; margin-bottom: 15px;">
                            <p style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 8px;">Update Current Checkpoint:</p>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <?php $points = ['Store', 'Block A', 'Main Library', 'Hostel 1 Gate', 'Block D']; 
                                foreach($points as $p_point): ?>
                                    <a href="rider_dashboard.php?order_id=<?php echo $o['id']; ?>&update_checkpoint=<?php echo $p_point; ?>" 
                                       class="btn btn-glass" style="padding: 4px 10px; font-size: 0.7rem; <?php echo ($o['checkpoint'] == $p_point) ? 'border-color: var(--primary); color: var(--primary);' : ''; ?>">
                                       <?php echo $p_point; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <form action="rider_dashboard.php" method="POST" style="display: flex; gap: 10px;">
                            <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                            <input type="text" name="otp" class="form-input" placeholder="Enter Member OTP" style="flex: 1; padding: 8px;" required>
                            <button type="submit" name="verify_otp" class="btn btn-primary" style="padding: 10px 15px;">Verify & Finish</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Available Market -->
        <div class="glass" style="padding: 2rem;">
            <h3 style="margin-bottom: 1.5rem;"><i class="fas fa-map-marker-alt" style="color: var(--accent);"></i> Available for Pickup</h3>
            
            <?php if(!$is_online): ?>
                <div style="text-align: center; padding: 2rem; border-radius: 12px; background: rgba(239, 68, 68, 0.1); border: 1px dashed var(--danger);">
                    <i class="fas fa-power-off" style="font-size: 2rem; color: var(--danger); margin-bottom: 1rem;"></i>
                    <p style="color: var(--danger); font-weight: 600;">You are currently offline.</p>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 5px;">Toggle your status to 'Online' to view and accept incoming delivery tasks.</p>
                </div>
            <?php elseif(empty($available_orders)): ?>
                <p style="color: var(--text-muted); text-align: center; padding: 2rem;">No orders ready for pickup yet.</p>
            <?php else: ?>
                <?php foreach($available_orders as $o): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 1px solid var(--glass-border); border-radius: 12px; margin-bottom: 1rem;">
                        <div>
                            <p style="font-weight: 600;">#<?php echo $o['id']; ?> - <?php echo $o['store_name']; ?></p>
                            <p style="font-size: 0.8rem; color: var(--text-muted);">Reward: <span style="color: var(--primary);">+50 Pts</span></p>
                        </div>
                        <a href="rider_dashboard.php?accept_order=<?php echo $o['id']; ?>" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.85rem;">Accept Task</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recently Completed -->
    <div class="glass" style="padding: 2rem; margin-top: 2rem;">
        <h3 style="margin-bottom: 1.5rem;"><i class="fas fa-check-circle" style="color: var(--success);"></i> Recently Completed</h3>
        <?php if(empty($completed_orders)): ?>
            <p style="color: var(--text-muted); text-align: center; padding: 1rem;">Finish your first delivery to see it here!</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border); text-align: left;">
                            <th style="padding: 10px; color: var(--text-muted);">Order</th>
                            <th style="padding: 10px; color: var(--text-muted);">Store</th>
                            <th style="padding: 10px; color: var(--text-muted);">Member</th>
                            <th style="padding: 10px; color: var(--text-muted);">Points Earned</th>
                            <th style="padding: 10px; color: var(--text-muted);">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($completed_orders as $co): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 10px;">#<?php echo $co['id']; ?></td>
                                <td style="padding: 10px;"><?php echo $co['store_name']; ?></td>
                                <td style="padding: 10px;"><?php echo $co['member_name']; ?></td>
                                <td style="padding: 10px; color: var(--primary);">+50 Pts</td>
                                <td style="padding: 10px; color: var(--text-muted);"><?php echo date('M d, H:i', strtotime($co['updated_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Live Location Simulation Script -->
<script>
    // In a real app, we would use navigator.geolocation.watchPosition
    // For UniSphere Demo, we'll simulate it every 15 seconds if an active task exists
    const simulateLocation = () => {
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition((position) => {
                const { latitude, longitude } = position.coords;
                // We would POST this to sync_location.php
                console.log("Syncing Location:", latitude, longitude);
            }, (error) => {
                console.warn("Location services failed. Fallback to Manual Checkpoint already active.");
            });
        }
    };
    setInterval(simulateLocation, 15000);
</script>

</body>
</html>
