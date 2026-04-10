<?php 
require_once 'includes/config.php';

if(isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Both fields are required.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // SECURITY: Redirect Admin to specialized login
            if ($user['role'] === 'ADMIN') {
                header("Location: admin_login.php?error=use_portal");
                exit();
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['points'] = $user['points'];

            // Redirect based on role
            if($user['role'] == 'STORE') {
                header("Location: store_dashboard.php");
            } elseif($user['role'] == 'RIDER') {
                header("Location: rider_dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    }
}

include_once 'includes/header.php';
?>

<div class="container auth-wrapper">
    <div class="auth-card glass">
        <div class="auth-header">
            <h2>Welcome Back</h2>
            <p>Login to your UniSphere Account</p>
        </div>

        <?php if($error): ?>
            <div class="badge badge-danger" style="display: block; margin-bottom: 1rem; text-align: center;"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" class="form-input" placeholder="name@university.edu" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 1rem;">
                Login Now
            </button>
        </form>

        <p style="margin-top: 2rem; text-align: center; color: var(--text-muted);">
            Don't have an account? <a href="register.php" style="color: var(--primary); text-decoration: none;">Register here</a>
        </p>
    </div>
</div>

</body>
</html>
