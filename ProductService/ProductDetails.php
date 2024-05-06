<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
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
        $data = json_decode(file_get_contents("php://input"), true);
    
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
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, new product could not be added."));
        exit();
    }
    
    $vendor_id = $row['vendor_id'];
    
    $product_name = mysqli_real_escape_string($conn, $data['product_name']);
    $price = mysqli_real_escape_string($conn, $data['price']);
    $inventory = mysqli_real_escape_string($conn, $data['inventory']);
    $barcode = mysqli_real_escape_string($conn, $data['barcode']);
    $createdAt = date('Y-m-d H:i:s'); 

    if (!is_string($product_name)) {
        echo json_encode(array("status" => "error", "message" => "Invalid type for product_name. Expected a string."));
        exit();
    }
    if (!is_numeric($price)) {
        echo json_encode(array("status" => "error", "message" => "Invalid type for price. Expected a number."));
        exit();
    }
    if (!is_numeric($inventory)) {
        echo json_encode(array("status" => "error", "message" => "Invalid type for inventory. Expected a number."));
        exit();
    }
    if (!is_string($barcode)) {
        echo json_encode(array("status" => "error", "message" => "Invalid type for barcode. Expected a string."));
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO products (product_name, price, vendor_id, inventory, barcode, createdAt) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdisss", $product_name, $price, $vendor_id, $inventory, $barcode, $createdAt);

    if ($stmt->execute() === TRUE) {
        $last_id = $stmt->insert_id;
        echo json_encode(array("status" => "success", "message" => "New product record created successfully", "product_id" => $last_id));
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
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, product details could not be fetched."));
        exit();
    }

    $vendor_id = $row['vendor_id'];

    if (isset($_GET['product_id'])) {
        
        $product_id = $_GET['product_id'];

        $stmt = $conn->prepare("SELECT product_id AS id, product_name, price, inventory, barcode FROM products WHERE product_id = ? AND vendor_id = ?");
        $stmt->bind_param("ss", $product_id, $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $product = $result->fetch_assoc();

        if ($product) {
            echo json_encode(array("status" => "success", "message" => "Product details fetched successfully", "product" => $product));
        } else {
            echo json_encode(array("status" => "error", "message" => "Product not found"));
        }
    } else {
        
        $stmt = $conn->prepare("SELECT product_id AS id, product_name, price, barcode FROM products WHERE vendor_id = ? AND deleted = FALSE");
        $stmt->bind_param("s", $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $products = $result->fetch_all(MYSQLI_ASSOC);

        echo json_encode(array("status" => "success", "message" => "Product details fetched successfully", "products" => $products));
    }
}


elseif ($_SERVER["REQUEST_METHOD"] == "PUT") {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['auth_token']) || !isset($data['product_id']) || !isset($data['fields'])) {
        echo json_encode(array("status" => "error", "message" => "Authentication token, product_id, and fields are required"));
        exit();
    }
    
    $stmt = $conn->prepare("SELECT vendor_id FROM vendors WHERE auth_token = ?");
    $stmt->bind_param("s", $data['auth_token']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, product could not be updated."));
        exit();
    }
    
    $vendor_id = $row['vendor_id'];
    
    $fields = $data['fields'];
    $allowed_fields = array("product_name", "price", "inventory", "barcode"); 
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

    $query = "UPDATE products SET " . $update_fields . " WHERE product_id = ? AND vendor_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $data['product_id'], $vendor_id);

    if ($stmt->execute() === TRUE) {
        echo json_encode(array("status" => "success", "message" => "Product details updated successfully"));
    } else {
        echo json_encode(array("status" => "error", "message" => $stmt->error));
    }
    $stmt->close();
}
elseif ($_SERVER["REQUEST_METHOD"] == "DELETE") {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['auth_token']) || !isset($data['product_id'])) {
        echo json_encode(array("status" => "error", "message" => "Authentication token and product_id are required"));
        exit();
    }
    
    $stmt = $conn->prepare("SELECT * FROM vendors WHERE auth_token = ?");
    $stmt->bind_param("s", $data['auth_token']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row) {
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, product could not be deleted."));
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE products SET deleted = TRUE WHERE product_id = ?");
    $stmt->bind_param("i", $data['product_id']);

    if ($stmt->execute() === TRUE) {
        echo json_encode(array("status" => "success", "message" => "Product deleted successfully"));
    } else {
        echo json_encode(array("status" => "error", "message" => $stmt->error));
    }
    $stmt->close();
}

 else {
    echo json_encode(array("status" => "error", "message" => "Invalid request method"));
}
?>
