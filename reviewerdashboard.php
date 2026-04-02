<?php
session_start();
include('../config/db.php'); 

// Permission check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'reviewer') {
    header("Location: ../login.php");
    exit();
}

$reviewerID = $_SESSION['user_id'];

$status_check_sql = "SELECT status FROM reviewer WHERE reviewerID = '" . mysqli_real_escape_string($conn, $reviewerID) . "' LIMIT 1";
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

// Get current reviewer information
$reviewer_id_raw = $_SESSION['user_id']; 
$reviewer_name = $_SESSION['user_name'];
$assigned_tasks = [];
// Fetch Assigned Tasks
$sql_tasks = "SELECT a.*, s.student_name, sch.scholarship_name, 
                     r.score as reviewer_score, r.comments as reviewer_comment
              FROM reviewerassignment ra 
              JOIN application a ON ra.applicationID = a.applicationID
              JOIN student s ON a.studentID = s.studentID
              JOIN scholarship sch ON a.scholarshipID = sch.scholarshipID
              LEFT JOIN review r ON a.applicationID = r.applicationID AND r.reviewerID = '$reviewer_id_raw'
              WHERE ra.reviewerID = '$reviewer_id_raw' 
              AND r.reviewID IS NULL
              ORDER BY a.submissionDate ASC";

$res_tasks = mysqli_query($conn, $sql_tasks);

if (!$res_tasks) {
    die("Query Failed: " . mysqli_error($conn));
}

$task_documents = [];
while ($row = mysqli_fetch_assoc($res_tasks)) {
    $assigned_tasks[] = $row;
    
    $appID = $row['applicationID'];

    $sql_docs = "SELECT * FROM document WHERE applicationID = '$appID'";
    $res_docs = mysqli_query($conn, $sql_docs);
    
    $docs = [];
    while ($doc_row = mysqli_fetch_assoc($res_docs)) {
        $docs[] = [
            'documentType' => $doc_row['documentType'], 
            'file_name'    => basename($doc_row['file_path']), 
            'uploadedDate' => $doc_row['uploadedDate']
        ];
    }
    
    $task_documents[$appID] = $docs;
}

$rubric_data = [];
$sql_rubric = "SELECT * FROM evaluationrubric ORDER BY ScholarshipID, CriteriaName";
$res_rubric = mysqli_query($conn, $sql_rubric);

while ($r_row = mysqli_fetch_assoc($res_rubric)) {
    $s_id = $r_row['ScholarshipID'];
    $rubric_data[$s_id][] = [
        'name' => $r_row['CriteriaName'],
        'max'  => $r_row['MaxScore'],
        'weight' => $r_row['Weight']
    ];
}

$current_reviewer_id = (is_numeric($reviewer_id_raw)) 
    ? "R" . str_pad($reviewer_id_raw, 3, "0", STR_PAD_LEFT) 
    : $reviewer_id_raw;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_info'])) {
    $appID = $_POST['application_id'];
    $studentID = $_POST['student_id'];
    $message = $_POST['request_message']; 

    $update_stmt = $conn->prepare("UPDATE application SET remark = 'Pending Info' WHERE applicationID = ?");
    
    $update_stmt->bind_param("s", $appID);
    
    if ($update_stmt->execute()) {
        $n_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM notification");
        $n_data = mysqli_fetch_assoc($n_query);
        $new_notif_id = "N" . str_pad($n_data['total'] + 1, 3, "0", STR_PAD_LEFT);
        $notif_msg = "Reviewer requested info: " . $message;  
        $notif_stmt = $conn->prepare("INSERT INTO notification (studentID, reviewerID, message, isRead) VALUES (?, ?, ?, 0)");
        $notif_stmt->bind_param("sss", $studentID, $reviewer_id_raw, $notif_msg);
        $notif_stmt->execute();

        echo "<script>alert('Request sent to student. Status updated to Pending Info.'); window.location.href='dashboard.php?view=tasks';</script>";
    } else {
        echo "<script>alert('Error sending request.');</script>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $appID = mysqli_real_escape_string($conn, $_POST['application_id']);
    $score = mysqli_real_escape_string($conn, $_POST['score']);
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);
    $date = date('Y-m-d');
    $reviewer_id_db = $reviewer_id_raw; 

    $sql_review = "INSERT INTO review (applicationID, reviewerID, score, comments, review_Date, review_status) 
                   VALUES ('$appID', '$reviewer_id_db', '$score', '$comment', '$date', 'Completed')";

    if (mysqli_query($conn, $sql_review)) {
        $sql_update = "UPDATE application SET application_status = 'Under Committee Review' WHERE applicationID = '$appID'";
        mysqli_query($conn, $sql_update);
        
        echo "<script>alert('Review submitted! Application sent to Committee for final decision.'); window.location.href='dashboard.php';</script>";
        exit();
    } else {
        die("Database Error: " . mysqli_error($conn));
    }
}

