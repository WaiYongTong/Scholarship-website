<?php
// Database connection settings 
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "scholarship_system";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Starting Password Encryption Process...</h2><hr>";

// Define the tables and corresponding column names to be processed
$tables = [
    'student' => [
        'id_col' => 'studentID', 
        'pass_col' => 'student_password'
    ],
    'reviewer' => [
        'id_col' => 'reviewerID', 
        'pass_col' => 'reviewer_password'
    ],
    'admin' => [
        'id_col' => 'adminID', 
        'pass_col' => 'admin_password'
    ],
    'committee' => [
        'id_col' => 'committeeID', 
        'pass_col' => 'committee_password'
    ]
];

// Iterate through each table for processing
foreach ($tables as $tableName => $columns) {
    $idCol = $columns['id_col'];
    $passCol = $columns['pass_col'];
    
    echo "<h3>Processing Table: $tableName</h3>";
    
    // Fetch all users from the table
    $sql = "SELECT $idCol, $passCol FROM $tableName";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $currentId = $row[$idCol];
            $currentPass = $row[$passCol];

            // Check if the password is already encrypted (Starts with $2y$ is usually a bcrypt hash)
            // If not, or if it is known plaintext, proceed with encryption
            if (substr($currentPass, 0, 4) !== '$2y$') {
                // Encrypt the password
                $hashedPass = password_hash($currentPass, PASSWORD_DEFAULT);
                
                // Update the database
                $updateSql = "UPDATE $tableName SET $passCol = ? WHERE $idCol = ?";
                $stmt = $conn->prepare($updateSql);
                $stmt->bind_param("ss", $hashedPass, $currentId);
                
                if ($stmt->execute()) {
                    echo "User <b>$currentId</b>: Password encrypted successfully.<br>";
                } else {
                    echo "User <b>$currentId</b>: Error updating password.<br>";
                }
                $stmt->close();
            } else {
                echo "User <b>$currentId</b>: Password already encrypted. Skipped.<br>";
            }
        }
    } else {
        echo "No users found in $tableName.<br>";
    }
}

echo "<hr><h2>All Done! Now try to login again.</h2>";
$conn->close();
?>