<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

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

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['all_customers'])) {
    if (!isset($_GET['auth_token'])) {
        error_log("Auth token is missing in the request.");
        echo json_encode(array("status" => "error", "message" => "Authentication token is missing"));
        exit();
    }
    
    $stmt = $conn->prepare("SELECT vendor_id FROM vendors WHERE auth_token = ?");
    $stmt->bind_param("s", $_GET['auth_token']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        error_log("Auth token mismatch. Request token: " . $_GET['auth_token']);
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, customer details could not be fetched."));
        exit();
    }
    
    $vendor_id = $row['vendor_id'];
    
    $stmt = $conn->prepare("SELECT name, phone FROM customers WHERE vendor_id = ?");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $customers = $result->fetch_all(MYSQLI_ASSOC);

    if ($customers) {
        $_SESSION['last_service_request_time'] = time(); 
        echo json_encode(array("status" => "success", "customers" => $customers));
    } else {
        echo json_encode(array("status" => "error", "message" => "No customers found for this vendor_id"));
    }
    $stmt->close();
}
elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $content_type = isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : null;
    if (strpos($content_type, "application/x-www-form-urlencoded") !== false) {
        $data = $_POST;
    } elseif (strpos($content_type, "application/json") !== false) {
        $data = json_decode(file_get_contents("php://input"), true);
    } else {
        echo json_encode(array("status" => "error", "message" => "Invalid content type"));
        exit();
    }
    
    if (!isset($data['auth_token'])) {
        error_log("Auth token is missing in the request.");
        echo json_encode(array("status" => "error", "message" => "Authentication token is missing"));
        exit();
    }
    
    $stmt = $conn->prepare("SELECT vendor_id FROM vendors WHERE auth_token = ?");
    $stmt->bind_param("s", $data['auth_token']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        error_log("Auth token mismatch. Request token: " . $data['auth_token']);
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, new customer details could not be added."));
        exit();
    }
    
    $vendor_id = $row['vendor_id'];
    
    $name = mysqli_real_escape_string($conn, $data['name']);
    $phone = mysqli_real_escape_string($conn, $data['phone']);
    $email = mysqli_real_escape_string($conn, $data['email']);
    $address = mysqli_real_escape_string($conn, $data['address']);

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
    if (!isset($_GET['auth_token'])) {
        error_log("Auth token is missing in the request.");
        echo json_encode(array("status" => "error", "message" => "Authentication token is missing"));
        exit();
    }
    
    $stmt = $conn->prepare("SELECT vendor_id FROM vendors WHERE auth_token = ?");
    $stmt->bind_param("s", $_GET['auth_token']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        error_log("Auth token mismatch. Request token: " . $_GET['auth_token']);
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, customer details could not be fetched."));
        exit();
    }
    
    $vendor_id = $row['vendor_id'];
    
    $phone = mysqli_real_escape_string($conn, $_GET['phone']);

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
    $content_type = isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : null;
    if (strpos($content_type, "application/x-www-form-urlencoded") !== false) {
        parse_str(file_get_contents("php://input"), $data);
    } elseif (strpos($content_type, "application/json") !== false) {
        $data = json_decode(file_get_contents("php://input"), true);
    } else {
        echo json_encode(array("status" => "error", "message" => "Invalid content type"));
        exit();
    }
    
    if (!isset($data['auth_token'])) {
        error_log("Auth token is missing in the request.");
        echo json_encode(array("status" => "error", "message" => "Authentication token is missing"));
        exit();
    }
    
    $stmt = $conn->prepare("SELECT vendor_id FROM vendors WHERE auth_token = ?");
    $stmt->bind_param("s", $data['auth_token']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        error_log("Auth token mismatch. Request token: " . $data['auth_token']);
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, customer details could not be deleted."));
        exit();
    }
    
    $vendor_id = $row['vendor_id'];
    
    $customer_id = mysqli_real_escape_string($conn, $data['customer_id']);

    $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ? AND vendor_id = ?");
    $stmt->bind_param("ii", $customer_id, $vendor_id);

    if ($stmt->execute() === TRUE) {
        echo json_encode(array("status" => "success", "message" => "Customer record deleted successfully"));
    } else {
        echo json_encode(array("status" => "error", "message" => $stmt->error));
    }
    $stmt->close();
}
elseif ($_SERVER["REQUEST_METHOD"] == "PUT") {
    $content_type = isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : null;
    if (strpos($content_type, "application/x-www-form-urlencoded") !== false) {
        parse_str(file_get_contents("php://input"), $data);
    } elseif (strpos($content_type, "application/json") !== false) {
        $data = json_decode(file_get_contents("php://input"), true);
    } else {
        echo json_encode(array("status" => "error", "message" => "Invalid content type"));
        exit();
    }
    
    if (!isset($data['auth_token']) || !isset($data['customer_id']) || !isset($data['fields'])) {
        echo json_encode(array("status" => "error", "message" => "Authentication token, customer_id, and fields are required"));
        exit();
    }
    
    $stmt = $conn->prepare("SELECT vendor_id FROM vendors WHERE auth_token = ?");
    $stmt->bind_param("s", $data['auth_token']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, customer could not be updated."));
        exit();
    }
    
    $vendor_id = $row['vendor_id'];
    
    $fields = $data['fields'];
    $allowed_fields = array("name", "phone", "email", "address"); 
    $update_fields = "";
    foreach ($fields as $key => $value) {
        if (!in_array($key, $allowed_fields)) {
            echo json_encode(array("status" => "error", "message" => "Invalid field name: " . $key));
            exit();
        }
        if ($update_fields != "") {
            $update_fields .= ", ";
        }
        $update_fields .= $key . " = '" . mysqli_real_escape_string($conn, $value) . "'";
    }

    $query = "UPDATE customers SET " . $update_fields . " WHERE customer_id = ? AND vendor_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $data['customer_id'], $vendor_id);

    if ($stmt->execute() === TRUE) {
        echo json_encode(array("status" => "success", "message" => "Customer updated successfully"));
    } else {
        echo json_encode(array("status" => "error", "message" => $stmt->error));
    }
    $stmt->close();
}

