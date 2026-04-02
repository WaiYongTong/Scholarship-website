<?php
session_start();
include('config/db.php');

$message = "";

// Handle registration form submission
if (isset($_POST['register'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    $email_parts = explode('@', $email);
    $domain = end($email_parts);

    $password_regex = "/^(?=.*[0-9])(?=.*[!@#$%^&*])[a-zA-Z0-9!@#$%^&*]{8,}$/";

    if ($domain !== 'student.edu.my') {
        $message = "<div class='alert error'>
                    <i class='fas fa-exclamation-circle'></i> Only @student.edu.my emails are allowed.
                    </div>";
    } elseif ($password !== $confirm_password) {
        $message = "<div class='alert error'>Passwords do not match!</div>";
    } elseif (!preg_match($password_regex, $password)) {
        $message = "<div class='alert error'>
                    <strong>Weak Password!</strong><br> 
                    Must be at least 8 characters and include a number and a special character (e.g., !, @, #).
                    </div>";
    } else {
        $check_email = mysqli_query($conn, "SELECT student_email FROM student WHERE student_email = '$email'");
        $check_phone = mysqli_query($conn, "SELECT student_phonenum FROM student WHERE student_phonenum = '$phone'");

        if (mysqli_num_rows($check_email) > 0) {
            $message = "<div style='color:red; text-align:center; margin-bottom:10px;'>Email already registered!</div>";
        } elseif (mysqli_num_rows($check_phone) > 0) {
            $message = "<div class='alert error'>Phone number already registered!</div>";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO student (student_name, student_email, student_phonenum, student_address, student_password) 
            VALUES ('$name', '$email', '$phone', '$address', '$hashed_password')";

            if (mysqli_query($conn, $sql)) {
                $real_id = mysqli_insert_id($conn);
                $_SESSION['user_id'] = $real_id; 
                $_SESSION['user_name'] = $name;
                $_SESSION['role'] = 'student';
    
                header("Location: student/dashboard.php");
                exit();
                }else {
                $message = "<div style='color:red; text-align:center;'>Error: " . mysqli_error($conn) . "</div>";
            }
        }
    }
}

include('includes/header.php');
?>

<style>
    .register-container {
        max-width: 800px;
        margin: 50px auto;
        padding: 40px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0px 10px 30px rgba(0,0,0,0.05);
        font-family: 'Segoe UI', sans-serif;
    }

    /* Alert messages */
    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
        text-align: center;
        border: 1px solid transparent;
    }
    .error {
        background: #fff5f8;
        color: #d9214e;
        border-color: #fac1cf;
    }

    /* Form */
    .form-row { display: flex; gap: 20px; margin-bottom: 15px; }
    .form-group { flex: 1; position: relative; }
    
    label { display: block; margin-bottom: 8px; font-weight: 600; color: #444; font-size: 14px; }
    
    input, textarea {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid #e1e1e1;
        border-radius: 8px;
        font-size: 14px;
        box-sizing: border-box;
        background: #fcfcfc;
    }

    input:focus, textarea:focus { 
        outline: none; 
        border-color: #007bff; 
        background: #fff;
        box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
    }

    /* Password */
    .password-hint {
        color: #666; 
        font-size: 12px; 
        display: block; 
        margin-top: 5px;
    }

    .password-strength {
        font-size: 12px;
        margin-top: 5px;
        font-weight: bold;
        display: none; 
    }

    /* Buttons and footer */
    .btn-submit {
        width: 100%;
        padding: 15px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: bold;
        font-size: 16px;
        cursor: pointer;
        margin-top: 20px;
        transition: 0.3s;
    }
    .btn-submit:hover { background: #0056b3; }

    .footer-links { text-align: center; margin-top: 25px; border-top: 1px solid #eee; padding-top: 20px; }
</style>

<div class="register-container">
    <div style="text-align: center; margin-bottom: 30px;">
        <i class="fa-solid fa-graduation-cap" style="font-size: 50px; color: #007bff;"></i>
        <h2 style="margin: 15px 0 5px 0;">Create Student Account</h2>
        <p style="color: #888;">Join the Digital Scholarship Application and Tracking System</p>
    </div>

    <?php echo $message; ?>
    
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" required placeholder="Enter full name" 
                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" required placeholder="name@student.edu.my"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" required placeholder="e.g. 0123456789"
                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
            </div>
            <div class="form-group">
                <label>Current Address</label>
                <textarea name="address" required placeholder="Enter permanent address" style="height: 45px;"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Create password">
                <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">
                    <i class="fas fa-info-circle"></i> Must be at least 8 characters and include a number and a special character (e.g., !, @, #)
                </small>
            </div>
            <div class="form-group">
                <label>Repeat Password</label>
                <input type="password" name="confirm_password" required placeholder="Confirm password">
            </div>
        </div>

        <button type="submit" name="register" class="btn-submit">REGISTER NOW</button>
    </form>
    
    <div class="footer-links">
        <p>Already have an account? <a href="login.php" style="color: #007bff; text-decoration: none; font-weight: 600;">Login here</a></p>
        <a href="index.php" style="color: #777; text-decoration: none; font-size: 13px;"><i class="fas fa-home"></i> Back to Home</a>
    </div>
</div>

<?php include('includes/footer.php'); ?>