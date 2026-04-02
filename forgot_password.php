<?php
session_start();
include('config/db.php');
$message = "";

if (isset($_POST['verify'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $user_id = mysqli_real_escape_string($conn, $_POST['id_number']);
    
    $email_parts = explode('@', $email);
    $domain = end($email_parts);

    // Map domains to tables and ID columns
    $map = [
        'student.edu.my' => ['table' => 'student', 'col_email' => 'student_email', 'col_id' => 'studentID'],
        'committee.edu.my' => ['table' => 'committee', 'col_email' => 'committee_email', 'col_id' => 'committeeID'],
        'reviewer.edu.my' => ['table' => 'reviewer', 'col_email' => 'reviewer_email', 'col_id' => 'reviewerID'],
        'admin.edu.my' => ['table' => 'admin', 'col_email' => 'admin_email', 'col_id' => 'adminID']
    ];

    if (isset($map[$domain])) {
        $data = $map[$domain];
        $sql = "SELECT * FROM {$data['table']} WHERE {$data['col_email']} = '$email' AND {$data['col_id']} = '$user_id' LIMIT 1";
        $res = mysqli_query($conn, $sql);

        if (mysqli_num_rows($res) > 0) {
            // Success: Carry the email over to the reset page via Session
            $_SESSION['reset_email'] = $email;
            header("Location: reset_password.php");
            exit();
        } else {
            $message = "<div style='color:red;'>Details do not match our records!</div>";
        }
    } else {
        $message = "<div style='color:red;'>Invalid institutional email domain.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Use the same CSS from your login.php for consistency */
        :root { --primary: #009ef7; --bg: #f5f8fa; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); width: 100%; max-width: 380px; }
        input { width: 100%; padding: 12px; margin-top: 8px; border: 1px solid #e1e3ea; border-radius: 6px; box-sizing: border-box; background: #f9fafb; margin-bottom: 15px; }
        button { width: 100%; padding: 13px; background: var(--primary); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
    </style>
</head>
<body>
<div class="login-card">
    <h2 style="text-align:center">Forgot Password</h2>
    <p style="text-align:center; color:#7e8299;">Verify your ID to reset password</p>
    <?php echo $message; ?>
    <form method="POST">
        <label>Email Address</label>
        <input type="email" name="email" required placeholder="name@student.edu.my">
        
        <label>Your ID Number (e.g. Student ID)</label>
        <input type="text" name="id_number" required placeholder="Enter your ID">
        
        <button type="submit" name="verify">Verify Account</button>
    </form>
    <div style="text-align:center; margin-top:15px;"><a href="login.php" style="text-decoration:none; color: #7e8299;">Back to Login</a></div>
</div>
</body>
</html>