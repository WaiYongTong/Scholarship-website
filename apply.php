<?php
    session_start();
    include('../config/db.php');

    // Security Check
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
        header("Location: ../login.php");
        exit();
    }

    $student_id = $_SESSION['user_id']; 
    $student_name = $_SESSION['user_name'];
    $message = "";
    $qualification = "";
    $cgpa = "";
    $f_occ = "";
    $f_inc = "";
    $m_occ = "";
    $m_inc = "";
    $scholarship_id_post = "";
    $selected_id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $qualification = $_POST['qualification'] ?? '';
        $cgpa = $_POST['cgpa'] ?? '';
        $f_occ = $_POST['father_occ'] ?? '';
        $f_inc = $_POST['father_income'] ?? '';
        $m_occ = $_POST['mother_occ'] ?? '';
        $m_inc = $_POST['mother_income'] ?? '';
        $scholarship_id_post = $_POST['scholarship_id'] ?? '';
    }

    // Handle form submission and prevent duplicate applications
    if (isset($_POST['submit_app'])) {
        $scholarshipID = mysqli_real_escape_string($conn, $_POST['scholarship_id']);
        $academicRecords = "CGPA " . mysqli_real_escape_string($conn, $_POST['cgpa']) . ", " . mysqli_real_escape_string($conn, $_POST['qualification']);
        
        $f_occ = mysqli_real_escape_string($conn, $_POST['father_occ']);
        $f_inc = mysqli_real_escape_string($conn, $_POST['father_income']);
        $m_occ = mysqli_real_escape_string($conn, $_POST['mother_occ']);
        $m_inc = mysqli_real_escape_string($conn, $_POST['mother_income']);
        $financialStatus = "Father: $f_occ (RM $f_inc), Mother: $m_occ (RM $m_inc)";
        $check_sql = "SELECT applicationID FROM application WHERE studentID = '$student_id' AND scholarshipID = '$scholarshipID'";
        $check_res = mysqli_query($conn, $check_sql);
        $selected_id = !empty($scholarship_id_post) ? $scholarship_id_post : (isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '');
        
        if (mysqli_num_rows($check_res) > 0) {
            $message = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> Error: You have already submitted an application for this scholarship.</div>";
        } else {
            $total_files = count($_FILES['docs']['name']);
            
            if ($total_files > 4) {
                 $message = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> Error: You can only upload a maximum of 4 files.</div>";
            } else {
                $upload_dir = "../uploads/";
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

                $sql_app = "INSERT INTO application (application_status, submissionDate, scholarshipID, studentID) 
                            VALUES ('Pending', NOW(), '$scholarshipID', '$student_id')";

                if (mysqli_query($conn, $sql_app)) {
                    $newApplicationID = mysqli_insert_id($conn);
                    
                    $first_file_path = ""; 
                    $upload_success_count = 0;

                    for ($i = 0; $i < $total_files; $i++) {
                        $tmp_name = $_FILES['docs']['tmp_name'][$i];
                        $file_name_origin = $_FILES['docs']['name'][$i];
                        
                        if ($tmp_name != "") {
                            $new_file_name = time() . "_{$i}_" . basename($file_name_origin);
                            $target_file = $upload_dir . $new_file_name;

                            if (move_uploaded_file($tmp_name, $target_file)) {
                                $upload_success_count++;

                                if ($i === 0) {
                                    $first_file_path = $new_file_name;
                                }

                                $docType = "Supporting Document " . ($i + 1); 
                                $db_path = "uploads/" . $new_file_name;
                                
                                $sql_doc = "INSERT INTO document (applicationID, documentType, file_path, uploadedDate) 
                                            VALUES ('$newApplicationID', '$docType', '$db_path', NOW())";
                                mysqli_query($conn, $sql_doc);
                            }
                        }
                    }

                    if ($upload_success_count > 0) {
                        $sql_form = "INSERT INTO applicationform (applicationID, scholarshipID, academicRecords, financialStatus, document_path, status) 
                                     VALUES ('$newApplicationID', '$scholarshipID', '$academicRecords', '$financialStatus', '$first_file_path', 'Pending')";

                        if (mysqli_query($conn, $sql_form)) {
                            $message = "<div class='alert success'><i class='fas fa-check-circle'></i> Application submitted successfully with $upload_success_count documents!</div>";
                            echo "<script>setTimeout(function(){ window.location.href='dashboard.php'; }, 2000);</script>";
                        } else {
                            $message = "<div class='alert error'>Error saving form details: " . mysqli_error($conn) . "</div>";
                        }
                    } else {
                        $message = "<div class='alert error'>Error: No files were uploaded successfully.</div>";
                    }

                } else {
                    $message = "<div class='alert error'>Database Error: " . mysqli_error($conn) . "</div>";
                }
            }
        }
    }

    $schemes = mysqli_query($conn, "SELECT scholarshipID, scholarship_name FROM scholarship WHERE scholarship_status = 'Open'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Application | Scholarship Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #009ef7; --dark: #1e1e2d; --bg: #f5f8fa; 
            --text-main: #181c32; --text-muted: #a1a5b7;
            --success-bg: #e8fff3; --success-text: #50cd89;
            --danger-bg: #fff5f8; --danger-text: #f1416c;
        }
        body { margin: 0; font-family: 'Inter', sans-serif; background-color: var(--bg); color: var(--text-main); display: flex; justify-content: center; min-height: 100vh; }
        .main-container { width: 100%; max-width: 800px; padding: 40px 20px; }
        .page-header { text-align: center; margin-bottom: 40px; }
        .page-header h1 { font-size: 2rem; font-weight: 800; margin: 0; }
        .page-header p { color: var(--text-muted); margin-top: 10px; }
        .card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.04); border: 1px solid #eff2f5; }
        .form-group { margin-bottom: 25px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 700; font-size: 0.85rem; text-transform: uppercase; color: var(--text-main); }
        input, select { width: 100%; padding: 14px; border: 1px solid #e4e6ef; border-radius: 8px; font-size: 1rem; background: #f9f9f9; box-sizing: border-box; transition: 0.3s; }
        input:focus, select:focus { border-color: var(--primary); outline: none; background: #fff; box-shadow: 0 0 0 4px rgba(0, 158, 247, 0.1); }
        
        .file-upload-area { border: 2px dashed #e4e6ef; padding: 25px; border-radius: 12px; background: #fcfcfc; text-align: center; transition: 0.3s; margin-top: 10px; }
        .file-upload-area:hover { border-color: var(--primary); background: #f8f9ff; }
        
        .button-group { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 30px; }
        .btn { padding: 16px; border-radius: 8px; font-weight: 700; font-size: 0.95rem; cursor: pointer; text-align: center; text-decoration: none; transition: 0.3s; border: none; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-submit { background: var(--primary); color: white; }
        .btn-submit:hover { background: #008be0; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 158, 247, 0.2); }
        .btn-cancel { background: #f1f1f4; color: #7e8299; }
        .btn-cancel:hover { background: #e4e6ef; color: var(--text-main); }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; font-weight: 600; display: flex; align-items: center; gap: 12px; }
        .success { background: var(--success-bg); color: var(--success-text); }
        .error { background: var(--danger-bg); color: var(--danger-text); }
    </style>
</head>
<body>

<div class="main-container">
    <div class="page-header">
        <h1>Scholarship Application</h1>
        <p>Complete the form below to submit your request.</p>
    </div>

    <?php echo $message; ?>

    <div class="card">
        <form method="POST" enctype="multipart/form-data">   
            <div class="form-group">
                <label><i class="fas fa-graduation-cap"></i> Scholarship Scheme</label>
                <select name="scholarship_id" required>
                    <option value="">-- Select Scholarship --</option>
                    <?php if($schemes): while($s = mysqli_fetch_assoc($schemes)): ?>
                        <option value="<?php echo $s['scholarshipID']; ?>" 
                            <?php echo ($s['scholarshipID'] == $selected_id) ? 'selected' : ''; ?>>
                            <?php echo $s['scholarshipID'] . ". " . htmlspecialchars($s['scholarship_name']); ?>
                        </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Highest Qualification</label>
                    <input type="text" name="qualification" placeholder="e.g. Computer Science" 
                        value="<?php echo htmlspecialchars($qualification); ?>" required>
                </div>
                <div class="form-group">
                    <label>Current CGPA</label>
                    <input type="number" step="0.01" min="0" max="4.00" name="cgpa" placeholder="e.g. 3.67"
                        value="<?php echo htmlspecialchars($cgpa); ?>" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Father's Occupation</label>
                    <select name="father_occ" required>
                        <option value="">-- Select --</option>
                        <?php
                        $occupations = ["Employed", "Self-Employed", "Retired", "Unemployed", "Deceased"];
                        foreach ($occupations as $occ) {
                            $sel = ($f_occ == $occ) ? 'selected' : '';
                            echo "<option value='$occ' $sel>$occ</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Father's Monthly Income (RM)</label>
                    <input type="number" name="father_income" 
                        value="<?php echo htmlspecialchars($f_inc); ?>" placeholder="0" min="0">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Mother's Occupation</label>
                    <select name="mother_occ" required>
                        <option value="">-- Select --</option>
                        <?php
                        $m_occupations = ["Employed", "Self-Employed", "Housewife", "Retired", "Unemployed", "Deceased"];
                        foreach ($m_occupations as $occ) {
                            $sel = ($m_occ == $occ) ? 'selected' : '';
                            echo "<option value='$occ' $sel>$occ</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mother's Monthly Income (RM)</label>
                    <input type="number" name="mother_income" 
                        value="<?php echo htmlspecialchars($m_inc); ?>" min="0">
                </div>
            </div>

            <div class="form-group">
                <label>Supporting Documents (Max 4)</label>
                <div class="file-upload-area">
                    <i class="fas fa-copy fa-2x" style="color: #cbd5e0; margin-bottom: 10px;"></i>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0 0 10px 0;">Hold <strong>Ctrl</strong> (or Command) to select multiple files.</p>
                    
                    <input type="file" name="docs[]" multiple accept=".pdf,.jpg,.jpeg,.png" required>
                </div>
            </div>

            <div class="button-group">
                <a href="dashboard.php" class="btn btn-cancel">
                    <i class="fas fa-times"></i> Discard
                </a>
                <button type="submit" name="submit_app" class="btn btn-submit">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </form>
    </div>
</div>

</body>
</html>