elseif ($_SERVER["REQUEST_METHOD"] == "DELETE") {
    $content_type = isset($_SERVER["CONTENT_TYPE"]) ? $_SERVER["CONTENT_TYPE"] : null;
    if (strpos($content_type, "application/x-www-form-urlencoded") !== false) {
        parse_str(file_get_contents("php://input"), $data);
    } elseif (strpos($content_type, "application/json") !== false) {
        $data = json_decode(file_get_contents("php://input"), true);
    } else {
        echo json_encode(array("status" => "error", "message" => "Invalid content type"));
        exit();
    }
    
    if (!isset($data['auth_token'])) {
        error_log("Auth token is missing in the request.");
        echo json_encode(array("status" => "error", "message" => "Authentication token is missing"));
        exit();
    }
    
    $stmt = $conn->prepare("SELECT vendor_id FROM vendors WHERE auth_token = ?");
    $stmt->bind_param("s", $data['auth_token']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        error_log("Auth token mismatch. Request token: " . $data['auth_token']);
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, customer details could not be deleted."));
        exit();
    }
    
    $vendor_id = $row['vendor_id'];
    
    $customer_id = mysqli_real_escape_string($conn, $data['customer_id']);

    $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ? AND vendor_id = ?");
    $stmt->bind_param("ii", $customer_id, $vendor_id);

    if ($stmt->execute() === TRUE) {
        echo json_encode(array("status" => "success", "message" => "Record deleted successfully"));
    } else {
        echo json_encode(array("status" => "error", "message" => $stmt->error));
    }
    $stmt->close();
}
else {
    echo json_encode(array("status" => "error", "message" => "Invalid request method"));
}


?>
