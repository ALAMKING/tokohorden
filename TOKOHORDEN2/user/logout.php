<?php
session_start();

// Hancurkan session user
unset($_SESSION['user_logged_in']);
unset($_SESSION['user_id']);
unset($_SESSION['user_nama']);
unset($_SESSION['user_email']);
unset($_SESSION['user_telepon']);
unset($_SESSION['user_alamat']);
unset($_SESSION['user_kota']);

// Redirect ke login
header('Location: login.php');
exit;
?>