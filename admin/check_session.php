<?php
session_start();
header('Content-Type: application/json');

// Check if session is valid
$valid = false;

if (isset($_POST['check_session'])) {
    // Check if admin session exists
    if (isset($_SESSION['admin']) && !empty($_SESSION['admin'])) {
        $valid = true;
    }
}

echo json_encode(['valid' => $valid]);
exit();
?>
