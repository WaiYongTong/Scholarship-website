<?php
session_start();
include('../config/db.php');

// Permission check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$status_check_sql = "SELECT status FROM admin WHERE adminID = '$current_user_id' LIMIT 1";
$status_res = mysqli_query($conn, $status_check_sql);
$user_data = mysqli_fetch_assoc($status_res);

$adminID_raw = $_SESSION['user_id']; 
$adminName   = $_SESSION['user_name'];

$adminID = (is_numeric($_SESSION['user_id'])) 
    ? "A" . str_pad($_SESSION['user_id'], 3, "0", STR_PAD_LEFT) 
    : $_SESSION['user_id'];

$users_sql = "
    (SELECT CONCAT('S', LPAD(studentID, 3, '0')) as id, student_name as name, student_email as email, 'student' as role, status FROM student)
    UNION
    (SELECT CONCAT('R', LPAD(reviewerID, 3, '0')) as id, reviewer_name as name, reviewer_email as email, 'reviewer' as role, status FROM reviewer)
    UNION
    (SELECT CONCAT('C', LPAD(committeeID, 3, '0')) as id, committee_name as name, committee_email as email, 'committee' as role, status FROM committee)
    UNION
    (SELECT CONCAT('A', LPAD(adminID, 3, '0')) as id, admin_name as name, admin_email as email, 'admin' as role, status FROM admin)
";

$users_result = mysqli_query($conn, $users_sql);
$users_list = [];
if ($users_result) {
    while ($row = mysqli_fetch_assoc($users_result)) {
        $users_list[] = $row;
    }
}

$current_date_today = date('Y-m-d');

$auto_close_sql = "UPDATE scholarship 
                   SET scholarship_status = 'Closed' 
                   WHERE close_date < '$current_date_today' 
                   AND scholarship_status = 'Open'";
mysqli_query($conn, $auto_close_sql);

// Panel 1: System Overview
$res_students = mysqli_query($conn, "SELECT COUNT(*) as total FROM student");
$total_students = mysqli_fetch_assoc($res_students)['total'] ?? 0;

$res_apps = mysqli_query($conn, "SELECT COUNT(*) as total FROM application");
$total_applications = mysqli_fetch_assoc($res_apps)['total'] ?? 0;

$res_pending = mysqli_query($conn, "SELECT COUNT(*) as total FROM application WHERE application_status = 'Pending'");
$pending_reviews = mysqli_fetch_assoc($res_pending)['total'] ?? 0;

$res_awarded = mysqli_query($conn, "SELECT COUNT(*) as total FROM application WHERE application_status = 'Approved'");
$scholarships_awarded = mysqli_fetch_assoc($res_awarded)['total'] ?? 0;

$res_completed = mysqli_query($conn, "SELECT COUNT(*) as total FROM review WHERE review_status = 'Completed'");
$completed_reviews = mysqli_fetch_assoc($res_completed)['total'] ?? 0;

function statusBadgeClass($text) {
    $t = strtolower(trim((string)$text));
    if ($t === 'active' || $t === 'open') return 'status-active';
    if ($t === 'inactive' || $t === 'closed') return 'status-inactive';
    return 'status-pending';
}

function roleBadgeClass($text) {
    $t = strtolower(trim((string)$text));
    if ($t === 'admin') return 'role-admin';
    if ($t === 'reviewer') return 'role-reviewer';
    if ($t === 'committee') return 'role-committee';
    return 'role-student';
}

function pick($arr, $keys, $default = '') {
    if (!is_array($arr)) return $default;
    foreach ($keys as $k) {
        if (array_key_exists($k, $arr) && $arr[$k] !== null) return $arr[$k];
    }
    return $default;
}

$profile = [];
$profile_sql = "
    SELECT *
    FROM admin
    WHERE adminID = '" . mysqli_real_escape_string($conn, (string)$adminID) . "'
       OR adminID = '" . mysqli_real_escape_string($conn, (string)$adminID_raw) . "'
    LIMIT 1
";
$profile_result = mysqli_query($conn, $profile_sql);
if ($profile_result) {
    $profile = mysqli_fetch_assoc($profile_result) ?? [];
}

$admin_fullname = pick($profile, ['adminName','admin_name','name','fullName'], $adminName);
$admin_email    = pick($profile, ['adminEmail','admin_email','email'], '');
$admin_phone    = pick($profile, ['adminPhone','admin_phone','admin_phonenum','phone','phoneNumber'], '');
$admin_address  = pick($profile, ['adminAddress','admin_address','address'], '');

// Add User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $role = $_POST['user_type']; 
    $name = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $pwd = $_POST['password']; 
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);

    // Determine Table and ID column
    if ($role == 'admin') {
        $table = 'admin';
        $id_col = 'adminID';
        $db_role = 'Admin';
    } else if ($role == 'committee') { 
        $table = 'committee';
        $id_col = 'committeeID';
        $db_role = 'Committee';
    } else { 
        $table = 'reviewer';
        $id_col = 'reviewerID';
        $db_role = 'Reviewer';
    }

    // Auto-generate Unique ID
    $id_query = mysqli_query($conn, "SELECT MAX($id_col) as max_id FROM $table");
    $id_row = mysqli_fetch_assoc($id_query);
    $manual_id = ($id_row['max_id']) ? $id_row['max_id'] + 1 : 101; // Starts at 101 if table is empty

    // Prepare Column Names
    $name_col = ($role == 'admin') ? 'admin_name' : (($role == 'committee') ? 'committee_name' : 'reviewer_name');
    $email_col = ($role == 'admin') ? 'admin_email' : (($role == 'committee') ? 'committee_email' : 'reviewer_email');
    $pwd_col = ($role == 'admin') ? 'admin_password' : (($role == 'committee') ? 'committee_password' : 'reviewer_password');
    $phone_col = ($role == 'admin') ? 'admin_phonenum' : (($role == 'committee') ? 'committee_phonenum' : 'reviewer_phonenum');
    $addr_col = ($role == 'admin') ? 'admin_address' : (($role == 'committee') ? 'committee_address' : 'reviewer_address');

