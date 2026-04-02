<?php
session_start();
include('../config/db.php');

/* =========================
    Status Check
========================= */

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'committee') {
    header("Location: ../login.php");
    exit();
}

$userID = $_SESSION['user_id'];

$status_check_sql = "SELECT status FROM committee WHERE committeeID = '" . mysqli_real_escape_string($conn, $userID) . "' LIMIT 1";
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

/* =========================
   Helpers
========================= */
function fetchCount($conn, $sql) {
    $res = mysqli_query($conn, $sql);
    if (!$res) return 0;
    $row = mysqli_fetch_assoc($res);
    return (int)($row['c'] ?? 0);
}

function statusBadgeClass($text) {
    $t = strtolower(trim((string)$text));
    if ($t === 'reviewed') return 'status-reviewed';
    if ($t === 'rejected') return 'status-rejected';
    if ($t === 'approved') return 'status-approved';
    if ($t === 'pending info') return 'status-info';
    if ($t === 'under committee review') return 'status-reviewed';
    return 'status-pending';
}

function decisionBadgeClass($text) {
    $t = strtolower(trim((string)$text));
    if ($t === 'approved') return 'decision-approved';
    if ($t === 'rejected') return 'decision-rejected';
    return 'decision-pending';
}

function pick($arr, $keys, $default = '') {
    if (!is_array($arr)) return $default;
    foreach ($keys as $k) {
        if (array_key_exists($k, $arr) && $arr[$k] !== null) return $arr[$k];
    }
    return $default;
}

/* =========================
   Security
========================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'committee') {
    header("Location: ../login.php");
    exit();
}

/* =========================
   Session variables
========================= */
$committeeID_raw = $_SESSION['user_id'] ?? '';
$committeeName   = $_SESSION['user_name'] ?? '';

$committeeID = (is_numeric($committeeID_raw))
    ? "C" . str_pad($committeeID_raw, 3, "0", STR_PAD_LEFT)
    : $committeeID_raw;

