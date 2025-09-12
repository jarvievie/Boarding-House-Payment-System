<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged out
$logged_in = false;

if (isset($_POST['check_logout'])) {
    // Check if admin or tenant session exists
    if (isset($_SESSION['admin']) && !empty($_SESSION['admin'])) {
        $logged_in = true;
    } elseif (isset($_SESSION['tenant']) && !empty($_SESSION['tenant'])) {
        $logged_in = true;
    }
}

echo json_encode(['logged_in' => $logged_in]);
exit();
?>