$sql = "INSERT INTO $table ($id_col, $name_col, $email_col, $pwd_col, $phone_col, $addr_col, role, status) 
            VALUES ('$manual_id', '$name', '$email', '$pwd', '$phone', '$address', '$db_role', 'active')";
    
    if (mysqli_query($conn, $sql)) {
        $formatted_id = str_pad($manual_id, 3, "0", STR_PAD_LEFT);
        echo "<script>alert('User added successfully! Generated ID: $formatted_id'); window.location.href='dashboard.php?tab=users';</script>";
        exit();
        } else {
        $error_msg = mysqli_error($conn);
        echo "<script>alert('Database Error: $error_msg');</script>";
    }
}

if ($user_data && strtolower($user_data['status']) !== 'active') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Account Deactivated</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            :root { 
                --primary: #009ef7; --bg: #f5f8fa; 
                --text-main: #181c32; --text-muted: #a1a5b7;
                --danger-text: #f1416c; --danger-bg: #fff5f8;
            }
            body { 
                margin: 0; font-family: 'Inter', sans-serif; 
                background: var(--bg); display: flex; 
                justify-content: center; align-items: center; 
                height: 100vh; color: var(--text-main);
            }
            .deactivated-card { 
                background: white; width: 100%; max-width: 480px; 
                padding: 50px 40px; border-radius: 12px; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
                text-align: center; border: 1px solid #eff2f5;
            }
            .icon-wrapper {
                width: 80px; height: 80px; background: var(--danger-bg);
                color: var(--danger-text); border-radius: 50%;
                display: flex; align-items: center; justify-content: center;
                margin: 0 auto 25px; font-size: 2rem;
            }
            .deactivated-card h1 { 
                color: var(--danger-text); font-size: 1.8rem;
                font-weight: 800; margin-bottom: 15px;
            }
            .deactivated-card p { 
                color: #5e6278; line-height: 1.6; 
                font-size: 1rem; margin-bottom: 30px;
            }
            .deactivated-card strong { color: var(--text-main); }
            .btn-return { 
                display: inline-block; padding: 14px 32px; 
                background: var(--primary); color: white; 
                text-decoration: none; border-radius: 8px; 
                font-weight: 700; transition: background 0.2s;
                font-size: 0.9rem;
            }
            .btn-return:hover { background: #0086d1; }
        </style>
    </head>
    <body>
        <div class="deactivated-card">
            <div class="icon-wrapper">
                <i class="fas fa-user-slash"></i>
            </div>
            <h1>Account Deactivated</h1>
            <p>
                Your account has been deactivated by the system administrator. 
                You no longer have access to the system.
                <br><br>
                Please contact the system admin for further assistance.
            </p>
            <a href="../logout.php" class="btn-return">Return to Login</a>
        </div>
    </body>
    </html>
    <?php
    exit(); 
}

// Edit Scholarship
if (isset($_POST['action']) && $_POST['action'] == 'edit_scholarship') {
    $id = $_POST['edit_scholarship_id'];
    $name = mysqli_real_escape_string($conn, $_POST['edit_s_name']);
    $desc = mysqli_real_escape_string($conn, $_POST['edit_s_description']);
    $deadline = $_POST['edit_s_deadline'];
    $status = $_POST['edit_s_status'];

    $sql = "UPDATE scholarship SET scholarship_name='$name', description='$desc', close_date='$deadline', scholarship_status='$status' WHERE scholarshipID='$id'";
    
    if (mysqli_query($conn, $sql)) {
        header("Location: dashboard.php?tab=scholarships&msg=updated");
    }
}

// Add Scholarship
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_scholarship') {
    $s_name = mysqli_real_escape_string($conn, $_POST['s_name']);
    $s_desc = mysqli_real_escape_string($conn, $_POST['s_description']); 
    $s_start = mysqli_real_escape_string($conn, $_POST['s_start_date']);
    $s_deadline = mysqli_real_escape_string($conn, $_POST['s_deadline']);
    $s_status = mysqli_real_escape_string($conn, $_POST['s_status']);

    $current_today = date('Y-m-d');
    $alert_message = "Scholarship Program created successfully!";
    
    if ($s_deadline < $current_today) {
        $s_status = 'Closed'; 
        $alert_message = "Notice: The selected Close Date is in the past. The scholarship will appear as Closed immediately upon creation.";
    }

    $sql = "INSERT INTO scholarship (scholarship_name, description, scholarship_status, open_date, close_date) 
            VALUES ('$s_name', '$s_desc', '$s_status', '$s_start', '$s_deadline')";

    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('$alert_message'); window.location.href='dashboard.php?tab=scholarships';</script>";
        exit();
    }
}

