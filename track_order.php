<?php 
require_once 'includes/config.php';

if(!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

if(!isset($_GET['order_id'])) {
    header("Location: my_orders.php");
    exit();
}

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'];

// 1. Fetch Order and Rider details
$stmt = $pdo->prepare("SELECT o.*, s.store_name, r.name as rider_name 
                       FROM orders o 
                       JOIN stores s ON o.store_id = s.id 
                       LEFT JOIN users r ON o.rider_id = r.id 
                       WHERE o.id = ? AND o.member_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if(!$order) {
    header("Location: my_orders.php");
    exit();
}

include_once 'includes/header.php';
?>

<div class="container" style="padding-top: 3rem;">
    <div class="flex-mobile-col" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-end;">
        <div>
            <h1 style="font-size: 2.5rem; margin-bottom: 5px;">Track Journey</h1>
            <p style="color: var(--text-muted);">Journey for UniMember Order #<?php echo $order_id; ?></p>
        </div>
        <div style="text-align: right;">
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 5px;">Current Milestone</p>
            <span class="badge badge-success"><?php echo $order['checkpoint']; ?></span>
        </div>
    </div>

    <div class="grid grid-2">
        <!-- Live Map Section -->
        <div class="glass" style="padding: 1rem; height: 500px; position: relative; overflow: hidden;">
            <div id="map" style="width: 100%; height: 100%; border-radius: 12px; z-index: 1;"></div>
            
            <!-- Tracking Card Overlay -->
            <div style="position: absolute; bottom: 20px; left: 20px; right: 20px; z-index: 1000; background: rgba(2, 6, 23, 0.9); backdrop-filter: blur(8px); padding: 1.5rem; border-radius: 15px; border: 1px solid var(--glass-border); display: flex; align-items: center; gap: 15px;">
                <div style="width: 50px; height: 50px; background: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-motorcycle" style="font-size: 1.5rem; color: white;"></i>
                </div>
                <div>
                    <h4 style="margin-bottom: 2px;">UniRider: <?php echo $order['rider_name'] ?? 'Assigning...'; ?></h4>
                    <p style="font-size: 0.8rem; color: var(--text-muted);"><?php echo ($order['status'] == 'PICKED_UP') ? 'Heading to your location' : 'Status: ' . str_replace('_', ' ', $order['status']); ?></p>
                </div>
            </div>
        </div>

        <!-- Status Timeline -->
        <div class="glass" style="padding: 2rem;">
            <h3>Delivery Timeline</h3>
            <div style="margin-top: 2rem;">
                <div style="display: flex; gap: 20px; margin-bottom: 2rem; position: relative;">
                    <div style="width: 12px; height: 12px; background: var(--accent); border-radius: 50%; position: relative; z-index: 2;"></div>
                    <div style="position: absolute; left: 5px; top: 12px; bottom: -30px; width: 2px; background: rgba(255,255,255,0.1); z-index: 1;"></div>
                    <div>
                        <h5 style="margin-bottom: 5px;">Payment Verified</h5>
                        <p style="font-size: 0.8rem; color: var(--text-muted);">UniStore confirmed your payment.</p>
                    </div>
                </div>

                <div style="display: flex; gap: 20px; margin-bottom: 2rem; position: relative;">
                    <div style="width: 12px; height: 12px; background: <?php echo ($order['status'] != 'CONFIRMED') ? 'var(--primary)' : 'rgba(255,255,255,0.1)'; ?>; border-radius: 50%; z-index: 2;"></div>
                    <div style="position: absolute; left: 5px; top: 12px; bottom: -30px; width: 2px; background: rgba(255,255,255,0.1); z-index: 1;"></div>
                    <div>
                        <h5 style="margin-bottom: 5px;">Picked Up From <?php echo $order['store_name']; ?></h5>
                        <p style="font-size: 0.8rem; color: var(--text-muted);"><?php echo ($order['status'] == 'PICKED_UP' || $order['status'] == 'IN_TRANSIT') ? 'Your UniRider has the items.' : 'Awaiting UniRider collection.'; ?></p>
                    </div>
                </div>

                <div style="display: flex; gap: 20px; margin-bottom: 2rem; position: relative;">
                    <div style="width: 12px; height: 12px; background: <?php echo ($order['status'] == 'DELIVERED') ? 'var(--success)' : 'rgba(255,255,255,0.1)'; ?>; border-radius: 50%; z-index: 2;"></div>
                    <div>
                        <h5 style="margin-bottom: 5px;">Delivered</h5>
                        <p style="font-size: 0.8rem; color: var(--text-muted);"><?php echo ($order['status'] == 'DELIVERED') ? 'Order successfully received.' : 'Pending final verification.'; ?></p>
                    </div>
                </div>
            </div>

            <!-- Contextual Card (OTP or Success) -->
            <?php if($order['status'] == 'DELIVERED'): ?>
                <div style="margin-top: 3rem; background: rgba(34, 197, 94, 0.05); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(34, 197, 94, 0.2); text-align: center;">
                    <i class="fas fa-check-circle" style="font-size: 2.5rem; color: var(--success); margin-bottom: 1rem;"></i>
                    <h4 style="margin-bottom: 5px;">Order Delivered!</h4>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Thank you for using UniSphere. We hope you enjoy your purchase!</p>
                    <a href="my_orders.php" class="btn btn-glass" style="margin-top: 1rem; width: 100%;">Back to My Orders</a>
                </div>
            <?php else: ?>
                <div style="margin-top: 3rem; background: rgba(239, 68, 68, 0.05); padding: 1.5rem; border-radius: 12px; border: 1px solid rgba(239, 68, 68, 0.2);">
                    <p style="font-size: 0.8rem; color: var(--danger); font-weight: 600; margin-bottom: 10px;">SAFETY PROTOCOL</p>
                    <p style="font-size: 0.85rem; line-height: 1.5;">Wait for the UniRider to arrive before sharing your OTP: <span style="color: white; font-weight: 700; letter-spacing: 1px;"><?php echo $order['otp']; ?></span></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Auto Refresh for Members -->
<?php if($order['status'] != 'DELIVERED' && $order['status'] != 'CANCELLED'): ?>
<script>
    setTimeout(function(){
       window.location.reload(1);
    }, 10000); 
</script>
<?php endif; ?>

<!-- Map Scripts -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
    // Initialize map
    var map = L.map('map').setView([28.6139, 77.2090], 15); // Default to a central point

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'UniSphere Logistics Integration'
    }).addTo(map);

    // Dark Map Styling (Approximate)
    document.querySelector('.leaflet-container').style.background = '#020617';

    // Mock markers
    var storeMarker = L.marker([28.6139, 77.2090]).addTo(map).bindPopup("UniStore Location").openPopup();
    var riderMarker = L.marker([28.6150, 77.2100]).addTo(map).bindPopup("UniRider Current Location");

    // Polyline for journey
    var polyline = L.polyline([
        [28.6139, 77.2090],
        [28.6145, 77.2095],
        [28.6150, 77.2100]
    ], {color: '#6366f1', weight: 8, opacity: 0.6}).addTo(map);

    // Real-time location fetch would happen here
    setInterval(() => {
        // fetch('sync_location.php?order_id=<?php echo $order_id; ?>')...
    }, 15000);
</script>

</body>
</html>
