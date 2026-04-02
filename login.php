<?php
session_start();
include('config/db.php');

$message = "";
$email = "";

if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Get the domain
    $email_parts = explode('@', $email);
    $domain = end($email_parts);

    //Student Check
    if ($domain === 'student.edu.my') {
        $sql = "SELECT * FROM student WHERE student_email = '$email' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if ($row = mysqli_fetch_assoc($res)) {
            $db_pass = $row['student_password'];
            
            if (password_verify($password, $db_pass) || $password === $db_pass) {
                $_SESSION['user_id'] = $row['studentID'];
                $_SESSION['user_name'] = $row['student_name'];
                $_SESSION['role'] = 'student';
                header("Location: student/dashboard.php");
                exit();
            } else { $message = "<div class='alert error'>Wrong Student Password!</div>"; }
        } else { $message = "<div class='alert error'>Student account not found!</div>"; }
    }
    
    //Committee Check
    elseif ($domain === 'committee.edu.my') {
        $sql = "SELECT * FROM committee WHERE committee_email = '$email' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if ($row = mysqli_fetch_assoc($res)) {
            $db_pass = $row['committee_password'];

            if (password_verify($password, $db_pass) || $password === $db_pass) {
                $_SESSION['user_id'] = $row['committeeID'];
                $_SESSION['user_name'] = $row['committee_name'];
                $_SESSION['role'] = 'committee';
                header("Location: committee/dashboard.php");
                exit();
            } else { $message = "<div class='alert error'>Wrong Committee Password!</div>"; }
        } else { $message = "<div class='alert error'>Committee account not found!</div>"; }
    }

    //Reviewer Check
    elseif ($domain === 'reviewer.edu.my') {
        $sql = "SELECT * FROM reviewer WHERE reviewer_email = '$email' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if ($row = mysqli_fetch_assoc($res)) {
            $db_pass = $row['reviewer_password'];

            if (password_verify($password, $db_pass) || $password === $db_pass) {
                $_SESSION['user_id'] = $row['reviewerID'];
                $_SESSION['user_name'] = $row['reviewer_name'];
                $_SESSION['role'] = 'reviewer';
                header("Location: reviewer/dashboard.php");
                exit();
            } else { $message = "<div class='alert error'>Wrong Reviewer Password!</div>"; }
        } else { $message = "<div class='alert error'>Reviewer account not found!</div>"; }
    }

    //Admin Check
    elseif ($domain === 'admin.edu.my') {
        $sql = "SELECT * FROM admin WHERE admin_email = '$email' LIMIT 1";
        $res = mysqli_query($conn, $sql);
        if ($row = mysqli_fetch_assoc($res)) {
            $db_pass = $row['admin_password'];

            if (password_verify($password, $db_pass) || $password === $db_pass) {
                $_SESSION['user_id'] = $row['adminID'];
                $_SESSION['user_name'] = $row['admin_name'];
                $_SESSION['role'] = 'admin';
                header("Location: admin/dashboard.php");
                exit();
            } else { $message = "<div class='alert error'>Wrong Admin Password!</div>"; }
        } else { $message = "<div class='alert error'>Admin account not found!</div>"; }
    }
    
    //If invalid, then display error
    else {
        $message = "<div class='alert error'>Please use an authorized institutional email.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Digital Scholarship Application and Tracking System</title>
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
        .home-link { text-align: center; margin-top: 20px; }
        .home-link a { color: #7e8299; text-decoration: none; font-size: 13px; transition: 0.2s; }
        .home-link a:hover { color: var(--primary); }
        .alert { padding: 10px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; text-align: center; }
        .error { background: #fff5f8; color: #f1416c; border: 1px solid #fac1cf; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="logo-section">
        <i class="fas fa-graduation-cap"></i>
        <h2>Welcome Back</h2>
        <p>Login with your institutional email</p>
    </div>

    <?php echo $message; ?>

    <form method="POST">
        <label><i class="fas fa-envelope"></i> Email Address</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="name@student.edu.my">
        
        <label><i class="fas fa-lock"></i> Password</label>
        <input type="password" name="password" required placeholder="••••••••">

        <div style="text-align: right; margin-top: 5px;">
            <a href="forgot_password.php" style="font-size: 13px; color: var(--primary); text-decoration: none;">Forgot Password?</a>
        </div>

        <button type="submit" name="login">Sign In</button>
    </form>

    <div class="home-link">
        <a href="index.php"><i class="fas fa-arrow-left"></i> Back to Home</a>
    </div>
</div>

</body>
</html>