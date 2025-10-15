<?php
// Start session (if you’re using sessions)
session_start();

// --- DELETE COOKIES ---
// Adjust these cookie names if needed
setcookie('EIMS_emp_Id', '', time() - 3600, '/');
setcookie('EIMS_usr_Id', '', time() - 3600, '/');
setcookie('EIMS_username', '', time() - 3600, '/');
setcookie('EIMS_user_lvl', '', time() - 3600, '/');

// --- DESTROY SESSION ---
session_unset();
session_destroy();

// --- REDIRECT TO LOGIN PAGE ---
header("Location: login.php");
exit();
?>
