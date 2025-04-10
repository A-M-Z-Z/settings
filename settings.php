<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Database Connection
$host = 'localhost';
$user = 'root';
$pass = 'root';
$dbname = 'cloudbox';
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$username = $_SESSION['username'];
$userid = $_SESSION['user_id'];
$messages = [];

// Fetch current user data
$stmt = $conn->prepare("SELECT username, email, full_name, phone, country, bio FROM users WHERE id = ?");
$stmt->bind_param("i", $userid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update Profile Information
    if (isset($_POST['update_profile'])) {
        $fullName = $conn->real_escape_string($_POST['full_name']);
        $email = $conn->real_escape_string($_POST['email']);
        $phone = $conn->real_escape_string($_POST['phone']);
        $country = $conn->real_escape_string($_POST['country']);
        $bio = $conn->real_escape_string($_POST['bio']);
        
        // Check if email is already used by another user
        $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkEmail->bind_param("si", $email, $userid);
        $checkEmail->execute();
        $emailResult = $checkEmail->get_result();
        
        if ($emailResult->num_rows > 0) {
            $messages[] = "<div class='alert alert-danger'>Email address is already in use by another account.</div>";
        } else {
            $updateStmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, country = ?, bio = ? WHERE id = ?");
            $updateStmt->bind_param("sssssi", $fullName, $email, $phone, $country, $bio, $userid);
            
            if ($updateStmt->execute()) {
                $messages[] = "<div class='alert alert-success'>Profile information updated successfully.</div>";
                // Update session data if needed
                $_SESSION['email'] = $email;
                
                // Refresh user data
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $messages[] = "<div class='alert alert-danger'>Error updating profile: " . $conn->error . "</div>";
            }
        }
    }
    
    // Change Username
    if (isset($_POST['change_username'])) {
        $newUsername = $conn->real_escape_string($_POST['new_username']);
        
        // Check if username is already taken
        $checkUsername = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $checkUsername->bind_param("s", $newUsername);
        $checkUsername->execute();
        $usernameResult = $checkUsername->get_result();
        
        if ($usernameResult->num_rows > 0) {
            $messages[] = "<div class='alert alert-danger'>Username is already taken.</div>";
        } else {
            $updateStmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
            $updateStmt->bind_param("si", $newUsername, $userid);
            
            if ($updateStmt->execute()) {
                $messages[] = "<div class='alert alert-success'>Username updated successfully. Please log in again with your new username.</div>";
                $_SESSION['username'] = $newUsername;
                
                // Refresh user data
                $username = $newUsername;
                $stmt = $conn->prepare("SELECT username, email, full_name, phone, country, bio FROM users WHERE id = ?");
                $stmt->bind_param("i", $userid);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $messages[] = "<div class='alert alert-danger'>Error updating username: " . $conn->error . "</div>";
            }
        }
    }
    
    // Change Password
    if (isset($_POST['change_password'])) {
        $currentPassword = $conn->real_escape_string($_POST['current_password']);
        $newPassword = $conn->real_escape_string($_POST['new_password']);
        $confirmPassword = $conn->real_escape_string($_POST['confirm_password']);
        
        // Verify current password
        $checkPassword = $conn->prepare("SELECT id FROM users WHERE id = ? AND password = ?");
        $hashedCurrentPwd = sha1($currentPassword);
        $checkPassword->bind_param("is", $userid, $hashedCurrentPwd);
        $checkPassword->execute();
        $passwordResult = $checkPassword->get_result();
        
        if ($passwordResult->num_rows === 0) {
            $messages[] = "<div class='alert alert-danger'>Current password is incorrect.</div>";
        } elseif ($newPassword !== $confirmPassword) {
            $messages[] = "<div class='alert alert-danger'>New passwords do not match.</div>";
        } elseif (strlen($newPassword) < 6) {
            $messages[] = "<div class='alert alert-danger'>New password must be at least 6 characters long.</div>";
        } else {
            $hashedNewPwd = sha1($newPassword);
            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->bind_param("si", $hashedNewPwd, $userid);
            
            if ($updateStmt->execute()) {
                $messages[] = "<div class='alert alert-success'>Password updated successfully.</div>";
            } else {
                $messages[] = "<div class='alert alert-danger'>Error updating password: " . $conn->error . "</div>";
            }
        }
    }
    
    // Delete Account
    if (isset($_POST['delete_account'])) {
        $confirmDelete = $conn->real_escape_string($_POST['confirm_delete']);
        
        if ($confirmDelete !== $username) {
            $messages[] = "<div class='alert alert-danger'>Username confirmation does not match. Account not deleted.</div>";
        } else {
            // Delete user's files first (to maintain referential integrity)
            $deleteFiles = $conn->prepare("DELETE FROM files WHERE user_id = ?");
            $deleteFiles->bind_param("i", $userid);
            $deleteFiles->execute();
            
            // Then delete the user
            $deleteUser = $conn->prepare("DELETE FROM users WHERE id = ?");
            $deleteUser->bind_param("i", $userid);
            
            if ($deleteUser->execute()) {
                // Clear session and redirect to login
                session_destroy();
                header("Location: index.php?deleted=1");
                exit();
            } else {
                $messages[] = "<div class='alert alert-danger'>Error deleting account: " . $conn->error . "</div>";
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
    <title>CloudBOX - Account Settings</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .top-bar {
            background-color: #4f46e5;
            padding: 15px;
            display: flex;
            align-items: center;
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .logo {
            margin-right: 15px;
        }
        
        .top-bar h1 {
            margin: 0;
            font-size: 22px;
        }
        
        .search-bar {
            margin-left: auto;
        }
        
        .search-bar input {
            border-radius: 20px;
            padding: 8px 15px;
            border: none;
            width: 250px;
        }
        
        .dashboard-nav {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }
        
        .dashboard-nav a {
            color: #4b5563;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 6px;
            transition: background-color 0.2s;
        }
        
        .dashboard-nav a:hover {
            background-color: #f3f4f6;
            color: #4f46e5;
        }
        
        main {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .settings-card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .settings-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
        }
        
        .settings-body {
            padding: 20px;
        }
        
        .danger-zone {
            background-color: #fdedeb;
            border-left: 4px solid #ef4444;
        }
        
        .btn-primary {
            background-color: #4f46e5;
            border-color: #4f46e5;
        }
        
        .btn-primary:hover {
            background-color: #4338ca;
            border-color: #4338ca;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .search-bar input {
                width: 150px;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">
            <img src="logo.png" alt="CloudBOX Logo" height="40">
        </div>
        <h1>CloudBOX</h1>
        <div class="search-bar">
            <input type="text" placeholder="Search files and folders..." class="form-control">
        </div>
    </div>
    
    <nav class="dashboard-nav">
        <a href="home.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="drive.php"><i class="fas fa-folder"></i> My Drive</a>
        <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1): ?>
        <a href="admin.php"><i class="fas fa-crown"></i> Admin Panel</a>
        <?php endif; ?>
        <a href="shared.php"><i class="fas fa-share-alt"></i> Shared Files</a>
        <a href="monitoring.php"><i class="fas fa-chart-line"></i> Monitoring</a>
        <a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <main>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">Account Settings</h1>
            <div>
                <span class="text-muted">Welcome, <?= htmlspecialchars($username) ?>!</span>
            </div>
        </div>
        
        <!-- Display messages -->
        <?php foreach ($messages as $message): ?>
            <?= $message ?>
        <?php endforeach; ?>
        
        <!-- Profile Information -->
        <div class="settings-card">
            <div class="settings-header">
                <i class="fas fa-user-circle me-2"></i> Profile Information
            </div>
            <div class="settings-body">
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="country" class="form-label">Country</label>
                            <select class="form-select" id="country" name="country">
                                <option value="">Select your country</option>
                                <option value="USA" <?= ($user['country'] ?? '') === 'USA' ? 'selected' : '' ?>>USA</option>
                                <option value="FR" <?= ($user['country'] ?? '') === 'FR' ? 'selected' : '' ?>>France</option>
                                <option value="CA" <?= ($user['country'] ?? '') === 'CA' ? 'selected' : '' ?>>Canada</option>
                                <option value="UK" <?= ($user['country'] ?? '') === 'UK' ? 'selected' : '' ?>>UK</option>
                                <!-- Add more countries as needed -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="bio" class="form-label">Bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="3"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
        </div>
        
        <!-- Change Username -->
        <div class="settings-card">
            <div class="settings-header">
                <i class="fas fa-user-tag me-2"></i> Change Username
            </div>
            <div class="settings-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="new_username" class="form-label">New Username</label>
                        <input type="text" class="form-control" id="new_username" name="new_username" required>
                        <div class="form-text">Current username: <?= htmlspecialchars($username) ?></div>
                    </div>
                    <button type="submit" name="change_username" class="btn btn-primary">Change Username</button>
                </form>
            </div>
        </div>
        
        <!-- Change Password -->
        <div class="settings-card">
            <div class="settings-header">
                <i class="fas fa-key me-2"></i> Change Password
            </div>
            <div class="settings-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                </form>
            </div>
        </div>
        
        <!-- Danger Zone -->
        <div class="settings-card danger-zone">
            <div class="settings-header text-danger">
                <i class="fas fa-exclamation-triangle me-2"></i> Danger Zone
            </div>
            <div class="settings-body">
                <h5>Delete Account</h5>
                <p class="text-muted">Once you delete your account, there is no going back. Please be certain.</p>
                
                <form method="POST" action="" class="mt-3">
                    <div class="mb-3">
                        <label for="confirm_delete" class="form-label">To confirm, type your username: <?= htmlspecialchars($username) ?></label>
                        <input type="text" class="form-control" id="confirm_delete" name="confirm_delete" required>
                    </div>
                    <button type="submit" name="delete_account" class="btn btn-danger">I understand, delete my account</button>
                </form>
            </div>
        </div>
    </main>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
