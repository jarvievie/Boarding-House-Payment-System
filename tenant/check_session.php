<?php
session_start();
header('Content-Type: application/json');

// Check if session is valid
$valid = false;

if (isset($_POST['check_session'])) {
    // Check if tenant session exists
    if (isset($_SESSION['tenant']) && !empty($_SESSION['tenant'])) {
        $valid = true;
    }
}

echo json_encode(['valid' => $valid]);
exit();
?>
