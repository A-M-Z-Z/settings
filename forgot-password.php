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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = $conn->real_escape_string($_POST['email']);
    
    // Check if email exists in database
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userId = $user['id'];
        $username = $user['username'];
        
        // Generate OTP code
        $otp = rand(100000, 999999);
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        // Save OTP to database
        $stmt = $conn->prepare("UPDATE users SET reset_code = ?, reset_expires = ? WHERE id = ?");
        $stmt->bind_param("ssi", $otp, $expires, $userId);
        $stmt->execute();
        
        // Send email with OTP
        $subject = "CloudBOX Password Reset";
        $message_body = "Hello $username,\n\n";
        $message_body .= "You have requested to reset your password. Your verification code is: $otp\n\n";
        $message_body .= "This code will expire in 15 minutes.\n\n";
        $message_body .= "If you did not request a password reset, please ignore this email.";
        
        $headers = "From: no-reply@cloudbox.com";
        
        if (mail($email, $subject, $message_body, $headers)) {
            $message = "<div class='alert alert-success'>A verification code has been sent to your email address. Please check your inbox.</div>";
            
            // Redirect to reset password page
            header("Location: reset-password.php?email=" . urlencode($email));
            exit;
        } else {
            $message = "<div class='alert alert-danger'>Failed to send verification email. Please try again later.</div>";
        }
    } else {
        // Don't reveal that email doesn't exist for security
        $message = "<div class='alert alert-info'>If your email address exists in our database, you will receive a password recovery link shortly.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CloudBOX - Forgot Password</title>
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
        
        <h2><i class="fas fa-lock-open me-2"></i>Forgot Password</h2>
        <p class="text-center mb-4">Enter your email address and we'll send you a verification code to reset your password.</p>
        
        <form method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Send Verification Code</button>
            </div>
        </form>
        
        <div class="mt-3 text-center">
            <a href="index.php" class="text-decoration-none">Back to Login</a>
        </div>
    </div>
</body>
</html>
