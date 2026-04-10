<?php 
require_once 'includes/config.php';

if(isLoggedIn()) {
    header("Location: index.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Basic Validation
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already registered.";
        } else {
            // Handle Store Registration Fields if role is STORE
            $store_name = $_POST['store_name'] ?? '';
            $location = $_POST['location'] ?? '';
            $contact = $_POST['contact'] ?? '';
            $aadhar_number = $_POST['aadhar_number'] ?? '';
            
            $store_image = '';
            $aadhar_image = '';

            if ($role === 'STORE') {
                if (empty($store_name) || empty($location) || empty($contact) || empty($aadhar_number)) {
                    $error = "Store details and Aadhar number are required for vendors.";
                } elseif (strlen($aadhar_number) != 12 || !is_numeric($aadhar_number)) {
                    $error = "Aadhar Number must be a 12-digit number.";
                } elseif (!isset($_FILES['store_image']) || !isset($_FILES['aadhar_image'])) {
                    $error = "Store image and Aadhar image are required.";
                } else {
                    // Upload Store Image
                    $target_dir_store = "assets/uploads/stores/";
                    $store_image_name = time() . "_store_" . basename($_FILES["store_image"]["name"]);
                    if (move_uploaded_file($_FILES["store_image"]["tmp_name"], $target_dir_store . $store_image_name)) {
                        $store_image = $target_dir_store . $store_image_name;
                    } else {
                        $error = "Failed to upload store image.";
                    }

                    // Upload Aadhar Image
                    if (!$error) {
                        $target_dir_aadhar = "assets/uploads/aadhar/";
                        $aadhar_image_name = time() . "_aadhar_" . basename($_FILES["aadhar_image"]["name"]);
                        if (move_uploaded_file($_FILES["aadhar_image"]["tmp_name"], $target_dir_aadhar . $aadhar_image_name)) {
                            $aadhar_image = $target_dir_aadhar . $aadhar_image_name;
                        } else {
                            $error = "Failed to upload Aadhar image.";
                        }
                    }
                }
            }

            if (!$error) {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $hashed_password, $role]);
                    $user_id = $pdo->lastInsertId();

                    if ($role === 'STORE') {
                        $stmt = $pdo->prepare("INSERT INTO stores (user_id, store_name, location, contact, image_url, aadhar_number, aadhar_image, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING')");
                        $stmt->execute([$user_id, $store_name, $location, $contact, $store_image, $aadhar_number, $aadhar_image]);
                    }

                    $pdo->commit();
                    $success = "Registration successful! " . ($role === 'STORE' ? "Your store is pending admin approval." : "You can now login.");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Registration failed: " . $e->getMessage();
                }
            }
        }
    }
}

include_once 'includes/header.php';
?>

<div class="container auth-wrapper">
    <div class="auth-card glass" style="max-width: 600px;">
        <div class="auth-header">
            <h2>Create Account</h2>
            <p>Join the UniSphere Ecosystem</p>
        </div>

        <?php if($error): ?>
            <div class="badge badge-danger" style="display: block; margin-bottom: 1rem; text-align: center;"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="badge badge-success" style="display: block; margin-bottom: 1rem; text-align: center;"><?php echo $success; ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST" enctype="multipart/form-data">
            <div class="grid grid-2" style="gap: 15px;">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-input" placeholder="Enter your name" required>
                </div>
                <div class="form-group">
                    <label>University Email</label>
                    <input type="email" name="email" class="form-input" placeholder="name@university.edu" required>
                </div>
            </div>

            <div class="grid grid-2" style="gap: 15px;">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-input" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label>I am a...</label>
                    <select name="role" id="roleSelect" class="form-input" style="background: #1e293b;" required onchange="toggleStoreFields()">
                        <option value="MEMBER">UniMember (Student)</option>
                        <option value="RIDER">UniRider (Delivery Partner)</option>
                        <option value="STORE">UniStore (Vendor)</option>
                    </select>
                </div>
            </div>

            <!-- Store Specific Fields -->
            <div id="storeFields" style="display: none; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; margin-top: 1rem;">
                <h4 style="margin-bottom: 1rem; color: var(--primary);">Store Details & Verification</h4>
                <div class="grid grid-2" style="gap: 15px;">
                    <div class="form-group">
                        <label>Store Name</label>
                        <input type="text" name="store_name" class="form-input" placeholder="e.g. Campus Bites">
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact" class="form-input" placeholder="+91 XXX XXX XXXX">
                    </div>
                </div>
                <div class="form-group">
                    <label>Store Location</label>
                    <input type="text" name="location" class="form-input" placeholder="e.g. Block A, Food Court">
                </div>
                <div class="grid grid-2" style="gap: 15px;">
                    <div class="form-group">
                        <label>Aadhar Number</label>
                        <input type="text" name="aadhar_number" class="form-input" placeholder="12-digit UID">
                    </div>
                    <div class="form-group">
                        <label>Store Display Image</label>
                        <input type="file" name="store_image" class="form-input" accept="image/*">
                    </div>
                </div>
                <div class="form-group">
                    <label>Upload Aadhar Image</label>
                    <input type="file" name="aadhar_image" class="form-input" accept="image/*">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 1rem;">
                Get Started
            </button>
        </form>

        <script>
        function toggleStoreFields() {
            var role = document.getElementById('roleSelect').value;
            var storeFields = document.getElementById('storeFields');
            if (role === 'STORE') {
                storeFields.style.display = 'block';
                storeFields.querySelectorAll('input').forEach(input => input.required = true);
            } else {
                storeFields.style.display = 'none';
                storeFields.querySelectorAll('input').forEach(input => input.required = false);
            }
        }
        </script>

        <p style="margin-top: 2rem; text-align: center; color: var(--text-muted);">
            Already have an account? <a href="login.php" style="color: var(--primary); text-decoration: none;">Login here</a>
        </p>
    </div>
</div>

<?php 
// No footer needed for auth or keep it minimal
?>
</body>
</html>
