<?php
// Check if user cookies are set
if (!isset($_COOKIE['EIMS_emp_Id']) || !isset($_COOKIE['EIMS_usr_Id'])) {
    // If cookies are missing, redirect to login
    header("Location: ../login.php");
    exit();
}

// Optional: you can also store cookie values in variables for easier access
$emp_id   = $_COOKIE['EIMS_emp_Id'];
$user_id  = $_COOKIE['EIMS_usr_Id'];
$username = $_COOKIE['EIMS_usrname'] ?? '';
$user_lvl = $_COOKIE['EIMS_usrlvl'] ?? '';
?>
