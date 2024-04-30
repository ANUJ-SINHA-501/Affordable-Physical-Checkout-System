// LineItems.php
<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

include '../db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = mysqli_real_escape_string($conn, $_POST['product_id']);
    $order_id = mysqli_real_escape_string($conn, $_POST['order_id']);
    $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
    $vendor_id = mysqli_real_escape_string($conn, $_POST['vendor_id']); 

    $stmt = $conn->prepare("SELECT price, inventory FROM products WHERE product_id = ? AND vendor_id = ?");
    $stmt->bind_param("ii", $product_id, $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $product_price = $product['price'];
    $inventory = $product['inventory'];

    if ($inventory < $quantity) {
        echo json_encode(array("status" => "error", "message" => "Not enough inventory for this product"));
        exit();
    }

    $total_value = $product_price * $quantity;

    $stmt = $conn->prepare("INSERT INTO line_items (product_id, order_id, quantity, vendor_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiii", $product_id, $order_id, $quantity, $vendor_id);

    if ($stmt->execute() === TRUE) {
        $stmt = $conn->prepare("UPDATE orders SET total_products = total_products + 1, total_units = total_units + ?, total_value = total_value + ? WHERE order_id = ?");
        $stmt->bind_param("idi", $quantity, $total_value, $order_id);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE products SET inventory = inventory - ? WHERE product_id = ? AND vendor_id = ?");
        $stmt->bind_param("iii", $quantity, $product_id, $vendor_id);
        $stmt->execute();

        echo json_encode(array("status" => "success", "message" => "Line item added successfully"));
    } else {
        echo json_encode(array("status" => "error", "message" => $stmt->error));
    }
    $stmt->close();
}
?>