// Process Assign Reviewers
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'assign_reviewer_action') {
    $application_id = mysqli_real_escape_string($conn, $_POST['application_id']);
    $selected_reviewers = $_POST['reviewers'] ?? []; 

    if (empty($application_id) || empty($selected_reviewers)) {
        echo "<script>alert('Error: Please select an application and check at least one reviewer.'); window.location.href='dashboard.php?tab=assign';</script>";
    } else {
        $success_count = 0;
        
        foreach ($selected_reviewers as $r_id) {
            $r_id = mysqli_real_escape_string($conn, $r_id);
            $current_date = date('Y-m-d');
            
            $check = mysqli_query($conn, "SELECT * FROM reviewerassignment WHERE applicationID = '$application_id' AND reviewerID = '$r_id'");
            
            if (mysqli_num_rows($check) == 0) {
                $ins = "INSERT INTO reviewerassignment (reviewerID, applicationID, assignedDate) 
                        VALUES ('$r_id', '$application_id', '$current_date')";
                if (mysqli_query($conn, $ins)) {
                    $success_count++;
                }
            }
        }
        
        if ($success_count > 0) {
            echo "<script>alert('Success: Reviewer(s) assigned to student application.'); window.location.href='dashboard.php?tab=assign';</script>";
        } else {
            echo "<script>alert('No new assignments made. Reviewer might already be assigned to this student.'); window.location.href='dashboard.php?tab=assign';</script>";
        }
        exit();
    }
}

// Edit User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_user') {
    $target_id_raw = mysqli_real_escape_string($conn, $_POST['edit_userid']);
    $new_name = mysqli_real_escape_string($conn, $_POST['edit_fullname']);
    
    $new_status = isset($_POST['edit_status']) ? mysqli_real_escape_string($conn, $_POST['edit_status']) : 'active'; 
 
    $numeric_id = (int)preg_replace('/[^0-9]/', '', $target_id_raw);

    if (strpos($target_id_raw, 'S') === 0) {
        $table = 'student'; $id_col = 'studentID'; $name_col = 'student_name';
    } elseif (strpos($target_id_raw, 'C') === 0) {
        $table = 'committee'; $id_col = 'committeeID'; $name_col = 'committee_name';
    } elseif (strpos($target_id_raw, 'A') === 0) {
        $table = 'admin'; $id_col = 'adminID'; $name_col = 'admin_name';
    } else {
        $table = 'reviewer'; $id_col = 'reviewerID'; $name_col = 'reviewer_name';
    }

    $update_sql = "UPDATE $table SET $name_col = '$new_name', status = '$new_status' WHERE $id_col = $numeric_id";
    
    if (mysqli_query($conn, $update_sql)) {
        echo "<script>alert('User updated successfully!'); window.location.href='dashboard.php?tab=users';</script>";
        exit();
    } else {
        echo "<script>alert('Error updating user: " . mysqli_error($conn) . "');</script>";
    }
}

// Delete User
if (isset($_GET['delete_id'])) {
    $del_id_raw = mysqli_real_escape_string($conn, $_GET['delete_id']);
    $numeric_id = (int)preg_replace('/[^0-9]/', '', $del_id_raw);

    if (strpos($del_id_raw, 'A') === 0) {
        $del_sql = "DELETE FROM admin WHERE adminID = $numeric_id";
    } else if (strpos($del_id_raw, 'S') === 0) {
        $del_sql = "DELETE FROM student WHERE studentID = $numeric_id";
    } else if (strpos($del_id_raw, 'C') === 0) {
        $del_sql = "DELETE FROM committee WHERE committeeID = $numeric_id";
    } else {
        $del_sql = "DELETE FROM reviewer WHERE reviewerID = $numeric_id";
    }
    
    if (mysqli_query($conn, $del_sql)) {
        echo "<script>alert('User deleted successfully!'); window.location.href='dashboard.php?tab=users';</script>";
        exit();
    }
}
// Edit Scholarship Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_scholarship') {
    $s_id = intval($_POST['edit_scholarship_id']); 
    $s_name = mysqli_real_escape_string($conn, $_POST['edit_s_name']);
    $s_desc = mysqli_real_escape_string($conn, $_POST['edit_s_description']);
    $s_start = mysqli_real_escape_string($conn, $_POST['edit_s_start']);
    $s_deadline = mysqli_real_escape_string($conn, $_POST['edit_s_deadline']);
    $s_status = mysqli_real_escape_string($conn, $_POST['edit_s_status']);

    if ($s_id > 0) {
        $update_sql = "UPDATE scholarship SET 
                        scholarship_name = '$s_name', 
                        description = '$s_desc', 
                        open_date = '$s_start',
                        close_date = '$s_deadline', 
                        scholarship_status = '$s_status' 
                       WHERE scholarshipID = $s_id";

        if (mysqli_query($conn, $update_sql)) {
            echo "<script>alert('Scholarship updated successfully!'); window.location.href='dashboard.php?tab=scholarships';</script>";
            exit();
        } else {
            echo "<script>alert('Database Error: " . mysqli_error($conn) . "');</script>";
        }
    } else {
        echo "<script>alert('Invalid Scholarship ID');</script>";
    }
}

