<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

include '../db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    session_start();

    
    if (!isset($_GET['auth_token'])) {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "Auth token is required"));
        exit();
    }

    
    if (!isset($_SESSION['vendor_id'])) {
        http_response_code(401);
        echo json_encode(array("status" => "error", "message" => "User is not logged in"));
        exit();
    }

    
    if ($_SESSION['auth_token'] !== $_GET['auth_token']) {
        http_response_code(403);
        echo json_encode(array("status" => "error", "message" => "Invalid auth token"));
        exit();
    }

    
    $vendor_id = $_SESSION['vendor_id'];
    $stmt = $conn->prepare("UPDATE vendors SET auth_token = NULL, auth_token_time = NULL WHERE vendor_id = ?");
    $stmt->bind_param("s", $vendor_id);
    $stmt->execute();

    
    if (session_status() == PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }

    echo json_encode(array("status" => "success", "message" => "Logout successful"));
} else {
    http_response_code(405);
    echo json_encode(array("status" => "error", "message" => "Invalid request method"));
}
?>
