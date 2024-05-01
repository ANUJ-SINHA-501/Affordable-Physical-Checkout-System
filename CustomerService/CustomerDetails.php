<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

include '../db_config.php';

session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, new customer details could not be added."));
        exit();
    }

    // Proceed with adding customer details
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $vendor_id = mysqli_real_escape_string($conn, $_POST['vendor_id']); 

    // Validation for email and phone number
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
    // CSRF token validation
    if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(array("status" => "error", "message" => "CSRF token validation failed"));
        exit();
    }

    // Proceed with retrieving customer details
    $phone = mysqli_real_escape_string($conn, $_GET['phone']);
    $vendor_id = mysqli_real_escape_string($conn, $_GET['vendor_id']); 

    $stmt = $conn->prepare("SELECT * FROM customers WHERE phone = ? AND vendor_id = ?");
    $stmt->bind_param("si", $phone, $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customer = $result->fetch_assoc();

    if ($customer) {
        echo json_encode(array("status" => "success", "customer" => $customer));
    } else {
        echo json_encode(array("status" => "error", "message" => "No customer found with this phone number and vendor_id"));
    }
    $stmt->close();
} elseif ($_SERVER["REQUEST_METHOD"] == "DELETE") {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(array("status" => "error", "message" => "CSRF token validation failed"));
        exit();
    }

    // Proceed with deleting customer record
    $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
    $vendor_id = mysqli_real_escape_string($conn, $_POST['vendor_id']); 

    $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ? AND vendor_id = ?");
    $stmt->bind_param("ii", $customer_id, $vendor_id);

    if ($stmt->execute() === TRUE) {
        echo json_encode(array("status" => "success", "message" => "Record deleted successfully"));
    } else {
        echo json_encode(array("status" => "error", "message" => $stmt->error));
    }
    $stmt->close();
}
?>
