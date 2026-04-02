<?php 
include('config/db.php');
include('includes/header.php'); 

$student_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

$sql = "SELECT s.*, a.applicationID 
        FROM scholarship s 
        LEFT JOIN application a ON s.scholarshipID = a.scholarshipID AND a.studentID = '$student_id'"; 
$result = mysqli_query($conn, $sql);
?>

<script>
    document.title = "Available Schemes | Digital Scholarship";
</script>

<style>
    .schemes-container {
        max-width: 1100px;
        margin: 50px auto;
        padding: 20px;
        font-family: 'Inter', 'Segoe UI', sans-serif;
    }
    .scheme-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-top: 30px;
        align-items: start;
    }
    .scheme-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 0 20px rgba(0,0,0,0.02);
        border-top: 5px solid #009ef7;
        transition: transform 0.3s;
        border-left: 1px solid #eff2f5;
        border-right: 1px solid #eff2f5;
        border-bottom: 1px solid #eff2f5;
    }
    .scheme-card:hover { transform: translateY(-5px); }
    .scheme-card h3 { color: #1e1e2d; margin: 10px 0; }
    .scheme-card p { color: #a1a5b7; font-size: 14px; margin: 5px 0; }
    .deadline { color: #f1416c; font-weight: bold; }
    
    .btn-details {
        display: inline-block;
        margin-top: 15px;
        padding: 10px 20px;
        background: #009ef7;
        color: #ffffff;
        text-decoration: none;
        border-radius: 5px;
        font-weight: bold;
        text-align: center;
        width: 100%;
        box-sizing: border-box;
        transition: 0.3s;
    }
    .btn-details:hover { background: #008be0; }

    .btn-closed {
        background: #e4e6ef !important;
        color: #7e8299 !important;
        cursor: not-allowed;
    }

    /* Keeps the card description short */
    .description-text {
        color: #5e6278;
        font-size: 14px;
        margin: 15px 0;
        line-height: 1.5;
        display: -webkit-box;
        -webkit-line-clamp: 2; /* Only show 2 lines on the card */
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Modal Background */
    .modal-overlay {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        backdrop-filter: blur(4px);
    }

    /* Modal Content Box */
    .modal-content {
        background-color: #fff;
        margin: 10% auto;
        padding: 30px;
        border-radius: 15px;
        width: 90%;
        max-width: 600px;
        position: relative;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }

    .close-modal {
        position: absolute;
        right: 20px;
        top: 15px;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: #a1a5b7;
    }

    .requirement-list {
        white-space: pre-line; /* Respects line breaks from DB */
        color: #3f4254;
        line-height: 1.8;
        background: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        border-left: 4px solid #009ef7;
    }

    /* Hide the actual checkbox */
    .toggle-input {
        display: none;
    }

    /* Style for the "View Requirements" label (acts as a button) */
    .toggle-label {
        display: block;
        color: #009ef7;
        font-weight: 600;
        cursor: pointer;
        margin: 10px 0;
        font-size: 14px;
    }

    .toggle-label:hover {
        text-decoration: underline;
    }

    /* Full content is hidden by default */
    .full-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
        background: #f8f9fa;
        border-radius: 8px;
        padding: 0 15px; /* Padding top/bottom added when expanded */
    }

    /* When checkbox is checked, expand the content */
    .toggle-input:checked ~ .full-content {
        max-height: 1000px; /* Large enough to fit text */
        padding: 15px;
        margin-bottom: 15px;
        border: 1px solid #eff2f5;
    }

    /* Format the text inside */
    .requirement-text {
        white-space: pre-line; /* Keeps your database line breaks */
        color: #3f4254;
        font-size: 14px;
        line-height: 1.6;
    }

    /* Change button text when open */
    .toggle-input:checked ~ .toggle-label::after {
        content: " (Hide)";
        font-size: 12px;
    }
</style>

<div class="schemes-container">
    <h2 style="text-align:center; color:#1e1e2d; font-weight: 800;">Available Scholarship Schemes</h2>
    <p style="text-align:center; color: #a1a5b7;">Find the right financial support for your educational journey.</p>

    <div class="scheme-grid">
    <?php 
    if ($result && mysqli_num_rows($result) > 0): 
        while($row = mysqli_fetch_assoc($result)): 
            $isOpen = (strtolower($row['scholarship_status']) == 'open');
    ?>
            <div class="scheme-card">
                <small style="color: #009ef7; font-weight: bold;"><?php echo $row['scholarshipID']; ?></small>
                <h3><?php echo $row['scholarship_name']; ?></h3>
                
                <input type="checkbox" id="expand-<?php echo $row['scholarshipID']; ?>" class="toggle-input">
                
                <label for="expand-<?php echo $row['scholarshipID']; ?>" class="toggle-label">
                    View Requirements & Details ↓
                </label>

                <div class="full-content">
                    <div class="requirement-text">
                        <?php 
                        // Use the correct column name from your DB here
                        echo htmlspecialchars($row['scholarship_description'] ?? $row['description'] ?? 'No details provided.'); 
                        ?>
                    </div>
                </div>
                <p>Status: <strong style="color:#50cd89;"><?php echo $row['scholarship_status']; ?></strong></p>
                <p>Deadline: <span class="deadline"><?php echo $row['close_date']; ?></span></p>
                
                <?php 
                    $hasApplied = !empty($row['applicationID']);

                    if ($hasApplied): ?>
                        <a href="student/dashboard.php" class="btn-details" style="background: #e4e6ef; color: #7e8299; cursor: default;">
                            <i class="fas fa-check"></i> Applied
                        </a>
                    <?php elseif ($isOpen): ?>
                        <a href="student/apply.php?id=<?php echo $row['scholarshipID']; ?>" class="btn-details">Apply Now</a>
                    <?php else: ?>
                        <a href="#" class="btn-details btn-closed">Closed</a>
                    <?php endif; ?>
            </div>
    <?php 
        endwhile; 
    else: 
    ?>
        <p style="text-align:center; width:100%; color: #a1a5b7;">No scholarships found in the database.</p>
    <?php endif; ?>
    </div>
</div>

<?php include('includes/footer.php'); ?>