<?php
    session_start();
    include('../config/db.php');

    // Check role
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
        header("Location: ../login.php");
        exit();
    }

    $studentID = $_SESSION['user_id'];

    $status_check_sql = "SELECT status FROM student WHERE studentID = '" . mysqli_real_escape_string($conn, $studentID) . "' LIMIT 1";
    $status_res = mysqli_query($conn, $status_check_sql);
    $user_data = mysqli_fetch_assoc($status_res);

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
                    --text-main: #181c32; --danger-text: #f1416c; --danger-bg: #fff5f8;
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
                .deactivated-card h1 { color: var(--danger-text); font-size: 1.8rem; font-weight: 800; margin-bottom: 15px; }
                .deactivated-card p { color: #5e6278; line-height: 1.6; font-size: 1rem; margin-bottom: 30px; }
                .btn-return { 
                    display: inline-block; padding: 14px 32px; background: var(--primary); 
                    color: white; text-decoration: none; border-radius: 8px; 
                    font-weight: 700; font-size: 0.9rem;
                }
            </style>
        </head>
        <body>
            <div class="deactivated-card">
                <div class="icon-wrapper"><i class="fas fa-user-slash"></i></div>
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

if (isset($_POST['submit_missing_doc'])) {
        $app_id = mysqli_real_escape_string($conn, $_POST['app_id']);
        
        if (isset($_FILES['missing_docs']) && !empty($_FILES['missing_docs']['name'][0])) {
            
            $upload_dir = "../uploads/";
            $success_count = 0;
            $file_count = count($_FILES['missing_docs']['name']);

            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['missing_docs']['error'][$i] == 0) {
                    
                    $file_name = time() . "_" . $i . "_supplementary_" . basename($_FILES["missing_docs"]["name"][$i]);
                    $target_file = $upload_dir . $file_name;

                    if (move_uploaded_file($_FILES["missing_docs"]["tmp_name"][$i], $target_file)) {
                        
                        $db_path = "uploads/" . $file_name;
                        $doc_sql = "INSERT INTO document (applicationID, documentType, file_path, uploadedDate) 
                                    VALUES ('$app_id', 'Supplementary Document', '$db_path', NOW())";
                        
                        if (mysqli_query($conn, $doc_sql)) {
                            $success_count++;
                        }
                    }
                }
            }

            if ($success_count > 0) {
                $update_sql = "UPDATE application SET remark = NULL WHERE applicationID = '$app_id'";
                mysqli_query($conn, $update_sql);

                echo "<script>alert('Successfully uploaded $success_count document(s)!'); window.location.href='dashboard.php';</script>";
            } else {
                echo "<script>alert('Error: No files were uploaded successfully.');</script>";
            }

        } else {
            echo "<script>alert('Please select at least one file to upload.');</script>";
        }
    }
    $student_id = $_SESSION['user_id'];
    $student_name = $_SESSION['user_name'];
    $custom_student_id = "S" . str_pad($student_id, 3, "0", STR_PAD_LEFT);
    // Get unread count
    $count_res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM notification WHERE studentID = '$student_id' AND isRead = 0");
    $unread_count = ($count_res) ? mysqli_fetch_assoc($count_res)['total'] : 0;

    //Get the latest 5 messages for pop-out dropdown
    $notif_list = mysqli_query($conn, "SELECT message FROM notification WHERE studentID = '$student_id' ORDER BY notificationID DESC LIMIT 5");
    
    // Get full student details
    $profile_sql = "SELECT student_name, student_email, student_phonenum, student_address FROM student WHERE studentID = '$student_id'";
    $profile_result = mysqli_query($conn, $profile_sql);
    $profile = mysqli_fetch_assoc($profile_result);

    $history_sql = "SELECT a.applicationID, a.application_status, a.committeeDecision, 
                       a.remark, a.decisionDate, a.submissionDate, 
                       af.academicRecords, af.financialStatus, 
                       ss.scholarship_name,
                       r.score
                FROM application a
                LEFT JOIN applicationform af ON a.applicationID = af.applicationID
                LEFT JOIN scholarship ss ON a.scholarshipID = ss.scholarshipID
                LEFT JOIN review r ON a.applicationID = r.applicationID 
                WHERE a.studentID = '$student_id'";

    //application history counts
    $history_result = mysqli_query($conn, $history_sql);
    $history_count = ($history_result) ? mysqli_num_rows($history_result) : 0;

    $pending_count = 0;
    $approved_count = 0;

    if ($history_count > 0) {
        while($row = mysqli_fetch_assoc($history_result)) {
            $current_status = trim($row['application_status']) ? trim($row['application_status']) : 'Pending';
            $committee_decision = trim($row['committeeDecision']);

            if ($committee_decision === 'Approved' || $current_status === 'Awarded' || $current_status === 'Approved') {
                $approved_count++;
            } 
            elseif ($committee_decision === 'Pending' || 
                    in_array($current_status, ['Pending', 'Pending Review', 'Reviewed', 'Under Review']) && 
                    $committee_decision !== 'Rejected') {
                $pending_count++;
            }
        }
        mysqli_data_seek($history_result, 0); 
    }

    $applied_scholarships = $history_count;
    $pending_apps = $pending_count; 
    $awarded_apps = $approved_count; 

    // Get review feedback
    $feedback_sql = "SELECT r.score, r.comments, r.review_Date, ss.scholarship_name 
                     FROM review r
                     JOIN application a ON r.applicationID = a.applicationID
                     JOIN scholarship ss ON a.scholarshipID = ss.scholarshipID
                     WHERE a.studentID = '$student_id'
                     ORDER BY r.review_Date DESC";

    $feedback_result = mysqli_query($conn, $feedback_sql);

    if (!$feedback_result) {
        die("Database Error: " . mysqli_error($conn));
    }

