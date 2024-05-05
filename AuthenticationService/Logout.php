<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

include '../db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // Check if auth_token is provided in the URL
    if (!isset($_GET['auth_token'])) {
        http_response_code(400);
        echo json_encode(array("status" => "error", "message" => "Auth token is required"));
        exit();
    }

    $auth_token = $_GET['auth_token'];

    // Verify the auth_token
    $stmt = $conn->prepare("SELECT vendor_id FROM vendors WHERE auth_token = ?");
    $stmt->bind_param("s", $auth_token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        http_response_code(403);
        echo json_encode(array("status" => "error", "message" => "Invalid auth token"));
        exit();
    }

    $vendor_id = $row['vendor_id'];

    // Clear auth_token and session
    $stmt = $conn->prepare("UPDATE vendors SET auth_token = NULL, auth_token_time = NULL WHERE vendor_id = ?");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(array("status" => "success", "message" => "Logout successful"));
} else {
    http_response_code(405);
    echo json_encode(array("status" => "error", "message" => "Invalid request method"));
}
?>
