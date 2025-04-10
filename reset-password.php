<?php
session_start();
// Database Connection
$host = 'localhost';
$user = 'root';
$pass = 'root';
$dbname = 'cloudbox';
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);

$message = "";
$email = isset($_GET['email']) ? $_GET['email'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_otp'])) {
        $email = $conn->real_escape_string($_POST['email']);
        $otp = $conn->real_escape_string($_POST['otp']);
        
        // Verify OTP
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_code = ? AND reset_expires > NOW()");
        $stmt->bind_param("ss", $email, $otp);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_verified'] = true;
            $message = "<div class='alert alert-success'>Code verified successfully. You can now reset your password.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Invalid or expired verification code. Please try again.</div>";
        }
    }
    
    if (isset($_POST['reset_password']) && isset($_SESSION['reset_verified']) && $_SESSION['reset_verified']) {
        $password = $conn->real_escape_string($_POST['password']);
        $confirm_password = $conn->real_escape_string($_POST['confirm_password']);
        
        if ($password !== $confirm_password) {
            $message = "<div class='alert alert-danger'>Passwords do not match.</div>";
        } elseif (strlen($password) < 6) {
            $message = "<div class='alert alert-danger'>Password must be at least 6 characters long.</div>";
        } else {
            $hashedPassword = sha1($password);
            $userId = $_SESSION['reset_user_id'];
            
            $stmt = $conn->prepare("UPDATE users SET password = ?, reset_code = NULL, reset_expires = NULL WHERE id = ?");
            $stmt->bind_param("si", $hashedPassword, $userId);
            
            if ($stmt->execute()) {
                // Clear reset session
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_verified']);
                
                $message = "<div class='alert alert-success'>Password has been reset successfully. You can now <a href='index.php'>login</a> with your new password.</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error resetting password. Please try again.</div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CloudBOX - Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style-login.css">
    <style>
        .form-container {
            max-width: 450px;
            margin: 100px auto;
            padding: 25px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .form-container h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #4f46e5;
        }
        
        .alert {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <?php if ($message): ?>
            <?= $message ?>
        <?php endif; ?>
        
        <h2><i class="fas fa-key me-2"></i>Reset Password</h2>
        
        <?php if (!isset($_SESSION['reset_verified']) || !$_SESSION['reset_verified']): ?>
            <!-- OTP Verification Form -->
            <p class="text-center mb-4">Enter the verification code sent to your email.</p>
            <form method="POST">
                <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                <div class="mb-3">
                    <label for="otp" class="form-label">Verification Code</label>
                    <input type="text" class="form-control" id="otp" name="otp" required>
                </div>
                <div class="d-grid">
                    <button type="submit" name="verify_otp" class="btn btn-primary">Verify Code</button>
                </div>
            </form>
        <?php else: ?>
            <!-- New Password Form -->
            <p class="text-center mb-4">Enter your new password.</p>
            <form method="POST">
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" name="reset_password" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        <?php endif; ?>
        
        <div class="mt-3 text-center">
            <a href="index.php" class="text-decoration-none">Back to Login</a>
        </div>
    </div>
</body>
</html>
