<?php 
require_once 'includes/config.php';

if(isLoggedIn() && hasRole('ADMIN')) {
    header("Location: admin_dashboard.php");
    exit();
}

$error = '';

if (isset($_GET['error']) && $_GET['error'] === 'use_portal') {
    $error = "Administrators must authenticate through this secure portal.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Administrator credentials are required.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'ADMIN'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['points'] = $user['points'];

            header("Location: admin_dashboard.php");
            exit();
        } else {
            $error = "Invalid administrator credentials or access denied.";
        }
    }
}

include_once 'includes/header.php';
?>

<div class="container auth-wrapper" style="background: linear-gradient(135deg, rgba(15,23,42,0.9), rgba(30,41,59,0.9)); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem;">
    <div class="auth-card glass" style="max-width: 450px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
        <div class="auth-header" style="text-align: center; margin-bottom: 2.5rem;">
            <div style="width: 80px; height: 80px; background: var(--primary); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; box-shadow: 0 0 30px rgba(59,130,246,0.3);">
                <i class="fas fa-shield-halved" style="font-size: 2.5rem; color: #fff;"></i>
            </div>
            <h2 style="font-size: 2rem; color: #fff;">Admin Portal</h2>
            <p style="color: var(--text-muted);">Secure access for UniSphere System Control</p>
        </div>

        <?php if($error): ?>
            <div class="badge badge-danger" style="display: block; margin-bottom: 2rem; padding: 1.2rem; text-align: center; font-weight: 500;">
                <i class="fas fa-exclamation-triangle" style="margin-right: 10px;"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="admin_login.php" method="POST">
            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 8px; display: block;">Administrative Email</label>
                <div style="position: relative;">
                    <i class="fas fa-envelope" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="email" name="email" class="form-input" placeholder="admin@unisphere.com" required style="padding-left: 45px; background: rgba(0,0,0,0.2);">
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 2rem;">
                <label style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 8px; display: block;">Passcode</label>
                <div style="position: relative;">
                    <i class="fas fa-lock" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="password" name="password" class="form-input" placeholder="••••••••" required style="padding-left: 45px; background: rgba(0,0,0,0.2);">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; height: 55px; font-size: 1.1rem; border-radius: 12px; font-weight: 600;">
                <i class="fas fa-sign-in-alt" style="margin-right: 10px;"></i> Authenticate Admin
            </button>
        </form>

        <p style="margin-top: 2.5rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
            This system is for authorized use only.<br>
            <a href="login.php" style="color: var(--primary); text-decoration: none; display: inline-block; margin-top: 10px;">Back to Member Login</a>
        </p>
    </div>
</div>

</body>
</html>
