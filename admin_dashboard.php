<?php 
require_once 'includes/config.php';

// Check if Admin
if(!isLoggedIn() || !hasRole('ADMIN')) {
    header("Location: index.php");
    exit();
}

$success = '';
$error = '';

// Handle Approval/Rejection
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $store_id = $_GET['id'];
    $new_status = ($action === 'approve') ? 'APPROVED' : 'REJECTED';

    $stmt = $pdo->prepare("UPDATE stores SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $store_id])) {
        $success = "Store " . ucfirst($action) . "d successfully.";
    } else {
        $error = "Failed to update store status.";
    }
}

// Fetch Pending Stores
$stmt = $pdo->prepare("SELECT s.*, u.name as owner_name, u.email as owner_email FROM stores s JOIN users u ON s.user_id = u.id WHERE s.status = 'PENDING' ORDER BY s.id DESC");
$stmt->execute();
$pending_stores = $stmt->fetchAll();

include_once 'includes/header.php';
?>

<div class="container" style="padding-top: 3rem;">
    <div class="flex-mobile-col" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 2.5rem;">Admin Control <span style="color: var(--primary);">Panel</span></h1>
            <p style="color: var(--text-muted);">Review and moderate campus store registrations.</p>
        </div>
        <div class="glass" style="padding: 1rem 1.5rem; text-align: center;">
            <span style="font-size: 2rem; font-weight: 800; color: var(--primary); display: block;"><?php echo count($pending_stores); ?></span>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Pending Requests</span>
        </div>
    </div>

    <?php if($success): ?>
        <div class="badge badge-success" style="display: block; margin-bottom: 2rem; padding: 1rem; text-align: center;"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="badge badge-danger" style="display: block; margin-bottom: 2rem; padding: 1rem; text-align: center;"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="grid grid-1" style="gap: 2rem;">
        <?php if(empty($pending_stores)): ?>
            <div class="glass" style="padding: 4rem; text-align: center;">
                <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--success); margin-bottom: 1rem; opacity: 0.3;"></i>
                <h3>No pending requests</h3>
                <p style="color: var(--text-muted);">All store applications have been processed.</p>
            </div>
        <?php endif; ?>

        <?php foreach($pending_stores as $s): ?>
            <div class="glass flex-mobile-col" style="padding: 2rem; display: flex; gap: 2rem; align-items: flex-start;">
                <!-- Store Image -->
                <div style="width: 200px; height: 150px; border-radius: 12px; overflow: hidden; background: #000;">
                    <img src="<?php echo $s['image_url']; ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="Store">
                </div>

                <!-- Store Info -->
                <div style="flex-grow: 1;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                        <h2 style="font-size: 1.8rem;"><?php echo $s['store_name']; ?></h2>
                        <span class="badge badge-warning">Awaiting Verification</span>
                    </div>
                    
                    <div class="grid grid-2" style="gap: 1.5rem;">
                        <div>
                            <p style="margin-bottom: 0.5rem;"><i class="fas fa-user-circle" style="width: 25px; color: var(--primary);"></i> <strong>Owner:</strong> <?php echo $s['owner_name']; ?> (<?php echo $s['owner_email']; ?>)</p>
                            <p style="margin-bottom: 0.5rem;"><i class="fas fa-map-marker-alt" style="width: 25px; color: var(--primary);"></i> <strong>Location:</strong> <?php echo $s['location']; ?></p>
                            <p style="margin-bottom: 0.5rem;"><i class="fas fa-phone" style="width: 25px; color: var(--primary);"></i> <strong>Contact:</strong> <?php echo $s['contact']; ?></p>
                        </div>
                        <div>
                            <p style="margin-bottom: 0.5rem;"><i class="fas fa-id-card" style="width: 25px; color: var(--primary);"></i> <strong>Aadhar No:</strong> <?php echo $s['aadhar_number']; ?></p>
                            <p style="margin-bottom: 0.5rem;"><i class="fas fa-image" style="width: 25px; color: var(--primary);"></i> <strong>Aadhar Card:</strong> <a href="<?php echo $s['aadhar_image']; ?>" target="_blank" style="color: var(--primary);">View Image</a></p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="admin_dashboard.php?action=approve&id=<?php echo $s['id']; ?>" class="btn btn-primary" onclick="return confirm('Approve this store?')">Approve Store</a>
                    <a href="admin_dashboard.php?action=reject&id=<?php echo $s['id']; ?>" class="btn btn-glass" style="color: #ef4444;" onclick="return confirm('Reject this store?')">Reject Request</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>