/* =========================
   Handle decision submission 
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_decision'])) {

    $appID    = mysqli_real_escape_string($conn, trim($_POST['applicationID'] ?? ''));
    $decision = mysqli_real_escape_string($conn, trim($_POST['decision'] ?? ''));
    $remark   = mysqli_real_escape_string($conn, trim($_POST['remark'] ?? ''));
    $date     = date('Y-m-d');

    // Save decision + remark, set decisionDate, and keep status under committee review until publish
    $sql = "UPDATE application SET 
                committeeDecision = '$decision', 
                remark = '$remark', 
                decisionDate = '$date'
            WHERE applicationID = '$appID'";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['flash_tasks'] = ['type'=>'success','msg'=>'Decision saved successfully!'];
    } else {
        $_SESSION['flash_tasks'] = ['type'=>'error','msg'=>'Save failed: ' . mysqli_error($conn)];
    }
    header("Location: dashboard.php?panel=tasks");
    exit();
}

/* =========================
   Publish results
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_results'])) {

    $not_published_cond = "COALESCE(TRIM(application_status),'') NOT IN ('Approved','Rejected')";

    $pending_to_decide = fetchCount($conn, "
        SELECT COUNT(*) AS c
        FROM application
        WHERE $not_published_cond
          AND (committeeDecision IS NULL
               OR TRIM(committeeDecision) = ''
               OR TRIM(committeeDecision) = 'pending')
    ");

    if ($pending_to_decide > 0) {
        $_SESSION['flash_publish'] = [
            'type' => 'error',
            'msg'  => "Cannot publish: still have $pending_to_decide pending decision(s)."
        ];
        header("Location: dashboard.php?panel=publish");
        exit();
    }

    $to_publish = [];

    $res_list = mysqli_query($conn, "
        SELECT a.applicationID, a.studentID, TRIM(a.committeeDecision) AS committeeDecision, sch.scholarship_name
        FROM application a
        JOIN scholarship sch ON a.scholarshipID = sch.scholarshipID
        WHERE TRIM(a.committeeDecision) IN ('Approved','Rejected') 
        AND a.application_status NOT IN ('Approved','Rejected') 
");
    if ($res_list) {
        while ($r = mysqli_fetch_assoc($res_list)) $to_publish[] = $r;
    }

    if (count($to_publish) === 0) {
        $_SESSION['flash_publish'] = ['type' => 'error', 'msg' => "No applications ready to publish."];
        header("Location: dashboard.php?panel=publish");
        exit();
    }

    $ok = mysqli_query($conn, "
        UPDATE application
        SET application_status = CASE
            WHEN TRIM(committeeDecision) = 'Approved' THEN 'Approved'
            WHEN TRIM(committeeDecision) = 'Rejected' THEN 'Rejected'
            ELSE application_status
        END
        WHERE $not_published_cond
          AND TRIM(committeeDecision) IN ('Approved','Rejected')
    ");

    if (!$ok) {
        $_SESSION['flash_publish'] = [
            'type' => 'error',
            'msg'  => "Publish failed: " . mysqli_error($conn)
        ];
        header("Location: dashboard.php?panel=publish");
        exit();
    }

    $affected = mysqli_affected_rows($conn);

    $sent = 0;

    $sel = $conn->prepare("
        SELECT notificationID
        FROM notification
        WHERE studentID = ?
        AND message LIKE CONCAT('%', ?, '%')
        ORDER BY notificationID DESC
        LIMIT 1
    ");
    if (!$sel) die("SEL prepare failed: " . $conn->error);

    $upd = $conn->prepare("
        UPDATE notification
        SET message = ?, isRead = 0
        WHERE notificationID = ?
    ");
    if (!$upd) die("UPD prepare failed: " . $conn->error);

    $ins = $conn->prepare("
        INSERT INTO notification (studentID, message, isRead)
        VALUES (?, ?, 0)
    ");
    if (!$ins) die("INS prepare failed: " . $conn->error);


foreach ($to_publish as $p) {
    $studentID = (int)$p['studentID'];
    $decision  = ucfirst(strtolower(trim($p['committeeDecision'])));
    $schName   = trim($p['scholarship_name']);
    
    $notif_msg = "Final Result: Your application for {$schName} is {$decision}. View in My Applications.";

    $ins = $conn->prepare("INSERT INTO notification (studentID, message, isRead) VALUES (?, ?, 0)");
    $ins->bind_param("is", $studentID, $notif_msg);
    if ($ins->execute()) $sent++;
}

    $sel->close();
    $upd->close();
    $ins->close();

    $_SESSION['flash_publish'] = [
        'type' => 'success',
        'msg'  => "Results published and notifications sent to $sent students."
    ];

    header("Location: dashboard.php?panel=publish");
    exit();
}

/* =========================
   Read flash message
========================= */
$publish_msg = "";
if (!empty($_SESSION['flash_publish'])) {
    $f = $_SESSION['flash_publish'];
    unset($_SESSION['flash_publish']);

    $color = ($f['type'] === 'success') ? '#28a745' : '#dc3545';
    $icon  = ($f['type'] === 'success') ? 'check-circle' : 'exclamation-triangle';
    
    $display_text = ($f['type'] === 'success') 
        ? "Success: Results published and students notified." 
        : "Error: " . htmlspecialchars($f['msg']);

    $publish_msg = "
        <div style='background: #f8f9fa; border-left: 5px solid $color; padding: 15px; margin-bottom: 20px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
            <div style='color: $color; font-weight: bold; font-size: 1.1em;'>
                <i class='fas fa-$icon'></i> $display_text
            </div>
        </div>
    ";
}

/* =========================
   Publish summary counts
========================= */
$not_published_cond = "COALESCE(TRIM(application_status),'') NOT IN ('Approved','Rejected')";

