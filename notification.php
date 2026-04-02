<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('../config/db.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

$student_id = $_SESSION['user_id'];

//Mark all as read immediately
mysqli_query($conn, "UPDATE notification SET isRead = 1 WHERE studentID = '$student_id'");

//Fetch all notifications
$all_notifs = mysqli_query($conn, "SELECT * FROM notification WHERE studentID = '$student_id' ORDER BY notificationID DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Notifications | Digital Scholarship</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f8fa; padding: 40px; margin: 0; }
        .container { max-width: 800px; margin: 0 auto; }
        .back-btn { text-decoration: none; color: #009ef7; font-weight: bold; display: inline-block; margin-bottom: 20px; transition: 0.3s; }
        .back-btn:hover { color: #008be0; }
        
        .card { background: white; padding: 10px; border-radius: 12px; box-shadow: 0 0 20px rgba(0,0,0,0.05); border: 1px solid #eff2f5; }
        .card-header { padding: 15px 20px; border-bottom: 1px solid #f1f1f4; font-weight: 800; font-size: 1.2rem; color: #1e1e2d; }
        
        .notif-row { padding: 20px; border-bottom: 1px solid #f1f1f4; display: flex; gap: 15px; align-items: start; transition: background 0.2s; }
        .notif-row:last-child { border-bottom: none; }
        .notif-row:hover { background: #fafafa; }
        
        .icon-box { width: 45px; height: 45px; background: #e8fff3; color: #50cd89; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .msg-content { flex-grow: 1; }
        .msg-text { color: #3f4254; font-size: 0.95rem; line-height: 1.5; margin: 0; }
        /* If your DB table has a timestamp column, you can show the date here */
        .msg-date { color: #a1a5b7; font-size: 0.8rem; display: block; margin-top: 8px; }
        
        .empty-state { text-align: center; padding: 40px; color: #a1a5b7; }
    </style>
</head>
<body>

<div class="container">
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
    
    <div class="card">
        <div class="card-header">
            <i class="fas fa-bell" style="color: #009ef7; margin-right: 10px;"></i> All Notifications
        </div>

        <?php if ($all_notifs && mysqli_num_rows($all_notifs) > 0): ?>
            <?php while($row = mysqli_fetch_assoc($all_notifs)): ?>
                <div class="notif-row">
                    <div class="icon-box">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="msg-content">
                        <p class="msg-text"><?php echo htmlspecialchars($row['message']); ?></p>
                        <span class="msg-date"><i class="far fa-clock"></i> Notification Received</span>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-envelope-open" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                <p>You don't have any notifications yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>