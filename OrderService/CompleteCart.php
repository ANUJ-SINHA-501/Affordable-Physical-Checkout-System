<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

date_default_timezone_set('Asia/Kolkata');

include '../db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

    // Lockout time in seconds
    $lockout_time = 20;
    
    
    if (!isset($_SESSION['last_request_time']) || (time() - $_SESSION['last_request_time']) >= $lockout_time) {
        $_SESSION['last_request_time'] = time();
    } else {
        
        $remaining_time = $lockout_time - (time() - $_SESSION['last_request_time']);
        echo json_encode(array("status" => "error", "message" => "Please wait $remaining_time seconds before making another service request"));
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);

    
    if (!isset($data['auth_token']) || $_SESSION['auth_token'] !== $data['auth_token']) {
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed"));
        exit();
    }

    
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
            echo json_encode(array("status" => "error", "message" => "Product not found for ID: $product_id or the product is not associated with this vendor"));
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
        $total_payable = $total_value + $tax_value;

        $stmt = $conn->prepare("UPDATE orders SET total_value = ?, total_products = ?, total_units = ?, tax_value = ?, total_payable = ? WHERE order_id = ?");
        $stmt->bind_param("diiddi", $total_value, $total_products, $total_units, $tax_value, $total_payable, $order_id); 
        if (!$stmt->execute()) {
            mysqli_rollback($conn);
            echo json_encode(array("status" => "error", "message" => $stmt->error));
            exit();
        }
        
        mysqli_commit($conn);

        $_SESSION['last_service_request_time'] = time();

        echo json_encode(array("status" => "success", "message" => "Order created successfully", "order_id" => $order_id, "total_value" => $total_value, "total_products" => $total_products, "total_units" => $total_units, "tax_value" => $tax_value, "total_payable" => $total_payable, "created_at" => date('d-m-Y H:i:s')));
    } else {
        mysqli_rollback($conn);
        echo json_encode(array("status" => "error", "message" => $stmt->error));
        exit();
    }
}
if ($_SERVER["REQUEST_METHOD"] == "GET") {
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
        echo json_encode(array("status" => "error", "message" => "Authentication token validation failed. Hence, order details could not be fetched."));
        exit();
    }
    
    $vendor_id = $row['vendor_id'];
    
    $stmt = $conn->prepare("SELECT orders.order_id, orders.created_at, orders.total_payable, customers.name AS CustomerName, customers.phone AS CustomerPhone
                            FROM orders 
                            LEFT JOIN customers ON orders.customer_id = customers.customer_id
                            WHERE orders.vendor_id = ?");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    
    if (count($orders) == 0) {
        echo json_encode(array("status" => "error", "message" => "No orders present"));
        exit();
    }

    $numbered_orders = array();
    foreach ($orders as $index => $order) {
        $order_id = $order['order_id'];
        $stmt = $conn->prepare("SELECT products.product_name, line_items.quantity
                                FROM line_items 
                                JOIN products ON line_items.product_id = products.product_id
                                WHERE line_items.order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $products = $result->fetch_all(MYSQLI_ASSOC);

        $numbered_products = array();
        foreach ($products as $product_index => $product) {
            $numbered_products[] = array_merge(array("product_number" => $product_index + 1), $product);
        }

        $numbered_orders[] = array_merge(array("order_number" => $index + 1), $order, array("products" => $numbered_products));
    }
    
    echo json_encode(array("status" => "success", "message" => "Order details fetched successfully", "orders" => $numbered_orders));
}

else {
    echo json_encode(array("status" => "error", "message" => "Invalid request method"));
}

?>
