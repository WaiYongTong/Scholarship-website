<?php
session_start();
include('config/db.php');

// Redirect if they haven't verified through forgot_password.php first
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];
$message = "";

if (isset($_POST['update'])) {
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if ($new_pass !== $confirm_pass) {
        $message = "<div style='color:red; margin-bottom: 15px;'>Passwords do not match!</div>";
    } else {
        $email_parts = explode('@', $email);
        $domain = end($email_parts);

        // Update logic based on your existing database structure
        if ($domain === 'student.edu.my') {
            $final_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $sql = "UPDATE student SET student_password = '$final_pass' WHERE student_email = '$email'";
        } elseif ($domain === 'committee.edu.my') {
            $sql = "UPDATE committee SET committee_password = '$new_pass' WHERE committee_email = '$email'";
        } elseif ($domain === 'reviewer.edu.my') {
            $sql = "UPDATE reviewer SET reviewer_password = '$new_pass' WHERE reviewer_email = '$email'";
        } elseif ($domain === 'admin.edu.my') {
            $sql = "UPDATE admin SET admin_password = '$new_pass' WHERE admin_email = '$email'";
        }

        if (mysqli_query($conn, $sql)) {
            unset($_SESSION['reset_email']); 
            echo "<script>alert('Password updated successfully!'); window.location='login.php';</script>";
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password | DSATS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --primary: #009ef7; --bg: #f5f8fa; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); width: 100%; max-width: 380px; }
        .logo-section { text-align: center; margin-bottom: 30px; }
        .logo-section i { font-size: 40px; color: var(--primary); margin-bottom: 10px; }
        h2 { margin: 0; color: #181c32; font-size: 24px; }
        p { color: #a1a5b7; font-size: 14px; margin-top: 5px; }
        label { display: block; margin-top: 20px; font-weight: 500; font-size: 13px; color: #3f4254; }
        input { width: 100%; padding: 12px; margin-top: 8px; border: 1px solid #e1e3ea; border-radius: 6px; box-sizing: border-box; background: #f9fafb; }
        input:focus { outline: none; border-color: var(--primary); background: #fff; }
        button { width: 100%; padding: 13px; background: var(--primary); color: white; border: none; border-radius: 6px; cursor: pointer; margin-top: 25px; font-weight: 600; font-size: 15px; transition: 0.3s; }
        button:hover { background: #0082cc; }
        .email-display { background: #eef3f7; padding: 10px; border-radius: 6px; font-size: 13px; color: #3f4254; text-align: center; margin-top: 10px; border: 1px dashed #009ef7; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="logo-section">
        <i class="fas fa-shield-alt"></i>
        <h2>Set New Password</h2>
        <p>Create a secure password for your account</p>
        <div class="email-display">
            <strong>Account:</strong> <?php echo htmlspecialchars($email); ?>
        </div>
    </div>

    <?php echo $message; ?>

    <form method="POST">
        <label><i class="fas fa-lock"></i> New Password</label>
        <input type="password" name="new_password" required placeholder="Min. 8 characters">
        
        <label><i class="fas fa-check-circle"></i> Confirm New Password</label>
        <input type="password" name="confirm_password" required placeholder="Repeat password">
        
        <button type="submit" name="update">Update Password</button>
    </form>
</div>

</body>
</html>