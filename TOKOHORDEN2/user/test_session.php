<?php
session_start();
echo "<pre>";
echo "Session Data:\n";
print_r($_SESSION);
echo "\nCookies:\n";
print_r($_COOKIE);
echo "</pre>";

// Test set session
$_SESSION['test'] = 'working';
echo "Session test set: " . $_SESSION['test'];
?>