$pending_to_decide = fetchCount($conn, "
    SELECT COUNT(*) AS c
    FROM application
    WHERE $not_published_cond
      AND (
          LOWER(TRIM(committeeDecision)) NOT IN ('approved', 'rejected') 
          OR committeeDecision IS NULL 
          OR TRIM(committeeDecision) = ''
      )
");

$ready_approved = fetchCount($conn, "
    SELECT COUNT(*) AS c
    FROM application
    WHERE $not_published_cond
      AND LOWER(TRIM(committeeDecision)) = 'approved'
");

$ready_rejected = fetchCount($conn, "
    SELECT COUNT(*) AS c
    FROM application
    WHERE $not_published_cond
      AND LOWER(TRIM(committeeDecision)) = 'rejected'
");

/* =========================
   Profile (committee)
========================= */
$profile = [];
$profile_sql = "
    SELECT *
    FROM committee
    WHERE committeeID = '" . mysqli_real_escape_string($conn, (string)$committeeID) . "'
       OR committeeID = '" . mysqli_real_escape_string($conn, (string)$committeeID_raw) . "'
    LIMIT 1
";
$profile_result = mysqli_query($conn, $profile_sql);
if ($profile_result) {
    $profile = mysqli_fetch_assoc($profile_result) ?? [];
}

$committee_fullname = pick($profile, ['committeeName', 'committee_name', 'name', 'fullName'], $committeeName);
$committee_email    = pick($profile, ['committeeEmail', 'committee_email', 'email'], '');
$committee_phone    = pick($profile, ['committeePhone', 'committee_phone', 'committee_phonenum', 'phone', 'phoneNumber'], '');
$committee_address  = pick($profile, ['committeeAddress', 'committee_address', 'address'], '');

/* =========================
   Home summary
========================= */
$totalApps       = fetchCount($conn, "SELECT COUNT(*) AS c FROM application");

$pendingDecision = fetchCount($conn, "
    SELECT COUNT(*) AS c
    FROM application
    WHERE COALESCE(TRIM(application_status),'') NOT IN ('Approved','Rejected')
      AND (
        committeeDecision IS NULL
        OR TRIM(committeeDecision) = ''
        OR TRIM(committeeDecision) = 'Pending'
      )
");

$approvedCount = fetchCount($conn, "SELECT COUNT(*) AS c FROM application 
    WHERE TRIM(committeeDecision) = 'Approved'");

$rejectedCount = fetchCount($conn, "SELECT COUNT(*) AS c FROM application 
    WHERE TRIM(committeeDecision) = 'Rejected'");

$recent_activity = [];
$sql_recent = "
    SELECT applicationID, studentID, committeeDecision, decisionDate
    FROM application
    WHERE committeeDecision IS NOT NULL
      AND TRIM(committeeDecision) <> ''
      AND TRIM(committeeDecision) <> 'Pending'
    ORDER BY decisionDate DESC
    LIMIT 5
";
$res_recent = mysqli_query($conn, $sql_recent);
if ($res_recent) {
    while ($r = mysqli_fetch_assoc($res_recent)) $recent_activity[] = $r;
}

/* =========================
   Assigned tasks
========================= */
$sql_tasks = "
    SELECT a.*, s.student_name, sch.scholarship_name, 
           r.score as reviewer_score, r.comments as reviewer_comment,
           af.academicRecords, af.financialStatus
    FROM application a
    LEFT JOIN student s ON a.studentID = s.studentID
    LEFT JOIN scholarship sch ON a.scholarshipID = sch.scholarshipID
    LEFT JOIN review r ON a.applicationID = r.applicationID
    LEFT JOIN applicationform af ON a.applicationID = af.applicationID
    WHERE COALESCE(TRIM(a.application_status),'') NOT IN ('Approved','Rejected')
      AND (a.committeeDecision IS NULL OR TRIM(a.committeeDecision) = '' OR TRIM(a.committeeDecision) = 'Pending')
    ORDER BY a.submissionDate ASC
";
$res_tasks = mysqli_query($conn, $sql_tasks);
$all_apps = [];
if ($res_tasks) {
    while ($row = mysqli_fetch_assoc($res_tasks)) $all_apps[] = $row;
}

/* =========================
   History
========================= */
$sql_history = "
    SELECT a.*, s.student_name
    FROM application a
    JOIN student s ON a.studentID = s.studentID
    WHERE TRIM(a.committeeDecision) IN ('Approved', 'Rejected')
    ORDER BY a.decisionDate DESC
";
$res_hist = mysqli_query($conn, $sql_history);
$history_apps = [];
if ($res_hist) {
    while ($r = mysqli_fetch_assoc($res_hist)) $history_apps[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Committee Portal - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        :root {
            --primary: #009ef7;
            --dark: #1e1e2d;
            --bg: #f5f8fa;
            --text-main: #181c32;
            --text-muted: #a1a5b7;

            --success-bg: #e8fff3; --success-text: #50cd89;
            --warning-bg: #fff8dd; --warning-text: #ffc700;
            --danger-bg:  #fff5f8; --danger-text:  #f1416c;

            --info-bg: #e0f2fe; --info-text: #009ef7;

            --card-border: #eff2f5;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            display: flex;
            background: var(--bg);
            color: var(--text-main);
        }

        .sidebar {
            width: 265px;
            background: var(--dark);
            height: 100vh;
            position: fixed;
        }
        .sidebar-brand {
            padding: 40px 25px;
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            line-height: 1.25;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .menu-item {
            padding: 12px 25px;
            cursor: pointer;
            color: #a2a3b7;
            display: flex;
            align-items: center;
            transition: 0.2s;
            font-size: 0.9rem;
        }
        .menu-item:hover, .menu-item.active {
            background: #2b2b40;
            color: white;
        }
        .menu-item i { margin-right: 15px; width: 20px; }

        .main {
            margin-left: 265px;
            flex: 1;
            padding: 35px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            border: 1px solid var(--card-border);
            box-shadow: 0 0 20px rgba(0,0,0,0.02);
        }

        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left;
            padding: 15px;
            color: var(--text-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            border-bottom: 1px dotted #e4e6ef;
            white-space: nowrap;
        }
        td {
            padding: 20px 15px;
            border-bottom: 1px solid #f1f1f4;
            font-size: 0.9rem;
            vertical-align: top;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }
        .status-pending  { background: var(--warning-bg); color: var(--warning-text); }
        .status-reviewed { background: var(--success-bg); color: var(--success-text); }
        .status-approved { background: var(--success-bg); color: var(--success-text); }
        .status-rejected { background: var(--danger-bg);  color: var(--danger-text); }
        .status-info     { background: var(--info-bg);    color: var(--info-text); }

        .decision-pending  { background: var(--warning-bg); color: var(--warning-text); }
        .decision-approved { background: var(--success-bg); color: var(--success-text); }
        .decision-rejected { background: var(--danger-bg);  color: var(--danger-text); }

        .btn {
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .btn-docs { background: #009ef7; color: white; margin-right: 6px; }
        .btn-eval { background: #10b981; color: white; }
        .btn-outline { background:#f5f8fa; color:#7e8299; border:1px solid #e4e6ef; }

        .page-panel { display: none; }
        .page-panel.active { display: block; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 26px;
        }
        .stat-card {
            background: white;
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 0 20px rgba(0,0,0,0.02);
        }
        .stat-icon {
            width: 54px; height: 54px;
            border-radius: 14px;
            display:flex; align-items:center; justify-content:center;
            background: #f5f8fa;
            border: 1px solid #eff2f5;
            margin-bottom: 18px;
            font-size: 22px;
            color: var(--primary);
        }
        .stat-value {
            font-size: 2.3rem;
            font-weight: 900;
            color: var(--primary);
            line-height: 1;
        }
        .stat-label {
            margin-top: 10px;
            color: var(--text-muted);
            font-size: 0.85rem;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }

        .muted-center { text-align:center; color:var(--text-muted); padding: 20px; }
        .quick-actions { display:flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }

        .alert {
            margin-top: 14px;
            padding: 12px 14px;
            border-radius: 10px;
            font-weight: 700;
            display: flex;
            gap: 10px;
            align-items: center;
            border: 1px solid #e4e6ef;
        }
        .alert.success { background: var(--success-bg); color: #0f5132; border-color:#b6f0cf; }
        .alert.error   { background: var(--danger-bg);  color: #842029; border-color:#f5c2c7; }

        .modal{
            display:none;
            position:fixed;
            inset:0;
            width:100vw;
            height:100vh;
            background:rgba(0,0,0,0.45);
            z-index:9999;
            justify-content:center;
            align-items:center;
            padding:20px;
        }
        .modal-content{
            width:min(520px, 95vw);
            max-height:90vh;
            overflow:auto;
            background:#fff;
            border-radius:12px;
            padding:30px;
            box-shadow:0 10px 30px rgba(0,0,0,0.25);
        }

        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e4e6ef;
            border-radius: 8px;
            background: #f9f9f9;
            margin-top: 8px;
            font-family: inherit;
        }

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
        .help-row {
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        @media (max-width: 1100px){
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 900px){
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>

<div class="sidebar">
    <div class="sidebar-brand">Digital Scholarship<br>Application and Track System</div>

    <div class="menu-item active" id="menu-home" onclick="showPanel('home')">
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

    <div class="menu-item" id="menu-publish" onclick="showPanel('publish')">
        <i class="fas fa-bullhorn"></i> Publish Results
    </div>

    <div class="menu-item" style="position: absolute; bottom: 20px; width: 100%; box-sizing: border-box;"
         onclick="window.location.href='../logout.php'">
        <i class="fas fa-sign-out-alt"></i> Logout
    </div>
</div>

<div class="main">

    <div style="display:flex; justify-content:space-between; margin-bottom:25px;">
        <h1 style="font-size: 1.8rem; font-weight: 900;" id="page-title">Committee Dashboard</h1>

        <div style="text-align: right;">
            <span style="font-weight: 800; display: block;">ID: <?php echo htmlspecialchars($committeeID); ?></span>
            <small style="color: var(--text-muted);">Committee: <?php echo htmlspecialchars($committeeName); ?></small>
        </div>
    </div>

    <!-- HOME -->
    <div id="home-panel" class="page-panel active">

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-value"><?php echo $totalApps; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-value"><?php echo $pendingDecision; ?></div>
                <div class="stat-label">Pending Decisions</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-circle-check"></i></div>
                <div class="stat-value"><?php echo $approvedCount; ?></div>
                <div class="stat-label">Approved</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-circle-xmark"></i></div>
                <div class="stat-value"><?php echo $rejectedCount; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <div class="card">
            <h2 style="margin:0 0 12px;">Recent Committee Activity</h2>
            <p style="margin:0; color:var(--text-muted);">
                Welcome to the Committee Portal. From here you can review applications, view reviewer feedback, approve/reject, and publish results.
            </p>

            <div style="margin-top: 18px;">
                <div style="font-weight:800; margin-bottom: 10px;">Quick Actions:</div>
                <div class="quick-actions">
                    <button class="btn btn-docs" type="button" onclick="showPanel('tasks')">
                        <i class="fas fa-list-ul"></i> View Applications
                    </button>
                    <button class="btn btn-eval" type="button" onclick="showPanel('publish')">
                        <i class="fas fa-bullhorn"></i> Publish Results
                    </button>
                    <button class="btn btn-outline" type="button" onclick="showPanel('history')">
                        <i class="fas fa-history"></i> View Decision History
                    </button>
                </div>
            </div>

            <div style="margin-top: 22px;">
                <div style="font-weight:800; margin-bottom: 10px;">Recent Decisions:</div>

                <?php if (empty($recent_activity)): ?>
                    <p class="muted-center">No decision activity found yet.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>App ID</th>
                                <th>Student</th>
                                <th>Decision</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_activity as $ra): ?>
                                <tr>
                                    <td><span style="font-weight:700; color:var(--primary);"><?php echo htmlspecialchars($ra['applicationID']); ?></span></td>
                                    <td><?php echo htmlspecialchars($ra['studentID']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo decisionBadgeClass($ra['committeeDecision']); ?>">
                                            <?php echo htmlspecialchars($ra['committeeDecision']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($ra['decisionDate']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TASKS -->
    <div id="tasks-panel" class="page-panel">
        <?php
            $tasks_msg = "";
            if (!empty($_SESSION['flash_tasks'])) {
                $f = $_SESSION['flash_tasks'];
                unset($_SESSION['flash_tasks']);

                $cls  = ($f['type'] === 'success') ? 'success' : 'error';
                $icon = ($f['type'] === 'success') ? 'fa-check-circle' : 'fa-times-circle';

                $tasks_msg = "
                    <div class='alert $cls'>
                        <i class='fas $icon'></i>
                        <span>" . htmlspecialchars((string)$f['msg']) . "</span>
                    </div>
                ";
            }
            echo $tasks_msg;
        ?>

        <div class="card">
            <?php if (empty($all_apps)): ?>
                <p class="muted-center">No applications found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>App ID</th>
                            <th>Status</th>
                            <th>Student</th>
                            <th>Academic</th>
                            <th>Financial</th>
                            <th>Decision</th>
                            <th>Remark</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_apps as $row): ?>
                        <tr>
                            <td style="color: var(--primary); font-weight: 700;">
                                <?php echo htmlspecialchars($row['applicationID']); ?>
                            </td>

                            <td>
                                <?php $st = $row['application_status'] ?: 'Pending'; ?>
                                <span class="status-badge <?php echo statusBadgeClass($st); ?>">
                                    <?php echo htmlspecialchars($st); ?>
                                </span>
                            </td>

                            <td><?php echo htmlspecialchars($row['student_name'] ?? $row['studentID']); ?></td>

                            <td><?php echo htmlspecialchars($row['academicRecords'] ?? 'No records found'); ?></td>

                            <td style="color: var(--text-muted);">
                                <?php echo htmlspecialchars($row['financialStatus'] ?? 'Income: RM 0'); ?>
                            </td>

                            <td>
                                <?php $dc = $row['committeeDecision'] ?? 'Pending'; ?>
                                <span class="status-badge <?php echo decisionBadgeClass($dc); ?>">
                                    <?php echo htmlspecialchars($dc); ?>
                                </span>
                            </td>

                            <td style="color: var(--text-muted);">
                                <?php echo htmlspecialchars($row['remark'] ?? '-'); ?>
                            </td>

                            <td>
                                <button class="btn btn-docs" type="button"
                                        onclick="openReviews('<?php echo htmlspecialchars($row['applicationID'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-eye"></i> Reviews
                                </button>
                                <button class="btn btn-eval" type="button"
                                        onclick="openDecision('<?php echo htmlspecialchars($row['applicationID'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-paper-plane"></i> Decide
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- HISTORY -->
    <div id="history-panel" class="page-panel">
        <div class="card">
            <?php if (empty($history_apps)): ?>
                <p class="muted-center">No decision history found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 80px;">App ID</th>
                            <th>Student</th>
                            <th>Decision</th>
                            <th>Remark</th>
                            <th>Decision Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history_apps as $row): ?>
                            <?php $decisionClass = decisionBadgeClass($row['committeeDecision']); ?>
                            <tr>
                                <td><span style="font-weight:700; color:var(--primary);"><?php echo htmlspecialchars($row['applicationID']); ?></span></td>
                                <td><?php echo htmlspecialchars($row['student_name'] ?? $row['studentID']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $decisionClass; ?>">
                                        <?php echo htmlspecialchars($row['committeeDecision']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['remark'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['decisionDate']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- PROFILE -->
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
                    <label>Full Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($committee_fullname); ?>" disabled style="background: #f8f9fa;">
                </div>
                <div class="form-group">
                    <label>Committee ID</label>
                    <input type="text" value="<?php echo htmlspecialchars($committeeID); ?>" disabled style="background: #f8f9fa;">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="text" value="<?php echo htmlspecialchars($committee_email); ?>" disabled style="background: #f8f9fa;">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" value="<?php echo htmlspecialchars($committee_phone); ?>" disabled style="background: #f8f9fa;">
                </div>
            </div>

            <div class="form-group">
                <label>Home / Office Address</label>
                <textarea disabled style="height: 100px; background: #f8f9fa; resize: none;"><?php echo htmlspecialchars($committee_address); ?></textarea>
            </div>

            <div class="card" style="margin-top: 20px; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin: 0; font-size: 1.1rem; color: var(--text-main);">Account Security</h3>
                    <p style="margin: 5px 0 0 0; color: var(--text-muted); font-size: 0.85rem;">Update your login credentials here.</p>
                </div>
                <button onclick="window.location.href='changepwd.php'"
                        style="background: var(--primary); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 700; cursor: pointer;">
                    <i class="fas fa-key"></i> Change Password
                </button>
            </div>

            <div class="help-row">
                <i class="fas fa-info-circle"></i>
                <span>To update your contact details, please submit a request to the Admin office.</span>
            </div>
        </div>
    </div>

    <!-- PUBLISH -->
    <div id="publish-panel" class="page-panel">
        <div class="card">
            <h2 style="margin-top:0; display:flex; align-items:center; gap:10px;">
                <i class="fas fa-bullhorn" style="color: var(--primary);"></i>
                Publish Scholarship Results
            </h2>

            <p style="color: var(--text-muted); margin-top:6px;">
                This will release final decisions to student dashboards by updating <b>application_status</b>.
            </p>

            <?php echo $publish_msg; ?>

            <div style="margin-top:18px; padding:16px; border:1px solid #e4e6ef; border-radius:12px; background:#f9f9f9;">
                <div style="font-weight:800; margin-bottom:10px;">What will change after publishing?</div>
                <ul style="margin:0; padding-left:18px; line-height:1.7;">
                    <li>Applications decided as <b>Approved/Rejected</b> will change from <b>Under Committee Review</b> → <b>Approved/Rejected</b>.</li>
                    <li>Students will be able to view final results in their dashboards.</li>
                    <li>Pending decisions will not be published.</li>
                </ul>
            </div>

            <div style="margin-top:18px; display:grid; grid-template-columns: repeat(2, 1fr); gap:12px;">

                <div style="border:1px solid #eff2f5; border-radius:12px; padding:14px; background:white;">
                    <div style="color:var(--text-muted); font-size:0.8rem; text-transform:uppercase; font-weight:800;">Approved</div>
                    <div style="font-size:1.8rem; font-weight:900;"><?php echo $ready_approved; ?></div>
                </div>

                <div style="border:1px solid #eff2f5; border-radius:12px; padding:14px; background:white;">
                    <div style="color:var(--text-muted); font-size:0.8rem; text-transform:uppercase; font-weight:800;">Pending decisions</div>
                    <div style="font-size:1.8rem; font-weight:900;"><?php echo $pending_to_decide; ?></div>
                </div>

            </div>

            <div style="margin-top:18px; display:flex; justify-content:flex-end;">
                <form method="POST" action="dashboard.php?panel=publish">
                    <button
                        type="submit"
                        name="publish_results"
                        class="btn btn-eval"
                        <?php echo ($pending_to_decide > 0) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>
                    >
                        <i class="fas fa-check"></i> Confirm Publish
                    </button>
                </form>
            </div>

            <?php if ($pending_to_decide > 0): ?>
                <div class="help-row" style="margin-top:10px;">
                    <i class="fas fa-info-circle"></i>
                    <span>Results cannot be published at this time. Finish all committee decisions before publishing.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Reviews Modal -->
<div id="reviewsModal" class="modal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0;">Reviewer Feedback</h3>
            <button type="button" class="btn btn-outline" onclick="closeReviews()">Close</button>
        </div>

        <div style="margin-top:15px;">
            <div style="font-weight:700; font-size:0.85rem;">
                Application: <span id="revAppID" style="color: var(--primary);"></span>
            </div>

            <div id="reviewsBody" style="margin-top:15px;">
                <p class="muted-center">Loading reviews...</p>
            </div>
        </div>
    </div>
</div>

<!-- Decision Modal -->
<div id="decisionModal" class="modal">
    <div class="modal-content">
        <form method="POST" action="dashboard.php">
            <h3 style="margin-top:0;">Committee Decision</h3>
            <input type="hidden" id="decisionAppID" name="applicationID">

            <div style="margin-top:15px; display: flex; flex-direction: column; gap: 10px;">
                <label style="font-weight:700; font-size:0.8rem; margin-bottom: 5px;">Decision</label>

                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="radio" name="decision" value="Approved" required style="width: auto; margin: 0;">
                    <span>Approve</span>
                </label>

                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="radio" name="decision" value="Rejected" required style="width: auto; margin: 0;">
                    <span>Reject</span>
                </label>
            </div>

            <div style="margin-top:20px;">
                <label style="font-weight:700; font-size:0.8rem; display: block; margin-bottom: 5px;">Remark</label>
                <textarea name="remark" rows="4" placeholder="Enter remarks..." required style="margin-top:0;"></textarea>
            </div>

            <div style="margin-top:25px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-outline" onclick="closeDecision()">Cancel</button>
                <button type="submit" name="submit_decision" class="btn btn-eval">Save Decision</button>
            </div>
        </form>
    </div>
</div>

<script>
function showPanel(type) {
    document.querySelectorAll('.page-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));

    const panel = document.getElementById(type + '-panel');
    const menu  = document.getElementById('menu-' + type);

    if (panel) panel.classList.add('active');
    if (menu) menu.classList.add('active');

    const titleMap = {
        home: 'Committee Dashboard',
        tasks: 'Assigned Tasks',
        history: 'My History',
        profile: 'My Profile',
        publish: 'Publish Results'
    };
    document.getElementById('page-title').innerText = titleMap[type] || 'Committee Dashboard';
}

function openReviews(appID) {
    closeDecision();

    document.getElementById('revAppID').innerText = appID;

    const modal = document.getElementById('reviewsModal');
    const body  = document.getElementById('reviewsBody');

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    body.innerHTML = "<p class='muted-center'>Loading reviewer feedback...</p>";

    fetch("get_reviews.php?applicationID=" + encodeURIComponent(appID), {
        method: "GET",
        headers: { "Accept": "application/json" },
        cache: "no-store"
    })
    .then(async (res) => {
        const text = await res.text();
        let json;
        try { json = JSON.parse(text); }
        catch (e) { throw new Error("Server did not return JSON"); }

        if (!res.ok || !json.ok) throw new Error(json.error || "Failed to load review data");
        return json.data || [];
    })
    .then((rows) => {
        if (!rows.length) {
            body.innerHTML = "<p class='muted-center'>No review submitted yet for this application.</p>";
            return;
        }

        const html = `
            <div style="display:flex; flex-direction:column; gap:12px;">
                ${rows.map(r => `
                    <div style="border:1px solid #e4e6ef; border-radius:12px; padding:14px; background:#fff;">
                        <div style="display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                            <div style="font-weight:800;">
                                Reviewer ID: ${escapeHtml(String(r.reviewerID ?? '-'))}
                            </div>
                            <div style="font-weight:900; color:#009ef7;">
                                Score: ${escapeHtml(String(r.score ?? '-'))}
                            </div>
                        </div>

                        <div style="margin-top:8px; color:#7e8299; font-size:0.85rem;">
                            Date: ${escapeHtml(String(r.review_Date ?? '-'))} &nbsp; | &nbsp;
                            Status: ${escapeHtml(String(r.review_status ?? '-'))}
                        </div>

                        <div style="margin-top:10px; line-height:1.6;">
                            ${escapeHtml(String(r.comments ?? '')).replace(/\\n/g, "<br>")}
                        </div>
                    </div>
                `).join("")}
            </div>
        `;
        body.innerHTML = html;
    })
    .catch(() => {
        body.innerHTML = "<p class='muted-center'>Failed to load review data.</p>";
    });
}

function escapeHtml(str) {
    return str
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

function closeReviews() {
    document.getElementById('reviewsModal').style.display = 'none';
    document.body.style.overflow = '';
}

function openDecision(appID) {
    closeReviews();
    document.getElementById('decisionAppID').value = appID;
    document.getElementById('decisionModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeDecision() {
    document.getElementById('decisionModal').style.display = 'none';
    document.body.style.overflow = '';
}

document.addEventListener('click', function(e){
    const reviewsModal = document.getElementById('reviewsModal');
    const decisionModal = document.getElementById('decisionModal');

    if (reviewsModal.style.display === 'flex' && e.target === reviewsModal) closeReviews();
    if (decisionModal.style.display === 'flex' && e.target === decisionModal) closeDecision();
});

document.addEventListener('DOMContentLoaded', function(){
    const params = new URLSearchParams(window.location.search);
    const p = params.get('panel') || 'home';
    showPanel(p);
});
</script>

</body>
</html>
