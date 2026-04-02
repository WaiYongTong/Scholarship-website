<?php
session_start();
include('../config/db.php');

// 1. Universal Security Check
if (!isset($_SESSION['role']) || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['role'];
$message = "";

$custom_admin_id = ($user_role === 'admin') ? "A" . str_pad($user_id, 3, "0", STR_PAD_LEFT) : ucfirst($user_role) . " ID: " . $user_id;

$table_map = [
    'student'   => ['table' => 'student',   'id_col' => 'studentID',   'pwd_col' => 'student_password'],
    'reviewer'  => ['table' => 'reviewer',  'id_col' => 'reviewerID',  'pwd_col' => 'reviewer_password'],
    'committee' => ['table' => 'committee', 'id_col' => 'committeeID', 'pwd_col' => 'committee_password'],
    'admin'     => ['table' => 'admin',     'id_col' => 'adminID',     'pwd_col' => 'admin_password']
];

$db_info = $table_map[$user_role] ?? null;

if (isset($_POST['update_password']) && $db_info) {
    $current_input = $_POST['current_password']; 
    $new_pwd = $_POST['new_password']; 
    $confirm_pwd = $_POST['confirm_password'];

    $table_name = $db_info['table'];
    $id_column = $db_info['id_col'];
    $pwd_column = $db_info['pwd_col'];

    // Securely fetch the stored password
    $stmt = $conn->prepare("SELECT $pwd_column FROM $table_name WHERE $id_column = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $stmt->bind_result($stored_pwd);
    $stmt->fetch();
    $stmt->close();

    if ($new_pwd !== $confirm_pwd) {
        $message = "<div class='alert error'>New passwords do not match.</div>";
    } 
    // Now $current_input is defined and can be used here safely
    elseif (!$stored_pwd || (!password_verify($current_input, $stored_pwd) && $current_input !== $stored_pwd)) {
        $message = "<div class='alert error'>Current password is incorrect.</div>";
    } else {
        // Proceed with hashing and updating
        $hashed_pwd = password_hash($new_pwd, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE $table_name SET $pwd_column = ? WHERE $id_column = ?");
        $update->bind_param("ss", $hashed_pwd, $user_id);
        
        if ($update->execute()) {
            $message = "<div class='alert success'>Password updated and secured!</div>";
        }
        $update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Settings | Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #009ef7; /* Matched to dashboard */
            --dark: #1e1e2d; 
            --bg: #f5f8fa;
            --text-main: #181c32;
            --text-muted: #a1a5b7;
            --sidebar-width: 265px; /* Exact same width as dashboard */
        }
        body { margin: 0; font-family: 'Inter', sans-serif; background-color: var(--bg); display: flex; min-height: 100vh; }
        
        /* SIDEBAR - EXACT MATCH TO DASHBOARD */
        .sidebar { width: 265px; background: var(--dark); height: 100vh; position: fixed; overflow-y: auto; }
        .sidebar-brand { padding: 40px 25px 20px 25px; color: white; font-weight: 700; font-size: 1.1rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu-item { padding: 12px 25px; cursor: pointer; color: #a2a3b7; display: flex; align-items: center; transition: 0.2s; font-size: 0.9rem; }
        .menu-item:hover, .menu-item.active { background: #2b2b40; color: white; }
        .menu-item i { margin-right: 15px; width: 20px; }


        /* MAIN CONTENT AREA */
        .main-content { 
            margin-left: var(--sidebar-width); 
            width: calc(100% - var(--sidebar-width)); 
            flex-grow: 1;
        }

        /* TOP HEADER LOGIC LIKE DASHBOARD */
        .dashboard-header {
            background: transparent;
            padding: 35px 40px 10px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .container { 
            padding: 25px 40px; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 70vh; 
        }

        /* PWD CARD */
        .pwd-card { 
            background: white; padding: 40px; border-radius: 12px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
            width: 100%; max-width: 450px; 
            border: 1px solid #eff2f5;
        }
        .pwd-card h2 { font-size: 1.25rem; margin-bottom: 25px; color: var(--text-main); font-weight: 800; border-bottom: 1px dotted #e4e6ef; padding-bottom: 15px;}
        
        .form-group { margin-bottom: 20px; }
        label { 
            display: block; margin-bottom: 12px; font-weight: 700; 
            color: var(--text-main); font-size: 0.8rem; text-transform: uppercase; 
        }
        
        input { 
            width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid #e4e6ef; 
            background: #f9f9f9; font-size: 0.9rem; box-sizing: border-box; 
        }
        input:focus { outline: none; border-color: var(--primary); background: #fff; }

        .btn-update { 
            width: 100%; padding: 15px; background: var(--primary); color: white; 
            border: none; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.3s;
        }
        .btn-update:hover { opacity: 0.9; transform: translateY(-1px); }

        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-size: 0.9rem; font-weight: 600;}
        .success { background: #e8fff3; color: #50cd89; }
        .error { background: #fff5f8; color: #f1416c; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">Digital Scholarship Application and Tracking System</div>
    
    <div class="menu-item" onclick="window.location.href='dashboard.php'">
        <i class="fas fa-th-large"></i> Back to Dashboard
    </div>

    <div class="menu-item active" onclick="window.location.href='changepwd.php'">
        <i class="fas fa-key"></i> Change Password
    </div>

    <div class="menu-item" style="position: absolute; bottom: 20px; width: 100%; box-sizing: border-box;" onclick="window.location.href='../logout.php'">
        <i class="fas fa-sign-out-alt"></i> Logout
    </div>
</div>

<div class="main-content">
    <header class="dashboard-header">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; margin:0;">Account Security</h1>
            <small style="color: var(--text-muted);">Manage your password settings</small>
        </div>

        <div style="text-align: right;">
            <span style="font-weight: 800; display: block;">ID: <?php echo htmlspecialchars($custom_admin_id); ?></span>
            <small style="color: var(--text-muted);"><?php echo ucfirst($user_role); ?> Account</small>
        </div>
    </header>

    <div class="container">
        <div class="pwd-card">
            <h2><i class="fas fa-lock" style="color: var(--primary);"></i> Change Password</h2>
            
            <?php echo $message; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" placeholder="••••••••" required>
                </div>

                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" placeholder="••••••••" required>
                </div>

                <button type="submit" name="update_password" class="btn-update">UPDATE PASSWORD</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
