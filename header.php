<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('config/db.php'); 

$unread_count = 0;
$notif_list = null;

if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'student') {
    $student_id = $_SESSION['user_id'];
    
    //Notifications Logic
    //Get the unread count
    $count_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM notification WHERE studentID = '$student_id' AND isRead = 0");
    if ($count_res) {
        $unread_count = mysqli_fetch_assoc($count_res)['total'];
    }

    //Get the latest 5 messages for the dropdown
    $notif_list = mysqli_query($conn, "SELECT message FROM notification WHERE studentID = '$student_id' ORDER BY notificationID DESC LIMIT 5");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : "Digital Scholarship Application and Tracking System"; ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>

        :root {
                --primary: #009ef7; 
                --dark: #1e1e2d; 
                --bg: #f5f8fa; 
                --text-main: #181c32; 
                --text-muted: #a1a5b7;
                --success-bg: #e8fff3; 
                --success-text: #50cd89;
                --warning-bg: #fff8dd; 
                --warning-text: #ffc700;
                --danger-bg: #fff5f8;  
                --danger-text: #f1416c;
                --sidebar-width: 265px;
            }

        body { margin: 0; font-family: Arial, sans-serif; background-color: #f8fbff; }
        
        nav { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 15px 50px; 
            background: #1e1e2d; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.2); 
        }
        
        .logo { font-weight: bold; font-size: 18px; color: #ffffff; letter-spacing: 0.5px; }
        nav ul { list-style: none; display: flex; margin: 0; align-items: center; }
        nav ul li { margin-left: 25px; }
        nav ul li a { text-decoration: none; color: rgba(255, 255, 255, 0.9); font-weight: 500; transition: 0.3s; }
        nav ul li a:hover { color: #009ef7; } 

        .btn-apply { background: #009ef7 !important; color: #ffffff !important; padding: 10px 25px; border-radius: 5px; font-weight: bold; transition: 0.3s; }
        .btn-apply:hover { background: #008be0 !important; }

            .notif-wrapper {
                position: relative;
                display: inline-block;
            }

            .notif-link {
                cursor: pointer;
                padding: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .notif-dropdown {
                display: none;
                position: absolute;
                right: 0;
                top: 100%;
                width: 280px;
                background: white;
                border: 1px solid #eff2f5;
                box-shadow: 0 10px 20px rgba(0,0,0,0.1);
                z-index: 1000;
                border-radius: 8px;
                overflow: hidden;
            }

            .notif-wrapper:hover .notif-dropdown {
                display: block;
            }

            .notif-item {
                padding: 15px;
                border-bottom: 1px solid #f1f1f4;
                font-size: 0.85rem;
                color: var(--text-main);
                line-height: 1.4;
                text-align: left;
                background: white;
                transition: background 0.2s;
            }

            .notif-item:hover {
                background: #f9f9f9;
            }

            .notif-item:last-child { border-bottom: none; }

            .notif-wrapper .badge {
                position: absolute;
                top: 2px;
                right: 2px;
                background: #f1416c;
                color: white;
                font-size: 10px;
                padding: 2px 6px;
                border-radius: 20px;
                font-weight: bold;
                border: 2px solid white;
            }

        .badge {
            position: absolute;
            top: -5px;
            right: -8px;
            background-color: #f1416c;
            color: white;
            font-size: 10px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 50%;
            border: 2px solid #1e1e2d;
        }
    </style>
</head>

<body>
<nav>
    <div class="logo">Digital Scholarship Application and Tracking System</div>
    <ul>
        <li><a href="index.php">Home</a></li>
        <li><a href="schemes.php">Schemes</a></li>

        <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'student'): ?>
            <li><a href="student/dashboard.php">Dashboard</a></li>

            <li><a href="logout.php">Logout</a></li>

            <li>
                <div class="notif-wrapper">
                    <div class="notif-link">
                        <i class="fas fa-bell" style="font-size: 1.2rem; color: #ffffff;"></i>
                        <?php if($unread_count > 0): ?>
                            <span class="badge" style="background: var(--danger-text); border: 2px solid white;"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="notif-dropdown" style="box-shadow: 0 10px 40px rgba(0,0,0,0.1); border: none;">
                        <div style="padding: 15px; font-weight: 800; border-bottom: 2px solid #009ef7; color: var(--text-main); background: #fff;">
                            Notifications
                        </div>
                        
                        <div style="max-height: 250px; overflow-y: auto;">
                            <?php 
                            if($notif_list && mysqli_num_rows($notif_list) > 0):
                                mysqli_data_seek($notif_list, 0); 
                                while($row = mysqli_fetch_assoc($notif_list)): ?>
                                    <div class="notif-item" style="border-left: 3px solid transparent; transition: 0.3s;">
                                        <div style="display: flex; gap: 10px;">
                                            <i class="fas fa-circle" style="color: var(--primary); font-size: 0.5rem; margin-top: 5px;"></i>
                                            <span><?php echo htmlspecialchars($row['message']); ?></span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div style="padding: 30px 15px; text-align: center; color: var(--text-muted);">
                                    <i class="fas fa-bell-slash" style="display: block; font-size: 1.5rem; margin-bottom: 10px; opacity: 0.3;"></i>
                                    No new notifications
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <a href="student/notification.php" style="display: block; padding: 12px; text-align: center; font-size: 0.75rem; color: var(--primary); text-decoration: none; font-weight: 700; background: #f9f9f9; border-top: 1px solid #f1f1f4;">
                            View All Notifications
                        </a>
                    </div>
                </div>
            </li>
        <?php else: ?>
            <li><a href="login.php">Login</a></li>
            <li><a href="register.php" class="btn-apply">Apply Now!</a></li>
        <?php endif; ?>
    </ul>
</nav>