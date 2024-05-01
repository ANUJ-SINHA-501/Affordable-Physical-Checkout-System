<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

date_default_timezone_set('Asia/Kolkata');

include '../db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Lockout time in seconds
    $lockout_time = 20;
    
    
    session_start();

    // Verify CSRF token
    if (!isset($_SESSION['last_request_time']) || (time() - $_SESSION['last_request_time']) >= $lockout_time) {
        // Update last request time
        $_SESSION['last_request_time'] = time();
    } else {
        // Calculate remaining lockout time
        $remaining_time = $lockout_time - (time() - $_SESSION['last_request_time']);
        echo json_encode(array("status" => "error", "message" => "Please wait $remaining_time seconds before making another service request"));
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);

    // Proceed with order creation
    mysqli_autocommit($conn, false);

    $line_items = isset($data['line_items']) ? $data['line_items'] : [];
    $customer_phone = isset($data['customer_phone']) ? $data['customer_phone'] : null; 

    if (empty($line_items)) {
        mysqli_rollback($conn);
        echo json_encode(array("status" => "error", "message" => "No line items provided"));
        exit();
    }

    $vendor_id = mysqli_real_escape_string($conn, $data['vendor_id']);

    foreach ($line_items as $line_item) {
        $product_id = mysqli_real_escape_string($conn, $line_item['product_id']);
        $quantity = mysqli_real_escape_string($conn, $line_item['quantity']);

        $stmt = $conn->prepare("SELECT price, inventory FROM products WHERE product_id = ? AND vendor_id = ?");
        $stmt->bind_param("ii", $product_id, $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $product = $result->fetch_assoc();
            $product_price = $product['price'];
            $inventory = $product['inventory'];

            if ($inventory < $quantity) {
                mysqli_rollback($conn);
                echo json_encode(array("status" => "error", "message" => "Not enough inventory for product ID: $product_id"));
                exit();
            }

            // Deduct quantity from inventory
            $new_inventory = $inventory - $quantity;
            $stmt = $conn->prepare("UPDATE products SET inventory = ? WHERE product_id = ?");
            $stmt->bind_param("ii", $new_inventory, $product_id);
            if (!$stmt->execute()) {
                mysqli_rollback($conn);
                echo json_encode(array("status" => "error", "message" => $stmt->error));
                exit();
            }

            
            $stmt = $conn->prepare("INSERT INTO line_items (product_id, quantity, vendor_id) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $product_id, $quantity, $vendor_id);
            if (!$stmt->execute()) {
                mysqli_rollback($conn);
                echo json_encode(array("status" => "error", "message" => $stmt->error));
                exit();
            }
        } else {
            mysqli_rollback($conn);
            echo json_encode(array("status" => "error", "message" => "Product not found for ID: $product_id"));
            exit();
        }
    }

    
    $customer_id = null;

    if ($customer_phone) {
        $stmt = $conn->prepare("SELECT customer_id FROM customers WHERE phone = ? AND vendor_id = ?");
        $stmt->bind_param("si", $customer_phone, $vendor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $customer = $result->fetch_assoc();

        if ($customer) {
            $customer_id = $customer['customer_id'];
        } else {
            echo json_encode(array("status" => "error", "message" => "No customer found with this phone number and vendor_id"));
            exit();
        }
    }

    
    $stmt = $conn->prepare("INSERT INTO orders (customer_id, vendor_id, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $customer_id, $vendor_id);
    if ($stmt->execute()) {
        $order_id = $stmt->insert_id;

        
        $stmt = $conn->prepare("UPDATE line_items SET order_id = ? WHERE vendor_id = ? AND order_id IS NULL");
        $stmt->bind_param("ii", $order_id, $vendor_id);
        if (!$stmt->execute()) {
            mysqli_rollback($conn);
            echo json_encode(array("status" => "error", "message" => $stmt->error));
            exit();
        }

        
        $stmt = $conn->prepare("SELECT SUM(products.price * line_items.quantity) AS total_value, 
                                        COUNT(DISTINCT line_items.product_id) AS total_products,
                                        SUM(line_items.quantity) AS total_units
                                FROM line_items 
                                JOIN products ON line_items.product_id = products.product_id
                                WHERE line_items.order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order_details = $result->fetch_assoc();
        $total_value = $order_details['total_value'];
        $total_products = $order_details['total_products'];
        $total_units = $order_details['total_units'];
        $tax_rate = 0.05; 
        $tax_value = $total_value * $tax_rate;

        
        $stmt = $conn->prepare("UPDATE orders SET total_value = ?, total_products = ?, total_units = ?, tax_value = ? WHERE order_id = ?");
        $stmt->bind_param("diidi", $total_value, $total_products, $total_units, $tax_value, $order_id); 
        if (!$stmt->execute()) {
            mysqli_rollback($conn);
            echo json_encode(array("status" => "error", "message" => $stmt->error));
            exit();
        }

        
        mysqli_commit($conn);

       
        echo json_encode(array("status" => "success", "message" => "Order created successfully", "order_id" => $order_id, "total_value" => $total_value, "total_products" => $total_products, "total_units" => $total_units, "tax_value" => $tax_value, "created_at" => date('d-m-Y H:i:s')));
    } else {
        mysqli_rollback($conn);
        echo json_encode(array("status" => "error", "message" => $stmt->error));
        exit();
    }
} else {
    echo json_encode(array("status" => "error", "message" => "Invalid request method"));
}
?>
