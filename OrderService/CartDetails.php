<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

include '../db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $data = json_decode(file_get_contents("php://input"), true);
    $customer_id = mysqli_real_escape_string($conn, $data['customer_id']);
    $vendor_id = mysqli_real_escape_string($conn, $data['vendor_id']);

    
    if (!isset($data['action'])) {
        echo json_encode(array("status" => "error", "message" => "No action provided"));
        exit();
    }

    
    $action = $data['action'];
    switch ($action) {
        case 'add_line_items':
            
            if (!isset($data['line_items']) || empty($data['line_items'])) {
                echo json_encode(array("status" => "error", "message" => "No line items provided"));
                exit();
            }

            
            foreach ($data['line_items'] as $line_item) {
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
                        echo json_encode(array("status" => "error", "message" => "Not enough inventory for product ID: $product_id"));
                        exit();
                    }

                    
                    $stmt = $conn->prepare("INSERT INTO line_items (product_id, quantity, vendor_id) VALUES (?, ?, ?)");
                    $stmt->bind_param("iii", $product_id, $quantity, $vendor_id);
                    if (!$stmt->execute()) {
                        echo json_encode(array("status" => "error", "message" => $stmt->error));
                        exit();
                    }
                } else {
                    echo json_encode(array("status" => "error", "message" => "Product not found for ID: $product_id"));
                    exit();
                }
            }
            echo json_encode(array("status" => "success", "message" => "Line items added successfully"));
            break;
        
        case 'generate_order':
            
            $stmt = $conn->prepare("INSERT INTO orders (customer_id, vendor_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $customer_id, $vendor_id);
            if ($stmt->execute()) {
                $order_id = $stmt->insert_id;

                
                $stmt = $conn->prepare("UPDATE line_items SET order_id = ? WHERE vendor_id = ? AND order_id IS NULL");
                $stmt->bind_param("ii", $order_id, $vendor_id);
                $stmt->execute();

                
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
                    echo json_encode(array("status" => "error", "message" => $stmt->error));
                    exit();
                }

                
                echo json_encode(array("status" => "success", "message" => "Order created successfully", "order_id" => $order_id, "total_value" => $total_value, "total_products" => $total_products, "total_units" => $total_units, "tax_value" => $tax_value));
            } else {
                echo json_encode(array("status" => "error", "message" => $stmt->error));
                exit();
            }
            break;

        default:
            echo json_encode(array("status" => "error", "message" => "Invalid action provided"));
            break;
    }
}
?>

<?php
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Methods: POST");
// header("Access-Control-Allow-Headers: Content-Type");

// include '../db_config.php';

// if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
//     $data = json_decode(file_get_contents("php://input"), true);
//     $customer_id = mysqli_real_escape_string($conn, $data['customer_id']);
//     $vendor_id = mysqli_real_escape_string($conn, $data['vendor_id']);

    
//     if (!isset($data['line_items']) || empty($data['line_items'])) {
//         echo json_encode(array("status" => "error", "message" => "No line items provided"));
//         exit();
//     }

    
//     mysqli_autocommit($conn, false);

    
//     $order_id = null;
//     foreach ($data['line_items'] as $line_item) {
//         $product_id = mysqli_real_escape_string($conn, $line_item['product_id']);
//         $quantity = mysqli_real_escape_string($conn, $line_item['quantity']);

        
//         $stmt = $conn->prepare("SELECT price, inventory FROM products WHERE product_id = ? AND vendor_id = ?");
//         $stmt->bind_param("ii", $product_id, $vendor_id);
//         $stmt->execute();
//         $result = $stmt->get_result();
//         if ($result->num_rows > 0) {
//             $product = $result->fetch_assoc();
//             $product_price = $product['price'];
//             $inventory = $product['inventory'];

//             if ($inventory < $quantity) {
//                 echo json_encode(array("status" => "error", "message" => "Not enough inventory for product ID: $product_id"));
//                 mysqli_rollback($conn);
//                 exit();
//             }

        
//             $stmt = $conn->prepare("INSERT INTO line_items (product_id, quantity, vendor_id) VALUES (?, ?, ?)");
//             $stmt->bind_param("iii", $product_id, $quantity, $vendor_id);
//             if (!$stmt->execute()) {
//                 echo json_encode(array("status" => "error", "message" => $stmt->error));
//                 mysqli_rollback($conn);
//                 exit();
//             }
//         } else {
//             echo json_encode(array("status" => "error", "message" => "Product not found for ID: $product_id"));
//             mysqli_rollback($conn);
//             exit();
//         }
//     }


//     $stmt = $conn->prepare("INSERT INTO orders (customer_id, vendor_id) VALUES (?, ?)");
//     $stmt->bind_param("ii", $customer_id, $vendor_id);
//     if ($stmt->execute()) {
//         $order_id = $stmt->insert_id;

        
//         $stmt = $conn->prepare("UPDATE line_items SET order_id = ? WHERE vendor_id = ? AND order_id IS NULL");
//         $stmt->bind_param("ii", $order_id, $vendor_id);
//         if (!$stmt->execute()) {
//             echo json_encode(array("status" => "error", "message" => $stmt->error));
//             mysqli_rollback($conn);
//             exit();
//         }

        
//         $stmt = $conn->prepare("SELECT SUM(products.price * line_items.quantity) AS total_value, 
//                                         COUNT(DISTINCT line_items.product_id) AS total_products,
//                                         SUM(line_items.quantity) AS total_units
//                                 FROM line_items 
//                                 JOIN products ON line_items.product_id = products.product_id
//                                 WHERE line_items.order_id = ?");
//         $stmt->bind_param("i", $order_id);
//         $stmt->execute();
//         $result = $stmt->get_result();
//         $order_details = $result->fetch_assoc();
//         $total_value = $order_details['total_value'];
//         $total_products = $order_details['total_products'];
//         $total_units = $order_details['total_units'];
//         $tax_rate = 0.05; 
//         $tax_value = $total_value * $tax_rate;

//         $stmt = $conn->prepare("UPDATE orders SET total_value = ?, total_products = ?, total_units = ?, tax_value = ? WHERE order_id = ?");
//         $stmt->bind_param("diidi", $total_value, $total_products, $total_units, $tax_value, $order_id); 
//         if (!$stmt->execute()) {
//             echo json_encode(array("status" => "error", "message" => $stmt->error));
//             mysqli_rollback($conn);
//             exit();
//         }

        
//         mysqli_commit($conn);

       
//         echo json_encode(array("status" => "success", "message" => "Order created successfully", "order_id" => $order_id, "total_value" => $total_value, "total_products" => $total_products, "total_units" => $total_units, "tax_value" => $tax_value));
//     } else {
//         echo json_encode(array("status" => "error", "message" => $stmt->error));
//         mysqli_rollback($conn);
//         exit();
//     }
// }
?>