?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Student Portal | Dashboard</title>
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

            /*Layout Styles*/
            body { margin: 0; font-family: 'Inter', sans-serif; background-color: var(--bg); display: flex; min-height: 100vh; }

            /*Sidebar*/
            .sidebar { width: 265px; background: var(--dark); height: 100vh; position: fixed; overflow-y: auto; }
            .sidebar-brand { padding: 40px 25px 20px 25px; color: white; font-weight: 700; font-size: 1.1rem; border-bottom: 1px solid rgba(255,255,255,0.1); }
            
            .menu-item { padding: 12px 25px; cursor: pointer; color: #a2a3b7; display: flex; align-items: center; transition: 0.2s; font-size: 0.9rem; }
            .menu-item:hover, .menu-item.active { background: #2b2b40; color: white; }
            .menu-item i { margin-right: 15px; width: 20px; }
            
            /*Main Content*/
            .main-content { 
                margin-left: var(--sidebar-width); 
                width: calc(100% - var(--sidebar-width)); 
                flex-grow: 1;
            }

            .dashboard-header {
                background: transparent;
                padding: 35px 40px 10px 40px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            /*Bell and dropdown*/
            .notif-wrapper {
                position: relative;
                display: inline-block;
            }

            /*The Pop-out window*/
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

            /* Notification Items */
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

            /* noti red circle*/
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

            .container { padding: 25px 40px; max-width: 1400px; }

            /*Panel System*/
            .panel { display: none; }
            .panel.active { display: block; }

            .card {
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 0 20px rgba(0,0,0,0.02);
                margin-bottom: 30px;
                border: 1px solid #eff2f5;
            }

            .card-header { margin-bottom: 25px; border-bottom: 1px dotted #e4e6ef; padding-bottom: 15px; }
            .card-header h2 { color: var(--text-main); margin: 0; font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }

            .form-grid { 
                display: grid; 
                grid-template-columns: 1fr 1fr; 
                gap: 20px; 
                width: 100%; 
                margin: 0; 
            }

            .form-group { margin-bottom: 20px; }
            label { 
                display: block; 
                margin-bottom: 12px;
                font-weight: 700; 
                color: var(--text-main); 
                font-size: 0.8rem; 
                text-transform: uppercase; 
            }
            
            input, select, textarea {
                width: 100%; padding: 12px; border: 1px solid #e4e6ef; border-radius: 8px; font-size: 0.9rem;
                background: #f9f9f9; color: var(--text-main); font-family: inherit; box-sizing: border-box;
            }
            input:focus, select:focus, textarea:focus { border-color: var(--primary); outline: none; background: #fff; }

            table { width: 100%; border-collapse: collapse; }
            th { text-align: left; padding: 15px; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; border-bottom: 1px dotted #e4e6ef; }
            td { padding: 18px 15px; border-bottom: 1px solid #f1f1f4; color: var(--text-main); font-size: 0.9rem; }

            .badge { padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
            .status-Pending { background: var(--warning-bg); color: var(--warning-text); }

            .status-Approved, .status-Awarded { 
                background: var(--success-bg); 
                color: var(--success-text); 
            }

            .status-Rejected { background: var(--danger-bg); color: var(--danger-text); }

            .status-UnderReview { background: #e1f0ff; color: #009ef7; }

            html { scroll-behavior: smooth; }
        </style>
    </head>

    <body>

    <div class="sidebar">
        <div class="sidebar-brand">Digital Scholarship Application and Tracking System</div>

        <div class="menu-item" onclick="window.location.href='../index.php'">
            <i class="fas fa-home"></i> Home
        </div>

        <div class="menu-item active" onclick="showPanel('dashboard')">
            <i class="fas fa-th-large"></i> Dashboard
        </div>

        <div class="menu-item" onclick="showPanel('profile')">
            <i class="fas fa-user-circle"></i> My Profile
        </div>

        <div class="menu-item" onclick="window.location.href='../schemes.php'">
            <i class="fas fa-search"></i> Browse Schemes
        </div>

        <div class="menu-item" onclick="showPanel('history')">
            <i class="fas fa-history"></i> My Applications
        </div>

        <div class="menu-item" onclick="showPanel('feedback')">
            <i class="fas fa-comment-dots"></i> Review Feedback
        </div>

        <div class="menu-item" style="position: absolute; bottom: 20px; width: 100%; box-sizing: border-box;" onclick="window.location.href='../logout.php'">
            <i class="fas fa-sign-out-alt"></i> Logout
        </div>
    </div>

    <!-- Welcome Section -->
    <div class="main-content">
        <header class="dashboard-header">
            <div> 
                <h1 style="font-size: 1.5rem; font-weight: 800; margin:0;" id="pageTitle">Dashboard</h1>
                <small style="color: var(--text-muted);">Welcome back, <?php echo htmlspecialchars($student_name); ?></small>
            </div>

            <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; gap: 5px;">
                
                <!-- Notification Section -->
                <div class="notif-wrapper">
                    <div class="notif-link">
                        <i class="fas fa-bell" style="font-size: 1.2rem; color: var(--text-main);"></i>
                        <?php if($unread_count > 0): ?>
                            <span class="badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="notif-dropdown">
                        <div style="padding: 15px; font-weight: 800; border-bottom: 2px solid var(--primary); color: var(--text-main); text-align: left; background: #fff;">
                            Notifications
                        </div>
                        
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php 
                            if($notif_list && mysqli_num_rows($notif_list) > 0):
                                mysqli_data_seek($notif_list, 0); 
                                while($row = mysqli_fetch_assoc($notif_list)): ?>
                                    <div class="notif-item">
                                        <i class="fas fa-info-circle" style="color: var(--primary); margin-right: 8px;"></i>
                                        <?php echo htmlspecialchars($row['message']); ?>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="notif-item" style="color: var(--text-muted); text-align: center;">
                                    <i class="fas fa-check-circle" style="display: block; font-size: 1.5rem; margin-bottom: 10px; color: #e4e6ef;"></i>
                                    No new updates
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <a href="notification.php" style="display: block; padding: 12px; text-align: center; font-size: 0.8rem; color: var(--primary); text-decoration: none; font-weight: bold; background: #f9f9f9; border-top: 1px solid #eff2f5;">
                            View All Notifications
                        </a>
                    </div>
                </div>

                <div style="line-height: 1.2;">
                    <span style="font-weight: 800; display: block; font-size: 1rem;">ID: <?php echo htmlspecialchars($custom_student_id); ?></span>
                    <small style="color: var(--text-muted); font-size: 0.8rem;">Student Account</small>
                </div>
            </div>
        </header>

        <div class="container">
            
            <!-- Dashboard Panel -->
            <div class="panel active" id="panel-dashboard">
                <!-- Dashboard Stats -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;">
                    <div class="card" style="text-align: center; padding: 20px;">
                        <i class="fas fa-paper-plane fa-2x" style="color: var(--primary);"></i>
                        <h1 style="margin: 10px 0;"><?php echo $applied_scholarships; ?></h1>
                        <small style="color: var(--text-muted); font-weight: bold;">Total Applied</small>
                    </div>

                    <div class="card" style="text-align: center; padding: 20px;">
                        <i class="fas fa-hourglass-half fa-2x" style="color: var(--warning-text);"></i>
                        <h1 style="margin: 10px 0;"><?php echo $pending_apps; ?></h1>
                        <small style="color: var(--text-muted); font-weight: bold;">Under Review</small>
                    </div>

                    <div class="card" style="text-align: center; padding: 20px;">
                        <i class="fas fa-trophy fa-2x" style="color: var(--success-text);"></i>
                        <h1 style="margin: 10px 0;"><?php echo $awarded_apps; ?></h1>
                        <small style="color: var(--text-muted); font-weight: bold;">Scholarships Awarded</small>
                    </div>
                </div>

                <!-- Application Status Overview -->
                <div class="card" style="margin-bottom: 30px;">
                    <div class="card-header">
                        <h2><i class="fas fa-chart-pie" style="color: var(--primary);"></i> Application Status Overview</h2>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; padding: 10px 0;">
                        
                        <div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">Pending Review</span>
                                <span style="font-size: 0.85rem; font-weight: 700; color: var(--warning-text);"><?php echo $pending_apps; ?></span>
                            </div>
                            <div style="background: #f1f1f4; height: 8px; border-radius: 10px; overflow: hidden;">
                                <div style="height: 100%; transition: width 0.5s; 
                                    width: <?php echo ($applied_scholarships > 0) ? ($pending_apps / $applied_scholarships * 100) : 0; ?>%; 
                                    background: <?php echo ($pending_apps > 0) ? 'var(--warning-text)' : '#e4e6ef'; ?>;">
                                </div>
                            </div>
                        </div>

                        <div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">Approved/Awarded</span>
                                <span style="font-size: 0.85rem; font-weight: 700; color: var(--success-text);"><?php echo $awarded_apps; ?></span>
                            </div>
                            <div style="background: #f1f1f4; height: 8px; border-radius: 10px; overflow: hidden;">
                                <div style="height: 100%; transition: width 0.5s;
                                    width: <?php echo ($applied_scholarships > 0) ? ($awarded_apps / $applied_scholarships * 100) : 0; ?>%; 
                                    background: <?php echo ($awarded_apps > 0) ? 'var(--success-text)' : '#e4e6ef'; ?>;">
                                </div>
                            </div>
                        </div>

                        <div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">Total Applications</span>
                                <span style="font-size: 0.85rem; font-weight: 700; color: var(--primary);"><?php echo $applied_scholarships; ?></span>
                            </div>
                            <div style="background: #f1f1f4; height: 8px; border-radius: 10px; overflow: hidden;">
                                <div style="height: 100%; transition: width 0.5s;
                                    width: <?php echo ($applied_scholarships > 0) ? 100 : 0; ?>%; 
                                    background: var(--primary);">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tips & Announcements -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div class="card" style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); border-left: 4px solid var(--primary);">
                        <div style="display: flex; gap: 15px; align-items: start;">
                            <i class="fas fa-lightbulb fa-2x" style="color: var(--primary);"></i>
                            <div>
                                <h3 style="margin: 0 0 10px 0; color: var(--text-main); font-size: 1rem;">Application Tips</h3>
                                <ul style="margin: 0; padding-left: 20px; color: var(--text-main); font-size: 0.85rem; line-height: 1.8;">
                                    <li>Ensure all documents are clear and readable</li>
                                    <li>Double-check your CGPA and academic records</li>
                                    <li>Submit applications before deadlines</li>
                                    <li>Check feedback regularly for improvement areas</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="background: linear-gradient(135deg, #f093fb15 0%, #f5576c15 100%); border-left: 4px solid #f5576c;">
                        <div style="display: flex; gap: 15px; align-items: start;">
                            <i class="fas fa-bullhorn fa-2x" style="color: #f5576c;"></i>
                            <div>
                                <h3 style="margin: 0 0 10px 0; color: var(--text-main); font-size: 1rem;">Important Notice</h3>
                                <p style="margin: 0; color: var(--text-main); font-size: 0.85rem; line-height: 1.8;">
                                    <strong>New scholarships available!</strong> Check the Browse Schemes page regularly for updated opportunities. Application deadlines vary by scheme.
                                </p>
                                <div style="margin-top: 15px;">
                                    <a href="../schemes.php" style="background: #f5576c; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.8rem; font-weight: 700; display: inline-block;">View All Schemes</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Panel -->
            <div class="panel" id="panel-profile">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-circle" style="color: var(--primary);"></i> My Profile Information</h2>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" value="<?php echo htmlspecialchars($profile['student_name']); ?>" disabled style="background: #f8f9fa;">
                        </div>
                        <div class="form-group">
                            <label>Student ID</label>
                            <input type="text" value="<?php echo htmlspecialchars($custom_student_id); ?>" disabled style="background: #f8f9fa;">
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="text" value="<?php echo htmlspecialchars($profile['student_email']); ?>" disabled style="background: #f8f9fa;">
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" value="<?php echo htmlspecialchars($profile['student_phonenum']); ?>" disabled style="background: #f8f9fa;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Home Address</label>
                        <textarea disabled style="height: 100px; background: #f8f9fa; resize: none;"><?php echo htmlspecialchars($profile['student_address']); ?></textarea>
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

                    <div style="margin-top: 10px; display: flex; align-items: center; gap: 8px; color: var(--text-muted); font-size: 0.85rem;">
                        <i class="fas fa-info-circle"></i>
                        <span>To update your contact details, please submit a request to the Admin office.</span>
                    </div>
                </div>
            </div>

            <!-- HISTORY PANEL -->
            <div class="panel" id="panel-history">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-folder-open" style="color: var(--primary);"></i> Application History</h2>
                    </div>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Scholarship Scheme</th>
                                    <th>Academic Records</th>
                                    <th>Financial Status</th>
                                    <th>Status</th>
                                    <th>Committee Remark</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($history_count > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($history_result)): ?>
                                        <tr>
                                            <td style="font-weight: 600; color: var(--dark);">
                                                <?php echo htmlspecialchars($row['scholarship_name']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['academicRecords']); ?></td>
                                            <td><?php echo htmlspecialchars($row['financialStatus']); ?></td>
                                            <td>
                                            <?php
                                                if (!empty($row['remark']) && trim($row['remark']) === 'Pending Info') {
                                                    echo '<span class="badge" style="background:#e0f2fe; color:#009ef7; margin-bottom:5px;">Action Required</span><br>';
                                                    echo '<button onclick="openUploadModal(' . (int)$row['applicationID'] . ')" style="background:#f1416c; color:white; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:0.8rem; font-weight:700; margin-top:5px;">
                                                            <i class="fas fa-upload"></i> Upload Document
                                                        </button>';
                                                } else {

                                                    $statusText = 'Pending';
                                                    if (!empty($row['committeeDecision'])) {
                                                        $statusText = trim($row['committeeDecision']);  // Approved / Rejected
                                                    } elseif (!empty($row['application_status'])) {
                                                        $statusText = trim($row['application_status']); // Under Committee Review / etc
                                                    }

                                                    if ($statusText === 'Awarded' || $statusText === 'Approved') {
                                                        $badgeClass = "status-Approved";
                                                    } elseif ($statusText === 'Rejected') {
                                                        $badgeClass = "status-Rejected";
                                                    } elseif ($statusText === 'Under Committee Review') {
                                                        $badgeClass = "status-UnderReview";
                                                    } else {
                                                        $badgeClass = "status-Pending";
                                                    }

                                                    echo '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($statusText) . '</span>';
                                                }
                                            ?>
                                            </td>


                                <td style="color:#5e6278;">
                                    <?php echo !empty($row['remark']) ? htmlspecialchars($row['remark']) : '-'; ?>
                                </td>

                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding: 40px; color: var(--text-muted);">
                                            No applications found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- FEEDBACK PANEL -->
            <div class="panel" id="panel-feedback">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-comments" style="color: var(--primary);"></i> Reviewer Feedback</h2>
                    </div>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Scholarship</th>
                                    <th>Score</th>
                                    <th>Reviewer Comments</th>
                                    <th>Review Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if(mysqli_num_rows($feedback_result) > 0):
                                    while($f = mysqli_fetch_assoc($feedback_result)): 
                                ?>
                                <tr>
                                    <td style="font-weight: 700;"><?php echo htmlspecialchars($f['scholarship_name']); ?></td>
                                    <td>
                                        <span class="badge" style="background: #e1f0ff; color: #009ef7;">
                                            <?php echo htmlspecialchars($f['score']); ?> / 100
                                        </span>
                                    </td>
                                    <td style="font-style: italic; color: #5e6278; max-width: 400px;">
                                        "<?php echo htmlspecialchars($f['comments']); ?>"
                                    </td>
                                    <td>
                                        <?php echo date('d M Y', strtotime($f['review_Date'])); ?>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile; 
                                else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align:center; padding: 40px; color: var(--text-muted);">
                                            <i class="fas fa-info-circle"></i> No reviewer feedback available yet.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>


            <div id="uploadModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
                <div style="background:white; padding:30px; border-radius:12px; width:400px; box-shadow:0 5px 15px rgba(0,0,0,0.2);">
                    <h3 style="margin-top:0; color:var(--dark);">Upload Missing Document</h3>
                    <p style="color:var(--text-muted); font-size:0.9rem;">The reviewer has requested additional information. Please upload the required document below.</p>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="app_id" id="upload_app_id">
                
                <div style="margin-bottom:20px;">
                    <label style="display:block; margin-bottom:5px; color:#5e6278;">Select File(s):</label>
                    <input type="file" name="missing_docs[]" accept=".pdf,.jpg,.png" multiple required style="width:100%; border:1px solid #e4e6ef; padding:10px;">
                    <small style="color:#a1a5b7; display:block; margin-top:5px;">Hold <strong>Ctrl</strong> to select multiple files.</small>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" onclick="closeUploadModal()" style="background:#f1f1f4; color:#7e8299; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">Cancel</button>
                    <button type="submit" name="submit_missing_doc" style="background:var(--primary); color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">Submit All</button>
                </div>
            </form>
                    </div>
                </div>

                <script>
                    function showPanel(panelName) {
                        document.querySelectorAll('.panel').forEach(panel => {
                            panel.classList.remove('active');
                        });
                        
                        // Show selected panel
                        document.getElementById('panel-' + panelName).classList.add('active');
                        
                        // Update menu items
                        document.querySelectorAll('.menu-item').forEach(item => {
                            item.classList.remove('active');
                        });
                        event.target.closest('.menu-item').classList.add('active');
                        
                        // Update page title
                        const titles = {
                            'dashboard': 'Dashboard',
                            'profile': 'My Profile',
                            'history': 'My Applications',
                            'feedback': 'Review Feedback'
                        };
                        document.getElementById('pageTitle').textContent = titles[panelName];
                        }

                function openUploadModal(appID) {
                    document.getElementById('upload_app_id').value = appID;
                    document.getElementById('uploadModal').style.display = 'flex';
                }

                function closeUploadModal() {
                    document.getElementById('uploadModal').style.display = 'none';
                }
                
                window.onclick = function(event) {
                    var modal = document.getElementById('uploadModal');
                    if (event.target == modal) {
                        modal.style.display = "none";
                    }
                }
            </script>
        </body>
    </html>
