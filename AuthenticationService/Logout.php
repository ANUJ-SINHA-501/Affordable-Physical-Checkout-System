<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

include '../db_config.php';

if (isset($_GET['vendor_id'])) {
    $vendor_id = $_GET['vendor_id'];
    $stmt = $conn->prepare("SELECT vendor_id FROM vendors WHERE vendor_id = ?");
    $stmt->bind_param("s", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE vendors SET csrf_token = NULL, csrf_token_time = NULL WHERE vendor_id = ?");
        $stmt->bind_param("s", $vendor_id);
        $stmt->execute();

        if (session_status() == PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        echo json_encode(array("status" => "success", "message" => "Logout successful"));
    } else {
        echo json_encode(array("status" => "error", "message" => "No user with this vendor_id is currently logged in"));
    }
} else {
    echo json_encode(array("status" => "error", "message" => "No vendor_id provided"));
}
?>