$history_records = [];
$sql_history = "
    SELECT r.applicationID, r.score, r.comments, r.review_Date, s.student_name, sch.scholarship_name
    FROM review r
    JOIN application a ON r.applicationID = a.applicationID
    JOIN student s ON a.studentID = s.studentID
    JOIN scholarship sch ON a.scholarshipID = sch.scholarshipID
    WHERE r.reviewerID = '$reviewer_id_raw'
    ORDER BY r.review_Date DESC
";
$result_history = mysqli_query($conn, $sql_history);
if ($result_history) {
    while($row = mysqli_fetch_assoc($result_history)) {
        $history_records[] = $row;
    }
}

function fetchOneRow($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    if (!$res) return null;
    return mysqli_fetch_assoc($res);
}
function fetchCount($conn, $sql) {
    $row = fetchOneRow($conn, $sql);
    return (int)($row['c'] ?? 0);
}
function fetchAvg($conn, $sql) {
    $row = fetchOneRow($conn, $sql);
    if (!$row) return 0;
    return (float)($row['a'] ?? 0);
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
    FROM reviewer
    WHERE reviewerID = '" . mysqli_real_escape_string($conn, (string)$current_reviewer_id) . "'
       OR reviewerID = '" . mysqli_real_escape_string($conn, (string)$reviewer_id_raw) . "'
    LIMIT 1
";
$profile_result = mysqli_query($conn, $profile_sql);
if ($profile_result) {
    $profile = mysqli_fetch_assoc($profile_result) ?? [];
}

$reviewer_fullname = pick($profile, ['reviewer_name', 'reviewerName'], $reviewer_name);
$reviewer_email    = pick($profile, ['reviewer_email', 'reviewerEmail'], '');
$reviewer_phone    = pick($profile, ['reviewer_phonenum', 'reviewerPhone'], '');
$reviewer_address  = pick($profile, ['reviewer_address', 'reviewerAddress'], '');
$totalAssigned = fetchCount($conn, "SELECT COUNT(*) AS c FROM reviewerassignment WHERE reviewerID = '$reviewer_id_raw'");
$pendingTasks = 0; 
$pendingInfo = 0;  

foreach ($assigned_tasks as $task) {
    if (isset($task['remark']) && $task['remark'] == 'Pending Info') {
        $pendingInfo++;
    } else {
        $pendingTasks++;
    }
}
$completedReviews = fetchCount($conn, "SELECT COUNT(*) AS c FROM review WHERE reviewerID = '$reviewer_id_raw'");
$avgScore = fetchAvg($conn, "SELECT AVG(score) AS a FROM review WHERE reviewerID = '$reviewer_id_raw'");
$avgScoreDisplay = number_format($avgScore, 1);
$reviewsThisWeek = fetchCount($conn, "
    SELECT COUNT(*) AS c 
    FROM review 
    WHERE reviewerID = '$reviewer_id_raw'
    AND YEARWEEK(review_Date, 1) = YEARWEEK(CURDATE(), 1)
");

$recent_reviews = [];
if (!empty($history_records)) {
    $recent_reviews = array_slice($history_records, 0, 5); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reviewer Portal - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { 
            --primary: #009ef7; --dark: #1e1e2d; --bg: #f5f8fa; 
            --text-main: #181c32; --text-muted: #a1a5b7;
            --success-bg: #e8fff3; --success-text: #50cd89;
            --warning-bg: #fff8dd; --warning-text: #ffc700;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', sans-serif; display: flex; background: var(--bg); color: var(--text-main); }
        .sidebar { width: 265px; background: var(--dark); height: 100vh; position: fixed; }
        .sidebar-brand { padding: 40px 25px; color: white; font-weight: 700; font-size: 1.1rem; line-height: 1.25; border-bottom: 1px solid rgba(255,255,255,0.1);}
        .menu-item { padding: 12px 25px; cursor: pointer; color: #a2a3b7; display: flex; align-items: center; transition: 0.2s; font-size: 0.9rem; }
        .menu-item:hover, .menu-item.active { background: #2b2b40; color: white; }
        .menu-item i { margin-right: 15px; width: 20px; }

        .main { margin-left: 265px; flex: 1; padding: 35px; min-width:0; }

        .topbar { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:25px; gap:15px; }
        .page-title { font-size: 1.8rem; font-weight: 900; margin:0; }
        .idbox { text-align:right; white-space:nowrap; }
        .idbox span { font-weight: 800; display:block; }
        .idbox small { color: var(--text-muted); }

        .card { background: white; border-radius: 12px; padding: 30px; border: 1px solid #eff2f5; box-shadow: 0 0 20px rgba(0,0,0,0.02); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; border-bottom: 1px dotted #e4e6ef; white-space: nowrap; }
        td { padding: 20px 15px; border-bottom: 1px solid #f1f1f4; font-size: 0.9rem; vertical-align: top; }

        .stat-grid { display:grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-top: 10px; }
        .stat-card { background:#fff; border:1px solid #eff2f5; border-radius: 14px; padding: 24px; box-shadow: 0 0 20px rgba(0,0,0,0.02); }
        .stat-icon {
            width: 54px; height: 54px; border-radius: 14px;
            background: #f5f8fa; display:flex; align-items:center; justify-content:center;
            border: 1px solid #eff2f5;
            margin-bottom: 18px;
        }
        .stat-icon i { font-size: 1.3rem; color: var(--primary); }
        .stat-value { font-size: 2.3rem; font-weight: 900; color: var(--primary); line-height: 1; }
        .stat-label { margin-top: 10px; letter-spacing: 0.02em; text-transform: uppercase; font-size: 0.85rem; color: var(--text-muted); }

        .status-badge { padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; display: inline-block; }
        .status-Pending-Review { background: var(--warning-bg); color: var(--warning-text); }
        .status-Pending-Info { background: #e0f2fe; color: #009ef7; }
        .status-Reviewed { background: var(--success-bg); color: var(--success-text); }

        .btn { border: none; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 700; display:inline-flex; align-items:center; gap:8px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-outline { background:#f5f8fa; color:#7e8299; border:1px solid #e4e6ef; }
        .btn-success { background: #10b981; color: white; }

        .badge-pending-review {
            background-color: #fff8dd; 
            color: #ffc700;           
            padding: 6px 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.75rem;
            display: inline-block;
        }

        .btn-blue { background-color: #009ef7; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
        .btn-orange { background-color: #ffc700; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }
        .btn-green { background-color: #50cd89; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; }

        .btn-blue:hover { background-color: #0086d1; }
        .btn-orange:hover { background-color: #e5b300; }
        .btn-green:hover { background-color: #44ad74; }

        .page-panel { display: none; }
        .page-panel.active { display: block; }
        .muted { color: var(--text-muted); }

        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); z-index:1000; justify-content:center; align-items:center; backdrop-filter: blur(4px); padding:20px;}
        .modal-content { background:white; width:500px; max-width:95vw; border-radius:12px; padding:30px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        input, textarea { width: 100%; padding: 12px; border: 1px solid #e4e6ef; border-radius: 8px; background: #f9f9f9; margin-top: 8px; font-family: inherit; box-sizing: border-box;}
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; width: 100%; margin: 0; }
        .form-group { margin-bottom: 20px; }
        .profile-label { display: block; margin-bottom: 12px; font-weight: 700; color: var(--text-main); font-size: 0.8rem; text-transform: uppercase; }
        
        .doc-link { display: block; padding: 10px; background: #f9f9f9; margin-bottom: 5px; border-radius: 6px; text-decoration: none; color: #333; border: 1px solid #eee; }
        .doc-link:hover { background: #eee; }

        .rubric-row { display: flex; gap: 10px; margin-bottom: 5px; }
        .rubric-item { flex: 1; }
        .rubric-item label { font-size: 0.75rem; font-weight: 700; color: #555; }
        .rubric-item input { margin-top: 5px; text-align:center; }
        .help-row { margin-top: 10px; display: flex; align-items: center; gap: 8px; color: var(--text-muted); font-size: 0.85rem; }

        @media (max-width: 1100px){
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
            .idbox { text-align:left; }
        }
        @media (max-width: 900px){
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">Digital Scholarship<br>Application and Track<br>System</div>

    <div class="menu-item active" id="menu-dashboard" onclick="showPanel('dashboard')">
        <i class="fas fa-gauge"></i> Dashboard
    </div>

    <div class="menu-item" id="menu-tasks" onclick="showPanel('tasks')">
        <i class="fas fa-list-ul"></i> Assigned Tasks
    </div>

    <div class="menu-item" id="menu-history" onclick="showPanel('history')">
        <i class="fas fa-history"></i> My History
    </div>

    <div class="menu-item" id="menu-profile" onclick="showPanel('profile')">
        <i class="fas fa-user-circle"></i> My Profile
    </div>

    <div class="menu-item" style="position: absolute; bottom: 20px; width: 100%; box-sizing: border-box;"
         onclick="window.location.href='../logout.php'">
        <i class="fas fa-sign-out-alt"></i> Logout
    </div>
</div>

<div class="main">
    <div class="topbar">
        <h1 class="page-title" id="page-title">Reviewer Dashboard</h1>
        <div class="idbox">
            <span>ID: <?php echo htmlspecialchars($current_reviewer_id); ?></span>
            <small>Reviewer: <?php echo htmlspecialchars($reviewer_name); ?></small>
        </div>
    </div>

    <div id="dashboard-panel" class="page-panel active">
        <div class="stat-grid" style="margin-bottom:18px;">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
                <div class="stat-value"><?php echo $totalAssigned; ?></div>
                <div class="stat-label">Total Assigned</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-value"><?php echo $pendingTasks; ?></div>
                <div class="stat-label">Pending Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-circle-question"></i></div>
                <div class="stat-value"><?php echo $pendingInfo; ?></div>
                <div class="stat-label">Pending Info</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-star"></i></div>
                <div class="stat-value"><?php echo $avgScoreDisplay; ?></div>
                <div class="stat-label">Average Score</div>
                <div class="muted" style="margin-top:10px;">This week: <?php echo $reviewsThisWeek; ?> reviews</div>
            </div>
        </div>

        <div class="card">
            <h2 style="margin:0 0 12px;">Recent Reviewer Activity</h2>
            <div style="margin-top: 18px;">
                <div style="font-weight:800; margin-bottom: 10px;">Quick Actions:</div>
                <div style="display:flex; gap: 10px; flex-wrap: wrap;">
                    <button class="btn btn-primary" type="button" onclick="showPanel('tasks')">
                        <i class="fas fa-list-ul"></i> View Assigned Tasks
                    </button>
                    <button class="btn btn-outline" type="button" onclick="showPanel('history')">
                        <i class="fas fa-history"></i> View Review History
                    </button>
                </div>
            </div>

            <div style="margin-top: 22px;">
                <div style="font-weight:800; margin-bottom: 10px;">Recent Reviews:</div>
                <?php if (empty($recent_reviews)): ?>
                    <p class="muted" style="margin:0;">No review records found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr><th>App ID</th><th>Student</th><th>Scholarship</th><th>Score</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_reviews as $rr): ?>
                                <tr>
                                    <td><span style="font-weight:700; color:var(--primary);"><?php echo htmlspecialchars($rr['applicationID']); ?></span></td>
                                    <td><?php echo htmlspecialchars($rr['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($rr['scholarship_name']); ?></td>
                                    <td><span style="font-weight:700; color:var(--primary);"><?php echo htmlspecialchars($rr['score']); ?></span>/100</td>
                                    <td><?php echo htmlspecialchars($rr['review_Date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="tasks-panel" class="page-panel">
    <div class="card">
        <?php if (empty($assigned_tasks)): ?>
            <p style="text-align:center; color:var(--text-muted); padding: 20px;">No applications assigned to you.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>SCHOLARSHIP</th>
                        <th>STUDENT NAME</th>
                        <th>SUBMISSION DATE</th> 
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assigned_tasks as $task): ?>
                        <tr>
                            <td>
                                <div style="color: #009ef7; font-weight: bold; font-size: 0.85rem;">
                                    AP<?php echo str_pad($task['applicationID'], 2, "0", STR_PAD_LEFT); ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #7e8299;">
                                    <?php echo htmlspecialchars($task['scholarship_name']); ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($task['student_name']); ?></td>
<td><?php echo htmlspecialchars($task['submissionDate']); ?></td>

<td>
    <?php if ($task['remark'] == 'Pending Info'): ?>
        <span style="background:#e0f2fe; color:#009ef7; padding:6px 12px; border-radius:8px; font-weight:700; font-size:0.75rem;">
            Pending Info
        </span>
    <?php else: ?>
        <span class="badge-pending-review">Pending Review</span>
    <?php endif; ?>
</td>

<td>
    <button class="btn-blue" onclick="openDocsModal('<?php echo $task['applicationID']; ?>')">
        <i class="fas fa-file-alt"></i> Docs
    </button>

  <?php if ($task['remark'] == 'Pending Info'): ?>
        <button disabled style="background-color: #7e8299; color: white; border: none; padding: 8px 12px; border-radius: 4px; cursor: not-allowed; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fas fa-check"></i> Requested
        </button>

    <?php else: ?>
        <button class="btn-orange" onclick="openRequestModal('<?php echo $task['applicationID']; ?>', '<?php echo $task['studentID']; ?>', '<?php echo addslashes($task['student_name']); ?>')">
            <i class="fas fa-info-circle"></i> Info
        </button>
    <?php endif; ?>

    <button class="btn-green" onclick="openEval('<?php echo $task['applicationID']; ?>', '<?php echo addslashes($task['student_name']); ?>', '<?php echo $task['scholarshipID']; ?>')">
        <i class="fas fa-star"></i> Eval
    </button>
</td>
</tr>
<?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <div id="history-panel" class="page-panel">
        <div class="card">
            <?php if(empty($history_records)): ?>
                <p style="text-align:center; color:var(--text-muted); padding: 20px;">No completed reviews found.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>Scholarship</th><th>Student</th><th>Score</th><th>Comment</th><th>Reviewed Date</th></tr></thead>
                <tbody>
                    <?php foreach($history_records as $hist): ?>
                    <tr>
                        <td><span style="font-weight:700;"><?php echo $hist['scholarship_name']; ?></span></td>
                        <td><?php echo $hist['student_name']; ?></td>
                        <td><span style="color:var(--primary); font-weight:800; font-size:1.1rem;"><?php echo $hist['score']; ?></span>/100</td>
                        <td style="font-style:italic; color:#5e6278;"><?php echo $hist['comments']; ?></td>
                        <td><?php echo $hist['review_Date']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
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
                    <input type="text" value="<?php echo htmlspecialchars($reviewer_fullname); ?>" disabled style="background: #f8f9fa;">
                </div>
                <div class="form-group">
                    <label class="profile-label">Reviewer ID</label>
                    <input type="text" value="<?php echo htmlspecialchars($current_reviewer_id); ?>" disabled style="background: #f8f9fa;">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="profile-label">Email Address</label>
                    <input type="text" value="<?php echo htmlspecialchars($reviewer_email); ?>" disabled style="background: #f8f9fa;">
                </div>
                <div class="form-group">
                    <label class="profile-label">Phone Number</label>
                    <input type="text" value="<?php echo htmlspecialchars($reviewer_phone); ?>" disabled style="background: #f8f9fa;">
                </div>
            </div>

            <div class="form-group">
                <label class="profile-label">Home / Office Address</label>
                <textarea disabled style="height: 100px; background: #f8f9fa; resize: none;"><?php echo htmlspecialchars($reviewer_address); ?></textarea>
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

<div id="docsModal" class="modal">
    <div class="modal-content">
        <h3>Supporting Documents</h3>
        <div id="docsList" style="margin: 20px 0;"></div>
        <div style="text-align:right;">
            <button class="btn btn-outline" type="button" onclick="closeModal('docsModal')">Close</button>
        </div>
    </div>
</div>

<div id="requestModal" class="modal">
    <div class="modal-content">
        <form method="POST">
            <h3>Request Info: <span id="reqStudentName" style="color:var(--primary)"></span></h3>
            <input type="hidden" name="application_id" id="reqAppID">
            <input type="hidden" name="student_id" id="reqStudentID">
            <div style="margin-top:15px;">
                <label style="font-weight:700; font-size:0.8rem;">What is missing?</label>
                <textarea name="request_message" rows="4" required placeholder="E.g., Please upload your certified transcript..."></textarea>
            </div>
            <div style="margin-top:25px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('requestModal')">Cancel</button>
                <button type="submit" name="request_info" class="btn btn-success">Send Request</button>
            </div>
        </form>
    </div>
</div>

<div id="evalModal" class="modal">
    <div class="modal-content">
        <form method="POST" action="">
            <h3>Evaluate: <span id="targetName" style="color: var(--primary);"></span></h3>
            <input type="hidden" name="application_id" id="modalAppID">
            
            <div style="background: #f1f1f4; padding: 10px; border-radius: 8px; text-align: center; margin-top: 15px;">
                <label style="font-weight: 800; font-size: 0.9rem; color: #555;">TOTAL SCORE</label>
                <div style="display:flex; justify-content:center; align-items:baseline;">
                    <input type="number" name="score" id="totalInput" readonly value="0" 
                           style="width: 80px; font-size: 2rem; font-weight: 900; color: var(--primary); text-align: right; border: none; background: transparent; padding:0; margin:0;">
                    <span style="font-size: 1.2rem; font-weight: 700; color: #999;">/ 100</span>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <div style="font-weight:700; margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:5px;">Scoring Criteria:</div>
    
                <div id="dynamicRubricContainer" class="rubric-row" style="flex-wrap: wrap;">
                    </div>
            </div>
            
            <div style="margin-top:15px;">
                <label style="font-weight:700; font-size:0.8rem;">Comments</label>
                <textarea name="comment" rows="3" required placeholder="Justify the score..."></textarea>
            </div>
            
            <div style="margin-top:25px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-outline" onclick="closeModal('evalModal')">Cancel</button>
                <button type="submit" name="submit_review" class="btn btn-success">Submit Review</button>
            </div>
        </form>
    </div>
</div>

<script>
    const allDocs = <?php echo json_encode($task_documents); ?>;
    const allRubrics = <?php echo json_encode($rubric_data); ?>;

    // Auto-Switch Tab Logic 
    document.addEventListener("DOMContentLoaded", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const view = urlParams.get('view');
        if (view === 'tasks') {
            showPanel('tasks');
        }
    });

    //Switch dashboard panels                  
    function showPanel(type) {
        document.querySelectorAll('.page-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));

        const panel = document.getElementById(type + '-panel');
        const menu  = document.getElementById('menu-' + type);

        if (panel) panel.classList.add('active');
        if (menu) menu.classList.add('active');

        if (type === 'dashboard') document.getElementById('page-title').innerText = 'Reviewer Dashboard';
        else if (type === 'tasks') document.getElementById('page-title').innerText = 'Assigned Tasks';
        else if (type === 'history') document.getElementById('page-title').innerText = 'My History';
        else if (type === 'profile') document.getElementById('page-title').innerText = 'My Profile';
    }

//Open documents popup 
function openDocsModal(appID) {
        const listDiv = document.getElementById('docsList');
        listDiv.innerHTML = '';
        const docs = allDocs[appID];
        
        if (docs && docs.length > 0) {
            docs.forEach(doc => {
                // Filename Cleanup
                let originalName = doc.file_name.replace(/^\d+_\d+_/, '');
                originalName = originalName.replace('supplementary_', '');
                // "NEW" Badge Logic
                let newBadge = '';
                if (doc.documentType === 'Supplementary Document') {
                    newBadge = '<span style="background: #ffe2e5; color: #f1416c; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: 800; margin-left: 8px;">NEW</span>';
                }

                const link = document.createElement('a');
                link.href = '../uploads/' + doc.file_name;
                link.className = 'doc-link';
                link.target = '_blank';
                
                link.innerHTML = `
                    <div style="display:flex; align-items:center; gap:12px;">
                        <i class="fas fa-file-alt" style="font-size:1.4rem; color:#009ef7;"></i>
                        <div>
                            <div style="font-weight:700; color:#3f4254; font-size:0.95rem; display:flex; align-items:center;">
                                ${originalName} ${newBadge}
                            </div>
                            <div style="font-size:0.75rem; color:#a1a5b7;">
                                ${doc.documentType} • <i class="far fa-clock"></i> ${doc.uploadedDate}
                            </div>
                        </div>
                    </div>
                `;
                listDiv.appendChild(link);
            });
        } else {
            listDiv.innerHTML = '<div style="padding:20px; text-align:center; color:#a1a5b7;"><i class="fas fa-folder-open" style="font-size:2rem; margin-bottom:10px;"></i><br>No documents found.</div>';
        }
        document.getElementById('docsModal').style.display = 'flex';
    }

    //Open request info popup
    function openRequestModal(appID, studentID, studentName) {
        document.getElementById('reqAppID').value = appID;
        document.getElementById('reqStudentID').value = studentID;
        document.getElementById('reqStudentName').innerText = studentName;
        document.getElementById('requestModal').style.display = 'flex';
    }

    //Open evaluation popup
    function openEval(appID, studentName, scholarshipID) {
        document.getElementById('modalAppID').value = appID;
        document.getElementById('targetName').innerText = studentName;
        document.getElementById('totalInput').value = 0;

        const container = document.getElementById('dynamicRubricContainer');
        container.innerHTML = ''; 

        const criteriaList = allRubrics[scholarshipID] || [];

        if (criteriaList.length === 0) {
            container.innerHTML = '<p style="color:red;">Error: No rubric found for this scholarship ID ('+scholarshipID+'). Please contact Admin.</p>';
        } else {
            criteriaList.forEach((c, index) => {
                const percentage = Math.round(c.weight * 100);
                
                const div = document.createElement('div');
                div.className = 'rubric-item';
                div.style.minWidth = '30%'; 
                div.style.marginBottom = '10px';

                div.innerHTML = `
                    <label>${c.name} (${percentage}%)</label>
                    <input type="number" 
                           class="rubric-input" 
                           data-weight="${c.weight}" 
                           min="0" 
                           max="${c.max}" 
                           placeholder="Max ${c.max}" 
                           oninput="calculateTotal()" 
                           required>
                `;
                container.appendChild(div);
            });
        }

        document.getElementById('evalModal').style.display = 'flex';
    }

    //Calculate total score based on rubric inputs
    function calculateTotal() {
        let totalScore = 0;
        const inputs = document.querySelectorAll('.rubric-input');

        inputs.forEach(input => {
            let val = parseFloat(input.value) || 0;
            let max = parseFloat(input.getAttribute('max'));
            let weight = parseFloat(input.getAttribute('data-weight'));

            if (val > max) {
                val = max;
                input.value = max; 
            }
            let weightedScore = (val / max) * (weight * 100);
            totalScore += weightedScore;
        });

        document.getElementById('totalInput').value = Math.round(totalScore);
    }

    function closeModal(id) { 
        document.getElementById(id).style.display = 'none'; 
    }
</script>

</body>
</html>