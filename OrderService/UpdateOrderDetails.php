// UpdateOrderDetails.php
<?php
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Methods: GET, POST");
// header("Access-Control-Allow-Headers: Content-Type");

// include '../db_config.php';

// if ($_SERVER["REQUEST_METHOD"] == "POST") {
   
//     $order_id = mysqli_real_escape_string($conn, $_POST['order_id']); 

//     $stmt = $conn->prepare("SELECT SUM(products.price * line_items.quantity) AS total_value, 
//                                     COUNT(DISTINCT line_items.product_id) AS total_products,
//                                     SUM(line_items.quantity) AS total_units
//                             FROM line_items 
//                             JOIN products ON line_items.product_id = products.product_id
//                             WHERE line_items.order_id = ?");
//     $stmt->bind_param("i", $order_id);
//     $stmt->execute();
//     $result = $stmt->get_result();
//     $order_details = $result->fetch_assoc();
//     $total_value = $order_details['total_value'];
//     $total_products = $order_details['total_products'];
//     $total_units = $order_details['total_units'];
//     $tax_rate = 0.05; 
//     $tax_value = $total_value * $tax_rate;

//     $stmt = $conn->prepare("UPDATE orders SET total_value = ?, total_products = ?, total_units = ?, tax_value = ? WHERE order_id = ?");
//     $stmt->bind_param("diidi", $total_value, $total_products, $total_units, $tax_value, $order_id); 

//     if ($stmt->execute() === TRUE) {
//         echo json_encode(array("status" => "success", "message" => "Order updated successfully", "order_id" => $order_id, "total_value" => $total_value, "total_products" => $total_products, "total_units" => $total_units, "tax_value" => $tax_value));
//     } else {
//         echo json_encode(array("status" => "error", "message" => $stmt->error));
//     }
//     $stmt->close();
// }
?>
