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
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, product could not be added."));
        exit();
    }

    // Proceed with adding product details
    $product_name = mysqli_real_escape_string($conn, $_POST['product_name']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $vendor_id = mysqli_real_escape_string($conn, $_POST['vendor_id']);
    $inventory = mysqli_real_escape_string($conn, $_POST['inventory']);
    $barcode = mysqli_real_escape_string($conn, $_POST['barcode']);
    

    $stmt = $conn->prepare("INSERT INTO products (product_name, price, vendor_id, inventory, barcode) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sdiss", $product_name, $price, $vendor_id, $inventory, $barcode);

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
}
?>
