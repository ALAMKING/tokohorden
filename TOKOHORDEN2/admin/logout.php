<?php
session_start();

// Hancurkan session admin
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_nama']);
unset($_SESSION['admin_role']);
unset($_SESSION['admin_email']);

// Redirect ke login
header('Location: login.php');
exit;
?>