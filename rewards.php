<?php 
require_once 'includes/config.php';

if(!isLoggedIn() || !hasRole('RIDER')) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// 1. Handle Point Redemption
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redeem_reward'])) {
    $reward_name = $_POST['reward_name'];
    $cost = (int)$_POST['cost'];
    
    // Fetch fresh points from DB
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $current_points = $user['points'] ?? 0;

    if ($current_points >= $cost) {
        $pdo->beginTransaction();
        try {
            // Deduct Points
            $stmt = $pdo->prepare("UPDATE users SET points = points - ? WHERE id = ?");
            $stmt->execute([$cost, $user_id]);

            // Log Redemption
            $stmt = $pdo->prepare("INSERT INTO redemptions (user_id, reward_name, points_cost) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $reward_name, $cost]);

            $pdo->commit();
            
            // Generate a random 12-character mock code
            $mock_code = strtoupper(bin2hex(random_bytes(6))); // 12 chars
            
            // Update Session
            $_SESSION['points'] -= $cost;
            $success = "Successfully redeemed: " . $reward_name . "! Your voucher code is: <span style='background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 4px; letter-spacing: 2px; font-weight: bold; margin-left: 5px; user-select: all;'>" . $mock_code . "</span>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Redemption failed. Please try again.";
        }
    } else {
        // Handled silently since frontend blocks the click unless bypassed.
        // $error = "Insufficient points balance for this reward.";
    }
}

// Fetch current points for display
$stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_points = $stmt->fetchColumn();

include_once 'includes/header.php';
?>

<div class="container" style="padding-top: 5rem;">
    <?php if($success): ?><div class="badge badge-success" style="width: 100%; margin-bottom: 2rem; padding: 1rem;"><?php echo $success; ?></div><?php endif; ?>
    <?php if($error): ?><div class="badge badge-danger" style="width: 100%; margin-bottom: 2rem; padding: 1rem;"><?php echo $error; ?></div><?php endif; ?>

    <div class="grid grid-2" style="align-items: center; gap: 4rem;">
        <div>
            <h1 style="font-size: 4rem; margin-bottom: 1rem;">UniRider<br><span style="color: var(--primary);">Rewards.</span></h1>
            <p style="color: var(--text-muted); font-size: 1.2rem; line-height: 1.6;">Fuel the campus, get rewarded. Redeem your UniSphere points for exclusive benefits.</p>
            
            <div style="margin-top: 3rem; display: flex; gap: 20px;">
                <div class="glass" style="padding: 1.5rem 3rem;">
                    <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 5px;">CURRENT BALANCE</p>
                    <h2 style="font-size: 3rem; color: var(--primary);"><?php echo number_get_format($current_points, 0); ?> pts</h2>
                </div>
            </div>
        </div>

        <div class="glass" style="padding: 3rem;">
            <h3 style="margin-bottom: 2rem;">Marketplace Redemption</h3>
            
            <?php 
            $available_rewards = [
                ['name' => 'Amazon Gift Card ₹500', 'cost' => 1500, 'desc' => 'Direct e-voucher code', 'logo' => 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Amazon_logo.svg'],
                ['name' => 'Swiggy Voucher ₹200', 'cost' => 600, 'desc' => 'Use on food orders', 'logo' => 'uploads/images/swiggy.png'],
                ['name' => 'Myntra Discount 20%', 'cost' => 400, 'desc' => 'Valid on top brands', 'logo' => 'uploads/images/myntra.png'],
                ['name' => 'Cafeteria Meal Voucher', 'cost' => 500, 'desc' => 'Redeem for 1 Full Meal', 'logo' => 'https://cdn-icons-png.flaticon.com/512/3448/3448261.png'],
                ['name' => 'Library Pass (Premium)', 'cost' => 300, 'desc' => 'Extended borrowing limits', 'logo' => 'https://cdn-icons-png.flaticon.com/512/3389/3389081.png']
            ];

            foreach($available_rewards as $reward): 
            ?>
                <div class="flex-mobile-col" style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.5rem; border-radius: 15px; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 50px; height: 50px; background: rgba(255,255,255,0.8); border-radius: 10px; padding: 5px; display: flex; align-items: center; justify-content: center;">
                            <img src="<?php echo $reward['logo']; ?>" alt="<?php echo $reward['name']; ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        </div>
                        <div>
                            <h4 style="margin-bottom: 5px;"><?php echo $reward['name']; ?></h4>
                            <p style="font-size: 0.8rem; color: var(--text-muted);"><?php echo $reward['desc']; ?></p>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <p style="font-weight: 700; color: var(--accent); margin-bottom: 10px;"><?php echo $reward['cost']; ?> Pts</p>
                        <form action="rewards.php" method="POST">
                            <input type="hidden" name="reward_name" value="<?php echo $reward['name']; ?>">
                            <input type="hidden" name="cost" value="<?php echo $reward['cost']; ?>">
                            <?php if($current_points < $reward['cost']): ?>
                                <button type="button" class="btn btn-primary" 
                                        style="padding: 8px 16px; font-size: 0.8rem; opacity:0.5;"
                                        onclick="showErrorMsg('insuf-<?php echo md5($reward['name']); ?>')">
                                    Redeem
                                </button>
                                <p id="insuf-<?php echo md5($reward['name']); ?>" style="display: none; color: var(--danger); font-size: 0.75rem; margin-top: 8px; line-height: 1.4; font-weight: 600;">
                                    Insufficient balance.<br>
                                    Earn <?php echo ($reward['cost'] - $current_points); ?> more points to redeem this offer.
                                </p>
                            <?php else: ?>
                                <button type="submit" name="redeem_reward" class="btn btn-primary" 
                                        style="padding: 8px 16px; font-size: 0.8rem;">
                                    Redeem
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <p style="text-align: center; color: var(--text-muted); font-size: 0.8rem;">Physical vouchers can be collected from the UniSphere Admin Office.</p>
        </div>
    </div>
</div>

<script>
// Auto-hide the inline insufficient balance messages after 5 seconds
function showErrorMsg(id) {
    const el = document.getElementById(id);
    el.style.display = 'block';
    setTimeout(() => {
        el.style.display = 'none';
    }, 5000);
}

// Auto-hide any global top badges (success/danger) after 5 seconds
setTimeout(() => {
    document.querySelectorAll('.badge-success, .badge-danger').forEach(el => el.style.display = 'none');
}, 5000);
</script>

</body>
</html>
