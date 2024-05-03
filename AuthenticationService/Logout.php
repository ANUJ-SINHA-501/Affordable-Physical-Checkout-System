<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

include '../db_config.php';

session_start();

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Check if auth_token is provided in the URL
    if (!isset($_GET['auth_token'])) {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "Auth token is required"));
        exit();
    }

    // Check if user is logged in
    if (!isset($_SESSION['vendor_id']) || !isset($_SESSION['auth_token'])) {
        http_response_code(401);
        echo json_encode(array("status" => "error", "message" => "User is not logged in"));
        exit();
    }

    // Verify the auth_token
    if ($_SESSION['auth_token'] !== $_GET['auth_token']) {
        http_response_code(403);
        echo json_encode(array("status" => "error", "message" => "Invalid auth token"));
        exit();
    }

    // Clear auth_token and session
    $vendor_id = $_SESSION['vendor_id'];
    $stmt = $conn->prepare("UPDATE vendors SET auth_token = NULL, auth_token_time = NULL WHERE vendor_id = ?");
    $stmt->bind_param("i", $vendor_id); // Assuming vendor_id is an integer
    $stmt->execute();
    $stmt->close();

    session_unset();
    session_destroy();

    echo json_encode(array("status" => "success", "message" => "Logout successful"));
} else {
    http_response_code(405);
    echo json_encode(array("status" => "error", "message" => "Invalid request method"));
}
?>
