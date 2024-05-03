<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

date_default_timezone_set('Asia/Kolkata');

include '../db_config.php';

session_start();

if (isset($_SESSION['last_service_request_time']) && (time() - $_SESSION['last_service_request_time']) >= 10800) {
    $vendor_id = $_SESSION['vendor_id'];
    $stmt = $conn->prepare("UPDATE vendors SET auth_token = NULL, auth_token_time = NULL WHERE vendor_id = ?");
    $stmt->bind_param("s", $vendor_id);
    $stmt->execute();
    
    if (session_status() == PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }

    echo json_encode(array("status" => "error", "message" => "You were inactive for 3 hours. Please login again."));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($_SERVER["CONTENT_TYPE"] == "application/x-www-form-urlencoded") {
        $data = $_POST;
    } else {
        $data = json_decode(file_get_contents("php://input"), true);
    }
    
    if (!isset($data['auth_token'])) {
        error_log("Auth token is missing in the request.");
        echo json_encode(array("status" => "error", "message" => "Authentication token is missing"));
        exit();
    }
    
    if ($_SESSION['auth_token'] !== $data['auth_token']) {
        error_log("Auth token mismatch. Session token: " . $_SESSION['auth_token'] . ", Request token: " . $data['auth_token']);
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, new product could not be added."));
        exit();
    }
    
    $product_name = mysqli_real_escape_string($conn, $data['product_name']);
    $price = mysqli_real_escape_string($conn, $data['price']);
    $vendor_id = mysqli_real_escape_string($conn, $data['vendor_id']);
    $inventory = mysqli_real_escape_string($conn, $data['inventory']);
    $barcode = mysqli_real_escape_string($conn, $data['barcode']);
    $createdAt = date('Y-m-d H:i:s'); 

    $stmt = $conn->prepare("INSERT INTO products (product_name, price, vendor_id, inventory, barcode, createdAt) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdisss", $product_name, $price, $vendor_id, $inventory, $barcode, $createdAt);

    if ($stmt->execute() === TRUE) {
        $last_id = $stmt->insert_id;
        echo json_encode(array("status" => "success", "message" => "New product record created successfully", "product_id" => $last_id));

        $_SESSION['last_service_request_time'] = time();
    } else {
        if ($conn->errno == 1062) {
            $error = $conn->error;
            if (strpos($error, 'product_name') !== false) {
                echo json_encode(array("status" => "error", "message" => "Product with the same name already exists for this vendor"));
            } elseif (strpos($error, 'barcode') !== false) {
                echo json_encode(array("status" => "error", "message" => "Product with the same barcode already exists"));
            } else {
                echo json_encode(array("status" => "error", "message" => $stmt->error));
            }
        } else {
            echo json_encode(array("status" => "error", "message" => $stmt->error));
        }
    }
    $stmt->close();
} else {
    echo json_encode(array("status" => "error", "message" => "Invalid request method"));
}
?>
