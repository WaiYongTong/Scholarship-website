<?php
session_start();
session_destroy();
// Outputting a script to show the alert and then redirect to login
echo "<script>
    alert('You have successfully logged out');
    window.location.href = 'login.php';
</script>";
exit();
?>