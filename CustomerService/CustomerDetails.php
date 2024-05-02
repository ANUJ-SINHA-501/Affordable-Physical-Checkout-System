<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

include '../db_config.php';

session_start();


if (isset($_SESSION['last_service_request_time']) && (time() - $_SESSION['last_service_request_time']) >= 10800) {

    // Logout the user
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
    
    if (!isset($_POST['auth_token']) || $_POST['auth_token'] !== $_SESSION['auth_token']) {
        http_response_code(403);
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, new customer details could not be added."));
        exit();
    }

    
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $vendor_id = mysqli_real_escape_string($conn, $_POST['vendor_id']); 

   
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(array("status" => "error", "message" => "Invalid email format"));
        exit();
    }

    if (!preg_match('/^\d{10}$/', $phone)) {
        echo json_encode(array("status" => "error", "message" => "Invalid phone number format"));
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO customers (name, phone, email, address, vendor_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $name, $phone, $email, $address, $vendor_id);

    if ($stmt->execute() === TRUE) {
        $customer_id = $stmt->insert_id;
        $_SESSION['last_service_request_time'] = time(); 
        echo json_encode(array("status" => "success", "message" => "New record created successfully", "customer_id" => $customer_id));
    } else {
        if ($conn->errno == 1062) {
            echo json_encode(array("status" => "error", "message" => "Email or phone number already exists"));
        } else {
            echo json_encode(array("status" => "error", "message" => $stmt->error));
        }
    }
    $stmt->close();
} elseif ($_SERVER["REQUEST_METHOD"] == "GET") {
    
    if (!isset($_GET['auth_token']) || $_GET['auth_token'] !== $_SESSION['auth_token']) {
        http_response_code(403);
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed"));
        exit();
    }

    
    $phone = mysqli_real_escape_string($conn, $_GET['phone']);
    $vendor_id = mysqli_real_escape_string($conn, $_GET['vendor_id']); 

    $stmt = $conn->prepare("SELECT * FROM customers WHERE phone = ? AND vendor_id = ?");
    $stmt->bind_param("si", $phone, $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();

    if ($customer) {
        $_SESSION['last_service_request_time'] = time(); 
        echo json_encode(array("status" => "success", "customer" => $customer));
    } else {
        echo json_encode(array("status" => "error", "message" => "No customer found with this phone number and vendor_id"));
    }
    $stmt->close();
} elseif ($_SERVER["REQUEST_METHOD"] == "DELETE") {
    
    if (!isset($_POST['auth_token']) || $_POST['auth_token'] !== $_SESSION['auth_token']) {
        http_response_code(403);
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed"));
        exit();
    }

    
    $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
    $vendor_id = mysqli_real_escape_string($conn, $_POST['vendor_id']); 

    $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ? AND vendor_id = ?");
    $stmt->bind_param("ii", $customer_id, $vendor_id);

    if ($stmt->execute() === TRUE) {
        $_SESSION['last_service_request_time'] = time(); 
        echo json_encode(array("status" => "success", "message" => "Record deleted successfully"));
    } else {
        echo json_encode(array("status" => "error", "message" => $stmt->error));
    }
    $stmt->close();
}
?>