// Close/Archive Scholarship
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'archive_scholarship') {
    $s_id = mysqli_real_escape_string($conn, $_POST['scholarship_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);

    $check_sql = "SELECT COUNT(*) as pending_count 
                  FROM application a
                  LEFT JOIN review r ON a.applicationID = r.applicationID
                  WHERE a.scholarshipID = '$s_id' AND (r.review_status != 'Completed' OR r.review_status IS NULL)";
    
    $check_res = mysqli_query($conn, $check_sql);
    $check_row = mysqli_fetch_assoc($check_res);

    if ($check_row['pending_count'] > 0) {
        echo "<script>alert('Cannot close: There are still {$check_row['pending_count']} pending reviews.'); window.location.href='dashboard.php?tab=scholarships';</script>";
    } else {
        $update_sql = "UPDATE scholarship SET scholarship_status = '$new_status' WHERE scholarshipID = '$s_id'";
        if (mysqli_query($conn, $update_sql)) {
            echo "<script>alert('Scholarship status updated to $new_status successfully!'); window.location.href='dashboard.php?tab=scholarships';</script>";
        }
    }
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Admin Portal - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root { 
            --primary: #009ef7; --dark: #1e1e2d; --bg: #f5f8fa; 
            --text-main: #181c32; --text-muted: #a1a5b7;
            --success-bg: #e8fff3; --success-text: #50cd89;
            --warning-bg: #fff8dd; --warning-text: #ffc700;
            --danger-bg: #fff5f8;  --danger-text: #f1416c;
            --info-bg: #e1f0ff;    --info-text: #009ef7;
        }

        body { margin: 0; font-family: 'Inter', sans-serif; display: flex; background: var(--bg); color: var(--text-main); }

        /* Sidebar */
        .sidebar { width: 265px; background: var(--dark); height: 100vh; position: fixed; }
        .sidebar-brand { padding: 40px 25px; color: white; font-weight: 700; font-size: 1.1rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .menu-item { padding: 12px 25px; cursor: pointer; color: #a2a3b7; display: flex; align-items: center; transition: 0.2s; font-size: 0.9rem; }
        .menu-item:hover, .menu-item.active { background: #2b2b40; color: white; }
        .menu-item i { margin-right: 15px; width: 20px; }

        .main { margin-left: 265px; flex: 1; padding: 35px; }
        .card { background: white; border-radius: 12px; padding: 30px; border: 1px solid #eff2f5; box-shadow: 0 0 20px rgba(0,0,0,0.02); margin-bottom: 25px; }

        /* Status Badges */
        .status-badge { padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .status-active { background: var(--success-bg); color: var(--success-text); }
        .status-inactive { background: var(--danger-bg); color: var(--danger-text); }
        .status-pending { background: var(--warning-bg); color: var(--warning-text); }
        
        /* Role Badges */
        .role-badge { padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .role-admin { background: var(--danger-bg); color: var(--danger-text); }
        .role-reviewer { background: var(--info-bg); color: var(--info-text); }
        .role-committee { background: #f0f2ff; color: #7239ea; }
        .role-student { background: #fff8dd; color: #ffc700; }

        /* Stats Cards */
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 12px; padding: 25px; border: 1px solid #eff2f5; box-shadow: 0 0 15px rgba(0,0,0,0.02); }
        .stat-number { font-size: 2rem; font-weight: 800; color: var(--primary); margin-bottom: 5px; }
        .stat-label { font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; }
        .stat-icon.students { background: #e1f0ff; color: #009ef7; }
        .stat-icon.applications { background: #f0f2ff; color: #7239ea; }
        .stat-icon.pending { background: #fff8dd; color: #ffc700; }
        .stat-icon.completed { background: #e8fff3; color: #50cd89; }

        .btn { border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 700; }
        .btn-primary { background: var(--primary); color: white; margin-right: 6px; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #f1416c; color: white; }
        .btn-secondary { background: #f5f8fa; color: #7e8299; }

        .page-panel { display: none; }
        .page-panel.active { display: block; }

        /* Modal */
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:1000; justify-content:center; align-items:center; backdrop-filter: blur(4px); }
        .modal-content { background:white; width:520px; border-radius:12px; padding:30px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }

        input, textarea, select { width: 100%; padding: 12px; border: 1px solid #e4e6ef; border-radius: 8px; background: #f9f9f9; margin-top: 8px; font-family: inherit; box-sizing: border-box;}
        .muted-center { text-align:center; color:var(--text-muted); padding: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-label { font-weight: 700; font-size: 0.8rem; display: block; margin-bottom: 5px; }
        
        .section-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 20px; color: var(--text-main); }
        .action-buttons { display: flex; gap: 10px; margin-top: 20px; }

        /* Profile layout */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            width: 100%;
            margin: 0;
        }
        .profile-label {
            display: block;
            margin-bottom: 12px;
            font-weight: 700;
            color: var(--text-main);
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .help-row {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        @media (max-width: 900px){
            .form-grid { grid-template-columns: 1fr; }
        }

        .role-committee {
        background-color: #e1d5f2; 
        color: #6f42c1;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
}

    </style>
</head>

<body>

<div class="sidebar">
    <div class="sidebar-brand">Digital Scholarship Application and Track System</div>

    <div class="menu-item active" id="menu-overview" onclick="showPanel('overview')">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </div>

    <div class="menu-item" id="menu-users" onclick="showPanel('users')">
        <i class="fas fa-users"></i> User Management
    </div>

    <div class="menu-item" id="menu-scholarships" onclick="showPanel('scholarships')">
        <i class="fas fa-award"></i> Scholarships
    </div>

    <div class="menu-item" id="menu-assign" onclick="showPanel('assign')">
        <i class="fas fa-user-check"></i> Assign Reviewers
    </div>

    <div class="menu-item" id="menu-profile" onclick="showPanel('profile')">
        <i class="fas fa-user-circle"></i> My Profile
    </div>

    <div class="menu-item" style="position: absolute; bottom: 20px; width: 100%; box-sizing: border-box;" onclick="window.location.href='../logout.php'">
        <i class="fas fa-sign-out-alt"></i> Logout
    </div>
</div>

<div class="main">
    <div style="display:flex; justify-content:space-between; margin-bottom:25px;">
        <h1 style="font-size: 1.5rem; font-weight: 800;" id="page-title">Admin Dashboard</h1>

        <div style="text-align: right;">
            <span style="font-weight: 800; display: block;">ID: <?php echo htmlspecialchars($adminID); ?></span>
            <small style="color: var(--text-muted);">Admin: <?php echo htmlspecialchars($admin_fullname); ?></small>
        </div>
    </div>

    <div id="overview-panel" class="page-panel active">
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon students">
                    <i class="fas fa-user-graduate fa-2x"></i>
                </div>
                <div class="stat-number"><?php echo $total_students; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon applications">
                    <i class="fas fa-file-alt fa-2x"></i>
                </div>
                <div class="stat-number"><?php echo $total_applications; ?></div>
                <div class="stat-label">Applications</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock fa-2x"></i>
                </div>
                <div class="stat-number"><?php echo $pending_reviews; ?></div>
                <div class="stat-label">Pending Reviews</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon completed">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
                <div class="stat-number"><?php echo $completed_reviews; ?></div>
                <div class="stat-label">Completed Reviews</div>
            </div>
        </div>

        <div class="card">
            <h3 style="margin-top:0;">Recent System Activity</h3>
            <p style="color: var(--text-muted);">
                Welcome to the Digital Scholarship Application and Tracking System Admin Portal. From here you can manage all aspects of the scholarship application system.
                <br><br>
                <strong>Quick Actions:</strong>
            </p>
            
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button class="btn btn-primary" onclick="openModal('addUser')">
                    <i class="fas fa-user-plus"></i> Add New User
                </button>
                <button class="btn btn-success" onclick="openModal('addScholarship')">
                    <i class="fas fa-plus-circle"></i> Create Scholarship
                </button>
            </div>
        </div>
    </div>

    <div id="users-panel" class="page-panel">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin:0;">User Management</h3>
                
                <div style="display: flex; gap: 10px; align-items: center;">
                    <select id="roleFilter" onchange="filterUsersByRole()" style="width: auto; margin: 0; padding: 6px 12px; border: 1px solid #e4e6ef; border-radius: 6px; background: white; font-size: 0.8rem; cursor: pointer;">
                        <option value="all">All Roles</option>
                        <option value="Student">Student</option>
                        <option value="Reviewer">Reviewer</option>
                        <option value="Committee">Committee</option>
                        <option value="Admin">Admin</option>
                    </select>
                    
                    <button class="btn btn-primary" onclick="openModal('addUser')" style="white-space: nowrap;">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                </div>
            </div>

    <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
        <thead>
            <tr style="text-align: left; border-bottom: 2px solid #eff2f5;">
                <th style="padding: 12px; font-size: 0.8rem; color: var(--text-muted);">USER ID</th>
                <th style="padding: 12px; font-size: 0.8rem; color: var(--text-muted);">NAME / EMAIL</th>
                <th style="padding: 12px; font-size: 0.8rem; color: var(--text-muted);">ROLE</th>
                <th style="padding: 12px; font-size: 0.8rem; color: var(--text-muted);">STATUS</th>
                <th style="padding: 12px; font-size: 0.8rem; color: var(--text-muted);">ACTIONS</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users_list as $u): ?>
            <tr class="user-row" data-role="<?php echo ucfirst($u['role']); ?>" style="border-bottom: 1px solid #eff2f5;">
                <td style="padding: 12px; font-weight: 700;"><?php echo $u['id']; ?></td>
                <td style="padding: 12px;">
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($u['name']); ?></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($u['email']); ?></div>
                </td>
                <td style="padding: 12px;">
                    <span class="role-badge <?php echo roleBadgeClass($u['role']); ?>"><?php echo ucfirst($u['role']); ?></span>
                </td>
                <td style="padding: 12px;">
                    <span class="status-badge <?php echo statusBadgeClass($u['status']); ?>"><?php echo ucfirst($u['status']); ?></span>
                </td>
                <td style="padding: 12px;">
                    <button class="btn btn-secondary" onclick="openModal('editUser', '<?php echo $u['id']; ?>', '<?php echo htmlspecialchars($u['name']); ?>')">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger" onclick="confirmDeleteUser('<?php echo $u['id']; ?>', '<?php echo htmlspecialchars($u['name']); ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
            </table>
</div>
    </div>

    <div id="scholarships-panel" class="page-panel"> <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin:0;">Scholarship Programs</h3>
            <button class="btn btn-success" onclick="openModal('addScholarship')">
                <i class="fas fa-plus-circle"></i> Create Scholarship
            </button>
        </div>

        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; border-bottom: 2px solid #eff2f5;">
                    <th style="padding: 12px; font-size: 0.8rem; color: var(--text-muted);">ID</th>
                    <th style="padding: 12px; font-size: 0.8rem; color: var(--text-muted);">NAME</th>
                    <th style="padding: 12px; font-size: 0.8rem; color: var(--text-muted);">START DATE</th>
                <th style="padding: 12px; font-size: 0.8rem; color: var(--text-muted);">CLOSE DATE</th>
                    <th style="padding: 12px; font-size: 0.8rem; color: var(--text-muted);">STATUS</th>
                    <th style="padding: 12px; font-size: 0.8rem; color: var(--text-muted);">ACTIONS</th>
                </tr>
            </thead>
            
            <tbody>
                <?php
                $s_query = mysqli_query($conn, "SELECT * FROM scholarship");

                while($s = mysqli_fetch_assoc($s_query)): 
                ?>
                <tr style="border-bottom: 1px solid #eff2f5;">
                    <td style="padding: 12px; font-weight: 700;">
                        <?php echo 'SS' . str_pad($s['scholarshipID'], 2, "0", STR_PAD_LEFT); ?>
                    </td>

                    <td style="padding: 12px; font-weight: 600;"><?php echo htmlspecialchars($s['scholarship_name']); ?></td>
                    <td style="padding: 12px; font-size: 0.85rem;"><?php echo $s['open_date']; ?></td>
                    <td style="padding: 12px; font-size: 0.85rem;"><?php echo $s['close_date']; ?></td>
                    
                    <td style="padding: 12px;">
                        <span class="status-badge <?php echo statusBadgeClass($s['scholarship_status']); ?>">
                            <?php echo ucfirst($s['scholarship_status']); ?>
                        </span>
                    </td>
                    <td style="padding: 12px;">
                        <div style="display: flex; gap: 5px;">
                            <button class="btn btn-secondary" onclick='openEditScholarshipModal(<?php echo json_encode($s); ?>)'>
                                <i class="fas fa-edit"></i> Edit
                            </button>

                            <?php 
                            $currentStatus = strtolower($s['scholarship_status']);
                            if ($currentStatus === 'closed'):  ?>
                                <button class="btn btn-warning" style="background: #f6993f; color: white;" onclick="archiveScholarship(<?php echo $s['scholarshipID']; ?>, 'Archived')">
                                    <i class="fas fa-archive"></i> Archive
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php 
                endwhile; 
                ?>
            </tbody>
        </table>
    </div>
</div>

<div id="assign-panel" class="page-panel">
    <div class="card">
        <div style="margin-bottom: 20px; border-bottom: 1px dotted #e4e6ef; padding-bottom: 12px;">
            <h2 style="margin:0; font-size: 1.25rem; font-weight: 900; display:flex; gap:10px; align-items:center;">
                <i class="fas fa-user-check" style="color: #7239ea;"></i>
                Assign Reviewers to Student Applications
            </h2>
        </div>

        <form method="POST" action="dashboard.php" style="max-width: 800px;">
            <input type="hidden" name="action" value="assign_reviewer_action">

            <div class="form-group">
                <label class="form-label">Select Student Application</label>
                <select name="application_id" required>
                    <option value="">-- Select Pending Application --</option>
                    <?php
                    // Fetch applications along with student and scholarship names
                    $apps_sql = "SELECT a.applicationID, s.student_name, sch.scholarship_name 
                                 FROM application a
                                 JOIN student s ON a.studentID = s.studentID
                                 JOIN scholarship sch ON a.scholarshipID = sch.scholarshipID
                                 WHERE a.application_status = 'Pending'";
                    $apps_res = mysqli_query($conn, $apps_sql);
                    while($app = mysqli_fetch_assoc($apps_res)) {
                        echo "<option value='{$app['applicationID']}'>
                            {$app['student_name']} ({$app['scholarship_name']})
                        </option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Select Available Reviewers</label>
                <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; border: 1px solid #e4e6ef;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <?php
                        $r_query = mysqli_query($conn, "SELECT reviewerID, reviewer_name FROM reviewer WHERE status = 'active'");
                        while($r = mysqli_fetch_assoc($r_query)) {
                            echo "<label style='display: flex; align-items: center; gap: 10px; cursor: pointer;'>
                                    <input type='checkbox' name='reviewers[]' value='{$r['reviewerID']}' style='width: auto; margin: 0;'> " 
                                    . htmlspecialchars($r['reviewer_name']) . 
                                 "</label>";
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <button type="submit" class="btn" style="background: #7239ea; color: white; padding: 12px 25px;">
                    <i class="fas fa-save"></i> Confirm Assignment
                </button>
                <button type="reset" class="btn btn-secondary" style="padding: 12px 25px;">Clear Form</button>
            </div>
        </form>
    </div>
</div>

    <div id="profile-panel" class="page-panel">
        <div class="card">
            <div style="margin-bottom: 18px; border-bottom: 1px dotted #e4e6ef; padding-bottom: 12px;">
                <h2 style="margin:0; font-size: 1.25rem; font-weight: 900; display:flex; gap:10px; align-items:center;">
                    <i class="fas fa-user-circle" style="color: var(--primary);"></i>
                    My Profile Information
                </h2>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="profile-label">Full Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($admin_fullname); ?>" disabled style="background: #f8f9fa;">
                </div>
                <div class="form-group">
                    <label class="profile-label">Admin ID</label>
                    <input type="text" value="<?php echo htmlspecialchars($adminID); ?>" disabled style="background: #f8f9fa;">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="profile-label">Email Address</label>
                    <input type="text" value="<?php echo htmlspecialchars($admin_email); ?>" disabled style="background: #f8f9fa;">
                </div>
                <div class="form-group">
                    <label class="profile-label">Phone Number</label>
                    <input type="text" value="<?php echo htmlspecialchars($admin_phone); ?>" disabled style="background: #f8f9fa;">
                </div>
            </div>

            <div class="form-group">
                <label class="profile-label">Home / Office Address</label>
                <textarea disabled style="height: 100px; background: #f8f9fa; resize: none;"><?php echo htmlspecialchars($admin_address); ?></textarea>
            </div>

            <div class="card" style="margin-top: 20px; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-main);">Account Security</h3>
                    <p style="margin: 5px 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Update your login credentials here.</p>
                </div>
                <button onclick="window.location.href='changepwd.php'" style="background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer;">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </div>

            <div class="help-row">
                <i class="fas fa-info-circle"></i>
                <span>To update your contact details, please submit a request to the Admin office.</span>
            </div>
        </div>
    </div>

</div>

<div id="addUserModal" class="modal">
    <div class="modal-content">
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="action" value="add_user">
            <div style="display:flex; justify-content:space-between; margin-bottom:20px;">
                <h3 style="margin:0;">Add New Staff Member</h3>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">X</button>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select name="user_type" id="roleSelector" required onchange="updateEmailPlaceholder()">
                    <option value="reviewer">Reviewer</option>
                    <option value="committee">Committee</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="fullname" required placeholder="Enter full name">
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" id="addUserEmail" required placeholder="name@reviewer.edu.my">
            </div>
            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" required placeholder="e.g. 012-3456789">
            </div>

            <div class="form-group">
                <label class="form-label">Address</label>
                <textarea name="address" rows="2" required placeholder="Enter your address..."></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Temporary Password</label>
                <input type="password" name="password" required placeholder="Enter your temporary password">
            </div>
            <div style="background: #f0f7ff; padding: 10px; border-radius: 6px; margin-bottom: 20px; font-size: 0.8rem; color: #0056b3;">
                <i class="fas fa-info-circle"></i> Staff ID will be automatically assigned upon saving.
            </div>
            <div style="text-align:right;">
                <button type="submit" class="btn btn-primary">Create Account</button>
            </div>
        </form>
    </div>
</div>
</div>

<div id="addScholarshipModal" class="modal">
    <div class="modal-content">
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="action" value="add_scholarship">
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h3 style="margin:0;">Create New Scholarship Program</h3>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>

            <div class="form-group">
                <label class="form-label">Scholarship Name</label>
                <input type="text" name="s_name" placeholder="Enter scholarship name..." required>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="s_description" rows="3" placeholder="Enter scholarship description..."></textarea>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="s_start_date" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Close Date</label>
                    <input type="date" name="s_deadline" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Initial Status</label>
                <select name="s_status">
                    <option value="open">Open</option>
                    <option value="draft">Draft</option>
                    <option value="archive">Archive</option>
                </select>
            </div>

            <div style="margin-top:25px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Program</button>
            </div>
        </form>
    </div>
</div>

<div id="assignReviewerModal" class="modal">
    <div class="modal-content">
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="action" value="assign_reviewer_action">
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h3 style="margin:0;">Assign Reviewers</h3>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>

            <div class="form-group">
                <label class="form-label">1. Select Scholarship Program</label>
                <select name="scholarship_id" required>
                    <option value="">-- Select Program --</option>
                    <?php
                    $s_list = mysqli_query($conn, "SELECT scholarshipID, scholarship_name FROM scholarship");
                    while($row = mysqli_fetch_assoc($s_list)) {
                        echo "<option value='{$row['scholarshipID']}'>{$row['scholarship_name']}</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">2. Select Reviewers</label>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; border: 1px solid #e4e6ef; max-height: 200px; overflow-y: auto;">
                    <?php
                    $r_list = mysqli_query($conn, "SELECT reviewerID, reviewer_name FROM reviewer");
                    while($rev = mysqli_fetch_assoc($r_list)) {
                        echo "<div style='margin-bottom: 8px;'>
                                <input type='checkbox' name='reviewers[]' value='{$rev['reviewerID']}' id='rev_{$rev['reviewerID']}' style='width:auto;'> 
                                <label for='rev_{$rev['reviewerID']}' style='display:inline; font-weight:500;'> " . htmlspecialchars($rev['reviewer_name']) . " ({$rev['reviewerID']})</label>
                              </div>";
                    }
                    ?>
                </div>
            </div>

            <div style="margin-top:25px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Confirm & Send Notification</button>
            </div>
        </form>
    </div>
</div>

<div id="editUserModal" class="modal">
    <div class="modal-content">
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="action" value="edit_user">
            
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0;">Edit User</h3>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
            </div>

            <div class="form-group">
                <label class="form-label">User ID</label>
                <input type="text" id="editUserId" name="edit_userid" readonly style="background:#f0f0f0;">
            </div>

            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" id="editFullname" name="edit_fullname" placeholder="Enter full name..." required>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="edit_status"> <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div style="margin-top:25px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update User</button>
            </div>
        </form>
    </div>
</div>

<div id="editScholarshipModal" class="modal">
    <div class="modal-content">
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="action" value="edit_scholarship">
            <input type="hidden" name="edit_scholarship_id" id="edit_s_id">
            
            <div class="form-group">
                <label>Scholarship Name</label>
                <input type="text" name="edit_s_name" id="edit_s_name" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="edit_s_description" id="edit_s_description" required></textarea>
            </div>
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="edit_s_start" id="edit_s_start" required>
            </div>
            <div class="form-group">
                <label>Close Date</label>
                <input type="date" name="edit_s_deadline" id="edit_s_deadline" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="edit_s_status" id="edit_s_status">
                    <option value="Open">Open</option>
                    <option value="Closed">Closed</option>
                    <option value="Archived">Archived</option>
                </select>
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn btn-primary">Update Scholarship</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal('editScholarship')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>

document.addEventListener('submit', function (e) {
    const target = e.target;
    
    // Check if the form being submitted is an "Add" or "Edit" scholarship form
    const isAddScholarship = target.querySelector('input[name="action"][value="add_scholarship"]');
    const isEditScholarship = target.querySelector('input[name="action"][value="edit_scholarship"]');

    if (isAddScholarship || isEditScholarship) {
        let startVal, endVal;

        if (isAddScholarship) {
            startVal = target.querySelector('input[name="s_start_date"]').value;
            endVal = target.querySelector('input[name="s_deadline"]').value;
        } else {
            startVal = target.querySelector('input[name="edit_s_start"]').value;
            endVal = target.querySelector('input[name="edit_s_deadline"]').value;
        }

        // Compare dates if both are filled
        if (startVal && endVal) {
            const startDate = new Date(startVal);
            const endDate = new Date(endVal);

            if (endDate < startDate) {
                e.preventDefault(); 
                alert("Error: The Close Date cannot be earlier than the Start Date. Please check your entries.");
            }
        }
    }
});
    
window.onload = function() {
    const urlParams = new URLSearchParams(window.location.search);
    let tab = urlParams.get('tab');
    
    if (!tab) {
        tab = 'overview'; 
    }
    showPanel(tab); 
};

function showPanel(panelId) {
    document.querySelectorAll('.page-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
    
    const targetPanel = document.getElementById(panelId + '-panel');
    if (targetPanel) {
        targetPanel.classList.add('active');
        document.getElementById('menu-' + panelId).classList.add('active');
        
        const titles = {
            'overview': 'Admin Dashboard',
            'users': 'User Management',
            'scholarships': 'Scholarship Programs',
            'assign': 'Assign Reviewers',
            'profile': 'My Profile'
        };
        document.getElementById('page-title').innerText = titles[panelId] || 'Admin Dashboard';
    }
}

function updateEmailPlaceholder() {
    const roleSelector = document.getElementById('roleSelector');
    const emailInput = document.getElementById('addUserEmail');
    
    if (!roleSelector || !emailInput) return; 

    const role = roleSelector.value;
    
    if (role === 'admin') {
        emailInput.placeholder = 'name@admin.edu.my';
    } else if (role === 'committee') {
        emailInput.placeholder = 'name@committee.edu.my';
    } else if (role === 'reviewer') {
        emailInput.placeholder = 'name@reviewer.edu.my';
    }
}

// Modal Functions
function openModal(modalType, id = null, name = null) {
    closeModal(); 
    
    if (modalType === 'addUser') {
        document.getElementById('addUserModal').style.display = 'flex';
    } else if (modalType === 'addScholarship') {
        document.getElementById('addScholarshipModal').style.display = 'flex';
    } else if (modalType === 'assignReviewer') {
        document.getElementById('assignReviewerModal').style.display = 'flex';
    } else

    if (modalType === 'editUser' && id) {
        document.getElementById('editUserId').value = id;
        if (name && document.getElementById('editFullname')) {
            document.getElementById('editFullname').value = name;
        }
        document.getElementById('editUserModal').style.display = 'flex';
    }
}

function closeModal() {
    document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
}

// Confirmation dialog for deletion
function confirmDeleteUser(userId, userName) {
    if (confirm(`Are you sure you want to delete user ${userName} (${userId})?`)) {
        window.location.href = `dashboard.php?delete_id=${userId}`;
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}

function archiveScholarship(id, status) {
    let actionText = status === 'Closed' ? 'close this scholarship program' : 'archive this program';
    
    if (confirm(`Are you sure you want to ${actionText}? \n\nNote: This will verify if all student applications have completed reviews.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'dashboard.php';

        const params = {
            'action': 'archive_scholarship',
            'scholarship_id': id,
            'new_status': status
        };

        for (const key in params) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = params[key];
            form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
    }
}

function filterUsersByRole() {
    const selectedRole = document.getElementById('roleFilter').value;
    const rows = document.querySelectorAll('.user-row'); 

    rows.forEach(row => {
        const rowRole = row.getAttribute('data-role');
        if (selectedRole === 'all' || rowRole === selectedRole) {
            row.style.display = ''; 
        } else {
            row.style.display = 'none'; 
        }
    });
}

function openEditScholarshipModal(scholarship) {
    document.getElementById('edit_s_id').value = scholarship.scholarshipID;
    document.getElementById('edit_s_name').value = scholarship.scholarship_name;
    document.getElementById('edit_s_description').value = scholarship.description;
    document.getElementById('edit_s_status').value = scholarship.scholarship_status;
    document.getElementById('edit_s_start').value = scholarship.open_date;
    document.getElementById('edit_s_deadline').value = scholarship.close_date;
    
    document.getElementById('editScholarshipModal').style.display = 'flex';
}

</script>

</body>
</html>