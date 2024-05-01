// OrderDetails.php
<?php
// header("Access-Control-Allow-Origin: *");
// header("Access-Control-Allow-Methods: GET, POST");
// header("Access-Control-Allow-Headers: Content-Type");

// include '../db_config.php';

// if ($_SERVER["REQUEST_METHOD"] == "POST") {
   
//     $customer_id = mysqli_real_escape_string($conn, $_POST['customer_id']);
//     $vendor_id = mysqli_real_escape_string($conn, $_POST['vendor_id']); 

//     $stmt = $conn->prepare("INSERT INTO orders (customer_id, vendor_id) VALUES (?, ?)");
//     $stmt->bind_param("ii", $customer_id, $vendor_id); 

//     if ($stmt->execute() === TRUE) {
//         $order_id = $stmt->insert_id;
//         echo json_encode(array("status" => "success", "message" => "Order created successfully", "order_id" => $order_id));
//     } else {
//         echo json_encode(array("status" => "error", "message" => $stmt->error));
//     }
//     $stmt->close();
// }
?>
