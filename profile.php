<?php 
require_once 'includes/config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Fetch current user details
$stmt = $pdo->prepare("SELECT name, email, delivery_address FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $delivery_address = ($_SESSION['role'] === 'MEMBER') ? ($_POST['delivery_address'] ?? '') : $user['delivery_address'];
    $new_password = $_POST['new_password'] ?? '';

    if (empty($name) || empty($email)) {
        $error = "Name and Email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email is taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $error = "Email is already in use by another account.";
        } else {
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, delivery_address = ?, password = ? WHERE id = ?");
                if ($update_stmt->execute([$name, $email, $delivery_address, $hashed_password, $user_id])) {
                    $success = "Profile updated successfully.";
                    $_SESSION['name'] = $name; // Update session name
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $user['delivery_address'] = $delivery_address;
                } else {
                    $error = "Failed to update profile.";
                }
            } else {
                $update_stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, delivery_address = ? WHERE id = ?");
                if ($update_stmt->execute([$name, $email, $delivery_address, $user_id])) {
                    $success = "Profile updated successfully.";
                    $_SESSION['name'] = $name; // Update session name
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $user['delivery_address'] = $delivery_address;
                } else {
                    $error = "Failed to update profile.";
                }
            }
        }
    }
}

include_once 'includes/header.php';
?>

<div class="container" style="padding-top: 3rem; margin-bottom: 4rem; display: flex; justify-content: center;">
    <div class="glass" style="padding: 3rem; border-radius: 16px; width: 100%; max-width: 500px;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <i class="fas fa-user-edit" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;"></i>
            <h2 style="color: var(--text-main);">Edit Profile</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Update your basic account information</p>
        </div>

        <?php if($error): ?>
            <div class="badge badge-danger" style="display: block; margin-bottom: 1.5rem; text-align: center; padding: 12px; font-size: 1rem;"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="badge badge-success" style="display: block; margin-bottom: 1.5rem; text-align: center; padding: 12px; font-size: 1rem;"><?php echo $success; ?></div>
        <?php endif; ?>

        <form action="profile.php" method="POST">
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 8px; color: var(--text-main); font-weight: 500;">Full Name</label>
                <input type="text" name="name" class="form-input" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 8px; color: var(--text-main); font-weight: 500;">Email Address</label>
                <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>

            <?php if ($_SESSION['role'] === 'MEMBER'): ?>
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 8px; color: var(--text-main); font-weight: 500;">Delivery Address</label>
                <input type="text" name="delivery_address" class="form-input" placeholder="e.g. Hostel A, Room 101" value="<?php echo htmlspecialchars($user['delivery_address'] ?? ''); ?>">
            </div>
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 2.5rem;">
                <label style="display: block; margin-bottom: 8px; color: var(--text-main); font-weight: 500;">New Password</label>
                <input type="password" name="new_password" class="form-input" placeholder="Leave blank to keep current password">
                <small style="color: var(--text-muted); margin-top: 5px; display: block;">Only fill this if you want to change your password.</small>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 14px; font-size: 1.1rem; border-radius: 8px;">
                <i class="fas fa-save" style="margin-right: 8px;"></i> Save Changes
            </button>
        </form>
    </div>
</div>

</body>
</html>
